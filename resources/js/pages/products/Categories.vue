<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
    ChevronUp,
    Edit2,
    ExternalLink,
    Layers,
    Package,
    PackageCheck,
    PackagePlus,
    Plus,
    RefreshCcw,
    RotateCcw,
    Search,
    Tag,
    Trash2,
    TrendingUp,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    { value: 'Piece',   label: 'Piece / Unit (Pcs)',      hint: 'Individual items sold one-by-one' },
    { value: 'Dozen',   label: 'Dozen (12s)',              hint: 'Bundles of 12 identical items' },
    { value: 'Box',     label: 'Box / Carton',             hint: 'Manufacturer case (custom qty)' },
    { value: 'Bale',    label: 'Bale (Bulk outer pack)',   hint: 'Heavy shrink-wrap/sack with multiple packs' },
    { value: 'Pack',    label: 'Pack / Packet',            hint: 'Consumer pre-packaged item' },
    { value: 'Kg',      label: 'Kilogram (Kg)',            hint: 'Sold by weight in kilograms' },
    { value: 'Gram',    label: 'Gram (g)',                 hint: 'Small weight measurements' },
    { value: 'Litre',   label: 'Litre (L)',                hint: 'Sold by volume in litres' },
    { value: 'Ml',      label: 'Millilitre (ml)',          hint: 'Small liquid volumes' },
    { value: 'Pair',    label: 'Pair',                     hint: 'Two matching items sold together' },
    { value: 'Roll',    label: 'Roll',                     hint: 'Rolled material (wire, fabric, paper)' },
    { value: 'Bag',     label: 'Bag / Sack',               hint: 'Loose material in a bag or sack' },
    { value: 'Bottle',  label: 'Bottle',                   hint: 'Liquids in bottles' },
    { value: 'Can',     label: 'Can / Tin',                hint: 'Canned or tinned goods' },
    { value: 'Loaf',    label: 'Loaf',                     hint: 'Bread or loaf-type products' },
    { value: 'Set',     label: 'Set / Kit',                hint: 'Grouped items sold as a set' },
    { value: 'Strip',   label: 'Strip / Blister',          hint: 'Medicine or small items in a strip' },
    { value: 'Vial',    label: 'Vial / Ampoule',           hint: 'Injectable or small liquid medicines' },
    { value: 'Length',  label: 'Length (Running)',         hint: 'Sold by length (pipes, timber, cable)' },
];

// Available preset colors
const colorPresets = [
    '#ef4444', '#f97316', '#f59e0b', '#84cc16',
    '#22c55e', '#14b8a6', '#3b82f6', '#8b5cf6',
    '#ec4899', '#64748b', '#0ea5e9', '#a16207',
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
};

const filteredCategories = computed(() => {
    let list = (props.categories ?? []).slice();

    // 1. Search
    const q = searchQuery.value.toLowerCase().trim();
    if (q) {
        list = list.filter(
            c => (c.name ?? '').toLowerCase().includes(q) ||
                 (c.description ?? '').toLowerCase().includes(q) ||
                 (c.default_unit ?? '').toLowerCase().includes(q),
        );
    }

    // 2. Unit filter
    if (selectedUnitFilter.value !== 'all') {
        list = list.filter(c => (c.default_unit ?? 'Piece') === selectedUnitFilter.value);
    }

    // 3. Status filter
    if (selectedStatusFilter.value === 'active') {
        list = list.filter(c => c.is_active);
    } else if (selectedStatusFilter.value === 'inactive') {
        list = list.filter(c => !c.is_active);
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
const totalStockValue  = computed(() => (props.categories ?? []).reduce((s, c) => s + c.stock_value,  0));
const topCategory      = computed(() => {
    const cats = (props.categories ?? []).slice();
    cats.sort((a, b) => b.retail_value - a.retail_value);
    return cats[0] ?? null;
});

// Unit badge distribution
const unitDistribution = computed(() => {
    const map: Record<string, number> = {};
    for (const c of (props.categories ?? [])) {
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
        Bale:   'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
        Box:    'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
        Dozen:  'bg-violet-500/15 text-violet-700 dark:text-violet-300 border-violet-500/30',
        Piece:  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
        Kg:     'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30',
        Litre:  'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border-cyan-500/30',
        Pack:   'bg-teal-500/15 text-teal-700 dark:text-teal-300 border-teal-500/30',
    };
    return map[unit] ?? 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30';
};

// ─── Create / Edit modal ──────────────────────────────────────────────────────
const modalOpen    = ref(false);
const editingCat   = ref<any | null>(null);
const isSaving     = ref(false);

const emptyForm = () => ({
    name:             '',
    description:      '',
    color:            '#3b82f6',
    icon:             'Package',
    default_unit:     'Piece',
    default_tax_rate: 16,
    is_active:        true,
    sort_order:       0,
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
        name:             cat.name,
        description:      cat.description ?? '',
        color:            cat.color ?? '#3b82f6',
        icon:             cat.icon ?? 'Package',
        default_unit:     cat.default_unit ?? 'Piece',
        default_tax_rate: cat.default_tax_rate ?? 16,
        is_active:        cat.is_active,
        sort_order:       cat.sort_order ?? 0,
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
const deleteTarget      = ref<any | null>(null);
const isDeleting        = ref(false);

const openDelete = (cat: any) => {
    deleteTarget.value  = cat;
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
    router.post('/products/categories/import-orphaned', { name }, {
        onSuccess: () => success('Imported', `"${name}" is now a formal category.`),
        onError:   (e) => error('Error', Object.values(e)[0] as string),
    });
};
</script>

<template>
    <Head :title="`${terms.categoryLabel}s — ${terms.itemsPlural}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto">

            <!-- ─── Header ─────────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <Layers class="size-6 text-primary" />
                            {{ terms.categoryLabel }}s
                        </h1>
                        <Badge variant="outline" class="font-bold text-xs uppercase bg-primary/10 text-primary border-primary/20">
                            {{ terms.badgeLabel }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Organise your {{ terms.itemsPlural.toLowerCase() }} catalog into logical groups. Define default packaging units (Bales, Boxes, Dozens, Pieces) and VAT rates per category.
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Link to Catalog -->
                    <Button as="a" href="/products" size="sm" variant="outline" class="h-9 gap-1.5 text-xs">
                        <Package class="size-3.5" />
                        All {{ terms.itemsPlural }}
                    </Button>
                    <Button size="sm" class="h-9 gap-1.5 text-xs bg-primary hover:bg-primary/90 text-primary-foreground font-bold" @click="openCreate">
                        <Plus class="size-4" />
                        New {{ terms.categoryLabel }}
                    </Button>
                </div>
            </div>

            <!-- ─── Stat Cards Row ─────────────────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total {{ terms.categoryLabel }}s</span>
                        <Layers class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-black text-foreground">{{ totalCategories }}</div>
                    <div class="text-[11px] text-muted-foreground">{{ (orphanedNames ?? []).length }} informal / unlinked</div>
                </Card>

                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total {{ terms.itemsPlural }}</span>
                        <Package class="size-4 text-emerald-600" />
                    </div>
                    <div class="text-2xl font-black text-foreground">{{ totalCatProducts }}</div>
                    <div class="text-[11px] text-muted-foreground">Out of {{ totalProducts ?? totalCatProducts }} in catalog</div>
                </Card>

                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total Stock Value</span>
                        <TrendingUp class="size-4 text-blue-500" />
                    </div>
                    <div class="text-xl font-black text-foreground">{{ formatCurrency(totalStockValue) }}</div>
                    <div class="text-[11px] text-muted-foreground">At cost across all categories</div>
                </Card>

                <Card class="border border-border/80 bg-card p-4 space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Top {{ terms.categoryLabel }}</span>
                        <PackageCheck class="size-4 text-amber-500" />
                    </div>
                    <div class="text-base font-black text-foreground truncate">{{ topCategory?.name ?? '—' }}</div>
                    <div class="text-[11px] text-muted-foreground">
                        {{ topCategory ? formatCurrency(topCategory.retail_value) + ' retail value' : 'No categories yet' }}
                    </div>
                </Card>
            </div>

            <!-- ─── Unit Distribution Pills ────────────────────────────── -->
            <div v-if="unitDistribution.length > 0" class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Packaging Units:</span>
                <span
                    v-for="[unit, count] in unitDistribution"
                    :key="unit"
                    :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-semibold', unitBadgeClass(unit)]"
                >
                    <Box class="size-3" />
                    {{ unit }} — {{ count }} item{{ count !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- ─── Orphaned Category Alert ────────────────────────────── -->
            <div v-if="(orphanedNames ?? []).length > 0" class="p-4 rounded-xl border border-amber-500/40 bg-amber-500/5 space-y-3">
                <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400 font-bold text-sm">
                    <AlertTriangle class="size-4 shrink-0" />
                    {{ orphanedNames!.length }} informal {{ orphanedNames!.length === 1 ? 'category' : 'categories' }} found in your catalog
                </div>
                <p class="text-xs text-muted-foreground">
                    The following category names are used in your product records but have not been formally defined yet. Import them to assign default packaging units, colours, and tax rates.
                </p>
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        v-for="name in orphanedNames"
                        :key="name"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300 text-[11px] font-semibold hover:bg-amber-500/20 transition-colors"
                        @click="importOrphan(name)"
                    >
                        <PackagePlus class="size-3" />
                        {{ name }} — Import
                    </button>
                </div>
            </div>

            <!-- ─── Search, Filters & Controls Bar ────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap flex-1 min-w-[280px]">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search categories..."
                            class="pl-9 pr-7 h-9 text-xs"
                        />
                        <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
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
                        class="h-9 text-xs gap-1 text-muted-foreground hover:text-foreground"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3" />
                        Reset
                    </Button>
                </div>

                <!-- Showing count -->
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-bold text-foreground">{{ filteredCategories.length }}</span> of <span class="font-bold text-foreground">{{ totalCategories }}</span> {{ terms.categoryLabel.toLowerCase() }}s
                </div>
            </div>

            <!-- ─── Empty State ────────────────────────────────────────── -->
            <div v-if="filteredCategories.length === 0" class="py-16 rounded-2xl border border-dashed border-border/80 bg-card/50 flex flex-col items-center gap-3 text-center text-muted-foreground">
                <div class="size-14 rounded-2xl bg-muted/60 flex items-center justify-center">
                    <Layers class="size-7 opacity-30" />
                </div>
                <div>
                    <p class="font-bold text-foreground text-sm">
                        {{ searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all' ? 'No matching categories found' : `No ${terms.categoryLabel.toLowerCase()}s yet` }}
                    </p>
                    <p class="text-xs mt-0.5">
                        {{ searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all' ? 'Try adjusting your search terms or filters.' : 'Create your first category to organise products and assign default packaging units.' }}
                    </p>
                </div>
                <Button
                    v-if="searchQuery || selectedUnitFilter !== 'all' || selectedStatusFilter !== 'all'"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs font-semibold mt-1"
                    @click="resetFilters"
                >
                    Clear Filters
                </Button>
                <Button
                    v-else
                    size="sm"
                    class="bg-primary text-primary-foreground font-bold mt-1"
                    @click="openCreate"
                >
                    <Plus class="size-4 mr-1" />
                    Create First {{ terms.categoryLabel }}
                </Button>
            </div>

            <!-- ─── Tabulated Category View ────────────────────────────── -->
            <div v-else class="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-muted/50 border-b border-border/70 text-muted-foreground select-none">
                            <tr>
                                <th class="py-3 px-3 w-12 text-center">
                                    <button class="flex items-center justify-center gap-1 font-bold text-[11px] hover:text-foreground mx-auto" @click="toggleSort('sort_order')">
                                        #
                                        <ArrowUpDown class="size-3 opacity-50" />
                                    </button>
                                </th>
                                <th class="py-3 px-4 min-w-[220px]">
                                    <button class="flex items-center gap-1 font-bold text-[11px] uppercase tracking-wider hover:text-foreground" @click="toggleSort('name')">
                                        {{ terms.categoryLabel }}
                                        <ChevronUp v-if="sortKey === 'name' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'name' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="py-3 px-4 min-w-[150px] font-bold text-[11px] uppercase tracking-wider">
                                    Default Unit
                                </th>
                                <th class="py-3 px-3 text-center w-20 font-bold text-[11px] uppercase tracking-wider">
                                    VAT
                                </th>
                                <th class="py-3 px-4 text-right min-w-[100px]">
                                    <button class="flex items-center justify-end gap-1 font-bold text-[11px] uppercase tracking-wider hover:text-foreground ml-auto" @click="toggleSort('product_count')">
                                        {{ terms.itemsPlural.split(' ')[0] }}
                                        <ChevronUp v-if="sortKey === 'product_count' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'product_count' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="py-3 px-4 text-right min-w-[120px]">
                                    <button class="flex items-center justify-end gap-1 font-bold text-[11px] uppercase tracking-wider hover:text-foreground ml-auto" @click="toggleSort('stock_units')">
                                        Stock Units
                                        <ChevronUp v-if="sortKey === 'stock_units' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'stock_units' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="py-3 px-4 text-right min-w-[150px]">
                                    <button class="flex items-center justify-end gap-1 font-bold text-[11px] uppercase tracking-wider hover:text-foreground ml-auto" @click="toggleSort('stock_value')">
                                        Stock Valuation
                                        <ChevronUp v-if="sortKey === 'stock_value' && sortDirection === 'asc'" class="size-3 text-primary" />
                                        <ChevronDown v-else-if="sortKey === 'stock_value' && sortDirection === 'desc'" class="size-3 text-primary" />
                                        <ArrowUpDown v-else class="size-3 opacity-40" />
                                    </button>
                                </th>
                                <th class="py-3 px-4 text-center w-24 font-bold text-[11px] uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="py-3 px-4 text-right w-28 font-bold text-[11px] uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="(cat, idx) in filteredCategories"
                                :key="cat.id"
                                class="hover:bg-muted/30 transition-colors group"
                            >
                                <!-- Sort Order / Index -->
                                <td class="py-3.5 px-3 text-center font-mono text-[11px] text-muted-foreground">
                                    {{ cat.sort_order || idx + 1 }}
                                </td>

                                <!-- Name & Description -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Color Pill / Accent -->
                                        <div
                                            class="size-8 rounded-xl flex items-center justify-center shrink-0 shadow-xs"
                                            :style="{ backgroundColor: (cat.color || '#3b82f6') + '25', border: `1.5px solid ${(cat.color || '#3b82f6')}55` }"
                                        >
                                            <Tag class="size-3.5" :style="{ color: cat.color || '#3b82f6' }" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-foreground text-sm group-hover:text-primary transition-colors">
                                                    {{ cat.name }}
                                                </span>
                                            </div>
                                            <p v-if="cat.description" class="text-[11px] text-muted-foreground truncate max-w-xs sm:max-w-md">
                                                {{ cat.description }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Packaging Unit Badge -->
                                <td class="py-3.5 px-4">
                                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-semibold', unitBadgeClass(cat.default_unit)]">
                                        <Box class="size-3" />
                                        {{ cat.default_unit }}
                                    </span>
                                </td>

                                <!-- VAT Rate -->
                                <td class="py-3.5 px-3 text-center">
                                    <span class="inline-block text-[11px] font-mono text-muted-foreground bg-muted px-2 py-0.5 rounded-md border border-border/60">
                                        {{ cat.default_tax_rate }}%
                                    </span>
                                </td>

                                <!-- Products Count -->
                                <td class="py-3.5 px-4 text-right">
                                    <a
                                        :href="`/products?category=${encodeURIComponent(cat.name)}`"
                                        class="inline-flex items-center justify-end gap-1.5 font-bold text-foreground hover:text-primary transition-colors group/link"
                                        title="View items in this category"
                                    >
                                        <span class="font-mono text-sm">{{ cat.product_count }}</span>
                                        <ExternalLink class="size-3 text-muted-foreground group-hover/link:text-primary" />
                                    </a>
                                </td>

                                <!-- Stock Units -->
                                <td class="py-3.5 px-4 text-right font-mono text-sm font-semibold text-foreground">
                                    {{ (cat.stock_units ?? 0).toLocaleString() }}
                                    <span class="text-[10px] text-muted-foreground font-sans block">{{ (cat.default_unit ?? 'piece').toLowerCase() }}s</span>
                                </td>

                                <!-- Inventory Value -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-bold text-primary text-xs">
                                        {{ formatCurrency(cat.stock_value) }}
                                    </div>
                                    <span class="text-[10px] text-muted-foreground font-mono block">
                                        Retail: {{ formatCurrency(cat.retail_value) }}
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center">
                                    <span
                                        v-if="cat.is_active"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold"
                                    >
                                        <CheckCircle2 class="size-2.5" /> Active
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-600 text-[10px] font-bold"
                                    >
                                        Inactive
                                    </span>
                                </td>

                                <!-- Actions Toolbar -->
                                <td class="py-3.5 px-4 text-right">
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
                                            :title="cat.product_count > 0 ? `Cannot delete — ${cat.product_count} product(s) assigned` : 'Delete category'"
                                            @click="openDelete(cat)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Table Footer / Summary Row -->
                        <tfoot class="bg-muted/40 border-t-2 border-border/80 font-semibold text-xs">
                            <tr>
                                <td colspan="4" class="py-3 px-4 text-muted-foreground">
                                    Total Across {{ tableTotals.count }} Listed {{ terms.categoryLabel }}s
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-sm font-bold text-foreground">
                                    {{ tableTotals.products.toLocaleString() }} items
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-sm font-bold text-foreground">
                                    {{ tableTotals.stockUnits.toLocaleString() }} units
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-black text-primary text-xs">
                                    {{ formatCurrency(tableTotals.costValue) }}
                                    <span class="text-[10px] text-muted-foreground font-mono font-normal block">
                                        Retail: {{ formatCurrency(tableTotals.retailValue) }}
                                    </span>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════════════
             CREATE / EDIT CATEGORY MODAL
        ═══════════════════════════════════════════════════════════ -->
        <Dialog :open="modalOpen" @update:open="(v) => (modalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto">
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
                        <Label class="text-xs font-semibold">Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
                        <Input v-model="form.description" placeholder="Brief description of this category..." class="mt-1 text-xs" />
                    </div>

                    <!-- Default Packaging Unit -->
                    <div>
                        <Label class="text-xs font-semibold">Default Packaging Unit</Label>
                        <p class="text-[11px] text-muted-foreground mb-1">
                            Sets the default unit when adding new products to this category (e.g. Bale for Flours, Piece for Beverages).
                        </p>
                        <select v-model="form.default_unit" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option v-for="u in unitOptions" :key="u.value" :value="u.value">
                                {{ u.label }}
                            </option>
                        </select>
                        <!-- Hint for selected unit -->
                        <p class="mt-1 text-[11px] text-muted-foreground italic">
                            {{ unitOptions.find(u => u.value === form.default_unit)?.hint }}
                        </p>
                    </div>

                    <!-- VAT Rate & Sort Order -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs font-semibold">Default VAT Rate (%)</Label>
                            <Input v-model.number="form.default_tax_rate" type="number" min="0" max="100" class="mt-1 text-xs font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs font-semibold">Sort Order</Label>
                            <Input v-model.number="form.sort_order" type="number" min="0" class="mt-1 text-xs font-mono" placeholder="0" />
                        </div>
                    </div>

                    <!-- Color Picker -->
                    <div>
                        <Label class="text-xs font-semibold">Category Colour</Label>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <button
                                v-for="preset in colorPresets"
                                :key="preset"
                                type="button"
                                class="size-7 rounded-lg border-2 transition-all hover:scale-110"
                                :style="{ backgroundColor: preset, borderColor: form.color === preset ? preset : 'transparent' }"
                                :class="form.color === preset ? 'ring-2 ring-offset-1 ring-primary/60 scale-110' : ''"
                                @click="form.color = preset"
                            />
                            <!-- Custom color input -->
                            <div class="flex items-center gap-1.5 ml-1">
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="size-7 rounded cursor-pointer border border-border"
                                    title="Pick custom colour"
                                />
                                <span class="text-[11px] font-mono text-muted-foreground">{{ form.color }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Active toggle -->
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="relative inline-flex h-5 w-9 items-center rounded-full border-2 transition-colors"
                            :class="form.is_active ? 'bg-primary border-primary' : 'bg-muted border-border'"
                            @click="form.is_active = !form.is_active"
                        >
                            <span
                                class="inline-block size-3.5 rounded-full bg-white shadow transform transition-transform"
                                :class="form.is_active ? 'translate-x-[18px]' : 'translate-x-0.5'"
                            />
                        </button>
                        <Label class="text-xs font-semibold cursor-pointer" @click="form.is_active = !form.is_active">
                            {{ form.is_active ? 'Active' : 'Inactive' }} — {{ form.is_active ? 'Visible in catalog and POS filters' : 'Hidden from catalog and filters' }}
                        </Label>
                    </div>
                </div>

                <DialogFooter class="pt-4 border-t border-border/60 flex justify-between gap-2">
                    <Button variant="ghost" size="sm" @click="modalOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary text-primary-foreground font-bold" :loading="isSaving" @click="handleSave">
                        {{ editingCat ? 'Save Changes' : `Create ${terms.categoryLabel}` }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ═══════════════════════════════════════════════════════════
             DELETE CONFIRMATION MODAL
        ═══════════════════════════════════════════════════════════ -->
        <Dialog :open="deleteConfirmOpen" @update:open="(v) => (deleteConfirmOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold text-rose-600">Delete {{ terms.categoryLabel }}</DialogTitle>
                </DialogHeader>
                <div class="py-2 text-sm text-muted-foreground space-y-2">
                    <p>Are you sure you want to delete <strong class="text-foreground">"{{ deleteTarget?.name }}"</strong>?</p>
                    <p v-if="deleteTarget?.product_count > 0" class="text-xs p-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                        <AlertTriangle class="size-3.5 shrink-0" />
                        This category has {{ deleteTarget.product_count }} product(s). Reassign them first.
                    </p>
                    <p v-else class="text-xs">This action cannot be undone.</p>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="deleteConfirmOpen = false">Cancel</Button>
                    <Button
                        size="sm"
                        class="bg-rose-600 hover:bg-rose-700 text-white font-bold"
                        :disabled="isDeleting || (deleteTarget?.product_count > 0)"
                        :loading="isDeleting"
                        @click="confirmDelete"
                    >
                        <Trash2 v-if="!isDeleting" class="size-3.5 mr-1" />
                        {{ isDeleting ? 'Deleting…' : 'Yes, Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
