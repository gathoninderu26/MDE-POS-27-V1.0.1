<script setup lang="ts">
import { DialogRoot, useForwardPropsEmits, type DialogRootEmits, type DialogRootProps } from 'radix-vue';
import { Loader2 } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<DialogRootProps & {
        showSpinner?: boolean;
        spinnerText?: string;
    }>(),
    {
        showSpinner: true,
        spinnerText: 'Loading dialog...',
    }
);

const emits = defineEmits<DialogRootEmits>();

const delegatedProps = computed(() => {
    const { showSpinner: _, spinnerText: __, open: ___, ...delegated } = props;
    return delegated;
});

const isControlled = computed(() => props.open !== undefined);
const isOpening = ref(false);
const actualOpen = ref(props.open ?? false);
let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.open,
    (newVal, oldVal) => {
        if (newVal === true && !oldVal) {
            if (props.showSpinner) {
                isOpening.value = true;
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => {
                    isOpening.value = false;
                    actualOpen.value = true;
                }, 220);
            } else {
                actualOpen.value = true;
            }
        } else if (newVal === false) {
            if (timer) clearTimeout(timer);
            timer = null;
            isOpening.value = false;
            actualOpen.value = false;
        } else if (newVal !== undefined) {
            actualOpen.value = newVal;
        }
    }
);

onUnmounted(() => {
    if (timer) clearTimeout(timer);
});

const handleUpdateOpen = (val: boolean) => {
    actualOpen.value = val;
    emits('update:open', val);
};

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <!-- Pre-dialog Loading Spinner Overlay -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpening"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-xs select-none pointer-events-none"
            >
                <div class="flex flex-col items-center gap-3 p-5 rounded-2xl bg-card border border-border/80 shadow-2xl min-w-[210px] text-center">
                    <div class="relative flex items-center justify-center size-12 rounded-xl bg-primary/10 text-primary">
                        <Loader2 class="size-6 animate-spin text-primary" />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-xs font-bold text-foreground">
                            {{ spinnerText }}
                        </p>
                        <p class="text-[10px] text-muted-foreground font-mono">
                            Please wait...
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Controlled DialogRoot (used across all pages with :open) -->
    <DialogRoot
        v-if="isControlled"
        v-bind="delegatedProps"
        :open="actualOpen"
        @update:open="handleUpdateOpen"
    >
        <slot />
    </DialogRoot>

    <!-- Uncontrolled DialogRoot fallback -->
    <DialogRoot v-else v-bind="forwarded">
        <slot />
    </DialogRoot>
</template>
