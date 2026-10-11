<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantDetailController extends Controller
{
    /**
     * Full tenant overview — all modules in one page.
     */
    public function show(Tenant $tenant): Response
    {
        $tid = $tenant->id;
        $today = now()->startOfDay();
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $sevenDaysAgo  = now()->subDays(7)->startOfDay();

        // ── KPIs ─────────────────────────────────────────────────────────────
        $todaySales     = (float) Sale::where('tenant_id', $tid)->where('created_at', '>=', $today)->sum('total');
        $todayProfit    = (float) Sale::where('tenant_id', $tid)->where('created_at', '>=', $today)->sum('profit');
        $todayTx        = Sale::where('tenant_id', $tid)->where('created_at', '>=', $today)->count();
        $sevenDaySales  = (float) Sale::where('tenant_id', $tid)->where('created_at', '>=', $sevenDaysAgo)->sum('total');
        $thirtyDaySales = (float) Sale::where('tenant_id', $tid)->where('created_at', '>=', $thirtyDaysAgo)->sum('total');
        $thirtyDayTx    = Sale::where('tenant_id', $tid)->where('created_at', '>=', $thirtyDaysAgo)->count();
        $thirtyDayExp   = (float) Expense::where('tenant_id', $tid)->where('date', '>=', $thirtyDaysAgo->toDateString())->sum('amount');
        $stockValue     = (float) (Product::where('tenant_id', $tid)->selectRaw('SUM(cost_price * stock_quantity) as v')->value('v') ?? 0);
        $totalProducts  = Product::where('tenant_id', $tid)->count();
        $lowStockCount  = Product::where('tenant_id', $tid)->whereColumn('stock_quantity', '<=', 'reorder_level')->count();

        $kpis = [
            'today_sales'             => $todaySales,
            'today_profit'            => $todayProfit,
            'today_transactions'      => $todayTx,
            'seven_day_sales'         => $sevenDaySales,
            'thirty_day_sales'        => $thirtyDaySales,
            'thirty_day_transactions' => $thirtyDayTx,
            'thirty_day_expenses'     => $thirtyDayExp,
            'stock_value'             => $stockValue,
            'total_products'          => $totalProducts,
            'low_stock_count'         => $lowStockCount,
            'total_users'             => User::where('tenant_id', $tid)->count(),
            'total_branches'          => Branch::where('tenant_id', $tid)->count(),
            'total_customers'         => Customer::where('tenant_id', $tid)->count(),
            'total_suppliers'         => Supplier::where('tenant_id', $tid)->count(),
        ];

        // ── Payment Mix ───────────────────────────────────────────────────────
        $totalSalesBase = $thirtyDaySales > 0 ? $thirtyDaySales : 1;
        $paymentMix = Sale::where('tenant_id', $tid)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('payment_method, SUM(total) as amount, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->map(fn($pm) => [
                'method'  => $pm->payment_method,
                'amount'  => (float) $pm->amount,
                'percent' => round(((float) $pm->amount / $totalSalesBase) * 100, 1),
                'count'   => $pm->count,
            ]);

        // ── Recent Sales ─────────────────────────────────────────────────────
        $recentSales = Sale::where('tenant_id', $tid)
            ->with(['branch', 'user'])
            ->latest()
            ->take(15)
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'receipt_number' => $s->receipt_number,
                'total'          => (float) $s->total,
                'profit'         => (float) $s->profit,
                'payment_method' => $s->payment_method,
                'payment_status' => $s->payment_status,
                'cashier'        => $s->user?->name ?? 'Staff',
                'branch'         => $s->branch?->name ?? 'Main Store',
                'time'           => $s->created_at->diffForHumans(),
                'date'           => $s->created_at->toDateString(),
            ]);

        // ── Products ─────────────────────────────────────────────────────────
        $products = Product::where('tenant_id', $tid)
            ->with('branch')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'sku'            => $p->sku ?? 'SKU-' . $p->id,
                'barcode'        => $p->barcode,
                'category'       => $p->category ?? 'General',
                'cost_price'     => (float) $p->cost_price,
                'selling_price'  => (float) $p->selling_price,
                'stock_quantity' => (int) $p->stock_quantity,
                'reorder_level'  => (int) $p->reorder_level,
                'unit'           => $p->unit,
                'status'         => $p->status,
                'branch_name'    => $p->branch?->name ?? 'All Branches',
                'low_stock'      => $p->stock_quantity <= $p->reorder_level,
            ]);

        // ── Branches ─────────────────────────────────────────────────────────
        $branchSalesMap = Sale::where('tenant_id', $tid)
            ->where('created_at', '>=', $today)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, SUM(total) as total_sales, COUNT(*) as tx_count')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $branches = Branch::where('tenant_id', $tid)->get()->map(fn($b) => [
            'id'              => $b->id,
            'name'            => $b->name,
            'code'            => $b->code,
            'type'            => $b->type,
            'location'        => $b->location,
            'phone'           => $b->phone,
            'manager'         => $b->manager,
            'operating_hours' => $b->operating_hours,
            'status'          => $b->status,
            'is_main'         => (bool) $b->is_main,
            'today_sales'     => (float) ($branchSalesMap->get($b->id)?->total_sales ?? 0),
            'today_tx'        => (int)   ($branchSalesMap->get($b->id)?->tx_count ?? 0),
        ]);

        // ── Staff Users ───────────────────────────────────────────────────────
        $users = User::where('tenant_id', $tid)
            ->orderByRaw("CASE role
                WHEN 'Owner' THEN 1
                WHEN 'Administrator' THEN 2
                WHEN 'Manager' THEN 3
                WHEN 'Supervisor' THEN 4
                WHEN 'Cashier' THEN 5
                WHEN 'Inventory Manager' THEN 6
                WHEN 'Accountant' THEN 7
                ELSE 8 END")
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'phone'      => $u->phone,
                'status'     => $u->status ?? 'Active',
                'created_at' => $u->created_at?->toDateString(),
                'last_login' => $u->updated_at?->diffForHumans(),
            ]);

        // ── Expenses (last 30 days) ───────────────────────────────────────────
        $expenses = Expense::where('tenant_id', $tid)
            ->where('date', '>=', $thirtyDaysAgo->toDateString())
            ->with('user')
            ->latest('date')
            ->take(30)
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'description'    => $e->description,
                'category'       => $e->category ?? 'General',
                'amount'         => (float) $e->amount,
                'date'           => $e->date,
                'recorded_by'    => $e->user?->name ?? 'Staff',
                'payment_method' => $e->payment_method ?? 'Cash',
                'notes'          => $e->notes,
            ]);

        // ── Purchases (last 20) ──────────────────────────────────────────────
        $purchases = Purchase::where('tenant_id', $tid)
            ->with(['supplier', 'branch'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'lpo_number'     => $p->lpo_number,
                'supplier'       => $p->supplier?->name ?? 'Direct Purchase',
                'branch'         => $p->branch?->name ?? 'HQ',
                'total_amount'   => (float) $p->total_amount,
                'amount_paid'    => (float) $p->amount_paid,
                'status'         => $p->status,
                'payment_status' => $p->payment_status,
                'ordered_at'     => $p->ordered_at?->toDateString(),
                'received_at'    => $p->received_at?->toDateString(),
            ]);

        // ── Customers ────────────────────────────────────────────────────────
        $customers = Customer::where('tenant_id', $tid)
            ->latest()
            ->take(30)
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'phone'          => $c->phone,
                'email'          => $c->email,
                'credit_limit'   => (float) $c->credit_limit,
                'credit_balance' => (float) $c->credit_balance,
                'status'         => $c->status ?? 'Active',
                'created_at'     => $c->created_at?->toDateString(),
            ]);

        // ── Suppliers ────────────────────────────────────────────────────────
        $suppliers = Supplier::where('tenant_id', $tid)
            ->latest()
            ->take(30)
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'contact'     => $s->contact,
                'phone'       => $s->phone,
                'email'       => $s->email,
                'category'    => $s->category,
                'balance_due' => (float) $s->balance_due,
                'status'      => $s->status ?? 'Active',
                'created_at'  => $s->created_at?->toDateString(),
            ]);

        return Inertia::render('super-admin/TenantDetail', [
            'tenant'      => [
                'id'              => $tenant->id,
                'name'            => $tenant->name,
                'slug'            => $tenant->slug,
                'kra_pin'         => $tenant->kra_pin,
                'email'           => $tenant->email,
                'phone'           => $tenant->phone,
                'country'         => $tenant->country,
                'currency'        => $tenant->currency ?? 'KES',
                'business_type'   => $tenant->business_type,
                'plan'            => $tenant->plan,
                'billing_cycle'   => $tenant->billing_cycle,
                'status'          => $tenant->status,
                'is_demo'         => (bool) $tenant->is_demo,
                'branch_count'    => $tenant->branch_count,
                'storage_quota_mb'=> $tenant->storage_quota_mb,
                'enabled_modules' => $tenant->enabled_modules ?? [],
                'settings'        => $tenant->settings ?? [],
                'subdomain_url'   => $tenant->getSubdomainUrl('/dashboard'),
                'created_at'      => $tenant->created_at?->toDateString(),
            ],
            'kpis'        => $kpis,
            'paymentMix'  => $paymentMix,
            'recentSales' => $recentSales,
            'products'    => $products,
            'branches'    => $branches,
            'users'       => $users,
            'expenses'    => $expenses,
            'purchases'   => $purchases,
            'customers'   => $customers,
            'suppliers'   => $suppliers,
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // ── User sub-actions ─────────────────────────────────────────────────────

    public function storeUser(Request $request, Tenant $tenant): RedirectResponse
    {
        $v = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|in:Owner,Administrator,Manager,Supervisor,Cashier,Inventory Manager,Accountant',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name'              => $v['name'],
            'email'             => $v['email'],
            'password'          => Hash::make($v['password']),
            'email_verified_at' => now(),
            'tenant_id'         => $tenant->id,
            'role'              => $v['role'],
            'phone'             => $v['phone'] ?? null,
            'is_super_admin'    => false,
            'status'            => 'Active',
        ]);

        return back()->with('success', "{$v['name']} added to {$tenant->name}.");
    }

    public function updateUser(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->id, 403);
        $v = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'role'   => 'sometimes|in:Owner,Administrator,Manager,Supervisor,Cashier,Inventory Manager,Accountant',
            'phone'  => 'nullable|string|max:20',
            'status' => 'sometimes|in:Active,Suspended',
        ]);
        $user->update($v);
        return back()->with('success', "{$user->name} updated.");
    }

    public function resetUserPassword(Tenant $tenant, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->id, 403);
        $temp = Str::password(12, symbols: false);
        $user->update(['password' => Hash::make($temp)]);
        return back()->with('success', "Password reset for {$user->name}. New temp: {$temp}");
    }

    public function destroyUser(Tenant $tenant, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->id, 403);
        if ($user->role === 'Owner' && $tenant->users()->where('role', 'Owner')->count() <= 1) {
            return back()->withErrors(['error' => 'Cannot delete the last Owner.']);
        }
        $name = $user->name;
        $user->delete();
        return back()->with('success', "{$name} removed from {$tenant->name}.");
    }

    // ── Tenant profile update ────────────────────────────────────────────────

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $v = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'email'             => 'sometimes|email|unique:tenants,email,' . $tenant->id,
            'phone'             => 'nullable|string|max:20',
            'kra_pin'           => 'nullable|string|max:20',
            'country'           => 'nullable|string|max:50',
            'currency'          => 'nullable|string|max:5',
            'plan'              => 'sometimes|in:Basic,Pro,Enterprise',
            'billing_cycle'     => 'sometimes|in:Weekly,Monthly,Quarterly,Semi-Annual,Annual',
            'status'            => 'sometimes|in:Trial,Active,Suspended',
            'storage_quota_mb'  => 'nullable|integer|min:512',
            'branch_count'      => 'nullable|integer|min:1',
            'enabled_modules'   => 'nullable|array',
            'settings'          => 'nullable|array',
        ]);
        if (isset($v['settings'])) {
            $v['settings'] = array_merge($tenant->settings ?? [], $v['settings']);
        }
        $tenant->update($v);
        return back()->with('success', "{$tenant->name} updated successfully.");
    }
}
