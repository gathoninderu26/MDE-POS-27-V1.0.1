<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Boxes,
    Check,
    CheckCircle2,
    Clock,
    Download,
    ExternalLink,
    Eye,
    FileSpreadsheet,
    FileText,
    HelpCircle,
    Loader2,
    Minus,
    Plus,
    QrCode,
    RefreshCw,
    Trash2,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    purchase: any | null;
    scanResult: any | null; // Data returned from /purchases/{id}/invoice/upload or existing invoice
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'reconciled'): void;
}>();

const { success, error, warning } = useToast();

const isSubmitting = ref(false);
const zoomLevel = ref(1);
const autoReceiveStock = ref(true);

// Editable Invoiced Form Fields
const invoiceForm = ref({
    file_path: '',
    file_name: '',
    mime_type: 'image/jpeg',
    file_size: 0,
    invoice_number: '',
    invoice_date: new Date().toISOString().slice(0, 10),
    tax_pin: '',
    control_unit_code: '',
    raw_qr_payload: '',
    notes: '',
});

// Editable Line Items
interface ReconciledLineItem {
    name: string;
    sku: string;
    product_id: number | null;
    quantity: number;
    unit_cost: number;
    total: number;
    po_quantity?: number;
    po_unit_cost?: number;
    po_total?: number;
    status?: string;
}

const items = ref<ReconciledLineItem[]>([]);

// Initialize form when scanResult arrives or modal opens
watch(
    () => props.scanResult,
    (res) => {
        if (!res) return;

        invoiceForm.value.file_path = res.file_path || '';
        invoiceForm.value.file_name = res.file_name || 'physical_invoice.jpg';
        invoiceForm.value.mime_type = res.mime_type || 'image/jpeg';
        invoiceForm.value.file_size = res.file_size || 0;
        invoiceForm.value.raw_qr_payload = res.qr?.raw || '';

        // Auto-fill header from detected QR
        invoiceForm.value.invoice_number =
            res.qr?.invoice_number ||
            `INV-${props.purchase?.refNumber?.replace(/^(PO|LPO)-/, '') || Math.floor(1000 + Math.random() * 9000)}`;
        invoiceForm.value.tax_pin = res.qr?.tax_pin || '';
        invoiceForm.value.control_unit_code = res.qr?.control_unit_code || '';
        invoiceForm.value.invoice_date = res.qr?.invoice_date || new Date().toISOString().slice(0, 10);

        // Populate items from comparison or PO
        if (res.comparison?.rows && res.comparison.rows.length > 0) {
            items.value = res.comparison.rows.map((r: any) => ({
                name: r.item_name,
                sku: r.sku || '',
                product_id: r.product_id || null,
                quantity: Number(r.invoiced_quantity || r.po_quantity || 1),
                unit_cost: Number(r.invoiced_unit_cost || r.po_unit_cost || 0),
                total: Number(r.invoiced_total || (r.po_quantity * r.po_unit_cost) || 0),
                po_quantity: Number(r.po_quantity || 0),
                po_unit_cost: Number(r.po_unit_cost || 0),
                po_total: Number(r.po_total || 0),
                status: r.status,
            }));
        } else {
            items.value = [
                {
                    name: props.purchase?.items || 'Supplies',
                    sku: '',
                    product_id: null,
                    quantity: 1,
                    unit_cost: Number(props.purchase?.amount || 0),
                    total: Number(props.purchase?.amount || 0),
                    po_quantity: 1,
                    po_unit_cost: Number(props.purchase?.amount || 0),
                    po_total: Number(props.purchase?.amount || 0),
                    status: 'EXACT_MATCH',
                },
            ];
        }
    },
    { immediate: true }
);

// Dynamic Calculations
const calculatedInvoicedTotal = computed(() => {
    return items.value.reduce((acc, i) => acc + (Number(i.total) || 0), 0);
});

const poExpectedTotal = computed(() => {
    return Number(props.purchase?.amount || 0);
});

const totalVarianceAmount = computed(() => {
    return Math.round((calculatedInvoicedTotal.value - poExpectedTotal.value) * 100) / 100;
});

const overallStatus = computed(() => {
    const varTotal = totalVarianceAmount.value;
    const hasRowVariance = items.value.some((i) => {
        const qtyDiff = (i.quantity || 0) - (i.po_quantity || 0);
        const costDiff = (i.unit_cost || 0) - (i.po_unit_cost || 0);
        return Math.abs(qtyDiff) > 0.001 || Math.abs(costDiff) > 0.01;
    });

    if (Math.abs(varTotal) <= 0.05 && !hasRowVariance) {
        return {
            label: '100% Exact Match',
            variant: 'success' as const,
            desc: 'Invoiced items, quantities, and prices completely match original PO.',
        };
    }

    if (varTotal > 0.05) {
        return {
            label: 'Price Variance (Surge)',
            variant: 'warning' as const,
            desc: `Invoice total exceeds PO by ${formatCurrency(varTotal)}.`,
        };
    }

    if (varTotal < -0.05) {
        return {
            label: 'Under-Billed / Short Delivery',
            variant: 'info' as const,
            desc: `Invoice total is ${formatCurrency(Math.abs(varTotal))} less than PO.`,
        };
    }

    return {
        label: 'Variance Detected',
        variant: 'warning' as const,
        desc: 'Review individual item unit prices and quantities.',
    };
});

const updateLineTotal = (item: ReconciledLineItem) => {
    item.quantity = Math.max(0, Number(item.quantity) || 0);
    item.unit_cost = Math.max(0, Number(item.unit_cost) || 0);
    item.total = Math.round(item.quantity * item.unit_cost * 100) / 100;

    // Recalculate row status
    const qtyDiff = (item.quantity || 0) - (item.po_quantity || 0);
    const costDiff = (item.unit_cost || 0) - (item.po_unit_cost || 0);

    if (Math.abs(qtyDiff) < 0.001 && Math.abs(costDiff) < 0.01) {
        item.status = 'EXACT_MATCH';
    } else if (Math.abs(costDiff) > 0.01 && Math.abs(qtyDiff) > 0.001) {
        item.status = 'BOTH_VARIANCE';
    } else if (Math.abs(costDiff) > 0.01) {
        item.status = 'PRICE_VARIANCE';
    } else {
        item.status = 'QTY_VARIANCE';
    }
};

const addItemLine = () => {
    items.value.push({
        name: 'Extra Invoiced Item',
        sku: '',
        product_id: null,
        quantity: 1,
        unit_cost: 0,
        total: 0,
        po_quantity: 0,
        po_unit_cost: 0,
        po_total: 0,
        status: 'UNEXPECTED_ITEM',
    });
};

const removeItemLine = (idx: number) => {
    if (items.value.length > 1) {
        items.value.splice(idx, 1);
    }
};

const previewSource = computed(() => {
    if (props.scanResult?.localPreview) {
        return props.scanResult.localPreview;
    }
    if (props.purchase?.id && props.purchase?.invoice?.hasDocument) {
        return `/purchases/${props.purchase.id}/invoice/document`;
    }
    return null;
});

const handleConfirmReconciliation = () => {
    if (!props.purchase) return;
    if (!invoiceForm.value.invoice_number.trim()) {
        warning('Missing Invoice #', 'Please provide the supplier invoice reference number.');
        return;
    }

    if (items.value.length === 0) {
        warning('Empty Items', 'At least one line item is required.');
        return;
    }

    isSubmitting.value = true;

    router.post(
        `/purchases/${props.purchase.id}/invoice/reconcile`,
        {
            file_path: invoiceForm.value.file_path,
            file_name: invoiceForm.value.file_name,
            mime_type: invoiceForm.value.mime_type,
            file_size: invoiceForm.value.file_size,
            invoice_number: invoiceForm.value.invoice_number.trim(),
            invoice_date: invoiceForm.value.invoice_date,
            tax_pin: invoiceForm.value.tax_pin || null,
            control_unit_code: invoiceForm.value.control_unit_code || null,
            raw_qr_payload: invoiceForm.value.raw_qr_payload || null,
            total_amount: calculatedInvoicedTotal.value,
            items: items.value.map((i) => ({
                name: i.name,
                sku: i.sku || '',
                product_id: i.product_id,
                quantity: i.quantity,
                unit_cost: i.unit_cost,
                total: i.total,
            })),
            auto_receive_stock: autoReceiveStock.value,
            notes: invoiceForm.value.notes || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                emit('reconciled');
                emit('update:open', false);
            },
            onError: (errs) => {
                isSubmitting.value = false;
                error('Reconciliation Failed', Object.values(errs)[0] as string);
            },
        }
    );
};

const handleSaveSoftcopyOnly = () => {
    if (!props.purchase) return;
    isSubmitting.value = true;

    router.post(
        `/purchases/${props.purchase.id}/invoice/reconcile`,
        {
            file_path: invoiceForm.value.file_path,
            file_name: invoiceForm.value.file_name,
            mime_type: invoiceForm.value.mime_type,
            file_size: invoiceForm.value.file_size,
            invoice_number: invoiceForm.value.invoice_number.trim() || `INV-${props.purchase.poNumber || props.purchase.refNumber}`,
            invoice_date: invoiceForm.value.invoice_date,
            tax_pin: invoiceForm.value.tax_pin || null,
            control_unit_code: invoiceForm.value.control_unit_code || null,
            raw_qr_payload: invoiceForm.value.raw_qr_payload || null,
            total_amount: calculatedInvoicedTotal.value || props.purchase.amount,
            items: [], // empty items signals softcopy only
            auto_receive_stock: autoReceiveStock.value,
            notes: invoiceForm.value.notes || 'Physical invoice softcopy archived without line item extraction.',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                success('Softcopy Archived', 'Invoice softcopy saved and attached to purchase order.');
                emit('reconciled');
                emit('update:open', false);
            },
            onError: (errs) => {
                isSubmitting.value = false;
                error('Save Failed', Object.values(errs)[0] as string);
            },
        }
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-6xl max-w-[96vw] max-h-[92vh] bg-card border-border p-0 overflow-hidden flex flex-col shadow-2xl">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-border bg-muted/20 shrink-0 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shadow-xs">
                        <FileSpreadsheet class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold text-foreground flex items-center gap-2">
                            <span>Supplier Invoice & PO Reconciliation</span>
                            <Badge :variant="overallStatus.variant" class="text-xs py-0.5 font-bold">
                                {{ overallStatus.label }}
                            </Badge>
                        </DialogTitle>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Comparing Physical Invoiced Billed Items vs Expected PO: <strong class="text-foreground font-mono">{{ purchase?.refNumber || purchase?.poNumber }}</strong>
                        </p>
                    </div>
                </div>

                <div class="hidden sm:flex items-center gap-4 text-xs font-mono">
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">PO Expected</div>
                        <div class="font-bold text-foreground">{{ formatCurrency(poExpectedTotal) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Invoiced Bill</div>
                        <div class="font-bold text-foreground">{{ formatCurrency(calculatedInvoicedTotal) }}</div>
                    </div>
                    <div class="text-right pl-2 border-l border-border">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Net Variance</div>
                        <div
                            :class="[
                                'font-bold',
                                totalVarianceAmount === 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : totalVarianceAmount > 0
                                      ? 'text-amber-600 dark:text-amber-400'
                                      : 'text-sky-600 dark:text-sky-400'
                            ]"
                        >
                            {{ totalVarianceAmount > 0 ? '+' : '' }}{{ formatCurrency(totalVarianceAmount) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Body: Split-Screen Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 flex-1 overflow-hidden divide-y lg:divide-y-0 lg:divide-x divide-border">
                <!-- LEFT COLUMN: High-Res Scanned Document Preview -->
                <div class="lg:col-span-5 bg-muted/10 p-4 flex flex-col overflow-hidden max-h-[72vh]">
                    <!-- Viewer Controls Toolbar -->
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-border text-xs">
                        <span class="font-semibold text-foreground flex items-center gap-1.5">
                            <Eye class="size-3.5 text-primary" />
                            <span>Physical Invoice Scan</span>
                        </span>

                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                                @click="zoomLevel = Math.max(0.6, zoomLevel - 0.2)"
                                title="Zoom Out"
                            >
                                <ZoomOut class="size-3.5" />
                            </Button>
                            <span class="text-[10px] font-mono text-muted-foreground px-1">{{ Math.round(zoomLevel * 100) }}%</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                                @click="zoomLevel = Math.min(2.5, zoomLevel + 0.2)"
                                title="Zoom In"
                            >
                                <ZoomIn class="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0 text-muted-foreground hover:text-foreground ml-1"
                                @click="zoomLevel = 1"
                                title="Reset Zoom"
                            >
                                <RefreshCw class="size-3" />
                            </Button>
                        </div>
                    </div>

                    <!-- Scrollable Zoomable Image Container -->
                    <div class="flex-1 overflow-auto rounded-xl border border-border bg-slate-950/80 p-2 flex items-center justify-center relative min-h-[220px]">
                        <div
                            v-if="previewSource"
                            class="transition-transform duration-150 origin-center"
                            :style="{ transform: `scale(${zoomLevel})` }"
                        >
                            <img
                                :src="previewSource"
                                alt="Physical Invoice Document"
                                class="max-w-full rounded-md shadow-lg object-contain select-none"
                            />
                        </div>
                        <div v-else class="text-center p-6 text-muted-foreground space-y-2">
                            <FileText class="size-10 mx-auto opacity-40" />
                            <p class="text-xs">No physical document attached.</p>
                        </div>
                    </div>

                    <!-- QR / Fiscal Metadata Tag -->
                    <div v-if="invoiceForm.raw_qr_payload" class="mt-3 p-2.5 rounded-lg border border-emerald-500/20 bg-emerald-500/5 text-[11px] font-mono">
                        <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold mb-0.5">
                            <QrCode class="size-3.5" />
                            <span>Fiscal QR Integrity Verified</span>
                        </div>
                        <p class="text-muted-foreground truncate" :title="invoiceForm.raw_qr_payload">
                            {{ invoiceForm.raw_qr_payload }}
                        </p>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Comparison & Reconciliation Matrix Form -->
                <div class="lg:col-span-7 p-4 sm:p-6 overflow-y-auto max-h-[72vh] space-y-5">
                    <!-- Invoice Header Fields -->
                    <div class="rounded-xl border border-border bg-card p-3 sm:p-4 space-y-3">
                        <div class="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <span>Supplier Invoice Header</span>
                            <span class="text-[10px] text-muted-foreground font-normal lowercase">(auto-populated from scan)</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <Label class="text-[11px] text-muted-foreground">Supplier Invoice # *</Label>
                                <Input
                                    v-model="invoiceForm.invoice_number"
                                    type="text"
                                    placeholder="e.g. INV-2026-904"
                                    class="h-8 text-xs font-mono font-bold mt-1"
                                />
                            </div>

                            <div>
                                <Label class="text-[11px] text-muted-foreground">Invoice Issue Date</Label>
                                <Input
                                    v-model="invoiceForm.invoice_date"
                                    type="date"
                                    class="h-8 text-xs font-mono mt-1"
                                />
                            </div>

                            <div>
                                <Label class="text-[11px] text-muted-foreground">Supplier Tax PIN / TIN</Label>
                                <Input
                                    v-model="invoiceForm.tax_pin"
                                    type="text"
                                    placeholder="e.g. P051234567Z"
                                    class="h-8 text-xs font-mono mt-1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Line Items Side-by-Side Comparison Matrix -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                                <span>Itemized Matching & Variance Matrix</span>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-6 text-[11px] px-2 gap-1"
                                @click="addItemLine"
                            >
                                <Plus class="size-3" />
                                <span>Add Extra Item</span>
                            </Button>
                        </div>

                        <!-- Table -->
                        <div class="rounded-xl border border-border overflow-x-auto shadow-xs">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/40 text-muted-foreground border-b border-border text-[10px] uppercase font-bold tracking-wider">
                                    <tr>
                                        <th class="p-2.5">Item Description</th>
                                        <th class="p-2.5 text-center">PO Expected Qty</th>
                                        <th class="p-2.5 text-center">Invoiced Qty</th>
                                        <th class="p-2.5 text-right">PO Unit Cost</th>
                                        <th class="p-2.5 text-right">Invoiced Unit Cost</th>
                                        <th class="p-2.5 text-right">Line Total</th>
                                        <th class="p-2.5 text-center">Status</th>
                                        <th class="p-2.5 w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="(item, idx) in items"
                                        :key="idx"
                                        :class="[
                                            'hover:bg-muted/20 transition-colors',
                                            item.status === 'EXACT_MATCH'
                                                ? 'bg-emerald-500/[0.02]'
                                                : item.status === 'PRICE_VARIANCE'
                                                  ? 'bg-amber-500/[0.04]'
                                                  : item.status === 'QTY_VARIANCE'
                                                    ? 'bg-sky-500/[0.04]'
                                                    : 'bg-rose-500/[0.04]'
                                        ]"
                                    >
                                        <!-- Item Description -->
                                        <td class="p-2.5 min-w-[160px]">
                                            <Input
                                                v-model="item.name"
                                                type="text"
                                                class="h-7 text-xs font-medium w-full"
                                                placeholder="Item description"
                                            />
                                            <span v-if="item.sku" class="text-[10px] font-mono text-muted-foreground block mt-0.5">
                                                SKU: {{ item.sku }}
                                            </span>
                                        </td>

                                        <!-- PO Expected Qty -->
                                        <td class="p-2.5 text-center font-mono font-medium text-muted-foreground">
                                            {{ item.po_quantity ?? 0 }}
                                        </td>

                                        <!-- Invoiced Qty -->
                                        <td class="p-2.5 text-center min-w-[70px]">
                                            <Input
                                                v-model.number="item.quantity"
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="h-7 text-xs font-mono text-center w-16 mx-auto"
                                                @input="updateLineTotal(item)"
                                            />
                                            <span
                                                v-if="(item.quantity - (item.po_quantity || 0)) !== 0"
                                                :class="[
                                                    'text-[10px] font-mono font-bold block mt-0.5',
                                                    (item.quantity - (item.po_quantity || 0)) > 0
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-rose-600 dark:text-rose-400'
                                                ]"
                                            >
                                                {{ (item.quantity - (item.po_quantity || 0)) > 0 ? '+' : '' }}{{ item.quantity - (item.po_quantity || 0) }}
                                            </span>
                                        </td>

                                        <!-- PO Unit Cost -->
                                        <td class="p-2.5 text-right font-mono text-muted-foreground">
                                            {{ formatCurrency(item.po_unit_cost || 0) }}
                                        </td>

                                        <!-- Invoiced Unit Cost -->
                                        <td class="p-2.5 text-right min-w-[90px]">
                                            <Input
                                                v-model.number="item.unit_cost"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="h-7 text-xs font-mono text-right w-24 ml-auto"
                                                @input="updateLineTotal(item)"
                                            />
                                            <span
                                                v-if="Math.abs((item.unit_cost - (item.po_unit_cost || 0))) > 0.01"
                                                :class="[
                                                    'text-[10px] font-mono font-bold block mt-0.5 text-right',
                                                    (item.unit_cost - (item.po_unit_cost || 0)) > 0
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-emerald-600 dark:text-emerald-400'
                                                ]"
                                            >
                                                {{ (item.unit_cost - (item.po_unit_cost || 0)) > 0 ? '+' : '' }}{{ formatCurrency(item.unit_cost - (item.po_unit_cost || 0)) }}
                                            </span>
                                        </td>

                                        <!-- Line Total -->
                                        <td class="p-2.5 text-right font-mono font-bold text-foreground">
                                            {{ formatCurrency(item.total) }}
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="p-2.5 text-center whitespace-nowrap">
                                            <span
                                                v-if="item.status === 'EXACT_MATCH'"
                                                class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded-md"
                                            >
                                                <Check class="size-3" /> Exact
                                            </span>
                                            <span
                                                v-else-if="item.status === 'PRICE_VARIANCE'"
                                                class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded-md"
                                            >
                                                <AlertTriangle class="size-3" /> Price Δ
                                            </span>
                                            <span
                                                v-else-if="item.status === 'QTY_VARIANCE'"
                                                class="inline-flex items-center gap-1 text-[10px] font-semibold text-sky-600 dark:text-sky-400 bg-sky-500/10 px-1.5 py-0.5 rounded-md"
                                            >
                                                <AlertCircle class="size-3" /> Qty Δ
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 text-[10px] font-semibold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded-md"
                                            >
                                                Unordered
                                            </span>
                                        </td>

                                        <!-- Delete Row -->
                                        <td class="p-2 text-center">
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-destructive p-1 rounded-sm cursor-pointer"
                                                @click="removeItemLine(idx)"
                                                title="Remove Line"
                                            >
                                                <Trash2 class="size-3" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Auto-Sync Inventory & Notes -->
                    <div class="rounded-xl border border-border bg-muted/20 p-3 space-y-3">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input
                                v-model="autoReceiveStock"
                                type="checkbox"
                                class="rounded border-border text-primary focus:ring-primary mt-0.5 size-4"
                            />
                            <div>
                                <span class="text-xs font-bold text-foreground block">Synchronize Stock into Inventory Automatically</span>
                                <span class="text-[11px] text-muted-foreground block">
                                    Increments product catalog stock quantities with the verified physical invoiced units and marks PO as Received.
                                </span>
                            </div>
                        </label>

                        <div>
                            <Label class="text-[11px] text-muted-foreground">Reconciliation Notes / Comments (Optional)</Label>
                            <Input
                                v-model="invoiceForm.notes"
                                type="text"
                                placeholder="e.g. Price difference approved by store manager due to supplier cost adjustment."
                                class="h-8 text-xs mt-1"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <DialogFooter class="px-6 py-4 border-t border-border bg-muted/20 shrink-0 flex items-center justify-between sm:justify-between">
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-xs"
                    :disabled="isSubmitting"
                    @click="$emit('update:open', false)"
                >
                    Cancel
                </Button>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="h-9 px-3.5 text-xs font-semibold gap-1.5 border border-border shadow-xs"
                        :disabled="isSubmitting"
                        @click="handleSaveSoftcopyOnly"
                        title="Save and archive this invoice document without requiring itemized matching"
                    >
                        <FileText class="size-3.5 text-primary" />
                        <span>Archive Softcopy Only</span>
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        class="h-9 px-5 text-xs font-bold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                        :disabled="isSubmitting"
                        @click="handleConfirmReconciliation"
                    >
                        <Loader2 v-if="isSubmitting" class="size-3.5 animate-spin" />
                        <CheckCircle2 v-else class="size-3.5" />
                        <span>{{ autoReceiveStock ? 'Approve & Restock Inventory' : 'Save Full Reconciliation' }}</span>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
