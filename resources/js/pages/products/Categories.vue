<script setup lang="ts">
import CategoriesGuideOverlay from '@/components/products/CategoriesGuideOverlay.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpDown,
    Box,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    ChevronUp,
    Compass,
    Edit2,
    ExternalLink,
    Layers,
    Package,
    PackageCheck,
    PackagePlus,
    Plus,
    RotateCcw,
    Search,
    Tag,
    Trash2,
    TrendingUp,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps<{
    categories?: any[];
    orphanedNames?: string[];
    totalProducts?: number;
}>();

// ─── Composables ─────────────────────────────────────────────────────────────
const { terms } = useBusinessTerms();
const { success, error, warning } = useToast();

// ─── Breadcrumbs ─────────────────────────────────────────────────────────────
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: terms.value.itemsPlural, href: '/products' },
    { title: `${terms.value.categoryLabel}s`, href: '/products/categories' },
]);

// ─── Packaging unit options ───────────────────────────────────────────────────
const unitOptions = [
    { value: 'Piece', label: 'Piece / Unit (Pcs)', hint: 'Individual items sold one-by-one' },
    { value: 'Dozen', label: 'Dozen (12s)', hint: 'Bundles of 12 identical items' },
    { value: 'Box', label: 'Box / Carton', hint: 'Manufacturer case (custom qty)' },
    { value: 'Bale', label: 'Bale (Bulk outer pack)', hint: 'Heavy shrink-wrap/sack with multiple packs' },
    { value: 'Pack', label: 'Pack / Packet', hint: 'Consumer pre-packaged item' },
    { value: 'Kg', label: 'Kilogram (Kg)', hint: 'Sold by weight in kilograms' },
    { value: 'Gram', label: 'Gram (g)', hint: 'Small weight measurements' },
    { value: 'Litre', label: 'Litre (L)', hint: 'Sold by volume in litres' },
    { value: 'Ml', label: 'Millilitre (ml)', hint: 'Small liquid volumes' },
    { value: 'Pair', label: 'Pair', hint: 'Two matching items sold together' },
    { value: 'Roll', label: 'Roll', hint: 'Rolled material (wire, fabric, paper)' },
    { value: 'Bag', label: 'Bag / Sack', hint: 'Loose material in a bag or sack' },
    { value: 'Bottle', label: 'Bottle', hint: 'Liquids in bottles' },
    { value: 'Can', label: 'Can / Tin', hint: 'Canned or tinned goods' },
    { value: 'Loaf', label: 'Loaf', hint: 'Bread or loaf-type products' },
    { value: 'Set', label: 'Set / Kit', hint: 'Grouped items sold as a set' },
    { value: 'Strip', label: 'Strip / Blister', hint: 'Medicine or small items in a strip' },
    { value: 'Vial', label: 'Vial / Ampoule', hint: 'Injectable or small liquid medicines' },
    { value: 'Length', label: 'Length (Running)', hint: 'Sold by length (pipes, timber, cable)' },
];

// Available preset colors
const colorPresets = [
    '#ef4444',
    '#f97316',
    '#f59e0b',
    '#84cc16',
    '#22c55e',
    '#14b8a6',
    '#3b82f6',
    '#8b5cf6',
    '#ec4899',
    '#64748b',
    '#0ea5e9',
    '#a16207',
];

// ─── Search, Filters & Sorting ────────────────────────────────────────────────
const searchQuery = ref('');
const selectedUnitFilter = ref('all');
const selectedStatusFilter = ref<'all' | 'active' | 'inactive'>('all');
const sortKey = ref<'name' | 'product_count' | 'stock_units' | 'stock_value' | 'retail_value' | 'sort_order'>('sort_order');
const sortDirection = ref<'asc' | 'desc'>('asc');

const toggleSort = (key: typeof sortKey.value) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDirection.value = key === 'name' || key === 'sort_order' ? 'asc' : 'desc';
    }
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedUnitFilter.value = 'all';
    selectedStatusFilter.value = 'all';
    sortKey.value = 'sort_order';
    sortDirection.value = 'asc';
    currentPage.value = 1;
};

const filteredCategories = computed(() => {
    let list = (props.categories ?? []).slice();

    // 1. Search
    const q = searchQuery.value.toLowerCase().trim();
    if (q) {
        list = list.filter(
            (c) =>
                (c.name ?? '').toLowerCase().includes(q) ||
                (c.description ?? '').toLowerCase().includes(q) ||
                (c.default_unit ?? '').toLowerCase().includes(q),
        );
    }

    // 2. Unit filter
    if (selectedUnitFilter.value !== 'all') {
        list = list.filter((c) => (c.default_unit ?? 'Piece') === selectedUnitFilter.value);
    }

    // 3. Status filter
    if (selectedStatusFilter.value === 'active') {
        list = list.filter((c) => c.is_active);
    } else if (selectedStatusFilter.value === 'inactive') {
        list = list.filter((c) => !c.is_active);
    }

    // 4. Sort
    list.sort((a, b) => {
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];

        if (typeof valA === 'string') {
            valA = valA.toLowerCase();
            valB = (valB ?? '').toLowerCase();
            return sortDirection.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
        }

        valA = Number(valA ?? 0);
        valB = Number(valB ?? 0);
        return sortDirection.value === 'asc' ? valA - valB : valB - valA;
    });

    return list;
});

// ─── Pagination ──────────────────────────────────────────────────────────────
const currentPage = ref(1);
const pageSize = ref(10);
const pageSizeOptions = [5, 10, 20, 50, 100];

const totalPages = computed(() => {
    return Math.ceil(filteredCategories.value.length / pageSize.value) || 1;
});

const paginatedCategories = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredCategories.value.slice(start, start + pageSize.value);
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const visiblePages = computed(() => {
    const total = totalPages.value;
    const curr = currentPage.value;
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

// Reset page whenever search, filter, or page size changes
watch([searchQuery, selectedUnitFilter, selectedStatusFilter, pageSize], () => {
    currentPage.value = 1;
});

const tableTotals = computed(() => {
    const list = filteredCategories.value;
    return {
        count: list.length,
        products: list.reduce((s, c) => s + (c.product_count ?? 0), 0),
        stockUnits: list.reduce((s, c) => s + (c.stock_units ?? 0), 0),
        costValue: list.reduce((s, c) => s + (c.stock_value ?? 0), 0),
        retailValue: list.reduce((s, c) => s + (c.retail_value ?? 0), 0),
    };
});

// ─── Stats ────────────────────────────────────────────────────────────────────
const totalCategories = computed(() => (props.categories ?? []).length);
const totalCatProducts = computed(() => (props.categories ?? []).reduce((s, c) => s + c.product_count, 0));
const totalStockValue = computed(() => (props.categories ?? []).reduce((s, c) => s + c.stock_value, 0));
const topCategory = computed(() => {
    const cats = (props.categories ?? []).slice();
    cats.sort((a, b) => b.retail_value - a.retail_value);
    return cats[0] ?? null;
});

// Unit badge distribution
const unitDistribution = computed(() => {
    const map: Record<string, number> = {};
    for (const c of props.categories ?? []) {
        const u = c.default_unit ?? 'Piece';
        map[u] = (map[u] ?? 0) + c.product_count;
    }
    return Object.entries(map)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5);
});

// ─── Unit badge colour helper ─────────────────────────────────────────────────
const unitBadgeClass = (unit: string) => {
    const map: Record<string, string> = {
        Bale: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
        Box: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
        Dozen: 'bg-violet-500/15 text-violet-700 dark:text-violet-300 border-violet-500/30',
        Piece: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
        Kg: 'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30',
        Litre: 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border-cyan-500/30',
        Pack: 'bg-teal-500/15 text-teal-700 dark:text-teal-300 border-teal-500/30',
    };
    return map[unit] ?? 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30';
};

// ─── Create / Edit modal ──────────────────────────────────────────────────────
const modalOpen = ref(false);
const editingCat = ref<any | null>(null);
const isSaving = ref(false);

const emptyForm = () => ({
    name: '',
    description: '',
    color: '#3b82f6',
    icon: 'Package',
    default_unit: 'Piece',
    default_tax_rate: 16,
    is_active: true,
    sort_order: 0,
});

const form = ref(emptyForm());

const openCreate = () => {
    editingCat.value = null;
    form.value = emptyForm();
    modalOpen.value = true;
};

const openEdit = (cat: any) => {
    editingCat.value = cat;
    form.value = {
        name: cat.name,
        description: cat.description ?? '',
        color: cat.color ?? '#3b82f6',
        icon: cat.icon ?? 'Package',
        default_unit: cat.default_unit ?? 'Piece',
        default_tax_rate: cat.default_tax_rate ?? 16,
        is_active: cat.is_active,
        sort_order: cat.sort_order ?? 0,
    };
    modalOpen.value = true;
};

const handleSave = () => {
    if (!form.value.name.trim()) {
        warning('Validation', 'Category name is required.');
        return;
    }
    isSaving.value = true;

    if (editingCat.value) {
        router.patch(`/products/categories/${editingCat.value.id}`, form.value, {
            onSuccess: () => {
                success('Updated', `"${form.value.name}" updated.`);
                modalOpen.value = false;
            },
            onError: (e) => error('Error', Object.values(e)[0] as string),
            onFinish: () => (isSaving.value = false),
        });
    } else {
        router.post('/products/categories', form.value, {
            onSuccess: () => {
                success('Created', `"${form.value.name}" created.`);
                modalOpen.value = false;
            },
            onError: (e) => error('Error', Object.values(e)[0] as string),
            onFinish: () => (isSaving.value = false),
        });
    }
};

// ─── Delete ───────────────────────────────────────────────────────────────────
const deleteConfirmOpen = ref(false);
const deleteTarget = ref<any | null>(null);
const isDeleting = ref(false);

const openDelete = (cat: any) => {
    deleteTarget.value = cat;
    deleteConfirmOpen.value = true;
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;
    isDeleting.value = true;
    router.delete(`/products/categories/${deleteTarget.value.id}`, {
        onSuccess: () => {
            success('Deleted', `"${deleteTarget.value.name}" removed.`);
            deleteConfirmOpen.value = false;
        },
        onError: (e) => error('Cannot Delete', Object.values(e)[0] as string),
        onFinish: () => (isDeleting.value = false),
    });
};

// ─── Import orphaned ─────────────────────────────────────────────────────────
const importOrphan = (name: string) => {
    router.post(
        '/products/categories/import-orphaned',
        { name },
        {
            onSuccess: () => success('Imported', `"${name}" is now a formal category.`),
            onError: (e) => error('Error', Object.values(e)[0] as string),
        },
    );
};

// ─── Categories Page Guide State ─────────────────────────────────────────────
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openCategoriesGuide = (step = 0) => {
    guideCurrentStep.value = step;
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'start-walkthrough') {
        guideCurrentStep.value = 1;
    } else if (actionType === 'open-create-modal') {
        openCreate();
    } else if (actionType === 'filter-active') {
        selectedStatusFilter.value = 'active';
    }
};

const handleNewItem = () => {
    openCreate();
};

const handleSubmitForm = () => {
    if (modalOpen.value) {
        handleSave();
    }
};

onMounted(() => {
    window.addEventListener('app:new-item', handleNewItem);
    window.addEventListener('app:submit-form', handleSubmitForm);

    const hasPrompted = sessionStorage.getItem('mde_categories_guide_prompted');
    if (!hasPrompted) {
        setTimeout(() => {
            guideOverlayOpen.value = true;
            sessionStorage.setItem('mde_categories_guide_prompted', 'true');
        }, 600);
    }
});

onUnmounted(() => {
    window.removeEventListener('app:new-item', handleNewItem);
    window.removeEventListener('app:submit-form', handleSubmitForm);
});
</script>

<template>
    <Head :title="`${terms.categoryLabel}s — ${terms.itemsPlural}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- ─── Header ─────────────────────────────────────────────── -->
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground">
                            <Layers class="size-6 text-primary" />
                            {{ terms.categoryLabel }}s
                        </h1>
                        <Badge variant="outline" class="border-primary/20 bg-primary/10 text-xs font-bold uppercase text-primary">
                            {{ terms.badgeLabel }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Organise your {{ terms.itemsPlural.toLowerCase() }} catalog into logical groups. Define default packaging units (Bales, Boxes,
                        Dozens, Pieces) and VAT rates per category.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Page Guide Button -->
                    <Button
                        id="categories-tour-guide-btn"
                        size="sm"
                        variant="outline"
                        class="h-9 gap-1.5 border-primary/30 text-xs font-semibold text-primary hover:bg-primary/10"
                        @click="openCategoriesGuide(0)"
                    >
                        <Compass class="size-4" />
                        Page Guide
                    </Button>

                    <!-- Link to Catalog -->
                    <Button id="categories-tour-products-btn" as="a" href="/products" size="sm" variant="outline" class="h-9 gap-1.5 text-xs">
                        <Package class="size-3.5" />
                        All {{ terms.itemsPlural }}
                    </Button>
                    <Button
                        id="categories-tour-create-btn"
                        size="sm"
                        class="h-9 gap-1.5 bg-primary text-xs font-bold text-primary-foreground hover:bg-primary/90"
                        @click="openCreate"
                    >
                        <Plus class="size-4" />
                        New {{ terms.categoryLabel }}
                    </Button>
                </div>
            </div>

            <!-- ─── Stat Cards Row ─────────────────────────────────────── -->
            <div id="categories-tour-kpi" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card class="space-y-1 border border-border/80 bg-card p-4">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total {{ terms.categoryLabel }}s</span>
                        <Layers class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-black text-foreground">{{ totalCategories }}</div>
                    <div class="text-[11px] text-muted-foreground">{{ (orphanedNames ?? []).length }} informal / unlinked</div>
                </Card>

                <Card class="space-y-1 border border-border/80 bg-card p-4">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total {{ terms.itemsPlural }}</span>
                        <Package class="size-4 text-emerald-600" />
                    </div>
                    <div class="text-2xl font-black text-foreground">{{ totalCatProducts }}</div>
                    <div class="text-[11px] text-muted-foreground">Out of {{ totalProducts ?? totalCatProducts }} in catalog</div>
                </Card>

                <Card class="space-y-1 border border-border/80 bg-card p-4">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total Stock Value</span>
                        <TrendingUp class="size-4 text-blue-500" />
                    </div>
                    <div class="text-xl font-black text-foreground">{{ formatCurrency(totalStockValue) }}</div>
                    <div class="text-[11px] text-muted-foreground">At cost across all categories</div>
                </Card>

                <Card class="space-y-1 border border-border/80 bg-card p-4">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Top {{ terms.categoryLabel }}</span>
                        <PackageCheck class="size-4 text-amber-500" />
                    </div>
                    <div class="truncate text-base font-black text-foreground">{{ topCategory?.name ?? '—' }}</div>
                    <div class="text-[11px] text-muted-foreground">
                        {{ topCategory ? formatCurrency(topCategory.retail_value) + ' retail value' : 'No categories yet' }}
                    </div>
                </Card>
            </div>

            <!-- ─── Unit Distribution Pills ────────────────────────────── -->
            <div id="categories-tour-units" v-if="unitDistribution.length > 0" class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Packaging Units:</span>
                <span
                    v-for="[unit, count] in unitDistribution"
                    :key="unit"
                    :class="['inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold', unitBadgeClass(unit)]"
                >
                    <Box class="size-3" />
                    {{ unit }} — {{ count }} item{{ count !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- ─── Orphaned Category Alert ────────────────────────────── -->
            <div
                id="categories-tour-orphaned"
                v-if="(orphanedNames ?? []).length > 0"
                class="space-y-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4"
            >
                <div class="flex items-center gap-2 text-sm font-bold text-amber-700 dark:text-amber-400">
                    <AlertTriangle class="size-4 shrink-0" />
                    {{ orphanedNames!.length }} informal {{ orphanedNames!.length === 1 ? 'category' : 'categories' }} found in your catalog
                </div>
                <p class="text-xs text-muted-foreground">
                    The following category names are used in your product records but have not been formally defined yet. Import them to assign
                    default packaging units, colours, and tax rates.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="name in orphanedNames"
                        :key="name"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-500/40 bg-amber-500/10 px-2.5 py-1.5 text-[11px] font-semibold text-amber-700 transition-colors hover:bg-amber-500/20 dark:text-amber-300"
                        @click="importOrphan(name)"
                    >
                        <PackagePlus class="size-3" />
                        {{ name }} — Import
                    </button>
                </div>
            </div>

            <!-- ─── Search, Filters & Controls Bar ────────────────────────── -->
            <div id="categories-tour-filters" class="flex flex-col flex-wrap justify-between gap-3 sm:flex-row sm:items-center">
                <div class="flex min-w-[280px] flex-1 flex-wrap items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchQuery" placeholder="Search categories..." class="h-9 pl-9 pr-7 text-xs" />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Packaging Unit Filter -->
                    <select
                        v-model="selectedUnitFilter"
                        class="h-9 rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="all">All Packaging Units</option>
                        <option v-for="u in unitOptions" :key="u.value" :value="u.value">
                            {{ u.label }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="selectedStatusFilter"
                        class="h-9 rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>

                    <!-- Reset button -->
                    <Button
                        v-if="searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all'"
                        variant="ghost"
                        size="sm"
                        class="h-9 gap-1 text-xs text-muted-foreground hover:text-foreground"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3" />
                        Reset
                    </Button>
                </div>

                <!-- Showing count -->
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-bold text-foreground">{{ filteredCategories.length }}</span> of
                    <span class="font-bold text-foreground">{{ totalCategories }}</span> {{ terms.categoryLabel.toLowerCase() }}s
                </div>
            </div>

            <!-- ─── Empty State ────────────────────────────────────────── -->
            <div
                v-if="filteredCategories.length === 0"
                class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border/80 bg-card/50 py-16 text-center text-muted-foreground"
            >
                <div class="flex size-14 items-center justify-center rounded-2xl bg-muted/60">
                    <Layers class="size-7 opacity-30" />
                </div>
                <div>
                    <p class="text-sm font-bold text-foreground">
                        {{
                            searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all'
                                ? 'No matching categories found'
                                : `No ${terms.categoryLabel.toLowerCase()}s yet`
                        }}
                    </p>
                    <p class="mt-0.5 text-xs">
                        {{
                            searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all'
                                ? 'Try adjusting your search terms or filters.'
                                : 'Create your first category to organise products and assign default packaging units.'
                        }}
                    </p>
                </div>
                <Button
                    v-if="searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all'"
                    variant="outline"
                    size="sm"
                    class="mt-1 h-8 text-xs font-semibold"
                    @click="resetFilters"
                >
                    Clear Filters
                </Button>
                <Button v-else size="sm" class="mt-1 bg-primary font-bold text-primary-foreground" @click="openCreate">
                    <Plus class="mr-1 size-4" />
                    Create First {{ terms.categoryLabel }}
                </Button>
            </div>

            <!-- ─── Tabulated Category View ────────────────────────────── -->
            <div id="categories-tour-table" v-else class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="select-none border-b border-border/70 bg-muted/50 text-muted-foreground">
                            <tr>
                                <th class="w-12 px-3 py-3 text-center">
                                    <button
                                        class="mx-auto flex items-center justify-center gap-1 text-[11px] font-bold hover:text-foreground"
                                        @click="toggleSort('sort_order')"
                                    >
                                        #
                                        <ArrowUpDown class="size-3 opacity-50" />
                                    </button>
                                </th>
                                <th class="min-w-[220px] px-4 py-3">
                                    <button
                                        class="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider hover:text-foreground"
                                        @click="toggleSort('name')"
                                    >
                                        {{ terms.categoryLabel }}
                                        <ChevronUp v-if="sortKey === 'name' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'name' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="min-w-[150px] px-4 py-3 text-[11px] font-bold uppercase tracking-wider">Default Unit</th>
                                <th class="w-20 px-3 py-3 text-center text-[11px] font-bold uppercase tracking-wider">VAT</th>
                                <th class="min-w-[100px] px-4 py-3 text-right">
                                    <button
                                        class="ml-auto flex items-center justify-end gap-1 text-[11px] font-bold uppercase tracking-wider hover:text-foreground"
                                        @click="toggleSort('product_count')"
                                    >
                                        {{ terms.itemsPlural.split(' ')[0] }}
                                        <ChevronUp v-if="sortKey === 'product_count' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown
                                            v-else-if="sortKey === 'product_count' && sortDirection === 'desc'"
                                            class="size-3 text-primary"
                                        />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="min-w-[120px] px-4 py-3 text-right">
                                    <button
                                        class="ml-auto flex items-center justify-end gap-1 text-[11px] font-bold uppercase tracking-wider hover:text-foreground"
                                        @click="toggleSort('stock_units')"
                                    >
                                        Stock Units
                                        <ChevronUp v-if="sortKey === 'stock_units' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'stock_units' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="min-w-[150px] px-4 py-3 text-right">
                                    <button
                                        class="ml-auto flex items-center justify-end gap-1 text-[11px] font-bold uppercase tracking-wider hover:text-foreground"
                                        @click="toggleSort('stock_value')"
                                    >
                                        Stock Valuation
                                        <ChevronUp v-if="sortKey === 'stock_value' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'stock_value' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="w-24 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider">Status</th>
                                <th class="w-28 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="(cat, idx) in paginatedCategories" :key="cat.id" class="group transition-colors hover:bg-muted/30">
                                <!-- Sort Order / Index -->
                                <td class="px-3 py-3.5 text-center font-mono text-[11px] text-muted-foreground">
                                    {{ cat.sort_order || (currentPage - 1) * pageSize + idx + 1 }}
                                </td>

                                <!-- Name & Description -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <!-- Color Pill / Accent -->
                                        <div
                                            class="shadow-xs flex size-8 shrink-0 items-center justify-center rounded-xl"
                                            :style="{
                                                backgroundColor: (cat.color || '#3b82f6') + '25',
                                                border: `1.5px solid ${cat.color || '#3b82f6'}55`,
                                            }"
                                        >
                                            <Tag class="size-3.5" :style="{ color: cat.color || '#3b82f6' }" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-bold text-foreground transition-colors group-hover:text-primary">
                                                    {{ cat.name }}
                                                </span>
                                            </div>
                                            <p v-if="cat.description" class="max-w-xs truncate text-[11px] text-muted-foreground sm:max-w-md">
                                                {{ cat.description }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Packaging Unit Badge -->
                                <td class="px-4 py-3.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold',
                                            unitBadgeClass(cat.default_unit),
                                        ]"
                                    >
                                        <Box class="size-3" />
                                        {{ cat.default_unit }}
                                    </span>
                                </td>

                                <!-- VAT Rate -->
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="inline-block rounded-md border border-border/60 bg-muted px-2 py-0.5 font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ cat.default_tax_rate }}%
                                    </span>
                                </td>

                                <!-- Products Count -->
                                <td class="px-4 py-3.5 text-right">
                                    <a
                                        :href="`/products?category=${encodeURIComponent(cat.name)}`"
                                        class="group/link inline-flex items-center justify-end gap-1.5 font-bold text-foreground transition-colors hover:text-primary"
                                        title="View items in this category"
                                    >
                                        <span class="font-mono text-sm">{{ cat.product_count }}</span>
                                        <ExternalLink class="size-3 text-muted-foreground group-hover/link:text-primary" />
                                    </a>
                                </td>

                                <!-- Stock Units -->
                                <td class="px-4 py-3.5 text-right font-mono text-sm font-semibold text-foreground">
                                    {{ (cat.stock_units ?? 0).toLocaleString() }}
                                    <span class="block font-sans text-[10px] text-muted-foreground"
                                        >{{ (cat.default_unit ?? 'piece').toLowerCase() }}s</span
                                    >
                                </td>

                                <!-- Inventory Value -->
                                <td class="px-4 py-3.5 text-right">
                                    <div class="font-mono text-xs font-bold text-primary">
                                        {{ formatCurrency(cat.stock_value) }}
                                    </div>
                                    <span class="block font-mono text-[10px] text-muted-foreground">
                                        Retail: {{ formatCurrency(cat.retail_value) }}
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        v-if="cat.is_active"
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="size-2.5" /> Active
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-600"
                                    >
                                        Inactive
                                    </span>
                                </td>

                                <!-- Actions Toolbar -->
                                <td class="px-4 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <!-- View products button -->
                                        <Button
                                            as="a"
                                            :href="`/products?category=${encodeURIComponent(cat.name)}`"
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0 hover:bg-primary/10 hover:text-primary"
                                            title="View catalog items"
                                        >
                                            <ExternalLink class="size-3.5" />
                                        </Button>

                                        <!-- Edit button -->
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0 hover:bg-primary/10 hover:text-primary"
                                            title="Edit category"
                                            @click="openEdit(cat)"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </Button>

                                        <!-- Delete button -->
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0 hover:bg-rose-500/10 hover:text-rose-500 disabled:opacity-40"
                                            :disabled="cat.product_count > 0"
                                            :title="
                                                cat.product_count > 0 ? `Cannot delete — ${cat.product_count} product(s) assigned` : 'Delete category'
                                            "
                                            @click="openDelete(cat)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Table Footer / Summary Row -->
                        <tfoot class="border-t-2 border-border/80 bg-muted/40 text-xs font-semibold">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-muted-foreground">
                                    Total Across {{ tableTotals.count }} Filtered {{ terms.categoryLabel }}s
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm font-bold text-foreground">
                                    {{ tableTotals.products.toLocaleString() }} items
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm font-bold text-foreground">
                                    {{ tableTotals.stockUnits.toLocaleString() }} units
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-xs font-black text-primary">
                                    {{ formatCurrency(tableTotals.costValue) }}
                                    <span class="block font-mono text-[10px] font-normal text-muted-foreground">
                                        Retail: {{ formatCurrency(tableTotals.retailValue) }}
                                    </span>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ─── Table Pagination Bar ────────────────────────────── -->
                <div
                    class="flex flex-col items-center justify-between gap-3 border-t border-border/70 bg-muted/20 px-4 py-3 text-xs text-muted-foreground sm:flex-row"
                >
                    <!-- Left: Showing summary & Page size selector -->
                    <div class="flex w-full flex-wrap items-center justify-between gap-3 sm:w-auto">
                        <div class="text-xs text-muted-foreground">
                            Showing
                            <span class="font-bold text-foreground">{{
                                filteredCategories.length === 0 ? 0 : (currentPage - 1) * pageSize + 1
                            }}</span>
                            to
                            <span class="font-bold text-foreground">{{ Math.min(currentPage * pageSize, filteredCategories.length) }}</span>
                            of
                            <span class="font-bold text-foreground">{{ filteredCategories.length }}</span>
                            {{ terms.categoryLabel.toLowerCase() }}s
                        </div>

                        <!-- Rows per page selector -->
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span>Per page:</span>
                            <select
                                v-model.number="pageSize"
                                class="h-7 rounded-md border border-input bg-background px-2 text-xs font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option v-for="size in pageSizeOptions" :key="size" :value="size">
                                    {{ size }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Right: Page Navigation -->
                    <div v-if="totalPages > 1" class="flex items-center gap-1">
                        <!-- First Page -->
                        <Button variant="outline" size="sm" class="h-8 w-8 p-0" :disabled="currentPage === 1" title="First page" @click="goToPage(1)">
                            <ChevronsLeft class="size-3.5" />
                        </Button>

                        <!-- Prev Page -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0"
                            :disabled="currentPage === 1"
                            title="Previous page"
                            @click="goToPage(currentPage - 1)"
                        >
                            <ChevronLeft class="size-3.5" />
                        </Button>

                        <!-- Page Pills -->
                        <template v-for="(p, i) in visiblePages" :key="i">
                            <span v-if="p === '...'" class="select-none px-2 py-1 text-xs text-muted-foreground"> … </span>
                            <Button
                                v-else
                                :variant="currentPage === p ? 'default' : 'outline'"
                                size="sm"
                                :class="[
                                    'h-8 min-w-8 px-2 text-xs font-semibold',
                                    currentPage === p
                                        ? 'shadow-xs bg-primary font-bold text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                ]"
                                @click="goToPage(p as number)"
                            >
                                {{ p }}
                            </Button>
                        </template>

                        <!-- Next Page -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0"
                            :disabled="currentPage === totalPages"
                            title="Next page"
                            @click="goToPage(currentPage + 1)"
                        >
                            <ChevronRight class="size-3.5" />
                        </Button>

                        <!-- Last Page -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0"
                            :disabled="currentPage === totalPages"
                            title="Last page"
                            @click="goToPage(totalPages)"
                        >
                            <ChevronsRight class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             CREATE / EDIT CATEGORY MODAL
        ═══════════════════════════════════════════════════════════ -->
        <Dialog :open="modalOpen" @update:open="(v) => (modalOpen = v)">
            <DialogContent class="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        {{ editingCat ? `Edit ${terms.categoryLabel}` : `New ${terms.categoryLabel}` }}
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-2 text-xs">
                    <!-- Name -->
                    <div>
                        <Label class="text-xs font-semibold">{{ terms.categoryLabel }} Name <span class="text-rose-500">*</span></Label>
                        <Input v-model="form.name" :placeholder="`e.g. Dairy & Beverages`" class="mt-1 text-xs" />
                    </div>

                    <!-- Description -->
                    <div>
                        <Label class="text-xs font-semibold">Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
                        <Input v-model="form.description" placeholder="Brief description of this category..." class="mt-1 text-xs" />
                    </div>

                    <!-- Default Packaging Unit -->
                    <div>
                        <Label class="text-xs font-semibold">Default Packaging Unit</Label>
                        <p class="mb-1 text-[11px] text-muted-foreground">
                            Sets the default unit when adding new products to this category (e.g. Bale for Flours, Piece for Beverages).
                        </p>
                        <select
                            v-model="form.default_unit"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="u in unitOptions" :key="u.value" :value="u.value">
                                {{ u.label }}
                            </option>
                        </select>
                        <!-- Hint for selected unit -->
                        <p class="mt-1 text-[11px] italic text-muted-foreground">
                            {{ unitOptions.find((u) => u.value === form.default_unit)?.hint }}
                        </p>
                    </div>

                    <!-- VAT Rate & Sort Order -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs font-semibold">Default VAT Rate (%)</Label>
                            <Input v-model.number="form.default_tax_rate" type="number" min="0" max="100" class="mt-1 font-mono text-xs" />
                        </div>
                        <div>
                            <Label class="text-xs font-semibold">Sort Order</Label>
                            <Input v-model.number="form.sort_order" type="number" min="0" class="mt-1 font-mono text-xs" placeholder="0" />
                        </div>
                    </div>

                    <!-- Color Picker -->
                    <div>
                        <Label class="text-xs font-semibold">Category Colour</Label>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                v-for="preset in colorPresets"
                                :key="preset"
                                type="button"
                                class="size-7 rounded-lg border-2 transition-all hover:scale-110"
                                :style="{ backgroundColor: preset, borderColor: form.color === preset ? preset : 'transparent' }"
                                :class="form.color === preset ? 'scale-110 ring-2 ring-primary/60 ring-offset-1' : ''"
                                @click="form.color = preset"
                            />
                            <!-- Custom color input -->
                            <div class="ml-1 flex items-center gap-1.5">
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="size-7 cursor-pointer rounded border border-border"
                                    title="Pick custom colour"
                                />
                                <span class="font-mono text-[11px] text-muted-foreground">{{ form.color }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Active toggle -->
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="relative inline-flex h-5 w-9 items-center rounded-full border-2 transition-colors"
                            :class="form.is_active ? 'border-primary bg-primary' : 'border-border bg-muted'"
                            @click="form.is_active = !form.is_active"
                        >
                            <span
                                class="inline-block size-3.5 transform rounded-full bg-white shadow transition-transform"
                                :class="form.is_active ? 'translate-x-[18px]' : 'translate-x-0.5'"
                            />
                        </button>
                        <Label class="cursor-pointer text-xs font-semibold" @click="form.is_active = !form.is_active">
                            {{ form.is_active ? 'Active' : 'Inactive' }} —
                            {{ form.is_active ? 'Visible in catalog and POS filters' : 'Hidden from catalog and filters' }}
                        </Label>
                    </div>
                </div>

                <DialogFooter class="flex justify-between gap-2 border-t border-border/60 pt-4">
                    <Button variant="ghost" size="sm" @click="modalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary font-bold text-primary-foreground" :loading="isSaving" @click="handleSave">
                        {{ editingCat ? 'Save Changes' : `Create ${terms.categoryLabel}` }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ═══════════════════════════════════════════════════════════
             DELETE CONFIRMATION MODAL
             ═══════════════════════════════════════════════════════════ -->
        <!-- Delete Category Warning Alert Dialog -->
        <ConfirmDialog
            v-model:open="deleteConfirmOpen"
            :title="`Delete ${terms.categoryLabel}`"
            :description="`Are you sure you want to permanently delete this ${terms.categoryLabel.toLowerCase()}? This action cannot be undone.`"
            :item-name="deleteTarget?.name"
            :warning-note="
                deleteTarget && deleteTarget.product_count > 0
                    ? `Cannot delete: This category currently has ${deleteTarget.product_count} product(s) assigned. Reassign them to another category first.`
                    : undefined
            "
            confirm-text="Yes, Delete Category"
            :disabled="deleteTarget?.product_count > 0"
            :loading="isDeleting"
            @confirm="confirmDelete"
        />

        <!-- Interactive Categories Guide Overlay -->
        <CategoriesGuideOverlay v-model:open="guideOverlayOpen" v-model:current-step="guideCurrentStep" @action="handleGuideAction" />
    </AppLayout>
</template>
