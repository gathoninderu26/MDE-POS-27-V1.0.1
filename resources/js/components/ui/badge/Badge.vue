<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

interface Props {
    variant?: 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning' | 'info';
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});

const variantStyles = {
    default: 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
    secondary: 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
    destructive: 'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
    outline: 'text-foreground border-border',
    success: 'border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
    warning: 'border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/20',
    info: 'border-transparent bg-sky-500/15 text-sky-700 dark:text-sky-400 border border-sky-500/20',
};
</script>

<template>
    <div
        :class="
            cn(
                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                variantStyles[props.variant],
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
