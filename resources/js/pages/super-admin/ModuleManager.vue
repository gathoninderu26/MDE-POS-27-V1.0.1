<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BusinessType, PlatformTenant } from '@/composables/useSuperAdmin';
import { allModules, businessTypeProfiles, useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Building2,
    Check,
    ChefHat,
    CheckCircle2,
    ChevronDown,
    ClipboardList,
    DollarSign,
    Fuel,
    Layers,
    MapPin,
    Package,
    Pill,
    Receipt,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
    Truck,
    UtensilsCrossed,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { tenants, applyBusinessTypePreset, toggleTenantModule, updateTenantModules } = useSuperAdmin();
const { success, info } = useToast();

// Map icon string keys to actual icon components
const iconMap: Record<string, any> = {
    ShoppingCart,
    Receipt,
    Package,
    Boxes,
    ClipboardList,
    Users,
    Truck,
    DollarSign,
    BarChart3,
    ShieldCheck,
    Store,
    Settings,
    Pill,
    UtensilsCrossed,
    ChefHat,
    Fuel,
    Star,
    MapPin,
};

const businessTypes = Object.keys(businessTypeProfiles) as BusinessType[];

const colorMap: Record<string, string> = {
    emerald: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
    blue: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    rose: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
    orange: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
    amber: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
    teal: 'bg-teal-500/20 text-teal-300 border-teal-500/30',
    violet: 'bg-violet-500/20 text-violet-300 border-violet-500/30',
    pink: 'bg-pink-500/20 text-pink-300 border-pink-500/30',
    yellow: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
    slate: 'bg-slate-500/20 text-slate-300 border-slate-500/30',
};

// Selected tenant for module management
const selectedTenant = ref<PlatformTenant | null>(null);
const moduleModal = ref(false);
const pendingModules = ref<string[]>([]);

const openModuleManager = (tenant: PlatformTenant) => {
    selectedTenant.value = tenant;
    pendingModules.value = [...tenant.enabledModules];
    moduleModal.value = true;
};

const togglePendingModule = (key: string) => {
    const idx = pendingModules.value.indexOf(key);
    if (idx !== -1) pendingModules.value.splice(idx, 1);
    else pendingModules.value.push(key);
};

const handleApplyPreset = (businessType: BusinessType) => {
    if (!selectedTenant.value) return;
    pendingModules.value = [...businessTypeProfiles[businessType].modules];
    info('Preset Applied', `${businessType} preset loaded. Review and save to confirm.`);
};

const handleSaveModules = () => {
    if (!selectedTenant.value) return;
    updateTenantModules(selectedTenant.value.id, pendingModules.value);
    success('Modules Updated', `${selectedTenant.value.name} now has ${pendingModules.value.length} active modules.`);
    moduleModal.value = false;
};

const handleApplyBusinessType = (tenant: PlatformTenant, type: BusinessType) => {
    applyBusinessTypePreset(tenant.id, type);
    success('Business Type Applied', `${tenant.name} switched to ${type} profile with ${businessTypeProfiles[type].modules.length} modules.`);
};
</script>

<template>
    <Head title="Super Admin — Module Manager" />
    <SuperAdminLayout>
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div>
                <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                    <Layers class="size-5 text-violet-400" />
                    Module Manager
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Configure which system modules each tenant can access based on their business type. Apply presets or customize module-by-module.
                </p>
            </div>

            <!-- Business Type Preset Cards -->
            <div>
                <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Business Type Presets</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
                    <div
                        v-for="type in businessTypes"
                        :key="type"
                        :class="['rounded-xl border p-3 space-y-2', colorMap[businessTypeProfiles[type].color]]"
                    >
                        <p class="text-xs font-extrabold">{{ type }}</p>
                        <p class="text-[10px] opacity-80 leading-relaxed">{{ businessTypeProfiles[type].description }}</p>
                        <p class="text-[10px] font-mono font-bold">{{ businessTypeProfiles[type].modules.length }} modules</p>
                    </div>
                </div>
            </div>

            <!-- Per-Tenant Module Configuration -->
            <div>
                <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Per-Tenant Module Configuration</h3>
                <div class="space-y-3">
                    <div
                        v-for="tenant in tenants"
                        :key="tenant.id"
                        class="rounded-xl border border-zinc-800 bg-zinc-900 p-4"
                        :class="tenant.status === 'Suspended' && 'opacity-60'"
                    >
                        <!-- Tenant header row -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-xl bg-violet-600/20 border border-violet-600/30 flex items-center justify-center shrink-0">
                                    <Building2 class="size-4 text-violet-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-zinc-100">{{ tenant.name }}</p>
                                    <div class="flex items-center gap-2 flex-wrap mt-0.5">
                                        <span
                                            :class="['text-[10px] font-bold px-1.5 py-0.5 rounded border', colorMap[businessTypeProfiles[tenant.businessType]?.color || 'slate']]"
                                        >
                                            {{ tenant.businessType }}
                                        </span>
                                        <span
                                            :class="[
                                                'text-[10px] font-extrabold font-mono px-1.5 py-0.5 rounded border',
                                                tenant.plan === 'Enterprise' ? 'text-violet-300 bg-violet-600/20 border-violet-600/30' :
                                                tenant.plan === 'Pro' ? 'text-sky-300 bg-sky-600/20 border-sky-600/30' :
                                                'text-zinc-300 bg-zinc-700/60 border-zinc-600/30',
                                            ]"
                                        >
                                            {{ tenant.plan }}
                                        </span>
                                        <span class="text-[10px] text-zinc-500">{{ tenant.enabledModules.length }} modules active</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Quick business type switcher -->
                                <select
                                    class="h-8 rounded-lg border border-zinc-700 bg-zinc-800 px-2 text-[11px] text-zinc-200 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                    :value="tenant.businessType"
                                    @change="handleApplyBusinessType(tenant, ($event.target as HTMLSelectElement).value as BusinessType)"
                                >
                                    <option v-for="type in businessTypes" :key="type" :value="type">{{ type }}</option>
                                </select>

                                <Button
                                    size="sm"
                                    class="h-8 text-[11px] bg-violet-600/20 text-violet-300 hover:bg-violet-600/40 border border-violet-600/30"
                                    variant="ghost"
                                    @click="openModuleManager(tenant)"
                                >
                                    <Settings class="size-3.5 mr-1" />
                                    Customize Modules
                                </Button>
                            </div>
                        </div>

                        <!-- Active module badges grid -->
                        <div class="flex flex-wrap gap-1.5">
                            <div
                                v-for="mod in allModules"
                                :key="mod.key"
                                :class="[
                                    'flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-semibold border transition-all',
                                    tenant.enabledModules.includes(mod.key)
                                        ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20'
                                        : 'bg-zinc-800/40 text-zinc-600 border-zinc-700/40 opacity-40',
                                ]"
                            >
                                <component :is="iconMap[mod.icon] || Package" class="size-3 shrink-0" />
                                <span>{{ mod.label }}</span>
                                <CheckCircle2
                                    v-if="tenant.enabledModules.includes(mod.key)"
                                    class="size-3 text-emerald-400 ml-0.5"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module Customization Modal -->
        <Dialog :open="moduleModal" @update:open="(v) => (moduleModal = v)">
            <DialogContent class="sm:max-w-2xl bg-zinc-900 border-zinc-700 text-zinc-100 max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        Customize Modules — {{ selectedTenant?.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only">Enable or disable feature modules for {{ selectedTenant?.name }}</DialogDescription>
                </DialogHeader>

                <!-- Quick preset buttons -->
                <div class="py-2">
                    <p class="text-[11px] text-zinc-500 font-semibold uppercase mb-2">Apply Business Type Preset</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="type in businessTypes"
                            :key="type"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded border text-[10px] font-bold transition-all',
                                selectedTenant?.businessType === type
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-500',
                            ]"
                            @click="handleApplyPreset(type)"
                        >
                            {{ type }}
                        </button>
                    </div>
                </div>

                <!-- Module Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 py-2">
                    <button
                        v-for="mod in allModules"
                        :key="mod.key"
                        type="button"
                        :class="[
                            'flex items-center gap-3 p-3 rounded-xl border text-left transition-all',
                            pendingModules.includes(mod.key)
                                ? 'border-emerald-500/40 bg-emerald-500/10'
                                : 'border-zinc-700 bg-zinc-800/60 hover:border-zinc-500',
                        ]"
                        @click="togglePendingModule(mod.key)"
                    >
                        <div
                            :class="[
                                'size-8 rounded-lg flex items-center justify-center shrink-0',
                                pendingModules.includes(mod.key) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-zinc-700 text-zinc-500',
                            ]"
                        >
                            <component :is="iconMap[mod.icon] || Package" class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-zinc-100 leading-tight">{{ mod.label }}</p>
                            <p class="text-[10px] text-zinc-500 leading-snug mt-0.5">{{ mod.description }}</p>
                        </div>
                        <CheckCircle2
                            v-if="pendingModules.includes(mod.key)"
                            class="size-4 text-emerald-400 shrink-0"
                        />
                        <div v-else class="size-4 rounded-full border border-zinc-600 shrink-0"></div>
                    </button>
                </div>

                <div class="text-[11px] text-zinc-500 py-1">
                    <span class="font-bold text-emerald-400">{{ pendingModules.length }}</span> of {{ allModules.length }} modules selected
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="moduleModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleSaveModules">
                        Save Module Configuration
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
