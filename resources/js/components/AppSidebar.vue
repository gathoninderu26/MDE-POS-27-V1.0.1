<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useBranch } from '@/composables/useBranch';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import { useDemoCountdown } from '@/composables/useDemoCountdown';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Boxes,
    Building,
    ChevronRight,
    CircleDollarSign,
    ClipboardList,
    Clock,
    CreditCard,
    DollarSign,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    History,
    Home,
    Layers,
    LayoutDashboard,
    LogOut,
    Package,
    Pill,
    PlusCircle,
    Receipt,
    RotateCcw,
    Scale,
    ScanBarcode,
    Settings,
    Shield,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Store,
    Tag,
    Truck,
    Undo2,
    UserCheck,
    Users,
    Utensils,
    Wrench,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const { currentTenant, hasModule, isDemo } = useTenant();
const { terms } = useBusinessTerms();
const { currentBranch } = useBranch();
const { can, currentRole } = usePermissions();
const { countdownText, isUrgent } = useDemoCountdown();

const currentPath = computed(() => page.url);

const isPathActive = (href: string) => {
    if (href === '/dashboard' && currentPath.value === '/dashboard') return true;
    if (href !== '/dashboard' && currentPath.value.startsWith(href)) return true;
    return false;
};

// Collapsible group open states
const openSections = ref<Record<string, boolean>>({
    sales: true,
    products: true,
    inventory: true,
    purchasing: false,
    customers: false,
    expenses: false,
    reports: false,
    users: false,
    settings: false,
});

const toggleSection = (section: string) => {
    openSections.value[section] = !openSections.value[section];
};

// Dynamic vertical icon for items
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
        <!-- Brand / Tenant Header with Vertical Badge -->
        <SidebarHeader class="border-b border-border/60 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground font-black text-sm shadow-md shrink-0">
                    <component :is="getVerticalItemIcon()" class="size-4 text-primary-foreground" />
                </div>
                <div class="flex flex-col overflow-hidden text-left leading-tight group-data-[collapsible=icon]:hidden">
                    <span class="truncate font-bold text-sm text-foreground">{{ currentTenant.name }}</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="truncate text-[11px] text-muted-foreground font-medium flex items-center gap-1">
                            <Store class="size-3 text-emerald-600 dark:text-emerald-400 inline" />
                            {{ currentBranch.name }}
                        </span>
                    </div>
                    <!-- Demo badge with countdown under branch name -->
                    <div
                        v-if="isDemo"
                        :class="[
                            'mt-1.5 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border text-[10px] font-mono font-bold tracking-wide w-fit shadow-xs',
                            isUrgent
                                ? 'bg-destructive/15 border-destructive/30 text-destructive animate-pulse'
                                : 'bg-amber-500/15 border-amber-500/30 text-amber-600 dark:text-amber-400',
                        ]"
                        title="Active Sandbox Demo Duration"
                    >
                        <Clock class="size-2.5 animate-spin" style="animation-duration: 10s" />
                        <span>DEMO: {{ countdownText }}</span>
                    </div>
                </div>
            </div>
        </SidebarHeader>

        <!-- Navigation Menus with Role & Module Filtering -->
        <SidebarContent class="py-2">
            <!-- Quick POS Launch Button (Adaptive Label) -->
            <div v-if="hasModule('pos') && can('pos', 'create')" class="px-3 mb-2 group-data-[collapsible=icon]:hidden">
                <Link
                    href="/pos"
                    class="flex items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-xs font-bold text-primary-foreground shadow hover:bg-primary/90 transition-all"
                >
                    <ShoppingCart class="size-4" />
                    <span>Launch {{ terms.badgeLabel.split(' ')[0] }} POS</span>
                </Link>
            </div>

            <!-- Dashboard -->
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/dashboard')">
                            <Link href="/dashboard" class="flex items-center gap-3">
                                <LayoutDashboard class="size-4 text-primary" />
                                <span class="font-medium text-xs">Dashboard</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Sales & Orders Module (Adaptive Terms) -->
            <SidebarGroup v-if="hasModule('sales') && can('sales', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground flex items-center justify-between"
                    @click="toggleSection('sales')"
                >
                    <span>{{ terms.showPrescriptions ? 'Dispensary & Orders' : terms.showTableManagement ? 'Dining & Tabs' : 'Sales & Orders' }}</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.sales ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.sales">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/pos')">
                            <Link href="/pos" class="flex items-center gap-3">
                                <ShoppingCart class="size-4 text-emerald-600" />
                                <span class="text-xs">{{ terms.showPrescriptions ? 'Dispensary POS' : terms.showTableManagement ? 'Table Register' : 'POS Register' }}</span>
                                <Badge variant="success" class="ml-auto text-[10px] px-1.5 py-0">Live</Badge>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/sales') && !isPathActive('/sales/returns')">
                            <Link href="/sales" class="flex items-center gap-3">
                                <Receipt class="size-4" />
                                <span class="text-xs">{{ terms.showPrescriptions ? 'Dispensation Log' : terms.showDimensionsAndSite ? 'Invoice & Delivery Notes' : 'Sales History' }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/sales/returns')">
                            <Link href="/sales/returns" class="flex items-center gap-3">
                                <RotateCcw class="size-4 text-amber-600" />
                                <span class="text-xs">Returns & Refunds</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Products / Catalog Module (Adaptive Terms) -->
            <SidebarGroup v-if="hasModule('products') && can('products', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground flex items-center justify-between"
                    @click="toggleSection('products')"
                >
                    <span>{{ terms.itemsPlural }}</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.products ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.products">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/products') && !isPathActive('/products/categories')">
                            <Link href="/products" class="flex items-center gap-3">
                                <component :is="getVerticalItemIcon()" class="size-4 text-primary" />
                                <span class="text-xs">{{ terms.catalogTitle }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/products/categories')">
                            <Link href="/products/categories" class="flex items-center gap-3">
                                <Layers class="size-4 text-primary/70" />
                                <span class="text-xs">{{ terms.categoryLabel }}s</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Inventory Module -->
            <SidebarGroup v-if="hasModule('inventory') && can('inventory', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground flex items-center justify-between"
                    @click="toggleSection('inventory')"
                >
                    <span>Inventory & Stock</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.inventory ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.inventory">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/inventory')">
                            <Link href="/inventory" class="flex items-center gap-3">
                                <Boxes class="size-4" />
                                <span class="text-xs">{{ terms.showPrescriptions ? 'Batch & Expiry Stock' : terms.showDimensionsAndSite ? 'Yard & Material Stock' : 'Stock Overview' }}</span>
                                <Badge variant="warning" class="ml-auto text-[10px] px-1.5 py-0">3 Low</Badge>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Purchasing Module -->
            <SidebarGroup v-if="hasModule('purchases') && can('purchases', 'view')" class="px-2 py-1">
                <SidebarGroupLabel
                    class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground flex items-center justify-between"
                    @click="toggleSection('purchasing')"
                >
                    <span>Purchases & Suppliers</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.purchasing ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.purchasing">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/purchases')">
                            <Link href="/purchases" class="flex items-center gap-3">
                                <Truck class="size-4" />
                                <span class="text-xs">Purchase Orders</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/suppliers')">
                            <Link href="/suppliers" class="flex items-center gap-3">
                                <Building class="size-4" />
                                <span class="text-xs">{{ terms.showPrescriptions ? 'Pharmaceutical Vendors' : 'Suppliers Directory' }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Customers Module (Adaptive Terms) -->
            <SidebarGroup v-if="hasModule('customers') && can('customers', 'view')" class="px-2 py-1">
                <SidebarGroupLabel class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    {{ terms.clientPlural }}
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/customers')">
                            <Link href="/customers" class="flex items-center gap-3">
                                <Users class="size-4" />
                                <span class="text-xs">{{ terms.clientLabel }} Directory</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Expenses Module -->
            <SidebarGroup v-if="hasModule('expenses') && can('expenses', 'view')" class="px-2 py-1">
                <SidebarGroupLabel class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    Finance
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/expenses')">
                            <Link href="/expenses" class="flex items-center gap-3">
                                <CircleDollarSign class="size-4 text-rose-500" />
                                <span class="text-xs">Expenses & Petty Cash</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Reports Module -->
            <SidebarGroup v-if="hasModule('reports') && can('reports', 'view')" class="px-2 py-1">
                <SidebarGroupLabel class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    Analytics & Reports
                </SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isPathActive('/reports')">
                            <Link href="/reports" class="flex items-center gap-3">
                                <BarChart3 class="size-4 text-sky-500" />
                                <span class="text-xs">Reports & P&L</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Administration & Settings -->
            <SidebarGroup
                v-if="(hasModule('users') && can('users', 'view')) || (hasModule('branches') && can('branches', 'view')) || (hasModule('settings') && can('settings', 'view'))"
                class="px-2 py-1"
            >
                <SidebarGroupLabel
                    class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground flex items-center justify-between"
                    @click="toggleSection('settings')"
                >
                    <span>Management</span>
                    <ChevronRight :class="['size-3.5 transition-transform', openSections.settings ? 'rotate-90' : '']" />
                </SidebarGroupLabel>
                <SidebarMenu v-show="openSections.settings">
                    <SidebarMenuItem v-if="hasModule('users') && can('users', 'view')">
                        <SidebarMenuButton as-child :is-active="isPathActive('/users')">
                            <Link href="/users" class="flex items-center gap-3">
                                <ShieldCheck class="size-4 text-emerald-600" />
                                <span class="text-xs">Users & Permissions</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem v-if="hasModule('branches') && can('branches', 'view')">
                        <SidebarMenuButton as-child :is-active="isPathActive('/branches')">
                            <Link href="/branches" class="flex items-center gap-3">
                                <Store class="size-4" />
                                <span class="text-xs">Branch Outlets</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <SidebarMenuItem v-if="hasModule('settings') && can('settings', 'view')">
                        <SidebarMenuButton as-child :is-active="isPathActive('/settings')">
                            <Link href="/settings" class="flex items-center gap-3">
                                <Settings class="size-4" />
                                <span class="text-xs">Store & Vertical Settings</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <!-- Sidebar Footer with Current Role Badge & Logout -->
        <SidebarFooter class="border-t border-border/70 p-3 bg-muted/20 space-y-2">
            <div class="flex items-center justify-between text-xs group-data-[collapsible=icon]:hidden">
                <div class="flex items-center gap-2">
                    <div class="size-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-muted-foreground font-medium">Role:</span>
                    <Badge variant="outline" class="font-bold">{{ currentRole }}</Badge>
                </div>
                <span class="text-[10px] text-muted-foreground font-mono uppercase">{{ currentTenant.businessType }}</span>
            </div>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all cursor-pointer group-data-[collapsible=icon]:p-2"
                title="Log out of business account"
            >
                <LogOut class="size-3.5" />
                <span class="group-data-[collapsible=icon]:hidden">Log Out</span>
            </Link>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
