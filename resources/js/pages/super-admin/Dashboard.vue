<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    CheckCircle2,
    Clock,
    CreditCard,
    ExternalLink,
    Globe,
    PauseCircle,
    PlayCircle,
    RefreshCw,
    Server,
    Shield,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    tenants?: any[];
    stats?: {
        total_tenants: number;
        active_tenants: number;
        suspended_tenants: number;
        trial_tenants: number;
        demo_tenants: number;
        total_users: number;
        total_mrr: number;
        total_branches: number;
        plan_breakdown: {
            Basic: number;
            Pro: number;
            Enterprise: number;
        };
    };
}>();

const { success, warning, error } = useToast();

const tenantsList = computed(() => props.tenants ?? []);
const totalTenants = computed(() => props.stats?.total_tenants ?? tenantsList.value.length);
const activeTenants = computed(() => props.stats?.active_tenants ?? tenantsList.value.filter((t) => t.status === 'Active').length);
const suspendedTenants = computed(() => props.stats?.suspended_tenants ?? tenantsList.value.filter((t) => t.status === 'Suspended').length);
const trialTenants = computed(() => props.stats?.trial_tenants ?? tenantsList.value.filter((t) => t.status === 'Trial').length);
const totalMRR = computed(() => props.stats?.total_mrr ?? 0);
const totalUsers = computed(() => props.stats?.total_users ?? 0);

const planColor: Record<string, string> = {
    Basic: 'outline',
    Pro: 'info',
    Enterprise: 'default',
};

const statusColor: Record<string, string> = {
    Active: 'success',
    Suspended: 'destructive',
    Trial: 'warning',
    Expired: 'destructive',
};

const handleSuspend = (id: string | number, name: string) => {
    router.patch(
        `/super-admin/tenants/${id}/suspend`,
        {},
        {
            onSuccess: () => warning('Tenant Suspended', `${name} has been suspended.`),
            onError: () => error('Error', 'Failed to suspend tenant.'),
        },
    );
};

const handleActivate = (id: string | number, name: string) => {
    router.patch(
        `/super-admin/tenants/${id}/activate`,
        {},
        {
            onSuccess: () => success('Tenant Activated', `${name} is now active.`),
            onError: () => error('Error', 'Failed to activate tenant.'),
        },
    );
};

// Platform health metrics (static for demo)
const platformHealth = [
    { label: 'API Response Time', value: '42ms', icon: Activity, color: 'text-emerald-400' },
    { label: 'Uptime (30d)', value: '99.98%', icon: Server, color: 'text-emerald-400' },
    { label: 'DB Queries/min', value: '8,412', icon: RefreshCw, color: 'text-sky-400' },
    { label: 'Active Sessions', value: '127', icon: Globe, color: 'text-violet-400' },
];
</script>

<template>
    <Head title="Super Admin — Platform Dashboard" />
    <SuperAdminLayout>
        <div class="mx-auto max-w-[1600px] space-y-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-1">
                <h1 class="flex items-center gap-2 text-xl font-bold text-zinc-100">
                    <Shield class="size-5 text-violet-400" />
                    Platform Overview
                </h1>
                <p class="text-xs text-zinc-500">Live view of all tenant accounts, subscription health, and platform metrics.</p>
            </div>

            <!-- Platform KPI Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Building2 class="size-3.5 text-violet-400" />
                        Total Tenants
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-zinc-100">{{ totalTenants }}</p>
                </div>
                <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <CheckCircle2 class="size-3.5 text-emerald-400" />
                        Active
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-emerald-400">{{ activeTenants }}</p>
                </div>
                <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Clock class="size-3.5 text-amber-400" />
                        On Trial
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-amber-400">{{ trialTenants }}</p>
                </div>
                <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <PauseCircle class="size-3.5 text-rose-400" />
                        Suspended
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-rose-400">{{ suspendedTenants }}</p>
                </div>
                <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <CreditCard class="size-3.5 text-sky-400" />
                        MRR
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-sky-400">{{ formatCurrency(totalMRR) }}</p>
                </div>
                <div class="space-y-1 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Users class="size-3.5 text-violet-400" />
                        Total Users
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-zinc-100">{{ totalUsers }}</p>
                </div>
            </div>

            <!-- Platform Health -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="metric in platformHealth"
                    :key="metric.label"
                    class="flex items-center gap-3 rounded-xl border border-zinc-800 bg-zinc-900/60 p-3"
                >
                    <component :is="metric.icon" :class="['size-5 shrink-0', metric.color]" />
                    <div>
                        <p class="text-[11px] text-zinc-500">{{ metric.label }}</p>
                        <p :class="['font-mono text-sm font-extrabold', metric.color]">{{ metric.value }}</p>
                    </div>
                </div>
            </div>

            <!-- All Tenants Table -->
            <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Registered Tenant Accounts</h3>
                        <p class="text-[11px] text-zinc-500">Manage subscription status, billing, and access for all tenant organizations.</p>
                    </div>
                    <Button size="sm" class="h-8 gap-1.5 bg-violet-600 text-xs text-white hover:bg-violet-700" as-child>
                        <a href="/super-admin/tenants">
                            <ExternalLink class="size-3.5" />
                            Manage All
                        </a>
                    </Button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Tenant / Organization</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Plan</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Status</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Users</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Branches</th>
                                <th class="px-4 py-3 text-right font-semibold uppercase">MRR</th>
                                <th class="px-4 py-3 font-semibold uppercase">Last Active</th>
                                <th class="px-4 py-3 text-right font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-if="tenantsList.length === 0">
                                <td colspan="8" class="px-5 py-8 text-center text-zinc-500">No tenant accounts registered yet.</td>
                            </tr>
                            <tr v-for="tenant in tenantsList" :key="tenant.id" class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-5 py-3">
                                    <div class="font-bold text-zinc-100">{{ tenant.name }}</div>
                                    <div class="font-mono text-[10px] text-zinc-500">{{ tenant.email }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded px-2 py-0.5 font-mono text-[10px] font-extrabold',
                                            tenant.plan === 'Enterprise'
                                                ? 'border border-violet-600/30 bg-violet-600/20 text-violet-300'
                                                : tenant.plan === 'Pro'
                                                  ? 'border border-sky-600/30 bg-sky-600/20 text-sky-300'
                                                  : 'border border-zinc-600/30 bg-zinc-700/60 text-zinc-300',
                                        ]"
                                    >
                                        {{ tenant.plan }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded px-2 py-0.5 text-[10px] font-bold',
                                            tenant.status === 'Active'
                                                ? 'bg-emerald-600/20 text-emerald-300'
                                                : tenant.status === 'Trial'
                                                  ? 'bg-amber-600/20 text-amber-300'
                                                  : 'bg-rose-600/20 text-rose-300',
                                        ]"
                                    >
                                        {{ tenant.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-mono text-zinc-300">
                                    {{ tenant.users_count ?? tenant.user_count ?? tenant.userCount ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-center font-mono text-zinc-300">
                                    {{ tenant.branch_count ?? tenant.branchCount ?? 1 }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-sky-400">
                                    {{ formatCurrency(tenant.monthly_revenue ?? tenant.monthlyRevenue ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-[11px] text-zinc-500">
                                    {{ tenant.last_active ?? tenant.lastActive ?? 'Recently' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button
                                            v-if="tenant.status === 'Active'"
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 px-2 text-[11px] text-rose-400 hover:bg-rose-500/10"
                                            @click="handleSuspend(tenant.id, tenant.name)"
                                        >
                                            <PauseCircle class="mr-1 size-3.5" />
                                            Suspend
                                        </Button>
                                        <Button
                                            v-else
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 px-2 text-[11px] text-emerald-400 hover:bg-emerald-500/10"
                                            @click="handleActivate(tenant.id, tenant.name)"
                                        >
                                            <PlayCircle class="mr-1 size-3.5" />
                                            Activate
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
