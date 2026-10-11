<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /**
     * Handle an incoming request.
     * Checks if the active tenant has the required module enabled.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $tenant = $request->user()?->tenant ?? (app()->bound('currentTenant') ? app('currentTenant') : null);

        if ($tenant) {
            // Check if ANY of the listed modules is enabled for this tenant
            $hasAccess = false;
            foreach ($modules as $module) {
                if ($tenant->hasModule($module)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (! $hasAccess) {
                $labels = implode(' or ', array_map(fn ($m) => ucfirst($m), $modules));

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => "The '{$labels}' page is not enabled for this business.",
                    ], 403);
                }

                return redirect()->route('dashboard')->with('error', "The {$labels} module is not enabled for your business plan. Please contact your Super Administrator to activate this page.");
            }
        }

        return $next($request);
    }
}
