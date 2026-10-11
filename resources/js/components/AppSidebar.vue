<script setup lang="ts">
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useBranch } from '@/composables/useBranch';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import { useCart } from '@/composables/useCart';
import { useDemoCountdown } from '@/composables/useDemoCountdown';
import { useFeatures } from '@/composables/useFeatures';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Building,
    ChevronRight,
    CircleDollarSign,
    Clock,
    Layers,
    LayoutDashboard,
    LogOut,
    Package,
    Pill,
    Receipt,
    RotateCcw,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Store,
    Truck,
    Users,
    Utensils,
    Wrench,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const { currentTenant, hasModule, isDemo } = useTenant();
const { isMultiBranch } = useFeatures();
const { terms } = useBusinessTerms();
const { currentBranch } = useBranch();
const { can, currentRole } = usePermissions();
const { countdownText, isUrgent } = useDemoCountdown();
useCart();

const currentPath = computed(() => page.url);

const isPathActive = (href: string) => {
    if (href === '/dashboard' && currentPath.value === '/dashboard') return true;
    if (href !== '/dashboard' && currentPath.value.startsWith(href)) return true;
    return false;
};

const openSections = ref<Record<string, boolean>>({
    sales: true,
    products: true,
    inventory: true,
    purchasing: false,
    customers: false,
    expenses: false,
    reports: false,
    settings: false,
});

const toggleSection = (section: string) => {
    openSections.value[section] = !openSections.value[section];
};

const getVerticalItemIcon = () => {
    if (terms.value.showPrescriptions) return Pill;
    if (terms.value.showDimensionsAndSite) return Wrench;
    if (terms.value.showTableManagement) return Utensils;
    if (terms.value.showSerialWarranty) return Smartphone;
    if (terms.value.businessType === 'Wholesale') return Truck;
    return Package;
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-border/70 bg-card">
        <!-- Brand / Tenant Header -->
        <SidebarHeader class="border-b border-border/60 p-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-black text-primary-foreground shadow-md"
                >
                    <component :is="getVerticalItemIcon()" class="size-4 text-primary-foreground" />
                </div>
                <div class="flex flex-col overflow-hidden text-left leading-tight group-data-[collapsible=icon]:hidden">
                    <span class="truncate text-sm font-bold text-foreground">{{ currentTenant.name }}</span>
                    <div v-if="isMultiBranch" class="mt-0.5 flex items-center gap-1.5">
                        <span class="flex items-center gap-1 truncate text-[11px] font-medium text-muted-foreground">
                            <Store class="inline size-3 text-emerald-600 dark:text-emerald-400" />
                            {{ currentBranch.name }}
                        </span>
                    </div>
                    <div v-else class="mt-0.5 flex items-center gap-1.5">
                        <span class="flex items-center gap-1 truncate text-[11px] font-medium text-muted-foreground">
                            <Store class="inline size-3 text-primary" />
                            {{ currentTenant.businessType }}
                        </span>
                    </div>
                    <!-- Demo / Live indicator -->
                    <div
                        v-if="isDemo"
                        :class="[
                            'shadow-xs mt-1.5 inline-flex w-fit items-center gap-1.5 rounded-md border px-2 py-0.5 font-mono text-[10px] font-bold tracking-wide',
                            isUrgent
                                ? 'animate-pulse border-destructive/30 bg-destructive/15 text-destructive'
                                : 'border-amber-500/30 bg-amber-500/15 text-amber-600 dark:text-amber-400',
                        ]"
                        title="Active Sandbox Demo Duration"
                    >
                        <Clock class="size-2.5 animate-spin" style="animation-duration: 10s" />
                        <span>DEMO: {{ countdownText }}</span>
                    </div>
                    <div
                        v-else
                        class="shadow-xs mt-1.5 inline-flex w-fit items-center gap-1.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-700 dark:text-emerald-400"
                        title="Live Production Workspace"
                    >
                        <span class="size-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                        <span>LIVE ACCOUNT</span>
                    </div>
                </div>
            </div>
        </SidebarHeader>

        <SidebarContent class="py-2">
            <!-- Quick POS Launch -->
            <div v-if="hasModule('pos') && can('pos', 'create')" class="mb-2 px-3 group-data-[collapsible=icon]:hidden">
                <Link
                    href="/pos"
                    class="flex items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-xs font-bold text-primary-foreground shadow transition-all hover:bg-primary/90"
                >
                    <ShoppingCart class="size-4" />
                    <span>Launch {{ terms.badgeLabel.split(' ')[0] }} POS</span>
                </Link>
            </div>

            <!-- Dashboard -->
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/dashboard')" tooltip="Dashboard">
                            <Link href="/dashboard">
                                <LayoutDashboard class="size-4 text-primary" />
                                <span>Dashboard</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Sales & Orders -->
            <SidebarGroup v-if="hasModule('sales') && can('sales', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="flex cursor-pointer items-center justify-between text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground"
                    @click="toggleSection('sales')"
                >
                    <span>{{
                        terms.showPrescriptions ? 'Dispensary & Orders' : terms.showTableManagement ? 'Dining & Tabs' : 'Sales & Orders'
                    }}</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.sales ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.sales">
                    <!-- POS Register -->
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/pos')" tooltip="POS Register">
                            <Link href="/pos">
                                <ShoppingCart class="size-4 text-emerald-600" />
                                <span>{{
                                    terms.showPrescriptions ? 'Dispensary POS' : terms.showTableManagement ? 'Table Register' : 'POS Register'
                                }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- Sales History -->
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/sales') && !isPathActive('/sales/returns')" tooltip="Sales History">
                            <Link href="/sales">
                                <Receipt class="size-4" />
                                <span>{{
                                    terms.showPrescriptions
                                        ? 'Dispensation Log'
                                        : terms.showDimensionsAndSite
                                          ? 'Invoice & Delivery Notes'
                                          : 'Sales History'
                                }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- Returns -->
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/sales/returns')" tooltip="Returns & Refunds">
                            <Link href="/sales/returns">
                                <RotateCcw class="size-4 text-amber-600" />
                                <span>Returns & Refunds</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Products / Catalog -->
            <SidebarGroup v-if="hasModule('products') && can('products', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="flex cursor-pointer items-center justify-between text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground"
                    @click="toggleSection('products')"
                >
                    <span>{{ terms.itemsPlural }}</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.products ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.products">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isPathActive('/products') && !isPathActive('/products/categories')"
                            tooltip="Product Catalog"
                        >
                            <Link href="/products">
                                <component :is="getVerticalItemIcon()" class="size-4 text-primary" />
                                <span>{{ terms.catalogTitle }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/products/categories')" tooltip="Categories">
                            <Link href="/products/categories">
                                <Layers class="size-4 text-primary/70" />
                                <span>{{ terms.categoryLabel }}s</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Inventory -->
            <SidebarGroup v-if="hasModule('inventory') && can('inventory', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="flex cursor-pointer items-center justify-between text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground"
                    @click="toggleSection('inventory')"
                >
                    <span>Inventory & Stock</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.inventory ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.inventory">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/inventory')" tooltip="Stock Overview">
                            <Link href="/inventory">
                                <Boxes class="size-4" />
                                <span>{{
                                    terms.showPrescriptions
                                        ? 'Batch & Expiry Stock'
                                        : terms.showDimensionsAndSite
                                          ? 'Yard & Material Stock'
                                          : 'Stock Overview'
                                }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Purchases & Suppliers -->
            <SidebarGroup v-if="hasModule('purchases') && can('purchases', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="flex cursor-pointer items-center justify-between text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground"
                    @click="toggleSection('purchasing')"
                >
                    <span>Purchases & Suppliers</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.purchasing ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.purchasing">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/purchases')" tooltip="Purchase Orders">
                            <Link href="/purchases">
                                <Truck class="size-4" />
                                <span>Purchase Orders</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/suppliers')" tooltip="Suppliers">
                            <Link href="/suppliers">
                                <Building class="size-4" />
                                <span>{{ terms.showPrescriptions ? 'Pharmaceutical Vendors' : 'Suppliers Directory' }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Customers -->
            <SidebarGroup v-if="hasModule('customers') && can('customers', 'view')" class="px-2 py-1">
                <SidebarGroupLabel class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    {{ terms.clientPlural }}
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/customers')" tooltip="Customer Directory">
                            <Link href="/customers">
                                <Users class="size-4" />
                                <span>{{ terms.clientLabel }} Directory</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Expenses -->
            <SidebarGroup v-if="hasModule('expenses') && can('expenses', 'view')" class="px-2 py-1">
                <SidebarGroupLabel class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground"> Finance </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/expenses')" tooltip="Expenses">
                            <Link href="/expenses">
                                <CircleDollarSign class="size-4 text-rose-500" />
                                <span>Expenses & Petty Cash</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Reports -->
            <SidebarGroup v-if="hasModule('reports') && can('reports', 'view')" class="px-2 py-1">
                <SidebarGroupLabel class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    Analytics & Reports
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/reports')" tooltip="Reports">
                            <Link href="/reports">
                                <BarChart3 class="size-4 text-sky-500" />
                                <span>Reports & P&L</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Management / Admin -->
            <SidebarGroup
                v-if="
                    (hasModule('users') && can('users', 'view')) ||
                    (isMultiBranch && can('branches', 'view')) ||
                    (hasModule('settings') && can('settings', 'view'))
                "
                class="px-2 py-1"
            >
                <SidebarGroupLabel
                    class="flex cursor-pointer items-center justify-between text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground"
                    @click="toggleSection('settings')"
                >
                    <span>Management</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.settings ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.settings">
                    <SidebarMenuItem v-if="hasModule('users') && can('users', 'view')">
                        <SidebarMenuButton as-child :is-active="isPathActive('/users')" tooltip="Users & Permissions">
                            <Link href="/users">
                                <ShieldCheck class="size-4 text-emerald-600" />
                                <span>Users & Permissions</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem v-if="isMultiBranch && can('branches', 'view')">
                        <SidebarMenuButton as-child :is-active="isPathActive('/branches')" tooltip="Branch Outlets">
                            <Link href="/branches">
                                <Store class="size-4" />
                                <span>Branch Outlets</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem v-if="hasModule('settings') && can('settings', 'view')">
                        <SidebarMenuButton as-child :is-active="isPathActive('/settings')" tooltip="Settings">
                            <Link href="/settings">
                                <Settings class="size-4" />
                                <span>Store & Vertical Settings</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <!-- Footer -->
        <SidebarFooter class="space-y-2 border-t border-border/70 bg-muted/20 p-3">
            <div class="flex items-center justify-between text-xs group-data-[collapsible=icon]:hidden">
                <div class="flex items-center gap-2">
                    <div class="size-2 animate-pulse rounded-full bg-emerald-500"></div>
                    <span class="font-medium text-muted-foreground">Role:</span>
                    <Badge variant="outline" class="font-bold">{{ currentRole }}</Badge>
                </div>
                <span class="font-mono text-[10px] uppercase text-muted-foreground">{{ currentTenant.businessType }}</span>
            </div>

            <Link
                v-if="(page.props.auth as any)?.is_impersonating"
                href="/super-admin/stop-impersonating"
                method="post"
                as="button"
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-600 transition-all hover:bg-amber-500/20 group-data-[collapsible=icon]:p-2 dark:text-amber-400"
                title="Return to Super Admin console"
            >
                <ShieldCheck class="size-3.5" />
                <span class="group-data-[collapsible=icon]:hidden">Exit Impersonation</span>
            </Link>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-600 transition-all hover:bg-rose-500/20 group-data-[collapsible=icon]:p-2 dark:text-rose-400"
                title="Log out of business account"
            >
                <LogOut class="size-3.5" />
                <span class="group-data-[collapsible=icon]:hidden">Log Out</span>
            </Link>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
