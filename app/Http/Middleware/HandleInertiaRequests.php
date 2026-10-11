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
                'is_demo'     => true,
                'days'        => $demoDays,
                'expires_at'  => $demoExpiresAt,
                'data_mode'   => $detectedMode,
                'tenant_slug' => $tenant?->slug,
                'demo_name'   => $tenant ? "[DEMO] {$tenant->name}" : 'Demo Account',
                'role'        => $demoSession['role'] ?? null,
                'persona'     => $demoSession['persona'] ?? $demoSession['role'] ?? null,
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
            'flash'       => [
                'success'            => fn () => $request->session()->get('success'),
                'error'              => fn () => $request->session()->get('error'),
                'warning'            => fn () => $request->session()->get('warning'),
                'info'               => fn () => $request->session()->get('info'),
                'createdCredentials' => fn () => $request->session()->get('createdCredentials'),
            ],
            'tenant'      => $tenant ? [
                'id'              => (string) $tenant->id,
                'name'            => $tenantDisplayName,
                'slug'            => $tenant->slug,
                'subdomain_url'   => $tenant->getSubdomainUrl(),
                'email'           => $tenant->email,
                'phone'           => $tenant->phone,
                'address'         => $tenant->address,
                'kra_pin'         => $tenant->kra_pin,
                'business_type'   => $tenant->business_type,
                'is_demo'         => $isDemoTenant,
                'plan'            => $tenant->plan,
                'currency'        => $tenant->currency ?? 'KES',
                'status'          => $tenant->status,
                'branch_count'    => $tenant->branch_count ?? 1,
                'is_multi_branch' => $tenant->isMultiBranch(),
                'enabled_modules' => $tenant->enabled_modules ?? [],
                'device_policy'   => $tenant->device_policy ?? [],
                'network_policy'  => $tenant->network_policy ?? [],
                'settings'        => $tenant->settings ?? [],
                'next_billing_date' => $tenant->next_billing_date?->toIso8601String(),
                'expires_at'      => $isDemo ? ($demoExpiresAt ?? null) : $tenant->next_billing_date?->toIso8601String(),
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
                'is_impersonating' => (bool) $request->session()->has('impersonated_by_admin_id'),
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
                    'branch_count'    => $tenant->branch_count ?? 1,
                    'is_multi_branch' => $tenant->isMultiBranch(),
                    'enabled_modules' => $tenant->enabled_modules ?? [],
                    'device_policy'   => $tenant->device_policy ?? [],
                    'network_policy'  => $tenant->network_policy ?? [],
                    'settings'        => $tenant->settings ?? [],
                ] : null,
                'permissions' => $permissions,
                'sync' => [
                    'mode' => config('tenant.mode', 'multi'),
                    'is_single_tenant' => config('tenant.mode') === 'single',
                    'pending_outbox' => rescue(fn () => \App\Models\SyncOutbox::where('status', 'pending')->count(), 0),
                ],
            ],
            'badgeCounts' => rescue(function () use ($tenant) {
                if (! $tenant) {
                    return [
                        'todaySales'       => 0,
                        'totalProducts'    => 0,
                        'totalCategories'  => 0,
                        'lowStock'         => 0,
                        'pendingPurchases' => 0,
                        'totalSuppliers'   => 0,
                        'totalCustomers'   => 0,
                        'todayExpenses'    => 0,
                        'activeClockIns'   => 0,
                        'totalBranches'    => 0,
                    ];
                }

                // Real queries — for demo tenants the default connection is already
                // switched to their isolated SQLite file by IdentifyTenantFromSubdomain.
                // For real tenants this runs against MySQL as always.
                return [
                    'todaySales'       => \App\Models\Sale::where('tenant_id', $tenant->id)->whereDate('created_at', today())->count(),
                    'totalProducts'    => \App\Models\Product::where('tenant_id', $tenant->id)->count(),
                    'totalCategories'  => \App\Models\ProductCategory::where('tenant_id', $tenant->id)->count(),
                    'lowStock'         => \App\Models\Product::where('tenant_id', $tenant->id)->whereColumn('stock_quantity', '<=', 'reorder_level')->count(),
                    'pendingPurchases' => \App\Models\Purchase::where('tenant_id', $tenant->id)->whereIn('status', ['Pending', 'Draft', 'Ordered'])->count(),
                    'totalSuppliers'   => \App\Models\Supplier::where('tenant_id', $tenant->id)->count(),
                    'totalCustomers'   => \App\Models\Customer::where('tenant_id', $tenant->id)->count(),
                    'todayExpenses'    => \App\Models\Expense::where('tenant_id', $tenant->id)->whereDate('date', today())->count(),
                    'activeClockIns'   => \App\Models\ClockSession::where('tenant_id', $tenant->id)->where('status', 'Active')->count(),
                    'totalBranches'    => \App\Models\Branch::where('tenant_id', $tenant->id)->count(),
                ];
            }, [
                'todaySales'       => 0,
                'totalProducts'    => 0,
                'totalCategories'  => 0,
                'lowStock'         => 0,
                'pendingPurchases' => 0,
                'totalSuppliers'   => 0,
                'totalCustomers'   => 0,
                'todayExpenses'    => 0,
                'activeClockIns'   => 0,
                'totalBranches'    => 0,
            ]),
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
