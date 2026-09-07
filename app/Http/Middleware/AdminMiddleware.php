<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $admin = $request->session()->get('admin_authenticated')
            ? Admin::find($request->session()->get('admin_id'))
            : null;

        if (! $admin || ! hash_equals($admin->sessionFingerprint(), (string) $request->session()->get('admin_fingerprint', ''))) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please sign in again.'], 401);
            }

            return redirect()->route('admin.login');
        }

        $request->attributes->set('admin', $admin);

        return $next($request);
    }
}
