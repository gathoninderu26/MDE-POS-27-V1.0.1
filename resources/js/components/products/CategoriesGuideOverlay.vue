<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Box,
    Check,
    CheckCircle2,
    Compass,
    Info,
    Layers,
    Plus,
    Search,
    Sparkles,
    TrendingUp,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    open: boolean;
    currentStep?: number;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    currentStep: 0,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:currentStep', step: number): void;
    (e: 'action', actionType: string): void;
}>();

export interface TourSection {
    id: string;
    targetId: string;
    stepNumber: number;
    title: string;
    tag: string;
    shape: 'rect' | 'circle';
    rx: number;
    icon: any;
    usedFor: string;
    capabilities: string[];
    howToUse: string[];
    proTip: string;
    fallbackTargetId?: string;
    shortcuts?: string;
    actionLabel?: string;
    actionType?: string;
}

const sections: TourSection[] = [
    {
        id: 'start',
        targetId: 'categories-tour-guide-btn',
        stepNumber: 0,
        title: 'Product Categories & Hierarchy Guide',
        tag: 'ORIENTATION & TAXONOMY',
        shape: 'rect',
        rx: 8,
        icon: Compass,
        usedFor:
            'Welcome to the Product Categories Control Center! Categories group your inventory catalog into logical departments, assign default packaging units (Bales, Boxes, Dozens, Pieces, Kg, Litres), standardize VAT tax rates, and power departmental sales analytics.',
        capabilities: [
            'Catalog Grouping & Departments',
            'Default Packaging Unit Pre-selection',
            'Standardized VAT Tax Rates (16% / 0%)',
            'Department Stock Valuation Audits',
            'Orphaned Category Auto-Import',
            'POS Fast-Key & Search Speed',
        ],
        howToUse: [
            'Click "Start Tour" or press → on your keyboard to navigate through each category section.',
            'Assign packaging units so new products automatically inherit compliant unit definitions.',
            'Filter and sort departments to monitor inventory capital across categories.',
            'Press Esc at any time to close the guide and manage your catalog.',
        ],
        proTip: 'The "Page Guide" button remains pinned in the header toolbar at all times so store managers and catalog admins can reference it anytime.',
        shortcuts: 'Click Page Guide (Begin) • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Start Walkthrough',
        actionType: 'start-walkthrough',
    },
    {
        id: 'create-category',
        targetId: 'categories-tour-create-btn',
        stepNumber: 1,
        title: 'Create & Configure Category',
        tag: 'TAXONOMY PROVISIONING',
        shape: 'rect',
        rx: 8,
        icon: Plus,
        usedFor:
            'Provision a new product category or department. Customize unique UI colors for POS touchscreens, select default packaging units (e.g. Bale, Box, Piece), and set default VAT tax rates (16% Standard or 0% Exempt).',
        capabilities: [
            'Department Name & Description',
            'Color Palette & POS Identification',
            'Default Packaging Unit Pre-selection',
            'Default VAT Tax Rate (16% / 0%)',
            'Display Sort Ordering Control',
        ],
        howToUse: [
            'Click "New Category" to open the creation dialog.',
            'Pick a distinct color tag for fast recognition on cashier POS screens.',
            'Set the default packaging unit to automate product creation workflows.',
        ],
        proTip: 'Setting a default VAT rate at the category level ensures newly created items are taxed accurately without manual configuration.',
        shortcuts: 'Click Highlighted Button • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Open New Category Modal',
        actionType: 'open-create-modal',
    },
    {
        id: 'kpi-cards',
        targetId: 'categories-tour-kpi',
        stepNumber: 2,
        title: 'Catalog Valuation & Category KPIs',
        tag: 'DEPARTMENT TELEMETRY',
        shape: 'rect',
        rx: 12,
        icon: TrendingUp,
        usedFor:
            'Monitor aggregate department telemetry across your entire inventory — including total registered categories, catalog product coverage, total stock value at cost, and your highest-grossing department.',
        capabilities: [
            'Total Category Count vs Unlinked',
            'Catalog Product Coverage',
            'Consolidated Stock Valuation at Cost',
            'Top Department by Retail Valuation',
        ],
        howToUse: [
            'Inspect the Top Category card to identify which department represents your largest retail valuation.',
            'Keep track of unlinked informal categories that need official structuring.',
        ],
        proTip: 'Regularly reviewing stock valuation by department helps balance inventory investments and identify slow-moving capital.',
        shortcuts: 'Esc (Exit) • ← / → (Browse)',
    },
    {
        id: 'unit-distribution',
        targetId: 'categories-tour-units',
        fallbackTargetId: 'categories-tour-kpi',
        stepNumber: 3,
        title: 'Packaging Units Breakdown',
        tag: 'MULTI-UNIT INVENTORY AUDIT',
        shape: 'rect',
        rx: 10,
        icon: Box,
        usedFor: 'View the live breakdown of products across standard packaging units — such as Bales, Boxes, Dozens, Kilograms, Litres, and Pieces.',
        capabilities: ['Real-Time Unit Distribution Counts', 'Visual Packaging Unit Badges', 'Wholesale vs Retail Product Ratios'],
        howToUse: [
            "Check the top packaging unit badges to understand your store's primary supply formats.",
            'Ensure bulk wholesale items (Bales, Boxes) are mapped to dedicated categories for easy stocktaking.',
        ],
        proTip: 'Standardizing units across categories simplifies bulk purchase order generation and supplier invoice reconciliation.',
        shortcuts: 'Esc (Exit) • ← / → (Browse)',
    },
    {
        id: 'orphaned-categories',
        targetId: 'categories-tour-orphaned',
        fallbackTargetId: 'categories-tour-filters',
        stepNumber: 4,
        title: 'Orphaned Category Auto-Import',
        tag: 'DATA MIGRATION & CLEANUP',
        shape: 'rect',
        rx: 12,
        icon: AlertTriangle,
        usedFor:
            'Automatically detect and import category names discovered during CSV product imports or POS migrations that do not yet exist as formal catalog departments.',
        capabilities: [
            'One-Click Category Formalization',
            'Auto-Link Discovered CSV Categories',
            'Preserve Product Associations',
            'Batch Migration Cleanup',
        ],
        howToUse: [
            'Click any discovered category tag to immediately promote it to a full system category.',
            'Once imported, edit the category to assign custom packaging units and tax rates.',
        ],
        proTip: 'Bulk product imports often introduce text-only category names; this importer lets you convert them into structured records with a single click.',
        shortcuts: 'Esc (Exit) • ← / → (Browse)',
    },
    {
        id: 'filter-controls',
        targetId: 'categories-tour-filters',
        stepNumber: 5,
        title: 'Search & Unit Filter Controls',
        tag: 'CATALOG REFINEMENT',
        shape: 'rect',
        rx: 10,
        icon: Search,
        usedFor:
            'Filter categories instantly by keyword search, default packaging unit (e.g. Piece, Box, Bale, Kg), or active/inactive lifecycle status.',
        capabilities: [
            'Instant Real-Time Keyword Search',
            'Packaging Unit Dropdown Filter',
            'Active vs Inactive Status Filter',
            'One-Click Filter Reset',
        ],
        howToUse: [
            'Type in the search bar to locate departments by name or description.',
            'Filter by specific packaging unit (e.g. "Bale") to view only wholesale categories.',
        ],
        proTip: 'Use the status filter to isolate archived or inactive departments without deleting historical sales records.',
        shortcuts: 'Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Filter Active Only',
        actionType: 'filter-active',
    },
    {
        id: 'categories-table',
        targetId: 'categories-tour-table',
        stepNumber: 6,
        title: 'Tabulated Categories Ledger',
        tag: 'DEPARTMENT MANAGEMENT & VALUATION',
        shape: 'rect',
        rx: 14,
        icon: Layers,
        usedFor:
            'Review the comprehensive categories table with product counts, total stock units in warehouse, cost valuation, retail potential value, and inline editing or deletion actions.',
        capabilities: [
            'Multi-Column Sorting (Name, Count, Stock, Valuation)',
            'Packaging Unit Badges & VAT Rate Indicators',
            'Stock Valuation at Cost & Retail Revenue',
            'Inline Category Editing Modal',
            'Safe Deletion Safeguards (Protects Active Items)',
        ],
        howToUse: [
            'Click any column header to sort ascending or descending.',
            'Click the Edit icon to update packaging units, theme colors, or tax rates.',
            'Click Delete to safely retire unused categories (categories with active products are protected).',
        ],
        proTip: 'Hovering over table rows highlights department margins between cost stock valuation and total retail value.',
        shortcuts: 'Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Create Category',
        actionType: 'open-create-modal',
    },
];

const internalStep = ref(props.currentStep || 0);
const targetRect = ref<{ top: number; left: number; width: number; height: number; right: number; bottom: number } | null>(null);
const windowSize = ref({ width: 0, height: 0 });
const cardRef = ref<HTMLElement | null>(null);
const cardRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);

watch(
    () => props.currentStep,
    (newVal) => {
        if (typeof newVal === 'number') {
            internalStep.value = newVal;
            updateTargetPosition();
        }
    },
);

const currentSection = computed(() => {
    return sections[internalStep.value] || sections[0];
});

const isLastStep = computed(() => internalStep.value === sections.length - 1);

const updateTargetPosition = () => {
    if (typeof window === 'undefined') return;

    windowSize.value = {
        width: window.innerWidth,
        height: window.innerHeight,
    };

    nextTick(() => {
        let el = document.getElementById(currentSection.value.targetId);
        if (!el && currentSection.value.fallbackTargetId) {
            el = document.getElementById(currentSection.value.fallbackTargetId);
        }
        if (el) {
            const rect = el.getBoundingClientRect();
            targetRect.value = {
                top: rect.top,
                left: rect.left,
                width: rect.width,
                height: rect.height,
                right: rect.right,
                bottom: rect.bottom,
            };

            // Auto-scroll target into view if outside viewport
            if (rect.top < 70 || rect.bottom > window.innerHeight - 70) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => {
                    const updated = el.getBoundingClientRect();
                    targetRect.value = {
                        top: updated.top,
                        left: updated.left,
                        width: updated.width,
                        height: updated.height,
                        right: updated.right,
                        bottom: updated.bottom,
                    };
                }, 350);
            }
        } else {
            targetRect.value = {
                top: window.innerHeight / 2 - 40,
                left: window.innerWidth / 2 - 150,
                width: 300,
                height: 80,
                right: window.innerWidth / 2 + 150,
                bottom: window.innerHeight / 2 + 40,
            };
        }

        nextTick(() => {
            if (cardRef.value) {
                const cRect = cardRef.value.getBoundingClientRect();
                cardRect.value = {
                    top: cRect.top,
                    left: cRect.left,
                    width: cRect.width,
                    height: cRect.height,
                };
            }
        });
    });
};

const nextStep = () => {
    if (internalStep.value < sections.length - 1) {
        internalStep.value++;
        emit('update:currentStep', internalStep.value);
        updateTargetPosition();
    } else {
        close();
    }
};

const prevStep = () => {
    if (internalStep.value > 0) {
        internalStep.value--;
        emit('update:currentStep', internalStep.value);
        updateTargetPosition();
    }
};

const goToStep = (idx: number) => {
    internalStep.value = idx;
    emit('update:currentStep', idx);
    updateTargetPosition();
};

const close = () => {
    emit('update:open', false);
};

const handleAction = () => {
    if (currentSection.value.actionType === 'start-walkthrough') {
        nextStep();
        return;
    }
    if (currentSection.value.actionType) {
        emit('action', currentSection.value.actionType);
    }
};

const handleTargetClick = () => {
    if (currentSection.value.id === 'start') {
        nextStep();
    } else {
        handleAction();
    }
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (!props.open) return;
    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowRight') {
        nextStep();
    } else if (e.key === 'ArrowLeft') {
        prevStep();
    }
};

watch(
    () => props.open,
    (val) => {
        if (val) {
            updateTargetPosition();
            window.addEventListener('resize', updateTargetPosition);
            window.addEventListener('scroll', updateTargetPosition, true);
            window.addEventListener('keydown', handleKeyDown);
        } else {
            window.removeEventListener('resize', updateTargetPosition);
            window.removeEventListener('scroll', updateTargetPosition, true);
            window.removeEventListener('keydown', handleKeyDown);
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.open) {
        updateTargetPosition();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateTargetPosition);
        window.removeEventListener('scroll', updateTargetPosition, true);
        window.removeEventListener('keydown', handleKeyDown);
    }
});

// Calculate intelligent placement for callout card ensuring zero overlap with highlighted button/target
const cardStyle = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return {
            bottom: '16px',
            left: '50%',
            transform: 'translateX(-50%)',
            maxWidth: '360px',
            width: 'calc(100% - 24px)',
        };
    }

    const { top, left, width, height } = targetRect.value;
    const isMobile = windowSize.value.width < 768;
    const cardWidth = isMobile ? Math.min(360, windowSize.value.width - 24) : 400;
    const estimatedCardHeight = 420;

    // Mobile placement
    if (isMobile) {
        const targetCenterY = top + height / 2;
        if (targetCenterY > windowSize.value.height / 2) {
            return {
                top: '12px',
                left: '12px',
                right: '12px',
                width: 'auto',
                maxWidth: '380px',
                margin: '0 auto',
            };
        }
        return {
            bottom: '12px',
            left: '12px',
            right: '12px',
            width: 'auto',
            maxWidth: '380px',
            margin: '0 auto',
        };
    }

    // Start / Top header placement
    if (currentSection.value.id === 'start' || top < 70) {
        let rightPos = 24;
        if (currentSection.value.id === 'start') {
            rightPos = Math.max(16, windowSize.value.width - (left + width) - 10);
        }
        return {
            top: `${top + height + 16}px`,
            right: `${rightPos}px`,
            width: `${cardWidth}px`,
        };
    }

    // Target is create button (top-right) -> place below and align to right
    if (currentSection.value.id === 'create-category') {
        return {
            top: `${top + height + 16}px`,
            right: `${Math.max(16, windowSize.value.width - (left + width) - 10)}px`,
            width: `${cardWidth}px`,
        };
    }

    // Large element (table or KPI): place above if space permits or centered below
    if (currentSection.value.id === 'categories-table') {
        if (top > estimatedCardHeight + 20) {
            return {
                top: `${Math.max(70, top - estimatedCardHeight - 16)}px`,
                left: '50%',
                transform: 'translateX(-50%)',
                width: `${cardWidth}px`,
            };
        }
        return {
            top: `${top + 30}px`,
            left: '50%',
            transform: 'translateX(-50%)',
            width: `${cardWidth}px`,
        };
    }

    // General fallback: place below target
    if (top + height + estimatedCardHeight < windowSize.value.height) {
        return {
            top: `${top + height + 16}px`,
            left: `${Math.max(16, Math.min(windowSize.value.width - cardWidth - 16, left + width / 2 - cardWidth / 2))}px`,
            width: `${cardWidth}px`,
        };
    }

    // If bottom clearance is tight, place above target
    return {
        top: `${Math.max(70, top - estimatedCardHeight - 16)}px`,
        left: `${Math.max(16, Math.min(windowSize.value.width - cardWidth - 16, left + width / 2 - cardWidth / 2))}px`,
        width: `${cardWidth}px`,
    };
});

// Speech / Text Bubble Tail computation
const speechBubbleTail = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return null;
    }

    const t = targetRect.value;
    const c = cardRect.value || {
        left: 0,
        top: 0,
        width: 400,
        height: 420,
    };

    const targetCenterX = t.left + t.width / 2;
    const targetCenterY = t.top + t.height / 2;

    // CASE 1: Target is above card
    if (t.top + t.height <= c.top + 20) {
        const offset = Math.max(32, Math.min((c.width || 400) - 32, targetCenterX - c.left));
        return {
            placement: 'top' as const,
            offset,
        };
    }
    // CASE 2: Target is below card
    if (t.top >= c.top + (c.height || 420) - 20) {
        const offset = Math.max(32, Math.min((c.width || 400) - 32, targetCenterX - c.left));
        return {
            placement: 'bottom' as const,
            offset,
        };
    }
    // CASE 3: Card is on LEFT, Target is on RIGHT
    if (c.left + (c.width || 400) <= t.left + 30) {
        const offset = Math.max(28, Math.min((c.height || 420) - 28, targetCenterY - c.top));
        return {
            placement: 'right' as const,
            offset,
        };
    }
    // CASE 4: Card is on RIGHT, Target is on LEFT
    const offset = Math.max(28, Math.min((c.height || 420) - 28, targetCenterY - c.top));
    return {
        placement: 'left' as const,
        offset,
    };
});
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="pointer-events-none fixed inset-0 z-50 select-none font-sans">
                <!-- SVG Dimming Backdrop with Cutout Hole -->
                <svg class="pointer-events-none absolute inset-0 size-full transition-all duration-300" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- SVG Mask Hole -->
                        <mask id="categories-enjoyhint-mask">
                            <!-- White fills entire viewport (dimmed area) -->
                            <rect width="100%" height="100%" fill="white" />

                            <!-- Cutout Hole (Black = 100% transparent opening to reveal the button/element) -->
                            <rect
                                v-if="targetRect"
                                :x="targetRect.left - 8"
                                :y="targetRect.top - 8"
                                :width="targetRect.width + 16"
                                :height="targetRect.height + 16"
                                :rx="currentSection.rx"
                                fill="black"
                                class="transition-all duration-300 ease-out"
                            />
                        </mask>
                    </defs>

                    <!-- Darkened Canvas Backdrop with Cutout Mask -->
                    <rect
                        width="100%"
                        height="100%"
                        fill="#000000"
                        fill-opacity="0.75"
                        mask="url(#categories-enjoyhint-mask)"
                        class="backdrop-blur-xs pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole: clicking highlighted button triggers action or advances step -->
                <div
                    v-if="targetRect"
                    class="pointer-events-auto absolute z-40 cursor-pointer"
                    :style="{
                        top: `${targetRect.top - 4}px`,
                        left: `${targetRect.left - 4}px`,
                        width: `${targetRect.width + 8}px`,
                        height: `${targetRect.height + 8}px`,
                    }"
                    @click="handleTargetClick"
                    :title="currentSection.id === 'start' ? 'Click Page Guide to begin walkthrough' : 'Click highlighted button'"
                ></div>

                <!-- EnjoyHint Glowing Target Boundary Ring -->
                <div
                    v-if="targetRect"
                    class="pointer-events-none absolute border-2 border-primary shadow-[0_0_35px_rgba(16,185,129,0.5)] ring-4 ring-primary/30 transition-all duration-300 ease-out"
                    :style="{
                        top: `${targetRect.top - 8}px`,
                        left: `${targetRect.left - 8}px`,
                        width: `${targetRect.width + 16}px`,
                        height: `${targetRect.height + 16}px`,
                        borderRadius: `${currentSection.rx}px`,
                    }"
                ></div>

                <!-- Guided Callout Text Bubble (Speech bubble dialog pointing directly to target) -->
                <div ref="cardRef" class="pointer-events-auto absolute z-50 transition-all duration-300 ease-out" :style="cardStyle" @click.stop>
                    <!-- Speech Bubble Tail: Top (points UP to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'top'"
                        class="pointer-events-none absolute -top-3.5 z-30"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path d="M 0,15 L 12,0 L 24,15" class="fill-muted/95 stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="1" y="14" width="22" height="3" class="fill-muted/95" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Bottom (points DOWN to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'bottom'"
                        class="pointer-events-none absolute -bottom-3.5 z-30"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path d="M 0,0 L 12,15 L 24,0" class="fill-muted/80 stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="1" y="-2" width="22" height="3" class="fill-muted/80" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Left (points LEFT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'left'"
                        class="pointer-events-none absolute -left-3.5 z-30"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path d="M 15,0 L 0,12 L 15,24" class="fill-card stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="14" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Right (points RIGHT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'right'"
                        class="pointer-events-none absolute -right-3.5 z-30"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path d="M 0,0 L 15,12 L 0,24" class="fill-card stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="-2" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <div
                        class="flex max-h-[calc(100vh-90px)] flex-col overflow-hidden rounded-2xl border-2 border-border bg-card text-card-foreground shadow-2xl ring-1 ring-black/10 dark:ring-white/15"
                    >
                        <!-- Card Header with Visible Background -->
                        <div class="flex shrink-0 items-center justify-between border-b border-border bg-muted/80 px-3.5 py-2.5 sm:px-4 sm:py-3">
                            <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                <div
                                    class="size-7.5 shadow-2xs flex shrink-0 items-center justify-center rounded-lg border border-primary/25 bg-primary/15 text-xs font-bold text-primary"
                                >
                                    <component :is="currentSection.icon" class="size-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <Badge
                                            variant="outline"
                                            class="h-4 shrink-0 border-primary/40 bg-primary/10 px-1.5 py-0 font-mono text-[9px] font-bold uppercase text-primary"
                                        >
                                            {{
                                                currentSection.id === 'start'
                                                    ? 'ORIENTATION'
                                                    : `Part ${currentSection.stepNumber}/${sections.length - 1}`
                                            }}
                                        </Badge>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="mt-0.5 truncate text-xs font-bold leading-snug text-foreground sm:text-sm">
                                        {{ currentSection.title }}
                                    </h3>
                                </div>
                            </div>

                            <!-- Close / Exit Tour Button -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="close"
                                class="ml-1 h-6 shrink-0 gap-1 rounded-md px-2 text-[11px] font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                title="Exit Tour (Esc)"
                            >
                                <span>Exit</span>
                                <X class="size-3" />
                            </Button>
                        </div>

                        <!-- Card Body -->
                        <div class="max-h-[min(500px,calc(100vh-200px))] space-y-3 overflow-y-auto p-3.5 text-xs">
                            <!-- 1. What it is used for -->
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary">
                                    <Info class="size-3.5" />
                                    <span>Purpose & Function:</span>
                                </div>
                                <p class="text-xs font-normal leading-relaxed text-foreground/90">
                                    {{ currentSection.usedFor }}
                                </p>

                                <!-- Key Capabilities Badges -->
                                <div v-if="currentSection.capabilities && currentSection.capabilities.length > 0" class="flex flex-wrap gap-1.5 pt-1">
                                    <span
                                        v-for="(cap, i) in currentSection.capabilities"
                                        :key="i"
                                        class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-muted px-2 py-0.5 text-[10px] font-medium text-foreground"
                                    >
                                        <CheckCircle2 class="size-2.5 shrink-0 text-primary" />
                                        {{ cap }}
                                    </span>
                                </div>
                            </div>

                            <!-- 2. Step-by-Step Instructions -->
                            <div class="space-y-1.5 border-t border-border/60 pt-1">
                                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <Sparkles class="size-3.5 text-amber-500" />
                                    <span>How to Use:</span>
                                </div>
                                <ul class="space-y-1 text-xs text-muted-foreground">
                                    <li
                                        v-for="(instruction, i) in currentSection.howToUse"
                                        :key="i"
                                        class="flex items-start gap-1.5 text-[11px] leading-relaxed"
                                    >
                                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>
                                        <span>{{ instruction }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- 3. Store Manager Pro Tip -->
                            <div
                                v-if="currentSection.proTip"
                                class="space-y-1 rounded-lg border border-amber-500/25 bg-amber-500/10 p-2.5 text-amber-950 dark:text-amber-100 sm:p-3"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:text-amber-300"
                                >
                                    <Zap class="size-3.5 shrink-0 fill-amber-500/30 text-amber-600 dark:text-amber-400" />
                                    <span>Store Manager Tip</span>
                                </span>
                                <p class="text-[11px] font-medium leading-relaxed">
                                    {{ currentSection.proTip }}
                                </p>
                            </div>
                            <!-- 4. Interactive Action Shortcut -->
                            <div v-if="currentSection.actionLabel" class="pt-0.5">
                                <button
                                    type="button"
                                    @click="handleAction"
                                    class="shadow-2xs flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-primary/30 bg-primary/15 px-3 py-2 text-xs font-bold text-primary transition-colors hover:bg-primary/25 active:scale-[0.99]"
                                >
                                    <span>{{ currentSection.actionLabel }}</span>
                                    <ArrowRight class="size-3.5 shrink-0" />
                                </button>
                            </div>
                        </div>

                        <!-- EnjoyHint Navigation Footer -->
                        <div class="flex shrink-0 items-center justify-between border-t border-border bg-muted/70 px-3.5 py-2.5">
                            <!-- Previous Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-7 gap-1 px-2.5 text-xs font-semibold text-muted-foreground hover:text-foreground"
                                :disabled="internalStep === 0"
                                @click="prevStep"
                            >
                                <ArrowLeft class="size-3" />
                                <span>Prev</span>
                            </Button>

                            <!-- Dot indicators -->
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-for="(_, idx) in sections"
                                    :key="idx"
                                    type="button"
                                    @click="goToStep(idx)"
                                    class="size-2 cursor-pointer rounded-full transition-all"
                                    :class="idx === internalStep ? 'w-4 bg-primary' : 'bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                                    :title="idx === 0 ? 'Start: Page Guide' : `Part ${idx}`"
                                ></button>
                            </div>

                            <!-- Next / Done Button -->
                            <Button
                                type="button"
                                size="sm"
                                class="shadow-xs h-7 cursor-pointer gap-1.5 bg-primary px-3 text-xs font-bold text-primary-foreground hover:bg-primary/90"
                                @click="nextStep"
                            >
                                <span>{{
                                    currentSection.id === 'start' ? 'Start Tour' : internalStep === sections.length - 1 ? 'Done' : 'Next'
                                }}</span>
                                <ArrowRight v-if="internalStep < sections.length - 1" class="size-3" />
                                <Check v-else class="size-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
