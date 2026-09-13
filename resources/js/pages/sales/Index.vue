<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import ReceiptModal from '@/components/pos/ReceiptModal.vue';
import type { CompletedSale } from '@/composables/useCart';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Download,
    Eye,
    FileSpreadsheet,
    Lock,
    Printer,
    Receipt,
    RotateCcw,
    ShieldAlert,
    ShoppingCart,
    UserCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    sales?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales Management', href: '/sales' },
];

const page = usePage();
const { currentTenant } = useTenant();
const { currentRole, can } = usePermissions();
const { success, info, warning } = useToast();

const authUser = computed(() => page?.props?.auth?.user as any);
const isCashier = computed(() => currentRole.value === 'Cashier' || authUser.value?.role === 'Cashier');

const salesList = computed<CompletedSale[]>(() => {
    if (props.sales && props.sales.length > 0) {
        return props.sales.map(s => ({
            receiptNumber: s.receipt_number,
            date: s.created_at,
            tenantName: currentTenant.value?.name || 'My Store',
            branchName: s.branch,
            cashierName: s.cashier,
            customer: {
                id: '1',
                name: s.customer_name || 'Walk-in Customer',
                phone: s.customer_phone || '',
                email: '',
                group: 'Retail',
                creditLimit: 0,
                currentBalance: 0,
                status: 'Active',
            },
            items: (s.items || []).map((item: any) => ({
                product: {
                    id: item.id?.toString() || '1',
                    sku: 'SKU',
                    barcode: '',
                    name: item.product_name,
                    category: 'General',
                    brand: '',
                    unit: 'Piece',
                    costPrice: 0,
                    retailPrice: item.unit_price,
                    wholesalePrice: item.unit_price,
                    stock: 10,
                    taxRate: 16,
                },
                quantity: item.quantity,
                priceType: 'retail',
                unitPrice: item.unit_price,
                discountPercent: 0,
            })),
            subtotal: s.subtotal,
            discountAmount: s.discount_amount,
            taxAmount: s.tax_amount,
            total: s.total,
            payments: [{ method: s.payment_method, amount: s.total }],
            changeGiven: 0,
            status: s.payment_status,
        }));
    }
    return [];
});

const salesScope = ref<'mine' | 'all'>(isCashier.value ? 'mine' : 'all');

const isMySale = (sale: CompletedSale) => {
    if (!authUser.value?.name) return true;
    const authName = authUser.value.name.toLowerCase();
    const cashierName = (sale.cashierName || '').toLowerCase();
    return cashierName.includes('cashier') || authName.includes(cashierName) || cashierName.includes(authName) || cashierName.includes('lead cashier');
};

const displayedSales = computed(() => {
    if (salesScope.value === 'mine') {
        return salesList.value.filter(s => isMySale(s));
    }
    return salesList.value;
});

const columns = [
    { key: 'receiptNumber', label: 'Receipt #' },
    { key: 'date', label: 'Date & Time' },
    { key: 'customer', label: 'Customer' },
    { key: 'cashierName', label: 'Cashier / Register' },
    { key: 'subtotal', label: 'Subtotal', align: 'right' as const },
    { key: 'taxAmount', label: 'VAT 16%', align: 'right' as const },
    { key: 'total', label: 'Total (KES)', align: 'right' as const },
    { key: 'payments', label: 'Payment Method', align: 'center' as const },
    { key: 'status', label: 'Status', align: 'center' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
];

const selectedSale = ref<CompletedSale | null>(null);
const receiptOpen = ref(false);

const viewReceipt = (sale: CompletedSale) => {
    selectedSale.value = sale;
    receiptOpen.value = true;
};

const handleRefundClick = () => {
    if (!can('sales', 'edit')) {
        warning('Supervisor Authorization Required', 'Cashiers cannot initiate refunds directly. Please call a Supervisor or Manager to authorize.');
        return;
    }
};
</script>

<template>
    <Head title="Sales Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-8 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-foreground">Sales Transaction History</h2>
                        <Badge v-if="isCashier" variant="outline" class="bg-primary/10 text-primary border-primary/20 text-xs">
                            Cashier Mode
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Audit receipts, payment channels, cashier performance, and reprint thermal invoices.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="!can('sales', 'edit')"
                        variant="outline"
                        size="sm"
                        class="h-9 text-xs gap-1.5 text-muted-foreground border-border/80 hover:bg-muted/50"
                        @click="handleRefundClick"
                    >
                        <Lock class="size-3.5 text-amber-500" />
                        Refunds (Supervisor Required)
                    </Button>
                    <Link v-else href="/sales/returns">
                        <Button variant="outline" size="sm" class="h-9 text-xs gap-1.5 text-amber-600 border-amber-600/30 hover:bg-amber-500/10">
                            <RotateCcw class="size-3.5" />
                            Process Return / Refund
                        </Button>
                    </Link>

                    <Link href="/pos">
                        <Button size="sm" class="h-9 text-xs gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                            <ShoppingCart class="size-3.5" />
                            Open POS Register
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Register Scope Filter Bar -->
            <div class="flex items-center justify-between p-3 rounded-xl border border-border/80 bg-card shadow-sm text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-muted-foreground">Displaying Transactions:</span>
                    <div class="flex items-center gap-1 rounded-lg border border-border/60 bg-background p-1">
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 rounded-md font-semibold text-xs transition-all flex items-center gap-1.5',
                                salesScope === 'mine'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/40',
                            ]"
                            @click="salesScope = 'mine'"
                        >
                            <UserCheck class="size-3.5" />
                            <span>My Register Sales (Active Shift)</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 rounded-md font-semibold text-xs transition-all flex items-center gap-1.5',
                                salesScope === 'all'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/40',
                            ]"
                            @click="salesScope = 'all'"
                        >
                            <Lock v-if="isCashier" class="size-3 text-amber-500" />
                            <span>All Branch Registers {{ isCashier ? '(Read-Only Mode)' : '' }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="isCashier && salesScope === 'all'" class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-300 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                    <ShieldAlert class="size-3.5 shrink-0" />
                    <span>Viewing other registers in read-only audit mode</span>
                </div>
            </div>

            <!-- DataTable -->
            <DataTable
                :columns="columns"
                :data="displayedSales"
                search-placeholder="Search receipt #, customer, cashier, payment ref..."
                :search-keys="['receiptNumber', 'cashierName', 'mpesaCode']"
            >
                <template #cell-receiptNumber="{ row }">
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono font-bold text-primary">{{ row.receiptNumber }}</span>
                    </div>
                    <div v-if="row.mpesaCode" class="text-[10px] text-emerald-600 font-mono">
                        Ref: {{ row.mpesaCode }}
                    </div>
                </template>

                <template #cell-date="{ row }">
                    <span class="text-xs">{{ formatDateTime(row.date) }}</span>
                </template>

                <template #cell-customer="{ row }">
                    <span class="font-medium">{{ row.customer?.name || 'Walk-in Customer' }}</span>
                </template>

                <template #cell-cashierName="{ row }">
                    <div class="flex flex-col gap-0.5">
                        <span class="font-medium text-xs">{{ row.cashierName }}</span>
                        <div>
                            <Badge
                                v-if="isMySale(row)"
                                variant="outline"
                                class="text-[9px] px-1 py-0 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 font-semibold"
                            >
                                My Register
                            </Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="text-[9px] px-1 py-0 bg-muted text-muted-foreground border-border/80 font-normal flex items-center gap-0.5 w-fit"
                            >
                                <Lock class="size-2.5 text-amber-500" />
                                Read-Only
                            </Badge>
                        </div>
                    </div>
                </template>

                <template #cell-subtotal="{ row }">
                    <span class="font-mono text-muted-foreground">{{ formatCurrency(row.subtotal) }}</span>
                </template>

                <template #cell-taxAmount="{ row }">
                    <span class="font-mono text-muted-foreground text-[11px]">{{ formatCurrency(row.taxAmount) }}</span>
                </template>

                <template #cell-total="{ row }">
                    <span class="font-mono font-bold text-foreground">{{ formatCurrency(row.total) }}</span>
                </template>

                <template #cell-payments="{ row }">
                    <Badge variant="outline" class="font-semibold text-[11px]">
                        {{ row.payments.map((p: any) => p.method).join(', ') }}
                    </Badge>
                </template>

                <template #cell-status="{ row }">
                    <Badge :variant="row.status === 'Completed' ? 'success' : 'destructive'">
                        {{ row.status }}
                    </Badge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 text-xs gap-1"
                            @click="viewReceipt(row)"
                        >
                            <Eye class="size-3.5" />
                            <span>{{ isMySale(row) ? 'Receipt' : 'Inspect' }}</span>
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Thermal Receipt Modal -->
        <ReceiptModal
            :open="receiptOpen"
            :sale="selectedSale"
            :kra-pin="currentTenant.kraPin"
            @update:open="(v) => (receiptOpen = v)"
        />
    </AppLayout>
</template>
