<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ArrowLeft,
    ArrowRight,
    Barcode,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Circle,
    Compass,
    CreditCard,
    Eye,
    HelpCircle,
    Info,
    Keyboard,
    Lock,
    Package,
    PartyPopper,
    Receipt,
    RotateCcw,
    ScanBarcode,
    Search,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Sparkles,
    Store,
    Target,
    UserCheck,
    UserPlus,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Props {
    cartItemsCount: number;
    customerSelected: boolean;
    saleCompleted: boolean;
    receiptViewed: boolean;
    tenantSlug?: string;
}

const props = withDefaults(defineProps<Props>(), {
    cartItemsCount: 0,
    customerSelected: false,
    saleCompleted: false,
    receiptViewed: false,
    tenantSlug: 'demo',
});

const emit = defineEmits<{
    (e: 'focus-search'): void;
    (e: 'open-customer-modal'): void;
    (e: 'open-pay-modal'): void;
    (e: 'open-tour-overlay', step?: number): void;
    (e: 'step-change', step: number): void;
}>();

// Navigation Mode: 'missions' (practice checklist) vs 'tour' (section-by-section breakdown)
const activeTab = ref<'tour' | 'missions'>('tour');
const currentTourStep = ref(0);

watch(currentTourStep, (newStep) => {
    emit('step-change', newStep);
});

const isCollapsed = ref(false);
const isDismissed = ref(false);

// Local storage key for persistent state
const storageKey = computed(() => `mde_demo_widget_${props.tenantSlug}`);

// Individual task completion tracking
const task1Done = ref(false); // Add item to cart
const task2Done = ref(false); // Select / change customer
const task3Done = ref(false); // Process test checkout
const task4Done = ref(false); // View / print receipt

// Section breakdown definition for the interactive screen tour
const tourSections = [
    {
        id: 'search',
        title: '1. Barcode Scanner & Instant Search',
        subtitle: 'Hardware Barcode Guns & Camera Scan',
        icon: ScanBarcode,
        description: 'Universal product lookup engine connecting physical USB/Bluetooth barcode scanners, mobile camera scanning, and live catalog search. Scanned items auto-commit instantly.',
        features: [
            'Plug & play USB/Bluetooth barcode guns without software drivers',
            'F2 global hotkey jumps straight to barcode input from anywhere',
            'Full-text fuzzy search by product name, SKU, brand, or IMEI',
            'Mobile camera scanning with real-time barcode auto-focus',
        ],
        proTip: 'Hardware barcode guns send a trailing Enter keystroke by default, querying and adding items in under 100ms.',
        actionLabel: 'Highlight Search Bar (F2)',
        action: () => emit('focus-search'),
    },
    {
        id: 'catalog',
        title: '2. Product Grid & Stock Intelligence',
        subtitle: 'Real-Time Inventory & Pricing Tiers',
        icon: Package,
        description: 'Touch-friendly product tiles showing live inventory quantities, stock depletion warnings, dual pricing tiers (Retail vs Wholesale / Trade), and vertical-specific specs.',
        features: [
            'Horizontal category pills for rapid department navigation',
            'Color-coded stock badges (Red <= 5 critical, Amber <= 15 low)',
            'Automatic price switching based on active customer tier or wholesale toggle',
            'Pharmacy batch/expiry and hardware job site attributes on tiles',
        ],
        proTip: 'Tapping any product card multiple times quickly increments its quantity on the active ticket.',
        actionLabel: null,
        action: null,
    },
    {
        id: 'cart',
        title: '3. Active Cart & Customer Profile',
        subtitle: 'Credit Limits & Line Item Discounts',
        icon: ShoppingCart,
        description: 'Live order ledger for adjusting ordered quantities, applying per-item discounts, tracking customer credit ceilings, insurance co-pay, and parking tickets.',
        features: [
            'Customer attachment with live store credit limit tracking',
            'Real-time subtotal, 16% tax (VAT), and promotional discount computation',
            'Quantity steppers and line-item percentage discount overrides',
            'Hold & resume ticket park (F4) to serve queues without losing work',
        ],
        proTip: 'Selecting a Wholesale customer profile automatically recalculates all existing ticket lines to trade pricing.',
        actionLabel: 'Open Customer Modal (Alt+C)',
        action: () => emit('open-customer-modal'),
    },
    {
        id: 'checkout',
        title: '4. Fast Checkout & Multi-Tender Bar',
        subtitle: 'Cash, M-Pesa STK & Split Payments',
        icon: CreditCard,
        description: 'Final transaction settlement engine providing real-time change calculation, automated M-Pesa STK push prompts, credit recording, or split payments across multiple tenders.',
        features: [
            'Automated M-Pesa STK push prompt to customer phone in 5s',
            'Quick-tender cash denomination buttons (Exact, KES 500, 1000, 2000)',
            'Split payments across Cash, M-Pesa, Card, and Store Credit',
            'Automatic 80mm thermal receipt generation with KRA PIN audit',
        ],
        proTip: 'Press Spacebar or F12 from anywhere on the register to open the checkout modal without using your mouse.',
        actionLabel: 'Open Payment Modal (F12)',
        action: () => emit('open-pay-modal'),
    },
    {
        id: 'header',
        title: '5. Header Controls & Terminal Security',
        subtitle: 'Lockout Mode & Shift Management',
        icon: ShieldCheck,
        description: 'The top navigation bar displays active branch, cashier ID, live cloud sync status, keyboard hotkeys guide, fullscreen kiosk mode, and supervisor lockout controls.',
        features: [
            'Terminal Lockout mode (requires 4-digit PIN 1234 to prevent cashier tampering)',
            'Keyboard hotkeys reference table for rapid mouse-free operations',
            'Shift close drawer & audit tally access for daily financial balancing',
            'Distraction-free fullscreen kiosk toggle for dedicated counter hardware',
        ],
        proTip: 'Locking the terminal hides the Exit POS button and traps browser back navigation, keeping staff restricted to the till.',
        actionLabel: null,
        action: null,
    },
];

const currentTour = computed(() => tourSections[currentTourStep.value]);

const nextTourStep = () => {
    if (currentTourStep.value < tourSections.length - 1) {
        currentTourStep.value++;
    } else {
        // When finished tour, offer practice missions
        activeTab.value = 'missions';
    }
};

const prevTourStep = () => {
    if (currentTourStep.value > 0) {
        currentTourStep.value--;
    }
};

const loadPersistedProgress = () => {
    if (typeof window === 'undefined') return;
    try {
        const raw = localStorage.getItem(storageKey.value);
        if (raw) {
            const saved = JSON.parse(raw);
            if (saved.task1) task1Done.value = true;
            if (saved.task2) task2Done.value = true;
            if (saved.task3) task3Done.value = true;
            if (saved.task4) task4Done.value = true;
            if (saved.isDismissed) isDismissed.value = true;
            if (saved.isCollapsed) isCollapsed.value = true;
            if (saved.activeTab) activeTab.value = saved.activeTab;
        }
    } catch {
        // Fallback gracefully
    }
};

const persistProgress = () => {
    if (typeof window === 'undefined') return;
    try {
        localStorage.setItem(
            storageKey.value,
            JSON.stringify({
                task1: task1Done.value,
                task2: task2Done.value,
                task3: task3Done.value,
                task4: task4Done.value,
                isDismissed: isDismissed.value,
                isCollapsed: isCollapsed.value,
                activeTab: activeTab.value,
            })
        );
    } catch {
        // Ignore
    }
};

// Auto-detect when props change
watch(
    () => props.cartItemsCount,
    (count) => {
        if (count > 0 && !task1Done.value) {
            task1Done.value = true;
            persistProgress();
        }
    },
    { immediate: true }
);

watch(
    () => props.customerSelected,
    (selected) => {
        if (selected && !task2Done.value) {
            task2Done.value = true;
            persistProgress();
        }
    },
    { immediate: true }
);

watch(
    () => props.saleCompleted,
    (completed) => {
        if (completed && !task3Done.value) {
            task3Done.value = true;
            persistProgress();
        }
    },
    { immediate: true }
);

watch(
    () => props.receiptViewed,
    (viewed) => {
        if (viewed && !task4Done.value) {
            task4Done.value = true;
            persistProgress();
        }
    },
    { immediate: true }
);

const tasks = computed(() => [
    {
        id: 'add_item',
        title: 'Add a product to cart',
        description: 'Click any catalog card or scan a barcode.',
        done: task1Done.value,
        actionLabel: 'Highlight Search Bar',
        action: () => emit('focus-search'),
    },
    {
        id: 'select_customer',
        title: 'Attach customer profile',
        description: 'Assign Walk-in or select a registered account.',
        done: task2Done.value,
        actionLabel: 'Open Customer Modal',
        action: () => emit('open-customer-modal'),
    },
    {
        id: 'complete_payment',
        title: 'Simulate checkout payment',
        description: 'Test Cash, M-Pesa, or Split Tender.',
        done: task3Done.value,
        actionLabel: 'Open Checkout',
        action: () => emit('open-pay-modal'),
    },
    {
        id: 'view_receipt',
        title: 'Inspect thermal receipt',
        description: 'Review 80mm thermal receipt with till summary.',
        done: task4Done.value,
        actionLabel: null,
        action: null,
    },
]);

const completedCount = computed(() => {
    let count = 0;
    if (task1Done.value) count++;
    if (task2Done.value) count++;
    if (task3Done.value) count++;
    if (task4Done.value) count++;
    return count;
});

const progressPercent = computed(() => Math.round((completedCount.value / 4) * 100));

const isAllCompleted = computed(() => completedCount.value === 4);

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
    persistProgress();
};

const dismissWidget = () => {
    isDismissed.value = true;
    persistProgress();
};

const resetMissions = () => {
    task1Done.value = false;
    task2Done.value = false;
    task3Done.value = false;
    task4Done.value = false;
    isDismissed.value = false;
    isCollapsed.value = false;
    persistProgress();
};

onMounted(() => {
    loadPersistedProgress();
});
</script>

<template>
    <!-- Floating Interactive Tutorial Dock (Bottom Left) -->
    <div
        v-if="!isDismissed"
        class="fixed bottom-4 left-4 z-40 max-w-sm w-[94vw] sm:w-[380px] transition-all duration-300 font-sans select-none"
    >
        <!-- Collapsed Floating Pill -->
        <div
            v-if="isCollapsed"
            @click="toggleCollapse"
            class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-full bg-card/95 border border-primary/40 shadow-xl backdrop-blur-md cursor-pointer hover:border-primary transition-all text-xs"
        >
            <div class="flex items-center gap-2">
                <div class="size-6 rounded-full bg-primary/15 text-primary flex items-center justify-center font-bold text-[10px]">
                    <Sparkles class="size-3" />
                </div>
                <div class="flex items-center gap-1.5 font-bold text-foreground">
                    <span>Interactive POS Guide</span>
                    <Badge variant="outline" class="text-[10px] px-1.5 py-0 h-4 border-primary/40 text-primary">
                        {{ activeTab === 'tour' ? `Step ${currentTourStep + 1}/5` : `${completedCount}/4 Done` }}
                    </Badge>
                </div>
            </div>

            <div class="flex items-center gap-1 text-muted-foreground">
                <ChevronUp class="size-3.5" />
            </div>
        </div>

        <!-- Expanded Card -->
        <div
            v-else
            class="rounded-2xl border border-border/80 bg-card/98 shadow-2xl backdrop-blur-md overflow-hidden text-foreground flex flex-col max-h-[85vh]"
        >
            <!-- Header -->
            <div class="px-4 py-3 bg-muted/40 border-b border-border/70 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <div class="size-7 rounded-lg bg-primary text-primary-foreground flex items-center justify-center font-black text-xs shadow-xs">
                        <Compass class="size-3.5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h4 class="text-xs font-extrabold text-foreground tracking-tight leading-none">
                                Terminal Interactive Guide
                            </h4>
                            <Badge variant="outline" class="text-[9px] px-1 py-0 h-3.5 border-primary/40 text-primary uppercase font-mono">
                                Demo
                            </Badge>
                        </div>
                        <p class="text-[10px] text-muted-foreground mt-0.5">
                            Master every section of the frontline checkout station
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="toggleCollapse"
                        class="size-6 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/80 flex items-center justify-center transition-colors cursor-pointer"
                        title="Minimize"
                    >
                        <ChevronDown class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        @click="dismissWidget"
                        class="size-6 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/80 flex items-center justify-center transition-colors cursor-pointer"
                        title="Dismiss guide"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>

            <!-- Tab Switcher: Screen Tour vs Hands-On Missions -->
            <div class="p-1.5 bg-muted/20 border-b border-border/60 flex gap-1 shrink-0">
                <button
                    type="button"
                    @click="() => { activeTab = 'tour'; persistProgress(); }"
                    :class="[
                        'flex-1 py-1 px-2.5 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer',
                        activeTab === 'tour'
                            ? 'bg-card text-primary shadow-xs border border-border/60'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <BookOpen class="size-3" />
                    <span>Screen Breakdown</span>
                    <span class="text-[10px] opacity-70">({{ currentTourStep + 1 }}/5)</span>
                </button>

                <button
                    type="button"
                    @click="() => { activeTab = 'missions'; persistProgress(); }"
                    :class="[
                        'flex-1 py-1 px-2.5 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer',
                        activeTab === 'missions'
                            ? 'bg-card text-primary shadow-xs border border-border/60'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <Target class="size-3" />
                    <span>Hands-on Tasks</span>
                    <span class="text-[10px] font-mono opacity-80 font-bold">({{ completedCount }}/4)</span>
                </button>
            </div>

            <!-- TAB 1: SCREEN TOUR & SECTION EXPLANATIONS -->
            <div v-if="activeTab === 'tour'" class="p-4 space-y-3 overflow-y-auto">
                <!-- Section Header -->
                <div class="flex items-start gap-2.5">
                    <div class="size-8 rounded-xl bg-primary/10 border border-primary/20 text-primary flex items-center justify-center shrink-0 mt-0.5">
                        <component :is="currentTour.icon" class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-xs font-extrabold text-foreground leading-tight">
                                {{ currentTour.title }}
                            </h5>
                            <span class="text-[10px] font-mono text-muted-foreground">
                                Step {{ currentTourStep + 1 }}/5
                            </span>
                        </div>
                        <p class="text-[11px] font-medium text-primary mt-0.5">
                            {{ currentTour.subtitle }}
                        </p>
                    </div>
                </div>

                <!-- Launch On-Screen Spotlight Button -->
                <button
                    type="button"
                    @click="emit('open-tour-overlay', currentTourStep)"
                    class="w-full py-2 px-3 rounded-xl bg-gradient-to-r from-primary/15 via-emerald-500/10 to-primary/15 hover:from-primary/25 hover:to-primary/25 border border-primary/30 text-primary text-xs font-black flex items-center justify-center gap-2 transition-all cursor-pointer shadow-xs active:scale-98"
                >
                    <Sparkles class="size-3.5 animate-pulse" />
                    <span>Spotlight & Mark on Terminal</span>
                    <span class="text-[10px] font-mono opacity-70">Part {{ currentTourStep + 1 }}/5 &rarr;</span>
                </button>

                <!-- Explanation Body -->
                <p class="text-xs text-muted-foreground leading-relaxed">
                    {{ currentTour.description }}
                </p>

                <!-- Key Capabilities Checklist -->
                <div class="p-2.5 rounded-xl bg-muted/40 border border-border/60 space-y-1.5 text-[11px]">
                    <span class="font-bold text-foreground text-[10px] uppercase tracking-wider block">
                        Key Capabilities:
                    </span>
                    <div
                        v-for="(feat, fIdx) in currentTour.features"
                        :key="fIdx"
                        class="flex items-start gap-2 text-muted-foreground"
                    >
                        <div class="size-1.5 rounded-full bg-primary shrink-0 mt-1.5"></div>
                        <span class="leading-tight text-foreground">{{ feat }}</span>
                    </div>
                </div>

                <!-- Store Manager Pro Tip Callout -->
                <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] space-y-1 text-amber-900 dark:text-amber-200">
                    <span class="font-bold flex items-center gap-1.5 text-amber-800 dark:text-amber-300 text-[10px] uppercase tracking-wide">
                        <Info class="size-3" />
                        <span>Store Manager Tip</span>
                    </span>
                    <p class="text-[10px] leading-relaxed">
                        {{ currentTour.proTip }}
                    </p>
                </div>

                <!-- Section Action Trigger -->
                <div v-if="currentTour.actionLabel" class="pt-0.5">
                    <button
                        type="button"
                        @click="currentTour.action"
                        class="w-full py-1.5 px-3 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer border border-primary/20"
                    >
                        <span>{{ currentTour.actionLabel }}</span>
                        <span>&rarr;</span>
                    </button>
                </div>

                <!-- Step Navigation Controls -->
                <div class="pt-2 flex items-center justify-between border-t border-border/60">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs px-2 gap-1 text-muted-foreground"
                        :disabled="currentTourStep === 0"
                        @click="prevTourStep"
                    >
                        <ArrowLeft class="size-3" />
                        <span>Previous</span>
                    </Button>

                    <!-- Dot Indicators -->
                    <div class="flex items-center gap-1">
                        <button
                            v-for="(_, idx) in tourSections"
                            :key="idx"
                            type="button"
                            @click="currentTourStep = idx"
                            class="size-2 rounded-full transition-all cursor-pointer"
                            :class="idx === currentTourStep ? 'bg-primary w-4' : 'bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                            :title="`Jump to section ${idx + 1}`"
                        ></button>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        class="h-7 text-xs px-2.5 gap-1 bg-primary text-primary-foreground font-bold"
                        @click="nextTourStep"
                    >
                        <span>{{ currentTourStep === tourSections.length - 1 ? 'Go to Tasks' : 'Next' }}</span>
                        <ArrowRight class="size-3" />
                    </Button>
                </div>
            </div>

            <!-- TAB 2: HANDS-ON MISSIONS & CHECKLIST -->
            <div v-else class="p-3.5 space-y-3 overflow-y-auto">
                <!-- Animated Progress Bar -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="font-bold text-foreground">Checkout Practice Progress</span>
                        <span class="font-mono text-primary font-bold">{{ progressPercent }}% ({{ completedCount }}/4)</span>
                    </div>
                    <div class="h-1.5 w-full bg-muted/60 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-primary transition-all duration-500 ease-out"
                            :style="{ width: `${progressPercent}%` }"
                        ></div>
                    </div>
                </div>

                <!-- All Missions Completed Celebration -->
                <div v-if="isAllCompleted" class="p-4 text-center space-y-3 bg-emerald-500/[0.04] rounded-xl border border-emerald-500/25">
                    <div class="size-11 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto shadow-xs">
                        <PartyPopper class="size-5 animate-bounce" />
                    </div>
                    <div class="space-y-1">
                        <h5 class="text-xs font-black text-foreground">
                            Mission Complete! POS Mastered 🎉
                        </h5>
                        <p class="text-[11px] text-muted-foreground leading-relaxed">
                            You've verified barcode scanning, catalog lookups, customer profile attachments, and thermal receipt generation.
                        </p>
                    </div>

                    <div class="p-2.5 rounded-xl border border-primary/20 bg-primary/[0.05] text-[11px] text-left space-y-1">
                        <span class="font-bold text-foreground flex items-center gap-1">
                            <ShieldCheck class="size-3.5 text-primary" />
                            <span>Ready to launch your real store?</span>
                        </span>
                        <p class="text-[10px] text-muted-foreground">
                            Get your permanent cloud database, custom subdomain, and thermal receipt printer setup.
                        </p>
                    </div>

                    <div class="pt-1 flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            class="w-full h-8 text-[11px] font-bold bg-primary hover:bg-primary/90 text-primary-foreground"
                            as-child
                        >
                            <a href="/#merchant-waitlist-section">
                                <span>Register Real Store</span>
                            </a>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2.5 text-[11px] text-muted-foreground hover:text-foreground shrink-0"
                            @click="resetMissions"
                            title="Restart checklist"
                        >
                            <RotateCcw class="size-3" />
                        </Button>
                    </div>
                </div>

                <!-- Active Task Items -->
                <div v-else class="space-y-2">
                    <div
                        v-for="(task, index) in tasks"
                        :key="task.id"
                        class="p-2 rounded-xl transition-colors border"
                        :class="[
                            task.done
                                ? 'bg-emerald-500/[0.05] border-emerald-500/25'
                                : 'bg-muted/25 border-border/50 hover:border-border'
                        ]"
                    >
                        <div class="flex items-start gap-2.5">
                            <div class="pt-0.5 shrink-0">
                                <CheckCircle2
                                    v-if="task.done"
                                    class="size-4 text-emerald-600 dark:text-emerald-400"
                                />
                                <Circle
                                    v-else
                                    class="size-4 text-muted-foreground/60"
                                />
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <span
                                        class="text-xs font-bold leading-none truncate"
                                        :class="task.done ? 'line-through text-muted-foreground' : 'text-foreground'"
                                    >
                                        {{ index + 1 }}. {{ task.title }}
                                    </span>

                                    <Badge
                                        v-if="task.done"
                                        variant="outline"
                                        class="text-[9px] px-1 py-0 border-emerald-500/30 text-emerald-600 bg-emerald-500/10 shrink-0"
                                    >
                                        Done
                                    </Badge>
                                </div>

                                <p class="text-[10px] text-muted-foreground mt-1 leading-snug">
                                    {{ task.description }}
                                </p>

                                <!-- Action Shortcut -->
                                <div v-if="!task.done && task.actionLabel" class="pt-1.5">
                                    <button
                                        type="button"
                                        @click="task.action"
                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-primary hover:underline cursor-pointer"
                                    >
                                        <span>{{ task.actionLabel }}</span>
                                        <span>&rarr;</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer reset link -->
                    <div class="pt-1 flex items-center justify-between text-[10px] text-muted-foreground">
                        <span>Practice sandbox session</span>
                        <button
                            type="button"
                            @click="resetMissions"
                            class="hover:text-foreground inline-flex items-center gap-1 cursor-pointer"
                        >
                            <RotateCcw class="size-2.5" />
                            <span>Reset Checklist</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
