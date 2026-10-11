<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, ArrowRight, Boxes, Check, Compass, Info, Keyboard, PackageCheck, Receipt, ScanLine, X, Zap } from 'lucide-vue-next';
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
    shortcuts?: string;
    actionLabel?: string;
    actionType?: string;
}

const sections: TourSection[] = [
    {
        id: 'start',
        targetId: 'purchases-tour-guide-btn',
        stepNumber: 0,
        title: 'Start Here: Click "Page Guide"',
        tag: 'INITIAL ORIENTATION & WALKTHROUGH',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        usedFor:
            'Welcome to Purchases & Procurement! Click the highlighted "Page Guide" button in the header right now to begin your interactive walkthrough of purchase orders, local LPOs, invoice scanning, and automated GRN receiving.',
        capabilities: [
            'Visual Screen Spotlight',
            'Contract PO vs Local LPO Guidance',
            'Physical Invoice & KRA QR Matching',
            'Automated GRN Stock Sync',
        ],
        howToUse: [
            'Click the glowing "Page Guide" button right now to begin the walkthrough.',
            'Follow the curved kinetic spotlight as it highlights each part of your procurement management center.',
            'Press Esc at any time to exit the guide and return to orders management.',
        ],
        proTip: 'The Page Guide button remains pinned in the top header at all times so any procurement manager, store owner, or inventory clerk can re-open walkthrough guidance on demand.',
        shortcuts: 'Click Page Guide (Begin) • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Click Page Guide to Begin Walkthrough',
        actionType: 'start-walkthrough',
    },
    {
        id: 'actions',
        targetId: 'purchases-tour-actions-header',
        stepNumber: 1,
        title: 'Procurement Actions: PO, LPO & Export',
        tag: 'ORDER CREATION & MULTI-FORMAT EXPORT',
        shape: 'rect',
        rx: 14,
        icon: Boxes,
        usedFor:
            'Primary creation hub for issuing contract Purchase Orders (PO) to primary distributors, generating rapid Local Purchase Orders (LPO) for spot purchases, and exporting complete order histories to PDF, Word, or CSV.',
        capabilities: [
            'Multi-Item Bulk PO Creator',
            'Local LPO Spot Purchasing',
            'Packaging Unit Multipliers',
            'Multi-Format Export: PDF, MS Word, CSV',
            'Pre-populated Supplier Catalog',
        ],
        howToUse: [
            'Click "New Bulk Purchase Order" to compose large multi-item distributor orders with packaging units (Cartons, Packs, Bags), projected inventory, and wholesale unit costs.',
            'Click "New Local PO (LPO)" for quick spot market purchases from local suppliers with specific purpose classification.',
            'Click "Download Orders" to generate branded PDF procurement orders, editable Word contracts, or CSV spreadsheets for accounting.',
        ],
        proTip: 'Local Purchase Orders (LPO) are optimized for spot restocking (e.g. fresh goods, emergency hardware, local supplies). They bypass slow approval cycles while maintaining full ledger traceability.',
        shortcuts: 'Click New Bulk PO • Click New LPO • Click Download',
        actionLabel: 'New Bulk Purchase Order',
        actionType: 'open-create-po',
    },
    {
        id: 'kpis',
        targetId: 'purchases-tour-kpis',
        stepNumber: 2,
        title: 'Procurement Capital & GRN Ledger',
        tag: 'REAL-TIME EXPENDITURE & DELIVERY METRICS',
        shape: 'rect',
        rx: 16,
        icon: Receipt,
        usedFor:
            'Real-time financial and fulfillment KPIs tracking total committed procurement capital, breakdown between distributor contracts and local LPOs, and verified Goods Received Notes (GRN).',
        capabilities: [
            'Total Procurement Value Ledger',
            'Contract PO vs Local LPO Value Split',
            'Pending Deliveries Counter',
            'Automated GRN Inventory Synchronization',
            'Real-Time Supplier Balances',
        ],
        howToUse: [
            'Track "Total Procurement Value" to monitor overall capital committed across all pending and fulfilled supplier orders.',
            'Compare Contract POs with Local LPOs to monitor the spending ratio between authorized distributors and local market vendors.',
            'Monitor "Stocked & Received (GRN)" to verify how many orders have arrived and been reconciled into active branch inventory.',
            'Watch the pending deliveries counter to follow up on overdue shipments before stock runs out on the sales floor.',
        ],
        proTip: 'Orders marked as "Received" have automatically updated inventory stock levels in real time. You never need to manually key in physical counts after an order is received.',
        shortcuts: 'Real-Time Overview • Automatic Totals',
        actionLabel: 'Filter Pending Deliveries',
        actionType: 'filter-pending',
    },
    {
        id: 'tabs',
        targetId: 'purchases-tour-tabs',
        stepNumber: 3,
        title: 'Document Switcher & Invoice Scanner',
        tag: 'SEGMENTED ARCHIVE & KRA QR RECONCILIATION',
        shape: 'rect',
        rx: 16,
        icon: ScanLine,
        usedFor:
            'Document type switcher to isolate All Orders, Physical Invoices, Contract POs, or Local LPOs, paired with the automated Camera/File Scanner for capturing physical supplier invoices and decoding KRA QR codes.',
        capabilities: [
            'Document Type Segmented Switcher',
            'Camera & Image Invoice Scanner',
            'KRA eTIMS QR Auto-Decoder',
            'Three-Way Matching (PO vs Invoice vs GRN)',
            'Instant Search & Status Filter',
        ],
        howToUse: [
            'Click "All Documents", "Invoices", "PO", or "LPO" tabs to immediately segment the order table by transaction category.',
            'Click "Scan / Upload Invoice" to snap a photo of a physical paper receipt — the system auto-extracts KRA PIN, CU Code, Invoice Date, and Total Amount.',
            'Type in the search bar to locate any purchase by PO reference, vendor name, delivery branch, or item name.',
            'Use the status filter dropdown to isolate orders by Pending, Approved, Received, or Cancelled.',
        ],
        proTip: 'The built-in KRA QR scanner reads cryptographic eTIMS receipts in under 1 second and immediately runs a line-item three-way match against the original PO pricing to detect unauthorized price increases.',
        shortcuts: 'Click Document Tabs • Type in Search Bar • Filter Status',
        actionLabel: 'Filter Invoices Tab',
        actionType: 'switch-tab-invoices',
    },
    {
        id: 'table',
        targetId: 'purchases-tour-table',
        stepNumber: 4,
        title: 'Procurement Order Ledger & Action Center',
        tag: 'LINE-BY-LINE AUDIT & ACTIONS',
        shape: 'rect',
        rx: 16,
        icon: PackageCheck,
        usedFor:
            'Interactive order ledger displaying PO references, vendor information, receiving branches, line-item descriptions, KRA invoice badges, delivery statuses, and immediate operational action buttons.',
        capabilities: [
            'One-Click Stock Receiving (GRN)',
            'KRA Invoice Matching & Variance Audit',
            'Structured Item Breakdown Viewer',
            'Instant PDF & MS Word Generator',
            'Delete / Cancel Order Safeguard',
        ],
        howToUse: [
            'When a delivery truck arrives, click "Receive & Sync Stock" on any pending PO — select delivered items to automatically increment warehouse inventory.',
            'Click "Scan Invoice" or "Match Invoice" on any row to link the physical supplier invoice and inspect item price variances.',
            'Click the Eye icon on any row to view complete order specifics: packaging units, packs quantity, units per pack, unit costs, and the ordering staff member.',
            'Click the printer icon or download buttons on any row to print an official physical receiving sheet or export an individual PO document.',
        ],
        proTip: 'If a supplier delivers a partial order, you can check off only the items that actually arrived. The system receives those items into stock and keeps the order status updated with an audit note.',
        shortcuts: 'Click Row Actions • Receive Stock • Match Invoice',
        actionLabel: 'Open Invoice Scanner',
        actionType: 'open-scanner',
    },
];

const internalStep = ref(props.currentStep || 0);

watch(
    () => props.currentStep,
    (val) => {
        if (val !== undefined && val !== internalStep.value) {
            internalStep.value = val;
            updateTargetPosition();
        }
    },
);

const currentSection = computed(() => sections[internalStep.value] || sections[0]);

// Bounding box of the spotlighted element
const targetRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const cardRef = ref<HTMLElement | null>(null);
const cardRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const windowSize = ref({ width: 0, height: 0 });

const updateTargetPosition = () => {
    if (!props.open) return;
    if (typeof window === 'undefined') return;

    windowSize.value = { width: window.innerWidth, height: window.innerHeight };

    const sec = currentSection.value;

    nextTick(() => {
        const el = document.getElementById(sec.targetId);
        if (el) {
            const rect = el.getBoundingClientRect();
            targetRect.value = {
                top: Math.max(0, rect.top),
                left: Math.max(0, rect.left),
                width: rect.width,
                height: rect.height,
            };
            // Scroll target into view smoothly if needed
            if (rect.top < 0 || rect.bottom > window.innerHeight) {
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } else {
            targetRect.value = null;
        }

        // Measure card rect for the speech bubble tail
        nextTick(() => {
            if (cardRef.value) {
                const c = cardRef.value.getBoundingClientRect();
                cardRect.value = {
                    top: c.top,
                    left: c.left,
                    width: c.width,
                    height: c.height,
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

// Keyboard navigation
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

// Calculate placement for the callout card ensuring zero overlap with highlighted button/target
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
    const estimatedCardHeight = 440;

    // On mobile screens: smartly position above or below target button
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

    // Top Header / Guide button section -> place below header target
    if (currentSection.value.id === 'start' || currentSection.value.id === 'actions') {
        const rightPos = Math.max(24, windowSize.value.width - (left + width));
        return {
            top: `${top + height + 16}px`,
            right: `${rightPos}px`,
            width: `${cardWidth}px`,
        };
    }

    // Table section -> target is large, anchor card at top-right of table
    if (currentSection.value.id === 'table') {
        return {
            top: `${Math.max(80, top + 20)}px`,
            right: '32px',
            width: `${cardWidth}px`,
        };
    }

    // Tabs section -> place comfortably below or to right of tabs
    if (currentSection.value.id === 'tabs') {
        if (top + height + estimatedCardHeight < windowSize.value.height - 30) {
            return {
                top: `${top + height + 16}px`,
                left: `${Math.max(24, left)}px`,
                width: `${cardWidth}px`,
            };
        }
        return {
            bottom: `${Math.max(20, windowSize.value.height - top + 16)}px`,
            left: `${Math.max(24, left)}px`,
            width: `${cardWidth}px`,
        };
    }

    // KPIs section -> place comfortably below KPI grid
    if (currentSection.value.id === 'kpis') {
        if (top + height + estimatedCardHeight < windowSize.value.height - 30) {
            return {
                top: `${top + height + 16}px`,
                left: '50%',
                transform: 'translateX(-50%)',
                width: `${cardWidth}px`,
            };
        }
        return {
            bottom: `${Math.max(20, windowSize.value.height - top + 16)}px`,
            left: '50%',
            transform: 'translateX(-50%)',
            width: `${cardWidth}px`,
        };
    }

    // Fallback: below target
    return {
        top: `${top + height + 16}px`,
        left: '50%',
        transform: 'translateX(-50%)',
        width: `${cardWidth}px`,
    };
});

// Speech / Text Bubble Tail: computes whether target is above, below, left, or right
const speechBubbleTail = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return null;
    }

    const t = targetRect.value;
    const c = cardRect.value || {
        left: 0,
        top: 0,
        width: 400,
        height: 440,
    };

    const targetCenterX = t.left + t.width / 2;
    const targetCenterY = t.top + t.height / 2;

    // Target is above card
    if (t.top + t.height <= c.top + 20) {
        const offset = Math.max(32, Math.min((c.width || 400) - 32, targetCenterX - c.left));
        return {
            placement: 'top' as const,
            offset,
        };
    }
    // Target is below card
    if (t.top >= c.top + (c.height || 440) - 20) {
        const offset = Math.max(32, Math.min((c.width || 400) - 32, targetCenterX - c.left));
        return {
            placement: 'bottom' as const,
            offset,
        };
    }
    // Target is to right of card
    if (c.left + (c.width || 400) <= t.left + 30) {
        const offset = Math.max(28, Math.min((c.height || 440) - 28, targetCenterY - c.top));
        return {
            placement: 'right' as const,
            offset,
        };
    }
    // Target is to left of card
    const offset = Math.max(28, Math.min((c.height || 440) - 28, targetCenterY - c.top));
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
                <!-- SVG Dimming Backdrop with EnjoyHint Cutout Hole -->
                <svg class="pointer-events-none absolute inset-0 h-full w-full transition-all duration-300" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- EnjoyHint SVG Mask Hole -->
                        <mask id="purchases-guide-mask">
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
                        mask="url(#purchases-guide-mask)"
                        class="pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole -->
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

                <!-- Glowing Target Boundary Ring -->
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

                <!-- Guided Callout Text Bubble -->
                <div ref="cardRef" class="pointer-events-auto absolute z-50 transition-all duration-300 ease-out" :style="cardStyle" @click.stop>
                    <!-- Speech Bubble Tail: Top -->
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

                    <!-- Speech Bubble Tail: Bottom -->
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

                    <!-- Speech Bubble Tail: Left -->
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

                    <!-- Speech Bubble Tail: Right -->
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
                        <!-- Card Header -->
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
                                            {{ currentSection.id === 'start' ? 'ORIENTATION' : `Part ${currentSection.stepNumber}/4` }}
                                        </Badge>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="mt-0.5 text-xs font-bold leading-snug text-foreground sm:text-sm">
                                        {{ currentSection.title }}
                                    </h3>
                                </div>
                            </div>

                            <!-- Close Button -->
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
                                        v-for="cap in currentSection.capabilities"
                                        :key="cap"
                                        class="shadow-2xs inline-flex items-center gap-1.5 rounded-md border border-border/80 bg-muted/80 px-2 py-0.5 text-[10px] font-medium text-foreground"
                                    >
                                        <span class="size-1.5 shrink-0 rounded-full bg-primary/80"></span>
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- 2. Step-by-step guidance on how to use it -->
                            <div class="space-y-2 rounded-lg border border-border/80 bg-muted/30 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-foreground">
                                        <span>How to use in procurement:</span>
                                    </span>
                                    <span
                                        class="rounded border border-border/50 bg-muted/60 px-1.5 py-0.5 font-mono text-[10px] font-bold text-muted-foreground"
                                    >
                                        {{ currentSection.howToUse.length }} steps
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, idx) in currentSection.howToUse"
                                        :key="idx"
                                        class="flex items-start gap-2.5 text-xs text-foreground"
                                    >
                                        <div
                                            class="size-4.5 shadow-2xs mt-0.5 flex shrink-0 items-center justify-center rounded-full bg-primary font-mono text-[10px] font-bold text-primary-foreground"
                                        >
                                            {{ idx + 1 }}
                                        </div>
                                        <span class="flex-1 break-words text-xs font-normal leading-relaxed text-foreground/95">
                                            {{ step }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Shortcuts Strip -->
                            <div
                                v-if="currentSection.shortcuts"
                                class="flex flex-wrap items-center justify-between gap-1.5 rounded-lg border border-border/80 bg-muted/40 px-3 py-2 text-xs"
                            >
                                <span class="flex shrink-0 items-center gap-1.5 text-[11px] font-bold text-muted-foreground">
                                    <Keyboard class="size-3.5 text-primary" />
                                    <span>Actions:</span>
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px]">
                                    <template v-for="(item, sIdx) in currentSection.shortcuts.split(' • ')" :key="sIdx">
                                        <kbd class="shadow-2xs rounded border border-border bg-background px-1.5 py-0.5 font-bold text-foreground">
                                            {{ item }}
                                        </kbd>
                                    </template>
                                </div>
                            </div>

                            <!-- 3. Store Manager Tip -->
                            <div
                                class="space-y-1 rounded-lg border border-amber-500/25 bg-amber-500/10 p-2.5 text-amber-950 dark:text-amber-100 sm:p-3"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:text-amber-300"
                                >
                                    <Zap class="size-3.5 shrink-0 fill-amber-500/30 text-amber-600 dark:text-amber-400" />
                                    <span>Procurement Best Practice</span>
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

                        <!-- Navigation Footer -->
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
