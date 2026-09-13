<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useBranch } from '@/composables/useBranch';
import type { UserRole } from '@/composables/usePermissions';
import { useDemoCountdown } from '@/composables/useDemoCountdown';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    Check,
    ChevronDown,
    Clock,
    Crown,
    Moon,
    ShoppingCart,
    Store,
    Sun,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage();
const { currentTenant, hasModule, isDemo } = useTenant();
const { currentBranch, branchesForCurrentTenant, setBranch } = useBranch();
const { currentRole, setRole, can } = usePermissions();
const { appearance, updateAppearance } = useAppearance();
const { info } = useToast();
const { countdownText, isUrgent, isExpired } = useDemoCountdown();

const authUser = computed(() => page?.props?.auth?.user as any);

const authInitials = computed(() => {
    const name = authUser.value?.name || currentTenant.value.name || 'User';
    return name
        .split(' ')
        .map((n: string) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const handleSwitchBranch = (branchId: string) => {
    setBranch(branchId);
    info('Branch Switched', `Active outlet set to ${currentBranch.value.name}`);
};

const toggleDarkMode = () => {
    const next = appearance.value === 'dark' ? 'light' : 'dark';
    updateAppearance(next);
};
</script>

<template>
    <header class="flex h-16 shrink-0 items-center justify-between border-b border-border/70 bg-card px-2.5 sm:px-4 md:px-6">
        <!-- Left: Sidebar Toggle + Tenant/Branch Context + Breadcrumbs -->
        <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
            <SidebarTrigger class="-ml-1 shrink-0" />

            <!-- Isolated Business Scope Display -->
            <div class="flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-1.5 rounded-lg border border-border/80 bg-background text-xs font-bold shadow-sm min-w-0">
                <Building2 class="size-3.5 text-primary shrink-0" />
                <span class="max-w-[120px] sm:max-w-[180px] md:max-w-[240px] truncate text-foreground text-xs">{{ currentTenant.name }}</span>
                <Badge variant="outline" class="text-[8px] sm:text-[9px] px-1 py-0 uppercase font-semibold hidden sm:inline-flex shrink-0">
                    {{ currentTenant.businessType }}
                </Badge>
            </div>

            <!-- Demo Countdown Indicator -->
            <div
                v-if="isDemo"
                :class="[
                    'flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold font-mono shrink-0 shadow-xs',
                    isUrgent
                        ? 'border-destructive/40 bg-destructive/10 text-destructive animate-pulse'
                        : 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                ]"
                title="Active Sandbox Demo Remaining Time"
            >
                <Clock class="size-3.5 animate-spin" style="animation-duration: 10s" />
                <span class="hidden md:inline text-[10px] font-extrabold uppercase tracking-wide">DEMO:</span>
                <span class="text-xs">{{ countdownText }}</span>
            </div>

            <!-- Branch Switcher -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm" class="h-9 gap-1.5 text-xs text-muted-foreground hover:text-foreground hidden md:flex shrink-0">
                        <Store class="size-3.5 text-emerald-600" />
                        <span class="max-w-[110px] truncate">{{ currentBranch.name }}</span>
                        <ChevronDown class="size-3 opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-56">
                    <DropdownMenuLabel class="text-xs text-muted-foreground">Select Operating Branch</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="branch in branchesForCurrentTenant(currentTenant.id)"
                        :key="branch.id"
                        class="flex items-center justify-between cursor-pointer text-xs"
                        @click="handleSwitchBranch(branch.id)"
                    >
                        <div>
                            <p class="font-medium">{{ branch.name }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ branch.location }}</p>
                        </div>
                        <Check v-if="branch.id === currentBranch.id" class="size-4 text-emerald-600" />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div v-if="breadcrumbs && breadcrumbs.length > 0" class="hidden xl:flex items-center pl-2 border-l border-border/70">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage class="text-xs font-semibold">{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href" class="text-xs">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </div>
        </div>

        <!-- Right: Role Badge, Theme, Notifications, POS Quick Launch -->
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <!-- Role Indicator Badge -->
            <div class="hidden lg:flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-border/80 bg-background text-xs font-semibold text-foreground">
                <Crown class="size-3.5 text-amber-500" />
                <span>Role: <strong>{{ authUser?.role || currentRole }}</strong></span>
            </div>

            <!-- Quick POS Button -->
            <Link v-if="hasModule('pos') && can('pos', 'create')" href="/pos">
                <Button size="sm" class="h-8 gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm px-2.5 sm:px-3">
                    <ShoppingCart class="size-3.5" />
                    <span class="hidden sm:inline">POS</span>
                </Button>
            </Link>

            <!-- Dark / Light Toggle -->
            <Button variant="ghost" size="icon" class="size-8" @click="toggleDarkMode" title="Toggle theme">
                <Sun v-if="appearance === 'dark'" class="size-4" />
                <Moon v-else class="size-4" />
            </Button>

            <!-- Notifications Popover -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="size-8 relative" title="Notifications">
                        <Bell class="size-4" />
                        <span class="absolute top-1.5 right-1.5 size-2 rounded-full bg-rose-500 ring-2 ring-background"></span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-[calc(100vw-32px)] sm:w-80 p-2">
                    <div class="flex items-center justify-between px-2 py-1.5">
                        <span class="text-xs font-bold">Notifications</span>
                        <Badge variant="destructive" class="text-[10px]">3 New</Badge>
                    </div>
                    <DropdownMenuSeparator />
                    <div class="space-y-1 text-xs">
                        <div class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/20">
                            <p class="font-semibold text-amber-800 dark:text-amber-300">Low Stock Alert</p>
                            <p class="text-[11px] text-muted-foreground">Brookside Whole Milk 500ml is below minimum (12 units left in Westlands)</p>
                        </div>
                        <div class="p-2 rounded-lg bg-sky-500/10 border border-sky-500/20">
                            <p class="font-semibold text-sky-800 dark:text-sky-300">Stock Transfer Incoming</p>
                            <p class="text-[11px] text-muted-foreground">Transfer #TRF-4091 from Industrial Area Warehouse arrived.</p>
                        </div>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- User Menu -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm" class="h-8 gap-1.5 pl-1.5 pr-1">
                        <div class="flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground text-[10px] font-bold">
                            {{ authInitials }}
                        </div>
                        <ChevronDown class="size-3 text-muted-foreground" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuLabel class="text-xs font-normal">
                        <div class="font-bold truncate">{{ authUser?.name || 'Account User' }}</div>
                        <div class="text-[10px] text-muted-foreground truncate">{{ authUser?.email || currentTenant.email }}</div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link href="/settings" class="cursor-pointer text-xs">Settings & Profile</Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link href="/logout" method="post" as="button" class="w-full text-left text-xs text-rose-600">
                            Log out
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>

    <!-- Demo Account Banner -->
    <div
        v-if="isDemo"
        class="flex items-center justify-center gap-2 px-4 py-1.5 bg-amber-500/15 border-b border-amber-500/30 text-amber-700 dark:text-amber-300 text-[11px] font-bold tracking-wider"
    >
        <Zap class="size-3.5 animate-pulse shrink-0" />
        <span>DEMO ACCOUNT — Data resets periodically. Changes will not be saved.</span>
        <Zap class="size-3.5 animate-pulse shrink-0" />
    </div>



    <!-- Global Toast Container -->
    <ToastContainer />
</template>
