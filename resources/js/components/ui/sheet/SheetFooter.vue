<script setup lang="ts">
import { cn } from '@/lib/utils';
import { provide, type HTMLAttributes } from 'vue';

provide('isInDialog', true);
provide('isInDialogFooter', true);

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div :class="cn('flex flex-col-reverse sm:flex-row sm:justify-end sm:gap-x-2', props.class)">
        <slot />
    </div>
</template>
