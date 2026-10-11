<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <div
        :class="[
            'flex items-center justify-center rounded-xl bg-primary text-primary-foreground font-black tracking-tight select-none shadow-sm',
            className,
        ]"
        v-bind="$attrs"
    >
        <span>MDE</span>
    </div>
</template>
