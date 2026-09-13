<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUser
{
    /**
     * Ensure the authenticated user belongs to a tenant.
     * Super admins accessing tenant routes are redirected to their console.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Super admins should be in /super-admin, not in tenant pages
        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        // If user has no tenant assigned yet, assign them to the default active tenant
        if (! $user->tenant_id || ! $user->tenant) {
            $defaultTenant = Tenant::active()->first() ?? Tenant::first();
            if ($defaultTenant) {
                $user->update([
                    'tenant_id' => $defaultTenant->id,
                    'role'      => $user->role ?: 'Owner',
                ]);
                $user->load('tenant');
            } else {
                Auth::guard('web')->logout();
                return redirect()->route('login')->withErrors([
                    'email' => 'No active tenant account found. Please contact support.',
                ]);
            }
        }

        if ($user->tenant && $user->tenant->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your tenant account has been suspended. Please contact support.',
            ]);
        }

        // Cross-subdomain security check: ensure user belongs to active subdomain tenant
        if (app()->bound('currentTenant')) {
            $activeTenant = app('currentTenant');
            if ((int) $user->tenant_id !== (int) $activeTenant->id) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => "Your account is not registered under {$activeTenant->name}.",
                ]);
            }
        }

        return $next($request);
    }
}
