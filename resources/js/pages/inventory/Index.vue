<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import StatCard from '@/components/ui/StatCard.vue';
import BarcodeLabelModal, { type ProductLabelData } from '@/components/inventory/BarcodeLabelModal.vue';
import ShelfPriceTagModal, { type ShelfPriceTagData } from '@/components/inventory/ShelfPriceTagModal.vue';
import StockGuideOverlay from '@/components/inventory/StockGuideOverlay.vue';
import { useBarcodeScanner } from '@/composables/useBarcodeScanner';
import { useBranch } from '@/composables/useBranch';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { useFeatures } from '@/composables/useFeatures';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeftRight,
    Boxes,
    Building2,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Clock,
    Compass,
    Download,
    Eye,
    FileSpreadsheet,
    History,
    Package,
    Plus,
    Printer,
    RotateCcw,
    Scale,
    ScanBarcode,
    Search,
    SlidersHorizontal,
    Sparkles,
    Tag,
    TrendingDown,
    TrendingUp,
    Truck,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    products?: any[];
    branches?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory & Stock Management', href: '/inventory' },
];

const { currentTenant } = useTenant();
const { isMultiBranch, hasFeature, isDemo } = useFeatures();
const { currentBranch, branchesForCurrentTenant } = useBranch();
const { success, warning, error, info } = useToast();

const defaultBranchName = computed(() => props.branches?.[0]?.name || currentBranch.value?.name || 'Main HQ');

const activeTab = ref<'overview' | 'audit' | 'adjustments' | 'transfers'>('overview');
const stockStatusFilter = ref<'all' | 'in_stock' | 'low_stock' | 'out_of_stock'>('all');

// Interactive Stock Overview Guide & Section Walkthrough
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openGuide = (step?: number) => {
    if (step !== undefined) {
        guideCurrentStep.value = step;
    } else {
        // Automatically start at the most relevant section for current active tab
        if (activeTab.value === 'audit') {
            guideCurrentStep.value = 13; // Live Stock Take & Audit terminal
        } else if (activeTab.value === 'adjustments') {
            guideCurrentStep.value = 18; // Shrinkage & adjustments ledger
        } else if (activeTab.value === 'transfers') {
            guideCurrentStep.value = 21; // Logistics & transfers
        } else {
            guideCurrentStep.value = 0; // Master overview
        }
    }
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'start-walkthrough') {
        guideCurrentStep.value = 1;
    } else if (actionType === 'filter-low-stock') {
        activeTab.value = 'overview';
        stockStatusFilter.value = 'low_stock';
    } else if (actionType === 'filter-all') {
        activeTab.value = 'overview';
        stockStatusFilter.value = 'all';
    } else if (actionType === 'focus-search') {
        activeTab.value = 'overview';
        nextTick(() => {
            const input = document.querySelector('#stock-tour-search input') as HTMLInputElement | null;
            input?.focus();
        });
    } else if (actionType === 'open-barcode-label') {
        const first = stockItems.value[0];
        if (first) openBarcodeLabelModal(first);
    } else if (actionType === 'open-shelf-tag') {
        const first = stockItems.value[0];
        if (first) openShelfPriceTagModal(first);
    } else if (actionType === 'open-stock-adjust') {
        const first = stockItems.value[0];
        openAdjustModal(first);
    } else if (actionType === 'start-stock-take') {
        startStockTake();
    } else if (actionType === 'focus-audit-scan') {
        activeTab.value = 'audit';
        nextTick(() => {
            auditInputRef.value?.focus();
        });
    } else if (actionType === 'reconcile-discrepancies') {
        handleReconcileStock();
    } else if (actionType === 'view-adjustments') {
        activeTab.value = 'adjustments';
    } else if (actionType === 'view-transfers') {
        activeTab.value = 'transfers';
    } else if (actionType === 'open-transfer-modal') {
        activeTab.value = 'transfers';
        transferModalOpen.value = true;
    } else if (actionType === 'export-csv') {
        exportStockCsv();
    }
};

const handleGuideTabSwitch = (tab: 'overview' | 'audit' | 'adjustments' | 'transfers') => {
    if (tab === 'audit' && !isAuditing.value) {
        startStockTake();
    } else {
        activeTab.value = tab;
    }
};

onMounted(() => {
    // Auto-prompt guide on first visit to Stock Management in this session
    if (!sessionStorage.getItem('mde_stock_guide_prompted')) {
        sessionStorage.setItem('mde_stock_guide_prompted', 'true');
        setTimeout(() => {
            openGuide(0);
        }, 600);
    }
});

// Default items for mock/fallback
const defaultStockItems = [
    { id: '1', product: 'Brookside Whole Milk 500ml', sku: 'DAI-001', barcode: '616110000101', category: 'Dairy', branch: 'Westlands Mega Store', available: 48, cost: 65, retail: 80, reorder: 15, unit: 'pcs', status: 'In Stock' },
    { id: '2', product: 'Broadways White Bread 400g', sku: 'BAK-002', barcode: '616110000202', category: 'Bakery', branch: 'Westlands Mega Store', available: 35, cost: 55, retail: 70, reorder: 10, unit: 'pcs', status: 'In Stock' },
    { id: '3', product: 'Mumias Pure Sugar 1kg', sku: 'GRO-003', barcode: '616110000303', category: 'Groceries', branch: 'Westlands Mega Store', available: 80, cost: 155, retail: 185, reorder: 20, unit: 'kg', status: 'In Stock' },
    { id: '4', product: 'Pembe Maize Flour 2kg', sku: 'GRO-004', barcode: '616110000404', category: 'Groceries', branch: 'Westlands Mega Store', available: 120, cost: 140, retail: 175, reorder: 25, unit: 'bales', status: 'In Stock' },
    { id: '5', product: 'Fresh Fri Vegetable Oil 2L', sku: 'OIL-006', barcode: '616110000606', category: 'Oils & Fats', branch: 'Westlands Mega Store', available: 24, cost: 580, retail: 680, reorder: 8, unit: 'pcs', status: 'In Stock' },
    { id: '6', product: 'Ketepa Pride Tea Leaves 500g', sku: 'BEV-007', barcode: '616110000707', category: 'Beverages', branch: 'Westlands Mega Store', available: 4, cost: 240, retail: 295, reorder: 10, unit: 'pcs', status: 'Low Stock' },
    { id: '7', product: 'Kiwi Shoe Polish Black 100ml', sku: 'HOU-015', barcode: '616110000808', category: 'Household Care', branch: 'Westlands Mega Store', available: 0, cost: 95, retail: 130, reorder: 5, unit: 'pcs', status: 'Out of Stock' },
];

const stockItems = computed(() => {
    if (props.products && props.products.length > 0) {
        return props.products.map((p) => {
            const qty = Number(p.stock_quantity ?? 0);
            const cost = Number(p.cost_price ?? 0);
            const retail = Number(p.selling_price ?? 0);
            const reorder = Number(p.reorder_level ?? 10);
            return {
                id: String(p.id),
                product: p.name,
                sku: p.sku || 'SKU-' + p.id,
                barcode: p.barcode || `616${String(p.id).padStart(9, '0')}`,
                category: p.category || 'General',
                branch: p.branch_name || defaultBranchName.value,
                available: qty,
                cost: cost,
                retail: retail,
                reorder: reorder,
                unit: p.unit || 'pcs',
                value: qty * cost,
                retailValue: qty * retail,
                status: qty <= 0 ? 'Out of Stock' : qty <= reorder ? 'Low Stock' : 'In Stock',
            };
        });
    }
    if (isDemo.value) {
        return defaultStockItems.map((i) => ({
            ...i,
            value: i.available * i.cost,
            retailValue: i.available * i.retail,
        }));
    }
    return [];
});

const filteredStockItems = computed(() => {
    if (stockStatusFilter.value === 'low_stock') {
        return stockItems.value.filter((i) => i.status === 'Low Stock');
    }
    if (stockStatusFilter.value === 'out_of_stock') {
        return stockItems.value.filter((i) => i.status === 'Out of Stock');
    }
    if (stockStatusFilter.value === 'in_stock') {
        return stockItems.value.filter((i) => i.status === 'In Stock');
    }
    return stockItems.value;
});

// Valuation KPIs
const totalCostValuation = computed(() => stockItems.value.reduce((acc, i) => acc + i.value, 0));
const totalRetailPotential = computed(() => stockItems.value.reduce((acc, i) => acc + i.retailValue, 0));
const projectedMargin = computed(() => {
    if (totalRetailPotential.value <= 0) return 0;
    return (((totalRetailPotential.value - totalCostValuation.value) / totalRetailPotential.value) * 100).toFixed(1);
});
const totalTrackedSkus = computed(() => stockItems.value.length);
const lowStockCount = computed(() => stockItems.value.filter((i) => i.status === 'Low Stock').length);
const outOfStockCount = computed(() => stockItems.value.filter((i) => i.status === 'Out of Stock').length);

const overviewColumns = computed(() => [
    { key: 'product', label: 'Product / Barcode' },
    ...(isMultiBranch.value ? [{ key: 'branch', label: 'Branch Location' }] : []),
    { key: 'available', label: 'Stock Level', align: 'center' as const },
    { key: 'cost', label: 'Unit Cost', align: 'right' as const },
    { key: 'value', label: 'Valuation (Cost)', align: 'right' as const },
    { key: 'status', label: 'Stock Health', align: 'center' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
]);

// ============================================================
// 1. PHYSICAL STOCK TAKE & BARCODE SHELF AUDIT
// ============================================================
interface AuditRow {
    productId: string;
    productName: string;
    sku: string;
    barcode: string;
    systemStock: number;
    countedStock: number;
    variance: number;
    unitCost: number;
    varianceCost: number;
}

const auditScanInput = ref('');
const auditInputRef = ref<HTMLInputElement | null>(null);
const auditRows = ref<AuditRow[]>([]);
const isAuditing = ref(false);

// Audit Table Search & Filter State
const auditSearchQuery = ref('');
const auditStatusFilter = ref<'all' | 'discrepancy' | 'match' | 'surplus' | 'shortage'>('all');
const auditCurrentPage = ref(1);
const auditPageSize = ref(10);
const auditPageSizeOptions = [5, 10, 20, 50, 100];

const filteredAuditRows = computed(() => {
    let list = auditRows.value;
    const q = auditSearchQuery.value.toLowerCase().trim();
    if (q) {
        list = list.filter(
            (r) =>
                r.productName.toLowerCase().includes(q) ||
                r.sku.toLowerCase().includes(q) ||
                r.barcode.toLowerCase().includes(q),
        );
    }
    if (auditStatusFilter.value === 'discrepancy') {
        list = list.filter((r) => r.variance !== 0);
    } else if (auditStatusFilter.value === 'match') {
        list = list.filter((r) => r.variance === 0);
    } else if (auditStatusFilter.value === 'surplus') {
        list = list.filter((r) => r.variance > 0);
    } else if (auditStatusFilter.value === 'shortage') {
        list = list.filter((r) => r.variance < 0);
    }
    return list;
});

const auditTotalPages = computed(() => {
    return Math.ceil(filteredAuditRows.value.length / auditPageSize.value) || 1;
});

const paginatedAuditRows = computed(() => {
    const start = (auditCurrentPage.value - 1) * auditPageSize.value;
    return filteredAuditRows.value.slice(start, start + auditPageSize.value);
});

const goToAuditPage = (page: number) => {
    if (page >= 1 && page <= auditTotalPages.value) {
        auditCurrentPage.value = page;
    }
};

const auditVisiblePages = computed(() => {
    const total = auditTotalPages.value;
    const curr = auditCurrentPage.value;
    if (total <= 5) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const pages: (number | string)[] = [];
    if (curr <= 3) {
        pages.push(1, 2, 3, 4, '...', total);
    } else if (curr >= total - 2) {
        pages.push(1, '...', total - 3, total - 2, total - 1, total);
    } else {
        pages.push(1, '...', curr - 1, curr, curr + 1, '...', total);
    }
    return pages;
});

watch([auditSearchQuery, auditStatusFilter, auditPageSize], () => {
    auditCurrentPage.value = 1;
});

const startStockTake = () => {
    isAuditing.value = true;
    activeTab.value = 'audit';
    auditSearchQuery.value = '';
    auditStatusFilter.value = 'all';
    auditCurrentPage.value = 1;
    // Initialize audit list with current system stock
    auditRows.value = stockItems.value.map((i) => ({
        productId: i.id,
        productName: i.product,
        sku: i.sku,
        barcode: i.barcode,
        systemStock: i.available,
        countedStock: i.available, // Start pre-filled or 0
        variance: 0,
        unitCost: i.cost,
        varianceCost: 0,
    }));
    nextTick(() => {
        auditInputRef.value?.focus();
    });
    info('Stock Audit Mode Active', 'Scan item barcodes or enter counts directly. Scanners will increment counts automatically.');
};

const handleAuditBarcodeScan = (scannedCode: string) => {
    if (!scannedCode) return;
    const code = scannedCode.trim().toLowerCase();

    const matched = auditRows.value.find(
        (r) => r.barcode.toLowerCase() === code || r.sku.toLowerCase() === code,
    );

    if (matched) {
        matched.countedStock++;
        matched.variance = matched.countedStock - matched.systemStock;
        matched.varianceCost = matched.variance * matched.unitCost;
        playScanBeep('success');
        success(`Counted: ${matched.productName}`, `Count increased to ${matched.countedStock} units (Variance: ${matched.variance >= 0 ? '+' : ''}${matched.variance})`);

        // If the item matches, ensure it is brought into view on the current page
        const idx = filteredAuditRows.value.findIndex((r) => r.productId === matched.productId);
        if (idx !== -1) {
            auditCurrentPage.value = Math.floor(idx / auditPageSize.value) + 1;
        }
    } else {
        playScanBeep('error');
        error('Unrecognized Item', `No tracked stock item matches barcode / SKU: ${scannedCode}`);
    }

    auditScanInput.value = '';
    nextTick(() => {
        auditInputRef.value?.focus();
    });
};

const updateCountDirectly = (row: AuditRow, newCount: number) => {
    row.countedStock = Math.max(0, newCount);
    row.variance = row.countedStock - row.systemStock;
    row.varianceCost = row.variance * row.unitCost;
};

const totalVarianceUnits = computed(() => auditRows.value.reduce((acc, r) => acc + r.variance, 0));
const totalVarianceValuation = computed(() => auditRows.value.reduce((acc, r) => acc + r.varianceCost, 0));

const handleReconcileStock = () => {
    const changesCount = auditRows.value.filter((r) => r.variance !== 0).length;
    if (changesCount === 0) {
        info('No Discrepancies', 'Physical counts match system inventory 100%.');
        return;
    }

    // Apply adjustments locally
    auditRows.value.forEach((r) => {
        if (r.variance !== 0) {
            adjustments.value.unshift({
                id: `ADJ-${Math.floor(110 + Math.random() * 890)}`,
                date: new Date().toISOString().replace('T', ' ').substring(0, 16),
                product: r.productName,
                change: r.variance,
                reason: 'Physical Stock Take Audit Reconciliation',
                by: 'Inventory Auditor',
            });
        }
    });

    success(
        'Stock Count Reconciled',
        `Adjusted ${changesCount} inventory lines. Total variance: ${totalVarianceUnits.value >= 0 ? '+' : ''}${totalVarianceUnits.value} units (${formatCurrency(totalVarianceValuation.value)}).`
    );
};

// Global Barcode Scanner Hook for Inventory Stock Take
const { playScanBeep } = useBarcodeScanner({
    onScan: (code) => {
        if (activeTab.value === 'audit') {
            handleAuditBarcodeScan(code);
        }
    },
});

// ============================================================
// 2. STOCK ADJUSTMENT MODAL & ACTIONS
// ============================================================
const adjustModalOpen = ref(false);
const adjustingProduct = ref<any>(null);
const adjustForm = ref({
    productId: '',
    productName: '',
    currentStock: 0,
    changeQty: 0,
    type: 'decrease' as 'increase' | 'decrease',
    reason: 'Damaged in Store / Breakage',
    notes: '',
});

const adjustmentReasons = [
    'Damaged in Store / Breakage',
    'Expired / Spoilage',
    'Theft / Unexplained Shrinkage',
    'Audit Discrepancy (Physical Count Difference)',
    'Internal Use / Store Sampling',
    'Vendor Restock / Inward Surplus',
    'Return to Vendor / Faulty Batch',
];

const openAdjustModal = (item?: any) => {
    if (item) {
        adjustingProduct.value = item;
        adjustForm.value = {
            productId: item.id,
            productName: item.product,
            currentStock: item.available,
            changeQty: 1,
            type: 'decrease',
            reason: 'Damaged in Store / Breakage',
            notes: '',
        };
    } else {
        const first = stockItems.value[0];
        adjustingProduct.value = first;
        adjustForm.value = {
            productId: first?.id || '1',
            productName: first?.product || '',
            currentStock: first?.available || 0,
            changeQty: 1,
            type: 'decrease',
            reason: 'Damaged in Store / Breakage',
            notes: '',
        };
    }
    adjustModalOpen.value = true;
};

const resultingStock = computed(() => {
    const shift = adjustForm.value.type === 'increase' ? adjustForm.value.changeQty : -adjustForm.value.changeQty;
    return Math.max(0, adjustForm.value.currentStock + shift);
});

const handleSaveAdjustment = () => {
    const shift = adjustForm.value.type === 'increase' ? adjustForm.value.changeQty : -adjustForm.value.changeQty;
    if (shift === 0) {
        warning('Zero Shift', 'Please enter a quantity change greater than 0.');
        return;
    }

    // Try backend request if productId is numeric
    if (props.products && props.products.length > 0) {
        router.post(
            '/inventory/adjust',
            {
                product_id: adjustForm.value.productId,
                change: shift,
                reason: adjustForm.value.reason,
                notes: adjustForm.value.notes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    adjustModalOpen.value = false;
                    success('Stock Adjusted', `Successfully updated stock for ${adjustForm.value.productName}.`);
                },
                onError: (errs) => {
                    error('Adjustment Failed', Object.values(errs)[0] as string);
                },
            },
        );
    } else {
        // Fallback local update
        adjustments.value.unshift({
            id: `ADJ-${Math.floor(200 + Math.random() * 800)}`,
            date: new Date().toISOString().replace('T', ' ').substring(0, 16),
            product: adjustForm.value.productName,
            change: shift,
            reason: adjustForm.value.reason,
            by: 'Cashier / Manager',
        });
        if (adjustingProduct.value) {
            adjustingProduct.value.available = resultingStock.value;
        }
        adjustModalOpen.value = false;
        success('Stock Adjusted', `Updated ${adjustForm.value.productName} to ${resultingStock.value} units.`);
    }
};

// ============================================================
// 3. BARCODE LABEL & SHELF PRICE TAG PRINT MODALS
// ============================================================
const labelModalOpen = ref(false);
const labelProduct = ref<ProductLabelData | null>(null);

const openBarcodeLabelModal = (item: any) => {
    labelProduct.value = {
        id: item.id,
        name: item.product,
        sku: item.sku,
        barcode: item.barcode,
        price: item.retail,
    };
    labelModalOpen.value = true;
};

// Shelf Price Tag (No Barcode) Modal
const shelfTagModalOpen = ref(false);
const shelfTagProduct = ref<ShelfPriceTagData | null>(null);

const openShelfPriceTagModal = (item: any) => {
    shelfTagProduct.value = {
        id: item.id,
        name: item.product,
        sku: item.sku,
        price: item.retail,
        category: item.category || 'General',
        unit: item.unit || 'pcs',
        branch: item.branch,
    };
    shelfTagModalOpen.value = true;
};

// ============================================================
// 4. BRANCH STOCK TRANSFERS
// ============================================================
const transfers = ref(isDemo.value ? [
    { id: 'TRF-4091', date: '2026-08-23 08:30', from: 'Industrial Area Central Warehouse', to: 'Westlands Mega Store', product: 'Pembe Maize Flour 2kg', qty: 50, status: 'Approved' },
    { id: 'TRF-4090', date: '2026-08-22 14:15', from: 'CBD City Square Branch', to: 'Kilimani Outlet', product: 'Fresh Fri Vegetable Oil 2L', qty: 12, status: 'Completed' },
    { id: 'TRF-4089', date: '2026-08-21 11:00', from: 'Westlands Mega Store', to: 'CBD City Square Branch', product: 'Brookside Whole Milk 500ml', qty: 30, status: 'Completed' },
] : []);

const transferColumns = [
    { key: 'id', label: 'Transfer Ref' },
    { key: 'from', label: 'Origin Branch' },
    { key: 'to', label: 'Destination' },
    { key: 'product', label: 'Item & Quantity' },
    { key: 'status', label: 'Status', align: 'center' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
];

const transferModalOpen = ref(false);
const newTransfer = ref({
    from: defaultBranchName.value,
    to: '',
    product: '',
    qty: 1,
    note: '',
});

const handleCreateTransfer = () => {
    transfers.value.unshift({
        id: `TRF-${Math.floor(4100 + Math.random() * 900)}`,
        date: new Date().toISOString().replace('T', ' ').substring(0, 16),
        from: newTransfer.value.from,
        to: newTransfer.value.to,
        product: newTransfer.value.product,
        qty: newTransfer.value.qty,
        status: 'Pending',
    });
    transferModalOpen.value = false;
    success('Transfer Dispatched', `Transfer request sent to ${newTransfer.value.to}`);
};

const handleCompleteTransfer = (trf: any) => {
    trf.status = 'Completed';
    success('Transfer Received', `Goods received and stock credited at destination.`);
};

// ============================================================
// 5. STOCK ADJUSTMENTS & SHRINKAGE LOG
// ============================================================
const adjustments = ref(isDemo.value ? [
    { id: 'ADJ-102', date: '2026-08-23 09:10', product: 'Ketepa Pride Tea Leaves 500g', change: -2, reason: 'Damaged in Store / Breakage', by: 'David M.' },
    { id: 'ADJ-101', date: '2026-08-22 16:45', product: 'Brookside Whole Milk 500ml', change: -3, reason: 'Expired / Spoilage', by: 'Wamtesh K.' },
    { id: 'ADJ-100', date: '2026-08-20 10:20', product: 'Mumias Pure Sugar 1kg', change: +10, reason: 'Physical Stock Take Audit Reconciliation', by: 'Admin' },
] : []);

const adjustmentColumns = [
    { key: 'id', label: 'Adjustment Ref' },
    { key: 'date', label: 'Timestamp' },
    { key: 'product', label: 'Product Item' },
    { key: 'change', label: 'Stock Shift', align: 'center' as const },
    { key: 'reason', label: 'Reason Code' },
    { key: 'by', label: 'Recorded By' },
];

// ============================================================
// 6. CSV EXPORT UTILITY
// ============================================================
const exportStockCsv = () => {
    const headers = ['Product Name', 'SKU', 'Barcode', 'Branch Location', 'Available Units', 'Unit Cost (KES)', 'Selling Price (KES)', 'Valuation (KES)', 'Reorder Level', 'Stock Health'];
    const rows = stockItems.value.map((i) => [
        `"${i.product.replace(/"/g, '""')}"`,
        `"${i.sku}"`,
        `"${i.barcode}"`,
        `"${i.branch}"`,
        i.available,
        i.cost,
        i.retail,
        i.value,
        i.reorder,
        `"${i.status}"`,
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `inventory_report_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    success('CSV Exported', 'Stock inventory report downloaded successfully.');
};
</script>

<template>
    <Head title="Inventory & Stock Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground flex items-center gap-2">
                        <Boxes class="size-6 text-primary" />
                        Inventory & Multi-Branch Stock Control
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Enterprise stock auditing, barcode scanning, shrinkage reconciliation, and thermal label generation.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        id="stock-tour-guide-btn"
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-primary/40 bg-primary/10 text-primary hover:bg-primary/20 font-semibold shadow-xs"
                        @click="openGuide()"
                    >
                        <Compass class="size-4 animate-spin-slow text-primary" />
                        <span>Page Guide</span>
                    </Button>

                    <Button
                        id="stock-tour-btn-export"
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-border shadow-xs"
                        @click="exportStockCsv"
                    >
                        <Download class="size-3.5 text-muted-foreground" />
                        Export CSV
                    </Button>

                    <Button
                        id="stock-tour-btn-audit"
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/20 font-semibold shadow-xs"
                        @click="startStockTake"
                    >
                        <ScanBarcode class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        Barcode Stock Take
                    </Button>

                    <Button
                        id="stock-tour-btn-adjust"
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-border shadow-xs"
                        @click="openAdjustModal()"
                    >
                        <SlidersHorizontal class="size-3.5" />
                        Adjust Stock
                    </Button>

                    <Button
                        v-if="isMultiBranch"
                        id="stock-tour-btn-transfer"
                        size="sm"
                        class="h-9 text-xs gap-1.5 bg-primary shadow-xs"
                        @click="transferModalOpen = true"
                    >
                        <ArrowLeftRight class="size-3.5" />
                        New Transfer
                    </Button>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    id="stock-tour-kpi-valuation"
                    title="Total Stock Valuation"
                    :value="formatCurrency(totalCostValuation)"
                    change-text="across all warehouse lines (at cost)"
                    :icon="Boxes"
                    icon-color="text-primary"
                />
                <StatCard
                    title="Retail Potential"
                    :value="formatCurrency(totalRetailPotential)"
                    :sub-value="`Proj. Margin: ${projectedMargin}%`"
                    :icon="TrendingUp"
                    icon-color="text-emerald-600"
                />
                <StatCard
                    id="stock-tour-kpi-health"
                    title="Low Stock Items"
                    :value="`${lowStockCount} Items`"
                    sub-value="at or below reorder threshold"
                    :icon="AlertTriangle"
                    icon-color="text-amber-600"
                />
                <StatCard
                    title="Out of Stock"
                    :value="`${outOfStockCount} SKUs`"
                    sub-value="requires immediate replenishment"
                    :icon="TrendingDown"
                    icon-color="text-rose-600"
                />
            </div>

            <!-- Tab Switcher Bar -->
            <div id="stock-tour-tabs" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-border pb-3">
                <div class="flex rounded-xl border border-border/80 p-1 bg-card text-xs font-semibold">
                    <button
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5',
                            activeTab === 'overview' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'overview'"
                    >
                        <Package class="size-3.5" />
                        Stock Catalog ({{ stockItems.length }})
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5',
                            activeTab === 'audit' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="startStockTake"
                    >
                        <ScanBarcode class="size-3.5" />
                        Stock Take & Audit
                        <span v-if="isAuditing" class="size-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5',
                            activeTab === 'adjustments' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'adjustments'"
                    >
                        <History class="size-3.5" />
                        Adjustments & Shrinkage ({{ adjustments.length }})
                    </button>

                    <button
                        v-if="isMultiBranch"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5',
                            activeTab === 'transfers' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'transfers'"
                    >
                        <ArrowLeftRight class="size-3.5" />
                        Inter-Branch Transfers ({{ transfers.length }})
                    </button>
                </div>

                <!-- Stock Health Filters (Active on Overview Tab) -->
                <div v-if="activeTab === 'overview'" class="flex items-center gap-1 bg-muted/40 p-1 rounded-lg border border-border/60 text-xs">
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors',
                            stockStatusFilter === 'all' ? 'bg-background shadow-xs text-foreground font-bold' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="stockStatusFilter = 'all'"
                    >
                        All Items
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors',
                            stockStatusFilter === 'in_stock' ? 'bg-background shadow-xs text-emerald-600 font-bold' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="stockStatusFilter = 'in_stock'"
                    >
                        In Stock
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors',
                            stockStatusFilter === 'low_stock' ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300 font-bold' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="stockStatusFilter = 'low_stock'"
                    >
                        Low Stock ({{ lowStockCount }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors',
                            stockStatusFilter === 'out_of_stock' ? 'bg-rose-500/20 text-rose-700 dark:text-rose-300 font-bold' : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="stockStatusFilter = 'out_of_stock'"
                    >
                        Out of Stock ({{ outOfStockCount }})
                    </button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 1: STOCK OVERVIEW & CATALOG -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'overview'" class="space-y-4">
                <DataTable
                    search-id="stock-tour-search"
                    :columns="overviewColumns"
                    :data="filteredStockItems"
                    search-placeholder="Filter stock by product name, SKU, or barcode..."
                    :search-keys="['product', 'sku', 'barcode', 'branch']"
                >
                    <template #cell-product="{ row }">
                        <div :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-col-product' : undefined">
                            <div class="font-bold text-foreground leading-tight">{{ row.product }}</div>
                            <div class="flex items-center gap-2 mt-0.5 text-[11px] text-muted-foreground font-mono">
                                <span>SKU: {{ row.sku }}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1 text-primary">
                                    <ScanBarcode class="size-3" />
                                    {{ row.barcode }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <template #cell-branch="{ row }">
                        <div :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-col-branch' : undefined" class="flex items-center gap-1.5 text-xs">
                            <Building2 class="size-3.5 text-muted-foreground" />
                            <span>{{ row.branch }}</span>
                        </div>
                    </template>

                    <template #cell-available="{ row }">
                        <div :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-col-stock' : undefined" class="font-mono text-center">
                            <span class="font-bold text-sm text-foreground">{{ row.available }}</span>
                            <span class="text-[10px] text-muted-foreground ml-1">{{ row.unit }}</span>
                            <div class="text-[10px] text-muted-foreground">Min: {{ row.reorder }}</div>
                        </div>
                    </template>

                    <template #cell-cost="{ row }">
                        <div :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-col-cost' : undefined" class="text-right font-mono">
                            <span class="font-medium text-foreground">{{ formatCurrency(row.cost) }}</span>
                            <div class="text-[10px] text-muted-foreground">Retail: {{ formatCurrency(row.retail) }}</div>
                        </div>
                    </template>

                    <template #cell-value="{ row }">
                        <div class="text-right font-mono">
                            <span class="font-bold text-foreground">{{ formatCurrency(row.value) }}</span>
                            <div class="text-[10px] text-emerald-600 dark:text-emerald-400">
                                Pot: {{ formatCurrency(row.retailValue) }}
                            </div>
                        </div>
                    </template>

                    <template #cell-status="{ row }">
                        <Badge
                            :variant="row.status === 'In Stock' ? 'success' : row.status === 'Low Stock' ? 'warning' : 'destructive'"
                            class="text-[10px] py-0.5"
                        >
                            {{ row.status }}
                        </Badge>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-1.5">
                            <Button
                                :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-btn-label' : undefined"
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs gap-1 text-muted-foreground hover:text-foreground"
                                @click="openBarcodeLabelModal(row)"
                                title="Print Barcode Labels"
                            >
                                <Printer class="size-3.5 text-primary" />
                                <span class="hidden sm:inline">Label</span>
                            </Button>

                            <Button
                                :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-btn-tag' : undefined"
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs gap-1 text-muted-foreground hover:text-amber-600 dark:hover:text-amber-400"
                                @click="openShelfPriceTagModal(row)"
                                title="Print Shelf Price Tag (No Barcode)"
                            >
                                <Tag class="size-3.5 text-amber-500" />
                                <span class="hidden sm:inline">Price Tag</span>
                            </Button>

                            <Button
                                :id="row.id === filteredStockItems[0]?.id ? 'stock-tour-btn-adjust' : undefined"
                                variant="outline"
                                size="sm"
                                class="h-7 px-2 text-xs gap-1"
                                @click="openAdjustModal(row)"
                                title="Quick Stock Adjustment"
                            >
                                <SlidersHorizontal class="size-3.5 text-muted-foreground" />
                                <span>Adjust</span>
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: PHYSICAL STOCK TAKE & AUDIT MODE (BARCODE SCANNER) -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'audit'" class="space-y-4">
                <!-- Barcode Scanner Audit Control Bar -->
                <div id="stock-tour-audit-box" class="p-4 rounded-xl border border-border/80 bg-card shadow-sm space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-foreground">Live Physical Stock Take Mode</h3>
                                <Badge variant="success" class="text-[10px] gap-1 py-0.5">
                                    <span class="size-1.5 rounded-full bg-white animate-pulse"></span>
                                    Hardware Scanner Armed
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Scan items using USB/Bluetooth barcode scanner or enter quantities. System automatically flags count variances.
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                id="stock-tour-audit-reconcile"
                                size="sm"
                                class="h-8 gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-xs font-semibold shadow-xs"
                                @click="handleReconcileStock"
                            >
                                <CheckCircle2 class="size-3.5" />
                                Reconcile Inventory Discrepancies
                            </Button>
                        </div>
                    </div>

                    <!-- Fast Scanner Search Input -->
                    <div id="stock-tour-audit-scan" class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <ScanBarcode class="absolute left-3 top-2.5 size-4 text-emerald-600 dark:text-emerald-400" />
                            <input
                                ref="auditInputRef"
                                v-model="auditScanInput"
                                type="text"
                                placeholder="Scan item barcode or enter SKU here..."
                                class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-24 text-xs font-mono font-semibold placeholder:text-muted-foreground focus:bg-background focus:ring-2 focus:ring-emerald-500"
                                @keydown.enter.prevent="handleAuditBarcodeScan(auditScanInput)"
                            />
                            <div class="absolute right-2.5 top-2.5 flex items-center gap-1.5 text-[10px] font-mono text-muted-foreground">
                                <kbd class="px-1 py-0.5 rounded bg-muted border border-border text-[9px]">Enter</kbd>
                                to submit
                            </div>
                        </div>
                    </div>

                    <!-- Variance Summary KPI Bar -->
                    <div id="stock-tour-audit-kpis" class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1 text-xs">
                        <div class="p-2.5 rounded-lg border bg-muted/30">
                            <span class="text-muted-foreground text-[11px] block">Items Audited</span>
                            <span class="font-bold text-foreground font-mono text-sm">{{ auditRows.length }} SKUs</span>
                        </div>
                        <div class="p-2.5 rounded-lg border bg-muted/30">
                            <span class="text-muted-foreground text-[11px] block">Discrepancy Lines</span>
                            <span class="font-bold text-amber-600 font-mono text-sm">
                                {{ auditRows.filter(r => r.variance !== 0).length }} Lines
                            </span>
                        </div>
                        <div class="p-2.5 rounded-lg border bg-muted/30">
                            <span class="text-muted-foreground text-[11px] block">Net Unit Variance</span>
                            <span
                                :class="[
                                    'font-bold font-mono text-sm',
                                    totalVarianceUnits === 0 ? 'text-muted-foreground' : totalVarianceUnits > 0 ? 'text-emerald-600' : 'text-rose-600'
                                ]"
                            >
                                {{ totalVarianceUnits >= 0 ? `+${totalVarianceUnits}` : totalVarianceUnits }} units
                            </span>
                        </div>
                        <div class="p-2.5 rounded-lg border bg-muted/30">
                            <span class="text-muted-foreground text-[11px] block">Variance Financial Impact</span>
                            <span
                                :class="[
                                    'font-bold font-mono text-sm',
                                    totalVarianceValuation === 0 ? 'text-muted-foreground' : totalVarianceValuation > 0 ? 'text-emerald-600' : 'text-rose-600'
                                ]"
                            >
                                {{ formatCurrency(totalVarianceValuation) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ─── Audit Filter & Search Toolbar ────────────────────── -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 flex-wrap flex-1 min-w-[280px]">
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-64">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                            <Input
                                v-model="auditSearchQuery"
                                placeholder="Search audit item, SKU or barcode..."
                                class="pl-9 pr-7 h-9 text-xs"
                            />
                            <button
                                v-if="auditSearchQuery"
                                @click="auditSearchQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <!-- Status / Variance Filter -->
                        <select
                            v-model="auditStatusFilter"
                            class="h-9 rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="all">All Audit Items</option>
                            <option value="discrepancy">Discrepancies Only (≠ 0)</option>
                            <option value="match">Exact Matches Only (= 0)</option>
                            <option value="surplus">Surplus Items Only (+)</option>
                            <option value="shortage">Shortage Items Only (-)</option>
                        </select>

                        <!-- Reset button -->
                        <Button
                            v-if="auditSearchQuery || auditStatusFilter !== 'all'"
                            variant="ghost"
                            size="sm"
                            class="h-9 text-xs gap-1 text-muted-foreground hover:text-foreground"
                            @click="auditSearchQuery = ''; auditStatusFilter = 'all'; auditCurrentPage = 1;"
                        >
                            <RotateCcw class="size-3" />
                            Reset
                        </Button>
                    </div>

                    <!-- Showing count -->
                    <div class="text-xs text-muted-foreground">
                        Showing <span class="font-bold text-foreground">{{ filteredAuditRows.length }}</span> of <span class="font-bold text-foreground">{{ auditRows.length }}</span> audit SKUs
                    </div>
                </div>

                <!-- Live Audit Table -->
                <div id="stock-tour-audit-table" class="rounded-xl border border-border/80 bg-card overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/50 border-b border-border text-muted-foreground font-semibold">
                                <tr>
                                    <th class="p-3">Product / Barcode</th>
                                    <th class="p-3 text-center">System Expected</th>
                                    <th class="p-3 text-center">Physical Counted</th>
                                    <th class="p-3 text-center">Variance (Shift)</th>
                                    <th class="p-3 text-right">Unit Cost</th>
                                    <th class="p-3 text-right">Variance Value</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="row in paginatedAuditRows"
                                    :key="row.productId"
                                    :class="[
                                        'hover:bg-muted/20 transition-colors',
                                        row.variance !== 0 ? 'bg-amber-500/5 dark:bg-amber-500/10' : ''
                                    ]"
                                >
                                    <td class="p-3">
                                        <div class="font-bold text-foreground">{{ row.productName }}</div>
                                        <div class="text-[11px] text-muted-foreground font-mono">
                                            SKU: {{ row.sku }} • Barcode: {{ row.barcode }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-center font-mono font-bold text-muted-foreground">
                                        {{ row.systemStock }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <div
                                            :id="paginatedAuditRows[0]?.productId === row.productId ? 'stock-tour-audit-stepper' : undefined"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                class="size-6 rounded border bg-muted/40 hover:bg-muted flex items-center justify-center font-bold text-xs cursor-pointer"
                                                @click="updateCountDirectly(row, row.countedStock - 1)"
                                            >
                                                -
                                            </button>
                                            <input
                                                type="number"
                                                :value="row.countedStock"
                                                @input="(e: any) => updateCountDirectly(row, Number(e.target.value))"
                                                class="w-16 h-7 text-center font-mono font-bold text-xs rounded border border-input bg-background"
                                            />
                                            <button
                                                type="button"
                                                class="size-6 rounded border bg-muted/40 hover:bg-muted flex items-center justify-center font-bold text-xs cursor-pointer"
                                                @click="updateCountDirectly(row, row.countedStock + 1)"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3 text-center font-mono font-bold">
                                        <span
                                            v-if="row.variance !== 0"
                                            :class="row.variance > 0 ? 'text-emerald-600' : 'text-rose-600'"
                                        >
                                            {{ row.variance > 0 ? `+${row.variance}` : row.variance }}
                                        </span>
                                        <span v-else class="text-muted-foreground">0</span>
                                    </td>
                                    <td class="p-3 text-right font-mono text-muted-foreground">
                                        {{ formatCurrency(row.unitCost) }}
                                    </td>
                                    <td class="p-3 text-right font-mono font-bold">
                                        <span
                                            v-if="row.varianceCost !== 0"
                                            :class="row.varianceCost > 0 ? 'text-emerald-600' : 'text-rose-600'"
                                        >
                                            {{ formatCurrency(row.varianceCost) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </td>
                                    <td
                                        :id="paginatedAuditRows[0]?.productId === row.productId ? 'stock-tour-audit-status' : undefined"
                                        class="p-3 text-center"
                                    >
                                        <Badge
                                            v-if="row.variance === 0"
                                            variant="success"
                                            class="text-[10px] py-0.5"
                                        >
                                            Exact Match
                                        </Badge>
                                        <Badge
                                            v-else-if="row.variance > 0"
                                            variant="info"
                                            class="text-[10px] py-0.5"
                                        >
                                            Surplus (+{{ row.variance }})
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="destructive"
                                            class="text-[10px] py-0.5"
                                        >
                                            Shortage ({{ row.variance }})
                                        </Badge>
                                    </td>
                                </tr>
                                <tr v-if="filteredAuditRows.length === 0">
                                    <td colspan="7" class="p-8 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center justify-center gap-1">
                                            <p class="text-sm font-medium">No matching audit items found</p>
                                            <p class="text-xs text-muted-foreground">Try adjusting your search query or variance filter</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ─── Table Pagination Bar ────────────────────────────── -->
                    <div
                        class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-border/70 bg-muted/20 px-4 py-3 text-xs text-muted-foreground"
                    >
                        <!-- Left: Showing summary & Page size selector -->
                        <div class="flex items-center gap-3 flex-wrap justify-between w-full sm:w-auto">
                            <div class="text-xs text-muted-foreground">
                                Showing
                                <span class="font-bold text-foreground">{{ filteredAuditRows.length === 0 ? 0 : (auditCurrentPage - 1) * auditPageSize + 1 }}</span>
                                to
                                <span class="font-bold text-foreground">{{ Math.min(auditCurrentPage * auditPageSize, filteredAuditRows.length) }}</span>
                                of
                                <span class="font-bold text-foreground">{{ filteredAuditRows.length }}</span>
                                items
                            </div>

                            <!-- Rows per page selector -->
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span>Per page:</span>
                                <select
                                    v-model.number="auditPageSize"
                                    class="h-7 rounded-md border border-input bg-background px-2 text-xs font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option v-for="size in auditPageSizeOptions" :key="size" :value="size">
                                        {{ size }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Right: Page Navigation -->
                        <div v-if="auditTotalPages > 1" class="flex items-center gap-1">
                            <!-- First Page -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 w-8 p-0"
                                :disabled="auditCurrentPage === 1"
                                title="First page"
                                @click="goToAuditPage(1)"
                            >
                                <ChevronsLeft class="size-3.5" />
                            </Button>

                            <!-- Prev Page -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 w-8 p-0"
                                :disabled="auditCurrentPage === 1"
                                title="Previous page"
                                @click="goToAuditPage(auditCurrentPage - 1)"
                            >
                                <ChevronLeft class="size-3.5" />
                            </Button>

                            <!-- Page Pills -->
                            <template v-for="(p, i) in auditVisiblePages" :key="i">
                                <span
                                    v-if="p === '...'"
                                    class="px-2 py-1 text-xs text-muted-foreground select-none"
                                >
                                    …
                                </span>
                                <Button
                                    v-else
                                    :variant="auditCurrentPage === p ? 'default' : 'outline'"
                                    size="sm"
                                    :class="[
                                        'h-8 min-w-8 px-2 text-xs font-semibold',
                                        auditCurrentPage === p ? 'bg-primary text-primary-foreground font-bold shadow-xs' : 'hover:bg-muted/60 text-muted-foreground hover:text-foreground'
                                    ]"
                                    @click="goToAuditPage(p as number)"
                                >
                                    {{ p }}
                                </Button>
                            </template>

                            <!-- Next Page -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 w-8 p-0"
                                :disabled="auditCurrentPage === auditTotalPages"
                                title="Next page"
                                @click="goToAuditPage(auditCurrentPage + 1)"
                            >
                                <ChevronRight class="size-3.5" />
                            </Button>

                            <!-- Last Page -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 w-8 p-0"
                                :disabled="auditCurrentPage === auditTotalPages"
                                title="Last page"
                                @click="goToAuditPage(auditTotalPages)"
                            >
                                <ChevronsRight class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: STOCK ADJUSTMENTS & SHRINKAGE -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'adjustments'" class="space-y-4">
                <DataTable
                    search-id="stock-tour-tab-adjustments"
                    :columns="adjustmentColumns"
                    :data="adjustments"
                    search-placeholder="Search adjustment ref or item..."
                    :search-keys="['id', 'product', 'reason', 'by']"
                >
                    <template #cell-id="{ row }">
                        <span class="font-mono font-bold text-primary">{{ row.id }}</span>
                    </template>

                    <template #cell-change="{ row }">
                        <span
                            :id="row.id === adjustments[0]?.id ? 'stock-tour-adj-shift' : undefined"
                            :class="[
                                'font-mono font-bold text-xs',
                                row.change > 0 ? 'text-emerald-600' : 'text-rose-600',
                            ]"
                        >
                            {{ row.change > 0 ? `+${row.change}` : row.change }} units
                        </span>
                    </template>

                    <template #cell-reason="{ row }">
                        <Badge
                            :id="row.id === adjustments[0]?.id ? 'stock-tour-adj-reason' : undefined"
                            variant="outline"
                            class="text-[11px] font-normal"
                        >
                            {{ row.reason }}
                        </Badge>
                    </template>
                </DataTable>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 4: INTER-BRANCH TRANSFERS -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'transfers' && isMultiBranch" class="space-y-4">
                <DataTable
                    search-id="stock-tour-tab-transfers"
                    :columns="transferColumns"
                    :data="transfers"
                    search-placeholder="Search transfer reference or branch..."
                    :search-keys="['id', 'from', 'to', 'product']"
                >
                    <template #actions>
                        <Button
                            id="stock-tour-tab4-new-transfer"
                            size="sm"
                            class="h-9 text-xs gap-1.5 bg-primary font-semibold shadow-xs"
                            @click="transferModalOpen = true"
                        >
                            <Plus class="size-3.5" />
                            New Transfer
                        </Button>
                    </template>

                    <template #cell-id="{ row }">
                        <span class="font-mono font-bold text-primary">{{ row.id }}</span>
                        <div class="text-[10px] text-muted-foreground">{{ row.date }}</div>
                    </template>

                    <template #cell-product="{ row }">
                        <span class="font-bold text-foreground">{{ row.qty }}x {{ row.product }}</span>
                    </template>

                    <template #cell-status="{ row }">
                        <Badge
                            :id="row.id === transfers[0]?.id ? 'stock-tour-transfer-status' : undefined"
                            :variant="row.status === 'Completed' ? 'success' : row.status === 'Approved' ? 'info' : 'warning'"
                        >
                            {{ row.status }}
                        </Badge>
                    </template>

                    <template #cell-actions="{ row }">
                        <Button
                            v-if="row.status !== 'Completed'"
                            :id="row.id === transfers[0]?.id ? 'stock-tour-transfer-receive' : undefined"
                            size="sm"
                            class="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 font-semibold shadow-2xs"
                            @click="handleCompleteTransfer(row)"
                        >
                            Receive & Stock
                        </Button>
                        <span v-else class="text-xs text-muted-foreground font-medium">Received ✓</span>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL: QUICK STOCK ADJUSTMENT -->
        <!-- ============================================================ -->
        <Dialog :open="adjustModalOpen" @update:open="(v) => (adjustModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <SlidersHorizontal class="size-4 text-primary" />
                        Record Stock Adjustment / Shrinkage
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-3 py-2 text-xs">
                    <!-- Product Selector -->
                    <div>
                        <Label class="text-xs">Product Item</Label>
                        <select
                            v-model="adjustForm.productId"
                            @change="() => {
                                const p = stockItems.find(i => i.id === adjustForm.productId);
                                if (p) {
                                    adjustForm.productName = p.product;
                                    adjustForm.currentStock = p.available;
                                }
                            }"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                        >
                            <option v-for="item in stockItems" :key="item.id" :value="item.id">
                                {{ item.product }} (Current: {{ item.available }})
                            </option>
                        </select>
                    </div>

                    <!-- Shift Direction -->
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :class="[
                                'py-2 px-3 rounded-lg border text-xs font-bold transition-all',
                                adjustForm.type === 'decrease'
                                    ? 'bg-rose-500/15 border-rose-500 text-rose-600 dark:text-rose-400'
                                    : 'border-border text-muted-foreground hover:text-foreground'
                            ]"
                            @click="adjustForm.type = 'decrease'"
                        >
                            - Deduct Stock (Loss)
                        </button>
                        <button
                            type="button"
                            :class="[
                                'py-2 px-3 rounded-lg border text-xs font-bold transition-all',
                                adjustForm.type === 'increase'
                                    ? 'bg-emerald-500/15 border-emerald-500 text-emerald-600 dark:text-emerald-400'
                                    : 'border-border text-muted-foreground hover:text-foreground'
                            ]"
                            @click="adjustForm.type = 'increase'"
                        >
                            + Add Stock (Surplus)
                        </button>
                    </div>

                    <!-- Quantity Shift -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Adjustment Quantity</Label>
                            <Input
                                v-model.number="adjustForm.changeQty"
                                type="number"
                                min="1"
                                class="mt-1 font-mono font-bold text-xs"
                            />
                        </div>
                        <div>
                            <Label class="text-xs">Resulting Stock Level</Label>
                            <div class="mt-1 h-9 flex items-center px-3 rounded-md bg-muted/40 border border-border/80 font-mono font-bold text-xs text-foreground">
                                {{ resultingStock }} units
                            </div>
                        </div>
                    </div>

                    <!-- Adjustment Reason -->
                    <div>
                        <Label class="text-xs">Reason Code</Label>
                        <select
                            v-model="adjustForm.reason"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                        >
                            <option v-for="r in adjustmentReasons" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>

                    <!-- Internal Notes -->
                    <div>
                        <Label class="text-xs">Internal Notes (Optional)</Label>
                        <Input
                            v-model="adjustForm.notes"
                            placeholder="e.g. Broken bottle during shelf rearrangement"
                            class="mt-1 text-xs"
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="adjustModalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleSaveAdjustment">Save Adjustment</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: NEW INTER-BRANCH TRANSFER -->
        <!-- ============================================================ -->
        <Dialog :open="transferModalOpen" @update:open="(v) => (transferModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Transfer Stock Between Branches</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Origin Branch (Source)</Label>
                        <select v-model="newTransfer.from" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option v-for="b in branchesForCurrentTenant(currentTenant.id)" :key="b.id" :value="b.name">{{ b.name }}</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-xs">Destination Branch</Label>
                        <select v-model="newTransfer.to" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option v-for="b in branchesForCurrentTenant(currentTenant.id)" :key="b.id" :value="b.name">{{ b.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Product</Label>
                            <Input v-model="newTransfer.product" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <Label class="text-xs">Quantity to Transfer</Label>
                            <Input v-model.number="newTransfer.qty" type="number" class="mt-1 text-xs font-mono font-bold" />
                        </div>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="transferModalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleCreateTransfer">Create Transfer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: BARCODE THERMAL LABEL GENERATOR -->
        <!-- ============================================================ -->
        <BarcodeLabelModal
            :open="labelModalOpen"
            :product="labelProduct"
            @update:open="(v) => (labelModalOpen = v)"
        />

        <!-- ============================================================ -->
        <!-- MODAL: SHELF PRICE TAG GENERATOR (NO BARCODE) -->
        <!-- ============================================================ -->
        <ShelfPriceTagModal
            :open="shelfTagModalOpen"
            :product="shelfTagProduct"
            @update:open="(v) => (shelfTagModalOpen = v)"
        />

        <!-- ============================================================ -->
        <!-- INTERACTIVE STOCK OVERVIEW SPOTLIGHT & TOUR GUIDE -->
        <!-- ============================================================ -->
        <StockGuideOverlay
            v-model:open="guideOverlayOpen"
            :current-step="guideCurrentStep"
            :active-tab="activeTab"
            @update:current-step="(s) => (guideCurrentStep = s)"
            @action="handleGuideAction"
            @switch-tab="handleGuideTabSwitch"
        />
    </AppLayout>
</template>
