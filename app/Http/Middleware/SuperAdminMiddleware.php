<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Only allow platform-level super admins.
     * Any unauthenticated user or non-super-admin user is rejected with a 404
     * so that the existence of the Super Admin console remains completely stealth and hidden.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('super-admin.login');
        }

        if (! $user->isSuperAdmin()) {
            abort(403, 'Access denied. Administrative security clearance required.');
        }

        return $next($request);
    }
}
