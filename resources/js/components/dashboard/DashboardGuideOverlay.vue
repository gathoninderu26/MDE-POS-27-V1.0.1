<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    CircleDollarSign,
    Compass,
    CreditCard,
    DollarSign,
    Info,
    Keyboard,
    Receipt,
    ShieldCheck,
    ShoppingCart,
    TrendingUp,
    Users,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    open: boolean;
    currentStep?: number;
    isCashier?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    currentStep: 0,
    isCashier: false,
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

// Owner / Management Dashboard Walkthrough Steps
const ownerSections: TourSection[] = [
    {
        id: 'start',
        targetId: 'dashboard-tour-guide-btn',
        stepNumber: 0,
        title: 'Dashboard Guide',
        tag: 'INTERACTIVE TOUR',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        usedFor: 'Start a step-by-step walkthrough of your business dashboard — covering analytics, staff shifts, financials, and POS.',
        capabilities: [
            'Multi-Branch Analytics',
            'Staff & POS Overview',
        ],
        howToUse: [
            'Click "Start Tour" below to begin.',
            'Use ← → arrow keys or Esc to navigate and exit.',
        ],
        proTip: 'Re-open this guide anytime from the header button.',
        actionLabel: 'Start Tour',
        actionType: 'start-walkthrough',
    },
    {
        id: 'header',
        targetId: 'dashboard-step-header',
        stepNumber: 1,
        title: 'Store Context & Period Filters',
        tag: 'BRANCH & TIMEFRAME',
        shape: 'rect',
        rx: 16,
        icon: Building2,
        usedFor: 'Shows your branch name, code badge, and time-range filters. All dashboard numbers update instantly when you switch periods.',
        capabilities: [
            'Today / 7D / 30D filters',
            'Branch scope indicator',
        ],
        howToUse: [
            'Click a time pill (Today, 7 Days…) to recalculate all metrics.',
            'Check the branch badge to confirm the active location.',
        ],
        proTip: '"7 Days" auto-compares vs the prior week so you spot growth immediately.',
    },
    {
        id: 'shifts',
        targetId: 'dashboard-step-shifts',
        stepNumber: 2,
        title: 'Staff Attendance & Shift Tracker',
        tag: 'LIVE TEAM STATUS',
        shape: 'rect',
        rx: 16,
        icon: Users,
        usedFor: 'Live view of clocked-in cashiers, breaks, absences, and each cashier\'s sales progress against their shift target.',
        capabilities: [
            'Active / Break / Absent status',
            'Per-cashier sales vs target',
        ],
        howToUse: [
            'Click the strip to expand the full attendance roster.',
            'Search or filter by branch to find a specific cashier.',
        ],
        proTip: 'Late arrivals are auto-flagged in minutes so supervisors can act immediately.',
    },
    {
        id: 'kpis',
        targetId: 'dashboard-step-kpis',
        stepNumber: 3,
        title: 'Financial KPIs',
        tag: 'REVENUE & PROFIT',
        shape: 'rect',
        rx: 16,
        icon: TrendingUp,
        usedFor: 'Gross Revenue, Gross Profit (with live margin ~29.4%), total orders, and operating expenses — all scoped to your selected time period.',
        capabilities: [
            'Gross Revenue & Profit margins',
            'Orders & Expense tracking',
        ],
        howToUse: [
            'Compare Revenue vs Expenses to gauge daily profitability.',
            'Hover a card for prior-period percentage change.',
        ],
        proTip: 'Margins are calculated per transaction using actual batch purchase cost.',
    },
    {
        id: 'balances',
        targetId: 'dashboard-step-balances',
        stepNumber: 4,
        title: 'Working Capital & Balances',
        tag: 'RECEIVABLES & ASSETS',
        shape: 'rect',
        rx: 16,
        icon: CircleDollarSign,
        usedFor: 'Four key balance indicators: Customer Receivables, Supplier Payables, Branch Stock Value, and Active Customer accounts.',
        capabilities: [
            'Customer & Supplier balances',
            'Stock value at cost',
        ],
        howToUse: [
            'Monitor receivables to follow up on outstanding credit customers.',
            'Track payables to avoid missing supplier payment deadlines.',
        ],
        proTip: 'Customer Receivables update automatically when credit payments are made at POS.',
    },
    {
        id: 'chart',
        targetId: 'dashboard-step-chart',
        stepNumber: 5,
        title: 'Hourly Sales Chart',
        tag: 'SALES VELOCITY',
        shape: 'rect',
        rx: 16,
        icon: TrendingUp,
        usedFor: 'Bar chart showing combined cashier revenue by hour. Pinpoints peak trading windows and average basket trends.',
        capabilities: [
            'Hourly revenue bars',
            'Peak hour detection',
        ],
        howToUse: [
            'Hover a bar to see exact revenue for that hour.',
            'Identify peak hours to schedule breaks and extra register coverage.',
        ],
        proTip: 'Schedule cashier breaks outside the highest-revenue hours to maximise throughput.',
    },
    {
        id: 'payments',
        targetId: 'dashboard-step-payments',
        stepNumber: 6,
        title: 'Payment Channels',
        tag: 'M-PESA · CASH · CARD',
        shape: 'rect',
        rx: 16,
        icon: CreditCard,
        usedFor: 'Tender split showing M-Pesa, Cash, Card, and Credit percentages with progress bars for quick reconciliation.',
        capabilities: [
            'M-Pesa / Cash / Card split',
            'Channel % progress bars',
        ],
        howToUse: [
            'Compare "Cash in Drawer" against physical till count at shift-end.',
            'Verify card totals against bank merchant settlement slips.',
        ],
        proTip: 'M-Pesa is auto-reconciled via Daraja STK — no manual SMS verification needed.',
    },
    {
        id: 'branches',
        targetId: 'dashboard-step-branches',
        stepNumber: 7,
        title: 'Branch Performance',
        tag: 'MULTI-OUTLET TRACKER',
        shape: 'rect',
        rx: 16,
        icon: Building2,
        usedFor: 'Per-branch sales vs daily target comparison with health status badges and active staff counts.',
        capabilities: [
            'Revenue vs target per branch',
            'On Track / Warning status',
        ],
        howToUse: [
            'Check branch bars early in the day to spot underperforming locations.',
            'Click "Manage Branches" to adjust targets or transfer inventory.',
        ],
        proTip: 'Branches consistently above 100% can be prioritised for faster stock replenishment.',
    },
    {
        id: 'top-products',
        targetId: 'dashboard-step-top-products',
        stepNumber: 8,
        title: 'Top Selling Products',
        tag: 'FASTEST MOVERS',
        shape: 'rect',
        rx: 16,
        icon: ShoppingCart,
        usedFor: 'Ranked list of best-selling items by units and revenue, showing margin contribution per product.',
        capabilities: [
            'Units sold & revenue',
            'Margin % per item',
        ],
        howToUse: [
            'Focus on high-margin items rather than pure volume leaders.',
            'Click "View Catalog" to adjust pricing or create bundles.',
        ],
        proTip: 'Pair thin-margin bestsellers with high-margin accessories to lift average ticket value.',
    },
    {
        id: 'low-stock',
        targetId: 'dashboard-step-low-stock',
        stepNumber: 9,
        title: 'Low Stock Alerts',
        tag: 'REORDER WARNINGS',
        shape: 'rect',
        rx: 16,
        icon: Zap,
        usedFor: 'Items near or below their minimum stock threshold, flagged with Critical (red) or Warning (amber) badges.',
        capabilities: [
            'Critical & warning badges',
            'Minimum safety stock thresholds',
        ],
        howToUse: [
            'Act on Critical alerts (≤ 5 units) immediately.',
            'Click "Stock Mgmt" to raise a purchase order.',
        ],
        proTip: 'Set safety stock levels with supplier lead-time in mind so shelves never go empty.',
    },
    {
        id: 'recent-sales',
        targetId: 'dashboard-step-recent-sales',
        stepNumber: 10,
        title: 'Recent Transactions',
        tag: 'LIVE AUDIT FEED',
        shape: 'rect',
        rx: 16,
        icon: Receipt,
        usedFor: 'Real-time stream of all cashier receipts with receipt numbers, cashier IDs, payment method, and completion status.',
        capabilities: [
            'Live receipt feed',
            'Cashier & payment tags',
        ],
        howToUse: [
            'Click a receipt number to see itemised lines and tax.',
            'Use "Sales Log" to reprint or process supervisor returns.',
        ],
        proTip: 'Voided or refunded tickets show immediate status flags for instant fraud detection.',
    },
    {
        id: 'pos-action',
        targetId: 'dashboard-step-pos-action',
        stepNumber: 11,
        title: 'Launch POS Register',
        tag: 'SALES TERMINAL',
        shape: 'rect',
        rx: 10,
        icon: Zap,
        usedFor: 'One-click launch into the full-screen cashier register with barcode scanner, M-Pesa STK, and KRA thermal receipts.',
        capabilities: [
            'Instant POS launch',
            'Barcode & M-Pesa ready',
        ],
        howToUse: [
            'Click "Open POS Register" to enter selling mode.',
        ],
        proTip: 'Restrict cashiers to POS-only mode so profit data stays confidential.',
        actionLabel: 'Launch POS Register',
        actionType: 'open-pos',
    },
];

// Cashier-Specific Dashboard Walkthrough Steps
const cashierSections: TourSection[] = [
    {
        id: 'start',
        targetId: 'cashier-tour-guide-btn',
        stepNumber: 0,
        title: 'Cashier Dashboard Guide',
        tag: 'REGISTER TOUR',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        usedFor: 'Quick tour of your register dashboard — shift stats, cash drawer, attendance, and your personal sales.',
        capabilities: [
            'Shift & drawer tracking',
            'Attendance calendar',
        ],
        howToUse: [
            'Click "Start Tour" to begin.',
            'Use ← → or Esc to navigate and exit.',
        ],
        proTip: 'Check this dashboard before and after every shift handover.',
        actionLabel: 'Start Tour',
        actionType: 'start-walkthrough',
    },
    {
        id: 'cashier-header',
        targetId: 'cashier-step-header',
        stepNumber: 1,
        title: 'Active Shift & Terminal',
        tag: 'SESSION STATUS',
        shape: 'rect',
        rx: 16,
        icon: ShoppingCart,
        usedFor: 'Shows your name, assigned terminal, branch, shift start time, and live elapsed duration.',
        capabilities: [
            'Live shift timer',
            'Terminal ID & branch',
        ],
        howToUse: [
            'Check elapsed time to plan meal and relief breaks.',
            'Use "Open POS Register" to start ringing up customers.',
        ],
        proTip: 'Lock your terminal with a supervisor code before stepping away from your till.',
    },
    {
        id: 'cashier-calendar',
        targetId: 'cashier-step-calendar',
        stepNumber: 2,
        title: 'Attendance Calendar',
        tag: 'MONTHLY ROSTER',
        shape: 'rect',
        rx: 16,
        icon: Calendar,
        usedFor: 'Your monthly shift calendar showing worked, absent, and upcoming shifts with clock-in times.',
        capabilities: [
            'Shifts worked this month',
            'Clock-in history',
        ],
        howToUse: [
            'Click the strip to expand your calendar.',
            'Tap a past day to review its clock-in and sales.',
        ],
        proTip: 'Green dots = completed shifts. Red dots = absent days.',
    },
    {
        id: 'cashier-target',
        targetId: 'cashier-step-target',
        stepNumber: 3,
        title: 'Shift Target Progress',
        tag: 'DAILY GOAL',
        shape: 'rect',
        rx: 16,
        icon: TrendingUp,
        usedFor: 'Progress bar showing your shift sales vs your daily target set by the store manager.',
        capabilities: [
            'Completion % bar',
            'Remaining amount to goal',
        ],
        howToUse: [
            'Monitor your percentage throughout the shift.',
            'Upsell items to hit 100% before shift end.',
        ],
        proTip: 'Consistently hitting 100%+ qualifies you for monthly performance bonuses.',
    },
    {
        id: 'cashier-kpis',
        targetId: 'cashier-step-kpis',
        stepNumber: 4,
        title: 'My Shift KPIs',
        tag: 'REGISTER STATS',
        shape: 'rect',
        rx: 16,
        icon: TrendingUp,
        usedFor: 'Your personal shift metrics: total sales, orders processed, average basket size, and refunds issued.',
        capabilities: [
            'Shift sales & order count',
            'Basket size & refunds',
        ],
        howToUse: [
            'Track sales to stay on target.',
            'Upsell to raise your average basket value.',
        ],
        proTip: 'All refunds are tagged to your cashier ID for inventory audit.',
    },
    {
        id: 'cashier-payments',
        targetId: 'cashier-step-payments',
        stepNumber: 5,
        title: 'Cash & M-Pesa Collected',
        tag: 'DRAWER BALANCE',
        shape: 'rect',
        rx: 16,
        icon: DollarSign,
        usedFor: 'Live tender breakdown: Cash in Drawer, M-Pesa received, Card payments, and Credit sales.',
        capabilities: [
            'Cash in drawer amount',
            'M-Pesa & Card totals',
        ],
        howToUse: [
            'At shift end, physically count drawer cash and compare to this figure.',
            'Report any discrepancy to your supervisor before clocking out.',
        ],
        proTip: 'Do a cash drop when drawer exceeds KES 20,000.',
    },
    {
        id: 'cashier-chart',
        targetId: 'cashier-step-chart',
        stepNumber: 6,
        title: 'Hourly Register Activity',
        tag: 'PEAK HOURS',
        shape: 'rect',
        rx: 16,
        icon: TrendingUp,
        usedFor: 'Hourly bar chart of sales on your register, highlighting your busiest checkout window.',
        capabilities: [
            'Hourly revenue bars',
            'Peak hour badge',
        ],
        howToUse: [
            'Hover a bar to see the exact revenue for that hour.',
        ],
        proTip: 'Stock up on receipt paper and float before your peak hour starts.',
    },
    {
        id: 'cashier-top-items',
        targetId: 'cashier-step-top-items',
        stepNumber: 7,
        title: 'My Top-Selling Items',
        tag: 'BESTSELLERS TODAY',
        shape: 'rect',
        rx: 16,
        icon: ShoppingCart,
        usedFor: 'Products you have sold most today by quantity and total value on this terminal.',
        capabilities: [
            'Units sold per item',
            'Revenue per bestseller',
        ],
        howToUse: [
            'Learn the PLU codes of your top items for faster scanning.',
        ],
        proTip: 'Fast scanning on top items keeps queue wait times under 60 seconds.',
    },
    {
        id: 'cashier-recent-receipts',
        targetId: 'cashier-step-recent-receipts',
        stepNumber: 8,
        title: 'My Recent Receipts',
        tag: 'TERMINAL AUDIT LOG',
        shape: 'rect',
        rx: 16,
        icon: Receipt,
        usedFor: 'Log of receipts issued on your terminal today with receipt numbers, customers, amounts, and status.',
        capabilities: [
            'Receipt number & customer',
            'Payment method & status',
        ],
        howToUse: [
            'Look up a receipt here if a customer requests a duplicate.',
            'Click "Full Sales Log" for older receipts.',
        ],
        proTip: 'Receipt numbers match the barcode on printed dockets for instant scan lookup.',
    },
    {
        id: 'cashier-shift-summary',
        targetId: 'cashier-step-shift-summary',
        stepNumber: 9,
        title: 'Shift Summary & Reconciliation',
        tag: 'TILL BALANCE',
        shape: 'rect',
        rx: 16,
        icon: ShieldCheck,
        usedFor: 'End-of-shift balance sheet: orders, cash drawer, M-Pesa total, refunds deducted, and net shift revenue.',
        capabilities: [
            'Net revenue after refunds',
            'Cash & M-Pesa reconciliation',
        ],
        howToUse: [
            'Count physical cash and compare to "Cash in Drawer".',
            'Confirm M-Pesa total vs Daraja/till phone confirmations.',
        ],
        proTip: 'Reconcile to zero before handing over the register keys.',
    },
    {
        id: 'cashier-pos-action',
        targetId: 'cashier-step-pos-action',
        stepNumber: 10,
        title: 'Open POS Register',
        tag: 'START SELLING',
        shape: 'rect',
        rx: 10,
        icon: Zap,
        usedFor: 'One-click into the full cashier register — barcode scanner, M-Pesa STK, and thermal receipt printing ready.',
        capabilities: [
            'Instant POS launch',
            'Barcode & M-Pesa ready',
        ],
        howToUse: [
            'Click "Open POS Register" to start serving customers.',
        ],
        proTip: 'F2 = scan barcode · Space = open checkout in POS mode.',
        actionLabel: 'Open POS Register',
        actionType: 'open-pos',
    },
];

const sections = computed(() => (props.isCashier ? cashierSections : ownerSections));

const internalStep = ref(props.currentStep);
const targetRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const cardRef = ref<HTMLElement | null>(null);
const cardRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const windowSize = ref({ width: 0, height: 0 });

watch(
    () => props.currentStep,
    (val) => {
        internalStep.value = val;
        updateTargetPosition();
    }
);

const currentSection = computed(() => {
    return sections.value[internalStep.value] || sections.value[0];
});

const updateTargetPosition = () => {
    if (typeof window === 'undefined') return;

    windowSize.value = {
        width: window.innerWidth,
        height: window.innerHeight,
    };

    const sec = currentSection.value;
    if (!sec) return;

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
            // Scroll target smoothly into view if needed
            if (rect.top < 60 || rect.bottom > window.innerHeight - 60) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            targetRect.value = null;
        }

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
    if (internalStep.value < sections.value.length - 1) {
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
    { immediate: true }
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

// Calculate intelligent placement for the callout card ensuring zero overlap with highlighted button/target
const cardStyle = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return {
            bottom: '16px',
            left: '50%',
            transform: 'translateX(-50%)',
            maxWidth: '340px',
            width: 'calc(100% - 24px)',
        };
    }

    const { top, left, width, height } = targetRect.value;
    const isMobile = windowSize.value.width < 768;
    const cardWidth = isMobile ? Math.min(320, windowSize.value.width - 24) : 340;
    const estimatedCardHeight = 300;
    const targetCenterY = top + height / 2;

    // On mobile screens: smartly position above or below the target button
    if (isMobile) {
        if (targetCenterY > windowSize.value.height / 2) {
            return {
                top: '12px',
                left: '12px',
                right: '12px',
                width: 'auto',
                maxWidth: '320px',
                margin: '0 auto',
            };
        }
        return {
            bottom: '12px',
            left: '12px',
            right: '12px',
            width: 'auto',
            maxWidth: '320px',
            margin: '0 auto',
        };
    }

    // Step 0: Initial Guide button -> place callout comfortably below button
    if (currentSection.value.id === 'start') {
        const rightPos = Math.max(16, windowSize.value.width - (left + width) - 10);
        return {
            top: `${top + height + 14}px`,
            right: `${rightPos}px`,
            width: `${cardWidth}px`,
        };
    }

    // Top header section (close to top edge)
    if (top < 110) {
        return {
            top: `${top + height + 14}px`,
            right: '20px',
            width: `${cardWidth}px`,
        };
    }

    // Side placement: check if card can sit comfortably to the left of the target
    if (left > cardWidth + 30) {
        const safeTop = Math.max(64, Math.min(windowSize.value.height - estimatedCardHeight - 20, top - 10));
        return {
            top: `${safeTop}px`,
            left: `${left - cardWidth - 20}px`,
            width: `${cardWidth}px`,
        };
    }

    // Side placement: check if card can sit comfortably to the right of the target
    if (left + width + cardWidth + 30 < windowSize.value.width) {
        const safeTop = Math.max(64, Math.min(windowSize.value.height - estimatedCardHeight - 20, top - 10));
        return {
            top: `${safeTop}px`,
            left: `${left + width + 20}px`,
            width: `${cardWidth}px`,
        };
    }

    // Wide elements (full-width grids, shifts table) -> place above or below target based on clearance
    const spaceAbove = top;
    const spaceBelow = windowSize.value.height - (top + height);

    if (spaceAbove > spaceBelow && spaceAbove >= 220) {
        const bottomOffset = Math.max(16, windowSize.value.height - top + 16);
        const optimalLeft = Math.max(20, Math.min(windowSize.value.width - cardWidth - 20, left + 40));
        return {
            bottom: `${bottomOffset}px`,
            left: `${optimalLeft}px`,
            width: `${cardWidth}px`,
        };
    }

    // Default: place below target
    const topOffset = Math.max(64, top + height + 16);
    const optimalLeft = Math.max(20, Math.min(windowSize.value.width - cardWidth - 20, left + 40));
    return {
        top: `${topOffset}px`,
        left: `${optimalLeft}px`,
        width: `${cardWidth}px`,
    };
});

// Speech / Text Bubble Tail: computes whether the target is above, below, left, or right
// of the card, and dynamically computes the pointer offset along the bubble's edge.
const speechBubbleTail = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return null;
    }

    const t = targetRect.value;
    const c = cardRect.value || {
        left: 0,
        top: 0,
        width: 380,
        height: 400,
    };

    const targetCenterX = t.left + t.width / 2;
    const targetCenterY = t.top + t.height / 2;

    // CASE 1: Target is above the card (e.g. Guide button / Header)
    if (t.top + t.height <= c.top + 20) {
        const offset = Math.max(32, Math.min((c.width || 380) - 32, targetCenterX - c.left));
        return {
            placement: 'top' as const,
            offset,
        };
    }
    // CASE 2: Target is below the card
    if (t.top >= c.top + (c.height || 400) - 20) {
        const offset = Math.max(32, Math.min((c.width || 380) - 32, targetCenterX - c.left));
        return {
            placement: 'bottom' as const,
            offset,
        };
    }
    // CASE 3: Card is on the LEFT, Target is on the RIGHT
    if (c.left + (c.width || 380) <= t.left + 30) {
        const offset = Math.max(28, Math.min((c.height || 400) - 28, targetCenterY - c.top));
        return {
            placement: 'right' as const,
            offset,
        };
    }
    // CASE 4: Card is on the RIGHT, Target is on the LEFT
    const offset = Math.max(28, Math.min((c.height || 400) - 28, targetCenterY - c.top));
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
            <div
                v-if="open"
                class="fixed inset-0 z-50 pointer-events-none select-none font-sans"
            >
                <!-- SVG Dimming Backdrop with Cutout Hole -->
                <svg
                    class="absolute inset-0 w-full h-full pointer-events-none transition-all duration-300"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <!-- Cutout Mask Hole -->
                        <mask id="dashboard-guide-mask">
                            <rect width="100%" height="100%" fill="white" />
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
                        mask="url(#dashboard-guide-mask)"
                        class="pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole: clicking highlighted button advances tour -->
                <div
                    v-if="targetRect"
                    class="absolute pointer-events-auto cursor-pointer z-40"
                    :style="{
                        top: `${targetRect.top - 4}px`,
                        left: `${targetRect.left - 4}px`,
                        width: `${targetRect.width + 8}px`,
                        height: `${targetRect.height + 8}px`,
                    }"
                    @click="handleTargetClick"
                    :title="currentSection.id === 'start' ? 'Click Guide to begin walkthrough' : 'Click highlighted area'"
                ></div>

                <!-- Glowing Target Boundary Ring -->
                <div
                    v-if="targetRect"
                    class="absolute pointer-events-none transition-all duration-300 ease-out border-2 border-primary shadow-[0_0_35px_rgba(16,185,129,0.5)] ring-4 ring-primary/30"
                    :style="{
                        top: `${targetRect.top - 8}px`,
                        left: `${targetRect.left - 8}px`,
                        width: `${targetRect.width + 16}px`,
                        height: `${targetRect.height + 16}px`,
                        borderRadius: `${currentSection.rx}px`,
                    }"
                ></div>

                <!-- Guided Callout Text Bubble (Speech bubble dialog pointing directly to target) -->
                <div
                    ref="cardRef"
                    class="absolute z-50 pointer-events-auto transition-all duration-300 ease-out"
                    :style="cardStyle"
                    @click.stop
                >
                    <!-- Speech Bubble Tail: Top (points UP to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'top'"
                        class="absolute -top-3.5 z-30 pointer-events-none"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,15 L 12,0 L 24,15"
                                class="fill-muted/95 stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="1" y="14" width="22" height="3" class="fill-muted/95" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Bottom (points DOWN to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'bottom'"
                        class="absolute -bottom-3.5 z-30 pointer-events-none"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,0 L 12,15 L 24,0"
                                class="fill-muted/80 stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="1" y="-2" width="22" height="3" class="fill-muted/80" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Left (points LEFT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'left'"
                        class="absolute -left-3.5 z-30 pointer-events-none"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 15,0 L 0,12 L 15,24"
                                class="fill-card stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="14" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Right (points RIGHT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'right'"
                        class="absolute -right-3.5 z-30 pointer-events-none"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,0 L 15,12 L 0,24"
                                class="fill-card stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="-2" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Text Bubble Container Card -->
                    <div class="rounded-2xl border-2 border-border bg-card shadow-2xl overflow-hidden text-card-foreground flex flex-col max-h-[calc(100vh-90px)] ring-1 ring-black/10 dark:ring-white/15">
                        <!-- Card Header -->
                        <div class="px-4 py-3 bg-muted/80 border-b border-border flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="size-8 rounded-lg bg-primary/15 text-primary border border-primary/25 flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
                                    <component :is="currentSection.icon" class="size-4.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Badge variant="outline" class="text-[9.5px] px-2 py-0.5 h-4.5 border-primary/40 bg-primary/10 text-primary uppercase font-mono font-bold shrink-0">
                                            {{ currentSection.id === 'start' ? 'ORIENTATION' : `Part ${currentSection.stepNumber}/${sections.length - 1}` }}
                                        </Badge>
                                        <span class="text-[10px] sm:text-[11px] font-bold text-muted-foreground uppercase tracking-wider truncate">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="text-sm sm:text-[15px] font-bold text-foreground leading-snug mt-0.5">
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
                                class="h-7 px-2.5 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 shrink-0 gap-1 rounded-lg ml-2"
                                title="Exit Tour (Esc)"
                            >
                                <span>Exit</span>
                                <X class="size-3.5" />
                            </Button>
                        </div>

                        <!-- Card Body -->
                        <div class="p-3 space-y-2.5 overflow-y-auto max-h-[min(380px,calc(100vh-190px))] text-xs">
                            <!-- Purpose & Function -->
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-1.5 text-[11px] sm:text-xs font-bold uppercase tracking-wider text-primary">
                                    <Info class="size-3.5" />
                                    <span>Purpose & Function:</span>
                                </div>
                                <p class="text-xs sm:text-[13px] text-foreground font-normal leading-relaxed">
                                    {{ currentSection.usedFor }}
                                </p>

                                <!-- Capabilities -->
                                <div v-if="currentSection.capabilities && currentSection.capabilities.length > 0" class="flex flex-wrap gap-1 pt-0.5">
                                    <span
                                        v-for="cap in currentSection.capabilities"
                                        :key="cap"
                                        class="inline-flex items-center gap-1.5 text-[10px] px-2 py-0.5 rounded-md font-semibold bg-muted/80 border border-border/80 text-foreground shadow-2xs"
                                    >
                                        <span class="size-1.5 rounded-full bg-primary shrink-0"></span>
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Guidance Steps -->
                            <div class="p-2.5 rounded-xl bg-muted/40 border border-border/80 space-y-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-foreground/70">How to use:</span>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, idx) in currentSection.howToUse"
                                        :key="idx"
                                        class="flex items-start gap-2 text-[11.5px] text-foreground"
                                    >
                                        <div class="size-4.5 rounded-full bg-primary text-primary-foreground font-mono font-bold text-[9px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                                            {{ idx + 1 }}
                                        </div>
                                        <span class="leading-relaxed font-normal text-foreground/95 flex-1 break-words">
                                            {{ step }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pro Tip -->
                            <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-950 dark:text-amber-100 space-y-1">
                                <span class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-300 text-[10px] uppercase tracking-wide">
                                    <Zap class="size-3 text-amber-600 dark:text-amber-400 fill-amber-500/30 shrink-0" />
                                    <span>Tip</span>
                                </span>
                                <p class="text-[11px] leading-relaxed font-medium">
                                    {{ currentSection.proTip }}
                                </p>
                            </div>

                            <!-- Interactive Action Shortcut -->
                            <div v-if="currentSection.actionLabel" class="pt-1">
                                <button
                                    type="button"
                                    @click="handleAction"
                                    class="w-full py-2.5 px-4 rounded-xl bg-primary/15 hover:bg-primary/25 text-primary text-xs sm:text-[13px] font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer border border-primary/30 shadow-2xs active:scale-[0.99]"
                                >
                                    <span>{{ currentSection.actionLabel }}</span>
                                    <ArrowRight class="size-4 shrink-0" />
                                </button>
                            </div>
                        </div>

                        <!-- Footer Navigation -->
                        <div class="px-4 py-3 bg-muted/70 border-t border-border flex items-center justify-between shrink-0">
                            <!-- Prev Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs px-3 gap-1 text-muted-foreground hover:text-foreground font-semibold"
                                :disabled="internalStep === 0"
                                @click="prevStep"
                            >
                                <ArrowLeft class="size-3.5" />
                                <span>Prev</span>
                            </Button>

                            <!-- Dot indicators -->
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-for="(_, idx) in sections"
                                    :key="idx"
                                    type="button"
                                    @click="goToStep(idx)"
                                    class="size-2 rounded-full transition-all cursor-pointer"
                                    :class="idx === internalStep ? 'bg-primary w-4' : 'bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                                    :title="idx === 0 ? 'Start: Dashboard Guide' : `Part ${idx}`"
                                ></button>
                            </div>

                            <!-- Next / Done Button -->
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs px-3.5 gap-1.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold shadow-xs cursor-pointer"
                                @click="nextStep"
                            >
                                <span>{{ currentSection.id === 'start' ? 'Start Tour' : internalStep === sections.length - 1 ? 'Done' : 'Next' }}</span>
                                <ArrowRight v-if="internalStep < sections.length - 1" class="size-3.5" />
                                <Check v-else class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
