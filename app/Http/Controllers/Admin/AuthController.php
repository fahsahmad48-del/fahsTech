<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Rules\BcryptPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Show the login page. If the admin is already logged in, open the product list. */
    public function showLogin(Request $request)
    {
        if ($request->session()->get('admin_authenticated')) {
            return redirect()->route('admin.products.index');
        }

        return view('admin.login');
    }

    /** Check the password and log the admin in. */
    public function login(Request $request)
    {
        // Check the password before using it. bail stops checking after the first error.
        // BcryptPassword rejects passwords over 72 bytes and the special null character.
        $validated = $request->validate([
            'password' => ['bail', 'required', 'string', 'max:72', new BcryptPassword], 
        ]);

        // Get the store's admin account from the database.
        $admin = Admin::first();

        // Check whether the typed password matches the saved password.
        // Show the same error if the account is missing or the password is wrong.
        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return back()->withErrors([
                'password' => 'Incorrect password.',
            ]);
        }

        // Give this login a new session ID for security.
        // The session is where the site remembers that this browser is logged in.
        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);
        $request->session()->put('admin_id', $admin->id);
        // Save a value based on the current password.
        // If the password changes, older logins will no longer match this value.
        $request->session()->put('admin_fingerprint', $admin->sessionFingerprint());

        return redirect()->route('admin.products.index');
    }

    /** Log the admin out and open the login page. */
    public function logout(Request $request)
    {
        // Remove the login information. invalidate() also clears the rest of the session.
        $request->session()->forget('admin_authenticated');
        $request->session()->invalidate();
        // Create a new form security code (CSRF token).
        // This helps stop other websites from sending forms as this user.
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
