<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { Check, Copy, Eye, Printer, ScanBarcode } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface ProductLabelData {
    id: string | number;
    name: string;
    sku?: string;
    barcode?: string;
    price?: number;
}

const props = defineProps<{
    open: boolean;
    product?: ProductLabelData | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { currentTenant } = useTenant();

const printQuantity = ref(1);
const labelSize = ref<'standard' | 'shelf' | 'compact'>('standard');
const showPrice = ref(true);
const showBusinessName = ref(true);
const showSku = ref(true);
const copied = ref(false);

const effectiveBarcode = computed(() => {
    return props.product?.barcode || props.product?.sku || '123456789012';
});

// Deterministic Code128-like vector barcode generator for crisp SVG rendering
const barcodeSvgBars = computed(() => {
    const code = effectiveBarcode.value;
    const bars: { x: number; width: number }[] = [];
    let currentX = 10;

    // Lead quiet zone
    currentX += 4;

    // Start pattern
    bars.push({ x: currentX, width: 2.5 });
    currentX += 4;
    bars.push({ x: currentX, width: 1.5 });
    currentX += 3;

    // Hash code string into alternating bar patterns
    for (let i = 0; i < code.length; i++) {
        const charCode = code.charCodeAt(i);
        const w1 = ((charCode * 3) % 4) + 1.2;
        const w2 = ((charCode * 7) % 3) + 1;
        const w3 = ((charCode * 5) % 4) + 1.2;

        bars.push({ x: currentX, width: w1 });
        currentX += w1 + 1.5;
        bars.push({ x: currentX, width: w2 });
        currentX += w2 + 2;
        bars.push({ x: currentX, width: w3 });
        currentX += w3 + 1.8;
    }

    // Stop pattern
    bars.push({ x: currentX, width: 3 });
    currentX += 4.5;
    bars.push({ x: currentX, width: 1.5 });
    currentX += 3;
    bars.push({ x: currentX, width: 2.5 });
    currentX += 10;

    return { bars, totalWidth: currentX };
});

const copyBarcode = async () => {
    if (!effectiveBarcode.value) return;
    try {
        await navigator.clipboard.writeText(effectiveBarcode.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {}
};

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base font-bold">
                    <ScanBarcode class="size-5 text-primary" />
                    Print Thermal Barcode Labels
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-2 text-xs">
                <!-- Product Summary Card -->
                <div class="flex items-center justify-between rounded-xl border border-border/80 bg-muted/40 p-3">
                    <div>
                        <h4 class="text-sm font-bold leading-tight text-foreground">{{ product?.name || 'Selected Product' }}</h4>
                        <div class="mt-1 flex items-center gap-3 text-muted-foreground">
                            <span class="font-mono">SKU: {{ product?.sku || 'N/A' }}</span>
                            <span>•</span>
                            <span class="font-bold text-foreground">{{ formatCurrency(product?.price || 0) }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="copyBarcode"
                        class="flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 font-mono text-muted-foreground transition-colors hover:text-foreground"
                        title="Copy Barcode Number"
                    >
                        <Check v-if="copied" class="size-3.5 text-emerald-600" />
                        <Copy v-else class="size-3.5" />
                        <span class="text-[11px]">{{ effectiveBarcode }}</span>
                    </button>
                </div>

                <!-- Label Configuration Settings -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label class="text-xs">Copies to Print</Label>
                        <Input v-model.number="printQuantity" type="number" min="1" max="500" class="mt-1 font-mono text-xs font-bold" />
                    </div>
                    <div>
                        <Label class="text-xs">Thermal Label Format</Label>
                        <select v-model="labelSize" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option value="standard">50mm × 30mm (Standard Roll)</option>
                            <option value="shelf">40mm × 25mm (Shelf Tag)</option>
                            <option value="compact">30mm × 20mm (Compact Jewelry)</option>
                        </select>
                    </div>
                </div>

                <!-- Display Toggles -->
                <div class="grid grid-cols-3 gap-2 rounded-lg border border-border/60 bg-muted/20 p-2.5">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" v-model="showBusinessName" class="size-3.5 rounded border-input text-primary" />
                        <span class="text-[11px] font-medium">Store Name</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" v-model="showPrice" class="size-3.5 rounded border-input text-primary" />
                        <span class="text-[11px] font-medium">Display Price</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" v-model="showSku" class="size-3.5 rounded border-input text-primary" />
                        <span class="text-[11px] font-medium">Show SKU</span>
                    </label>
                </div>

                <!-- Live Label Print Preview -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                        <span class="flex items-center gap-1 font-semibold"> <Eye class="size-3.5" /> Live Thermal Preview </span>
                        <span class="font-mono text-[10px] uppercase">1:1 Thermal Output</span>
                    </div>

                    <div class="flex items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/40 p-4">
                        <!-- Single Thermal Label Mockup -->
                        <div
                            class="flex select-none flex-col items-center justify-between rounded bg-white p-3 text-center text-black shadow-md"
                            :class="[
                                labelSize === 'standard'
                                    ? 'h-[140px] w-[240px]'
                                    : labelSize === 'shelf'
                                      ? 'h-[120px] w-[200px]'
                                      : 'h-[100px] w-[170px]',
                            ]"
                        >
                            <div v-if="showBusinessName" class="w-full truncate text-[10px] font-black uppercase tracking-wider text-neutral-700">
                                {{ currentTenant.name || 'MDE RETAIL' }}
                            </div>

                            <div class="mt-0.5 line-clamp-1 w-full text-[11px] font-bold text-neutral-900">
                                {{ product?.name || 'Sample Product' }}
                            </div>

                            <!-- SVG Barcode representation -->
                            <div class="my-1 flex w-full flex-col items-center">
                                <svg :viewBox="`0 0 ${barcodeSvgBars.totalWidth} 40`" class="h-8 w-full max-w-[190px]" preserveAspectRatio="none">
                                    <rect
                                        v-for="(bar, idx) in barcodeSvgBars.bars"
                                        :key="idx"
                                        :x="bar.x"
                                        y="0"
                                        :width="bar.width"
                                        height="40"
                                        fill="#000"
                                    />
                                </svg>
                                <div class="mt-0.5 font-mono text-[9px] font-bold tracking-[0.18em] text-neutral-900">
                                    {{ effectiveBarcode }}
                                </div>
                            </div>

                            <!-- Footer with SKU & Price -->
                            <div class="flex w-full items-center justify-between border-t border-neutral-200 pt-0.5 text-[9px] font-bold">
                                <span v-if="showSku" class="max-w-[90px] truncate font-mono text-neutral-600">
                                    {{ product?.sku || 'SKU' }}
                                </span>
                                <span v-else></span>

                                <span v-if="showPrice" class="text-xs font-black text-black">
                                    {{ formatCurrency(product?.price || 0) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <div class="flex items-center gap-1 text-[11px] text-muted-foreground">
                    <span>Target: {{ printQuantity }} label{{ printQuantity > 1 ? 's' : '' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" @click="emit('update:open', false)">Close</Button>
                    <Button size="sm" class="gap-1.5 bg-primary" @click="handlePrint">
                        <Printer class="size-3.5" />
                        Print Labels
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Hidden Print Area for Thermal Printing -->
    <div id="thermal-print-container" class="hidden print:block">
        <div v-for="n in printQuantity" :key="n" class="thermal-label-page" :class="[labelSize]">
            <div v-if="showBusinessName" class="label-header">
                {{ currentTenant.name || 'MDE RETAIL' }}
            </div>
            <div class="label-title">
                {{ product?.name }}
            </div>
            <div class="label-barcode">
                <svg :viewBox="`0 0 ${barcodeSvgBars.totalWidth} 40`" style="width: 100%; height: 35px" preserveAspectRatio="none">
                    <rect v-for="(bar, idx) in barcodeSvgBars.bars" :key="idx" :x="bar.x" y="0" :width="bar.width" height="40" fill="#000" />
                </svg>
                <div class="label-code">{{ effectiveBarcode }}</div>
            </div>
            <div class="label-footer">
                <span v-if="showSku">{{ product?.sku }}</span>
                <span v-if="showPrice" class="label-price">{{ formatCurrency(product?.price || 0) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden !important;
    }
    #thermal-print-container,
    #thermal-print-container * {
        visibility: visible !important;
    }
    #thermal-print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    .thermal-label-page {
        page-break-after: always;
        break-after: page;
        margin: 0 auto;
        box-sizing: border-box;
        padding: 2mm;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        text-align: center;
        color: #000;
        background: #fff;
    }
    .thermal-label-page.standard {
        width: 50mm;
        height: 30mm;
    }
    .thermal-label-page.shelf {
        width: 40mm;
        height: 25mm;
    }
    .thermal-label-page.compact {
        width: 30mm;
        height: 20mm;
    }
    .label-header {
        font-size: 7pt;
        font-weight: 800;
        text-transform: uppercase;
    }
    .label-title {
        font-size: 8pt;
        font-weight: 700;
        line-height: 1.1;
        max-height: 18pt;
        overflow: hidden;
    }
    .label-code {
        font-family: monospace;
        font-size: 7pt;
        letter-spacing: 0.1em;
        font-weight: 700;
    }
    .label-footer {
        width: 100%;
        display: flex;
        justify-content: space-between;
        font-size: 7pt;
        font-weight: 700;
        border-top: 0.5pt solid #000;
        padding-top: 1mm;
    }
    .label-price {
        font-size: 9pt;
        font-weight: 900;
    }
}
</style>
