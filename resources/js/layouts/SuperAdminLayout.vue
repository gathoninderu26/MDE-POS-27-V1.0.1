<script setup lang="ts">
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    ChevronRight,
    CreditCard,
    Database,
    Layers,
    LayoutDashboard,
    LogOut,
    Menu,
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
    <div class="flex h-[100dvh] w-screen overflow-hidden bg-zinc-950 text-zinc-100">
        <!-- Desktop Super Admin Sidebar (hidden on mobile, visible on lg) -->
        <aside
            :class="[
                'hidden shrink-0 flex-col border-r border-zinc-800 bg-zinc-900 transition-all duration-200 lg:flex',
                sidebarOpen ? 'w-64' : 'w-16',
            ]"
        >
            <!-- Header -->
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-zinc-800 px-3.5">
                <div v-if="sidebarOpen" class="flex items-center gap-2.5 overflow-hidden">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-600">
                        <Shield class="size-4 text-white" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-extrabold leading-tight text-white">MDE POS</p>
                        <p class="truncate text-[10px] font-semibold text-violet-400">Platform Super Admin</p>
                    </div>
                </div>
                <div v-else class="mx-auto flex size-8 items-center justify-center rounded-lg bg-violet-600">
                    <Shield class="size-4 text-white" />
                </div>

                <button
                    type="button"
                    class="hidden size-6 items-center justify-center rounded text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-white lg:flex"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <ChevronRight :class="['size-3.5 transition-transform duration-200', sidebarOpen ? 'rotate-180' : '']" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-3">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-xs font-semibold transition-all',
                        currentPath === item.href
                            ? 'border border-violet-600/30 bg-violet-600/20 text-violet-300'
                            : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100',
                    ]"
                >
                    <component :is="item.icon" class="size-4 shrink-0" />
                    <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                    <span v-if="item.preview && sidebarOpen" class="ml-auto rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-[9px] text-zinc-400"
                        >Preview</span
                    >
                </Link>
            </nav>

            <!-- Footer -->
            <div class="space-y-0.5 border-t border-zinc-800 px-2 pb-4 pt-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-xs font-semibold text-rose-400 transition-all hover:bg-rose-950/30 hover:text-rose-300"
                    @click="handleLogout"
                >
                    <LogOut class="size-4 shrink-0" />
                    <span v-if="sidebarOpen">Sign Out</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Slide-over Drawer (<lg) -->
        <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 flex lg:hidden">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="mobileMenuOpen = false"></div>

            <!-- Drawer Content -->
            <aside class="relative z-10 flex h-full w-72 max-w-[80vw] flex-col border-r border-zinc-800 bg-zinc-900 shadow-2xl">
                <div class="flex h-16 shrink-0 items-center justify-between border-b border-zinc-800 px-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-600">
                            <Shield class="size-4 text-white" />
                        </div>
                        <div>
                            <p class="text-xs font-extrabold leading-tight text-white">MDE POS Platform</p>
                            <p class="text-[10px] font-semibold text-violet-400">Super Admin Console</p>
                        </div>
                    </div>
                    <button type="button" class="p-1 text-zinc-400 hover:text-white" @click="mobileMenuOpen = false">
                        <X class="size-5" />
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-xs font-semibold transition-all',
                            currentPath === item.href ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>

                <div class="border-t border-zinc-800 px-3 pb-4 pt-3">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-xs font-semibold text-rose-400 transition-all hover:bg-rose-950/30 hover:text-rose-300"
                        @click="handleLogout"
                    >
                        <LogOut class="size-4 shrink-0" />
                        <span>Sign Out</span>
                    </button>
                </div>
            </aside>
        </div>

        <!-- Main Area -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top Header -->
            <header class="flex h-16 shrink-0 items-center justify-between border-b border-zinc-800 bg-zinc-900 px-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <!-- Mobile Hamburger Button (<lg) -->
                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100 lg:hidden"
                        @click="mobileMenuOpen = true"
                    >
                        <Menu class="size-5" />
                    </button>

                    <!-- Desktop Sidebar Toggle (lg+) -->
                    <button
                        type="button"
                        class="hidden shrink-0 text-zinc-400 hover:text-zinc-100 lg:inline-flex"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <ChevronRight :class="['size-5 transition-transform', sidebarOpen && 'rotate-180']" />
                    </button>

                    <div
                        class="flex items-center gap-1.5 truncate rounded-lg border border-violet-600/20 bg-violet-600/10 px-2.5 py-1.5 sm:gap-2 sm:px-3"
                    >
                        <ShieldCheck class="size-4 shrink-0 text-violet-400" />
                        <span class="truncate text-xs font-bold text-violet-300">Super Admin</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <button type="button" class="relative rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100">
                        <Bell class="size-4 sm:size-5" />
                        <span class="absolute right-1 top-1 size-2 rounded-full bg-rose-500"></span>
                    </button>

                    <div class="flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-2 py-1.5 sm:px-3">
                        <div class="flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-600 text-[10px] font-bold text-white">
                            SA
                        </div>
                        <div class="hidden flex-col text-left sm:flex">
                            <span class="max-w-[130px] truncate text-xs font-semibold leading-tight text-zinc-200">{{
                                authUser?.name || 'Platform Admin'
                            }}</span>
                            <span class="max-w-[130px] truncate text-[10px] leading-none text-zinc-400">{{
                                authUser?.email || 'superadmin@mdepos.co.ke'
                            }}</span>
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
