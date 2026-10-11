<script setup lang="ts">
import ReportsGuideOverlay from '@/components/reports/ReportsGuideOverlay.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import StatCard from '@/components/ui/StatCard.vue';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, CircleDollarSign, Compass, Download, FileSpreadsheet, PieChart, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    pnl?: {
        revenue: number;
        cogs: number;
        grossProfit: number;
        operatingExpenses: number;
        netProfit: number;
        margin: number;
    };
    categoryBreakdown?: Array<{
        name: string;
        sales: number;
        margin: number;
    }>;
    cashierPerformance?: Array<{
        cashier: string;
        shift: string;
        tickets: number;
        volume: number;
        avgTicket: number;
    }>;
    currentRange?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports & P&L', href: '/reports' },
];

const { currentTenant } = useTenant();
const { success, info } = useToast();

const selectedRange = ref(props.currentRange || 'month');

const pnl = computed(
    () =>
        props.pnl ?? {
            revenue: 0,
            cogs: 0,
            grossProfit: 0,
            operatingExpenses: 0,
            netProfit: 0,
            margin: 0,
        },
);

const categoryBreakdown = computed(() => props.categoryBreakdown ?? []);
const cashierPerformance = computed(() => props.cashierPerformance ?? []);

const switchRange = (range: string) => {
    selectedRange.value = range;
    router.get('/reports', { range }, { preserveState: true, preserveScroll: true });
};

const handleExportPDF = () => {
    info('Generating Report', 'Exporting Profit & Loss PDF statement...');
    setTimeout(() => {
        window.print();
        success('Print Dialog Opened', 'Report statement ready.');
    }, 500);
};

const handleExportCSV = () => {
    info('Generating CSV', 'Preparing CSV raw transaction ledger data...');
    const rows = [
        ['Category / Item', 'Sales (KES)', 'Margin %'],
        ...categoryBreakdown.value.map((c) => [c.name, c.sales.toString(), `${c.margin}%`]),
    ];
    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map((e) => e.join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `report-${selectedRange.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    success('CSV Exported', 'Downloaded ledger CSV file.');
};

// --- Page Guide Overlay State ---
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openGuide = (step = 0) => {
    guideCurrentStep.value = step;
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'start-walkthrough') {
        guideCurrentStep.value = 1;
    } else if (actionType === 'filter-month') {
        switchRange('month');
    } else if (actionType === 'export-csv') {
        handleExportCSV();
    } else if (actionType === 'print-statement') {
        handleExportPDF();
    }
};
</script>

<template>
    <Head title="Business Reports & P&L" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground">Financial & Operational Analytics</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Profit & Loss (P&L), category performance, cashier auditing, and tax reports.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        id="reports-tour-guide-btn"
                        variant="outline"
                        size="sm"
                        class="h-9 gap-1.5 border-primary/30 text-xs font-semibold text-primary hover:bg-primary/5"
                        @click="openGuide(0)"
                    >
                        <Compass class="size-3.5" />
                        Page Guide
                    </Button>

                    <div id="reports-tour-date-filter" class="flex rounded-lg border border-border bg-card p-0.5 text-xs font-medium">
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-2.5 py-1 transition-colors',
                                selectedRange === 'today'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="switchRange('today')"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-2.5 py-1 transition-colors',
                                selectedRange === 'week'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="switchRange('week')"
                        >
                            7 Days
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-2.5 py-1 transition-colors',
                                selectedRange === 'month'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="switchRange('month')"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-2.5 py-1 transition-colors',
                                selectedRange === 'year'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="switchRange('year')"
                        >
                            This Year
                        </button>
                    </div>

                    <Button id="reports-tour-export-csv" variant="outline" size="sm" class="h-9 gap-1.5 text-xs" @click="handleExportCSV">
                        <FileSpreadsheet class="size-3.5" />
                        Export Excel / CSV
                    </Button>
                    <Button id="reports-tour-print-pdf" size="sm" class="h-9 gap-1.5 bg-primary text-xs" @click="handleExportPDF">
                        <Download class="size-3.5" />
                        Print Statement
                    </Button>
                </div>
            </div>

            <!-- P&L Summary Cards -->
            <div id="reports-tour-kpis" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <StatCard
                    title="Gross Sales Revenue"
                    :value="formatCurrency(pnl.revenue)"
                    change-text="top-line revenue"
                    :icon="TrendingUp"
                    icon-color="text-emerald-600"
                />
                <StatCard
                    title="Cost of Goods Sold (COGS)"
                    :value="formatCurrency(pnl.cogs)"
                    change-text="inventory procurement"
                    :icon="CircleDollarSign"
                    icon-color="text-muted-foreground"
                />
                <StatCard
                    title="Gross Profit"
                    :value="formatCurrency(pnl.grossProfit)"
                    :change-text="`margin ~${pnl.margin}%`"
                    :icon="PieChart"
                    icon-color="text-primary"
                />
                <StatCard
                    title="Operating Expenses"
                    :value="formatCurrency(pnl.operatingExpenses)"
                    change-text="rent, utilities, overheads"
                    :icon="CircleDollarSign"
                    icon-color="text-rose-600"
                />
                <StatCard
                    title="Net Profit (EBITDA)"
                    :value="formatCurrency(pnl.netProfit)"
                    :change-text="pnl.netProfit >= 0 ? 'profitable period' : 'operating loss'"
                    :icon="BarChart3"
                    :icon-color="pnl.netProfit >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                    :icon-bg="pnl.netProfit >= 0 ? 'bg-emerald-500/10' : 'bg-rose-500/10'"
                />
            </div>

            <!-- Profit & Loss Statement Table Component -->
            <Card id="reports-tour-pnl-table" class="border-border/80">
                <CardHeader class="flex flex-row items-center justify-between border-b border-border/70 pb-3">
                    <div>
                        <CardTitle class="text-base font-bold">Comprehensive Profit & Loss Statement</CardTitle>
                        <CardDescription class="text-xs">Period: {{ selectedRange.toUpperCase() }}</CardDescription>
                    </div>
                    <Badge :variant="pnl.netProfit >= 0 ? 'success' : 'destructive'">
                        {{ pnl.netProfit >= 0 ? 'Positive Net Return' : 'Overhead Deficit' }}
                    </Badge>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="divide-y divide-border/60 font-mono text-xs">
                        <div class="flex justify-between bg-muted/20 p-4 text-sm font-bold">
                            <span>1. REVENUE / GROSS SALES</span>
                            <span class="text-foreground">{{ formatCurrency(pnl.revenue) }}</span>
                        </div>
                        <div class="flex justify-between p-3.5 pl-8 text-muted-foreground">
                            <span>Less: Cost of Goods Sold (COGS)</span>
                            <span class="text-rose-600">- {{ formatCurrency(pnl.cogs) }}</span>
                        </div>
                        <div class="flex justify-between bg-muted/40 p-4 text-sm font-bold">
                            <span>2. GROSS PROFIT</span>
                            <span class="text-primary">{{ formatCurrency(pnl.grossProfit) }}</span>
                        </div>
                        <div class="flex justify-between p-3.5 pl-8 text-muted-foreground">
                            <span>Operating Overheads (Electricity, Rent, Transport, Maintenance)</span>
                            <span class="text-rose-600">- {{ formatCurrency(pnl.operatingExpenses) }}</span>
                        </div>
                        <div class="flex justify-between bg-emerald-500/10 p-4 text-base font-black text-emerald-900 dark:text-emerald-300">
                            <span>3. NET PROFIT</span>
                            <span>{{ formatCurrency(pnl.netProfit) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Bottom Row: Category Performance & Cashier Auditing -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Category Breakdown -->
                <Card id="reports-tour-category-breakdown" class="border-border/80">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-bold">Category Profitability</CardTitle>
                        <CardDescription class="text-xs">Sales contribution and profit margin by department</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="categoryBreakdown.length === 0" class="p-6 text-center text-xs text-muted-foreground">
                            No sales transactions recorded in this period yet.
                        </div>
                        <div v-else class="divide-y divide-border/60 text-xs">
                            <div v-for="cat in categoryBreakdown" :key="cat.name" class="flex items-center justify-between p-3.5 hover:bg-muted/20">
                                <div>
                                    <p class="font-bold text-foreground">{{ cat.name }}</p>
                                    <p class="text-[11px] text-muted-foreground">Avg gross margin: {{ cat.margin }}%</p>
                                </div>
                                <div class="text-right font-mono">
                                    <p class="font-bold text-foreground">{{ formatCurrency(cat.sales) }}</p>
                                    <Badge variant="outline" class="mt-0.5 text-[10px]">
                                        {{ pnl.revenue > 0 ? Math.round((cat.sales / pnl.revenue) * 100) : 0 }}% share
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Cashier Performance Auditing -->
                <Card id="reports-tour-cashier-performance" class="border-border/80">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-bold">Cashier Shift Performance</CardTitle>
                        <CardDescription class="text-xs">Transaction volume & average basket size per operator</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="cashierPerformance.length === 0" class="p-6 text-center text-xs text-muted-foreground">
                            No cashier shift data recorded in this period yet.
                        </div>
                        <div v-else class="divide-y divide-border/60 text-xs">
                            <div v-for="c in cashierPerformance" :key="c.cashier" class="flex items-center justify-between p-3.5 hover:bg-muted/20">
                                <div>
                                    <p class="font-bold text-foreground">{{ c.cashier }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ c.shift }} • {{ c.tickets }} receipts issued</p>
                                </div>
                                <div class="text-right font-mono">
                                    <p class="font-bold text-foreground">{{ formatCurrency(c.volume) }}</p>
                                    <p class="text-[11px] text-muted-foreground">Avg: {{ formatCurrency(c.avgTicket) }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Reports & P&L Interactive Tour Overlay -->
        <ReportsGuideOverlay v-model:open="guideOverlayOpen" v-model:current-step="guideCurrentStep" @action="handleGuideAction" />
    </AppLayout>
</template>
