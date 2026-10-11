<script setup lang="ts">
import BranchGuideOverlay from '@/components/branches/BranchGuideOverlay.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Branch, BranchStaffMember, BranchType, ColabLink } from '@/composables/useBranch';
import { useBranch } from '@/composables/useBranch';
import { formatCurrency } from '@/composables/useCurrency';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    Clock,
    Compass,
    DollarSign,
    GitBranch,
    Handshake,
    HardDrive,
    MapPin,
    PauseCircle,
    PlayCircle,
    Plus,
    ShieldCheck,
    Store,
    TrendingUp,
    User,
    UserCheck,
    UserPlus,
    Users,
    Wifi,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watchEffect } from 'vue';

const props = defineProps<{
    branches?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Management', href: '/branches' },
    { title: 'Branches & Sub-Outlets', href: '/branches' },
];

const { currentTenant, allTenants } = useTenant();
const {
    currentBranch,
    setBranch,
    setBranches,
    tenantAllBranches,
    tenantActiveBranches,
    colabPartnerBranches,
    allAccessibleBranches,
    toggleBranchStatus,
    addColabLink,
} = useBranch();
const { success, warning, error, info } = useToast();

const activeFilter = ref<'all' | 'own' | 'colab' | 'suspended'>('all');

const branchesList = computed<Branch[]>(() => {
    if (props.branches && Array.isArray(props.branches)) {
        return props.branches.map((b) => ({
            id: (b.id || '').toString(),
            tenantId: (b.tenantId || '').toString(),
            name: b.name,
            code: b.code || `BR-${b.id}`,
            branchType: (b.branchType || b.type || 'Sub-Branch') as BranchType,
            location: b.location || 'Not specified',
            phone: b.phone || 'N/A',
            manager: b.manager || 'Unassigned',
            currentStockValue: b.currentStockValue ?? 0,
            todaySales: b.todaySales ?? 0,
            monthlySales: b.monthlySales ?? 0,
            transactionsToday: b.transactionsToday ?? 0,
            terminalsCount: b.terminalsCount ?? 1,
            storageUsedMB: b.storageUsedMB ?? 10,
            storageLimitMB: b.storageLimitMB ?? 512,
            operatingHours: b.operatingHours || '08:00 AM - 08:00 PM',
            wifiSSID: b.wifiSSID || '',
            status: b.status || 'Active',
            isMain: Boolean(b.isMain),
            staffMembers: b.staffMembers || [],
        }));
    }
    return [];
});

watchEffect(() => {
    if (branchesList.value.length > 0) {
        setBranches(branchesList.value);
    }
});

const effectiveBranches = computed(() => {
    if (tenantAllBranches.value && tenantAllBranches.value.length > 0) {
        return tenantAllBranches.value;
    }
    return branchesList.value;
});

const displayedBranches = computed(() => {
    if (activeFilter.value === 'suspended') {
        return effectiveBranches.value.filter((b) => b.status === 'Suspended');
    }
    if (activeFilter.value === 'colab') {
        return colabPartnerBranches.value || [];
    }
    if (activeFilter.value === 'own') {
        return effectiveBranches.value;
    }
    return allAccessibleBranches.value && allAccessibleBranches.value.length > 0 ? allAccessibleBranches.value : effectiveBranches.value;
});

// Aggregate stats across tenant branches
const totalTenantStaff = computed(() => {
    return effectiveBranches.value.reduce((acc, b) => acc + (b.staffMembers?.length || 0), 0);
});

const totalTenantStock = computed(() => {
    return effectiveBranches.value.reduce((acc, b) => acc + (b.currentStockValue || 0), 0);
});

const totalTenantMonthlySales = computed(() => {
    return effectiveBranches.value.reduce((acc, b) => acc + (b.monthlySales || 0), 0);
});

// New Branch / Sub-Branch Modal State
const newBranchModal = ref(false);
const newBranch = ref<{
    name: string;
    code: string;
    branchType: BranchType;
    parentBranchId: string;
    location: string;
    phone: string;
    manager: string;
    operatingHours: string;
    wifiSSID: string;
}>({
    name: '',
    code: '',
    branchType: 'Sub-Branch',
    parentBranchId: '',
    location: '',
    phone: '',
    manager: '',
    operatingHours: '08:00 AM - 08:00 PM',
    wifiSSID: '',
});

// Colab Partner Linking Modal
const colabModal = ref(false);
const selectedBranchToShare = ref('');
const targetPartnerTenantId = ref('');
const colabType = ref<ColabLink['colabType']>('Cross-Store Concession');
const colabPermission = ref<ColabLink['permission']>('Stock Visibility & Transfer');

// Branch Staff & Telemetry Detail Modal
const selectedBranchDetail = ref<Branch | null>(null);
const detailModalOpen = ref(false);

// New Staff Member Form
const newStaffFormOpen = ref(false);
const newStaff = ref<{
    name: string;
    role: BranchStaffMember['role'];
    phone: string;
    shift: BranchStaffMember['shift'];
}>({
    name: '',
    role: 'Sales Attendant',
    phone: '',
    shift: 'Morning',
});

const openBranchDetail = (branch: Branch) => {
    selectedBranchDetail.value = branch;
    detailModalOpen.value = true;
    newStaffFormOpen.value = false;
};

const isCreatingBranch = ref(false);
const isCreatingColabLink = ref(false);

const handleCreateBranch = () => {
    if (!newBranch.value.name || isCreatingBranch.value) return;

    isCreatingBranch.value = true;
    router.post(
        '/branches',
        {
            name: newBranch.value.name,
            code: newBranch.value.code,
            type: newBranch.value.branchType,
            location: newBranch.value.location,
            phone: newBranch.value.phone,
            manager: newBranch.value.manager,
            operating_hours: newBranch.value.operatingHours,
            wifi_ssid: newBranch.value.wifiSSID,
            is_main: newBranch.value.branchType === 'Main HQ',
        },
        {
            onSuccess: () => {
                newBranchModal.value = false;
                success('Branch Registered', `${newBranch.value.name} registered successfully.`);
                newBranch.value = {
                    name: '',
                    code: '',
                    branchType: 'Sub-Branch',
                    parentBranchId: '',
                    location: '',
                    phone: '',
                    manager: '',
                    operatingHours: '08:00 AM - 08:00 PM',
                    wifiSSID: '',
                };
            },
            onError: (errs) => error('Registration Failed', Object.values(errs)[0] as string),
            onFinish: () => (isCreatingBranch.value = false),
        },
    );
};

const handleSwitchBranch = (branchId: string, branchName: string) => {
    const ok = setBranch(branchId);
    if (ok) {
        success('Active POS Station Selected', `Terminal switched to ${branchName}`);
    } else {
        warning('Access Denied', 'Cannot switch to this branch. It may be suspended or unauthorized.');
    }
};

const handleToggleStatus = (b: Branch, e?: Event) => {
    if (e) e.stopPropagation();
    const newStatus = toggleBranchStatus(b.id);
    if (newStatus === 'Suspended') {
        warning('Branch Suspended', `${b.name} checkout operations have been paused.`);
    } else if (newStatus === 'Active') {
        success('Branch Activated', `${b.name} operations resumed.`);
    }
};

const handleCreateColabLink = async () => {
    if (!selectedBranchToShare.value || !targetPartnerTenantId.value || isCreatingColabLink.value) return;

    const targetTenant = allTenants.value.find((t) => t.id === targetPartnerTenantId.value);
    if (!targetTenant) return;

    isCreatingColabLink.value = true;
    await new Promise((r) => setTimeout(r, 350));
    const link: ColabLink = {
        linkedTenantId: targetTenant.id,
        linkedTenantName: targetTenant.name,
        colabType: colabType.value,
        permission: colabPermission.value,
        authorizedAt: new Date().toISOString().split('T')[0],
    };

    addColabLink(selectedBranchToShare.value, link);
    colabModal.value = false;
    success('Colab Link Created', `Branch linked with partner organization: ${targetTenant.name}`);
    isCreatingColabLink.value = false;
};

const handleAddStaffMember = () => {
    if (!selectedBranchDetail.value || !newStaff.value.name) return;

    const member: BranchStaffMember = {
        id: `s-${Date.now()}`,
        name: newStaff.value.name,
        role: newStaff.value.role,
        phone: newStaff.value.phone || '+254 700 000 000',
        shift: newStaff.value.shift,
        status: 'On Shift',
    };

    if (!selectedBranchDetail.value.staffMembers) {
        selectedBranchDetail.value.staffMembers = [];
    }
    selectedBranchDetail.value.staffMembers.push(member);
    newStaffFormOpen.value = false;
    newStaff.value = { name: '', role: 'Sales Attendant', phone: '', shift: 'Morning' };
    success('Staff Assigned', `${member.name} assigned as ${member.role}`);
};

// --- Page Guide Overlay State ---
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openBranchGuide = (step = 0) => {
    guideCurrentStep.value = step;
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'start-walkthrough') {
        guideCurrentStep.value = 1;
    } else if (actionType === 'open-create-branch') {
        newBranch.value.parentBranchId = tenantActiveBranches.value[0]?.id || '';
        newBranchModal.value = true;
    } else if (actionType === 'open-colab-modal') {
        selectedBranchToShare.value = tenantActiveBranches.value[0]?.id || '';
        colabModal.value = true;
    } else if (actionType === 'view-active-roster') {
        openBranchDetail(currentBranch.value);
    } else if (actionType === 'filter-own') {
        activeFilter.value = 'own';
    }
};

const handleNewItem = () => {
    newBranchModal.value = true;
};

const handleSubmitForm = () => {
    if (newBranchModal.value) {
        handleCreateBranch();
    } else if (colabModal.value) {
        handleCreateColabLink();
    }
};

onMounted(() => {
    window.addEventListener('app:new-item', handleNewItem);
    window.addEventListener('app:submit-form', handleSubmitForm);
});

onUnmounted(() => {
    window.removeEventListener('app:new-item', handleNewItem);
    window.removeEventListener('app:submit-form', handleSubmitForm);
});
</script>

<template>
    <Head title="Branch Outlets & Sub-Locations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Header & Multi-Branch Subscription Quota -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-bold tracking-tight text-foreground">Branch Outlets & Locations</h2>
                        <Badge variant="outline" class="border-primary/20 bg-primary/10 text-xs font-bold uppercase text-primary">
                            {{ currentTenant.name }}
                        </Badge>
                        <Badge variant="default" class="bg-emerald-600 text-xs font-bold text-white"> {{ currentTenant.plan }} Subscription </Badge>
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                        <ShieldCheck class="inline size-3.5 text-emerald-600" />
                        <span
                            >Tenant Multi-Location Isolation: Managing {{ branchesList.length }} outlets with
                            {{ branchesList.reduce((acc, b) => acc + (b.staffMembers?.length ?? 0), 0) }} assigned staff members.</span
                        >
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        id="branch-tour-guide-btn"
                        variant="outline"
                        size="sm"
                        class="h-9 gap-1.5 border-primary/30 text-xs font-semibold text-primary hover:bg-primary/10"
                        @click="openBranchGuide(0)"
                    >
                        <Compass class="size-4" />
                        Page Guide
                    </Button>

                    <Button
                        id="branch-tour-colab-btn"
                        variant="outline"
                        size="sm"
                        class="h-9 gap-1.5 border-primary/30 text-xs text-primary hover:bg-primary/10"
                        @click="
                            () => {
                                selectedBranchToShare = tenantActiveBranches[0]?.id || '';
                                colabModal = true;
                            }
                        "
                    >
                        <Handshake class="size-4" />
                        Link Colab Partner
                    </Button>

                    <Button
                        id="branch-tour-create-btn"
                        size="sm"
                        class="h-9 gap-1.5 bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                        @click="
                            () => {
                                newBranch.parentBranchId = tenantActiveBranches[0]?.id || '';
                                newBranchModal = true;
                            }
                        "
                    >
                        <Plus class="size-4" />
                        Create Branch / Sub-Branch
                    </Button>
                </div>
            </div>

            <!-- Organization Branch Subscription Quota & Overview Bar -->
            <div id="branch-tour-quota" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card class="space-y-1 border border-border/80 bg-card p-3.5">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Branch Quota</span>
                        <Building2 class="size-4 text-primary" />
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-mono text-xl font-black text-foreground">{{ effectiveBranches.length }}</span>
                        <span class="text-xs text-muted-foreground">/ 10 Allowed</span>
                    </div>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-muted">
                        <div
                            class="h-1.5 rounded-full bg-primary"
                            :style="{ width: `${Math.min(100, (effectiveBranches.length / 10) * 100)}%` }"
                        ></div>
                    </div>
                </Card>

                <Card class="space-y-1 border border-border/80 bg-card p-3.5">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total Assigned Staff</span>
                        <Users class="size-4 text-emerald-600" />
                    </div>
                    <div class="font-mono text-xl font-black text-foreground">{{ totalTenantStaff }} Staff Members</div>
                    <span class="block text-[10px] text-muted-foreground">Across {{ effectiveBranches.length }} operating outlets</span>
                </Card>

                <Card class="space-y-1 border border-border/80 bg-card p-3.5">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total Stock Valuation</span>
                        <TrendingUp class="size-4 text-sky-500" />
                    </div>
                    <div class="font-mono text-xl font-black text-foreground text-primary">
                        {{ formatCurrency(totalTenantStock) }}
                    </div>
                    <span class="block text-[10px] text-muted-foreground">Network-wide active inventory</span>
                </Card>

                <Card class="space-y-1 border border-border/80 bg-card p-3.5">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Monthly Sales Run-Rate</span>
                        <DollarSign class="size-4 text-amber-500" />
                    </div>
                    <div class="font-mono text-xl font-black text-emerald-600 text-foreground dark:text-emerald-400">
                        {{ formatCurrency(totalTenantMonthlySales) }}
                    </div>
                    <span class="block text-[10px] text-muted-foreground">MTD revenue across outlets</span>
                </Card>
            </div>

            <!-- Spotlight: Active Operating POS Station -->
            <Card id="branch-tour-active-station" class="border-2 border-primary/40 bg-gradient-to-br from-card via-card to-primary/5 shadow-md">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow"
                                >
                                    <Store class="size-5" />
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <CardTitle class="text-lg font-black text-foreground">{{ currentBranch.name }}</CardTitle>
                                        <Badge variant="success" class="px-2 py-0.5 text-[10px] font-bold">CURRENT ACTIVE POS STATION</Badge>
                                        <Badge
                                            v-if="currentBranch.isColabBranch"
                                            variant="outline"
                                            class="border-amber-500/30 bg-amber-500/10 text-[10px] font-bold text-amber-700"
                                        >
                                            Partner Colab Outlet
                                        </Badge>
                                        <Badge v-else-if="currentBranch.branchType" variant="outline" class="text-[10px] font-bold">
                                            {{ currentBranch.branchType }}
                                        </Badge>
                                    </div>
                                    <CardDescription class="mt-0.5 flex items-center gap-1.5 text-xs">
                                        <MapPin class="size-3 text-muted-foreground" />
                                        {{ currentBranch.location }}
                                        <span v-if="currentBranch.parentBranchName" class="font-medium text-primary">
                                            (Linked to {{ currentBranch.parentBranchName }})
                                        </span>
                                    </CardDescription>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-xs">
                            <Button size="sm" variant="outline" class="h-8 gap-1.5 text-xs font-semibold" @click="openBranchDetail(currentBranch)">
                                <Users class="size-3.5" />
                                Staff Roster & Details
                            </Button>

                            <Badge variant="outline" class="font-mono font-bold">{{ currentBranch.code }}</Badge>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-5">
                        <div class="space-y-1 rounded-xl border border-border/80 bg-card/80 p-3">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-[11px] font-medium">Today's Sales</span>
                                <DollarSign class="size-3.5 text-emerald-600" />
                            </div>
                            <div class="font-mono text-base font-black text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(currentBranch.todaySales) }}
                            </div>
                            <span class="text-[10px] text-muted-foreground">{{ currentBranch.transactionsToday }} transactions</span>
                        </div>

                        <div class="space-y-1 rounded-xl border border-border/80 bg-card/80 p-3">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-[11px] font-medium">Inventory Value</span>
                                <TrendingUp class="size-3.5 text-primary" />
                            </div>
                            <div class="font-mono text-base font-black text-primary">
                                {{ formatCurrency(currentBranch.currentStockValue) }}
                            </div>
                            <span class="text-[10px] text-muted-foreground">Active in-store stock</span>
                        </div>

                        <div class="space-y-1 rounded-xl border border-border/80 bg-card/80 p-3">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-[11px] font-medium">Branch Manager</span>
                                <User class="size-3.5 text-muted-foreground" />
                            </div>
                            <div class="truncate text-xs font-bold text-foreground">
                                {{ currentBranch.manager }}
                            </div>
                            <span class="block truncate font-mono text-[10px] text-muted-foreground">{{ currentBranch.phone }}</span>
                        </div>

                        <div class="space-y-1 rounded-xl border border-border/80 bg-card/80 p-3">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-[11px] font-medium">Assigned Staff</span>
                                <Users class="size-3.5 text-muted-foreground" />
                            </div>
                            <div class="text-xs font-bold text-foreground">{{ currentBranch.staffMembers?.length || 1 }} Staff Members</div>
                            <span class="flex items-center gap-1 text-[10px] font-medium text-emerald-600">
                                <span class="inline-block size-1.5 rounded-full bg-emerald-500"></span>
                                {{ currentBranch.staffMembers?.filter((s) => s.status === 'On Shift').length || 1 }} on shift now
                            </span>
                        </div>

                        <div class="space-y-1 rounded-xl border border-border/80 bg-card/80 p-3">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-[11px] font-medium">Operating Hours</span>
                                <Clock class="size-3.5 text-muted-foreground" />
                            </div>
                            <div class="truncate text-xs font-medium text-foreground">
                                {{ currentBranch.operatingHours }}
                            </div>
                            <span v-if="currentBranch.wifiSSID" class="flex items-center gap-1 truncate font-mono text-[10px] text-muted-foreground">
                                <Wifi class="size-3 shrink-0 text-primary" /> {{ currentBranch.wifiSSID }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Filter Tabs (All vs Direct vs Colab Partner vs Suspended) -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div id="branch-tour-filters" class="flex rounded-xl border border-border/80 bg-card p-1 text-xs font-semibold">
                    <button
                        type="button"
                        :class="[
                            'rounded-lg px-3.5 py-1.5 transition-all',
                            activeFilter === 'all' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeFilter = 'all'"
                    >
                        All Accessible ({{
                            allAccessibleBranches && allAccessibleBranches.length > 0 ? allAccessibleBranches.length : effectiveBranches.length
                        }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            'rounded-lg px-3.5 py-1.5 transition-all',
                            activeFilter === 'own' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeFilter = 'own'"
                    >
                        Direct & Sub-Branches ({{ effectiveBranches.length }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            'rounded-lg px-3.5 py-1.5 transition-all',
                            activeFilter === 'colab' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeFilter = 'colab'"
                    >
                        Colab Partner Outlets ({{ colabPartnerBranches?.length || 0 }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            'rounded-lg px-3.5 py-1.5 transition-all',
                            activeFilter === 'suspended' ? 'bg-rose-600 text-white shadow' : 'text-rose-600 hover:text-rose-700 dark:text-rose-400',
                        ]"
                        @click="activeFilter = 'suspended'"
                    >
                        Suspended ({{ effectiveBranches.filter((b) => b.status === 'Suspended').length }})
                    </button>
                </div>
            </div>

            <!-- Outlets List Cards -->
            <div id="branch-tour-outlets-grid" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="b in displayedBranches"
                    :key="b.id"
                    :class="[
                        'relative flex flex-col justify-between border transition-all duration-200 hover:shadow-md',
                        b.id === currentBranch.id && b.status === 'Active'
                            ? 'border-primary bg-primary/5 shadow ring-2 ring-primary/20'
                            : b.status === 'Suspended'
                              ? 'border-rose-300 bg-rose-50/20 opacity-80 dark:border-rose-900/60 dark:bg-rose-950/10'
                              : 'border-border/80 bg-card hover:border-primary/50',
                    ]"
                >
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <CardTitle class="text-base font-bold text-foreground">
                                        {{ b.name }}
                                    </CardTitle>

                                    <Badge
                                        v-if="b.isColabBranch"
                                        variant="outline"
                                        class="border-amber-500/30 bg-amber-500/10 text-[9px] font-bold text-amber-700"
                                    >
                                        Colab Partner
                                    </Badge>
                                    <Badge v-else-if="b.branchType" variant="outline" class="text-[9px] font-bold">
                                        {{ b.branchType }}
                                    </Badge>
                                </div>

                                <CardDescription class="mt-0.5 flex items-center gap-1 text-xs">
                                    <MapPin class="size-3 shrink-0 text-muted-foreground" />
                                    <span class="truncate">{{ b.location }}</span>
                                </CardDescription>

                                <!-- Sub-branch Parent Link -->
                                <div v-if="b.parentBranchName" class="mt-1 flex items-center gap-1 text-[11px] font-medium text-primary">
                                    <GitBranch class="size-3 text-primary" />
                                    <span>Parent: {{ b.parentBranchName }}</span>
                                </div>

                                <!-- Colab Host Org Link -->
                                <div
                                    v-if="b.isColabBranch && b.tenantName"
                                    class="mt-1 flex items-center gap-1 text-[11px] font-medium text-amber-700 dark:text-amber-300"
                                >
                                    <Building2 class="size-3 text-amber-600" />
                                    <span>Host: {{ b.tenantName }}</span>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <Badge :variant="b.status === 'Active' ? 'success' : 'destructive'" class="text-[10px] font-bold uppercase">
                                {{ b.status }}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="flex-1 space-y-3 pt-2 text-xs">
                        <!-- Sales & Stock metrics -->
                        <div class="grid grid-cols-2 gap-2 rounded-lg bg-muted/40 p-2.5 font-mono">
                            <div>
                                <span class="block font-sans text-[10px] text-muted-foreground">Today's Sales</span>
                                <span class="font-bold text-foreground">{{ formatCurrency(b.todaySales) }}</span>
                            </div>
                            <div>
                                <span class="block font-sans text-[10px] text-muted-foreground">Stock Value</span>
                                <span class="font-bold text-primary">{{ formatCurrency(b.currentStockValue) }}</span>
                            </div>
                        </div>

                        <!-- Staff Members Mini Roster -->
                        <div class="space-y-1 border-t border-border/60 pt-1">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span class="flex items-center gap-1 font-medium">
                                    <Users class="size-3 text-primary" />
                                    Staff Roster ({{ b.staffMembers?.length || 1 }})
                                </span>
                                <span class="font-medium text-foreground">Mgr: {{ b.manager }}</span>
                            </div>

                            <!-- Staff member avatar tags -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                <span
                                    v-for="s in (b.staffMembers || []).slice(0, 3)"
                                    :key="s.id"
                                    class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[10px] font-medium"
                                >
                                    <span :class="['size-1.5 rounded-full', s.status === 'On Shift' ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                                    {{ s.name.split(' ')[0] }} ({{ s.role.split(' ')[0] }})
                                </span>
                                <span v-if="(b.staffMembers?.length || 0) > 3" class="text-[10px] text-muted-foreground">
                                    +{{ (b.staffMembers?.length || 0) - 3 }} more
                                </span>
                            </div>
                        </div>

                        <!-- Storage & Terminal usage -->
                        <div class="space-y-1 border-t border-border/60 pt-1 text-[11px]">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="flex items-center gap-1"><HardDrive class="size-3" /> Storage Usage</span>
                                <span class="font-mono">{{ b.storageUsedMB }}MB / {{ b.storageLimitMB }}MB</span>
                            </div>
                            <div class="h-1 w-full rounded-full bg-muted">
                                <div class="h-1 rounded-full bg-primary" :style="{ width: `${(b.storageUsedMB / b.storageLimitMB) * 100}%` }"></div>
                            </div>
                        </div>

                        <!-- Colab Links list if this branch is shared -->
                        <div
                            v-if="b.colabLinks && b.colabLinks.length > 0"
                            class="space-y-1 rounded-lg border border-primary/20 bg-primary/5 p-2 text-[10px]"
                        >
                            <div class="flex items-center gap-1 font-bold text-primary">
                                <Handshake class="size-3" />
                                <span>Collaborating Partners ({{ b.colabLinks.length }})</span>
                            </div>
                            <div v-for="c in b.colabLinks" :key="c.linkedTenantId" class="flex justify-between text-muted-foreground">
                                <span class="truncate">{{ c.linkedTenantName }}</span>
                                <Badge variant="outline" class="px-1 py-0 text-[9px]">{{ c.permission }}</Badge>
                            </div>
                        </div>
                    </CardContent>

                    <!-- Card Actions Toolbar -->
                    <div class="mt-auto flex items-center justify-between gap-2 border-t border-border/50 p-3 pt-0">
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8 gap-1 text-xs text-muted-foreground hover:text-foreground"
                            @click="openBranchDetail(b)"
                        >
                            <Users class="size-3.5" />
                            Manage Staff
                        </Button>

                        <div class="flex items-center gap-1.5">
                            <!-- Suspend / Reactivate Action (Only for own branches) -->
                            <Button
                                v-if="!b.isColabBranch"
                                size="sm"
                                variant="outline"
                                :class="[
                                    'h-8 gap-1 text-xs',
                                    b.status === 'Active'
                                        ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20'
                                        : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/20',
                                ]"
                                @click="handleToggleStatus(b, $event)"
                            >
                                <PauseCircle v-if="b.status === 'Active'" class="size-3.5" />
                                <PlayCircle v-else class="size-3.5" />
                                {{ b.status === 'Active' ? 'Suspend' : 'Resume' }}
                            </Button>

                            <!-- Select Active Branch for POS -->
                            <Button
                                v-if="b.status === 'Active'"
                                size="sm"
                                :variant="b.id === currentBranch.id ? 'default' : 'secondary'"
                                class="h-8 text-xs font-bold"
                                @click="handleSwitchBranch(b.id, b.name)"
                            >
                                {{ b.id === currentBranch.id ? 'Selected' : 'Use in POS' }}
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Staff Roster & Detailed Branch Telemetry Drawer / Modal -->
        <Dialog :open="detailModalOpen" @update:open="(v) => (detailModalOpen = v)">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader v-if="selectedBranchDetail">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <DialogTitle class="text-lg font-bold">{{ selectedBranchDetail.name }}</DialogTitle>
                            <Badge :variant="selectedBranchDetail.status === 'Active' ? 'success' : 'destructive'" class="text-[10px]">
                                {{ selectedBranchDetail.status }}
                            </Badge>
                        </div>
                        <Badge variant="outline" class="font-mono">{{ selectedBranchDetail.code }}</Badge>
                    </div>
                    <p class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                        <MapPin class="size-3 text-muted-foreground" />
                        {{ selectedBranchDetail.location }}
                    </p>
                </DialogHeader>

                <div v-if="selectedBranchDetail" class="space-y-4 py-2 text-xs">
                    <!-- Branch Specs Grid -->
                    <div class="grid grid-cols-3 gap-2 rounded-xl border border-border/80 bg-muted/30 p-3">
                        <div>
                            <span class="block text-[10px] text-muted-foreground">Operating Hours</span>
                            <span class="font-semibold">{{ selectedBranchDetail.operatingHours }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-muted-foreground">Store WiFi SSID</span>
                            <span class="font-mono font-semibold">{{ selectedBranchDetail.wifiSSID || 'Not Configured' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-muted-foreground">Terminals Count</span>
                            <span class="font-semibold">{{ selectedBranchDetail.terminalsCount }} POS Registers</span>
                        </div>
                    </div>

                    <!-- Staff Members Roster Header & Add Button -->
                    <div class="flex items-center justify-between border-t border-border/60 pt-2">
                        <div>
                            <h4 class="text-sm font-bold text-foreground">
                                Assigned Staff Members ({{ selectedBranchDetail.staffMembers?.length || 0 }})
                            </h4>
                            <p class="text-[11px] text-muted-foreground">Manage cashiers, floor managers, and shift attendants for this outlet.</p>
                        </div>
                        <Button size="sm" class="h-8 gap-1 bg-primary text-xs text-primary-foreground" @click="newStaffFormOpen = !newStaffFormOpen">
                            <UserPlus class="size-3.5" />
                            Assign Staff
                        </Button>
                    </div>

                    <!-- New Staff Input Form inline -->
                    <div v-if="newStaffFormOpen" class="space-y-3 rounded-xl border border-primary/30 bg-primary/5 p-3">
                        <div class="text-xs font-bold text-primary">Assign New Staff Member</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label class="text-[11px]">Staff Name</Label>
                                <Input v-model="newStaff.name" placeholder="e.g. John Kinyanjui" class="mt-1 h-8 text-xs" />
                            </div>
                            <div>
                                <Label class="text-[11px]">Role / Position</Label>
                                <select
                                    v-model="newStaff.role"
                                    class="mt-1 h-8 w-full rounded-md border border-input bg-background px-2 text-xs font-semibold"
                                >
                                    <option value="Branch Manager">Branch Manager</option>
                                    <option value="Head Cashier">Head Cashier</option>
                                    <option value="Pharmacist">Pharmacist</option>
                                    <option value="Sales Attendant">Sales Attendant</option>
                                    <option value="Inventory Officer">Inventory Officer</option>
                                    <option value="Security">Security Officer</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label class="text-[11px]">Telephone</Label>
                                <Input v-model="newStaff.phone" placeholder="+254 7XX XXX XXX" class="mt-1 h-8 font-mono text-xs" />
                            </div>
                            <div>
                                <Label class="text-[11px]">Shift Schedule</Label>
                                <select v-model="newStaff.shift" class="mt-1 h-8 w-full rounded-md border border-input bg-background px-2 text-xs">
                                    <option value="Morning">Morning Shift (07:00 - 15:00)</option>
                                    <option value="Evening">Evening Shift (15:00 - 23:00)</option>
                                    <option value="Full Day">Full Day Coverage</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-1">
                            <Button size="sm" variant="ghost" class="h-7 text-xs" @click="newStaffFormOpen = false">Cancel</Button>
                            <Button size="sm" class="h-7 bg-primary text-xs" @click="handleAddStaffMember">Save Staff</Button>
                        </div>
                    </div>

                    <!-- Staff List Table -->
                    <div class="overflow-hidden rounded-xl border border-border/80">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-border/60 bg-muted/50 font-semibold text-muted-foreground">
                                <tr>
                                    <th class="p-2.5">Staff Name</th>
                                    <th class="p-2.5">Role</th>
                                    <th class="p-2.5">Shift</th>
                                    <th class="p-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="s in selectedBranchDetail.staffMembers" :key="s.id" class="hover:bg-muted/20">
                                    <td class="p-2.5 font-medium text-foreground">
                                        <div class="flex items-center gap-1.5">
                                            <UserCheck class="size-3 text-primary" />
                                            {{ s.name }}
                                        </div>
                                        <span class="pl-4.5 block font-mono text-[10px] text-muted-foreground">{{ s.phone }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <Badge variant="outline" class="text-[10px]">{{ s.role }}</Badge>
                                    </td>
                                    <td class="p-2.5 text-muted-foreground">{{ s.shift }}</td>
                                    <td class="p-2.5">
                                        <Badge :variant="s.status === 'On Shift' ? 'success' : 'outline'" class="text-[9px]">
                                            {{ s.status }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <DialogFooter class="gap-2 border-t border-border/60 pt-2">
                    <Button variant="ghost" size="sm" @click="detailModalOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create Branch / Sub-Branch Modal -->
        <Dialog :open="newBranchModal" @update:open="(v) => (newBranchModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Create Branch or Sub-Branch</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Outlet Classification</Label>
                        <select v-model="newBranch.branchType" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option value="Main HQ">Main Headquarters / Flagship</option>
                            <option value="Sub-Branch">Sub-Branch / Satellite Store</option>
                            <option value="Kiosk Outlet">Express Kiosk / Booth</option>
                            <option value="Distribution Yard">Distribution Yard / Warehouse</option>
                        </select>
                    </div>

                    <!-- If Sub-Branch, Choose Parent Branch -->
                    <div v-if="newBranch.branchType !== 'Main HQ'">
                        <Label class="text-xs">Parent Main Branch</Label>
                        <select
                            v-model="newBranch.parentBranchId"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs font-semibold"
                        >
                            <option v-for="b in tenantActiveBranches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code }})</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-xs">Branch / Outlet Name</Label>
                        <Input v-model="newBranch.name" placeholder="e.g. Westlands City Mall Sub-Branch" class="mt-1 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Outlet Code</Label>
                            <Input v-model="newBranch.code" placeholder="WST-02" class="mt-1 font-mono text-xs" />
                        </div>
                        <div>
                            <Label class="text-xs">Telephone Contact</Label>
                            <Input v-model="newBranch.phone" placeholder="+254 7XX XXX XXX" class="mt-1 font-mono text-xs" />
                        </div>
                    </div>

                    <div>
                        <Label class="text-xs">Physical Location / Address</Label>
                        <Input v-model="newBranch.location" placeholder="e.g. 2nd Floor City Mall, Nairobi" class="mt-1 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Branch Manager</Label>
                            <Input v-model="newBranch.manager" placeholder="e.g. Joyce Ndinda" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <Label class="text-xs">Store WiFi SSID</Label>
                            <Input v-model="newBranch.wifiSSID" placeholder="e.g. CityMall_Store_POS" class="mt-1 font-mono text-xs" />
                        </div>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="newBranchModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isCreatingBranch" @click="handleCreateBranch">Register Outlet</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Link Collaborative Partner Branch Modal -->
        <Dialog :open="colabModal" @update:open="(v) => (colabModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Handshake class="size-4 text-primary" />
                        Link Collaborative Partner Branch
                    </DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <p class="text-[11px] text-muted-foreground">
                        Authorize a partner organization to access an in-store concession, shared warehouse, or joint fulfillment station.
                    </p>

                    <div>
                        <Label class="text-xs">Select Your Branch to Share</Label>
                        <select
                            v-model="selectedBranchToShare"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs font-semibold"
                        >
                            <option v-for="b in tenantActiveBranches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code }})</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-xs">Partner Tenant Organization</Label>
                        <select
                            v-model="targetPartnerTenantId"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs font-semibold"
                        >
                            <option v-for="t in allTenants.filter((x) => x.id !== currentTenant.id)" :key="t.id" :value="t.id">
                                {{ t.name }} ({{ t.businessType }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-xs">Collaboration Purpose</Label>
                        <select v-model="colabType" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option value="Cross-Store Concession">Cross-Store Concession (In-store counter)</option>
                            <option value="Warehouse Fulfillment">Warehouse Fulfillment & Cross-docking</option>
                            <option value="B2B Distribution Hub">B2B Distribution & Dispatch Hub</option>
                            <option value="Joint Operating Facility">Joint Operating Facility</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-xs">Granted Access Level</Label>
                        <select
                            v-model="colabPermission"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs font-bold text-primary"
                        >
                            <option value="Stock Visibility & Transfer">Stock Visibility & Inventory Transfer Only</option>
                            <option value="Full POS & Inventory">Full POS Register & Inventory Control</option>
                            <option value="View Only">View Telemetry Only</option>
                        </select>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="colabModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isCreatingColabLink" @click="handleCreateColabLink">Authorize Colab Link</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Interactive Branches & Sub-Locations Guide Overlay -->
        <BranchGuideOverlay v-model:open="guideOverlayOpen" v-model:current-step="guideCurrentStep" @action="handleGuideAction" />
    </AppLayout>
</template>
