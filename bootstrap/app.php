<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenantFromSubdomain;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            IdentifyTenantFromSubdomain::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'mpesa/*',
            'demo-login',
        ]);

        // Register named middleware aliases
        $middleware->alias([
            'super_admin' => SuperAdminMiddleware::class,
            'tenant_user' => EnsureTenantUser::class,
            'module' => EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // When a CSRF session expires (419), redirect back with a fresh session/token
            if ($status === 419 && ! $request->expectsJson()) {
                return back()->with([
                    'message' => 'Your session expired. Please try again.',
                ]);
            }

            // Render Inertia error page for common HTTP error codes (not for API requests)
            if (
                in_array($status, [403, 404, 500, 503])
                && ! $request->expectsJson()
                && ! $request->is('_ignition/*')
            ) {
                return Inertia::render("errors/{$status}", ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
