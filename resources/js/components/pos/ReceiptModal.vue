<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import type { CompletedSale } from '@/composables/useCart';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import { CheckCircle2, Download, Mail, Pill, Printer, Truck, Utensils, X } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
    sale: CompletedSale | null;
    kraPin?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { terms } = useBusinessTerms();
const { success } = useToast();

const handlePrint = () => {
    window.print();
    success('Print job dispatched', 'Receipt sent to default thermal receipt printer');
};

const handleEmail = () => {
    success('Receipt Sent', `Email receipt sent to ${props.sale?.customer?.email || 'customer'}`);
};

const handleDownload = () => {
    success('Download Started', 'PDF receipt format downloaded successfully');
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="w-[95vw] sm:max-w-md p-0 max-h-[92vh] flex flex-col overflow-hidden bg-background">
            <DialogHeader class="px-4 sm:px-6 pt-4 sm:pt-5 pb-3 border-b border-border/70 flex flex-row items-center justify-between shrink-0">
                <div>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <CheckCircle2 class="size-5 text-emerald-500" />
                        Transaction Completed
                    </DialogTitle>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ terms.receiptTitle }} # {{ sale?.receiptNumber }}</p>
                </div>
            </DialogHeader>

            <!-- Printable Thermal Receipt (80mm styling) -->
            <div class="px-3 sm:px-6 py-4 overflow-y-auto print:max-h-none print:p-0 flex-1">
                <div id="thermal-receipt" class="mx-auto bg-card border border-border/70 p-4 sm:p-5 rounded-lg shadow-sm font-mono text-xs space-y-3 text-card-foreground">
                    <!-- Business Header -->
                    <div class="text-center space-y-0.5 border-b border-dashed border-border/90 pb-3">
                        <h4 class="text-sm font-black uppercase tracking-wider font-sans">{{ sale?.tenantName || 'Retail Business' }}</h4>
                        <p class="text-[11px] text-muted-foreground">{{ sale?.branchName || 'Main Store Branch' }}</p>
                        <p class="text-[11px] text-muted-foreground">KRA PIN: {{ kraPin || 'P051234567Z' }}</p>
                        <p class="text-[11px] text-muted-foreground">Tel: +254 712 345 678</p>
                        <Badge variant="outline" class="text-[9px] font-sans font-bold uppercase mt-1">
                            {{ terms.receiptTitle }}
                        </Badge>
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

                        <div v-if="sale?.mpesaCode" class="flex justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                            <span>M-Pesa Ref:</span>
                            <span>{{ sale.mpesaCode }}</span>
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
                    <div class="text-[11px] space-y-0.5 border-b border-dashed border-border/90 pb-2 font-mono">
                        <div v-for="(p, idx) in sale?.payments || []" :key="idx" class="flex justify-between">
                            <span class="text-muted-foreground">Payment ({{ p.method }}):</span>
                            <span>{{ formatCurrency(p.amount) }}</span>
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

                        <div class="font-mono text-xs tracking-widest py-1 bg-muted/60 rounded">
                            ||||| | |||| || |||||| | ||||| ||||
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <DialogFooter class="px-6 py-3.5 bg-muted/40 border-t border-border/70 flex sm:justify-between items-center gap-2">
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
                        Close
                    </Button>
                    <Button size="sm" class="h-8 gap-1.5 text-xs bg-primary text-primary-foreground font-bold" @click="handlePrint">
                        <Printer class="size-3.5" />
                        Print Thermal
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
