<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useDemoCountdown } from '@/composables/useDemoCountdown';
import { useTenant } from '@/composables/useTenant';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Building2,
    Check,
    CircleDollarSign,
    Clock,
    Compass,
    CreditCard,
    Crown,
    Eye,
    EyeOff,
    ExternalLink,
    KeyRound,
    LoaderCircle,
    Lock,
    MapPin,
    Monitor,
    Phone,
    Pill,
    Receipt,
    RefreshCcw,
    ShieldAlert,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Sparkles,
    Store,
    TrendingUp,
    Truck,
    UserCheck,
    Utensils,
    Wrench,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const showPassword = ref(false);


const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const { currentTenant, setTenant, isSubdomain, isDemo } = useTenant();
const { daysLeft, hoursLeft, minutesLeft, secondsLeft, isExpired, isUrgent } = useDemoCountdown();

interface StaffRolePreset {
    role: string;
    prefix: string;
    label: string;
    description: string;
    icon: any;
}

const staffPresets: StaffRolePreset[] = [
    { role: 'Owner', prefix: 'owner', label: 'Owner', description: 'Full access to all 12 modules & administration', icon: Crown },
    { role: 'Manager', prefix: 'manager', label: 'Manager', description: 'POS, catalog, inventory & sales operations', icon: ShieldCheck },
    { role: 'Cashier', prefix: 'cashier', label: 'Cashier', description: 'POS register, barcode scanning & sales history', icon: ShoppingCart },
    { role: 'Inventory Manager', prefix: 'inventory', label: 'Inventory', description: 'Stock replenishment, batches & suppliers', icon: Boxes },
    { role: 'Accountant', prefix: 'accountant', label: 'Accountant', description: 'Expenses, petty cash & P&L analytics', icon: CircleDollarSign },
    { role: 'Supervisor', prefix: 'supervisor', label: 'Supervisor', description: 'POS supervisor, returns & price check', icon: UserCheck },
];

const normalizeRolePrefix = (roleStr: string): string => {
    const r = (roleStr || '').toLowerCase().trim();
    if (r.includes('cashier')) return 'cashier';
    if (r.includes('manager') && !r.includes('inventory')) return 'manager';
    if (r.includes('inventory')) return 'inventory';
    if (r.includes('accountant')) return 'accountant';
    if (r.includes('supervisor')) return 'supervisor';
    return 'owner';
};

// Detect the subscribed demo persona from URL, server session, or local storage
const subscribedRole = computed<string>(() => {
    // 1. From URL query params (?role=... or ?persona=...)
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const qRole = params.get('role') || params.get('persona');
        if (qRole) return qRole;
    }

    // 2. From Inertia server session props
    const serverRole =
        (page.props as any)?.demo?.role ||
        (page.props as any)?.demo?.persona ||
        (page.props as any)?.tenant?.demo_role ||
        (page.props as any)?.tenant?.role;
    if (serverRole) return serverRole;

    // 3. From localStorage for this tenant
    if (typeof window !== 'undefined') {
        const slug = currentTenant.value?.slug || '';
        const storedRole =
            localStorage.getItem(`mde_demo_role_${slug}`) ||
            localStorage.getItem(`mde_demo_persona_${slug}`) ||
            localStorage.getItem('mde_demo_role') ||
            localStorage.getItem('mde_demo_persona');
        if (storedRole) return storedRole;
    }

    // Default fallback demo persona
    return 'Owner';
});

const subscribedPrefixes = computed<string[]>(() => {
    const raw = subscribedRole.value;
    const parts = raw.split(',').map((p) => normalizeRolePrefix(p));
    return [...new Set(parts)];
});

// Remove all roles the user hasn't subscribed to in terms of the demo persona
const availablePresets = computed(() => {
    const filtered = staffPresets.filter((preset) => subscribedPrefixes.value.includes(preset.prefix));
    return filtered.length > 0 ? filtered : [staffPresets[0]];
});

const selectedRole = ref<string>('owner');

const activePreset = computed(() => {
    return availablePresets.value.find((p) => p.prefix === selectedRole.value) || availablePresets.value[0];
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
    tenant: '',
});

const applyPreset = (prefix: string) => {
    selectedRole.value = prefix;
    const slug = currentTenant.value?.slug || 'nairobi-fresh-mart';
    form.email = `${prefix}@${slug}.mdepos.co.ke`;
    form.password = 'password';
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const tenantParam = urlParams.get('tenant');
        if (tenantParam) {
            setTenant(tenantParam);
        }
    }

    // Only auto-fill presets if this is an interactive demo account
    if (isDemo.value) {
        if (typeof window !== 'undefined') {
            const urlParams = new URLSearchParams(window.location.search);
            const roleParam = urlParams.get('role') || urlParams.get('persona');
            if (roleParam) {
                const prefix = normalizeRolePrefix(roleParam);
                selectedRole.value = prefix;
                applyPreset(prefix);
                return;
            }
        }
        // Apply subscribed role preset with current tenant slug
        const initialPrefix = availablePresets.value[0]?.prefix || 'owner';
        selectedRole.value = initialPrefix;
        applyPreset(initialPrefix);
    }
});

// Watch currentTenant slug and demo state changes
watch(
    () => [currentTenant.value?.slug, isDemo.value],
    () => {
        if (isDemo.value) {
            const initialPrefix = availablePresets.value[0]?.prefix || selectedRole.value || 'owner';
            selectedRole.value = initialPrefix;
            applyPreset(initialPrefix);
        } else {
            // For real accounts, clear any default mock credentials
            if (form.email.includes('.mdepos.co.ke') && form.password === 'password') {
                form.email = '';
                form.password = '';
            }
        }
    },
);

// Computed: did the backend tell us this account is expired?
const accountExpiredError = computed(() => (form.errors as any).account_expired ?? null);

const executeFormPost = () => {
    // Only pass tenant slug if explicitly provided by server prop or authenticating demo preset
    const isMockEmail = form.email.includes('.mdepos.co.ke');
    if (page?.props?.tenant) {
        form.tenant = currentTenant.value?.slug || '';
    } else if (isDemo.value && isMockEmail) {
        form.tenant = currentTenant.value?.slug || '';
    } else if (currentTenant.value?.slug) {
        form.tenant = currentTenant.value.slug;
    } else {
        form.tenant = '';
    }

    if (typeof window !== 'undefined') {
        sessionStorage.setItem('mde_auto_open_guide', 'true');
        sessionStorage.removeItem('mde_dashboard_guide_prompted');
        sessionStorage.removeItem('mde_pos_guide_prompted');
    }
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};

// Block submit if demo account is expired
const submit = () => {
    if (form.processing) return;
    if (isDemo.value && (isExpired.value || accountExpiredError.value)) return;
    executeFormPost();
};



const getBusinessIcon = (type?: string) => {
    switch (type) {
        case 'Pharmacy':
            return Pill;
        case 'Hardware':
            return Wrench;
        case 'Restaurant':
            return Utensils;
        case 'Electronics':
            return Smartphone;
        case 'Wholesale':
        case 'Wholesale & Retail':
            return Truck;
        default:
            return Store;
    }
};

const getVerticalBadgeColor = (type?: string) => {
    switch (type) {
        case 'Pharmacy':
            return 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/30';
        case 'Hardware':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30';
        case 'Restaurant':
            return 'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/30';
        case 'Electronics':
            return 'bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-500/30';
        case 'Wholesale':
        case 'Wholesale & Retail':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30';
        default:
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
    }
};


</script>

<template>
    <AuthBase
        :title="currentTenant?.id ? currentTenant.name : (isDemo ? (isSubdomain ? 'Demo Terminal Sandbox' : 'Demo Store Terminal') : (isSubdomain ? 'Operator Authentication' : 'Store Terminal Sign In'))"
        :description="currentTenant?.id ? `${currentTenant.businessType} • Authorized Staff Portal` : (isDemo ? 'Explore POS, Inventory & Back-Office with simulated personas' : 'Enter authorized employee credentials to open terminal register')"
    >
        <Head :title="`Log in - ${currentTenant.name}`" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- 1. ENVIRONMENT MODE STRIP (Demo Sandbox vs Live Production)       -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- DEMO SANDBOX BANNER -->
        <div
            v-if="isDemo"
            class="mb-4 rounded-xl border border-amber-500/35 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-primary/10 p-3 shadow-xs"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 shrink-0">
                        <Sparkles class="size-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-wider text-amber-900 dark:text-amber-200 truncate">
                            Demo Sandbox Mode
                        </p>
                        <p class="text-[11px] text-muted-foreground truncate">
                            Sample store catalog & transactions • 1-Click persona switching
                        </p>
                    </div>
                </div>
                <Badge variant="outline" class="border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-200 text-[10px] font-mono font-bold uppercase shrink-0">
                    Sandbox
                </Badge>
            </div>
        </div>

        <!-- REAL PRODUCTION BANNER -->
        <div
            v-else
            class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3 shadow-xs"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 shrink-0">
                        <ShieldCheck class="size-4" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                            <p class="text-xs font-black uppercase tracking-wider text-emerald-950 dark:text-emerald-100 truncate">
                                Live Production Portal
                            </p>
                        </div>
                        <p class="text-[11px] text-muted-foreground truncate">
                            Secure dedicated tenant partition • Encrypted session
                        </p>
                    </div>
                </div>
                <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px] font-mono font-bold uppercase shrink-0 tracking-wide">
                    Live
                </Badge>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- Account Expired Alert (DEMO ONLY)                                  -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div
            v-if="isDemo && (accountExpiredError || isExpired)"
            class="mb-5 rounded-2xl border border-destructive/40 bg-destructive/5 overflow-hidden shadow-md"
        >
            <!-- Header strip -->
            <div class="flex items-center gap-3 px-4 py-3 bg-destructive/10 border-b border-destructive/25">
                <div class="flex size-8 items-center justify-center rounded-xl bg-destructive/20 text-destructive shrink-0">
                    <AlertTriangle class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-extrabold text-destructive leading-tight">Demo Account Expired</p>
                    <p class="text-[11px] text-destructive/70 font-medium mt-0.5">
                        Your trial period for <strong>{{ currentTenant.name }}</strong> has ended.
                    </p>
                </div>
                <span class="px-2 py-0.5 rounded-md bg-destructive text-destructive-foreground text-[10px] font-bold font-mono uppercase tracking-wide shrink-0">
                    EXPIRED
                </span>
            </div>

            <!-- Body -->
            <div class="px-4 py-4 space-y-4">
                <p class="text-xs text-muted-foreground leading-relaxed">
                    Your demo access window has closed and login has been disabled. You have two options:
                </p>

                <!-- Option 1: Resubscribe for another demo -->
                <div class="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5 space-y-2">
                    <div class="flex items-center gap-2">
                        <RefreshCcw class="size-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
                        <span class="text-xs font-bold text-amber-900 dark:text-amber-200">Extend Your Demo</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                        Need more time to explore the system? Request a fresh demo session and we'll set you up with another trial period.
                    </p>
                    <a
                        href="https://mdepos.co.ke/demo"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-colors shadow-sm"
                    >
                        <RefreshCcw class="size-3" />
                        Request Another Demo
                        <ExternalLink class="size-3 opacity-70" />
                    </a>
                </div>

                <!-- Option 2: Join waitlist -->
                <div class="p-3 rounded-xl border border-primary/25 bg-primary/5 space-y-2">
                    <div class="flex items-center gap-2">
                        <TrendingUp class="size-3.5 text-primary shrink-0" />
                        <span class="text-xs font-bold text-foreground">Happy with the Demo? Go Live!</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                        If MDE POS met your expectations, join our early-access waitlist and be among the first merchants to get a fully-activated account.
                    </p>
                    <a
                        href="https://mdepos.co.ke/waitlist"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary hover:bg-primary/90 text-primary-foreground text-xs font-bold transition-colors shadow-sm"
                    >
                        <TrendingUp class="size-3" />
                        Join the Waitlist
                        <ExternalLink class="size-3 opacity-70" />
                    </a>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- 2. BUSINESS IDENTITY CARD                                         -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div class="mb-5 space-y-3">
            <div
                :class="[
                    'p-4 rounded-xl border shadow-sm space-y-3 transition-all',
                    isDemo
                        ? 'border-primary/20 bg-primary/5'
                        : 'border-border/80 bg-card/60'
                ]"
            >
                <div class="flex items-center justify-between">
                    <span
                        :class="[
                            'text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5',
                            isDemo ? 'text-primary' : 'text-emerald-600 dark:text-emerald-400'
                        ]"
                    >
                        <component :is="isDemo ? Sparkles : ShieldCheck" class="size-3.5" />
                        {{ isDemo ? 'Dedicated Demo Portal' : 'Official Store Gateway' }}
                    </span>
                    <Badge variant="outline" :class="['text-[9px] font-bold uppercase shrink-0', getVerticalBadgeColor(currentTenant.businessType)]">
                        {{ currentTenant.businessType }}
                    </Badge>
                </div>

                <div class="flex items-center gap-3 min-w-0">
                    <div
                        :class="[
                            'flex size-11 items-center justify-center rounded-xl font-black shrink-0 shadow-sm',
                            isDemo ? 'bg-primary text-primary-foreground' : 'bg-emerald-600 text-white'
                        ]"
                    >
                        <component :is="getBusinessIcon(currentTenant.businessType)" class="size-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-extrabold text-sm sm:text-base truncate text-foreground">
                            {{ currentTenant.name }}
                        </h2>
                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground font-mono">
                            <span :class="isDemo ? 'text-primary font-semibold' : 'text-emerald-600 dark:text-emerald-400 font-semibold'">
                                {{ currentTenant.slug }}
                            </span>
                            <span>•</span>
                            <span>{{ currentTenant.plan }} Plan</span>
                        </div>
                    </div>
                </div>

                <!-- REAL ACCOUNT METADATA ROW (Tax PIN, Contact, Verified Status) -->
                <div v-if="!isDemo" class="pt-2.5 border-t border-border/60 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                    <div v-if="currentTenant.kraPin" class="flex items-center gap-1.5 text-muted-foreground">
                        <ShieldCheck class="size-3.5 text-emerald-600 shrink-0" />
                        <span class="font-mono font-medium">PIN: {{ currentTenant.kraPin }}</span>
                    </div>
                    <div v-else class="flex items-center gap-1.5 text-muted-foreground">
                        <Check class="size-3.5 text-emerald-600 shrink-0" />
                        <span>KRA eTIMS Compliant</span>
                    </div>

                    <div v-if="currentTenant.phone" class="flex items-center gap-1.5 text-muted-foreground truncate">
                        <Phone class="size-3.5 text-primary shrink-0" />
                        <span class="truncate">{{ currentTenant.phone }}</span>
                    </div>
                    <div v-else-if="currentTenant.address" class="flex items-center gap-1.5 text-muted-foreground truncate">
                        <MapPin class="size-3.5 text-primary shrink-0" />
                        <span class="truncate">{{ currentTenant.address }}</span>
                    </div>
                    <div v-else class="flex items-center gap-1.5 text-muted-foreground">
                        <Building2 class="size-3.5 text-primary shrink-0" />
                        <span>Live Tenant Isolated</span>
                    </div>
                </div>

                <!-- Account Expiry Countdown Timer (DEMO ONLY) -->
                <div
                    v-if="isDemo"
                    :class="[
                        'p-2.5 rounded-xl border flex items-center justify-between gap-2 transition-all shadow-xs',
                        isExpired
                            ? 'bg-destructive/10 border-destructive/30 text-destructive'
                            : isUrgent
                                ? 'bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-200'
                                : 'bg-emerald-500/10 border-emerald-500/25 text-emerald-950 dark:text-emerald-100'
                    ]"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <div
                            :class="[
                                'size-7 rounded-lg flex items-center justify-center shrink-0 shadow-xs',
                                isExpired
                                    ? 'bg-destructive/20 text-destructive'
                                    : isUrgent
                                        ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                                        : 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                            ]"
                        >
                            <Clock
                                class="size-3.5"
                                :class="{ 'animate-pulse': isUrgent, 'animate-spin': !isExpired && !isUrgent }"
                                :style="{ animationDuration: !isExpired && !isUrgent ? '12s' : undefined }"
                            />
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider opacity-70 leading-none">
                                {{ isExpired ? 'Status' : 'Trial Validity' }}
                            </span>
                            <span class="text-xs font-black truncate mt-0.5">
                                {{ isExpired ? 'Account Expired' : 'Expires In:' }}
                            </span>
                        </div>
                    </div>

                    <!-- Live Ticking Countdown -->
                    <div v-if="!isExpired" class="flex items-center gap-1 font-mono font-bold text-xs shrink-0">
                        <span
                            :class="[
                                'px-1.5 py-0.5 rounded-md border text-center min-w-[28px] shadow-xs bg-background/90',
                                isUrgent
                                    ? 'border-amber-500/40 text-amber-600 dark:text-amber-400'
                                    : 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400',
                            ]"
                            :title="`${daysLeft} Days`"
                        >
                            {{ daysLeft }}<span class="text-[9px] font-normal opacity-75">d</span>
                        </span>
                        <span class="font-bold opacity-40">:</span>
                        <span
                            :class="[
                                'px-1.5 py-0.5 rounded-md border text-center min-w-[28px] shadow-xs bg-background/90',
                                isUrgent
                                    ? 'border-amber-500/40 text-amber-600 dark:text-amber-400'
                                    : 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400',
                            ]"
                            :title="`${hoursLeft} Hours`"
                        >
                            {{ String(hoursLeft).padStart(2, '0') }}<span class="text-[9px] font-normal opacity-75">h</span>
                        </span>
                        <span class="font-bold opacity-40">:</span>
                        <span
                            :class="[
                                'px-1.5 py-0.5 rounded-md border text-center min-w-[28px] shadow-xs bg-background/90',
                                isUrgent
                                    ? 'border-amber-500/40 text-amber-600 dark:text-amber-400'
                                    : 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400',
                            ]"
                            :title="`${minutesLeft} Minutes`"
                        >
                            {{ String(minutesLeft).padStart(2, '0') }}<span class="text-[9px] font-normal opacity-75">m</span>
                        </span>
                        <span class="font-bold opacity-40">:</span>
                        <span
                            :class="[
                                'px-1.5 py-0.5 rounded-md border text-center min-w-[28px] shadow-xs bg-background/90',
                                isUrgent
                                    ? 'border-amber-500/40 text-amber-600 dark:text-amber-400'
                                    : 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400',
                            ]"
                            :title="`${secondsLeft} Seconds`"
                        >
                            {{ String(secondsLeft).padStart(2, '0') }}<span class="text-[9px] font-normal opacity-75">s</span>
                        </span>
                    </div>
                    <div
                        v-else
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-destructive text-destructive-foreground font-bold text-xs font-mono shrink-0 shadow-sm"
                    >
                        Expired
                    </div>
                </div>
            </div>

            <!-- Quick Staff Role Selector Presets (DEMO ONLY) -->
            <div v-if="isDemo" class="p-3.5 rounded-xl border border-border/70 bg-muted/30 space-y-2.5">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                        <KeyRound class="size-3" />
                        Fill Credentials for Role
                    </span>
                    <span class="text-[10px] text-muted-foreground font-mono">password: password</span>
                </div>

                <div :class="[
                    'grid gap-1.5',
                    availablePresets.length === 1 ? 'grid-cols-1' : availablePresets.length === 2 ? 'grid-cols-2' : 'grid-cols-3'
                ]">
                    <button
                        v-for="preset in availablePresets"
                        :key="preset.prefix"
                        type="button"
                        @click="applyPreset(preset.prefix)"
                        :class="[
                            'px-2.5 py-2 rounded-lg border text-xs font-semibold flex items-center justify-center gap-2 transition-all cursor-pointer',
                            selectedRole === preset.prefix
                                ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                : 'bg-background/80 hover:bg-background border-border text-foreground hover:border-primary/40',
                        ]"
                        :title="preset.description"
                    >
                        <component :is="preset.icon" class="size-3.5 shrink-0" />
                        <span class="truncate">
                            {{ preset.label }}
                            <template v-if="availablePresets.length === 1"> (Subscribed Persona)</template>
                        </span>
                    </button>
                </div>

                <!-- Active Role Chosen & Description Banner -->
                <div class="p-2.5 rounded-lg border border-primary/25 bg-primary/5 flex items-start gap-2.5 text-xs transition-all">
                    <div class="size-6 rounded-md bg-primary/10 border border-primary/25 flex items-center justify-center shrink-0 text-primary mt-0.5">
                        <component :is="activePreset.icon" class="size-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-foreground text-xs">{{ activePreset.role }}</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-primary/15 text-primary font-bold font-mono uppercase tracking-wide">
                                Active Role
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground leading-relaxed mt-0.5">
                            {{ activePreset.description }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Real Account Secure Operator Notice (REAL ONLY) -->
            <div v-else class="p-3 rounded-xl border border-border/80 bg-muted/20 flex items-start gap-2.5 text-xs text-muted-foreground">
                <Lock class="size-4 text-emerald-600 shrink-0 mt-0.5" />
                <p class="text-[11px] leading-relaxed">
                    Please sign in with your registered employee work email and terminal password. Access is audited and restricted to authorized personnel.
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label for="email" class="text-xs font-semibold">
                        {{ isDemo ? 'Demo Staff Email Address' : 'Staff Work Email Address' }}
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        :placeholder="isDemo ? `owner@${currentTenant.slug}.mdepos.co.ke` : 'employee@business.com'"
                        class="h-10 text-xs font-mono"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-xs font-semibold">Terminal Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-xs" tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="••••••••"
                            class="h-10 text-xs pr-10"
                        />
                        <button
                            type="button"
                            tabindex="-1"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground transition-colors"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        >
                            <EyeOff v-if="showPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-2 text-xs">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Remember terminal credentials</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    :class="[
                        'mt-1 w-full h-10 font-bold transition-all',
                        !isDemo && 'bg-emerald-600 hover:bg-emerald-700 text-white'
                    ]"
                    tabindex="4"
                    :disabled="form.processing || (isDemo && (isExpired || !!accountExpiredError))"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    <template v-else-if="isDemo">
                        <Sparkles class="size-4 mr-2" />
                        <span>Launch Demo as {{ activePreset.role }}</span>
                    </template>
                    <template v-else>
                        <Lock class="size-4 mr-2" />
                        <span>Sign In to {{ currentTenant.name }}</span>
                    </template>
                </Button>
            </div>

            <!-- Multi-Tenant Isolation Footer -->
            <div class="pt-2 border-t border-border/60">
                <div
                    :class="[
                        'flex items-center justify-center gap-2 p-2 rounded-xl text-[11px]',
                        isDemo
                            ? 'bg-amber-500/5 border border-amber-500/20 text-amber-900 dark:text-amber-200'
                            : 'bg-emerald-500/5 border border-emerald-500/20 text-emerald-950 dark:text-emerald-100'
                    ]"
                >
                    <component
                        :is="isDemo ? Sparkles : ShieldCheck"
                        :class="['size-3.5 shrink-0', isDemo ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400']"
                    />
                    <span v-if="isDemo">Demo Sandbox Partition • Test Mode</span>
                    <span v-else>Official Production Workspace • 256-bit TLS Encrypted</span>
                </div>
            </div>

            <!-- Super Admin Access Link -->
            <div class="pt-3 text-center">
                <Link
                    href="/super-admin/login"
                    class="text-[11px] font-mono text-muted-foreground/60 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors inline-flex items-center gap-1.5"
                >
                    <ShieldCheck class="size-3 text-emerald-500/70" />
                    <span>Platform Super Admin Gateway &rarr;</span>
                </Link>
            </div>
        </form>
    </AuthBase>
</template>
