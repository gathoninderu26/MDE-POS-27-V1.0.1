<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, KeyRound, Lock, ShieldAlert, ShieldCheck, Terminal } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isProcessing = ref(false);

const submit = () => {
    isProcessing.value = true;
    form.post('/mde-control-portal', {
        onFinish: () => {
            isProcessing.value = false;
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="System Command Console" />

    <div class="min-h-screen w-full bg-slate-950 text-slate-100 flex flex-col justify-center items-center p-4 relative overflow-hidden font-sans select-none">
        <!-- Background Grid Grid Effect -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] opacity-30"></div>

        <!-- Glow Accents -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 size-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Security Terminal Card -->
        <div class="relative w-full max-w-md bg-slate-900/90 border border-slate-800 backdrop-blur-xl rounded-2xl shadow-2xl overflow-hidden p-6 sm:p-8 space-y-6">
            
            <!-- Terminal Header Bar -->
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-2">
                    <div class="size-3 rounded-full bg-rose-500/80"></div>
                    <div class="size-3 rounded-full bg-amber-500/80"></div>
                    <div class="size-3 rounded-full bg-emerald-500/80"></div>
                    <span class="text-[11px] font-mono text-slate-400 ml-2">mde-core://root-access</span>
                </div>
                <div class="flex items-center gap-1.5 text-[10px] font-mono text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                    <ShieldCheck class="size-3" />
                    <span>ENCRYPTED</span>
                </div>
            </div>

            <!-- Console Title -->
            <div class="space-y-1 text-center">
                <div class="mx-auto size-12 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-emerald-400 shadow-inner mb-3">
                    <Terminal class="size-6" />
                </div>
                <h1 class="text-xl font-bold tracking-tight text-white font-mono">Platform Admin Gateway</h1>
                <p class="text-xs text-slate-400 font-mono">
                    Restricted Area • Clearance Level 0
                </p>
            </div>

            <!-- Status / Alert -->
            <div v-if="status" class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-xs font-mono text-emerald-400 flex items-center gap-2">
                <ShieldCheck class="size-4 shrink-0" />
                <span>{{ status }}</span>
            </div>

            <div v-if="form.errors.email" class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-xs font-mono text-rose-400 flex items-center gap-2">
                <ShieldAlert class="size-4 shrink-0" />
                <span>{{ form.errors.email }}</span>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-4 font-mono">
                <!-- Root Email / Identifier -->
                <div class="space-y-1.5">
                    <Label for="email" class="text-xs text-slate-300 flex items-center gap-1.5">
                        <KeyRound class="size-3 text-slate-400" />
                        <span>Administrator Email</span>
                    </Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="superadmin@mdepos.co.ke"
                        required
                        autofocus
                        autocomplete="username"
                        class="bg-slate-950/80 border-slate-700 text-slate-100 placeholder:text-slate-600 text-xs h-10 font-mono focus:border-emerald-500 focus:ring-emerald-500/20"
                    />
                </div>

                <!-- Secret Master Key / Password -->
                <div class="space-y-1.5">
                    <Label for="password" class="text-xs text-slate-300 flex items-center gap-1.5">
                        <Lock class="size-3 text-slate-400" />
                        <span>Master Security Key</span>
                    </Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••••••"
                        required
                        autocomplete="current-password"
                        class="bg-slate-950/80 border-slate-700 text-slate-100 placeholder:text-slate-600 text-xs h-10 font-mono focus:border-emerald-500 focus:ring-emerald-500/20"
                    />
                </div>

                <!-- Remember / Session Flag -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="size-3.5 rounded border-slate-700 bg-slate-950 text-emerald-600 focus:ring-emerald-500/30"
                        />
                        <span class="text-[11px] text-slate-400">Keep session state</span>
                    </label>
                    <span class="text-[10px] text-slate-500">IP: Protected</span>
                </div>

                <!-- Submit Button -->
                <Button
                    type="submit"
                    :disabled="form.processing || isProcessing"
                    class="w-full h-10 bg-emerald-600 hover:bg-emerald-500 text-white font-mono font-bold text-xs shadow-lg shadow-emerald-950/50 transition-all gap-2 mt-2"
                >
                    <span v-if="form.processing || isProcessing" class="size-3.5 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                    <Terminal v-else class="size-3.5" />
                    <span>Authenticate Clearance</span>
                </Button>
            </form>

            <!-- Security Audit Footer -->
            <div class="border-t border-slate-800/80 pt-3 text-center">
                <p class="text-[10px] text-slate-500 font-mono flex items-center justify-center gap-1">
                    <AlertTriangle class="size-3 text-amber-500/80" />
                    <span>Unauthorized access attempts are audited and reported.</span>
                </p>
            </div>
        </div>

        <p class="text-[10px] text-slate-600 font-mono mt-6">MDE POS • Multi-Tenant Kernel Engine</p>
    </div>
</template>
