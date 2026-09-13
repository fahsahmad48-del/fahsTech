<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeOfferTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        Admin::create(['password' => 'original-password']);
        $this->post('/admin/login', ['password' => 'original-password'])->assertRedirect();
    }

    public function test_ajax_save_updates_the_cached_home_offer_and_preserves_other_settings(): void
    {
        $this->signIn();
        $setting = Setting::create(['store_name' => 'Fahs Store', 'tagline' => 'Welcome',
            'about_title' => 'About us', 'about_text' => 'Our store', 'whatsapp_number' => '+96171123456']);
        Setting::cached();
        $offer = 'عروض مميزة على الأجهزة والعطور — Special offers';
        $this->withHeader('Accept', 'application/json')->post('/admin/settings', [
            '_method' => 'PUT', 'store_name' => 'Fahs Store', 'offer_text' => $offer,
        ])->assertOk()->assertJsonPath('message', 'Settings updated successfully.')->assertHeaderMissing('Location');
        $this->assertFalse(Cache::has('home.settings'));
        $this->assertSame($offer, $setting->fresh()->offer_text);
        $this->assertSame($offer, Setting::cached()->offer_text);
        $this->assertSame('Welcome', $setting->fresh()->tagline);
        $this->assertSame('About us', $setting->fresh()->about_title);
        $this->assertSame('Our store', $setting->fresh()->about_text);
        $this->assertSame('+96171123456', $setting->fresh()->whatsapp_number);
        $this->get('/')->assertOk()->assertSeeInOrder(['site-header', 'id="offerBar"', 'class="hero"'], false)->assertSee($offer);
        $this->get('/admin/settings')->assertOk()->assertSee('name="offer_text"', false)
            ->assertSee('maxlength="500"', false)->assertSee($offer);
        $this->putJson('/admin/settings', ['store_name' => 'Fahs Store', 'offer_text' => ''])->assertOk();
        $this->assertNull($setting->fresh()->offer_text);
        $this->get('/')->assertOk()->assertDontSee('id="offerBar"', false);
    }

    public function test_offer_is_absent_for_missing_null_empty_and_whitespace_settings(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="offerBar"', false);
        $setting = Setting::create(['store_name' => 'Fahs Store']);
        foreach ([null, '', " \t\n "] as $text) {
            $setting->update(['offer_text' => $text]);
            Cache::forget('home.settings');
            $this->get('/')->assertOk()->assertDontSee('id="offerBar"', false)->assertDontSee('js/home.js');
        }
    }

    public function test_offer_is_escaped_and_only_appears_on_home(): void
    {
        $offer = '<img src=x onerror="alert(1)"> & "Sale"';
        Setting::create(['store_name' => 'Fahs Store', 'offer_text' => $offer]);
        $response = $this->get('/')->assertOk()->assertSee($offer)->assertDontSee($offer, false);
        $this->assertSame(2, substr_count($response->getContent(), e($offer)));
        $this->get('/search')->assertOk()->assertDontSee('id="offerBar"', false);
        $this->get('/perfumes')->assertOk()->assertDontSee('id="offerBar"', false);
    }

    public function test_json_validation_rejects_invalid_offers_without_changing_the_cache(): void
    {
        $this->signIn();
        Setting::create(['store_name' => 'Fahs Store', 'offer_text' => 'Original']);
        Setting::cached();
        foreach ([str_repeat('x', 501), str_repeat('ع', 501), ['invalid']] as $text) {
            $this->putJson('/admin/settings', ['store_name' => 'Fahs Store', 'offer_text' => $text])
                ->assertUnprocessable()->assertJsonValidationErrors('offer_text');
            $this->assertSame('Original', Setting::first()->offer_text);
            $this->assertSame('Original', Setting::cached()->offer_text);
        }
        $this->putJson('/admin/settings', ['store_name' => 'Fahs Store', 'offer_text' => str_repeat('ع', 500)])->assertOk();
        $this->assertSame(str_repeat('ع', 500), Setting::first()->offer_text);
    }

    public function test_migration_adds_only_the_nullable_offer_and_rolls_back_without_losing_settings(): void
    {
        $migration = require database_path('migrations/2026_09_13_000000_add_offer_text_to_settings_table.php');
        $migration->down();
        $before = Schema::getColumnListing('settings');
        Setting::create(['store_name' => 'Existing store', 'tagline' => 'Keep me']);
        $migration->up();
        $this->assertSame(['offer_text'], array_values(array_diff(Schema::getColumnListing('settings'), $before)));
        $this->assertNull(Setting::first()->offer_text);
        $migration->down();
        $this->assertSame($before, Schema::getColumnListing('settings'));
        $this->assertDatabaseHas('settings', ['store_name' => 'Existing store', 'tagline' => 'Keep me']);
    }
}
