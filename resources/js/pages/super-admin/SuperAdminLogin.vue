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
    Layers,
    Lock,
    Radio,
    Server,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
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

    <div class="min-h-screen w-full bg-[#070b12] text-slate-100 flex flex-col justify-between items-center p-4 sm:p-6 relative overflow-hidden font-sans select-none">
        
        <!-- Cybernetic Grid & Glow Mesh Background -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#0f172a_1px,transparent_1px),linear-gradient(to_bottom,#0f172a_1px,transparent_1px)] bg-[size:3.5rem_3.5rem] [mask-image:radial-gradient(ellipse_70%_60%_at_50%_40%,#000_70%,transparent_100%)] opacity-40 pointer-events-none"></div>
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 size-[550px] bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute -bottom-40 right-10 size-[450px] bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>

        <!-- Top Platform Telemetry Bar -->
        <header class="w-full max-w-4xl relative z-10 flex flex-wrap items-center justify-between gap-3 py-2 px-4 rounded-xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md text-[11px] font-mono text-slate-400">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="relative flex size-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2 bg-emerald-500"></span>
                    </span>
                    <span class="font-bold text-slate-200">KERNEL v1.0.1</span>
                </div>
                <span class="text-slate-600 hidden sm:inline">|</span>
                <div class="hidden sm:flex items-center gap-1 text-slate-400">
                    <Server class="size-3 text-emerald-400" />
                    <span>CENTRAL CLUSTER</span>
                </div>
                <span class="text-slate-600 hidden md:inline">|</span>
                <div class="hidden md:flex items-center gap-1 text-slate-400">
                    <Activity class="size-3 text-cyan-400" />
                    <span>AUTH PROTOCOL: TLS 1.3</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-bold text-[10px] tracking-wide flex items-center gap-1">
                    <ShieldCheck class="size-3" />
                    CLEARANCE: ROOT (SUPER ADMIN)
                </span>
            </div>
        </header>

        <!-- Main Security Gateway Card -->
        <main class="w-full max-w-md relative z-10 my-auto py-4">
            <div class="bg-slate-900/90 border border-slate-800/90 backdrop-blur-2xl rounded-2xl shadow-2xl shadow-black/80 overflow-hidden p-6 sm:p-8 space-y-6 relative">
                
                <!-- Glowing Top Accent Line -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-500"></div>

                <!-- Window Console Controls Header -->
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div class="flex items-center gap-2">
                        <div class="size-2.5 rounded-full bg-rose-500/70 border border-rose-400/40"></div>
                        <div class="size-2.5 rounded-full bg-amber-500/70 border border-amber-400/40"></div>
                        <div class="size-2.5 rounded-full bg-emerald-500/70 border border-emerald-400/40"></div>
                        <span class="text-[11px] font-mono text-slate-400 ml-2 tracking-tight">mde-kernel://auth.root</span>
                    </div>
                    <span class="text-[10px] font-mono text-slate-500">PORT: 8000</span>
                </div>

                <!-- Badge & Title -->
                <div class="text-center space-y-2">
                    <div class="mx-auto size-14 rounded-2xl bg-gradient-to-b from-slate-800 to-slate-950 border border-slate-700/80 flex items-center justify-center text-emerald-400 shadow-inner ring-4 ring-emerald-500/10 mb-2">
                        <Cpu class="size-7 animate-pulse text-emerald-400" />
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-white font-mono flex items-center justify-center gap-2">
                        <span>Platform Super Admin</span>
                    </h1>
                    <p class="text-xs text-slate-400 leading-relaxed font-sans max-w-xs mx-auto">
                        Multi-tenant core supervisor terminal. Administrative security clearance required.
                    </p>
                </div>

                <!-- Status Messages / Feedback -->
                <div v-if="status" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs font-mono text-emerald-400 flex items-center gap-2">
                    <ShieldCheck class="size-4 shrink-0" />
                    <span>{{ status }}</span>
                </div>

                <div v-if="form.errors.email" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-xs font-mono text-rose-400 flex items-start gap-2.5">
                    <ShieldAlert class="size-4 shrink-0 mt-0.5 text-rose-400" />
                    <div>
                        <p class="font-bold">Access Verification Failed</p>
                        <p class="text-[11px] text-rose-300 mt-0.5">{{ form.errors.email }}</p>
                    </div>
                </div>

                <!-- Authentication Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email / Root Identifier -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="admin-email" class="text-xs font-medium text-slate-300 flex items-center gap-1.5 font-mono">
                                <KeyRound class="size-3 text-emerald-400" />
                                <span>Root Administrator Email</span>
                            </Label>
                            <button
                                type="button"
                                class="text-[10px] font-mono text-emerald-400 hover:text-emerald-300 hover:underline transition-colors"
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
                            class="bg-slate-950/90 border-slate-700/80 text-slate-100 placeholder:text-slate-600 text-xs h-10 font-mono focus:border-emerald-500 focus:ring-emerald-500/20"
                        />
                    </div>

                    <!-- Master Security Key / Password -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="admin-password" class="text-xs font-medium text-slate-300 flex items-center gap-1.5 font-mono">
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
                                class="bg-slate-950/90 border-slate-700/80 text-slate-100 placeholder:text-slate-600 text-xs h-10 font-mono pr-9 focus:border-emerald-500 focus:ring-emerald-500/20"
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
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
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="size-3.5 rounded border-slate-700 bg-slate-950 text-emerald-600 focus:ring-emerald-500/30"
                            />
                            <span class="text-[11px] text-slate-400 font-sans">Retain terminal authorization</span>
                        </label>
                        <span class="text-[10px] text-slate-500 font-mono flex items-center gap-1">
                            <Radio class="size-3 text-emerald-500/70" />
                            <span>IP Monitored</span>
                        </span>
                    </div>

                    <!-- Authenticate Clearance Button -->
                    <Button
                        type="submit"
                        :disabled="form.processing || isProcessing"
                        class="w-full h-11 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-mono font-bold text-xs shadow-lg shadow-emerald-950/60 transition-all gap-2 mt-2 border border-emerald-400/20"
                    >
                        <span v-if="form.processing || isProcessing" class="size-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                        <Terminal v-else class="size-4" />
                        <span>{{ form.processing || isProcessing ? 'Verifying Security Clearance…' : 'Authenticate Root Clearance' }}</span>
                    </Button>
                </form>

                <!-- Switch to Store Login Notice -->
                <div class="border-t border-slate-800/80 pt-4 flex flex-col items-center gap-2 text-center">
                    <p class="text-[11px] text-slate-400 font-sans">
                        Are you a store manager, cashier, or tenant staff?
                    </p>
                    <Link
                        href="/login"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-400 hover:text-emerald-300 transition-colors"
                    >
                        <span>Go to Store Staff Login</span>
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>

                <!-- Security Advisory Notice -->
                <div class="rounded-lg bg-slate-950/70 border border-slate-800/70 p-2.5 text-center">
                    <p class="text-[10px] text-slate-500 font-mono flex items-center justify-center gap-1.5">
                        <AlertTriangle class="size-3 text-amber-500/80 shrink-0" />
                        <span>All access attempts logged with IP and hardware fingerprints.</span>
                    </p>
                </div>
            </div>
        </main>

        <!-- Bottom Platform Footer -->
        <footer class="w-full max-w-4xl relative z-10 py-2 text-center text-[10px] font-mono text-slate-500 flex flex-wrap items-center justify-between gap-2 border-t border-slate-900 pt-3">
            <span>MDE-POS Kernel Platform Authority &copy; {{ new Date().getFullYear() }}</span>
            <div class="flex items-center gap-3">
                <span>Multi-Tenant Architecture</span>
                <span>•</span>
                <span>Subdomain Routing Engine</span>
            </div>
        </footer>
    </div>
</template>
