<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Component } from 'vue';

interface Props {
    title: string;
    value: string | number;
    subValue?: string;
    change?: number;
    changeText?: string;
    icon?: Component;
    iconColor?: string;
    iconBg?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    iconColor: 'text-primary',
    iconBg: 'bg-primary/10',
});
</script>

<template>
    <Card :class="cn('overflow-hidden transition-all duration-200 hover:shadow-md border-border/80', props.class)">
        <CardContent class="p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ title }}</p>
                <div v-if="icon" :class="cn('flex size-9 items-center justify-center rounded-lg', iconBg)">
                    <component :is="icon" :class="cn('size-5', iconColor)" />
                </div>
            </div>

            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight text-foreground">{{ value }}</span>
                <span v-if="subValue" class="text-xs text-muted-foreground">{{ subValue }}</span>
            </div>

            <div v-if="change !== undefined || changeText" class="mt-2.5 flex items-center gap-1.5 text-xs">
                <span
                    v-if="change !== undefined"
                    :class="[
                        'inline-flex items-center font-medium',
                        change > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : change < 0
                              ? 'text-rose-600 dark:text-rose-400'
                              : 'text-muted-foreground',
                    ]"
                >
                    <span v-if="change > 0">↑ +{{ change }}%</span>
                    <span v-else-if="change < 0">↓ {{ change }}%</span>
                    <span v-else>— 0%</span>
                </span>
                <span v-if="changeText" class="text-muted-foreground">{{ changeText }}</span>
            </div>
        </CardContent>
    </Card>
</template>
