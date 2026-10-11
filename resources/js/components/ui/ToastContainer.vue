<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { AlertCircle, CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';

const { toasts, removeToast } = useToast();

const getIcon = (type?: string) => {
    switch (type) {
        case 'success':
            return CheckCircle2;
        case 'error':
            return XCircle;
        case 'warning':
            return AlertCircle;
        default:
            return Info;
    }
};

const getBorderColor = (type?: string) => {
    switch (type) {
        case 'success':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-950 dark:text-emerald-200';
        case 'error':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-950 dark:text-rose-200';
        case 'warning':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-950 dark:text-amber-200';
        default:
            return 'border-sky-500/30 bg-sky-500/10 text-sky-950 dark:text-sky-200';
    }
};

const getIconColor = (type?: string) => {
    switch (type) {
        case 'success':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'error':
            return 'text-rose-600 dark:text-rose-400';
        case 'warning':
            return 'text-amber-600 dark:text-amber-400';
        default:
            return 'text-sky-600 dark:text-sky-400';
    }
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] flex w-full max-w-sm flex-col gap-2 pointer-events-none px-4 print:hidden">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform -translate-y-3 opacity-0 scale-95"
                enter-to-class="transform translate-y-0 opacity-100 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform -translate-y-2 opacity-0 scale-95"
            >
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    :class="[
                        'pointer-events-auto flex w-full items-start gap-3 rounded-xl border p-4 shadow-lg backdrop-blur-md transition-all',
                        getBorderColor(t.type),
                        'bg-card/95 text-card-foreground',
                    ]"
                >
                    <component :is="getIcon(t.type)" :class="['mt-0.5 size-5 shrink-0', getIconColor(t.type)]" />
                    <div class="flex-1">
                        <h5 class="text-sm font-semibold">{{ t.title }}</h5>
                        <p v-if="t.description" class="mt-0.5 text-xs text-muted-foreground">{{ t.description }}</p>
                    </div>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        @click="removeToast(t.id)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
