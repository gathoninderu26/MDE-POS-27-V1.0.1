<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useBranch } from '@/composables/useBranch';
import { useFeatures } from '@/composables/useFeatures';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import type { ProductItem } from '@/composables/useCart';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Box,
    Calendar,
    CheckCircle2,
    Compass,
    Download,
    Edit,
    ExternalLink,
    FileSpreadsheet,
    Filter,
    FolderTree,
    History,
    Layers,
    Loader2,
    Package,
    Pill,
    Plus,
    Printer,
    QrCode,
    RefreshCcw,
    RotateCcw,
    ScanBarcode,
    Search,
    Smartphone,
    SlidersHorizontal,
    Tag,
    Trash2,
    TrendingDown,
    TrendingUp,
    Truck,
    Utensils,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import BarcodeLabelModal, { type ProductLabelData } from '@/components/inventory/BarcodeLabelModal.vue';
import ProductImportModal from '@/components/products/ProductImportModal.vue';
import ProductGuideOverlay from '@/components/products/ProductGuideOverlay.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useBarcodeScanner } from '@/composables/useBarcodeScanner';
import { useBarcodeProductLookup } from '@/composables/useBarcodeProductLookup';

const props = defineProps<{
    products?: any[];
    branches?: any[];
    categories?: any[];
    initialCategory?: string;
}>();

const { terms } = useBusinessTerms();
const { isMultiBranch } = useFeatures();
const { currentBranch } = useBranch();
const { success, warning, error, info } = useToast();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: terms.value.itemsPlural, href: '/products' },
]);

// Initialize selected category from prop or URL search param (e.g. from Categories page)
const getInitialCategory = () => {
    if (props.initialCategory) return props.initialCategory;
    if (typeof window !== 'undefined') {
        const urlParam = new URLSearchParams(window.location.search).get('category');
        if (urlParam) return urlParam;
    }
    return 'All';
};

const selectedCategory = ref(getInitialCategory());

watch(() => props.initialCategory, (newCat) => {
    if (newCat) selectedCategory.value = newCat;
});

const products = computed<ProductItem[]>(() => {
    if (props.products) {
        return props.products.map(p => ({
            id: p.id,
            name: p.name,
            sku: p.sku,
            barcode: p.barcode,
            category: p.category || 'General',
            costPrice: p.cost_price ?? p.costPrice ?? 0,
            retailPrice: p.selling_price ?? p.retailPrice ?? 0,
            wholesalePrice: p.selling_price ?? p.wholesalePrice ?? 0,
            stock: p.stock_quantity ?? p.stock ?? 0,
            minStock: p.reorder_level ?? p.minStock ?? 5,
            unit: p.unit || 'Piece',
            taxRate: 16,
        }));
    }
    return [];
});

// Dynamic categories list prioritizing formal categories from database
const categories = computed(() => {
    const set = new Set<string>();
    set.add('All');
    (props.categories ?? []).forEach((c: any) => {
        if (c.name) set.add(c.name);
    });
    products.value.forEach((p) => {
        if (p.category) set.add(p.category);
    });
    return Array.from(set);
});

const getCategoryMeta = (catName: string) => {
    return (props.categories ?? []).find((c: any) => c.name === catName) ?? null;
};

// ============================================================
// COMPUTED STATS
// ============================================================
const totalStockValue = computed(() =>
    products.value.reduce((acc, p) => acc + p.costPrice * p.stock, 0),
);

const totalRetailValue = computed(() =>
    products.value.reduce((acc, p) => acc + p.retailPrice * p.stock, 0),
);

const grossMarginValue = computed(() => totalRetailValue.value - totalStockValue.value);

const lowStockItems = computed(() =>
    products.value.filter((p) => p.stock <= (p.minStock ?? 10)),
);

const outOfStockItems = computed(() =>
    products.value.filter((p) => p.stock === 0),
);

const expiringItems = computed(() => {
    if (!terms.value.showBatchExpiry) return [];
    const soon = new Date();
    soon.setDate(soon.getDate() + 90);
    return products.value.filter((p) => {
        if (!p.expiryDate) return false;
        const d = new Date(p.expiryDate);
        return d <= soon && d > new Date();
    });
});

// ============================================================
// COLUMNS DEFINITION
// ============================================================
const columns = computed(() => [
    { key: 'product', label: terms.value.itemLabel },
    { key: 'category', label: terms.value.categoryLabel },
    { key: 'costPrice', label: 'Cost Price', align: 'right' as const },
    { key: 'retailPrice', label: 'Retail Price', align: 'right' as const },
    { key: 'margin', label: 'Margin %', align: 'center' as const },
    { key: 'stock', label: 'Stock Level', align: 'center' as const },
    ...(terms.value.showBatchExpiry ? [{ key: 'expiry', label: 'Batch / Expiry', align: 'center' as const }] : []),
    { key: 'actions', label: 'Actions', align: 'right' as const },
]);

// Filtered & Searched List
const searchQuery = ref('');
const filteredList = computed(() => {
    let list = products.value;
    if (selectedCategory.value !== 'All') {
        list = list.filter((p) => p.category === selectedCategory.value);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                p.sku?.toLowerCase().includes(q) ||
                p.barcode?.toLowerCase().includes(q) ||
                p.brand?.toLowerCase().includes(q) ||
                p.genericName?.toLowerCase().includes(q) ||
                p.specifications?.toLowerCase().includes(q),
        );
    }
    return list;
});

// Compute margin percent for a product
const getMarginPct = (p: ProductItem) => {
    if (!p.retailPrice || !p.costPrice) return 0;
    return Math.round(((p.retailPrice - p.costPrice) / p.retailPrice) * 100);
};

// Days until expiry
const daysUntilExpiry = (date?: string) => {
    if (!date) return null;
    const diff = new Date(date).getTime() - Date.now();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
};

// ============================================================
// ADD / EDIT PRODUCT DRAWER
// ============================================================
const productDrawerOpen = ref(false);
const editingProduct = ref<ProductItem | null>(null);
const isSavingProduct = ref(false);

const form = ref<{
    name: string;
    sku: string;
    barcode: string;
    category: string;
    brand: string;
    unit: string;
    costPrice: number;
    retailPrice: number;
    wholesalePrice: number;
    stock: number;
    minStock: number;
    taxRate: number;
    description?: string;
    genericName?: string;
    dosage?: string;
    batchNumber?: string;
    expiryDate?: string;
    isPrescriptionOnly?: boolean;
    specifications?: string;
    dimensionUnit?: string;
    serialNumber?: string;
    imeiNumber?: string;
    warrantyMonths?: number;
}>({
    name: '',
    sku: '',
    barcode: '',
    category: '',
    brand: '',
    unit: 'Piece',
    costPrice: 0,
    retailPrice: 0,
    wholesalePrice: 0,
    stock: 0,
    minStock: 10,
    taxRate: 16,
    description: '',
});

const openAddProduct = () => {
    editingProduct.value = null;
    const defaultCat = selectedCategory.value !== 'All'
        ? selectedCategory.value
        : ((props.categories && props.categories[0]?.name) || categories.value.find(x => x !== 'All') || 'General');

    const matchedCat = getCategoryMeta(defaultCat);

    form.value = {
        name: '',
        sku: `SKU-${Date.now().toString().slice(-5)}`,
        barcode: '',
        category: defaultCat,
        brand: '',
        unit: matchedCat?.default_unit || terms.value.unitLabel || 'Piece',
        costPrice: 0,
        retailPrice: 0,
        wholesalePrice: 0,
        stock: 50,
        minStock: 10,
        taxRate: matchedCat?.default_tax_rate ? Number(matchedCat.default_tax_rate) : 16,
        description: '',
    };
    productDrawerOpen.value = true;
    // Auto-focus barcode field so a scanner can fire immediately
    nextTick(() => barcodeInputRef.value?.focus());
};

const handleCategoryChange = (e: Event) => {
    const catName = (e.target as HTMLSelectElement).value;
    form.value.category = catName;
    const matched = getCategoryMeta(catName);
    if (matched) {
        if (matched.default_unit) form.value.unit = matched.default_unit;
        if (matched.default_tax_rate !== undefined && matched.default_tax_rate !== null) {
            form.value.taxRate = Number(matched.default_tax_rate);
        }
    }
};

const openEditProduct = (product: ProductItem) => {
    editingProduct.value = product;
    form.value = {
        name: product.name,
        sku: product.sku,
        barcode: product.barcode,
        category: product.category,
        brand: product.brand || '',
        unit: product.unit,
        costPrice: product.costPrice,
        retailPrice: product.retailPrice,
        wholesalePrice: product.wholesalePrice,
        stock: product.stock,
        minStock: product.minStock || 10,
        taxRate: product.taxRate,
        description: product.description || '',
        genericName: product.genericName,
        dosage: product.dosage,
        batchNumber: product.batchNumber,
        expiryDate: product.expiryDate,
        isPrescriptionOnly: product.isPrescriptionOnly,
        specifications: product.specifications,
        dimensionUnit: product.dimensionUnit,
        serialNumber: product.serialNumber,
        imeiNumber: product.imeiNumber,
        warrantyMonths: product.warrantyMonths,
    };
    productDrawerOpen.value = true;
};

const handleSaveProduct = () => {
    if (!form.value.name.trim()) return;

    const payload = {
        name: form.value.name,
        sku: form.value.sku,
        barcode: form.value.barcode,
        category: form.value.category,
        cost_price: form.value.costPrice,
        selling_price: form.value.retailPrice,
        stock_quantity: form.value.stock,
        reorder_level: form.value.minStock,
        unit: form.value.unit,
    };

    isSavingProduct.value = true;
    if (editingProduct.value) {
        router.patch(`/products/${editingProduct.value.id}`, payload, {
            onSuccess: () => {
                success(`${terms.value.itemLabel} Updated`, `${form.value.name} updated successfully.`);
                productDrawerOpen.value = false;
            },
            onError: (errs) => error('Update Failed', Object.values(errs)[0] as string),
            onFinish: () => (isSavingProduct.value = false),
        });
    } else {
        router.post('/products', payload, {
            onSuccess: () => {
                success(`${terms.value.itemLabel} Added`, `${form.value.name} added to catalog.`);
                productDrawerOpen.value = false;
            },
            onError: (errs) => error('Creation Failed', Object.values(errs)[0] as string),
            onFinish: () => (isSavingProduct.value = false),
        });
    }
};

// ============================================================
// BARCODE SCANNER + PRODUCT AUTO-FILL
// ============================================================
const barcodeInputRef     = ref<HTMLInputElement | null>(null);
const productNameInputRef = ref<HTMLInputElement | null>(null);
const lookupBanner        = ref<{ found: boolean; fields: string[]; source?: string } | null>(null);
const barcodeCaptured     = ref(false);

const { isLookingUp, lookupStatus, lookupByBarcode } = useBarcodeProductLookup();

/**
 * Called by useBarcodeScanner whenever a valid HID burst is detected,
 * or when the user enters/pastes a barcode and presses Enter.
 * 1. Fills form.barcode with the scanned value and marks it captured.
 * 2. Fires a multi-tier lookup (UPC Item DB + Open Food Facts -> Backend Master DB + Web Search).
 * 3. Auto-fills available fields and shows a summary banner.
 */
const onBarcodeScanned = async (scannedCode?: string) => {
    const code = (scannedCode || form.value.barcode || '').trim();
    if (!code) return;

    form.value.barcode = code;
    barcodeCaptured.value = true;
    lookupBanner.value = null;

    const result = await lookupByBarcode(code);

    if (result.found) {
        const filled: string[] = [];

        if (result.name && !form.value.name) {
            form.value.name = result.name;
            filled.push('Name');
        }
        if (result.brand && !form.value.brand) {
            form.value.brand = result.brand;
            filled.push('Brand');
        }
        if (result.category) {
            // Try to match an existing category (case-insensitive); else set as-is
            const existing = categories.value.find(
                c => c.toLowerCase() === result.category!.toLowerCase()
            );
            form.value.category = existing ?? result.category;
            filled.push('Category');
        }
        if (result.description && !form.value.description) {
            form.value.description = result.description;
            filled.push('Description');
        }

        lookupBanner.value = { found: true, fields: filled, source: result.source };
        
        let title = 'Product Found';
        if (result.source === 'web_search') {
            title = 'Found via Web Search';
        } else if (result.source === 'pos_catalog' || result.source === 'cached') {
            title = 'Found in POS Catalog';
        }

        success(
            title,
            `Auto-filled: ${filled.join(', ')}. Add pricing & stock to continue.`,
        );
        await nextTick();
        productNameInputRef.value?.focus();
    } else {
        lookupBanner.value = { found: false, fields: [] };
        info(
            'Barcode Not Found',
            'No matching details in public or web databases. Please enter details manually.',
        );
        await nextTick();
        productNameInputRef.value?.focus();
    }
};

// Start the global HID scanner listener while the Add drawer is open.
// useBarcodeScanner attaches/detaches window keydown on mount/unmount,
// so we create it conditionally using a watcher approach via a child flag.
const scannerActive = ref(false);

watch(productDrawerOpen, (open) => {
    if (open && !editingProduct.value) {
        scannerActive.value = true;
        lookupBanner.value = null;
        barcodeCaptured.value = false;
        nextTick(() => barcodeInputRef.value?.focus());
    } else {
        scannerActive.value = false;
        lookupBanner.value = null;
        barcodeCaptured.value = false;
    }
});

// Inline scanner — always mounted but only processes when scannerActive
useBarcodeScanner({
    onScan: (code) => {
        if (!scannerActive.value) return;
        onBarcodeScanned(code);
    },
    minChars: 4,
    maxInterval: 50,
    soundEnabled: true,
});

const deleteConfirmOpen = ref(false);
const deleteProductTarget = ref<ProductItem | null>(null);
const isDeletingProduct = ref(false);

const openDeleteProductModal = (product: ProductItem) => {
    deleteProductTarget.value = product;
    deleteConfirmOpen.value = true;
};

const confirmDeleteProduct = () => {
    if (!deleteProductTarget.value) return;
    isDeletingProduct.value = true;
    router.delete(`/products/${deleteProductTarget.value.id}`, {
        onSuccess: () => {
            success(`${terms.value.itemLabel} Removed`, `${deleteProductTarget.value?.name} deleted from catalog.`);
            deleteConfirmOpen.value = false;
        },
        onError: () => error('Delete Failed', 'Could not delete product.'),
        onFinish: () => (isDeletingProduct.value = false),
    });
};

// ============================================================
// STOCK ADJUSTMENT MODAL (with log history)
// ============================================================
const adjustModalOpen = ref(false);
const adjustTarget = ref<ProductItem | null>(null);
const adjustQty = ref(0);
const adjustDelta = ref(0);
const adjustMode = ref<'set' | 'add' | 'subtract'>('set');
const adjustReason = ref('Stock Count Correction');

interface StockLog {
    productId: string;
    action: string;
    from: number;
    to: number;
    reason: string;
    by: string;
    at: string;
}
const stockLogs = ref<StockLog[]>([]);

const openStockAdjust = (product: ProductItem) => {
    adjustTarget.value = product;
    adjustQty.value = product.stock;
    adjustDelta.value = 0;
    adjustMode.value = 'set';
    adjustReason.value = 'Stock Count Correction';
    adjustModalOpen.value = true;
};

// Barcode Thermal Label Printing
const labelModalOpen = ref(false);
const labelProduct = ref<ProductLabelData | null>(null);

const openBarcodeLabel = (product: ProductItem) => {
    labelProduct.value = {
        id: product.id,
        name: product.name,
        sku: product.sku,
        barcode: product.barcode,
        price: product.retailPrice,
    };
    labelModalOpen.value = true;
};

const previewNewQty = computed(() => {
    if (!adjustTarget.value) return 0;
    if (adjustMode.value === 'set') return adjustQty.value;
    if (adjustMode.value === 'add') return adjustTarget.value.stock + adjustDelta.value;
    return Math.max(0, adjustTarget.value.stock - adjustDelta.value);
});

const isAdjusting = ref(false);

const handleSaveAdjustment = async () => {
    if (!adjustTarget.value || isAdjusting.value) return;
    isAdjusting.value = true;
    await new Promise((r) => setTimeout(r, 350));
    const oldQty = adjustTarget.value.stock;
    const newQty = previewNewQty.value;

    adjustTarget.value.stock = newQty;
    stockLogs.value.unshift({
        productId: adjustTarget.value.id,
        action: adjustMode.value,
        from: oldQty,
        to: newQty,
        reason: adjustReason.value,
        by: 'Store Manager',
        at: new Date().toLocaleString('en-KE'),
    });

    success(
        'Stock Adjusted',
        `${adjustTarget.value.name}: ${oldQty} → ${newQty} (${adjustReason.value})`,
    );
    isAdjusting.value = false;
    adjustModalOpen.value = false;
};

// ============================================================
// PRODUCT DETAIL MODAL
// ============================================================
const detailModalOpen = ref(false);
const detailProduct = ref<ProductItem | null>(null);

const openDetail = (p: ProductItem) => {
    detailProduct.value = p;
    detailModalOpen.value = true;
};

const productLogs = computed(() => {
    if (!detailProduct.value) return [];
    return stockLogs.value.filter((l) => l.productId === detailProduct.value?.id);
});

// Import Modal State
const importModalOpen = ref(false);

// Export CSV handler
const exportProductsCsv = () => {
    if (!products.value.length) {
        warning('Catalog is empty', 'No products to export.');
        return;
    }
    const headers = ['Name', 'SKU', 'Barcode', 'Category', 'Cost Price', 'Selling Price', 'Stock Quantity', 'Reorder Level', 'Unit'];
    const rows = products.value.map((p) => [
        `"${(p.name || '').replace(/"/g, '""')}"`,
        `"${p.sku || ''}"`,
        `"${p.barcode || ''}"`,
        `"${(p.category || 'General').replace(/"/g, '""')}"`,
        p.costPrice ?? 0,
        p.retailPrice ?? 0,
        p.stock ?? 0,
        p.minStock ?? 5,
        `"${p.unit || 'Piece'}"`,
    ]);
    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `products_catalog_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    success('Export Complete', `Exported ${products.value.length} products to CSV.`);
};

// ============================================================
// INTERACTIVE PRODUCT CATALOG GUIDE & WALKTHROUGH
// ============================================================
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openGuide = (step = 0) => {
    guideCurrentStep.value = step;
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'open-add-drawer') {
        guideOverlayOpen.value = false;
        openAddProduct();
    } else if (actionType === 'focus-search') {
        guideOverlayOpen.value = false;
        nextTick(() => {
            const searchInput = document.querySelector('input[type="search"], input[placeholder*="Search"]') as HTMLInputElement;
            if (searchInput) searchInput.focus();
        });
    } else if (actionType === 'open-stock-adjust') {
        const first = filteredList.value[0];
        if (first) {
            guideOverlayOpen.value = false;
            openStockAdjust(first);
        }
    } else if (actionType === 'open-barcode-label') {
        const first = filteredList.value[0];
        if (first) {
            guideOverlayOpen.value = false;
            openBarcodeLabel(first);
        }
    } else if (actionType === 'open-product-detail') {
        const first = filteredList.value[0];
        if (first) {
            guideOverlayOpen.value = false;
            openDetail(first);
        }
    } else if (actionType === 'open-edit-drawer') {
        const first = filteredList.value[0];
        if (first) {
            guideOverlayOpen.value = false;
            openEditProduct(first);
        }
    } else if (actionType === 'open-import-modal') {
        guideOverlayOpen.value = false;
        importModalOpen.value = true;
    }
};

const handleNewItem = () => {
    openAddProduct();
};

const handleSubmitForm = () => {
    if (formModalOpen.value) {
        handleSaveProduct();
    } else if (adjustModalOpen.value) {
        handleSaveAdjustment();
    }
};

onMounted(() => {
    window.addEventListener('app:new-item', handleNewItem);
    window.addEventListener('app:submit-form', handleSubmitForm);

    // Auto-prompt guide on first visit to Products Catalog in this session
    if (!sessionStorage.getItem('mde_product_guide_prompted')) {
        sessionStorage.setItem('mde_product_guide_prompted', 'true');
        setTimeout(() => {
            openGuide(0);
        }, 750);
    }
});

onUnmounted(() => {
    window.removeEventListener('app:new-item', handleNewItem);
    window.removeEventListener('app:submit-form', handleSubmitForm);
});
</script>

<template>
    <Head :title="`${terms.itemsPlural} Master Catalog`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ terms.catalogTitle }}</h1>
                        <Badge variant="outline" class="font-bold text-xs uppercase bg-primary/10 text-primary border-primary/20">
                            {{ terms.badgeLabel }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Manage master {{ terms.itemsPlural.toLowerCase() }}, pricing tiers, barcodes, stock levels and supplier intelligence.
                    </p>
                </div>

                <div id="product-step-actions" class="flex items-center gap-2 flex-wrap">
                    <!-- Product Catalog Page Guide Button -->
                    <Button
                        id="product-tour-guide-btn"
                        size="sm"
                        variant="outline"
                        class="h-9 gap-1.5 text-xs border-primary/40 text-primary bg-primary/5 hover:bg-primary/10 font-bold shadow-xs cursor-pointer"
                        @click="openGuide(0)"
                        title="Interactive Product Catalog Walkthrough"
                    >
                        <Compass class="size-3.5" />
                        <span>Page Guide</span>
                    </Button>

                    <Button as="a" href="/products/categories" size="sm" variant="outline" class="h-9 gap-1.5 text-xs hover:border-primary/50 hover:bg-primary/5 transition-colors">
                        <FolderTree class="size-3.5 text-primary" />
                        Categories
                    </Button>
                    <Button id="product-step-export-btn" size="sm" variant="outline" class="h-9 gap-1.5 text-xs" @click="exportProductsCsv">
                        <FileSpreadsheet class="size-3.5" />
                        Export CSV
                    </Button>
                    <Button id="product-step-import-btn" size="sm" variant="outline" class="h-9 gap-1.5 text-xs hover:bg-primary/10 hover:text-primary hover:border-primary/40 transition-colors" @click="importModalOpen = true">
                        <Download class="size-3.5" />
                        Import
                    </Button>
                    <Button
                        id="product-step-add-btn"
                        size="sm"
                        class="h-9 gap-1.5 text-xs bg-primary hover:bg-primary/90 text-primary-foreground font-bold shadow-sm cursor-pointer"
                        @click="openAddProduct"
                    >
                        <Plus class="size-4" />
                        Add New {{ terms.itemLabel }}
                    </Button>
                </div>
            </div>

            <!-- Catalog Stats Cards Row -->
            <div id="product-step-stats" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total {{ terms.itemsPlural }}</span>
                        <Package class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-black text-foreground font-mono">{{ products.length }}</div>
                    <a href="/products/categories" class="text-[10px] text-primary hover:underline font-medium inline-flex items-center gap-1">
                        {{ categories.length - 1 }} categories &rarr;
                    </a>
                </Card>

                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Stock Cost Value</span>
                        <Tag class="size-4 text-sky-500" />
                    </div>
                    <div class="text-2xl font-black text-foreground font-mono text-primary">
                        {{ formatCurrency(totalStockValue) }}
                    </div>
                    <span class="text-[10px] text-muted-foreground block">
                        Retail value: {{ formatCurrency(totalRetailValue) }}
                    </span>
                </Card>

                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Gross Margin Potential</span>
                        <TrendingUp class="size-4 text-emerald-600" />
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                        {{ formatCurrency(grossMarginValue) }}
                    </div>
                    <span class="text-[10px] text-muted-foreground block">
                        {{ grossMarginValue > 0 ? Math.round((grossMarginValue / totalRetailValue) * 100) : 0 }}% avg gross margin
                    </span>
                </Card>

                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Stock Health</span>
                        <AlertTriangle class="size-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-black font-mono" :class="lowStockItems.length > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'">
                        {{ lowStockItems.length }} Low Stock
                    </div>
                    <span class="text-[10px] text-muted-foreground block">
                        {{ outOfStockItems.length }} items out of stock
                        <span v-if="terms.showBatchExpiry && expiringItems.length > 0" class="text-rose-600 font-semibold">
                            · {{ expiringItems.length }} expiring soon
                        </span>
                    </span>
                </Card>
            </div>

            <!-- Low Stock & Expiry Alerts Banner -->
            <div id="product-step-alerts" v-if="lowStockItems.length > 0 || (terms.showBatchExpiry && expiringItems.length > 0)" class="space-y-2">
                <div v-if="lowStockItems.length > 0" class="flex items-center gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-xs">
                    <AlertTriangle class="size-4 text-amber-600 shrink-0" />
                    <span class="font-semibold text-amber-800 dark:text-amber-300">
                        {{ lowStockItems.length }} {{ terms.itemsPlural.toLowerCase() }} are below minimum stock threshold:
                    </span>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <Badge v-for="p in lowStockItems.slice(0,5)" :key="p.id" variant="outline" class="text-[10px] bg-amber-50 dark:bg-amber-900/20 text-amber-700 border-amber-400/30">
                            {{ p.name }} ({{ p.stock }} left)
                        </Badge>
                        <span v-if="lowStockItems.length > 5" class="text-amber-700 text-[10px]">+{{ lowStockItems.length - 5 }} more</span>
                    </div>
                </div>

                <div v-if="terms.showBatchExpiry && expiringItems.length > 0" class="flex items-center gap-3 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 text-xs">
                    <Calendar class="size-4 text-rose-600 shrink-0" />
                    <span class="font-semibold text-rose-800 dark:text-rose-300">
                        {{ expiringItems.length }} items expiring within 90 days:
                    </span>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <Badge v-for="p in expiringItems.slice(0,4)" :key="p.id" variant="outline" class="text-[10px] bg-rose-50 dark:bg-rose-900/20 text-rose-700 border-rose-400/30">
                            {{ p.name }} ({{ daysUntilExpiry(p.expiryDate) }} days)
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Categories Filter Bar -->
            <div id="product-step-categories" class="flex items-center justify-between gap-2 overflow-x-auto pb-1 text-xs scrollbar-none">
                <div class="flex items-center gap-1.5 flex-wrap md:flex-nowrap">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 font-bold rounded-xl border text-xs whitespace-nowrap transition-all flex items-center gap-1.5',
                            selectedCategory === cat
                                ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                : 'bg-card border-border/80 text-muted-foreground hover:text-foreground',
                        ]"
                        @click="selectedCategory = cat"
                    >
                        <span
                            v-if="cat !== 'All' && getCategoryMeta(cat)?.color"
                            class="size-2 rounded-full shrink-0"
                            :style="{ backgroundColor: getCategoryMeta(cat)?.color }"
                        />
                        {{ cat }}
                        <span v-if="cat === 'All'" class="ml-0.5 opacity-60">({{ products.length }})</span>
                    </button>
                </div>
                <a
                    href="/products/categories"
                    class="text-xs font-semibold text-primary hover:underline whitespace-nowrap shrink-0 flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-primary/20 hover:bg-primary/10 transition-colors ml-auto"
                >
                    <FolderTree class="size-3.5" />
                    Manage Categories &rarr;
                </a>
            </div>

            <!-- Products DataTable -->
            <div id="product-step-table">
                <DataTable
                    :columns="columns"
                    :data="filteredList"
                    :search-placeholder="`Search ${terms.itemsPlural.toLowerCase()} by name, SKU, barcode, brand...`"
                    :search-keys="['name', 'sku', 'barcode', 'brand', 'category', 'genericName', 'specifications']"
                    :page-size="12"
                >
                <!-- Product Name Column -->
                <template #cell-product="{ row }">
                    <div
                        :id="row.id === filteredList[0]?.id ? 'product-step-col-product' : undefined"
                        class="flex items-center gap-3 cursor-pointer"
                        @click="openDetail(row)"
                    >
                        <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs shrink-0">
                            <Pill v-if="terms.showPrescriptions" class="size-4" />
                            <Wrench v-else-if="terms.showDimensionsAndSite" class="size-4" />
                            <Utensils v-else-if="terms.showTableManagement" class="size-4" />
                            <Smartphone v-else-if="terms.showSerialWarranty" class="size-4" />
                            <Package v-else class="size-4" />
                        </div>
                        <div>
                            <div class="font-bold text-foreground hover:text-primary transition-colors">{{ row.name }}</div>
                            <!-- Vertical-specific secondary info -->
                            <div v-if="row.genericName" class="text-[11px] text-teal-600 dark:text-teal-400 italic">
                                Generic: {{ row.genericName }}
                                <Badge v-if="row.isPrescriptionOnly" variant="outline" class="text-[9px] ml-1 border-rose-500/30 text-rose-600">Rx Only</Badge>
                            </div>
                            <div v-else-if="row.specifications" class="text-[11px] text-amber-600 dark:text-amber-400 font-mono truncate max-w-[200px]">
                                {{ row.specifications }}
                            </div>
                            <div v-else-if="row.imeiNumber" class="text-[11px] text-blue-600 dark:text-blue-400 font-mono">
                                IMEI: {{ row.imeiNumber }}
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-muted-foreground font-mono mt-0.5">
                                <span>{{ row.sku }}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1"><ScanBarcode class="size-3 inline" /> {{ row.barcode }}</span>
                                <span v-if="row.brand" class="text-muted-foreground">• {{ row.brand }}</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Category Column -->
                <template #cell-category="{ row }">
                    <div class="flex items-center gap-2">
                        <span
                            v-if="getCategoryMeta(row.category)?.color"
                            class="size-2.5 rounded-full shrink-0 shadow-xs"
                            :style="{ backgroundColor: getCategoryMeta(row.category)?.color }"
                        />
                        <div>
                            <button
                                type="button"
                                class="text-xs font-bold text-foreground hover:text-primary transition-colors cursor-pointer text-left flex items-center gap-1"
                                @click="selectedCategory = row.category"
                                title="Filter products by this category"
                            >
                                {{ row.category }}
                            </button>
                            <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-muted-foreground">
                                <Badge variant="outline" class="text-[9px] px-1.5 py-0 font-normal">
                                    {{ row.unit || getCategoryMeta(row.category)?.default_unit || 'Piece' }}
                                </Badge>
                                <span v-if="getCategoryMeta(row.category)?.default_tax_rate" class="font-mono text-[9px]">
                                    {{ getCategoryMeta(row.category)?.default_tax_rate }}% VAT
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Cost Price -->
                <template #cell-costPrice="{ row }">
                    <span class="font-mono text-muted-foreground text-xs">{{ formatCurrency(row.costPrice) }}</span>
                </template>

                <!-- Retail Price -->
                <template #cell-retailPrice="{ row }">
                    <div
                        :id="row.id === filteredList[0]?.id ? 'product-step-col-price' : undefined"
                        class="text-right"
                    >
                        <span class="font-mono font-bold text-foreground text-xs">{{ formatCurrency(row.retailPrice) }}</span>
                        <div v-if="row.wholesalePrice && row.wholesalePrice !== row.retailPrice" class="text-[10px] text-primary font-mono">
                            WS: {{ formatCurrency(row.wholesalePrice) }}
                        </div>
                    </div>
                </template>

                <!-- Gross Margin % -->
                <template #cell-margin="{ row }">
                    <div class="flex items-center justify-center gap-1">
                        <span
                            :class="[
                                'text-xs font-bold font-mono',
                                getMarginPct(row) >= 30 ? 'text-emerald-600 dark:text-emerald-400' :
                                getMarginPct(row) >= 15 ? 'text-amber-600' : 'text-rose-600'
                            ]"
                        >
                            {{ getMarginPct(row) }}%
                        </span>
                        <TrendingUp v-if="getMarginPct(row) >= 20" class="size-3 text-emerald-600" />
                        <TrendingDown v-else-if="getMarginPct(row) < 10" class="size-3 text-rose-500" />
                    </div>
                </template>

                <!-- Stock Level Column -->
                <template #cell-stock="{ row }">
                    <div
                        :id="row.id === filteredList[0]?.id ? 'product-step-stock-col' : undefined"
                        class="flex flex-col items-center gap-0.5"
                    >
                        <Badge
                            :variant="row.stock === 0 ? 'destructive' : row.stock <= (row.minStock || 10) ? 'warning' : 'outline'"
                            class="font-mono text-[11px]"
                        >
                            {{ row.stock }} {{ row.unit }}
                        </Badge>
                        <div v-if="row.stock > 0 && row.minStock" class="w-14 bg-muted rounded-full h-1">
                            <div
                                class="h-1 rounded-full"
                                :class="row.stock <= row.minStock ? 'bg-amber-500' : 'bg-emerald-500'"
                                :style="{ width: `${Math.min((row.stock / (row.minStock * 3)) * 100, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </template>

                <!-- Expiry Column (Pharmacy/FMCG) -->
                <template #cell-expiry="{ row }">
                    <div
                        :id="row.id === filteredList[0]?.id ? 'product-step-col-expiry' : undefined"
                        class="text-center"
                    >
                        <template v-if="row.expiryDate">
                            <div
                                :class="[
                                    'text-[11px] font-mono font-semibold',
                                    (daysUntilExpiry(row.expiryDate) ?? 999) <= 30 ? 'text-rose-600' :
                                    (daysUntilExpiry(row.expiryDate) ?? 999) <= 90 ? 'text-amber-600' : 'text-foreground'
                                ]"
                            >
                                {{ row.expiryDate }}
                            </div>
                            <div class="text-[10px] text-muted-foreground">
                                {{ daysUntilExpiry(row.expiryDate) }}d left
                            </div>
                        </template>
                        <span v-else class="text-[10px] text-muted-foreground">—</span>
                    </div>
                </template>

                <!-- Row Actions -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <Button
                            :id="row.id === filteredList[0]?.id ? 'product-step-adjust-btn' : undefined"
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs px-2 gap-1 text-muted-foreground hover:text-foreground cursor-pointer"
                            @click="openStockAdjust(row)"
                        >
                            <RefreshCcw class="size-3" />
                            Stock
                        </Button>
                        <Button
                            :id="row.id === filteredList[0]?.id ? 'product-step-barcode-col' : undefined"
                            variant="ghost"
                            size="sm"
                            class="h-7 w-7 p-0 text-muted-foreground hover:text-primary cursor-pointer"
                            @click="openBarcodeLabel(row)"
                            title="Print Barcode Labels"
                        >
                            <Printer class="size-3.5" />
                        </Button>
                        <Button
                            :id="row.id === filteredList[0]?.id ? 'product-step-edit-btn' : undefined"
                            variant="ghost"
                            size="sm"
                            class="h-7 w-7 p-0 text-muted-foreground hover:text-foreground cursor-pointer"
                            @click="openEditProduct(row)"
                        >
                            <Edit class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 w-7 p-0 text-muted-foreground hover:text-rose-600 cursor-pointer"
                            title="Delete product"
                            @click="openDeleteProductModal(row)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </template>
            </DataTable>
            </div>
        </div>

        <!-- ================================================
             PRODUCT DETAIL MODAL
             ================================================ -->
        <Dialog :open="detailModalOpen" @update:open="(v) => (detailModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-xl max-h-[85vh] overflow-y-auto">
                <DialogHeader v-if="detailProduct">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <DialogTitle class="text-lg font-black text-foreground">{{ detailProduct.name }}</DialogTitle>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <Badge variant="outline" class="text-[10px]">{{ detailProduct.category }}</Badge>
                                <Badge variant="outline" class="text-[10px] font-mono">{{ detailProduct.sku }}</Badge>
                                <Badge v-if="detailProduct.isPrescriptionOnly" variant="destructive" class="text-[10px]">Rx Only</Badge>
                                <Badge
                                    :variant="detailProduct.stock === 0 ? 'destructive' : detailProduct.stock <= (detailProduct.minStock ?? 10) ? 'warning' : 'success'"
                                    class="text-[10px]"
                                >
                                    {{ detailProduct.stock === 0 ? 'Out of Stock' : detailProduct.stock + ' in Stock' }}
                                </Badge>
                            </div>
                        </div>
                        <Button size="sm" variant="outline" class="h-8 text-xs gap-1 shrink-0" @click="() => { detailModalOpen = false; openEditProduct(detailProduct!) }">
                            <Edit class="size-3.5" /> Edit
                        </Button>
                    </div>
                </DialogHeader>

                <div v-if="detailProduct" class="space-y-4 py-2 text-xs">
                    <!-- Key Metrics Row -->
                    <div class="grid grid-cols-3 gap-2">
                        <div class="p-3 rounded-xl bg-muted/40 border border-border/80 text-center space-y-0.5">
                            <div class="text-[10px] text-muted-foreground">Cost Price</div>
                            <div class="text-sm font-black font-mono text-muted-foreground">{{ formatCurrency(detailProduct.costPrice) }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-primary/5 border border-primary/20 text-center space-y-0.5">
                            <div class="text-[10px] text-muted-foreground">Retail Price</div>
                            <div class="text-sm font-black font-mono text-foreground">{{ formatCurrency(detailProduct.retailPrice) }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-500/5 border border-emerald-500/20 text-center space-y-0.5">
                            <div class="text-[10px] text-muted-foreground">Gross Margin</div>
                            <div class="text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">{{ getMarginPct(detailProduct) }}%</div>
                        </div>
                    </div>

                    <!-- Product Identifiers -->
                    <div class="p-3 rounded-xl border border-border/80 bg-muted/30 space-y-2">
                        <div class="font-bold text-foreground text-xs">Identifiers & Classification</div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[11px]">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">SKU:</span>
                                <span class="font-mono font-bold">{{ detailProduct.sku }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Barcode:</span>
                                <span class="font-mono font-bold">{{ detailProduct.barcode }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Brand:</span>
                                <span class="font-medium">{{ detailProduct.brand || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Unit:</span>
                                <span class="font-medium">{{ detailProduct.unit }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">VAT Rate:</span>
                                <span class="font-mono">{{ detailProduct.taxRate }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Wholesale Price:</span>
                                <span class="font-mono text-primary">{{ formatCurrency(detailProduct.wholesalePrice) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Vertical-Specific Details -->
                    <div v-if="detailProduct.genericName || detailProduct.dosage || detailProduct.batchNumber || detailProduct.expiryDate" class="p-3 rounded-xl border border-teal-500/30 bg-teal-500/5 space-y-2">
                        <div class="font-bold text-teal-700 dark:text-teal-300 text-xs flex items-center gap-1">
                            <Pill class="size-3" /> Pharmaceutical Details
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[11px]">
                            <div v-if="detailProduct.genericName" class="flex justify-between col-span-2">
                                <span class="text-muted-foreground">Generic Name:</span>
                                <span class="font-medium italic text-teal-700 dark:text-teal-300">{{ detailProduct.genericName }}</span>
                            </div>
                            <div v-if="detailProduct.dosage" class="flex justify-between col-span-2">
                                <span class="text-muted-foreground">Dosage Instructions:</span>
                                <span class="font-medium">{{ detailProduct.dosage }}</span>
                            </div>
                            <div v-if="detailProduct.batchNumber" class="flex justify-between">
                                <span class="text-muted-foreground">Batch Number:</span>
                                <span class="font-mono font-bold">{{ detailProduct.batchNumber }}</span>
                            </div>
                            <div v-if="detailProduct.expiryDate" class="flex justify-between">
                                <span class="text-muted-foreground">Expiry Date:</span>
                                <span :class="['font-mono font-bold', (daysUntilExpiry(detailProduct.expiryDate) ?? 999) <= 90 ? 'text-rose-600' : 'text-foreground']">
                                    {{ detailProduct.expiryDate }} ({{ daysUntilExpiry(detailProduct.expiryDate) }}d)
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="detailProduct.specifications" class="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5 space-y-2">
                        <div class="font-bold text-amber-700 dark:text-amber-300 text-xs flex items-center gap-1">
                            <Wrench class="size-3" /> Technical Specifications
                        </div>
                        <p class="text-[11px] font-mono text-foreground leading-relaxed">{{ detailProduct.specifications }}</p>
                    </div>

                    <div v-if="detailProduct.imeiNumber || detailProduct.serialNumber || detailProduct.warrantyMonths" class="p-3 rounded-xl border border-blue-500/30 bg-blue-500/5 space-y-2">
                        <div class="font-bold text-blue-700 dark:text-blue-300 text-xs flex items-center gap-1">
                            <Smartphone class="size-3" /> Device & Warranty Info
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[11px]">
                            <div v-if="detailProduct.imeiNumber" class="flex justify-between col-span-2">
                                <span class="text-muted-foreground">IMEI Number:</span>
                                <span class="font-mono font-bold">{{ detailProduct.imeiNumber }}</span>
                            </div>
                            <div v-if="detailProduct.serialNumber" class="flex justify-between">
                                <span class="text-muted-foreground">Serial Number:</span>
                                <span class="font-mono font-bold">{{ detailProduct.serialNumber }}</span>
                            </div>
                            <div v-if="detailProduct.warrantyMonths" class="flex justify-between">
                                <span class="text-muted-foreground">Warranty:</span>
                                <span class="font-bold text-emerald-600">{{ detailProduct.warrantyMonths }} months</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Level Meter -->
                    <div class="p-3 rounded-xl border border-border/80 bg-card space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold text-foreground">
                            <span>Stock Level Status</span>
                            <span class="font-mono">{{ detailProduct.stock }} / Min: {{ detailProduct.minStock ?? 10 }} {{ detailProduct.unit }}</span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-2">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="detailProduct.stock === 0 ? 'bg-rose-500' : detailProduct.stock <= (detailProduct.minStock ?? 10) ? 'bg-amber-500' : 'bg-emerald-500'"
                                :style="{ width: `${Math.min((detailProduct.stock / ((detailProduct.minStock ?? 10) * 5)) * 100, 100)}%` }"
                            ></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-muted-foreground">
                            <span>Total Stock Value: <strong class="text-foreground font-mono">{{ formatCurrency(detailProduct.costPrice * detailProduct.stock) }}</strong></span>
                            <Button size="sm" variant="outline" class="h-6 text-[10px] px-2 gap-1" @click="() => { detailModalOpen = false; openStockAdjust(detailProduct!) }">
                                <RefreshCcw class="size-3" /> Adjust Stock
                            </Button>
                        </div>
                    </div>

                    <!-- Stock Adjustment Log -->
                    <div v-if="productLogs.length > 0" class="space-y-1.5">
                        <div class="text-xs font-bold text-foreground flex items-center gap-1.5">
                            <History class="size-3.5 text-muted-foreground" /> Stock Adjustment History
                        </div>
                        <div class="rounded-xl border border-border/80 overflow-hidden">
                            <table class="w-full text-left text-[11px]">
                                <thead class="bg-muted/50 border-b border-border/60 text-muted-foreground">
                                    <tr>
                                        <th class="p-2">Date / Time</th>
                                        <th class="p-2 text-center">From</th>
                                        <th class="p-2 text-center">To</th>
                                        <th class="p-2">Reason</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="(log, i) in productLogs" :key="i" class="hover:bg-muted/20">
                                        <td class="p-2 text-muted-foreground">{{ log.at }}</td>
                                        <td class="p-2 text-center font-mono">{{ log.from }}</td>
                                        <td class="p-2 text-center font-mono font-bold text-primary">{{ log.to }}</td>
                                        <td class="p-2 text-muted-foreground truncate max-w-[120px]">{{ log.reason }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-2 border-t border-border/60">
                    <Button variant="ghost" size="sm" @click="detailModalOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ================================================
             ADD / EDIT PRODUCT DRAWER
             ================================================ -->
        <Sheet :open="productDrawerOpen" @update:open="(v) => (productDrawerOpen = v)">
            <SheetContent side="right" class="w-full sm:max-w-lg p-6 flex flex-col overflow-y-auto">
                <SheetHeader class="pb-4 border-b border-border/70">
                    <SheetTitle class="text-lg font-bold">
                        {{ editingProduct ? `Edit ${terms.itemLabel}` : `Add New ${terms.itemLabel}` }}
                    </SheetTitle>
                    <SheetDescription class="text-xs">
                        Configure pricing tiers, barcodes, {{ terms.unitLabel.toLowerCase() }}, stock thresholds, and vertical attributes.
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 py-4 space-y-4 text-xs">
                    <!-- Name & Description -->
                    <div class="space-y-2">
                        <div>
                            <Label class="text-xs flex items-center justify-between">
                                <span>{{ terms.itemLabel }} Name</span>
                                <span v-if="lookupBanner?.found && lookupBanner.fields.includes('Name')" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                                    <CheckCircle2 class="size-3 inline" /> Auto-filled
                                </span>
                            </Label>
                            <input
                                ref="productNameInputRef"
                                v-model="form.name"
                                :placeholder="`e.g. ${terms.itemLabel} title`"
                                class="flex h-9 w-full rounded-md border bg-background px-3 py-1 text-xs shadow-sm transition-all duration-300 placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring mt-1"
                                :class="lookupBanner?.found && lookupBanner.fields.includes('Name') ? 'border-emerald-500 bg-emerald-500/10 text-emerald-950 dark:text-emerald-100 ring-1 ring-emerald-500/30 font-medium' : 'border-input'"
                            />
                        </div>
                        <div>
                            <Label class="text-xs">Short Description / Notes</Label>
                            <Input v-model="form.description" placeholder="Internal notes or description..." class="mt-1 text-xs" />
                        </div>
                    </div>

                    <!-- Pharmacy-specific -->
                    <div v-if="terms.showPrescriptions" class="p-3 rounded-xl border border-teal-500/30 bg-teal-500/5 space-y-3">
                        <div class="font-bold text-teal-700 dark:text-teal-300 text-[11px] uppercase tracking-wider">Pharmaceutical Info</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Generic Name / Active Ingredients</Label>
                                <Input v-model="form.genericName" placeholder="e.g. Amoxicillin 500mg" class="mt-1 text-xs" />
                            </div>
                            <div>
                                <Label class="text-xs">Default Dosage</Label>
                                <Input v-model="form.dosage" placeholder="e.g. 1 Tab TDS x 7 days" class="mt-1 text-xs" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Batch Number</Label>
                                <Input v-model="form.batchNumber" placeholder="e.g. BT-2024-0987" class="mt-1 text-xs font-mono" />
                            </div>
                            <div>
                                <Label class="text-xs">Expiry Date</Label>
                                <Input v-model="form.expiryDate" type="date" class="mt-1 text-xs font-mono" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.isPrescriptionOnly" id="rxOnly" class="rounded" />
                            <label for="rxOnly" class="text-xs text-muted-foreground">Prescription Only (Rx) — requires doctor's note</label>
                        </div>
                    </div>

                    <!-- Hardware / Construction-specific -->
                    <div v-if="terms.showDimensionsAndSite" class="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5 space-y-2">
                        <div class="font-bold text-amber-700 dark:text-amber-300 text-[11px] uppercase tracking-wider">Material Specifications</div>
                        <div>
                            <Label class="text-xs">Technical Specification / Gauge / Dimensions</Label>
                            <Input v-model="form.specifications" placeholder="e.g. BS 4449 Grade 500B | 12mm x 12m" class="mt-1 text-xs font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs">Measurement Unit</Label>
                            <Input v-model="form.dimensionUnit" placeholder="e.g. Length (meters), Volume (liters)" class="mt-1 text-xs" />
                        </div>
                    </div>

                    <!-- Electronics-specific -->
                    <div v-if="terms.showSerialWarranty" class="p-3 rounded-xl border border-blue-500/30 bg-blue-500/5 space-y-2">
                        <div class="font-bold text-blue-700 dark:text-blue-300 text-[11px] uppercase tracking-wider">Device & Warranty</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">IMEI Number</Label>
                                <Input v-model="form.imeiNumber" placeholder="15-digit IMEI" class="mt-1 text-xs font-mono" />
                            </div>
                            <div>
                                <Label class="text-xs">Serial Number</Label>
                                <Input v-model="form.serialNumber" placeholder="Device Serial" class="mt-1 text-xs font-mono" />
                            </div>
                        </div>
                        <div>
                            <Label class="text-xs">Warranty Duration (Months)</Label>
                            <Input v-model.number="form.warrantyMonths" type="number" placeholder="e.g. 12" class="mt-1 text-xs font-mono" />
                        </div>
                    </div>

                    <!-- Core Identifiers: SKU + Barcode -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">SKU Code</Label>
                            <Input v-model="form.sku" class="mt-1 text-xs font-mono" />
                        </div>

                        <!-- Barcode — auto-focused; useBarcodeScanner listens globally -->
                        <div>
                            <Label class="text-xs flex items-center gap-1">
                                <ScanBarcode class="size-3 text-muted-foreground" />
                                Barcode
                                <span v-if="barcodeCaptured" class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40 animate-pulse flex items-center gap-1">
                                    <CheckCircle2 class="size-2.5" /> CAPTURED
                                </span>
                                <span v-if="isLookingUp" class="ml-auto flex items-center gap-1 text-[10px] text-primary font-semibold">
                                    <Loader2 class="size-3 animate-spin" />
                                    {{ lookupStatus === 'searching_extended' ? 'Searching web catalog...' : 'Looking up...' }}
                                </span>
                            </Label>

                            <!-- Input wrapper -->
                            <div class="relative mt-1">
                                <input
                                    ref="barcodeInputRef"
                                    v-model="form.barcode"
                                    placeholder="Scan barcode or type & press Enter..."
                                    class="allow-scanner flex h-9 w-full rounded-md border px-3 py-1 pr-8 text-xs font-mono shadow-sm transition-all duration-300 placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    :class="[
                                        isLookingUp ? 'opacity-60 pointer-events-none' : '',
                                        barcodeCaptured
                                            ? 'border-emerald-500 bg-emerald-500/15 text-emerald-950 dark:text-emerald-100 ring-2 ring-emerald-500/40 font-bold'
                                            : 'border-input bg-background'
                                    ]"
                                    autocomplete="off"
                                    :disabled="isLookingUp"
                                    @input="barcodeCaptured = false"
                                    @keydown.enter.prevent="onBarcodeScanned(form.barcode)"
                                />
                                <Loader2
                                    v-if="isLookingUp"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 size-3.5 animate-spin text-primary"
                                />
                                <CheckCircle2
                                    v-else-if="barcodeCaptured"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-emerald-600 dark:text-emerald-400"
                                />
                                <button
                                    v-else-if="form.barcode"
                                    type="button"
                                    @click="onBarcodeScanned(form.barcode)"
                                    title="Lookup barcode"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <Search class="size-3.5" />
                                </button>
                            </div>

                            <!-- Lookup result banner -->
                            <div
                                v-if="lookupBanner"
                                :class="[
                                    'mt-1.5 flex items-start gap-2 rounded-lg border px-2.5 py-2 text-[11px]',
                                    lookupBanner.found
                                        ? 'bg-emerald-500/8 border-emerald-500/30 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-amber-500/8 border-amber-500/30 text-amber-700 dark:text-amber-400'
                                ]"
                            >
                                <CheckCircle2 v-if="lookupBanner.found" class="size-3.5 mt-0.5 shrink-0" />
                                <AlertTriangle v-else class="size-3.5 mt-0.5 shrink-0" />
                                <span v-if="lookupBanner.found">
                                    <span
                                        v-if="lookupBanner.source === 'web_search'"
                                        class="inline-block px-1.5 py-0.5 mr-1 rounded bg-blue-500/15 text-blue-700 dark:text-blue-300 font-bold text-[9px] uppercase tracking-wider"
                                    >Web Search</span>
                                    <span
                                        v-else-if="lookupBanner.source === 'pos_catalog' || lookupBanner.source === 'cached'"
                                        class="inline-block px-1.5 py-0.5 mr-1 rounded bg-purple-500/15 text-purple-700 dark:text-purple-300 font-bold text-[9px] uppercase tracking-wider"
                                    >Master Catalog</span>
                                    Auto-filled: <strong>{{ lookupBanner.fields.join(', ') }}</strong>.
                                    Review &amp; add pricing below.
                                </span>
                                <span v-else>
                                    Not found in public/web databases — fill details manually. (Will save to master catalog once saved!)
                                </span>
                            </div>

                            <p v-if="!editingProduct && !form.barcode && !isLookingUp && !lookupBanner"
                               class="mt-1 text-[10px] text-muted-foreground">
                                Scanner auto-ready — scan now or type &amp; press Enter.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs flex items-center justify-between">
                                <span>{{ terms.categoryLabel }}</span>
                                <div class="flex items-center gap-1.5">
                                    <span v-if="lookupBanner?.found && lookupBanner.fields.includes('Category')" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                                        <CheckCircle2 class="size-2.5 inline" /> Auto-filled
                                    </span>
                                    <a href="/products/categories" target="_blank" class="text-[10px] text-primary hover:underline flex items-center gap-0.5 font-normal">
                                        <ExternalLink class="size-2.5 inline" /> Manage
                                    </a>
                                </div>
                            </Label>
                            <select
                                v-model="form.category"
                                class="mt-1 h-9 w-full rounded-md border bg-background px-3 text-xs transition-all duration-300"
                                :class="lookupBanner?.found && lookupBanner.fields.includes('Category') ? 'border-emerald-500 bg-emerald-500/10 text-emerald-950 dark:text-emerald-100 ring-1 ring-emerald-500/30' : 'border-input'"
                                @change="handleCategoryChange"
                            >
                                <option v-for="c in categories.filter(x => x !== 'All')" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <!-- Unit and tax hint for selected category -->
                            <p v-if="getCategoryMeta(form.category)" class="text-[10px] text-muted-foreground mt-0.5">
                                Default: {{ getCategoryMeta(form.category)?.default_unit }} · VAT {{ getCategoryMeta(form.category)?.default_tax_rate }}%
                            </p>
                        </div>
                        <div>
                            <Label class="text-xs flex items-center justify-between">
                                <span>Brand / Manufacturer</span>
                                <span v-if="lookupBanner?.found && lookupBanner.fields.includes('Brand')" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                                    <CheckCircle2 class="size-2.5 inline" /> Auto-filled
                                </span>
                            </Label>
                            <Input
                                v-model="form.brand"
                                placeholder="e.g. Manufacturer"
                                class="mt-1 text-xs transition-all duration-300"
                                :class="lookupBanner?.found && lookupBanner.fields.includes('Brand') ? 'border-emerald-500 bg-emerald-500/10 text-emerald-950 dark:text-emerald-100 ring-1 ring-emerald-500/30 font-medium' : ''"
                            />
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="p-3 bg-muted/30 rounded-xl border border-border/70 space-y-3">
                        <span class="font-bold text-foreground text-xs">Pricing Configuration</span>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <Label class="text-[11px] text-muted-foreground">Cost Price (Buy)</Label>
                                <Input v-model.number="form.costPrice" type="number" class="mt-1 text-xs font-mono" />
                            </div>
                            <div>
                                <Label class="text-[11px] text-muted-foreground">Retail Price (Sell)</Label>
                                <Input v-model.number="form.retailPrice" type="number" class="mt-1 text-xs font-mono font-bold" />
                            </div>
                            <div>
                                <Label class="text-[11px] text-muted-foreground">Wholesale / Tier 2</Label>
                                <Input v-model.number="form.wholesalePrice" type="number" class="mt-1 text-xs font-mono font-bold text-primary" />
                            </div>
                        </div>
                        <!-- Live Margin Preview -->
                        <div v-if="form.retailPrice > 0 && form.costPrice > 0" class="flex items-center justify-between p-2 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-[11px]">
                            <span class="text-muted-foreground">Gross Margin Preview:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                {{ Math.round(((form.retailPrice - form.costPrice) / form.retailPrice) * 100) }}% margin
                                · {{ formatCurrency(form.retailPrice - form.costPrice) }} per {{ form.unit || 'unit' }}
                            </span>
                        </div>
                    </div>

                    <!-- Stock, Packaging Unit & Threshold -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div>
                            <Label class="text-xs">Packaging Unit</Label>
                            <select
                                v-model="form.unit"
                                class="mt-1 h-9 w-full rounded-md border border-input bg-background px-2 text-xs"
                            >
                                <option value="Piece">Piece / Unit (Pcs)</option>
                                <option value="Dozen">Dozen (12s)</option>
                                <option value="Box">Box / Carton</option>
                                <option value="Bale">Bale (Bulk pack)</option>
                                <option value="Pack">Pack / Packet</option>
                                <option value="Kg">Kilogram (Kg)</option>
                                <option value="Gram">Gram (g)</option>
                                <option value="Litre">Litre (L)</option>
                                <option value="Ml">Millilitre (ml)</option>
                                <option value="Pair">Pair</option>
                                <option value="Roll">Roll</option>
                                <option value="Bag">Bag / Sack</option>
                                <option value="Bottle">Bottle</option>
                                <option value="Can">Can / Tin</option>
                                <option value="Loaf">Loaf</option>
                                <option value="Set">Set / Kit</option>
                                <option value="Strip">Strip / Blister</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-xs">Opening Stock</Label>
                            <Input v-model.number="form.stock" type="number" class="mt-1 text-xs font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs">Reorder Point</Label>
                            <Input v-model.number="form.minStock" type="number" class="mt-1 text-xs font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs">VAT Rate (%)</Label>
                            <Input v-model.number="form.taxRate" type="number" class="mt-1 text-xs font-mono" />
                        </div>
                    </div>
                </div>

                <SheetFooter class="pt-4 border-t border-border/70 flex justify-between gap-2">
                    <Button variant="ghost" size="sm" @click="productDrawerOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isSavingProduct" @click="handleSaveProduct">
                        Save {{ terms.itemLabel }}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>

        <!-- ================================================
             STOCK ADJUSTMENT MODAL (with mode & log)
             ================================================ -->
        <Dialog :open="adjustModalOpen" @update:open="(v) => (adjustModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Stock Adjustment</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-2 text-xs">
                    <div class="p-3 rounded-xl bg-muted/40 border border-border/80">
                        <p class="font-bold text-foreground text-sm">{{ adjustTarget?.name }}</p>
                        <div class="flex items-center gap-4 mt-1 text-[11px] text-muted-foreground">
                            <span>SKU: <strong class="font-mono">{{ adjustTarget?.sku }}</strong></span>
                            <span>Current Stock: <strong class="font-mono text-foreground">{{ adjustTarget?.stock }} {{ adjustTarget?.unit }}</strong></span>
                        </div>
                    </div>

                    <!-- Adjustment Mode Tabs -->
                    <div>
                        <Label class="text-xs">Adjustment Method</Label>
                        <div class="flex rounded-xl border border-border/80 p-1 bg-card text-xs font-semibold mt-1">
                            <button
                                type="button"
                                :class="['flex-1 px-3 py-1.5 rounded-lg transition-all', adjustMode === 'set' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground']"
                                @click="adjustMode = 'set'"
                            >Set Quantity</button>
                            <button
                                type="button"
                                :class="['flex-1 px-3 py-1.5 rounded-lg transition-all', adjustMode === 'add' ? 'bg-emerald-600 text-white shadow' : 'text-muted-foreground hover:text-foreground']"
                                @click="adjustMode = 'add'"
                            >+ Receive Stock</button>
                            <button
                                type="button"
                                :class="['flex-1 px-3 py-1.5 rounded-lg transition-all', adjustMode === 'subtract' ? 'bg-rose-600 text-white shadow' : 'text-muted-foreground hover:text-foreground']"
                                @click="adjustMode = 'subtract'"
                            >- Deduct</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">
                                {{ adjustMode === 'set' ? 'New Quantity' : adjustMode === 'add' ? 'Quantity to Receive' : 'Quantity to Deduct' }}
                            </Label>
                            <Input
                                v-if="adjustMode === 'set'"
                                v-model.number="adjustQty"
                                type="number"
                                class="mt-1 font-mono font-bold text-lg text-center"
                            />
                            <Input
                                v-else
                                v-model.number="adjustDelta"
                                type="number"
                                class="mt-1 font-mono font-bold text-lg text-center"
                            />
                        </div>
                        <div>
                            <Label class="text-xs">Reason for Adjustment</Label>
                            <select v-model="adjustReason" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option>Stock Count Correction</option>
                                <option>New Purchase / GRN Received</option>
                                <option>Damaged / Spoilage</option>
                                <option>Expired — Written Off</option>
                                <option>Return to Supplier</option>
                                <option v-if="isMultiBranch">Inter-Branch Transfer</option>
                                <option>Promotions / Sampling</option>
                                <option>Opening Stock Entry</option>
                            </select>
                        </div>
                    </div>

                    <!-- Preview Result -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-muted/40 border border-border/80 text-xs">
                        <div>
                            <span class="text-muted-foreground">Current:</span>
                            <span class="font-mono font-bold ml-1">{{ adjustTarget?.stock }} {{ adjustTarget?.unit }}</span>
                        </div>
                        <ArrowDownRight class="size-4 text-muted-foreground" />
                        <div>
                            <span class="text-muted-foreground">New Total:</span>
                            <span class="font-mono font-black ml-1 text-primary text-sm">{{ previewNewQty }} {{ adjustTarget?.unit }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="adjustModalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary font-bold" :loading="isAdjusting" @click="handleSaveAdjustment">Apply Adjustment</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Barcode Label Generator & Thermal Printing Modal -->
        <BarcodeLabelModal
            :open="labelModalOpen"
            :product="labelProduct"
            @update:open="(v) => (labelModalOpen = v)"
        />

        <!-- Product Universal Document Import Modal -->
        <ProductImportModal
            v-model:open="importModalOpen"
            :existing-categories="categories"
        />

        <!-- Delete Product Warning Alert Dialog -->
        <ConfirmDialog
            v-model:open="deleteConfirmOpen"
            :title="`Delete ${terms.itemLabel}`"
            :description="`Are you sure you want to permanently delete this ${terms.itemLabel.toLowerCase()}? All associated pricing records and barcode history will be removed.`"
            :item-name="deleteProductTarget ? `${deleteProductTarget.name} (SKU: ${deleteProductTarget.sku})` : undefined"
            :warning-note="deleteProductTarget && deleteProductTarget.stock > 0 ? `Caution: This item has ${deleteProductTarget.stock} units currently in active stock. Reconcile or adjust stock first to avoid ledger discrepancies.` : undefined"
            confirm-text="Yes, Delete Product"
            :loading="isDeletingProduct"
            @confirm="confirmDeleteProduct"
        />

        <!-- Interactive Product Catalog Guide Overlay -->
        <ProductGuideOverlay
            v-model:open="guideOverlayOpen"
            v-model:current-step="guideCurrentStep"
            @action="handleGuideAction"
        />
    </AppLayout>
</template>
