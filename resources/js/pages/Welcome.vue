<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    Clock,
    Copy,
    Crown,
    Database,
    ExternalLink,
    Mail,
    MessageSquare,
    Package,
    Phone,
    PhoneCall,
    RotateCcw,
    Send,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Sparkles,
    Store,
    Upload,
    UserCheck,
    Users,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const page = usePage();

// If accessed on a tenant subdomain or with tenant context, lock out and redirect immediately
onMounted(() => {
    const tenant = (page.props as any)?.tenant;
    const isSubdomain = (page.props as any)?.isSubdomain;
    if (tenant || isSubdomain) {
        const targetUrl = tenant?.subdomain_url || '/login';
        window.location.href = targetUrl;
    }
});

interface DemoTenant {
    name: string;
    slug: string;
    business_type: string;
    badge_color: string;
    tagline: string;
}

interface DemoRole {
    id: string;
    title: string;
    description: string;
}

interface DemoPeriod {
    id: string;
    days: number;
    label: string;
    price: number;
}

interface DemoDataMode {
    id: 'mock' | 'own';
    label: string;
    badge: string;
    description: string;
}

const props = withDefaults(
    defineProps<{
        baseDomain?: string;
    }>(),
    {
        baseDomain: 'localhost',
    },
);

// Subdomain state (Disabled for self-service with waitlist notice)
const storeSubdomain = ref('');
const inputError = ref('');
const loadingTenant = ref<string | null>(null);

// Collapsible Demo Section State
const isDemoSectionOpen = ref(false);
onMounted(() => {
    isDemoSectionOpen.value = false;
});

// Waitlist Modal State
const showWaitlistModal = ref(false);
const isSubmittingWaitlist = ref(false);
const waitlistSubmitted = ref(false);
const waitlistForm = ref({
    businessName: '',
    fullName: '',
    email: '',
    phone: '',
    businessType: 'Supermarket',
});
const waitlistError = ref('');

// Contact Admin Modal & Callback State
const showContactAdminModal = ref(false);
const callbackRequested = ref(false);
const adminInquiryMessage = ref('');
const isSubmittingInquiry = ref(false);
const inquiryError = ref('');

// The Two Official Demo Businesses
const demoBusinesses: DemoTenant[] = [
    {
        name: 'Nairobi Fresh Mart Supermarket',
        slug: 'nairobi-fresh-mart',
        business_type: 'Supermarket',
        badge_color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
        tagline: 'High-Volume FMCG Retail & Multi-Branch POS',
    },
    {
        name: 'Metro Wholesale & Retail Distributors',
        slug: 'metro-wholesale-retail',
        business_type: 'Wholesale & Retail',
        badge_color: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
        tagline: 'Bulk B2B Distribution, Tiered Pricing & Walk-in Retail',
    },
];

// Available Roles
const demoRoles: DemoRole[] = [
    {
        id: 'Cashier',
        title: 'Lead Cashier (POS Terminal Register & Barcode Scanning)',
        description: 'Direct POS Register screen with quick sale billing and receipt printing.',
    },
    {
        id: 'Manager',
        title: 'Store Manager (Daily Operations, Shifts & Overrides)',
        description: 'Store operations view with inventory adjustments and shift reconciliation.',
    },
    {
        id: 'Owner',
        title: 'Business Owner / Admin (Full Back-Office & Financials)',
        description: 'Complete back-office executive analytics, profit margins, and branch control.',
    },
    {
        id: 'Inventory Manager',
        title: 'Inventory Manager (Stock Audits, Warehouse & Receiving)',
        description: 'Warehouse stock control, supplier orders receiving, and transfer management.',
    },
];

// Demo Period Options
const demoPeriods: DemoPeriod[] = [
    { id: '1day', days: 1, label: '1 Day (24-Hour Express Trial) â€” KES 50', price: 50 },
    { id: '3days', days: 3, label: '3 Days (Evaluation Sandbox) â€” KES 150', price: 150 },
    { id: '7days', days: 7, label: '7 Days (1 Week Team Pilot - Recommended) â€” KES 300', price: 300 },
    { id: '14days', days: 14, label: '14 Days (2 Weeks Extended Pilot) â€” KES 550', price: 550 },
    { id: '30days', days: 30, label: '30 Days (1 Month Enterprise Sandbox) â€” KES 990', price: 990 },
    { id: 'custom', days: 5, label: 'Custom Period...', price: 225 },
];

// Dropdown & Form State
const selectedDemoSlug = ref<string>('nairobi-fresh-mart');
const userEmail = ref('');
const userPhone = ref('');
const selectedRoleId = ref<string>('Owner');
const selectedPeriodId = ref<string>('7days');
const customDays = ref<number>(5);
const mpesaNumber = ref('');

// Demo Data Mode: 'mock' = pre-seeded demo data | 'own' = clean slate environment without mock records
const demoDataModes: DemoDataMode[] = [
    {
        id: 'mock',
        label: 'Demo with Mock Data (Pre-loaded with sample products, sales & customers)',
        badge: 'Mock Sample Data',
        description: 'Pre-loaded with sample FMCG inventory, customer records, and sales history for instant exploration.',
    },
    {
        id: 'own',
        label: 'Demo without Mock Data (Clean slate â€” start fresh with your own products & sales)',
        badge: 'Clean Slate Sandbox',
        description:
            'Clean slate environment without sample records. Perfect for testing setup with your own business inventory and staff from scratch.',
    },
];

const selectedDataMode = ref<'mock' | 'own'>('own');

// Validation Errors
const formErrors = ref({
    demo: '',
    email: '',
    phone: '',
    mpesa: '',
});

// STK & Notification State
const isProcessingStk = ref(false);
const stkPromptSent = ref(false);
const stkCountdown = ref(10);
let stkTimer: ReturnType<typeof setInterval> | null = null;
const isDemoActivated = ref(false);
const isCopied = ref(false);

const displayDomainSuffix = computed(() => {
    if (typeof window === 'undefined') return props.baseDomain || 'localhost:8000';
    const hostname = window.location.hostname;
    const port = window.location.port ? `:${window.location.port}` : '';
    if (hostname === '127.0.0.1' || hostname.endsWith('.localhost') || hostname === 'localhost') {
        return 'localhost' + port;
    }
    return hostname + port;
});

const currentSelectedDemo = computed(() => {
    return demoBusinesses.find((d) => d.slug === selectedDemoSlug.value) || demoBusinesses[0];
});

const currentSelectedRole = computed(() => {
    return demoRoles.find((r) => r.id === selectedRoleId.value) || demoRoles[0];
});

const currentSelectedDataMode = computed(() => {
    return demoDataModes.find((m) => m.id === selectedDataMode.value) || demoDataModes[0];
});

const currentEffectiveDays = computed(() => {
    if (selectedPeriodId.value === 'custom') {
        return Math.max(1, Math.min(90, Number(customDays.value) || 1));
    }
    const p = demoPeriods.find((item) => item.id === selectedPeriodId.value);
    return p ? p.days : 7;
});

const calculatedTotalPrice = computed(() => {
    if (selectedPeriodId.value === 'custom') {
        const days = currentEffectiveDays.value;
        if (days <= 3) return days * 50;
        if (days <= 7) return Math.round(days * 45);
        if (days <= 14) return Math.round(days * 40);
        return Math.round(days * 35);
    }
    const p = demoPeriods.find((item) => item.id === selectedPeriodId.value);
    return p ? p.price : 300;
});

const calculatedExpiryFormatted = computed(() => {
    const expiry = new Date();
    expiry.setDate(expiry.getDate() + currentEffectiveDays.value);
    return expiry.toLocaleDateString('en-KE', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
});

const effectiveDemoSlug = computed(() => {
    const baseSlug = currentSelectedDemo.value.slug;
    if (selectedDataMode.value === 'own') {
        return baseSlug === 'metro-wholesale-retail' ? 'metro-wholesale-clean' : 'nairobi-fresh-clean';
    }
    return baseSlug;
});

const generatedDemoSubdomain = computed(() => {
    return `${effectiveDemoSlug.value}.${displayDomainSuffix.value}`;
});

// Auto-sync M-Pesa number when user enters phone
watch(userPhone, (newVal) => {
    if (!mpesaNumber.value || mpesaNumber.value === newVal.slice(0, -1)) {
        mpesaNumber.value = newVal;
    }
});

// Navigate to existing store subdomain
const navigateToTenant = (slug: string) => {
    const cleanSlug = slug
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '');
    if (!cleanSlug) {
        inputError.value = 'Please enter a valid store subdomain identifier.';
        return;
    }

    inputError.value = '';
    if (typeof window === 'undefined') return;

    const protocol = window.location.protocol;
    const hostname = window.location.hostname;
    const port = window.location.port ? `:${window.location.port}` : '';

    if (hostname === '127.0.0.1' || hostname === 'localhost') {
        window.location.href = `/login?tenant=${cleanSlug}`;
        return;
    }

    let baseDomain = hostname;
    if (baseDomain.endsWith('.localhost') || baseDomain === 'localhost') {
        baseDomain = 'localhost';
    } else if (baseDomain.includes('.')) {
        const parts = baseDomain.split('.');
        if (parts.length > 2) {
            baseDomain = parts.slice(1).join('.');
        }
    }

    window.location.href = `${protocol}//${cleanSlug}.${baseDomain}${port}/login`;
};

const handleDomainSubmit = () => {
    navigateToTenant(storeSubdomain.value);
};

// Form Validation
const validateDemoForm = (): boolean => {
    let isValid = true;
    formErrors.value.demo = '';
    formErrors.value.email = '';
    formErrors.value.phone = '';
    formErrors.value.mpesa = '';

    if (!selectedDemoSlug.value) {
        formErrors.value.demo = 'Please select a business demo account.';
        isValid = false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!userEmail.value.trim() || !emailRegex.test(userEmail.value.trim())) {
        formErrors.value.email = 'Valid email is required.';
        isValid = false;
    }

    const cleanPhone = userPhone.value.trim().replace(/[\s-]/g, '');
    if (!cleanPhone || cleanPhone.length < 9) {
        formErrors.value.phone = 'Valid phone number is required.';
        isValid = false;
    }

    const cleanMpesa = mpesaNumber.value.trim().replace(/[\s-]/g, '');
    if (!cleanMpesa || cleanMpesa.length < 9) {
        formErrors.value.mpesa = 'Valid M-Pesa number is required for STK prompt.';
        isValid = false;
    }

    return isValid;
};

// M-Pesa STK Prompt Trigger
const triggerMpesaStkPush = () => {
    if (!validateDemoForm()) return;

    isDemoSectionOpen.value = true;
    isProcessingStk.value = true;
    stkPromptSent.value = true;
    stkCountdown.value = 8;

    if (stkTimer) clearInterval(stkTimer);

    stkTimer = setInterval(() => {
        if (stkCountdown.value > 1) {
            stkCountdown.value--;
        } else {
            completeDemoActivation();
        }
    }, 1000);
};

const completeDemoActivation = () => {
    if (stkTimer) {
        clearInterval(stkTimer);
        stkTimer = null;
    }
    isProcessingStk.value = false;
    stkPromptSent.value = false;
    isDemoActivated.value = true;
    isDemoSectionOpen.value = true;
};

const copySubdomainLink = async () => {
    if (typeof window === 'undefined') return;
    const protocol = window.location.protocol;
    const url = `${protocol}//${generatedDemoSubdomain.value}`;
    try {
        await navigator.clipboard.writeText(url);
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 3000);
    } catch {
        // fallback
    }
};

const launchDemoTerminal = (overrideSlug?: string, overrideRole?: string) => {
    const slug = typeof overrideSlug === 'string' && overrideSlug.trim() ? overrideSlug.trim() : effectiveDemoSlug.value;
    const role = typeof overrideRole === 'string' && overrideRole.trim() ? overrideRole.trim() : currentSelectedRole.value.id;
    const days = currentEffectiveDays.value;
    const dataMode = selectedDataMode.value;
    loadingTenant.value = `${slug}-${role}`;

    if (typeof window !== 'undefined') {
        const expiryMs = Date.now() + days * 24 * 60 * 60 * 1000;
        localStorage.setItem(`mde_demo_expiry_${slug}`, String(expiryMs));
        // Persist data mode for the demo session
        localStorage.setItem(`mde_demo_datamode_${slug}`, dataMode);
    }

    router.post(
        '/demo-login',
        { tenant: slug, role: role, days: days, data_mode: dataMode },
        {
            onError: (errors) => {
                console.error('Demo login error:', errors);
                const firstErr = Object.values(errors)[0];
                alert(typeof firstErr === 'string' ? firstErr : 'Could not access demo sandbox. Please try again.');
            },
            onFinish: () => {
                loadingTenant.value = null;
            },
        },
    );
};

const resetDemoForm = () => {
    isDemoActivated.value = false;
    stkPromptSent.value = false;
    isProcessingStk.value = false;
    if (stkTimer) clearInterval(stkTimer);
};

const submitAdminInquiry = () => {
    const cleanPhone = userPhone.value.trim();
    if (!cleanPhone || cleanPhone.length < 7) {
        inquiryError.value = 'Please enter a valid telephone number for setup callback.';
        return;
    }
    inquiryError.value = '';
    isSubmittingInquiry.value = true;

    router.post(
        '/admin-inquiry',
        {
            phone: cleanPhone,
            message: adminInquiryMessage.value || 'Setup callback requested from Welcome portal',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                callbackRequested.value = true;
                adminInquiryMessage.value = '';
            },
            onError: (errors) => {
                const firstErr = Object.values(errors)[0];
                inquiryError.value = typeof firstErr === 'string' ? firstErr : 'Could not submit callback request. Please try again.';
            },
            onFinish: () => {
                isSubmittingInquiry.value = false;
            },
        },
    );
};

const closeContactAdminModal = () => {
    showContactAdminModal.value = false;
    callbackRequested.value = false;
    inquiryError.value = '';
};

const handleWaitlistSubmit = () => {
    if (!waitlistForm.value.businessName.trim() || !waitlistForm.value.email.trim()) {
        waitlistError.value = 'Please provide both your business name and work email.';
        return;
    }
    waitlistError.value = '';
    isSubmittingWaitlist.value = true;

    router.post(
        '/waitlist',
        {
            business_name: waitlistForm.value.businessName,
            full_name: waitlistForm.value.fullName,
            email: waitlistForm.value.email,
            phone: waitlistForm.value.phone,
            business_type: waitlistForm.value.businessType,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                waitlistSubmitted.value = true;
            },
            onError: (errors) => {
                const firstErr = Object.values(errors)[0];
                waitlistError.value = typeof firstErr === 'string' ? firstErr : 'Could not submit waitlist registration. Please check your input.';
            },
            onFinish: () => {
                isSubmittingWaitlist.value = false;
            },
        },
    );
};

const resetWaitlistModal = () => {
    showWaitlistModal.value = false;
    waitlistSubmitted.value = false;
    waitlistForm.value = {
        businessName: '',
        fullName: '',
        email: '',
        phone: '',
        businessType: 'Supermarket',
    };
    waitlistError.value = '';
};

const scrollToDemos = () => {
    isDemoSectionOpen.value = true;
    if (typeof document !== 'undefined') {
        const elem = document.getElementById('demo-sandbox-section');
        if (elem) {
            elem.scrollIntoView({ behavior: 'smooth' });
        }
    }
};

const quickLaunchDemo = (slug: 'nairobi-fresh-mart' | 'metro-wholesale-retail', role: string = 'Cashier') => {
    selectedDemoSlug.value = slug;
    selectedRoleId.value = role;
    launchDemoTerminal(slug, role);
};
</script>

<template>
    <Head title="Client Store Login — MDE Enterprise POS" />

    <div
        class="relative flex min-h-screen flex-col overflow-hidden bg-background text-foreground selection:bg-primary selection:text-primary-foreground"
    >
        <!-- Subtle Ambient Background Glows -->
        <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 size-[650px] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 right-10 -z-10 size-[450px] rounded-full bg-emerald-500/5 blur-3xl"></div>

        <!-- Top Navigation Bar -->
        <header class="flex items-center justify-between border-b border-border/70 bg-card/60 px-4 py-3.5 backdrop-blur-md lg:px-8">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary text-base font-black text-primary-foreground shadow-sm">
                    MDE
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-extrabold leading-none tracking-tight text-foreground">MDE Point of Sale</span>
                        <Badge
                            variant="outline"
                            class="border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0 font-mono text-[10px] text-emerald-600 dark:text-emerald-400"
                        >
                            v1.0.1
                        </Badge>
                    </div>
                    <p class="text-[11px] text-muted-foreground">Multi-Tenant Store Terminal Portal</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                >
                    <span class="size-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                    Terminal Cluster Active
                </span>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="mx-auto flex w-full max-w-xl flex-1 flex-col items-center justify-center space-y-5 p-4 sm:p-6 lg:p-8">
            <!-- Header Title -->
            <div class="w-full space-y-1.5 text-center">
                <div
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-0.5 text-[11px] font-bold text-primary backdrop-blur-sm"
                >
                    <Sparkles class="size-3" />
                    <span>Instant Demo Access &amp; Merchant Terminal</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-foreground sm:text-3xl">Client Store Terminal Login</h1>
                <p class="text-xs text-muted-foreground sm:text-sm">Enter your merchant subdomain or toggle open a demo sandbox evaluation below.</p>
            </div>

            <!-- Unified Card Container -->
            <div class="w-full overflow-hidden rounded-2xl border border-border/60 bg-card shadow-2xl">
                <!-- Client Store Subdomain — visible when demo is closed -->
                <div v-show="!isDemoSectionOpen" class="space-y-3.5 border-t border-border/60 bg-card p-5 sm:p-6">
                    <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        <span class="flex items-center gap-1.5 text-foreground">
                            <Building2 class="size-3.5 text-primary" />
                            <span>Client Store Subdomain</span>
                        </span>
                        <Badge
                            variant="outline"
                            class="gap-1 border-amber-500/30 bg-amber-500/10 text-[10px] font-bold text-amber-600 dark:text-amber-400"
                        >
                            <Clock class="size-2.5" />
                            Coming Soon
                        </Badge>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="flex cursor-not-allowed items-center overflow-hidden rounded-xl border border-input/60 bg-muted/40 opacity-80 shadow-inner"
                        >
                            <div class="pl-3.5 pr-1 text-muted-foreground/60">
                                <Store class="size-4" />
                            </div>
                            <input
                                disabled
                                placeholder="your-store-subdomain"
                                class="flex-1 cursor-not-allowed select-none bg-transparent px-2.5 py-2.5 font-mono text-xs text-muted-foreground outline-none placeholder:text-muted-foreground/40"
                            />
                            <div
                                class="cursor-not-allowed select-none border-l border-border/60 bg-muted/70 px-3 py-2.5 font-mono text-xs text-muted-foreground/70"
                            >
                                .{{ displayDomainSuffix }}
                            </div>
                        </div>

                        <div class="space-y-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs">
                            <div class="flex items-start gap-2.5">
                                <Sparkles class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div class="space-y-1">
                                    <h4 class="text-xs font-bold leading-tight text-foreground">Self-Service Merchant Subdomains Launching Soon!</h4>
                                    <p class="text-[11px] leading-relaxed text-muted-foreground">
                                        Join our early-access waitlist for priority setup and launch discounts.
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                class="shadow-xs h-8 w-full cursor-pointer gap-1.5 bg-gradient-to-r from-amber-600 to-amber-500 text-xs font-bold text-white hover:from-amber-700 hover:to-amber-600"
                                @click="showWaitlistModal = true"
                            >
                                <Clock class="size-3.5" />
                                <span>Register for Waitlist</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Interactive Collapsible Toggle Header -->
                <button
                    id="demo-sandbox-section"
                    type="button"
                    @click="isDemoSectionOpen = !isDemoSectionOpen"
                    class="group flex w-full cursor-pointer select-none items-center justify-between border-b border-border/50 bg-muted/20 px-5 py-3.5 text-left transition-colors hover:bg-muted/40"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-6 shrink-0 items-center justify-center rounded-md border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        >
                            <Zap class="size-3.5" />
                        </div>
                        <div>
                            <span class="flex items-center gap-2 text-xs font-bold text-foreground transition-colors group-hover:text-primary">
                                <span>Try a Demo Sandbox Account</span>
                                <Badge
                                    variant="outline"
                                    class="border-amber-500/30 bg-amber-500/10 font-mono text-[9px] text-amber-600 dark:text-amber-400"
                                >
                                    Evaluation
                                </Badge>
                            </span>
                            <span class="block text-[10px] text-muted-foreground">
                                Pre-configured supermarket &amp; wholesale test environments
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="hidden text-[10px] font-medium text-muted-foreground sm:inline">
                            {{ isDemoSectionOpen ? 'Hide' : 'Configure Demo' }}
                        </span>
                        <ChevronDown
                            :class="[
                                'size-4 text-muted-foreground transition-transform duration-200',
                                isDemoSectionOpen ? 'rotate-180 text-primary' : '',
                            ]"
                        />
                    </div>
                </button>

                <!-- Demo Configuration Form (scrollable) -->
                <div
                    v-show="isDemoSectionOpen"
                    class="scrollbar-thin max-h-[60vh] space-y-4 overflow-y-auto overscroll-contain bg-card/50 p-5 sm:p-6"
                >
                    <!-- Prominent Non-Production Sandbox Disclaimer Notice -->
                    <div
                        class="flex items-start gap-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-950 shadow-sm dark:text-amber-200"
                    >
                        <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div class="space-y-0.5 leading-snug">
                            <span class="block text-[11px] font-bold text-amber-800 dark:text-amber-300">
                                Important Notice: Demo Account Sandbox Only
                            </span>
                            <p class="text-[10px] text-amber-900/85 dark:text-amber-300/85">
                                This demo account is an isolated evaluation environment preloaded with mock products and test data.
                                <strong>It cannot be used as a real live business account</strong> or for recording actual fiscal/commercial sales.
                                All mock transactions are temporary and periodically refreshed.
                            </p>
                        </div>
                    </div>

                    <!-- Notification of Active Demo Subdomain (When Activated) -->
                    <div v-if="isDemoActivated" class="space-y-4">
                        <div class="space-y-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-white">
                                    <CheckCircle2 class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-foreground">Demo Subdomain Provisioned & Ready</h3>
                                        <Badge class="bg-emerald-600 text-[9px] font-bold uppercase tracking-wider text-white"> Active </Badge>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Your access has been activated for
                                        <span class="font-bold text-foreground">{{ currentEffectiveDays }} day(s)</span> (expires
                                        {{ calculatedExpiryFormatted }}).
                                    </p>
                                </div>
                            </div>

                            <!-- Dedicated Subdomain Display Box -->
                            <div class="space-y-2 rounded-lg border border-emerald-500/20 bg-background/90 p-2.5">
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider">Access Subdomain URL:</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400"
                                        >KES {{ calculatedTotalPrice }} Paid</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between gap-2 rounded-md border border-border/70 bg-muted/40 p-1.5 font-mono text-xs"
                                >
                                    <span class="truncate pl-1 font-bold text-foreground">
                                        {{ generatedDemoSubdomain }}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-6 shrink-0 cursor-pointer gap-1 px-2 text-[11px] font-bold"
                                        @click="copySubdomainLink"
                                    >
                                        <component :is="isCopied ? Check : Copy" class="size-3 text-primary" />
                                        <span>{{ isCopied ? 'Copied!' : 'Copy' }}</span>
                                    </Button>
                                </div>

                                <div class="flex items-center justify-between pt-1 text-[11px] text-muted-foreground">
                                    <span
                                        >Role: <strong class="text-foreground">{{ currentSelectedRole.title }}</strong></span
                                    >
                                    <span
                                        >Account:
                                        <strong class="text-foreground"
                                            >{{ currentSelectedDemo.name }} ({{ selectedDataMode === 'mock' ? 'Mock Data' : 'Clean Slate' }})</strong
                                        ></span
                                    >
                                </div>
                            </div>

                            <!-- Direct 1-Click Launch Button -->
                            <div class="space-y-2 pt-1">
                                <Button
                                    type="button"
                                    @click="() => launchDemoTerminal()"
                                    :disabled="loadingTenant !== null"
                                    class="h-10 w-full cursor-pointer gap-2 bg-primary text-xs font-bold text-primary-foreground shadow-sm hover:bg-primary/90"
                                >
                                    <Sparkles class="size-3.5" />
                                    <span>
                                        {{
                                            loadingTenant ? 'Launching Terminal...' : `Launch Demo as ${currentSelectedRole.title.split(' ')[0]} Now`
                                        }}
                                    </span>
                                    <ArrowRight class="ml-auto size-3.5" />
                                </Button>

                                <div class="flex items-center justify-between pt-0.5 text-xs">
                                    <button
                                        type="button"
                                        @click="navigateToTenant(effectiveDemoSlug)"
                                        class="inline-flex cursor-pointer items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground"
                                    >
                                        <ExternalLink class="size-3" />
                                        <span>Visit Subdomain Login</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="resetDemoForm"
                                        class="inline-flex cursor-pointer items-center gap-1 text-[11px] text-primary hover:underline"
                                    >
                                        <RotateCcw class="size-3" />
                                        <span>Change / Test Another Demo</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Satisfied with Demo? Contact System Administrator Callout -->
                            <div class="space-y-2 rounded-xl border border-primary/20 bg-primary/[0.04] p-3.5 pt-3">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <ShieldCheck class="size-3.5 text-primary" />
                                        <span>Satisfied with your demo evaluation?</span>
                                    </span>
                                    <Badge variant="outline" class="border-primary/30 font-mono text-[9px] text-primary"> Live Production </Badge>
                                </div>
                                <p class="text-[11px] leading-relaxed text-muted-foreground">
                                    Ready for a real production store? Contact the System Administrator to provision your permanent database, custom
                                    store domain, and production tills.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="mt-1 h-8 w-full cursor-pointer gap-2 border-primary/40 text-xs font-bold text-primary hover:bg-primary/10"
                                    @click="showContactAdminModal = true"
                                >
                                    <PhoneCall class="size-3.5" />
                                    <span>Contact System Administrator</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- STK Push Waiting Prompt State -->
                    <div v-else-if="stkPromptSent" class="space-y-3.5 rounded-xl border border-emerald-500/40 bg-emerald-500/[0.04] p-5 text-center">
                        <div class="relative mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                            <div class="absolute inset-0 animate-ping rounded-full border-2 border-emerald-500 opacity-25"></div>
                            <Smartphone class="size-6" />
                        </div>

                        <div class="space-y-1">
                            <h4 class="text-xs font-bold text-foreground">M-Pesa STK Push Sent to {{ mpesaNumber }}</h4>
                            <p class="text-[11px] leading-relaxed text-muted-foreground">
                                Enter your <strong class="text-foreground">M-Pesa PIN</strong> on your mobile device to authorize payment of
                                <strong class="font-mono text-emerald-600 dark:text-emerald-400">KES {{ calculatedTotalPrice }}</strong
                                >.
                            </p>
                        </div>

                        <div class="flex items-center justify-center gap-1.5 font-mono text-[11px] text-muted-foreground">
                            <Clock class="size-3 animate-spin text-emerald-600" />
                            <span>Awaiting PIN prompt response ({{ stkCountdown }}s)...</span>
                        </div>

                        <div class="flex items-center justify-center gap-2 pt-2">
                            <Button
                                type="button"
                                size="sm"
                                @click="completeDemoActivation"
                                class="h-8 cursor-pointer bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700"
                            >
                                <CheckCircle2 class="mr-1 size-3" />
                                <span>Simulate PIN Confirmation</span>
                            </Button>
                            <Button type="button" variant="outline" size="sm" @click="stkPromptSent = false" class="h-8 cursor-pointer text-xs">
                                <span>Cancel</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Professional Demo Dropdowns & Details Form -->
                    <form v-else @submit.prevent="triggerMpesaStkPush" class="space-y-3.5">
                        <!-- 1. Demo Business Selection â€” Card Selector -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <label class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <Store class="size-3 text-primary" />
                                    <span>Select Business Demo</span>
                                </label>
                                <Badge variant="outline" :class="['px-1.5 py-0 text-[9px] font-bold uppercase', currentSelectedDemo.badge_color]">
                                    {{ currentSelectedDemo.business_type }}
                                </Badge>
                            </div>

                            <div class="space-y-1.5">
                                <button
                                    v-for="demo in demoBusinesses"
                                    :key="demo.slug"
                                    type="button"
                                    @click="selectedDemoSlug = demo.slug"
                                    :class="[
                                        'flex w-full cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-all',
                                        selectedDemoSlug === demo.slug
                                            ? 'border-primary bg-primary/[0.04] shadow-sm'
                                            : 'border-border/60 bg-background hover:border-border hover:bg-muted/30',
                                    ]"
                                >
                                    <div
                                        :class="[
                                            'flex size-7 shrink-0 items-center justify-center rounded-lg text-[10px] font-black transition-colors',
                                            selectedDemoSlug === demo.slug ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <Store class="size-3.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate text-xs font-bold text-foreground">{{ demo.name }}</span>
                                            <Badge variant="outline" :class="['shrink-0 px-1 py-0 text-[9px]', demo.badge_color]">
                                                {{ demo.business_type }}
                                            </Badge>
                                        </div>
                                        <p class="truncate font-mono text-[10px] text-muted-foreground">{{ demo.tagline }}</p>
                                    </div>
                                    <Check v-if="selectedDemoSlug === demo.slug" class="size-3.5 shrink-0 text-primary" />
                                </button>
                            </div>

                            <div class="flex items-center justify-between px-1 font-mono text-[10px] text-muted-foreground">
                                <span>Target: {{ generatedDemoSubdomain }}</span>
                                <span class="text-primary/80">{{ currentSelectedDemo.tagline }}</span>
                            </div>
                        </div>

                        <!-- 2. Demo Data Mode â€” Card Selector -->
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                <label class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <Database class="size-3 text-primary" />
                                    <span>Select Demo Data Mode</span>
                                </label>
                                <span
                                    class="inline-flex items-center gap-0.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-emerald-600 dark:text-emerald-400"
                                >
                                    <Check class="size-2.5" />
                                    Recommended: No Mock Data
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <button
                                    v-for="mode in demoDataModes"
                                    :key="mode.id"
                                    type="button"
                                    @click="selectedDataMode = mode.id"
                                    :class="[
                                        'flex w-full cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition-all',
                                        selectedDataMode === mode.id
                                            ? mode.id === 'own'
                                                ? 'border-emerald-500/50 bg-emerald-500/[0.05] shadow-sm'
                                                : 'border-primary bg-primary/[0.04] shadow-sm'
                                            : 'border-border/60 bg-background hover:border-border hover:bg-muted/30',
                                    ]"
                                >
                                    <div
                                        :class="[
                                            'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg transition-colors',
                                            selectedDataMode === mode.id
                                                ? mode.id === 'own'
                                                    ? 'bg-emerald-500 text-white'
                                                    : 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <Package v-if="mode.id === 'mock'" class="size-3.5" />
                                        <Upload v-else class="size-3.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-xs font-bold text-foreground">{{ mode.badge }}</span>
                                            <Badge
                                                v-if="mode.id === 'own'"
                                                variant="outline"
                                                class="shrink-0 border-emerald-500/30 bg-emerald-500/10 px-1 py-0 text-[9px] text-emerald-600 dark:text-emerald-400"
                                            >
                                                Recommended
                                            </Badge>
                                        </div>
                                        <p class="mt-0.5 text-[10px] leading-snug text-muted-foreground">{{ mode.description }}</p>
                                    </div>
                                    <Check
                                        v-if="selectedDataMode === mode.id"
                                        :class="[
                                            'mt-0.5 size-3.5 shrink-0',
                                            mode.id === 'own' ? 'text-emerald-600 dark:text-emerald-400' : 'text-primary',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- 3. Contact Fields (Email & Phone) -->
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="flex items-center gap-1 text-[11px] font-semibold text-foreground">
                                    <Mail class="size-3 text-muted-foreground" />
                                    <span>Valid Email</span>
                                </label>
                                <input
                                    v-model="userEmail"
                                    type="email"
                                    placeholder="name@company.com"
                                    class="w-full rounded-xl border border-input bg-background px-2.5 py-1.5 text-xs text-foreground outline-none transition-all placeholder:text-muted-foreground/60 focus:border-primary focus:ring-2 focus:ring-primary"
                                />
                                <p v-if="formErrors.email" class="text-[10px] font-medium text-destructive">
                                    {{ formErrors.email }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label class="flex items-center gap-1 text-[11px] font-semibold text-foreground">
                                    <Phone class="size-3 text-muted-foreground" />
                                    <span>Phone Number</span>
                                </label>
                                <input
                                    v-model="userPhone"
                                    type="tel"
                                    placeholder="0712 345 678"
                                    class="w-full rounded-xl border border-input bg-background px-2.5 py-1.5 text-xs text-foreground outline-none transition-all placeholder:text-muted-foreground/60 focus:border-primary focus:ring-2 focus:ring-primary"
                                />
                                <p v-if="formErrors.phone" class="text-[10px] font-medium text-destructive">
                                    {{ formErrors.phone }}
                                </p>
                            </div>
                        </div>

                        <!-- 3. User Persona â€” Card Selector -->
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                <label class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <Users class="size-3 text-primary" />
                                    <span>Select Demo User Persona</span>
                                </label>
                                <span
                                    class="inline-flex items-center gap-0.5 rounded-full border border-primary/30 bg-primary/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-primary"
                                >
                                    <Crown class="size-2.5" />
                                    Recommended: Owner
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <button
                                    v-for="role in demoRoles"
                                    :key="role.id"
                                    type="button"
                                    @click="selectedRoleId = role.id"
                                    :class="[
                                        'flex w-full cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition-all',
                                        selectedRoleId === role.id
                                            ? 'border-primary bg-primary/[0.04] shadow-sm'
                                            : 'border-border/60 bg-background hover:border-border hover:bg-muted/30',
                                    ]"
                                >
                                    <div
                                        :class="[
                                            'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg transition-colors',
                                            selectedRoleId === role.id ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <Crown v-if="role.id === 'Owner'" class="size-3.5" />
                                        <UserCheck v-else-if="role.id === 'Manager'" class="size-3.5" />
                                        <ShoppingCart v-else-if="role.id === 'Cashier'" class="size-3.5" />
                                        <Boxes v-else class="size-3.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-xs font-bold text-foreground">{{ role.title }}</span>
                                            <Badge
                                                v-if="role.id === 'Owner'"
                                                variant="outline"
                                                class="shrink-0 border-primary/30 bg-primary/10 px-1 py-0 text-[9px] text-primary"
                                            >
                                                Recommended
                                            </Badge>
                                        </div>
                                        <p class="mt-0.5 text-[10px] leading-snug text-muted-foreground">{{ role.description }}</p>
                                    </div>
                                    <Check v-if="selectedRoleId === role.id" class="mt-0.5 size-3.5 shrink-0 text-primary" />
                                </button>
                            </div>
                        </div>

                        <!-- 4. Demo Period Dropdown & Dynamic Price -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <label class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <Calendar class="size-3 text-primary" />
                                    <span>Demo Period</span>
                                </label>
                                <span class="font-mono text-[10px] text-muted-foreground"> Valid until {{ calculatedExpiryFormatted }} </span>
                            </div>

                            <div class="relative">
                                <select
                                    v-model="selectedPeriodId"
                                    class="w-full cursor-pointer appearance-none rounded-xl border border-input bg-background px-3 py-2 pr-8 text-xs text-foreground shadow-sm outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary"
                                >
                                    <option v-for="period in demoPeriods" :key="period.id" :value="period.id">
                                        {{ period.label }}
                                    </option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            </div>

                            <!-- Custom days inline row -->
                            <div v-if="selectedPeriodId === 'custom'" class="flex items-center gap-2 px-1 pt-1">
                                <span class="text-[11px] text-muted-foreground">Enter number of days:</span>
                                <input
                                    v-model.number="customDays"
                                    type="number"
                                    min="1"
                                    max="90"
                                    class="w-16 rounded border border-input bg-background px-2 py-1 font-mono text-xs text-foreground"
                                />
                                <span class="text-[11px] text-muted-foreground">days</span>
                            </div>
                        </div>

                        <!-- 5. Compact Price & Summary Strip -->
                        <div class="flex items-center justify-between rounded-xl border border-primary/20 bg-primary/[0.04] p-2.5 text-xs">
                            <div>
                                <span class="block text-[10px] text-muted-foreground">Calculated Demo Fee ({{ currentEffectiveDays }} Days):</span>
                                <span class="font-bold text-foreground">{{ currentSelectedDemo.name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-mono text-base font-black tracking-tight text-primary"> KES {{ calculatedTotalPrice }} </span>
                            </div>
                        </div>

                        <!-- 6. M-Pesa Prompt Mobile Number -->
                        <div class="space-y-1">
                            <label class="flex items-center justify-between text-[11px] font-semibold text-foreground">
                                <span class="flex items-center gap-1">
                                    <Smartphone class="size-3 text-emerald-600 dark:text-emerald-400" />
                                    <span>M-Pesa Number for Prompt</span>
                                </span>
                                <span class="font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400">M-PESA STK</span>
                            </label>

                            <div
                                class="flex items-center overflow-hidden rounded-xl border border-input bg-background shadow-sm transition-all focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500"
                            >
                                <div class="pl-3 pr-2 text-xs font-bold text-emerald-600">KE</div>
                                <input
                                    v-model="mpesaNumber"
                                    type="tel"
                                    placeholder="07XX XXX XXX or 254..."
                                    class="flex-1 bg-transparent px-2 py-2 font-mono text-xs text-foreground outline-none placeholder:text-muted-foreground/60"
                                />
                                <div
                                    class="mr-1.5 rounded border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-[9px] font-bold uppercase text-emerald-600"
                                >
                                    STK Push
                                </div>
                            </div>
                            <p v-if="formErrors.mpesa" class="text-[10px] font-medium text-destructive">
                                {{ formErrors.mpesa }}
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="mt-1 h-10 w-full cursor-pointer gap-2 bg-emerald-600 text-xs font-bold text-white shadow-sm hover:bg-emerald-700"
                        >
                            <Smartphone class="size-3.5" />
                            <span>Prompt M-Pesa STK Push (KES {{ calculatedTotalPrice }})</span>
                        </Button>

                        <!-- Satisfied with Demo Option to Contact Admin -->
                        <div class="flex items-center justify-between border-t border-border/50 pt-2 text-[11px] text-muted-foreground">
                            <span>Satisfied with testing? Ready for a live account?</span>
                            <button
                                type="button"
                                @click="showContactAdminModal = true"
                                class="inline-flex cursor-pointer items-center gap-1 font-semibold text-primary hover:underline"
                            >
                                <span>Contact System Admin</span>
                                <ArrowRight class="size-3" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <!-- Interactive Contact System Administrator Modal -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showContactAdminModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
                @click.self="showContactAdminModal = false"
            >
                <div class="relative w-full max-w-md space-y-4 rounded-2xl border border-border/80 bg-card p-5 shadow-2xl sm:p-6">
                    <!-- Close button -->
                    <button
                        type="button"
                        @click="showContactAdminModal = false"
                        class="absolute right-4 top-4 flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>

                    <!-- Header -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                            <ShieldCheck class="size-4" />
                            <span>Central Production Onboarding</span>
                        </div>
                        <h3 class="text-lg font-bold text-foreground">Contact System Administrator</h3>
                        <p class="text-xs leading-relaxed text-muted-foreground">
                            Ready to move from evaluation to a permanent merchant account? Contact our systems administrator to activate your live
                            store.
                        </p>
                    </div>

                    <!-- Direct Contact Channels -->
                    <div class="space-y-2">
                        <a
                            href="https://wa.me/254712345678?text=Hello%20MDE%20Admin,%20I%20have%20evaluated%20the%20demo%20and%20am%20satisfied.%20I%20would%20like%20to%20set%20up%20a%20live%20production%20account."
                            target="_blank"
                            rel="noopener"
                            class="flex cursor-pointer items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3 text-xs font-medium transition-colors hover:bg-emerald-500/10"
                        >
                            <div class="flex items-center gap-2.5 text-foreground">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-500 text-white">
                                    <MessageSquare class="size-4" />
                                </div>
                                <div>
                                    <span class="block font-bold text-emerald-700 dark:text-emerald-400">Direct WhatsApp Support</span>
                                    <span class="text-[11px] text-muted-foreground">Instant chat with lead administrator</span>
                                </div>
                            </div>
                            <ArrowRight class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        </a>

                        <a
                            href="tel:+254712345678"
                            class="flex cursor-pointer items-center justify-between rounded-xl border border-border/80 bg-background p-3 text-xs font-medium transition-colors hover:bg-muted/40"
                        >
                            <div class="flex items-center gap-2.5 text-foreground">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <PhoneCall class="size-4" />
                                </div>
                                <div>
                                    <span class="block font-bold">Telephone Admin Hotline</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">+254 712 345 678</span>
                                </div>
                            </div>
                            <ArrowRight class="size-3.5 text-muted-foreground" />
                        </a>

                        <a
                            href="mailto:admin@mdepos.co.ke?subject=Production%20Store%20Setup%20Request"
                            class="flex cursor-pointer items-center justify-between rounded-xl border border-border/80 bg-background p-3 text-xs font-medium transition-colors hover:bg-muted/40"
                        >
                            <div class="flex items-center gap-2.5 text-foreground">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    <Mail class="size-4" />
                                </div>
                                <div>
                                    <span class="block font-bold">Official Administration Email</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">admin@mdepos.co.ke</span>
                                </div>
                            </div>
                            <ArrowRight class="size-3.5 text-muted-foreground" />
                        </a>
                    </div>

                    <!-- Callback Request Form -->
                    <div class="space-y-2.5 border-t border-border/70 pt-2">
                        <div
                            v-if="callbackRequested"
                            class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-300"
                        >
                            <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                            <span
                                >Callback request sent! Our System Administrator will contact you at
                                {{ userPhone || 'your contact number' }} shortly.</span
                            >
                        </div>

                        <div v-else class="space-y-2">
                            <label class="block text-[11px] font-semibold text-foreground"> Or Request an Administrator Callback: </label>
                            <div class="flex gap-2">
                                <input
                                    v-model="userPhone"
                                    type="tel"
                                    placeholder="Your phone number"
                                    class="flex-1 rounded-xl border border-input bg-background px-2.5 py-1.5 font-mono text-xs text-foreground outline-none placeholder:text-muted-foreground/60 focus:border-primary focus:ring-2 focus:ring-primary"
                                    @keydown.enter.prevent="submitAdminInquiry"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-8 shrink-0 cursor-pointer gap-1.5 text-xs font-bold"
                                    :loading="isSubmittingInquiry"
                                    @click="submitAdminInquiry"
                                >
                                    <Send class="size-3" />
                                    <span>Request Setup</span>
                                </Button>
                            </div>
                            <p v-if="inquiryError" class="text-[10px] font-medium text-destructive">
                                {{ inquiryError }}
                            </p>
                        </div>
                    </div>

                    <!-- Dismiss button -->
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="w-full cursor-pointer text-xs text-muted-foreground"
                        @click="closeContactAdminModal"
                    >
                        <span>Close Window</span>
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Interactive Early Access Waitlist Modal -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showWaitlistModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
                @click.self="resetWaitlistModal"
            >
                <div class="relative w-full max-w-md space-y-4 rounded-2xl border border-border/80 bg-card p-5 shadow-2xl sm:p-6">
                    <!-- Close button -->
                    <button
                        type="button"
                        @click="resetWaitlistModal"
                        class="absolute right-4 top-4 flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>

                    <!-- Success State -->
                    <div v-if="waitlistSubmitted" class="space-y-3 py-4 text-center">
                        <div
                            class="mx-auto flex size-12 items-center justify-center rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-600"
                        >
                            <CheckCircle2 class="size-6" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-bold text-foreground">You're on the Priority Waitlist!</h3>
                            <p class="mx-auto max-w-xs text-xs text-muted-foreground">
                                Thank you, <strong class="text-foreground">{{ waitlistForm.fullName || waitlistForm.businessName }}</strong
                                >. We'll email you at <span class="font-mono text-foreground">{{ waitlistForm.email }}</span> the moment self-service
                                merchant onboarding goes live.
                            </p>
                        </div>
                        <div class="space-y-1.5 rounded-xl border border-primary/20 bg-primary/5 p-3 text-left text-xs">
                            <span class="flex items-center gap-1.5 font-bold text-foreground">
                                <Zap class="size-3.5 text-primary" />
                                <span>Try out our live demos while you wait!</span>
                            </span>
                            <p class="text-[11px] text-muted-foreground">
                                Explore full POS functionality, barcode scanning, shift rosters, and stock intelligence right now.
                            </p>
                        </div>
                        <div class="pt-2">
                            <Button
                                type="button"
                                size="sm"
                                class="w-full bg-primary font-bold text-primary-foreground"
                                @click="
                                    () => {
                                        resetWaitlistModal();
                                        scrollToDemos();
                                    }
                                "
                            >
                                <Sparkles class="mr-1 size-3.5" />
                                Explore Interactive Demos Now
                            </Button>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <div v-else class="space-y-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                <Clock class="size-4" />
                                <span>Priority Onboarding</span>
                            </div>
                            <h3 class="text-lg font-bold text-foreground">Register for Early Access Waitlist</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground">
                                Be among the first merchants to receive a custom subdomain, dedicated database, and automated onboarding.
                            </p>
                        </div>

                        <form @submit.prevent="handleWaitlistSubmit" class="space-y-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-foreground">Business / Store Name *</label>
                                <input
                                    v-model="waitlistForm.businessName"
                                    type="text"
                                    required
                                    placeholder="e.g. Highlands Supermarket Ltd"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-foreground">Contact Person Name</label>
                                    <input
                                        v-model="waitlistForm.fullName"
                                        type="text"
                                        placeholder="e.g. Sarah Kimani"
                                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-foreground">Business Type</label>
                                    <select
                                        v-model="waitlistForm.businessType"
                                        class="w-full cursor-pointer rounded-xl border border-input bg-background px-2.5 py-2 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="Supermarket">Supermarket / FMCG</option>
                                        <option value="Wholesale">Wholesale & Distribution</option>
                                        <option value="Pharmacy">Pharmacy & Chemist</option>
                                        <option value="Hardware">Hardware & Building</option>
                                        <option value="Electronics">Electronics & Mobile</option>
                                        <option value="General Retail">General Retail / Mini-Mart</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-foreground">Work Email *</label>
                                    <input
                                        v-model="waitlistForm.email"
                                        type="email"
                                        required
                                        placeholder="admin@mystore.co.ke"
                                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-foreground">Phone / WhatsApp</label>
                                    <input
                                        v-model="waitlistForm.phone"
                                        type="tel"
                                        placeholder="+254 7XX XXX XXX"
                                        class="w-full rounded-xl border border-input bg-background px-3 py-2 font-mono text-xs text-foreground outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                            </div>

                            <p v-if="waitlistError" class="text-[10px] font-medium text-destructive">
                                {{ waitlistError }}
                            </p>

                            <div class="flex items-center justify-end gap-2 pt-2">
                                <Button type="button" variant="ghost" size="sm" @click="resetWaitlistModal"> Cancel </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-amber-600 font-bold text-white hover:bg-amber-700"
                                    :loading="isSubmittingWaitlist"
                                >
                                    Join Priority Waitlist
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Minimal Footer -->
        <footer class="border-t border-border/70 bg-card/60 px-4 py-4 text-center text-xs text-muted-foreground">
            <p>© 2026 MDE Software Systems. Multi-Tenant Retail Point of Sale & Inventory Platform.</p>
        </footer>
    </div>
</template>
