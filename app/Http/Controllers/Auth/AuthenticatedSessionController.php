<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     * Super admins → /super-admin, tenant users → /dashboard.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Super Admin credentials must only be used on the secret Super Admin gateway
        if ($user->isSuperAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Super Administrator accounts must sign in via the dedicated Platform Super Admin Gateway at /super-admin/login.',
            ]);
        }

        // ── Demo / subscription expiry gate ──────────────────────────────────
        // Load the tenant and check whether their account has lapsed.
        $tenant = \App\Models\Tenant::find($user->tenant_id);
        if ($tenant) {
            $isStatusExpired = in_array($tenant->status, ['Expired', 'Suspended', 'Inactive']);
            $isBillingLapsed = $tenant->next_billing_date && $tenant->next_billing_date->isPast();

            if ($isStatusExpired || $isBillingLapsed) {
                // Log the user back out — they must not be allowed in.
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'account_expired' => 'Your demo account has expired.',
                ]);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        $request->session()->regenerate();

        if ($tenant) {
            $request->session()->put('active_tenant_id', $tenant->id);
            $request->session()->put('active_tenant_slug', $tenant->slug);
            app()->instance('currentTenant', $tenant);
            $request->attributes->set('tenant', $tenant);

            if ($tenant->is_demo) {
                $days = 7;
                $request->session()->put('demo_session', [
                    'is_demo'     => true,
                    'days'        => $days,
                    'expires_at'  => now()->addDays($days)->toIso8601String(),
                    'tenant_slug' => $tenant->slug,
                    'tenant_name' => "[DEMO] {$tenant->name}",
                    'role'        => $user->role,
                    'persona'     => $user->role,
                ]);
            }
        }

        // Cashiers open POS terminal by default
        if ($user->role === 'Cashier') {
            return redirect()->intended(route('pos', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Authenticate directly into one of the two supported demo business accounts.
     */
    public function demoLogin(Request $request): RedirectResponse
    {
        $rawTenant = $request->input('tenant') ?: $request->query('tenant');
        if (is_array($rawTenant)) {
            $rawTenant = $rawTenant['slug'] ?? 'nairobi-fresh-mart';
        }
        $tenantSlug = is_string($rawTenant) && trim($rawTenant) !== '' ? strtolower(trim($rawTenant)) : 'nairobi-fresh-mart';

        $rawRole = $request->input('role') ?: $request->query('role');
        if (is_array($rawRole)) {
            $rawRole = $rawRole['id'] ?? 'Cashier';
        }
        $role = is_string($rawRole) && trim($rawRole) !== '' ? trim($rawRole) : 'Cashier';

        // Data mode: 'mock' (pre-seeded sample data) or 'own' (clean slate, 0 mock records)
        $rawMode = $request->input('data_mode') ?: $request->query('data_mode', 'mock');
        $dataMode = in_array($rawMode, ['mock', 'own', 'no_mock', 'clean']) ? $rawMode : 'mock';
        $isCleanMode = in_array($dataMode, ['own', 'no_mock', 'clean']);

        // STRICT SECURITY GATE: If a real commercial tenant slug is requested, reject immediately
        $requestedTenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
        if ($requestedTenant && ! $requestedTenant->is_demo) {
            abort(403, 'Unauthorized access. Live production merchant accounts cannot be accessed via demo login.');
        }

        // Resolve isolated tenant slug based on business type and data mode
        if (str_contains($tenantSlug, 'metro') || str_contains($tenantSlug, 'wholesale')) {
            $effectiveSlug = $isCleanMode ? 'metro-wholesale-clean' : 'metro-wholesale-retail';
        } elseif (str_contains($tenantSlug, 'nairobi') || str_contains($tenantSlug, 'fresh') || $tenantSlug === '') {
            $effectiveSlug = $isCleanMode ? 'nairobi-fresh-clean' : 'nairobi-fresh-mart';
        } else {
            // Any other requested slug that is not a recognized demo
            abort(403, 'Unauthorized access. Only official demo sandboxes can be accessed via demo login.');
        }

        $tenant = \App\Models\Tenant::where('slug', $effectiveSlug)->first();
        if (! $tenant) {
            abort(404, 'Demo tenant sandbox not found.');
        }

        if (! $tenant->is_demo) {
            abort(403, 'Unauthorized access. Live production merchant accounts cannot be accessed via demo login.');
        }

        // ── Switch to the demo SQLite BEFORE looking up users ────────────────
        // This is critical: user IDs in the SQLite file differ from MySQL.
        // Auth::login() stores the user ID in the session; on subsequent requests
        // the middleware switches back to this same SQLite, so the ID must match.
        \App\Services\DemoDatabaseService::switchTo($tenant);
        app()->instance('currentTenant', $tenant);
        $request->attributes->set('tenant', $tenant);
        // ─────────────────────────────────────────────────────────────────────

        // Normalize role name
        $roleMap = [
            'owner' => 'Owner',
            'business owner' => 'Owner',
            'manager' => 'Manager',
            'store manager' => 'Manager',
            'cashier' => 'Cashier',
            'inventory' => 'Inventory Manager',
            'inventory manager' => 'Inventory Manager',
            'administrator' => 'Administrator',
            'admin' => 'Administrator',
            'accountant' => 'Accountant',
            'supervisor' => 'Supervisor',
        ];
        $targetRole = $roleMap[strtolower(trim($role))] ?? ucfirst($role);

        // Find staff user by target role or default to first tenant user
        // (runs against the demo SQLite connection now active)
        $user = \App\Models\User::where('tenant_id', $tenant->id)
            ->where(function ($q) use ($targetRole) {
                $q->where('role', $targetRole)
                  ->orWhere('role', 'like', "%{$targetRole}%");
            })
            ->first()
            ?: \App\Models\User::where('tenant_id', $tenant->id)->first();

        if (! $user) {
            // Create default staff user for this tenant if none exists
            $user = \App\Models\User::create([
                'name' => "Demo {$targetRole}",
                'email' => strtolower(str_replace(' ', '', $targetRole)) . "@{$tenant->slug}.mdepos.co.ke",
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => $targetRole,
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]);
        }

        $days = (int) ($request->input('days') ?: $request->query('days', 7));
        if ($days < 1) {
            $days = 7;
        }
        $expiresAt = now()->addDays($days);

        // Data mode: 'mock' (pre-seeded sample data) or 'own' (clean slate)
        $sessionDataMode = $isCleanMode ? 'own' : 'mock';

        // If already logged in under another user or session, cleanly log out first
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Ensure current active tenant slug is bound for domainless local environments
        $request->session()->put('active_tenant_id', $tenant->id);
        $request->session()->put('active_tenant_slug', $tenant->slug);

        $request->session()->put('demo_session', [
            'is_demo'     => true,
            'days'        => $days,
            'expires_at'  => $expiresAt->toIso8601String(),
            'tenant_slug' => $tenant->slug,
            'tenant_name' => "[DEMO] {$tenant->name}",
            'data_mode'   => $sessionDataMode,
            'role'        => $targetRole,
            'persona'     => $targetRole,
        ]);

        // Redirect directly to POS register for Cashier role, or dashboard for others
        if ($user->role === 'Cashier' || strtolower($role) === 'cashier') {
            return redirect()->route('pos', ['tenant' => $tenant->slug]);
        }

        return redirect()->route('dashboard', ['tenant' => $tenant->slug]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
