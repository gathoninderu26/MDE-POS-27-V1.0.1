<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminAuthController extends Controller
{
    /**
     * Show the stealth super admin login console.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        return Inertia::render('super-admin/SuperAdminLogin', [
            'status' => $request->session()->get('status'),
            'submitUrl' => '/'.ltrim($request->path(), '/'),
        ]);
    }

    /**
     * Handle stealth super admin authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 900); // 15 min lock

            throw ValidationException::withMessages([
                'email' => 'Access denied. Administrative security clearance failed.',
            ]);
        }

        $user = Auth::user();

        // Must be verified Super Admin
        if (! $user->isSuperAdmin()) {
            Auth::guard('web')->logout();
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'email' => 'Access denied. Administrative security clearance failed.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('super-admin.dashboard'));
    }

    /**
     * Destroy super admin session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
