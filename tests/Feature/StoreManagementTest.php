<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Admin
    {
        $admin = Admin::create(['password' => 'original-password']);
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect('/admin/products');

        return $admin;
    }

    private function productData(Category $category, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Galaxy Test', 'brand' => 'Samsung', 'category_id' => $category->id,
            'price' => '599.99', 'ram' => '8GB, 12GB, 8GB, ', 'storage' => '128GB, 256GB',
            'colors' => 'Black, Blue', 'screen' => 'OLED', 'processor' => 'Test chip',
            'battery' => '5000 mAh', 'camera' => '50 MP',
        ], $overrides);
    }

    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create(array_replace([
            'name' => 'Test phone', 'brand' => 'Samsung', 'category_id' => $category->id, 'price' => 100,
        ], $overrides));
    }

    public function test_admin_routes_require_a_valid_admin_session(): void
    {
        $category = Category::create(['name' => 'Phones']);
        $product = $this->product($category);
        foreach ([
            ['GET', '/admin/products'], ['POST', '/admin/products'], ['PUT', "/admin/products/{$product->id}"],
            ['DELETE', "/admin/products/{$product->id}"], ['GET', '/admin/categories'], ['POST', '/admin/categories'],
            ['PUT', "/admin/categories/{$category->id}"], ['DELETE', "/admin/categories/{$category->id}"],
            ['GET', '/admin/settings'], ['PUT', '/admin/settings'], ['PUT', '/admin/settings/password'], ['POST', '/admin/logout'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect('/admin/login');
        }
        $this->withSession(['admin_authenticated' => true])->get('/admin/products')->assertRedirect('/admin/login');
        $this->getJson('/admin/products')->assertUnauthorized();
    }

    public function test_login_is_throttled_and_recovers_after_the_window(): void
    {
        Admin::create(['password' => 'original-password']);
        for ($i = 0; $i < 5; $i++) {
            $this->from('/admin/login')->post('/admin/login', ['password' => 'wrong'])->assertSessionHasErrors('password');
        }
        $this->post('/admin/login', ['password' => 'original-password'])->assertStatus(429)->assertHeader('Retry-After')->assertSee('Too many attempts');
        $this->travel(61)->seconds();
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect('/admin/products');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/admin/products')->assertRedirect('/admin/login');
    }

    public function test_password_change_checks_current_password_hashes_new_password_and_revokes_old_sessions(): void
    {
        $admin = $this->signIn();
        $oldSession = session()->only(['admin_authenticated', 'admin_id', 'admin_fingerprint']);
        $passwordData = ['current_password' => 'wrong', 'new_password' => 'replacement-password', 'new_password_confirmation' => 'replacement-password'];
        $this->from('/admin/settings')->put('/admin/settings/password', $passwordData)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('original-password', $admin->fresh()->password));

        $this->put('/admin/settings/password', array_replace($passwordData, ['current_password' => 'original-password']))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('replacement-password', $admin->fresh()->password));
        $this->assertNotSame('replacement-password', $admin->fresh()->getRawOriginal('password'));
        $this->get('/admin/settings')->assertOk();
        $this->withSession($oldSession)->get('/admin/settings')->assertRedirect('/admin/login');
    }

    public function test_password_validation_does_not_flash_secrets(): void
    {
        $this->signIn();
        $this->from('/admin/settings')->put('/admin/settings/password', [
            'current_password' => 'original-password', 'new_password' => 'short', 'new_password_confirmation' => 'different',
        ])->assertSessionHasErrors('new_password');
        foreach (['current_password', 'new_password', 'new_password_confirmation'] as $field) {
            $this->assertArrayNotHasKey($field, session()->getOldInput());
        }
        $long = str_repeat('é', 40);
        $this->put('/admin/settings/password', [
            'current_password' => 'original-password', 'new_password' => $long, 'new_password_confirmation' => $long,
        ])->assertSessionHasErrors('new_password');
    }

    public function test_admin_model_hashes_plaintext_without_double_hashing(): void
    {
        $admin = Admin::create(['password' => 'original-password']);
        $this->assertTrue(Hash::check('original-password', $admin->password));
        $hash = Hash::make('another-password');
        $admin->update(['password' => $hash]);
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertArrayNotHasKey('password', $admin->toArray());
    }

    public function test_password_changes_preserve_intentional_whitespace(): void
    {
        $admin = $this->signIn();
        $password = '  exact-password  ';
        $this->put('/admin/settings/password', [
            'current_password' => 'original-password', 'new_password' => $password, 'new_password_confirmation' => $password,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $admin->fresh()->password));
        $this->assertFalse(Hash::check(trim($password), $admin->fresh()->password));
        $this->post('/admin/logout');
        $this->post('/admin/login', ['password' => $password])->assertRedirect('/admin/products');
    }

    public function test_admin_pagination_redirects_when_the_last_page_becomes_empty(): void
    {
        $this->signIn();
        $category = Category::create(['name' => 'Phones']);
        $this->product($category);
        $this->get('/admin/products?page=2')->assertRedirect(route('admin.products.index', ['page' => 1]));
    }

    public function test_csrf_is_required_on_admin_mutations(): void
    {
        // Laravel normally bypasses CSRF during tests; exercise the real token check.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        foreach ([['POST', '/admin/login'], ['POST', '/admin/products'], ['POST', '/admin/categories'], ['PUT', '/admin/settings'], ['PUT', '/admin/settings/password'], ['POST', '/admin/logout']] as [$method, $uri]) {
            $this->call($method, $uri)->assertStatus(419);
        }
        Admin::create(['password' => 'original-password']);
        $this->withSession(['_token' => 'test-token'])->post('/admin/login', [
            '_token' => 'test-token', 'password' => 'original-password',
        ])->assertRedirect('/admin/products');
    }

    public function test_product_images_options_and_caches_follow_create_update_delete(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Phones']);
        Cache::put('home.products.latest', 'stale');
        Cache::put('home.categories', 'stale');
        $data = $this->productData($category);
        $this->from('/admin/products')->post('/admin/products', $data + ['image' => UploadedFile::fake()->image('phone.jpg', 1600, 800)])
            ->assertRedirect('/admin/products')->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $oldImage = $product->image_path;
        $this->assertSame(['8GB', '12GB'], $product->ram);
        $this->assertSame(['128GB', '256GB'], $product->storage);
        $this->assertSame(['Black', 'Blue'], $product->colors);
        $this->assertSame('OLED', $product->specs['Screen']);
        Storage::disk('public')->assertExists($oldImage);
        $info = getimagesize(Storage::disk('public')->path($oldImage));
        $this->assertSame([1200, 600, IMAGETYPE_WEBP], array_slice($info, 0, 3));
        $this->assertFalse(Cache::has('home.products.latest'));
        $this->assertFalse(Cache::has('home.categories'));
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('OLED')->assertSee('5000 mAh');

        $this->put('/admin/products/'.$product->id, array_replace($data, ['name' => 'Renamed phone', 'ram' => '', 'screen' => '0']))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->ram);
        $this->assertSame('0', $product->fresh()->specs['Screen']);
        $this->assertSame($product->slug, $product->fresh()->slug);
        Storage::disk('public')->assertExists($oldImage);

        $this->put('/admin/products/'.$product->id, $data + ['image' => UploadedFile::fake()->image('new.png', 300, 200)])->assertSessionHasNoErrors();
        $newImage = $product->fresh()->image_path;
        Storage::disk('public')->assertMissing($oldImage);
        $this->assertSame([300, 200], array_slice(getimagesize(Storage::disk('public')->path($newImage)), 0, 2));
        Cache::put('home.products.latest', 'stale');
        Cache::put('home.categories', 'stale');
        $this->delete('/admin/products/'.$product->id)->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($newImage);
        $this->assertModelMissing($product);
        $this->assertFalse(Cache::has('home.products.latest'));
        $this->assertFalse(Cache::has('home.categories'));
    }

    public function test_invalid_product_data_does_not_create_products_or_files(): void
    {
        Storage::fake('public');
        $this->signIn();
        $this->post('/admin/products', [
            'name' => str_repeat('x', 121), 'brand' => [], 'price' => '-1', 'category_id' => 999,
            'ram' => ['8GB'], 'image' => UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors(['name', 'brand', 'price', 'category_id', 'ram', 'image']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_database_save_cleans_the_new_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Phones']);
        Product::creating(function () {
            throw new \RuntimeException('Simulated database failure');
        });
        $this->post('/admin/products', $this->productData($category) + ['image' => UploadedFile::fake()->image('phone.png')])->assertStatus(500);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_slugs_are_unique_nonempty_and_fit_the_database_column(): void
    {
        $category = Category::create(['name' => 'Phones']);
        foreach (['Same phone', 'Same phone', '📱', '📱', str_repeat('影', 120)] as $name) {
            $product = $this->product($category, ['name' => $name]);
            $this->assertNotEmpty($product->slug);
            $this->assertLessThanOrEqual(140, strlen($product->slug));
        }
        $this->assertSame(5, Product::distinct()->count('slug'));
    }

    public function test_failed_product_update_or_delete_keeps_the_current_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Phones']);
        $product = $this->product($category, ['image_path' => 'products/original.webp']);
        Storage::disk('public')->put($product->image_path, 'original image');
        Product::updating(function () {
            throw new \RuntimeException('Simulated update failure');
        });
        $this->put('/admin/products/'.$product->id, $this->productData($category) + ['image' => UploadedFile::fake()->image('new.png')])->assertStatus(500);
        $this->assertSame(['products/original.webp'], Storage::disk('public')->allFiles());
        $this->assertSame('products/original.webp', $product->fresh()->image_path);
        Product::deleting(function () {
            throw new \RuntimeException('Simulated delete failure');
        });
        $this->delete('/admin/products/'.$product->id)->assertStatus(500);
        $this->assertModelExists($product);
        Storage::disk('public')->assertExists('products/original.webp');
    }

    public function test_oversized_images_and_fractional_prices_are_rejected(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Phones']);
        $this->post('/admin/products', $this->productData($category, ['price' => '1.234']) + [
            'image' => UploadedFile::fake()->image('large.png', 4097, 10),
        ])->assertSessionHasErrors(['image', 'price']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_category_crud_rejects_duplicates_and_protects_products(): void
    {
        $this->signIn();
        foreach (['home.products.latest', 'home.categories', 'search.categories'] as $key) {
            Cache::put($key, 'stale');
        }
        $this->post('/admin/categories', ['name' => '  Phones  '])->assertSessionHasNoErrors();
        foreach (['home.products.latest', 'home.categories', 'search.categories'] as $key) {
            $this->assertFalse(Cache::has($key));
        }
        $category = Category::firstOrFail();
        $this->assertSame('Phones', $category->name);
        $this->post('/admin/categories', ['name' => ' phones '])->assertSessionHasErrors('name');
        $this->put('/admin/categories/'.$category->id, ['name' => 'Phones'])->assertSessionHasNoErrors();
        $this->put('/admin/categories/'.$category->id, ['name' => 'Flagships'])->assertSessionHasNoErrors();
        $product = $this->product($category);
        $this->delete('/admin/categories/'.$category->id)->assertSessionHasErrors('category');
        $this->assertModelExists($category);
        $this->assertModelExists($product);
        $product->delete();
        $this->delete('/admin/categories/'.$category->id)->assertSessionHasNoErrors();
        $this->assertModelMissing($category);
    }

    public function test_settings_are_refreshed_on_all_store_pages(): void
    {
        $this->signIn();
        Setting::create(['store_name' => 'Old store']);
        Setting::cached();
        $this->put('/admin/settings', [
            'store_name' => 'Updated Store', 'tagline' => 'Updated tagline',
            'about_title' => 'Our story', 'about_text' => 'Helpful phone advice.',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(Cache::has('home.settings'));
        $this->get('/')->assertOk()->assertSee('Our story')->assertSee('Updated tagline')->assertSee('Helpful phone advice.');
        $this->get('/search')->assertOk()->assertSee('Updated Store');
        $category = Category::create(['name' => 'Phones']);
        $product = $this->product($category);
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('Updated Store');
    }

    public function test_search_filters_name_brand_category_and_paginates_stably(): void
    {
        $category = Category::create(['name' => 'Phones']);
        $other = Category::create(['name' => 'Other']);
        for ($i = 0; $i < 10; $i++) {
            $this->product($category, ['name' => 'Galaxy '.$i]);
        }
        $this->product($other, ['name' => 'Galaxy elsewhere']);
        $this->product($category, ['name' => '100% special', 'brand' => 'Other']);
        $url = '/search/products?q=samsung&category='.$category->id;
        $first = $this->getJson($url)->assertOk()->assertJsonPath('products.total', 10)->assertJsonCount(8, 'products.data');
        $second = $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(2, 'products.data');
        $this->assertEmpty(array_intersect(array_column($first->json('products.data'), 'id'), array_column($second->json('products.data'), 'id')));
        $this->getJson('/search/products?q=galaxy')->assertJsonPath('products.total', 11);
        $this->getJson('/search/products?q=%25')->assertJsonPath('products.total', 1);
        $this->getJson('/search/products?q=missing')->assertJsonPath('products.total', 0);
        $this->getJson('/search/products?q[]=invalid&category=bad&page=-1')->assertUnprocessable()->assertJsonValidationErrors(['q', 'category', 'page']);
    }
}
