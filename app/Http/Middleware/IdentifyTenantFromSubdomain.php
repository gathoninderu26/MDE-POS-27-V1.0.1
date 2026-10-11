<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\DemoDatabaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantFromSubdomain
{
    /**
     * Handle an incoming request and resolve the active tenant from the subdomain.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Guarantee central database connection for tenant resolution
        DB::setDefaultConnection(config('database.default', 'mysql'));

        // ── Single-Tenant Dedicated Machine Lock (XAMPP / Local POS PC) ───────
        if (config('tenant.mode') === 'single' || env('TENANT_MODE') === 'single') {
            $lockedId = config('tenant.locked_id') ?? env('LOCKED_TENANT_ID');
            $lockedSlug = config('tenant.locked_slug') ?? env('LOCKED_TENANT_SLUG');

            $tenant = null;
            if ($lockedId) {
                $tenant = Tenant::find($lockedId);
            }
            if (! $tenant && $lockedSlug) {
                $tenant = Tenant::where('slug', $lockedSlug)->first();
            }
            if (! $tenant) {
                $tenant = Tenant::first();
            }

            if ($tenant) {
                app()->instance('currentTenant', $tenant);
                $request->attributes->set('tenant', $tenant);
                $this->switchToDemoDatabase($tenant);

                // Ensure authenticated user belongs strictly to this bound tenant
                $user = $request->user();
                if ($user && $user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'email' => "This computer is registered strictly to {$tenant->name}. Only accounts from this business may log in.",
                    ]);
                }

                return $next($request);
            }
        }

        $host = $request->getHost();
        $baseDomain = config('app.domain', 'localhost');
        
        // If on the root/central domain itself or direct IP
        if ($host === $baseDomain || $host === '127.0.0.1' || empty($host)) {
            // Support query param or input 'tenant' for local dev or query fallback
            if ($request->filled('tenant')) {
                $tenantRaw = $request->input('tenant');
                $tenantSlug = null;

                if (is_string($tenantRaw)) {
                    $tenantSlug = strtolower(trim($tenantRaw));
                } elseif (is_array($tenantRaw) && isset($tenantRaw['slug']) && is_string($tenantRaw['slug'])) {
                    $tenantSlug = strtolower(trim($tenantRaw['slug']));
                }

                if ($tenantSlug) {
                    $tenant = Tenant::where('slug', $tenantSlug)->first();

                    if (! $tenant) {
                        return Inertia::render('errors/TenantNotFound', [
                            'subdomain' => $tenantSlug,
                            'centralUrl' => config('app.url', 'http://localhost:8000'),
                        ])->toResponse($request)->setStatusCode(404);
                    }

                    if ($tenant->isSuspended()) {
                        return Inertia::render('errors/TenantNotFound', [
                            'subdomain' => $tenantSlug,
                            'tenantName' => $tenant->name,
                            'isSuspended' => true,
                            'centralUrl' => config('app.url', 'http://localhost:8000'),
                        ])->toResponse($request)->setStatusCode(403);
                    }

                    // Bind active tenant to service container
                    app()->instance('currentTenant', $tenant);
                    $request->attributes->set('tenant', $tenant);
                    $this->switchToDemoDatabase($tenant);

                    if ($request->hasSession()) {
                        $request->session()->put('active_tenant_slug', $tenant->slug);
                    }

                    // Tenant users must NEVER access central welcome / landing page
                    if ($request->is('/') || $request->is('welcome')) {
                        if ($request->user()) {
                            return redirect()->route('dashboard');
                        }
                        return redirect()->to($tenant->getSubdomainUrl('/login'));
                    }

                    // If user is authenticated, ensure they belong to this tenant
                    $user = $request->user();
                    if ($user) {
                        // If visiting the login page specifically, allow clean session re-auth instead of redirect loop
                        if ($request->routeIs('login') || $request->is('login')) {
                            if ($user->isSuperAdmin() || ($user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id)) {
                                Auth::guard('web')->logout();
                                $request->session()->invalidate();
                                $request->session()->regenerateToken();
                            }
                        } elseif ($user->isSuperAdmin()) {
                            return redirect()->away(config('app.url', 'http://localhost:8000') . '/super-admin');
                        } elseif ($user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id) {
                            Auth::guard('web')->logout();
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();

                            return redirect()->route('login', ['tenant' => $tenant->slug])->withErrors([
                                'email' => "Your account is not authorized for {$tenant->name}. Please sign in with {$tenant->name} credentials.",
                            ]);
                        }
                    }
                }
            } elseif ($request->user() && $request->user()->tenant) {
                $tenant = $request->user()->tenant;
                app()->instance('currentTenant', $tenant);
                $request->attributes->set('tenant', $tenant);
                $this->switchToDemoDatabase($tenant);
            } elseif ($request->hasSession() && $request->session()->has('active_tenant_slug')) {
                $tenant = Tenant::where('slug', $request->session()->get('active_tenant_slug'))->first();
                if ($tenant) {
                    app()->instance('currentTenant', $tenant);
                    $request->attributes->set('tenant', $tenant);
                    $this->switchToDemoDatabase($tenant);

                    // Tenant users must NEVER access central welcome / landing page
                    if ($request->is('/') || $request->is('welcome')) {
                        if ($request->user()) {
                            return redirect()->route('dashboard');
                        }
                        return redirect()->to($tenant->getSubdomainUrl('/login'));
                    }
                }
            }

            return $next($request);
        }

        // Strip baseDomain from the end of the host
        $subdomainPart = preg_replace('/\.' . preg_quote($baseDomain, '/') . '$/i', '', $host);

        // If there were nested subdomains (e.g. store.previous.localhost), get the primary leftmost part
        $parts = explode('.', $subdomainPart);
        $subdomain = strtolower(trim($parts[0]));

        // If on root or www
        if ($subdomain === $baseDomain || empty($subdomain) || $subdomain === 'www') {
            return $next($request);
        }

        // Query active tenant by slug
        $tenant = Tenant::where('slug', $subdomain)->first();

        if (! $tenant) {
            return Inertia::render('errors/TenantNotFound', [
                'subdomain' => $subdomain,
                'centralUrl' => config('app.url', 'http://localhost:8000'),
            ])->toResponse($request)->setStatusCode(404);
        }

        if ($tenant->isSuspended()) {
            return Inertia::render('errors/TenantNotFound', [
                'subdomain' => $subdomain,
                'tenantName' => $tenant->name,
                'isSuspended' => true,
                'centralUrl' => config('app.url', 'http://localhost:8000'),
            ])->toResponse($request)->setStatusCode(403);
        }

        // Bind active tenant to service container
        app()->instance('currentTenant', $tenant);
        $request->attributes->set('tenant', $tenant);
        $this->switchToDemoDatabase($tenant);

        if ($request->hasSession()) {
            $request->session()->put('active_tenant_slug', $tenant->slug);
        }

        $user = $request->user();

        // Subdomain visitors must NEVER access central welcome / landing page
        if ($request->is('/') || $request->is('welcome')) {
            if ($user) {
                return redirect()->route('dashboard');
            }
            return redirect()->to($tenant->getSubdomainUrl('/login'));
        }

        // If user is authenticated, ensure they belong to this tenant
        if ($user) {
            if ($request->routeIs('login') || $request->is('login')) {
                if ($user->isSuperAdmin() || ($user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id)) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            } elseif ($user->isSuperAdmin()) {
                // Super admins should use central console
                return redirect()->away(config('app.url', 'http://localhost:8000') . '/super-admin');
            } elseif ($user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id) {
                // Cross-tenant credential isolation check
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => "Your account is not authorized for {$tenant->name}. Please sign in with {$tenant->name} credentials.",
                ]);
            }
        }

        return $next($request);
    }

    /**
     * When the resolved tenant is a demo, point the 'demo' connection at a
     * per-tenant SQLite file and make it the default Eloquent connection.
     *
     * Sessions (SESSION_CONNECTION=mysql), Cache, and Queue use their own
     * explicit connection config and are never affected by this switch.
     *
     * Real tenants never enter this branch — their MySQL connection is unchanged.
     */
    protected function switchToDemoDatabase(Tenant $tenant): void
    {
        DemoDatabaseService::switchTo($tenant);
    }

    /**
     * Clean up and reset default database connection to MySQL after request finishes.
     */
    public function terminate(Request $request, Response $response): void
    {
        DB::setDefaultConnection(config('database.default', 'mysql'));
    }
}
