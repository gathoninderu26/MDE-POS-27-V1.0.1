<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import { computed, inject, onBeforeUnmount, onMounted, ref, type HTMLAttributes } from 'vue';
import { buttonVariants, type ButtonVariants } from '.';
import { Loader2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
    loading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
    loading: false,
    disabled: false,
});

const buttonRef = ref<any>(null);
const isLocalSubmitting = ref(false);
const isInertiaSubmitting = ref(false);
let clickedRecentlyTimeout: ReturnType<typeof setTimeout> | null = null;
let safetyTimeout: ReturnType<typeof setTimeout> | null = null;
let clickedRecently = false;

// Injected flags from dialog container
const isInDialog = inject<boolean>('isInDialog', false);
const isInDialogFooter = inject<boolean>('isInDialogFooter', false);

// Global Inertia request lifecycle listener
let unsubStart: (() => void) | null = null;
let unsubFinish: (() => void) | null = null;

try {
    unsubStart = router.on('start', () => {
        if (clickedRecently) {
            isInertiaSubmitting.value = true;
            if (safetyTimeout) clearTimeout(safetyTimeout);
            safetyTimeout = setTimeout(() => {
                isInertiaSubmitting.value = false;
                clickedRecently = false;
            }, 20000);
        }
    });

    unsubFinish = router.on('finish', () => {
        isInertiaSubmitting.value = false;
        isLocalSubmitting.value = false;
        clickedRecently = false;
        if (safetyTimeout) clearTimeout(safetyTimeout);
    });
} catch {
    // Non-Inertia or test environment fallback
}

onMounted(() => {
    try {
        const el = buttonRef.value?.$el || buttonRef.value;
        if (el instanceof HTMLElement) {
            const form = el.closest('form');
            if (form) {
                const handleFormSubmit = () => {
                    // If button is submit type or has primary styling inside form
                    const btnType = el.getAttribute('type');
                    if (btnType === 'submit' || !btnType || btnType === '') {
                        clickedRecently = true;
                        if (clickedRecentlyTimeout) clearTimeout(clickedRecentlyTimeout);
                        clickedRecentlyTimeout = setTimeout(() => {
                            clickedRecently = false;
                        }, 1500);
                    }
                };
                form.addEventListener('submit', handleFormSubmit);
                onBeforeUnmount(() => {
                    form.removeEventListener('submit', handleFormSubmit);
                });
            }
        }
    } catch {
        // Ignored
    }
});

onBeforeUnmount(() => {
    unsubStart?.();
    unsubFinish?.();
    if (clickedRecentlyTimeout) clearTimeout(clickedRecentlyTimeout);
    if (safetyTimeout) clearTimeout(safetyTimeout);
});

const isSpinning = computed(() => {
    return Boolean(props.loading || isLocalSubmitting.value || isInertiaSubmitting.value);
});

const handleClick = (event: MouseEvent) => {
    if (isSpinning.value || props.disabled) {
        event.preventDefault();
        event.stopPropagation();
        return;
    }

    clickedRecently = true;
    if (clickedRecentlyTimeout) clearTimeout(clickedRecentlyTimeout);
    clickedRecentlyTimeout = setTimeout(() => {
        clickedRecently = false;
    }, 1500);
};
</script>

<template>
    <Primitive
        ref="buttonRef"
        :as="as"
        :as-child="asChild"
        :disabled="isSpinning || disabled || undefined"
        :aria-busy="isSpinning"
        :class="cn(buttonVariants({ variant, size }), props.class)"
        @click="handleClick"
    >
        <Loader2 v-if="!asChild && isSpinning" class="size-3.5 animate-spin shrink-0" />
        <slot />
    </Primitive>
</template>
