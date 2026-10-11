<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { Eye, Percent, Printer, Tag } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface ShelfPriceTagData {
    id: string | number;
    name: string;
    sku?: string;
    price?: number;
    category?: string;
    unit?: string;
    branch?: string;
}

const props = defineProps<{
    open: boolean;
    product?: ShelfPriceTagData | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { currentTenant } = useTenant();

// Configuration States
const printQuantity = ref(1);
const tagSize = ref<'standard' | 'promo' | 'compact'>('standard');
const tagTheme = ref<'classic' | 'promo_yellow' | 'sale_red'>('classic');

// Content Customizations
const customPrice = ref<number>(0);
const customUnit = ref<string>('Piece');
const showPromo = ref(false);
const wasPrice = ref<number>(0);
const showStoreName = ref(true);
const showVatNotice = ref(true);
const showSku = ref(true);
const showCategory = ref(true);
const showDate = ref(true);
const customNote = ref('');

// Initialize values when product changes
watch(
    () => props.product,
    (prod) => {
        if (prod) {
            customPrice.value = Number(prod.price ?? 0);
            customUnit.value = prod.unit || 'Piece';
            wasPrice.value = Math.round((Number(prod.price ?? 0) * 1.15) / 5) * 5; // default suggested regular price
            showPromo.value = false;
            customNote.value = '';
        }
    },
    { immediate: true },
);

const effectivePrice = computed(() => Number(customPrice.value || 0));
const effectiveWasPrice = computed(() => Number(wasPrice.value || 0));
const savingsAmount = computed(() => Math.max(0, effectiveWasPrice.value - effectivePrice.value));
const savingsPercent = computed(() => {
    if (effectiveWasPrice.value <= 0 || effectivePrice.value >= effectiveWasPrice.value) return 0;
    return Math.round(((effectiveWasPrice.value - effectivePrice.value) / effectiveWasPrice.value) * 100);
});

const todayFormatted = computed(() => {
    return new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
});

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-h-[92vh] w-[95vw] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <div class="flex items-center justify-between pr-6">
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <Tag class="size-4" />
                        </div>
                        <div>
                            <span>Shelf Edge Price Tag</span>
                            <span class="block text-[11px] font-normal text-muted-foreground">
                                High-visibility retail shelf talker (No Barcode)
                            </span>
                        </div>
                    </DialogTitle>
                </div>
            </DialogHeader>

            <div class="space-y-4 py-2 text-xs">
                <!-- Product Target Banner -->
                <div class="flex flex-col justify-between gap-2 rounded-xl border border-border/80 bg-muted/40 p-3 sm:flex-row sm:items-center">
                    <div>
                        <div class="text-sm font-black leading-tight text-foreground">
                            {{ product?.name || 'Selected Product' }}
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-muted-foreground">
                            <span v-if="product?.sku" class="rounded border border-border/60 bg-muted px-1.5 py-0.5 font-mono text-[11px]">
                                SKU: {{ product.sku }}
                            </span>
                            <span v-if="product?.category" class="text-[11px]"> Cat: {{ product.category }} </span>
                            <span v-if="product?.branch" class="text-[11px]"> • {{ product.branch }} </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Base Price</div>
                        <div class="font-mono text-base font-black text-amber-600 dark:text-amber-400">
                            {{ formatCurrency(product?.price || 0) }}
                        </div>
                    </div>
                </div>

                <!-- Tag Dimensions & Theme Row -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <Label class="text-xs">Copies to Print</Label>
                        <Input v-model.number="printQuantity" type="number" min="1" max="100" class="mt-1 font-mono text-xs font-bold" />
                    </div>
                    <div>
                        <Label class="text-xs">Shelf Tag Size</Label>
                        <select v-model="tagSize" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option value="standard">60mm × 40mm (Standard Shelf Rail)</option>
                            <option value="promo">80mm × 55mm (Large Promo Talker)</option>
                            <option value="compact">50mm × 25mm (Narrow Shelf Strip)</option>
                        </select>
                    </div>
                    <div>
                        <Label class="text-xs">Tag Visual Style</Label>
                        <select v-model="tagTheme" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs font-semibold">
                            <option value="classic">Standard Clean (Monochrome)</option>
                            <option value="promo_yellow">Yellow Attention Header (Special)</option>
                            <option value="sale_red">Red Discount / Clearance Banner</option>
                        </select>
                    </div>
                </div>

                <!-- Price Customizer & Promotion Controls -->
                <div class="space-y-3 rounded-xl border border-border/80 bg-muted/25 p-3">
                    <div class="flex items-center justify-between text-xs font-semibold text-foreground">
                        <span>Shelf Price Configuration</span>
                        <label class="flex cursor-pointer items-center gap-1.5 text-amber-600 dark:text-amber-400">
                            <input type="checkbox" v-model="showPromo" class="size-3.5 rounded border-input text-amber-600" />
                            <span class="text-[11px] font-bold">Enable Promo / "Was" Price</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <Label class="text-[11px]">Display Price (KES)</Label>
                            <Input v-model.number="customPrice" type="number" step="0.5" class="mt-1 font-mono text-xs font-bold" />
                        </div>
                        <div>
                            <Label class="text-[11px]">Unit of Measure Context</Label>
                            <Input v-model="customUnit" placeholder="e.g. Piece, 500ml, 1kg, Pack" class="mt-1 text-xs" />
                        </div>
                        <div v-if="showPromo">
                            <Label class="text-[11px]">Was / Regular Price (KES)</Label>
                            <Input
                                v-model.number="wasPrice"
                                type="number"
                                step="0.5"
                                class="mt-1 border-rose-500/40 font-mono text-xs font-bold text-rose-600"
                            />
                        </div>
                        <div v-else>
                            <Label class="text-[11px]">Shelf Tag Subtext</Label>
                            <Input v-model="customNote" placeholder="e.g. Best Value, Fresh Today, Local" class="mt-1 text-xs" />
                        </div>
                    </div>

                    <!-- Promo Savings Callout if enabled -->
                    <div
                        v-if="showPromo && savingsAmount > 0"
                        class="flex items-center justify-between rounded-lg border border-rose-500/20 bg-rose-500/10 p-2 text-[11px] text-rose-700 dark:text-rose-300"
                    >
                        <span class="flex items-center gap-1 font-semibold">
                            <Percent class="size-3.5" />
                            Savings Calculated: <strong>{{ formatCurrency(savingsAmount) }} off</strong> ({{ savingsPercent }}% discount)
                        </span>
                        <span class="font-mono text-[10px] font-bold uppercase">Promo Tag Active</span>
                    </div>
                </div>

                <!-- Display Element Toggles -->
                <div class="grid grid-cols-2 gap-2 rounded-lg border border-border/60 bg-muted/20 p-2.5 text-[11px] sm:grid-cols-5">
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <input type="checkbox" v-model="showStoreName" class="size-3.5 rounded border-input text-primary" />
                        <span class="font-medium">Store Name</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <input type="checkbox" v-model="showVatNotice" class="size-3.5 rounded border-input text-primary" />
                        <span class="font-medium">VAT Inclusive</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <input type="checkbox" v-model="showSku" class="size-3.5 rounded border-input text-primary" />
                        <span class="font-medium">SKU / Code</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <input type="checkbox" v-model="showCategory" class="size-3.5 rounded border-input text-primary" />
                        <span class="font-medium">Category</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <input type="checkbox" v-model="showDate" class="size-3.5 rounded border-input text-primary" />
                        <span class="font-medium">Date Stamp</span>
                    </label>
                </div>

                <!-- Live Shelf Tag WYSIWYG Preview -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                        <span class="flex items-center gap-1 font-semibold"> <Eye class="size-3.5" /> Live Shelf Tag Preview (No Barcode) </span>
                        <span class="font-mono text-[10px] uppercase tracking-wider"> Actual Shelf Output · {{ tagSize }} </span>
                    </div>

                    <div class="flex min-h-[220px] items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/40 p-6">
                        <!-- Shelf Tag Card -->
                        <div
                            class="flex select-none flex-col justify-between overflow-hidden rounded border-2 bg-white text-black shadow-lg transition-all duration-200"
                            :class="[
                                tagSize === 'standard'
                                    ? 'h-[180px] w-[280px] p-3'
                                    : tagSize === 'promo'
                                      ? 'h-[210px] w-[320px] p-4'
                                      : 'h-[135px] w-[240px] p-2.5',
                                tagTheme === 'sale_red' ? 'border-rose-600' : tagTheme === 'promo_yellow' ? 'border-amber-500' : 'border-neutral-900',
                            ]"
                        >
                            <!-- Top Header Banner -->
                            <div>
                                <!-- Header accent banner if theme selected -->
                                <div
                                    v-if="tagTheme === 'sale_red'"
                                    class="-mx-3 -mt-3 mb-1.5 bg-rose-600 py-0.5 text-center text-[10px] font-black uppercase tracking-widest text-white"
                                >
                                    {{ showPromo ? 'SPECIAL OFFER · SAVE NOW' : 'SPECIAL PRICE' }}
                                </div>
                                <div
                                    v-else-if="tagTheme === 'promo_yellow'"
                                    class="-mx-3 -mt-3 mb-1.5 bg-amber-400 py-0.5 text-center text-[10px] font-black uppercase tracking-widest text-neutral-900"
                                >
                                    {{ showPromo ? 'PROMOTIONAL VALUE' : 'GREAT VALUE' }}
                                </div>

                                <div class="flex items-center justify-between text-[9px] font-bold uppercase tracking-wider text-neutral-600">
                                    <span v-if="showStoreName" class="max-w-[170px] truncate">
                                        {{ currentTenant.name || product?.branch || 'MDE RETAIL' }}
                                    </span>
                                    <span v-else></span>

                                    <span
                                        v-if="showCategory && product?.category"
                                        class="py-0.2 rounded bg-neutral-100 px-1 font-normal lowercase text-neutral-500"
                                    >
                                        {{ product.category }}
                                    </span>
                                </div>

                                <!-- Product Name -->
                                <div
                                    class="mt-1 line-clamp-2 font-black leading-tight tracking-tight text-neutral-900"
                                    :class="tagSize === 'promo' ? 'text-base' : 'text-sm'"
                                >
                                    {{ product?.name || 'Product Title' }}
                                </div>

                                <div v-if="customNote && !showPromo" class="mt-0.5 text-[10px] font-semibold italic text-neutral-500">
                                    {{ customNote }}
                                </div>
                            </div>

                            <!-- Center / Main Price Presentation (No Barcode!) -->
                            <div class="my-auto flex flex-col items-center justify-center py-1 text-center">
                                <!-- Was Price strike if Promo is enabled -->
                                <div v-if="showPromo && effectiveWasPrice > 0" class="mb-0.5 flex items-center gap-2">
                                    <span class="text-[11px] font-bold text-neutral-500 line-through">
                                        WAS: {{ formatCurrency(effectiveWasPrice) }}
                                    </span>
                                    <span class="py-0.2 rounded bg-rose-600 px-1.5 text-[9px] font-black text-white">
                                        SAVE {{ savingsPercent }}%
                                    </span>
                                </div>

                                <!-- HUGE PROMINENT SELLING PRICE -->
                                <div class="flex items-baseline justify-center gap-1">
                                    <span class="text-xs font-black tracking-tight text-neutral-700">KES</span>
                                    <span
                                        class="font-mono font-black leading-none tracking-tighter text-black"
                                        :class="tagSize === 'promo' ? 'text-4xl' : tagSize === 'standard' ? 'text-3xl' : 'text-2xl'"
                                    >
                                        {{ effectivePrice.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </span>
                                </div>

                                <!-- Unit of Measure Context -->
                                <div class="mt-0.5 text-[10px] font-extrabold uppercase tracking-wider text-neutral-700">
                                    PER {{ customUnit.toUpperCase() }}
                                </div>
                            </div>

                            <!-- Bottom Metadata Strip -->
                            <div
                                class="flex items-center justify-between border-t border-neutral-300 pt-1 font-mono text-[8px] font-bold text-neutral-600"
                            >
                                <div>
                                    <span v-if="showSku">REF: {{ product?.sku || 'N/A' }}</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span v-if="showVatNotice" class="font-sans font-bold text-neutral-800"> VAT INCL (16%) </span>
                                    <span v-if="showDate" class="text-neutral-400">
                                        {{ todayFormatted }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <div class="flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                    <span
                        >Print Queue: <strong>{{ printQuantity }}</strong> shelf tag{{ printQuantity > 1 ? 's' : '' }}</span
                    >
                    <span>• No Barcode Mode</span>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" @click="emit('update:open', false)">Cancel</Button>
                    <Button size="sm" class="gap-1.5 bg-amber-600 font-bold text-white hover:bg-amber-700" @click="handlePrint">
                        <Printer class="size-3.5" />
                        Print Shelf Price Tag
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Hidden Print Area for Shelf Price Tags (NO BARCODES) -->
    <div id="shelf-tag-print-container" class="hidden print:block">
        <div v-for="n in printQuantity" :key="n" class="shelf-price-tag-card" :class="[tagSize, tagTheme]">
            <!-- Banner for Promo themes -->
            <div v-if="tagTheme === 'sale_red'" class="tag-print-banner sale-banner">
                {{ showPromo ? 'SPECIAL OFFER · SAVE NOW' : 'SPECIAL PRICE' }}
            </div>
            <div v-else-if="tagTheme === 'promo_yellow'" class="tag-print-banner promo-banner">
                {{ showPromo ? 'PROMOTIONAL VALUE' : 'GREAT VALUE' }}
            </div>

            <!-- Top Header -->
            <div class="tag-print-header">
                <span v-if="showStoreName" class="store-name">
                    {{ currentTenant.name || product?.branch || 'MDE RETAIL' }}
                </span>
                <span v-if="showCategory && product?.category" class="category-name">
                    {{ product.category }}
                </span>
            </div>

            <!-- Product Title -->
            <div class="tag-print-title">
                {{ product?.name }}
            </div>
            <div v-if="customNote && !showPromo" class="tag-print-subnote">
                {{ customNote }}
            </div>

            <!-- Price Zone -->
            <div class="tag-print-price-zone">
                <div v-if="showPromo && effectiveWasPrice > 0" class="was-price-row">
                    <span class="was-strike">WAS: {{ formatCurrency(effectiveWasPrice) }}</span>
                    <span class="save-badge">SAVE {{ savingsPercent }}%</span>
                </div>

                <div class="price-main">
                    <span class="currency-prefix">KES</span>
                    <span class="price-numeral">
                        {{ effectivePrice.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                    </span>
                </div>

                <div class="unit-measure">PER {{ customUnit.toUpperCase() }}</div>
            </div>

            <!-- Tag Footer -->
            <div class="tag-print-footer">
                <span v-if="showSku" class="sku-ref">REF: {{ product?.sku || 'N/A' }}</span>
                <span v-if="showVatNotice" class="vat-tag">VAT INCL (16%)</span>
                <span v-if="showDate" class="date-tag">{{ todayFormatted }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden !important;
    }
    #shelf-tag-print-container,
    #shelf-tag-print-container * {
        visibility: visible !important;
    }
    #shelf-tag-print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 4mm;
    }

    .shelf-price-tag-card {
        page-break-inside: avoid;
        break-inside: avoid;
        margin: 2mm;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: #000;
        background: #fff;
        border: 1pt solid #000;
        border-radius: 2mm;
        padding: 2.5mm;
        overflow: hidden;
    }

    /* Size Dimensions */
    .shelf-price-tag-card.standard {
        width: 60mm;
        height: 40mm;
    }
    .shelf-price-tag-card.promo {
        width: 80mm;
        height: 55mm;
    }
    .shelf-price-tag-card.compact {
        width: 50mm;
        height: 25mm;
    }

    .tag-print-banner {
        text-align: center;
        font-size: 7pt;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin: -2.5mm -2.5mm 1.5mm -2.5mm;
        padding: 1mm 0;
        color: #fff;
    }
    .tag-print-banner.sale-banner {
        background-color: #000 !important;
        color: #fff !important;
        border-bottom: 1pt solid #000;
    }
    .tag-print-banner.promo-banner {
        background-color: #eee !important;
        color: #000 !important;
        border-bottom: 1pt solid #000;
    }

    .tag-print-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 6.5pt;
        font-weight: 800;
        text-transform: uppercase;
        color: #333;
    }

    .tag-print-title {
        font-size: 9pt;
        font-weight: 900;
        line-height: 1.15;
        max-height: 19pt;
        overflow: hidden;
        color: #000;
        margin-top: 1mm;
    }

    .tag-print-subnote {
        font-size: 6.5pt;
        font-style: italic;
        color: #555;
    }

    .tag-print-price-zone {
        text-align: center;
        margin: auto 0;
    }

    .was-price-row {
        font-size: 6.5pt;
        font-weight: 700;
        margin-bottom: 0.5mm;
    }
    .was-strike {
        text-decoration: line-through;
        color: #666;
        margin-right: 2mm;
    }
    .save-badge {
        font-weight: 900;
        background: #000;
        color: #fff;
        padding: 0.2mm 1mm;
        border-radius: 1mm;
    }

    .price-main {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 1mm;
    }
    .currency-prefix {
        font-size: 8pt;
        font-weight: 900;
    }
    .price-numeral {
        font-size: 20pt;
        font-weight: 900;
        font-family: monospace;
        letter-spacing: -0.05em;
        line-height: 1;
    }
    .shelf-price-tag-card.promo .price-numeral {
        font-size: 26pt;
    }
    .shelf-price-tag-card.compact .price-numeral {
        font-size: 14pt;
    }

    .unit-measure {
        font-size: 6.5pt;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-top: 0.5mm;
    }

    .tag-print-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 5.5pt;
        font-family: monospace;
        font-weight: 700;
        border-top: 0.5pt solid #000;
        padding-top: 0.8mm;
        margin-top: 1mm;
    }
}
</style>
