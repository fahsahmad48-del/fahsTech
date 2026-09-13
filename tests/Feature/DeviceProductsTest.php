<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeviceProductsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        Admin::create(['password' => 'original-password']);
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect('/admin/products');
    }

    private function deviceData(Category $category, array $overrides = []): array
    {
        return array_replace([
            'product_type' => 'laptop', 'name' => 'MacBook Air', 'brand' => 'Apple',
            'category_id' => $category->id, 'price' => '999.99', 'colors' => 'Blue, Silver, Blue',
            'description' => 'A portable computer.',
            'specs' => [
                ['name' => ' Processor ', 'value' => ' Apple M4 '],
                ['name' => 'RAM', 'value' => '16GB'],
                ['name' => '', 'value' => ''],
            ],
        ], $overrides);
    }

    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create(array_replace([
            'name' => 'Catalog product', 'brand' => 'Brand', 'price' => 100,
            'category_id' => $category->id,
        ], $overrides));
    }

    public function test_migration_preserves_existing_products_and_defaults_them_to_phone(): void
    {
        $migration = require database_path('migrations/2026_09_11_000000_add_product_type_to_products_table.php');
        $migration->down();
        $category = Category::create(['name' => 'Existing']);
        $id = DB::table('products')->insertGetId([
            'name' => 'Existing phone', 'brand' => 'Brand', 'slug' => 'existing-phone',
            'category_id' => $category->id, 'price' => 100, 'specs' => '{"Screen":"OLED"}',
        ]);
        $migration->up();
        $product = Product::findOrFail($id);
        $this->assertSame('phone', $product->product_type);
        $this->assertSame(['Screen' => 'OLED'], $product->specs);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_public_search_works_before_the_product_type_migration(): void
    {
        $migration = require database_path('migrations/2026_09_11_000000_add_product_type_to_products_table.php');
        $migration->down();

        try {
            $category = Category::create(['name' => 'Existing']);
            $other = Category::create(['name' => 'Other']);
            for ($i = 0; $i < 10; $i++) {
                // Insert legacy rows without the new model's product_type default.
                DB::table('products')->insert([
                    'name' => 'Legacy '.$i, 'brand' => 'Brand', 'slug' => 'legacy-'.$i,
                    'category_id' => $i === 9 ? $other->id : $category->id,
                    'price' => 100, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $this->assertSame('/search', route('search', [], false));
            $this->assertSame('/search/products', route('search.products', [], false));
            $this->get(route('search'))->assertOk()
                ->assertSee('data-endpoint="'.route('search.products').'"', false);

            $url = route('search.products', ['q' => 'Legacy', 'category' => $category->id]);
            $first = $this->getJson($url)->assertOk()->assertHeader('Content-Type', 'application/json')
                ->assertJsonPath('products.total', 9)->assertJsonPath('products.last_page', 2)
                ->assertJsonCount(8, 'products.data')
                ->assertJsonPath('products.data.0.category.id', $category->id)
                ->assertJsonStructure([
                    'products' => ['data' => [['id', 'name', 'brand', 'slug', 'price', 'image_path', 'category']], 'current_page', 'last_page', 'total'],
                    'categories' => [['id', 'name']],
                ]);
            $second = $this->getJson($first->json('products.next_page_url'))->assertOk()
                ->assertJsonPath('products.total', 9)->assertJsonPath('products.current_page', 2)
                ->assertJsonCount(1, 'products.data');
            $this->assertEmpty(array_intersect(
                array_column($first->json('products.data'), 'id'),
                array_column($second->json('products.data'), 'id'),
            ));
            $this->getJson(route('search.products'))->assertOk()->assertJsonPath('products.total', 10);
            $this->getJson(route('search.products', ['q' => 'missing']))->assertOk()->assertJsonCount(0, 'products.data');
        } finally {
            $migration->up();
        }
    }

    public function test_phone_creation_and_edit_force_phone_even_with_a_forged_type(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Budget']);
        $data = $this->deviceData($category, ['product_type' => 'laptop', 'screen' => 'OLED', 'ram' => '8GB, 12GB']);
        $this->post('/admin/products', $data + ['image' => UploadedFile::fake()->image('phone.png')])->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertSame('phone', $product->product_type);
        $this->assertSame(['Screen' => 'OLED'], $product->specs);
        $this->assertSame(['8GB', '12GB'], $product->ram);
        $this->put('/admin/products/'.$product->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('phone', $product->fresh()->product_type);
    }

    public function test_all_device_types_use_generic_specs_image_processing_and_cache_invalidation(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Custom category']);
        foreach (Product::deviceTypes() as $type => $label) {
            Cache::put('home.products.latest', 'stale');
            Cache::put('home.categories', 'stale');
            $this->post('/admin/products/devices', $this->deviceData($category, ['product_type' => $type, 'name' => $label]) + [
                'image' => UploadedFile::fake()->image('device.jpg', 1600, 800),
            ])->assertSessionHasNoErrors();
            $product = Product::where('product_type', $type)->firstOrFail();
            $this->assertSame(['Processor' => 'Apple M4', 'RAM' => '16GB'], $product->specs);
            $this->assertSame(['Blue', 'Silver'], $product->colors);
            $this->assertNull($product->ram);
            $this->assertNull($product->storage);
            $this->assertMatchesRegularExpression('/^products\/[a-f0-9-]{36}\.webp$/', $product->image_path);
            $this->assertSame([1200, 600, IMAGETYPE_WEBP], array_slice(getimagesize(Storage::disk('public')->path($product->image_path)), 0, 3));
            $this->assertFalse(Cache::has('home.products.latest'));
            $this->assertFalse(Cache::has('home.categories'));
        }
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_devices_reject_invalid_types_partial_duplicate_and_oversized_specs(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Devices']);
        $cases = [
            [['product_type' => 'phone'], 'product_type'],
            [['product_type' => 'perfume'], 'product_type'],
            [['product_type' => 'unknown'], 'product_type'],
            [['product_type' => null], 'product_type'],
            [['specs' => [['name' => 'RAM', 'value' => '16GB'], ['name' => ' ram ', 'value' => '8GB']]], 'specs.1.name'],
            [['specs' => [['name' => 'RAM', 'value' => '']]], 'specs.0.value'],
            [['specs' => [['name' => '', 'value' => '16GB']]], 'specs.0.name'],
            [['specs' => [['name' => str_repeat('x', 61), 'value' => 'value']]], 'specs.0.name'],
            [['specs' => [['name' => 'Name', 'value' => str_repeat('x', 256)]]], 'specs.0.value'],
            [['specs' => array_fill(0, 31, ['name' => '', 'value' => ''])], 'specs'],
            [['specs' => ['not a row']], 'specs.0'],
            [['specs' => [['name' => ['invalid'], 'value' => 'value']]], 'specs.0.name'],
            [['specs' => [['name' => 'Name', 'value' => 'Value', 'extra' => 'no']]], 'specs.0'],
            [['image' => UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml')], 'image'],
            [['image' => UploadedFile::fake()->image('large.png', 4097, 10)], 'image'],
            [['price' => '1.234'], 'price'],
        ];
        foreach ($cases as [$override, $field]) {
            $data = array_replace($this->deviceData($category), ['image' => UploadedFile::fake()->image('device.png')], $override);
            $this->from('/admin/products')->post('/admin/products/devices', $data)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_device_edit_changes_and_removes_specs_preserves_then_replaces_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Devices']);
        $product = $this->product($category, ['product_type' => 'laptop', 'specs' => ['Processor' => 'Old', 'RAM' => '8GB'], 'image_path' => 'products/old.webp']);
        Storage::disk('public')->put($product->image_path, 'old image');
        $data = $this->deviceData($category, ['specs' => [['name' => 'Storage', 'value' => '1TB'], ['name' => 'USB Ports', 'value' => '0']]]);
        $this->put('/admin/products/'.$product->id, $data)->assertSessionHasNoErrors();
        $this->assertSame(['Storage' => '1TB', 'USB Ports' => '0'], $product->fresh()->specs);
        Storage::disk('public')->assertExists('products/old.webp');
        Cache::put('home.products.latest', 'stale');
        Cache::put('home.categories', 'stale');
        $this->put('/admin/products/'.$product->id, $data + ['image' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing('products/old.webp');
        Storage::disk('public')->assertExists($product->fresh()->image_path);
        $this->assertFalse(Cache::has('home.products.latest'));
        $this->assertFalse(Cache::has('home.categories'));
        $this->put('/admin/products/'.$product->id, $this->deviceData($category, ['specs' => []]))->assertSessionHasNoErrors();
        $this->assertSame([], $product->fresh()->specs);
        $this->put('/admin/products/'.$product->id, $this->deviceData($category, ['product_type' => 'phone']))->assertSessionHasErrors('product_type');
        $this->assertSame('laptop', $product->fresh()->product_type);
    }

    public function test_failed_device_create_cleans_new_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Devices']);
        Product::creating(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->post('/admin/products/devices', $this->deviceData($category) + ['image' => UploadedFile::fake()->image('device.png')])->assertStatus(500);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_device_update_and_delete_keep_old_image_and_record(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Devices']);
        $product = $this->product($category, ['product_type' => 'laptop', 'image_path' => 'products/original.webp']);
        Storage::disk('public')->put($product->image_path, 'original');
        Product::updating(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->put('/admin/products/'.$product->id, $this->deviceData($category) + ['image' => UploadedFile::fake()->image('new.png')])->assertStatus(500);
        $this->assertSame(['products/original.webp'], Storage::disk('public')->allFiles());
        $this->assertSame('products/original.webp', $product->fresh()->image_path);
        Product::deleting(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->delete('/admin/products/'.$product->id)->assertStatus(500);
        $this->assertModelExists($product);
        Storage::disk('public')->assertExists('products/original.webp');
    }

    public function test_admin_filter_returns_escaped_rows_and_keeps_category_through_pagination(): void
    {
        $this->signIn();
        $category = Category::create(['name' => 'Custom Audio']);
        $other = Category::create(['name' => 'Gaming']);
        for ($i = 0; $i < 16; $i++) {
            $this->product($category, ['name' => 'Audio '.$i, 'product_type' => $i % 2 ? 'earbuds' : 'phone']);
        }
        $outside = $this->product($other, ['name' => 'Outside category', 'product_type' => 'playstation']);
        $first = $this->getJson('/admin/products/filter?category='.$category->id)->assertOk()->assertJsonPath('count', 16)->assertJsonPath('page', 1);
        $this->assertStringNotContainsString($outside->name, $first->json('html'));
        $this->assertStringContainsString('category='.$category->id, $first->json('pagination'));
        $this->assertStringContainsString('page=2', $first->json('pagination'));
        $this->assertStringContainsString('name="_token"', $first->json('html'));
        $this->assertStringContainsString('name="_method" value="DELETE"', $first->json('html'));
        $this->assertSame(15, substr_count($first->json('html'), 'data-update-url='));
        $second = $this->getJson('/admin/products/filter?category='.$category->id.'&page=2')->assertOk()->assertJsonPath('count', 16)->assertJsonPath('page', 2);
        $this->assertSame(1, substr_count($second->json('html'), 'data-update-url='));
        $this->getJson('/admin/products/filter')->assertJsonPath('count', 17);
        $this->getJson('/admin/products/filter?category=')->assertJsonPath('count', 17);
        $empty = Category::create(['name' => 'Empty']);
        $emptyResponse = $this->getJson('/admin/products/filter?category='.$empty->id.'&page=9')->assertJsonPath('count', 0)->assertJsonPath('page', 1);
        $this->assertStringContainsString('No products found in this category.', $emptyResponse->json('html'));
        $this->getJson('/admin/products/filter?category[]=bad&page=-1')->assertUnprocessable()->assertJsonValidationErrors(['category', 'page']);
        $this->product($other, ['name' => '<script>alert(1)</script>', 'product_type' => 'laptop', 'specs' => ['<b>Spec</b>' => '"><script>bad</script>']]);
        $escaped = $this->getJson('/admin/products/filter?category='.$other->id)->assertOk();
        $this->assertStringNotContainsString('<script>', $escaped->json('html'));
        $this->assertStringContainsString('&lt;script&gt;', $escaped->json('html'));
    }

    public function test_shared_delete_preserves_category_and_handles_an_empty_last_page(): void
    {
        Storage::fake('public');
        $this->signIn();
        $category = Category::create(['name' => 'Mixed']);
        foreach (['phone', 'laptop'] as $type) {
            $product = $this->product($category, ['product_type' => $type, 'image_path' => "products/$type.webp"]);
            Storage::disk('public')->put($product->image_path, 'image');
            Cache::put('home.products.latest', 'stale');
            Cache::put('home.categories', 'stale');
            $this->delete('/admin/products/'.$product->id, ['filter_category' => $category->id, 'filter_page' => 2])
                ->assertRedirect(route('admin.products.index', ['category' => $category->id, 'page' => 2]));
            $this->get('/admin/products?category='.$category->id.'&page=2')
                ->assertRedirect(route('admin.products.index', ['category' => $category->id, 'page' => 1]));
            $this->assertModelMissing($product);
            Storage::disk('public')->assertMissing($product->image_path);
            $this->assertFalse(Cache::has('home.products.latest'));
            $this->assertFalse(Cache::has('home.categories'));
        }
    }

    public function test_public_catalog_search_home_and_details_support_all_types(): void
    {
        $category = Category::create(['name' => 'Mixed']);
        foreach (Product::TYPES as $type => $label) {
            $product = $this->product($category, [
                'name' => 'Unique '.$label, 'product_type' => $type,
                'specs' => $type === 'phone' ? ['screen' => 'OLED', 'battery' => '5000 mAh'] : ['Connectivity' => 'Bluetooth 5.3', 'USB Ports' => '0'],
            ]);
            $detail = $this->get('/products/'.$product->slug)->assertOk()->assertSee('Specifications')->assertSee($product->name);
            if ($type === 'phone') {
                $detail->assertSee('OLED')->assertSee('5000 mAh')->assertDontSee('<td>Camera</td>', false);
            } else {
                $detail->assertSee('Connectivity')->assertSee('Bluetooth 5.3')->assertSee('USB Ports')
                    ->assertDontSee('<td>Camera</td>', false)->assertDontSee('<td>Screen</td>', false);
            }
            $this->getJson('/search/products?q='.urlencode($product->name).'&category='.$category->id)
                ->assertOk()->assertJsonPath('products.total', 1)->assertJsonPath('products.data.0.product_type', $type);
        }
        $this->getJson('/search/products')->assertJsonPath('products.total', 7);
        $this->get('/search')->assertOk()->assertSee('Browse Products')->assertDontSee('Loading phones');
        $home = $this->get('/')->assertOk()->assertSee('7')->assertSee('View all products')
            ->assertSee('Phone Care')->assertSee('We Maintain and Repair Phones')->assertSee('Whish Services');
        foreach (Product::all() as $product) {
            $home->assertSee($product->name);
        }
        $this->assertCount(7, Cache::get('home.products.latest'));
    }

    public function test_new_routes_require_admin_authentication_and_device_validation_restores_input(): void
    {
        $this->getJson('/admin/products/filter')->assertUnauthorized();
        $this->post('/admin/products/devices')->assertRedirect('/admin/login');
        $this->signIn();
        $category = Category::create(['name' => 'Audio']);
        $this->from('/admin/products')->post('/admin/products/devices', $this->deviceData($category) + ['_form' => 'device'])
            ->assertSessionHasErrors('image')->assertSessionHasInput('_form', 'device')->assertSessionHasInput('specs.0.value', 'Apple M4');
        $this->get('/admin/products')->assertOk()->assertSee('deviceModal')->assertSee('Please select the image again');
    }
}
