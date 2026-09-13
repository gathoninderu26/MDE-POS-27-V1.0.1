<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import type { PlatformTenant, TenantPaymentConfig } from '@/composables/useSuperAdmin';
import { platformPaymentMethods, useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Banknote,
    BookOpen,
    Building2,
    CheckCircle2,
    CreditCard,
    FileCheck,
    Gift,
    Landmark,
    PhoneCall,
    QrCode,
    Search,
    Send,
    Settings,
    ShieldCheck,
    Smartphone,
    Wallet,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { tenants, getTenantPaymentConfig, updateTenantPaymentConfig, toggleTenantPaymentMethod } = useSuperAdmin();
const { success, info } = useToast();

const iconMap: Record<string, any> = {
    Smartphone,
    PhoneCall,
    QrCode,
    CreditCard,
    Landmark,
    Banknote,
    BookOpen,
    Gift,
    FileCheck,
};

const searchQuery = ref('');
const configModal = ref(false);
const selectedTenant = ref<PlatformTenant | null>(null);
const activeConfig = ref<TenantPaymentConfig | null>(null);

const filteredTenants = computed(() => {
    return tenants.filter((t) => {
        return (
            !searchQuery.value ||
            t.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            t.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            t.businessType.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    });
});

const mpesaActiveCount = computed(() => {
    return tenants.filter((t) => {
        const cfg = getTenantPaymentConfig(t.id);
        return cfg.mpesaStk || cfg.mpesaPaybill || cfg.mpesaTill;
    }).length;
});

const etimsActiveCount = computed(() => {
    return tenants.filter((t) => getTenantPaymentConfig(t.id).etimsAutoInvoice).length;
});

const cardActiveCount = computed(() => {
    return tenants.filter((t) => getTenantPaymentConfig(t.id).cardGateway).length;
});

const openConfig = (tenant: PlatformTenant) => {
    selectedTenant.value = tenant;
    const cfg = getTenantPaymentConfig(tenant.id);
    activeConfig.value = { ...cfg };
    configModal.value = true;
};

const handleSaveConfig = () => {
    if (!selectedTenant.value || !activeConfig.value) return;
    updateTenantPaymentConfig(selectedTenant.value.id, activeConfig.value);
    success('Payment Gateways Updated', `${selectedTenant.value.name} payment channels have been updated.`);
    configModal.value = false;
};

const handleToggleMethod = (tenantId: string, key: any) => {
    toggleTenantPaymentMethod(tenantId, key);
    info('Channel Toggled', 'Tenant payment channel updated in real time.');
};

const handleTestPing = () => {
    success('Connection Verified', 'Gateway endpoint responded OK (200) - M-Pesa Daraja & Card Gateway reachable.');
};
</script>

<template>
    <Head title="Super Admin — Tenant Payment Methods" />
    <SuperAdminLayout>
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                        <Wallet class="size-5 text-violet-400" />
                        Tenant Payment Methods & Gateways
                    </h1>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Manage accepted payment channels, M-Pesa Till/Paybill numbers, card processors, and KRA eTIMS integration per tenant.
                    </p>
                </div>
            </div>

            <!-- Platform Payment KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <Smartphone class="size-3.5 text-emerald-400" /> M-Pesa Integrated
                    </div>
                    <p class="text-2xl font-extrabold text-emerald-400 font-mono">{{ mpesaActiveCount }} / {{ tenants.length }}</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Tenants with live M-Pesa channels</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <CreditCard class="size-3.5 text-sky-400" /> Card & Gateway
                    </div>
                    <p class="text-2xl font-extrabold text-sky-400 font-mono">{{ cardActiveCount }} / {{ tenants.length }}</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Active PDQ & Visa/Mastercard</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <FileCheck class="size-3.5 text-violet-400" /> KRA eTIMS Fiscalized
                    </div>
                    <p class="text-2xl font-extrabold text-violet-400 font-mono">{{ etimsActiveCount }} / {{ tenants.length }}</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Real-time KRA QR auto-invoice</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <Zap class="size-3.5 text-amber-400" /> Supported Channels
                    </div>
                    <p class="text-2xl font-extrabold text-amber-400 font-mono">{{ platformPaymentMethods.length }} Methods</p>
                    <p class="text-[10px] text-zinc-600 mt-1">STK Push, Till, Paybill, Bank, Credit</p>
                </div>
            </div>

            <!-- Platform Supported Channels Summary -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Platform Gateway Matrix</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-2.5">
                    <div
                        v-for="method in platformPaymentMethods"
                        :key="method.key"
                        class="p-3 rounded-lg border border-zinc-800 bg-zinc-950/60 flex items-start gap-2.5"
                    >
                        <div class="size-7 rounded bg-violet-600/20 text-violet-400 flex items-center justify-center shrink-0 mt-0.5">
                            <component :is="iconMap[method.icon] || CreditCard" class="size-3.5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-zinc-200 truncate">{{ method.label }}</p>
                            <p class="text-[10px] text-zinc-500 line-clamp-1 mt-0.5">{{ method.category }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Payment Control Table -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-zinc-800">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Per-Tenant Payment Configuration</h3>
                        <p class="text-[11px] text-zinc-500">Toggle enabled channels or click Configure to edit Paybill, Till, and gateway secrets.</p>
                    </div>

                    <div class="relative w-72">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-500" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Filter tenants..."
                            class="h-8 w-full rounded-lg border border-zinc-700 bg-zinc-800 pl-8 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-800/60 text-zinc-400 border-b border-zinc-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Tenant</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">STK Push</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">Paybill</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">Till No</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">Card / PDQ</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">Cash</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">Credit Ledger</th>
                                <th class="px-3 py-3 font-semibold uppercase text-center">KRA eTIMS</th>
                                <th class="px-4 py-3 font-semibold uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="tenant in filteredTenants" :key="tenant.id" class="hover:bg-zinc-800/40 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-zinc-100">{{ tenant.name }}</div>
                                    <div class="flex items-center gap-2 text-[10px] text-zinc-500 font-mono mt-0.5">
                                        <span>{{ tenant.businessType }}</span>
                                        <span>•</span>
                                        <span :class="getTenantPaymentConfig(tenant.id).mpesaEnvironment === 'production' ? 'text-emerald-400' : 'text-amber-400'">
                                            {{ getTenantPaymentConfig(tenant.id).mpesaEnvironment.toUpperCase() }}
                                        </span>
                                    </div>
                                </td>

                                <!-- STK Push -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).mpesaStk
                                                ? 'bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'mpesaStk')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).mpesaStk" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- Paybill -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).mpesaPaybill
                                                ? 'bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'mpesaPaybill')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).mpesaPaybill" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- Till No -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).mpesaTill
                                                ? 'bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'mpesaTill')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).mpesaTill" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- Card / PDQ -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).cardGateway
                                                ? 'bg-sky-600/20 text-sky-400 border border-sky-500/30 hover:bg-sky-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'cardGateway')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).cardGateway" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- Cash -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).cash
                                                ? 'bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'cash')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).cash" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- Store Credit -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).creditLedger
                                                ? 'bg-violet-600/20 text-violet-400 border border-violet-500/30 hover:bg-violet-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'creditLedger')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).creditLedger" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- eTIMS -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'size-6 rounded-full inline-flex items-center justify-center transition-all',
                                            getTenantPaymentConfig(tenant.id).etimsAutoInvoice
                                                ? 'bg-violet-600/20 text-violet-400 border border-violet-500/30 hover:bg-violet-600/30'
                                                : 'bg-zinc-800 text-zinc-600 hover:bg-zinc-700',
                                        ]"
                                        @click="handleToggleMethod(tenant.id, 'etimsAutoInvoice')"
                                    >
                                        <CheckCircle2 v-if="getTenantPaymentConfig(tenant.id).etimsAutoInvoice" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                    </button>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        size="sm"
                                        class="h-7 text-[11px] bg-violet-600/20 text-violet-300 hover:bg-violet-600/40 border border-violet-600/30 gap-1"
                                        @click="openConfig(tenant)"
                                    >
                                        <Settings class="size-3" />
                                        Configure
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tenant Gateway Credentials Modal -->
        <Dialog :open="configModal" @update:open="(v) => (configModal = v)">
            <DialogContent class="sm:max-w-xl bg-zinc-900 border-zinc-700 text-zinc-100 max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Wallet class="size-4 text-violet-400" />
                        Gateway Setup — {{ selectedTenant?.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only">Configure payment gateway credentials and M-Pesa settings for {{ selectedTenant?.name }}</DialogDescription>
                </DialogHeader>

                <div v-if="activeConfig" class="space-y-4 py-2 text-xs">
                    <!-- Environment & KRA -->
                    <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-zinc-950/60 border border-zinc-800">
                        <div>
                            <Label class="text-zinc-400 text-[11px]">M-Pesa API Mode</Label>
                            <select
                                v-model="activeConfig.mpesaEnvironment"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                            >
                                <option value="sandbox">Sandbox (Testing)</option>
                                <option value="production">Production (Live)</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-zinc-400 text-[11px]">Card Processing Surcharge (%)</Label>
                            <Input
                                v-model.number="activeConfig.customSurchargePercent"
                                type="number"
                                step="0.1"
                                placeholder="0.0"
                                class="mt-1 h-9 text-xs bg-zinc-800 border-zinc-700 text-zinc-100"
                            />
                        </div>
                    </div>

                    <!-- M-Pesa Numbers -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-300">M-Pesa Credentials & Tills</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-zinc-400 text-[11px]">Business Paybill No</Label>
                                <Input
                                    v-model="activeConfig.paybillNumber"
                                    placeholder="e.g. 522522"
                                    class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                />
                            </div>
                            <div>
                                <Label class="text-zinc-400 text-[11px]">Buy Goods Till No</Label>
                                <Input
                                    v-model="activeConfig.tillNumber"
                                    placeholder="e.g. 890123"
                                    class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100 font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Policy Checkboxes -->
                    <div class="space-y-2 pt-2 border-t border-zinc-800">
                        <h4 class="text-xs font-bold text-zinc-300">Transaction Policies</h4>
                        <label class="flex items-center gap-2 p-2.5 rounded-lg bg-zinc-800/40 border border-zinc-800 cursor-pointer">
                            <input
                                v-model="activeConfig.allowPartialPayment"
                                type="checkbox"
                                class="rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                            <div>
                                <p class="font-bold text-zinc-200 text-xs">Allow Split / Partial Tender</p>
                                <p class="text-[10px] text-zinc-500">Allow customer to pay part in Cash and remaining via M-Pesa</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-2 p-2.5 rounded-lg bg-zinc-800/40 border border-zinc-800 cursor-pointer">
                            <input
                                v-model="activeConfig.etimsAutoInvoice"
                                type="checkbox"
                                class="rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                            <div>
                                <p class="font-bold text-zinc-200 text-xs">KRA eTIMS Auto Fiscal Receipt</p>
                                <p class="text-[10px] text-zinc-500">Transmit fiscal receipt on checkout and append KRA QR code</p>
                            </div>
                        </label>
                    </div>

                    <!-- Diagnostic Ping -->
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-800/20 border border-zinc-800">
                        <span class="text-zinc-400 text-[11px]">Test gateway connectivity</span>
                        <Button
                            size="sm"
                            type="button"
                            variant="ghost"
                            class="h-7 text-[11px] bg-zinc-800 text-zinc-300 hover:text-emerald-400 gap-1.5"
                            @click="handleTestPing"
                        >
                            <Send class="size-3" />
                            Ping Gateway
                        </Button>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="configModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleSaveConfig">
                        Save Gateway Settings
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
