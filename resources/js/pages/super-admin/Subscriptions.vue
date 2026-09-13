<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import type { BillingCycle, PlatformTenant, SubscriptionPlan, TenantSubscriptionInvoice } from '@/composables/useSuperAdmin';
import { billingCycleProfiles, planFeatures, subscriptionPlans, useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    Download,
    Layers,
    Plus,
    RefreshCw,
    Search,
    Send,
    ShieldAlert,
    Smartphone,
    TrendingUp,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const {
    tenants,
    totalMRR,
    activeTenants,
    subscriptionInvoices,
    assignTenantSubscription,
    extendTenantSubscription,
} = useSuperAdmin();
const { success, warning, info } = useToast();

const searchQuery = ref('');
const selectedCycleFilter = ref('All');
const selectedPlanFilter = ref('All');

// Add Subscription Modal
const addSubModal = ref(false);
const newSubTenantId = ref(tenants[0]?.id || 'T-001');
const newSubPlan = ref<SubscriptionPlan>('Pro');
const newSubCycle = ref<BillingCycle>('Monthly');
const newSubAmount = ref(6500);
const newSubPaymentMethod = ref<TenantSubscriptionInvoice['paymentMethod']>('M-Pesa Express (STK)');
const newSubAutoRenew = ref(true);

// Extend Subscription Modal
const extendModal = ref(false);
const selectedInvoice = ref<TenantSubscriptionInvoice | null>(null);
const extraDays = ref(30);

// Default cycle pricing calculator
const calculateDefaultPrice = (plan: SubscriptionPlan, cycle: BillingCycle) => {
    const baseMonthly = subscriptionPlans[plan].price;
    if (cycle === 'Weekly') return Math.round(baseMonthly * 0.28);
    if (cycle === 'Monthly') return baseMonthly;
    if (cycle === 'Quarterly') return Math.round(baseMonthly * 3 * 0.92);
    if (cycle === 'Semi-Annual') return Math.round(baseMonthly * 6 * 0.88);
    if (cycle === 'Annual') return Math.round(baseMonthly * 10); // 2 months free
    return baseMonthly;
};

const handleCycleChange = () => {
    newSubAmount.value = calculateDefaultPrice(newSubPlan.value, newSubCycle.value);
};

const handlePlanChange = () => {
    newSubAmount.value = calculateDefaultPrice(newSubPlan.value, newSubCycle.value);
};

const handleCreateSubscription = () => {
    const inv = assignTenantSubscription(
        newSubTenantId.value,
        newSubPlan.value,
        newSubCycle.value,
        newSubAmount.value,
        newSubPaymentMethod.value,
        newSubAutoRenew.value
    );
    if (inv) {
        success(
            'Subscription Activated',
            `Assigned ${newSubPlan.value} (${newSubCycle.value}) to ${inv.tenantName} for ${formatCurrency(newSubAmount.value)}.`
        );
    }
    addSubModal.value = false;
};

const openExtendModal = (inv: TenantSubscriptionInvoice) => {
    selectedInvoice.value = inv;
    extendModal.value = true;
};

const handleConfirmExtend = () => {
    if (!selectedInvoice.value) return;
    extendTenantSubscription(selectedInvoice.value.tenantId, extraDays.value);
    success(
        'Validity Extended',
        `Extended subscription for ${selectedInvoice.value.tenantName} by +${extraDays.value} days.`
    );
    extendModal.value = false;
};

const handleSendMpesaPrompt = (inv: TenantSubscriptionInvoice) => {
    success(
        'M-Pesa STK Dispatched',
        `Prompt for ${formatCurrency(inv.amount)} sent to ${inv.tenantName} finance phone.`
    );
};

const handleDownloadInvoice = (inv: TenantSubscriptionInvoice) => {
    info('Invoice Downloaded', `Downloading invoice PDF ${inv.invoiceNumber}...`);
};

// Filtered Invoices
const filteredInvoices = computed(() => {
    return subscriptionInvoices.filter((inv) => {
        const matchSearch =
            !searchQuery.value ||
            inv.tenantName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            inv.invoiceNumber.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchCycle = selectedCycleFilter.value === 'All' || inv.billingCycle === selectedCycleFilter.value;
        const matchPlan = selectedPlanFilter.value === 'All' || inv.plan === selectedPlanFilter.value;
        return matchSearch && matchCycle && matchPlan;
    });
});

const projectedARR = computed(() => totalMRR * 12);
</script>

<template>
    <Head title="Super Admin — Manage Subscriptions" />
    <SuperAdminLayout>
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                        <CreditCard class="size-5 text-violet-400" />
                        Subscription & Billing Engine
                    </h1>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Manage weekly, monthly, quarterly, semi-annual, and annual subscriptions for all tenants.
                    </p>
                </div>

                <Button
                    size="sm"
                    class="h-8 text-xs bg-violet-600 hover:bg-violet-700 text-white gap-1.5"
                    @click="addSubModal = true"
                >
                    <Plus class="size-3.5" />
                    Add / Assign Subscription
                </Button>
            </div>

            <!-- Revenue KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 font-semibold uppercase flex items-center gap-1.5 mb-2">
                        <TrendingUp class="size-3.5 text-sky-400" /> Monthly Revenue Run-Rate
                    </p>
                    <p class="text-2xl font-extrabold text-sky-400 font-mono">{{ formatCurrency(totalMRR) }}</p>
                    <p class="text-[10px] text-zinc-600 mt-1">MRR across {{ activeTenants }} active tenants</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 font-semibold uppercase flex items-center gap-1.5 mb-2">
                        <Zap class="size-3.5 text-amber-400" /> Annual Projected ARR
                    </p>
                    <p class="text-2xl font-extrabold text-amber-400 font-mono">{{ formatCurrency(projectedARR) }}</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Annualized recurring contract volume</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 font-semibold uppercase flex items-center gap-1.5 mb-2">
                        <Calendar class="size-3.5 text-violet-400" /> Active Billing Cycles
                    </p>
                    <div class="flex items-center gap-1.5 text-[10px] text-zinc-400 mt-1 flex-wrap">
                        <span class="px-1.5 py-0.5 rounded bg-zinc-800 font-bold">Weekly</span>
                        <span class="px-1.5 py-0.5 rounded bg-zinc-800 font-bold">Monthly</span>
                        <span class="px-1.5 py-0.5 rounded bg-zinc-800 font-bold">Quarterly</span>
                        <span class="px-1.5 py-0.5 rounded bg-zinc-800 font-bold">Annual</span>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 font-semibold uppercase flex items-center gap-1.5 mb-2">
                        <AlertTriangle class="size-3.5 text-rose-400" /> Overdue / At Risk
                    </p>
                    <p class="text-2xl font-extrabold text-rose-400 font-mono">
                        {{ subscriptionInvoices.filter(i => i.status === 'Overdue').length }} Invoices
                    </p>
                    <p class="text-[10px] text-zinc-600 mt-1">Tenants requiring payment follow-up</p>
                </div>
            </div>

            <!-- Billing Frequencies / Cycles Comparison Matrix -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider">
                        Billing Frequency Profiles & Pricing Schedule
                    </h3>
                    <span class="text-[11px] text-zinc-500 font-mono">5 Supported Billing Cadences</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
                    <div
                        v-for="(profile, cycleKey) in billingCycleProfiles"
                        :key="cycleKey"
                        class="p-3 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-2"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-zinc-200">{{ cycleKey }}</span>
                            <span class="text-[9px] font-bold text-violet-400 bg-violet-600/10 px-1.5 py-0.5 rounded">
                                {{ profile.days }} Days
                            </span>
                        </div>
                        <p class="text-[10px] text-emerald-400 font-bold">{{ profile.discount }}</p>
                        <div class="text-[10px] text-zinc-400 space-y-0.5 pt-1 border-t border-zinc-800">
                            <div>Basic: <strong class="text-zinc-200">{{ formatCurrency(calculateDefaultPrice('Basic', cycleKey as BillingCycle)) }}</strong></div>
                            <div>Pro: <strong class="text-sky-300">{{ formatCurrency(calculateDefaultPrice('Pro', cycleKey as BillingCycle)) }}</strong></div>
                            <div>Enterprise: <strong class="text-violet-300">{{ formatCurrency(calculateDefaultPrice('Enterprise', cycleKey as BillingCycle)) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per-Tenant Invoices & Active Subscriptions Table -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-zinc-800">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Tenant Subscriptions & Billing Invoices</h3>
                        <p class="text-[11px] text-zinc-500">Manage, renew, and track payment status across all tenant organizations.</p>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Frequency filter -->
                        <div class="flex items-center gap-1 text-xs">
                            <span class="text-zinc-500 text-[11px]">Cycle:</span>
                            <button
                                v-for="c in ['All', 'Weekly', 'Monthly', 'Quarterly', 'Annual']"
                                :key="c"
                                type="button"
                                :class="[
                                    'px-2 py-0.5 rounded border text-[10px] font-semibold transition-all',
                                    selectedCycleFilter === c ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-700 text-zinc-400 hover:text-zinc-200',
                                ]"
                                @click="selectedCycleFilter = c"
                            >
                                {{ c }}
                            </button>
                        </div>

                        <div class="relative w-56">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-500" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search invoices, tenants..."
                                class="h-8 w-full rounded-lg border border-zinc-700 bg-zinc-800 pl-8 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-800/60 text-zinc-400 border-b border-zinc-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Invoice #</th>
                                <th class="px-4 py-3 font-semibold uppercase">Tenant Organization</th>
                                <th class="px-4 py-3 font-semibold uppercase text-center">Plan</th>
                                <th class="px-4 py-3 font-semibold uppercase text-center">Billing Cycle</th>
                                <th class="px-4 py-3 font-semibold uppercase text-right">Amount (KES)</th>
                                <th class="px-4 py-3 font-semibold uppercase">Next Renewal</th>
                                <th class="px-4 py-3 font-semibold uppercase text-center">Status</th>
                                <th class="px-4 py-3 font-semibold uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="inv in filteredInvoices" :key="inv.id" class="hover:bg-zinc-800/40 transition-colors">
                                <td class="px-5 py-3.5 font-mono font-bold text-zinc-200">
                                    {{ inv.invoiceNumber }}
                                    <div class="text-[10px] text-zinc-500 font-normal">{{ inv.paymentMethod }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-zinc-100">{{ inv.tenantName }}</div>
                                    <div class="text-[10px] text-zinc-500">ID: {{ inv.tenantId }}</div>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'text-[10px] font-extrabold font-mono px-2 py-0.5 rounded border',
                                            inv.plan === 'Enterprise' ? 'text-violet-300 bg-violet-600/20 border-violet-600/30' :
                                            inv.plan === 'Pro' ? 'text-sky-300 bg-sky-600/20 border-sky-600/30' :
                                            'text-zinc-300 bg-zinc-700/60 border-zinc-600/30',
                                        ]"
                                    >
                                        {{ inv.plan }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700">
                                        {{ inv.billingCycle }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-right font-mono font-bold text-sky-400">
                                    {{ formatCurrency(inv.amount) }}
                                </td>

                                <td class="px-4 py-3.5 text-[11px] text-zinc-300">
                                    <div class="flex items-center gap-1.5 font-mono">
                                        <Calendar class="size-3 text-violet-400" />
                                        <span>{{ inv.dueDate }}</span>
                                    </div>
                                    <div v-if="inv.autoRenew" class="text-[9px] text-emerald-400 mt-0.5">
                                        Auto-Renew Active
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'text-[10px] font-bold px-2 py-0.5 rounded',
                                            inv.status === 'Paid' ? 'bg-emerald-600/20 text-emerald-300' :
                                            inv.status === 'Pending' ? 'bg-amber-600/20 text-amber-300' :
                                            inv.status === 'Grace Period' ? 'bg-sky-600/20 text-sky-300' :
                                            'bg-rose-600/20 text-rose-300',
                                        ]"
                                    >
                                        {{ inv.status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-zinc-400 hover:text-emerald-400 hover:bg-emerald-600/10 transition-colors"
                                            title="Send M-Pesa STK Renewal Prompt"
                                            @click="handleSendMpesaPrompt(inv)"
                                        >
                                            <Smartphone class="size-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-zinc-400 hover:text-violet-400 hover:bg-violet-600/10 transition-colors"
                                            title="Extend Validity / Grace Period"
                                            @click="openExtendModal(inv)"
                                        >
                                            <Clock class="size-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-zinc-400 hover:text-sky-400 hover:bg-sky-600/10 transition-colors"
                                            title="Download Invoice PDF"
                                            @click="handleDownloadInvoice(inv)"
                                        >
                                            <Download class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add / Assign Subscription Modal -->
        <Dialog :open="addSubModal" @update:open="(v) => (addSubModal = v)">
            <DialogContent class="sm:max-w-lg bg-zinc-900 border-zinc-700 text-zinc-100">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <CreditCard class="size-4 text-violet-400" />
                        Create / Assign Tenant Subscription
                    </DialogTitle>
                    <DialogDescription class="sr-only">Create or assign a subscription plan and billing cycle to a tenant</DialogDescription>
                </DialogHeader>

                <div class="space-y-3.5 py-2 text-xs">
                    <div>
                        <Label class="text-zinc-400 text-[11px]">Select Target Tenant</Label>
                        <select
                            v-model="newSubTenantId"
                            class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                        >
                            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} (Current: {{ t.plan }})</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-zinc-400 text-[11px]">Subscription Plan</Label>
                            <select
                                v-model="newSubPlan"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                                @change="handlePlanChange"
                            >
                                <option value="Basic">Basic (1 Branch / 5 Users)</option>
                                <option value="Pro">Pro (5 Branches / 20 Users)</option>
                                <option value="Enterprise">Enterprise (Unlimited)</option>
                            </select>
                        </div>

                        <div>
                            <Label class="text-zinc-400 text-[11px]">Billing Frequency / Cycle</Label>
                            <select
                                v-model="newSubCycle"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200 font-bold"
                                @change="handleCycleChange"
                            >
                                <option value="Weekly">Weekly (7 Days)</option>
                                <option value="Monthly">Monthly (30 Days)</option>
                                <option value="Quarterly">Quarterly (3 Months)</option>
                                <option value="Semi-Annual">Semi-Annual (6 Months)</option>
                                <option value="Annual">Annual (12 Months)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-zinc-400 text-[11px]">Billing Amount (KES)</Label>
                            <Input
                                v-model.number="newSubAmount"
                                type="number"
                                class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono font-bold"
                            />
                        </div>

                        <div>
                            <Label class="text-zinc-400 text-[11px]">Payment Channel</Label>
                            <select
                                v-model="newSubPaymentMethod"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                            >
                                <option value="M-Pesa Express (STK)">M-Pesa Express (STK Push)</option>
                                <option value="M-Pesa Paybill">M-Pesa Paybill</option>
                                <option value="Bank Wire / RTGS">Bank Wire / RTGS</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Cash / Cheque">Cash / Cheque</option>
                            </select>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 p-2.5 rounded-lg bg-zinc-800/40 border border-zinc-800 cursor-pointer">
                        <input
                            v-model="newSubAutoRenew"
                            type="checkbox"
                            class="rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                        />
                        <div>
                            <p class="font-bold text-zinc-200 text-xs">Enable Automatic Renewal</p>
                            <p class="text-[10px] text-zinc-500">Auto-bill via M-Pesa STK push or card on cycle renewal date</p>
                        </div>
                    </label>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="addSubModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleCreateSubscription">
                        Activate Subscription
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Extend Validity / Grace Period Modal -->
        <Dialog :open="extendModal" @update:open="(v) => (extendModal = v)">
            <DialogContent class="sm:max-w-md bg-zinc-900 border-zinc-700 text-zinc-100">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Clock class="size-4 text-violet-400" />
                        Extend Subscription Validity
                    </DialogTitle>
                    <DialogDescription class="sr-only">Add additional days to the active subscription period for {{ selectedInvoice?.tenantName }}</DialogDescription>
                </DialogHeader>

                <div v-if="selectedInvoice" class="space-y-3 py-2 text-xs">
                    <p class="text-zinc-300">
                        Extend active access for <strong class="text-white">{{ selectedInvoice.tenantName }}</strong>:
                    </p>

                    <div>
                        <Label class="text-zinc-400 text-[11px]">Select Additional Validity Days</Label>
                        <select
                            v-model.number="extraDays"
                            class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200 font-bold"
                        >
                            <option :value="7">+7 Days (Weekly Extension)</option>
                            <option :value="14">+14 Days (Two Weeks)</option>
                            <option :value="30">+30 Days (1 Month)</option>
                            <option :value="90">+90 Days (1 Quarter)</option>
                            <option :value="180">+180 Days (Semi-Annual)</option>
                            <option :value="365">+365 Days (1 Full Year)</option>
                        </select>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="extendModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleConfirmExtend">
                        Apply Extension
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
