<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantFromSubdomain
{
    /**
     * Handle an incoming request and resolve the active tenant from the subdomain.
     */
    public function handle(Request $request, Closure $next): Response
    {
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

                // If user is authenticated, ensure they belong to this tenant
                $user = $request->user();
                if ($user) {
                    if ($user->isSuperAdmin()) {
                        return redirect()->away(config('app.url', 'http://localhost:8000') . '/super-admin');
                    }

                    if ($user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id) {
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
            } elseif ($request->hasSession() && $request->session()->has('active_tenant_slug')) {
                $tenant = Tenant::where('slug', $request->session()->get('active_tenant_slug'))->first();
                if ($tenant) {
                    app()->instance('currentTenant', $tenant);
                    $request->attributes->set('tenant', $tenant);
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

        // If user is authenticated, ensure they belong to this tenant
        $user = $request->user();
        if ($user) {
            // Super admins should use central console
            if ($user->isSuperAdmin()) {
                return redirect()->away(config('app.url', 'http://localhost:8000') . '/super-admin');
            }

            // Cross-tenant credential isolation check
            if ($user->tenant_id && (int) $user->tenant_id !== (int) $tenant->id) {
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
}
