<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Rules\BcryptPassword;
use App\Support\WhatsAppNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /** Show the store settings form with the current saved values. */
    public function index()
    {
        $settings = Setting::first();

        return view('admin.settings.index', compact('settings'));
    }

    /** Check and save the store settings, then clear the old saved copy. */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'offer_text' => ['nullable', 'string', 'max:500'],
            'about_title' => ['nullable', 'string', 'max:150'],
            'about_text' => ['nullable', 'string', 'max:3000'],
            // bail stops at the first error. nullable means the number can be left empty.
            // Check that the number can be written in the format WhatsApp needs.
            'whatsapp_number' => ['bail', 'nullable', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (WhatsAppNumber::normalize($value) === null) {
                    $fail('Enter a WhatsApp number with country code and 7 to 15 digits. Spaces, +, dashes, dots and parentheses are allowed.');
                }
            }],
        ]);

        // Get the saved settings, or prepare a new row if there are none.
        // fill() puts the form values in the settings. save() writes them to the database.
        $settings = Setting::firstOrNew();
        $settings->fill($validated);
        $settings->save();

        // Remove the old saved copy so the next page visit loads the new settings.
        Cache::forget('home.settings');

        // Send the message to JavaScript, or go back and show it on the page.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Settings updated successfully.']);
        }

        return back()->with('success', 'Store settings updated successfully.');
    }

    /** Check the old password and save the new one, while keeping this browser logged in. */
    public function updatePassword(Request $request)
    {
        // The new password and confirmation must match. The old password cannot be reused.
        // BcryptPassword rejects passwords over 72 bytes and the special null character.
        $validated = $request->validate([
            'current_password' => ['bail', 'required', 'string', 'max:72', new BcryptPassword],
            'new_password' => ['bail', 'required', 'string', 'min:8', 'max:72', 'confirmed', 'different:current_password', new BcryptPassword],
        ]);

        // Get the admin account that was checked before this function ran.
        $admin = $request->attributes->get('admin');

        // Check the typed password against the saved password hash.
        // A hash is a protected value saved instead of the actual password.
        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $admin->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // Give this login a new session ID and remember the new password's fingerprint.
        // This browser stays logged in. Other logins must sign in again.
        $request->session()->regenerate();
        $request->session()->put('admin_fingerprint', $admin->sessionFingerprint()); 

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password changed successfully.',
                // Send the new form security code so JavaScript can use it for the next form.
                'csrf_token' => $request->session()->token(),
            ]);
        }

        return back()->with('success', 'Admin password changed successfully.');
    }
}
