<script setup lang="ts">
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    BookOpen,
    Boxes,
    Building2,
    Check,
    CheckCircle2,
    ChefHat,
    ChevronRight,
    CircleDollarSign,
    ClipboardList,
    Copy,
    CreditCard,
    DollarSign,
    ExternalLink,
    Fuel,
    Globe,
    HardDrive,
    Key,
    Layers,
    Package,
    Phone,
    Pill,
    Receipt,
    RefreshCw,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
    Trash2,
    TrendingDown,
    TrendingUp,
    Truck,
    UserCheck,
    UserCog,
    UserPlus,
    Users,
    UtensilsCrossed,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        kra_pin: string | null;
        email: string;
        phone: string | null;
        country: string;
        currency: string;
        business_type: string;
        plan: string;
        billing_cycle: string;
        status: string;
        is_demo: boolean;
        branch_count: number;
        storage_quota_mb: number;
        enabled_modules: string[];
        settings: Record<string, any>;
        subdomain_url: string;
        created_at: string;
    };
    kpis: {
        today_sales: number;
        today_profit: number;
        today_transactions: number;
        seven_day_sales: number;
        thirty_day_sales: number;
        thirty_day_transactions: number;
        thirty_day_expenses: number;
        stock_value: number;
        total_products: number;
        low_stock_count: number;
        total_users: number;
        total_branches: number;
        total_customers: number;
        total_suppliers: number;
    };
    paymentMix: { method: string; amount: number; percent: number; count: number }[];
    recentSales: any[];
    products: any[];
    branches: any[];
    users: any[];
    expenses: any[];
    purchases: any[];
    customers: any[];
    suppliers: any[];
    flash?: { success?: string; error?: string };
}>();

const { success, warning, error, info } = useToast();

// Flash messages
watch(
    () => props.flash,
    (flash) => {
        if (flash?.success) success('Done', flash.success);
        if (flash?.error) error('Error', flash.error);
    },
    { immediate: true },
);

const copyTenantUrl = (url?: string) => {
    if (!url) return;
    navigator.clipboard.writeText(url);
    success('Copied!', 'Tenant login URL copied to clipboard.');
};

// ─── Tabs ─────────────────────────────────────────────────────────────────────
type Tab =
    | 'overview'
    | 'products'
    | 'sales'
    | 'branches'
    | 'users'
    | 'expenses'
    | 'purchases'
    | 'customers'
    | 'suppliers'
    | 'modules'
    | 'settings'
    | 'payments';
const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const activeTab = ref<Tab>((urlParams?.get('tab') as Tab) || 'overview');

const tabs: { id: Tab; label: string; icon: any; count?: () => number }[] = [
    { id: 'overview', label: 'Overview', icon: BarChart3 },
    { id: 'sales', label: 'Sales', icon: Zap, count: () => props.recentSales.length },
    { id: 'products', label: 'Products', icon: Package, count: () => props.kpis.total_products },
    { id: 'branches', label: 'Branches', icon: Building2, count: () => props.kpis.total_branches },
    { id: 'users', label: 'Staff', icon: Users, count: () => props.kpis.total_users },
    { id: 'expenses', label: 'Expenses', icon: Wallet, count: () => props.expenses.length },
    { id: 'purchases', label: 'Purchases', icon: Truck, count: () => props.purchases.length },
    { id: 'customers', label: 'Customers', icon: UserCheck, count: () => props.kpis.total_customers },
    { id: 'suppliers', label: 'Suppliers', icon: Globe, count: () => props.kpis.total_suppliers },
    { id: 'modules', label: 'Pages & Modules', icon: Layers, count: () => selectedModules.value.length },
    { id: 'payments', label: 'Payment Methods', icon: CreditCard },
    { id: 'settings', label: 'Settings', icon: Settings },
];

// ─── Add User Modal ───────────────────────────────────────────────────────────
const addUserModal = ref(false);
const addUserForm = reactive({ name: '', email: '', role: 'Cashier', phone: '', password: '' });
const addUserProcessing = ref(false);

const handleAddUser = () => {
    if (!addUserForm.name || !addUserForm.email || !addUserForm.password) {
        warning('Required', 'Name, email and password are required.');
        return;
    }
    addUserProcessing.value = true;
    router.post(`/super-admin/tenants/${props.tenant.id}/detail/users`, addUserForm, {
        onSuccess: () => {
            addUserModal.value = false;
            Object.assign(addUserForm, { name: '', email: '', role: 'Cashier', phone: '', password: '' });
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (addUserProcessing.value = false),
    });
};

// ─── Edit User Modal ──────────────────────────────────────────────────────────
const editUserModal = ref(false);
const editUserTarget = ref<any>(null);
const editUserForm = reactive({ name: '', role: '', phone: '', status: 'Active' });
const editUserProcessing = ref(false);

const openEditUser = (u: any) => {
    editUserTarget.value = u;
    Object.assign(editUserForm, { name: u.name, role: u.role, phone: u.phone ?? '', status: u.status });
    editUserModal.value = true;
};

const handleEditUser = () => {
    if (!editUserTarget.value) return;
    editUserProcessing.value = true;
    router.patch(`/super-admin/tenants/${props.tenant.id}/detail/users/${editUserTarget.value.id}`, editUserForm, {
        onSuccess: () => {
            editUserModal.value = false;
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (editUserProcessing.value = false),
    });
};

// ─── Delete User ──────────────────────────────────────────────────────────────
const deleteUserConfirmOpen = ref(false);
const userToDelete = ref<any>(null);
const isDeletingUser = ref(false);

const openDeleteUser = (u: any) => {
    userToDelete.value = u;
    deleteUserConfirmOpen.value = true;
};
const confirmDeleteUser = () => {
    if (!userToDelete.value) return;
    isDeletingUser.value = true;
    router.delete(`/super-admin/tenants/${props.tenant.id}/detail/users/${userToDelete.value.id}`, {
        onSuccess: () => {
            deleteUserConfirmOpen.value = false;
        },
        onError: () => error('Failed', 'Could not delete user.'),
        onFinish: () => (isDeletingUser.value = false),
    });
};

// ─── Reset Password ───────────────────────────────────────────────────────────
const handleResetPassword = (u: any) => {
    router.post(
        `/super-admin/tenants/${props.tenant.id}/detail/users/${u.id}/reset-password`,
        {},
        {
            onSuccess: () => info('Password Reset', `New temp password shown in success message.`),
            onError: () => error('Failed', 'Could not reset password.'),
        },
    );
};

// ─── Settings (profile update) ────────────────────────────────────────────────
const settingsForm = reactive({
    name: props.tenant.name,
    email: props.tenant.email,
    phone: props.tenant.phone ?? '',
    kra_pin: props.tenant.kra_pin ?? '',
    country: props.tenant.country,
    currency: props.tenant.currency,
    plan: props.tenant.plan,
    billing_cycle: props.tenant.billing_cycle,
    status: props.tenant.status,
    storage_quota_mb: props.tenant.storage_quota_mb,
    branch_count: props.tenant.branch_count,
});
const settingsProcessing = ref(false);
const handleSaveSettings = () => {
    settingsProcessing.value = true;
    router.patch(`/super-admin/tenants/${props.tenant.id}/detail`, settingsForm, {
        onSuccess: () => success('Saved', `${settingsForm.name} settings updated.`),
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (settingsProcessing.value = false),
    });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const statusClass = (s: string) =>
    s === 'Active'
        ? 'bg-emerald-500/20 text-emerald-300'
        : s === 'Trial'
          ? 'bg-amber-500/20 text-amber-300'
          : s === 'Suspended'
            ? 'bg-rose-500/20 text-rose-300'
            : 'bg-zinc-700 text-zinc-400';

const roleColor = (r: string) =>
    r === 'Owner'
        ? 'text-violet-300 bg-violet-500/20 border-violet-500/30'
        : r === 'Administrator'
          ? 'text-sky-300 bg-sky-500/20 border-sky-500/30'
          : r === 'Manager'
            ? 'text-blue-300 bg-blue-500/20 border-blue-500/30'
            : r === 'Cashier'
              ? 'text-emerald-300 bg-emerald-500/20 border-emerald-500/30'
              : r === 'Inventory Manager'
                ? 'text-amber-300 bg-amber-500/20 border-amber-500/30'
                : r === 'Supervisor'
                  ? 'text-teal-300 bg-teal-500/20 border-teal-500/30'
                  : r === 'Accountant'
                    ? 'text-pink-300 bg-pink-500/20 border-pink-500/30'
                    : 'text-zinc-300 bg-zinc-700 border-zinc-600';

const allModules = [
    'pos',
    'sales',
    'products',
    'inventory',
    'purchases',
    'customers',
    'suppliers',
    'expenses',
    'reports',
    'users',
    'branches',
    'settings',
];

interface ModuleDef {
    key: string;
    label: string;
    category: 'Sales & Checkout' | 'Catalog & Stock' | 'CRM & Relations' | 'Finance & Analytics' | 'Management' | 'Industry Verticals';
    route?: string;
    description: string;
    icon: any;
    recommendedFor: string;
}

const ALL_SYSTEM_MODULES: ModuleDef[] = [
    // Sales & Checkout
    {
        key: 'pos',
        label: 'POS Cashier Terminal',
        category: 'Sales & Checkout',
        route: '/pos',
        description: 'Fast cashier register, barcode scan, cart management, instant M-Pesa STK & receipt printing.',
        icon: ShoppingCart,
        recommendedFor: 'All Retail & Food',
    },
    {
        key: 'sales',
        label: 'Sales History & Orders',
        category: 'Sales & Checkout',
        route: '/sales',
        description: 'Detailed sales ledger, receipts, invoice generation, shift reconciliation, and refunds/returns.',
        icon: Receipt,
        recommendedFor: 'All Business Types',
    },

    // Catalog & Stock
    {
        key: 'products',
        label: 'Product Catalog',
        category: 'Catalog & Stock',
        route: '/products',
        description: 'Item directory, SKU codes, category taxonomy, cost/retail pricing tiers, and barcode printing.',
        icon: Package,
        recommendedFor: 'All Businesses with Items',
    },
    {
        key: 'inventory',
        label: 'Inventory Control',
        category: 'Catalog & Stock',
        route: '/inventory',
        description: 'Real-time stock tracking, warehouse adjustments, shrinkage logs, and automated low-stock alerts.',
        icon: Boxes,
        recommendedFor: 'Retail, Wholesale, Hardware',
    },
    {
        key: 'purchases',
        label: 'Purchase Orders & LPO',
        category: 'Catalog & Stock',
        route: '/purchases',
        description: 'Supplier purchase orders, receiving notes, invoice attachments, and stock intake verification.',
        icon: ClipboardList,
        recommendedFor: 'Supermarket, Wholesale, Hardware',
    },
    {
        key: 'suppliers',
        label: 'Suppliers & Vendors',
        category: 'Catalog & Stock',
        route: '/suppliers',
        description: 'Vendor contact directory, accounts payable ledger, supplier payment vouchers, and balances.',
        icon: Truck,
        recommendedFor: 'Wholesale, Supermarket, Pharmacy',
    },

    // CRM & Relations
    {
        key: 'customers',
        label: 'Customer Accounts & Credit',
        category: 'CRM & Relations',
        route: '/customers',
        description: 'Customer directory, store credit limit management, debtor ledger, and payment tracking.',
        icon: UserCheck,
        recommendedFor: 'Wholesale, Supermarket, General',
    },
    {
        key: 'loyalty',
        label: 'Loyalty & Reward Points',
        category: 'CRM & Relations',
        description: 'Points accumulation on purchases, member reward tiers, and promotional discount redemption.',
        icon: Star,
        recommendedFor: 'Supermarket, Apparel, Restaurants',
    },

    // Finance & Analytics
    {
        key: 'expenses',
        label: 'Expenses & Petty Cash',
        category: 'Finance & Analytics',
        route: '/expenses',
        description: 'Operational expense tracking, petty cash vouchers, expense categories, and spend approvals.',
        icon: Wallet,
        recommendedFor: 'All Business Types',
    },
    {
        key: 'reports',
        label: 'Reports & P&L Analytics',
        category: 'Finance & Analytics',
        route: '/reports',
        description: 'Comprehensive financial statements, sales trend curves, profit margins, and KRA VAT reports.',
        icon: BarChart3,
        recommendedFor: 'All Business Types',
    },

    // Management
    {
        key: 'users',
        label: 'Staff & Roles Management',
        category: 'Management',
        route: '/users',
        description: 'Staff accounts, role permissions (Cashier, Supervisor, Manager, Owner), and access control.',
        icon: ShieldCheck,
        recommendedFor: 'All Businesses (2+ Staff)',
    },
    {
        key: 'branches',
        label: 'Branch Outlets & Locations',
        category: 'Management',
        route: '/branches',
        description: 'Multi-location store management, inter-branch stock transfers, and outlet performance.',
        icon: Store,
        recommendedFor: 'Multi-Branch & Enterprise',
    },
    {
        key: 'settings',
        label: 'Store & Fiscal Settings',
        category: 'Management',
        route: '/settings',
        description: 'Store contact details, KRA eTIMS fiscal configuration, receipt branding, and payment setups.',
        icon: Settings,
        recommendedFor: 'All Businesses (Core)',
    },

    // Industry Verticals
    {
        key: 'prescriptions',
        label: 'Prescriptions & Dispensary (Rx)',
        category: 'Industry Verticals',
        description: 'Pharmaceutical prescription logging, doctor references, dosage instructions, and drug dispensing.',
        icon: Pill,
        recommendedFor: 'Pharmacy & Chemists Only',
    },
    {
        key: 'tables',
        label: 'Dining Tables & Floor Plan',
        category: 'Industry Verticals',
        description: 'Interactive restaurant table layouts, seated order routing, open tabs, and bill splitting.',
        icon: UtensilsCrossed,
        recommendedFor: 'Restaurants, Cafes & Bars',
    },
    {
        key: 'kitchen',
        label: 'Kitchen Display System (KDS)',
        category: 'Industry Verticals',
        description: 'Real-time kitchen order display queue, order cook times, and order completion dispatching.',
        icon: ChefHat,
        recommendedFor: 'Restaurants, Cafes & Bakeries',
    },
    {
        key: 'fuel',
        label: 'Fuel Pump Control',
        category: 'Industry Verticals',
        description: 'Fuel pump dispenser integration, meter readings, dipstick inventory, and fuel sales logs.',
        icon: Fuel,
        recommendedFor: 'Petrol & Service Stations',
    },
];

const BUSINESS_TYPE_PRESETS: Record<string, { label: string; icon: string; modules: string[] }> = {
    Supermarket: {
        label: 'Supermarket',
        icon: '🛒',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
            'loyalty',
        ],
    },
    Wholesale: {
        label: 'Wholesale & B2B',
        icon: '📦',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
    Pharmacy: {
        label: 'Pharmacy / Chemist',
        icon: '💊',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'settings',
            'prescriptions',
        ],
    },
    Restaurant: {
        label: 'Restaurant / Cafe',
        icon: '🍽️',
        modules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'expenses', 'reports', 'users', 'settings', 'tables', 'kitchen'],
    },
    Hardware: {
        label: 'Hardware & Building',
        icon: '🔨',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
    'General Trade': {
        label: 'General Trade / Kiosk',
        icon: '🏪',
        modules: ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings'],
    },
    Electronics: {
        label: 'Electronics & Tech',
        icon: '📱',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
    'Clothing & Apparel': {
        label: 'Clothing & Fashion',
        icon: '👗',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'settings',
            'loyalty',
        ],
    },
    Bakery: {
        label: 'Bakery & Pastry',
        icon: '🥖',
        modules: ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings', 'kitchen'],
    },
    'Petrol Station': {
        label: 'Petrol Station',
        icon: '⛽',
        modules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'expenses', 'reports', 'users', 'settings', 'fuel'],
    },
};

const SIZE_PRESETS = [
    {
        id: 'starter',
        name: 'Small Business (Lite / Starter)',
        badge: 'Lite Scale (6 Pages)',
        description: 'Core POS register, sales ledger, basic product catalog, expenses, users, and store settings.',
        modules: ['pos', 'sales', 'products', 'expenses', 'users', 'settings'],
    },
    {
        id: 'pro',
        name: 'Medium Business (Standard / Pro)',
        badge: 'Growth Scale (11 Pages)',
        description: 'Complete stock inventory, purchasing, supplier accounts, CRM debtors, and analytics.',
        modules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'settings'],
    },
    {
        id: 'enterprise',
        name: 'Large Enterprise (Full Suite)',
        badge: 'Enterprise Multi-Branch (All Pages)',
        description: 'All system capabilities including multi-branch outlets, loyalty, and industry extensions.',
        modules: ALL_SYSTEM_MODULES.map((m) => m.key),
    },
];

const selectedModules = ref<string[]>([...(props.tenant.enabled_modules || [])]);
const isModuleSelected = (key: string) => selectedModules.value.includes(key);

const toggleModule = (key: string) => {
    const idx = selectedModules.value.indexOf(key);
    if (idx !== -1) {
        selectedModules.value.splice(idx, 1);
    } else {
        selectedModules.value.push(key);
    }
};

const applyTypePreset = (typeKey: string) => {
    const preset = BUSINESS_TYPE_PRESETS[typeKey];
    if (preset) {
        selectedModules.value = [...preset.modules];
        info('Preset Applied', `Loaded ${preset.modules.length} recommended pages for ${preset.label}. Remember to save.`);
    }
};

const applySizePreset = (preset: (typeof SIZE_PRESETS)[number]) => {
    selectedModules.value = [...preset.modules];
    info('Scale Preset Applied', `Loaded ${preset.modules.length} pages for ${preset.name}. Remember to save.`);
};

const selectAllModules = () => {
    selectedModules.value = ALL_SYSTEM_MODULES.map((m) => m.key);
    info('All Selected', `All ${ALL_SYSTEM_MODULES.length} system pages enabled.`);
};

const deselectOptionalModules = () => {
    selectedModules.value = ['pos', 'sales', 'settings'];
    info('Reset to Core', 'Only core POS, Sales and Settings remain enabled.');
};

const savingModules = ref(false);
const handleSaveModules = () => {
    savingModules.value = true;
    router.patch(
        `/super-admin/tenants/${props.tenant.id}/detail`,
        {
            enabled_modules: selectedModules.value,
        },
        {
            onSuccess: () => {
                success('Page Permissions Saved', `Updated page access for ${props.tenant.name}. ${selectedModules.value.length} pages are active.`);
            },
            onError: () => {
                error('Save Failed', 'Could not update tenant modules. Please check connection.');
            },
            onFinish: () => {
                savingModules.value = false;
            },
        },
    );
};

const moduleCategories = computed(() => {
    const cats: Record<string, ModuleDef[]> = {};
    ALL_SYSTEM_MODULES.forEach((m) => {
        if (!cats[m.category]) cats[m.category] = [];
        cats[m.category].push(m);
    });
    return cats;
});

const totalExpenses30 = computed(() => props.expenses.reduce((s, e) => s + e.amount, 0));

// ─── Payment Methods Configuration ───────────────────────────────────────────
interface PaymentMethodConfig {
    enabled: boolean;
    label: string;
    // M-Pesa specific
    consumer_key?: string;
    consumer_secret?: string;
    shortcode?: string;
    passkey?: string;
    type?: 'paybill' | 'till';
    // Bank specific
    bank_name?: string;
    account_number?: string;
    // Card specific
    provider?: string;
    // Credit specific
    credit_limit?: number;
}

interface PaymentSettings {
    cash: PaymentMethodConfig;
    mpesa: PaymentMethodConfig;
    card: PaymentMethodConfig;
    bank: PaymentMethodConfig;
    credit: PaymentMethodConfig;
}

const tenantSettings = props.tenant.settings ?? {};
const existingPayments = tenantSettings.payment_methods ?? {};

const paymentSettings = reactive<PaymentSettings>({
    cash: {
        enabled: existingPayments.cash?.enabled ?? true,
        label: 'Cash',
    },
    mpesa: {
        enabled: existingPayments.mpesa?.enabled ?? false,
        label: 'M-Pesa',
        consumer_key: existingPayments.mpesa?.consumer_key ?? '',
        consumer_secret: existingPayments.mpesa?.consumer_secret ?? '',
        shortcode: existingPayments.mpesa?.shortcode ?? '',
        passkey: existingPayments.mpesa?.passkey ?? '',
        type: existingPayments.mpesa?.type ?? 'paybill',
    },
    card: {
        enabled: existingPayments.card?.enabled ?? false,
        label: 'Card / POS Machine',
        provider: existingPayments.card?.provider ?? '',
    },
    bank: {
        enabled: existingPayments.bank?.enabled ?? false,
        label: 'Bank Transfer',
        bank_name: existingPayments.bank?.bank_name ?? '',
        account_number: existingPayments.bank?.account_number ?? '',
    },
    credit: {
        enabled: existingPayments.credit?.enabled ?? false,
        label: 'Store Credit / Debt',
        credit_limit: existingPayments.credit?.credit_limit ?? 0,
    },
});

const savingPayments = ref(false);
const handleSavePayments = () => {
    savingPayments.value = true;
    router.patch(
        `/super-admin/tenants/${props.tenant.id}/detail`,
        {
            settings: { payment_methods: paymentSettings },
        },
        {
            onSuccess: () => success('Saved', 'Payment methods updated for ' + props.tenant.name),
            onError: () => error('Failed', 'Could not save payment settings.'),
            onFinish: () => (savingPayments.value = false),
        },
    );
};

const enabledPaymentCount = computed(() => Object.values(paymentSettings).filter((m) => m.enabled).length);
</script>

<template>
    <Head :title="`${tenant.name} — Super Admin`" />
    <SuperAdminLayout>
        <div class="mx-auto max-w-[1600px] space-y-5 p-4 sm:p-6">
            <!-- ── Header ──────────────────────────────────────────────────── -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a
                    href="/super-admin/tenants"
                    class="inline-flex shrink-0 items-center gap-1.5 text-xs text-zinc-400 transition-colors hover:text-zinc-100"
                >
                    <ArrowLeft class="size-3.5" />
                    All Tenants
                </a>
                <ChevronRight class="hidden size-3.5 text-zinc-600 sm:block" />

                <div class="flex flex-1 items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-violet-600/30 bg-violet-600/20">
                        <Building2 class="size-5 text-violet-400" />
                    </div>
                    <div>
                        <h1 class="flex items-center gap-2 text-lg font-bold leading-tight text-zinc-100">
                            {{ tenant.name }}
                            <span
                                v-if="tenant.is_demo"
                                class="rounded border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 font-mono text-[9px] font-extrabold uppercase tracking-wider text-amber-300"
                                >Demo</span
                            >
                            <span
                                v-else
                                class="rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[9px] font-extrabold uppercase tracking-wider text-emerald-300"
                                >Live</span
                            >
                        </h1>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 font-mono text-[11px] text-zinc-400">
                            <span>{{ tenant.business_type }} · {{ tenant.plan }}</span>
                            <span class="text-zinc-600">·</span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded border border-zinc-700/60 bg-zinc-800/80 px-2 py-0.5 text-violet-300"
                            >
                                <span>{{ tenant.subdomain_url }}</span>
                                <button
                                    type="button"
                                    class="text-zinc-400 transition-colors hover:text-white"
                                    title="Copy login URL"
                                    @click="copyTenantUrl(tenant.subdomain_url)"
                                >
                                    <Copy class="size-3" />
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span :class="['rounded-lg px-2.5 py-1 text-[10px] font-bold', statusClass(tenant.status)]">
                        {{ tenant.status }}
                    </span>
                    <a
                        :href="tenant.subdomain_url"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-violet-700"
                    >
                        <ExternalLink class="size-3.5" />
                        Open Portal
                    </a>
                </div>
            </div>

            <!-- ── Tab Navigation ──────────────────────────────────────────── -->
            <div class="scrollbar-none flex items-center gap-1 overflow-x-auto border-b border-zinc-800 pb-0.5">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    :class="[
                        '-mb-px flex items-center gap-1.5 whitespace-nowrap rounded-t-lg border-b-2 px-3 py-2 text-[11px] font-semibold transition-colors',
                        activeTab === tab.id
                            ? 'border-violet-500 bg-violet-600/10 text-violet-300'
                            : 'border-transparent text-zinc-500 hover:bg-zinc-800/60 hover:text-zinc-200',
                    ]"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="size-3.5" />
                    {{ tab.label }}
                    <span v-if="tab.count && tab.count() > 0" class="rounded-full bg-zinc-700 px-1.5 py-0.5 text-[9px] font-bold text-zinc-300">
                        {{ tab.count() }}
                    </span>
                </button>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: OVERVIEW                                                    -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'overview'" class="space-y-5">
                <!-- KPI Grid -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <Zap class="size-3 text-emerald-400" /> Today Sales
                        </div>
                        <p class="font-mono text-xl font-extrabold text-emerald-400">{{ formatCurrency(kpis.today_sales) }}</p>
                        <p class="text-[10px] text-zinc-500">{{ kpis.today_transactions }} txns</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <TrendingUp class="size-3 text-sky-400" /> Today Profit
                        </div>
                        <p class="font-mono text-xl font-extrabold text-sky-400">{{ formatCurrency(kpis.today_profit) }}</p>
                        <p class="text-[10px] text-zinc-500">Net margin</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <BarChart3 class="size-3 text-violet-400" /> 7-Day Sales
                        </div>
                        <p class="font-mono text-xl font-extrabold text-violet-400">{{ formatCurrency(kpis.seven_day_sales) }}</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <Activity class="size-3 text-amber-400" /> 30-Day Sales
                        </div>
                        <p class="font-mono text-xl font-extrabold text-amber-400">{{ formatCurrency(kpis.thirty_day_sales) }}</p>
                        <p class="text-[10px] text-zinc-500">{{ kpis.thirty_day_transactions }} txns</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <HardDrive class="size-3 text-teal-400" /> Stock Value
                        </div>
                        <p class="font-mono text-xl font-extrabold text-teal-400">{{ formatCurrency(kpis.stock_value) }}</p>
                        <p class="text-[10px]" :class="kpis.low_stock_count > 0 ? 'font-semibold text-rose-400' : 'text-zinc-500'">
                            {{ kpis.low_stock_count > 0 ? `${kpis.low_stock_count} low stock!` : 'Stock OK' }}
                        </p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <TrendingDown class="size-3 text-rose-400" /> 30-Day Expenses
                        </div>
                        <p class="font-mono text-xl font-extrabold text-rose-400">{{ formatCurrency(kpis.thirty_day_expenses) }}</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-3.5">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase text-zinc-500">
                            <Package class="size-3 text-zinc-400" /> Products
                        </div>
                        <p class="font-mono text-xl font-extrabold text-zinc-100">{{ kpis.total_products }}</p>
                        <p class="text-[10px] text-zinc-500">{{ kpis.total_branches }} branches · {{ kpis.total_users }} staff</p>
                    </div>
                </div>

                <!-- Payment Mix + Recent Sales -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Payment Mix -->
                    <div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <h3 class="flex items-center gap-2 text-xs font-bold text-zinc-200">
                            <Wallet class="size-4 text-violet-400" />
                            30-Day Payment Mix
                        </h3>
                        <div v-if="paymentMix.length === 0" class="py-6 text-center text-xs text-zinc-600">No sales data yet</div>
                        <div v-else class="space-y-2.5">
                            <div v-for="pm in paymentMix" :key="pm.method" class="space-y-1">
                                <div class="flex justify-between text-xs">
                                    <span class="font-semibold text-zinc-300">{{ pm.method }}</span>
                                    <span class="font-mono text-zinc-400">{{ formatCurrency(pm.amount) }} ({{ pm.percent }}%)</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-zinc-800">
                                    <div class="h-1.5 rounded-full bg-violet-500 transition-all" :style="`width: ${pm.percent}%`" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Sales -->
                    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 lg:col-span-2">
                        <div class="flex items-center justify-between border-b border-zinc-800 px-4 py-3">
                            <h3 class="flex items-center gap-2 text-xs font-bold text-zinc-200">
                                <ShoppingCart class="size-4 text-emerald-400" /> Recent Sales
                            </h3>
                            <button type="button" @click="activeTab = 'sales'" class="text-[10px] text-violet-400 hover:underline">View all</button>
                        </div>
                        <div v-if="recentSales.length === 0" class="py-8 text-center text-xs text-zinc-600">No sales recorded yet</div>
                        <div v-else class="max-h-72 divide-y divide-zinc-800 overflow-y-auto">
                            <div
                                v-for="sale in recentSales.slice(0, 8)"
                                :key="sale.id"
                                class="flex items-center justify-between px-4 py-2.5 text-xs transition-colors hover:bg-zinc-800/40"
                            >
                                <div>
                                    <p class="font-mono font-bold text-zinc-100">{{ sale.receipt_number }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ sale.cashier }} · {{ sale.branch }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-extrabold text-emerald-400">{{ formatCurrency(sale.total) }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ sale.payment_method }} · {{ sale.time }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: SALES                                                       -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'sales'" class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-3">
                    <h3 class="text-sm font-bold text-zinc-100">Recent Transactions (Last 15)</h3>
                    <p class="text-[11px] text-zinc-500">Live sales log for {{ tenant.name }}</p>
                </div>
                <div v-if="recentSales.length === 0" class="py-12 text-center text-sm text-zinc-600">No sales recorded yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Receipt</th>
                                <th class="px-4 py-3 font-semibold">Cashier</th>
                                <th class="px-4 py-3 font-semibold">Branch</th>
                                <th class="px-4 py-3 text-right font-semibold">Total</th>
                                <th class="px-4 py-3 text-right font-semibold">Profit</th>
                                <th class="px-4 py-3 font-semibold">Payment</th>
                                <th class="px-4 py-3 font-semibold">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="s in recentSales" :key="s.id" class="hover:bg-zinc-800/40">
                                <td class="px-5 py-2.5 font-mono font-bold text-violet-300">{{ s.receipt_number }}</td>
                                <td class="px-4 py-2.5 text-zinc-300">{{ s.cashier }}</td>
                                <td class="px-4 py-2.5 text-zinc-400">{{ s.branch }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-400">{{ formatCurrency(s.total) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-sky-400">{{ formatCurrency(s.profit) }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-300">{{
                                        s.payment_method
                                    }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-zinc-500">{{ s.time }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: PRODUCTS                                                    -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'products'" class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Product Catalogue</h3>
                        <p class="text-[11px] text-zinc-500">
                            {{ kpis.total_products }} total · <span class="font-semibold text-rose-400">{{ kpis.low_stock_count }} low stock</span>
                        </p>
                    </div>
                </div>
                <div v-if="products.length === 0" class="py-12 text-center text-sm text-zinc-600">No products found.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Product</th>
                                <th class="px-4 py-3 font-semibold">SKU</th>
                                <th class="px-4 py-3 font-semibold">Category</th>
                                <th class="px-4 py-3 text-right font-semibold">Cost</th>
                                <th class="px-4 py-3 text-right font-semibold">Price</th>
                                <th class="px-4 py-3 text-center font-semibold">Stock</th>
                                <th class="px-4 py-3 font-semibold">Branch</th>
                                <th class="px-4 py-3 text-center font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="p in products" :key="p.id" class="hover:bg-zinc-800/40" :class="p.low_stock ? 'bg-rose-950/10' : ''">
                                <td class="px-5 py-2.5">
                                    <p class="font-semibold text-zinc-100">{{ p.name }}</p>
                                    <p v-if="p.barcode" class="font-mono text-[10px] text-zinc-500">{{ p.barcode }}</p>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-violet-300">{{ p.sku }}</td>
                                <td class="px-4 py-2.5 text-zinc-400">{{ p.category }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-zinc-300">{{ formatCurrency(p.cost_price) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-400">{{ formatCurrency(p.selling_price) }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span :class="['font-mono font-bold', p.low_stock ? 'text-rose-400' : 'text-zinc-200']">{{
                                        p.stock_quantity
                                    }}</span>
                                    <span v-if="p.low_stock" class="ml-1 text-[9px] font-bold text-rose-400">LOW</span>
                                </td>
                                <td class="px-4 py-2.5 text-[11px] text-zinc-400">{{ p.branch_name }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-[10px] font-bold',
                                            p.status === 'Active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-zinc-700 text-zinc-400',
                                        ]"
                                        >{{ p.status }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: BRANCHES                                                    -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'branches'" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="b in branches" :key="b.id" class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-violet-500/20">
                                    <Building2 class="size-4 text-violet-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-zinc-100">{{ b.name }}</p>
                                    <p class="font-mono text-[10px] text-zinc-500">{{ b.code }} · {{ b.type }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-if="b.is_main"
                                    class="rounded border border-violet-500/30 bg-violet-500/10 px-1.5 py-0.5 text-[9px] font-extrabold uppercase text-violet-300"
                                    >HQ</span
                                >
                                <span
                                    :class="[
                                        'rounded px-2 py-0.5 text-[10px] font-bold',
                                        b.status === 'Active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300',
                                    ]"
                                    >{{ b.status }}</span
                                >
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg bg-zinc-800/60 p-2.5 text-center">
                                <p class="font-mono font-extrabold text-emerald-400">{{ formatCurrency(b.today_sales) }}</p>
                                <p class="text-[10px] text-zinc-500">Today Sales</p>
                            </div>
                            <div class="rounded-lg bg-zinc-800/60 p-2.5 text-center">
                                <p class="font-mono font-extrabold text-zinc-200">{{ b.today_tx }}</p>
                                <p class="text-[10px] text-zinc-500">Today Txns</p>
                            </div>
                        </div>
                        <div class="space-y-0.5 text-[11px] text-zinc-500">
                            <p>📍 {{ b.location }}</p>
                            <p>📞 {{ b.phone }}</p>
                            <p>
                                👤 Manager: <span class="text-zinc-300">{{ b.manager }}</span>
                            </p>
                            <p>🕐 {{ b.operating_hours }}</p>
                        </div>
                    </div>
                    <div
                        v-if="branches.length === 0"
                        class="col-span-3 rounded-xl border border-zinc-800 bg-zinc-900 py-12 text-center text-sm text-zinc-600"
                    >
                        No branches found.
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: USERS / STAFF                                               -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'users'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Staff Accounts</h3>
                        <p class="text-[11px] text-zinc-500">{{ kpis.total_users }} total users in this organization</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-violet-700"
                        @click="addUserModal = true"
                    >
                        <UserPlus class="size-3.5" />
                        Add Staff
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                    <div v-if="users.length === 0" class="py-12 text-center text-sm text-zinc-600">No staff users yet.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Name</th>
                                    <th class="px-4 py-3 font-semibold">Email</th>
                                    <th class="px-4 py-3 font-semibold">Role</th>
                                    <th class="px-4 py-3 font-semibold">Phone</th>
                                    <th class="px-4 py-3 text-center font-semibold">Status</th>
                                    <th class="px-4 py-3 font-semibold">Joined</th>
                                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800">
                                <tr v-for="u in users" :key="u.id" class="transition-colors hover:bg-zinc-800/40">
                                    <td class="px-5 py-2.5">
                                        <p class="font-bold text-zinc-100">{{ u.name }}</p>
                                        <p class="text-[10px] text-zinc-500">Last: {{ u.last_login }}</p>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-zinc-400">{{ u.email }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="['rounded border px-2 py-0.5 text-[10px] font-bold', roleColor(u.role)]">{{ u.role }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-zinc-400">{{ u.phone ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span
                                            :class="[
                                                'rounded px-2 py-0.5 text-[10px] font-bold',
                                                u.status === 'Active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300',
                                            ]"
                                        >
                                            {{ u.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-zinc-500">{{ u.created_at }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                @click="openEditUser(u)"
                                                class="rounded bg-zinc-800 px-2 py-1 text-[10px] text-zinc-300 transition-colors hover:bg-zinc-700 hover:text-white"
                                            >
                                                <UserCog class="inline size-3" /> Edit
                                            </button>
                                            <button
                                                type="button"
                                                @click="handleResetPassword(u)"
                                                class="rounded bg-amber-600/20 px-2 py-1 text-[10px] text-amber-300 transition-colors hover:bg-amber-600/40"
                                            >
                                                <Key class="inline size-3" /> Reset
                                            </button>
                                            <button
                                                v-if="u.role !== 'Owner'"
                                                type="button"
                                                @click="openDeleteUser(u)"
                                                class="rounded bg-rose-600/10 px-2 py-1 text-[10px] text-rose-400 transition-colors hover:bg-rose-600/30"
                                            >
                                                <Trash2 class="inline size-3" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: EXPENSES                                                    -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'expenses'" class="space-y-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-[11px] font-semibold uppercase text-zinc-500">30-Day Total</p>
                        <p class="mt-1 font-mono text-2xl font-extrabold text-rose-400">{{ formatCurrency(totalExpenses30) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-[11px] font-semibold uppercase text-zinc-500">Records</p>
                        <p class="mt-1 font-mono text-2xl font-extrabold text-zinc-100">{{ expenses.length }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-3">
                        <h3 class="text-sm font-bold text-zinc-100">Expense Ledger (Last 30 Days)</h3>
                    </div>
                    <div v-if="expenses.length === 0" class="py-12 text-center text-sm text-zinc-600">No expenses recorded.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Description</th>
                                    <th class="px-4 py-3 font-semibold">Category</th>
                                    <th class="px-4 py-3 text-right font-semibold">Amount</th>
                                    <th class="px-4 py-3 font-semibold">Date</th>
                                    <th class="px-4 py-3 font-semibold">Recorded By</th>
                                    <th class="px-4 py-3 font-semibold">Payment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800">
                                <tr v-for="e in expenses" :key="e.id" class="hover:bg-zinc-800/40">
                                    <td class="px-5 py-2.5 font-semibold text-zinc-200">{{ e.description }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] text-zinc-300">{{ e.category }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-rose-400">{{ formatCurrency(e.amount) }}</td>
                                    <td class="px-4 py-2.5 text-zinc-400">{{ e.date }}</td>
                                    <td class="px-4 py-2.5 text-zinc-500">{{ e.recorded_by }}</td>
                                    <td class="px-4 py-2.5 text-zinc-400">{{ e.payment_method }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: PURCHASES                                                   -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'purchases'" class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-3">
                    <h3 class="text-sm font-bold text-zinc-100">Purchase Orders (Last 20)</h3>
                </div>
                <div v-if="purchases.length === 0" class="py-12 text-center text-sm text-zinc-600">No purchase orders yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">LPO #</th>
                                <th class="px-4 py-3 font-semibold">Supplier</th>
                                <th class="px-4 py-3 font-semibold">Branch</th>
                                <th class="px-4 py-3 text-right font-semibold">Total</th>
                                <th class="px-4 py-3 text-right font-semibold">Paid</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Payment</th>
                                <th class="px-4 py-3 font-semibold">Ordered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="p in purchases" :key="p.id" class="hover:bg-zinc-800/40">
                                <td class="px-5 py-2.5 font-mono font-bold text-violet-300">{{ p.lpo_number ?? 'N/A' }}</td>
                                <td class="px-4 py-2.5 text-zinc-200">{{ p.supplier }}</td>
                                <td class="px-4 py-2.5 text-zinc-400">{{ p.branch }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-zinc-100">{{ formatCurrency(p.total_amount) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-emerald-400">{{ formatCurrency(p.amount_paid) }}</td>
                                <td class="px-4 py-2.5">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-[10px] font-bold',
                                            p.status === 'Received' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300',
                                        ]"
                                        >{{ p.status }}</span
                                    >
                                </td>
                                <td class="px-4 py-2.5">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-[10px] font-bold',
                                            p.payment_status === 'Paid' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300',
                                        ]"
                                        >{{ p.payment_status }}</span
                                    >
                                </td>
                                <td class="px-4 py-2.5 text-zinc-500">{{ p.ordered_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: CUSTOMERS                                                   -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'customers'" class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-3">
                    <h3 class="text-sm font-bold text-zinc-100">Customer Accounts ({{ kpis.total_customers }} total)</h3>
                </div>
                <div v-if="customers.length === 0" class="py-12 text-center text-sm text-zinc-600">No customers registered.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold">Phone</th>
                                <th class="px-4 py-3 font-semibold">Email</th>
                                <th class="px-4 py-3 text-right font-semibold">Credit Limit</th>
                                <th class="px-4 py-3 text-right font-semibold">Balance Due</th>
                                <th class="px-4 py-3 text-center font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="c in customers" :key="c.id" class="hover:bg-zinc-800/40">
                                <td class="px-5 py-2.5 font-semibold text-zinc-100">{{ c.name }}</td>
                                <td class="px-4 py-2.5 font-mono text-zinc-400">{{ c.phone ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-zinc-500">{{ c.email ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-zinc-300">{{ formatCurrency(c.credit_limit) }}</td>
                                <td
                                    class="px-4 py-2.5 text-right font-mono"
                                    :class="c.credit_balance > 0 ? 'font-bold text-rose-400' : 'text-zinc-400'"
                                >
                                    {{ formatCurrency(c.credit_balance) }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span :class="['rounded px-2 py-0.5 text-[10px] font-bold', statusClass(c.status)]">{{ c.status }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-zinc-500">{{ c.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: SUPPLIERS                                                   -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'suppliers'" class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-3">
                    <h3 class="text-sm font-bold text-zinc-100">Supplier Directory ({{ kpis.total_suppliers }} total)</h3>
                </div>
                <div v-if="suppliers.length === 0" class="py-12 text-center text-sm text-zinc-600">No suppliers registered.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Supplier</th>
                                <th class="px-4 py-3 font-semibold">Contact</th>
                                <th class="px-4 py-3 font-semibold">Phone</th>
                                <th class="px-4 py-3 font-semibold">Category</th>
                                <th class="px-4 py-3 text-right font-semibold">Balance Due</th>
                                <th class="px-4 py-3 text-center font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="s in suppliers" :key="s.id" class="hover:bg-zinc-800/40">
                                <td class="px-5 py-2.5 font-semibold text-zinc-100">{{ s.name }}</td>
                                <td class="px-4 py-2.5 text-zinc-400">{{ s.contact ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-mono text-zinc-400">{{ s.phone ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] text-zinc-300">{{ s.category ?? 'General' }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono" :class="s.balance_due > 0 ? 'font-bold text-rose-400' : 'text-zinc-400'">
                                    {{ formatCurrency(s.balance_due) }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span :class="['rounded px-2 py-0.5 text-[10px] font-bold', statusClass(s.status)]">{{ s.status }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: SETTINGS                                                    -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'settings'" class="space-y-5">
                <h3 class="text-sm font-bold text-zinc-100">Tenant Configuration</h3>

                <!-- Profile -->
                <div class="space-y-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Organization Profile</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Business Name</label>
                            <input
                                v-model="settingsForm.name"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">KRA PIN</label>
                            <input
                                v-model="settingsForm.kra_pin"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Primary Email</label>
                            <input
                                v-model="settingsForm.email"
                                type="email"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Phone</label>
                            <input
                                v-model="settingsForm.phone"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Country</label>
                            <input
                                v-model="settingsForm.country"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Currency</label>
                            <input
                                v-model="settingsForm.currency"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Subscription -->
                <div class="space-y-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Subscription & Billing</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Plan</label>
                            <select
                                v-model="settingsForm.plan"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            >
                                <option value="Basic">Basic</option>
                                <option value="Pro">Pro</option>
                                <option value="Enterprise">Enterprise</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Billing Cycle</label>
                            <select
                                v-model="settingsForm.billing_cycle"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            >
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Semi-Annual">Semi-Annual</option>
                                <option value="Annual">Annual</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Account Status</label>
                            <select
                                v-model="settingsForm.status"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            >
                                <option value="Trial">Trial</option>
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Branch Limit</label>
                            <input
                                v-model.number="settingsForm.branch_count"
                                type="number"
                                min="1"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-400">Storage Quota (MB)</label>
                            <input
                                v-model.number="settingsForm.storage_quota_mb"
                                type="number"
                                min="512"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        :disabled="settingsProcessing"
                        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2 text-xs font-bold text-white transition-colors hover:bg-violet-700 disabled:opacity-60"
                        @click="handleSaveSettings"
                    >
                        <RefreshCw class="size-3.5" :class="settingsProcessing ? 'animate-spin' : ''" />
                        {{ settingsProcessing ? 'Saving…' : 'Save Settings' }}
                    </button>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: PAYMENT METHODS                                             -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'payments'" class="space-y-5">
                <!-- Header -->
                <div
                    class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-800 bg-zinc-900/90 p-5 backdrop-blur md:flex-row md:items-center"
                >
                    <div class="space-y-1">
                        <h2 class="flex items-center gap-2 text-base font-bold text-zinc-100">
                            <CreditCard class="size-4.5 text-violet-400" />
                            Payment Methods for {{ tenant.name }}
                            <span
                                class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] font-extrabold text-emerald-300"
                            >
                                {{ enabledPaymentCount }} / 5 Active
                            </span>
                        </h2>
                        <p class="max-w-2xl text-xs text-zinc-400">
                            Configure which payment methods this tenant accepts at the POS. M-Pesa credentials override the global .env defaults,
                            enabling per-tenant Daraja integration.
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="savingPayments"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-violet-900/30 transition-all hover:bg-violet-700 disabled:opacity-60"
                        @click="handleSavePayments"
                    >
                        <RefreshCw v-if="savingPayments" class="size-3.5 animate-spin" />
                        <Check v-else class="size-3.5" />
                        {{ savingPayments ? 'Saving…' : 'Save Payment Config' }}
                    </button>
                </div>

                <!-- ── Cash ───────────────────────────────────────────────────── -->
                <div
                    class="overflow-hidden rounded-xl border bg-zinc-900 transition-colors"
                    :class="paymentSettings.cash.enabled ? 'border-emerald-500/30' : 'border-zinc-800 opacity-70'"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b px-5 py-4"
                        :class="paymentSettings.cash.enabled ? 'border-emerald-500/20 bg-emerald-950/10' : 'border-zinc-800'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                :class="
                                    paymentSettings.cash.enabled
                                        ? 'border-emerald-500/40 bg-emerald-600/20 text-emerald-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                <DollarSign class="size-4" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-zinc-100">Cash</p>
                                <p class="text-[11px] text-zinc-400">Physical currency accepted at counter — no credentials required.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="paymentSettings.cash.enabled = !paymentSettings.cash.enabled"
                            class="flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-colors"
                            :class="paymentSettings.cash.enabled ? 'justify-end bg-emerald-600' : 'justify-start bg-zinc-700'"
                        >
                            <div class="size-5 rounded-full bg-white shadow-sm"></div>
                        </button>
                    </div>
                    <div v-if="paymentSettings.cash.enabled" class="px-5 py-3">
                        <p class="flex items-center gap-1.5 text-[11px] text-emerald-400">
                            <CheckCircle2 class="size-3.5" /> Cash is enabled — no extra setup needed.
                        </p>
                    </div>
                </div>

                <!-- ── M-Pesa ──────────────────────────────────────────────────── -->
                <div
                    class="overflow-hidden rounded-xl border bg-zinc-900 transition-colors"
                    :class="paymentSettings.mpesa.enabled ? 'border-emerald-500/30' : 'border-zinc-800'"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b px-5 py-4"
                        :class="paymentSettings.mpesa.enabled ? 'border-emerald-500/20 bg-emerald-950/10' : 'border-zinc-800'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                :class="
                                    paymentSettings.mpesa.enabled
                                        ? 'border-emerald-500/40 bg-emerald-600/20 text-emerald-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                <Phone class="size-4" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-zinc-100">M-Pesa STK Push</p>
                                <p class="text-[11px] text-zinc-400">
                                    Safaricom Daraja API — auto-prompts customer phone for payment. Leave credentials blank to use global .env
                                    fallback.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="paymentSettings.mpesa.enabled = !paymentSettings.mpesa.enabled"
                            class="flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-colors"
                            :class="paymentSettings.mpesa.enabled ? 'justify-end bg-emerald-600' : 'justify-start bg-zinc-700'"
                        >
                            <div class="size-5 rounded-full bg-white shadow-sm"></div>
                        </button>
                    </div>

                    <div v-if="paymentSettings.mpesa.enabled" class="space-y-4 px-5 py-4">
                        <!-- Shortcode type toggle -->
                        <div class="flex items-center gap-2">
                            <p class="mr-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">Account Type:</p>
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors"
                                :class="
                                    paymentSettings.mpesa.type === 'paybill'
                                        ? 'border-emerald-500/40 bg-emerald-600/20 text-emerald-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-zinc-200'
                                "
                                @click="paymentSettings.mpesa.type = 'paybill'"
                            >
                                Paybill
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors"
                                :class="
                                    paymentSettings.mpesa.type === 'till'
                                        ? 'border-emerald-500/40 bg-emerald-600/20 text-emerald-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-zinc-200'
                                "
                                @click="paymentSettings.mpesa.type = 'till'"
                            >
                                Till Number
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="flex items-center gap-1 text-[11px] font-semibold text-zinc-400">
                                    Shortcode / {{ paymentSettings.mpesa.type === 'till' ? 'Till No.' : 'Paybill No.' }}
                                    <span class="font-mono text-[9px] text-zinc-600">(e.g. 174379)</span>
                                </label>
                                <input
                                    v-model="paymentSettings.mpesa.shortcode"
                                    placeholder="e.g. 600000"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-zinc-400">Passkey (Lipa Na M-Pesa)</label>
                                <input
                                    v-model="paymentSettings.mpesa.passkey"
                                    type="password"
                                    placeholder="bfb279f9…"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-zinc-400">Consumer Key</label>
                                <input
                                    v-model="paymentSettings.mpesa.consumer_key"
                                    placeholder="Daraja app consumer key"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-zinc-400">Consumer Secret</label>
                                <input
                                    v-model="paymentSettings.mpesa.consumer_secret"
                                    type="password"
                                    placeholder="Daraja app consumer secret"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div class="flex items-start gap-2 rounded-lg border border-amber-500/20 bg-amber-950/10 px-3.5 py-2.5">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0 text-amber-400" />
                            <p class="text-[11px] leading-relaxed text-amber-300">
                                Leave all fields blank to inherit the system-wide Daraja credentials from
                                <code class="font-mono text-amber-400">.env</code>. Filling any field here overrides the global config for this tenant
                                only.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── Card / POS Machine ─────────────────────────────────────── -->
                <div
                    class="overflow-hidden rounded-xl border bg-zinc-900 transition-colors"
                    :class="paymentSettings.card.enabled ? 'border-sky-500/30' : 'border-zinc-800'"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b px-5 py-4"
                        :class="paymentSettings.card.enabled ? 'border-sky-500/20 bg-sky-950/10' : 'border-zinc-800'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                :class="
                                    paymentSettings.card.enabled
                                        ? 'border-sky-500/40 bg-sky-600/20 text-sky-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                <CreditCard class="size-4" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-zinc-100">Card / POS Machine</p>
                                <p class="text-[11px] text-zinc-400">Visa, Mastercard, Airtel Money or any card swipe POS terminal at checkout.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="paymentSettings.card.enabled = !paymentSettings.card.enabled"
                            class="flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-colors"
                            :class="paymentSettings.card.enabled ? 'justify-end bg-sky-600' : 'justify-start bg-zinc-700'"
                        >
                            <div class="size-5 rounded-full bg-white shadow-sm"></div>
                        </button>
                    </div>
                    <div v-if="paymentSettings.card.enabled" class="space-y-3 px-5 py-4">
                        <div class="max-w-xs space-y-1">
                            <label class="text-[11px] font-semibold text-zinc-400"
                                >Card Provider / Terminal Label <span class="text-zinc-600">(optional)</span></label
                            >
                            <input
                                v-model="paymentSettings.card.provider"
                                placeholder="e.g. Equity, NCBA, Visa"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 placeholder-zinc-600 focus:border-sky-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- ── Bank Transfer ───────────────────────────────────────────── -->
                <div
                    class="overflow-hidden rounded-xl border bg-zinc-900 transition-colors"
                    :class="paymentSettings.bank.enabled ? 'border-violet-500/30' : 'border-zinc-800'"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b px-5 py-4"
                        :class="paymentSettings.bank.enabled ? 'border-violet-500/20 bg-violet-950/10' : 'border-zinc-800'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                :class="
                                    paymentSettings.bank.enabled
                                        ? 'border-violet-500/40 bg-violet-600/20 text-violet-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                <BookOpen class="size-4" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-zinc-100">Bank Transfer / EFT</p>
                                <p class="text-[11px] text-zinc-400">
                                    Customer pays via bank transfer, RTGS, or direct deposit into the business account.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="paymentSettings.bank.enabled = !paymentSettings.bank.enabled"
                            class="flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-colors"
                            :class="paymentSettings.bank.enabled ? 'justify-end bg-violet-600' : 'justify-start bg-zinc-700'"
                        >
                            <div class="size-5 rounded-full bg-white shadow-sm"></div>
                        </button>
                    </div>
                    <div v-if="paymentSettings.bank.enabled" class="space-y-3 px-5 py-4">
                        <div class="grid max-w-lg grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-zinc-400">Bank Name</label>
                                <input
                                    v-model="paymentSettings.bank.bank_name"
                                    placeholder="e.g. KCB, Equity, Co-op"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 placeholder-zinc-600 focus:border-violet-500 focus:outline-none"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-zinc-400">Account Number</label>
                                <input
                                    v-model="paymentSettings.bank.account_number"
                                    placeholder="e.g. 1234567890"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-100 placeholder-zinc-600 focus:border-violet-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Store Credit ────────────────────────────────────────────── -->
                <div
                    class="overflow-hidden rounded-xl border bg-zinc-900 transition-colors"
                    :class="paymentSettings.credit.enabled ? 'border-amber-500/30' : 'border-zinc-800'"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b px-5 py-4"
                        :class="paymentSettings.credit.enabled ? 'border-amber-500/20 bg-amber-950/10' : 'border-zinc-800'"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                :class="
                                    paymentSettings.credit.enabled
                                        ? 'border-amber-500/40 bg-amber-600/20 text-amber-300'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                <CircleDollarSign class="size-4" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-zinc-100">Store Credit / Customer Debt</p>
                                <p class="text-[11px] text-zinc-400">
                                    Allow trusted customers to buy on credit. Transactions recorded against customer balance ledger.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="paymentSettings.credit.enabled = !paymentSettings.credit.enabled"
                            class="flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-colors"
                            :class="paymentSettings.credit.enabled ? 'justify-end bg-amber-600' : 'justify-start bg-zinc-700'"
                        >
                            <div class="size-5 rounded-full bg-white shadow-sm"></div>
                        </button>
                    </div>
                    <div v-if="paymentSettings.credit.enabled" class="space-y-3 px-5 py-4">
                        <div class="max-w-xs space-y-1">
                            <label class="text-[11px] font-semibold text-zinc-400">Default Credit Limit per Customer ({{ tenant.currency }})</label>
                            <input
                                v-model.number="paymentSettings.credit.credit_limit"
                                type="number"
                                min="0"
                                placeholder="0 = unlimited"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-100 placeholder-zinc-600 focus:border-amber-500 focus:outline-none"
                            />
                            <p class="text-[10px] text-zinc-600">Set to 0 to allow unlimited credit (not recommended).</p>
                        </div>
                    </div>
                </div>

                <!-- Sticky Bottom Save Bar -->
                <div
                    class="sticky bottom-4 flex items-center justify-between gap-4 rounded-xl border border-zinc-700/80 bg-zinc-900/95 p-4 shadow-2xl backdrop-blur-md"
                >
                    <div class="flex items-center gap-2 text-xs text-zinc-300">
                        <span class="size-2 animate-pulse rounded-full bg-violet-500"></span>
                        <span
                            ><strong>{{ enabledPaymentCount }}</strong> payment method{{ enabledPaymentCount !== 1 ? 's' : '' }} currently enabled for
                            this tenant.</span
                        >
                    </div>
                    <button
                        type="button"
                        :disabled="savingPayments"
                        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-6 py-2.5 text-xs font-bold text-white shadow-lg shadow-violet-900/30 transition-all hover:bg-violet-700 disabled:opacity-60"
                        @click="handleSavePayments"
                    >
                        <RefreshCw v-if="savingPayments" class="size-3.5 animate-spin" />
                        <Check v-else class="size-3.5" />
                        {{ savingPayments ? 'Saving Changes…' : 'Save Payment Config' }}
                    </button>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════ -->
            <!-- TAB: PAGES & MODULES ACCESS MANAGEMENT                           -->
            <!-- ════════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'modules'" class="space-y-6">
                <!-- Header / Save Banner -->
                <div
                    class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-800 bg-zinc-900/90 p-4 backdrop-blur sm:p-5 md:flex-row md:items-center"
                >
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="flex items-center gap-2 text-base font-bold text-zinc-100">
                                <Layers class="size-4.5 text-violet-400" />
                                Enabled Pages & Modules for {{ tenant.name }}
                            </h2>
                            <span
                                class="rounded-full border border-violet-500/40 bg-violet-500/10 px-2 py-0.5 font-mono text-[10px] font-extrabold text-violet-300"
                            >
                                {{ selectedModules.length }} / {{ ALL_SYSTEM_MODULES.length }} Pages Active
                            </span>
                        </div>
                        <p class="max-w-2xl text-xs text-zinc-400">
                            Customize which pages this business can see and access. Turn modules ON or OFF depending on the business type and
                            operation size. Modules disabled here will not appear in the tenant's sidebar and are blocked at the server level.
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-300 transition-colors hover:text-white"
                            @click="selectAllModules"
                        >
                            Select All
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-300 transition-colors hover:text-white"
                            @click="deselectOptionalModules"
                        >
                            Core Only
                        </button>
                        <button
                            type="button"
                            :disabled="savingModules"
                            class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2 text-xs font-bold text-white shadow-lg shadow-violet-900/30 transition-all hover:bg-violet-700 disabled:opacity-60"
                            @click="handleSaveModules"
                        >
                            <RefreshCw v-if="savingModules" class="size-3.5 animate-spin" />
                            <Check v-else class="size-3.5" />
                            {{ savingModules ? 'Saving Changes…' : 'Save Enabled Pages' }}
                        </button>
                    </div>
                </div>

                <!-- One-Click Presets by Business Type -->
                <div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-200">
                                <span>🏢</span> Recommended Presets by Business Type
                            </h3>
                            <p class="text-[11px] text-zinc-400">
                                Click a business preset to instantly select the ideal page package for that vertical.
                            </p>
                        </div>
                        <span class="hidden font-mono text-[10px] text-zinc-500 sm:inline"
                            >Current Tenant Type: <strong class="text-violet-300">{{ tenant.business_type }}</strong></span
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
                        <button
                            v-for="(preset, typeKey) in BUSINESS_TYPE_PRESETS"
                            :key="typeKey"
                            type="button"
                            class="group flex flex-col justify-between rounded-lg border p-2.5 text-left transition-all"
                            :class="[
                                tenant.business_type === typeKey
                                    ? 'border-violet-500/50 bg-violet-950/20 text-violet-200 ring-1 ring-violet-500/30'
                                    : 'border-zinc-800 bg-zinc-800/40 text-zinc-300 hover:border-zinc-700 hover:bg-zinc-800',
                            ]"
                            @click="applyTypePreset(typeKey as string)"
                        >
                            <div class="mb-1 flex items-center gap-1.5">
                                <span class="text-base">{{ preset.icon }}</span>
                                <span class="text-xs font-bold group-hover:text-white">{{ preset.label }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] text-zinc-500">
                                <span>{{ preset.modules.length }} modules</span>
                                <span class="font-semibold text-violet-400 opacity-0 transition-opacity group-hover:opacity-100">Apply →</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Presets by Business Scale / Size -->
                <div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 sm:p-5">
                    <div>
                        <h3 class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-200">
                            <span>📐</span> Presets by Business Size / Tier
                        </h3>
                        <p class="text-[11px] text-zinc-400">Configure based on how large or established the tenant is.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div
                            v-for="scale in SIZE_PRESETS"
                            :key="scale.id"
                            class="flex flex-col justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-800/30 p-3.5 transition-all hover:border-zinc-700"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-zinc-100">{{ scale.name }}</span>
                                    <span class="rounded border border-zinc-700 bg-zinc-800 px-1.5 py-0.5 font-mono text-[9px] text-zinc-300">{{
                                        scale.badge
                                    }}</span>
                                </div>
                                <p class="text-[11px] leading-relaxed text-zinc-400">{{ scale.description }}</p>
                            </div>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 py-1.5 text-xs font-semibold text-zinc-200 transition-colors hover:bg-zinc-700"
                                @click="applySizePreset(scale)"
                            >
                                Apply {{ scale.modules.length }} Pages
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Granular Page / Module List by Category -->
                <div class="space-y-6">
                    <div v-for="(modules, catName) in moduleCategories" :key="catName" class="space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-800 pb-1">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-400">
                                <span>{{ catName }}</span>
                                <span class="font-mono text-[10px] text-zinc-600"
                                    >({{ modules.filter((m) => isModuleSelected(m.key)).length }}/{{ modules.length }} active)</span
                                >
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="mod in modules"
                                :key="mod.key"
                                class="flex cursor-pointer select-none flex-col justify-between gap-3 rounded-xl border p-4 transition-all"
                                :class="[
                                    isModuleSelected(mod.key)
                                        ? 'border-violet-500/40 bg-violet-950/15 shadow-sm'
                                        : 'border-zinc-800 bg-zinc-900/40 opacity-70 hover:border-zinc-700 hover:opacity-100',
                                ]"
                                @click="toggleModule(mod.key)"
                            >
                                <div class="space-y-2">
                                    <!-- Header Row -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="flex size-8 shrink-0 items-center justify-center rounded-lg border"
                                                :class="[
                                                    isModuleSelected(mod.key)
                                                        ? 'border-violet-500/40 bg-violet-600/20 text-violet-300'
                                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500',
                                                ]"
                                            >
                                                <component :is="mod.icon" class="size-4" />
                                            </div>
                                            <div>
                                                <h4 class="flex items-center gap-1.5 text-xs font-bold text-zinc-100">
                                                    {{ mod.label }}
                                                    <span
                                                        v-if="mod.route"
                                                        class="py-0.2 rounded border border-zinc-700 bg-zinc-800 px-1 font-mono text-[9px] text-zinc-400"
                                                    >
                                                        {{ mod.route }}
                                                    </span>
                                                </h4>
                                                <p class="text-[10px] text-zinc-500">
                                                    Key: <span class="font-mono text-zinc-400">{{ mod.key }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Toggle Switch Pill -->
                                        <div
                                            class="flex h-5 w-10 shrink-0 items-center rounded-full p-0.5 transition-colors"
                                            :class="isModuleSelected(mod.key) ? 'justify-end bg-violet-600' : 'justify-start bg-zinc-700'"
                                        >
                                            <div class="size-4 rounded-full bg-white shadow-sm"></div>
                                        </div>
                                    </div>

                                    <!-- Description -->
                                    <p class="text-[11px] leading-relaxed text-zinc-400">
                                        {{ mod.description }}
                                    </p>
                                </div>

                                <!-- Footer details -->
                                <div class="flex items-center justify-between border-t border-zinc-800/80 pt-2 text-[10px]">
                                    <span class="text-zinc-500"
                                        >Ideal for: <span class="text-zinc-300">{{ mod.recommendedFor }}</span></span
                                    >
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[9px] font-bold"
                                        :class="isModuleSelected(mod.key) ? 'bg-emerald-500/10 text-emerald-300' : 'bg-zinc-800 text-zinc-500'"
                                    >
                                        {{ isModuleSelected(mod.key) ? '● Active' : '○ Disabled' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Save Action Bar -->
                <div
                    class="sticky bottom-4 flex items-center justify-between gap-4 rounded-xl border border-zinc-700/80 bg-zinc-900/95 p-4 shadow-2xl backdrop-blur-md"
                >
                    <div class="flex items-center gap-2 text-xs text-zinc-300">
                        <span class="size-2 animate-pulse rounded-full bg-violet-500"></span>
                        <span
                            ><strong>{{ selectedModules.length }}</strong> pages currently enabled for this tenant.</span
                        >
                    </div>
                    <button
                        type="button"
                        :disabled="savingModules"
                        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-6 py-2.5 text-xs font-bold text-white shadow-lg shadow-violet-900/30 transition-all hover:bg-violet-700 disabled:opacity-60"
                        @click="handleSaveModules"
                    >
                        <RefreshCw v-if="savingModules" class="size-3.5 animate-spin" />
                        <Check v-else class="size-3.5" />
                        {{ savingModules ? 'Saving Changes…' : 'Save Enabled Pages' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Add User Modal ───────────────────────────────────────────────── -->
        <Dialog :open="addUserModal" @update:open="(v) => (addUserModal = v)">
            <DialogContent class="border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <UserPlus class="size-4.5 text-violet-400" /> Add Staff Account
                    </DialogTitle>
                    <DialogDescription class="text-xs text-zinc-400">Create a new user account for {{ tenant.name }}.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-zinc-400">Full Name *</label>
                            <input
                                v-model="addUserForm.name"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-zinc-400">Role *</label>
                            <select
                                v-model="addUserForm.role"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            >
                                <option value="Owner">Owner</option>
                                <option value="Administrator">Administrator</option>
                                <option value="Manager">Manager</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Inventory Manager">Inventory Manager</option>
                                <option value="Accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-zinc-400">Email *</label>
                            <input
                                v-model="addUserForm.email"
                                type="email"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-zinc-400">Phone</label>
                            <input
                                v-model="addUserForm.phone"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <label class="text-zinc-400">Initial Password *</label>
                            <input
                                v-model="addUserForm.password"
                                type="text"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 font-mono font-bold text-emerald-400 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-xs text-zinc-400 transition-colors hover:text-zinc-100"
                        @click="addUserModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="addUserProcessing"
                        class="rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-violet-700 disabled:opacity-60"
                        @click="handleAddUser"
                    >
                        {{ addUserProcessing ? 'Adding…' : 'Add Staff Account' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Edit User Modal ──────────────────────────────────────────────── -->
        <Dialog :open="editUserModal" @update:open="(v) => (editUserModal = v)">
            <DialogContent class="border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <UserCog class="size-4.5 text-violet-400" /> Edit {{ editUserTarget?.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only">Edit user details</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-zinc-400">Name</label>
                            <input
                                v-model="editUserForm.name"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-zinc-400">Role</label>
                            <select
                                v-model="editUserForm.role"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            >
                                <option value="Owner">Owner</option>
                                <option value="Administrator">Administrator</option>
                                <option value="Manager">Manager</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Inventory Manager">Inventory Manager</option>
                                <option value="Accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-zinc-400">Phone</label>
                            <input
                                v-model="editUserForm.phone"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-zinc-400">Status</label>
                            <select
                                v-model="editUserForm.status"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-zinc-100 focus:border-violet-500 focus:outline-none"
                            >
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <button type="button" class="rounded-lg px-4 py-2 text-xs text-zinc-400 hover:text-zinc-100" @click="editUserModal = false">
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="editUserProcessing"
                        class="rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-violet-700 disabled:opacity-60"
                        @click="handleEditUser"
                    >
                        {{ editUserProcessing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Delete User Confirm ──────────────────────────────────────────── -->
        <ConfirmDialog
            v-model:open="deleteUserConfirmOpen"
            title="Remove Staff Account"
            description="Are you sure you want to permanently remove this user from the tenant?"
            :item-name="userToDelete?.name"
            warning-note="The user's transaction history will be retained but they will lose all access."
            confirm-text="Remove Account"
            :loading="isDeletingUser"
            @confirm="confirmDeleteUser"
        />
    </SuperAdminLayout>
</template>
