<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ReceiptModal from '@/components/pos/ReceiptModal.vue';
import type { CompletedSale } from '@/composables/useCart';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { useFeatures } from '@/composables/useFeatures';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    Copy,
    Download,
    Eye,
    FileSpreadsheet,
    Lock,
    Printer,
    Receipt,
    RotateCcw,
    ShieldAlert,
    ShoppingCart,
    Smartphone,
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
const { isMultiBranch } = useFeatures();
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
            mpesaCode: s.mpesa_code,
            mpesaPayerName: s.mpesa_payer_name,
        }));
    }
    return [];
});

const salesScope = ref<'mine' | 'all' | 'mpesa'>(isCashier.value ? 'mine' : 'all');
const selectedBranch = ref<string>('all');

const branchOptions = computed(() => {
    const branches = new Set<string>();
    salesList.value.forEach(s => {
        if (s.branchName) branches.add(s.branchName);
    });
    return Array.from(branches);
});

const isMySale = (sale: CompletedSale) => {
    if (!authUser.value?.name) return true;
    const authName = authUser.value.name.toLowerCase();
    const cashierName = (sale.cashierName || '').toLowerCase();
    return cashierName.includes('cashier') || authName.includes(cashierName) || cashierName.includes(authName) || cashierName.includes('lead cashier');
};

const displayedSales = computed(() => {
    let list = salesList.value;
    if (salesScope.value === 'mine') {
        list = list.filter(s => isMySale(s));
    }
    if (selectedBranch.value !== 'all') {
        list = list.filter(s => s.branchName === selectedBranch.value);
    }
    return list;
});

const columns = computed(() => [
    { key: 'receiptNumber', label: 'Receipt #' },
    { key: 'date', label: 'Date & Time' },
    ...(isMultiBranch.value ? [{ key: 'branchName', label: 'Branch' }] : []),
    { key: 'customer', label: 'Customer' },
    { key: 'cashierName', label: 'Cashier / Register' },
    { key: 'subtotal', label: 'Subtotal', align: 'right' as const },
    { key: 'taxAmount', label: 'VAT 16%', align: 'right' as const },
    { key: 'total', label: 'Total (KES)', align: 'right' as const },
    { key: 'payments', label: 'Payment Method', align: 'center' as const },
    { key: 'status', label: 'Status', align: 'center' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
]);

const mpesaColumns = [
    { key: 'customerName', label: 'Customer / Payer' },
    { key: 'code', label: 'M-Pesa Code' },
    { key: 'branchName', label: 'Branch / Store' },
    { key: 'date', label: 'Date & Time' },
    { key: 'amount', label: 'Amount (KES)', align: 'right' as const },
    { key: 'initiatedBy', label: 'Initiated By (Cashier)' },
    { key: 'actions', label: 'M-Pesa Message', align: 'center' as const },
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

const allMpesaCount = computed(() => {
    return salesList.value.filter(s => {
        const raw = s as any;
        const hasMpesaMethod = s.payments?.some((p: any) =>
            p.method && (p.method.toLowerCase().includes('mpesa') || p.method.toLowerCase().includes('m-pesa'))
        );
        return hasMpesaMethod || !!raw.mpesaCode || !!raw.mpesaPayerName;
    }).length;
});

const mpesaSales = computed(() => {
    let list = salesList.value;
    if (selectedBranch.value !== 'all') {
        list = list.filter(s => s.branchName === selectedBranch.value);
    }
    return list
        .filter(s => {
            const raw = s as any;
            const hasMpesaMethod = s.payments?.some((p: any) =>
                p.method && (p.method.toLowerCase().includes('mpesa') || p.method.toLowerCase().includes('m-pesa'))
            );
            return hasMpesaMethod || !!raw.mpesaCode || !!raw.mpesaPayerName;
        })
        .map(s => {
            const raw = s as any;
            const mpesaPayment = s.payments?.find((p: any) =>
                p.method && (p.method.toLowerCase().includes('mpesa') || p.method.toLowerCase().includes('m-pesa'))
            );
            const customerName = raw.mpesaPayerName && raw.mpesaPayerName !== '—'
                ? raw.mpesaPayerName
                : (s.customer?.name && s.customer.name !== 'Walk-in Customer' ? s.customer.name : (raw.mpesaPayerName || 'Walk-in Customer'));
            const code = raw.mpesaCode || mpesaPayment?.reference || '—';
            const amount = mpesaPayment?.amount ?? s.total;
            const phone = s.customer?.phone || '';

            return {
                receiptNumber: s.receiptNumber,
                customerName,
                customerPhone: phone,
                code,
                date: s.date,
                amount,
                branchName: s.branchName || 'Main Store',
                initiatedBy: s.cashierName || 'Staff Cashier',
                rawSale: s,
            };
        });
});

const selectedMpesaMessage = ref<{
    code: string;
    customerName: string;
    phone: string;
    amount: number;
    date: string;
    branchName: string;
    initiatedBy: string;
    receiptNumber: string;
    exactMessage: string;
} | null>(null);

const mpesaMessageModalOpen = ref(false);
const messageCopied = ref(false);

const viewMpesaMessage = (row: any) => {
    const formattedAmount = Number(row.amount).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const phone = row.customerPhone && row.customerPhone !== '' ? row.customerPhone : '2547XXXXXXXX';
    
    // Official Safaricom M-Pesa SMS confirmation message
    const exactMessage = `${row.code} Confirmed. Ksh${formattedAmount} received from ${row.customerName} ${phone} on ${row.date}. Account: ${row.branchName}. Transaction cost, Ksh0.00.`;

    selectedMpesaMessage.value = {
        code: row.code,
        customerName: row.customerName,
        phone,
        amount: row.amount,
        date: row.date,
        branchName: row.branchName,
        initiatedBy: row.initiatedBy,
        receiptNumber: row.receiptNumber,
        exactMessage,
    };
    mpesaMessageModalOpen.value = true;
};

const copyMpesaMessage = async () => {
    if (!selectedMpesaMessage.value?.exactMessage) return;
    try {
        await navigator.clipboard.writeText(selectedMpesaMessage.value.exactMessage);
        messageCopied.value = true;
        success('Copied!', 'Exact M-Pesa SMS notification copied to clipboard.');
        setTimeout(() => (messageCopied.value = false), 2500);
    } catch {
        // fallback
    }
};
</script>

<template>
    <Head title="Sales Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
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

            <!-- Register Scope Filter Bar (Unified Switcher with Branch Differentiation) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 rounded-xl border border-border/80 bg-card shadow-sm text-xs">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-muted-foreground">Displaying Transactions:</span>
                    <div class="flex flex-wrap items-center gap-1 rounded-lg border border-border/60 bg-background p-1">
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
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 rounded-md font-semibold text-xs transition-all flex items-center gap-1.5',
                                salesScope === 'mpesa'
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/40',
                            ]"
                            @click="salesScope = 'mpesa'"
                        >
                            <Smartphone class="size-3.5" />
                            <span>M-Pesa Messages</span>
                            <span v-if="allMpesaCount > 0" class="ml-1 bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 rounded-full px-1.5 py-0 text-[10px] font-bold">
                                {{ allMpesaCount }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Branch Differentiator Filter & Mode Indicator -->
                <div class="flex items-center gap-2">
                    <div v-if="isMultiBranch && branchOptions.length > 1" class="flex items-center gap-1.5 bg-muted/40 border border-border/70 rounded-lg px-2.5 py-1">
                        <Building2 class="size-3.5 text-muted-foreground" />
                        <span class="text-muted-foreground font-medium">Branch:</span>
                        <select
                            v-model="selectedBranch"
                            class="bg-transparent text-foreground text-xs font-semibold focus:outline-none cursor-pointer"
                        >
                            <option value="all">All Branches</option>
                            <option v-for="branch in branchOptions" :key="branch" :value="branch">
                                {{ branch }}
                            </option>
                        </select>
                    </div>

                    <div v-if="isCashier && salesScope === 'all'" class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-300 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                        <ShieldAlert class="size-3.5 shrink-0" />
                        <span>Viewing other registers in read-only audit mode</span>
                    </div>
                </div>
            </div>

            <!-- All Sales DataTable (when not on M-Pesa view) -->
            <DataTable
                v-if="salesScope !== 'mpesa'"
                :columns="columns"
                :data="displayedSales"
                search-placeholder="Search receipt #, branch, customer, cashier, payment ref..."
                :search-keys="['receiptNumber', 'branchName', 'cashierName', 'mpesaCode']"
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

                <template #cell-branchName="{ row }">
                    <Badge variant="outline" class="text-[11px] px-2 py-0.5 bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/30 font-semibold flex items-center gap-1 w-fit">
                        <Building2 class="size-3" />
                        {{ row.branchName }}
                    </Badge>
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

            <!-- M-Pesa Messages DataTable (Differentiating Branches) -->
            <DataTable
                v-else
                :columns="mpesaColumns"
                :data="mpesaSales"
                search-placeholder="Search customer, M-Pesa code, branch, cashier..."
                :search-keys="['customerName', 'code', 'branchName', 'initiatedBy']"
            >
                <template #cell-customerName="{ row }">
                    <div class="flex items-center gap-2">
                        <div class="size-7 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            {{ (row.customerName || 'C').charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex flex-col">
                            <span class="font-medium text-foreground text-sm">{{ row.customerName }}</span>
                            <span v-if="row.customerPhone" class="text-[11px] font-mono text-muted-foreground">
                                {{ row.customerPhone }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded text-xs border border-emerald-500/20">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-branchName="{ row }">
                    <Badge variant="outline" class="text-[11px] px-2 py-0.5 bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/30 font-semibold flex items-center gap-1 w-fit">
                        <Building2 class="size-3" />
                        {{ row.branchName }}
                    </Badge>
                </template>

                <template #cell-date="{ row }">
                    <span class="text-xs text-muted-foreground">{{ formatDateTime(row.date) }}</span>
                </template>

                <template #cell-amount="{ row }">
                    <span class="font-mono font-bold text-foreground text-sm">{{ formatCurrency(row.amount) }}</span>
                </template>

                <template #cell-initiatedBy="{ row }">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-medium">{{ row.initiatedBy }}</span>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-center">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs gap-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 font-semibold"
                            @click="viewMpesaMessage(row)"
                        >
                            <Smartphone class="size-3.5" />
                            <span>View Message</span>
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Exact M-Pesa SMS Message Notification Dialog (NOT thermal receipt) -->
        <Dialog :open="mpesaMessageModalOpen" @update:open="(v) => (mpesaMessageModalOpen = v)">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Smartphone class="size-4 text-emerald-600" />
                        <span>M-Pesa Payment Notification</span>
                        <Badge variant="outline" class="font-mono text-[10px] ml-auto mr-6">SMS</Badge>
                    </DialogTitle>
                    <DialogDescription>
                        Branch: <strong>{{ selectedMpesaMessage?.branchName }}</strong> • Ref: <span class="font-mono">{{ selectedMpesaMessage?.receiptNumber }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-1 text-xs">
                    <!-- Exact SMS Message Box -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-muted-foreground">Exact Safaricom SMS Message</span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs gap-1.5"
                                @click="copyMpesaMessage"
                            >
                                <Check v-if="messageCopied" class="size-3.5 text-emerald-600" />
                                <Copy v-else class="size-3.5" />
                                <span>{{ messageCopied ? 'Copied' : 'Copy Message' }}</span>
                            </Button>
                        </div>
                        <div class="p-3.5 bg-muted/40 rounded-lg border border-border/80 font-mono text-xs text-foreground leading-relaxed select-all">
                            {{ selectedMpesaMessage?.exactMessage }}
                        </div>
                    </div>

                    <!-- Breakdown Cards Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div class="p-2.5 rounded-lg border border-border/70 bg-muted/20 space-y-1">
                            <span class="text-muted-foreground text-[11px]">M-Pesa Code</span>
                            <p class="font-mono font-bold text-foreground text-sm">
                                {{ selectedMpesaMessage?.code }}
                            </p>
                        </div>

                        <div class="p-2.5 rounded-lg border border-border/70 bg-muted/20 space-y-1">
                            <span class="text-muted-foreground text-[11px]">Amount</span>
                            <p class="font-mono font-bold text-foreground text-sm">
                                KES {{ Number(selectedMpesaMessage?.amount).toLocaleString('en-KE', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>

                        <div class="p-2.5 rounded-lg border border-border/70 bg-muted/20 space-y-1">
                            <span class="text-muted-foreground text-[11px]">Branch Account</span>
                            <p class="font-semibold text-foreground truncate">
                                {{ selectedMpesaMessage?.branchName }}
                            </p>
                        </div>

                        <div class="p-2.5 rounded-lg border border-border/70 bg-muted/20 space-y-1 col-span-2 sm:col-span-1">
                            <span class="text-muted-foreground text-[11px]">Payer Name</span>
                            <p class="font-semibold text-foreground truncate">
                                {{ selectedMpesaMessage?.customerName }}
                            </p>
                        </div>

                        <div class="p-2.5 rounded-lg border border-border/70 bg-muted/20 space-y-1">
                            <span class="text-muted-foreground text-[11px]">Initiated By</span>
                            <p class="font-medium text-foreground truncate">
                                {{ selectedMpesaMessage?.initiatedBy }}
                            </p>
                        </div>

                        <div class="p-2.5 rounded-lg border border-border/70 bg-muted/20 space-y-1">
                            <span class="text-muted-foreground text-[11px]">Date & Time</span>
                            <p class="font-medium text-foreground">
                                {{ selectedMpesaMessage?.date }}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="mpesaMessageModalOpen = false"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Thermal Receipt Modal -->
        <ReceiptModal
            :open="receiptOpen"
            :sale="selectedSale"
            :kra-pin="currentTenant.kraPin"
            @update:open="(v) => (receiptOpen = v)"
        />
    </AppLayout>
</template>
