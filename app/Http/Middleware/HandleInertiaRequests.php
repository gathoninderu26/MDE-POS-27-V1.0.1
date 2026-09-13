<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        // Resolve tenant from service container (if on a subdomain) or from the authenticated user
        $tenant = app()->bound('currentTenant')
            ? app('currentTenant')
            : ($user?->tenant);

        // Build permission matrix for the user's role (mirrors usePermissions.ts)
        $permissions = $user && $user->isTenantUser()
            ? $this->buildPermissions($user->role ?? 'Owner')
            : [];

        $demoSession = $request->session()->get('demo_session');
        $isDemoTenant = (bool) ($tenant?->is_demo ?? false);
        $isDemo = $isDemoTenant || ! empty($demoSession['is_demo']);

        $demoData = null;
        if ($isDemo) {
            $demoDays = $demoSession['days'] ?? 7;
            $demoExpiresAt = $demoSession['expires_at'] ?? now()->addDays($demoDays)->toIso8601String();
            $detectedMode = $demoSession['data_mode'] ?? (str_contains($tenant?->slug ?? '', 'clean') ? 'own' : 'mock');
            $demoData = [
                'is_demo'    => true,
                'days'       => $demoDays,
                'expires_at' => $demoExpiresAt,
                'data_mode'  => $detectedMode,
                'tenant_slug' => $tenant?->slug,
                'demo_name'  => $tenant ? "[DEMO] {$tenant->name}" : 'Demo Account',
            ];
        }

        $tenantDisplayName = $tenant?->name;
        if ($isDemo && $tenantDisplayName && ! str_starts_with($tenantDisplayName, '[DEMO]')) {
            $tenantDisplayName = "[DEMO] {$tenantDisplayName}";
        }

        return array_merge(parent::share($request), [
            'name'        => config('app.name'),
            'quote'       => ['message' => trim($message), 'author' => trim($author)],
            'isSubdomain' => app()->bound('currentTenant'),
            'demo'        => $demoData,
            'tenant'      => $tenant ? [
                'id'              => (string) $tenant->id,
                'name'            => $tenantDisplayName,
                'slug'            => $tenant->slug,
                'subdomain_url'   => $tenant->getSubdomainUrl(),
                'email'           => $tenant->email,
                'phone'           => $tenant->phone,
                'kra_pin'         => $tenant->kra_pin,
                'business_type'   => $tenant->business_type,
                'plan'            => $tenant->plan,
                'currency'        => $tenant->currency ?? 'KES',
                'status'          => $tenant->status,
                'enabled_modules' => $tenant->enabled_modules ?? [],
            ] : null,
            'auth'        => [
                'user' => $user ? [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'role'           => $user->role,
                    'is_super_admin' => $user->is_super_admin,
                    'phone'          => $user->phone,
                    'status'         => $user->status,
                    'tenant_id'      => $user->tenant_id,
                ] : null,
                'tenant' => $tenant ? [
                    'id'              => (string) $tenant->id,
                    'name'            => $tenantDisplayName,
                    'slug'            => $tenant->slug,
                    'subdomain_url'   => $tenant->getSubdomainUrl(),
                    'email'           => $tenant->email,
                    'phone'           => $tenant->phone,
                    'kra_pin'         => $tenant->kra_pin,
                    'business_type'   => $tenant->business_type,
                    'plan'            => $tenant->plan,
                    'currency'        => $tenant->currency ?? 'KES',
                    'status'          => $tenant->status,
                    'enabled_modules' => $tenant->enabled_modules ?? [],
                ] : null,
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Build the permission matrix for a given role.
     * Matches the structure in usePermissions.ts exactly.
     *
     * @return array<string, array<string, bool>>
     */
    private function buildPermissions(?string $role): array
    {
        $role = $role ?: 'Owner';
        $matrix = [
            'Owner' => [
                'pos'       => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'sales'     => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'products'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'inventory' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'purchases' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'customers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'suppliers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'expenses'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'reports'   => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'users'     => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'branches'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'settings'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
            ],
            'Administrator' => [
                'pos'       => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'sales'     => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'products'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'inventory' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'purchases' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'customers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'suppliers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'expenses'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'reports'   => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'users'     => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'branches'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'settings'  => ['view' => true,  'create' => false, 'edit' => true,  'delete' => false],
            ],
            'Manager' => [
                'pos'       => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'sales'     => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'products'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'inventory' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'purchases' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'customers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'suppliers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'expenses'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'reports'   => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'users'     => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'branches'  => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'settings'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ],
            'Cashier' => [
                'pos'       => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
                'sales'     => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'products'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'inventory' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'purchases' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'customers' => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
                'suppliers' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'expenses'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'reports'   => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'users'     => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'branches'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'settings'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ],
            'Inventory Manager' => [
                'pos'       => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'sales'     => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'products'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'inventory' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'purchases' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'customers' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'suppliers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'expenses'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'reports'   => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'users'     => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'branches'  => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'settings'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ],
            'Accountant' => [
                'pos'       => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'sales'     => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'products'  => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'inventory' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'purchases' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'customers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'suppliers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'expenses'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'reports'   => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'users'     => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'branches'  => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'settings'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ],
            'Supervisor' => [
                'pos'       => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'sales'     => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'products'  => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
                'inventory' => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
                'purchases' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'customers' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => false],
                'suppliers' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'expenses'  => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
                'reports'   => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
                'users'     => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'branches'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'settings'  => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ];

        return $matrix[$role] ?? $matrix['Cashier'];
    }
}
