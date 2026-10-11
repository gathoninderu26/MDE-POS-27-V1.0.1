<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BusinessType } from '@/composables/useSuperAdmin';
import { allModules, businessTypeProfiles, useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Building2,
    CheckCircle2,
    ChefHat,
    ClipboardList,
    DollarSign,
    ExternalLink,
    Fuel,
    Layers,
    MapPin,
    Package,
    Pill,
    Receipt,
    RefreshCw,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
    Truck,
    Users,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    tenants?: any[];
}>();

const { tenants: mockTenants, applyBusinessTypePreset, toggleTenantModule, updateTenantModules } = useSuperAdmin();
const { success, error, info } = useToast();

const displayedTenants = computed(() => {
    if (props.tenants && props.tenants.length > 0) {
        return props.tenants;
    }
    return mockTenants;
});

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
const selectedTenant = ref<any | null>(null);
const moduleModal = ref(false);
const pendingModules = ref<string[]>([]);
const isSaving = ref(false);

const openModuleManager = (tenant: any) => {
    selectedTenant.value = tenant;
    pendingModules.value = [...(tenant.enabledModules || tenant.enabled_modules || [])];
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
    info('Preset Applied', `${businessType} preset loaded (${pendingModules.value.length} modules). Review and save to confirm.`);
};

const handleSaveModules = () => {
    if (!selectedTenant.value) return;
    isSaving.value = true;

    // Persist to backend DB if real tenant
    router.patch(
        `/super-admin/tenants/${selectedTenant.value.id}/detail`,
        {
            enabled_modules: pendingModules.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                updateTenantModules(selectedTenant.value.id, pendingModules.value);
                if (selectedTenant.value) {
                    selectedTenant.value.enabledModules = [...pendingModules.value];
                    selectedTenant.value.enabled_modules = [...pendingModules.value];
                }
                success('Modules Updated', `${selectedTenant.value.name} now has ${pendingModules.value.length} active pages.`);
                moduleModal.value = false;
            },
            onError: () => {
                error('Save Failed', 'Could not save modules to database.');
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const handleApplyBusinessType = (tenant: any, type: BusinessType) => {
    const modules = [...businessTypeProfiles[type].modules];
    router.patch(
        `/super-admin/tenants/${tenant.id}/detail`,
        {
            business_type: type,
            enabled_modules: modules,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                tenant.businessType = type;
                tenant.business_type = type;
                tenant.enabledModules = modules;
                tenant.enabled_modules = modules;
                applyBusinessTypePreset(tenant.id, type);
                success('Business Type Applied', `${tenant.name} switched to ${type} preset with ${modules.length} active pages.`);
            },
            onError: () => {
                error('Failed', 'Could not update business type on server.');
            },
        },
    );
};
</script>

<template>
    <Head title="Super Admin — Module Manager" />
    <SuperAdminLayout>
        <div class="mx-auto max-w-[1600px] space-y-6 p-6">
            <!-- Header -->
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-zinc-100">
                    <Layers class="size-5 text-violet-400" />
                    Module Manager
                </h1>
                <p class="mt-0.5 text-xs text-zinc-500">
                    Configure which system modules each tenant can access based on their business type. Apply presets or customize module-by-module.
                </p>
            </div>

            <!-- Business Type Preset Cards -->
            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-zinc-400">Business Type Presets</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    <div
                        v-for="type in businessTypes"
                        :key="type"
                        :class="['space-y-2 rounded-xl border p-3', colorMap[businessTypeProfiles[type].color]]"
                    >
                        <p class="text-xs font-extrabold">{{ type }}</p>
                        <p class="text-[10px] leading-relaxed opacity-80">{{ businessTypeProfiles[type].description }}</p>
                        <p class="font-mono text-[10px] font-bold">{{ businessTypeProfiles[type].modules.length }} modules</p>
                    </div>
                </div>
            </div>

            <!-- Per-Tenant Module Configuration -->
            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-zinc-400">Per-Tenant Module Configuration</h3>
                <div class="space-y-3">
                    <div
                        v-for="tenant in displayedTenants"
                        :key="tenant.id"
                        class="rounded-xl border border-zinc-800 bg-zinc-900 p-4"
                        :class="tenant.status === 'Suspended' && 'opacity-60'"
                    >
                        <!-- Tenant header row -->
                        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-violet-600/30 bg-violet-600/20">
                                    <Building2 class="size-4 text-violet-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-zinc-100">{{ tenant.name }}</p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                        <span
                                            :class="[
                                                'rounded border px-1.5 py-0.5 text-[10px] font-bold',
                                                colorMap[businessTypeProfiles[tenant.businessType || tenant.business_type]?.color || 'slate'],
                                            ]"
                                        >
                                            {{ tenant.businessType || tenant.business_type }}
                                        </span>
                                        <span
                                            :class="[
                                                'rounded border px-1.5 py-0.5 font-mono text-[10px] font-extrabold',
                                                tenant.plan === 'Enterprise'
                                                    ? 'border-violet-600/30 bg-violet-600/20 text-violet-300'
                                                    : tenant.plan === 'Pro'
                                                      ? 'border-sky-600/30 bg-sky-600/20 text-sky-300'
                                                      : 'border-zinc-600/30 bg-zinc-700/60 text-zinc-300',
                                            ]"
                                        >
                                            {{ tenant.plan }}
                                        </span>
                                        <span class="text-[10px] text-zinc-500"
                                            >{{ (tenant.enabledModules || tenant.enabled_modules || []).length }} modules active</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Quick business type switcher -->
                                <select
                                    class="h-8 rounded-lg border border-zinc-700 bg-zinc-800 px-2 text-[11px] text-zinc-200 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                    :value="tenant.businessType || tenant.business_type"
                                    @change="handleApplyBusinessType(tenant, ($event.target as HTMLSelectElement).value as BusinessType)"
                                >
                                    <option v-for="type in businessTypes" :key="type" :value="type">{{ type }}</option>
                                </select>

                                <Button
                                    size="sm"
                                    class="h-8 border border-violet-600/30 bg-violet-600/20 text-[11px] text-violet-300 hover:bg-violet-600/40"
                                    variant="ghost"
                                    @click="openModuleManager(tenant)"
                                >
                                    <Settings class="mr-1 size-3.5" />
                                    Customize Modules
                                </Button>

                                <a
                                    :href="`/super-admin/tenants/${tenant.id}/detail?tab=modules`"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-zinc-700 bg-zinc-800 px-2.5 text-[11px] font-semibold text-zinc-300 transition-colors hover:border-zinc-500 hover:text-white"
                                    title="Open Full Tenant Configuration"
                                >
                                    <ExternalLink class="size-3" />
                                    Full Setup
                                </a>
                            </div>
                        </div>

                        <!-- Active module badges grid -->
                        <div class="flex flex-wrap gap-1.5">
                            <div
                                v-for="mod in allModules"
                                :key="mod.key"
                                :class="[
                                    'flex items-center gap-1 rounded-lg border px-2 py-1 text-[10px] font-semibold transition-all',
                                    (tenant.enabledModules || tenant.enabled_modules || []).includes(mod.key)
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                        : 'border-zinc-700/40 bg-zinc-800/40 text-zinc-600 opacity-40',
                                ]"
                            >
                                <component :is="iconMap[mod.icon] || Package" class="size-3 shrink-0" />
                                <span>{{ mod.label }}</span>
                                <CheckCircle2
                                    v-if="(tenant.enabledModules || tenant.enabled_modules || []).includes(mod.key)"
                                    class="ml-0.5 size-3 text-emerald-400"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module Customization Modal -->
        <Dialog :open="moduleModal" @update:open="(v) => (moduleModal = v)">
            <DialogContent class="max-h-[90vh] overflow-y-auto border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold"> Customize Modules — {{ selectedTenant?.name }} </DialogTitle>
                    <DialogDescription class="sr-only">Enable or disable feature modules for {{ selectedTenant?.name }}</DialogDescription>
                </DialogHeader>

                <!-- Quick preset buttons -->
                <div class="py-2">
                    <p class="mb-2 text-[11px] font-semibold uppercase text-zinc-500">Apply Business Type Preset</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="type in businessTypes"
                            :key="type"
                            type="button"
                            :class="[
                                'rounded border px-2.5 py-1 text-[10px] font-bold transition-all',
                                selectedTenant?.businessType === type
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : 'border-zinc-700 text-zinc-400 hover:border-zinc-500 hover:text-zinc-100',
                            ]"
                            @click="handleApplyPreset(type)"
                        >
                            {{ type }}
                        </button>
                    </div>
                </div>

                <!-- Module Grid -->
                <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-2">
                    <button
                        v-for="mod in allModules"
                        :key="mod.key"
                        type="button"
                        :class="[
                            'flex items-center gap-3 rounded-xl border p-3 text-left transition-all',
                            pendingModules.includes(mod.key)
                                ? 'border-emerald-500/40 bg-emerald-500/10'
                                : 'border-zinc-700 bg-zinc-800/60 hover:border-zinc-500',
                        ]"
                        @click="togglePendingModule(mod.key)"
                    >
                        <div
                            :class="[
                                'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                pendingModules.includes(mod.key) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-zinc-700 text-zinc-500',
                            ]"
                        >
                            <component :is="iconMap[mod.icon] || Package" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold leading-tight text-zinc-100">{{ mod.label }}</p>
                            <p class="mt-0.5 text-[10px] leading-snug text-zinc-500">{{ mod.description }}</p>
                        </div>
                        <CheckCircle2 v-if="pendingModules.includes(mod.key)" class="size-4 shrink-0 text-emerald-400" />
                        <div v-else class="size-4 shrink-0 rounded-full border border-zinc-600"></div>
                    </button>
                </div>

                <div class="py-1 text-[11px] text-zinc-500">
                    <span class="font-bold text-emerald-400">{{ pendingModules.length }}</span> of {{ allModules.length }} modules selected
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="moduleModal = false">Cancel</Button>
                    <Button
                        size="sm"
                        :disabled="isSaving"
                        class="bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60"
                        @click="handleSaveModules"
                    >
                        <RefreshCw v-if="isSaving" class="mr-1 size-3 animate-spin" />
                        {{ isSaving ? 'Saving…' : 'Save Module Configuration' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
