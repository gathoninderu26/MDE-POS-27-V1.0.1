<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import type { PlatformTenant, SubscriptionPlan, TenantTelemetry } from '@/composables/useSuperAdmin';
import { useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    Banknote,
    Building2,
    Check,
    CheckCircle2,
    Copy,
    Cpu,
    CreditCard,
    Database,
    ExternalLink,
    Eye,
    FileCheck,
    Globe,
    HardDrive,
    Key,
    Layers,
    PauseCircle,
    PlayCircle,
    Plus,
    Radio,
    RefreshCw,
    Search,
    Settings,
    ShieldCheck,
    Smartphone,
    Trash2,
    Users,
    Wallet,
    Wifi,
    Zap,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    tenants?: any[];
    flash?: {
        success?: string;
        createdCredentials?: {
            tenant_name: string;
            subdomain: string;
            subdomain_url: string;
            owner_email: string;
            owner_password: string;
        };
    };
}>();

const { subscriptionPlans, getTenantTelemetry } = useSuperAdmin();
const { success, warning, error, info } = useToast();

const searchQuery = ref('');
const planFilter = ref('All');
const statusFilter = ref('All');

// Registration Modal
const registerModal = ref(false);
const registerTab = ref<'profile' | 'payments' | 'devices' | 'network'>('profile');
const isRegistering = ref(false);

// Post-creation Credentials Modal
const createdCredentialsModal = ref(false);
const createdCredentialsData = ref<{
    tenant_id?: number | string;
    tenant_name: string;
    subdomain: string;
    subdomain_url: string;
    owner_email: string;
    owner_password: string;
} | null>(null);

const generateRandomPassword = () => {
    return 'Owner@' + Math.floor(1000 + Math.random() * 9000) + '!';
};

const newTenantForm = reactive({
    name: '',
    slug: '',
    kraPin: '',
    email: '',
    phone: '',
    country: 'Kenya',
    currency: 'KES',
    businessType: 'Supermarket',
    plan: 'Pro' as SubscriptionPlan,
    billingCycle: 'Monthly',
    branchCount: 1,
    userCount: 5,
    ownerEmail: '',
    ownerPassword: generateRandomPassword(),
    // Store Payment Channels Setup
    enableCash: true,
    cashDrawerLabel: 'Main Till Drawer',
    enableMpesaTill: true,
    mpesaTillNumber: '',
    mpesaStoreName: '',
    enableMpesaPaybill: false,
    mpesaPaybillNumber: '',
    mpesaPaybillAccount: '',
    enableCard: false,
    cardTerminalId: '',
    cardBankName: 'Equity Bank',
    enableBank: false,
    bankName: 'KCB Bank',
    bankAccountNumber: '',
    enableCredit: false,
    // Device & Hardware binding
    selectedDeviceTypes: ['POS Terminal', 'Desktop PC'],
    requireDeviceBinding: true,
    posHardwareCount: 2,
    blockVpn: true,
    requireStoreWifi: true,
    primaryStoreWifiSSID: '',
    blockHotspot: true,
    cellularPolicy: 'field_roles_only',
    singleSessionPerCashier: true,
});

watch(
    () => newTenantForm.name,
    (newName) => {
        if (newName) {
            newTenantForm.slug = newName
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '');
        }
    },
);

watch(
    () => newTenantForm.email,
    (newEmail) => {
        if (!newTenantForm.ownerEmail) {
            newTenantForm.ownerEmail = newEmail;
        }
    },
);

const regeneratePassword = () => {
    newTenantForm.ownerPassword = generateRandomPassword();
};

const copyCredentials = () => {
    if (!createdCredentialsData.value) return;
    const text = `Business: ${createdCredentialsData.value.tenant_name}\nSubdomain URL: ${createdCredentialsData.value.subdomain_url}\nOwner Email: ${createdCredentialsData.value.owner_email}\nPassword: ${createdCredentialsData.value.owner_password}`;
    navigator.clipboard.writeText(text);
    success('Copied!', 'Tenant subdomain and owner credentials copied to clipboard.');
};

const copyTenantUrl = (url?: string) => {
    if (!url) return;
    navigator.clipboard.writeText(url);
    success('Copied!', 'Tenant login URL copied to clipboard.');
};

const handleLaunchWorkspace = () => {
    const id = createdCredentialsData.value?.tenant_id;
    if (!id) return;
    createdCredentialsModal.value = false;
    router.post(
        `/super-admin/tenants/${id}/impersonate`,
        {},
        {
            onError: () => error('Launch Failed', 'Could not open the tenant workspace. Please try again.'),
        },
    );
};

const toggleDeviceType = (type: string) => {
    const idx = newTenantForm.selectedDeviceTypes.indexOf(type);
    if (idx === -1) {
        newTenantForm.selectedDeviceTypes.push(type);
    } else {
        newTenantForm.selectedDeviceTypes.splice(idx, 1);
    }
};

const handleRegisterTenant = () => {
    if (!newTenantForm.name || !newTenantForm.email) {
        warning('Required Fields', 'Please enter tenant organization name and primary email.');
        return;
    }
    isRegistering.value = true;
    router.post(
        '/super-admin/tenants',
        {
            name: newTenantForm.name,
            slug: newTenantForm.slug || undefined,
            kra_pin: newTenantForm.kraPin,
            email: newTenantForm.email,
            phone: newTenantForm.phone,
            country: newTenantForm.country,
            currency: newTenantForm.currency,
            business_type: newTenantForm.businessType,
            plan: newTenantForm.plan,
            billing_cycle: newTenantForm.billingCycle,
            branch_count: newTenantForm.branchCount,
            user_count: newTenantForm.userCount,
            owner_email: newTenantForm.ownerEmail || newTenantForm.email,
            owner_password: newTenantForm.ownerPassword || undefined,
            // Store Payment Channels Setup
            enable_cash: newTenantForm.enableCash,
            cash_drawer_label: newTenantForm.cashDrawerLabel,
            enable_mpesa_till: newTenantForm.enableMpesaTill,
            mpesa_till_number: newTenantForm.mpesaTillNumber || undefined,
            mpesa_store_name: newTenantForm.mpesaStoreName || undefined,
            enable_mpesa_paybill: newTenantForm.enableMpesaPaybill,
            mpesa_paybill_number: newTenantForm.mpesaPaybillNumber || undefined,
            mpesa_paybill_account: newTenantForm.mpesaPaybillAccount || undefined,
            enable_card: newTenantForm.enableCard,
            card_terminal_id: newTenantForm.cardTerminalId || undefined,
            card_bank_name: newTenantForm.cardBankName || undefined,
            enable_bank: newTenantForm.enableBank,
            bank_name: newTenantForm.bankName || undefined,
            bank_account_number: newTenantForm.bankAccountNumber || undefined,
            enable_credit: newTenantForm.enableCredit,
            // Authorized Devices & Hardware binding
            selected_device_types: newTenantForm.selectedDeviceTypes,
            require_device_binding: newTenantForm.requireDeviceBinding,
            pos_hardware_count: newTenantForm.posHardwareCount,
            single_session_per_cashier: newTenantForm.singleSessionPerCashier,
            // Network & Connection security
            block_vpn: newTenantForm.blockVpn,
            require_store_wifi: newTenantForm.requireStoreWifi,
            primary_store_wifi_ssid: newTenantForm.primaryStoreWifiSSID,
            block_hotspot: newTenantForm.blockHotspot,
            cellular_policy: newTenantForm.cellularPolicy,
        },
        {
            onSuccess: (page: any) => {
                registerModal.value = false;
                const creds = page?.props?.flash?.createdCredentials || (props.flash as any)?.createdCredentials;
                if (creds) {
                    createdCredentialsData.value = creds;
                    createdCredentialsModal.value = true;
                }
                success('Tenant Registered', `${newTenantForm.name} registered with dedicated subdomain and owner login.`);
                newTenantForm.name = '';
                newTenantForm.slug = '';
                newTenantForm.kraPin = '';
                newTenantForm.email = '';
                newTenantForm.phone = '';
                newTenantForm.ownerEmail = '';
                newTenantForm.ownerPassword = generateRandomPassword();
                newTenantForm.primaryStoreWifiSSID = '';
                newTenantForm.enableCash = true;
                newTenantForm.cashDrawerLabel = 'Main Till Drawer';
                newTenantForm.enableMpesaTill = true;
                newTenantForm.mpesaTillNumber = '';
                newTenantForm.mpesaStoreName = '';
                newTenantForm.enableMpesaPaybill = false;
                newTenantForm.mpesaPaybillNumber = '';
                newTenantForm.mpesaPaybillAccount = '';
                newTenantForm.enableCard = false;
                newTenantForm.cardTerminalId = '';
                newTenantForm.enableBank = false;
                newTenantForm.bankName = 'KCB Bank';
                newTenantForm.bankAccountNumber = '';
                newTenantForm.enableCredit = false;
                registerTab.value = 'profile';
            },
            onError: (errs) => error('Registration Failed', Object.values(errs)[0] as string),
            onFinish: () => (isRegistering.value = false),
        },
    );
};

// Live Telemetry Modal
const telemetryModal = ref(false);
const activeTelemetry = ref<TenantTelemetry | null>(null);
const monitoredTenant = ref<PlatformTenant | null>(null);

const openTelemetry = (tenant: any) => {
    monitoredTenant.value = tenant;
    activeTelemetry.value = getTenantTelemetry(tenant.id, tenant);
    telemetryModal.value = true;
};

const handleRefreshTelemetry = () => {
    if (monitoredTenant.value) {
        activeTelemetry.value = getTenantTelemetry(monitoredTenant.value.id, monitoredTenant.value as any);
        info('Telemetry Refreshed', 'Latest data stream and activity buffer synchronized.');
    }
};

const filteredTenants = computed(() => {
    const list = props.tenants ?? [];
    return list.filter((t: any) => {
        const matchSearch =
            !searchQuery.value ||
            t.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            t.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (t.kra_pin ?? '').toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchPlan = planFilter.value === 'All' || t.plan === planFilter.value;
        const matchStatus = statusFilter.value === 'All' || t.status === statusFilter.value;
        return matchSearch && matchPlan && matchStatus;
    });
});

// When Inertia re-renders with flash credentials (after back() redirect), show the modal
watch(
    () => props.flash?.createdCredentials,
    (creds) => {
        if (creds) {
            createdCredentialsData.value = creds;
            createdCredentialsModal.value = true;
        }
    },
    { immediate: true },
);

// Plan upgrade modal
const upgradeModal = ref(false);
const selectedTenant = ref<PlatformTenant | null>(null);
const selectedPlan = ref<SubscriptionPlan>('Pro');

const openUpgradeModal = (tenant: PlatformTenant) => {
    selectedTenant.value = tenant;
    selectedPlan.value = tenant.plan;
    upgradeModal.value = true;
};

const handleUpgrade = () => {
    if (!selectedTenant.value) return;
    router.patch(
        `/super-admin/tenants/${selectedTenant.value.id}`,
        { plan: selectedPlan.value },
        {
            onSuccess: () => {
                success('Plan Updated', `${selectedTenant.value!.name} upgraded to ${selectedPlan.value} plan.`);
                upgradeModal.value = false;
            },
            onError: () => error('Failed', 'Could not update the subscription plan.'),
        },
    );
};

const handleSuspend = (t: PlatformTenant) => {
    router.patch(
        `/super-admin/tenants/${t.id}/suspend`,
        {},
        {
            onSuccess: () => warning('Tenant Suspended', `${t.name} access has been restricted.`),
            onError: () => error('Failed', 'Could not suspend tenant.'),
        },
    );
};

const handleActivate = (t: PlatformTenant) => {
    router.patch(
        `/super-admin/tenants/${t.id}/activate`,
        {},
        {
            onSuccess: () => success('Tenant Activated', `${t.name} is now active.`),
            onError: () => error('Failed', 'Could not activate tenant.'),
        },
    );
};

const deleteConfirmOpen = ref(false);
const tenantToDelete = ref<PlatformTenant | null>(null);
const isDeletingTenant = ref(false);

const openDelete = (t: PlatformTenant) => {
    tenantToDelete.value = t;
    deleteConfirmOpen.value = true;
};

const confirmDeleteTenant = () => {
    if (!tenantToDelete.value) return;
    isDeletingTenant.value = true;
    router.delete(`/super-admin/tenants/${tenantToDelete.value.id}`, {
        onSuccess: () => {
            success('Tenant Deleted', `${tenantToDelete.value?.name} and all associated data removed.`);
            deleteConfirmOpen.value = false;
        },
        onError: () => error('Failed', 'Could not delete tenant.'),
        onFinish: () => (isDeletingTenant.value = false),
    });
};

const storagePercent = (t: PlatformTenant) => Math.min(100, Math.round((t.storageUsedMB / t.storageQuotaMB) * 100));

// ── Edit Tenant ───────────────────────────────────────────────────────────────
const editModal = ref(false);
const editTarget = ref<PlatformTenant | null>(null);
const editForm = ref({
    name: '',
    email: '',
    phone: '',
    kra_pin: '',
    address: '',
    plan: 'Pro' as SubscriptionPlan,
    billing_cycle: 'Monthly',
    branch_count: 1,
    storage_quota_mb: 5120,
    status: 'Active',
});
const editProcessing = ref(false);

const allModulesList = [
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

const openEdit = (t: PlatformTenant) => {
    editTarget.value = t;
    editForm.value = {
        name: t.name,
        email: t.email,
        phone: t.phone ?? '',
        kra_pin: t.kraPin ?? '',
        address: '',
        plan: t.plan,
        billing_cycle: t.billingCycle ?? 'Monthly',
        branch_count: t.branchCount ?? 1,
        storage_quota_mb: t.storageQuotaMB ?? 5120,
        status: t.status,
    };
    editModal.value = true;
};

const handleEdit = () => {
    if (!editTarget.value) return;
    editProcessing.value = true;
    router.patch(`/super-admin/tenants/${editTarget.value.id}`, editForm.value, {
        onSuccess: () => {
            success('Tenant Updated', `${editForm.value.name} has been updated.`);
            editModal.value = false;
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (editProcessing.value = false),
    });
};
</script>

<template>
    <Head title="Super Admin — Tenants" />
    <SuperAdminLayout>
        <div class="mx-auto max-w-[1600px] space-y-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-bold text-zinc-100">
                        <Building2 class="size-5 text-violet-400" />
                        Tenant Account Management
                    </h1>
                    <p class="mt-0.5 text-xs text-zinc-500">Create, configure, suspend, and manage all registered tenant organizations.</p>
                </div>
                <Button
                    size="sm"
                    class="h-8 cursor-pointer gap-1.5 bg-violet-600 text-xs text-white hover:bg-violet-700"
                    @click="registerModal = true"
                >
                    <Plus class="size-3.5" />
                    Register New Tenant
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-500" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search tenant, email, KRA PIN..."
                        class="h-9 w-64 rounded-lg border border-zinc-700 bg-zinc-800 pl-8 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    />
                </div>

                <div class="flex items-center gap-1 text-xs font-semibold">
                    <span class="text-zinc-500">Plan:</span>
                    <button
                        v-for="plan in ['All', 'Basic', 'Pro', 'Enterprise']"
                        :key="plan"
                        type="button"
                        :class="[
                            'whitespace-nowrap rounded border px-2.5 py-1 text-[11px]',
                            planFilter === plan ? 'border-violet-600 bg-violet-600 text-white' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100',
                        ]"
                        @click="planFilter = plan"
                    >
                        {{ plan }}
                    </button>
                </div>

                <div class="flex items-center gap-1 text-xs font-semibold">
                    <span class="text-zinc-500">Status:</span>
                    <button
                        v-for="s in ['All', 'Active', 'Trial', 'Suspended']"
                        :key="s"
                        type="button"
                        :class="[
                            'whitespace-nowrap rounded border px-2.5 py-1 text-[11px]',
                            statusFilter === s ? 'border-violet-600 bg-violet-600 text-white' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100',
                        ]"
                        @click="statusFilter = s"
                    >
                        {{ s }}
                    </button>
                </div>
            </div>

            <!-- Tenant Cards Grid -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="tenant in filteredTenants"
                    :key="tenant.id"
                    class="space-y-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5"
                    :class="tenant.status === 'Suspended' ? 'border-rose-900/40 opacity-70' : ''"
                >
                    <!-- Card Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-violet-600/30 bg-violet-600/20">
                                <Building2 class="size-5 text-violet-400" />
                            </div>
                            <div>
                                <p class="text-sm font-bold leading-tight text-zinc-100">{{ tenant.name }}</p>
                                <p class="font-mono text-[10px] text-zinc-500">{{ tenant.kraPin }}</p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <span
                                v-if="tenant.is_demo"
                                class="rounded border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 font-mono text-[9px] font-extrabold uppercase tracking-wider text-amber-300"
                            >
                                Demo
                            </span>
                            <span
                                v-else
                                class="rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[9px] font-extrabold uppercase tracking-wider text-emerald-300"
                            >
                                Live Client
                            </span>
                            <span
                                :class="[
                                    'rounded border px-2 py-0.5 font-mono text-[10px] font-extrabold',
                                    tenant.plan === 'Enterprise'
                                        ? 'border-violet-600/30 bg-violet-600/20 text-violet-300'
                                        : tenant.plan === 'Pro'
                                          ? 'border-sky-600/30 bg-sky-600/20 text-sky-300'
                                          : 'border-zinc-600/30 bg-zinc-700/60 text-zinc-300',
                                ]"
                            >
                                {{ tenant.plan }}
                            </span>
                            <span
                                :class="[
                                    'rounded px-2 py-0.5 text-[10px] font-bold',
                                    tenant.status === 'Active'
                                        ? 'bg-emerald-600/20 text-emerald-300'
                                        : tenant.status === 'Trial'
                                          ? 'bg-amber-600/20 text-amber-300'
                                          : 'bg-rose-600/20 text-rose-300',
                                ]"
                            >
                                {{ tenant.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-zinc-800/60 py-2">
                            <p class="font-mono font-extrabold text-zinc-100">{{ tenant.userCount }}</p>
                            <p class="text-[10px] text-zinc-500">Staff Users</p>
                        </div>
                        <div class="rounded-lg bg-zinc-800/60 py-2">
                            <p class="font-mono font-extrabold text-zinc-100">{{ tenant.branchCount }}</p>
                            <p class="text-[10px] text-zinc-500">Branches</p>
                        </div>
                        <div class="rounded-lg bg-zinc-800/60 py-2">
                            <p class="font-mono font-extrabold text-sky-400">
                                {{ tenant.monthlyRevenue > 0 ? formatCurrency(tenant.monthlyRevenue) : 'Trial' }}
                            </p>
                            <p class="text-[10px] text-zinc-500">MRR</p>
                        </div>
                    </div>

                    <!-- Storage Bar -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                            <span class="flex items-center gap-1"><HardDrive class="size-3" /> Storage</span>
                            <span
                                >{{ storagePercent(tenant) }}% used ({{ (tenant.storageUsedMB / 1024).toFixed(1) }} GB /
                                {{ (tenant.storageQuotaMB / 1024).toFixed(0) }} GB)</span
                            >
                        </div>
                        <div class="h-1.5 rounded-full bg-zinc-800">
                            <div
                                :class="[
                                    'h-1.5 rounded-full transition-all',
                                    storagePercent(tenant) > 85 ? 'bg-rose-500' : storagePercent(tenant) > 60 ? 'bg-amber-500' : 'bg-emerald-500',
                                ]"
                                :style="`width: ${storagePercent(tenant)}%`"
                            />
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="space-y-0.5 text-[10px] text-zinc-500">
                        <p>{{ tenant.email }}</p>
                        <p>
                            Next billing: <span class="font-semibold text-zinc-300">{{ tenant.nextBillingDate }}</span> ({{ tenant.billingCycle }})
                        </p>
                        <p>
                            Last active: <span class="text-zinc-300">{{ tenant.lastActive }}</span>
                        </p>
                    </div>

                    <!-- Dedicated Tenant Portal / Login URL -->
                    <div class="space-y-1 rounded-lg border border-zinc-800 bg-zinc-950/80 p-2">
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="flex items-center gap-1 font-semibold text-zinc-400">
                                <Globe class="size-3 text-violet-400" />
                                Portal / Login URL
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
                                    title="Copy login URL"
                                    @click="copyTenantUrl(tenant.subdomain_url)"
                                >
                                    <Copy class="size-2.5" />
                                    Copy
                                </button>
                                <a
                                    :href="tenant.subdomain_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold text-violet-400 transition-colors hover:bg-violet-900/30 hover:text-violet-300"
                                    title="Open tenant login"
                                >
                                    <ExternalLink class="size-2.5" />
                                    Open
                                </a>
                            </div>
                        </div>
                        <p class="select-all truncate font-mono text-[10px] text-violet-300" :title="tenant.subdomain_url">
                            {{ tenant.subdomain_url }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-1.5 border-t border-zinc-800 pt-1">
                        <!-- Primary Actions Row -->
                        <div class="flex items-center gap-1.5">
                            <a
                                :href="`/super-admin/tenants/${tenant.id}/detail`"
                                class="flex flex-1 items-center justify-center gap-1 rounded border border-violet-600 bg-violet-600 py-1.5 text-[10px] font-bold text-white transition-colors hover:bg-violet-700"
                            >
                                <Eye class="size-3" />
                                Full Detail
                            </a>
                            <a
                                :href="`/super-admin/tenants/${tenant.id}/users`"
                                class="flex flex-1 items-center justify-center gap-1 rounded border border-violet-600/30 bg-violet-600/20 py-1.5 text-[10px] font-bold text-violet-300 transition-colors hover:bg-violet-600/40"
                            >
                                <Users class="size-3" />
                                Users
                            </a>
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-1 rounded border border-emerald-500/30 bg-emerald-600/20 py-1.5 text-[10px] font-semibold text-emerald-300 transition-colors hover:bg-emerald-600/40"
                                @click="openTelemetry(tenant)"
                            >
                                <Activity class="size-3" />
                                Pulse
                            </button>
                            <button
                                type="button"
                                class="rounded border border-zinc-700 bg-zinc-800 px-2.5 py-1.5 text-[10px] font-semibold text-zinc-400 transition-colors hover:border-zinc-500 hover:text-white"
                                title="Edit Tenant"
                                @click="openEdit(tenant)"
                            >
                                <Settings class="size-3" />
                            </button>
                        </div>

                        <!-- Secondary Actions Row -->
                        <div class="flex items-center gap-1.5">
                            <a
                                :href="`/super-admin/tenants/${tenant.id}/detail?tab=modules`"
                                class="flex flex-1 items-center justify-center gap-1 rounded border border-violet-500/30 bg-violet-600/15 py-1.5 text-[10px] font-semibold text-violet-300 transition-colors hover:border-violet-500/50 hover:bg-violet-600/30"
                                title="Configure Enabled Pages & Modules"
                            >
                                <Layers class="size-3" />
                                Pages ({{ tenant.enabledModules?.length ?? tenant.enabled_modules?.length ?? 0 }})
                            </a>
                            <button
                                type="button"
                                class="flex-1 rounded border border-zinc-700 bg-zinc-800 py-1.5 text-[10px] font-semibold text-zinc-400 transition-colors hover:border-violet-500 hover:text-white"
                                @click="openUpgradeModal(tenant)"
                            >
                                <Zap class="mr-1 inline size-3 text-amber-400" />
                                Plan
                            </button>
                            <button
                                v-if="tenant.status !== 'Suspended'"
                                type="button"
                                class="rounded bg-rose-600/20 px-2.5 py-1.5 text-[10px] font-semibold text-rose-300 transition-colors hover:bg-rose-600/40"
                                @click="handleSuspend(tenant)"
                                title="Suspend Tenant"
                            >
                                <PauseCircle class="inline size-3" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded bg-emerald-600/20 px-2.5 py-1.5 text-[10px] font-semibold text-emerald-300 transition-colors hover:bg-emerald-600/40"
                                @click="handleActivate(tenant)"
                                title="Activate Tenant"
                            >
                                <PlayCircle class="inline size-3" />
                            </button>
                            <button
                                type="button"
                                class="rounded bg-zinc-800 px-2.5 py-1.5 text-[10px] font-semibold text-zinc-500 transition-colors hover:bg-rose-600/10 hover:text-rose-400"
                                @click="openDelete(tenant)"
                                title="Delete Tenant"
                            >
                                <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="filteredTenants.length === 0" class="py-16 text-center text-zinc-500">
                <Building2 class="mx-auto mb-3 size-12 opacity-30" />
                <p class="font-semibold">No tenants match your filters</p>
            </div>
        </div>

        <!-- Live Tenant Telemetry & Pulse Modal -->
        <Dialog :open="telemetryModal" @update:open="(v) => (telemetryModal = v)">
            <DialogContent class="max-h-[90vh] overflow-y-auto border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-3xl">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <DialogDescription class="sr-only">Live telemetry and activity pulse for {{ monitoredTenant?.name }}</DialogDescription>
                    <div class="flex items-center justify-between">
                        <DialogTitle class="flex items-center gap-2 text-base font-bold">
                            <Activity class="size-4.5 animate-pulse text-emerald-400" />
                            Live Telemetry & Activity Pulse — {{ monitoredTenant?.name }}
                        </DialogTitle>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 gap-1.5 bg-zinc-800 text-[11px] text-zinc-300 hover:text-emerald-400"
                            @click="handleRefreshTelemetry"
                        >
                            <RefreshCw class="size-3" />
                            Sync Stream
                        </Button>
                    </div>
                    <div class="mt-1 flex items-center gap-3 text-xs text-zinc-400">
                        <span
                            >Organization ID: <strong class="font-mono text-zinc-200">{{ monitoredTenant?.id }}</strong></span
                        >
                        <span>•</span>
                        <span
                            >Type: <strong class="text-violet-300">{{ monitoredTenant?.businessType }}</strong></span
                        >
                        <span>•</span>
                        <span
                            >Plan: <strong class="font-mono text-sky-300">{{ monitoredTenant?.plan }}</strong></span
                        >
                    </div>
                </DialogHeader>

                <div v-if="activeTelemetry" class="space-y-4 py-3 text-xs">
                    <!-- Real-Time Pulse & Throughput Bar -->
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="mb-1 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <Zap class="size-3.5 text-emerald-400" /> Data Pulse Rate
                            </div>
                            <p class="font-mono text-xl font-extrabold text-emerald-400">{{ activeTelemetry.pulseRateRPS }} req/s</p>
                            <div class="mt-0.5 flex items-center gap-1 text-[10px] text-emerald-500">
                                <span class="size-1.5 animate-ping rounded-full bg-emerald-400"></span>
                                Live WebSocket Stream
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="mb-1 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <Database class="size-3.5 text-sky-400" /> Query Velocity
                            </div>
                            <p class="font-mono text-xl font-extrabold text-sky-400">{{ activeTelemetry.queryVelocityQPM }} QPM</p>
                            <p class="mt-0.5 text-[10px] text-zinc-500">Database write/read rate</p>
                        </div>

                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="mb-1 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <Smartphone class="size-3.5 text-violet-400" /> Active Cashiers
                            </div>
                            <p class="font-mono text-xl font-extrabold text-violet-400">{{ activeTelemetry.activeCashiersCount }} Terminals</p>
                            <p class="mt-0.5 text-[10px] text-zinc-500">{{ activeTelemetry.activeSessionsCount }} logged-in staff</p>
                        </div>

                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="mb-1 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <Globe class="size-3.5 text-amber-400" /> Network Latency
                            </div>
                            <p class="font-mono text-xl font-extrabold text-amber-400">{{ activeTelemetry.latencyMS }} ms</p>
                            <p class="mt-0.5 text-[10px] font-semibold text-emerald-400">0.00% Error Rate</p>
                        </div>
                    </div>

                    <!-- Resource Utilization Breakdown -->
                    <div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                        <h4 class="flex items-center gap-2 text-xs font-bold text-zinc-300">
                            <Cpu class="size-3.5 text-violet-400" />
                            Tenant Resource & Storage Allocation
                        </h4>

                        <div class="grid grid-cols-1 gap-3 text-[11px] sm:grid-cols-3">
                            <!-- CPU -->
                            <div class="space-y-1.5 rounded-lg border border-zinc-800 bg-zinc-900 p-2.5">
                                <div class="flex justify-between text-zinc-400">
                                    <span>CPU Compute</span>
                                    <span class="font-mono font-bold text-zinc-200">{{ activeTelemetry.cpuUsagePercent }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-zinc-800">
                                    <div
                                        class="h-1.5 rounded-full bg-violet-500 transition-all"
                                        :style="`width: ${activeTelemetry.cpuUsagePercent}%`"
                                    />
                                </div>
                            </div>

                            <!-- RAM -->
                            <div class="space-y-1.5 rounded-lg border border-zinc-800 bg-zinc-900 p-2.5">
                                <div class="flex justify-between text-zinc-400">
                                    <span>Memory Usage</span>
                                    <span class="font-mono font-bold text-zinc-200"
                                        >{{ activeTelemetry.memoryUsageMB }} MB / {{ activeTelemetry.memoryQuotaMB }} MB</span
                                    >
                                </div>
                                <div class="h-1.5 rounded-full bg-zinc-800">
                                    <div
                                        class="h-1.5 rounded-full bg-sky-500 transition-all"
                                        :style="`width: ${(activeTelemetry.memoryUsageMB / activeTelemetry.memoryQuotaMB) * 100}%`"
                                    />
                                </div>
                            </div>

                            <!-- Bandwidth -->
                            <div class="space-y-1.5 rounded-lg border border-zinc-800 bg-zinc-900 p-2.5">
                                <div class="flex justify-between text-zinc-400">
                                    <span>Bandwidth Today</span>
                                    <span class="font-mono font-bold text-zinc-200">{{ activeTelemetry.bandwidthTodayGB }} GB</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-zinc-800">
                                    <div
                                        class="h-1.5 rounded-full bg-emerald-500 transition-all"
                                        :style="`width: ${Math.min(100, (activeTelemetry.bandwidthTodayGB / 10) * 100)}%`"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Storage Breakdown sub-bar -->
                        <div class="flex items-center justify-between border-t border-zinc-800 pt-1 text-[10px] text-zinc-400">
                            <span class="flex items-center gap-1.5">
                                <Database class="size-3 text-sky-400" /> DB Storage:
                                <strong class="font-mono text-zinc-200">{{ activeTelemetry.databaseSizeMB }} MB</strong>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <HardDrive class="size-3 text-violet-400" /> Media & Receipts:
                                <strong class="font-mono text-zinc-200">{{ activeTelemetry.mediaUploadsSizeMB }} MB</strong>
                            </span>
                            <span
                                >Total Used:
                                <strong class="font-mono text-zinc-200"
                                    >{{ activeTelemetry.databaseSizeMB + activeTelemetry.mediaUploadsSizeMB }} MB</strong
                                ></span
                            >
                        </div>
                    </div>

                    <!-- Real-Time Activity Stream (Live Feed) -->
                    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950/60">
                        <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-900 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="size-2 animate-pulse rounded-full bg-emerald-400"></span>
                                <h4 class="text-xs font-bold text-zinc-200">Real-Time Activity Audit Trail</h4>
                            </div>
                            <span class="font-mono text-[10px] text-zinc-500">Live Event Stream</span>
                        </div>

                        <div class="max-h-56 divide-y divide-zinc-800 overflow-y-auto">
                            <div
                                v-for="act in activeTelemetry.liveActivities"
                                :key="act.id"
                                class="flex items-start gap-3 p-3 transition-colors hover:bg-zinc-900/50"
                            >
                                <div
                                    :class="[
                                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg',
                                        act.type === 'sale'
                                            ? 'bg-emerald-500/20 text-emerald-400'
                                            : act.type === 'payment'
                                              ? 'bg-sky-500/20 text-sky-400'
                                              : act.type === 'compliance'
                                                ? 'bg-violet-500/20 text-violet-400'
                                                : act.type === 'report'
                                                  ? 'bg-amber-500/20 text-amber-400'
                                                  : 'bg-zinc-800 text-zinc-400',
                                    ]"
                                >
                                    <Zap v-if="act.type === 'sale'" class="size-3.5" />
                                    <Smartphone v-else-if="act.type === 'payment'" class="size-3.5" />
                                    <FileCheck v-else-if="act.type === 'compliance'" class="size-3.5" />
                                    <Activity v-else class="size-3.5" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-xs font-bold text-zinc-100">{{ act.action }}</p>
                                        <span class="shrink-0 font-mono text-[10px] text-zinc-500">{{ act.timestamp }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-zinc-400">{{ act.detail }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-[10px] text-zinc-500">
                                        <span
                                            >User: <strong class="text-zinc-300">{{ act.actor }}</strong> ({{ act.role }})</span
                                        >
                                        <span>•</span>
                                        <span
                                            >Station: <strong class="text-zinc-300">{{ act.branch }}</strong></span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button size="sm" class="bg-violet-600 text-white hover:bg-violet-700" @click="telemetryModal = false">
                        Close Telemetry Console
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Upgrade Plan Modal -->
        <Dialog :open="upgradeModal" @update:open="(v) => (upgradeModal = v)">
            <DialogContent class="border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Change Subscription Plan</DialogTitle>
                    <DialogDescription class="sr-only">Select a new subscription plan for {{ selectedTenant?.name }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-3 text-xs">
                    <p class="text-zinc-400">
                        Updating plan for: <strong class="text-zinc-100">{{ selectedTenant?.name }}</strong>
                    </p>
                    <div class="grid gap-2">
                        <button
                            v-for="plan in ['Basic', 'Pro', 'Enterprise']"
                            :key="plan"
                            type="button"
                            :class="[
                                'flex items-center justify-between rounded-xl border p-3 text-left transition-all',
                                selectedPlan === plan ? 'border-violet-500 bg-violet-600/20' : 'border-zinc-700 bg-zinc-800 hover:border-zinc-500',
                            ]"
                            @click="selectedPlan = plan as SubscriptionPlan"
                        >
                            <div>
                                <p class="font-bold text-zinc-100">{{ plan }}</p>
                                <p class="text-[10px] text-zinc-400">
                                    {{
                                        plan === 'Basic'
                                            ? 'KES 2,500/mo · 1 Branch · 5 Users'
                                            : plan === 'Pro'
                                              ? 'KES 6,500/mo · 5 Branches · 20 Users'
                                              : 'KES 15,000/mo · Unlimited Branches & Users'
                                    }}
                                </p>
                            </div>
                            <Check v-if="selectedPlan === plan" class="size-4 shrink-0 text-violet-400" />
                        </button>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-100" @click="upgradeModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 text-white hover:bg-violet-700" @click="handleUpgrade">Apply Plan Change</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Register New Tenant Modal (With Devices & Network Restrictions) -->
        <Dialog :open="registerModal" @update:open="(v) => (registerModal = v)">
            <DialogContent class="max-h-[90vh] w-[95vw] overflow-y-auto border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-2xl">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Building2 class="size-4.5 text-violet-400" />
                        Register New Tenant Organization
                    </DialogTitle>
                    <DialogDescription class="text-xs text-zinc-400">
                        Setup tenant profile, subscription plan, authorized POS hardware devices, and network access restrictions.
                    </DialogDescription>

                    <!-- Registration Step Navigation -->
                    <div class="scrollbar-none flex items-center gap-2 overflow-x-auto pt-2 text-xs">
                        <button
                            type="button"
                            :class="[
                                'flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-semibold transition-all',
                                registerTab === 'profile'
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'profile'"
                        >
                            <Building2 class="size-3.5" />
                            1. Profile & Plan
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-semibold transition-all',
                                registerTab === 'payments'
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'payments'"
                        >
                            <Banknote class="size-3.5" />
                            2. Payment Channels
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-semibold transition-all',
                                registerTab === 'devices'
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'devices'"
                        >
                            <Smartphone class="size-3.5" />
                            3. Devices ({{ newTenantForm.selectedDeviceTypes.length }})
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-semibold transition-all',
                                registerTab === 'network'
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'network'"
                        >
                            <Wifi class="size-3.5" />
                            4. Network
                        </button>
                    </div>
                </DialogHeader>

                <!-- Step 1: Business Profile & Subscription -->
                <div v-if="registerTab === 'profile'" class="space-y-3.5 py-3 text-xs">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <Label class="text-[11px] text-zinc-400">Business / Organization Name *</Label>
                            <Input
                                v-model="newTenantForm.name"
                                placeholder="e.g. Kilimani Fresh Mart"
                                class="mt-1 border-zinc-700 bg-zinc-800 text-xs font-semibold text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label class="text-[11px] text-zinc-400">Subdomain Identifier (Unique Slug) *</Label>
                            <div class="mt-1 flex items-center rounded-md border border-zinc-700 bg-zinc-800 px-3">
                                <Input
                                    v-model="newTenantForm.slug"
                                    placeholder="kilimani-fresh-mart"
                                    class="border-0 bg-transparent p-0 font-mono text-xs text-violet-300 focus-visible:ring-0"
                                />
                                <span class="shrink-0 pl-1 font-mono text-[10px] text-zinc-500">.localhost:8000</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <Label class="text-[11px] text-zinc-400">Primary Contact Email *</Label>
                            <Input
                                v-model="newTenantForm.email"
                                type="email"
                                placeholder="admin@tenant.co.ke"
                                class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label class="text-[11px] text-zinc-400">Contact Phone (M-Pesa Notifications) *</Label>
                            <Input
                                v-model="newTenantForm.phone"
                                placeholder="+254 7XX XXX XXX"
                                class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                            />
                        </div>
                    </div>

                    <!-- Owner Initial Login Credentials -->
                    <div class="space-y-2.5 rounded-xl border border-violet-900/40 bg-zinc-950/80 p-3">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-violet-400">
                                <Key class="size-3" />
                                Owner Initial Login Credentials
                            </span>
                            <span class="text-[10px] text-zinc-500">Auto-created for business administrator</span>
                        </div>
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div>
                                <Label class="text-[10px] text-zinc-400">Owner Login Email</Label>
                                <Input
                                    v-model="newTenantForm.ownerEmail"
                                    type="email"
                                    placeholder="owner@tenant.co.ke"
                                    class="mt-1 border-zinc-800 bg-zinc-900 text-xs text-zinc-200"
                                />
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <Label class="text-[10px] text-zinc-400">Initial Password</Label>
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 text-[10px] text-violet-400 hover:underline"
                                        @click="regeneratePassword"
                                    >
                                        <RefreshCw class="size-2.5" />
                                        Regenerate
                                    </button>
                                </div>
                                <Input
                                    v-model="newTenantForm.ownerPassword"
                                    class="mt-1 border-zinc-800 bg-zinc-900 font-mono text-xs font-bold text-emerald-400"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <Label class="text-[11px] text-zinc-400">Business Type Preset</Label>
                            <select
                                v-model="newTenantForm.businessType"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                            >
                                <option value="Supermarket">Supermarket</option>
                                <option value="Wholesale">Wholesale</option>
                                <option value="Pharmacy">Pharmacy</option>
                                <option value="Restaurant">Restaurant</option>
                                <option value="Hardware">Hardware</option>
                                <option value="General Trade">General Trade</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Clothing & Apparel">Clothing & Apparel</option>
                                <option value="Bakery">Bakery</option>
                                <option value="Petrol Station">Petrol Station</option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-[11px] text-zinc-400">Initial Subscription Plan</Label>
                            <select
                                v-model="newTenantForm.plan"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                            >
                                <option value="Basic">Basic (KES 2,500/mo)</option>
                                <option value="Pro">Pro (KES 6,500/mo)</option>
                                <option value="Enterprise">Enterprise (KES 15,000/mo)</option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-[11px] text-zinc-400">Billing Frequency / Cycle</Label>
                            <select
                                v-model="newTenantForm.billingCycle"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs font-bold text-zinc-200"
                            >
                                <option value="Weekly">Weekly (7 Days)</option>
                                <option value="Monthly">Monthly (30 Days)</option>
                                <option value="Quarterly">Quarterly (3 Months)</option>
                                <option value="Semi-Annual">Semi-Annual (6 Months)</option>
                                <option value="Annual">Annual (12 Months)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <Label class="text-[11px] text-zinc-400">Initial Branches Count</Label>
                            <Input
                                v-model.number="newTenantForm.branchCount"
                                type="number"
                                min="1"
                                class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label class="text-[11px] text-zinc-400">Initial Staff Accounts Quota</Label>
                            <Input
                                v-model.number="newTenantForm.userCount"
                                type="number"
                                min="1"
                                class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                            />
                        </div>
                    </div>
                </div>

                <!-- Step 2: Storefront Payment Channels Setup -->
                <div v-if="registerTab === 'payments'" class="space-y-3.5 py-3 text-xs">
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Configure Storefront Payment Methods</p>
                        <p class="mt-0.5 text-[11px] text-zinc-400">
                            Set up how customers pay at checkout. These settings immediately configure the cashier's POS screen.
                        </p>
                    </div>

                    <div class="space-y-2.5">
                        <!-- Cash Tender -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                        <Banknote class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-100">Cash Payment & Cash Drawer</p>
                                        <p class="text-[10px] text-zinc-400">Accept physical currency notes and coins</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableCash"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableCash" class="border-t border-zinc-800 pt-2">
                                <Label class="text-[10px] text-zinc-400">Till Drawer Reference</Label>
                                <Input
                                    v-model="newTenantForm.cashDrawerLabel"
                                    placeholder="e.g. Main Cash Drawer"
                                    class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                />
                            </div>
                        </div>

                        <!-- M-Pesa Buy Goods Till -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                        <Smartphone class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-100">Lipa na M-Pesa Buy Goods (Till Number)</p>
                                        <p class="text-[10px] text-zinc-400">Standard Safaricom Buy Goods retail till</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableMpesaTill"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableMpesaTill" class="grid grid-cols-1 gap-2 border-t border-zinc-800 pt-2 sm:grid-cols-2">
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Safaricom Till Number</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaTillNumber"
                                        placeholder="e.g. 523910"
                                        class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs font-bold text-zinc-100"
                                    />
                                </div>
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Store Name (on customer phone)</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaStoreName"
                                        placeholder="e.g. Nairobi Fresh Mart"
                                        class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- M-Pesa Paybill -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                        <Smartphone class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-100">Lipa na M-Pesa Paybill</p>
                                        <p class="text-[10px] text-zinc-400">Business Paybill Number + Account Reference</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableMpesaPaybill"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableMpesaPaybill" class="grid grid-cols-1 gap-2 border-t border-zinc-800 pt-2 sm:grid-cols-2">
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Business Shortcode (Paybill)</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaPaybillNumber"
                                        placeholder="e.g. 247247"
                                        class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs font-bold text-zinc-100"
                                    />
                                </div>
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Account Number / Reference</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaPaybillAccount"
                                        placeholder="e.g. 0110293847"
                                        class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Card PDQ Terminal -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-sky-500/20 text-sky-400">
                                        <CreditCard class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-100">Visa / Mastercard / PDQ Card Terminal</p>
                                        <p class="text-[10px] text-zinc-400">Card terminal swipe/tap payment</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableCard"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-sky-500 focus:ring-sky-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableCard" class="grid grid-cols-1 gap-2 border-t border-zinc-800 pt-2 sm:grid-cols-2">
                                <div>
                                    <Label class="text-[10px] text-zinc-400">PDQ Terminal ID</Label>
                                    <Input
                                        v-model="newTenantForm.cardTerminalId"
                                        placeholder="e.g. EQ-PDQ-8901"
                                        class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                    />
                                </div>
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Card Machine Bank</Label>
                                    <Input
                                        v-model="newTenantForm.cardBankName"
                                        placeholder="e.g. Equity Bank / KCB / Absa / Pesapal"
                                        class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-violet-500/20 text-violet-400">
                                        <Building2 class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-100">Direct Bank Wire / Pesalink Deposit</p>
                                        <p class="text-[10px] text-zinc-400">Electronic funds transfer directly to merchant account</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableBank"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-violet-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableBank" class="grid grid-cols-1 gap-2 border-t border-zinc-800 pt-2 sm:grid-cols-2">
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Bank Name</Label>
                                    <Input
                                        v-model="newTenantForm.bankName"
                                        placeholder="e.g. KCB Bank / Co-op Bank"
                                        class="mt-1 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                    />
                                </div>
                                <div>
                                    <Label class="text-[10px] text-zinc-400">Account Number</Label>
                                    <Input
                                        v-model="newTenantForm.bankAccountNumber"
                                        placeholder="e.g. 1122334455"
                                        class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Customer Credit Account -->
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400">
                                    <Wallet class="size-3.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-zinc-100">Allow Customer Credit Accounts</p>
                                    <p class="text-[10px] text-zinc-400">Permit credit sales against registered debtor accounts</p>
                                </div>
                            </div>
                            <input
                                v-model="newTenantForm.enableCredit"
                                type="checkbox"
                                class="size-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                            />
                        </label>
                    </div>
                </div>

                <!-- Step 3: Authorized Devices Setup -->
                <div v-if="registerTab === 'devices'" class="space-y-4 py-3 text-xs">
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Select Kind of Devices Tenant Will Use</p>
                        <p class="mt-0.5 text-[11px] text-zinc-400">
                            Authorize the hardware form factors permitted to log into this tenant's POS terminals.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <!-- POS Terminal -->
                        <div
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all',
                                newTenantForm.selectedDeviceTypes.includes('POS Terminal')
                                    ? 'border-violet-500 bg-violet-600/15'
                                    : 'border-zinc-800 bg-zinc-950/60 opacity-60 hover:opacity-100',
                            ]"
                            @click="toggleDeviceType('POS Terminal')"
                        >
                            <input
                                type="checkbox"
                                :checked="newTenantForm.selectedDeviceTypes.includes('POS Terminal')"
                                class="mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                                @click.stop
                                @change="toggleDeviceType('POS Terminal')"
                            />
                            <div>
                                <p class="text-xs font-bold text-zinc-100">Dedicated POS Hardware Terminals</p>
                                <p class="text-[10px] text-zinc-400">Sunmi V2/T2, PAX A920, Nexgo, Smart Android POS</p>
                            </div>
                        </div>

                        <!-- Desktop PC -->
                        <div
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all',
                                newTenantForm.selectedDeviceTypes.includes('Desktop PC')
                                    ? 'border-violet-500 bg-violet-600/15'
                                    : 'border-zinc-800 bg-zinc-950/60 opacity-60 hover:opacity-100',
                            ]"
                            @click="toggleDeviceType('Desktop PC')"
                        >
                            <input
                                type="checkbox"
                                :checked="newTenantForm.selectedDeviceTypes.includes('Desktop PC')"
                                class="mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                                @click.stop
                                @change="toggleDeviceType('Desktop PC')"
                            />
                            <div>
                                <p class="text-xs font-bold text-zinc-100">Desktop PC / Counter Computer</p>
                                <p class="text-[10px] text-zinc-400">Windows 10/11 POS, Dell OptiPlex, iMac, ChromeOS</p>
                            </div>
                        </div>

                        <!-- Tablet -->
                        <div
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all',
                                newTenantForm.selectedDeviceTypes.includes('Tablet')
                                    ? 'border-violet-500 bg-violet-600/15'
                                    : 'border-zinc-800 bg-zinc-950/60 opacity-60 hover:opacity-100',
                            ]"
                            @click="toggleDeviceType('Tablet')"
                        >
                            <input
                                type="checkbox"
                                :checked="newTenantForm.selectedDeviceTypes.includes('Tablet')"
                                class="mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                                @click.stop
                                @change="toggleDeviceType('Tablet')"
                            />
                            <div>
                                <p class="text-xs font-bold text-zinc-100">Tablet / iPad Mobile Register</p>
                                <p class="text-[10px] text-zinc-400">Apple iPad POS, Samsung Galaxy Tab, Lenovo Kiosk</p>
                            </div>
                        </div>

                        <!-- Smartphone -->
                        <div
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all',
                                newTenantForm.selectedDeviceTypes.includes('Smartphone')
                                    ? 'border-violet-500 bg-violet-600/15'
                                    : 'border-zinc-800 bg-zinc-950/60 opacity-60 hover:opacity-100',
                            ]"
                            @click="toggleDeviceType('Smartphone')"
                        >
                            <input
                                type="checkbox"
                                :checked="newTenantForm.selectedDeviceTypes.includes('Smartphone')"
                                class="mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                                @click.stop
                                @change="toggleDeviceType('Smartphone')"
                            />
                            <div>
                                <p class="text-xs font-bold text-zinc-100">Mobile Smartphones (Android / iOS)</p>
                                <p class="text-[10px] text-zinc-400">Field sales, delivery riders, table-side ordering</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hardware Binding & Quota Controls -->
                    <div class="space-y-3 border-t border-zinc-800 pt-2">
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div>
                                <p class="text-xs font-bold text-zinc-200">Enforce Hardware Fingerprint & MAC Binding</p>
                                <p class="text-[10px] text-zinc-400">Requires tenant Admin to approve each terminal before cashiers can login.</p>
                            </div>
                            <input
                                v-model="newTenantForm.requireDeviceBinding"
                                type="checkbox"
                                class="size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-[11px] text-zinc-400">Authorized POS Register Tokens</Label>
                                <Input
                                    v-model.number="newTenantForm.posHardwareCount"
                                    type="number"
                                    min="1"
                                    class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                />
                            </div>

                            <div class="flex items-end pb-1 text-[11px] text-zinc-400">Max allowed simultaneously bound POS registers.</div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Network & Security Restrictions -->
                <div v-if="registerTab === 'network'" class="space-y-3.5 py-3 text-xs">
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Configure Network Access Guardrails</p>
                        <p class="mt-0.5 text-[11px] text-zinc-400">
                            Prevent unauthorized off-site cashier checkouts and protect against proxy spoofing.
                        </p>
                    </div>

                    <div class="space-y-2.5">
                        <!-- VPN Blocker -->
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-rose-500/20 text-rose-400">
                                    <ShieldCheck class="size-3.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-zinc-100">Block Commercial VPNs & Proxy Tunnels</p>
                                    <p class="text-[10px] text-zinc-400">Auto-quarantine connections from NordVPN, ExpressVPN, Tor, WireGuard</p>
                                </div>
                            </div>
                            <input
                                v-model="newTenantForm.blockVpn"
                                type="checkbox"
                                class="size-4 rounded border-zinc-700 bg-zinc-900 text-rose-500 focus:ring-rose-500"
                            />
                        </label>

                        <!-- Enforce Store Wi-Fi -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                        <Wifi class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-zinc-100">Enforce Mandatory Store Wi-Fi Network</p>
                                        <p class="text-[10px] text-zinc-400">Cashier POS shifts allowed strictly within verified store LAN router</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.requireStoreWifi"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div v-if="newTenantForm.requireStoreWifi" class="border-t border-zinc-800 pt-2">
                                <Label class="text-[10px] text-zinc-400">Primary Store Wi-Fi SSID / Router Name</Label>
                                <Input
                                    v-model="newTenantForm.primaryStoreWifiSSID"
                                    placeholder="e.g. KilimaniPOS-SecureStore-5G"
                                    class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                />
                            </div>
                        </div>

                        <!-- Block Hotspot -->
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400">
                                    <Radio class="size-3.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-zinc-100">Block Personal Mobile Hotspots & Tethering</p>
                                    <p class="text-[10px] text-zinc-400">Disallow cashiers from routing POS sales over personal phone hotspots</p>
                                </div>
                            </div>
                            <input
                                v-model="newTenantForm.blockHotspot"
                                type="checkbox"
                                class="size-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                            />
                        </label>

                        <!-- Cellular SIM Data Policy -->
                        <div class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-sky-500/20 text-sky-400">
                                    <Smartphone class="size-3.5" />
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-zinc-100">Cellular (SIM 4G/5G) Data Access Policy</p>
                                    <p class="text-[10px] text-zinc-400">Controls whether SIM mobile data is allowed for cashier stations</p>

                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        <button
                                            type="button"
                                            :class="[
                                                'rounded-lg border p-2 text-left text-[10px] font-semibold transition-all',
                                                newTenantForm.cellularPolicy === 'field_roles_only'
                                                    ? 'border-sky-500 bg-sky-600/20 text-sky-200'
                                                    : 'border-zinc-800 bg-zinc-900 text-zinc-400',
                                            ]"
                                            @click="newTenantForm.cellularPolicy = 'field_roles_only'"
                                        >
                                            Role-Gated (Field & Delivery Only)
                                        </button>

                                        <button
                                            type="button"
                                            :class="[
                                                'rounded-lg border p-2 text-left text-[10px] font-semibold transition-all',
                                                newTenantForm.cellularPolicy === 'block_cellular'
                                                    ? 'border-rose-500 bg-rose-600/20 text-rose-200'
                                                    : 'border-zinc-800 bg-zinc-900 text-zinc-400',
                                            ]"
                                            @click="newTenantForm.cellularPolicy = 'block_cellular'"
                                        >
                                            Strict Store LAN (Block SIM Data)
                                        </button>

                                        <button
                                            type="button"
                                            :class="[
                                                'rounded-lg border p-2 text-left text-[10px] font-semibold transition-all',
                                                newTenantForm.cellularPolicy === 'allow_all'
                                                    ? 'border-emerald-500 bg-emerald-600/20 text-emerald-200'
                                                    : 'border-zinc-800 bg-zinc-900 text-zinc-400',
                                            ]"
                                            @click="newTenantForm.cellularPolicy = 'allow_all'"
                                        >
                                            Allow All SIM Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Single-Session Cashier Lock -->
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3">
                            <div>
                                <p class="text-xs font-bold text-zinc-100">Single-Terminal Active Session Lock</p>
                                <p class="text-[10px] text-zinc-400">Prevent simultaneous logins on multiple registers with the same cashier user</p>
                            </div>
                            <input
                                v-model="newTenantForm.singleSessionPerCashier"
                                type="checkbox"
                                class="size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                        </label>
                    </div>
                </div>

                <DialogFooter class="gap-2 border-t border-zinc-800 pt-3">
                    <Button variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-100" @click="registerModal = false"> Cancel </Button>
                    <Button
                        v-if="registerTab !== 'network'"
                        size="sm"
                        class="bg-zinc-800 text-zinc-200 hover:bg-zinc-700"
                        @click="registerTab = registerTab === 'profile' ? 'payments' : registerTab === 'payments' ? 'devices' : 'network'"
                    >
                        Next Step →
                    </Button>
                    <Button size="sm" class="bg-violet-600 text-white hover:bg-violet-700" @click="handleRegisterTenant">
                        Complete Tenant Registration
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════ EDIT TENANT MODAL ════════════ -->
        <Dialog :open="editModal" @update:open="(v) => (editModal = v)">
            <DialogContent class="max-h-[90vh] overflow-y-auto border-zinc-800 bg-zinc-900 text-zinc-100 sm:max-w-2xl">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Settings class="size-4 text-violet-400" />
                        Edit Tenant — {{ editTarget?.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only">Edit tenant organization profile and subscription settings</DialogDescription>
                </DialogHeader>

                <div class="space-y-5 py-3">
                    <!-- Profile Section -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Organization Details</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Business Name</Label>
                                <Input v-model="editForm.name" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">KRA PIN</Label>
                                <Input v-model="editForm.kra_pin" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Primary Email</Label>
                                <Input v-model="editForm.email" type="email" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Phone</Label>
                                <Input v-model="editForm.phone" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                            </div>
                            <div class="col-span-2 space-y-1">
                                <Label class="text-xs text-zinc-400">Address</Label>
                                <Input v-model="editForm.address" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                            </div>
                        </div>
                    </div>

                    <!-- Subscription -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Subscription & Billing</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Plan</Label>
                                <select
                                    v-model="editForm.plan"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                                >
                                    <option value="Basic">Basic</option>
                                    <option value="Pro">Pro</option>
                                    <option value="Enterprise">Enterprise</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Billing Cycle</Label>
                                <select
                                    v-model="editForm.billing_cycle"
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
                                <Label class="text-xs text-zinc-400">Branch Limit</Label>
                                <Input
                                    v-model.number="editForm.branch_count"
                                    type="number"
                                    min="1"
                                    class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Storage Quota (MB)</Label>
                                <Input
                                    v-model.number="editForm.storage_quota_mb"
                                    type="number"
                                    min="512"
                                    class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Account Status</Label>
                                <select
                                    v-model="editForm.status"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                                >
                                    <option value="Trial">Trial</option>
                                    <option value="Active">Active</option>
                                    <option value="Suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 border-t border-zinc-800 pt-3">
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="editModal = false">Cancel</Button>
                    <Button class="gap-1.5 bg-violet-600 text-xs text-white hover:bg-violet-500" :disabled="editProcessing" @click="handleEdit">
                        <RefreshCw class="size-3.5" :class="editProcessing ? 'animate-spin' : ''" />
                        {{ editProcessing ? 'Saving…' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════ TENANT CREATED CREDENTIALS MODAL ════════════ -->
        <Dialog :open="createdCredentialsModal" @update:open="(v) => (createdCredentialsModal = v)">
            <DialogContent class="border-zinc-800 bg-zinc-900 text-zinc-100 shadow-2xl sm:max-w-lg">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2 text-emerald-400">
                        <CheckCircle2 class="size-5" />
                        <DialogTitle class="text-base font-bold text-zinc-100"> Tenant Registered Successfully </DialogTitle>
                    </div>
                    <DialogDescription class="mt-1 text-xs text-zinc-400">
                        Dedicated subdomain environment and business administrator credentials are ready.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="createdCredentialsData" class="space-y-3.5 py-3 text-xs">
                    <!-- Business & Subdomain URL -->
                    <div class="space-y-1.5 rounded-xl border border-zinc-800 bg-zinc-950/80 p-3">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500"> Dedicated Business Subdomain </span>
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-mono text-sm font-bold text-violet-300">
                                {{ createdCredentialsData.subdomain_url }}
                            </span>
                            <a
                                :href="createdCredentialsData.subdomain_url"
                                target="_blank"
                                class="inline-flex shrink-0 items-center gap-1 rounded-md bg-violet-600/20 px-2.5 py-1 text-[11px] font-semibold text-violet-300 transition-colors hover:bg-violet-600/30"
                            >
                                <ExternalLink class="size-3" />
                                Open Portal
                            </a>
                        </div>
                    </div>

                    <!-- Owner Login Credentials -->
                    <div class="space-y-3 rounded-xl border border-violet-900/40 bg-zinc-950/80 p-3.5">
                        <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-violet-400">
                            <Key class="size-3" />
                            Owner Login Credentials
                        </span>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <span class="text-[10px] text-zinc-400">Username / Email</span>
                                <p class="select-all truncate font-mono text-xs font-semibold text-zinc-200">
                                    {{ createdCredentialsData.owner_email }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] text-zinc-400">Initial Password</span>
                                <p class="select-all font-mono text-xs font-bold text-emerald-400">
                                    {{ createdCredentialsData.owner_password }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 p-2.5 text-[11px] text-amber-300">
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <p>
                            When the business owner opens their subdomain URL, they will access their dedicated workspace directly with no business
                            selector.
                        </p>
                    </div>
                </div>

                <DialogFooter class="flex-wrap gap-2 border-t border-zinc-800 pt-3">
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="createdCredentialsModal = false"> Done </Button>
                    <Button class="gap-1.5 bg-zinc-700 text-xs text-white hover:bg-zinc-600" @click="copyCredentials">
                        <Copy class="size-3.5" />
                        Copy Credentials
                    </Button>
                    <Button
                        v-if="createdCredentialsData?.tenant_id"
                        class="gap-1.5 bg-emerald-600 text-xs text-white hover:bg-emerald-500"
                        @click="handleLaunchWorkspace"
                    >
                        <ExternalLink class="size-3.5" />
                        Launch Workspace
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Tenant Warning Alert Dialog -->
        <ConfirmDialog
            v-model:open="deleteConfirmOpen"
            title="Permanently Delete Tenant Workspace"
            description="Are you sure you want to permanently delete this tenant? All associated users, branches, inventory, sales, and financial ledgers will be irreversibly erased."
            :item-name="tenantToDelete ? `${tenantToDelete.name} (${tenantToDelete.slug || tenantToDelete.subdomain || 'tenant'})` : undefined"
            warning-note="DANGER: This action cannot be reversed. All database records and transaction histories for this business will be permanently purged."
            confirm-text="Permanently Delete"
            :loading="isDeletingTenant"
            @confirm="confirmDeleteTenant"
        />
    </SuperAdminLayout>
</template>
