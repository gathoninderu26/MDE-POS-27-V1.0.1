<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    HelpCircle,
    PlugZap,
    RefreshCw,
    SearchX,
    ServerCrash,
    ShieldX,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
}>();

const config = computed(() => {
    switch (props.status) {
        case 404:
            return {
                icon: SearchX,
                aura: 'rgba(245,158,11,0.07)',
                iconBg: 'bg-amber-500/10 border-amber-500/20 text-amber-500',
                badge: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                title: 'Page Not Found',
                description: "We couldn't find the page you're looking for. It may have been moved, renamed, or may never have existed.",
                tips: [
                    'Double-check the URL spelling for typos.',
                    'Navigate back to the dashboard and try again.',
                    'If you followed a link, it may be outdated.',
                ],
            };
        case 403:
            return {
                icon: ShieldX,
                aura: 'rgba(239,68,68,0.07)',
                iconBg: 'bg-rose-500/10 border-rose-500/20 text-rose-500',
                badge: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
                title: 'Access Forbidden',
                description: "You don't have the required permissions to access this resource. Contact your administrator if you believe this is a mistake.",
                tips: [
                    'Ensure you are logged in with the correct account.',
                    'Ask your administrator to review your role permissions.',
                    'You may have followed a link meant for a different role.',
                ],
            };
        case 500:
            return {
                icon: ServerCrash,
                aura: 'rgba(168,85,247,0.07)',
                iconBg: 'bg-violet-500/10 border-violet-500/20 text-violet-500',
                badge: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
                title: 'Server Error',
                description: 'Something went wrong on our end. Our team has been notified and is working on a fix. Please try again shortly.',
                tips: [
                    'Refresh the page — it may resolve itself.',
                    'Try again in a few minutes.',
                    'If the issue persists, contact your system administrator.',
                ],
            };
        case 503:
            return {
                icon: PlugZap,
                aura: 'rgba(59,130,246,0.07)',
                iconBg: 'bg-blue-500/10 border-blue-500/20 text-blue-500',
                badge: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
                title: 'Service Unavailable',
                description: 'MDE POS is temporarily offline for scheduled maintenance. We will be back online shortly.',
                tips: [
                    'The system is undergoing routine maintenance.',
                    'Please check back in a few minutes.',
                    'Contact support if this lasts longer than expected.',
                ],
            };
        case 419:
            return {
                icon: RefreshCw,
                aura: 'rgba(20,184,166,0.07)',
                iconBg: 'bg-teal-500/10 border-teal-500/20 text-teal-500',
                badge: 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
                title: 'Session Expired',
                description: "Your session has expired for security reasons. Please refresh the page or log in again to continue.",
                tips: [
                    'Refresh the page to renew your session.',
                    'Log in again if the problem continues.',
                    'Avoid leaving the app idle for long periods.',
                ],
            };
        default:
            return {
                icon: AlertTriangle,
                aura: 'rgba(245,158,11,0.07)',
                iconBg: 'bg-amber-500/10 border-amber-500/20 text-amber-500',
                badge: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                title: 'Unexpected Error',
                description: "An unexpected error occurred. If this keeps happening, please contact your system administrator.",
                tips: [
                    'Try refreshing the page.',
                    'Return to the dashboard and try again.',
                    'Contact support if the error persists.',
                ],
            };
    }
});

const statusLabels: Record<number, string> = {
    403: 'Forbidden',
    404: 'Not Found',
    419: 'Session Expired',
    500: 'Server Error',
    503: 'Service Unavailable',
};

const statusLabel = computed(() => statusLabels[props.status] ?? 'Error');

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
};
</script>

<template>
    <Head :title="`${status} — ${config.title}`" />

    <div class="relative flex min-h-svh w-full flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
        <!-- Ambient aura -->
        <div
            class="pointer-events-none absolute inset-0"
            :style="`background: radial-gradient(circle at 50% 38%, ${config.aura}, transparent 62%)`"
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
                            <span :class="['inline-flex items-center rounded-lg px-2.5 py-0.5 font-mono text-sm font-black tracking-wide', config.badge]">
                                {{ status }}
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                                {{ statusLabel }}
                            </span>
                        </div>
                        <h1 class="text-xl font-semibold text-foreground">{{ config.title }}</h1>
                        <p class="text-center text-sm leading-relaxed text-muted-foreground">
                            {{ config.description }}
                        </p>
                    </div>
                </div>

                <!-- Body -->
                <div class="space-y-4">
                    <!-- Icon -->
                    <div class="flex justify-center">
                        <div :class="['flex size-16 items-center justify-center rounded-2xl border shadow-sm', config.iconBg]">
                            <component :is="config.icon" class="size-8" />
                        </div>
                    </div>

                    <!-- Tips -->
                    <div class="rounded-xl border border-border/60 bg-muted/30 p-3.5 text-left text-xs space-y-2">
                        <div class="flex items-center gap-1.5 font-semibold text-foreground">
                            <HelpCircle class="size-3.5 text-primary" />
                            <span>What you can do:</span>
                        </div>
                        <ul class="list-inside list-disc space-y-1 text-[11px] text-muted-foreground">
                            <li v-for="tip in config.tips" :key="tip">{{ tip }}</li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2">
                        <Button class="w-full gap-2 text-xs font-bold h-10 shadow-sm" @click="goBack">
                            <ArrowLeft class="size-4" />
                            <span>Go Back</span>
                        </Button>
                        <a href="/">
                            <Button variant="outline" class="w-full gap-2 text-xs h-9">
                                Return to Dashboard
                            </Button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="absolute bottom-6 text-xs font-mono text-muted-foreground">
            MDE POS &nbsp;•&nbsp; Error {{ status }}
        </p>
    </div>
</template>
