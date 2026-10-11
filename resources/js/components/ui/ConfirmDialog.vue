<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle, Trash2 } from 'lucide-vue-next';

interface Props {
    open: boolean;
    title: string;
    description: string;
    itemName?: string;
    warningNote?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'destructive' | 'default';
    loading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Yes, Delete',
    cancelText: 'Cancel',
    variant: 'destructive',
    loading: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const handleCancel = () => {
    emit('update:open', false);
    emit('cancel');
};

const handleConfirm = () => {
    emit('confirm');
};
</script>

<template>
    <Dialog :open="open" spinnerText="Preparing Confirmation..." @update:open="(v) => emit('update:open', v)">
        <DialogContent class="w-[94vw] max-w-md p-6">
            <DialogHeader class="gap-3">
                <div class="flex items-start gap-3.5">
                    <div
                        :class="[
                            'flex size-11 shrink-0 items-center justify-center rounded-xl shadow-xs',
                            variant === 'destructive'
                                ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30'
                                : 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30',
                        ]"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                    <div class="space-y-1 text-left">
                        <DialogTitle class="text-base font-bold text-foreground">
                            {{ title }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Target Item Highlight if provided -->
            <div v-if="itemName" class="rounded-xl border border-border/80 bg-muted/40 p-3 text-xs flex items-center justify-between">
                <span class="text-muted-foreground">Target Record:</span>
                <span class="font-bold text-foreground truncate max-w-[240px] font-mono">{{ itemName }}</span>
            </div>

            <!-- Caution Note (e.g. blockers or unrecoverable warnings) -->
            <div
                v-if="warningNote"
                class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2"
            >
                <AlertTriangle class="size-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                <span class="leading-relaxed">{{ warningNote }}</span>
            </div>

            <slot />

            <DialogFooter class="mt-2 flex items-center justify-end gap-2">
                <Button variant="ghost" size="sm" :disabled="loading" @click="handleCancel">
                    {{ cancelText }}
                </Button>
                <Button
                    :variant="variant === 'destructive' ? 'destructive' : 'default'"
                    size="sm"
                    :class="variant === 'destructive' ? 'bg-rose-600 hover:bg-rose-700 text-white font-bold gap-1.5 shadow-xs' : 'font-bold gap-1.5 shadow-xs'"
                    :disabled="disabled || loading"
                    :loading="loading"
                    @click="handleConfirm"
                >
                    <Trash2 v-if="variant === 'destructive' && !loading" class="size-3.5" />
                    <span>{{ confirmText }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
