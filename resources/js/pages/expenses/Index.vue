<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency, formatDateOnly } from '@/composables/useCurrency';
import { useFeatures } from '@/composables/useFeatures';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CircleDollarSign, Plus, Receipt } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    expenses?: any[];
    branches?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];

const { isMultiBranch } = useFeatures();
const { success, error } = useToast();

const expensesList = computed(() => {
    if (props.expenses && props.expenses.length > 0) {
        return props.expenses.map(e => ({
            id: `EXP-${e.id}`,
            date: e.date,
            category: e.category,
            description: e.description || e.title,
            amount: e.amount,
            paymentMethod: e.payment_method,
            branch: e.branch,
            recordedBy: e.logged_by,
            status: 'Approved',
        }));
    }
    return [];
});

const columns = computed(() => [
    { key: 'id', label: 'Expense #' },
    { key: 'date', label: 'Date' },
    { key: 'category', label: 'Category' },
    { key: 'description', label: 'Description' },
    { key: 'amount', label: 'Amount (KES)', align: 'right' as const },
    { key: 'paymentMethod', label: 'Payment Method' },
    ...(isMultiBranch.value ? [{ key: 'branch', label: 'Branch' }] : []),
    { key: 'recordedBy', label: 'Recorded By' },
]);

const newExpenseModal = ref(false);
const newExp = ref({
    title: '',
    category: 'Electricity / Utilities',
    description: '',
    amount: 1000,
    paymentMethod: 'M-Pesa Till',
    branch_id: null as number | null,
});

const handleRecordExpense = () => {
    router.post('/expenses', {
        title: newExp.value.title || newExp.value.description || newExp.value.category,
        category: newExp.value.category,
        amount: newExp.value.amount,
        date: new Date().toISOString().substring(0, 10),
        payment_method: newExp.value.paymentMethod,
        branch_id: newExp.value.branch_id,
        description: newExp.value.description,
    }, {
        onSuccess: () => {
            newExpenseModal.value = false;
            success('Expense Recorded', `${formatCurrency(newExp.value.amount)} recorded.`);
            newExp.value = {
                title: '',
                category: 'Electricity / Utilities',
                description: '',
                amount: 1000,
                paymentMethod: 'M-Pesa Till',
                branch_id: null,
            };
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
    });
};
</script>

<template>
    <Head title="Expense Tracking" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground">Expenses & Petty Cash</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Record branch operating overheads (rent, electricity, salaries, transport, maintenance).
                    </p>
                </div>

                <Button size="sm" class="h-9 text-xs gap-1.5 bg-primary" @click="newExpenseModal = true">
                    <Plus class="size-3.5" />
                    Record Expense
                </Button>
            </div>

            <!-- DataTable -->
            <DataTable
                :columns="columns"
                :data="expensesList"
                search-placeholder="Search expense, category, description..."
                :search-keys="['id', 'category', 'description', 'recordedBy', 'branch']"
            >
                <template #cell-id="{ row }">
                    <span class="font-mono font-bold text-primary">{{ row.id }}</span>
                </template>

                <template #cell-category="{ row }">
                    <span class="font-semibold">{{ row.category }}</span>
                </template>

                <template #cell-amount="{ row }">
                    <span class="font-mono font-bold text-rose-600">{{ formatCurrency(row.amount) }}</span>
                </template>

                <template #cell-paymentMethod="{ row }">
                    <Badge variant="outline">{{ row.paymentMethod }}</Badge>
                </template>
            </DataTable>
        </div>

        <!-- New Expense Modal -->
        <Dialog :open="newExpenseModal" @update:open="(v) => (newExpenseModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Record Store Expense</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Expense Category</Label>
                        <select v-model="newExp.category" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option value="Rent">Rent</option>
                            <option value="Electricity / Utilities">Electricity / Utilities</option>
                            <option value="Transport & Logistics">Transport & Logistics</option>
                            <option value="Salaries & Wages">Salaries & Wages</option>
                            <option value="Maintenance">Maintenance & Repairs</option>
                            <option value="Marketing">Marketing & Promotions</option>
                            <option value="Other Overheads">Other Overheads</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-xs">Description / Reason</Label>
                        <Input v-model="newExp.description" placeholder="e.g. KPLC token for freezers" class="mt-1 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Amount (KES)</Label>
                            <Input v-model.number="newExp.amount" type="number" class="mt-1 text-xs font-mono font-bold" />
                        </div>
                        <div>
                            <Label class="text-xs">Payment Method</Label>
                            <select v-model="newExp.paymentMethod" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option value="Petty Cash">Petty Cash</option>
                                <option value="M-Pesa Till">M-Pesa Till</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="newExpenseModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleRecordExpense">Record Expense</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
