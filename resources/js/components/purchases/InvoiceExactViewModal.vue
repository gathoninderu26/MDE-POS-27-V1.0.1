<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { Building2, ExternalLink, FileText, Printer, Receipt } from 'lucide-vue-next';
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
    },
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
        <DialogContent class="flex max-h-[92vh] max-w-[95vw] flex-col overflow-hidden border-border bg-card p-0 shadow-2xl sm:max-w-4xl">
            <!-- Header Bar -->
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/20 px-6 py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="shadow-xs flex size-10 shrink-0 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                    >
                        <Receipt class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="flex flex-wrap items-center gap-2 text-base font-bold text-foreground">
                            <span>Supplier Physical Invoice Document</span>
                            <Badge
                                variant="outline"
                                class="border-indigo-500/20 bg-indigo-500/10 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ invoice?.invoiceNumber || 'INVOICE' }}
                            </Badge>
                        </DialogTitle>
                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                            Physical invoice billed by <strong class="text-foreground">{{ purchase?.supplier }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Actions: Switcher & External Link -->
                <div class="flex shrink-0 items-center gap-2">
                    <!-- Segmented View Switcher: Voucher vs Scanned Softcopy -->
                    <div v-if="invoice?.hasDocument" class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5 text-xs font-semibold">
                        <button
                            type="button"
                            :class="[
                                'flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 transition-all',
                                viewMode === 'voucher'
                                    ? 'shadow-xs bg-background font-bold text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="viewMode = 'voucher'"
                        >
                            <Receipt class="size-3 text-indigo-500" />
                            <span>Physical Voucher</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 transition-all',
                                viewMode === 'softcopy'
                                    ? 'shadow-xs bg-background font-bold text-indigo-600 dark:text-indigo-400'
                                    : 'text-muted-foreground hover:text-foreground',
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
                        class="shadow-xs inline-flex items-center gap-1 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted/40"
                        title="Open file in a new browser tab"
                    >
                        <ExternalLink class="size-3.5 text-primary" />
                        <span>Open New Tab</span>
                    </a>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="max-h-[75vh] space-y-6 overflow-y-auto p-6">
                <!-- VIEW MODE 1: ORIGINAL SCANNED SOFTCOPY EMBEDDED -->
                <div v-if="viewMode === 'softcopy' && invoice?.hasDocument" class="flex min-h-[450px] flex-col items-center justify-center">
                    <iframe
                        v-if="isPdf"
                        :src="`/purchases/${purchase.id}/invoice/document`"
                        class="shadow-xs h-[65vh] w-full rounded-xl border border-border bg-white"
                    ></iframe>
                    <div v-else class="flex w-full flex-col items-center justify-center rounded-xl border border-border bg-muted/10 p-2">
                        <img
                            :src="`/purchases/${purchase.id}/invoice/document`"
                            alt="Original Physical Invoice"
                            class="shadow-xs max-h-[65vh] w-auto rounded-lg object-contain"
                        />
                        <p class="mt-2 font-mono text-[11px] text-muted-foreground">
                            {{ invoice.fileName }} ({{ Math.round((invoice.fileSizeBytes || 0) / 1024) }} KB)
                        </p>
                    </div>
                </div>

                <!-- VIEW MODE 2: PRINTABLE PHYSICAL INVOICE VOUCHER -->
                <div
                    v-else
                    id="printable-exact-invoice"
                    class="shadow-xs space-y-6 rounded-2xl border border-border bg-background p-6 text-foreground sm:p-8"
                >
                    <!-- Top Invoice Header: Supplier & Fiscal Information -->
                    <div class="flex flex-col justify-between gap-6 border-b border-border pb-6 sm:flex-row sm:items-start">
                        <div>
                            <div
                                class="mb-1 inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400"
                            >
                                <Building2 class="size-3.5" />
                                <span>Supplier / Vendor</span>
                            </div>
                            <h2 class="text-xl font-black text-foreground">{{ purchase?.supplier || 'Supplier' }}</h2>
                            <div class="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                <p v-if="invoice?.taxPin"><strong class="text-foreground">Tax PIN:</strong> {{ invoice.taxPin }}</p>
                                <p v-if="invoice?.controlUnitCode">
                                    <strong class="text-foreground">Control Unit (CU):</strong> {{ invoice.controlUnitCode }}
                                </p>
                                <p><strong class="text-foreground">Associated PO Ref:</strong> {{ purchase?.refNumber || purchase?.poNumber }}</p>
                            </div>
                        </div>

                        <!-- Right: Document Title & Metadata Box -->
                        <div class="space-y-1 sm:text-right">
                            <span class="inline-block rounded-md bg-indigo-600 px-3 py-1 text-xs font-black uppercase tracking-widest text-white">
                                TAX INVOICE
                            </span>
                            <div class="space-y-1 pt-2 font-mono text-xs">
                                <div class="flex gap-2 sm:justify-end">
                                    <span class="text-muted-foreground">Invoice No:</span>
                                    <span class="font-bold text-foreground">{{ invoice?.invoiceNumber }}</span>
                                </div>
                                <div class="flex gap-2 sm:justify-end">
                                    <span class="text-muted-foreground">Invoice Date:</span>
                                    <span class="font-bold text-foreground">{{ invoice?.invoiceDate || purchase?.date }}</span>
                                </div>
                                <div class="flex gap-2 sm:justify-end">
                                    <span class="text-muted-foreground">Recorded On:</span>
                                    <span>{{ invoice?.verifiedAt || purchase?.orderTime }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Billed To Section -->
                    <div class="grid grid-cols-1 gap-4 rounded-xl border border-border/80 bg-muted/30 p-4 text-xs sm:grid-cols-2">
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Billed To (Purchaser)</span>
                            <p class="mt-0.5 text-sm font-bold text-foreground">{{ currentTenant?.name || 'MDE Store' }}</p>
                            <p class="mt-0.5 text-muted-foreground">Branch: {{ purchase?.branch || 'Main Store' }}</p>
                        </div>
                        <div class="sm:text-right">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Received / Verified By</span>
                            <p class="mt-0.5 text-sm font-bold text-foreground">
                                {{ invoice?.verifiedByName || purchase?.createdByName || 'Store Officer' }}
                            </p>
                            <p class="mt-0.5 text-muted-foreground">
                                Document Status: <strong class="font-semibold text-emerald-600 dark:text-emerald-400">Archived Softcopy</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Exact Billed Items Table -->
                    <div>
                        <div class="mb-2 flex items-center justify-between text-xs font-bold uppercase tracking-wider text-foreground">
                            <span>Billed Goods & Services</span>
                            <span class="text-[10px] font-normal text-muted-foreground">{{ invoiceItems.length }} Item(s)</span>
                        </div>

                        <div class="shadow-xs overflow-hidden rounded-xl border border-border">
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="border-b border-border bg-muted/60 text-[10px] font-bold uppercase tracking-wider text-muted-foreground"
                                >
                                    <tr>
                                        <th class="w-10 p-3 text-center">#</th>
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
                                            <span class="block font-bold text-foreground">{{ item.name }}</span>
                                            <span v-if="item.sku" class="font-mono text-[10px] text-muted-foreground">SKU: {{ item.sku }}</span>
                                        </td>
                                        <td class="p-3 text-center font-mono font-bold">{{ item.quantity }}</td>
                                        <td class="p-3 text-right font-mono">{{ formatCurrency(item.unit_cost) }}</td>
                                        <td class="p-3 text-right font-mono font-bold">{{ formatCurrency(item.total) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-t border-border bg-muted/40 font-bold">
                                    <tr>
                                        <td colspan="4" class="p-3 text-right text-[11px] uppercase text-muted-foreground">
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
                    <div v-if="invoice?.notes" class="border-t border-border pt-3 text-xs text-muted-foreground">
                        <span class="font-medium text-foreground">Notes:</span> {{ invoice.notes }}
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="flex shrink-0 items-center justify-between border-t border-border bg-muted/20 px-6 py-4 sm:justify-between">
                <Button variant="ghost" size="sm" class="text-xs" @click="$emit('update:open', false)"> Close </Button>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-8 cursor-pointer gap-1.5 text-xs font-semibold" @click="triggerPrint">
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
