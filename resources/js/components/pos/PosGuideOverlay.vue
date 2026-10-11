<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Compass,
    CreditCard,
    HelpCircle,
    Info,
    Keyboard,
    Package,
    ScanBarcode,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
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
    (e: 'switch-tab', tab: 'catalog' | 'cart'): void;
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
    mobileTab?: 'catalog' | 'cart';
}

const sections: TourSection[] = [
    {
        id: 'start',
        targetId: 'pos-tour-guide-btn',
        stepNumber: 0,
        title: 'Start Here: Click "Page Guide"',
        tag: 'INITIAL ORIENTATION & WALKTHROUGH',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        usedFor: 'Welcome to MDE-POS! Please click the highlighted "Page Guide" button in the header right now to begin your interactive terminal walkthrough.',
        capabilities: [
            'Visual Screen Spotlight',
            'Cashier Frontline Procedures',
            'Hardware Barcode Scanner Support',
            'KRA VAT & Multi-Tender Checkout',
        ],
        howToUse: [
            'Click the glowing "Page Guide" button right now to begin the terminal walkthrough.',
            'Follow the curved kinetic arrows as they spotlight each part of your cashier till.',
            'Press Esc at any time to exit the guide and return to cashier mode.',
        ],
        proTip: 'The Page Guide button remains pinned in the top header at all times so any cashier or trainee can re-open terminal guidance on demand.',
        shortcuts: 'Click Page Guide (Begin) • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Click Page Guide to Begin Walkthrough',
        actionType: 'start-walkthrough',
    },
    {
        id: 'search',
        targetId: 'pos-tour-search',
        stepNumber: 1,
        title: 'Barcode Scanner & Instant Search',
        tag: 'FRONT-LINE SCANNING & HARDWARE',
        shape: 'rect',
        rx: 16,
        icon: ScanBarcode,
        usedFor: 'Universal product lookup engine connecting physical USB/Bluetooth barcode guns, mobile camera scanners, and live catalog search. Handles EAN-13, UPC, SKU numbers, IMEI numbers, and generic pharmaceutical names with auto-commit.',
        capabilities: [
            'USB & Bluetooth HID Gun',
            'Mobile Camera Scanner',
            'Full-Text Fuzzy Search',
            'IMEI & Serial Tracking',
            'Auto-Commit on Enter',
        ],
        howToUse: [
            'Plug in any standard USB or Bluetooth barcode scanner into your register till — it functions as an automatic keyboard wedge with zero driver setup.',
            'Press F2 at any time from anywhere on the screen to instantly jump the cursor directly into this search input.',
            'Aim scanner gun at any product barcode and pull trigger — matching items are automatically queried and incremented in the cart ticket in under 100ms.',
            'For products without physical barcodes, type 2 or 3 letters of the item name, SKU, or brand, then use Up/Down arrow keys or tap to select.',
            'On smartphones or tablet registers, tap the green "Scan" button to engage the camera barcode scanner with real-time auto-focus.',
            'Pressing Enter after typing an exact SKU or barcode immediately commits the item directly into the active order ledger.',
        ],
        proTip: 'Hardware barcode guns send a trailing Enter keystroke by default. In MDE-POS, this adds the item and leaves the input focused and cleared, ready for the next consecutive scan in milliseconds.',
        shortcuts: 'F2 (Focus Search) • Enter (Add to Cart) • Esc (Clear)',
        actionLabel: 'Highlight & Focus Search (F2)',
        actionType: 'focus-search',
        mobileTab: 'catalog',
    },
    {
        id: 'catalog',
        targetId: 'pos-tour-catalog',
        stepNumber: 2,
        title: 'Product Catalog & Inventory Intelligence',
        tag: 'REAL-TIME INVENTORY & PRICING TIERS',
        shape: 'rect',
        rx: 18,
        icon: Package,
        usedFor: 'Touch-friendly grid displaying active inventory with real-time stock balances, depletion color warnings, dual pricing tiers (Retail vs Wholesale / Trade), and vertical-specific identifiers (generic drug names, site delivery, table numbers).',
        capabilities: [
            'Touch-Friendly Grid',
            'Real-Time Stock Depletion',
            'Dual Pricing Tiers',
            'Quick Category Pills',
            'Batch & Expiry Badges',
        ],
        howToUse: [
            'Tap or click any product card to immediately add 1 unit to the active ticket — tap multiple times to quickly increment quantities.',
            'Use the horizontal category pills at the top to filter items by department (e.g. Antibiotics, Hardware Tools, Beverages, Dry Goods).',
            'Monitor the color-coded stock badges: Red indicates critical stock (<= 5 units remaining), Amber indicates low stock (<= 15 units remaining).',
            'Toggle the Retail / Wholesale switcher (or press Alt+W) to instantly flip catalog prices to bulk trade rates across all items.',
            'Review specialized attributes right on the tile: batch numbers and expiry dates for pharmacy, specifications for construction, or modifiers for dining.',
            'Hover over any card to view detailed unit measurements (Pieces, Bags, Cartons, Liters, Meters) and track low stock warnings.',
        ],
        proTip: 'When an item hits 0 units, the system prevents accidental overselling and displays a restock alert. Supervisors can override if stock has physically arrived before warehouse receiving.',
        shortcuts: 'Alt + W (Toggle Wholesale/Retail) • Category Pills (Filter)',
        actionLabel: 'Filter Categories',
        actionType: 'select-first-category',
        mobileTab: 'catalog',
    },
    {
        id: 'cart',
        targetId: 'pos-tour-cart',
        stepNumber: 3,
        title: 'Active Ticket & Customer Profile',
        tag: 'ORDER LEDGER & CREDIT LIMITS',
        shape: 'rect',
        rx: 18,
        icon: ShoppingCart,
        usedFor: 'Live customer order ledger managing item quantities, line-item percentage discounts, customer credit limit tracking, insurance co-pay details, and hold/resume sales for high-volume queue management.',
        capabilities: [
            'Customer Credit Limits',
            'Insurance Co-Pay Tracking',
            'Line Item Steppers',
            'Per-Item % Discounts',
            'Park & Hold Tickets (F4)',
        ],
        howToUse: [
            'Select a customer from the account dropdown or click "Add Client" (Alt+C) to register walk-ins into loyalty or trade accounts.',
            'For credit customers, the system automatically checks their remaining credit limit and alerts you if an order exceeds approved ceiling.',
            'Use "+" and "-" buttons or type into the numeric box to modify quantities — items auto-recalculate line totals, tax, and discounts.',
            'Click "% Disc" on any line item to apply a targeted promotional or clearance discount without altering store-wide prices.',
            'Click "Hold Sale" (F4) to park the customer ticket if they forgot their wallet or need another item, freeing your terminal to serve the queue.',
            'Click the trash icon to remove an individual line, or click the cart clear icon (F8) to void the entire ticket with confirmation.',
        ],
        proTip: 'Selecting a Wholesale or Corporate customer profile automatically recalculates all existing cart items to wholesale trade pricing in real time.',
        shortcuts: 'Alt + C (Select Customer) • F4 (Hold Order) • F8 (Void Order)',
        actionLabel: 'Attach / New Customer (Alt+C)',
        actionType: 'open-customer',
        mobileTab: 'cart',
    },
    {
        id: 'checkout',
        targetId: 'pos-tour-checkout',
        stepNumber: 4,
        title: 'Totals, Tax & Multi-Tender Checkout',
        tag: 'SETTLEMENT, VAT & RECEIPT',
        shape: 'rect',
        rx: 18,
        icon: CreditCard,
        usedFor: 'Final transaction settlement engine providing real-time 16% VAT computation, bill-level discounts, instant tender buttons (M-Pesa Express, Cash Exact), split tender payments, and KRA-compliant 80mm thermal receipts.',
        capabilities: [
            'M-Pesa STK Push',
            'Cash Change Calculator',
            'Split Tender (Card/Cash/M-Pesa)',
            '16% VAT KRA Calculation',
            '80mm Thermal Receipt',
        ],
        howToUse: [
            'Inspect the real-time breakdown: Items count, Subtotal, Applied discount percentage, and 16% VAT before requesting payment.',
            'Click "M-Pesa Express" to send a direct STK push prompt to the customer’s phone — payment auto-reconciles in 5 seconds.',
            'Click "Cash Exact" or "Pay & Checkout" (F12 / Space) to open the tender dialog with quick-denomination cash buttons (KES 500, 1000, 2000).',
            'For split payments, enter partial amounts across Cash, M-Pesa, Card, and Credit Ledger until the remaining balance reaches KES 0.00.',
            'Upon completing the payment, an 80mm thermal receipt with till register ID, cashier name, and KRA PIN prints automatically.',
            'Thermal receipts can be reprinted, emailed, or sent via WhatsApp directly from the receipt preview dialog.',
        ],
        proTip: 'Press Spacebar or F12 from anywhere on the terminal to instantly open the checkout modal without moving your hand to the mouse.',
        shortcuts: 'F12 / Space (Open Pay Modal) • Enter (Confirm) • Esc (Cancel)',
        actionLabel: 'Open Checkout Modal (F12)',
        actionType: 'open-checkout',
        mobileTab: 'cart',
    },
    {
        id: 'header',
        targetId: 'pos-tour-header',
        stepNumber: 5,
        title: 'Header Controls, Hotkeys & Till Security',
        tag: 'KIOSK SECURITY & SHIFT AUDIT',
        shape: 'rect',
        rx: 14,
        icon: ShieldCheck,
        usedFor: 'Terminal-level operations displaying store branch, active cashier identity, live network sync status, sandbox demo countdown, keyboard hotkeys reference, fullscreen kiosk toggle, and supervisor PIN lockout mode.',
        capabilities: [
            'Supervisor PIN Lockout',
            'Keyboard Hotkeys Cheatsheet',
            'Offline Sync Status',
            'Branch & Register ID',
            'Fullscreen Kiosk Mode',
        ],
        howToUse: [
            'Check the green "Online / Sync OK" indicator to ensure all transactions are backing up to cloud ledger in real time.',
            'Click the Lock icon when stepping away from the register — this hides "Exit POS" and locks the register until the 4-digit PIN (1234) is entered.',
            'Click "Shortcuts" (or press ?) to display the full keyboard shortcut reference table for high-volume mouse-free cashiering.',
            'Click the Fullscreen icon to maximize the terminal, hiding browser tabs and navigation bars for dedicated point-of-sale kiosks.',
            'In demo mode, track remaining sandbox duration with the countdown badge in the center of the header.',
            'Access the shift close drawer at the end of day to tally opening cash, cash drops, M-Pesa totals, and print the X/Z financial report.',
        ],
        proTip: 'When the terminal is locked, browser back button navigation is trapped, preventing cashiers from accessing admin backend settings without supervisor authorization.',
        shortcuts: '? or Shortcuts (View Hotkeys) • Lock Icon (Lock Till) • Fullscreen (Kiosk)',
        actionLabel: 'View Keyboard Shortcuts',
        actionType: 'open-shortcuts',
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
    }
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
    if (sec.mobileTab && window.innerWidth < 1024) {
        emit('switch-tab', sec.mobileTab);
    }

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

        // Measure card rect for the EnjoyHint arrow
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
    const cardWidth = isMobile ? Math.min(340, windowSize.value.width - 24) : 380;
    const estimatedCardHeight = 400;

    // On mobile screens: smartly position above or below the target button so it is NEVER covered
    if (isMobile) {
        const targetCenterY = top + height / 2;
        // If target button/element is in lower half of screen (e.g. checkout), anchor card at TOP
        if (targetCenterY > windowSize.value.height / 2) {
            return {
                top: '12px',
                left: '12px',
                right: '12px',
                width: 'auto',
                maxWidth: '360px',
                margin: '0 auto',
            };
        }
        // If target is in upper half (e.g. search/header), anchor card at BOTTOM
        return {
            bottom: '12px',
            left: '12px',
            right: '12px',
            width: 'auto',
            maxWidth: '360px',
            margin: '0 auto',
        };
    }

    // Top header section or initial Page Guide button -> place callout comfortably below header
    if (currentSection.value.id === 'header' || currentSection.value.id === 'start' || top < 70) {
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

    // Part 4 (Checkout): Target is at bottom-right.
    // Position card in the upper-left of checkout area so it is 100% visible and NEVER hidden below the screen fold!
    if (currentSection.value.id === 'checkout') {
        const optimalLeft = Math.max(16, left - cardWidth - 24);
        return {
            top: '72px',
            left: `${optimalLeft}px`,
            width: `${cardWidth}px`,
        };
    }

    // Right section (Cart) -> place callout to the left of target with safe top placement
    if (left > cardWidth + 24) {
        const optimalTop = Math.max(70, Math.min(top, 100));
        return {
            top: `${optimalTop}px`,
            left: `${left - cardWidth - 20}px`,
            width: `${cardWidth}px`,
        };
    }

    // Left sections (Search, Catalog) -> place callout to the right of target
    if (left + width < windowSize.value.width - cardWidth - 24) {
        const optimalTop = Math.max(70, Math.min(top, 120));
        return {
            top: `${optimalTop}px`,
            left: `${left + width + 20}px`,
            width: `${cardWidth}px`,
        };
    }

    // Fallback: If horizontal clearance is tight, place above or below target
    if (top > estimatedCardHeight + 20) {
        return {
            bottom: `${windowSize.value.height - top + 16}px`,
            left: '50%',
            transform: 'translateX(-50%)',
            width: `${cardWidth}px`,
        };
    }

    return {
        top: `${top + height + 16}px`,
        left: '50%',
        transform: 'translateX(-50%)',
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

    // CASE 1: Target is above the card (e.g. Page Guide button / Header)
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
    // CASE 3: Card is on the LEFT, Target is on the RIGHT (e.g. Cart, Checkout)
    if (c.left + (c.width || 380) <= t.left + 30) {
        const targetAnchorY = currentSection.value.id === 'checkout'
            ? t.top + t.height / 2
            : t.top + Math.min(70, t.height / 2);
        const offset = Math.max(28, Math.min((c.height || 400) - 28, targetAnchorY - c.top));
        return {
            placement: 'right' as const,
            offset,
        };
    }
    // CASE 4: Card is on the RIGHT, Target is on the LEFT (e.g. Search, Catalog)
    const targetAnchorY = currentSection.value.id === 'catalog'
        ? t.top + 90
        : t.top + Math.min(60, t.height / 2);
    const offset = Math.max(28, Math.min((c.height || 400) - 28, targetAnchorY - c.top));
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
                <!-- SVG Dimming Backdrop with EnjoyHint Cutout Hole & Bubble Connector -->
                <svg
                    class="absolute inset-0 w-full h-full pointer-events-none transition-all duration-300"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <!-- EnjoyHint SVG Mask Hole -->
                        <mask id="enjoyhint-mask">
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
                        mask="url(#enjoyhint-mask)"
                        class="pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole: clicking highlighted button triggers action or advances step -->
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

                <!-- EnjoyHint Glowing Target Boundary Ring (Clean frame without obstructing badges) -->
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
                                            {{ currentSection.id === 'start' ? 'ORIENTATION' : `Part ${currentSection.stepNumber}/5` }}
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

                        <!-- Card Body (Clean & readable spacing with complete visibility) -->
                        <div class="p-3.5 space-y-3 overflow-y-auto max-h-[min(500px,calc(100vh-200px))] text-xs">
                            <!-- 1. What it is used for -->
                            <div class="space-y-1.5">
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
                                        <span>How to use on terminal:</span>
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

