<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-xl bg-primary text-xs font-black text-primary-foreground shadow-sm"
    >
        MDE
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">MDE Point of Sale</span>
    </div>
</template>
