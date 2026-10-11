<script setup lang="ts">
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Bell,
    Building2,
    ChevronDown,
    ChevronRight,
    CreditCard,
    Database,
    Gauge,
    LayoutDashboard,
    Layers,
    LogOut,
    Menu,
    Settings,
    Shield,
    ShieldCheck,
    Users,
    Wallet,
    Wifi,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const authUser = computed(() => page?.props?.auth?.user as any);

const navItems = [
    { label: 'Platform Dashboard', href: '/super-admin', icon: LayoutDashboard, preview: false },
    { label: 'Tenant Accounts', href: '/super-admin/tenants', icon: Building2, preview: false },
    { label: 'Module Manager', href: '/super-admin/modules', icon: Layers, preview: true },
    { label: 'Payment Gateways', href: '/super-admin/payment-methods', icon: Wallet, preview: true },
    { label: 'Device & Network Security', href: '/super-admin/security-policies', icon: Wifi, preview: true },
    { label: 'System Backups', href: '/super-admin/backups', icon: Database, preview: true },
    { label: 'Subscriptions & Billing', href: '/super-admin/subscriptions', icon: CreditCard, preview: true },
    { label: 'System Users', href: '/super-admin/system-users', icon: Users, preview: true },
];

const currentPath = computed(() => {
    if (typeof window !== 'undefined') return window.location.pathname;
    return '/super-admin';
});

const sidebarOpen = ref(true);
const mobileMenuOpen = ref(false);

const handleLogout = () => {
    router.post('/super-admin/logout');
};
</script>

<template>
    <div class="flex h-[100dvh] w-screen bg-zinc-950 text-zinc-100 overflow-hidden">
        <!-- Desktop Super Admin Sidebar (hidden on mobile, visible on lg) -->
        <aside
            :class="[
                'hidden lg:flex flex-col shrink-0 border-r border-zinc-800 bg-zinc-900 transition-all duration-200',
                sidebarOpen ? 'w-64' : 'w-16',
            ]"
        >
            <!-- Header -->
            <div class="flex h-16 items-center justify-between px-3.5 border-b border-zinc-800 shrink-0">
                <div v-if="sidebarOpen" class="flex items-center gap-2.5 overflow-hidden">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-violet-600 shrink-0">
                        <Shield class="size-4 text-white" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold text-white leading-tight truncate">MDE POS</p>
                        <p class="text-[10px] text-violet-400 font-semibold truncate">Platform Super Admin</p>
                    </div>
                </div>
                <div v-else class="flex size-8 mx-auto items-center justify-center rounded-lg bg-violet-600">
                    <Shield class="size-4 text-white" />
                </div>

                <button
                    type="button"
                    class="hidden lg:flex size-6 items-center justify-center rounded text-zinc-400 hover:text-white hover:bg-zinc-800 transition-colors"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <ChevronRight :class="['size-3.5 transition-transform duration-200', sidebarOpen ? 'rotate-180' : '']" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-2 py-3 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition-all',
                        currentPath === item.href
                            ? 'bg-violet-600/20 text-violet-300 border border-violet-600/30'
                            : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800',
                    ]"
                >
                    <component :is="item.icon" class="size-4 shrink-0" />
                    <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                    <span v-if="item.preview && sidebarOpen" class="ml-auto text-[9px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 font-mono">Preview</span>
                </Link>
            </nav>

            <!-- Footer -->
            <div class="px-2 pb-4 space-y-0.5 border-t border-zinc-800 pt-3">
                <button
                    type="button"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-950/30 transition-all"
                    @click="handleLogout"
                >
                    <LogOut class="size-4 shrink-0" />
                    <span v-if="sidebarOpen">Sign Out</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Slide-over Drawer (<lg) -->
        <div v-if="mobileMenuOpen" class="lg:hidden fixed inset-0 z-50 flex">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="mobileMenuOpen = false"></div>

            <!-- Drawer Content -->
            <aside class="relative flex flex-col w-72 max-w-[80vw] h-full bg-zinc-900 border-r border-zinc-800 z-10 shadow-2xl">
                <div class="flex h-16 items-center justify-between px-4 border-b border-zinc-800 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-violet-600 shrink-0">
                            <Shield class="size-4 text-white" />
                        </div>
                        <div>
                            <p class="text-xs font-extrabold text-white leading-tight">MDE POS Platform</p>
                            <p class="text-[10px] text-violet-400 font-semibold">Super Admin Console</p>
                        </div>
                    </div>
                    <button type="button" class="text-zinc-400 hover:text-white p-1" @click="mobileMenuOpen = false">
                        <X class="size-5" />
                    </button>
                </div>

                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition-all',
                            currentPath === item.href
                                ? 'bg-violet-600 text-white shadow-sm'
                                : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>

                <div class="px-3 pb-4 border-t border-zinc-800 pt-3">
                    <button
                        type="button"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-950/30 transition-all"
                        @click="handleLogout"
                    >
                        <LogOut class="size-4 shrink-0" />
                        <span>Sign Out</span>
                    </button>
                </div>
            </aside>
        </div>

        <!-- Main Area -->
        <div class="flex flex-col flex-1 min-w-0">
            <!-- Top Header -->
            <header class="flex h-16 items-center justify-between px-3 sm:px-6 border-b border-zinc-800 bg-zinc-900 shrink-0">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <!-- Mobile Hamburger Button (<lg) -->
                    <button
                        type="button"
                        class="lg:hidden text-zinc-400 hover:text-zinc-100 p-1.5 rounded-lg hover:bg-zinc-800 shrink-0"
                        @click="mobileMenuOpen = true"
                    >
                        <Menu class="size-5" />
                    </button>

                    <!-- Desktop Sidebar Toggle (lg+) -->
                    <button
                        type="button"
                        class="hidden lg:inline-flex text-zinc-400 hover:text-zinc-100 shrink-0"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <ChevronRight :class="['size-5 transition-transform', sidebarOpen && 'rotate-180']" />
                    </button>

                    <div class="flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-lg bg-violet-600/10 border border-violet-600/20 truncate">
                        <ShieldCheck class="size-4 text-violet-400 shrink-0" />
                        <span class="text-xs font-bold text-violet-300 truncate">Super Admin</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <button type="button" class="relative text-zinc-400 hover:text-zinc-100 p-1.5 rounded-lg hover:bg-zinc-800">
                        <Bell class="size-4 sm:size-5" />
                        <span class="absolute top-1 right-1 size-2 rounded-full bg-rose-500"></span>
                    </button>

                    <div class="flex items-center gap-2 px-2 sm:px-3 py-1.5 rounded-lg border border-zinc-700 bg-zinc-800">
                        <div class="size-6 rounded-full bg-violet-600 flex items-center justify-center text-[10px] font-bold text-white shrink-0">SA</div>
                        <div class="hidden sm:flex flex-col text-left">
                            <span class="text-xs font-semibold text-zinc-200 leading-tight truncate max-w-[130px]">{{ authUser?.name || 'Platform Admin' }}</span>
                            <span class="text-[10px] text-zinc-400 leading-none truncate max-w-[130px]">{{ authUser?.email || 'superadmin@mdepos.co.ke' }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-zinc-950 p-3 sm:p-6">
                <slot />
            </main>
        </div>

        <ToastContainer />
    </div>
</template>
