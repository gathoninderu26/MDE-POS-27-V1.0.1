import { computed, reactive } from 'vue';
import { generateMpesaCode } from './useCurrency';

export interface ProductItem {
    id: string;
    sku: string;
    barcode: string;
    name: string;
    category: string;
    brand: string;
    unit: string;
    costPrice: number;
    retailPrice: number;
    wholesalePrice: number;
    stock: number;
    image?: string;
    taxRate: number; // e.g. 16 for 16% VAT
    minStock?: number;
    description?: string;
    
    // Vertical-specific properties
    genericName?: string;
    dosage?: string;
    batchNumber?: string;
    expiryDate?: string;
    isPrescriptionOnly?: boolean;
    specifications?: string;
    dimensionUnit?: string;
    modifiers?: string;
    station?: string;
    serialNumber?: string;
    imeiNumber?: string;
    warrantyMonths?: number;
    pumpNumber?: number;
    nozzleNumber?: number;
    meterStart?: number;
    meterEnd?: number;
}

export interface CartItem {
    product: ProductItem;
    quantity: number;
    priceType: 'retail' | 'wholesale';
    unitPrice: number;
    discountPercent: number;
    customNote?: string;
}

export interface Customer {
    id: string;
    name: string;
    phone: string;
    email: string;
    group: 'Retail' | 'Wholesale' | 'VIP' | 'Corporate' | 'Patient' | 'Contractor';
    creditLimit: number;
    currentBalance: number;
    status: 'Active' | 'Suspended';
    insuranceProvider?: string;
    insurancePolicyNo?: string;
    siteAddress?: string;
}

export interface PaymentEntry {
    method: 'Cash' | 'M-Pesa' | 'Card' | 'Bank' | 'Credit' | string;
    amount: number;
    reference?: string;
    phone?: string;
    payerName?: string;
    confirmedAt?: string;
}

export interface HeldSale {
    id: string;
    heldAt: string;
    customer: Customer | null;
    items: CartItem[];
    note?: string;
    totalAmount: number;
    tableNumber?: string;
    guestCount?: number;
    rxNumber?: string;
    siteAddress?: string;
}

export interface CompletedSale {
    receiptNumber: string;
    date: string;
    tenantName: string;
    branchName: string;
    cashierName: string;
    customer: Customer | null;
    items: CartItem[];
    subtotal: number;
    discountAmount: number;
    taxAmount: number;
    total: number;
    payments: PaymentEntry[];
    changeGiven: number;
    mpesaCode?: string;
    mpesaPayerName?: string;
    status: 'Completed' | 'Refunded' | 'Held' | 'Void';
    
    // Vertical metadata for receipts
    rxNumber?: string;
    doctorName?: string;
    deliverySite?: string;
    vehicleReg?: string;
    driverName?: string;
    tableNumber?: string;
    guestCount?: number;
    serverName?: string;
}

const defaultCustomer: Customer = {
    id: 'walk-in',
    name: 'Walk-in Customer',
    phone: '+254 700 000 000',
    email: 'walkin@pos.local',
    group: 'Retail',
    creditLimit: 0,
    currentBalance: 0,
    status: 'Active',
};

const state = reactive<{
    items: CartItem[];
    customer: Customer;
    cartDiscountPercent: number;
    heldSales: HeldSale[];
    recentSales: CompletedSale[];
    pricingMode: 'retail' | 'wholesale';
    activeReceipt: CompletedSale | null;
    
    // Active session vertical metadata
    activeRxNumber: string;
    activeDoctorName: string;
    activeDeliverySite: string;
    activeVehicleReg: string;
    activeTableNumber: string;
    activeGuestCount: number;
}>({
    items: [],
    customer: defaultCustomer,
    cartDiscountPercent: 0,
    heldSales: [],
    recentSales: [],
    pricingMode: 'retail',
    activeReceipt: null,
    activeRxNumber: '',
    activeDoctorName: '',
    activeDeliverySite: '',
    activeVehicleReg: '',
    activeTableNumber: '',
    activeGuestCount: 2,
});

export function useCart() {
    const itemsCount = computed(() => {
        return state.items.reduce((sum, item) => sum + item.quantity, 0);
    });

    const subtotal = computed(() => {
        return state.items.reduce((sum, item) => {
            const lineGross = item.unitPrice * item.quantity;
            const lineDiscount = lineGross * (item.discountPercent / 100);
            return sum + (lineGross - lineDiscount);
        }, 0);
    });

    const discountAmount = computed(() => {
        return subtotal.value * (state.cartDiscountPercent / 100);
    });

    const taxableAmount = computed(() => {
        return Math.max(0, subtotal.value - discountAmount.value);
    });

    // 16% standard VAT Kenya (Zero rated for medications with 0% tax)
    const taxAmount = computed(() => {
        return state.items.reduce((sum, item) => {
            const lineGross = item.unitPrice * item.quantity;
            const lineDiscount = lineGross * (item.discountPercent / 100);
            const lineNet = lineGross - lineDiscount;
            const itemTaxRate = (item.product.taxRate ?? 16) / 100;
            return sum + (lineNet * itemTaxRate);
        }, 0);
    });

    const total = computed(() => {
        return taxableAmount.value + taxAmount.value;
    });

    const addItem = (product: ProductItem, qty: number = 1) => {
        const existing = state.items.find((i) => i.product.id === product.id);
        const price = state.pricingMode === 'wholesale' ? product.wholesalePrice : product.retailPrice;

        if (existing) {
            existing.quantity += qty;
        } else {
            state.items.push({
                product,
                quantity: qty,
                priceType: state.pricingMode,
                unitPrice: price,
                discountPercent: 0,
            });
        }
    };

    const updateQuantity = (productId: string, quantity: number) => {
        const item = state.items.find((i) => i.product.id === productId);
        if (item) {
            if (quantity <= 0) {
                removeItem(productId);
            } else {
                item.quantity = quantity;
            }
        }
    };

    const updateItemDiscount = (productId: string, discount: number) => {
        const item = state.items.find((i) => i.product.id === productId);
        if (item) {
            item.discountPercent = Math.max(0, Math.min(100, discount));
        }
    };

    const updateItemPrice = (productId: string, price: number) => {
        const item = state.items.find((i) => i.product.id === productId);
        if (item) {
            item.unitPrice = Math.max(0, price);
        }
    };

    const removeItem = (productId: string) => {
        const index = state.items.findIndex((i) => i.product.id === productId);
        if (index !== -1) {
            state.items.splice(index, 1);
        }
    };

    const clearCart = () => {
        state.items = [];
        state.cartDiscountPercent = 0;
        state.customer = defaultCustomer;
        state.activeRxNumber = '';
        state.activeDoctorName = '';
        state.activeDeliverySite = '';
        state.activeVehicleReg = '';
        state.activeTableNumber = '';
    };

    const setPricingMode = (mode: 'retail' | 'wholesale') => {
        state.pricingMode = mode;
        state.items.forEach((item) => {
            item.priceType = mode;
            item.unitPrice = mode === 'wholesale' ? item.product.wholesalePrice : item.product.retailPrice;
        });
    };

    const setCustomer = (cust: Customer) => {
        state.customer = cust;
        if (cust.group === 'Wholesale') {
            setPricingMode('wholesale');
        }
        if (cust.siteAddress) {
            state.activeDeliverySite = cust.siteAddress;
        }
    };

    const holdSale = (note?: string) => {
        if (state.items.length === 0) return null;
        const held: HeldSale = {
            id: `HOLD-${Date.now().toString().slice(-4)}`,
            heldAt: new Date().toISOString(),
            customer: state.customer.id === 'walk-in' ? null : state.customer,
            items: JSON.parse(JSON.stringify(state.items)),
            note,
            totalAmount: total.value,
            tableNumber: state.activeTableNumber || undefined,
            guestCount: state.activeGuestCount,
            rxNumber: state.activeRxNumber || undefined,
            siteAddress: state.activeDeliverySite || undefined,
        };
        state.heldSales.unshift(held);
        clearCart();
        return held;
    };

    const resumeSale = (heldId: string) => {
        const index = state.heldSales.findIndex((h) => h.id === heldId);
        if (index !== -1) {
            const held = state.heldSales[index];
            state.items = held.items;
            state.customer = held.customer || defaultCustomer;
            state.activeTableNumber = held.tableNumber || '';
            state.activeGuestCount = held.guestCount || 2;
            state.activeRxNumber = held.rxNumber || '';
            state.activeDeliverySite = held.siteAddress || '';
            state.heldSales.splice(index, 1);
        }
    };

    const completeSale = (
        tenantName: string,
        branchName: string,
        cashierName: string,
        payments: PaymentEntry[],
        tenderedAmount: number,
        extraMeta?: {
            rxNumber?: string;
            doctorName?: string;
            deliverySite?: string;
            vehicleReg?: string;
            tableNumber?: string;
            guestCount?: number;
            serverName?: string;
        },
    ): CompletedSale => {
        const receiptNo = `REC-${new Date().getFullYear()}-${Math.floor(100000 + Math.random() * 900000)}`;
        const mpesaPayment = payments.find((p) => p.method === 'M-Pesa');
        const mpesaCode = mpesaPayment?.reference || (mpesaPayment ? generateMpesaCode() : undefined);
        const changeGiven = Math.max(0, tenderedAmount - total.value);

        const sale: CompletedSale = {
            receiptNumber: receiptNo,
            date: new Date().toISOString(),
            tenantName,
            branchName,
            cashierName,
            customer: state.customer,
            items: JSON.parse(JSON.stringify(state.items)),
            subtotal: subtotal.value,
            discountAmount: discountAmount.value,
            taxAmount: taxAmount.value,
            total: total.value,
            payments,
            changeGiven,
            mpesaCode,
            mpesaPayerName: mpesaPayment?.payerName,
            status: 'Completed',
            rxNumber: extraMeta?.rxNumber || state.activeRxNumber || undefined,
            doctorName: extraMeta?.doctorName || state.activeDoctorName || undefined,
            deliverySite: extraMeta?.deliverySite || state.activeDeliverySite || undefined,
            vehicleReg: extraMeta?.vehicleReg || state.activeVehicleReg || undefined,
            tableNumber: extraMeta?.tableNumber || state.activeTableNumber || undefined,
            guestCount: extraMeta?.guestCount || state.activeGuestCount,
            serverName: extraMeta?.serverName || cashierName,
        };

        state.recentSales.unshift(sale);
        state.activeReceipt = sale;
        clearCart();
        return sale;
    };

    return {
        items: computed(() => state.items),
        customer: computed(() => state.customer),
        cartDiscountPercent: computed({
            get: () => state.cartDiscountPercent,
            set: (v) => (state.cartDiscountPercent = Math.max(0, Math.min(100, v))),
        }),
        pricingMode: computed(() => state.pricingMode),
        heldSales: computed(() => state.heldSales),
        recentSales: computed(() => state.recentSales),
        activeReceipt: computed({
            get: () => state.activeReceipt,
            set: (v) => (state.activeReceipt = v),
        }),
        activeRxNumber: computed({
            get: () => state.activeRxNumber,
            set: (v) => (state.activeRxNumber = v),
        }),
        activeDoctorName: computed({
            get: () => state.activeDoctorName,
            set: (v) => (state.activeDoctorName = v),
        }),
        activeDeliverySite: computed({
            get: () => state.activeDeliverySite,
            set: (v) => (state.activeDeliverySite = v),
        }),
        activeVehicleReg: computed({
            get: () => state.activeVehicleReg,
            set: (v) => (state.activeVehicleReg = v),
        }),
        activeTableNumber: computed({
            get: () => state.activeTableNumber,
            set: (v) => (state.activeTableNumber = v),
        }),
        activeGuestCount: computed({
            get: () => state.activeGuestCount,
            set: (v) => (state.activeGuestCount = v),
        }),
        itemsCount,
        subtotal,
        discountAmount,
        taxableAmount,
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
    };
}
