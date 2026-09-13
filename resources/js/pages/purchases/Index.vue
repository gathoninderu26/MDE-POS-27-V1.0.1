<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import StatCard from '@/components/ui/StatCard.vue';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Boxes,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    CheckSquare,
    Clock,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    FileType2,
    Filter,
    Layers,
    Package,
    PackageCheck,
    Plus,
    Printer,
    Receipt,
    RefreshCw,
    Search,
    ShoppingBag,
    Sparkles,
    Square,
    Trash2,
    Truck,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface BulkPurchaseItem {
    product_id: number | null;
    product_name: string;
    sku: string;
    package_unit: string;
    units_per_pack: number;
    packs_quantity: number;
    total_units: number;
    current_stock: number;
    projected_stock: number;
    unit_cost: number;
    line_total: number;
}

const props = defineProps<{
    purchases?: any[];
    suppliers?: any[];
    branches?: any[];
    products?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchases & Procurement', href: '/purchases' },
];

const { currentTenant } = useTenant();
const { success, warning, error, info } = useToast();

const purchaseOrders = computed(() => {
    return props.purchases ?? [];
});

// Document Filter Tabs & Search Filter
const activeDocType = ref<'ALL' | 'PO' | 'LPO'>('ALL');
const tableSearchQuery = ref('');
const tableStatusFilter = ref('ALL');

const poList = computed(() => purchaseOrders.value.filter((p) => (p.documentType ?? 'PO') === 'PO'));
const lpoList = computed(() => purchaseOrders.value.filter((p) => p.documentType === 'LPO'));

// KPI Metrics
const totalProcurementValue = computed(() => purchaseOrders.value.reduce((acc, p) => acc + (p.amount || 0), 0));
const totalPOValue = computed(() => poList.value.reduce((acc, p) => acc + (p.amount || 0), 0));
const totalLPOValue = computed(() => lpoList.value.reduce((acc, p) => acc + (p.amount || 0), 0));
const pendingPOCount = computed(() => purchaseOrders.value.filter((p) => p.status === 'Pending' || p.status === 'Approved').length);
const receivedPOCount = computed(() => purchaseOrders.value.filter((p) => p.status === 'Received').length);

const displayedOrders = computed(() => {
    let list = purchaseOrders.value;

    if (activeDocType.value === 'PO') {
        list = poList.value;
    } else if (activeDocType.value === 'LPO') {
        list = lpoList.value;
    }

    if (tableStatusFilter.value !== 'ALL') {
        list = list.filter((p) => p.status === tableStatusFilter.value);
    }

    if (tableSearchQuery.value.trim()) {
        const q = tableSearchQuery.value.toLowerCase().trim();
        list = list.filter((p) => {
            const refNo = (p.documentType === 'LPO' ? (p.lpoNumber || p.poNumber) : p.poNumber) || '';
            const supplier = p.supplier || '';
            const branch = p.branch || '';
            const items = p.items || '';
            const purpose = p.lpoPurpose || '';
            const creator = p.createdByName || '';
            const status = p.status || '';
            return (
                refNo.toLowerCase().includes(q) ||
                supplier.toLowerCase().includes(q) ||
                branch.toLowerCase().includes(q) ||
                items.toLowerCase().includes(q) ||
                purpose.toLowerCase().includes(q) ||
                creator.toLowerCase().includes(q) ||
                status.toLowerCase().includes(q)
            );
        });
    }

    return list.map((p) => ({
        ...p,
        refNumber: p.documentType === 'LPO' ? (p.lpoNumber || p.poNumber) : p.poNumber,
    }));
});

const columns = computed(() => {
    return [
        { key: 'poNumber', label: 'Document Ref' },
        { key: 'lpoPurpose', label: 'Type & Purpose' },
        { key: 'supplier', label: 'Supplier / Vendor' },
        { key: 'branch', label: 'Receiving Branch' },
        { key: 'items', label: 'Procurement Summary' },
        { key: 'amount', label: 'Total Value (KES)', align: 'right' as const },
        { key: 'status', label: 'Delivery Status & Sync', align: 'center' as const },
        { key: 'actions', label: 'Actions', align: 'right' as const },
    ];
});

// ============================================================
// PO CREATION MODAL & BULK LINE ITEMS
// ============================================================
const newPOModal = ref(false);
const creationMode = ref<'bulk' | 'quick'>('bulk');

const newPO = ref({
    supplier_id: null as number | null,
    branch_id: null as number | null,
    items_description: '',
    total_amount: 0,
    expected_date: '',
    notes: '',
});

const bulkItems = ref<BulkPurchaseItem[]>([
    {
        product_id: null,
        product_name: 'Pembe Maize Flour 2kg',
        sku: 'GRO-004',
        package_unit: 'Bales',
        units_per_pack: 12,
        packs_quantity: 10,
        total_units: 120,
        current_stock: 40,
        projected_stock: 160,
        unit_cost: 140,
        line_total: 16800,
    },
]);

const packageUnits = [
    'Cartons',
    'Bales',
    'Crates',
    'Sacks / Bags',
    'Pallets',
    'Packs',
    'Pieces / Units',
];

const updateBulkLineCalculations = (item: BulkPurchaseItem) => {
    item.units_per_pack = Math.max(1, item.units_per_pack || 1);
    item.packs_quantity = Math.max(1, item.packs_quantity || 1);
    item.total_units = item.units_per_pack * item.packs_quantity;
    item.current_stock = item.current_stock || 0;
    item.projected_stock = item.current_stock + item.total_units;
    item.line_total = item.total_units * (item.unit_cost || 0);
};

const handleSelectProductForLine = (item: BulkPurchaseItem, prodId: number) => {
    const product = props.products?.find((p) => p.id === prodId);
    if (product) {
        item.product_id = product.id;
        item.product_name = product.name;
        item.sku = product.sku || '';
        item.unit_cost = Number(product.cost_price || 0);
        item.current_stock = Number(product.stock_quantity ?? 0);
        updateBulkLineCalculations(item);
    }
};

const addBulkLineItem = () => {
    bulkItems.value.push({
        product_id: null,
        product_name: '',
        sku: '',
        package_unit: 'Cartons',
        units_per_pack: 12,
        packs_quantity: 5,
        total_units: 60,
        current_stock: 0,
        projected_stock: 60,
        unit_cost: 0,
        line_total: 0,
    });
};

const removeBulkLineItem = (index: number) => {
    if (bulkItems.value.length > 1) {
        bulkItems.value.splice(index, 1);
    } else {
        bulkItems.value = [
            {
                product_id: null,
                product_name: '',
                sku: '',
                package_unit: 'Cartons',
                units_per_pack: 12,
                packs_quantity: 1,
                total_units: 12,
                current_stock: 0,
                projected_stock: 12,
                unit_cost: 0,
                line_total: 0,
            },
        ];
    }
};

const calculatedBulkTotal = computed(() => {
    return bulkItems.value.reduce((acc, i) => acc + (i.line_total || 0), 0);
});

const calculatedTotalUnits = computed(() => {
    return bulkItems.value.reduce((acc, i) => acc + (i.total_units || 0), 0);
});

const calculatedCurrentStock = computed(() => {
    return bulkItems.value.reduce((acc, i) => acc + (i.current_stock || 0), 0);
});

const calculatedProjectedStock = computed(() => {
    return calculatedCurrentStock.value + calculatedTotalUnits.value;
});

// Auto-populate Low Stock catalog items
const autoPopulateLowStock = () => {
    if (!props.products || props.products.length === 0) {
        info('Catalog Empty', 'No active products found in catalog.');
        return;
    }

    const lowStock = props.products.filter(
        (p) => Number(p.stock_quantity ?? 0) <= Number(p.reorder_level ?? 10),
    );

    if (lowStock.length === 0) {
        info('Stock Optimal', 'All catalog products are currently above their reorder thresholds.');
        return;
    }

    bulkItems.value = lowStock.map((p) => {
        const reorder = Number(p.reorder_level ?? 10);
        const current = Number(p.stock_quantity ?? 0);
        const neededUnits = Math.max(12, reorder * 3 - current);
        const unitsPerPack = 12;
        const packs = Math.ceil(neededUnits / unitsPerPack);
        const unitCost = Number(p.cost_price ?? 0);

        return {
            product_id: p.id,
            product_name: p.name,
            sku: p.sku || '',
            package_unit: 'Cartons',
            units_per_pack: unitsPerPack,
            packs_quantity: packs,
            total_units: packs * unitsPerPack,
            current_stock: current,
            projected_stock: current + packs * unitsPerPack,
            unit_cost: unitCost,
            line_total: packs * unitsPerPack * unitCost,
        };
    });

    success('Low Stock Populated', `Added ${lowStock.length} items requiring restocking.`);
};

const openCreatePOModal = (mode: 'bulk' | 'quick' = 'bulk') => {
    creationMode.value = mode;
    newPO.value = {
        supplier_id: props.suppliers?.[0]?.id || null,
        branch_id: props.branches?.[0]?.id || null,
        items_description: '',
        total_amount: 15000,
        expected_date: new Date(Date.now() + 86400000 * 3).toISOString().slice(0, 10),
        notes: '',
    };
    newPOModal.value = true;
};

const handleCreatePO = () => {
    let description = '';
    let total = 0;
    let notesData: any = null;

    if (creationMode.value === 'bulk') {
        const validItems = bulkItems.value.filter((i) => i.product_name.trim() !== '' || i.product_id);
        if (validItems.length === 0) {
            warning('Incomplete Order', 'Please select at least one item for bulk purchase.');
            return;
        }

        total = calculatedBulkTotal.value;
        description = validItems
            .map((i) => `${i.product_name || 'Item'} (${i.packs_quantity} ${i.package_unit} / ${i.total_units} units)`)
            .join(' • ');

        // Structured JSON items for automatic inventory restock on receive
        notesData = JSON.stringify(
            validItems.map((i) => ({
                product_id: i.product_id,
                name: i.product_name,
                sku: i.sku,
                package_unit: i.package_unit,
                packs: i.packs_quantity,
                units_per_pack: i.units_per_pack,
                current_stock: i.current_stock,
                quantity: i.total_units,
                projected_stock: i.projected_stock,
                unit_cost: i.unit_cost,
                total: i.line_total,
            })),
        );
    } else {
        total = Number(newPO.value.total_amount) || 0;
        description = newPO.value.items_description || 'General Store Supplies';
        notesData = newPO.value.notes || null;
    }

    router.post(
        '/purchases',
        {
            document_type: 'PO',
            supplier_id: newPO.value.supplier_id,
            branch_id: newPO.value.branch_id,
            items_description: description,
            total_amount: total,
            expected_date: newPO.value.expected_date || null,
            notes: notesData,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newPOModal.value = false;
                success('Purchase Order Dispatched', 'Purchase order created and logged for vendor delivery.');
            },
            onError: (errs) => error('Creation Failed', Object.values(errs)[0] as string),
        },
    );
};

// ============================================================
// LPO (LOCAL PURCHASE ORDER) CREATION & HANDLERS
// ============================================================
const newLPOModal = ref(false);

const lpoPurposes = [
    'Maintenance & Repairs',
    'Casual & Local Market Buying',
    'Perishables & Fresh Produce',
    'Office & Store Consumables',
    'Utilities & Emergency Services',
    'Direct Customer Fulfillment',
    'Miscellaneous Supplies',
];

const newLPO = ref({
    supplier_id: null as number | null,
    branch_id: null as number | null,
    lpo_purpose: 'Maintenance & Repairs',
    items_description: '',
    total_amount: 5000,
    expected_date: new Date(Date.now() + 86400000).toISOString().slice(0, 10),
    notes: '',
});

const openCreateLPOModal = () => {
    newLPO.value = {
        supplier_id: props.suppliers?.[0]?.id || null,
        branch_id: props.branches?.[0]?.id || null,
        lpo_purpose: 'Maintenance & Repairs',
        items_description: '',
        total_amount: 5000,
        expected_date: new Date(Date.now() + 86400000).toISOString().slice(0, 10),
        notes: '',
    };
    newLPOModal.value = true;
};

const handleCreateLPO = () => {
    if (!newLPO.value.lpo_purpose) {
        warning('Purpose Required', 'Please choose a valid LPO purpose or category.');
        return;
    }
    if (!newLPO.value.total_amount || newLPO.value.total_amount <= 0) {
        warning('Amount Required', 'Please enter a valid estimated order value.');
        return;
    }

    router.post(
        '/purchases',
        {
            document_type: 'LPO',
            lpo_purpose: newLPO.value.lpo_purpose,
            supplier_id: newLPO.value.supplier_id,
            branch_id: newLPO.value.branch_id,
            items_description: newLPO.value.items_description || newLPO.value.lpo_purpose,
            total_amount: newLPO.value.total_amount,
            expected_date: newLPO.value.expected_date || null,
            notes: newLPO.value.notes || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newLPOModal.value = false;
                activeDocType.value = 'LPO';
                success('LPO Issued', 'Local Purchase Order generated and registered successfully.');
            },
            onError: (errs) => error('Creation Failed', (Object.values(errs)[0] as string) || 'Unable to create LPO.'),
        },
    );
};

// ============================================================
// GOODS RECEIVING & INVENTORY SYNC MODAL
// ============================================================
const receiveModalOpen = ref(false);
const receivingPO = ref<any>(null);
const selectedReceiveProductIds = ref<number[]>([]);
const receivingNotes = ref('');

const openReceiveModal = (po: any) => {
    receivingPO.value = po;
    receivingNotes.value = '';
    const items = getPOStructuredItems(po);
    if (items && items.length > 0) {
        selectedReceiveProductIds.value = items.map((i: any) => i.product_id).filter(Boolean);
    } else {
        selectedReceiveProductIds.value = [];
    }
    receiveModalOpen.value = true;
};

const toggleReceiveItem = (prodId: number) => {
    const idx = selectedReceiveProductIds.value.indexOf(prodId);
    if (idx >= 0) {
        selectedReceiveProductIds.value.splice(idx, 1);
    } else {
        selectedReceiveProductIds.value.push(prodId);
    }
};

const toggleSelectAllReceiveItems = () => {
    const items = getPOStructuredItems(receivingPO.value);
    if (!items) return;
    const allIds = items.map((i: any) => i.product_id).filter(Boolean);
    if (selectedReceiveProductIds.value.length === allIds.length) {
        selectedReceiveProductIds.value = [];
    } else {
        selectedReceiveProductIds.value = [...allIds];
    }
};

const handleConfirmReceiveAndSync = () => {
    if (!receivingPO.value) return;
    const po = receivingPO.value;
    const refNo = po.documentType === 'LPO' ? (po.lpoNumber || po.poNumber) : po.poNumber;

    router.patch(
        `/purchases/${po.id}/receive`,
        {
            received_items: selectedReceiveProductIds.value,
            receiving_notes: receivingNotes.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                receiveModalOpen.value = false;
                success(
                    'Stock Received & Synced',
                    `${refNo} verified. Inventory stock automatically adjusted and synchronized.`
                );
            },
            onError: (errs) => error('Receiving Failed', Object.values(errs)[0] as string),
        },
    );
};

const handleResyncStock = (po: any) => {
    const refNo = po.documentType === 'LPO' ? (po.lpoNumber || po.poNumber) : po.poNumber;
    success(
        'Inventory Synchronized',
        `Stock is already up-to-date and synced with ${refNo} (${po.syncedAt || po.orderTime}).`
    );
};

const handleDeletePO = (po: any) => {
    const refNo = po.documentType === 'LPO' ? (po.lpoNumber || po.poNumber) : po.poNumber;
    if (!confirm(`Are you sure you want to cancel order ${refNo}?`)) return;

    router.delete(`/purchases/${po.id}`, {
        preserveScroll: true,
        onSuccess: () => success('Order Cancelled', `Order ${refNo} has been removed.`),
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
    });
};

// ============================================================
// PO BREAKDOWN DETAIL MODAL (WHO, WHEN, WHAT)
// ============================================================
const detailModalOpen = ref(false);
const detailPO = ref<any>(null);
const parsedDetailItems = computed(() => {
    if (!detailPO.value?.notes) return null;
    try {
        const parsed = JSON.parse(detailPO.value.notes);
        return Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
});

const openPODetail = (po: any) => {
    detailPO.value = po;
    detailModalOpen.value = true;
};

// ============================================================
// EXPORT UTILITIES: PDF, WORD, CSV
// ============================================================
const downloadModalOpen = ref(false);
const selectedPOForDownload = ref<any>(null);

const openDownloadModal = (po?: any) => {
    selectedPOForDownload.value = po || null;
    downloadModalOpen.value = true;
};

const downloadCsv = (po?: any) => {
    let headers: string[];
    let rows: (string | number)[][];
    let filename = '';

    if (po) {
        const isLPO = po.documentType === 'LPO';
        const docRef = isLPO ? (po.lpoNumber || po.poNumber) : po.poNumber;
        filename = `${docRef}_order.csv`;
        const items = getPOStructuredItems(po);
        if (items && items.length > 0) {
            headers = ['Item Name', 'SKU', 'Packaging Unit', 'Packs', 'Units Per Pack', 'Total Units', 'Unit Cost (KES)', 'Subtotal (KES)'];
            rows = items.map((i: any) => [
                `"${(i.name || i.product_name).replace(/"/g, '""')}"`,
                `"${i.sku || ''}"`,
                `"${i.package_unit || 'Pcs'}"`,
                i.packs || 1,
                i.units_per_pack || 1,
                i.quantity || i.total_units,
                i.unit_cost,
                i.total || i.line_total,
            ]);
        } else {
            headers = ['Document Type', 'Reference', 'Purpose', 'Supplier / Vendor', 'Branch', 'Items', 'Total Value (KES)', 'Status', 'Date', 'Ordered By'];
            rows = [[`"${po.documentType || 'PO'}"`, `"${docRef}"`, `"${po.lpoPurpose || 'N/A'}"`, `"${po.supplier}"`, `"${po.branch}"`, `"${(po.items || '').replace(/"/g, '""')}"`, po.amount, `"${po.status}"`, `"${po.date}"`, `"${po.createdByName || 'Admin'}"`]];
        }
    } else {
        filename = `procurement_orders_${new Date().toISOString().slice(0, 10)}.csv`;
        headers = ['Document Type', 'Reference', 'Purpose', 'Supplier / Vendor', 'Branch', 'Items Description', 'Total Value (KES)', 'Delivery Status', 'Date', 'Ordered By'];
        rows = purchaseOrders.value.map((p) => [
            `"${p.documentType || 'PO'}"`,
            `"${p.documentType === 'LPO' ? (p.lpoNumber || p.poNumber) : p.poNumber}"`,
            `"${p.lpoPurpose || 'Contract PO'}"`,
            `"${p.supplier}"`,
            `"${p.branch}"`,
            `"${(p.items || '').replace(/"/g, '""')}"`,
            p.amount,
            `"${p.status}"`,
            `"${p.date}"`,
            `"${p.createdByName || 'Admin'}"`,
        ]);
    }

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    downloadModalOpen.value = false;
    success('CSV Exported', `Downloaded ${filename}`);
};

const downloadWord = (po?: any) => {
    const targetPOs = po ? [po] : purchaseOrders.value;
    const businessName = currentTenant.value?.name || 'MDE Retail Systems';
    const isSingle = !!po;
    const docRef = po ? (po.documentType === 'LPO' ? (po.lpoNumber || po.poNumber) : po.poNumber) : '';
    const filename = isSingle ? `${docRef}_order.doc` : `procurement_orders_report_${new Date().toISOString().slice(0, 10)}.doc`;

    let bodyHtml = '';

    targetPOs.forEach((targetPO) => {
        const isLPO = targetPO.documentType === 'LPO';
        const docTitle = isLPO ? 'LOCAL PURCHASE ORDER (LPO)' : 'PURCHASE ORDER (PO)';
        const refNumber = isLPO ? (targetPO.lpoNumber || targetPO.poNumber) : targetPO.poNumber;
        const items = getPOStructuredItems(targetPO);
        bodyHtml += `
            <div style="margin-bottom: 40px; page-break-after: always;">
                <h1 style="color: #047857; margin-bottom: 4px; font-size: 20pt;">${businessName}</h1>
                <h2 style="color: #334155; font-size: 14pt; margin-top: 0;">${docTitle}: ${refNumber}</h2>
                <hr style="border: 0; border-top: 2px solid #047857; margin-bottom: 15px;" />
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt;">
                    <tr>
                        <td style="padding: 4px 0; width: 50%;"><strong>Supplier / Vendor:</strong> ${targetPO.supplier}</td>
                        <td style="padding: 4px 0; width: 50%;"><strong>Order Date & Time:</strong> ${targetPO.orderTime || targetPO.date}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0;"><strong>Receiving Branch:</strong> ${targetPO.branch}</td>
                        <td style="padding: 4px 0;"><strong>Ordered By:</strong> ${targetPO.createdByName || 'Procurement Officer'}</td>
                    </tr>
                    ${isLPO ? `
                    <tr>
                        <td style="padding: 4px 0;" colspan="2"><strong>LPO Category / Purpose:</strong> ${targetPO.lpoPurpose || 'Local Procurement'}</td>
                    </tr>
                    ` : ''}
                    <tr>
                        <td style="padding: 4px 0;"><strong>Delivery Status:</strong> ${targetPO.status}</td>
                        <td style="padding: 4px 0;"><strong>Total Value:</strong> KES ${Number(targetPO.amount).toLocaleString()}</td>
                    </tr>
                </table>

                <h3 style="color: #0f172a; font-size: 11pt; margin-bottom: 8px;">Procurement Line Items Breakdown</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 25px;">
                    <thead>
                        <tr style="background-color: #f1f5f9; text-align: left;">
                            <th style="border: 1px solid #cbd5e1; padding: 6px 8px;">Item Description</th>
                            <th style="border: 1px solid #cbd5e1; padding: 6px 8px;">Packaging Unit</th>
                            <th style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center;">Packs</th>
                            <th style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center;">Total Units</th>
                            <th style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right;">Unit Cost (KES)</th>
                            <th style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right;">Line Total (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${
                            items && items.length > 0
                                ? items
                                      .map(
                                          (it: any) => `
                                    <tr>
                                        <td style="border: 1px solid #cbd5e1; padding: 6px 8px;"><strong>${it.name || it.product_name}</strong><br/><span style="color:#64748b; font-size: 8pt;">SKU: ${it.sku || 'N/A'}</span></td>
                                        <td style="border: 1px solid #cbd5e1; padding: 6px 8px;">${it.package_unit || 'Pcs'}</td>
                                        <td style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center;">${it.packs || 1}</td>
                                        <td style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; font-weight: bold;">${it.quantity || it.total_units}</td>
                                        <td style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right;">${Number(it.unit_cost).toLocaleString()}</td>
                                        <td style="border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right; font-weight: bold;">${Number(it.total || it.line_total).toLocaleString()}</td>
                                    </tr>
                                `,
                                      )
                                      .join('')
                                : `
                                    <tr>
                                        <td colspan="6" style="border: 1px solid #cbd5e1; padding: 8px;">${targetPO.items}</td>
                                    </tr>
                                `
                        }
                        <tr style="background-color: #f8fafc; font-weight: bold;">
                            <td colspan="5" style="border: 1px solid #cbd5e1; padding: 8px; text-align: right;">Grand Total (KES):</td>
                            <td style="border: 1px solid #cbd5e1; padding: 8px; text-align: right; color: #047857; font-size: 11pt;">KES ${Number(targetPO.amount).toLocaleString()}</td>
                        </tr>
                    </tbody>
                </table>

                <table style="width: 100%; margin-top: 50px; font-size: 9pt;">
                    <tr>
                        <td style="width: 50%; padding-top: 40px; border-top: 1px solid #94a3b8; text-align: center;">
                            <strong>Authorized By (${targetPO.createdByName || 'Procurement Officer'})</strong><br/>
                            Signature & Date
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 40%; padding-top: 40px; border-top: 1px solid #94a3b8; text-align: center;">
                            <strong>Received By (Warehouse Storekeeper)</strong><br/>
                            Signature & Date
                        </td>
                    </tr>
                </table>
            </div>
        `;
    });

    const fullHtml = `
        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
        <head>
            <meta charset='utf-8'>
            <title>Purchase Order</title>
            <style>
                body { font-family: 'Calibri', 'Arial', sans-serif; margin: 15mm; }
            </style>
        </head>
        <body>
            ${bodyHtml}
        </body>
        </html>
    `;

    const blob = new Blob(['\ufeff' + fullHtml], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);

    downloadModalOpen.value = false;
    success('Word Document Exported', `Downloaded ${filename}`);
};

const printablePO = ref<any>(null);
const printPOModalOpen = ref(false);

const openPrintPdf = (po?: any) => {
    printablePO.value = po || purchaseOrders.value[0] || null;
    printPOModalOpen.value = true;
    downloadModalOpen.value = false;
};

const triggerBrowserPrint = () => {
    window.print();
};

const getPOStructuredItems = (po: any) => {
    if (!po?.notes) return null;
    try {
        const parsed = JSON.parse(po.notes);
        return Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
};
</script>

<template>
    <Head title="Purchase Orders & Procurement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-8 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header Section with Buttons on the Exact Same Alignment -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-foreground flex items-center gap-2">
                        <Truck class="size-6 text-primary shrink-0" />
                        <span>Purchase Orders (PO) & Local Purchase Orders (LPO)</span>
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Manage supplier purchase orders, local market purchasing (LPO), packaging units, real-time inventory levels, and automated GRN receipt.
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0 flex-wrap sm:flex-nowrap">
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-border shadow-xs whitespace-nowrap"
                        @click="openDownloadModal()"
                    >
                        <Download class="size-3.5 text-muted-foreground" />
                        <span>Download Orders</span>
                    </Button>

                    <Button
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-amber-500/40 text-amber-600 dark:text-amber-400 bg-amber-500/10 hover:bg-amber-500/20 font-semibold shadow-xs whitespace-nowrap"
                        @click="openCreateLPOModal()"
                    >
                        <Receipt class="size-3.5" />
                        <span>New Local PO (LPO)</span>
                    </Button>

                    <Button
                        size="sm"
                        class="h-9 text-xs gap-1.5 bg-primary shadow-xs whitespace-nowrap"
                        @click="openCreatePOModal('bulk')"
                    >
                        <Boxes class="size-3.5" />
                        <span>New Bulk Purchase Order</span>
                    </Button>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Procurement Value"
                    :value="formatCurrency(totalProcurementValue)"
                    change-text="across all active PO & LPO orders"
                    :icon="Boxes"
                    icon-color="text-primary"
                />
                <StatCard
                    title="Contract Purchase Orders (PO)"
                    :value="`${poList.length} Orders`"
                    :sub-value="formatCurrency(totalPOValue)"
                    :icon="FileText"
                    icon-color="text-sky-600"
                />
                <StatCard
                    title="Local Purchase Orders (LPO)"
                    :value="`${lpoList.length} Orders`"
                    :sub-value="formatCurrency(totalLPOValue)"
                    :icon="Receipt"
                    icon-color="text-amber-600"
                />
                <StatCard
                    title="Stocked & Received (GRN)"
                    :value="`${receivedPOCount} Orders`"
                    :sub-value="`${pendingPOCount} pending delivery`"
                    :icon="PackageCheck"
                    icon-color="text-emerald-600"
                />
            </div>

            <!-- Tabs & Search Toolbar on the Exact Same Horizontal Alignment -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 rounded-xl border border-border bg-card p-2.5 sm:p-3 shadow-xs">
                <!-- Left: Segmented Tab Switcher (PO / LPO / ALL) -->
                <div class="inline-flex items-center rounded-lg border border-border bg-muted/40 p-1 text-xs font-semibold gap-1 shrink-0 overflow-x-auto scrollbar-none">
                    <button
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5 cursor-pointer whitespace-nowrap',
                            activeDocType === 'ALL'
                                ? 'bg-background text-foreground shadow-xs font-bold'
                                : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeDocType = 'ALL'"
                    >
                        <Layers class="size-3.5" />
                        <span>All Documents</span>
                        <span class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-foreground font-mono">{{ purchaseOrders.length }}</span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5 cursor-pointer whitespace-nowrap',
                            activeDocType === 'PO'
                                ? 'bg-background text-sky-600 dark:text-sky-400 shadow-xs font-bold'
                                : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeDocType = 'PO'"
                    >
                        <FileText class="size-3.5 text-sky-500" />
                        <span>Purchase Orders (PO)</span>
                        <span class="rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 px-1.5 py-0.5 text-[10px] font-mono">{{ poList.length }}</span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5 cursor-pointer whitespace-nowrap',
                            activeDocType === 'LPO'
                                ? 'bg-background text-amber-600 dark:text-amber-400 shadow-xs font-bold'
                                : 'text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeDocType = 'LPO'"
                    >
                        <Receipt class="size-3.5 text-amber-500" />
                        <span>Local PO (LPO)</span>
                        <span class="rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 text-[10px] font-mono">{{ lpoList.length }}</span>
                    </button>
                </div>

                <!-- Right: Prominent Search Bar + Status Filter on the Exact Same Alignment -->
                <div class="flex flex-1 items-center justify-end gap-2 w-full lg:w-auto">
                    <!-- Prominent Search Bar -->
                    <div class="relative flex-1 sm:max-w-md w-full">
                        <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            v-model="tableSearchQuery"
                            type="text"
                            placeholder="Search reference, vendor, branch, purpose, items..."
                            class="h-9 pl-9 pr-8 text-xs bg-muted/20 hover:bg-muted/30 focus:bg-background transition-colors w-full rounded-lg border-border"
                        />
                        <button
                            v-if="tableSearchQuery"
                            type="button"
                            class="absolute right-2.5 top-2 text-muted-foreground hover:text-foreground p-0.5 cursor-pointer"
                            @click="tableSearchQuery = ''"
                            title="Clear search"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Status Filter Dropdown -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <select
                            v-model="tableStatusFilter"
                            class="h-9 text-xs rounded-lg border border-border bg-background px-2.5 py-1 text-foreground focus:outline-hidden focus:ring-1 focus:ring-primary cursor-pointer whitespace-nowrap"
                        >
                            <option value="ALL">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Received">Received</option>
                            <option value="Approved">Approved</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Purchase Orders & LPO Scrollable DataTable -->
            <div class="space-y-4">
                <DataTable
                    :columns="columns"
                    :data="displayedOrders"
                    :searchable="false"
                    max-height="max-h-[600px]"
                    :sticky-header="true"
                    :page-size="15"
                >
                    <!-- Reference Column (PO or LPO) -->
                    <template #cell-poNumber="{ row }">
                        <div>
                            <div class="flex items-center gap-1.5 font-mono font-bold">
                                <Badge
                                    :variant="row.documentType === 'LPO' ? 'outline' : 'secondary'"
                                    :class="[
                                        'text-[9px] px-1.5 py-0 font-bold uppercase tracking-wider',
                                        row.documentType === 'LPO'
                                            ? 'border-amber-500/40 text-amber-600 dark:text-amber-400 bg-amber-500/10'
                                            : 'border-sky-500/40 text-sky-600 dark:text-sky-400 bg-sky-500/10'
                                    ]"
                                >
                                    {{ row.documentType === 'LPO' ? 'LPO' : 'PO' }}
                                </Badge>
                                <span :class="row.documentType === 'LPO' ? 'text-amber-600 dark:text-amber-400' : 'text-primary'">
                                    {{ row.documentType === 'LPO' ? (row.lpoNumber || row.poNumber) : row.poNumber }}
                                </span>
                            </div>
                            <div class="text-[10px] text-muted-foreground flex items-center gap-1 mt-0.5 font-mono">
                                <Clock class="size-3" />
                                <span>{{ row.orderTime || row.date }}</span>
                            </div>
                            <div class="text-[10px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                <User class="size-3" />
                                <span>By: {{ row.createdByName || 'Admin' }}</span>
                            </div>
                        </div>
                    </template>

                    <!-- Type & Purpose Column -->
                    <template #cell-lpoPurpose="{ row }">
                        <div>
                            <div v-if="row.documentType === 'LPO'" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/20">
                                <Receipt class="size-3" />
                                <span>{{ row.lpoPurpose || 'Local Purchase' }}</span>
                            </div>
                            <div v-else class="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground bg-muted/40 px-2 py-0.5 rounded-md">
                                <Boxes class="size-3 text-sky-500" />
                                <span>Vendor Contract PO</span>
                            </div>
                        </div>
                    </template>

                    <!-- Supplier / Vendor -->
                    <template #cell-supplier="{ row }">
                        <div>
                            <span class="font-bold text-foreground text-xs">{{ row.supplier }}</span>
                            <div class="text-[10px] text-muted-foreground">Due: {{ row.expectedDate }}</div>
                        </div>
                    </template>

                    <!-- Receiving Branch -->
                    <template #cell-branch="{ row }">
                        <div class="flex items-center gap-1.5 text-xs">
                            <Building2 class="size-3.5 text-muted-foreground" />
                            <span>{{ row.branch }}</span>
                        </div>
                    </template>

                    <!-- Procurement Summary -->
                    <template #cell-items="{ row }">
                        <div class="max-w-[280px] truncate" :title="row.items">
                            <span class="text-xs text-foreground font-medium">{{ row.items }}</span>
                        </div>
                    </template>

                    <!-- Total Amount -->
                    <template #cell-amount="{ row }">
                        <span class="font-mono font-bold text-foreground text-xs">{{ formatCurrency(row.amount) }}</span>
                    </template>

                    <!-- Delivery Status & Sync Badge -->
                    <template #cell-status="{ row }">
                        <div class="flex flex-col items-center gap-1">
                            <Badge
                                :variant="row.status === 'Received' ? 'success' : row.status === 'Approved' ? 'info' : 'warning'"
                                class="text-[10px] py-0.5"
                            >
                                {{ row.status === 'Received' ? 'Stock Received' : row.status }}
                            </Badge>
                            <!-- Synced to Stock Indicator -->
                            <div
                                v-if="row.status === 'Received'"
                                class="inline-flex items-center gap-1 text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold"
                                :title="`Stock levels adjusted and synced (${row.syncedAt || row.date})`"
                            >
                                <RefreshCw class="size-2.5 text-emerald-500 animate-none" />
                                <span>Stock Synced</span>
                            </div>
                        </div>
                    </template>

                    <!-- Action Column (View, Download, Sync/Receive, Delete) -->
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                            <!-- 1. View Details Icon Button -->
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-muted-foreground hover:text-foreground shrink-0"
                                @click="openPODetail(row)"
                                title="View Purchase Order Details (When, Who, Items)"
                            >
                                <Eye class="size-3.5 text-primary" />
                            </Button>

                            <!-- 2. Download Icon Button (PDF / Word / CSV) -->
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-muted-foreground hover:text-primary shrink-0"
                                @click="openDownloadModal(row)"
                                title="Download as PDF, Word, or CSV"
                            >
                                <Download class="size-3.5" />
                            </Button>

                            <!-- 3. Sync / Receive Stock Button -->
                            <Button
                                v-if="row.status !== 'Received'"
                                size="sm"
                                class="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 font-semibold shadow-xs gap-1 shrink-0 px-2"
                                @click="openReceiveModal(row)"
                                title="Verify Items & Synchronize into Inventory"
                            >
                                <RefreshCw class="size-3" />
                                <span>Receive</span>
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs border-emerald-500/30 text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20 font-semibold gap-1 shrink-0 px-2"
                                @click="handleResyncStock(row)"
                                title="Stock Synchronized. Click to view status"
                            >
                                <CheckCircle2 class="size-3" />
                                <span>Synced ✓</span>
                            </Button>

                            <!-- 4. Delete PO -->
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive shrink-0"
                                @click="handleDeletePO(row)"
                                title="Cancel Order"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL: BULK & STANDARD PURCHASE ORDER BUILDER -->
        <!-- ============================================================ -->
        <Dialog :open="newPOModal" @update:open="(v) => (newPOModal = v)">
            <DialogContent class="w-[98vw] max-w-6xl xl:max-w-7xl max-h-[94vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Boxes class="size-5 text-primary" />
                        Create Purchase Order (PO)
                    </DialogTitle>
                </DialogHeader>

                <!-- Order Mode Tabs -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-border pb-2 pt-1 gap-2">
                    <div class="flex rounded-lg border border-border bg-muted/30 p-1 text-xs font-semibold">
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5',
                                creationMode === 'bulk' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'
                            ]"
                            @click="creationMode = 'bulk'"
                        >
                            <Layers class="size-3.5" />
                            Bulk Item Procurement Builder
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5',
                                creationMode === 'quick' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'
                            ]"
                            @click="creationMode = 'quick'"
                        >
                            <FileText class="size-3.5" />
                            Quick PO (Summary)
                        </button>
                    </div>

                    <Button
                        v-if="creationMode === 'bulk'"
                        size="sm"
                        variant="outline"
                        class="h-8 text-[11px] gap-1 bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 font-semibold"
                        @click="autoPopulateLowStock"
                    >
                        <Sparkles class="size-3" />
                        Auto-Fill Low Stock Items
                    </Button>
                </div>

                <div class="space-y-4 py-2 text-xs">
                    <!-- General Order Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/20 rounded-xl border border-border/60">
                        <div>
                            <Label class="text-xs font-semibold">Supplier / Vendor</Label>
                            <select
                                v-model="newPO.supplier_id"
                                class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs focus:ring-1 focus:ring-primary shadow-xs"
                            >
                                <option :value="null">-- Select Vendor --</option>
                                <option v-for="s in props.suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-xs font-semibold">Receiving Branch</Label>
                            <select
                                v-model="newPO.branch_id"
                                class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs focus:ring-1 focus:ring-primary shadow-xs"
                            >
                                <option :value="null">-- Select Branch --</option>
                                <option v-for="b in props.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-xs font-semibold">Expected Delivery Date</Label>
                            <Input v-model="newPO.expected_date" type="date" class="mt-1 h-9 text-xs font-mono shadow-xs" />
                        </div>
                    </div>

                    <!-- BULK MODE: Interactive Line Items Builder -->
                    <div v-if="creationMode === 'bulk'" class="space-y-3">
                        <!-- Lines Table Header & Add Button -->
                        <div class="flex items-center justify-between pt-1">
                            <span class="font-bold text-foreground flex items-center gap-1.5 text-xs">
                                <Package class="size-4 text-primary" />
                                Procurement Line Items ({{ bulkItems.length }})
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs gap-1 font-semibold"
                                @click="addBulkLineItem"
                            >
                                <Plus class="size-3.5" />
                                Add Line Item
                            </Button>
                        </div>

                        <!-- Multi-Item Table (All inputs visible and clear) -->
                        <div class="rounded-xl border border-border bg-card overflow-hidden shadow-xs">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-muted/60 border-b border-border text-muted-foreground font-semibold">
                                        <tr>
                                            <th class="p-2.5 min-w-[240px]">Product / Item</th>
                                            <th class="p-2.5 w-[140px]">Packaging Unit</th>
                                            <th class="p-2.5 text-center w-[90px]">Units/Pack</th>
                                            <th class="p-2.5 text-center w-[95px]">Packs Qty</th>
                                            <th class="p-2.5 text-center w-[85px] bg-muted/30">Current</th>
                                            <th class="p-2.5 text-center w-[95px]">Order Units</th>
                                            <th class="p-2.5 text-center w-[95px] bg-emerald-500/10">Projected</th>
                                            <th class="p-2.5 text-right w-[115px]">Unit Cost (KES)</th>
                                            <th class="p-2.5 text-right w-[125px]">Subtotal</th>
                                            <th class="p-2.5 text-center w-[44px]"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr v-for="(item, idx) in bulkItems" :key="idx" class="hover:bg-muted/20 transition-colors">
                                            <!-- Product Item Picker -->
                                            <td class="p-2">
                                                <select
                                                    v-if="props.products && props.products.length > 0"
                                                    :value="item.product_id"
                                                    @change="(e: any) => handleSelectProductForLine(item, Number(e.target.value))"
                                                    class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs font-semibold focus:ring-1 focus:ring-primary shadow-xs"
                                                >
                                                    <option :value="null">-- Select Catalog Item --</option>
                                                    <option v-for="p in props.products" :key="p.id" :value="p.id">
                                                        {{ p.name }} (Stock: {{ p.stock_quantity ?? 0 }})
                                                    </option>
                                                </select>
                                                <Input
                                                    v-else
                                                    v-model="item.product_name"
                                                    placeholder="Product description"
                                                    class="h-9 text-xs font-semibold shadow-xs"
                                                />
                                            </td>

                                            <!-- Packaging Unit -->
                                            <td class="p-2">
                                                <select
                                                    v-model="item.package_unit"
                                                    class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs focus:ring-1 focus:ring-primary shadow-xs"
                                                >
                                                    <option v-for="u in packageUnits" :key="u" :value="u">{{ u }}</option>
                                                </select>
                                            </td>

                                            <!-- Units per Pack -->
                                            <td class="p-2 text-center">
                                                <Input
                                                    v-model.number="item.units_per_pack"
                                                    type="number"
                                                    min="1"
                                                    @input="updateBulkLineCalculations(item)"
                                                    class="h-9 text-center font-mono text-xs w-full shadow-xs"
                                                />
                                            </td>

                                            <!-- Packs Quantity -->
                                            <td class="p-2 text-center">
                                                <Input
                                                    v-model.number="item.packs_quantity"
                                                    type="number"
                                                    min="1"
                                                    @input="updateBulkLineCalculations(item)"
                                                    class="h-9 text-center font-mono font-bold text-xs w-full text-primary shadow-xs"
                                                />
                                            </td>

                                            <!-- Current Stock -->
                                            <td class="p-2 text-center">
                                                <div class="h-9 flex items-center justify-center font-mono font-semibold text-xs text-muted-foreground bg-muted/20 rounded-md">
                                                    {{ item.current_stock ?? 0 }}
                                                </div>
                                            </td>

                                            <!-- Order Units (Readonly) -->
                                            <td class="p-2 text-center">
                                                <div class="h-9 flex items-center justify-center font-mono font-bold text-xs text-foreground bg-muted/10 rounded-md">
                                                    +{{ item.total_units }}
                                                </div>
                                            </td>

                                            <!-- Projected Total Units After Receipt -->
                                            <td class="p-2 text-center">
                                                <div class="h-9 flex items-center justify-center font-mono font-black text-xs text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 rounded-md border border-emerald-500/20">
                                                    {{ item.projected_stock }}
                                                </div>
                                            </td>

                                            <!-- Unit Cost -->
                                            <td class="p-2 text-right">
                                                <Input
                                                    v-model.number="item.unit_cost"
                                                    type="number"
                                                    min="0"
                                                    @input="updateBulkLineCalculations(item)"
                                                    class="h-9 text-right font-mono text-xs w-full shadow-xs"
                                                />
                                            </td>

                                            <!-- Subtotal -->
                                            <td class="p-2 text-right">
                                                <div class="h-9 flex items-center justify-end font-mono font-bold text-xs text-foreground pr-1">
                                                    {{ formatCurrency(item.line_total) }}
                                                </div>
                                            </td>

                                            <!-- Delete Row -->
                                            <td class="p-2 text-center">
                                                <div class="h-9 flex items-center justify-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-8 text-muted-foreground hover:text-destructive"
                                                        @click="removeBulkLineItem(idx)"
                                                        title="Remove Row"
                                                    >
                                                        <Trash2 class="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Bottom Row Action -->
                            <div class="p-2 bg-muted/20 border-t border-border/60 flex items-center justify-between">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    class="h-7 text-xs gap-1 text-primary hover:text-primary font-semibold"
                                    @click="addBulkLineItem"
                                >
                                    <Plus class="size-3.5" />
                                    Add Another Item
                                </Button>
                                <span class="text-[11px] text-muted-foreground">
                                    {{ bulkItems.length }} item{{ bulkItems.length === 1 ? '' : 's' }} in procurement order
                                </span>
                            </div>
                        </div>

                        <!-- Order Summary Card with Current vs Projected Stock -->
                        <div class="p-3 bg-muted/30 rounded-xl border border-border flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-4 text-xs">
                                <div>
                                    <span class="text-muted-foreground">Line Items:</span>
                                    <span class="font-bold text-foreground ml-1">{{ bulkItems.length }}</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Current Stock in Hand:</span>
                                    <span class="font-mono font-bold text-muted-foreground ml-1">{{ calculatedCurrentStock }} units</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Order Units:</span>
                                    <span class="font-mono font-bold text-primary ml-1">+{{ calculatedTotalUnits }} units</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Projected Warehouse Stock:</span>
                                    <span class="font-mono font-black text-emerald-600 dark:text-emerald-400 ml-1">
                                        {{ calculatedProjectedStock }} units
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] text-muted-foreground mr-2">Total Procurement Value:</span>
                                <span class="text-base font-black text-foreground font-mono">
                                    {{ formatCurrency(calculatedBulkTotal) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- QUICK MODE: Standard text description & amount -->
                    <div v-else class="space-y-3">
                        <div>
                            <Label class="text-xs">Items & Specifications</Label>
                            <Input
                                v-model="newPO.items_description"
                                placeholder="e.g. Pembe Maize Flour 2kg (100 bales), Sugar 1kg (50 cartons)"
                                class="mt-1 text-xs"
                            />
                        </div>

                        <div>
                            <Label class="text-xs">Total Order Value (KES)</Label>
                            <Input
                                v-model.number="newPO.total_amount"
                                type="number"
                                class="mt-1 text-xs font-mono font-bold"
                            />
                        </div>

                        <div>
                            <Label class="text-xs">Delivery Instructions / Notes</Label>
                            <Input
                                v-model="newPO.notes"
                                placeholder="e.g. Deliver to Industrial Area Warehouse loading bay 3"
                                class="mt-1 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" @click="newPOModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary font-bold" @click="handleCreatePO">
                        Dispatch Purchase Order
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: CREATE LOCAL PURCHASE ORDER (LPO) -->
        <!-- ============================================================ -->
        <Dialog :open="newLPOModal" @update:open="(v) => (newLPOModal = v)">
            <DialogContent class="w-[96vw] sm:max-w-xl max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <div class="size-8 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <Receipt class="size-4" />
                        </div>
                        <div>
                            <span>Create Local Purchase Order (LPO)</span>
                            <p class="text-[11px] text-muted-foreground font-normal mt-0.5">
                                Issue an official local purchase order for local market buying, emergency repairs, perishables, or branch supplies.
                            </p>
                        </div>
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-2 text-xs">
                    <!-- Reference and Purpose -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Document Reference</Label>
                            <div class="mt-1 h-9 px-3 rounded-md border border-border bg-muted/30 flex items-center justify-between">
                                <span class="font-bold text-amber-600 dark:text-amber-400 font-mono">LPO Voucher</span>
                                <Badge variant="outline" class="text-[9px] border-amber-500/30 text-amber-600 bg-amber-500/10">Auto-assigned</Badge>
                            </div>
                        </div>

                        <div>
                            <Label class="text-xs font-semibold">LPO Purpose / Category <span class="text-destructive">*</span></Label>
                            <select
                                v-model="newLPO.lpo_purpose"
                                class="mt-1 w-full h-9 text-xs rounded-md border border-border bg-background px-3 text-foreground focus:outline-hidden focus:ring-1 focus:ring-amber-500 cursor-pointer"
                            >
                                <option v-for="purpose in lpoPurposes" :key="purpose" :value="purpose">
                                    {{ purpose }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Vendor / Supplier and Branch -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Local Vendor / Merchant</Label>
                            <select
                                v-model="newLPO.supplier_id"
                                class="mt-1 w-full h-9 text-xs rounded-md border border-border bg-background px-3 text-foreground focus:outline-hidden focus:ring-1 focus:ring-amber-500 cursor-pointer"
                            >
                                <option :value="null">Direct Cash / Local Merchant</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-xs">Requesting Branch</Label>
                            <select
                                v-model="newLPO.branch_id"
                                class="mt-1 w-full h-9 text-xs rounded-md border border-border bg-background px-3 text-foreground focus:outline-hidden focus:ring-1 focus:ring-amber-500 cursor-pointer"
                            >
                                <option :value="null">Main Store / HQ</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Expected Date & Estimated Amount -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Required Delivery Date</Label>
                            <Input
                                v-model="newLPO.expected_date"
                                type="date"
                                class="mt-1 text-xs"
                            />
                        </div>

                        <div>
                            <Label class="text-xs font-semibold">Estimated Total Value (KES) <span class="text-destructive">*</span></Label>
                            <Input
                                v-model.number="newLPO.total_amount"
                                type="number"
                                min="0"
                                step="100"
                                placeholder="e.g. 5000"
                                class="mt-1 text-xs font-mono font-bold"
                            />
                        </div>
                    </div>

                    <!-- Items / Requirements Description -->
                    <div>
                        <Label class="text-xs font-semibold">Items & Specifications / Scope <span class="text-destructive">*</span></Label>
                        <textarea
                            v-model="newLPO.items_description"
                            rows="3"
                            placeholder="Describe items, specifications, units, or service scope (e.g. 5x 18W Philips LED Batten Tubes, 2 rolls insulated 2.5mm copper cable, emergency electrical rewiring labor)"
                            class="mt-1 w-full rounded-md border border-border bg-background p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:outline-hidden focus:ring-1 focus:ring-amber-500"
                        ></textarea>
                    </div>

                    <!-- Internal Notes & Reason -->
                    <div>
                        <Label class="text-xs">Reason / Approval Justification</Label>
                        <Input
                            v-model="newLPO.notes"
                            placeholder="e.g. Urgent repair for bakery chiller lighting; approved by Branch Manager"
                            class="mt-1 text-xs"
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" @click="newLPOModal = false">Cancel</Button>
                    <Button
                        size="sm"
                        class="bg-amber-600 hover:bg-amber-700 font-bold text-white shadow-xs gap-1.5"
                        @click="handleCreateLPO"
                    >
                        <Receipt class="size-3.5" />
                        Generate Local Purchase Order
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: GOODS RECEIVING & STOCK ADJUSTMENT SYNC WITH CHECKBOXES -->
        <!-- ============================================================ -->
        <Dialog :open="receiveModalOpen" @update:open="(v) => (receiveModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-2xl max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <RefreshCw class="size-5 text-emerald-600" />
                        Receive & Synchronize Stock: {{ receivingPO?.documentType === 'LPO' ? (receivingPO?.lpoNumber || receivingPO?.poNumber) : receivingPO?.poNumber }}
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-2 text-xs">
                    <!-- Purchase Audit Details Card (WHO, WHEN, SUPPLIER) -->
                    <div class="p-3 bg-muted/40 rounded-xl border border-border/80 grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">When Ordered</span>
                            <span class="font-bold text-foreground font-mono">{{ receivingPO?.orderTime || receivingPO?.date }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">Ordered By</span>
                            <span class="font-bold text-foreground">{{ receivingPO?.createdByName || 'Procurement Officer' }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">Vendor / Supplier</span>
                            <span class="font-bold text-foreground truncate block">{{ receivingPO?.supplier }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">Receiving Branch</span>
                            <span class="font-bold text-foreground">{{ receivingPO?.branch }}</span>
                        </div>
                    </div>

                    <!-- Items Checklist with Checkboxes -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                <PackageCheck class="size-4 text-emerald-600" />
                                Check Items Received & Verified:
                            </span>
                            <button
                                type="button"
                                @click="toggleSelectAllReceiveItems"
                                class="text-[11px] text-primary hover:underline font-semibold cursor-pointer"
                            >
                                {{ selectedReceiveProductIds.length === (getPOStructuredItems(receivingPO)?.length || 0) ? 'Deselect All' : 'Select All' }}
                            </button>
                        </div>

                        <!-- Structured items list -->
                        <div v-if="getPOStructuredItems(receivingPO)" class="border rounded-xl divide-y divide-border/60 overflow-hidden bg-card">
                            <div
                                v-for="(item, idx) in getPOStructuredItems(receivingPO)"
                                :key="idx"
                                :class="[
                                    'p-3 flex items-center justify-between transition-colors',
                                    selectedReceiveProductIds.includes(item.product_id) ? 'bg-emerald-500/5' : 'bg-muted/10 opacity-70'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        :checked="selectedReceiveProductIds.includes(item.product_id)"
                                        @change="toggleReceiveItem(item.product_id)"
                                        class="size-4 rounded border-input text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                    />
                                    <div>
                                        <div class="font-bold text-foreground text-xs">{{ item.name || item.product_name }}</div>
                                        <div class="text-[11px] text-muted-foreground font-mono">
                                            <span>SKU: {{ item.sku || 'N/A' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ item.packs || 1 }} {{ item.package_unit || 'Pcs' }}</span>
                                            <span class="mx-1">•</span>
                                            <span class="font-bold text-foreground">+{{ item.quantity || item.total_units }} units to add</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right font-mono">
                                    <span class="font-black text-xs text-foreground">{{ formatCurrency(item.total || item.line_total) }}</span>
                                    <div class="text-[10px] text-emerald-600 font-semibold">
                                        {{ selectedReceiveProductIds.includes(item.product_id) ? 'Will Sync ✓' : 'Skip Sync' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fallback simple PO -->
                        <div v-else class="p-3 bg-muted/20 rounded-xl border flex items-center gap-3">
                            <input
                                type="checkbox"
                                checked
                                class="size-4 rounded border-input text-emerald-600 focus:ring-emerald-500"
                            />
                            <div class="flex-1">
                                <div class="font-bold text-foreground">{{ receivingPO?.items }}</div>
                                <div class="text-[11px] text-muted-foreground font-mono">Total Value: {{ formatCurrency(receivingPO?.amount || 0) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Receiving Notes & Audit Remark -->
                    <div>
                        <Label class="text-xs">Goods Receiving Notes / Condition (Optional)</Label>
                        <Input
                            v-model="receivingNotes"
                            placeholder="e.g. All packs inspected and verified in warehouse bay #2"
                            class="mt-1 text-xs"
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" @click="receiveModalOpen = false">Cancel</Button>
                    <Button
                        size="sm"
                        class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 font-bold shadow-xs text-white"
                        @click="handleConfirmReceiveAndSync"
                    >
                        <RefreshCw class="size-3.5" />
                        Confirm & Sync to Inventory
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: PO BREAKDOWN & LINE ITEMS DETAIL (VIEW ICON) -->
        <!-- ============================================================ -->
        <Dialog :open="detailModalOpen" @update:open="(v) => (detailModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <Receipt v-if="detailPO?.documentType === 'LPO'" class="size-4 text-amber-500" />
                            <FileText v-else class="size-4 text-primary" />
                            <span>
                                {{ detailPO?.documentType === 'LPO' ? 'Local Purchase Order' : 'Purchase Order' }}:
                                {{ detailPO?.documentType === 'LPO' ? (detailPO?.lpoNumber || detailPO?.poNumber) : detailPO?.poNumber }}
                            </span>
                        </span>
                        <Button size="sm" variant="outline" class="h-7 text-xs gap-1" @click="openDownloadModal(detailPO)">
                            <Download class="size-3 text-primary" />
                            Export
                        </Button>
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-2 text-xs">
                    <!-- Complete PO Metadata (WHO, WHEN, TIME, VENDOR, BRANCH) -->
                    <div class="p-3 bg-muted/40 rounded-xl border border-border/80 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">When Ordered</span>
                            <span class="font-bold text-foreground font-mono">{{ detailPO?.orderTime || detailPO?.date }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">Ordered By</span>
                            <span class="font-bold text-foreground">{{ detailPO?.createdByName || 'Procurement Officer' }}</span>
                            <div v-if="detailPO?.createdByEmail" class="text-[10px] text-muted-foreground truncate">{{ detailPO?.createdByEmail }}</div>
                        </div>
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">Supplier / Vendor</span>
                            <span class="font-bold text-foreground truncate block">{{ detailPO?.supplier }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground text-[10px] block uppercase font-mono">Receiving Branch</span>
                            <span class="font-bold text-foreground">{{ detailPO?.branch }}</span>
                        </div>
                    </div>

                    <!-- LPO Purpose Card if LPO -->
                    <div
                        v-if="detailPO?.documentType === 'LPO'"
                        class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <Receipt class="size-4 text-amber-600 dark:text-amber-400" />
                            <span class="font-semibold text-xs">LPO Category / Purpose:</span>
                            <span class="font-bold">{{ detailPO?.lpoPurpose || 'General Local Procurement' }}</span>
                        </div>
                        <Badge variant="outline" class="text-[10px] border-amber-500/30 text-amber-600 bg-amber-500/10">Local Purchase Voucher</Badge>
                    </div>

                    <!-- Sync Status Banner -->
                    <div
                        v-if="detailPO?.status === 'Received'"
                        class="flex items-center justify-between p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-semibold"
                    >
                        <div class="flex items-center gap-1.5">
                            <CheckCircle2 class="size-4 text-emerald-600" />
                            <span>Stock Synchronized into Inventory</span>
                        </div>
                        <span class="font-mono text-[11px]">Synced: {{ detailPO?.syncedAt || detailPO?.orderTime }}</span>
                    </div>

                    <!-- Itemized List Table -->
                    <div v-if="parsedDetailItems" class="space-y-2">
                        <span class="font-bold text-foreground text-xs">Procurement Line Items</span>
                        <div class="border rounded-xl divide-y divide-border/60 overflow-hidden">
                            <div
                                v-for="(item, idx) in parsedDetailItems"
                                :key="idx"
                                class="p-2.5 flex items-center justify-between bg-card text-xs"
                            >
                                <div>
                                    <div class="font-bold text-foreground">{{ item.name }}</div>
                                    <div class="text-[11px] text-muted-foreground font-mono">
                                        {{ item.packs }} {{ item.package_unit }} •
                                        <span class="text-foreground font-semibold">{{ item.quantity }} units</span>
                                        @ {{ formatCurrency(item.unit_cost) }}
                                        <span v-if="item.current_stock !== undefined" class="text-[10px] text-muted-foreground ml-1">
                                            (Prior Stock: {{ item.current_stock }})
                                        </span>
                                    </div>
                                </div>
                                <div class="font-mono font-bold text-foreground">
                                    {{ formatCurrency(item.total) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-3 bg-muted/20 rounded-lg text-muted-foreground">
                        {{ detailPO?.items }}
                    </div>

                    <!-- Total Row -->
                    <div class="flex items-center justify-between p-3 rounded-lg bg-muted/40 border font-bold">
                        <span>Total Procurement Order Value:</span>
                        <span class="text-sm text-primary font-mono font-black">{{ formatCurrency(detailPO?.amount || 0) }}</span>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" @click="detailModalOpen = false">Close</Button>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="detailPO?.status !== 'Received'"
                            size="sm"
                            class="bg-emerald-600 hover:bg-emerald-700 font-bold gap-1"
                            @click="detailModalOpen = false; openReceiveModal(detailPO)"
                        >
                            <RefreshCw class="size-3.5" />
                            Receive & Sync Stock
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: DOWNLOAD EXPORT OPTIONS (PDF, WORD, CSV) -->
        <!-- ============================================================ -->
        <Dialog :open="downloadModalOpen" @update:open="(v) => (downloadModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Download class="size-5 text-primary" />
                        Download Purchase Order Document
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-3 py-2 text-xs">
                    <p class="text-muted-foreground text-xs">
                        <span v-if="selectedPOForDownload">
                            Exporting <strong>{{ selectedPOForDownload.documentType === 'LPO' ? (selectedPOForDownload.lpoNumber || selectedPOForDownload.poNumber) : selectedPOForDownload.poNumber }}</strong> (Vendor: {{ selectedPOForDownload.supplier }}).
                        </span>
                        <span v-else>
                            Exporting all active Purchase & Local Purchase Orders for <strong>{{ currentTenant.name || 'Store' }}</strong>.
                        </span>
                        Choose your desired document format:
                    </p>

                    <div class="grid grid-cols-1 gap-2.5">
                        <!-- Option 1: PDF Document -->
                        <button
                            type="button"
                            @click="openPrintPdf(selectedPOForDownload)"
                            class="p-3 rounded-xl border border-border/80 bg-card hover:bg-muted/40 transition-all flex items-center gap-3 text-left cursor-pointer group shadow-xs"
                        >
                            <div class="size-10 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-600 flex items-center justify-center shrink-0">
                                <Printer class="size-5" />
                            </div>
                            <div class="flex-1">
                                <div class="font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-1.5">
                                    <span>Adobe PDF / Print Document</span>
                                    <Badge variant="outline" class="text-[9px] py-0 px-1 font-mono">.pdf</Badge>
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                    Formal printed voucher with company logo, header, itemized table & signature blocks.
                                </p>
                            </div>
                        </button>

                        <!-- Option 2: Word Document -->
                        <button
                            type="button"
                            @click="downloadWord(selectedPOForDownload)"
                            class="p-3 rounded-xl border border-border/80 bg-card hover:bg-muted/40 transition-all flex items-center gap-3 text-left cursor-pointer group shadow-xs"
                        >
                            <div class="size-10 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-600 flex items-center justify-center shrink-0">
                                <FileType2 class="size-5" />
                            </div>
                            <div class="flex-1">
                                <div class="font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-1.5">
                                    <span>Microsoft Word Document</span>
                                    <Badge variant="outline" class="text-[9px] py-0 px-1 font-mono">.doc / .docx</Badge>
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                    Formatted editable Word document with vendor details, line items table, and totals.
                                </p>
                            </div>
                        </button>

                        <!-- Option 3: CSV Spreadsheet -->
                        <button
                            type="button"
                            @click="downloadCsv(selectedPOForDownload)"
                            class="p-3 rounded-xl border border-border/80 bg-card hover:bg-muted/40 transition-all flex items-center gap-3 text-left cursor-pointer group shadow-xs"
                        >
                            <div class="size-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 flex items-center justify-center shrink-0">
                                <FileSpreadsheet class="size-5" />
                            </div>
                            <div class="flex-1">
                                <div class="font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-1.5">
                                    <span>Excel / CSV Spreadsheet</span>
                                    <Badge variant="outline" class="text-[9px] py-0 px-1 font-mono">.csv</Badge>
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                    Standard tabular data ready for Microsoft Excel, Google Sheets, or ERP integration.
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="ghost" size="sm" @click="downloadModalOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ============================================================ -->
        <!-- MODAL: PRINT / PDF PREVIEW -->
        <!-- ============================================================ -->
        <Dialog :open="printPOModalOpen" @update:open="(v) => (printPOModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center justify-between">
                        <span>{{ printablePO?.documentType === 'LPO' ? 'Local Purchase Order' : 'Purchase Order' }} Print Preview</span>
                        <Button size="sm" class="gap-1.5 bg-primary" @click="triggerBrowserPrint">
                            <Printer class="size-3.5" />
                            Print / Save as PDF
                        </Button>
                    </DialogTitle>
                </DialogHeader>

                <!-- Printable Voucher Mockup -->
                <div id="printable-po-voucher" class="p-6 bg-white text-black rounded-xl border border-neutral-200 text-xs space-y-4">
                    <!-- Voucher Header -->
                    <div class="flex items-start justify-between border-b pb-4">
                        <div>
                            <h3 class="text-lg font-black uppercase text-emerald-800 tracking-wider">
                                {{ currentTenant.name || 'MDE RETAIL SYSTEMS' }}
                            </h3>
                            <p class="text-[11px] text-neutral-600 font-mono">
                                {{ printablePO?.documentType === 'LPO' ? 'Official Local Purchase Order (LPO)' : 'Official Procurement Purchase Order (PO)' }}
                            </p>
                            <p class="text-[10px] text-neutral-500">Branch: {{ printablePO?.branch || 'Main Store' }}</p>
                            <p v-if="printablePO?.documentType === 'LPO' && printablePO?.lpoPurpose" class="text-[10px] text-amber-800 font-semibold mt-0.5">
                                Purpose: {{ printablePO?.lpoPurpose }}
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-base font-black font-mono text-neutral-900">
                                {{ printablePO?.documentType === 'LPO' ? (printablePO?.lpoNumber || printablePO?.poNumber) : printablePO?.poNumber }}
                            </div>
                            <div class="text-[11px] text-neutral-600">Order: {{ printablePO?.orderTime || printablePO?.date }}</div>
                            <div class="text-[11px] text-neutral-600">By: {{ printablePO?.createdByName || 'Admin' }}</div>
                        </div>
                    </div>

                    <!-- Vendor Box -->
                    <div class="p-3 bg-neutral-50 rounded-lg border border-neutral-200 flex justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-neutral-500 block">Vendor / Supplier</span>
                            <span class="font-bold text-sm text-neutral-900">{{ printablePO?.supplier }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-neutral-500 block">Payment / Delivery Status</span>
                            <span class="font-bold text-neutral-900">{{ printablePO?.status }}</span>
                        </div>
                    </div>

                    <!-- Table -->
                    <table class="w-full border-collapse border border-neutral-300 text-left text-xs">
                        <thead class="bg-neutral-100 font-bold">
                            <tr>
                                <th class="border border-neutral-300 p-2">Item Description</th>
                                <th class="border border-neutral-300 p-2">Unit</th>
                                <th class="border border-neutral-300 p-2 text-center">Packs</th>
                                <th class="border border-neutral-300 p-2 text-center">Total Units</th>
                                <th class="border border-neutral-300 p-2 text-right">Unit Cost</th>
                                <th class="border border-neutral-300 p-2 text-right">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="getPOStructuredItems(printablePO)">
                                <tr v-for="(it, idx) in getPOStructuredItems(printablePO)" :key="idx">
                                    <td class="border border-neutral-300 p-2">
                                        <div class="font-bold">{{ it.name || it.product_name }}</div>
                                        <div class="text-[10px] text-neutral-500 font-mono">{{ it.sku }}</div>
                                    </td>
                                    <td class="border border-neutral-300 p-2">{{ it.package_unit || 'Pcs' }}</td>
                                    <td class="border border-neutral-300 p-2 text-center">{{ it.packs || 1 }}</td>
                                    <td class="border border-neutral-300 p-2 text-center font-bold">{{ it.quantity || it.total_units }}</td>
                                    <td class="border border-neutral-300 p-2 text-right font-mono">{{ formatCurrency(it.unit_cost) }}</td>
                                    <td class="border border-neutral-300 p-2 text-right font-bold font-mono">{{ formatCurrency(it.total || it.line_total) }}</td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="6" class="border border-neutral-300 p-3">
                                    {{ printablePO?.items }}
                                </td>
                            </tr>
                            <tr class="bg-neutral-50 font-bold">
                                <td colspan="5" class="border border-neutral-300 p-2.5 text-right uppercase text-[11px]">Grand Total (KES):</td>
                                <td class="border border-neutral-300 p-2.5 text-right font-mono font-black text-sm text-emerald-800">
                                    {{ formatCurrency(printablePO?.amount || 0) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Authorization Signatures -->
                    <div class="grid grid-cols-2 gap-8 pt-8 text-[11px]">
                        <div class="border-t border-neutral-400 pt-2 text-center">
                            <p class="font-bold">Authorized Purchasing Officer</p>
                            <p class="text-[10px] text-neutral-500">Sign & Stamp</p>
                        </div>
                        <div class="border-t border-neutral-400 pt-2 text-center">
                            <p class="font-bold">Store Receiving Officer</p>
                            <p class="text-[10px] text-neutral-500">Sign & Date</p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="printPOModalOpen = false">Close</Button>
                    <Button size="sm" class="gap-1.5 bg-primary" @click="triggerBrowserPrint">
                        <Printer class="size-3.5" />
                        Print Document
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printable-po-voucher,
    #printable-po-voucher * {
        visibility: visible !important;
    }
    #printable-po-voucher {
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
