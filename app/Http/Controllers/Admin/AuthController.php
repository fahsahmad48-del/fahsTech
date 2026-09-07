<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->get('admin_authenticated')) {
            return redirect()->route('admin.products.index');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:72'],
        ]);

        $admin = Admin::first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return back()->withErrors([
                'password' => 'Incorrect password.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);
        $request->session()->put('admin_id', $admin->id);
        $request->session()->put('admin_fingerprint', $admin->sessionFingerprint());

        return redirect()->route('admin.products.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
