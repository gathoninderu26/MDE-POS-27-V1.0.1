<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Cpu,
    Eye,
    EyeOff,
    KeyRound,
    Lock,
    Radio,
    Server,
    ShieldAlert,
    ShieldCheck,
    Terminal,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    status?: string;
    submitUrl?: string;
}>();

const showPassword = ref(false);
const isProcessing = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const quickFillEmail = () => {
    form.email = 'superadmin@mdepos.co.ke';
};

const submit = () => {
    isProcessing.value = true;
    const targetUrl = props.submitUrl || '/super-admin/login';
    form.post(targetUrl, {
        onFinish: () => {
            isProcessing.value = false;
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Super Admin Command Console — MDE POS Kernel" />

    <div
        class="relative flex min-h-screen w-full select-none flex-col items-center justify-between overflow-hidden bg-[#070b12] p-4 font-sans text-slate-100 sm:p-6"
    >
        <!-- Cybernetic Grid & Glow Mesh Background -->
        <div
            class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,#0f172a_1px,transparent_1px),linear-gradient(to_bottom,#0f172a_1px,transparent_1px)] bg-[size:3.5rem_3.5rem] opacity-40 [mask-image:radial-gradient(ellipse_70%_60%_at_50%_40%,#000_70%,transparent_100%)]"
        ></div>
        <div class="pointer-events-none absolute -top-40 left-1/2 size-[550px] -translate-x-1/2 rounded-full bg-emerald-500/10 blur-[120px]"></div>
        <div class="pointer-events-none absolute -bottom-40 right-10 size-[450px] rounded-full bg-cyan-500/10 blur-[120px]"></div>

        <!-- Top Platform Telemetry Bar -->
        <header
            class="relative z-10 flex w-full max-w-4xl flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800/80 bg-slate-900/60 px-4 py-2 font-mono text-[11px] text-slate-400 backdrop-blur-md"
        >
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <span class="font-bold text-slate-200">KERNEL v1.0.1</span>
                </div>
                <span class="hidden text-slate-600 sm:inline">|</span>
                <div class="hidden items-center gap-1 text-slate-400 sm:flex">
                    <Server class="size-3 text-emerald-400" />
                    <span>CENTRAL CLUSTER</span>
                </div>
                <span class="hidden text-slate-600 md:inline">|</span>
                <div class="hidden items-center gap-1 text-slate-400 md:flex">
                    <Activity class="size-3 text-cyan-400" />
                    <span>AUTH PROTOCOL: TLS 1.3</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="flex items-center gap-1 rounded border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-400"
                >
                    <ShieldCheck class="size-3" />
                    CLEARANCE: ROOT (SUPER ADMIN)
                </span>
            </div>
        </header>

        <!-- Main Security Gateway Card -->
        <main class="relative z-10 my-auto w-full max-w-md py-4">
            <div
                class="relative space-y-6 overflow-hidden rounded-2xl border border-slate-800/90 bg-slate-900/90 p-6 shadow-2xl shadow-black/80 backdrop-blur-2xl sm:p-8"
            >
                <!-- Glowing Top Accent Line -->
                <div class="absolute left-0 right-0 top-0 h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-500"></div>

                <!-- Window Console Controls Header -->
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div class="flex items-center gap-2">
                        <div class="size-2.5 rounded-full border border-rose-400/40 bg-rose-500/70"></div>
                        <div class="size-2.5 rounded-full border border-amber-400/40 bg-amber-500/70"></div>
                        <div class="size-2.5 rounded-full border border-emerald-400/40 bg-emerald-500/70"></div>
                        <span class="ml-2 font-mono text-[11px] tracking-tight text-slate-400">mde-kernel://auth.root</span>
                    </div>
                    <span class="font-mono text-[10px] text-slate-500">PORT: 8000</span>
                </div>

                <!-- Badge & Title -->
                <div class="space-y-2 text-center">
                    <div
                        class="mx-auto mb-2 flex size-14 items-center justify-center rounded-2xl border border-slate-700/80 bg-gradient-to-b from-slate-800 to-slate-950 text-emerald-400 shadow-inner ring-4 ring-emerald-500/10"
                    >
                        <Cpu class="size-7 animate-pulse text-emerald-400" />
                    </div>
                    <h1 class="flex items-center justify-center gap-2 font-mono text-xl font-bold tracking-tight text-white">
                        <span>Platform Super Admin</span>
                    </h1>
                    <p class="mx-auto max-w-xs font-sans text-xs leading-relaxed text-slate-400">
                        Multi-tenant core supervisor terminal. Administrative security clearance required.
                    </p>
                </div>

                <!-- Status Messages / Feedback -->
                <div
                    v-if="status"
                    class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 font-mono text-xs text-emerald-400"
                >
                    <ShieldCheck class="size-4 shrink-0" />
                    <span>{{ status }}</span>
                </div>

                <div
                    v-if="form.errors.email"
                    class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 font-mono text-xs text-rose-400"
                >
                    <ShieldAlert class="mt-0.5 size-4 shrink-0 text-rose-400" />
                    <div>
                        <p class="font-bold">Access Verification Failed</p>
                        <p class="mt-0.5 text-[11px] text-rose-300">{{ form.errors.email }}</p>
                    </div>
                </div>

                <!-- Authentication Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email / Root Identifier -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="admin-email" class="flex items-center gap-1.5 font-mono text-xs font-medium text-slate-300">
                                <KeyRound class="size-3 text-emerald-400" />
                                <span>Root Administrator Email</span>
                            </Label>
                            <button
                                type="button"
                                class="font-mono text-[10px] text-emerald-400 transition-colors hover:text-emerald-300 hover:underline"
                                @click="quickFillEmail"
                            >
                                Autofill Admin
                            </button>
                        </div>
                        <Input
                            id="admin-email"
                            v-model="form.email"
                            type="email"
                            placeholder="superadmin@mdepos.co.ke"
                            required
                            autofocus
                            autocomplete="username"
                            class="h-10 border-slate-700/80 bg-slate-950/90 font-mono text-xs text-slate-100 placeholder:text-slate-600 focus:border-emerald-500 focus:ring-emerald-500/20"
                        />
                    </div>

                    <!-- Master Security Key / Password -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="admin-password" class="flex items-center gap-1.5 font-mono text-xs font-medium text-slate-300">
                                <Lock class="size-3 text-emerald-400" />
                                <span>Master Security Key</span>
                            </Label>
                        </div>
                        <div class="relative">
                            <Input
                                id="admin-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••••••••••"
                                required
                                autocomplete="current-password"
                                class="h-10 border-slate-700/80 bg-slate-950/90 pr-9 font-mono text-xs text-slate-100 placeholder:text-slate-600 focus:border-emerald-500 focus:ring-emerald-500/20"
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 transition-colors hover:text-slate-300"
                                @click="showPassword = !showPassword"
                                tabindex="-1"
                            >
                                <Eye v-if="!showPassword" class="size-4" />
                                <EyeOff v-else class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Hardware Session Persistence Toggle -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="size-3.5 rounded border-slate-700 bg-slate-950 text-emerald-600 focus:ring-emerald-500/30"
                            />
                            <span class="font-sans text-[11px] text-slate-400">Retain terminal authorization</span>
                        </label>
                        <span class="flex items-center gap-1 font-mono text-[10px] text-slate-500">
                            <Radio class="size-3 text-emerald-500/70" />
                            <span>IP Monitored</span>
                        </span>
                    </div>

                    <!-- Authenticate Clearance Button -->
                    <Button
                        type="submit"
                        :disabled="form.processing || isProcessing"
                        class="mt-2 h-11 w-full gap-2 border border-emerald-400/20 bg-gradient-to-r from-emerald-600 to-teal-600 font-mono text-xs font-bold text-white shadow-lg shadow-emerald-950/60 transition-all hover:from-emerald-500 hover:to-teal-500"
                    >
                        <span
                            v-if="form.processing || isProcessing"
                            class="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                        ></span>
                        <Terminal v-else class="size-4" />
                        <span>{{ form.processing || isProcessing ? 'Verifying Security Clearance…' : 'Authenticate Root Clearance' }}</span>
                    </Button>
                </form>

                <!-- Switch to Store Login Notice -->
                <div class="flex flex-col items-center gap-2 border-t border-slate-800/80 pt-4 text-center">
                    <p class="font-sans text-[11px] text-slate-400">Are you a store manager, cashier, or tenant staff?</p>
                    <Link
                        href="/login"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-400 transition-colors hover:text-emerald-300"
                    >
                        <span>Go to Store Staff Login</span>
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>

                <!-- Security Advisory Notice -->
                <div class="rounded-lg border border-slate-800/70 bg-slate-950/70 p-2.5 text-center">
                    <p class="flex items-center justify-center gap-1.5 font-mono text-[10px] text-slate-500">
                        <AlertTriangle class="size-3 shrink-0 text-amber-500/80" />
                        <span>All access attempts logged with IP and hardware fingerprints.</span>
                    </p>
                </div>
            </div>
        </main>

        <!-- Bottom Platform Footer -->
        <footer
            class="relative z-10 flex w-full max-w-4xl flex-wrap items-center justify-between gap-2 border-t border-slate-900 py-2 pt-3 text-center font-mono text-[10px] text-slate-500"
        >
            <span>MDE-POS Kernel Platform Authority &copy; {{ new Date().getFullYear() }}</span>
            <div class="flex items-center gap-3">
                <span>Multi-Tenant Architecture</span>
                <span>•</span>
                <span>Subdomain Routing Engine</span>
            </div>
        </footer>
    </div>
</template>
