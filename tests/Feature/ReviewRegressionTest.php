<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase; 

    private function signIn(): void
    {
        Admin::create(['password' => 'review-password']);
        $this->post('/admin/login', ['password' => 'review-password'])->assertRedirect('/admin/products');
    }

    public function test_category_validation_redirect_handles_array_input(): void
    {
        $this->signIn();
        $this->from('/admin/categories')->post('/admin/categories', ['name' => ['invalid']])
            ->assertRedirect('/admin/categories');
        $this->get('/admin/categories')->assertOk()->assertSee('must be a string');
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_settings_validation_redirect_handles_array_inputs(): void
    {
        $this->signIn();
        $this->from('/admin/settings')->put('/admin/settings', array_fill_keys([
            'store_name', 'tagline', 'offer_text', 'about_title', 'about_text', 'whatsapp_number',
        ], ['invalid']))->assertRedirect('/admin/settings');
        $this->get('/admin/settings')->assertOk()->assertSee('must be a string');
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_device_validation_feedback_survives_a_browser_redirect(): void
    {
        $this->signIn();
        $this->from('/admin/products')->post('/admin/products/devices', ['_form' => 'device'])
            ->assertRedirect('/admin/products');
        $this->get('/admin/products')->assertOk()->assertSee('Please select the image again');
    }

    public function test_login_rejects_passwords_beyond_bcrypt_byte_limit(): void
    {
        $password = str_repeat('é', 36);
        Admin::create(['password' => $password]);
        $this->postJson('/admin/login', ['password' => $password.'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->getJson('/admin/products')->assertUnauthorized();
        $this->post('/admin/login', ['password' => $password])->assertRedirect('/admin/products');
    }

    public function test_password_change_rejects_null_bytes_without_changing_password(): void
    {
        $this->signIn();
        $this->putJson('/admin/settings/password', [
            'current_password' => 'review-password', 'new_password' => "invalid\0password",
            'new_password_confirmation' => "invalid\0password",
        ])->assertUnprocessable()->assertJsonValidationErrors('new_password');
        $this->assertTrue(Hash::check('review-password', Admin::first()->password));
    }

    public function test_password_change_requires_the_exact_current_password(): void
    {
        $password = str_repeat('é', 36);
        $admin = Admin::create(['password' => $password]);
        $this->post('/admin/login', ['password' => $password])->assertRedirect('/admin/products');
        $this->putJson('/admin/settings/password', [
            'current_password' => $password.'x', 'new_password' => 'replacement-password',
            'new_password_confirmation' => 'replacement-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check($password, $admin->fresh()->password));
    }
}
