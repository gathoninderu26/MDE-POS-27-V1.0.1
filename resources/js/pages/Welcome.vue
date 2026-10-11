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
    FileSpreadsheet,
    Lock,
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
    Truck,
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
        description: 'Clean slate environment without sample records. Perfect for testing setup with your own business inventory and staff from scratch.',
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
    const cleanSlug = slug.trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
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
    const slug = (typeof overrideSlug === 'string' && overrideSlug.trim()) ? overrideSlug.trim() : effectiveDemoSlug.value;
    const role = (typeof overrideRole === 'string' && overrideRole.trim()) ? overrideRole.trim() : currentSelectedRole.value.id;
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

    <div class="min-h-screen bg-background text-foreground flex flex-col selection:bg-primary selection:text-primary-foreground relative overflow-hidden">
        <!-- Subtle Ambient Background Glows -->
        <div class="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 size-[650px] rounded-full bg-primary/10 blur-3xl -z-10"></div>
        <div class="pointer-events-none absolute bottom-0 right-10 size-[450px] rounded-full bg-emerald-500/5 blur-3xl -z-10"></div>

        <!-- Top Navigation Bar -->
        <header class="border-b border-border/70 bg-card/60 backdrop-blur-md px-4 lg:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground font-black text-base shadow-sm">
                    MDE
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-extrabold tracking-tight leading-none text-foreground">MDE Point of Sale</span>
                        <Badge variant="outline" class="text-[10px] px-1.5 py-0 font-mono text-emerald-600 dark:text-emerald-400 border-emerald-500/30 bg-emerald-500/10">
                            v1.0.1
                        </Badge>
                    </div>
                    <p class="text-[11px] text-muted-foreground">Multi-Tenant Store Terminal Portal</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-medium text-[11px]">
                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Terminal Cluster Active
                </span>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8 max-w-xl mx-auto w-full space-y-5">
            <!-- Header Title -->
            <div class="text-center space-y-1.5 w-full">
                <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-[11px] font-bold backdrop-blur-sm">
                    <Sparkles class="size-3" />
                    <span>Instant Demo Access &amp; Merchant Terminal</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-foreground">
                    Client Store Terminal Login
                </h1>
                <p class="text-xs sm:text-sm text-muted-foreground">
                    Enter your merchant subdomain or toggle open a demo sandbox evaluation below.
                </p>
            </div>

            <!-- Unified Card Container -->
            <div class="w-full rounded-2xl border border-border/60 bg-card shadow-2xl overflow-hidden">
                <!-- Client Store Subdomain — visible when demo is closed -->
                <div v-show="!isDemoSectionOpen" class="p-5 sm:p-6 space-y-3.5 bg-card border-t border-border/60">
                    <div class="flex items-center justify-between text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        <span class="flex items-center gap-1.5 text-foreground">
                            <Building2 class="size-3.5 text-primary" />
                            <span>Client Store Subdomain</span>
                        </span>
                        <Badge variant="outline" class="text-[10px] font-bold text-amber-600 dark:text-amber-400 border-amber-500/30 bg-amber-500/10 gap-1">
                            <Clock class="size-2.5" />
                            Coming Soon
                        </Badge>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center rounded-xl border border-input/60 bg-muted/40 shadow-inner overflow-hidden opacity-80 cursor-not-allowed">
                            <div class="pl-3.5 pr-1 text-muted-foreground/60">
                                <Store class="size-4" />
                            </div>
                            <input
                                disabled
                                placeholder="your-store-subdomain"
                                class="flex-1 px-2.5 py-2.5 text-xs bg-transparent outline-none font-mono text-muted-foreground cursor-not-allowed placeholder:text-muted-foreground/40 select-none"
                            />
                            <div class="px-3 py-2.5 bg-muted/70 border-l border-border/60 text-xs text-muted-foreground/70 font-mono select-none cursor-not-allowed">
                                .{{ displayDomainSuffix }}
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl border border-amber-500/30 bg-amber-500/10 text-xs space-y-2.5">
                            <div class="flex items-start gap-2.5">
                                <Sparkles class="size-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                                <div class="space-y-1">
                                    <h4 class="font-bold text-foreground text-xs leading-tight">Self-Service Merchant Subdomains Launching Soon!</h4>
                                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                                        Join our early-access waitlist for priority setup and launch discounts.
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                class="w-full h-8 text-xs font-bold gap-1.5 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-700 hover:to-amber-600 text-white shadow-xs cursor-pointer"
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
                    class="w-full flex items-center justify-between px-5 py-3.5 bg-muted/20 hover:bg-muted/40 border-b border-border/50 text-left cursor-pointer transition-colors select-none group"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="size-6 rounded-md bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <Zap class="size-3.5" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-2">
                                <span>Try a Demo Sandbox Account</span>
                                <Badge variant="outline" class="text-[9px] font-mono text-amber-600 dark:text-amber-400 border-amber-500/30 bg-amber-500/10">
                                    Evaluation
                                </Badge>
                            </span>
                            <span class="text-[10px] text-muted-foreground block">
                                Pre-configured supermarket &amp; wholesale test environments
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-medium text-muted-foreground hidden sm:inline">
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
                <div v-show="isDemoSectionOpen" class="p-5 sm:p-6 bg-card/50 space-y-4 max-h-[60vh] overflow-y-auto overscroll-contain scrollbar-thin">



                    <!-- Prominent Non-Production Sandbox Disclaimer Notice -->
                    <div class="p-3 rounded-xl border border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-200 text-xs flex items-start gap-2.5 shadow-sm">
                        <AlertTriangle class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div class="space-y-0.5 leading-snug">
                            <span class="font-bold text-[11px] block text-amber-800 dark:text-amber-300">
                                Important Notice: Demo Account Sandbox Only
                            </span>
                            <p class="text-[10px] text-amber-900/85 dark:text-amber-300/85">
                                This demo account is an isolated evaluation environment preloaded with mock products and test data. <strong>It cannot be used as a real live business account</strong> or for recording actual fiscal/commercial sales. All mock transactions are temporary and periodically refreshed.
                            </p>
                        </div>
                    </div>

                    <!-- Notification of Active Demo Subdomain (When Activated) -->
                    <div v-if="isDemoActivated" class="space-y-4">
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="size-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center shrink-0">
                                    <CheckCircle2 class="size-5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-foreground">
                                            Demo Subdomain Provisioned & Ready
                                        </h3>
                                        <Badge class="bg-emerald-600 text-white text-[9px] uppercase tracking-wider font-bold">
                                            Active
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        Your access has been activated for <span class="font-bold text-foreground">{{ currentEffectiveDays }} day(s)</span> (expires {{ calculatedExpiryFormatted }}).
                                    </p>
                                </div>
                            </div>

                            <!-- Dedicated Subdomain Display Box -->
                            <div class="p-2.5 rounded-lg bg-background/90 border border-emerald-500/20 space-y-2">
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span class="font-semibold uppercase tracking-wider text-[10px]">Access Subdomain URL:</span>
                                    <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">KES {{ calculatedTotalPrice }} Paid</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 p-1.5 rounded-md bg-muted/40 border border-border/70 font-mono text-xs">
                                    <span class="text-foreground font-bold truncate pl-1">
                                        {{ generatedDemoSubdomain }}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-6 px-2 text-[11px] font-bold gap-1 cursor-pointer shrink-0"
                                        @click="copySubdomainLink"
                                    >
                                        <component :is="isCopied ? Check : Copy" class="size-3 text-primary" />
                                        <span>{{ isCopied ? 'Copied!' : 'Copy' }}</span>
                                    </Button>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1">
                                    <span>Role: <strong class="text-foreground">{{ currentSelectedRole.title }}</strong></span>
                                    <span>Account: <strong class="text-foreground">{{ currentSelectedDemo.name }} ({{ selectedDataMode === 'mock' ? 'Mock Data' : 'Clean Slate' }})</strong></span>
                                </div>
                            </div>

                            <!-- Direct 1-Click Launch Button -->
                            <div class="space-y-2 pt-1">
                                <Button
                                    type="button"
                                    @click="() => launchDemoTerminal()"
                                    :disabled="loadingTenant !== null"
                                    class="w-full h-10 text-xs font-bold gap-2 cursor-pointer bg-primary hover:bg-primary/90 text-primary-foreground shadow-sm"
                                >
                                    <Sparkles class="size-3.5" />
                                    <span>
                                        {{ loadingTenant ? 'Launching Terminal...' : `Launch Demo as ${currentSelectedRole.title.split(' ')[0]} Now` }}
                                    </span>
                                    <ArrowRight class="size-3.5 ml-auto" />
                                </Button>

                                <div class="flex items-center justify-between text-xs pt-0.5">
                                    <button
                                        type="button"
                                        @click="navigateToTenant(effectiveDemoSlug)"
                                        class="text-muted-foreground hover:text-foreground text-[11px] inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <ExternalLink class="size-3" />
                                        <span>Visit Subdomain Login</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="resetDemoForm"
                                        class="text-primary hover:underline text-[11px] inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <RotateCcw class="size-3" />
                                        <span>Change / Test Another Demo</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Satisfied with Demo? Contact System Administrator Callout -->
                            <div class="rounded-xl border border-primary/20 bg-primary/[0.04] p-3.5 space-y-2 pt-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                        <ShieldCheck class="size-3.5 text-primary" />
                                        <span>Satisfied with your demo evaluation?</span>
                                    </span>
                                    <Badge variant="outline" class="text-[9px] border-primary/30 text-primary font-mono">
                                        Live Production
                                    </Badge>
                                </div>
                                <p class="text-[11px] text-muted-foreground leading-relaxed">
                                    Ready for a real production store? Contact the System Administrator to provision your permanent database, custom store domain, and production tills.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="w-full h-8 text-xs font-bold gap-2 border-primary/40 hover:bg-primary/10 text-primary cursor-pointer mt-1"
                                    @click="showContactAdminModal = true"
                                >
                                    <PhoneCall class="size-3.5" />
                                    <span>Contact System Administrator</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- STK Push Waiting Prompt State -->
                    <div v-else-if="stkPromptSent" class="rounded-xl border border-emerald-500/40 bg-emerald-500/[0.04] p-5 text-center space-y-3.5">
                        <div class="relative mx-auto size-12 flex items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                            <div class="absolute inset-0 rounded-full border-2 border-emerald-500 animate-ping opacity-25"></div>
                            <Smartphone class="size-6" />
                        </div>

                        <div class="space-y-1">
                            <h4 class="text-xs font-bold text-foreground">
                                M-Pesa STK Push Sent to {{ mpesaNumber }}
                            </h4>
                            <p class="text-[11px] text-muted-foreground leading-relaxed">
                                Enter your <strong class="text-foreground">M-Pesa PIN</strong> on your mobile device to authorize payment of <strong class="text-emerald-600 dark:text-emerald-400 font-mono">KES {{ calculatedTotalPrice }}</strong>.
                            </p>
                        </div>

                        <div class="flex items-center justify-center gap-1.5 text-[11px] font-mono text-muted-foreground">
                            <Clock class="size-3 animate-spin text-emerald-600" />
                            <span>Awaiting PIN prompt response ({{ stkCountdown }}s)...</span>
                        </div>

                        <div class="pt-2 flex items-center justify-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                @click="completeDemoActivation"
                                class="h-8 px-3 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer"
                            >
                                <CheckCircle2 class="size-3 mr-1" />
                                <span>Simulate PIN Confirmation</span>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="stkPromptSent = false"
                                class="h-8 text-xs cursor-pointer"
                            >
                                <span>Cancel</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Professional Demo Dropdowns & Details Form -->
                    <form v-else @submit.prevent="triggerMpesaStkPush" class="space-y-3.5">

                        <!-- 1. Demo Business Selection â€” Card Selector -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                    <Store class="size-3 text-primary" />
                                    <span>Select Business Demo</span>
                                </label>
                                <Badge variant="outline" :class="['text-[9px] font-bold uppercase px-1.5 py-0', currentSelectedDemo.badge_color]">
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
                                        'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border text-left cursor-pointer transition-all',
                                        selectedDemoSlug === demo.slug
                                            ? 'border-primary bg-primary/[0.04] shadow-sm'
                                            : 'border-border/60 bg-background hover:border-border hover:bg-muted/30',
                                    ]"
                                >
                                    <div :class="[
                                        'size-7 rounded-lg flex items-center justify-center shrink-0 text-[10px] font-black transition-colors',
                                        selectedDemoSlug === demo.slug
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    ]">
                                        <Store class="size-3.5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold text-foreground truncate">{{ demo.name }}</span>
                                            <Badge variant="outline" :class="['text-[9px] px-1 py-0 shrink-0', demo.badge_color]">
                                                {{ demo.business_type }}
                                            </Badge>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground truncate font-mono">{{ demo.tagline }}</p>
                                    </div>
                                    <Check
                                        v-if="selectedDemoSlug === demo.slug"
                                        class="size-3.5 text-primary shrink-0"
                                    />
                                </button>
                            </div>

                            <div class="flex items-center justify-between text-[10px] text-muted-foreground px-1 font-mono">
                                <span>Target: {{ generatedDemoSubdomain }}</span>
                                <span class="text-primary/80">{{ currentSelectedDemo.tagline }}</span>
                            </div>
                        </div>

                        <!-- 2. Demo Data Mode â€” Card Selector -->
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                    <Database class="size-3 text-primary" />
                                    <span>Select Demo Data Mode</span>
                                </label>
                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
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
                                        'w-full flex items-start gap-3 px-3 py-2.5 rounded-xl border text-left cursor-pointer transition-all',
                                        selectedDataMode === mode.id
                                            ? mode.id === 'own'
                                                ? 'border-emerald-500/50 bg-emerald-500/[0.05] shadow-sm'
                                                : 'border-primary bg-primary/[0.04] shadow-sm'
                                            : 'border-border/60 bg-background hover:border-border hover:bg-muted/30',
                                    ]"
                                >
                                    <div :class="[
                                        'size-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 transition-colors',
                                        selectedDataMode === mode.id
                                            ? mode.id === 'own'
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    ]">
                                        <Package v-if="mode.id === 'mock'" class="size-3.5" />
                                        <Upload v-else class="size-3.5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-foreground">{{ mode.badge }}</span>
                                            <Badge
                                                v-if="mode.id === 'own'"
                                                variant="outline"
                                                class="text-[9px] px-1 py-0 shrink-0 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30"
                                            >
                                                Recommended
                                            </Badge>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground leading-snug mt-0.5">{{ mode.description }}</p>
                                    </div>
                                    <Check
                                        v-if="selectedDataMode === mode.id"
                                        :class="[
                                            'size-3.5 shrink-0 mt-0.5',
                                            mode.id === 'own' ? 'text-emerald-600 dark:text-emerald-400' : 'text-primary'
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- 3. Contact Fields (Email & Phone) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-foreground flex items-center gap-1">
                                    <Mail class="size-3 text-muted-foreground" />
                                    <span>Valid Email</span>
                                </label>
                                <input
                                    v-model="userEmail"
                                    type="email"
                                    placeholder="name@company.com"
                                    class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-input bg-background outline-none focus:ring-2 focus:ring-primary focus:border-primary text-foreground placeholder:text-muted-foreground/60 transition-all"
                                />
                                <p v-if="formErrors.email" class="text-[10px] text-destructive font-medium">
                                    {{ formErrors.email }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-foreground flex items-center gap-1">
                                    <Phone class="size-3 text-muted-foreground" />
                                    <span>Phone Number</span>
                                </label>
                                <input
                                    v-model="userPhone"
                                    type="tel"
                                    placeholder="0712 345 678"
                                    class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-input bg-background outline-none focus:ring-2 focus:ring-primary focus:border-primary text-foreground placeholder:text-muted-foreground/60 transition-all"
                                />
                                <p v-if="formErrors.phone" class="text-[10px] text-destructive font-medium">
                                    {{ formErrors.phone }}
                                </p>
                            </div>
                        </div>

                        <!-- 3. User Persona â€” Card Selector -->
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                    <Users class="size-3 text-primary" />
                                    <span>Select Demo User Persona</span>
                                </label>
                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/30">
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
                                        'w-full flex items-start gap-3 px-3 py-2.5 rounded-xl border text-left cursor-pointer transition-all',
                                        selectedRoleId === role.id
                                            ? 'border-primary bg-primary/[0.04] shadow-sm'
                                            : 'border-border/60 bg-background hover:border-border hover:bg-muted/30',
                                    ]"
                                >
                                    <div :class="[
                                        'size-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 transition-colors',
                                        selectedRoleId === role.id
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    ]">
                                        <Crown v-if="role.id === 'Owner'" class="size-3.5" />
                                        <UserCheck v-else-if="role.id === 'Manager'" class="size-3.5" />
                                        <ShoppingCart v-else-if="role.id === 'Cashier'" class="size-3.5" />
                                        <Boxes v-else class="size-3.5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-foreground">{{ role.title }}</span>
                                            <Badge
                                                v-if="role.id === 'Owner'"
                                                variant="outline"
                                                class="text-[9px] px-1 py-0 shrink-0 bg-primary/10 text-primary border-primary/30"
                                            >
                                                Recommended
                                            </Badge>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground leading-snug mt-0.5">{{ role.description }}</p>
                                    </div>
                                    <Check
                                        v-if="selectedRoleId === role.id"
                                        class="size-3.5 text-primary shrink-0 mt-0.5"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- 4. Demo Period Dropdown & Dynamic Price -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <label class="font-bold text-muted-foreground uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                    <Calendar class="size-3 text-primary" />
                                    <span>Demo Period</span>
                                </label>
                                <span class="text-[10px] font-mono text-muted-foreground">
                                    Valid until {{ calculatedExpiryFormatted }}
                                </span>
                            </div>

                            <div class="relative">
                                <select
                                    v-model="selectedPeriodId"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background outline-none focus:ring-2 focus:ring-primary focus:border-primary text-foreground appearance-none pr-8 cursor-pointer shadow-sm transition-all"
                                >
                                    <option
                                        v-for="period in demoPeriods"
                                        :key="period.id"
                                        :value="period.id"
                                    >
                                        {{ period.label }}
                                    </option>
                                </select>
                                <ChevronDown class="size-3.5 text-muted-foreground absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>

                            <!-- Custom days inline row -->
                            <div v-if="selectedPeriodId === 'custom'" class="flex items-center gap-2 pt-1 px-1">
                                <span class="text-[11px] text-muted-foreground">Enter number of days:</span>
                                <input
                                    v-model.number="customDays"
                                    type="number"
                                    min="1"
                                    max="90"
                                    class="w-16 px-2 py-1 text-xs rounded border border-input bg-background text-foreground font-mono"
                                />
                                <span class="text-[11px] text-muted-foreground">days</span>
                            </div>
                        </div>

                        <!-- 5. Compact Price & Summary Strip -->
                        <div class="p-2.5 rounded-xl border border-primary/20 bg-primary/[0.04] flex items-center justify-between text-xs">
                            <div>
                                <span class="text-[10px] text-muted-foreground block">Calculated Demo Fee ({{ currentEffectiveDays }} Days):</span>
                                <span class="font-bold text-foreground">{{ currentSelectedDemo.name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-base font-black text-primary font-mono tracking-tight">
                                    KES {{ calculatedTotalPrice }}
                                </span>
                            </div>
                        </div>

                        <!-- 6. M-Pesa Prompt Mobile Number -->
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-foreground flex items-center justify-between">
                                <span class="flex items-center gap-1">
                                    <Smartphone class="size-3 text-emerald-600 dark:text-emerald-400" />
                                    <span>M-Pesa Number for Prompt</span>
                                </span>
                                <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-bold">M-PESA STK</span>
                            </label>

                            <div class="flex items-center rounded-xl border border-input bg-background shadow-sm overflow-hidden focus-within:ring-2 focus-within:ring-emerald-500 focus-within:border-emerald-500 transition-all">
                                <div class="pl-3 pr-2 text-emerald-600 font-bold text-xs">
                                    KE
                                </div>
                                <input
                                    v-model="mpesaNumber"
                                    type="tel"
                                    placeholder="07XX XXX XXX or 254..."
                                    class="flex-1 px-2 py-2 text-xs bg-transparent outline-none font-mono text-foreground placeholder:text-muted-foreground/60"
                                />
                                <div class="px-2.5 py-1 bg-emerald-500/10 text-emerald-600 text-[9px] font-bold uppercase rounded mr-1.5 border border-emerald-500/20">
                                    STK Push
                                </div>
                            </div>
                            <p v-if="formErrors.mpesa" class="text-[10px] text-destructive font-medium">
                                {{ formErrors.mpesa }}
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="w-full h-10 text-xs font-bold gap-2 cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm mt-1"
                        >
                            <Smartphone class="size-3.5" />
                            <span>Prompt M-Pesa STK Push (KES {{ calculatedTotalPrice }})</span>
                        </Button>

                        <!-- Satisfied with Demo Option to Contact Admin -->
                        <div class="pt-2 border-t border-border/50 flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>Satisfied with testing? Ready for a live account?</span>
                            <button
                                type="button"
                                @click="showContactAdminModal = true"
                                class="text-primary hover:underline font-semibold cursor-pointer inline-flex items-center gap-1"
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
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm"
                @click.self="showContactAdminModal = false"
            >
                <div class="w-full max-w-md rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-2xl space-y-4 relative">
                    <!-- Close button -->
                    <button
                        type="button"
                        @click="showContactAdminModal = false"
                        class="absolute right-4 top-4 size-7 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/60 flex items-center justify-center cursor-pointer transition-colors"
                    >
                        <X class="size-4" />
                    </button>

                    <!-- Header -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider">
                            <ShieldCheck class="size-4" />
                            <span>Central Production Onboarding</span>
                        </div>
                        <h3 class="text-lg font-bold text-foreground">
                            Contact System Administrator
                        </h3>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Ready to move from evaluation to a permanent merchant account? Contact our systems administrator to activate your live store.
                        </p>
                    </div>

                    <!-- Direct Contact Channels -->
                    <div class="space-y-2">
                        <a
                            href="https://wa.me/254712345678?text=Hello%20MDE%20Admin,%20I%20have%20evaluated%20the%20demo%20and%20am%20satisfied.%20I%20would%20like%20to%20set%20up%20a%20live%20production%20account."
                            target="_blank"
                            rel="noopener"
                            class="flex items-center justify-between p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 hover:bg-emerald-500/10 transition-colors text-xs font-medium cursor-pointer"
                        >
                            <div class="flex items-center gap-2.5 text-foreground">
                                <div class="size-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center">
                                    <MessageSquare class="size-4" />
                                </div>
                                <div>
                                    <span class="font-bold block text-emerald-700 dark:text-emerald-400">Direct WhatsApp Support</span>
                                    <span class="text-[11px] text-muted-foreground">Instant chat with lead administrator</span>
                                </div>
                            </div>
                            <ArrowRight class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        </a>

                        <a
                            href="tel:+254712345678"
                            class="flex items-center justify-between p-3 rounded-xl border border-border/80 bg-background hover:bg-muted/40 transition-colors text-xs font-medium cursor-pointer"
                        >
                            <div class="flex items-center gap-2.5 text-foreground">
                                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <PhoneCall class="size-4" />
                                </div>
                                <div>
                                    <span class="font-bold block">Telephone Admin Hotline</span>
                                    <span class="text-[11px] text-muted-foreground font-mono">+254 712 345 678</span>
                                </div>
                            </div>
                            <ArrowRight class="size-3.5 text-muted-foreground" />
                        </a>

                        <a
                            href="mailto:admin@mdepos.co.ke?subject=Production%20Store%20Setup%20Request"
                            class="flex items-center justify-between p-3 rounded-xl border border-border/80 bg-background hover:bg-muted/40 transition-colors text-xs font-medium cursor-pointer"
                        >
                            <div class="flex items-center gap-2.5 text-foreground">
                                <div class="size-8 rounded-lg bg-muted text-muted-foreground flex items-center justify-center">
                                    <Mail class="size-4" />
                                </div>
                                <div>
                                    <span class="font-bold block">Official Administration Email</span>
                                    <span class="text-[11px] text-muted-foreground font-mono">admin@mdepos.co.ke</span>
                                </div>
                            </div>
                            <ArrowRight class="size-3.5 text-muted-foreground" />
                        </a>
                    </div>

                    <!-- Callback Request Form -->
                    <div class="pt-2 border-t border-border/70 space-y-2.5">
                        <div v-if="callbackRequested" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
                            <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                            <span>Callback request sent! Our System Administrator will contact you at {{ userPhone || 'your contact number' }} shortly.</span>
                        </div>

                        <div v-else class="space-y-2">
                            <label class="text-[11px] font-semibold text-foreground block">
                                Or Request an Administrator Callback:
                            </label>
                            <div class="flex gap-2">
                                <input
                                    v-model="userPhone"
                                    type="tel"
                                    placeholder="Your phone number"
                                    class="flex-1 px-2.5 py-1.5 text-xs rounded-xl border border-input bg-background outline-none focus:ring-2 focus:ring-primary focus:border-primary text-foreground placeholder:text-muted-foreground/60 font-mono"
                                    @keydown.enter.prevent="submitAdminInquiry"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-8 text-xs font-bold gap-1.5 cursor-pointer shrink-0"
                                    :loading="isSubmittingInquiry"
                                    @click="submitAdminInquiry"
                                >
                                    <Send class="size-3" />
                                    <span>Request Setup</span>
                                </Button>
                            </div>
                            <p v-if="inquiryError" class="text-[10px] text-destructive font-medium">
                                {{ inquiryError }}
                            </p>
                        </div>
                    </div>

                    <!-- Dismiss button -->
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="w-full text-xs text-muted-foreground cursor-pointer"
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
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm"
                @click.self="resetWaitlistModal"
            >
                <div class="w-full max-w-md rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-2xl space-y-4 relative">
                    <!-- Close button -->
                    <button
                        type="button"
                        @click="resetWaitlistModal"
                        class="absolute right-4 top-4 size-7 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/60 flex items-center justify-center cursor-pointer transition-colors"
                    >
                        <X class="size-4" />
                    </button>

                    <!-- Success State -->
                    <div v-if="waitlistSubmitted" class="text-center py-4 space-y-3">
                        <div class="size-12 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 flex items-center justify-center mx-auto">
                            <CheckCircle2 class="size-6" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-bold text-foreground">You're on the Priority Waitlist!</h3>
                            <p class="text-xs text-muted-foreground max-w-xs mx-auto">
                                Thank you, <strong class="text-foreground">{{ waitlistForm.fullName || waitlistForm.businessName }}</strong>. We'll email you at <span class="font-mono text-foreground">{{ waitlistForm.email }}</span> the moment self-service merchant onboarding goes live.
                            </p>
                        </div>
                        <div class="p-3 rounded-xl bg-primary/5 border border-primary/20 text-xs space-y-1.5 text-left">
                            <span class="font-bold text-foreground flex items-center gap-1.5">
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
                                class="w-full bg-primary text-primary-foreground font-bold"
                                @click="() => { resetWaitlistModal(); scrollToDemos(); }"
                            >
                                <Sparkles class="size-3.5 mr-1" />
                                Explore Interactive Demos Now
                            </Button>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <div v-else class="space-y-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 font-bold text-xs uppercase tracking-wider">
                                <Clock class="size-4" />
                                <span>Priority Onboarding</span>
                            </div>
                            <h3 class="text-lg font-bold text-foreground">
                                Register for Early Access Waitlist
                            </h3>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                Be among the first merchants to receive a custom subdomain, dedicated database, and automated onboarding.
                            </p>
                        </div>

                        <form @submit.prevent="handleWaitlistSubmit" class="space-y-3">
                            <div>
                                <label class="text-[11px] font-semibold text-foreground block mb-1">Business / Store Name *</label>
                                <input
                                    v-model="waitlistForm.businessName"
                                    type="text"
                                    required
                                    placeholder="e.g. Highlands Supermarket Ltd"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="text-[11px] font-semibold text-foreground block mb-1">Contact Person Name</label>
                                    <input
                                        v-model="waitlistForm.fullName"
                                        type="text"
                                        placeholder="e.g. Sarah Kimani"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-foreground block mb-1">Business Type</label>
                                    <select
                                        v-model="waitlistForm.businessType"
                                        class="w-full px-2.5 py-2 text-xs rounded-xl border border-input bg-background text-foreground outline-none focus:ring-2 focus:ring-primary cursor-pointer"
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
                                    <label class="text-[11px] font-semibold text-foreground block mb-1">Work Email *</label>
                                    <input
                                        v-model="waitlistForm.email"
                                        type="email"
                                        required
                                        placeholder="admin@mystore.co.ke"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-foreground block mb-1">Phone / WhatsApp</label>
                                    <input
                                        v-model="waitlistForm.phone"
                                        type="tel"
                                        placeholder="+254 7XX XXX XXX"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground font-mono outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                            </div>

                            <p v-if="waitlistError" class="text-[10px] text-destructive font-medium">
                                {{ waitlistError }}
                            </p>

                            <div class="pt-2 flex items-center justify-end gap-2">
                                <Button type="button" variant="ghost" size="sm" @click="resetWaitlistModal">
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-amber-600 hover:bg-amber-700 text-white font-bold"
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
        <footer class="border-t border-border/70 bg-card/60 py-4 px-4 text-center text-xs text-muted-foreground">
            <p>© 2026 MDE Software Systems. Multi-Tenant Retail Point of Sale & Inventory Platform.</p>
        </footer>
    </div>
</template>
