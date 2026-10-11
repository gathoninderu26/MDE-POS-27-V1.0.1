<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CameraScannerModal from '@/components/pos/CameraScannerModal.vue';
import HoldSalesDrawer from '@/components/pos/HoldSalesDrawer.vue';
import PaymentModal from '@/components/pos/PaymentModal.vue';
import PosGuideOverlay from '@/components/pos/PosGuideOverlay.vue';
import ReceiptModal from '@/components/pos/ReceiptModal.vue';
import { useBranch } from '@/composables/useBranch';
import { useFeatures } from '@/composables/useFeatures';
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
    ArrowLeft,
    ArrowRight,
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
    LayoutGrid,
    Loader2,
    Lock,
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
    Sparkles,
    Store,
    Tag,
    Trash2,
    Truck,
    Unlock,
    User,
    UserPlus,
    Utensils,
    Wallet,
    Wrench,
    X,
    Zap,
} from 'lucide-vue-next';
import { isInputFocused } from '@/composables/useKeyboardNavigation';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    products?: any[];
    branches?: any[];
    customers?: any[];
}>();

const { currentTenant, isDemo } = useTenant();
const { isMultiBranch, hasModule, hasFeature } = useFeatures();
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

// Supermarket Cashier Operating Mode ('scanner' | 'manual')
const isSupermarket = computed(() => (currentTenant.value?.businessType || 'Supermarket') === 'Supermarket');

type CashierMode = 'scanner' | 'manual';

const getInitialCashierMode = (): CashierMode => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('mde_pos_sales_mode');
        if (saved === 'scanner' || saved === 'manual') {
            return saved as CashierMode;
        }
        const scannerConfig = localStorage.getItem('mde_pos_scanner_enabled');
        if (scannerConfig === 'false') {
            return 'manual';
        }
        return 'scanner';
    }
    return 'scanner';
};

const cashierMode = ref<CashierMode>(getInitialCashierMode());

// Cashier mode lock state (L key to lock, U key to unlock) — persisted in localStorage across sessions
const modeLocked = ref<boolean>(
    typeof window !== 'undefined' && localStorage.getItem('mde_pos_mode_locked') === 'true'
);
const lockMode = () => {
    modeLocked.value = true;
    if (typeof window !== 'undefined') {
        localStorage.setItem('mde_pos_mode_locked', 'true');
        localStorage.setItem('mde_pos_sales_mode', cashierMode.value);
    }
};
const unlockMode = () => {
    modeLocked.value = false;
    if (typeof window !== 'undefined') {
        localStorage.removeItem('mde_pos_mode_locked');
    }
};

const setCashierMode = (mode: CashierMode) => {
    if (modeLocked.value) {
        info('Mode Locked', `Operating mode is locked in ${cashierMode.value === 'scanner' ? '⚡ Scanner' : '☷ Manual'} Mode. Press "U" key to unlock.`);
        return;
    }
    cashierMode.value = mode;
    if (typeof window !== 'undefined') {
        localStorage.setItem('mde_pos_sales_mode', mode);
    }
    if (mode === 'scanner') {
        nextTick(() => {
            barcodeInput.value?.focus();
        });
    }
};

// Scanner Mode: Cart position toggle (left / right) — persisted in localStorage
type CartSide = 'left' | 'right';
const cartSide = ref<CartSide>(
    (typeof window !== 'undefined' && (localStorage.getItem('mde_pos_cart_side') as CartSide)) || 'right'
);
const toggleCartSide = () => {
    cartSide.value = cartSide.value === 'right' ? 'left' : 'right';
    if (typeof window !== 'undefined') {
        localStorage.setItem('mde_pos_cart_side', cartSide.value);
    }
};
const setCartSide = (side: CartSide) => {
    cartSide.value = side;
    if (typeof window !== 'undefined') {
        localStorage.setItem('mde_pos_cart_side', side);
    }
};

// Local active product catalog based on backend data with vertical fallback
const selectedCategory = ref('All');
const selectedBrand = ref('All');
const localAddedProducts = ref<ProductItem[]>([]);

const productsCatalog = computed<ProductItem[]>(() => {
    const list: ProductItem[] = [];
    if (props.products && props.products.length > 0) {
        props.products.forEach((p) => {
            list.push({
                id: p.id,
                name: p.name,
                sku: p.sku || `SKU-${p.id}`,
                barcode: p.barcode || '',
                category: p.category || 'General',
                brand: p.brand || '',
                costPrice: p.costPrice ?? p.cost_price ?? 0,
                retailPrice: p.sellingPrice ?? p.selling_price ?? p.price ?? 0,
                wholesalePrice: p.wholesalePrice ?? p.wholesale_price ?? p.sellingPrice ?? p.selling_price ?? p.price ?? 0,
                stock: p.stock ?? p.stock_quantity ?? 0,
                minStock: p.minStock ?? 5,
                unit: p.unit || 'Piece',
                taxRate: p.taxRate ?? 16,
                cartonSku: p.cartonSku,
                baleBarcode: p.baleBarcode,
                productLine: p.productLine,
                genericName: p.genericName,
                specifications: p.specifications,
                dosage: p.dosage,
                batchNumber: p.batchNumber,
                station: p.station,
                serialNumber: p.serialNumber,
                imeiNumber: p.imeiNumber,
                expiryDate: p.expiryDate,
            });
        });
    } else if (isDemo.value && activeCatalog.value && activeCatalog.value.length > 0) {
        list.push(...activeCatalog.value);
    }

    if (localAddedProducts.value.length > 0) {
        list.unshift(...localAddedProducts.value);
    }

    return list;
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

// Dynamic brands from catalog
const brands = computed(() => {
    const set = new Set<string>();
    set.add('All');
    productsCatalog.value.forEach((p) => {
        if (p.brand && p.brand.trim()) set.add(p.brand.trim());
    });
    return Array.from(set);
});

// Search & Barcode Scan
const searchQuery = ref('');
const barcodeInput = ref<HTMLInputElement | null>(null);
const notFoundBarcode = ref('');
const showNotFoundBanner = ref(false);
const lastScannedProduct = ref<ProductItem | null>(null);
const lastScanTime = ref<string>('');

// Quick Add Product Modal state
const quickAddModalOpen = ref(false);
const quickAddName = ref('');
const quickAddBarcode = ref('');
const quickAddCategory = ref('General');
const quickAddPrice = ref<number | ''>('');
const quickAddCost = ref<number | ''>('');
const quickAddStock = ref<number>(20);

// Customer Accounts
const demoCustomers: Customer[] = [
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
];

const customersList = ref<Customer[]>([
    defaultCustomer,
    ...((props.customers && props.customers.length > 0)
        ? props.customers
        : (isDemo.value ? demoCustomers : []))
]);

const newCustomerModalOpen = ref(false);
const newCustName = ref('');
const newCustPhone = ref('');
const newCustGroup = ref<'Retail' | 'Wholesale' | 'VIP' | 'Corporate'>('Retail');
const newCustExtra = ref('');

// Modals State
const paymentModalOpen = ref(false);
const receiptModalOpen = ref(false);
const autoPrintReceipt = ref(false);
const holdDrawerOpen = ref(false);
const holdNoteModalOpen = ref(false);
const holdNoteText = ref('');
const discountModalOpen = ref(false);
const discountInput = ref(0);
const cameraScannerOpen = ref(false);

const openDiscountModal = () => {
    discountModalOpen.value = true;
};

const openNewCustomerModal = () => {
    newCustomerModalOpen.value = true;
};

const openHoldDrawer = () => {
    holdDrawerOpen.value = true;
};

const openCameraScanner = () => {
    cameraScannerOpen.value = true;
};

// Interactive Terminal Guide & Section Walkthrough
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openGuide = (step = 0) => {
    guideCurrentStep.value = step;
    guideOverlayOpen.value = true;
};

const handleOpenTour = () => {
    if (guideOverlayOpen.value && guideCurrentStep.value === 0) {
        guideCurrentStep.value = 1;
    } else {
        openGuide(0);
    }
};

const handleMissionFocusSearch = () => {
    activeMobileTab.value = 'catalog';
    nextTick(() => {
        barcodeInput.value?.focus();
        searchQuery.value = '';
    });
};

const handleMissionOpenCustomer = () => {
    activeMobileTab.value = 'cart';
    openNewCustomerModal();
};

const handleMissionOpenPay = () => {
    activeMobileTab.value = 'cart';
    if (items.value.length === 0 && productsCatalog.value.length > 0) {
        addItem(productsCatalog.value[0], 1);
    }
    openPaymentModal();
};

const handleGuideAction = (actionType: string) => {
    switch (actionType) {
        case 'focus-search':
            handleMissionFocusSearch();
            break;
        case 'select-first-category':
            if (categories.value && categories.value.length > 1) {
                selectedCategory.value = categories.value[1];
            } else if (categories.value && categories.value.length > 0) {
                selectedCategory.value = categories.value[0];
            }
            break;
        case 'open-customer':
            handleMissionOpenCustomer();
            break;
        case 'open-checkout':
            handleMissionOpenPay();
            break;
        case 'open-shortcuts':
            info('Keyboard Hotkeys', 'F2 to Search, F12 to Checkout, F4 to Hold Sale, Alt+C for Customer.');
            break;
    }
};

const handleGuideTabSwitch = (tab: 'catalog' | 'cart') => {
    activeMobileTab.value = tab;
};

// Filtered Products
const filteredProducts = computed(() => {
    let result = productsCatalog.value;

    if (selectedCategory.value !== 'All') {
        result = result.filter((p) => p.category === selectedCategory.value);
    }

    if (selectedBrand.value !== 'All') {
        result = result.filter((p) => p.brand === selectedBrand.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                (p.barcode && p.barcode.toLowerCase().includes(q)) ||
                (p.sku && p.sku.toLowerCase().includes(q)) ||
                (p.category && p.category.toLowerCase().includes(q)) ||
                (p.brand && p.brand.toLowerCase().includes(q)) ||
                (p.unit && p.unit.toLowerCase().includes(q)) ||
                ((p as any).cartonSku && (p as any).cartonSku.toLowerCase().includes(q)) ||
                ((p as any).baleBarcode && (p as any).baleBarcode.toLowerCase().includes(q)) ||
                ((p as any).productLine && (p as any).productLine.toLowerCase().includes(q)) ||
                (p.description && p.description.toLowerCase().includes(q)) ||
                (p.genericName && p.genericName.toLowerCase().includes(q)) ||
                (p.specifications && p.specifications.toLowerCase().includes(q)) ||
                (p.dosage && p.dosage.toLowerCase().includes(q)) ||
                (p.batchNumber && p.batchNumber.toLowerCase().includes(q)) ||
                (p.station && p.station.toLowerCase().includes(q)) ||
                (p.serialNumber && p.serialNumber.toLowerCase().includes(q)) ||
                (p.imeiNumber && p.imeiNumber.includes(q)),
        );
    }

    return result;
});

// Single unified product-to-cart handler used across Scanner, Manual, Search, and Quick-Add
const addProductToCart = (product: ProductItem, qty: number = 1) => {
    addItem(product, qty);
};

const getCartQuantity = (productId: string | number) => {
    const item = items.value.find((i) => i.product.id === productId);
    return item ? item.quantity : 0;
};

// Core Barcode / Item Lookup & Add Handler (used by Hardware Scanner, Camera Scanner, and Enter key)
const processScannedBarcode = (rawCode: string) => {
    if (!rawCode) return;
    const cleanCode = rawCode.trim();

    const matched = productsCatalog.value.find(
        (p) =>
            p.barcode === cleanCode ||
            p.sku.toLowerCase() === cleanCode.toLowerCase() ||
            (p as any).cartonSku?.toLowerCase() === cleanCode.toLowerCase() ||
            (p as any).baleBarcode?.toLowerCase() === cleanCode.toLowerCase() ||
            p.serialNumber?.toLowerCase() === cleanCode.toLowerCase() ||
            p.imeiNumber === cleanCode,
    );

    if (matched) {
        addProductToCart(matched, 1);
        lastScannedProduct.value = matched;
        lastScanTime.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        searchQuery.value = '';
        notFoundBarcode.value = '';
        showNotFoundBanner.value = false;
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
            addProductToCart(fallback, 1);
            lastScannedProduct.value = fallback;
            lastScanTime.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            searchQuery.value = '';
            notFoundBarcode.value = '';
            showNotFoundBanner.value = false;
            playScanBeep('success');
            success(`${terms.value.itemLabel} Added`, `${fallback.name} added to cart`);
            nextTick(() => {
                barcodeInput.value?.focus();
            });
        } else {
            playScanBeep('error');
            notFoundBarcode.value = cleanCode;
            showNotFoundBanner.value = true;
            error('Product not found', `No catalog item matches code: ${cleanCode}`);
            nextTick(() => {
                barcodeInput.value?.focus();
                barcodeInput.value?.select?.();
            });
        }
    }
};

// Hardware USB / Bluetooth Barcode Scanner global listener
const { isScanning: isHardwareScanning, playScanBeep, scannerConnected } = useBarcodeScanner({
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
    addProductToCart(product, 1);
    success('Added to Cart', `${product.name} (Qty: ${getCartQuantity(product.id)})`);
    if (cashierMode.value === 'scanner') {
        nextTick(() => {
            barcodeInput.value?.focus();
        });
    }
};

const handleSearchNotFound = () => {
    if (isSupermarket.value) {
        setCashierMode('manual');
    }
    searchQuery.value = '';
    showNotFoundBanner.value = false;
    nextTick(() => {
        barcodeInput.value?.focus();
    });
};

const openQuickAddProduct = (barcode = '') => {
    quickAddBarcode.value = barcode;
    quickAddName.value = '';
    quickAddCategory.value = categories.value.find((c) => c !== 'All') || 'General';
    quickAddPrice.value = '';
    quickAddCost.value = '';
    quickAddStock.value = 20;
    quickAddModalOpen.value = true;
};

const handleSaveQuickProduct = () => {
    if (!quickAddName.value.trim()) {
        error('Validation', 'Please enter a product name');
        return;
    }
    const priceNum = Number(quickAddPrice.value) || 0;
    if (priceNum <= 0) {
        error('Validation', 'Please enter a valid selling price');
        return;
    }

    const newProd: ProductItem = {
        id: `local-prod-${Date.now()}`,
        name: quickAddName.value.trim(),
        sku: `SKU-${Date.now().toString().slice(-5)}`,
        barcode: quickAddBarcode.value.trim(),
        category: quickAddCategory.value || 'General',
        brand: '',
        unit: 'Piece',
        costPrice: Number(quickAddCost.value) || 0,
        retailPrice: priceNum,
        wholesalePrice: priceNum,
        stock: Number(quickAddStock.value) || 20,
        minStock: 5,
        taxRate: 16,
    };

    localAddedProducts.value.unshift(newProd);
    quickAddModalOpen.value = false;
    showNotFoundBanner.value = false;

    // Immediately add to active cart
    addProductToCart(newProd, 1);
    lastScannedProduct.value = newProd;
    lastScanTime.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    success('Product Registered & Added', `${newProd.name} added to cart at ${formatCurrency(newProd.retailPrice)}`);

    nextTick(() => {
        barcodeInput.value?.focus();
    });
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
        mpesa_code: payload.payments.find(p => p.method === 'M-Pesa')?.reference || null,
        mpesa_payer_name: payload.payments.find(p => p.method === 'M-Pesa')?.payerName || null,
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

    const hasMpesa = payload.payments.some((p: any) => p.method === 'M-Pesa' || p.method?.includes('M-Pesa'));
    autoPrintReceipt.value = hasMpesa;

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

// Helper to detect if cashier is typing in an editable field (vs readonly barcode input)
const isTypingInInput = (): boolean => {
    if (typeof document === 'undefined') return false;
    const active = document.activeElement as HTMLElement | null;
    if (!active) return false;
    if (active instanceof HTMLInputElement) {
        if (active.readOnly || active.disabled) return false;
        return true;
    }
    const tag = active.tagName.toLowerCase();
    if (tag === 'textarea' || tag === 'select' || active.getAttribute('contenteditable') === 'true') {
        return true;
    }
    return false;
};

// Keyboard listener for POS Shortcuts
const handleKeydown = (e: KeyboardEvent) => {
    // F1 or F2: Focus barcode / item search
    if (e.key === 'F1' || e.key === 'F2') {
        e.preventDefault();
        barcodeInput.value?.focus();
        barcodeInput.value?.select?.();
        return;
    }

    // F12 or Space (when not typing in an input): Pay & Checkout
    if (e.key === 'F12' || (e.code === 'Space' && (e.ctrlKey || !isInputFocused()))) {
        if (!paymentModalOpen.value) {
            e.preventDefault();
            openPaymentModal();
            return;
        }
    }

    // F4: Hold active sale
    if (e.key === 'F4') {
        e.preventDefault();
        triggerHoldSale();
        return;
    }

    // F8: Clear / Cancel Cart
    if (e.key === 'F8') {
        e.preventDefault();
        if (items.value.length > 0) {
            clearCart();
            info('Cart Cleared', 'Active cart was reset.');
        }
        return;
    }

    // Alt key shortcuts in POS
    if (e.altKey && !e.ctrlKey && !e.metaKey) {
        const k = e.key.toLowerCase();
        // Alt + W: Toggle Wholesale / Retail
        if (k === 'w' && terms.value.showWholesaleToggle) {
            e.preventDefault();
            setPricingMode(pricingMode.value === 'retail' ? 'wholesale' : 'retail');
            return;
        }
        // Alt + C: Customer Select
        if (k === 'c') {
            e.preventDefault();
            openNewCustomerModal();
            return;
        }
        // Alt + D: Apply Cart Discount
        if (k === 'd') {
            e.preventDefault();
            openDiscountModal();
            return;
        }
    }

    // F3 or Alt + M: Toggle Cashier Mode (Scanner <-> Manual)
    if ((e.key === 'F3' || (e.altKey && !e.ctrlKey && !e.metaKey && e.key.toLowerCase() === 'm')) && isSupermarket.value) {
        e.preventDefault();
        if (modeLocked.value) {
            info('Mode Locked', `Operating mode is locked in ${cashierMode.value === 'scanner' ? '⚡ Scanner' : '☷ Manual'} Mode. Press "U" key to unlock.`);
            return;
        }
        setCashierMode(cashierMode.value === 'scanner' ? 'manual' : 'scanner');
        info('Operating Mode', `Switched to ${cashierMode.value === 'scanner' ? '⚡ Scanner Mode' : '☷ Manual Mode'}`);
        return;
    }

    // L / Alt+L: Lock current cashier mode (persisted across refresh and re-login)
    const isLKey = (e.key === 'l' || e.key === 'L') && !e.ctrlKey && !e.metaKey;
    if (isSupermarket.value && isLKey && (!isTypingInInput() || e.altKey)) {
        e.preventDefault();
        lockMode();
        info('Mode Locked', `${cashierMode.value === 'scanner' ? '⚡ Scanner Mode' : '☷ Manual Mode'} locked. Persisted across refresh & re-login. Press "U" to unlock.`);
        return;
    }

    // U / Alt+U: Unlock cashier mode
    const isUKey = (e.key === 'u' || e.key === 'U') && !e.ctrlKey && !e.metaKey;
    if (isSupermarket.value && isUKey && (!isTypingInInput() || e.altKey)) {
        e.preventDefault();
        unlockMode();
        info('Mode Unlocked', 'Cashier mode unlocked. Mode switching is now enabled.');
        return;
    }

    // '/' when not in input: Focus Barcode / Search
    if (!isInputFocused() && e.key === '/') {
        e.preventDefault();
        barcodeInput.value?.focus();
        barcodeInput.value?.select?.();
        return;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    nextTick(() => {
        barcodeInput.value?.focus();
    });

    // Auto-spotlight Page Guide button on first entry in demo mode or after login
    const shouldAutoOpen =
        sessionStorage.getItem('mde_auto_open_guide') === 'true' ||
        (isDemo.value && !sessionStorage.getItem('mde_pos_guide_prompted'));

    if (shouldAutoOpen) {
        sessionStorage.setItem('mde_pos_guide_prompted', 'true');
        sessionStorage.removeItem('mde_auto_open_guide');
        setTimeout(() => {
            openGuide(0);
        }, 600);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head :title="`${terms.badgeLabel} - Cashier Terminal`" />

    <PosLayout
        @open-tour="handleOpenTour"
        @open-tour-step="(step) => openGuide(step)"
    >
        <div :class="['flex h-full w-full overflow-hidden flex-col relative', isSupermarket && cashierMode === 'scanner' && cartSide === 'left' ? 'lg:flex-row-reverse' : 'lg:flex-row']">
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

            <!-- Left & Center: Product Catalog or Scanner Console -->
            <div
                :class="[
                    'flex-col overflow-hidden border-r border-border/80 bg-muted/10 p-2 sm:p-3 lg:p-4 gap-2.5 sm:gap-3 transition-all duration-200',
                    activeMobileTab === 'cart' ? 'hidden lg:flex' : 'flex',
                    isSupermarket && cashierMode === 'scanner'
                        ? 'w-full lg:w-[380px] xl:w-[420px] shrink-0'
                        : 'flex-1',
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

                <!-- Cashier Operating Mode Switcher (For Supermarket Business Only) -->
                <div
                    v-if="isSupermarket"
                    class="flex items-center justify-between gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl border bg-card/90 shadow-2xs flex-wrap"
                >
                    <!-- Mode Switcher Toggle Buttons -->
                    <div class="flex items-center gap-1 p-0.5 rounded-lg bg-muted/60 border border-border/80 text-xs">
                        <button
                            type="button"
                            :class="[
                                'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer select-none',
                                cashierMode === 'scanner'
                                    ? 'bg-amber-500 text-slate-950 shadow-sm font-black'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/80',
                                modeLocked && cashierMode !== 'scanner' ? 'opacity-50 cursor-not-allowed' : '',
                            ]"
                            :title="modeLocked ? 'Mode locked (Press U to unlock)' : 'Scanner Mode: Barcode scans automatically add products to cart'"
                            @click="setCashierMode('scanner')"
                        >
                            <Zap class="size-3.5" :class="cashierMode === 'scanner' ? 'fill-current' : ''" />
                            <span>⚡ Scanner Mode</span>
                            <Lock v-if="modeLocked && cashierMode === 'scanner'" class="size-3 ml-0.5" />
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer select-none',
                                cashierMode === 'manual'
                                    ? 'bg-primary text-primary-foreground shadow-sm font-black'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/80',
                                modeLocked && cashierMode !== 'manual' ? 'opacity-50 cursor-not-allowed' : '',
                            ]"
                            :title="modeLocked ? 'Mode locked (Press U to unlock)' : 'Manual Mode: Click product cards or search manually'"
                            @click="setCashierMode('manual')"
                        >
                            <LayoutGrid class="size-3.5" />
                            <span>☷ Manual Mode</span>
                            <Lock v-if="modeLocked && cashierMode === 'manual'" class="size-3 ml-0.5" />
                        </button>
                    </div>

                    <!-- Visual Status & Mode Hint -->
                    <div class="flex items-center gap-2 text-xs">
                        <!-- Mode Lock Indicator -->
                        <div
                            v-if="modeLocked"
                            class="flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 font-bold text-[10px]"
                            title="Mode locked (Press 'U' key to unlock)"
                        >
                            <Lock class="size-3" />
                            <span>LOCKED (Press 'U' to unlock)</span>
                        </div>
                        <div
                            v-else
                            class="hidden xl:flex items-center gap-1 text-[10px] text-muted-foreground font-medium"
                            title="Press 'L' key to lock this mode"
                        >
                            <Unlock class="size-3 opacity-60" />
                            <span>Press 'L' to lock</span>
                        </div>

                        <div
                            v-if="cashierMode === 'scanner'"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-500/10 border border-emerald-500/25 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px]"
                        >
                            <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>● Scanner Ready</span>
                        </div>

                        <div
                            v-else
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-sky-500/10 border border-sky-500/25 text-sky-600 dark:text-sky-400 font-semibold text-[11px]"
                        >
                            <span class="size-2 rounded-full border-2 border-sky-500 bg-transparent"></span>
                            <span>○ Manual Checkout</span>
                        </div>

                        <span class="text-muted-foreground text-[10px] hidden md:inline font-medium">
                            {{ cashierMode === 'scanner' ? 'Scan barcode → auto-added to cart' : 'Click product cards or search' }}
                        </span>
                    </div>

                    <!-- Cart Side Toggle Icons (Scanner Mode only) -->
                    <div v-if="cashierMode === 'scanner'" class="ml-auto flex items-center gap-0.5">
                        <button
                            type="button"
                            :class="[
                                'flex size-7 items-center justify-center rounded-md border transition-all',
                                cartSide === 'left'
                                    ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                    : 'border-border/60 bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                            title="Cart on the left"
                            @click="setCartSide('left')"
                        >
                            <ArrowLeft class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex size-7 items-center justify-center rounded-md border transition-all',
                                cartSide === 'right'
                                    ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                    : 'border-border/60 bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                            title="Cart on the right"
                            @click="setCartSide('right')"
                        >
                            <ArrowRight class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Barcode & Search Input Bar (Part 1 Target) -->
                <div id="pos-tour-search" class="flex items-center gap-1.5 sm:gap-2 bg-card p-1.5 sm:p-2 rounded-xl border border-border/80 shadow-sm relative">
                    <!-- Marker ① Search & Scan -->
                    <button
                        v-if="isDemo"
                        type="button"
                        @click="openGuide(1)"
                        class="hidden md:inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 border border-primary/30 text-primary text-[10px] font-mono font-bold hover:bg-primary/20 transition-all cursor-pointer shrink-0 shadow-2xs"
                        title="Part 1: Barcode Scanner & Search (Click to inspect)"
                    >
                        <span class="relative flex size-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2 bg-primary"></span>
                        </span>
                        <span>① Search</span>
                    </button>

                    <div class="relative flex-1">
                        <ScanBarcode class="absolute left-3 top-2.5 size-4 text-primary" />
                        <input
                            ref="barcodeInput"
                            v-model="searchQuery"
                            type="text"
                            :readonly="isSupermarket && cashierMode === 'scanner'"
                            :placeholder="
                                isSupermarket
                                    ? (cashierMode === 'scanner' ? 'Scanner active — type/scan barcode and press Enter...' : 'Search products by name, SKU, or category...')
                                    : terms.searchPlaceholder
                            "
                            :class="[
                                'h-9 w-full rounded-lg border-0 pl-9 pr-32 text-xs font-medium placeholder:text-muted-foreground',
                                isSupermarket && cashierMode === 'scanner'
                                    ? 'bg-muted/20 text-muted-foreground cursor-default select-none focus:ring-0 focus:bg-muted/20'
                                    : 'bg-muted/40 focus:bg-background focus:ring-2 focus:ring-primary',
                            ]"
                            @keydown.enter.prevent="handleBarcodeSubmit"
                        />
                        <div
                            v-if="!isSupermarket"
                            class="absolute right-2 top-2 hidden sm:flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 select-none pointer-events-none"
                        >
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Scanner Ready</span>
                        </div>
                        <div
                            v-else-if="cashierMode === 'scanner'"
                            class="absolute right-2 top-2 hidden sm:flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 select-none pointer-events-none"
                        >
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>● Scanner Ready</span>
                        </div>
                        <div
                            v-else
                            class="absolute right-2 top-2 hidden sm:flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-sky-500/10 border border-sky-500/20 text-[10px] font-semibold text-sky-600 dark:text-sky-400 select-none pointer-events-none"
                        >
                            <span class="size-1.5 rounded-full border border-sky-500 bg-transparent"></span>
                            <span>○ Manual Mode</span>
                        </div>
                    </div>

                    <!-- Mobile Camera Scanner Button (Mobile Mode Only) -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 px-2.5 text-xs gap-1.5 bg-emerald-500/10 border-emerald-600/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/20 font-bold shrink-0 shadow-sm inline-flex lg:hidden"
                        @click="openCameraScanner"
                        title="Open Mobile Camera Barcode Scanner"
                    >
                        <Camera class="size-4 text-emerald-600 dark:text-emerald-400 animate-pulse" />
                        <span class="text-xs">Scan</span>
                    </Button>

                    <!-- Category Quick Select Pills (Desktop) (Manual Mode Only) -->
                    <div
                        v-if="!isSupermarket || cashierMode !== 'scanner'"
                        class="hidden sm:flex items-center gap-1 overflow-x-auto max-w-[320px] py-0.5 scrollbar-none"
                    >
                        <button
                            v-for="cat in categories.slice(0, 4)"
                            :key="cat"
                            type="button"
                            :class="[
                                'px-2.5 py-1 text-xs font-semibold rounded-lg whitespace-nowrap transition-all cursor-pointer',
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

                <!-- Product Not Found Notification Banner (Scanner Mode & Hybrid) -->
                <div
                    v-if="showNotFoundBanner"
                    class="flex items-center justify-between gap-2 p-2 sm:p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-800 dark:text-amber-200"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <AlertCircle class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                        <span class="font-bold truncate">Product not found: <span class="font-mono underline">{{ notFoundBarcode }}</span></span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 px-2.5 text-[11px] font-bold bg-background text-foreground border-amber-500/40 hover:bg-amber-500/10"
                            @click="handleSearchNotFound"
                        >
                            <Search class="size-3 mr-1" />
                            Search Product
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="h-7 px-2.5 text-[11px] font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-xs"
                            @click="openQuickAddProduct(notFoundBarcode)"
                        >
                            <Plus class="size-3 mr-1" />
                            Add Product
                        </Button>
                        <button
                            type="button"
                            class="p-1 text-muted-foreground hover:text-foreground rounded cursor-pointer"
                            title="Dismiss notification"
                            @click="showNotFoundBanner = false"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Scanner Mode Status Hint Bar -->
                <div
                    v-if="isSupermarket && cashierMode === 'scanner'"
                    class="flex items-center justify-between px-3 py-1 rounded-lg bg-amber-500/5 border border-amber-500/20 text-[11px] text-amber-800 dark:text-amber-300 font-medium"
                >
                    <div class="flex items-center gap-1.5">
                        <Zap class="size-3 text-amber-600 dark:text-amber-400 shrink-0 fill-current" />
                        <span>Scanner Mode: Scans add directly to cart. Rescan increases quantity. {{ modeLocked ? '(Locked: press "U" to unlock)' : '(Press "L" to lock mode)' }}</span>
                    </div>
                    <span class="font-mono text-[10px] text-muted-foreground hidden lg:inline">Input auto-focused</span>
                </div>

                <!-- Brand Filter Bar (Manual Mode) -->
                <div
                    v-if="cashierMode === 'manual' && brands.length > 2"
                    class="flex items-center gap-1.5 text-xs overflow-x-auto scrollbar-none py-0.5"
                >
                    <span class="text-[11px] font-semibold text-muted-foreground shrink-0">Brand:</span>
                    <button
                        v-for="b in brands"
                        :key="b"
                        type="button"
                        :class="[
                            'px-2.5 py-0.5 text-[11px] font-medium rounded-md border whitespace-nowrap transition-all shrink-0 cursor-pointer',
                            selectedBrand === b
                                ? 'bg-primary/20 border-primary text-primary font-bold'
                                : 'bg-card border-border/70 text-muted-foreground hover:text-foreground',
                        ]"
                        @click="selectedBrand = b"
                    >
                        {{ b }}
                    </button>
                </div>

                <!-- Category Pills (Horizontal List) & Part 2 Marker (Hidden in Scanner Mode) -->
                <div
                    v-if="!isSupermarket || cashierMode !== 'scanner'"
                    class="flex items-center justify-between gap-2 pb-1"
                >
                    <div class="flex items-center gap-1.5 overflow-x-auto text-xs scrollbar-none flex-1">
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold rounded-full border text-xs whitespace-nowrap transition-all shrink-0 cursor-pointer',
                                selectedCategory === cat
                                    ? 'bg-foreground text-background border-foreground shadow-sm'
                                    : 'bg-card border-border/80 text-muted-foreground hover:border-foreground/40',
                            ]"
                            @click="selectedCategory = cat"
                        >
                            {{ cat }}
                        </button>
                    </div>

                    <!-- Marker ② Catalog Grid -->
                    <button
                        v-if="isDemo"
                        type="button"
                        @click="openGuide(2)"
                        class="hidden sm:inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 border border-primary/30 text-primary text-[10px] font-mono font-bold hover:bg-primary/20 transition-all cursor-pointer shrink-0 shadow-2xs"
                        title="Part 2: Product Catalog & Live Stock (Click to inspect)"
                    >
                        <span class="relative flex size-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2 bg-primary"></span>
                        </span>
                        <span>② Catalog</span>
                    </button>
                </div>

                <!-- Product Grid (Touch-friendly & fast) (Part 2 Target) (Hidden in Scanner Mode) -->
                <div
                    v-if="!isSupermarket || cashierMode !== 'scanner'"
                    id="pos-tour-catalog"
                    class="flex-1 overflow-y-auto pr-1 pb-16 lg:pb-0 relative"
                >
                    <div class="grid grid-cols-2 gap-2 sm:gap-2.5 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-4">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="group flex flex-col justify-between rounded-xl border border-border/80 bg-card p-2.5 sm:p-3 shadow-sm hover:border-primary hover:shadow-md transition-all cursor-pointer active:scale-95 select-none relative"
                            tabindex="0"
                            @click="handleProductClick(product)"
                            @keydown.enter.prevent="handleProductClick(product)"
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
                                        {{ product.brand ? `${product.brand} • ` : '' }}{{ product.sku }}
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
                                <div
                                    class="flex size-6 items-center justify-center rounded-lg bg-muted text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0 font-bold"
                                    title="Add to cart"
                                >
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

                <!-- Scanner Mode Station Panel (Cards hidden, cart expanded) -->
                <div
                    v-else-if="isSupermarket && cashierMode === 'scanner'"
                    class="flex-1 flex flex-col justify-between p-4 rounded-xl border border-dashed border-border/80 bg-muted/20 overflow-y-auto gap-4"
                >
                    <!-- Last Scanned Product Details Card -->
                    <div v-if="lastScannedProduct" class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <Zap class="size-3.5 fill-current" /> Last Scanned Item
                            </span>
                            <span class="text-[10px] font-mono text-muted-foreground">{{ lastScanTime }}</span>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-bold text-sm text-foreground leading-tight">{{ lastScannedProduct.name }}</h4>
                            <div class="flex items-center gap-2 text-xs font-mono text-muted-foreground">
                                <span>SKU/Barcode: {{ lastScannedProduct.barcode || lastScannedProduct.sku }}</span>
                                <span>•</span>
                                <span>Stock: {{ lastScannedProduct.stock }}</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-emerald-500/20 flex items-center justify-between font-mono">
                            <div>
                                <span class="text-[10px] text-muted-foreground block font-sans">Price</span>
                                <span class="font-bold text-base text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(pricingMode === 'wholesale' ? lastScannedProduct.wholesalePrice : lastScannedProduct.retailPrice) }}
                                </span>
                            </div>
                            <Badge variant="outline" class="font-bold font-mono text-xs bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">
                                {{ getCartQuantity(lastScannedProduct.id) }} in cart
                            </Badge>
                        </div>
                    </div>

                    <div v-else class="rounded-xl border border-border/60 bg-card p-6 text-center space-y-3">
                        <ScanBarcode class="size-8 mx-auto text-primary/70 animate-pulse" />
                        <h4 class="font-bold text-sm text-foreground">Ready to Scan</h4>
                        <p class="text-xs text-muted-foreground">Point your barcode reader or type a barcode and press Enter to add items immediately.</p>

                        <!-- Powered by M-Pesa Badge -->
                        <div class="pt-3 border-t border-border/50 flex flex-col items-center justify-center gap-1.5">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 dark:bg-emerald-950/30">
                                <img
                                    src="/images/mpesa-logo.svg"
                                    alt="M-PESA"
                                    class="h-5 w-auto object-contain"
                                />
                                <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                                    Powered by M-PESA
                                </span>
                            </div>
                            <span class="text-[10px] text-muted-foreground font-mono">
                                Instant Daraja STK Push & C2B Integrated
                            </span>
                        </div>
                    </div>

                    <!-- Scan Mode Station Guide & Short instructions -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                            <ScanBarcode class="size-4 text-primary" />
                            <span>Continuous Barcode Scanning Active</span>
                        </div>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Product catalog cards are hidden to give maximum space to the customer cart ledger. Continuous scanning will automatically append or increment items.
                        </p>
                        
                        <div class="grid grid-cols-1 gap-2 pt-2 text-xs">
                            <div class="p-2.5 rounded-lg bg-card border border-border/60 flex items-center justify-between">
                                <span class="text-muted-foreground">Scan Barcode / Enter</span>
                                <kbd class="px-2 py-0.5 rounded bg-muted border font-mono text-[10px] font-bold">↵ Enter</kbd>
                            </div>
                            <div class="p-2.5 rounded-lg bg-card border border-border/60 flex items-center justify-between">
                                <span class="text-muted-foreground">Switch to Manual Mode</span>
                                <kbd class="px-2 py-0.5 rounded bg-muted border font-mono text-[10px] font-bold">Alt + M / F3</kbd>
                            </div>
                            <div class="p-2.5 rounded-lg bg-card border border-border/60 flex items-center justify-between">
                                <span class="text-muted-foreground">Quick Checkout</span>
                                <kbd class="px-2 py-0.5 rounded bg-muted border font-mono text-[10px] font-bold">F12</kbd>
                            </div>
                        </div>
                    </div>

                    <!-- Switch to Manual Mode Action Button -->
                    <div class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full text-xs h-9 font-medium gap-2 justify-center"
                            @click="setCashierMode('manual')"
                        >
                            <LayoutGrid class="size-3.5" />
                            <span>Browse Product Cards (Manual Mode)</span>
                        </Button>
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

            <!-- Right: Interactive Shopping Cart & Cashier Engine (Expanded in Scanner Mode) -->
            <div
                id="pos-tour-cart"
                :class="[
                    'flex-col bg-card border-l border-border/80 shadow-lg relative transition-all duration-200',
                    activeMobileTab === 'catalog' ? 'hidden lg:flex' : 'flex flex-1',
                    isSupermarket && cashierMode === 'scanner'
                        ? 'flex-1 w-full max-w-none'
                        : 'w-full max-w-none lg:max-w-md xl:max-w-lg',
                ]"
            >
                <!-- Cart Top Header: Client, Price Tier & Order Actions -->
                <div class="p-2.5 sm:p-3 border-b border-border/70 space-y-2 bg-muted/20">
                    <!-- Marker ③ Customer & Active Cart Badge -->
                    <div v-if="isDemo" class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Active Customer Ticket</span>
                        <button
                            type="button"
                            @click="openGuide(3)"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-primary/10 border border-primary/30 text-primary text-[10px] font-mono font-bold hover:bg-primary/20 transition-all cursor-pointer shadow-2xs"
                            title="Part 3: Customer Profile & Order Ledger (Click to inspect)"
                        >
                            <span class="relative flex size-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span class="relative inline-flex rounded-full size-2 bg-primary"></span>
                            </span>
                            <span>③ Customer & Items</span>
                        </button>
                    </div>

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
                    <div v-if="hasModule('customers')" class="flex items-center justify-between gap-2">
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
                            @click="openNewCustomerModal"
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
                                @click="openHoldDrawer"
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
                    <!-- Scanner Mode Header (Shows column headers for clean horizontal alignment in expanded cart) -->
                    <div
                        v-if="isSupermarket && cashierMode === 'scanner' && items.length > 0"
                        class="hidden md:flex items-center justify-between pb-2 text-[11px] font-mono font-bold text-muted-foreground uppercase tracking-wider px-2 border-b border-border/60 mb-1"
                    >
                        <div class="flex-1 min-w-[200px]">Item Description</div>
                        <div class="w-24 text-right">Unit Price</div>
                        <div class="w-36 text-center">Quantity</div>
                        <div class="w-20 text-center">Discount</div>
                        <div class="w-28 text-right">Subtotal</div>
                        <div class="w-10 text-right"></div>
                    </div>

                    <div
                        v-for="item in items"
                        :key="item.product.id"
                        :class="[
                            'transition-colors group',
                            isSupermarket && cashierMode === 'scanner'
                                ? 'py-2 md:py-2.5 md:flex md:items-center md:justify-between md:gap-4 md:px-2 hover:bg-muted/40 rounded-lg'
                                : 'py-2.5 first:pt-0 last:pb-0 space-y-1.5'
                        ]"
                    >
                        <!-- Scanner Mode Layout (Clean, highly visible horizontal alignment) -->
                        <template v-if="isSupermarket && cashierMode === 'scanner'">
                            <!-- Desktop view (md and up): Perfectly aligned single row -->
                            <div class="hidden md:flex md:items-center md:justify-between md:gap-4 w-full">
                                <!-- 1. Description -->
                                <div class="flex-1 min-w-[200px]">
                                    <h5 class="text-xs sm:text-sm font-bold leading-tight text-foreground truncate" :title="item.product.name">
                                        {{ item.product.name }}
                                    </h5>
                                    <div class="flex items-center gap-2 text-[10px] text-muted-foreground font-mono mt-0.5">
                                        <span v-if="item.product.barcode || item.product.sku">
                                            {{ item.product.barcode || item.product.sku }}
                                        </span>
                                        <span v-if="item.product.dosage" class="text-teal-600 dark:text-teal-400 font-medium">
                                            • {{ item.product.dosage }}
                                        </span>
                                        <span v-if="item.product.serialNumber" class="text-violet-600">
                                            • S/N: {{ item.product.serialNumber }}
                                        </span>
                                    </div>
                                </div>

                                <!-- 2. Unit Price -->
                                <div class="w-24 text-right font-mono text-xs font-semibold text-muted-foreground">
                                    {{ formatCurrency(item.unitPrice) }}
                                </div>

                                <!-- 3. Quantity Stepper -->
                                <div class="w-36 flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-md border border-border/80 bg-background text-foreground hover:bg-muted active:scale-95 transition-transform"
                                        title="Decrease quantity"
                                        @click="updateQuantity(item.product.id, item.quantity - 1)"
                                    >
                                        <Minus class="size-3" />
                                    </button>
                                    <input
                                        :value="item.quantity"
                                        type="number"
                                        min="1"
                                        class="h-7 w-12 rounded-md border border-input bg-background text-center font-mono text-xs font-bold"
                                        @change="(e: any) => updateQuantity(item.product.id, Number(e.target.value))"
                                    />
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-md border border-border/80 bg-background text-foreground hover:bg-muted active:scale-95 transition-transform"
                                        title="Increase quantity"
                                        @click="updateQuantity(item.product.id, item.quantity + 1)"
                                    >
                                        <Plus class="size-3" />
                                    </button>
                                </div>

                                <!-- 4. Discount -->
                                <div class="w-20 flex items-center justify-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'px-2 py-1 rounded text-xs transition-colors',
                                            item.discountPercent > 0
                                                ? 'bg-amber-500/10 text-amber-600 font-bold border border-amber-500/30'
                                                : 'text-muted-foreground hover:text-primary border border-transparent hover:border-border/60'
                                        ]"
                                        title="Set item discount %"
                                        @click="() => {
                                            const disc = prompt('Enter Item Discount %:', String(item.discountPercent));
                                            if (disc !== null) updateItemDiscount(item.product.id, Number(disc));
                                        }"
                                    >
                                        {{ item.discountPercent > 0 ? `-${item.discountPercent}%` : '% Disc' }}
                                    </button>
                                </div>

                                <!-- 5. Subtotal -->
                                <div class="w-28 text-right font-mono font-bold text-sm text-foreground">
                                    {{ formatCurrency(item.quantity * item.unitPrice * (1 - item.discountPercent / 100)) }}
                                </div>

                                <!-- 6. Remove -->
                                <div class="w-10 flex justify-end">
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-rose-600 p-1.5 rounded-md hover:bg-rose-500/10 transition-colors"
                                        title="Remove item"
                                        @click="removeItem(item.product.id)"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Mobile fallback for scanner mode -->
                            <div class="md:hidden space-y-1.5 w-full">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <h5 class="text-xs font-bold leading-tight text-foreground truncate">{{ item.product.name }}</h5>
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
                                <div class="flex items-center justify-between pt-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="flex size-7 items-center justify-center rounded-md border border-border/80 bg-background text-foreground hover:bg-muted active:scale-95 transition-transform"
                                            @click="updateQuantity(item.product.id, item.quantity - 1)"
                                        >
                                            <Minus class="size-3" />
                                        </button>
                                        <input
                                            :value="item.quantity"
                                            type="number"
                                            min="1"
                                            class="h-7 w-12 rounded-md border border-input bg-background text-center font-mono text-xs font-bold"
                                            @change="(e: any) => updateQuantity(item.product.id, Number(e.target.value))"
                                        />
                                        <button
                                            type="button"
                                            class="flex size-7 items-center justify-center rounded-md border border-border/80 bg-background text-foreground hover:bg-muted active:scale-95 transition-transform"
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
                        </template>

                        <!-- Standard / Manual Mode Layout -->
                        <template v-else>
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
                        </template>
                    </div>

                    <div v-if="items.length === 0" class="h-full flex flex-col items-center justify-center py-16 text-center text-muted-foreground text-xs">
                        <ShoppingCart class="size-10 opacity-30 mb-2" />
                        <p class="font-bold text-sm text-foreground">Order Queue Empty</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5 max-w-xs">{{ terms.emptyCartMessage }}</p>
                    </div>
                </div>

                <!-- Live Subtotal, VAT, Discounts, and Total Summary (Part 4 Target) -->
                <div id="pos-tour-checkout" class="relative">
                    <div class="border-t border-border/80 bg-muted/20 p-3 sm:p-3.5 space-y-1.5 sm:space-y-2 text-xs font-mono">
                        <div class="flex justify-between items-center text-muted-foreground">
                            <span>Items Count: {{ itemsCount }} units</span>
                            <!-- Marker ④ Checkout & Pay -->
                            <button
                                v-if="isDemo"
                                type="button"
                                @click="openGuide(4)"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-primary/10 border border-primary/30 text-primary text-[10px] font-mono font-bold hover:bg-primary/20 transition-all cursor-pointer shadow-2xs"
                                title="Part 4: Totals & Multi-Tender Bar (Click to inspect)"
                            >
                                <span class="relative flex size-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                    <span class="relative inline-flex rounded-full size-2 bg-primary"></span>
                                </span>
                                <span>④ Pay & Checkout</span>
                            </button>
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
                                    @click="openDiscountModal"
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
            :phone="currentTenant.phone"
            :address="currentTenant.address"
            :auto-print="autoPrintReceipt"
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
        <Dialog :open="holdNoteModalOpen" spinnerText="Preparing Hold Order..." @update:open="(v) => (holdNoteModalOpen = v)">
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
        <Dialog :open="discountModalOpen" spinnerText="Opening Order Discount..." @update:open="(v) => (discountModalOpen = v)">
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
        <Dialog :open="newCustomerModalOpen" spinnerText="Opening Customer Registration..." @update:open="(v) => (newCustomerModalOpen = v)">
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

        <!-- Quick Add Product Modal (When scanned barcode is not found in catalog) -->
        <Dialog :open="quickAddModalOpen" spinnerText="Opening Quick Product Entry..." @update:open="(v) => (quickAddModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Plus class="size-4 text-primary" />
                        <span>Quick Add New Product</span>
                    </DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Product Name *</Label>
                        <Input v-model="quickAddName" placeholder="e.g. Brookside Whole Milk 500ml" class="text-xs mt-1" autofocus />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <Label class="text-xs">Barcode</Label>
                            <Input v-model="quickAddBarcode" placeholder="Barcode" class="text-xs mt-1 font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs">Category</Label>
                            <select v-model="quickAddCategory" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs font-semibold">
                                <option v-for="cat in categories.filter(c => c !== 'All')" :key="cat" :value="cat">
                                    {{ cat }}
                                </option>
                                <option value="General">General</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <Label class="text-xs">Selling Price (KES) *</Label>
                            <Input v-model="quickAddPrice" type="number" step="0.5" placeholder="0.00" class="text-xs mt-1 font-mono font-bold" />
                        </div>
                        <div>
                            <Label class="text-xs">Cost Price (KES)</Label>
                            <Input v-model="quickAddCost" type="number" step="0.5" placeholder="0.00" class="text-xs mt-1 font-mono" />
                        </div>
                    </div>
                    <div>
                        <Label class="text-xs">Initial Stock Quantity</Label>
                        <Input v-model.number="quickAddStock" type="number" placeholder="20" class="text-xs mt-1 font-mono" />
                    </div>
                </div>
                <DialogFooter class="gap-2 flex-row justify-end">
                    <Button variant="ghost" size="sm" @click="quickAddModalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleSaveQuickProduct">Save & Add to Cart</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Mobile Camera Barcode Scanner Modal -->
        <CameraScannerModal
            :open="cameraScannerOpen"
            @update:open="(v) => (cameraScannerOpen = v)"
            @scan="handleCameraScan"
        />

        <!-- Fullscreen Interactive Page Spotlight Guide & Section Walkthrough -->
        <PosGuideOverlay
            v-if="isDemo"
            :open="guideOverlayOpen"
            :current-step="guideCurrentStep"
            @update:open="(v) => (guideOverlayOpen = v)"
            @update:current-step="(s) => (guideCurrentStep = s)"
            @action="handleGuideAction"
            @switch-tab="handleGuideTabSwitch"
        />
    </PosLayout>
</template>
