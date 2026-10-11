<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import {
    Building2,
    Calendar,
    CheckCircle2,
    Download,
    ExternalLink,
    Eye,
    FileText,
    Hash,
    Printer,
    QrCode,
    Receipt,
    User,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    purchase: any | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { currentTenant } = useTenant();
const viewMode = ref<'voucher' | 'softcopy'>('voucher');

// Reset to voucher view whenever a new purchase is opened
watch(
    () => props.purchase,
    () => {
        viewMode.value = 'voucher';
    }
);

const invoice = computed(() => {
    return props.purchase?.invoice || null;
});

const isPdf = computed(() => {
    const fileName = invoice.value?.fileName?.toLowerCase() || '';
    return fileName.endsWith('.pdf');
});

const invoiceItems = computed(() => {
    if (!invoice.value) return [];
    if (Array.isArray(invoice.value.extractedItems) && invoice.value.extractedItems.length > 0) {
        return invoice.value.extractedItems;
    }
    if (Array.isArray(invoice.value.matrix) && invoice.value.matrix.length > 0) {
        return invoice.value.matrix.map((m: any) => ({
            name: m.item_name,
            sku: m.sku,
            quantity: m.invoiced_quantity,
            unit_cost: m.invoiced_unit_cost,
            total: m.invoiced_total,
        }));
    }

    // Try parsing purchase notes for structured line items if softcopy was stored directly
    if (props.purchase?.notes) {
        try {
            const parsed = JSON.parse(props.purchase.notes);
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed.map((it: any) => ({
                    name: it.name || it.product_name,
                    sku: it.sku || '',
                    quantity: it.quantity || it.total_units || 1,
                    unit_cost: it.unit_cost || 0,
                    total: it.total || it.line_total || 0,
                }));
            }
        } catch {
            // Not JSON notes, fall through
        }
    }

    return [
        {
            name: props.purchase?.items || 'General Goods & Supplies',
            sku: '',
            quantity: 1,
            unit_cost: invoice.value.totalAmount,
            total: invoice.value.totalAmount,
        },
    ];
});

const triggerPrint = () => {
    window.print();
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-4xl max-w-[95vw] max-h-[92vh] bg-card border-border p-0 overflow-hidden flex flex-col shadow-2xl">
            <!-- Header Bar -->
            <div class="px-6 py-4 border-b border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0 shadow-xs">
                        <Receipt class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-bold text-foreground flex items-center gap-2 flex-wrap">
                            <span>Supplier Physical Invoice Document</span>
                            <Badge variant="outline" class="text-xs font-mono font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20">
                                {{ invoice?.invoiceNumber || 'INVOICE' }}
                            </Badge>
                        </DialogTitle>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            Physical invoice billed by <strong class="text-foreground">{{ purchase?.supplier }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Actions: Switcher & External Link -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Segmented View Switcher: Voucher vs Scanned Softcopy -->
                    <div v-if="invoice?.hasDocument" class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5 text-xs font-semibold">
                        <button
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-md transition-all flex items-center gap-1.5 cursor-pointer',
                                viewMode === 'voucher'
                                    ? 'bg-background text-foreground shadow-xs font-bold'
                                    : 'text-muted-foreground hover:text-foreground'
                            ]"
                            @click="viewMode = 'voucher'"
                        >
                            <Receipt class="size-3 text-indigo-500" />
                            <span>Physical Voucher</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-md transition-all flex items-center gap-1.5 cursor-pointer',
                                viewMode === 'softcopy'
                                    ? 'bg-background text-indigo-600 dark:text-indigo-400 shadow-xs font-bold'
                                    : 'text-muted-foreground hover:text-foreground'
                            ]"
                            @click="viewMode = 'softcopy'"
                        >
                            <FileText class="size-3 text-indigo-500" />
                            <span>Original Softcopy</span>
                        </button>
                    </div>

                    <a
                        v-if="purchase?.id && invoice?.hasDocument"
                        :href="`/purchases/${purchase.id}/invoice/document`"
                        target="_blank"
                        class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-border bg-background hover:bg-muted/40 text-foreground transition-colors shadow-xs"
                        title="Open file in a new browser tab"
                    >
                        <ExternalLink class="size-3.5 text-primary" />
                        <span>Open New Tab</span>
                    </a>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[75vh] space-y-6">
                <!-- VIEW MODE 1: ORIGINAL SCANNED SOFTCOPY EMBEDDED -->
                <div v-if="viewMode === 'softcopy' && invoice?.hasDocument" class="flex flex-col items-center justify-center min-h-[450px]">
                    <iframe
                        v-if="isPdf"
                        :src="`/purchases/${purchase.id}/invoice/document`"
                        class="w-full h-[65vh] rounded-xl border border-border bg-white shadow-xs"
                    ></iframe>
                    <div v-else class="w-full flex flex-col items-center justify-center p-2 bg-muted/10 rounded-xl border border-border">
                        <img
                            :src="`/purchases/${purchase.id}/invoice/document`"
                            alt="Original Physical Invoice"
                            class="max-h-[65vh] w-auto object-contain rounded-lg shadow-xs"
                        />
                        <p class="text-[11px] text-muted-foreground mt-2 font-mono">
                            {{ invoice.fileName }} ({{ Math.round((invoice.fileSizeBytes || 0) / 1024) }} KB)
                        </p>
                    </div>
                </div>

                <!-- VIEW MODE 2: PRINTABLE PHYSICAL INVOICE VOUCHER -->
                <div v-else id="printable-exact-invoice" class="bg-background rounded-2xl border border-border shadow-xs p-6 sm:p-8 space-y-6 text-foreground">
                    <!-- Top Invoice Header: Supplier & Fiscal Information -->
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-border">
                        <div>
                            <div class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider font-bold text-indigo-600 dark:text-indigo-400 mb-1">
                                <Building2 class="size-3.5" />
                                <span>Supplier / Vendor</span>
                            </div>
                            <h2 class="text-xl font-black text-foreground">{{ purchase?.supplier || 'Supplier' }}</h2>
                            <div class="text-xs text-muted-foreground mt-1 space-y-0.5">
                                <p v-if="invoice?.taxPin"><strong class="text-foreground">Tax PIN:</strong> {{ invoice.taxPin }}</p>
                                <p v-if="invoice?.controlUnitCode"><strong class="text-foreground">Control Unit (CU):</strong> {{ invoice.controlUnitCode }}</p>
                                <p><strong class="text-foreground">Associated PO Ref:</strong> {{ purchase?.refNumber || purchase?.poNumber }}</p>
                            </div>
                        </div>

                        <!-- Right: Document Title & Metadata Box -->
                        <div class="sm:text-right space-y-1">
                            <span class="inline-block px-3 py-1 bg-indigo-600 text-white rounded-md text-xs font-black uppercase tracking-widest">
                                TAX INVOICE
                            </span>
                            <div class="pt-2 text-xs font-mono space-y-1">
                                <div class="flex sm:justify-end gap-2">
                                    <span class="text-muted-foreground">Invoice No:</span>
                                    <span class="font-bold text-foreground">{{ invoice?.invoiceNumber }}</span>
                                </div>
                                <div class="flex sm:justify-end gap-2">
                                    <span class="text-muted-foreground">Invoice Date:</span>
                                    <span class="font-bold text-foreground">{{ invoice?.invoiceDate || purchase?.date }}</span>
                                </div>
                                <div class="flex sm:justify-end gap-2">
                                    <span class="text-muted-foreground">Recorded On:</span>
                                    <span>{{ invoice?.verifiedAt || purchase?.orderTime }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Billed To Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs bg-muted/30 p-4 rounded-xl border border-border/80">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider block">Billed To (Purchaser)</span>
                            <p class="font-bold text-sm text-foreground mt-0.5">{{ currentTenant?.name || 'MDE Store' }}</p>
                            <p class="text-muted-foreground mt-0.5">Branch: {{ purchase?.branch || 'Main Store' }}</p>
                        </div>
                        <div class="sm:text-right">
                            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider block">Received / Verified By</span>
                            <p class="font-bold text-sm text-foreground mt-0.5">{{ invoice?.verifiedByName || purchase?.createdByName || 'Store Officer' }}</p>
                            <p class="text-muted-foreground mt-0.5">Document Status: <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">Archived Softcopy</strong></p>
                        </div>
                    </div>

                    <!-- Exact Billed Items Table -->
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-foreground mb-2 flex items-center justify-between">
                            <span>Billed Goods & Services</span>
                            <span class="text-[10px] text-muted-foreground font-normal">{{ invoiceItems.length }} Item(s)</span>
                        </div>

                        <div class="rounded-xl border border-border overflow-hidden shadow-xs">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/60 text-muted-foreground font-bold uppercase text-[10px] tracking-wider border-b border-border">
                                    <tr>
                                        <th class="p-3 w-10 text-center">#</th>
                                        <th class="p-3">Item Description</th>
                                        <th class="p-3 text-center">Billed Quantity</th>
                                        <th class="p-3 text-right">Unit Rate (KES)</th>
                                        <th class="p-3 text-right">Total Amount (KES)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="(item, idx) in invoiceItems" :key="idx" class="hover:bg-muted/20">
                                        <td class="p-3 text-center font-mono text-muted-foreground">{{ idx + 1 }}</td>
                                        <td class="p-3">
                                            <span class="font-bold text-foreground block">{{ item.name }}</span>
                                            <span v-if="item.sku" class="text-[10px] font-mono text-muted-foreground">SKU: {{ item.sku }}</span>
                                        </td>
                                        <td class="p-3 text-center font-mono font-bold">{{ item.quantity }}</td>
                                        <td class="p-3 text-right font-mono">{{ formatCurrency(item.unit_cost) }}</td>
                                        <td class="p-3 text-right font-mono font-bold">{{ formatCurrency(item.total) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-muted/40 font-bold border-t border-border">
                                    <tr>
                                        <td colspan="4" class="p-3 text-right uppercase text-[11px] text-muted-foreground">
                                            Total Invoiced Amount (KES):
                                        </td>
                                        <td class="p-3 text-right font-mono text-sm font-black text-indigo-600 dark:text-indigo-400">
                                            {{ formatCurrency(invoice?.totalAmount || purchase?.amount || 0) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Notes if Any -->
                    <div v-if="invoice?.notes" class="text-xs text-muted-foreground border-t border-border pt-3">
                        <span class="font-medium text-foreground">Notes:</span> {{ invoice.notes }}
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="px-6 py-4 border-t border-border bg-muted/20 flex items-center justify-between sm:justify-between shrink-0">
                <Button variant="ghost" size="sm" class="text-xs" @click="$emit('update:open', false)">
                    Close
                </Button>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs gap-1.5 font-semibold cursor-pointer"
                        @click="triggerPrint"
                    >
                        <Printer class="size-3.5" />
                        <span>Print Invoice</span>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printable-exact-invoice,
    #printable-exact-invoice * {
        visibility: visible !important;
    }
    #printable-exact-invoice {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 10mm;
        border: none !important;
    }
}
</style>
