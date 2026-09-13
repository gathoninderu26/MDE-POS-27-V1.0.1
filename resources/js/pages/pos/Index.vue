<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CameraScannerModal from '@/components/pos/CameraScannerModal.vue';
import HoldSalesDrawer from '@/components/pos/HoldSalesDrawer.vue';
import PaymentModal from '@/components/pos/PaymentModal.vue';
import ReceiptModal from '@/components/pos/ReceiptModal.vue';
import DemoMissionWidget from '@/components/demo/DemoMissionWidget.vue';
import { useBranch } from '@/composables/useBranch';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import type { Customer, PaymentEntry, ProductItem } from '@/composables/useCart';
import { useCart } from '@/composables/useCart';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import { useBarcodeScanner } from '@/composables/useBarcodeScanner';
import PosLayout from '@/layouts/PosLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    Building2,
    Calendar,
    Camera,
    Check,
    ChefHat,
    Clock,
    CreditCard,
    DollarSign,
    FileText,
    Minus,
    Package,
    PauseCircle,
    Percent,
    Pill,
    Play,
    Plus,
    Receipt,
    RotateCcw,
    Ruler,
    ScanBarcode,
    Search,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Store,
    Tag,
    Trash2,
    Truck,
    User,
    UserPlus,
    Utensils,
    Wallet,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    products?: any[];
    branches?: any[];
}>();

const { currentTenant, isDemo } = useTenant();

const handleMissionFocusSearch = () => {
    barcodeInput.value?.focus();
    searchQuery.value = '';
};

const handleMissionOpenCustomer = () => {
    newCustomerModalOpen.value = true;
};

const handleMissionOpenPay = () => {
    if (items.value.length === 0 && productsCatalog.value.length > 0) {
        addItem(productsCatalog.value[0], 1);
    }
    paymentModalOpen.value = true;
};

const { currentBranch } = useBranch();
const { terms, activeCatalog } = useBusinessTerms();
const {
    items,
    customer,
    cartDiscountPercent,
    pricingMode,
    heldSales,
    activeReceipt,
    activeRxNumber,
    activeDoctorName,
    activeDeliverySite,
    activeVehicleReg,
    activeTableNumber,
    activeGuestCount,
    itemsCount,
    subtotal,
    discountAmount,
    taxAmount,
    total,
    addItem,
    updateQuantity,
    updateItemDiscount,
    updateItemPrice,
    removeItem,
    clearCart,
    setPricingMode,
    setCustomer,
    holdSale,
    resumeSale,
    completeSale,
    defaultCustomer,
} = useCart();

const { success, error, info } = useToast();

// Local active product catalog based on backend data
const selectedCategory = ref('All');
const productsCatalog = computed<ProductItem[]>(() => {
    if (props.products && props.products.length > 0) {
        return props.products.map(p => ({
            id: p.id,
            name: p.name,
            sku: p.sku || `SKU-${p.id}`,
            barcode: p.barcode,
            category: p.category || 'General',
            costPrice: p.costPrice ?? p.cost_price ?? 0,
            retailPrice: p.sellingPrice ?? p.selling_price ?? p.price ?? 0,
            wholesalePrice: p.sellingPrice ?? p.selling_price ?? p.price ?? 0,
            stock: p.stock ?? p.stock_quantity ?? 0,
            minStock: 5,
            unit: p.unit || 'Piece',
            taxRate: 16,
        }));
    }
    return [];
});
const activeMobileTab = ref<'catalog' | 'cart'>('catalog');

// Dynamic categories from catalog
const categories = computed(() => {
    const set = new Set<string>();
    set.add('All');
    productsCatalog.value.forEach((p) => {
        if (p.category) set.add(p.category);
    });
    return Array.from(set);
});

// Search & Barcode Scan
const searchQuery = ref('');
const barcodeInput = ref<HTMLInputElement | null>(null);

// Customer Accounts
const customersList = ref<Customer[]>([
    defaultCustomer,
    {
        id: 'cust-1',
        name: 'Ochieng Retailers (Wholesale)',
        phone: '+254 711 223 344',
        email: 'ochieng@retails.co.ke',
        group: 'Wholesale',
        creditLimit: 500000,
        currentBalance: 124000,
        status: 'Active',
    },
    {
        id: 'cust-2',
        name: 'Grace Muthoni (VIP / Patient)',
        phone: '+254 722 554 433',
        email: 'grace.m@gmail.com',
        group: 'VIP',
        creditLimit: 50000,
        currentBalance: 8500,
        status: 'Active',
        insuranceProvider: 'Jubilee Health',
        insurancePolicyNo: 'JUB-88912',
    },
    {
        id: 'cust-3',
        name: 'Buildcon Africa Contractors',
        phone: '+254 733 998 877',
        email: 'procurement@buildcon.co.ke',
        group: 'Corporate',
        creditLimit: 1200000,
        currentBalance: 340000,
        status: 'Active',
        siteAddress: 'Westlands Tower, Block 4B',
    },
]);

const newCustomerModalOpen = ref(false);
const newCustName = ref('');
const newCustPhone = ref('');
const newCustGroup = ref<'Retail' | 'Wholesale' | 'VIP' | 'Corporate'>('Retail');
const newCustExtra = ref('');

// Modals State
const paymentModalOpen = ref(false);
const receiptModalOpen = ref(false);
const holdDrawerOpen = ref(false);
const holdNoteModalOpen = ref(false);
const holdNoteText = ref('');
const discountModalOpen = ref(false);
const discountInput = ref(0);
const cameraScannerOpen = ref(false);

// Filtered Products
const filteredProducts = computed(() => {
    let result = productsCatalog.value;

    if (selectedCategory.value !== 'All') {
        result = result.filter((p) => p.category === selectedCategory.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                p.barcode.includes(q) ||
                p.sku.toLowerCase() === q ||
                p.brand?.toLowerCase().includes(q) ||
                p.genericName?.toLowerCase().includes(q) ||
                p.specifications?.toLowerCase().includes(q) ||
                p.serialNumber?.toLowerCase() === q ||
                p.imeiNumber === q,
        );
    }

    return result;
});

// Core Barcode / Item Lookup & Add Handler (used by Hardware Scanner, Camera Scanner, and Enter key)
const processScannedBarcode = (rawCode: string) => {
    if (!rawCode) return;
    const cleanCode = rawCode.trim();

    const matched = productsCatalog.value.find(
        (p) =>
            p.barcode === cleanCode ||
            p.sku.toLowerCase() === cleanCode.toLowerCase() ||
            p.serialNumber?.toLowerCase() === cleanCode.toLowerCase() ||
            p.imeiNumber === cleanCode,
    );

    if (matched) {
        addItem(matched, 1);
        searchQuery.value = '';
        playScanBeep('success');
        success(
            `Scanned: ${matched.name}`,
            `Added 1x ${matched.name} (${formatCurrency(pricingMode.value === 'wholesale' ? matched.wholesalePrice : matched.retailPrice)})`,
        );
        nextTick(() => {
            barcodeInput.value?.focus();
        });
    } else {
        if (filteredProducts.value.length === 1) {
            const fallback = filteredProducts.value[0];
            addItem(fallback, 1);
            searchQuery.value = '';
            playScanBeep('success');
            success(`${terms.value.itemLabel} Added`, `${fallback.name} added to cart`);
            nextTick(() => {
                barcodeInput.value?.focus();
            });
        } else {
            playScanBeep('error');
            error('Barcode Not Found', `No catalog item matches scanned code: ${cleanCode}`);
        }
    }
};

// Hardware USB / Bluetooth Barcode Scanner global listener
const { isScanning: isHardwareScanning, playScanBeep } = useBarcodeScanner({
    onScan: (code) => {
        processScannedBarcode(code);
    },
});

// Mobile Camera Barcode Scan Handler
const handleCameraScan = (code: string) => {
    processScannedBarcode(code);
};

// Barcode Scan / Enter handler (Manual Search box)
const handleBarcodeSubmit = () => {
    processScannedBarcode(searchQuery.value);
};

const handleProductClick = (product: ProductItem) => {
    addItem(product, 1);
    success('Added to Cart', `${product.name}`);
};

// Handle Payment
const openPaymentModal = () => {
    if (items.value.length === 0) {
        error('Empty Cart', `Please add ${terms.value.itemsPlural.toLowerCase()} before initiating checkout.`);
        return;
    }
    paymentModalOpen.value = true;
};

const handlePaymentComplete = (payload: { payments: PaymentEntry[]; tenderedAmount: number }) => {
    const saleItemsPayload = items.value.map(i => ({
        product_id: typeof i.product.id === 'number' ? i.product.id : null,
        product_name: i.product.name,
        quantity: i.quantity,
        unit_price: i.unitPrice,
        cost_price: i.product.costPrice ?? 0,
    }));

    router.post('/pos/sale', {
        items: saleItemsPayload,
        subtotal: subtotal.value,
        tax_amount: taxAmount.value,
        discount_amount: discountAmount.value,
        total: total.value,
        payment_method: payload.payments[0]?.method || 'Cash',
        customer_name: customer.value.name !== 'Walk-in Customer' ? customer.value.name : null,
        customer_phone: customer.value.phone || null,
    }, {
        preserveScroll: true,
        onError: (errs) => console.error('Sale sync error:', errs),
    });

    const sale = completeSale(
        currentTenant.value.name,
        currentBranch.value.name,
        `Staff Cashier`,
        payload.payments,
        payload.tenderedAmount,
        {
            rxNumber: activeRxNumber.value,
            doctorName: activeDoctorName.value,
            deliverySite: activeDeliverySite.value,
            vehicleReg: activeVehicleReg.value,
            tableNumber: activeTableNumber.value,
            guestCount: activeGuestCount.value,
            serverName: `Staff Cashier`,
        },
    );

    paymentModalOpen.value = false;
    receiptModalOpen.value = true;
    success('Transaction Completed!', `${terms.value.receiptTitle} ${sale.receiptNumber} generated.`);
};

// Hold Sale workflow
const triggerHoldSale = () => {
    if (items.value.length === 0) {
        error('Empty Cart', `Cannot hold an empty order.`);
        return;
    }
    holdNoteText.value = '';
    holdNoteModalOpen.value = true;
};

const confirmHoldSale = () => {
    const held = holdSale(holdNoteText.value);
    holdNoteModalOpen.value = false;
    if (held) {
        info('Order Saved', `Draft ${held.id} saved under ${terms.value.heldDrawerTitle}.`);
    }
};

const handleResumeHeld = (id: string) => {
    resumeSale(id);
    holdDrawerOpen.value = false;
    success('Order Resumed', `Loaded order ${id} into active terminal.`);
};

// Create New Quick Customer
const handleCreateCustomer = () => {
    if (!newCustName.value.trim() || !newCustPhone.value.trim()) {
        error('Validation', `Please provide ${terms.value.clientLabel.toLowerCase()} name and phone number.`);
        return;
    }

    const newCust: Customer = {
        id: `cust-${Date.now()}`,
        name: newCustName.value.trim(),
        phone: newCustPhone.value.trim(),
        email: `${newCustName.value.toLowerCase().replace(/\s+/g, '')}@client.local`,
        group: newCustGroup.value,
        creditLimit: newCustGroup.value === 'Wholesale' ? 200000 : 20000,
        currentBalance: 0,
        status: 'Active',
        siteAddress: terms.value.showDimensionsAndSite ? newCustExtra.value : undefined,
        insurancePolicyNo: terms.value.showPrescriptions ? newCustExtra.value : undefined,
    };

    customersList.value.push(newCust);
    setCustomer(newCust);
    newCustomerModalOpen.value = false;
    newCustName.value = '';
    newCustPhone.value = '';
    newCustExtra.value = '';
    success(`${terms.value.clientLabel} Registered`, `${newCust.name} selected for active session.`);
};

// Apply Cart Discount
const applyCartDiscount = () => {
    cartDiscountPercent.value = discountInput.value;
    discountModalOpen.value = false;
    success('Discount Applied', `${cartDiscountPercent.value}% order discount applied.`);
};

// Keyboard listener for POS Shortcuts
const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'F1' || e.key === 'F2') {
        e.preventDefault();
        barcodeInput.value?.focus();
    } else if (e.key === 'F12' || (e.code === 'Space' && e.ctrlKey)) {
        e.preventDefault();
        openPaymentModal();
    } else if (e.key === 'F4') {
        e.preventDefault();
        triggerHoldSale();
    } else if (e.key === 'F8' && terms.value.showWholesaleToggle) {
        e.preventDefault();
        setPricingMode(pricingMode.value === 'retail' ? 'wholesale' : 'retail');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    nextTick(() => {
        barcodeInput.value?.focus();
    });
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head :title="`${terms.badgeLabel} - Cashier Terminal`" />

    <PosLayout>
        <div class="flex h-full w-full overflow-hidden flex-col lg:flex-row relative">
            <!-- Mobile Navigation Tab Switcher (<lg) -->
            <div class="flex lg:hidden shrink-0 items-center justify-between border-b border-border/80 bg-card p-1.5 gap-1 shadow-sm z-10">
                <button
                    type="button"
                    :class="[
                        'flex-1 py-2 px-3 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all',
                        activeMobileTab === 'catalog'
                            ? 'bg-primary text-primary-foreground shadow'
                            : 'bg-muted/40 text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeMobileTab = 'catalog'"
                >
                    <Package class="size-3.5" />
                    <span>Catalog ({{ filteredProducts.length }})</span>
                </button>

                <button
                    type="button"
                    :class="[
                        'flex-1 py-2 px-3 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all relative',
                        activeMobileTab === 'cart'
                            ? 'bg-primary text-primary-foreground shadow'
                            : 'bg-muted/40 text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeMobileTab = 'cart'"
                >
                    <ShoppingCart class="size-3.5" />
                    <span>Cart ({{ itemsCount }})</span>
                    <Badge v-if="items.length > 0" :variant="activeMobileTab === 'cart' ? 'outline' : 'default'" class="ml-1 text-[10px] px-1.5 py-0 font-mono">
                        {{ formatCurrency(total) }}
                    </Badge>
                </button>
            </div>

            <!-- Left & Center: Product Catalog, Category Bar, Search (60% - 65% width) -->
            <div
                :class="[
                    'flex-1 flex-col overflow-hidden border-r border-border/80 bg-muted/10 p-2 sm:p-3 lg:p-4 gap-2.5 sm:gap-3',
                    activeMobileTab === 'cart' ? 'hidden lg:flex' : 'flex',
                ]"
            >
                <!-- Specialized Header Banner based on Business Vertical -->
                <div class="flex items-center justify-between px-2.5 sm:px-3 py-1.5 rounded-xl border bg-card/80 text-xs shadow-sm">
                    <div class="flex items-center gap-2 min-w-0">
                        <Badge variant="outline" class="font-bold text-[10px] sm:text-[11px] uppercase tracking-wider bg-primary/10 text-primary border-primary/20 shrink-0">
                            {{ terms.badgeLabel }}
                        </Badge>
                        <span class="text-muted-foreground hidden sm:inline">•</span>
                        <span class="font-medium text-muted-foreground truncate hidden sm:inline">{{ currentTenant.name }}</span>
                    </div>

                    <!-- Vertical Specialized Inputs in Top Header -->
                    <div class="flex items-center gap-1.5 sm:gap-2 font-mono text-[10px] sm:text-[11px] min-w-0">
                        <!-- Pharmacy Rx Input -->
                        <div v-if="terms.showPrescriptions" class="flex items-center gap-1 bg-teal-500/10 text-teal-700 dark:text-teal-300 px-1.5 sm:px-2 py-0.5 rounded-md border border-teal-500/20">
                            <Pill class="size-3.5 shrink-0" />
                            <input
                                v-model="activeRxNumber"
                                placeholder="Rx # (e.g. RX-8901)"
                                class="bg-transparent border-0 h-5 w-20 sm:w-28 text-[10px] sm:text-[11px] p-0 font-bold placeholder:text-teal-600/50 focus:ring-0"
                            />
                        </div>

                        <!-- Hardware Site Input -->
                        <div v-if="terms.showDimensionsAndSite" class="flex items-center gap-1 bg-amber-500/10 text-amber-700 dark:text-amber-300 px-1.5 sm:px-2 py-0.5 rounded-md border border-amber-500/20">
                            <Truck class="size-3.5 shrink-0" />
                            <input
                                v-model="activeDeliverySite"
                                placeholder="Site: Plot / Building"
                                class="bg-transparent border-0 h-5 w-24 sm:w-32 text-[10px] sm:text-[11px] p-0 font-bold placeholder:text-amber-600/50 focus:ring-0"
                            />
                        </div>

                        <!-- Restaurant Table Input -->
                        <div v-if="terms.showTableManagement" class="flex items-center gap-1 bg-orange-500/10 text-orange-700 dark:text-orange-300 px-1.5 sm:px-2 py-0.5 rounded-md border border-orange-500/20">
                            <Utensils class="size-3.5 shrink-0" />
                            <input
                                v-model="activeTableNumber"
                                placeholder="Table # (e.g. T-12)"
                                class="bg-transparent border-0 h-5 w-16 sm:w-20 text-[10px] sm:text-[11px] p-0 font-bold placeholder:text-orange-600/50 focus:ring-0"
                            />
                        </div>

                        <span class="text-muted-foreground hidden md:inline">{{ terms.staffLabel }}: Cashier</span>
                    </div>
                </div>

                <!-- Barcode & Search Input Bar -->
                <div class="flex items-center gap-1.5 sm:gap-2 bg-card p-1.5 sm:p-2 rounded-xl border border-border/80 shadow-sm">
                    <div class="relative flex-1">
                        <ScanBarcode class="absolute left-3 top-2.5 size-4 text-primary" />
                        <input
                            ref="barcodeInput"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="terms.searchPlaceholder"
                            class="h-9 w-full rounded-lg border-0 bg-muted/40 pl-9 pr-28 text-xs font-medium placeholder:text-muted-foreground focus:bg-background focus:ring-2 focus:ring-primary"
                            @keydown.enter.prevent="handleBarcodeSubmit"
                        />
                        <div class="absolute right-2 top-2 hidden sm:flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 select-none pointer-events-none">
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Scanner Ready</span>
                        </div>
                    </div>

                    <!-- Mobile Camera Scanner Button (Mobile Mode Only) -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 px-2.5 text-xs gap-1.5 bg-emerald-500/10 border-emerald-600/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/20 font-bold shrink-0 shadow-sm inline-flex lg:hidden"
                        @click="cameraScannerOpen = true"
                        title="Open Mobile Camera Barcode Scanner"
                    >
                        <Camera class="size-4 text-emerald-600 dark:text-emerald-400 animate-pulse" />
                        <span class="text-xs">Scan</span>
                    </Button>

                    <!-- Category Quick Select Pills (Desktop) -->
                    <div class="hidden sm:flex items-center gap-1 overflow-x-auto max-w-[320px] py-0.5 scrollbar-none">
                        <button
                            v-for="cat in categories.slice(0, 4)"
                            :key="cat"
                            type="button"
                            :class="[
                                'px-2.5 py-1 text-xs font-semibold rounded-lg whitespace-nowrap transition-all',
                                selectedCategory === cat
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/60 text-muted-foreground hover:text-foreground hover:bg-muted',
                            ]"
                            @click="selectedCategory = cat"
                        >
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <!-- Category Pills (Horizontal List) -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs scrollbar-none">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        type="button"
                        :class="[
                            'px-3 py-1 font-semibold rounded-full border text-xs whitespace-nowrap transition-all shrink-0',
                            selectedCategory === cat
                                ? 'bg-foreground text-background border-foreground shadow-sm'
                                : 'bg-card border-border/80 text-muted-foreground hover:border-foreground/40',
                        ]"
                        @click="selectedCategory = cat"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Product Grid (Touch-friendly & fast) -->
                <div class="flex-1 overflow-y-auto pr-1 pb-16 lg:pb-0">
                    <div class="grid grid-cols-2 gap-2 sm:gap-2.5 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-4">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="group flex flex-col justify-between rounded-xl border border-border/80 bg-card p-2.5 sm:p-3 shadow-sm hover:border-primary hover:shadow-md transition-all cursor-pointer active:scale-95 select-none"
                            @click="handleProductClick(product)"
                        >
                            <div class="space-y-1 sm:space-y-1.5">
                                <div class="flex items-start justify-between gap-1">
                                    <Badge variant="outline" class="font-mono text-[9px] sm:text-[10px] uppercase font-bold text-primary bg-primary/5 border-primary/20 truncate">
                                        {{ product.category }}
                                    </Badge>
                                    <Badge
                                        :variant="product.stock <= 5 ? 'destructive' : product.stock <= 15 ? 'warning' : 'outline'"
                                        class="font-mono text-[9px] sm:text-[10px] px-1.5 py-0 shrink-0"
                                    >
                                        {{ product.stock }} {{ product.unit || '' }}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 class="font-bold text-xs leading-tight text-foreground line-clamp-2">{{ product.name }}</h4>
                                    
                                    <!-- Specialized Vertical Subtitle -->
                                    <p v-if="product.genericName" class="text-[10px] text-teal-600 dark:text-teal-400 font-medium mt-0.5 italic truncate">
                                        Rx: {{ product.genericName }}
                                    </p>
                                    <p v-else-if="product.specifications" class="text-[10px] text-amber-600 dark:text-amber-400 font-mono mt-0.5 truncate">
                                        {{ product.specifications }}
                                    </p>
                                    <p v-else-if="product.modifiers" class="text-[10px] text-orange-600 dark:text-orange-400 font-medium mt-0.5 truncate">
                                        {{ product.modifiers }}
                                    </p>
                                    <p v-else-if="product.serialNumber" class="text-[10px] text-violet-600 dark:text-violet-400 font-mono mt-0.5 truncate">
                                        S/N: {{ product.serialNumber }}
                                    </p>
                                    <p v-else class="text-[10px] text-muted-foreground mt-0.5 truncate">
                                        {{ product.brand }} • {{ product.sku }}
                                    </p>
                                </div>
                            </div>

                            <!-- Expiry or Dosage Pill for Pharmacy -->
                            <div v-if="product.expiryDate" class="mt-1 flex items-center gap-1.5 text-[9px] sm:text-[10px] font-mono text-muted-foreground">
                                <span>Batch: {{ product.batchNumber }}</span>
                                <span class="text-teal-600 font-bold">Exp: {{ product.expiryDate }}</span>
                            </div>

                            <div class="mt-2 sm:mt-3 flex items-center justify-between border-t border-border/50 pt-2 font-mono">
                                <div>
                                    <span class="text-[9px] sm:text-[10px] text-muted-foreground block font-sans">
                                        {{ pricingMode === 'wholesale' ? 'Wholesale' : 'Price' }}
                                    </span>
                                    <span class="font-bold text-xs sm:text-sm text-primary">
                                        {{ formatCurrency(pricingMode === 'wholesale' ? product.wholesalePrice : product.retailPrice) }}
                                    </span>
                                </div>
                                <div class="flex size-6 items-center justify-center rounded-lg bg-muted text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                                    <Plus class="size-3.5" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredProducts.length === 0" class="py-16 text-center text-muted-foreground text-xs">
                        <Package class="size-8 mx-auto mb-2 opacity-40" />
                        <p class="font-semibold text-sm">No {{ terms.itemsPlural.toLowerCase() }} found</p>
                        <p class="text-muted-foreground mt-0.5">Try searching with a different term or category.</p>
                    </div>
                </div>

                <!-- Floating Mobile Cart Bar (When viewing catalog on mobile with items in cart) -->
                <div v-if="items.length > 0" class="lg:hidden absolute bottom-3 left-3 right-3 z-30">
                    <button
                        type="button"
                        class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-xl flex items-center justify-between transition-all active:scale-[0.98]"
                        @click="activeMobileTab = 'cart'"
                    >
                        <div class="flex items-center gap-2">
                            <ShoppingCart class="size-4" />
                            <span>View Cart ({{ itemsCount }} {{ itemsCount === 1 ? 'item' : 'items' }})</span>
                        </div>
                        <div class="font-mono text-base font-black">
                            {{ formatCurrency(total) }} →
                        </div>
                    </button>
                </div>
            </div>

            <!-- Right: Interactive Shopping Cart & Cashier Engine (35% - 40% width on desktop, 100% on mobile cart tab) -->
            <div
                :class="[
                    'w-full max-w-none lg:max-w-md xl:max-w-lg flex-col bg-card border-l border-border/80 shadow-lg',
                    activeMobileTab === 'catalog' ? 'hidden lg:flex' : 'flex flex-1',
                ]"
            >
                <!-- Cart Top Header: Client, Price Tier & Order Actions -->
                <div class="p-2.5 sm:p-3 border-b border-border/70 space-y-2 bg-muted/20">
                    <!-- Mobile Back to Catalog Button -->
                    <div class="lg:hidden flex items-center justify-between pb-1 border-b border-border/60">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                            @click="activeMobileTab = 'catalog'"
                        >
                            <ArrowLeft class="size-3.5" />
                            <span>← Add More Products</span>
                        </button>
                        <span class="text-xs font-bold text-muted-foreground font-mono">{{ itemsCount }} items</span>
                    </div>

                    <!-- Client Selector & Add Quick Client -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex-1">
                            <select
                                :value="customer.id"
                                class="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-xs font-semibold"
                                @change="(e: any) => {
                                    const c = customersList.find(x => x.id === e.target.value);
                                    if (c) setCustomer(c);
                                }"
                            >
                                <option v-for="c in customersList" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.group }})
                                </option>
                            </select>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-2 text-xs gap-1 shrink-0"
                            @click="newCustomerModalOpen = true"
                        >
                            <UserPlus class="size-3.5" />
                            <span class="hidden sm:inline">Add {{ terms.clientLabel }}</span>
                        </Button>
                    </div>

                    <!-- Client Credit / Insurance Co-pay Indicator -->
                    <div v-if="customer.creditLimit > 0" class="flex items-center justify-between bg-amber-500/10 px-2.5 py-1 rounded-md text-[11px] text-amber-800 dark:text-amber-300">
                        <span>Credit Limit: {{ formatCurrency(customer.creditLimit) }}</span>
                        <span class="font-bold">Balance: {{ formatCurrency(customer.currentBalance) }}</span>
                    </div>
                    <div v-else-if="customer.insurancePolicyNo" class="flex items-center justify-between bg-teal-500/10 px-2.5 py-1 rounded-md text-[11px] text-teal-800 dark:text-teal-300">
                        <span>Insurance: {{ customer.insuranceProvider }} ({{ customer.insurancePolicyNo }})</span>
                        <span class="font-bold">Co-pay Active</span>
                    </div>

                    <!-- Pricing Mode Switcher & Cart Controls -->
                    <div class="flex items-center justify-between text-xs pt-1">
                        <div v-if="terms.showWholesaleToggle" class="flex items-center rounded-lg border border-border/80 p-0.5 bg-muted/40 text-[11px]">
                            <button
                                type="button"
                                :class="[
                                    'px-2.5 py-0.5 font-bold rounded-md transition-all',
                                    pricingMode === 'retail' ? 'bg-background shadow text-foreground' : 'text-muted-foreground',
                                ]"
                                @click="setPricingMode('retail')"
                            >
                                Retail
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'px-2.5 py-0.5 font-bold rounded-md transition-all',
                                    pricingMode === 'wholesale' ? 'bg-background shadow text-primary' : 'text-muted-foreground',
                                ]"
                                @click="setPricingMode('wholesale')"
                            >
                                Wholesale
                            </button>
                        </div>
                        <div v-else class="text-[11px] text-muted-foreground font-semibold">
                            Standard {{ terms.badgeLabel }}
                        </div>

                        <!-- Hold & Cart Action Buttons -->
                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs gap-1 text-muted-foreground hover:text-foreground relative"
                                @click="holdDrawerOpen = true"
                            >
                                <PauseCircle class="size-3.5" />
                                <span>{{ terms.heldDrawerTitle.split(' ')[0] }}</span>
                                <Badge v-if="heldSales.length > 0" variant="destructive" class="ml-0.5 text-[9px] px-1 py-0">
                                    {{ heldSales.length }}
                                </Badge>
                            </Button>

                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs gap-1 text-muted-foreground hover:text-amber-600"
                                :disabled="items.length === 0"
                                @click="triggerHoldSale"
                            >
                                <Play class="size-3.5" />
                                <span>{{ terms.holdOrderAction }}</span>
                            </Button>

                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-1.5 text-xs text-muted-foreground hover:text-rose-600"
                                :disabled="items.length === 0"
                                @click="clearCart"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Line Items Scrollable List -->
                <div class="flex-1 overflow-y-auto p-2.5 sm:p-3 divide-y divide-border/60">
                    <div
                        v-for="item in items"
                        :key="item.product.id"
                        class="py-2.5 first:pt-0 last:pb-0 space-y-1.5 group"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h5 class="text-xs font-bold leading-tight text-foreground truncate">{{ item.product.name }}</h5>
                                
                                <!-- Dosage Instructions or Serial note -->
                                <div v-if="item.product.dosage" class="text-[11px] text-teal-600 dark:text-teal-400 font-medium">
                                    Dosage: {{ item.product.dosage }}
                                </div>
                                <div v-if="item.product.serialNumber" class="text-[10px] text-violet-600 font-mono">
                                    S/N: {{ item.product.serialNumber }}
                                </div>

                                <div class="flex items-center gap-2 text-[11px] text-muted-foreground font-mono mt-0.5">
                                    <span>@ {{ formatCurrency(item.unitPrice) }}</span>
                                    <span v-if="item.discountPercent > 0" class="text-amber-600 font-semibold font-sans">
                                        -{{ item.discountPercent }}% off
                                    </span>
                                </div>
                            </div>

                            <div class="text-right font-mono font-bold text-xs text-foreground shrink-0">
                                {{ formatCurrency(item.quantity * item.unitPrice * (1 - item.discountPercent / 100)) }}
                            </div>
                        </div>

                        <!-- Stepper & Actions -->
                        <div class="flex items-center justify-between pt-0.5">
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="flex size-7 sm:size-6 items-center justify-center rounded-md border border-border/80 bg-background text-foreground hover:bg-muted active:scale-95 transition-transform"
                                    @click="updateQuantity(item.product.id, item.quantity - 1)"
                                >
                                    <Minus class="size-3" />
                                </button>
                                <input
                                    :value="item.quantity"
                                    type="number"
                                    min="1"
                                    class="h-7 sm:h-6 w-12 rounded-md border border-input bg-background text-center font-mono text-xs font-bold"
                                    @change="(e: any) => updateQuantity(item.product.id, Number(e.target.value))"
                                />
                                <button
                                    type="button"
                                    class="flex size-7 sm:size-6 items-center justify-center rounded-md border border-border/80 bg-background text-foreground hover:bg-muted active:scale-95 transition-transform"
                                    @click="updateQuantity(item.product.id, item.quantity + 1)"
                                >
                                    <Plus class="size-3" />
                                </button>
                            </div>

                            <div class="flex items-center gap-1 text-[11px]">
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-primary px-2 py-1 rounded border border-transparent hover:border-border/60 text-xs"
                                    @click="() => {
                                        const disc = prompt('Enter Item Discount %:', String(item.discountPercent));
                                        if (disc !== null) updateItemDiscount(item.product.id, Number(disc));
                                    }"
                                >
                                    % Disc
                                </button>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-rose-600 p-1.5"
                                    @click="removeItem(item.product.id)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="items.length === 0" class="h-full flex flex-col items-center justify-center py-16 text-center text-muted-foreground text-xs">
                        <ShoppingCart class="size-10 opacity-30 mb-2" />
                        <p class="font-bold text-sm text-foreground">Order Queue Empty</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5 max-w-xs">{{ terms.emptyCartMessage }}</p>
                    </div>
                </div>

                <!-- Live Subtotal, VAT, Discounts, and Total Summary -->
                <div class="border-t border-border/80 bg-muted/20 p-3 sm:p-3.5 space-y-1.5 sm:space-y-2 text-xs font-mono">
                    <div class="flex justify-between text-muted-foreground">
                        <span>Items Count:</span>
                        <span>{{ itemsCount }} units</span>
                    </div>

                    <div class="flex justify-between text-muted-foreground">
                        <span>Subtotal:</span>
                        <span>{{ formatCurrency(subtotal) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-muted-foreground">
                        <span class="flex items-center gap-1 font-sans">
                            Discount:
                            <button
                                type="button"
                                class="text-primary hover:underline text-[11px]"
                                @click="discountModalOpen = true"
                            >
                                ({{ cartDiscountPercent }}%)
                            </button>
                        </span>
                        <span class="text-amber-600">-{{ formatCurrency(discountAmount) }}</span>
                    </div>

                    <div class="flex justify-between text-muted-foreground">
                        <span>Tax / VAT:</span>
                        <span>{{ formatCurrency(taxAmount) }}</span>
                    </div>

                    <div class="flex justify-between items-baseline pt-2 border-t border-border/70 font-sans">
                        <span class="text-xs sm:text-sm font-bold text-foreground">TOTAL PAYABLE:</span>
                        <span class="text-xl sm:text-2xl font-black text-primary font-mono">{{ formatCurrency(total) }}</span>
                    </div>
                </div>

                <!-- Tender Actions Area -->
                <div class="p-2.5 sm:p-3 border-t border-border/80 bg-card space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            variant="outline"
                            class="h-11 font-bold text-xs gap-1.5 bg-background border-emerald-600/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/10"
                            :disabled="items.length === 0"
                            @click="openPaymentModal"
                        >
                            <Smartphone class="size-4 text-emerald-600" />
                            M-Pesa Express
                        </Button>

                        <Button
                            variant="outline"
                            class="h-11 font-bold text-xs gap-1.5 bg-background border-border/80 hover:bg-muted"
                            :disabled="items.length === 0"
                            @click="openPaymentModal"
                        >
                            <Banknote class="size-4 text-amber-600" />
                            Cash Exact
                        </Button>
                    </div>

                    <!-- Main Pay & Complete Button -->
                    <Button
                        size="lg"
                        class="h-12 w-full bg-primary hover:bg-primary/90 text-primary-foreground font-black text-sm tracking-wide gap-2 shadow-md"
                        :disabled="items.length === 0"
                        @click="openPaymentModal"
                    >
                        <CreditCard class="size-5" />
                        <span>{{ terms.checkoutAction.toUpperCase() }}</span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <PaymentModal
            :open="paymentModalOpen"
            :total-amount="total"
            :customer="customer"
            @update:open="(v) => (paymentModalOpen = v)"
            @complete="handlePaymentComplete"
        />

        <!-- Receipt Print Modal -->
        <ReceiptModal
            :open="receiptModalOpen"
            :sale="activeReceipt"
            :kra-pin="currentTenant.kraPin"
            @update:open="(v) => (receiptModalOpen = v)"
        />

        <!-- Held Sales Drawer -->
        <HoldSalesDrawer
            :open="holdDrawerOpen"
            :held-sales="heldSales"
            @update:open="(v) => (holdDrawerOpen = v)"
            @resume="handleResumeHeld"
        />

        <!-- Hold Note Prompt Modal -->
        <Dialog :open="holdNoteModalOpen" @update:open="(v) => (holdNoteModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">{{ terms.holdOrderAction }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-2 py-2">
                    <Label class="text-xs">Optional Reference Note (e.g., Table 4, Waiting for Doctor verification, Driver dispatch)</Label>
                    <Input v-model="holdNoteText" placeholder="Enter note..." class="text-xs" />
                </div>
                <DialogFooter class="gap-2 flex-row justify-end">
                    <Button variant="ghost" size="sm" @click="holdNoteModalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="confirmHoldSale">{{ terms.holdOrderAction }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cart Discount Modal -->
        <Dialog :open="discountModalOpen" @update:open="(v) => (discountModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-xs">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Apply Order Discount</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <Label class="text-xs">Discount Percentage (%)</Label>
                    <Input v-model.number="discountInput" type="number" min="0" max="100" class="text-lg font-bold font-mono" />
                </div>
                <DialogFooter>
                    <Button size="sm" class="w-full bg-primary" @click="applyCartDiscount">Apply Discount</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Quick Create Customer Modal -->
        <Dialog :open="newCustomerModalOpen" @update:open="(v) => (newCustomerModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">New {{ terms.clientLabel }} Account</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">{{ terms.clientLabel }} Name</Label>
                        <Input v-model="newCustName" :placeholder="terms.clientPlaceholder" class="text-xs mt-1" />
                    </div>
                    <div>
                        <Label class="text-xs">Phone Number (M-Pesa / SMS)</Label>
                        <Input v-model="newCustPhone" placeholder="07XX XXX XXX" class="text-xs mt-1 font-mono" />
                    </div>
                    <div>
                        <Label class="text-xs">Account Tier / Group</Label>
                        <select v-model="newCustGroup" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option value="Retail">Standard Retail</option>
                            <option value="Wholesale">Wholesale / Trade</option>
                            <option value="VIP">VIP Account</option>
                            <option value="Corporate">Corporate / Credit</option>
                        </select>
                    </div>
                    <div v-if="terms.showDimensionsAndSite">
                        <Label class="text-xs">Job Site / Delivery Address</Label>
                        <Input v-model="newCustExtra" placeholder="e.g. Westlands Tower Plot 4" class="text-xs mt-1" />
                    </div>
                    <div v-else-if="terms.showPrescriptions">
                        <Label class="text-xs">Insurance Policy / Member No.</Label>
                        <Input v-model="newCustExtra" placeholder="e.g. JUB-908129" class="text-xs mt-1" />
                    </div>
                </div>
                <DialogFooter class="gap-2 flex-row justify-end">
                    <Button variant="ghost" size="sm" @click="newCustomerModalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleCreateCustomer">Register & Select</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Mobile Camera Barcode Scanner Modal -->
        <CameraScannerModal
            :open="cameraScannerOpen"
            @update:open="(v) => (cameraScannerOpen = v)"
            @scan="handleCameraScan"
        />

        <!-- Interactive Demo Missions Widget (Demo Mode Only) -->
        <DemoMissionWidget
            v-if="isDemo"
            :cart-items-count="itemsCount"
            :customer-selected="customer.id !== defaultCustomer.id || newCustomerModalOpen"
            :sale-completed="activeReceipt !== null"
            :receipt-viewed="receiptModalOpen"
            :tenant-slug="currentTenant.slug"
            @focus-search="handleMissionFocusSearch"
            @open-customer-modal="handleMissionOpenCustomer"
            @open-pay-modal="handleMissionOpenPay"
        />
    </PosLayout>
</template>
