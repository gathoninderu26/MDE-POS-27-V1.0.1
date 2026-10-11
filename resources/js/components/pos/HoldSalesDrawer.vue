<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { HeldSale } from '@/composables/useCart';
import { formatCurrency, formatDateTime } from '@/composables/useCurrency';
import { Clock, Play, User } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    heldSales: HeldSale[];
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'resume', id: string): void;
    (e: 'delete', id: string): void;
}>();
</script>

<template>
    <Sheet :open="open" spinnerText="Opening Held Orders..." @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="flex w-full flex-col p-6 sm:max-w-md">
            <SheetHeader class="border-b border-border/70 pb-4">
                <SheetTitle class="flex items-center gap-2 text-base font-bold">
                    <Clock class="size-4 text-primary" />
                    Held Orders / Saved Carts ({{ heldSales.length }})
                </SheetTitle>
                <SheetDescription class="text-xs"> Resume any saved order when the customer returns to checkout. </SheetDescription>
            </SheetHeader>

            <div class="flex-1 space-y-3 overflow-y-auto py-4">
                <div
                    v-for="sale in heldSales"
                    :key="sale.id"
                    class="space-y-3 rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/50"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-bold text-primary">{{ sale.id }}</span>
                        <Badge variant="outline">{{ formatDateTime(sale.heldAt) }}</Badge>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                            <User class="size-3.5" />
                            <span>{{ sale.customer?.name || 'Walk-in Customer' }}</span>
                        </div>
                        <span class="font-mono font-bold text-foreground">{{ formatCurrency(sale.totalAmount) }}</span>
                    </div>

                    <div class="rounded-lg bg-muted/30 p-2 text-xs text-muted-foreground">
                        <span class="font-medium">{{ sale.items.length }} line items: </span>
                        <span class="truncate">{{ sale.items.map((i) => `${i.quantity}x ${i.product.name}`).join(', ') }}</span>
                    </div>

                    <div v-if="sale.note" class="text-[11px] italic text-amber-600 dark:text-amber-400">Note: "{{ sale.note }}"</div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <Button size="sm" class="h-8 gap-1.5 bg-primary text-xs" @click="emit('resume', sale.id)">
                            <Play class="size-3.5" />
                            Resume Order
                        </Button>
                    </div>
                </div>

                <div v-if="heldSales.length === 0" class="py-16 text-center text-xs text-muted-foreground">
                    <p class="font-medium">No held carts currently</p>
                    <p class="mt-1 text-[11px]">Click "Hold Sale" in the POS register to save active cart.</p>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
