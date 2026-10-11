<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ArrowRight, Boxes, Compass, Info, Scale } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

interface Props {
    open: boolean;
    tenantName?: string;
    totalSKUs?: number;
    lowStockCount?: number;
    totalValuationFormatted?: string;
    durationMs?: number;
}

const props = withDefaults(defineProps<Props>(), {
    open: true,
    tenantName: 'Inventory & Stock Control',
    totalSKUs: 0,
    lowStockCount: 0,
    totalValuationFormatted: '—',
    durationMs: 4000,
});

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'finish'): void;
}>();

const progress = ref(15);
const isComplete = ref(false);
let interval: any = null;

const startLoading = () => {
    progress.value = 15;
    isComplete.value = false;

    const startTime = Date.now();
    const duration = props.durationMs;

    interval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const pct = Math.min(100, Math.round((elapsed / duration) * 100));
        progress.value = Math.max(15, pct);

        if (pct >= 100) {
            isComplete.value = true;
            clearInterval(interval);
        }
    }, 60);
};

const handleContinue = () => {
    if (interval) clearInterval(interval);
    emit('update:open', false);
    emit('finish');
};

onMounted(() => {
    if (props.open) {
        startLoading();
    }
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-md">
            <div class="relative w-full max-w-md space-y-4 rounded-xl border border-border bg-card p-6 text-center shadow-xl">
                <!-- Clean Stock Icon -->
                <div class="mx-auto flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Boxes class="size-6" />
                </div>

                <!-- Title -->
                <div>
                    <h3 class="text-base font-bold text-foreground">Enterprise Stock & Valuation</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ tenantName }} &bull; Live Ledger Audit</p>
                </div>

                <!-- Stock Metrics Summary Grid -->
                <div class="grid grid-cols-2 gap-2 rounded-lg border border-border/80 bg-muted/40 p-3 text-left text-xs">
                    <div class="space-y-0.5">
                        <span class="flex items-center gap-1 text-[10px] font-medium text-muted-foreground">
                            <Boxes class="size-3 text-primary" /> Tracked SKUs
                        </span>
                        <div class="font-mono font-bold text-foreground">{{ totalSKUs }} SKUs</div>
                    </div>
                    <div class="space-y-0.5">
                        <span class="flex items-center gap-1 text-[10px] font-medium text-muted-foreground">
                            <Scale class="size-3 text-primary" /> Total Valuation
                        </span>
                        <div class="truncate font-mono font-bold text-foreground">{{ totalValuationFormatted }}</div>
                    </div>
                </div>

                <!-- Policy Message Card -->
                <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3.5 text-left text-xs text-amber-950 dark:text-amber-200">
                    <div class="flex items-start gap-2.5">
                        <Info class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p class="leading-relaxed">
                            Perform <strong>physical stock takes</strong> with wireless barcode scanners, reconcile inventory variances, and log
                            shrinkage adjustments.
                        </p>
                    </div>
                </div>

                <!-- Clean Progress Bar -->
                <div class="h-1 w-full overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary transition-all duration-150" :style="{ width: `${progress}%` }"></div>
                </div>

                <!-- Action Button: Done & Open Page Guide -->
                <Button
                    type="button"
                    class="h-9 w-full gap-1.5 bg-primary text-xs font-semibold text-primary-foreground hover:bg-primary/90"
                    @click="handleContinue"
                >
                    <Compass class="size-3.5" />
                    <span>Done — Open Page Guide</span>
                    <ArrowRight class="ml-auto size-3.5" />
                </Button>
            </div>
        </div>
    </Transition>
</template>
