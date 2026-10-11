<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the demo SQLite template with realistic, self-contained data.
 *
 * This seeder runs exclusively against the 'demo' connection (a per-slug SQLite file).
 * It is invoked by:
 *   php artisan demo:refresh
 *
 * The resulting _template.sqlite is copied to {slug}.sqlite for every new demo
 * tenant automatically by IdentifyTenantFromSubdomain middleware.
 *
 * IMPORTANT: This seeder inserts a single demo tenant with a fixed ID (1) so that
 * tenant_id FK references remain consistent across the template.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $allModules = [
            'pos', 'sales', 'products', 'inventory', 'purchases',
            'customers', 'suppliers', 'expenses', 'reports',
            'users', 'branches', 'settings',
        ];

        // ── 1. Tenant ─────────────────────────────────────────────────────────
        /** @var Tenant $tenant */
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'demo-store'],
            [
                'name'             => 'Demo Store',
                'kra_pin'          => 'P051234567Z',
                'email'            => 'demo@mdepos.co.ke',
                'phone'            => '+254 712 000 001',
                'address'          => 'Westlands, Nairobi',
                'country'          => 'Kenya',
                'currency'         => 'KES',
                'business_type'    => 'Supermarket',
                'is_demo'          => true,
                'plan'             => 'Enterprise',
                'billing_cycle'    => 'Annual',
                'status'           => 'Active',
                'branch_count'     => 2,
                'user_count'       => 7,
                'storage_used_mb'  => 0,
                'storage_quota_mb' => 10240,
                'monthly_revenue'  => 185000,
                'enabled_modules'  => $allModules,
                'settings' => [
                    'vat_rate'             => 16,
                    'receipt_header'       => "Welcome to Demo Store\nThank you for shopping with us!",
                    'receipt_footer'       => "Goods once sold cannot be returned without receipt.\nPowered by MDE POS Kenya",
                    'enable_etims'         => true,
                    'low_stock_threshold'  => 10,
                    'payment_methods'      => [
                        ['id' => '1', 'name' => 'Cash',                        'type' => 'Cash',         'enabled' => true, 'feePercent' => 0,   'accountRef' => 'Till Drawer'],
                        ['id' => '2', 'name' => 'M-Pesa Buy Goods Till',       'type' => 'Mobile Money', 'enabled' => true, 'feePercent' => 0,   'accountRef' => 'Till # 523910'],
                        ['id' => '3', 'name' => 'Visa & Mastercard Debit/Credit', 'type' => 'Card',      'enabled' => true, 'feePercent' => 1.5, 'accountRef' => 'PDQ Terminal'],
                    ],
                ],
            ]
        );

        // ── 2. Users ───────────────────────────────────────────────────────────
        $staffRoles = [
            ['role' => 'Owner',             'prefix' => 'owner',      'title' => 'Owner'],
            ['role' => 'Administrator',     'prefix' => 'admin',      'title' => 'System Administrator'],
            ['role' => 'Manager',           'prefix' => 'manager',    'title' => 'Store Manager'],
            ['role' => 'Cashier',           'prefix' => 'cashier',    'title' => 'Lead Cashier'],
            ['role' => 'Inventory Manager', 'prefix' => 'inventory',  'title' => 'Inventory Manager'],
            ['role' => 'Accountant',        'prefix' => 'accountant', 'title' => 'Chief Accountant'],
            ['role' => 'Supervisor',        'prefix' => 'supervisor', 'title' => 'Floor Supervisor'],
        ];

        $primaryUser = null;
        foreach ($staffRoles as $staff) {
            $u = User::updateOrCreate(
                ['email' => $staff['prefix'] . '@demo-store.mdepos.co.ke', 'tenant_id' => $tenant->id],
                [
                    'name'              => 'Demo Store ' . $staff['title'],
                    'password'          => Hash::make('password'),
                    'role'              => $staff['role'],
                    'is_super_admin'    => false,
                    'status'            => 'Active',
                    'email_verified_at' => now(),
                ]
            );
            if ($staff['role'] === 'Owner') {
                $primaryUser = $u;
            }
        }

        // ── 3. Branches ────────────────────────────────────────────────────────
        $mainBranch = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BR-01'],
            [
                'name'            => 'HQ Main Store',
                'type'            => 'Flagship Store',
                'location'        => 'Westlands, Nairobi',
                'phone'           => '+254 712 000 001',
                'manager'         => 'Store Manager',
                'is_main'         => true,
                'status'          => 'Active',
                'operating_hours' => '07:30 AM - 09:00 PM',
            ]
        );

        $branch2 = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'BR-02'],
            [
                'name'            => 'Branch Express - CBD',
                'type'            => 'Satellite Express',
                'location'        => 'Moi Avenue, CBD Nairobi',
                'phone'           => '+254 711 000 111',
                'manager'         => 'Branch Lead',
                'is_main'         => false,
                'status'          => 'Active',
                'operating_hours' => '08:00 AM - 08:00 PM',
            ]
        );

        // ── 4. Product Categories ──────────────────────────────────────────────
        $categories = [
            'Dairy & Beverages', 'Grain & Flours', 'Edible Oils & Cooking Fat',
            'Bakery', 'Detergents & Household', 'Snacks & Confectionery',
            'Personal Care', 'Frozen & Chilled',
        ];

        $catMap = [];
        foreach ($categories as $catName) {
            $cat = ProductCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $catName],
                ['description' => $catName . ' products', 'is_active' => true]
            );
            $catMap[$catName] = $cat->id;
        }

        // ── 5. Suppliers ───────────────────────────────────────────────────────
        $suppliersData = [
            ['name' => 'Brookside Dairy Ltd',     'contact_person' => 'Martin Ndungu',    'phone' => '+254 722 111 222', 'email' => 'orders@brookside.co.ke',       'address' => 'Ruiru, Thika Superhighway',      'total_purchases' => 450000,  'outstanding_balance' => 65000],
            ['name' => 'Pembe Flour Mills Ltd',   'contact_person' => 'Salim Ahmed',      'phone' => '+254 733 444 555', 'email' => 'supply@pembe.co.ke',           'address' => 'Lunga Lunga Rd, Industrial Area','total_purchases' => 1200000, 'outstanding_balance' => 240000],
            ['name' => 'Kapa Oil Refineries',     'contact_person' => 'Jennifer Achieng', 'phone' => '+254 711 777 888', 'email' => 'sales@kapa-oil.com',           'address' => 'Mombasa Rd, Athi River',         'total_purchases' => 890000,  'outstanding_balance' => 120000],
            ['name' => 'Broadways Bakery Ltd',    'contact_person' => 'Peter Chege',      'phone' => '+254 720 999 000', 'email' => 'distribution@broadways.co.ke', 'address' => 'Thika Town Industrial Zone',     'total_purchases' => 320000,  'outstanding_balance' => 0],
            ['name' => 'Unga Group Ltd',          'contact_person' => 'Alice Mwangi',     'phone' => '+254 722 300 400', 'email' => 'trade@unga.co.ke',             'address' => 'Ruaraka, Nairobi',               'total_purchases' => 560000,  'outstanding_balance' => 45000],
            ['name' => 'Bidco Africa Ltd',        'contact_person' => 'James Kamau',      'phone' => '+254 733 500 600', 'email' => 'orders@bidco.co.ke',           'address' => 'Thika Road, Ruiru',              'total_purchases' => 780000,  'outstanding_balance' => 90000],
            ['name' => 'Procter & Gamble Kenya',  'contact_person' => 'Susan Otieno',     'phone' => '+254 711 600 700', 'email' => 'orders@pg.co.ke',              'address' => 'Upperhill, Nairobi',             'total_purchases' => 430000,  'outstanding_balance' => 30000],
            ['name' => 'Unilever Kenya Ltd',      'contact_person' => 'John Kariuki',     'phone' => '+254 722 700 800', 'email' => 'orders@unilever.co.ke',        'address' => 'Industrial Area, Nairobi',       'total_purchases' => 970000,  'outstanding_balance' => 150000],
            ['name' => 'Highlands Mineral Water', 'contact_person' => 'Lucy Wanjiku',     'phone' => '+254 733 800 900', 'email' => 'distribution@highlands.co.ke', 'address' => 'Limuru, Kiambu County',          'total_purchases' => 210000,  'outstanding_balance' => 0],
            ['name' => 'Nairobi Bottlers Ltd',    'contact_person' => 'David Omondi',     'phone' => '+254 711 900 001', 'email' => 'trade@nairobibottlers.co.ke',  'address' => 'Embakasi, Nairobi',              'total_purchases' => 680000,  'outstanding_balance' => 70000],
            ['name' => 'Kevian Kenya Ltd',        'contact_person' => 'Esther Njoki',     'phone' => '+254 722 001 002', 'email' => 'orders@kevian.co.ke',          'address' => 'Thika, Kiambu County',           'total_purchases' => 310000,  'outstanding_balance' => 20000],
            ['name' => 'Nice & Lovely Moko',      'contact_person' => 'Alex Mutua',       'phone' => '+254 733 002 003', 'email' => 'sales@niceandlovely.co.ke',    'address' => 'Industrial Area, Nairobi',       'total_purchases' => 190000,  'outstanding_balance' => 0],
        ];

        $seededSuppliers = [];
        foreach ($suppliersData as $s) {
            $supplier = Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $s['name']],
                [...$s, 'status' => 'Active']
            );
            $seededSuppliers[] = $supplier;
        }

        // ── 6. Products (24 items across 8 categories) ────────────────────────
        $productsData = [
            // Dairy & Beverages (6)
            ['name' => 'Brookside Whole Milk 500ml',       'sku' => 'MLK-500',  'barcode' => '6161100010001', 'category' => 'Dairy & Beverages',         'cost_price' =>  52.00, 'selling_price' =>  65.00, 'stock_quantity' => 150, 'reorder_level' => 20, 'unit' => 'pkt'],
            ['name' => 'Ketepa Pride Tea Leaves 250g',     'sku' => 'TEA-250',  'barcode' => '6161100010002', 'category' => 'Dairy & Beverages',         'cost_price' => 120.00, 'selling_price' => 155.00, 'stock_quantity' =>  75, 'reorder_level' => 15, 'unit' => 'pkt'],
            ['name' => 'Highlands Still Water 500ml',      'sku' => 'WAT-500',  'barcode' => '6161100010003', 'category' => 'Dairy & Beverages',         'cost_price' =>  25.00, 'selling_price' =>  35.00, 'stock_quantity' => 200, 'reorder_level' => 30, 'unit' => 'btl'],
            ['name' => 'Coca-Cola 500ml PET',              'sku' => 'CKE-500',  'barcode' => '6161100010004', 'category' => 'Dairy & Beverages',         'cost_price' =>  55.00, 'selling_price' =>  70.00, 'stock_quantity' => 120, 'reorder_level' => 24, 'unit' => 'btl'],
            ['name' => 'Alvaro Pineapple 330ml',           'sku' => 'ALV-330',  'barcode' => '6161100010005', 'category' => 'Dairy & Beverages',         'cost_price' =>  60.00, 'selling_price' =>  75.00, 'stock_quantity' =>   8, 'reorder_level' => 15, 'unit' => 'can'],
            ['name' => 'Mala Fresh Yoghurt 400g',          'sku' => 'YGT-400',  'barcode' => '6161100010006', 'category' => 'Dairy & Beverages',         'cost_price' =>  85.00, 'selling_price' => 110.00, 'stock_quantity' =>  40, 'reorder_level' => 10, 'unit' => 'cup'],
            // Grain & Flours (3)
            ['name' => 'Pembe Maize Flour 2kg',            'sku' => 'FLR-2KG',  'barcode' => '6161100010007', 'category' => 'Grain & Flours',           'cost_price' => 170.00, 'selling_price' => 210.00, 'stock_quantity' => 220, 'reorder_level' => 30, 'unit' => 'bale'],
            ['name' => 'Dawaat Basmati Rice 2kg',          'sku' => 'RCE-2KG',  'barcode' => '6161100010008', 'category' => 'Grain & Flours',           'cost_price' => 410.00, 'selling_price' => 520.00, 'stock_quantity' =>  60, 'reorder_level' => 10, 'unit' => 'pkt'],
            ['name' => 'Unga Ndovu Wheat Flour 1kg',       'sku' => 'WHT-1KG',  'barcode' => '6161100010009', 'category' => 'Grain & Flours',           'cost_price' =>  95.00, 'selling_price' => 120.00, 'stock_quantity' =>   7, 'reorder_level' => 20, 'unit' => 'pkt'],
            // Edible Oils (2)
            ['name' => 'Fresh Fri Cooking Oil 2L',         'sku' => 'OIL-2L',   'barcode' => '6161100010010', 'category' => 'Edible Oils & Cooking Fat', 'cost_price' => 560.00, 'selling_price' => 680.00, 'stock_quantity' =>  85, 'reorder_level' => 12, 'unit' => 'btl'],
            ['name' => 'Elianto Vegetable Oil 1L',         'sku' => 'OIL-1L',   'barcode' => '6161100010011', 'category' => 'Edible Oils & Cooking Fat', 'cost_price' => 295.00, 'selling_price' => 360.00, 'stock_quantity' =>  45, 'reorder_level' => 10, 'unit' => 'btl'],
            // Bakery (2)
            ['name' => 'Broadways White Bread 400g',       'sku' => 'BRD-400',  'barcode' => '6161100010012', 'category' => 'Bakery',                   'cost_price' =>  55.00, 'selling_price' =>  70.00, 'stock_quantity' =>  90, 'reorder_level' => 15, 'unit' => 'loaf'],
            ['name' => 'Butter Croissant 2-Pack',          'sku' => 'CRS-2PK',  'barcode' => '6161100010013', 'category' => 'Bakery',                   'cost_price' =>  90.00, 'selling_price' => 120.00, 'stock_quantity' =>   5, 'reorder_level' => 10, 'unit' => 'pkt'],
            // Detergents (3)
            ['name' => 'Sunlight 2in1 Washing Powder 1kg', 'sku' => 'DET-1KG',  'barcode' => '6161100010014', 'category' => 'Detergents & Household',   'cost_price' => 240.00, 'selling_price' => 310.00, 'stock_quantity' => 110, 'reorder_level' => 15, 'unit' => 'pkt'],
            ['name' => 'Ariel Handwash Powder 500g',       'sku' => 'ARI-500',  'barcode' => '6161100010015', 'category' => 'Detergents & Household',   'cost_price' => 185.00, 'selling_price' => 240.00, 'stock_quantity' =>  55, 'reorder_level' => 10, 'unit' => 'pkt'],
            ['name' => 'Jik Bleach 750ml',                 'sku' => 'JIK-750',  'barcode' => '6161100010016', 'category' => 'Detergents & Household',   'cost_price' =>  95.00, 'selling_price' => 125.00, 'stock_quantity' =>  70, 'reorder_level' => 12, 'unit' => 'btl'],
            // Snacks (3)
            ['name' => 'Pringles Original 149g',           'sku' => 'PRN-149',  'barcode' => '6161100010017', 'category' => 'Snacks & Confectionery',   'cost_price' => 380.00, 'selling_price' => 480.00, 'stock_quantity' =>  30, 'reorder_level' =>  8, 'unit' => 'can'],
            ['name' => 'Cadbury Dairy Milk 90g',           'sku' => 'CDM-090',  'barcode' => '6161100010018', 'category' => 'Snacks & Confectionery',   'cost_price' => 190.00, 'selling_price' => 250.00, 'stock_quantity' =>  50, 'reorder_level' => 10, 'unit' => 'bar'],
            ['name' => 'Simba Chilli Crunch 200g',         'sku' => 'SIM-200',  'barcode' => '6161100010019', 'category' => 'Snacks & Confectionery',   'cost_price' =>  70.00, 'selling_price' =>  95.00, 'stock_quantity' =>  80, 'reorder_level' => 15, 'unit' => 'pkt'],
            // Personal Care (3)
            ['name' => 'Dove Body Wash 500ml',             'sku' => 'DVE-500',  'barcode' => '6161100010020', 'category' => 'Personal Care',            'cost_price' => 520.00, 'selling_price' => 650.00, 'stock_quantity' =>  25, 'reorder_level' =>  5, 'unit' => 'btl'],
            ['name' => 'Colgate MaxFresh Toothpaste 150g', 'sku' => 'COL-150',  'barcode' => '6161100010021', 'category' => 'Personal Care',            'cost_price' => 155.00, 'selling_price' => 200.00, 'stock_quantity' =>  60, 'reorder_level' => 12, 'unit' => 'tube'],
            ['name' => 'Nice & Lovely Lotion 400ml',       'sku' => 'NNL-400',  'barcode' => '6161100010022', 'category' => 'Personal Care',            'cost_price' => 195.00, 'selling_price' => 250.00, 'stock_quantity' =>  35, 'reorder_level' => 10, 'unit' => 'btl'],
            // Frozen & Chilled (2)
            ['name' => 'Farmer\'s Choice Beef Sausages 500g', 'sku' => 'SAU-500', 'barcode' => '6161100010023', 'category' => 'Frozen & Chilled',       'cost_price' => 290.00, 'selling_price' => 370.00, 'stock_quantity' =>  18, 'reorder_level' =>  5, 'unit' => 'pkt'],
            ['name' => 'Daima Full Cream Milk 1L',         'sku' => 'DMA-1L',   'barcode' => '6161100010024', 'category' => 'Frozen & Chilled',         'cost_price' =>  95.00, 'selling_price' => 120.00, 'stock_quantity' =>  55, 'reorder_level' => 10, 'unit' => 'btl'],
        ];

        $seededProducts = [];
        foreach ($productsData as $p) {
            $prod = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $p['sku']],
                [
                    'name'          => $p['name'],
                    'barcode'       => $p['barcode'],
                    'category'      => $p['category'],
                    'cost_price'    => $p['cost_price'],
                    'selling_price' => $p['selling_price'],
                    'stock_quantity'=> $p['stock_quantity'],
                    'reorder_level' => $p['reorder_level'],
                    'unit'          => $p['unit'],
                    'branch_id'     => $mainBranch->id,
                    'status'        => 'Active',
                ]
            );
            $seededProducts[] = $prod;
        }

        // ── 7. Customers (34 accounts) ────────────────────────────────────────
        $customersData = [
            ['name' => 'Ochieng Supermarket (Wholesale)',  'phone' => '+254 711 223 344', 'email' => 'orders@ochiengretails.co.ke',  'group' => 'Wholesale',  'credit_limit' => 500000, 'current_balance' => 124000],
            ['name' => 'Grace Muthoni',                    'phone' => '+254 722 554 433', 'email' => 'grace.m@gmail.com',            'group' => 'VIP',        'credit_limit' =>  50000, 'current_balance' =>   8500],
            ['name' => 'Sarit Cafe Supplies',              'phone' => '+254 733 998 877', 'email' => 'accounts@saritcafe.com',       'group' => 'Corporate',  'credit_limit' => 300000, 'current_balance' =>  89000],
            ['name' => 'Samuel Kibet',                     'phone' => '+254 712 887 766', 'email' => 'samuel.kibet@gmail.com',       'group' => 'Retail',     'credit_limit' =>  10000, 'current_balance' =>       0],
            ['name' => 'Fatuma Abdi',                      'phone' => '+254 711 334 455', 'email' => 'fatuma.abdi@gmail.com',        'group' => 'Retail',     'credit_limit' =>  10000, 'current_balance' =>    500],
            ['name' => 'City Cafeteria Ltd',               'phone' => '+254 722 445 566', 'email' => 'purchase@citycafeteria.co.ke', 'group' => 'Corporate',  'credit_limit' => 200000, 'current_balance' =>  42000],
            ['name' => 'Kamau General Shop',               'phone' => '+254 733 556 677', 'email' => 'kamau.shop@gmail.com',         'group' => 'Wholesale',  'credit_limit' => 150000, 'current_balance' =>  31000],
            ['name' => 'Aisha Mohammed',                   'phone' => '+254 712 667 788', 'email' => 'aisha.m@gmail.com',            'group' => 'VIP',        'credit_limit' =>  30000, 'current_balance' =>   2000],
            ['name' => 'Westgate Hotel Purchases',         'phone' => '+254 722 778 899', 'email' => 'supplies@westgate.co.ke',      'group' => 'Corporate',  'credit_limit' => 500000, 'current_balance' => 178000],
            ['name' => 'Peter Njoroge',                    'phone' => '+254 733 889 900', 'email' => 'peter.njoroge@gmail.com',      'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
            ['name' => 'Nairobi Hardware Stores',          'phone' => '+254 711 990 011', 'email' => 'buy@nairobihardware.co.ke',    'group' => 'Wholesale',  'credit_limit' => 250000, 'current_balance' =>  60000],
            ['name' => 'Sarah Wanjiku',                    'phone' => '+254 722 001 122', 'email' => 'sarah.w@gmail.com',            'group' => 'Retail',     'credit_limit' =>   8000, 'current_balance' =>   1200],
            ['name' => 'Mombasa Traders Co.',              'phone' => '+254 733 112 233', 'email' => 'trade@mombasatraders.co.ke',   'group' => 'Wholesale',  'credit_limit' => 400000, 'current_balance' =>  95000],
            ['name' => 'John Mwangi',                      'phone' => '+254 712 223 344', 'email' => 'john.mwangi@gmail.com',        'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
            ['name' => 'Kisumu Fresh Foods',               'phone' => '+254 722 334 455', 'email' => 'orders@kisumufresh.co.ke',     'group' => 'Wholesale',  'credit_limit' => 300000, 'current_balance' =>  72000],
            ['name' => 'Mary Wambui',                      'phone' => '+254 733 445 566', 'email' => 'mary.w@gmail.com',             'group' => 'VIP',        'credit_limit' =>  20000, 'current_balance' =>   3500],
            ['name' => 'Rift Valley Provisions',           'phone' => '+254 711 556 677', 'email' => 'buy@rvprovisions.co.ke',       'group' => 'Corporate',  'credit_limit' => 350000, 'current_balance' => 110000],
            ['name' => 'Moses Otieno',                     'phone' => '+254 722 667 788', 'email' => 'moses.o@gmail.com',            'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
            ['name' => 'Zuri Boutique Hotel',              'phone' => '+254 733 778 899', 'email' => 'catering@zuriboutique.co.ke',  'group' => 'Corporate',  'credit_limit' => 200000, 'current_balance' =>  45000],
            ['name' => 'Agnes Kamau',                      'phone' => '+254 712 889 900', 'email' => 'agnes.k@gmail.com',            'group' => 'Retail',     'credit_limit' =>   8000, 'current_balance' =>    800],
            ['name' => 'Nakumatt Traders Ltd',             'phone' => '+254 722 990 011', 'email' => 'supply@nakumatt.co.ke',        'group' => 'Wholesale',  'credit_limit' => 600000, 'current_balance' => 220000],
            ['name' => 'James Odhiambo',                   'phone' => '+254 733 001 122', 'email' => 'james.o@gmail.com',            'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
            ['name' => 'Sunshine Bakery Supplies',         'phone' => '+254 711 112 233', 'email' => 'buy@sunshinebakery.co.ke',     'group' => 'Corporate',  'credit_limit' => 150000, 'current_balance' =>  28000],
            ['name' => 'Esther Wanjiru',                   'phone' => '+254 722 223 344', 'email' => 'esther.w@gmail.com',           'group' => 'VIP',        'credit_limit' =>  15000, 'current_balance' =>   2000],
            ['name' => 'Eldoret Wholesale Hub',            'phone' => '+254 733 334 455', 'email' => 'orders@eldoretwholesale.co.ke','group' => 'Wholesale',  'credit_limit' => 500000, 'current_balance' => 140000],
            ['name' => 'Brian Mutua',                      'phone' => '+254 712 445 566', 'email' => 'brian.m@gmail.com',            'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
            ['name' => 'Nairobi Catering Ltd',             'phone' => '+254 722 556 677', 'email' => 'kitchen@nairobicatering.co.ke','group' => 'Corporate',  'credit_limit' => 250000, 'current_balance' =>  66000],
            ['name' => 'Lucy Njeri',                       'phone' => '+254 733 667 788', 'email' => 'lucy.n@gmail.com',             'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>    300],
            ['name' => 'Thika Road Distributors',          'phone' => '+254 711 778 899', 'email' => 'dist@thikaroad.co.ke',         'group' => 'Wholesale',  'credit_limit' => 400000, 'current_balance' =>  98000],
            ['name' => 'Kevin Gitau',                      'phone' => '+254 722 889 900', 'email' => 'kevin.g@gmail.com',            'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
            ['name' => 'Nakuru Provisions Ltd',            'phone' => '+254 733 990 011', 'email' => 'buy@nakuruprovisions.co.ke',   'group' => 'Wholesale',  'credit_limit' => 300000, 'current_balance' =>  75000],
            ['name' => 'Naomi Wanjiru',                    'phone' => '+254 711 001 122', 'email' => 'naomi.w@gmail.com',            'group' => 'VIP',        'credit_limit' =>  25000, 'current_balance' =>   4000],
            ['name' => 'Coastal Fresh Produce',            'phone' => '+254 722 112 233', 'email' => 'orders@coastalfresh.co.ke',    'group' => 'Corporate',  'credit_limit' => 200000, 'current_balance' =>  53000],
            ['name' => 'Daniel Otieno',                    'phone' => '+254 733 223 344', 'email' => 'daniel.o@gmail.com',           'group' => 'Retail',     'credit_limit' =>   5000, 'current_balance' =>       0],
        ];

        foreach ($customersData as $c) {
            Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $c['name']],
                [...$c, 'status' => 'Active']
            );
        }

        // ── 8. Purchases (3 pending) ───────────────────────────────────────────
        $purchasesData = [
            [
                'supplier_id'       => $seededSuppliers[0]->id,
                'branch_id'         => $mainBranch->id,
                'po_number'         => 'PO-DEMO-001',
                'order_date'        => now()->subDays(5)->toDateString(),
                'expected_date'     => now()->subDays(3)->toDateString(),
                'items_description' => 'Brookside Whole Milk 500ml (200 pkts)',
                'total_amount'      => 10400,
                'paid_amount'       => 10400,
                'payment_status'    => 'Paid',
                'status'            => 'Received',
            ],
            [
                'supplier_id'       => $seededSuppliers[1]->id,
                'branch_id'         => $mainBranch->id,
                'po_number'         => 'PO-DEMO-002',
                'order_date'        => now()->subDays(2)->toDateString(),
                'expected_date'     => now()->addDay()->toDateString(),
                'items_description' => 'Pembe Maize Flour 2kg (100 bales)',
                'total_amount'      => 84000,
                'paid_amount'       => 0,
                'payment_status'    => 'Pending',
                'status'            => 'Pending',
            ],
            [
                'supplier_id'       => $seededSuppliers[2]->id,
                'branch_id'         => $branch2->id,
                'po_number'         => 'PO-DEMO-003',
                'order_date'        => now()->subDay()->toDateString(),
                'expected_date'     => now()->addDays(2)->toDateString(),
                'items_description' => 'Fresh Fri Vegetable Oil 2L (40 cartons)',
                'total_amount'      => 55200,
                'paid_amount'       => 20000,
                'payment_status'    => 'Partial',
                'status'            => 'Approved',
            ],
        ];

        foreach ($purchasesData as $po) {
            Purchase::firstOrCreate(
                ['tenant_id' => $tenant->id, 'po_number' => $po['po_number']],
                [...$po, 'user_id' => $primaryUser?->id]
            );
        }

        // ── 9. Expenses ────────────────────────────────────────────────────────
        $expensesData = [
            ['title' => 'KPLC Electricity Bill - Freezers & Store',   'category' => 'Electricity / Utilities', 'amount' => 18500, 'date' => now()->subDays(2)->toDateString(), 'payment_method' => 'M-Pesa Till',    'description' => 'Tokens for walk-in cold rooms'],
            ['title' => 'Store Premises Monthly Lease',               'category' => 'Rent',                    'amount' => 95000, 'date' => now()->startOfMonth()->toDateString(), 'payment_method' => 'Bank Transfer', 'description' => 'Main store premises rent'],
            ['title' => 'Bulk Stock Offloading Logistics',            'category' => 'Transport & Logistics',   'amount' =>  6500, 'date' => now()->subDay()->toDateString(),    'payment_method' => 'Petty Cash',     'description' => 'Truck unloading wages'],
            ['title' => 'Security Guard Monthly Wages',               'category' => 'Salaries',                'amount' => 22000, 'date' => now()->startOfMonth()->toDateString(), 'payment_method' => 'Bank Transfer', 'description' => '2 security guards'],
            ['title' => 'Internet & WiFi Bill',                       'category' => 'Utilities',               'amount' =>  4500, 'date' => now()->subDays(10)->toDateString(), 'payment_method' => 'M-Pesa Till',    'description' => 'Zuku business fiber'],
            ['title' => 'Pest Control Service',                       'category' => 'Maintenance',             'amount' =>  3200, 'date' => now()->subDays(14)->toDateString(), 'payment_method' => 'Cash',            'description' => 'Monthly fumigation'],
        ];

        foreach ($expensesData as $e) {
            Expense::firstOrCreate(
                ['tenant_id' => $tenant->id, 'title' => $e['title']],
                [...$e, 'branch_id' => $mainBranch->id, 'user_id' => $primaryUser?->id]
            );
        }

        // ── 10. Sales — 28 spread over last 7 days ─────────────────────────────
        if (Sale::where('tenant_id', $tenant->id)->count() === 0) {
            $customers = ['Walk-in Customer', 'Grace Muthoni', 'Samuel Kibet', 'Ochieng Supermarket', 'City Cafeteria Ltd', 'Mary Wambui', 'James Odhiambo'];
            $methods   = ['Cash', 'M-Pesa Buy Goods Till', 'Visa & Mastercard Debit/Credit'];

            for ($day = 6; $day >= 0; $day--) {
                $saleDate   = now()->subDays($day);
                $salesOnDay = ($day === 0) ? 6 : rand(3, 5); // today gets 6 (shows in badge)

                for ($i = 0; $i < $salesOnDay; $i++) {
                    // Pick 1–3 random products for this sale
                    $lineItems   = collect($seededProducts)->random(rand(1, 3));
                    $subtotal    = 0;
                    $profit      = 0;
                    $saleItemRows= [];

                    foreach ($lineItems as $prod) {
                        $qty      = rand(1, 4);
                        $lineTotal= $prod->selling_price * $qty;
                        $subtotal += $lineTotal;
                        $profit   += ($prod->selling_price - $prod->cost_price) * $qty;
                        $saleItemRows[] = [
                            'product_id'   => $prod->id,
                            'product_name' => $prod->name,
                            'quantity'     => $qty,
                            'unit_price'   => $prod->selling_price,
                            'cost_price'   => $prod->cost_price,
                            'total'        => $lineTotal,
                        ];
                    }

                    $sale = Sale::create([
                        'tenant_id'      => $tenant->id,
                        'branch_id'      => ($i % 3 === 0) ? $branch2->id : $mainBranch->id,
                        'user_id'        => $primaryUser?->id,
                        'receipt_number' => 'REC-' . $saleDate->format('ymd') . '-' . strtoupper(Str::random(5)),
                        'subtotal'       => $subtotal,
                        'tax_amount'     => round($subtotal * 0.16, 2),
                        'discount_amount'=> 0,
                        'total'          => $subtotal,
                        'profit'         => $profit,
                        'payment_method' => $methods[array_rand($methods)],
                        'payment_status' => 'Completed',
                        'customer_name'  => $customers[array_rand($customers)],
                        'customer_phone' => '+254 7' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                        'created_at'     => $saleDate->setTime(rand(8, 20), rand(0, 59)),
                        'updated_at'     => $saleDate,
                    ]);

                    foreach ($saleItemRows as $row) {
                        SaleItem::create(['sale_id' => $sale->id, ...$row]);
                    }
                }
            }
        }
    }
}
