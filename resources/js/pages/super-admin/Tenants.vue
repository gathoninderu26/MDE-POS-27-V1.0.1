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
    Clock,
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
    Lock,
    PauseCircle,
    PlayCircle,
    Plus,
    Radio,
    RefreshCw,
    Search,
    Server,
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
import type { TenantRegistrationInput } from '@/composables/useSuperAdmin';

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

const {
    subscriptionPlans,
    getTenantTelemetry,
} = useSuperAdmin();
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
    router.post(`/super-admin/tenants/${id}/impersonate`, {}, {
        onError: () => error('Launch Failed', 'Could not open the tenant workspace. Please try again.'),
    });
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
    router.post('/super-admin/tenants', {
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
    }, {
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
    });
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
    router.patch(`/super-admin/tenants/${selectedTenant.value.id}`, { plan: selectedPlan.value }, {
        onSuccess: () => {
            success('Plan Updated', `${selectedTenant.value!.name} upgraded to ${selectedPlan.value} plan.`);
            upgradeModal.value = false;
        },
        onError: () => error('Failed', 'Could not update the subscription plan.'),
    });
};

const handleSuspend = (t: PlatformTenant) => {
    router.patch(`/super-admin/tenants/${t.id}/suspend`, {}, {
        onSuccess: () => warning('Tenant Suspended', `${t.name} access has been restricted.`),
        onError: () => error('Failed', 'Could not suspend tenant.'),
    });
};

const handleActivate = (t: PlatformTenant) => {
    router.patch(`/super-admin/tenants/${t.id}/activate`, {}, {
        onSuccess: () => success('Tenant Activated', `${t.name} is now active.`),
        onError: () => error('Failed', 'Could not activate tenant.'),
    });
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
    name: '', email: '', phone: '', kra_pin: '', address: '',
    plan: 'Pro' as SubscriptionPlan, billing_cycle: 'Monthly',
    branch_count: 1, storage_quota_mb: 5120, status: 'Active',
});
const editProcessing = ref(false);

const allModulesList = ['pos','sales','products','inventory','purchases','customers','suppliers','expenses','reports','users','branches','settings'];

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
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                        <Building2 class="size-5 text-violet-400" />
                        Tenant Account Management
                    </h1>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Create, configure, suspend, and manage all registered tenant organizations.
                    </p>
                </div>
                <Button
                    size="sm"
                    class="h-8 text-xs bg-violet-600 hover:bg-violet-700 text-white gap-1.5 cursor-pointer"
                    @click="registerModal = true"
                >
                    <Plus class="size-3.5" />
                    Register New Tenant
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-500" />
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
                            'px-2.5 py-1 rounded border text-[11px] whitespace-nowrap',
                            planFilter === plan ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100',
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
                            'px-2.5 py-1 rounded border text-[11px] whitespace-nowrap',
                            statusFilter === s ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100',
                        ]"
                        @click="statusFilter = s"
                    >
                        {{ s }}
                    </button>
                </div>
            </div>

            <!-- Tenant Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div
                    v-for="tenant in filteredTenants"
                    :key="tenant.id"
                    class="rounded-xl border border-zinc-800 bg-zinc-900 p-5 space-y-4"
                    :class="tenant.status === 'Suspended' ? 'opacity-70 border-rose-900/40' : ''"
                >
                    <!-- Card Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-violet-600/20 border border-violet-600/30 flex items-center justify-center shrink-0">
                                <Building2 class="size-5 text-violet-400" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-zinc-100 leading-tight">{{ tenant.name }}</p>
                                <p class="text-[10px] text-zinc-500 font-mono">{{ tenant.kraPin }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <span
                                v-if="tenant.is_demo"
                                class="text-[9px] font-extrabold font-mono px-1.5 py-0.5 rounded border text-amber-300 bg-amber-500/10 border-amber-500/30 uppercase tracking-wider"
                            >
                                Demo
                            </span>
                            <span
                                v-else
                                class="text-[9px] font-extrabold font-mono px-1.5 py-0.5 rounded border text-emerald-300 bg-emerald-500/10 border-emerald-500/30 uppercase tracking-wider"
                            >
                                Live Client
                            </span>
                            <span
                                :class="[
                                    'text-[10px] font-extrabold font-mono px-2 py-0.5 rounded border',
                                    tenant.plan === 'Enterprise' ? 'text-violet-300 bg-violet-600/20 border-violet-600/30' :
                                    tenant.plan === 'Pro' ? 'text-sky-300 bg-sky-600/20 border-sky-600/30' :
                                    'text-zinc-300 bg-zinc-700/60 border-zinc-600/30',
                                ]"
                            >
                                {{ tenant.plan }}
                            </span>
                            <span
                                :class="[
                                    'text-[10px] font-bold px-2 py-0.5 rounded',
                                    tenant.status === 'Active' ? 'bg-emerald-600/20 text-emerald-300' :
                                    tenant.status === 'Trial' ? 'bg-amber-600/20 text-amber-300' :
                                    'bg-rose-600/20 text-rose-300',
                                ]"
                            >
                                {{ tenant.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-zinc-800/60 py-2">
                            <p class="font-extrabold font-mono text-zinc-100">{{ tenant.userCount }}</p>
                            <p class="text-[10px] text-zinc-500">Staff Users</p>
                        </div>
                        <div class="rounded-lg bg-zinc-800/60 py-2">
                            <p class="font-extrabold font-mono text-zinc-100">{{ tenant.branchCount }}</p>
                            <p class="text-[10px] text-zinc-500">Branches</p>
                        </div>
                        <div class="rounded-lg bg-zinc-800/60 py-2">
                            <p class="font-extrabold font-mono text-sky-400">{{ tenant.monthlyRevenue > 0 ? formatCurrency(tenant.monthlyRevenue) : 'Trial' }}</p>
                            <p class="text-[10px] text-zinc-500">MRR</p>
                        </div>
                    </div>

                    <!-- Storage Bar -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[10px] text-zinc-500">
                            <span class="flex items-center gap-1"><HardDrive class="size-3" /> Storage</span>
                            <span>{{ storagePercent(tenant) }}% used ({{ (tenant.storageUsedMB / 1024).toFixed(1) }} GB / {{ (tenant.storageQuotaMB / 1024).toFixed(0) }} GB)</span>
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
                    <div class="text-[10px] text-zinc-500 space-y-0.5">
                        <p>{{ tenant.email }}</p>
                        <p>Next billing: <span class="text-zinc-300 font-semibold">{{ tenant.nextBillingDate }}</span> ({{ tenant.billingCycle }})</p>
                        <p>Last active: <span class="text-zinc-300">{{ tenant.lastActive }}</span></p>
                    </div>

                    <!-- Dedicated Tenant Portal / Login URL -->
                    <div class="p-2 rounded-lg bg-zinc-950/80 border border-zinc-800 space-y-1">
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="text-zinc-400 font-semibold flex items-center gap-1">
                                <Globe class="size-3 text-violet-400" />
                                Portal / Login URL
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="text-[10px] text-zinc-400 hover:text-zinc-100 px-1.5 py-0.5 rounded hover:bg-zinc-800 transition-colors inline-flex items-center gap-1"
                                    title="Copy login URL"
                                    @click="copyTenantUrl(tenant.subdomain_url)"
                                >
                                    <Copy class="size-2.5" />
                                    Copy
                                </button>
                                <a
                                    :href="tenant.subdomain_url"
                                    target="_blank"
                                    class="text-[10px] text-violet-400 hover:text-violet-300 px-1.5 py-0.5 rounded hover:bg-violet-900/30 transition-colors inline-flex items-center gap-0.5 font-semibold"
                                    title="Open tenant login"
                                >
                                    <ExternalLink class="size-2.5" />
                                    Open
                                </a>
                            </div>
                        </div>
                        <p class="font-mono text-[10px] text-violet-300 truncate select-all" :title="tenant.subdomain_url">
                            {{ tenant.subdomain_url }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-1.5 pt-1 border-t border-zinc-800">
                        <!-- Primary Actions Row -->
                        <div class="flex items-center gap-1.5">
                            <a
                                :href="`/super-admin/tenants/${tenant.id}/detail`"
                                class="flex-1 py-1.5 rounded text-[10px] font-bold bg-violet-600 text-white hover:bg-violet-700 border border-violet-600 transition-colors flex items-center justify-center gap-1"
                            >
                                <Eye class="size-3" />
                                Full Detail
                            </a>
                            <a
                                :href="`/super-admin/tenants/${tenant.id}/users`"
                                class="flex-1 py-1.5 rounded text-[10px] font-bold bg-violet-600/20 text-violet-300 hover:bg-violet-600/40 border border-violet-600/30 transition-colors flex items-center justify-center gap-1"
                            >
                                <Users class="size-3" />
                                Users
                            </a>
                            <button
                                type="button"
                                class="flex-1 py-1.5 rounded text-[10px] font-semibold bg-emerald-600/20 text-emerald-300 hover:bg-emerald-600/40 border border-emerald-500/30 transition-colors flex items-center justify-center gap-1"
                                @click="openTelemetry(tenant)"
                            >
                                <Activity class="size-3" />
                                Pulse
                            </button>
                            <button
                                type="button"
                                class="py-1.5 px-2.5 rounded text-[10px] font-semibold bg-zinc-800 border border-zinc-700 text-zinc-400 hover:text-white hover:border-zinc-500 transition-colors"
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
                                class="flex-1 py-1.5 rounded text-[10px] font-semibold bg-violet-600/15 text-violet-300 hover:bg-violet-600/30 border border-violet-500/30 hover:border-violet-500/50 transition-colors flex items-center justify-center gap-1"
                                title="Configure Enabled Pages & Modules"
                            >
                                <Layers class="size-3" />
                                Pages ({{ tenant.enabledModules?.length ?? tenant.enabled_modules?.length ?? 0 }})
                            </a>
                            <button
                                type="button"
                                class="flex-1 py-1.5 rounded text-[10px] font-semibold bg-zinc-800 text-zinc-400 hover:text-white border border-zinc-700 hover:border-violet-500 transition-colors"
                                @click="openUpgradeModal(tenant)"
                            >
                                <Zap class="size-3 inline mr-1 text-amber-400" />
                                Plan
                            </button>
                            <button
                                v-if="tenant.status !== 'Suspended'"
                                type="button"
                                class="py-1.5 px-2.5 rounded text-[10px] font-semibold bg-rose-600/20 text-rose-300 hover:bg-rose-600/40 transition-colors"
                                @click="handleSuspend(tenant)"
                                title="Suspend Tenant"
                            >
                                <PauseCircle class="size-3 inline" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="py-1.5 px-2.5 rounded text-[10px] font-semibold bg-emerald-600/20 text-emerald-300 hover:bg-emerald-600/40 transition-colors"
                                @click="handleActivate(tenant)"
                                title="Activate Tenant"
                            >
                                <PlayCircle class="size-3 inline" />
                            </button>
                            <button
                                type="button"
                                class="py-1.5 px-2.5 rounded text-[10px] font-semibold bg-zinc-800 text-zinc-500 hover:text-rose-400 hover:bg-rose-600/10 transition-colors"
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
            <div v-if="filteredTenants.length === 0" class="text-center py-16 text-zinc-500">
                <Building2 class="size-12 mx-auto mb-3 opacity-30" />
                <p class="font-semibold">No tenants match your filters</p>
            </div>
        </div>

        <!-- Live Tenant Telemetry & Pulse Modal -->
        <Dialog :open="telemetryModal" @update:open="(v) => (telemetryModal = v)">
            <DialogContent class="sm:max-w-3xl bg-zinc-900 border-zinc-700 text-zinc-100 max-h-[90vh] overflow-y-auto">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <DialogDescription class="sr-only">Live telemetry and activity pulse for {{ monitoredTenant?.name }}</DialogDescription>
                    <div class="flex items-center justify-between">
                        <DialogTitle class="text-base font-bold flex items-center gap-2">
                            <Activity class="size-4.5 text-emerald-400 animate-pulse" />
                            Live Telemetry & Activity Pulse — {{ monitoredTenant?.name }}
                        </DialogTitle>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 text-[11px] bg-zinc-800 text-zinc-300 hover:text-emerald-400 gap-1.5"
                            @click="handleRefreshTelemetry"
                        >
                            <RefreshCw class="size-3" />
                            Sync Stream
                        </Button>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-zinc-400 mt-1">
                        <span>Organization ID: <strong class="text-zinc-200 font-mono">{{ monitoredTenant?.id }}</strong></span>
                        <span>•</span>
                        <span>Type: <strong class="text-violet-300">{{ monitoredTenant?.businessType }}</strong></span>
                        <span>•</span>
                        <span>Plan: <strong class="text-sky-300 font-mono">{{ monitoredTenant?.plan }}</strong></span>
                    </div>
                </DialogHeader>

                <div v-if="activeTelemetry" class="space-y-4 py-3 text-xs">
                    <!-- Real-Time Pulse & Throughput Bar -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="p-3 rounded-xl border border-zinc-800 bg-zinc-950/70">
                            <div class="flex items-center gap-1.5 text-[11px] text-zinc-400 mb-1">
                                <Zap class="size-3.5 text-emerald-400" /> Data Pulse Rate
                            </div>
                            <p class="text-xl font-extrabold text-emerald-400 font-mono">{{ activeTelemetry.pulseRateRPS }} req/s</p>
                            <div class="flex items-center gap-1 text-[10px] text-emerald-500 mt-0.5">
                                <span class="size-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                Live WebSocket Stream
                            </div>
                        </div>

                        <div class="p-3 rounded-xl border border-zinc-800 bg-zinc-950/70">
                            <div class="flex items-center gap-1.5 text-[11px] text-zinc-400 mb-1">
                                <Database class="size-3.5 text-sky-400" /> Query Velocity
                            </div>
                            <p class="text-xl font-extrabold text-sky-400 font-mono">{{ activeTelemetry.queryVelocityQPM }} QPM</p>
                            <p class="text-[10px] text-zinc-500 mt-0.5">Database write/read rate</p>
                        </div>

                        <div class="p-3 rounded-xl border border-zinc-800 bg-zinc-950/70">
                            <div class="flex items-center gap-1.5 text-[11px] text-zinc-400 mb-1">
                                <Smartphone class="size-3.5 text-violet-400" /> Active Cashiers
                            </div>
                            <p class="text-xl font-extrabold text-violet-400 font-mono">{{ activeTelemetry.activeCashiersCount }} Terminals</p>
                            <p class="text-[10px] text-zinc-500 mt-0.5">{{ activeTelemetry.activeSessionsCount }} logged-in staff</p>
                        </div>

                        <div class="p-3 rounded-xl border border-zinc-800 bg-zinc-950/70">
                            <div class="flex items-center gap-1.5 text-[11px] text-zinc-400 mb-1">
                                <Globe class="size-3.5 text-amber-400" /> Network Latency
                            </div>
                            <p class="text-xl font-extrabold text-amber-400 font-mono">{{ activeTelemetry.latencyMS }} ms</p>
                            <p class="text-[10px] text-emerald-400 mt-0.5 font-semibold">0.00% Error Rate</p>
                        </div>
                    </div>

                    <!-- Resource Utilization Breakdown -->
                    <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4 space-y-3">
                        <h4 class="text-xs font-bold text-zinc-300 flex items-center gap-2">
                            <Cpu class="size-3.5 text-violet-400" />
                            Tenant Resource & Storage Allocation
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[11px]">
                            <!-- CPU -->
                            <div class="space-y-1.5 p-2.5 rounded-lg bg-zinc-900 border border-zinc-800">
                                <div class="flex justify-between text-zinc-400">
                                    <span>CPU Compute</span>
                                    <span class="font-bold text-zinc-200 font-mono">{{ activeTelemetry.cpuUsagePercent }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-zinc-800">
                                    <div
                                        class="h-1.5 rounded-full bg-violet-500 transition-all"
                                        :style="`width: ${activeTelemetry.cpuUsagePercent}%`"
                                    />
                                </div>
                            </div>

                            <!-- RAM -->
                            <div class="space-y-1.5 p-2.5 rounded-lg bg-zinc-900 border border-zinc-800">
                                <div class="flex justify-between text-zinc-400">
                                    <span>Memory Usage</span>
                                    <span class="font-bold text-zinc-200 font-mono">{{ activeTelemetry.memoryUsageMB }} MB / {{ activeTelemetry.memoryQuotaMB }} MB</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-zinc-800">
                                    <div
                                        class="h-1.5 rounded-full bg-sky-500 transition-all"
                                        :style="`width: ${(activeTelemetry.memoryUsageMB / activeTelemetry.memoryQuotaMB) * 100}%`"
                                    />
                                </div>
                            </div>

                            <!-- Bandwidth -->
                            <div class="space-y-1.5 p-2.5 rounded-lg bg-zinc-900 border border-zinc-800">
                                <div class="flex justify-between text-zinc-400">
                                    <span>Bandwidth Today</span>
                                    <span class="font-bold text-zinc-200 font-mono">{{ activeTelemetry.bandwidthTodayGB }} GB</span>
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
                        <div class="flex items-center justify-between text-[10px] text-zinc-400 pt-1 border-t border-zinc-800">
                            <span class="flex items-center gap-1.5">
                                <Database class="size-3 text-sky-400" /> DB Storage: <strong class="text-zinc-200 font-mono">{{ activeTelemetry.databaseSizeMB }} MB</strong>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <HardDrive class="size-3 text-violet-400" /> Media & Receipts: <strong class="text-zinc-200 font-mono">{{ activeTelemetry.mediaUploadsSizeMB }} MB</strong>
                            </span>
                            <span>Total Used: <strong class="text-zinc-200 font-mono">{{ (activeTelemetry.databaseSizeMB + activeTelemetry.mediaUploadsSizeMB) }} MB</strong></span>
                        </div>
                    </div>

                    <!-- Real-Time Activity Stream (Live Feed) -->
                    <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 overflow-hidden">
                        <div class="px-4 py-3 border-b border-zinc-800 flex items-center justify-between bg-zinc-900">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <h4 class="text-xs font-bold text-zinc-200">Real-Time Activity Audit Trail</h4>
                            </div>
                            <span class="text-[10px] text-zinc-500 font-mono">Live Event Stream</span>
                        </div>

                        <div class="divide-y divide-zinc-800 max-h-56 overflow-y-auto">
                            <div
                                v-for="act in activeTelemetry.liveActivities"
                                :key="act.id"
                                class="p-3 hover:bg-zinc-900/50 transition-colors flex items-start gap-3"
                            >
                                <div
                                    :class="[
                                        'size-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                                        act.type === 'sale' ? 'bg-emerald-500/20 text-emerald-400' :
                                        act.type === 'payment' ? 'bg-sky-500/20 text-sky-400' :
                                        act.type === 'compliance' ? 'bg-violet-500/20 text-violet-400' :
                                        act.type === 'report' ? 'bg-amber-500/20 text-amber-400' :
                                        'bg-zinc-800 text-zinc-400',
                                    ]"
                                >
                                    <Zap v-if="act.type === 'sale'" class="size-3.5" />
                                    <Smartphone v-else-if="act.type === 'payment'" class="size-3.5" />
                                    <FileCheck v-else-if="act.type === 'compliance'" class="size-3.5" />
                                    <Activity v-else class="size-3.5" />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-zinc-100 truncate">{{ act.action }}</p>
                                        <span class="text-[10px] text-zinc-500 font-mono shrink-0">{{ act.timestamp }}</span>
                                    </div>
                                    <p class="text-[11px] text-zinc-400 mt-0.5">{{ act.detail }}</p>
                                    <div class="flex items-center gap-2 text-[10px] text-zinc-500 mt-1">
                                        <span>User: <strong class="text-zinc-300">{{ act.actor }}</strong> ({{ act.role }})</span>
                                        <span>•</span>
                                        <span>Station: <strong class="text-zinc-300">{{ act.branch }}</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="telemetryModal = false">
                        Close Telemetry Console
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Upgrade Plan Modal -->
        <Dialog :open="upgradeModal" @update:open="(v) => (upgradeModal = v)">
            <DialogContent class="sm:max-w-md bg-zinc-900 border-zinc-700 text-zinc-100">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Change Subscription Plan</DialogTitle>
                    <DialogDescription class="sr-only">Select a new subscription plan for {{ selectedTenant?.name }}</DialogDescription>
                </DialogHeader>
                <div class="py-3 space-y-3 text-xs">
                    <p class="text-zinc-400">Updating plan for: <strong class="text-zinc-100">{{ selectedTenant?.name }}</strong></p>
                    <div class="grid gap-2">
                        <button
                            v-for="plan in ['Basic', 'Pro', 'Enterprise']"
                            :key="plan"
                            type="button"
                            :class="[
                                'flex items-center justify-between p-3 rounded-xl border text-left transition-all',
                                selectedPlan === plan
                                    ? 'border-violet-500 bg-violet-600/20'
                                    : 'border-zinc-700 bg-zinc-800 hover:border-zinc-500',
                            ]"
                            @click="selectedPlan = plan as SubscriptionPlan"
                        >
                            <div>
                                <p class="font-bold text-zinc-100">{{ plan }}</p>
                                <p class="text-[10px] text-zinc-400">
                                    {{ plan === 'Basic' ? 'KES 2,500/mo · 1 Branch · 5 Users' :
                                       plan === 'Pro' ? 'KES 6,500/mo · 5 Branches · 20 Users' :
                                       'KES 15,000/mo · Unlimited Branches & Users' }}
                                </p>
                            </div>
                            <Check v-if="selectedPlan === plan" class="size-4 text-violet-400 shrink-0" />
                        </button>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-100" @click="upgradeModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleUpgrade">Apply Plan Change</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Register New Tenant Modal (With Devices & Network Restrictions) -->
        <Dialog :open="registerModal" @update:open="(v) => (registerModal = v)">
            <DialogContent class="w-[95vw] sm:max-w-2xl bg-zinc-900 border-zinc-700 text-zinc-100 max-h-[90vh] overflow-y-auto">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Building2 class="size-4.5 text-violet-400" />
                        Register New Tenant Organization
                    </DialogTitle>
                    <DialogDescription class="text-xs text-zinc-400">
                        Setup tenant profile, subscription plan, authorized POS hardware devices, and network access restrictions.
                    </DialogDescription>

                    <!-- Registration Step Navigation -->
                    <div class="flex items-center gap-2 pt-2 text-xs overflow-x-auto scrollbar-none">
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold rounded-lg border transition-all text-xs flex items-center gap-1.5 shrink-0',
                                registerTab === 'profile'
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'profile'"
                        >
                            <Building2 class="size-3.5" />
                            1. Profile & Plan
                        </button>

                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold rounded-lg border transition-all text-xs flex items-center gap-1.5 shrink-0',
                                registerTab === 'payments'
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'payments'"
                        >
                            <Banknote class="size-3.5" />
                            2. Payment Channels
                        </button>

                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold rounded-lg border transition-all text-xs flex items-center gap-1.5 shrink-0',
                                registerTab === 'devices'
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:text-zinc-200',
                            ]"
                            @click="registerTab = 'devices'"
                        >
                            <Smartphone class="size-3.5" />
                            3. Devices ({{ newTenantForm.selectedDeviceTypes.length }})
                        </button>

                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold rounded-lg border transition-all text-xs flex items-center gap-1.5 shrink-0',
                                registerTab === 'network'
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:text-zinc-200',
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <Label class="text-zinc-400 text-[11px]">Business / Organization Name *</Label>
                            <Input
                                v-model="newTenantForm.name"
                                placeholder="e.g. Kilimani Fresh Mart"
                                class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-semibold"
                            />
                        </div>

                        <div>
                            <Label class="text-zinc-400 text-[11px]">Subdomain Identifier (Unique Slug) *</Label>
                            <div class="mt-1 flex items-center rounded-md border border-zinc-700 bg-zinc-800 px-3">
                                <Input
                                    v-model="newTenantForm.slug"
                                    placeholder="kilimani-fresh-mart"
                                    class="border-0 bg-transparent p-0 text-xs text-violet-300 font-mono focus-visible:ring-0"
                                />
                                <span class="text-[10px] text-zinc-500 font-mono shrink-0 pl-1">.localhost:8000</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <Label class="text-zinc-400 text-[11px]">Primary Contact Email *</Label>
                            <Input
                                v-model="newTenantForm.email"
                                type="email"
                                placeholder="admin@tenant.co.ke"
                                class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label class="text-zinc-400 text-[11px]">Contact Phone (M-Pesa Notifications) *</Label>
                            <Input
                                v-model="newTenantForm.phone"
                                placeholder="+254 7XX XXX XXX"
                                class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                            />
                        </div>
                    </div>

                    <!-- Owner Initial Login Credentials -->
                    <div class="p-3 rounded-xl bg-zinc-950/80 border border-violet-900/40 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-violet-400 flex items-center gap-1.5">
                                <Key class="size-3" />
                                Owner Initial Login Credentials
                            </span>
                            <span class="text-[10px] text-zinc-500">Auto-created for business administrator</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <Label class="text-zinc-400 text-[10px]">Owner Login Email</Label>
                                <Input
                                    v-model="newTenantForm.ownerEmail"
                                    type="email"
                                    placeholder="owner@tenant.co.ke"
                                    class="mt-1 text-xs bg-zinc-900 border-zinc-800 text-zinc-200"
                                />
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <Label class="text-zinc-400 text-[10px]">Initial Password</Label>
                                    <button
                                        type="button"
                                        class="text-[10px] text-violet-400 hover:underline flex items-center gap-1"
                                        @click="regeneratePassword"
                                    >
                                        <RefreshCw class="size-2.5" />
                                        Regenerate
                                    </button>
                                </div>
                                <Input
                                    v-model="newTenantForm.ownerPassword"
                                    class="mt-1 text-xs bg-zinc-900 border-zinc-800 text-emerald-400 font-mono font-bold"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <Label class="text-zinc-400 text-[11px]">Business Type Preset</Label>
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
                            <Label class="text-zinc-400 text-[11px]">Initial Subscription Plan</Label>
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
                            <Label class="text-zinc-400 text-[11px]">Billing Frequency / Cycle</Label>
                            <select
                                v-model="newTenantForm.billingCycle"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200 font-bold"
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
                            <Label class="text-zinc-400 text-[11px]">Initial Branches Count</Label>
                            <Input
                                v-model.number="newTenantForm.branchCount"
                                type="number"
                                min="1"
                                class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label class="text-zinc-400 text-[11px]">Initial Staff Accounts Quota</Label>
                            <Input
                                v-model.number="newTenantForm.userCount"
                                type="number"
                                min="1"
                                class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                            />
                        </div>
                    </div>
                </div>

                <!-- Step 2: Storefront Payment Channels Setup -->
                <div v-if="registerTab === 'payments'" class="space-y-3.5 py-3 text-xs">
                    <div>
                        <p class="font-bold text-zinc-200 text-xs">Configure Storefront Payment Methods</p>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            Set up how customers pay at checkout. These settings immediately configure the cashier's POS screen.
                        </p>
                    </div>

                    <div class="space-y-2.5">
                        <!-- Cash Tender -->
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                        <Banknote class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100 text-xs">Cash Payment & Cash Drawer</p>
                                        <p class="text-[10px] text-zinc-400">Accept physical currency notes and coins</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableCash"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableCash" class="pt-2 border-t border-zinc-800">
                                <Label class="text-zinc-400 text-[10px]">Till Drawer Reference</Label>
                                <Input
                                    v-model="newTenantForm.cashDrawerLabel"
                                    placeholder="e.g. Main Cash Drawer"
                                    class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                                />
                            </div>
                        </div>

                        <!-- M-Pesa Buy Goods Till -->
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                        <Smartphone class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100 text-xs">Lipa na M-Pesa Buy Goods (Till Number)</p>
                                        <p class="text-[10px] text-zinc-400">Standard Safaricom Buy Goods retail till</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableMpesaTill"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableMpesaTill" class="pt-2 border-t border-zinc-800 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Safaricom Till Number</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaTillNumber"
                                        placeholder="e.g. 523910"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono font-bold"
                                    />
                                </div>
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Store Name (on customer phone)</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaStoreName"
                                        placeholder="e.g. Nairobi Fresh Mart"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- M-Pesa Paybill -->
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                        <Smartphone class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100 text-xs">Lipa na M-Pesa Paybill</p>
                                        <p class="text-[10px] text-zinc-400">Business Paybill Number + Account Reference</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableMpesaPaybill"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableMpesaPaybill" class="pt-2 border-t border-zinc-800 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Business Shortcode (Paybill)</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaPaybillNumber"
                                        placeholder="e.g. 247247"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono font-bold"
                                    />
                                </div>
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Account Number / Reference</Label>
                                    <Input
                                        v-model="newTenantForm.mpesaPaybillAccount"
                                        placeholder="e.g. 0110293847"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Card PDQ Terminal -->
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0">
                                        <CreditCard class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100 text-xs">Visa / Mastercard / PDQ Card Terminal</p>
                                        <p class="text-[10px] text-zinc-400">Card terminal swipe/tap payment</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableCard"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-sky-500 focus:ring-sky-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableCard" class="pt-2 border-t border-zinc-800 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">PDQ Terminal ID</Label>
                                    <Input
                                        v-model="newTenantForm.cardTerminalId"
                                        placeholder="e.g. EQ-PDQ-8901"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                    />
                                </div>
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Card Machine Bank</Label>
                                    <Input
                                        v-model="newTenantForm.cardBankName"
                                        placeholder="e.g. Equity Bank / KCB / Absa / Pesapal"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-lg bg-violet-500/20 text-violet-400 flex items-center justify-center shrink-0">
                                        <Building2 class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100 text-xs">Direct Bank Wire / Pesalink Deposit</p>
                                        <p class="text-[10px] text-zinc-400">Electronic funds transfer directly to merchant account</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.enableBank"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-violet-500"
                                />
                            </div>
                            <div v-if="newTenantForm.enableBank" class="pt-2 border-t border-zinc-800 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Bank Name</Label>
                                    <Input
                                        v-model="newTenantForm.bankName"
                                        placeholder="e.g. KCB Bank / Co-op Bank"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                                    />
                                </div>
                                <div>
                                    <Label class="text-zinc-400 text-[10px]">Account Number</Label>
                                    <Input
                                        v-model="newTenantForm.bankAccountNumber"
                                        placeholder="e.g. 1122334455"
                                        class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Customer Credit Account -->
                        <label class="flex items-center justify-between p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 cursor-pointer">
                            <div class="flex items-start gap-2.5">
                                <div class="size-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                                    <Wallet class="size-3.5" />
                                </div>
                                <div>
                                    <p class="font-bold text-zinc-100 text-xs">Allow Customer Credit Accounts</p>
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
                        <p class="font-bold text-zinc-200 text-xs">Select Kind of Devices Tenant Will Use</p>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            Authorize the hardware form factors permitted to log into this tenant's POS terminals.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- POS Terminal -->
                        <div
                            :class="[
                                'p-3 rounded-xl border cursor-pointer transition-all flex items-start gap-3',
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
                                <p class="font-bold text-zinc-100 text-xs">Dedicated POS Hardware Terminals</p>
                                <p class="text-[10px] text-zinc-400">Sunmi V2/T2, PAX A920, Nexgo, Smart Android POS</p>
                            </div>
                        </div>

                        <!-- Desktop PC -->
                        <div
                            :class="[
                                'p-3 rounded-xl border cursor-pointer transition-all flex items-start gap-3',
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
                                <p class="font-bold text-zinc-100 text-xs">Desktop PC / Counter Computer</p>
                                <p class="text-[10px] text-zinc-400">Windows 10/11 POS, Dell OptiPlex, iMac, ChromeOS</p>
                            </div>
                        </div>

                        <!-- Tablet -->
                        <div
                            :class="[
                                'p-3 rounded-xl border cursor-pointer transition-all flex items-start gap-3',
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
                                <p class="font-bold text-zinc-100 text-xs">Tablet / iPad Mobile Register</p>
                                <p class="text-[10px] text-zinc-400">Apple iPad POS, Samsung Galaxy Tab, Lenovo Kiosk</p>
                            </div>
                        </div>

                        <!-- Smartphone -->
                        <div
                            :class="[
                                'p-3 rounded-xl border cursor-pointer transition-all flex items-start gap-3',
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
                                <p class="font-bold text-zinc-100 text-xs">Mobile Smartphones (Android / iOS)</p>
                                <p class="text-[10px] text-zinc-400">Field sales, delivery riders, table-side ordering</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hardware Binding & Quota Controls -->
                    <div class="space-y-3 pt-2 border-t border-zinc-800">
                        <label class="flex items-center justify-between p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 cursor-pointer">
                            <div>
                                <p class="font-bold text-zinc-200 text-xs">Enforce Hardware Fingerprint & MAC Binding</p>
                                <p class="text-[10px] text-zinc-400">
                                    Requires tenant Admin to approve each terminal before cashiers can login.
                                </p>
                            </div>
                            <input
                                v-model="newTenantForm.requireDeviceBinding"
                                type="checkbox"
                                class="size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-zinc-400 text-[11px]">Authorized POS Register Tokens</Label>
                                <Input
                                    v-model.number="newTenantForm.posHardwareCount"
                                    type="number"
                                    min="1"
                                    class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                />
                            </div>

                            <div class="flex items-end pb-1 text-[11px] text-zinc-400">
                                Max allowed simultaneously bound POS registers.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Network & Security Restrictions -->
                <div v-if="registerTab === 'network'" class="space-y-3.5 py-3 text-xs">
                    <div>
                        <p class="font-bold text-zinc-200 text-xs">Configure Network Access Guardrails</p>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            Prevent unauthorized off-site cashier checkouts and protect against proxy spoofing.
                        </p>
                    </div>

                    <div class="space-y-2.5">
                        <!-- VPN Blocker -->
                        <label class="flex items-center justify-between p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 cursor-pointer">
                            <div class="flex items-start gap-2.5">
                                <div class="size-7 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0 mt-0.5">
                                    <ShieldCheck class="size-3.5" />
                                </div>
                                <div>
                                    <p class="font-bold text-zinc-100 text-xs">Block Commercial VPNs & Proxy Tunnels</p>
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
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                                        <Wifi class="size-3.5" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100 text-xs">Enforce Mandatory Store Wi-Fi Network</p>
                                        <p class="text-[10px] text-zinc-400">Cashier POS shifts allowed strictly within verified store LAN router</p>
                                    </div>
                                </div>
                                <input
                                    v-model="newTenantForm.requireStoreWifi"
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div v-if="newTenantForm.requireStoreWifi" class="pt-2 border-t border-zinc-800">
                                <Label class="text-zinc-400 text-[10px]">Primary Store Wi-Fi SSID / Router Name</Label>
                                <Input
                                    v-model="newTenantForm.primaryStoreWifiSSID"
                                    placeholder="e.g. KilimaniPOS-SecureStore-5G"
                                    class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                />
                            </div>
                        </div>

                        <!-- Block Hotspot -->
                        <label class="flex items-center justify-between p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 cursor-pointer">
                            <div class="flex items-start gap-2.5">
                                <div class="size-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                                    <Radio class="size-3.5" />
                                </div>
                                <div>
                                    <p class="font-bold text-zinc-100 text-xs">Block Personal Mobile Hotspots & Tethering</p>
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
                        <div class="p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 space-y-2">
                            <div class="flex items-start gap-2.5">
                                <div class="size-7 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0 mt-0.5">
                                    <Smartphone class="size-3.5" />
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-zinc-100 text-xs">Cellular (SIM 4G/5G) Data Access Policy</p>
                                    <p class="text-[10px] text-zinc-400">Controls whether SIM mobile data is allowed for cashier stations</p>

                                    <div class="grid grid-cols-3 gap-2 mt-2">
                                        <button
                                            type="button"
                                            :class="[
                                                'p-2 rounded-lg border text-left text-[10px] font-semibold transition-all',
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
                                                'p-2 rounded-lg border text-left text-[10px] font-semibold transition-all',
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
                                                'p-2 rounded-lg border text-left text-[10px] font-semibold transition-all',
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
                        <label class="flex items-center justify-between p-3 rounded-xl bg-zinc-950/70 border border-zinc-800 cursor-pointer">
                            <div>
                                <p class="font-bold text-zinc-100 text-xs">Single-Terminal Active Session Lock</p>
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
                    <Button variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-100" @click="registerModal = false">
                        Cancel
                    </Button>
                    <Button
                        v-if="registerTab !== 'network'"
                        size="sm"
                        class="bg-zinc-800 hover:bg-zinc-700 text-zinc-200"
                        @click="registerTab = registerTab === 'profile' ? 'payments' : registerTab === 'payments' ? 'devices' : 'network'"
                    >
                        Next Step →
                    </Button>
                    <Button
                        size="sm"
                        class="bg-violet-600 hover:bg-violet-700 text-white"
                        @click="handleRegisterTenant"
                    >
                        Complete Tenant Registration
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════ EDIT TENANT MODAL ════════════ -->
        <Dialog :open="editModal" @update:open="(v) => (editModal = v)">
            <DialogContent class="sm:max-w-2xl bg-zinc-900 border-zinc-800 text-zinc-100 max-h-[90vh] overflow-y-auto">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
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
                                <Input v-model="editForm.name" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">KRA PIN</Label>
                                <Input v-model="editForm.kra_pin" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Primary Email</Label>
                                <Input v-model="editForm.email" type="email" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Phone</Label>
                                <Input v-model="editForm.phone" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1 col-span-2">
                                <Label class="text-xs text-zinc-400">Address</Label>
                                <Input v-model="editForm.address" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                        </div>
                    </div>

                    <!-- Subscription -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Subscription & Billing</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Plan</Label>
                                <select v-model="editForm.plan" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none">
                                    <option value="Basic">Basic</option>
                                    <option value="Pro">Pro</option>
                                    <option value="Enterprise">Enterprise</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Billing Cycle</Label>
                                <select v-model="editForm.billing_cycle" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none">
                                    <option value="Weekly">Weekly</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Semi-Annual">Semi-Annual</option>
                                    <option value="Annual">Annual</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Branch Limit</Label>
                                <Input v-model.number="editForm.branch_count" type="number" min="1" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Storage Quota (MB)</Label>
                                <Input v-model.number="editForm.storage_quota_mb" type="number" min="512" class="bg-zinc-800 border-zinc-700 text-xs text-zinc-100" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-zinc-400">Account Status</Label>
                                <select v-model="editForm.status" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none">
                                    <option value="Trial">Trial</option>
                                    <option value="Active">Active</option>
                                    <option value="Suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-zinc-800 pt-3 gap-2">
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="editModal = false">Cancel</Button>
                    <Button class="bg-violet-600 hover:bg-violet-500 text-white text-xs gap-1.5" :disabled="editProcessing" @click="handleEdit">
                        <RefreshCw class="size-3.5" :class="editProcessing ? 'animate-spin' : ''" />
                        {{ editProcessing ? 'Saving…' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════ TENANT CREATED CREDENTIALS MODAL ════════════ -->
        <Dialog :open="createdCredentialsModal" @update:open="(v) => (createdCredentialsModal = v)">
            <DialogContent class="sm:max-w-lg bg-zinc-900 border-zinc-800 text-zinc-100 shadow-2xl">
                <DialogHeader class="border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2 text-emerald-400">
                        <CheckCircle2 class="size-5" />
                        <DialogTitle class="text-base font-bold text-zinc-100">
                            Tenant Registered Successfully
                        </DialogTitle>
                    </div>
                    <DialogDescription class="text-xs text-zinc-400 mt-1">
                        Dedicated subdomain environment and business administrator credentials are ready.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="createdCredentialsData" class="py-3 space-y-3.5 text-xs">
                    <!-- Business & Subdomain URL -->
                    <div class="p-3 rounded-xl bg-zinc-950/80 border border-zinc-800 space-y-1.5">
                        <span class="text-[10px] uppercase font-bold text-zinc-500 tracking-wider">
                            Dedicated Business Subdomain
                        </span>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-sm font-bold text-violet-300 truncate">
                                {{ createdCredentialsData.subdomain_url }}
                            </span>
                            <a
                                :href="createdCredentialsData.subdomain_url"
                                target="_blank"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-violet-600/20 text-violet-300 hover:bg-violet-600/30 text-[11px] font-semibold transition-colors shrink-0"
                            >
                                <ExternalLink class="size-3" />
                                Open Portal
                            </a>
                        </div>
                    </div>

                    <!-- Owner Login Credentials -->
                    <div class="p-3.5 rounded-xl bg-zinc-950/80 border border-violet-900/40 space-y-3">
                        <span class="text-[10px] uppercase font-bold text-violet-400 tracking-wider flex items-center gap-1.5">
                            <Key class="size-3" />
                            Owner Login Credentials
                        </span>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <span class="text-[10px] text-zinc-400">Username / Email</span>
                                <p class="font-mono text-xs text-zinc-200 font-semibold truncate select-all">
                                    {{ createdCredentialsData.owner_email }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] text-zinc-400">Initial Password</span>
                                <p class="font-mono text-xs text-emerald-400 font-bold select-all">
                                    {{ createdCredentialsData.owner_password }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-start gap-2 text-[11px] text-amber-300">
                        <AlertCircle class="size-4 shrink-0 mt-0.5" />
                        <p>
                            When the business owner opens their subdomain URL, they will access their dedicated workspace directly with no business selector.
                        </p>
                    </div>
                </div>

                <DialogFooter class="border-t border-zinc-800 pt-3 gap-2 flex-wrap">
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="createdCredentialsModal = false">
                        Done
                    </Button>
                    <Button class="bg-zinc-700 hover:bg-zinc-600 text-white text-xs gap-1.5" @click="copyCredentials">
                        <Copy class="size-3.5" />
                        Copy Credentials
                    </Button>
                    <Button
                        v-if="createdCredentialsData?.tenant_id"
                        class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs gap-1.5"
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

