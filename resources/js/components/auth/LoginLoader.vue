<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ArrowRight,
    BarChart3,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    Cpu,
    Database,
    Key,
    Layers,
    Loader2,
    Package,
    ShieldCheck,
    Sparkles,
    Store,
    Users,
    Wifi,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Props {
    open: boolean;
    tenantName?: string;
    staffName?: string;
    staffRole?: string;
    planName?: string;
}

const props = withDefaults(defineProps<Props>(), {
    open: true,
    tenantName: 'Your Store',
    staffName: 'Staff Member',
    staffRole: 'Owner',
    planName: 'Pro',
});

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'finish'): void;
}>();

const DURATION_MS = 7000;
const progress = ref(0);
const isDone = ref(false);
let interval: ReturnType<typeof setInterval> | null = null;
let tipTimer: ReturnType<typeof setInterval> | null = null;

const steps = [
    { icon: Key, label: 'Authenticating staff credentials' },
    { icon: ShieldCheck, label: 'Verifying tenant isolation & session scope' },
    { icon: Database, label: 'Loading inventory catalog & product index' },
    { icon: Package, label: 'Syncing stock levels & batch records' },
    { icon: Users, label: 'Fetching staff roster & active shifts' },
    { icon: CircleDollarSign, label: 'Loading financial records & P&L data' },
    { icon: BarChart3, label: 'Preparing sales analytics & KPI dashboard' },
    { icon: Wifi, label: 'Establishing POS terminal connection' },
    { icon: Layers, label: 'Applying role-based module permissions' },
    { icon: Cpu, label: 'Initializing system environment' },
];

const stepThresholds = [10, 20, 30, 42, 52, 62, 72, 82, 92, 100];

const currentStepIndex = computed(() => {
    for (let i = 0; i < stepThresholds.length; i++) {
        if (progress.value < stepThresholds[i]) return i;
    }
    return steps.length - 1;
});

const completedSteps = computed(() => stepThresholds.filter((t) => progress.value >= t).length);

const tips = [
    { icon: Zap, text: 'Press F11 on the POS terminal for full-screen cashier mode.' },
    { icon: ShieldCheck, text: 'Each staff role has isolated module access � cashiers cannot view P&L reports.' },
    { icon: BarChart3, text: 'Use the Reports tab daily to track gross profit margins by category.' },
    { icon: Package, text: 'Set reorder points on fast-moving stock to get low-inventory alerts.' },
    { icon: CircleDollarSign, text: 'Petty cash entries in the Expenses module sync to your P&L automatically.' },
    { icon: Users, text: 'Assign shifts to staff so the system tracks who processed each sale.' },
    { icon: Wifi, text: 'The POS works offline � sales sync automatically when connection is restored.' },
    { icon: Clock, text: 'Run your end-of-day Z-Report before closing to reconcile the cash drawer.' },
];

const tipIndex = ref(0);

const startLoading = () => {
    progress.value = 0;
    isDone.value = false;
    tipIndex.value = 0;

    const startTime = Date.now();

    interval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const pct = Math.min(100, Math.round((elapsed / DURATION_MS) * 100));
        progress.value = pct;

        if (pct >= 100) {
            clearInterval(interval!);
            interval = null;
            isDone.value = true;
            if (tipTimer) clearInterval(tipTimer);
        }
    }, 50);

    tipTimer = setInterval(() => {
        tipIndex.value = (tipIndex.value + 1) % tips.length;
    }, 2800);
};

const dismiss = () => {
    if (interval) {
        clearInterval(interval);
        interval = null;
    }
    if (tipTimer) {
        clearInterval(tipTimer);
        tipTimer = null;
    }
    emit('update:open', false);
    emit('finish');
};

onMounted(() => {
    if (props.open) startLoading();
});
onUnmounted(() => {
    if (interval) clearInterval(interval);
    if (tipTimer) clearInterval(tipTimer);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-500 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center bg-background/95 p-4 backdrop-blur-xl">
            <div
                class="bg-card/98 relative w-full max-w-2xl overflow-hidden rounded-2xl border border-border/70 shadow-2xl shadow-black/25 backdrop-blur-xl duration-300 animate-in fade-in zoom-in-95"
            >
                <div class="pointer-events-none absolute -left-20 -top-20 size-56 rounded-full bg-primary/15 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-20 -right-20 size-56 rounded-full bg-emerald-500/10 blur-3xl"></div>

                <!-- Header -->
                <div class="relative flex items-center gap-4 border-b border-border/60 px-6 pb-5 pt-7">
                    <div class="relative flex size-14 shrink-0 items-center justify-center">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-primary/20 opacity-60"></span>
                        <div
                            :class="[
                                'relative flex size-14 items-center justify-center rounded-2xl shadow-lg transition-all duration-700',
                                isDone ? 'bg-emerald-500 shadow-emerald-500/30' : 'bg-primary shadow-primary/30',
                            ]"
                        >
                            <CheckCircle2 v-if="isDone" class="size-7 text-white" />
                            <Store v-else class="size-7 animate-pulse text-primary-foreground" />
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-black tracking-tight text-foreground">
                                {{ isDone ? 'Welcome Back!' : 'Signing You In...' }}
                            </h2>
                            <Badge
                                variant="outline"
                                class="border-primary/30 bg-primary/5 text-[9px] font-bold uppercase tracking-wider text-primary"
                            >
                                {{ planName }} Plan
                            </Badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <span class="font-semibold text-foreground">{{ staffName }}</span>
                            <span> &middot; {{ staffRole }} &middot; </span>
                            <span class="font-mono text-primary">{{ tenantName }}</span>
                        </p>
                    </div>

                    <Transition
                        enter-active-class="transition duration-300"
                        enter-from-class="opacity-0 scale-75"
                        enter-to-class="opacity-100 scale-100"
                    >
                        <Badge
                            v-if="isDone"
                            variant="outline"
                            class="shrink-0 border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-bold text-emerald-700 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="mr-1.5 size-3" />
                            All Systems Ready
                        </Badge>
                        <div v-else class="shrink-0 text-right">
                            <div class="font-mono text-2xl font-black leading-none text-primary">
                                {{ progress }}<span class="text-sm font-bold">%</span>
                            </div>
                            <div class="mt-0.5 text-[9px] uppercase tracking-wider text-muted-foreground">Loading</div>
                        </div>
                    </Transition>
                </div>

                <!-- Body: two columns -->
                <div class="grid grid-cols-1 gap-5 px-6 py-5 sm:grid-cols-2">
                    <!-- LEFT: Progress + Steps -->
                    <div class="space-y-3">
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">System Startup</p>
                            <div class="h-2.5 w-full overflow-hidden rounded-full border border-border/50 bg-muted/60">
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all duration-150 ease-linear',
                                        isDone
                                            ? 'bg-gradient-to-r from-emerald-500 to-emerald-400'
                                            : 'bg-gradient-to-r from-primary via-sky-500 to-primary',
                                    ]"
                                    :style="{ width: `${progress}%` }"
                                ></div>
                            </div>
                        </div>

                        <div class="space-y-0.5">
                            <div
                                v-for="(step, idx) in steps"
                                :key="idx"
                                :class="[
                                    'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs transition-all duration-300',
                                    idx < completedSteps
                                        ? 'text-foreground'
                                        : idx === currentStepIndex
                                          ? 'bg-primary/8 text-foreground ring-1 ring-primary/20'
                                          : 'text-muted-foreground/45',
                                ]"
                            >
                                <div
                                    :class="[
                                        'flex size-5 shrink-0 items-center justify-center rounded transition-all duration-300',
                                        idx < completedSteps
                                            ? 'bg-emerald-500 text-white'
                                            : idx === currentStepIndex
                                              ? 'bg-primary text-primary-foreground'
                                              : 'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    <CheckCircle2 v-if="idx < completedSteps" class="size-3" />
                                    <Loader2 v-else-if="idx === currentStepIndex" class="size-3 animate-spin" />
                                    <component :is="step.icon" v-else class="size-3 opacity-40" />
                                </div>
                                <span :class="['flex-1 truncate font-medium', idx < completedSteps ? 'line-through decoration-emerald-500/50' : '']">
                                    {{ step.label }}
                                </span>
                                <span v-if="idx < completedSteps" class="shrink-0 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                                    >&#10003;</span
                                >
                                <span v-else-if="idx === currentStepIndex" class="shrink-0 text-[9px] font-bold uppercase text-primary">Now</span>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Tips + Security -->
                    <div class="flex flex-col gap-4">
                        <!-- Rotating Pro Tip -->
                        <div class="flex-1 space-y-2.5 rounded-xl border border-primary/20 bg-primary/5 p-4">
                            <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-primary">
                                <Sparkles class="size-3.5" />
                                Pro Tip
                            </div>
                            <Transition
                                mode="out-in"
                                enter-active-class="transition duration-300 ease-out"
                                enter-from-class="opacity-0 translate-y-1"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-200 ease-in"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0 -translate-y-1"
                            >
                                <div :key="tipIndex" class="flex items-start gap-2.5">
                                    <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/15">
                                        <component :is="tips[tipIndex].icon" class="size-3.5 text-primary" />
                                    </div>
                                    <p class="text-xs leading-relaxed text-foreground/80">{{ tips[tipIndex].text }}</p>
                                </div>
                            </Transition>
                            <!-- Dot indicators -->
                            <div class="flex items-center gap-1 pt-1">
                                <div
                                    v-for="(_, i) in tips"
                                    :key="i"
                                    :class="[
                                        'h-1.5 rounded-full transition-all duration-300',
                                        i === tipIndex ? 'w-4 bg-primary' : 'w-1.5 bg-primary/20',
                                    ]"
                                ></div>
                            </div>
                        </div>

                        <!-- Security assurance -->
                        <div class="space-y-2 rounded-xl border border-emerald-500/25 bg-emerald-500/5 p-3.5">
                            <div
                                class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400"
                            >
                                <ShieldCheck class="size-3.5" />
                                Session Security
                            </div>
                            <div class="space-y-1.5 text-[11px] text-emerald-900/80 dark:text-emerald-200/80">
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 class="size-3 shrink-0 text-emerald-500" />
                                    <span>Multi-tenant database partition active</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 class="size-3 shrink-0 text-emerald-500" />
                                    <span>Role-based access control enforced</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 class="size-3 shrink-0 text-emerald-500" />
                                    <span>Session scoped to {{ tenantName }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between gap-3 border-t border-border/60 bg-muted/20 px-6 py-4">
                    <p class="font-mono text-[10px] text-muted-foreground">MDE POS &middot; Secure Multi-Tenant Platform</p>
                    <Transition
                        enter-active-class="transition duration-400 ease-out"
                        enter-from-class="opacity-0 translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                    >
                        <Button
                            v-if="isDone"
                            type="button"
                            class="h-9 gap-2 bg-emerald-600 px-5 text-sm font-bold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-700"
                            @click="dismiss"
                        >
                            <CheckCircle2 class="size-4" />
                            Enter Dashboard
                            <ArrowRight class="size-4" />
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 px-4 text-xs text-muted-foreground hover:text-foreground"
                            @click="dismiss"
                        >
                            Skip
                        </Button>
                    </Transition>
                </div>
            </div>
        </div>
    </Transition>
</template>
