<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import { useFeatures } from '@/composables/useFeatures';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    returns?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
    { title: 'Returns & Refunds', href: '/sales/returns' },
];

const { isDemo } = useFeatures();
const { success, error, info } = useToast();

const demoReturnRecords = [
    {
        id: 'RET-004',
        receiptNumber: 'REC-2026-904120',
        date: '2026-08-23 09:15',
        customer: 'Walk-in Customer',
        product: 'Broadways White Bread 400g',
        qty: 1,
        refundAmount: 70,
        reason: 'Expired on shelf',
        restock: false,
        status: 'Completed',
    },
    {
        id: 'RET-003',
        receiptNumber: 'REC-2026-904112',
        date: '2026-08-22 15:40',
        customer: 'Grace Muthoni',
        product: 'Bio Yoghurt Strawberry 250ml',
        qty: 2,
        refundAmount: 250,
        reason: 'Wrong flavor selected',
        restock: true,
        status: 'Completed',
    },
];

const returnRecords = ref(props.returns && props.returns.length > 0 ? props.returns : isDemo.value ? demoReturnRecords : []);

const columns = [
    { key: 'id', label: 'Return #' },
    { key: 'receiptNumber', label: 'Original Receipt' },
    { key: 'customer', label: 'Customer' },
    { key: 'product', label: 'Returned Product' },
    { key: 'refundAmount', label: 'Refund Amount', align: 'right' as const },
    { key: 'reason', label: 'Reason' },
    { key: 'status', label: 'Status', align: 'center' as const },
];

// New Return Modal
const newReturnOpen = ref(false);
const receiptSearch = ref('');
const returnReason = ref('Customer Change of Mind');
const returnQty = ref(1);
const restockItem = ref(true);

const handleProcessReturn = () => {
    if (!receiptSearch.value) {
        error('Receipt Required', 'Please enter a valid original receipt number.');
        return;
    }

    returnRecords.value.unshift({
        id: `RET-00${returnRecords.value.length + 1}`,
        receiptNumber: receiptSearch.value.toUpperCase(),
        date: new Date().toISOString().replace('T', ' ').substring(0, 16),
        customer: 'Customer',
        product: `Item from #${receiptSearch.value.toUpperCase()}`,
        qty: returnQty.value,
        refundAmount: returnQty.value * 100,
        reason: returnReason.value,
        restock: restockItem.value,
        status: 'Completed',
    });

    newReturnOpen.value = false;
    receiptSearch.value = '';
    success('Return Processed', 'Refund calculated and inventory updated according to restock policy.');
};
</script>

<template>
    <Head title="Sales Returns & Refunds" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground">Sales Returns & Refunds</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Accept returned items with receipt verification, calculate cash/M-Pesa refunds, and restock inventory.
                    </p>
                </div>

                <Button size="sm" class="h-9 gap-1.5 bg-amber-600 text-xs font-bold text-white hover:bg-amber-700" @click="newReturnOpen = true">
                    <RotateCcw class="size-3.5" />
                    Process Return Ticket
                </Button>
            </div>

            <!-- Return Records DataTable -->
            <DataTable :columns="columns" :data="returnRecords" search-placeholder="Search return # or original receipt...">
                <template #cell-id="{ row }">
                    <span class="font-mono font-bold text-primary">{{ row.id }}</span>
                    <div class="text-[10px] text-muted-foreground">{{ row.date }}</div>
                </template>

                <template #cell-receiptNumber="{ row }">
                    <span class="font-mono font-bold text-foreground">{{ row.receiptNumber }}</span>
                </template>

                <template #cell-product="{ row }">
                    <div class="font-semibold">{{ row.qty }}x {{ row.product }}</div>
                    <div class="text-[10px] text-muted-foreground">Restocked: {{ row.restock ? 'Yes' : 'No (Written off)' }}</div>
                </template>

                <template #cell-refundAmount="{ row }">
                    <span class="font-mono font-bold text-rose-600">{{ formatCurrency(row.refundAmount) }}</span>
                </template>

                <template #cell-status="{ row }">
                    <Badge variant="success">{{ row.status }}</Badge>
                </template>
            </DataTable>
        </div>

        <!-- New Return Modal -->
        <Dialog :open="newReturnOpen" @update:open="(v) => (newReturnOpen = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Process Sales Return & Refund</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Original Receipt #</Label>
                        <Input v-model="receiptSearch" placeholder="e.g. REC-2026-904128" class="mt-1 font-mono uppercase" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Returned Quantity</Label>
                            <Input v-model.number="returnQty" type="number" min="1" class="mt-1 font-mono font-bold" />
                        </div>
                        <div>
                            <Label class="text-xs">Return Reason</Label>
                            <select v-model="returnReason" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option value="Customer Change of Mind">Customer Change of Mind</option>
                                <option value="Defective / Damaged">Defective / Damaged</option>
                                <option value="Expired on shelf">Expired on shelf</option>
                                <option value="Wrong item supplied">Wrong item supplied</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input id="restock-cb" v-model="restockItem" type="checkbox" class="rounded border-input text-primary" />
                        <label for="restock-cb" class="cursor-pointer text-xs text-foreground"> Return item back into available store stock </label>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="newReturnOpen = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleProcessReturn">Issue Refund</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
