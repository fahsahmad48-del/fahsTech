<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Perfume;
use App\Models\Product;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminJsonActionsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Admin
    {
        $admin = Admin::create(['password' => 'original-password']);
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect();

        return $admin;
    }

    public function test_phone_and_device_multipart_actions_return_json_and_refresh_filtered_rows(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Electronics']);
        foreach (['phone' => '/admin/products', 'laptop' => '/admin/products/devices'] as $type => $url) {
            $data = ['name' => 'New '.$type, 'brand' => 'Brand', 'category_id' => $category->id,
                'price' => '99.50', 'product_type' => $type];
            $this->withHeader('Accept', 'application/json')->post($url, $data + [
                'image' => UploadedFile::fake()->image('item.png'),
                'filter_page' => 1, 'filter_category' => $category->id,
            ])->assertCreated()->assertJsonStructure(['message'])->assertHeaderMissing('Location');
            $product = Product::latest('id')->firstOrFail();
            $this->post('/admin/products/'.$product->id, array_replace($data, [
                '_method' => 'PUT', 'name' => 'Updated '.$type,
            ]))->assertOk()->assertJsonStructure(['message']);
            $list = $this->getJson('/admin/products/filter?category='.$category->id)->assertOk()->assertJsonPath('count', 1);
            $this->assertStringContainsString('Updated '.$type, $list->json('html'));
            $this->assertStringContainsString('edit-product', $list->json('html'));
            $this->deleteJson('/admin/products/'.$product->id)->assertOk()->assertJsonPath('message', 'Product deleted successfully.');
            $this->getJson('/admin/products/filter?category='.$category->id.'&page=2')->assertOk()
                ->assertJsonPath('count', 0)->assertJsonPath('page', 1);
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_perfume_multipart_actions_return_json_and_refresh_rows(): void
    {
        Storage::fake('public');
        $this->signIn();
        $data = ['name' => 'New perfume', 'brand' => 'Brand', 'category' => 'men', 'price' => '40.00'];
        $this->withHeader('Accept', 'application/json')->post('/admin/perfumes', $data + [
            'image' => UploadedFile::fake()->image('scent.png'),
        ])->assertCreated()->assertJsonPath('message', 'Perfume added successfully.')->assertHeaderMissing('Location');
        $perfume = Perfume::firstOrFail();
        $this->post('/admin/perfumes/'.$perfume->id, array_replace($data, [
            '_method' => 'PUT', 'name' => 'Updated perfume',
        ]))->assertOk()->assertJsonPath('message', 'Perfume updated successfully.');
        $list = $this->getJson('/admin/perfumes/filter?category=men')->assertOk()->assertJsonPath('count', 1);
        $this->assertStringContainsString('Updated perfume', $list->json('html'));
        $this->assertStringContainsString('edit-perfume', $list->json('html'));
        $this->deleteJson('/admin/perfumes/'.$perfume->id)->assertOk()->assertJsonPath('message', 'Perfume deleted successfully.');
        $this->getJson('/admin/perfumes/filter?category=men&page=2')->assertOk()->assertJsonPath('count', 0)->assertJsonPath('page', 1);
    }

    public function test_category_json_actions_and_blocked_deletion(): void
    {
        $this->signIn();
        $this->postJson('/admin/categories', ['name' => 'Phones'])->assertCreated();
        $category = Category::firstOrFail();
        $this->postJson('/admin/categories', ['name' => 'phones'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->withHeader('Accept', 'application/json')->post('/admin/categories/'.$category->id, [
            '_method' => 'PUT', 'name' => 'Devices',
        ])->assertOk()->assertJsonPath('message', 'Category updated successfully.');
        $list = $this->getJson('/admin/categories')->assertOk();
        $this->assertStringContainsString('Devices', $list->json('html'));
        $product = Product::create(['name' => 'Phone', 'brand' => 'Brand', 'category_id' => $category->id, 'price' => 1]);
        $this->deleteJson('/admin/categories/'.$category->id)->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->assertModelExists($category);
        $product->delete();
        $this->deleteJson('/admin/categories/'.$category->id)->assertOk()->assertJsonPath('message', 'Category deleted successfully.');
        $this->assertStringContainsString('No categories added yet.', $this->getJson('/admin/categories')->json('html'));
    }

    public function test_json_validation_includes_nested_specification_fields(): void
    {
        $this->signIn();
        $this->postJson('/admin/products', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'image']);
        $this->postJson('/admin/products/devices', [])->assertUnprocessable()->assertJsonValidationErrors(['product_type', 'image']);
        $perfume = Perfume::create(['name' => 'Scent', 'brand' => 'Brand', 'category' => 'women', 'price' => 1, 'image_path' => 'perfumes/test.webp']);
        $this->putJson('/admin/perfumes/'.$perfume->id, [
            'name' => 'Scent', 'brand' => 'Brand', 'category' => 'women', 'price' => 1,
            'specs' => [['name' => 'Size', 'value' => '']],
        ])->assertUnprocessable()->assertJsonValidationErrors('specs.0.value');
        $this->putJson('/admin/settings', ['store_name' => ''])->assertUnprocessable()->assertJsonValidationErrors('store_name');
    }

    public function test_password_json_rotates_session_and_csrf_and_allows_another_save_and_logout(): void
    {
        $admin = $this->signIn();
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests() { return false; }
        });
        $oldToken = session()->token();
        $oldId = session()->getId();
        $oldFingerprint = session('admin_fingerprint');
        $password = ['_method' => 'PUT', '_token' => $oldToken, 'current_password' => 'wrong',
            'new_password' => 'replacement-password', 'new_password_confirmation' => 'replacement-password'];
        $this->withHeader('Accept', 'application/json')->post('/admin/settings/password', $password)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $response = $this->post('/admin/settings/password', array_replace($password, ['current_password' => 'original-password']))
            ->assertOk()->assertJsonPath('message', 'Password changed successfully.')->assertHeaderMissing('Location');
        $token = $response->json('csrf_token');
        $this->assertNotSame($oldToken, $token);
        $this->assertSame(session()->token(), $token);
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldFingerprint, session('admin_fingerprint'));
        $this->assertTrue(Hash::check('replacement-password', $admin->fresh()->password));
        $this->post('/admin/settings', ['_method' => 'PUT', '_token' => $oldToken, 'store_name' => 'Stale'])->assertStatus(419);
        $this->post('/admin/settings', ['_method' => 'PUT', '_token' => $token, 'store_name' => 'Updated Store'])
            ->assertOk()->assertJsonPath('message', 'Settings updated successfully.');
        $this->assertDatabaseHas('settings', ['store_name' => 'Updated Store']);
        $this->post('/admin/logout', ['_token' => $token])->assertRedirect('/admin/login');
    }
}
