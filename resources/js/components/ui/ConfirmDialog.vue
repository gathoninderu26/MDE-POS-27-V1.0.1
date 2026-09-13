<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle } from 'lucide-vue-next';

interface Props {
    open: boolean;
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'destructive' | 'default';
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'destructive',
    loading: false,
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
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="gap-2">
                <div class="flex items-center gap-3">
                    <div
                        :class="[
                            'flex size-10 items-center justify-center rounded-full',
                            variant === 'destructive' ? 'bg-destructive/15 text-destructive' : 'bg-primary/15 text-primary',
                        ]"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-semibold">{{ title }}</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogFooter class="mt-4 gap-2 sm:gap-0">
                <Button variant="outline" size="sm" :disabled="loading" @click="handleCancel">
                    {{ cancelText }}
                </Button>
                <Button
                    :variant="variant === 'destructive' ? 'destructive' : 'default'"
                    size="sm"
                    :disabled="loading"
                    @click="handleConfirm"
                >
                    <span v-if="loading">Processing...</span>
                    <span v-else>{{ confirmText }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
