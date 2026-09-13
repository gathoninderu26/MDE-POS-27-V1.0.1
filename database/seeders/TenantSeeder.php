<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Each tenant gets:
     * - A record in the tenants table
     * - One Owner user (email: owner@{slug}.mdepos.co.ke, password: password)
     *
     * TENANT ISOLATION NOTE:
     * Every user is bound to exactly one tenant via tenant_id.
     * Even if two tenants have the same business_type, their data
     * is completely isolated at the DB level by this FK.
     */
    public function run(): void
    {
        $allModules = [
            'pos', 'sales', 'products', 'inventory', 'purchases',
            'customers', 'suppliers', 'expenses', 'reports',
            'users', 'branches', 'settings',
        ];

        $restaurantModules = [
            'pos', 'sales', 'products', 'inventory', 'purchases',
            'customers', 'expenses', 'reports', 'users', 'settings',
        ];

        $tenants = [
            // 1. Supermarket — With Mock Data
            [
                'name'          => 'Nairobi Fresh Mart Supermarket',
                'slug'          => 'nairobi-fresh-mart',
                'kra_pin'       => 'P051234567Z',
                'email'         => 'info@nairobifreshmart.co.ke',
                'phone'         => '+254 712 345 678',
                'address'       => 'Woodvale Grove, Westlands, Nairobi',
                'business_type' => 'Supermarket',
                'is_demo'       => true,
                'plan'          => 'Enterprise',
                'billing_cycle' => 'Annual',
                'status'        => 'Active',
                'branch_count'  => 4,
                'user_count'    => 20,
                'monthly_revenue' => 185000,
                'storage_quota_mb' => 10240,
                'enabled_modules' => $allModules,
            ],
            // 2. Supermarket — Without Mock Data (Clean Slate)
            [
                'name'          => 'Nairobi Fresh Mart (Clean Sandbox)',
                'slug'          => 'nairobi-fresh-clean',
                'kra_pin'       => 'P051234568Z',
                'email'         => 'clean@nairobifreshmart.co.ke',
                'phone'         => '+254 712 345 679',
                'address'       => 'Woodvale Grove, Westlands, Nairobi',
                'business_type' => 'Supermarket',
                'is_demo'       => true,
                'plan'          => 'Enterprise',
                'billing_cycle' => 'Annual',
                'status'        => 'Active',
                'branch_count'  => 1,
                'user_count'    => 7,
                'monthly_revenue' => 0,
                'storage_quota_mb' => 10240,
                'enabled_modules' => $allModules,
            ],
            // 3. Wholesale & Retail — With Mock Data
            [
                'name'          => 'Metro Wholesale & Retail Distributors',
                'slug'          => 'metro-wholesale-retail',
                'kra_pin'       => 'P058822334K',
                'email'         => 'orders@metrowholesale.co.ke',
                'phone'         => '+254 722 334 455',
                'address'       => 'Commercial Street, Industrial Area, Nairobi',
                'business_type' => 'Wholesale',
                'is_demo'       => true,
                'plan'          => 'Pro',
                'billing_cycle' => 'Monthly',
                'status'        => 'Active',
                'branch_count'  => 2,
                'user_count'    => 8,
                'monthly_revenue' => 245000,
                'storage_quota_mb' => 5120,
                'enabled_modules' => $allModules,
            ],
            // 4. Wholesale & Retail — Without Mock Data (Clean Slate)
            [
                'name'          => 'Metro Wholesale (Clean Sandbox)',
                'slug'          => 'metro-wholesale-clean',
                'kra_pin'       => 'P058822335K',
                'email'         => 'clean@metrowholesale.co.ke',
                'phone'         => '+254 722 334 456',
                'address'       => 'Commercial Street, Industrial Area, Nairobi',
                'business_type' => 'Wholesale',
                'is_demo'       => true,
                'plan'          => 'Pro',
                'billing_cycle' => 'Monthly',
                'status'        => 'Active',
                'branch_count'  => 1,
                'user_count'    => 7,
                'monthly_revenue' => 0,
                'storage_quota_mb' => 5120,
                'enabled_modules' => $allModules,
            ],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::updateOrCreate(
                ['slug' => $tenantData['slug']],
                [
                    'country'          => 'Kenya',
                    'currency'         => 'KES',
                    'storage_used_mb'  => 0,
                    ...$tenantData,
                ]
            );

            // Ensure primary default branch exists for POS operations
            \App\Models\Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'BR-01'],
                [
                    'name' => 'HQ Main Store',
                    'type' => 'Flagship Store',
                    'location' => $tenant->address ?? 'Nairobi, Kenya',
                    'phone' => $tenant->phone ?? '+254 700 000 000',
                    'manager' => 'Store Manager',
                    'is_main' => true,
                    'status' => 'Active',
                    'operating_hours' => '07:30 AM - 09:00 PM',
                ]
            );

            // ISOLATION RULE:
            // - Mock tenants  → full suite of 7 staff roles (for demo exploration)
            // - Clean tenants → ONLY the owner account (no pre-seeded staff to avoid mock-data appearance)
            $cleanSlugs = ['nairobi-fresh-clean', 'metro-wholesale-clean'];
            $isClean    = in_array($tenantData['slug'], $cleanSlugs);

            if ($isClean) {
                // Clean Slate: bare minimum — owner login only
                User::updateOrCreate(
                    [
                        'email'     => 'owner@' . $tenant->slug . '.mdepos.co.ke',
                        'tenant_id' => $tenant->id,
                    ],
                    [
                        'name'              => $tenant->name . ' Owner',
                        'password'          => Hash::make('password'),
                        'role'              => 'Owner',
                        'is_super_admin'    => false,
                        'status'            => 'Active',
                        'email_verified_at' => now(),
                    ]
                );
                $this->command->info("✅ Clean tenant synced: {$tenant->name} — owner account only (password: password)");
            } else {
                // Mock Tenant: full staff roster for demo exploration
                $staffRoles = [
                    ['role' => 'Owner',             'prefix' => 'owner',      'title' => 'Owner'],
                    ['role' => 'Administrator',     'prefix' => 'admin',      'title' => 'System Administrator'],
                    ['role' => 'Manager',           'prefix' => 'manager',    'title' => 'Store Manager'],
                    ['role' => 'Cashier',           'prefix' => 'cashier',    'title' => 'Lead Cashier'],
                    ['role' => 'Inventory Manager', 'prefix' => 'inventory',  'title' => 'Inventory Manager'],
                    ['role' => 'Accountant',        'prefix' => 'accountant', 'title' => 'Chief Accountant'],
                    ['role' => 'Supervisor',        'prefix' => 'supervisor', 'title' => 'Floor Supervisor'],
                ];

                foreach ($staffRoles as $staff) {
                    User::updateOrCreate(
                        [
                            'email'     => $staff['prefix'] . '@' . $tenant->slug . '.mdepos.co.ke',
                            'tenant_id' => $tenant->id,
                        ],
                        [
                            'name'              => $tenant->name . ' ' . $staff['title'],
                            'password'          => Hash::make('password'),
                            'role'              => $staff['role'],
                            'is_super_admin'    => false,
                            'status'            => 'Active',
                            'email_verified_at' => now(),
                        ]
                    );
                }
                $this->command->info("✅ Mock tenant synced: {$tenant->name} — full staff roster (password: password)");
            }
        }
    }
}
