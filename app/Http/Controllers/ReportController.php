<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $range = $request->query('range', 'month');

        $startDate = match ($range) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7)->startOfDay(),
            'quarter' => now()->subDays(90)->startOfDay(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        // Revenue and Gross Profit from Sales
        $salesQuery = Sale::where('tenant_id', $tenantId)->where('created_at', '>=', $startDate);
        $revenue = (float) (clone $salesQuery)->sum('total');
        $grossProfit = (float) (clone $salesQuery)->sum('profit');
        $cogs = max(0, $revenue - $grossProfit);

        // Operating Expenses
        $operatingExpenses = (float) Expense::where('tenant_id', $tenantId)
            ->where('date', '>=', $startDate->toDateString())
            ->sum('amount');

        $netProfit = $grossProfit - $operatingExpenses;
        $margin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0;

        $pnl = [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'grossProfit' => $grossProfit,
            'operatingExpenses' => $operatingExpenses,
            'netProfit' => $netProfit,
            'margin' => $margin,
        ];

        // Category Breakdown from Products and SaleItems
        $categoryBreakdown = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.created_at', '>=', $startDate)
            ->selectRaw('COALESCE(products.category, "General") as name, SUM(sale_items.total) as sales, SUM((sale_items.unit_price - sale_items.cost_price) * sale_items.quantity) as profit')
            ->groupBy('name')
            ->orderByDesc('sales')
            ->take(6)
            ->get()
            ->map(fn ($cat) => [
                'name' => $cat->name,
                'sales' => (float) $cat->sales,
                'margin' => (float) $cat->sales > 0 ? round(((float) $cat->profit / (float) $cat->sales) * 100, 1) : 0,
            ]);

        // Fallback default categories if no sale items yet
        if ($categoryBreakdown->isEmpty()) {
            $categoryBreakdown = collect([
                ['name' => 'General Merchandise', 'sales' => $revenue, 'margin' => $margin],
            ]);
        }

        // Cashier shift performance
        $cashierStats = Sale::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('user_id, COUNT(*) as tickets, SUM(total) as volume')
            ->groupBy('user_id')
            ->with('user')
            ->get()
            ->map(function ($cs) {
                $vol = (float) $cs->volume;
                $tix = (int) $cs->tickets;

                return [
                    'cashier' => $cs->user?->name ?? 'Staff Cashier',
                    'shift' => 'General Shift',
                    'tickets' => $tix,
                    'volume' => $vol,
                    'avgTicket' => $tix > 0 ? round($vol / $tix) : 0,
                ];
            });

        if ($cashierStats->isEmpty()) {
            $cashierStats = collect([
                ['cashier' => $request->user()->name ?? 'Manager', 'shift' => 'All Shifts', 'tickets' => 0, 'volume' => 0, 'avgTicket' => 0],
            ]);
        }

        return Inertia::render('reports/Index', [
            'pnl' => $pnl,
            'categoryBreakdown' => $categoryBreakdown,
            'cashierPerformance' => $cashierStats,
            'currentRange' => $range,
        ]);
    }
}
