<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessDataSeeder extends Seeder
{
    public function run(): void
    {
        // STRICT ISOLATION: Only seed mock demo data (products, customers, sales, purchases)
        // into designated mock demo tenants. Clean demo tenants and real super admin tenants
        // are NEVER seeded with mock data.
        $mockSlugs = ['nairobi-fresh-mart', 'metro-wholesale-retail'];
        $tenants = Tenant::whereIn('slug', $mockSlugs)->where('is_demo', true)->get();

        foreach ($tenants as $tenant) {
            // 1. Branches
            $mainBranch = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'HQ Main Store'],
                [
                    'code' => 'BR-01',
                    'type' => 'Flagship Store',
                    'location' => $tenant->address ?? 'Nairobi, Kenya',
                    'phone' => $tenant->phone ?? '+254 700 000 000',
                    'manager' => 'Store Manager',
                    'is_main' => true,
                    'status' => 'Active',
                    'operating_hours' => '07:30 AM - 09:00 PM',
                ]
            );

            $secondBranch = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Branch Express - CBD'],
                [
                    'code' => 'BR-02',
                    'type' => 'Satellite Express',
                    'location' => 'Moi Avenue, CBD Nairobi',
                    'phone' => '+254 711 000 111',
                    'manager' => 'Branch Lead',
                    'is_main' => false,
                    'status' => 'Active',
                    'operating_hours' => '08:00 AM - 08:00 PM',
                ]
            );

            $user = User::where('tenant_id', $tenant->id)->first();

            // 2. Customers
            $customersData = [
                ['name' => 'Ochieng Supermarket (Wholesale)', 'phone' => '+254 711 223 344', 'email' => 'orders@ochiengretails.co.ke', 'group' => 'Wholesale', 'credit_limit' => 500000, 'current_balance' => 124000],
                ['name' => 'Grace Muthoni (VIP Retail)', 'phone' => '+254 722 554 433', 'email' => 'grace.m@gmail.com', 'group' => 'VIP', 'credit_limit' => 50000, 'current_balance' => 8500],
                ['name' => 'Sarit Cafe Supplies', 'phone' => '+254 733 998 877', 'email' => 'accounts@saritcafe.com', 'group' => 'Corporate', 'credit_limit' => 300000, 'current_balance' => 89000],
                ['name' => 'Samuel Kibet', 'phone' => '+254 712 887 766', 'email' => 'samuel.kibet@gmail.com', 'group' => 'Retail', 'credit_limit' => 10000, 'current_balance' => 0],
            ];

            foreach ($customersData as $c) {
                Customer::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $c['name']],
                    [
                        ...$c,
                        'status' => 'Active',
                    ]
                );
            }

            // 3. Suppliers
            $suppliersData = [
                ['name' => 'Brookside Dairy Ltd', 'contact_person' => 'Martin Ndungu', 'phone' => '+254 722 111 222', 'email' => 'orders@brookside.co.ke', 'address' => 'Ruiru, Thika Superhighway', 'total_purchases' => 450000, 'outstanding_balance' => 65000],
                ['name' => 'Pembe Flour Mills Ltd', 'contact_person' => 'Salim Ahmed', 'phone' => '+254 733 444 555', 'email' => 'supply@pembe.co.ke', 'address' => 'Lunga Lunga Rd, Industrial Area', 'total_purchases' => 1200000, 'outstanding_balance' => 240000],
                ['name' => 'Kapa Oil Refineries', 'contact_person' => 'Jennifer Achieng', 'phone' => '+254 711 777 888', 'email' => 'sales@kapa-oil.com', 'address' => 'Mombasa Rd, Athi River', 'total_purchases' => 890000, 'outstanding_balance' => 120000],
                ['name' => 'Broadways Bakery Ltd', 'contact_person' => 'Peter Chege', 'phone' => '+254 720 999 000', 'email' => 'distribution@broadways.co.ke', 'address' => 'Thika Town Industrial Zone', 'total_purchases' => 320000, 'outstanding_balance' => 0],
            ];

            $seededSuppliers = [];
            foreach ($suppliersData as $s) {
                $supplier = Supplier::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $s['name']],
                    [
                        ...$s,
                        'status' => 'Active',
                    ]
                );
                $seededSuppliers[] = $supplier;
            }

            // 4. Products
            $productsData = [
                ['name' => 'Brookside Whole Milk 500ml', 'sku' => 'MLK-500', 'barcode' => '616110001001', 'category' => 'Dairy & Beverages', 'cost_price' => 52.00, 'selling_price' => 65.00, 'stock_quantity' => 150, 'unit' => 'pkt'],
                ['name' => 'Pembe Maize Flour 2kg', 'sku' => 'FLR-2KG', 'barcode' => '616110001002', 'category' => 'Grain & Flours', 'cost_price' => 170.00, 'selling_price' => 210.00, 'stock_quantity' => 220, 'unit' => 'bale'],
                ['name' => 'Fresh Fri Cooking Oil 2L', 'sku' => 'OIL-2L', 'barcode' => '616110001003', 'category' => 'Edible Oils & Cooking Fat', 'cost_price' => 560.00, 'selling_price' => 680.00, 'stock_quantity' => 85, 'unit' => 'btl'],
                ['name' => 'Broadways White Bread 400g', 'sku' => 'BRD-400', 'barcode' => '616110001004', 'category' => 'Bakery', 'cost_price' => 55.00, 'selling_price' => 70.00, 'stock_quantity' => 90, 'unit' => 'loaf'],
                ['name' => 'Sunlight 2in1 Washing Powder 1kg', 'sku' => 'DET-1KG', 'barcode' => '616110001005', 'category' => 'Detergents & Household', 'cost_price' => 240.00, 'selling_price' => 310.00, 'stock_quantity' => 110, 'unit' => 'pkt'],
                ['name' => 'Ketepa Pride Tea Leaves 250g', 'sku' => 'TEA-250', 'barcode' => '616110001006', 'category' => 'Dairy & Beverages', 'cost_price' => 120.00, 'selling_price' => 155.00, 'stock_quantity' => 75, 'unit' => 'pkt'],
                ['name' => 'Dawaat Basmati Rice 2kg', 'sku' => 'RCE-2KG', 'barcode' => '616110001007', 'category' => 'Grain & Flours', 'cost_price' => 410.00, 'selling_price' => 520.00, 'stock_quantity' => 60, 'unit' => 'pkt'],
            ];

            $seededProducts = [];
            foreach ($productsData as $p) {
                $prod = Product::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'sku' => $p['sku']],
                    [
                        ...$p,
                        'branch_id' => $mainBranch->id,
                        'reorder_level' => 15,
                        'status' => 'Active',
                    ]
                );
                $seededProducts[] = $prod;
            }

            // 5. Purchases
            $purchasesData = [
                [
                    'supplier_id' => $seededSuppliers[0]->id,
                    'branch_id' => $mainBranch->id,
                    'po_number' => 'PO-8821',
                    'order_date' => now()->subDays(5)->toDateString(),
                    'expected_date' => now()->subDays(3)->toDateString(),
                    'items_description' => 'Brookside Whole Milk 500ml (200 pkts)',
                    'total_amount' => 10400,
                    'paid_amount' => 10400,
                    'payment_status' => 'Paid',
                    'status' => 'Received',
                ],
                [
                    'supplier_id' => $seededSuppliers[1]->id,
                    'branch_id' => $mainBranch->id,
                    'po_number' => 'PO-8822',
                    'order_date' => now()->subDays(2)->toDateString(),
                    'expected_date' => now()->addDay()->toDateString(),
                    'items_description' => 'Pembe Maize Flour 2kg (100 bales)',
                    'total_amount' => 84000,
                    'paid_amount' => 0,
                    'payment_status' => 'Pending',
                    'status' => 'Pending',
                ],
                [
                    'supplier_id' => $seededSuppliers[2]->id,
                    'branch_id' => $secondBranch->id,
                    'po_number' => 'PO-8823',
                    'order_date' => now()->subDays(1)->toDateString(),
                    'expected_date' => now()->addDays(2)->toDateString(),
                    'items_description' => 'Fresh Fri Vegetable Oil 2L (40 cartons)',
                    'total_amount' => 55200,
                    'paid_amount' => 20000,
                    'payment_status' => 'Partial',
                    'status' => 'Approved',
                ],
            ];

            foreach ($purchasesData as $po) {
                Purchase::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'po_number' => $po['po_number']],
                    [
                        ...$po,
                        'user_id' => $user?->id,
                    ]
                );
            }

            // 6. Expenses
            $expensesData = [
                ['title' => 'KPLC Electricity Bill - Freezers & Store', 'category' => 'Electricity / Utilities', 'amount' => 18500, 'date' => now()->subDays(2)->toDateString(), 'payment_method' => 'M-Pesa Till', 'description' => 'Tokens for walk-in cold rooms'],
                ['title' => 'Store Premises Monthly Lease', 'category' => 'Rent', 'amount' => 95000, 'date' => now()->startOfMonth()->toDateString(), 'payment_method' => 'Bank Transfer', 'description' => 'Main store premises rent'],
                ['title' => 'Bulk Stock Offloading Logistics', 'category' => 'Transport & Logistics', 'amount' => 6500, 'date' => now()->subDays(1)->toDateString(), 'payment_method' => 'Petty Cash', 'description' => 'Logistics truck unloading wages'],
            ];

            foreach ($expensesData as $e) {
                Expense::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'title' => $e['title']],
                    [
                        ...$e,
                        'branch_id' => $mainBranch->id,
                        'user_id' => $user?->id,
                    ]
                );
            }

            // 7. Sales & Sale Items
            if (Sale::where('tenant_id', $tenant->id)->count() === 0 && count($seededProducts) >= 3) {
                $p1 = $seededProducts[0];
                $p2 = $seededProducts[1];
                $p3 = $seededProducts[2];

                // Sale 1
                $sub1 = ($p1->selling_price * 2) + ($p2->selling_price * 1);
                $profit1 = (($p1->selling_price - $p1->cost_price) * 2) + ($p2->selling_price - $p2->cost_price);
                $sale1 = Sale::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $mainBranch->id,
                    'user_id' => $user?->id,
                    'receipt_number' => 'REC-' . date('ymd') . '-T' . $tenant->id . '-0001-' . strtoupper(Str::random(3)),
                    'subtotal' => $sub1,
                    'tax_amount' => round($sub1 * 0.16, 2),
                    'discount_amount' => 0,
                    'total' => $sub1,
                    'profit' => $profit1,
                    'payment_method' => 'M-Pesa Buy Goods Till',
                    'payment_status' => 'Completed',
                    'customer_name' => 'Walk-in Retail Customer',
                    'customer_phone' => '+254 712 334 455',
                    'created_at' => now()->subHours(3),
                ]);

                SaleItem::create([
                    'sale_id' => $sale1->id,
                    'product_id' => $p1->id,
                    'product_name' => $p1->name,
                    'quantity' => 2,
                    'unit_price' => $p1->selling_price,
                    'cost_price' => $p1->cost_price,
                    'total' => $p1->selling_price * 2,
                ]);

                SaleItem::create([
                    'sale_id' => $sale1->id,
                    'product_id' => $p2->id,
                    'product_name' => $p2->name,
                    'quantity' => 1,
                    'unit_price' => $p2->selling_price,
                    'cost_price' => $p2->cost_price,
                    'total' => $p2->selling_price,
                ]);

                // Sale 2
                $sub2 = ($p3->selling_price * 3);
                $profit2 = ($p3->selling_price - $p3->cost_price) * 3;
                $sale2 = Sale::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $mainBranch->id,
                    'user_id' => $user?->id,
                    'receipt_number' => 'REC-' . date('ymd') . '-T' . $tenant->id . '-0002-' . strtoupper(Str::random(3)),
                    'subtotal' => $sub2,
                    'tax_amount' => round($sub2 * 0.16, 2),
                    'discount_amount' => 0,
                    'total' => $sub2,
                    'profit' => $profit2,
                    'payment_method' => 'Cash',
                    'payment_status' => 'Completed',
                    'customer_name' => 'Ochieng Supermarket (Wholesale)',
                    'customer_phone' => '+254 711 223 344',
                    'created_at' => now()->subHour(),
                ]);

                SaleItem::create([
                    'sale_id' => $sale2->id,
                    'product_id' => $p3->id,
                    'product_name' => $p3->name,
                    'quantity' => 3,
                    'unit_price' => $p3->selling_price,
                    'cost_price' => $p3->cost_price,
                    'total' => $p3->selling_price * 3,
                ]);
            }

            // 8. Tenant default settings
            if (empty($tenant->settings)) {
                $tenant->update([
                    'settings' => [
                        'vat_rate' => 16,
                        'receipt_header' => "Welcome to {$tenant->name}\nThank you for shopping with us!",
                        'receipt_footer' => "Goods once sold cannot be returned without receipt.\nPowered by MDE POS Kenya",
                        'enable_etims' => true,
                        'low_stock_threshold' => 10,
                        'payment_methods' => [
                            ['id' => '1', 'name' => 'Cash', 'type' => 'Cash', 'enabled' => true, 'feePercent' => 0, 'accountRef' => 'Till Drawer'],
                            ['id' => '2', 'name' => 'M-Pesa Buy Goods Till', 'type' => 'Mobile Money', 'enabled' => true, 'feePercent' => 0, 'accountRef' => 'Till # 523910'],
                            ['id' => '3', 'name' => 'Visa & Mastercard Debit/Credit', 'type' => 'Card', 'enabled' => true, 'feePercent' => 1.5, 'accountRef' => 'PDQ Terminal'],
                        ],
                    ],
                ]);
            }
        }
    }
}
