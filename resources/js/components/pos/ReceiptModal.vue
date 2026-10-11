<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import type { CompletedSale } from '@/composables/useCart';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import { CheckCircle2, Download, Mail, Pill, Printer, Truck, Utensils, X } from 'lucide-vue-next';

import { computed, onUnmounted, watch } from 'vue';

const props = withDefaults(defineProps<{
    open: boolean;
    sale: CompletedSale | null;
    kraPin?: string;
    phone?: string;
    address?: string;
    autoPrint?: boolean;
}>(), {
    autoPrint: false,
});

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const handleReceiptKeydown = (e: KeyboardEvent) => {
    if (!props.open) return;
    const key = e.key.toLowerCase();
    if (key === 'p') {
        e.preventDefault();
        handlePrint();
    } else if (key === 'c' || e.key === 'Escape') {
        e.preventDefault();
        emit('update:open', false);
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            window.addEventListener('keydown', handleReceiptKeydown);
            if (props.autoPrint) {
                setTimeout(() => {
                    handlePrint();
                }, 300);
            }
        } else {
            window.removeEventListener('keydown', handleReceiptKeydown);
        }
    }
);

onUnmounted(() => {
    window.removeEventListener('keydown', handleReceiptKeydown);
});

const { terms } = useBusinessTerms();
const { success } = useToast();

// Standard Code 39 vector barcode generator for the receipt number
const CODE39_MAP: Record<string, string> = {
    '0': '000110100', '1': '100100001', '2': '001100001', '3': '101100000',
    '4': '000110001', '5': '100110000', '6': '001110000', '7': '000100101',
    '8': '100100100', '9': '001100100', 'A': '100001001', 'B': '001001001',
    'C': '101001000', 'D': '000011001', 'E': '100011000', 'F': '001011000',
    'G': '000001101', 'H': '100001100', 'I': '001001100', 'J': '000011100',
    'K': '100000011', 'L': '001000011', 'M': '101000010', 'N': '000010011',
    'O': '100010010', 'P': '001010010', 'Q': '000000111', 'R': '100000110',
    'S': '001000110', 'T': '000010110', 'U': '110000001', 'V': '011000001',
    'W': '111000000', 'X': '010010001', 'Y': '110010000', 'Z': '011010000',
    '-': '010000101', '.': '110000100', ' ': '011000100', '$': '010101000',
    '/': '010100010', '+': '010001010', '%': '000101010', '*': '010010100',
};

const barcodeSvg = computed(() => {
    const raw = props.sale?.receiptNumber || 'REC-0000';
    const clean = '*' + raw.toUpperCase().replace(/[^0-9A-Z\-\. \$\/\+\%]/g, '') + '*';
    const narrow = 1.6;
    const wide = 3.6;
    const gap = 1.6;

    const bars: { x: number; width: number }[] = [];
    let currentX = 8; // Left quiet zone

    for (let i = 0; i < clean.length; i++) {
        const char = clean[i];
        const pattern = CODE39_MAP[char] || CODE39_MAP['-'];

        for (let j = 0; j < 9; j++) {
            const isBar = j % 2 === 0;
            const isWide = pattern[j] === '1';
            const width = isWide ? wide : narrow;

            if (isBar) {
                bars.push({ x: currentX, width });
            }
            currentX += width;
        }

        currentX += gap;
    }

    return { bars, totalWidth: currentX + 8 };
});

const handlePrint = () => {
    window.print();
    success('Print job dispatched', 'Receipt sent to default thermal receipt printer');
};

const handleEmail = () => {
    success('Receipt Sent', `Email receipt sent to ${props.sale?.customer?.email || 'customer'}`);
};

const handleDownload = () => {
    window.print();
};
</script>

<template>
    <Dialog :open="open" spinnerText="Generating Receipt..." @update:open="(v) => emit('update:open', v)">
        <DialogContent class="w-[95vw] sm:max-w-md p-0 max-h-[92vh] flex flex-col overflow-hidden bg-background print:border-none print:shadow-none print:bg-transparent print:p-0 print:m-0 print:max-h-none print:overflow-visible print:w-auto print:max-w-none print:static print:transform-none">
            <DialogHeader class="px-4 sm:px-6 pt-4 sm:pt-5 pb-3 border-b border-border/70 flex flex-row items-center justify-between shrink-0 print:hidden">
                <div>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <CheckCircle2 class="size-5 text-emerald-500" />
                        Transaction Completed
                    </DialogTitle>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ terms.receiptTitle }} # {{ sale?.receiptNumber }}</p>
                </div>
            </DialogHeader>

            <!-- Printable Thermal Receipt (80mm styling) -->
            <div class="px-3 sm:px-6 py-4 overflow-y-auto print:max-h-none print:p-0 print:overflow-visible flex-1">
                <div id="thermal-receipt" class="mx-auto bg-card border border-border/70 p-4 sm:p-5 rounded-lg shadow-sm font-mono text-xs space-y-3 text-card-foreground">
                    <!-- Business Header -->
                    <div class="text-center space-y-0.5 border-b border-dashed border-border/90 pb-3">
                        <h4 class="text-sm font-black uppercase tracking-wider font-sans">{{ sale?.tenantName }}</h4>
                        <p class="text-[11px] text-muted-foreground">{{ sale?.branchName }}</p>
                        <p v-if="address" class="text-[11px] text-muted-foreground">{{ address }}</p>
                        <p v-if="phone" class="text-[11px] text-muted-foreground">Tel: {{ phone }}</p>
                        <p class="text-[11px] font-semibold text-foreground">Customer: {{ sale?.customer?.name || 'Walk-in Customer' }}</p>
                        <p class="text-[11px] font-bold tracking-widest uppercase font-mono mt-1 text-foreground">
                            {{ terms.receiptTitle }}
                        </p>
                    </div>

                    <!-- Meta Details & Vertical Specific Meta -->
                    <div class="text-[11px] space-y-0.5 border-b border-dashed border-border/90 pb-2">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Doc Number:</span>
                            <span class="font-bold">{{ sale?.receiptNumber }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Date & Time:</span>
                            <span>{{ sale ? formatDateTime(sale.date) : '' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ terms.staffLabel }}:</span>
                            <span>{{ sale?.cashierName }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{ terms.clientLabel }}:</span>
                            <span class="font-medium">{{ sale?.customer?.name || 'Walk-in' }}</span>
                        </div>

                        <!-- Pharmacy Specific Fields on Receipt -->
                        <template v-if="terms.showPrescriptions && sale?.rxNumber">
                            <div class="flex justify-between text-teal-600 dark:text-teal-400 font-semibold">
                                <span>Rx Number:</span>
                                <span>{{ sale.rxNumber }}</span>
                            </div>
                            <div v-if="sale.doctorName" class="flex justify-between text-muted-foreground">
                                <span>Prescriber:</span>
                                <span>Dr. {{ sale.doctorName }}</span>
                            </div>
                        </template>

                        <!-- Hardware Specific Fields on Receipt -->
                        <template v-if="terms.showDimensionsAndSite && sale?.deliverySite">
                            <div class="flex justify-between text-amber-600 dark:text-amber-400 font-semibold">
                                <span>Delivery Site:</span>
                                <span>{{ sale.deliverySite }}</span>
                            </div>
                        </template>

                        <!-- Restaurant Specific Fields on Receipt -->
                        <template v-if="terms.showTableManagement && sale?.tableNumber">
                            <div class="flex justify-between text-orange-600 dark:text-orange-400 font-semibold">
                                <span>Table / Area:</span>
                                <span>{{ sale.tableNumber }} ({{ sale.guestCount || 2 }} Guests)</span>
                            </div>
                        </template>

                        <div v-if="sale?.mpesaCode || sale?.payments?.some((p) => p.method === 'M-Pesa' || p.method?.toLowerCase().includes('mpesa') || p.method?.toLowerCase().includes('till') || p.method?.toLowerCase().includes('paybill'))" class="flex justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                            <span>M-Pesa Code:</span>
                            <span class="font-mono">{{ sale?.mpesaCode || sale?.payments?.find((p) => p.method === 'M-Pesa' || p.method?.toLowerCase().includes('mpesa') || p.method?.toLowerCase().includes('till') || p.method?.toLowerCase().includes('paybill'))?.reference || 'CONFIRMED' }}</span>
                        </div>
                    </div>

                    <!-- Line Items Table -->
                    <div class="border-b border-dashed border-border/90 pb-2">
                        <table class="w-full text-[11px]">
                            <thead>
                                <tr class="text-muted-foreground border-b border-border/40">
                                    <th class="text-left pb-1 font-semibold">{{ terms.itemLabel }}</th>
                                    <th class="text-center pb-1 font-semibold">Qty</th>
                                    <th class="text-right pb-1 font-semibold">Price</th>
                                    <th class="text-right pb-1 font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/30">
                                <tr v-for="item in sale?.items || []" :key="item.product.id" class="py-1">
                                    <td class="py-1 max-w-[130px]">
                                        <div class="font-sans font-medium text-[11px]">{{ item.product.name }}</div>
                                        <!-- Pharmacy dosage instructions -->
                                        <div v-if="item.product.dosage" class="text-[9px] text-teal-600 dark:text-teal-400 italic">
                                            Dosage: {{ item.product.dosage }}
                                        </div>
                                        <!-- Electronics serial warranty -->
                                        <div v-if="item.product.serialNumber" class="text-[9px] text-violet-600 font-mono">
                                            S/N: {{ item.product.serialNumber }} ({{ item.product.warrantyMonths }}m wty)
                                        </div>
                                        <div v-if="item.discountPercent > 0" class="text-[9px] text-amber-600">
                                            Disc: {{ item.discountPercent }}%
                                        </div>
                                    </td>
                                    <td class="text-center py-1 font-mono">{{ item.quantity }}</td>
                                    <td class="text-right py-1 font-mono">{{ formatCurrency(item.unitPrice) }}</td>
                                    <td class="text-right py-1 font-mono font-medium">
                                        {{ formatCurrency(item.quantity * item.unitPrice * (1 - item.discountPercent / 100)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary & Tax Breakdown -->
                    <div class="text-[11px] space-y-1 border-b border-dashed border-border/90 pb-2 font-mono">
                        <div class="flex justify-between text-muted-foreground">
                            <span>Subtotal:</span>
                            <span>{{ formatCurrency(sale?.subtotal || 0) }}</span>
                        </div>
                        <div v-if="(sale?.discountAmount || 0) > 0" class="flex justify-between text-amber-600">
                            <span>Order Discount:</span>
                            <span>-{{ formatCurrency(sale?.discountAmount || 0) }}</span>
                        </div>
                        <div class="flex justify-between text-muted-foreground">
                            <span>VAT (16% Incl.):</span>
                            <span>{{ formatCurrency(sale?.taxAmount || 0) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold pt-1 border-t border-border/40 font-sans">
                            <span>TOTAL PAID:</span>
                            <span class="text-primary">{{ formatCurrency(sale?.total || 0) }}</span>
                        </div>
                    </div>

                    <!-- Payments & Change -->
                    <div class="text-[11px] space-y-1 border-b border-dashed border-border/90 pb-2 font-mono">
                        <div v-for="(p, idx) in sale?.payments || []" :key="idx" class="space-y-0.5">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Payment ({{ p.method }}):</span>
                                <span>{{ formatCurrency(p.amount) }}</span>
                            </div>
                            <div v-if="p.method === 'M-Pesa' || p.method?.toLowerCase().includes('mpesa') || p.method?.toLowerCase().includes('till') || p.method?.toLowerCase().includes('paybill')" class="flex justify-between text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
                                <span>M-Pesa Code:</span>
                                <span class="font-mono">{{ p.reference || sale?.mpesaCode || 'CONFIRMED' }}</span>
                            </div>
                            <div v-else-if="p.reference" class="flex justify-between text-[10px] text-muted-foreground font-mono">
                                <span>Ref / Auth:</span>
                                <span>{{ p.reference }}</span>
                            </div>
                        </div>
                        <div v-if="(sale?.changeGiven || 0) > 0" class="flex justify-between font-bold text-foreground">
                            <span>Change Returned:</span>
                            <span>{{ formatCurrency(sale?.changeGiven || 0) }}</span>
                        </div>
                    </div>

                    <!-- Footer & KRA / Industry Legal Notes -->
                    <div class="text-center pt-2 space-y-1.5 text-muted-foreground text-[10px]">
                        <p class="font-sans font-semibold text-foreground">Thank you for your business!</p>
                        
                        <p v-if="terms.showPrescriptions">
                            Please complete entire course of prescribed antibiotics. Keep out of reach of children.
                        </p>
                        <p v-else-if="terms.showDimensionsAndSite">
                            Verify material specifications and dimensions upon dispatch before signing delivery note.
                        </p>
                        <p v-else-if="terms.showSerialWarranty">
                            Please retain this invoice with serial numbers for manufacturer warranty claims.
                        </p>
                        <p v-else>
                            Goods once sold cannot be returned without original receipt.
                        </p>

                        <!-- Thermal Barcode for Scanner Lookup -->
                        <div class="pt-2 flex flex-col items-center justify-center select-none">
                            <svg
                                :viewBox="`0 0 ${barcodeSvg.totalWidth} 40`"
                                class="w-full max-w-[210px] h-9 mx-auto"
                                preserveAspectRatio="none"
                            >
                                <rect
                                    v-for="(bar, idx) in barcodeSvg.bars"
                                    :key="idx"
                                    :x="bar.x"
                                    y="0"
                                    :width="bar.width"
                                    height="40"
                                    fill="#000000"
                                />
                            </svg>
                            <div class="font-mono text-[9px] tracking-[0.22em] font-bold text-center mt-1 text-foreground">
                                {{ sale?.receiptNumber }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <DialogFooter class="px-6 py-3.5 bg-muted/40 border-t border-border/70 flex sm:justify-between items-center gap-2 print:hidden">
                <div class="flex items-center gap-1.5">
                    <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs" @click="handleEmail">
                        <Mail class="size-3.5" />
                        <span class="hidden sm:inline">Email</span>
                    </Button>
                    <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs" @click="handleDownload">
                        <Download class="size-3.5" />
                        <span class="hidden sm:inline">PDF</span>
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" class="h-8 text-xs" @click="emit('update:open', false)">
                        Close (C)
                    </Button>
                    <Button size="sm" class="h-8 gap-1.5 text-xs bg-primary text-primary-foreground font-bold" @click="handlePrint">
                        <Printer class="size-3.5" />
                        Print Thermal (P)
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style>
@media print {
    @page {
        size: auto;
        margin: 0mm 2mm;
    }

    /* Hide entire page and chrome */
    body * {
        visibility: hidden !important;
    }

    /* Isolate and display only the thermal receipt slip */
    #thermal-receipt,
    #thermal-receipt * {
        visibility: visible !important;
    }

    #thermal-receipt {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        top: 0 !important;
        margin: 0 auto !important;
        width: 76mm !important;
        max-width: 100% !important;
        padding: 2mm 0 !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        background: transparent !important;
        color: #000000 !important;
    }

    #thermal-receipt .text-muted-foreground {
        color: #333333 !important;
    }

    #thermal-receipt .border-border\/90,
    #thermal-receipt .border-border\/70,
    #thermal-receipt .border-border\/40,
    #thermal-receipt .border-border\/30 {
        border-color: #111111 !important;
    }

    #thermal-receipt .bg-muted\/60 {
        background: transparent !important;
    }
}
</style>
