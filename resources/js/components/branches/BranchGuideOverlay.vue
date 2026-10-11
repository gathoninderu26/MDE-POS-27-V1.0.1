<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Building2,
    Check,
    Clock,
    Compass,
    DollarSign,
    GitBranch,
    Handshake,
    HelpCircle,
    Info,
    Keyboard,
    Layers,
    MapPin,
    Network,
    Plus,
    Sparkles,
    Store,
    TrendingUp,
    Users,
    Wifi,
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
    notice?: string;
    shortcuts?: string;
    actionLabel?: string;
    actionType?: string;
    preferredPlacement?: 'bottom-right' | 'bottom-left' | 'left' | 'right' | 'bottom' | 'top';
}

const sections: TourSection[] = [
    {
        id: 'start',
        targetId: 'branch-tour-guide-btn',
        stepNumber: 0,
        title: 'Branch Outlets & Locations Guide',
        tag: 'EXPANSION & STORE TOPOLOGY',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        usedFor: 'Manage physical retail stores, satellite concessions, sub-locations, and franchise outlets from a single unified cloud ledger. Centralize stock transfers, cashier staffing, and multi-store reporting.',
        capabilities: [
            'Multi-Location Store Provisioning',
            'Independent POS Station Switching',
            'Cross-Store Inventory Visibility',
            'Staff Roster & Shift Segregation',
            'Consolidated Enterprise Sales Telemetry',
            'Concession & Colab Partner Sharing',
        ],
        howToUse: [
            'Click "Start Tour" or press → to discover how multi-outlet networking works.',
            'Switch active POS terminal stations to record sales under specific physical outlets.',
            'Inspect network quotas, inventory distribution, and remote store lockout status.',
        ],
        notice: 'Multi-Branch Activation Policy: Multi-branch expansion and sub-location networking automatically activates after three (3) months of consistent trading history and active subscription use on your primary store account.',
        proTip: 'Multi-Branch Activation: Store accounts must maintain 3 months of consistent trading before sub-outlets are provisioned to maintain audit integrity and supply chain readiness.',
        shortcuts: 'Click Page Guide (Begin) • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Start Walkthrough',
        actionType: 'start-walkthrough',
        preferredPlacement: 'bottom-right',
    },
    {
        id: 'create-branch',
        targetId: 'branch-tour-create-btn',
        stepNumber: 1,
        title: 'Register New Branch or Sub-Outlet',
        tag: 'STORE PROVISIONING & TOPOLOGY',
        shape: 'rect',
        rx: 10,
        icon: Plus,
        usedFor: 'Provision a new physical retail branch, kiosk, or satellite outlet. Assign store operating hours, local Wi-Fi router BSSID, store manager credentials, and parent-child inventory distribution links.',
        capabilities: [
            'Sub-Branch & Satellite Outlet Setup',
            'Parent HQ Inventory Hierarchy',
            'Operating Hours & Wi-Fi Geofence',
            'Dedicated Branch Manager Assignment',
        ],
        howToUse: [
            'Click "Create Branch / Sub-Branch" to open the store provisioning dialog.',
            'Enter the branch legal name, unique store identifier code, and physical geolocation.',
            'Select whether it operates as an independent Main HQ or receives restocks from a parent distribution center.',
        ],
        notice: 'Feature Availability: New branch creation activates after 3 months of consistent primary store operations.',
        proTip: 'Assigning a dedicated store Wi-Fi SSID ensures cashiers can only clock in and run transactions when physically inside the authorized retail premises.',
        actionLabel: 'Open Branch Setup Modal',
        actionType: 'open-create-branch',
        preferredPlacement: 'bottom-left',
    },
    {
        id: 'link-colab',
        targetId: 'branch-tour-colab-btn',
        stepNumber: 2,
        title: 'Concession & Colab Partner Linking',
        tag: 'CROSS-STORE COLLABORATION',
        shape: 'rect',
        rx: 10,
        icon: Handshake,
        usedFor: 'Establish B2B collaborative links with authorized partner merchants, concessionaires, or wholesale suppliers for live stock visibility, consignment inventory reconciliation, and cross-store transfers.',
        capabilities: [
            'Cross-Tenant B2B Store Linking',
            'Real-Time Consignment Stock Sync',
            'Wholesale Concession Tracking',
            'Granular Read / Transfer Permissions',
        ],
        howToUse: [
            'Click "Link Colab Partner" to open the partner authorization modal.',
            'Select which of your stores to share and enter the verified Partner Tenant ID.',
            'Choose between "Cross-Store Concession" or "Wholesale Consignment" with controlled access permissions.',
        ],
        proTip: 'Colab links allow you to sell vendor products on consignment without manually duplicating their inventory catalog into your database.',
        actionLabel: 'Open Colab Link Modal',
        actionType: 'open-colab-modal',
        preferredPlacement: 'bottom-left',
    },
    {
        id: 'quota-bar',
        targetId: 'branch-tour-quota',
        stepNumber: 3,
        title: 'Organization Quotas & Enterprise Telemetry',
        tag: 'CAPACITY & RUN-RATE TELEMETRY',
        shape: 'rect',
        rx: 12,
        icon: Building2,
        usedFor: 'High-level operational scorecard: monitors allocated branch license quotas (e.g. 10 allowed), total staff headcount across all stores, aggregate network inventory value, and month-to-date sales run-rate.',
        capabilities: [
            'Real-Time Branch License Quota Bar',
            'Enterprise-Wide Staff Aggregation',
            'Consolidated Inventory Stock Valuation',
            'Network Monthly Revenue Run-Rate',
        ],
        howToUse: [
            'Review the progress bar to verify how many branch slots remain under your current subscription tier.',
            'Track total inventory asset values distributed across your entire retail chain in real time.',
            'Inspect network-wide monthly gross revenue to compare individual outlet performance against company targets.',
        ],
        proTip: 'As your business scales past 10 outlets, contact enterprise support to unlock unlimited branch clusters with custom warehouse distribution channels.',
        preferredPlacement: 'bottom',
    },
    {
        id: 'active-station',
        targetId: 'branch-tour-active-station',
        stepNumber: 4,
        title: 'Current Active POS Terminal Station',
        tag: 'LIVE REGISTER AUDIT & SWITCHING',
        shape: 'rect',
        rx: 14,
        icon: Store,
        usedFor: 'Spotlight panel for the store you are currently operating on. Any transaction processed in the POS Terminal, receipt issued, or inventory adjustment executed is recorded under this designated station.',
        capabilities: [
            'Active Cashier Station Identification',
            'Live Today\'s Sales & Transaction Count',
            'In-Store Inventory Valuation',
            'Branch Manager & Cashier Shift Roster',
            'Store Operating Hours & Local Wi-Fi Status',
        ],
        howToUse: [
            'Confirm the outlet name and code displayed in green matches your current physical workstation.',
            'Click "Staff Roster & Details" to inspect which team members are currently on shift.',
            'Monitor live transactions and sales generated exclusively at this outlet today.',
        ],
        proTip: 'Always verify your active station before ringing up sales so receipts print the correct store address, telephone contact, and KRA compliance tax register number.',
        actionLabel: 'View Active Station Roster',
        actionType: 'view-active-roster',
        preferredPlacement: 'bottom',
    },
    {
        id: 'filter-tabs',
        targetId: 'branch-tour-filters',
        stepNumber: 5,
        title: 'Branch Classification & Status Filter',
        tag: 'NETWORK SEGMENTATION & VIEWS',
        shape: 'rect',
        rx: 10,
        icon: Layers,
        usedFor: 'Quickly toggle view between All Accessible outlets, Direct & Sub-Branches, Colab Partner Stores, or Suspended registers requiring supervisory intervention.',
        capabilities: [
            'All Accessible Network Outlets View',
            'Internal Direct Branches Only',
            'External Colab Partner Outlets',
            'Suspended & Quarantined Stores',
        ],
        howToUse: [
            'Click "Direct & Sub-Branches" to focus exclusively on company-owned stores.',
            'Click "Colab Partner Outlets" to audit third-party concession inventory.',
            'Click "Suspended" to identify registers temporarily locked for security or end-of-lease closures.',
        ],
        proTip: 'When troubleshooting store synchronization or auditing cashier shifts, filter by "Direct" to isolate internal company records.',
        actionLabel: 'Filter Direct Branches',
        actionType: 'filter-own',
        preferredPlacement: 'bottom',
    },
    {
        id: 'outlets-grid',
        targetId: 'branch-tour-outlets-grid',
        stepNumber: 6,
        title: 'Outlet Management Cards & Remote Controls',
        tag: 'SWITCHING & REMOTE LOCKOUT',
        shape: 'rect',
        rx: 14,
        icon: Network,
        usedFor: 'Individual branch control cards: switch your active POS terminal to any location, view assigned staff, inspect inventory value, or remotely suspend register operations during security emergencies.',
        capabilities: [
            '1-Click Active POS Terminal Switching',
            'Remote Outlet Pause & Lockout Control',
            'Branch Manager & Staff Roster Viewer',
            'Parent-Child Warehouse Restock Mapping',
        ],
        howToUse: [
            'Click "Switch POS to this Outlet" to instantly migrate your cashier terminal to that store.',
            'Click the Pause icon on any card to immediately suspend checkout operations at that location.',
            'Click "Manage Outlet" to review employee rosters, shift assignments, and daily financial run-rates.',
        ],
        proTip: 'The green ring highlights your current active terminal. Cashiers cannot switch to suspended branches, protecting off-hours sales integrity.',
        shortcuts: 'Click Outlet Card (Switch Station)',
        preferredPlacement: 'top',
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
                        <mask id="branch-enjoyhint-mask">
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
                        mask="url(#branch-enjoyhint-mask)"
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
                                variant="ghost"
                                size="sm"
                                class="size-7 p-0 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted shrink-0 ml-1.5"
                                @click="close"
                                title="Exit Tour (Esc)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>

                        <!-- Special Activation Policy Notice (if defined on section) -->
                        <div
                            v-if="currentSection.notice"
                            class="px-3 py-2 bg-amber-500/15 border-b border-amber-500/30 text-amber-900 dark:text-amber-200 text-[11px] flex items-start gap-2 shrink-0 leading-tight"
                        >
                            <Sparkles class="size-3.5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                            <div>
                                <strong class="font-semibold block">Activation Requirement:</strong>
                                <span>{{ currentSection.notice }}</span>
                            </div>
                        </div>

                        <!-- Scrollable Card Body -->
                        <div class="p-3.5 sm:p-4 space-y-3 overflow-y-auto flex-1 text-xs">
                            <!-- Purpose -->
                            <div>
                                <h4 class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider mb-1">
                                    What is this used for?
                                </h4>
                                <p class="text-foreground leading-relaxed text-xs">
                                    {{ currentSection.usedFor }}
                                </p>
                            </div>

                            <!-- Capabilities Tags -->
                            <div>
                                <h4 class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider mb-1.5">
                                    Core Capabilities
                                </h4>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="(cap, i) in currentSection.capabilities"
                                        :key="i"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-muted/80 border border-border/80 text-[10px] text-foreground font-medium"
                                    >
                                        <Check class="size-2.5 text-primary shrink-0" />
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- How to use -->
                            <div>
                                <h4 class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider mb-1">
                                    How to use it
                                </h4>
                                <ul class="space-y-1.5 pl-1">
                                    <li
                                        v-for="(instruction, i) in currentSection.howToUse"
                                        :key="i"
                                        class="text-foreground leading-relaxed flex items-start gap-2 text-[11px]"
                                    >
                                        <span class="size-3.5 rounded-full bg-primary/20 text-primary font-bold text-[9px] flex items-center justify-center shrink-0 mt-0.5">
                                            {{ i + 1 }}
                                        </span>
                                        <span class="flex-1">{{ instruction }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Pro Tip Box -->
                            <div v-if="currentSection.proTip" class="p-2.5 sm:p-3 rounded-lg bg-amber-500/10 border border-amber-500/25 text-amber-950 dark:text-amber-100 space-y-1">
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
