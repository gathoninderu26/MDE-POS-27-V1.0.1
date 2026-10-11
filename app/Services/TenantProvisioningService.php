<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Provision a fully initialized tenant account.
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $branchCount = max(1, (int) ($data['branch_count'] ?? 1));
            $plan = $data['plan'] ?? 'Pro';
            $businessType = $data['business_type'] ?? 'Supermarket';

            // 1. Resolve Enabled Modules dynamically
            $enabledModules = $this->resolveModules(
                $businessType,
                $plan,
                $branchCount,
                $data['enabled_modules'] ?? null
            );

            $isMultiBranch = in_array('branches', $enabledModules) && $branchCount > 1;

            // 2. Slug generation
            $baseSlug = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $baseSlug;
            $i = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }

            // 3. Quota resolution
            $storageQuotaMB = match ($plan) {
                'Basic'      => 1024,
                'Enterprise' => 51200,
                default      => 5120,
            };

            // 4. Device & Network security policies
            $devicePolicy = [
                'selected_device_types'      => $data['selected_device_types'] ?? ['POS Terminal', 'Desktop PC'],
                'require_device_binding'     => (bool) ($data['require_device_binding'] ?? false),
                'pos_hardware_count'         => max(1, (int) ($data['pos_hardware_count'] ?? 1)),
                'single_session_per_cashier' => (bool) ($data['single_session_per_cashier'] ?? true),
            ];

            $networkPolicy = [
                'block_vpn'               => (bool) ($data['block_vpn'] ?? false),
                'require_store_wifi'      => (bool) ($data['require_store_wifi'] ?? false),
                'primary_store_wifi_ssid' => $data['primary_store_wifi_ssid'] ?? null,
                'block_hotspot'           => (bool) ($data['block_hotspot'] ?? false),
                'cellular_policy'         => $data['cellular_policy'] ?? 'allow_all',
            ];

            $paymentMethods = $this->resolvePaymentMethods($data, $data['name']);

            $settings = [
                'is_multi_branch'    => $isMultiBranch,
                'currency'           => $data['currency'] ?? 'KES',
                'country'            => $data['country'] ?? 'Kenya',
                'fiscal_tax_enabled' => !empty($data['kra_pin']),
                'payment_methods'    => $paymentMethods,
            ];

            // 5. Create Tenant record
            $tenant = Tenant::create([
                'name'             => $data['name'],
                'slug'             => $slug,
                'kra_pin'          => $data['kra_pin'] ?? null,
                'email'            => $data['email'],
                'phone'            => $data['phone'] ?? null,
                'country'          => $data['country'] ?? 'Kenya',
                'currency'         => $data['currency'] ?? 'KES',
                'business_type'    => $businessType,
                'plan'             => $plan,
                'billing_cycle'    => $data['billing_cycle'] ?? 'Monthly',
                'status'           => 'Active',
                'is_demo'          => false,
                'branch_count'     => $branchCount,
                'user_count'       => max(1, (int) ($data['user_count'] ?? 5)),
                'storage_used_mb'  => 0,
                'storage_quota_mb' => $storageQuotaMB,
                'monthly_revenue'  => 0,
                'enabled_modules'  => $enabledModules,
                'custom_modules'   => $data['custom_modules'] ?? [],
                'device_policy'    => $devicePolicy,
                'network_policy'   => $networkPolicy,
                'settings'         => $settings,
                'next_billing_date' => now()->addMonth(),
            ]);

            // 6. Create Owner User
            $ownerEmail = !empty($data['owner_email']) ? $data['owner_email'] : $data['email'];
            $ownerPassword = !empty($data['owner_password']) ? $data['owner_password'] : 'Owner@' . rand(1000, 9999) . '!';

            $owner = User::create([
                'name'              => $data['name'] . ' Administrator',
                'email'             => $ownerEmail,
                'password'          => Hash::make($ownerPassword),
                'tenant_id'         => $tenant->id,
                'role'              => 'Owner',
                'is_super_admin'    => false,
                'status'            => 'Active',
                'phone'             => $data['phone'] ?? null,
                'email_verified_at' => now(),
            ]);

            // 7. Create Default Main HQ Branch
            $branchName = $isMultiBranch ? ($tenant->name . ' (Main HQ)') : $tenant->name;
            $branch = Branch::create([
                'tenant_id'       => $tenant->id,
                'name'            => $branchName,
                'code'            => 'HQ-01',
                'type'            => 'Main HQ',
                'location'        => $tenant->country ?? 'Main Location',
                'phone'           => $tenant->phone ?? 'N/A',
                'manager'         => $owner->name,
                'operating_hours' => '08:00 AM - 08:00 PM',
                'wifi_ssid'       => $networkPolicy['primary_store_wifi_ssid'] ?? null,
                'status'          => 'Active',
                'is_main'         => true,
            ]);

            // 8. Seed Starter Categories for this business type
            $this->seedStarterCategories($tenant);

            return [
                'tenant'      => $tenant,
                'owner'       => $owner,
                'branch'      => $branch,
                'credentials' => [
                    'tenant_id'      => (string) $tenant->id,
                    'tenant_name'    => $tenant->name,
                    'subdomain'      => $tenant->slug,
                    'subdomain_url'  => $tenant->getSubdomainUrl('/login'),
                    'owner_email'    => $ownerEmail,
                    'owner_password' => $ownerPassword,
                ],
            ];
        });
    }

    /**
     * Resolve module capability list based on industry vertical and subscription plan.
     */
    public function resolveModules(string $businessType, string $plan, int $branchCount, ?array $customOverrides = null): array
    {
        if ($customOverrides !== null && count($customOverrides) > 0) {
            return array_values(array_unique($customOverrides));
        }

        $baseModules = match ($businessType) {
            'Supermarket'        => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
            'Pharmacy'           => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings', 'prescriptions'],
            'Restaurant'         => ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings', 'tables', 'kitchen'],
            'Wholesale'          => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
            'Hardware'           => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
            'General Trade'      => ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings'],
            'Electronics'        => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
            'Clothing & Apparel' => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
            'Bakery'             => ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings'],
            'Petrol Station'     => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'expenses', 'reports', 'users', 'settings', 'fuel'],
            default              => ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
        };

        // Multi-branch module is ONLY included if tenant is configured for multiple branches or is Enterprise
        if ($branchCount > 1 || $plan === 'Enterprise') {
            $baseModules[] = 'branches';
        }

        return array_values(array_unique($baseModules));
    }

    /**
     * Seed baseline categories suited to the business type so the real account doesn't start empty.
     */
    protected function seedStarterCategories(Tenant $tenant): void
    {
        $presets = match ($tenant->business_type) {
            'Supermarket' => ['Groceries & Dry Foods', 'Fresh Produce & Bakery', 'Beverages & Dairy', 'Toiletries & Household', 'Snacks & Confectionery'],
            'Pharmacy'    => ['Prescription Drugs (Rx)', 'Over-The-Counter (OTC)', 'First Aid & Surgical', 'Vitamins & Supplements', 'Personal Care'],
            'Restaurant'  => ['Appetizers & Starters', 'Main Courses', 'Beverages & Cocktails', 'Desserts', 'Side Orders'],
            'Hardware'    => ['Building Materials', 'Plumbing & Pipes', 'Electrical & Lighting', 'Tools & Fasteners', 'Paints & Finishes'],
            'Wholesale'   => ['Bulk Grains & Flours', 'Cooking Fats & Oils', 'Packaged Beverages', 'Cleaning Supplies', 'Packaging Materials'],
            'Electronics' => ['Phones & Accessories', 'Computer Peripherals', 'Cables & Adapters', 'Power & Chargers', 'Audio & Speakers'],
            'Clothing & Apparel' => ['Menswear', 'Womenswear', 'Footwear', 'Children & Baby', 'Accessories'],
            'Bakery'      => ['Breads & Rolls', 'Pastries & Pies', 'Cakes & Desserts', 'Baking Ingredients', 'Beverages'],
            'Petrol Station' => ['Fuel (PMS / AGO / IK)', 'Engine Oils & Lubricants', 'Car Care & Accessories', 'Convenience Store', 'Gas Refills'],
            default       => ['General Merchandise', 'Dry Goods', 'Beverages', 'Personal Care', 'Household'],
        };

        foreach ($presets as $index => $catName) {
            ProductCategory::create([
                'tenant_id'   => $tenant->id,
                'name'        => $catName,
                'slug'        => Str::slug($catName),
                'description' => "Initial {$catName} category",
            ]);
        }
    }

    /**
     * Resolve default and customized payment channels during onboarding.
     */
    public function resolvePaymentMethods(array $data, string $storeName): array
    {
        if (!empty($data['payment_methods']) && is_array($data['payment_methods'])) {
            return $data['payment_methods'];
        }

        $methods = [];

        // 1. Cash
        $methods[] = [
            'id' => 'pm_cash',
            'code' => 'cash',
            'name' => 'Cash',
            'type' => 'Cash',
            'enabled' => isset($data['enable_cash']) ? (bool) $data['enable_cash'] : true,
            'is_default' => true,
            'fee_percent' => 0,
            'instructions' => 'Accept cash tender into register till drawer',
            'details' => [
                'drawer_label' => $data['cash_drawer_label'] ?? 'Store Cash Drawer',
            ],
        ];

        // 2. M-Pesa Buy Goods Till
        $enableTill = isset($data['enable_mpesa_till']) ? (bool) $data['enable_mpesa_till'] : true;
        $methods[] = [
            'id' => 'pm_mpesa_till',
            'code' => 'mpesa_till',
            'name' => 'M-Pesa Buy Goods',
            'type' => 'Mobile Money',
            'enabled' => $enableTill,
            'is_default' => false,
            'fee_percent' => 0,
            'instructions' => 'Lipa na M-Pesa -> Buy Goods and Services',
            'details' => [
                'till_number' => $data['mpesa_till_number'] ?? '523910',
                'store_name'  => $data['mpesa_store_name'] ?? $storeName,
                'enable_stk'  => (bool) ($data['mpesa_till_enable_stk'] ?? true),
            ],
        ];

        // 3. M-Pesa Paybill
        $enablePaybill = (bool) ($data['enable_mpesa_paybill'] ?? false);
        $methods[] = [
            'id' => 'pm_mpesa_paybill',
            'code' => 'mpesa_paybill',
            'name' => 'M-Pesa Paybill',
            'type' => 'Mobile Money',
            'enabled' => $enablePaybill,
            'is_default' => false,
            'fee_percent' => 0,
            'instructions' => 'Lipa na M-Pesa -> Paybill',
            'details' => [
                'business_number' => $data['mpesa_paybill_number'] ?? '247247',
                'account_number'  => $data['mpesa_paybill_account'] ?? 'STORE',
                'account_type'    => 'fixed',
            ],
        ];

        // 4. Card PDQ Terminal
        $enableCard = (bool) ($data['enable_card'] ?? false);
        $methods[] = [
            'id' => 'pm_card_pdq',
            'code' => 'card',
            'name' => 'Visa & Mastercard (PDQ)',
            'type' => 'Card',
            'enabled' => $enableCard,
            'is_default' => false,
            'fee_percent' => (float) ($data['card_fee_percent'] ?? 0),
            'instructions' => 'Swipe or tap card on POS PDQ terminal',
            'details' => [
                'terminal_id' => $data['card_terminal_id'] ?? 'EQ-PDQ-8901',
                'bank_name'   => $data['card_bank_name'] ?? 'Equity Bank',
            ],
        ];

        // 5. Bank Wire / Pesalink
        $enableBank = (bool) ($data['enable_bank'] ?? false);
        $methods[] = [
            'id' => 'pm_bank',
            'code' => 'bank',
            'name' => 'Bank Wire / Pesalink',
            'type' => 'Bank',
            'enabled' => $enableBank,
            'is_default' => false,
            'fee_percent' => 0,
            'instructions' => 'Direct bank transfer or Pesalink deposit',
            'details' => [
                'bank_name'      => $data['bank_name'] ?? 'KCB Bank',
                'account_name'   => $data['bank_account_name'] ?? $storeName,
                'account_number' => $data['bank_account_number'] ?? '',
            ],
        ];

        // 6. Customer Credit Account
        $enableCredit = (bool) ($data['enable_credit'] ?? false);
        $methods[] = [
            'id' => 'pm_credit',
            'code' => 'credit',
            'name' => 'Customer Credit Account',
            'type' => 'Credit',
            'enabled' => $enableCredit,
            'is_default' => false,
            'fee_percent' => 0,
            'instructions' => 'Billed to customer credit ledger',
            'details' => [],
        ];

        return $methods;
    }
}
