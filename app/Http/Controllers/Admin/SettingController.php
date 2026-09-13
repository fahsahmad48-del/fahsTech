<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\WhatsAppNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    // Show settings
    public function index()
    {
        $settings = Setting::first();

        return view('admin.settings.index', compact('settings'));
    }

    // Update store settings
    public function update(Request $request)
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'offer_text' => ['nullable', 'string', 'max:500'],
            'about_title' => ['nullable', 'string', 'max:150'],
            'about_text' => ['nullable', 'string', 'max:3000'],
            'whatsapp_number' => ['bail', 'nullable', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (WhatsAppNumber::normalize($value) === null) {
                    $fail('Enter a WhatsApp number with country code and 7 to 15 digits. Spaces, +, dashes, dots and parentheses are allowed.');
                }
            }],
        ]);

        $settings = Setting::firstOrNew();
        $settings->fill($validated); 
        $settings->save();

        Cache::forget('home.settings');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Settings updated successfully.']);
        }

        return back()->with('success', 'Store settings updated successfully.');
    }

    // Change admin password
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:72'],
            'new_password' => ['bail', 'required', 'string', 'min:8', 'max:72', 'confirmed', 'different:current_password', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The new password must not exceed 72 bytes.');
                }
            }],
        ]);

        $admin = $request->attributes->get('admin');

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $admin->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        $request->session()->regenerate();
        $request->session()->put('admin_fingerprint', $admin->sessionFingerprint());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password changed successfully.',
                'csrf_token' => $request->session()->token(),
            ]);
        }

        return back()->with('success', 'Admin password changed successfully.');
    }
}
