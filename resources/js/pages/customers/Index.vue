<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CreditCard, DollarSign, Mail, Phone, Plus, Trash2, UserCheck, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    customers?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];

const { success, error } = useToast();

const customerList = computed(() => {
    return props.customers ?? [];
});

const columns = [
    { key: 'name', label: 'Customer Account' },
    { key: 'phone', label: 'Contact Details' },
    { key: 'group', label: 'Tier Group', align: 'center' as const },
    { key: 'creditLimit', label: 'Credit Limit', align: 'right' as const },
    { key: 'currentBalance', label: 'Outstanding Balance', align: 'right' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
];

const newCustomerModal = ref(false);
const payCreditModal = ref(false);
const selectedCust = ref<any | null>(null);
const paymentReceived = ref(0);

const newCust = ref({
    name: '',
    phone: '',
    email: '',
    group: 'Retail',
    credit_limit: 0,
    notes: '',
});

const handleCreateCustomer = () => {
    router.post('/customers', {
        name: newCust.value.name,
        phone: newCust.value.phone,
        email: newCust.value.email,
        group: newCust.value.group,
        credit_limit: newCust.value.credit_limit,
        notes: newCust.value.notes,
    }, {
        onSuccess: () => {
            newCustomerModal.value = false;
            success('Customer Created', `Account for ${newCust.value.name} registered.`);
            newCust.value = {
                name: '',
                phone: '',
                email: '',
                group: 'Retail',
                credit_limit: 0,
                notes: '',
            };
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
    });
};

const openPayModal = (c: any) => {
    selectedCust.value = c;
    paymentReceived.value = c.currentBalance;
    payCreditModal.value = true;
};

const handleRecordCustomerPayment = () => {
    if (!selectedCust.value) return;

    router.post(`/customers/${selectedCust.value.id}/payment`, {
        amount: paymentReceived.value,
    }, {
        onSuccess: () => {
            payCreditModal.value = false;
            success('Payment Received', `Recorded ${formatCurrency(paymentReceived.value)} collected from ${selectedCust.value.name}.`);
        },
        onError: (errs) => error('Payment Failed', Object.values(errs)[0] as string),
    });
};

const handleDeleteCustomer = (c: any) => {
    if (!confirm(`Are you sure you want to remove ${c.name}?`)) return;

    router.delete(`/customers/${c.id}`, {
        onSuccess: () => success('Customer Removed', 'Customer account deleted.'),
        onError: (errs) => error('Error', Object.values(errs)[0] as string),
    });
};
</script>

<template>
    <Head title="Customer Directory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-8 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground">Customer Accounts & Credit</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Manage retail, VIP, wholesale, and corporate customer credit lines and payments.
                    </p>
                </div>

                <Button size="sm" class="h-9 text-xs gap-1.5 bg-primary" @click="newCustomerModal = true">
                    <Plus class="size-3.5" />
                    New Customer Account
                </Button>
            </div>

            <!-- DataTable -->
            <DataTable
                :columns="columns"
                :data="customerList"
                search-placeholder="Search customer, phone, email..."
                :search-keys="['name', 'phone', 'email', 'group']"
            >
                <template #cell-name="{ row }">
                    <div>
                        <div class="font-bold text-foreground">{{ row.name }}</div>
                        <div class="text-[11px] text-muted-foreground">{{ row.email }}</div>
                    </div>
                </template>

                <template #cell-phone="{ row }">
                    <span class="font-mono text-xs">{{ row.phone }}</span>
                </template>

                <template #cell-group="{ row }">
                    <Badge
                        :variant="row.group === 'Wholesale' ? 'info' : row.group === 'VIP' ? 'warning' : 'outline'"
                    >
                        {{ row.group }}
                    </Badge>
                </template>

                <template #cell-creditLimit="{ row }">
                    <span class="font-mono text-muted-foreground">{{ formatCurrency(row.creditLimit) }}</span>
                </template>

                <template #cell-currentBalance="{ row }">
                    <span
                        :class="[
                            'font-mono font-bold',
                            row.currentBalance > 0 ? 'text-amber-600' : 'text-emerald-600',
                        ]"
                    >
                        {{ formatCurrency(row.currentBalance) }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-1.5">
                        <Button
                            v-if="row.currentBalance > 0"
                            size="sm"
                            class="h-7 text-xs bg-emerald-600 hover:bg-emerald-700"
                            @click="openPayModal(row)"
                        >
                            Collect Payment
                        </Button>
                        <span v-else class="text-xs text-muted-foreground">Clear ✓</span>

                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 text-muted-foreground hover:text-destructive"
                            @click="handleDeleteCustomer(row)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- New Customer Modal -->
        <Dialog :open="newCustomerModal" @update:open="(v) => (newCustomerModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Register Customer Account</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Full Name / Company Name</Label>
                        <Input v-model="newCust.name" placeholder="e.g. Ochieng Supermarket" class="mt-1 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Phone Number</Label>
                            <Input v-model="newCust.phone" placeholder="+254 7XX XXX XXX" class="mt-1 text-xs font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs">Email Address</Label>
                            <Input v-model="newCust.email" type="email" placeholder="client@example.com" class="mt-1 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Tier Group</Label>
                            <select v-model="newCust.group" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option value="Retail">Retail</option>
                                <option value="Wholesale">Wholesale</option>
                                <option value="VIP">VIP</option>
                                <option value="Corporate">Corporate</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-xs">Credit Limit (KES)</Label>
                            <Input v-model.number="newCust.credit_limit" type="number" class="mt-1 text-xs font-mono font-bold" />
                        </div>
                    </div>

                    <div>
                        <Label class="text-xs">Internal Notes</Label>
                        <Input v-model="newCust.notes" placeholder="e.g. Delivery terms or payment schedule" class="mt-1 text-xs" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="newCustomerModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleCreateCustomer">Save Customer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Collect Credit Payment Modal -->
        <Dialog :open="payCreditModal" @update:open="(v) => (payCreditModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Collect Customer Credit Payment</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <p class="font-semibold text-foreground">{{ selectedCust?.name }}</p>
                    <p class="text-muted-foreground">Outstanding Balance: {{ formatCurrency(selectedCust?.currentBalance || 0) }}</p>
                    <div>
                        <Label class="text-xs">Amount Collected (KES)</Label>
                        <Input v-model.number="paymentReceived" type="number" class="mt-1 font-mono font-bold" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="payCreditModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleRecordCustomerPayment">Record Receipt</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
