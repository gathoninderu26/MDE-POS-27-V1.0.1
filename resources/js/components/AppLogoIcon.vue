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
            'flex select-none items-center justify-center rounded-xl bg-primary font-black tracking-tight text-primary-foreground shadow-sm',
            className,
        ]"
        v-bind="$attrs"
    >
        <span>MDE</span>
    </div>
</template>
