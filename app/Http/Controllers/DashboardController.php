<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ClockSession;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $today = now()->startOfDay();

        // Real Sales Calculations
        $todaySales = (float) Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $today)->sum('total');
        $todayProfit = (float) Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $today)->sum('profit');
        $todayTransactions = Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $today)->count();

        // 7 Days
        $sevenDaysAgo = now()->subDays(7)->startOfDay();
        $sevenDaysSales = (float) Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $sevenDaysAgo)->sum('total');
        $sevenDaysProfit = (float) Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $sevenDaysAgo)->sum('profit');
        $sevenDaysTransactions = Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $sevenDaysAgo)->count();

        // 30 Days
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $thirtyDaysSales = (float) Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $thirtyDaysAgo)->sum('total');
        $thirtyDaysProfit = (float) Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $thirtyDaysAgo)->sum('profit');
        $thirtyDaysTransactions = Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $thirtyDaysAgo)->count();

        // Stock Value
        $stockValue = (float) (Product::where('tenant_id', $tenantId)->selectRaw('SUM(cost_price * stock_quantity) as total_val')->value('total_val') ?: 0);

        // Expenses
        $todayExpenses = (float) Expense::where('tenant_id', $tenantId)->where('date', now()->toDateString())->sum('amount');
        $thirtyDaysExpenses = (float) Expense::where('tenant_id', $tenantId)->where('date', '>=', $thirtyDaysAgo->toDateString())->sum('amount');

        // Payment Breakdown (All time or 30 days)
        $paymentMethods = Sale::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('payment_method, SUM(total) as amount, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        $totalThirtyDaysSales = $thirtyDaysSales > 0 ? $thirtyDaysSales : 1;
        $paymentMix = $paymentMethods->map(fn($pm) => [
            'method' => $pm->payment_method,
            'amount' => (float) $pm->amount,
            'percent' => round(((float) $pm->amount / $totalThirtyDaysSales) * 100, 1),
            'count' => $pm->count,
        ]);

        // Recent Sales
        $recentSales = Sale::where('tenant_id', $tenantId)
            ->with(['branch', 'user'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'receipt_number' => $s->receipt_number,
                'total' => (float) $s->total,
                'payment_method' => $s->payment_method,
                'payment_status' => $s->payment_status,
                'cashier' => $s->user?->name ?? 'Staff',
                'branch' => $s->branch?->name ?? 'Main Store',
                'time' => $s->created_at->diffForHumans(),
            ]);

        // Clocked in staff
        $activeSessions = ClockSession::where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->with(['user', 'branch'])
            ->get()
            ->map(fn($cs) => [
                'id' => $cs->id,
                'name' => $cs->user?->name ?? 'User',
                'role' => $cs->user?->role ?? 'Staff',
                'branch' => $cs->branch?->name ?? 'Main Store',
                'clocked_in_at' => $cs->clocked_in_at->format('h:i A'),
                'duration' => $cs->clocked_in_at->diffForHumans(null, true),
            ]);

        // Pre-aggregate branch statistics to eliminate N+1 queries
        $branchStockValues = Product::where('tenant_id', $tenantId)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, SUM(cost_price * stock_quantity) as val')
            ->groupBy('branch_id')
            ->pluck('val', 'branch_id');

        $branchTodaySales = Sale::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $today)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, SUM(total) as total_sales, COUNT(*) as tx_count')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        // Branches with real stats
        $branches = Branch::where('tenant_id', $tenantId)
            ->get()
            ->map(function ($b) use ($branchStockValues, $branchTodaySales) {
                $salesData = $branchTodaySales->get($b->id);
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'code' => $b->code,
                    'type' => $b->type,
                    'location' => $b->location,
                    'manager' => $b->manager,
                    'status' => $b->status,
                    'is_main' => (bool) $b->is_main,
                    'stock_value' => (float) ($branchStockValues->get($b->id) ?? 0),
                    'today_sales' => (float) ($salesData?->total_sales ?? 0),
                    'transactions_today' => (int) ($salesData?->tx_count ?? 0),
                ];
            });

        $kpis = [
            'today' => [
                'sales' => $todaySales,
                'profit' => $todayProfit,
                'transactions' => $todayTransactions,
                'stock_value' => $stockValue,
                'expenses' => $todayExpenses,
            ],
            'seven_days' => [
                'sales' => $sevenDaysSales,
                'profit' => $sevenDaysProfit,
                'transactions' => $sevenDaysTransactions,
                'stock_value' => $stockValue,
                'expenses' => $thirtyDaysExpenses,
            ],
            'thirty_days' => [
                'sales' => $thirtyDaysSales,
                'profit' => $thirtyDaysProfit,
                'transactions' => $thirtyDaysTransactions,
                'stock_value' => $stockValue,
                'expenses' => $thirtyDaysExpenses,
            ],
        ];

        return Inertia::render('Dashboard', [
            'kpis' => $kpis,
            'paymentMix' => $paymentMix,
            'recentSales' => $recentSales,
            'activeSessions' => $activeSessions,
            'branches' => $branches,
        ]);
    }
}
