<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WhatsAppContactTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        Admin::create(['password' => 'original-password']);
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect('/admin/products');
    }

    private function publicPages(): array
    {
        $category = Category::create(['name' => 'Phones']);
        $product = Product::create([
            'name' => 'Contact test phone', 'brand' => 'Samsung', 'category_id' => $category->id, 'price' => 100,
        ]);

        return ['/', '/search', '/products/'.$product->slug];
    }

    public function test_admin_can_save_change_and_clear_the_number_and_refresh_cached_links(): void
    {
        $this->signIn();
        $pages = $this->publicPages();
        Setting::cached();

        foreach (['+961 (71) 123-456' => '96171123456', '00 44 7700 900123' => '447700900123'] as $number => $digits) {
            $this->from('/admin/settings')->put('/admin/settings', [
                'store_name' => 'Mobile Hub', 'whatsapp_number' => $number,
            ])->assertRedirect('/admin/settings')->assertSessionHasNoErrors();
            $this->assertFalse(Cache::has('home.settings'));
            $this->assertDatabaseHas('settings', ['whatsapp_number' => $number]);
            $this->get('/admin/settings')->assertOk()->assertSee('name="whatsapp_number"', false)->assertSee($number);

            foreach ($pages as $page) {
                $response = $this->get($page)->assertOk()
                    ->assertSee('href="https://wa.me/'.$digits.'"', false)
                    ->assertSee('target="_blank"', false)
                    ->assertSee('rel="noopener noreferrer"', false)
                    ->assertSee('aria-label="Chat with us on WhatsApp (opens in a new tab)"', false);
                $this->assertSame(1, substr_count($response->getContent(), 'class="whatsapp-float"'));
            }
        }

        $this->put('/admin/settings', ['store_name' => 'Mobile Hub', 'whatsapp_number' => ''])
            ->assertSessionHasNoErrors();
        $this->assertFalse(Cache::has('home.settings'));
        $this->assertDatabaseHas('settings', ['whatsapp_number' => null]);
        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertDontSee('class="whatsapp-float"', false)
                ->assertDontSee('has-whatsapp-contact');
        }
    }

    public function test_invalid_numbers_are_rejected_without_changing_settings_or_cache(): void
    {
        $this->signIn();
        Setting::create(['store_name' => 'Mobile Hub', 'whatsapp_number' => '+96171123456']);
        Setting::cached();

        foreach (['123', '071123456', '+00096171123456', str_repeat('1', 16), str_repeat(' ', 31).'1', 'hello', '+96171abc123456', '96171123456?text=spam', '96171123456/evil', '++96171123456', ['96171123456']] as $number) {
            $this->from('/admin/settings')->put('/admin/settings', [
                'store_name' => 'Mobile Hub', 'whatsapp_number' => $number,
            ])->assertSessionHasErrors('whatsapp_number');
            $this->assertSame('+96171123456', Setting::first()->whatsapp_number);
            $this->assertSame('+96171123456', Setting::cached()->whatsapp_number);
        }
    }

    public function test_form_preserves_number_when_another_field_fails_validation(): void
    {
        $this->signIn();
        $this->from('/admin/settings')->put('/admin/settings', [
            'store_name' => '', 'whatsapp_number' => '+961 71 123 456',
        ])->assertSessionHasErrors('store_name');
        $this->get('/admin/settings')->assertSee('value="+961 71 123 456"', false);
    }

    public function test_button_is_absent_on_admin_login_admin_pages_api_and_other_public_routes(): void
    {
        Setting::create(['store_name' => 'Mobile Hub', 'whatsapp_number' => '+96171123456']);
        // A future page using the shared layout must still be excluded.
        Route::get('/contact-test', fn () => view('search', ['categories' => []]))->name('contact.test');
        foreach (['/admin/login', '/contact-test', '/search/products'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('class="whatsapp-float"', false);
        }
        $this->get('/does-not-exist')->assertNotFound()->assertDontSee('class="whatsapp-float"', false);
        $this->signIn();
        foreach (['/admin/products', '/admin/categories', '/admin/settings'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('class="whatsapp-float"', false);
        }
    }

    public function test_missing_empty_or_malformed_stored_numbers_never_render_the_button(): void
    {
        $pages = $this->publicPages();
        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertDontSee('class="whatsapp-float"', false);
        }
        $setting = Setting::create(['store_name' => 'Mobile Hub']);
        foreach ([null, '', '   ', '+', '---', 'javascript:alert(1)', '96171abc123456'] as $number) {
            $setting->update(['whatsapp_number' => $number]);
            Cache::forget('home.settings');
            foreach ($pages as $page) {
                $this->get($page)->assertOk()->assertDontSee('class="whatsapp-float"', false);
            }
        }
    }
}
