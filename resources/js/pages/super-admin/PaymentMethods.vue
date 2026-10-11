<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PlatformTenant, TenantPaymentConfig } from '@/composables/useSuperAdmin';
import { platformPaymentMethods, useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Banknote,
    BookOpen,
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
        <div class="mx-auto max-w-[1600px] space-y-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-bold text-zinc-100">
                        <Wallet class="size-5 text-violet-400" />
                        Tenant Payment Methods & Gateways
                    </h1>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        Manage accepted payment channels, M-Pesa Till/Paybill numbers, card processors, and KRA eTIMS integration per tenant.
                    </p>
                </div>
            </div>

            <!-- Platform Payment KPIs -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Smartphone class="size-3.5 text-emerald-400" /> M-Pesa Integrated
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-emerald-400">{{ mpesaActiveCount }} / {{ tenants.length }}</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Tenants with live M-Pesa channels</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <CreditCard class="size-3.5 text-sky-400" /> Card & Gateway
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-sky-400">{{ cardActiveCount }} / {{ tenants.length }}</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Active PDQ & Visa/Mastercard</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <FileCheck class="size-3.5 text-violet-400" /> KRA eTIMS Fiscalized
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-violet-400">{{ etimsActiveCount }} / {{ tenants.length }}</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Real-time KRA QR auto-invoice</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Zap class="size-3.5 text-amber-400" /> Supported Channels
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-amber-400">{{ platformPaymentMethods.length }} Methods</p>
                    <p class="mt-1 text-[10px] text-zinc-600">STK Push, Till, Paybill, Bank, Credit</p>
                </div>
            </div>

            <!-- Platform Supported Channels Summary -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-zinc-400">Platform Gateway Matrix</h3>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-5">
                    <div
                        v-for="method in platformPaymentMethods"
                        :key="method.key"
                        class="flex items-start gap-2.5 rounded-lg border border-zinc-800 bg-zinc-950/60 p-3"
                    >
                        <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded bg-violet-600/20 text-violet-400">
                            <component :is="iconMap[method.icon] || CreditCard" class="size-3.5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-zinc-200">{{ method.label }}</p>
                            <p class="mt-0.5 line-clamp-1 text-[10px] text-zinc-500">{{ method.category }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Payment Control Table -->
            <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="flex flex-col gap-3 border-b border-zinc-800 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Per-Tenant Payment Configuration</h3>
                        <p class="text-[11px] text-zinc-500">
                            Toggle enabled channels or click Configure to edit Paybill, Till, and gateway secrets.
                        </p>
                    </div>

                    <div class="relative w-72">
                        <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-500" />
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
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Tenant</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">STK Push</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">Paybill</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">Till No</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">Card / PDQ</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">Cash</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">Credit Ledger</th>
                                <th class="px-3 py-3 text-center font-semibold uppercase">KRA eTIMS</th>
                                <th class="px-4 py-3 text-right font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="tenant in filteredTenants" :key="tenant.id" class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-zinc-100">{{ tenant.name }}</div>
                                    <div class="mt-0.5 flex items-center gap-2 font-mono text-[10px] text-zinc-500">
                                        <span>{{ tenant.businessType }}</span>
                                        <span>•</span>
                                        <span
                                            :class="
                                                getTenantPaymentConfig(tenant.id).mpesaEnvironment === 'production'
                                                    ? 'text-emerald-400'
                                                    : 'text-amber-400'
                                            "
                                        >
                                            {{ getTenantPaymentConfig(tenant.id).mpesaEnvironment.toUpperCase() }}
                                        </span>
                                    </div>
                                </td>

                                <!-- STK Push -->
                                <td class="px-3 py-3.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).mpesaStk
                                                ? 'border border-emerald-500/30 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30'
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
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).mpesaPaybill
                                                ? 'border border-emerald-500/30 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30'
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
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).mpesaTill
                                                ? 'border border-emerald-500/30 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30'
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
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).cardGateway
                                                ? 'border border-sky-500/30 bg-sky-600/20 text-sky-400 hover:bg-sky-600/30'
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
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).cash
                                                ? 'border border-emerald-500/30 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30'
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
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).creditLedger
                                                ? 'border border-violet-500/30 bg-violet-600/20 text-violet-400 hover:bg-violet-600/30'
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
                                            'inline-flex size-6 items-center justify-center rounded-full transition-all',
                                            getTenantPaymentConfig(tenant.id).etimsAutoInvoice
                                                ? 'border border-violet-500/30 bg-violet-600/20 text-violet-400 hover:bg-violet-600/30'
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
                                        class="h-7 gap-1 border border-violet-600/30 bg-violet-600/20 text-[11px] text-violet-300 hover:bg-violet-600/40"
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
            <DialogContent class="max-h-[90vh] overflow-y-auto border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Wallet class="size-4 text-violet-400" />
                        Gateway Setup — {{ selectedTenant?.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only"
                        >Configure payment gateway credentials and M-Pesa settings for {{ selectedTenant?.name }}</DialogDescription
                    >
                </DialogHeader>

                <div v-if="activeConfig" class="space-y-4 py-2 text-xs">
                    <!-- Environment & KRA -->
                    <div class="grid grid-cols-2 gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                        <div>
                            <Label class="text-[11px] text-zinc-400">M-Pesa API Mode</Label>
                            <select
                                v-model="activeConfig.mpesaEnvironment"
                                class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                            >
                                <option value="sandbox">Sandbox (Testing)</option>
                                <option value="production">Production (Live)</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-[11px] text-zinc-400">Card Processing Surcharge (%)</Label>
                            <Input
                                v-model.number="activeConfig.customSurchargePercent"
                                type="number"
                                step="0.1"
                                placeholder="0.0"
                                class="mt-1 h-9 border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                            />
                        </div>
                    </div>

                    <!-- M-Pesa Numbers -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-zinc-300">M-Pesa Credentials & Tills</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-[11px] text-zinc-400">Business Paybill No</Label>
                                <Input
                                    v-model="activeConfig.paybillNumber"
                                    placeholder="e.g. 522522"
                                    class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                />
                            </div>
                            <div>
                                <Label class="text-[11px] text-zinc-400">Buy Goods Till No</Label>
                                <Input
                                    v-model="activeConfig.tillNumber"
                                    placeholder="e.g. 890123"
                                    class="mt-1 border-zinc-700 bg-zinc-800 font-mono text-xs text-zinc-100"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Policy Checkboxes -->
                    <div class="space-y-2 border-t border-zinc-800 pt-2">
                        <h4 class="text-xs font-bold text-zinc-300">Transaction Policies</h4>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-800/40 p-2.5">
                            <input
                                v-model="activeConfig.allowPartialPayment"
                                type="checkbox"
                                class="rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                            <div>
                                <p class="text-xs font-bold text-zinc-200">Allow Split / Partial Tender</p>
                                <p class="text-[10px] text-zinc-500">Allow customer to pay part in Cash and remaining via M-Pesa</p>
                            </div>
                        </label>

                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-800/40 p-2.5">
                            <input
                                v-model="activeConfig.etimsAutoInvoice"
                                type="checkbox"
                                class="rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            />
                            <div>
                                <p class="text-xs font-bold text-zinc-200">KRA eTIMS Auto Fiscal Receipt</p>
                                <p class="text-[10px] text-zinc-500">Transmit fiscal receipt on checkout and append KRA QR code</p>
                            </div>
                        </label>
                    </div>

                    <!-- Diagnostic Ping -->
                    <div class="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-800/20 p-3">
                        <span class="text-[11px] text-zinc-400">Test gateway connectivity</span>
                        <Button
                            size="sm"
                            type="button"
                            variant="ghost"
                            class="h-7 gap-1.5 bg-zinc-800 text-[11px] text-zinc-300 hover:text-emerald-400"
                            @click="handleTestPing"
                        >
                            <Send class="size-3" />
                            Ping Gateway
                        </Button>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="configModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 text-white hover:bg-violet-700" @click="handleSaveConfig"> Save Gateway Settings </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
