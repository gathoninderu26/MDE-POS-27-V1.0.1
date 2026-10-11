<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BarChart3,
    Calendar,
    Check,
    CircleDollarSign,
    Compass,
    Download,
    FileSpreadsheet,
    FileText,
    HelpCircle,
    Info,
    Keyboard,
    PieChart,
    Printer,
    Sparkles,
    TrendingUp,
    Users,
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
    shortcuts?: string;
    actionLabel?: string;
    actionType?: string;
    preferredPlacement?: 'bottom-right' | 'bottom-left' | 'left' | 'right' | 'bottom' | 'top';
}

const sections: TourSection[] = [
    {
        id: 'start',
        targetId: 'reports-tour-guide-btn',
        stepNumber: 0,
        title: 'Reports & Analytics Guide',
        tag: 'EXECUTIVE OVERVIEW & P&L ORIENTATION',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        usedFor: 'Welcome to the Financial & Operational Reports Control Center! This comprehensive analytics suite delivers real-time Profit & Loss (P&L) statements, cost of goods sold (COGS) tracking, gross & net margins (EBITDA), departmental profitability audits, cashier accountability metrics, and multi-format exports for tax compliance.',
        capabilities: [
            'Real-Time P&L Generation',
            'COGS & Margin Calculations',
            'Departmental Profitability',
            'Cashier Register Audits',
            'Excel & CSV Data Exports',
            'Printable Statement PDF',
        ],
        howToUse: [
            'Click "Start Tour" or press → on your keyboard to navigate through each financial reporting section.',
            'Use the date period switcher to inspect Today, 7 Days, This Month, or Annual trends.',
            'Export raw transaction files or trigger formatted print statements for financial audits.',
            'Press Esc at any time to close the guide and interact with your financial ledger.',
        ],
        proTip: 'The "Page Guide" button remains pinned in your header toolbar at all times so owners, store managers, and accountants can reference it whenever needed.',
        shortcuts: 'Click Page Guide (Begin) • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Start Walkthrough',
        actionType: 'start-walkthrough',
        preferredPlacement: 'bottom-right',
    },
    {
        id: 'date-filter',
        targetId: 'reports-tour-date-filter',
        stepNumber: 1,
        title: 'Reporting Period Range Switcher',
        tag: 'TEMPORAL RECONCILIATION & AUDIT',
        shape: 'rect',
        rx: 10,
        icon: Calendar,
        usedFor: 'Instant multi-interval reporting filter: switch seamlessly between Today (daily drawer balancing), 7 Days (weekly trade velocity), This Month (monthly P&L & VAT return period), and This Year (annual fiscal performance and shareholder returns).',
        capabilities: [
            'Single-Click Range Filtering',
            'Today\'s Live Sales Reconciliation',
            'Weekly Trading Velocity Audits',
            'Monthly Accounting & Tax Cycles',
            'Full Fiscal Year Performance',
        ],
        howToUse: [
            'Click "Today" to balance end-of-day register drawer sales against live POS orders.',
            'Click "7 Days" to review weekly customer traffic and sales spikes.',
            'Click "This Month" to analyze 30-day gross profit, operating overheads, and net margins.',
            'Click "This Year" for annual corporate tax computations and multi-month growth trend comparisons.',
        ],
        proTip: 'When reconciling end-of-day cashier drawers, switch to "Today" to match physical drawer cash with the exact gross revenue reported here.',
        shortcuts: 'Alt+1 (Today) • Alt+2 (Week) • Alt+3 (Month) • Alt+4 (Year)',
        actionLabel: 'Switch to This Month',
        actionType: 'filter-month',
        preferredPlacement: 'bottom',
    },
    {
        id: 'export-csv',
        targetId: 'reports-tour-export-csv',
        stepNumber: 2,
        title: 'Excel / CSV Raw Transaction Export',
        tag: 'DATA EXPORT & SPREADSHEET AUDIT',
        shape: 'rect',
        rx: 10,
        icon: FileSpreadsheet,
        usedFor: 'Generates structured CSV and Excel ledger spreadsheets containing raw sales totals, product departmental categories, and profit margins. Ready for direct ingestion into QuickBooks, Sage, Xero, or custom spreadsheet modeling.',
        capabilities: [
            '1-Click Instant Spreadsheet Generation',
            'Raw Departmental Sales Ledger',
            'Profit Margin Percentage Breakdown',
            'Compatibility with QuickBooks & Excel',
        ],
        howToUse: [
            'Select your desired reporting period using the date range buttons.',
            'Click "Export Excel / CSV" to immediately download the raw transaction ledger file.',
            'Open in Microsoft Excel or Google Sheets to perform custom pivot table analyses or supply chain forecasting.',
        ],
        proTip: 'Export monthly CSV files on the 1st of every month to maintain an external, immutable financial archive for your corporate accountants.',
        actionLabel: 'Export CSV Ledger Now',
        actionType: 'export-csv',
        preferredPlacement: 'bottom-left',
    },
    {
        id: 'print-pdf',
        targetId: 'reports-tour-print-pdf',
        stepNumber: 3,
        title: 'Printable Executive P&L Statements',
        tag: 'OFFICIAL FINANCIAL STATEMENTS',
        shape: 'rect',
        rx: 10,
        icon: Printer,
        usedFor: 'Generates beautifully styled, formal Profit & Loss statements ready for physical thermal or A4 printing, bank loan applications, investor presentations, and Kenya Revenue Authority (KRA) tax documentation.',
        capabilities: [
            'Official P&L Executive Format',
            'Corporate Tenant Header & Timestamping',
            'Revenue, COGS & Overhead Segregation',
            'Clean Thermal / A4 Print Stylesheet',
        ],
        howToUse: [
            'Click "Print Statement" to open the browser\'s high-resolution print dialog.',
            'Choose "Save as PDF" to generate a digital PDF statement or select your store printer.',
            'The print stylesheet automatically formats the statement with clean margins and hides web navigation bars.',
        ],
        proTip: 'Select "Save as PDF" in your print dialog to email formal monthly statements directly to your store directors or bank managers.',
        shortcuts: 'Ctrl+P (Print Preview)',
        actionLabel: 'Open Print Statement Preview',
        actionType: 'print-statement',
        preferredPlacement: 'bottom-left',
    },
    {
        id: 'kpis',
        targetId: 'reports-tour-kpis',
        stepNumber: 4,
        title: 'Executive Financial Health Telemetry',
        tag: 'TOP-LINE REVENUE & EBITDA TELEMETRY',
        shape: 'rect',
        rx: 14,
        icon: TrendingUp,
        usedFor: 'High-level financial scorecard displaying 5 essential business vitals: Gross Sales Revenue (top-line receipts), Cost of Goods Sold (COGS procurement), Gross Profit (and overall gross margin %), Operating Expenses (store overheads), and Net Profit (EBITDA).',
        capabilities: [
            'Real-Time Gross Sales Totals',
            'Automated Cost of Goods Sold (COGS)',
            'Live Gross Margin Percentage Indicator',
            'Overhead Expense Aggregation',
            'Net Profit EBITDA Calculation',
        ],
        howToUse: [
            'Compare Gross Profit against Operating Expenses to ensure healthy positive net return.',
            'Monitor the gross margin percentage: retail supermarkets target 18-25%, while pharmacies target 30-45%.',
            'If Net Profit is green, your business is operating at an EBITDA surplus for the chosen date range.',
        ],
        proTip: 'A rising Gross Revenue coupled with a declining Gross Margin % usually signals unrecorded cost price increases from suppliers or excessive frontline promotional discounts.',
        preferredPlacement: 'bottom',
    },
    {
        id: 'pnl-table',
        targetId: 'reports-tour-pnl-table',
        stepNumber: 5,
        title: 'Stepped Profit & Loss Ledger',
        tag: 'STEPPED P&L INCOME STATEMENT',
        shape: 'rect',
        rx: 14,
        icon: CircleDollarSign,
        usedFor: 'Standard multi-step financial income ledger: Step 1 (Revenue / Gross Sales) − Less COGS = Step 2 (Gross Profit) − Less Operating Overheads = Step 3 (Net Profit). Clearly visualizes where money is earned and spent.',
        capabilities: [
            'Standard Multi-Step Accounting Ledger',
            'Automated COGS Inventory Cost Deduction',
            'Detailed Operating Overhead Breakdown',
            'Dynamic Positive/Deficit Status Tagging',
        ],
        howToUse: [
            'Inspect Section 1 to confirm gross trading volume across all checkout stations.',
            'Verify that COGS accurately reflects wholesale acquisition costs from your purchase orders.',
            'Review Operating Overheads (rent, utilities, salaries, transport) recorded in the Expenses module.',
            'Confirm the final Section 3 Net Profit matches your bank statement cash flow.',
        ],
        proTip: 'Log all petty cash and store operational receipts in the Expenses module so your P&L statement accurately accounts for all business tax write-offs.',
        preferredPlacement: 'top',
    },
    {
        id: 'category-breakdown',
        targetId: 'reports-tour-category-breakdown',
        stepNumber: 6,
        title: 'Category Profitability & Contribution Matrix',
        tag: 'DEPARTMENTAL MARGIN & SHARE AUDIT',
        shape: 'rect',
        rx: 14,
        icon: PieChart,
        usedFor: 'Departmental sales and margin analysis: ranks product categories by total sales volume, reveals individual gross profit margins per category, and calculates each department\'s percentage share of overall company revenue.',
        capabilities: [
            'Department-Level Sales Contribution',
            'Individual Category Margin Auditing',
            'Percentage Revenue Share Calculation',
            'High-Margin vs High-Volume Identification',
        ],
        howToUse: [
            'Identify which categories generate the bulk of your store revenue (e.g. Groceries vs Beverages vs Dairy).',
            'Pay close attention to categories with high margin percentages (> 30%) — expand shelf space and marketing for these items.',
            'Look for categories with low margins (< 10%) and re-evaluate wholesale supplier agreements.',
        ],
        proTip: 'High-volume, low-margin goods (e.g. maize flour, sugar) drive foot traffic, while high-margin impulse items (e.g. confectionary, cosmetics) drive bottom-line net profit.',
        preferredPlacement: 'right',
    },
    {
        id: 'cashier-performance',
        targetId: 'reports-tour-cashier-performance',
        stepNumber: 7,
        title: 'Cashier Shift Performance & Accountability',
        tag: 'CASHIER PERFORMANCE & TICKET AUDITING',
        shape: 'rect',
        rx: 14,
        icon: BarChart3,
        usedFor: 'Operator productivity scorecard: monitors each staff member\'s sales volume, number of customer receipts issued, assigned shift timing, and average basket ticket size to identify top performers and operational bottlenecks.',
        capabilities: [
            'Operator Sales Volume Tracking',
            'Customer Receipt Count Telemetry',
            'Average Basket / Ticket Size Calculation',
            'Shift Session Accountability Auditing',
        ],
        howToUse: [
            'Review which cashiers processed the highest sales volumes and customer throughput.',
            'Compare average ticket sizes: cashiers with higher average tickets are successfully cross-selling and up-selling products at checkout.',
            'Correlate shift receipt counts with the Duty Roster to ensure registers were properly staffed during peak trading hours.',
        ],
        proTip: 'Reward cashiers who maintain both high transaction speed (> 150 receipts/shift) and high average basket values to incentivize excellent frontline customer service.',
        preferredPlacement: 'left',
    },
];

const internalStep = ref(props.currentStep ?? 0);
const targetRect = ref<{ top: number; left: number; width: number; height: number; right: number; bottom: number } | null>(null);
const cardRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const windowSize = ref({ width: 0, height: 0 });
const cardRef = ref<HTMLElement | null>(null);
let measurePollTimer: any = null;

const currentSection = computed(() => {
    return sections[internalStep.value] || sections[0];
});

// Measure target element position on page
const measureTarget = () => {
    if (typeof window === 'undefined') return;

    windowSize.value = {
        width: window.innerWidth,
        height: window.innerHeight,
    };

    const targetEl = document.getElementById(currentSection.value.targetId);
    if (!targetEl) {
        targetRect.value = null;
        return;
    }

    const rect = targetEl.getBoundingClientRect();
    targetRect.value = {
        top: rect.top,
        left: rect.left,
        width: rect.width,
        height: rect.height,
        right: rect.right,
        bottom: rect.bottom,
    };

    // Auto-scroll target into view smoothly if outside visible viewport
    if (rect.top < 60 || rect.bottom > window.innerHeight - 60) {
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(measureTarget, 350);
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
};

const findAndMeasure = (attempts = 0) => {
    measureTarget();
    if (!targetRect.value && attempts < 8) {
        measurePollTimer = setTimeout(() => findAndMeasure(attempts + 1), 50);
    }
};

// Compute smart speech bubble card position
const cardStyle = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return {
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: 'min(92vw, 440px)',
        };
    }

    const t = targetRect.value;
    const wWidth = windowSize.value.width;
    const wHeight = windowSize.value.height;
    const cardWidth = Math.min(wWidth - 32, 440);
    const cardHeight = 420;
    const pref = currentSection.value.preferredPlacement || 'bottom';

    // Mobile fallback (screen width < 768px): fixed centered top or bottom card
    if (wWidth < 768) {
        const targetCenterY = t.top + t.height / 2;
        if (targetCenterY > wHeight / 2) {
            return {
                top: '16px',
                left: '50%',
                transform: 'translateX(-50%)',
                width: `${cardWidth}px`,
            };
        } else {
            return {
                bottom: '16px',
                left: '50%',
                transform: 'translateX(-50%)',
                width: `${cardWidth}px`,
            };
        }
    }

    // Preferred placement logic with intelligent collision fallbacks:
    if (pref === 'bottom-right') {
        const left = Math.min(wWidth - cardWidth - 24, Math.max(20, t.left));
        const top = Math.min(wHeight - cardHeight, t.bottom + 16);
        return { top: `${Math.max(16, top)}px`, left: `${left}px`, width: `${cardWidth}px` };
    }

    if (pref === 'bottom-left') {
        const left = Math.max(24, t.right - cardWidth);
        const top = Math.min(wHeight - cardHeight, t.bottom + 16);
        return { top: `${Math.max(16, top)}px`, left: `${left}px`, width: `${cardWidth}px` };
    }

    if (pref === 'left') {
        if (t.left >= cardWidth + 30) {
            const left = Math.max(24, t.left - cardWidth - 16);
            const top = Math.max(20, Math.min(wHeight - cardHeight, t.top));
            return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
        } else {
            const left = Math.min(wWidth - cardWidth - 24, t.right + 16);
            const top = Math.max(20, Math.min(wHeight - cardHeight, t.top));
            return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
        }
    }

    if (pref === 'right') {
        if (wWidth - t.right >= cardWidth + 30) {
            const left = Math.min(wWidth - cardWidth - 24, t.right + 16);
            const top = Math.max(20, Math.min(wHeight - cardHeight, t.top));
            return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
        } else {
            const left = Math.max(24, t.left - cardWidth - 16);
            const top = Math.max(20, Math.min(wHeight - cardHeight, t.top));
            return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
        }
    }

    if (pref === 'top') {
        const left = Math.max(24, Math.min(wWidth - cardWidth - 24, t.left + t.width / 2 - cardWidth / 2));
        if (t.top >= cardHeight + 20) {
            const top = Math.max(20, t.top - cardHeight - 12);
            return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
        } else {
            const top = Math.min(wHeight - cardHeight, t.bottom + 16);
            return { top: `${Math.max(16, top)}px`, left: `${left}px`, width: `${cardWidth}px` };
        }
    }

    // Default 'bottom'
    const left = Math.max(24, Math.min(wWidth - cardWidth - 24, t.left + t.width / 2 - cardWidth / 2));
    if (t.bottom + cardHeight + 20 <= wHeight) {
        const top = t.bottom + 16;
        return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
    } else if (t.top >= cardHeight + 20) {
        const top = Math.max(20, t.top - cardHeight - 12);
        return { top: `${top}px`, left: `${left}px`, width: `${cardWidth}px` };
    } else {
        const top = Math.min(wHeight - cardHeight, t.bottom + 16);
        return { top: `${Math.max(16, top)}px`, left: `${left}px`, width: `${cardWidth}px` };
    }
});

// Dynamic directional arrow tail positioning
const speechBubbleTail = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) return null;

    const t = targetRect.value;
    const c = cardRect.value || { left: 0, top: 0, width: 440, height: 400 };
    const targetCenterX = t.left + t.width / 2;
    const targetCenterY = t.top + t.height / 2;

    // Target is above card
    if (t.bottom <= c.top + 20) {
        const offset = Math.max(32, Math.min((c.width || 440) - 32, targetCenterX - c.left));
        return { placement: 'top' as const, offset };
    }
    // Target is below card
    if (t.top >= c.top + (c.height || 400) - 20) {
        const offset = Math.max(32, Math.min((c.width || 440) - 32, targetCenterX - c.left));
        return { placement: 'bottom' as const, offset };
    }
    // Card is to the left of target
    if (c.left + (c.width || 440) <= t.left + 30) {
        const offset = Math.max(28, Math.min((c.height || 400) - 28, targetCenterY - c.top));
        return { placement: 'right' as const, offset };
    }
    // Card is to the right of target
    const offset = Math.max(28, Math.min((c.height || 400) - 28, targetCenterY - c.top));
    return { placement: 'left' as const, offset };
});

const nextStep = () => {
    if (internalStep.value < sections.length - 1) {
        internalStep.value++;
        emit('update:currentStep', internalStep.value);
    } else {
        close();
    }
};

const prevStep = () => {
    if (internalStep.value > 0) {
        internalStep.value--;
        emit('update:currentStep', internalStep.value);
    }
};

const goToStep = (step: number) => {
    if (step >= 0 && step < sections.length) {
        internalStep.value = step;
        emit('update:currentStep', step);
    }
};

const close = () => {
    emit('update:open', false);
};

const handleAction = () => {
    if (currentSection.value.actionType) {
        emit('action', currentSection.value.actionType);
    }
};

const handleTargetClick = () => {
    if (currentSection.value.id === 'start') {
        nextStep();
    } else if (currentSection.value.actionType) {
        handleAction();
    } else {
        nextStep();
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (!props.open) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        close();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextStep();
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevStep();
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            internalStep.value = props.currentStep ?? 0;
            nextTick(() => {
                findAndMeasure();
            });
        } else {
            if (measurePollTimer) clearTimeout(measurePollTimer);
        }
    },
    { immediate: true },
);

watch(
    () => props.currentStep,
    (val) => {
        if (val !== undefined && val !== internalStep.value) {
            internalStep.value = val;
        }
    },
);

watch(
    () => internalStep.value,
    () => {
        if (props.open) {
            nextTick(() => {
                findAndMeasure();
            });
        }
    },
);

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', measureTarget);
    window.addEventListener('scroll', measureTarget, true);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', measureTarget);
    window.removeEventListener('scroll', measureTarget, true);
    if (measurePollTimer) clearTimeout(measurePollTimer);
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
                <!-- SVG Dimming Backdrop with EnjoyHint Cutout Hole -->
                <svg
                    class="absolute inset-0 w-full h-full pointer-events-none transition-all duration-300"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <!-- EnjoyHint SVG Mask Hole -->
                        <mask id="reports-enjoyhint-mask">
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
                        mask="url(#reports-enjoyhint-mask)"
                        class="pointer-events-auto cursor-pointer"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole: clicking highlighted element triggers action or advances step -->
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
                    :title="currentSection.id === 'start' ? 'Click Page Guide to begin walkthrough' : 'Click highlighted button'"
                ></div>

                <!-- EnjoyHint Glowing Target Boundary Ring -->
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

                    <div class="rounded-2xl border-2 border-border bg-card shadow-2xl overflow-hidden text-card-foreground flex flex-col max-h-[calc(100vh-90px)] ring-1 ring-black/10 dark:ring-white/15">
                        <!-- Card Header with Visible Background -->
                        <div class="px-3.5 py-2.5 sm:px-4 sm:py-3 bg-muted/80 border-b border-border flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <div class="size-7.5 rounded-lg bg-primary/15 text-primary border border-primary/25 flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
                                    <component :is="currentSection.icon" class="size-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <Badge variant="outline" class="text-[9px] px-1.5 py-0 h-4 border-primary/40 bg-primary/10 text-primary uppercase font-mono font-bold shrink-0">
                                            {{ currentSection.id === 'start' ? 'ORIENTATION' : `Part ${currentSection.stepNumber}/${sections.length - 1}` }}
                                        </Badge>
                                        <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="text-xs sm:text-sm font-bold text-foreground leading-snug mt-0.5">
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
                                class="h-6 px-2 text-[11px] font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 shrink-0 gap-1 rounded-md ml-1"
                                title="Exit Tour (Esc)"
                            >
                                <span>Exit</span>
                                <X class="size-3" />
                            </Button>
                        </div>

                        <!-- Card Body (Scrollable if needed) -->
                        <div class="p-3.5 sm:p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-280px)] text-xs">
                            <!-- 1. What is this section used for -->
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary">
                                    <Info class="size-3.5" />
                                    <span>Purpose & Function:</span>
                                </div>
                                <p class="text-xs text-foreground/90 font-normal leading-relaxed">
                                    {{ currentSection.usedFor }}
                                </p>

                                <!-- Key Capabilities Badges -->
                                <div v-if="currentSection.capabilities && currentSection.capabilities.length > 0" class="flex flex-wrap gap-1.5 pt-1">
                                    <span
                                        v-for="cap in currentSection.capabilities"
                                        :key="cap"
                                        class="inline-flex items-center gap-1.5 text-[10px] px-2 py-0.5 rounded-md font-medium bg-muted/80 border border-border/80 text-foreground shadow-2xs"
                                    >
                                        <span class="size-1.5 rounded-full bg-primary/80 shrink-0"></span>
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- 2. Step-by-step guidance on how to use it -->
                            <div class="p-3 rounded-lg bg-muted/30 border border-border/80 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-foreground flex items-center gap-1">
                                        <span>How to use on reports:</span>
                                    </span>
                                    <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-muted/60 text-muted-foreground border border-border/50">
                                        {{ currentSection.howToUse.length }} steps
                                    </span>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, idx) in currentSection.howToUse"
                                        :key="idx"
                                        class="flex items-start gap-2.5 text-xs text-foreground"
                                    >
                                        <div class="size-4.5 rounded-full bg-primary text-primary-foreground font-mono font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                                            {{ idx + 1 }}
                                        </div>
                                        <span class="leading-relaxed font-normal text-foreground/95 flex-1 text-xs break-words">
                                            {{ step }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Keyboard Hotkeys Strip with styled key badges -->
                            <div v-if="currentSection.shortcuts" class="px-3 py-2 rounded-lg bg-muted/40 border border-border/80 flex flex-wrap items-center justify-between gap-1.5 text-xs">
                                <span class="font-bold text-muted-foreground flex items-center gap-1.5 text-[11px] shrink-0">
                                    <Keyboard class="size-3.5 text-primary" />
                                    <span>Hotkeys:</span>
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px]">
                                    <template v-for="(item, sIdx) in currentSection.shortcuts.split(' • ')" :key="sIdx">
                                        <kbd class="px-1.5 py-0.5 rounded bg-background border border-border text-foreground font-bold shadow-2xs">
                                            {{ item }}
                                        </kbd>
                                    </template>
                                </div>
                            </div>

                            <!-- 3. Store Manager Pro Tip -->
                            <div class="p-2.5 sm:p-3 rounded-lg bg-amber-500/10 border border-amber-500/25 text-amber-950 dark:text-amber-100 space-y-1">
                                <span class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-300 text-[10px] uppercase tracking-wide">
                                    <Zap class="size-3.5 text-amber-600 dark:text-amber-400 fill-amber-500/30 shrink-0" />
                                    <span>Store Manager Tip</span>
                                </span>
                                <p class="text-[11px] leading-relaxed font-medium">
                                    {{ currentSection.proTip }}
                                </p>
                            </div>

                            <!-- 4. Interactive Action Shortcut -->
                            <div v-if="currentSection.actionLabel" class="pt-0.5">
                                <button
                                    type="button"
                                    @click="handleAction"
                                    class="w-full py-2 px-3 rounded-lg bg-primary/15 hover:bg-primary/25 text-primary text-xs font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer border border-primary/30 shadow-2xs active:scale-[0.99]"
                                >
                                    <span>{{ currentSection.actionLabel }}</span>
                                    <ArrowRight class="size-3.5 shrink-0" />
                                </button>
                            </div>
                        </div>

                        <!-- EnjoyHint Navigation Footer -->
                        <div class="px-3.5 py-2.5 bg-muted/70 border-t border-border flex items-center justify-between shrink-0">
                            <!-- Previous Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs px-2.5 gap-1 text-muted-foreground hover:text-foreground font-semibold"
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
                                    class="size-2 rounded-full transition-all cursor-pointer"
                                    :class="idx === internalStep ? 'bg-primary w-4' : 'bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                                    :title="idx === 0 ? 'Start: Page Guide' : `Part ${idx}`"
                                ></button>
                            </div>

                            <!-- Next / Done Button -->
                            <Button
                                type="button"
                                size="sm"
                                class="h-7 text-xs px-3 gap-1.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold shadow-xs cursor-pointer"
                                @click="nextStep"
                            >
                                <span>{{ currentSection.id === 'start' ? 'Start Tour' : internalStep === sections.length - 1 ? 'Done' : 'Next' }}</span>
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
