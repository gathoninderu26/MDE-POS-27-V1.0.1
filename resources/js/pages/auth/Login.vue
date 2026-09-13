<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/composables/useTenant';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    Building2,
    CircleDollarSign,
    Crown,
    KeyRound,
    LoaderCircle,
    Pill,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Store,
    Truck,
    UserCheck,
    Utensils,
    Wrench,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { currentTenant, setTenant, isSubdomain } = useTenant();

interface StaffRolePreset {
    role: string;
    prefix: string;
    label: string;
    description: string;
    icon: any;
}

const staffPresets: StaffRolePreset[] = [
    { role: 'Owner', prefix: 'owner', label: 'Owner', description: 'Full access to all 12 modules & administration', icon: Crown },
    { role: 'Manager', prefix: 'manager', label: 'Manager', description: 'POS, catalog, inventory & sales operations', icon: ShieldCheck },
    { role: 'Cashier', prefix: 'cashier', label: 'Cashier', description: 'POS register, barcode scanning & sales history', icon: ShoppingCart },
    { role: 'Inventory Manager', prefix: 'inventory', label: 'Inventory', description: 'Stock replenishment, batches & suppliers', icon: Boxes },
    { role: 'Accountant', prefix: 'accountant', label: 'Accountant', description: 'Expenses, petty cash & P&L analytics', icon: CircleDollarSign },
    { role: 'Supervisor', prefix: 'supervisor', label: 'Supervisor', description: 'POS supervisor, returns & price check', icon: UserCheck },
];

const selectedRole = ref<string>('owner');

const form = useForm({
    email: '',
    password: '',
    remember: true,
    tenant: '',
});

const applyPreset = (prefix: string) => {
    selectedRole.value = prefix;
    const slug = currentTenant.value?.slug || 'nairobi-fresh-mart';
    form.email = `${prefix}@${slug}.mdepos.co.ke`;
    form.password = 'password';
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const tenantParam = urlParams.get('tenant');
        if (tenantParam) {
            setTenant(tenantParam);
        }
    }
    // Apply initial role preset with current tenant slug
    applyPreset('owner');
});

// Watch currentTenant slug changes to update credentials dynamically
watch(
    () => currentTenant.value?.slug,
    () => {
        applyPreset(selectedRole.value);
    },
);

const submit = () => {
    form.tenant = currentTenant.value?.slug || '';
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const demoLoggingIn = ref(false);

const instantDemoLogin = (role: 'Cashier' | 'Owner') => {
    demoLoggingIn.value = true;
    router.post(
        route('demo.login'),
        {
            tenant: currentTenant.value?.slug || 'nairobi-fresh-mart',
            role: role,
        },
        {
            onFinish: () => {
                demoLoggingIn.value = false;
            },
        },
    );
};

const getBusinessIcon = (type?: string) => {
    switch (type) {
        case 'Pharmacy':
            return Pill;
        case 'Hardware':
            return Wrench;
        case 'Restaurant':
            return Utensils;
        case 'Electronics':
            return Smartphone;
        case 'Wholesale':
        case 'Wholesale & Retail':
            return Truck;
        default:
            return Store;
    }
};

const getVerticalBadgeColor = (type?: string) => {
    switch (type) {
        case 'Pharmacy':
            return 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/30';
        case 'Hardware':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30';
        case 'Restaurant':
            return 'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/30';
        case 'Electronics':
            return 'bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-500/30';
        case 'Wholesale':
        case 'Wholesale & Retail':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30';
        default:
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
    }
};

const centralDomainUrl = computed(() => {
    if (typeof window === 'undefined') return '/';
    const protocol = window.location.protocol;
    const port = window.location.port ? `:${window.location.port}` : '';
    const hostname = window.location.hostname;

    let base = hostname;
    if (base.endsWith('.localhost') || base === 'localhost') {
        return `${protocol}//localhost${port}/`;
    } else if (base.includes('.')) {
        const parts = base.split('.');
        if (parts.length > 2) {
            return `${protocol}//${parts.slice(1).join('.')}${port}/`;
        }
    }
    return '/';
});
</script>

<template>
    <AuthBase
        :title="isSubdomain ? `Log in to ${currentTenant.name}` : 'Store Terminal Sign In'"
        :description="isSubdomain ? 'Enter your credentials to access your isolated POS workspace' : 'Enter your store credentials to access your POS terminal'"
    >
        <Head :title="`Log in - ${currentTenant.name}`" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- Dynamic Business Identity Card -->
        <div class="mb-5 space-y-3">
            <div class="p-4 rounded-xl border border-primary/20 bg-primary/5 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                        <ShieldCheck class="size-3.5 text-primary" />
                        Dedicated Store Portal
                    </span>
                    <Badge variant="outline" :class="['text-[9px] font-bold uppercase shrink-0', getVerticalBadgeColor(currentTenant.businessType)]">
                        {{ currentTenant.businessType }}
                    </Badge>
                </div>

                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground font-black shrink-0 shadow-sm">
                        <component :is="getBusinessIcon(currentTenant.businessType)" class="size-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-extrabold text-sm sm:text-base truncate text-foreground">
                            {{ currentTenant.name }}
                        </h2>
                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground font-mono">
                            <span class="text-primary font-semibold">{{ currentTenant.slug }}</span>
                            <span>•</span>
                            <span>{{ currentTenant.plan }} Plan</span>
                        </div>
                    </div>
                </div>

                <!-- Navigation link to switch store domain -->
                <div class="pt-2 border-t border-primary/10 flex items-center justify-between text-xs">
                    <span class="text-muted-foreground text-[11px]">Wrong business?</span>
                    <a
                        :href="centralDomainUrl"
                        class="inline-flex items-center gap-1 text-primary hover:underline font-semibold text-[11px] cursor-pointer"
                    >
                        <ArrowLeft class="size-3" />
                        <span>Change Store Domain</span>
                    </a>
                </div>
            </div>

            <!-- Instant 1-Click Demo Login Block -->
            <div class="p-3.5 rounded-xl border border-primary/30 bg-primary/10 space-y-2.5">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                        <Zap class="size-3.5 text-primary" />
                        1-Click Instant Demo Login
                    </span>
                    <span class="text-[10px] text-muted-foreground font-mono">No password required</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        size="sm"
                        class="h-9 text-xs font-bold gap-1.5 bg-primary hover:bg-primary/90 cursor-pointer shadow-sm"
                        :disabled="demoLoggingIn"
                        @click="instantDemoLogin('Cashier')"
                    >
                        <ShoppingCart class="size-3.5 shrink-0" />
                        <span class="truncate">Cashier (POS)</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9 text-xs font-bold gap-1.5 border-border hover:border-primary/60 cursor-pointer"
                        :disabled="demoLoggingIn"
                        @click="instantDemoLogin('Owner')"
                    >
                        <Crown class="size-3.5 text-amber-500 shrink-0" />
                        <span class="truncate">Store Owner</span>
                    </Button>
                </div>
            </div>

            <!-- Quick Staff Role Selector Presets -->
            <div class="p-3 rounded-xl border border-border/70 bg-muted/30 space-y-2">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                        <KeyRound class="size-3" />
                        Fill Credentials for Role
                    </span>
                    <span class="text-[10px] text-muted-foreground font-mono">password: password</span>
                </div>

                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        v-for="preset in staffPresets"
                        :key="preset.prefix"
                        type="button"
                        @click="applyPreset(preset.prefix)"
                        :class="[
                            'px-2 py-1.5 rounded-lg border text-xs font-semibold flex items-center justify-center gap-1.5 transition-all cursor-pointer',
                            selectedRole === preset.prefix
                                ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                : 'bg-background/80 hover:bg-background border-border text-foreground hover:border-primary/40',
                        ]"
                        :title="preset.description"
                    >
                        <component :is="preset.icon" class="size-3 shrink-0" />
                        <span class="truncate">{{ preset.label }}</span>
                    </button>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label for="email" class="text-xs font-semibold">Staff Email Address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        :placeholder="`owner@${currentTenant.slug}.mdepos.co.ke`"
                        class="h-10 text-xs font-mono"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-xs font-semibold">Terminal Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-xs" tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="••••••••"
                        class="h-10 text-xs"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-2 text-xs">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Remember terminal credentials</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-1 w-full h-10 font-bold" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    <span>Sign In to {{ currentTenant.name }}</span>
                </Button>
            </div>

            <!-- Multi-Tenant Isolation Footer -->
            <div class="pt-2 border-t border-border/60">
                <div class="flex items-center justify-center gap-2 p-2 rounded-xl bg-muted/40 border border-border text-[11px] text-muted-foreground">
                    <ShieldCheck class="size-3.5 text-emerald-500 shrink-0" />
                    <span>Multi-Tenant Partition Active • DB Scoped</span>
                </div>
            </div>
        </form>
    </AuthBase>
</template>
