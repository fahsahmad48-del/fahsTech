<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Perfume;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerfumesTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        Admin::create(['password' => 'original-password']);
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect('/admin/products');
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Dior Sauvage', 'category' => 'men', 'price' => '120.50',
            'description' => 'A fresh fragrance.',
            'specs' => [['name' => ' Size ', 'value' => ' 100 ml '], ['name' => '', 'value' => '']],
        ], $overrides);
    }

    private function perfume(array $overrides = []): Perfume
    {
        return Perfume::create($this->data(array_replace([
            'specs' => ['Size' => '100 ml'], 'image_path' => 'perfumes/original.webp',
        ], $overrides)));
    }

    public function test_perfumes_have_a_separate_table_and_unique_stable_slugs(): void
    {
        $this->assertSame([
            'men' => 'Men', 'women' => 'Women', 'kids' => 'Kids',
            'body-mists' => 'Body Mists', 'home-sprays' => 'Home Sprays',
        ], Perfume::CATEGORIES);
        $this->assertTrue(Schema::hasColumns('perfumes', [
            'id', 'name', 'slug', 'category', 'price', 'specs', 'description',
            'image_path', 'image_status', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasTable('perfume_categories'));
        $this->assertFalse(Schema::hasColumn('perfumes', 'category_id'));
        $this->assertFalse(Schema::hasColumn('perfumes', 'brand'));
        $this->assertTrue(Schema::hasColumn('products', 'brand'));
        foreach (['dior-sauvage', 'dior-sauvage-1', 'dior-sauvage-2'] as $slug) {
            $perfume = $this->perfume();
            $this->assertSame($slug, $perfume->slug);
            $this->assertSame('120.50', $perfume->price);
        }
        $perfume->update(['name' => 'Renamed perfume']);
        $this->assertSame('dior-sauvage-2', $perfume->fresh()->slug);
        $this->assertSame('perfume', $this->perfume(['name' => '!!!'])->slug);
        $reserved = $this->perfume(['name' => 'Filter']);
        $this->assertSame('filter-1', $reserved->slug);
        $this->get('/perfumes/'.$reserved->slug)->assertOk();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_column_removal_can_be_rolled_back_and_reapplied_with_existing_perfumes(): void
    {
        $perfume = $this->perfume();
        $attributes = $perfume->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_09_15_000000_drop_brand_from_perfumes_table.php');

        $migration->down();
        try {
            $this->assertTrue(Schema::hasColumn('perfumes', 'brand'));
            $this->assertSame('', DB::table('perfumes')->where('id', $perfume->id)->value('brand'));
        } finally {
            $migration->up();
        }

        $this->assertFalse(Schema::hasColumn('perfumes', 'brand'));
        $this->assertSame($attributes, $perfume->fresh()->getAttributes());
        $this->assertTrue(Schema::hasColumn('products', 'brand'));
    }

    public function test_perfume_forms_lists_details_and_json_have_no_brand_field(): void
    {
        $perfume = $this->perfume();
        $this->assertNotContains('brand', $perfume->getFillable());
        $this->assertArrayNotHasKey('brand', $perfume->fresh()->toArray());
        $this->signIn();

        $this->get('/admin/perfumes')->assertOk()
            ->assertSee('id="perfumeName"', false)->assertSee('id="perfumeCategory"', false)
            ->assertDontSee('perfumeBrand', false)->assertDontSee('name="brand"', false)
            ->assertDontSee('<th>Brand</th>', false)->assertDontSee('data-brand', false);
        $this->get('/perfumes')->assertOk()->assertSee($perfume->name)
            ->assertDontSee('card-brand', false);
        $this->get('/perfumes/'.$perfume->slug)->assertOk()->assertSee($perfume->name)
            ->assertSee('Men')->assertSee('120.50')->assertSee('A fresh fragrance.')
            ->assertDontSee('brand-tag', false)->assertDontSee('<td>Brand</td>', false);

        foreach (['/admin/perfumes/filter', '/perfumes/filter'] as $path) {
            $response = $this->getJson($path)->assertOk()->assertJsonPath('count', 1);
            $this->assertStringNotContainsString('brand', $response->getContent());
        }

        $perfume->delete();
        $empty = $this->getJson('/admin/perfumes/filter')->assertOk()->assertJsonPath('count', 0);
        $this->assertStringContainsString('colspan="4"', $empty->json('html'));
    }

    public function test_every_category_can_be_created_with_specs_and_processed_images(): void
    {
        Storage::fake('public');
        $this->signIn();
        Cache::put('home.products.latest', 'keep');
        foreach (array_keys(Perfume::CATEGORIES) as $category) {
            $this->post('/admin/perfumes', $this->data(['category' => $category]) + [
                'image' => UploadedFile::fake()->image('scent.png', 1800, 900),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $perfume = Perfume::latest('id')->first();
            $this->assertSame($category, $perfume->category);
            $this->assertSame(['Size' => '100 ml'], $perfume->specs);
            $this->assertSame('active', $perfume->image_status);
            $this->assertMatchesRegularExpression('/^perfumes\/[0-9a-f-]{36}\.webp$/', $perfume->image_path);
            $image = getimagesize(Storage::disk('public')->path($perfume->image_path));
            $this->assertSame([1200, 600], [$image[0], $image[1]]);
            $this->assertSame('image/webp', $image['mime']);
        }
        $this->assertSame('keep', Cache::get('home.products.latest'));
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_validation_rejects_invalid_fields_and_malformed_specs_before_upload(): void
    {
        Storage::fake('public');
        $this->signIn();
        foreach ([
            [['category' => 'other'], 'category'],
            [['category' => ['men']], 'category'], [['name' => ''], 'name'],
            [['price' => '-1'], 'price'],
            [['price' => '1.001'], 'price'], [['price' => '100000000'], 'price'],
            [['description' => str_repeat('x', 5001)], 'description'],
            [['specs' => 'bad'], 'specs'], [['specs' => ['Size' => '100 ml']], 'specs.Size'],
            [['specs' => [['name' => ['bad'], 'value' => '100 ml']]], 'specs.0.name'],
            [['specs' => [['name' => 'Size', 'value' => '']]], 'specs.0.value'],
            [['specs' => [['name' => '', 'value' => '100 ml']]], 'specs.0.name'],
            [['specs' => [['name' => 'Size', 'value' => '100'], ['name' => ' size ', 'value' => '50']]], 'specs.1.name'],
            [['specs' => [['name' => 'Size', 'value' => '100', 'extra' => 'bad']]], 'specs.0'],
            [['specs' => [['name' => str_repeat('a', 61), 'value' => '100']]], 'specs.0.name'],
            [['specs' => [['name' => 'Size', 'value' => str_repeat('a', 256)]]], 'specs.0.value'],
            [['specs' => array_fill(0, 31, ['name' => '', 'value' => ''])], 'specs'],
        ] as [$overrides, $field]) {
            $this->postJson('/admin/perfumes', $this->data($overrides) + ['image' => UploadedFile::fake()->image('scent.jpg')])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach ([
            UploadedFile::fake()->image('large.png', 4097, 1),
            UploadedFile::fake()->image('large.jpg')->size(8193),
            UploadedFile::fake()->create('fake.jpg', 1, 'text/plain'),
            UploadedFile::fake()->image('animation.gif'),
        ] as $image) {
            $this->postJson('/admin/perfumes', $this->data() + ['image' => $image])
                ->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->postJson('/admin/perfumes', $this->data())->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('perfumes', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_update_edits_specs_replaces_images_and_delete_cleans_storage(): void
    {
        Storage::fake('public');
        $this->signIn();
        $perfume = $this->perfume();
        Storage::disk('public')->put($perfume->image_path, 'original');
        $this->put('/admin/perfumes/'.$perfume->id, $this->data([
            'name' => 'New name', 'category' => 'women',
            'specs' => [['name' => 'Longevity', 'value' => '8 hours'], ['name' => 'Alcohol', 'value' => '0']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['Longevity' => '8 hours', 'Alcohol' => '0'], $perfume->fresh()->specs);
        Storage::disk('public')->assertExists('perfumes/original.webp');
        $this->put('/admin/perfumes/'.$perfume->id, $this->data(['specs' => []]) + [
            'image' => UploadedFile::fake()->image('replacement.webp'),
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $perfume->fresh()->specs);
        Storage::disk('public')->assertMissing('perfumes/original.webp');
        Storage::disk('public')->assertExists($perfume->fresh()->image_path);
        $this->delete('/admin/perfumes/'.$perfume->id, ['filter_category' => 'men', 'filter_page' => 2])
            ->assertRedirect(route('admin.perfumes.index', ['category' => 'men', 'page' => 2]));
        $this->assertModelMissing($perfume);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get('/admin/perfumes?category=men&page=2')->assertRedirect(route('admin.perfumes.index', ['category' => 'men', 'page' => 1]));
    }

    public function test_database_create_failure_cleans_the_new_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        Perfume::creating(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->post('/admin/perfumes', $this->data() + ['image' => UploadedFile::fake()->image('scent.png')])->assertStatus(500);
        $this->assertDatabaseCount('perfumes', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_database_update_and_delete_failures_preserve_the_record_and_old_image(): void
    {
        Storage::fake('public');
        $this->signIn();
        $perfume = $this->perfume();
        Storage::disk('public')->put($perfume->image_path, 'original');
        Perfume::updating(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->put('/admin/perfumes/'.$perfume->id, $this->data() + ['image' => UploadedFile::fake()->image('scent.png')])->assertStatus(500);
        $this->assertSame(['perfumes/original.webp'], Storage::disk('public')->allFiles());
        $this->assertSame('perfumes/original.webp', $perfume->fresh()->image_path);
        Perfume::deleting(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->delete('/admin/perfumes/'.$perfume->id)->assertStatus(500);
        $this->assertModelExists($perfume);
        Storage::disk('public')->assertExists('perfumes/original.webp');
    }

    public function test_admin_and_public_filters_paginate_and_escape_their_results(): void
    {
        $this->signIn();
        for ($i = 0; $i < 16; $i++) {
            $this->perfume(['name' => 'Men scent '.$i]);
        }
        $this->perfume(['name' => 'Women scent', 'category' => 'women']);
        $this->perfume(['name' => '<script>alert(1)</script>', 'category' => 'women']);
        foreach (['/admin/perfumes', '/perfumes'] as $path) {
            $this->get($path)->assertOk()->assertSee('Perfumes');
            $first = $this->getJson($path.'/filter?category=men')->assertOk()->assertJsonPath('count', 16)->assertJsonPath('page', 1);
            $this->assertStringNotContainsString('Women scent', $first->json('html'));
            $this->assertStringContainsString('category=men', $first->json('pagination'));
            $this->assertStringContainsString('page=2', $first->json('pagination'));
            $this->getJson($path.'/filter?category=men&page=2')->assertJsonPath('page', 2)->assertJsonPath('count', 16);
            $this->getJson($path.'/filter?category=men&page=99')->assertJsonPath('page', 2);
            $this->getJson($path.'/filter')->assertJsonPath('count', 18);
            $this->getJson($path.'/filter?category=')->assertJsonPath('count', 18);
            $escaped = $this->getJson($path.'/filter?category=women')->assertJsonPath('count', 2);
            $this->assertStringNotContainsString('<script>', $escaped->json('html'));
            $this->assertStringContainsString('&lt;script&gt;', $escaped->json('html'));
            $this->getJson($path.'/filter?category=other&page=-1')->assertUnprocessable()->assertJsonValidationErrors(['category', 'page']);
            $this->getJson($path.'/filter?category[]=men')->assertUnprocessable();
        }
        $this->getJson('/search/products')->assertJsonPath('products.total', 0);
        Perfume::query()->delete();
        foreach (['/admin/perfumes', '/perfumes'] as $path) {
            $empty = $this->getJson($path.'/filter?category=women&page=4')->assertJsonPath('count', 0)->assertJsonPath('page', 1);
            $this->assertStringContainsString($path === '/perfumes'
                ? 'No perfumes match your search and category.' : 'No perfumes match this category.', $empty->json('html'));
        }
    }

    public function test_name_search_is_partial_case_insensitive_literal_and_combined_with_category(): void
    {
        $this->signIn();
        $this->perfume(['name' => 'Sauvage']);
        $this->perfume(['name' => 'Sauvage Rose', 'category' => 'women']);
        $this->perfume(['name' => 'Quiet scent', 'description' => 'Sauvage']);
        $this->perfume(['name' => '50%_! Spray', 'category' => 'home-sprays']);
        $this->perfume(['name' => '500 Extra Spray', 'category' => 'body-mists']);
        $this->perfume(['name' => 'Scent 0', 'category' => 'kids']);

        foreach (['/perfumes'] as $path) {
            $this->get($path.'?q=SAUV&category=men')->assertOk()
                ->assertSee('value="SAUV"', false)->assertSee('Sauvage')->assertDontSee('Sauvage Rose');
            foreach (['sauv', 'SAUV'] as $q) {
                $this->getJson($path.'/filter?'.http_build_query(['q' => $q]))
                    ->assertOk()->assertJsonPath('count', 2);
            }
            foreach (['%', '_', '!', '%_!'] as $q) {
                $response = $this->getJson($path.'/filter?'.http_build_query(['q' => $q]))
                    ->assertOk()->assertJsonPath('count', 1);
                $this->assertStringContainsString('50%_! Spray', $response->json('html'));
            }
            $this->getJson($path.'/filter?q=spray&category=home-sprays')->assertJsonPath('count', 1);
            $this->getJson($path.'/filter?q=spray&category=kids')->assertJsonPath('count', 0);
            $this->getJson($path.'/filter?q=0&category=kids')->assertJsonPath('count', 1);
            $this->getJson($path.'/filter?q=%20%20')->assertJsonPath('count', 6);
            $this->getJson($path.'/filter?q[]=sauv')->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->getJson($path.'/filter?q='.str_repeat('x', 121))->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->get($path)->assertSeeInOrder(['All Categories', 'Men', 'Women', 'Kids', 'Body Mists', 'Home Sprays']);
        }
    }

    public function test_search_and_category_survive_pagination_and_admin_actions(): void
    {
        $this->signIn();
        for ($i = 1; $i <= 16; $i++) {
            $this->perfume(['name' => 'Spray 0 '.$i, 'category' => 'home-sprays']);
        }
        $this->perfume(['name' => 'Other', 'category' => 'home-sprays']);
        $this->perfume(['name' => 'Spray', 'category' => 'body-mists']);
        foreach (['/perfumes'] as $path) {
            $response = $this->getJson($path.'/filter?q=spray&category=home-sprays')
                ->assertOk()->assertJsonPath('count', 16);
            $this->assertStringContainsString('q=spray', $response->json('pagination'));
            $this->assertStringContainsString('category=home-sprays', $response->json('pagination'));
            $this->assertStringContainsString('page=2', $response->json('pagination'));
            $this->getJson($path.'/filter?q=spray&category=home-sprays&page=2')
                ->assertJsonPath('count', 16)->assertJsonPath('page', 2);
            $this->getJson($path.'/filter?q=spray&category=home-sprays&page=99')->assertJsonPath('page', 2);
            $route = $path === '/perfumes' ? 'perfumes.index' : 'admin.perfumes.index';
            $this->get($path.'?q=0&category=home-sprays&page=99')->assertRedirect(route($route, [
                'q' => '0', 'category' => 'home-sprays', 'page' => 2,
            ]));
        }
        $perfume = Perfume::where('category', 'home-sprays')->first();
        $this->delete('/admin/perfumes/'.$perfume->id, [
            'filter_q' => '0', 'filter_category' => 'home-sprays', 'filter_page' => 2,
        ])->assertRedirect(route('admin.perfumes.index', ['category' => 'home-sprays', 'page' => 2]));
    }

    public function test_admin_filters_only_by_category_and_ignores_name_queries(): void
    {
        $this->signIn();
        for ($i = 1; $i <= 16; $i++) {
            $this->perfume(['name' => 'Women scent '.$i, 'category' => 'women']);
        }
        $this->perfume(['name' => 'Men scent', 'category' => 'men']);
        $this->get('/admin/perfumes?q[]=ignored&category=women')->assertOk()
            ->assertDontSee('data-search-input', false)->assertDontSee('name="q"', false)
            ->assertDontSee('name="filter_q"', false)
            ->assertSeeInOrder(['All Categories', 'Men', 'Women', 'Kids', 'Body Mists', 'Home Sprays']);
        foreach (['not found', ['ignored'], str_repeat('x', 121)] as $query) {
            $response = $this->getJson('/admin/perfumes/filter?'.http_build_query(['q' => $query, 'category' => 'women']))
                ->assertOk()->assertJsonPath('count', 16);
            $this->assertStringContainsString('category=women', $response->json('pagination'));
            $this->assertStringNotContainsString('q=', $response->json('pagination'));
            $this->assertStringNotContainsString('filter_q', $response->json('html'));
        }
        $this->getJson('/admin/perfumes/filter?category=women&page=2&q=ignored')
            ->assertOk()->assertJsonPath('count', 16)->assertJsonPath('page', 2);
        $this->get('/admin/perfumes?category=women&page=99&q[]=ignored')
            ->assertRedirect(route('admin.perfumes.index', ['category' => 'women', 'page' => 2]));
        foreach (Perfume::CATEGORIES as $category => $label) {
            $this->getJson('/admin/perfumes/filter?category='.$category)->assertOk()
                ->assertJsonPath('count', match ($category) { 'women' => 16, 'men' => 1, default => 0 });
        }
    }

    public function test_public_slug_details_render_dynamic_specs_whatsapp_and_public_navigation(): void
    {
        Setting::create(['store_name' => 'Mobile Hub', 'whatsapp_number' => '+96171123456']);
        $perfume = $this->perfume(['specs' => ['Top Notes' => 'Bergamot', 'Alcohol' => '0', 'Empty' => '', 'Nested' => ['bad'], '<script>' => '<b>value</b>']]);
        $detail = $this->get('/perfumes/'.$perfume->slug)->assertOk()->assertSee('Dior Sauvage')
            ->assertSee('Top Notes')->assertSee('Bergamot')->assertSee('Alcohol')->assertSee('<td>0</td>', false)
            ->assertDontSee('<td>Empty</td>', false)->assertDontSee('<td>Nested</td>', false)
            ->assertSee('&lt;b&gt;value&lt;/b&gt;', false)->assertSee('class="whatsapp-float"', false);
        $this->get('/perfumes/does-not-exist')->assertNotFound();
        $this->get('/perfumes/'.$perfume->id)->assertNotFound();
        $guestHtml = $this->get('/perfumes')->assertSee('Staff Login')->assertSee('class="whatsapp-float"', false)->getContent();
        preg_match('/<nav class="main-nav".*?<\/nav>/s', $guestHtml, $guestNav);
        $this->assertStringNotContainsString('Admin Dashboard', $guestNav[0]);
        $this->signIn();
        $html = $this->get('/perfumes')->assertOk()->getContent();
        preg_match('/<nav class="main-nav".*?<\/nav>/s', $html, $nav);
        $this->assertStringContainsString('Admin Dashboard', $nav[0]);
        $this->assertStringContainsString(route('admin.products.index'), $nav[0]);
        $this->assertStringContainsString('class="active"', $nav[0]);
        $this->assertStringContainsString('Perfumes', $nav[0]);
    }

    public function test_admin_routes_are_protected(): void
    {
        $perfume = $this->perfume();
        foreach ([['GET', '/admin/perfumes'], ['POST', '/admin/perfumes'], ['PUT', '/admin/perfumes/'.$perfume->id], ['DELETE', '/admin/perfumes/'.$perfume->id]] as [$method, $url]) {
            $this->call($method, $url)->assertRedirect('/admin/login');
        }
        $this->getJson('/admin/perfumes/filter')->assertUnauthorized();
    }

    public function test_validation_input_and_existing_image_are_restored(): void
    {
        $perfume = $this->perfume();
        $this->signIn();
        $this->from('/admin/perfumes')->put('/admin/perfumes/'.$perfume->id, $this->data(['price' => '-1']) + ['_form' => 'perfume', '_record' => $perfume->id])
            ->assertRedirect('/admin/perfumes');
        $this->get('/admin/perfumes')->assertOk()->assertSee('perfumeModal')->assertSee('Please select the image again')
            ->assertSee('data-old-image="'.asset('storage/'.$perfume->image_path).'"', false);
    }

    public function test_numeric_specification_names_are_stored_as_a_json_object(): void
    {
        $this->signIn();
        $perfume = $this->perfume();
        $this->put('/admin/perfumes/'.$perfume->id, $this->data(['specs' => [['name' => '0', 'value' => 'zero']]]))->assertSessionHasNoErrors();
        $this->assertSame('{"0":"zero"}', DB::table('perfumes')->where('id', $perfume->id)->value('specs'));
    }
}
