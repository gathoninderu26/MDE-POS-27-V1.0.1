<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Inbox } from 'lucide-vue-next';
import type { Component } from 'vue';

interface Props {
    title: string;
    description: string;
    icon?: Component;
    actionText?: string;
}

withDefaults(defineProps<Props>(), {
    icon: Inbox,
});

defineEmits<{
    (e: 'action'): void;
}>();
</script>

<template>
    <div class="flex min-h-[300px] flex-col items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/20 p-8 text-center">
        <div class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground shadow-sm">
            <component :is="icon" class="size-7" />
        </div>
        <h4 class="mt-4 text-base font-semibold text-foreground">{{ title }}</h4>
        <p class="mt-1 max-w-sm text-xs text-muted-foreground">{{ description }}</p>
        <div v-if="actionText" class="mt-5">
            <Button size="sm" @click="$emit('action')">
                {{ actionText }}
            </Button>
        </div>
    </div>
</template>
