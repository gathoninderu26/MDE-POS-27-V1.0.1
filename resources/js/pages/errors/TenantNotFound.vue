<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import { AlertOctagon, ArrowLeft, HelpCircle, Store } from 'lucide-vue-next';

const props = defineProps<{
    subdomain: string;
    tenantName?: string;
    isSuspended?: boolean;
    centralUrl?: string;
}>();
</script>

<template>
    <Head :title="isSuspended ? 'Account Suspended — MDE POS' : 'Store Not Found — MDE POS'" />

    <div class="relative flex min-h-svh w-full flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
        <!-- Ambient aura -->
        <div
            class="pointer-events-none absolute inset-0"
            :style="`background: radial-gradient(circle at 50% 38%, ${isSuspended ? 'rgba(239,68,68,0.07)' : 'rgba(245,158,11,0.07)'}, transparent 62%)`"
        />

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <!-- Logo & Heading -->
                <div class="flex flex-col items-center gap-4">
                    <a href="/" class="flex flex-col items-center gap-2">
                        <div class="mb-1 flex size-9 items-center justify-center rounded-md">
                            <AppLogoIcon class="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                        </div>
                    </a>
                    <div class="space-y-1.5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <span
                                :class="[
                                    'inline-flex items-center rounded-lg px-2.5 py-0.5 font-mono text-sm font-black tracking-wide',
                                    isSuspended
                                        ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                ]"
                            >
                                {{ isSuspended ? '403' : '404' }}
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                                {{ isSuspended ? 'Suspended' : 'Not Found' }}
                            </span>
                        </div>
                        <h1 class="text-xl font-semibold text-foreground">
                            {{ isSuspended ? 'Account Access Restricted' : 'Store Not Found' }}
                        </h1>
                        <p class="text-center text-sm leading-relaxed text-muted-foreground">
                            <span v-if="isSuspended">
                                The workspace for <strong class="text-foreground">{{ tenantName || subdomain }}</strong> has been suspended due to a
                                billing or policy review.
                            </span>
                            <span v-else> No active business is registered under this subdomain address. </span>
                        </p>
                    </div>
                </div>

                <!-- Body -->
                <div class="space-y-4">
                    <!-- Icon -->
                    <div class="flex justify-center">
                        <div
                            :class="[
                                'flex size-16 items-center justify-center rounded-2xl border shadow-sm',
                                isSuspended
                                    ? 'border-rose-500/20 bg-rose-500/10 text-rose-500'
                                    : 'border-amber-500/20 bg-amber-500/10 text-amber-500',
                            ]"
                        >
                            <AlertOctagon v-if="isSuspended" class="size-8" />
                            <Store v-else class="size-8" />
                        </div>
                    </div>

                    <!-- Subdomain tag -->
                    <div class="flex justify-center">
                        <div
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-muted/60 px-3 py-1.5 font-mono text-xs font-bold text-primary"
                        >
                            <Store class="size-3 opacity-60" />
                            {{ subdomain }}.mdepos.co.ke
                        </div>
                    </div>

                    <!-- Tips -->
                    <div class="space-y-2 rounded-xl border border-border/60 bg-muted/30 p-3.5 text-left text-xs">
                        <div class="flex items-center gap-1.5 font-semibold text-foreground">
                            <HelpCircle class="size-3.5 text-primary" />
                            <span>What you can do:</span>
                        </div>
                        <ul class="list-inside list-disc space-y-1 text-[11px] text-muted-foreground">
                            <li v-if="!isSuspended">Verify your store subdomain URL (e.g. <code>yourstore.mdepos.co.ke</code>).</li>
                            <li>Contact your business administrator for your direct store login link.</li>
                            <li v-if="isSuspended">Contact your account manager to resolve billing issues.</li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2">
                        <a href="/" class="block w-full">
                            <Button class="h-10 w-full gap-2 text-xs font-bold shadow-sm">
                                <ArrowLeft class="size-4" />
                                <span>Return to Main Portal</span>
                            </Button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="absolute bottom-6 font-mono text-xs text-muted-foreground">MDE POS &nbsp;•&nbsp; Multi-Tenant Subdomain Routing</p>
    </div>
</template>
