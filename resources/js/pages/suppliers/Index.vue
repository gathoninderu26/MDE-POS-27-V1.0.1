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
import { Building, DollarSign, Mail, Phone, Plus, Trash2, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    suppliers?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Suppliers', href: '/suppliers' },
];

const { success, error } = useToast();

const supplierList = computed(() => {
    return props.suppliers ?? [];
});

const columns = [
    { key: 'name', label: 'Supplier & Contact' },
    { key: 'phone', label: 'Phone & Email' },
    { key: 'totalPurchases', label: 'Total Purchases', align: 'right' as const },
    { key: 'outstandingBalance', label: 'Outstanding Payables', align: 'right' as const },
    { key: 'status', label: 'Status', align: 'center' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
];

const newSupplierModal = ref(false);
const paySupplierModal = ref(false);
const selectedSupp = ref<any>(null);
const payAmount = ref(0);

const newSupp = ref({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
});

const isSavingSupplier = ref(false);
const isPayingSupplier = ref(false);

const handleCreateSupplier = () => {
    isSavingSupplier.value = true;
    router.post('/suppliers', {
        name: newSupp.value.name,
        contact_person: newSupp.value.contact_person,
        phone: newSupp.value.phone,
        email: newSupp.value.email,
        address: newSupp.value.address,
        notes: newSupp.value.notes,
    }, {
        onSuccess: () => {
            newSupplierModal.value = false;
            success('Supplier Created', `Supplier ${newSupp.value.name} registered.`);
            newSupp.value = {
                name: '',
                contact_person: '',
                phone: '',
                email: '',
                address: '',
                notes: '',
            };
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (isSavingSupplier.value = false),
    });
};

const openPayModal = (s: any) => {
    selectedSupp.value = s;
    payAmount.value = s.outstandingBalance;
    paySupplierModal.value = true;
};

const handleRecordPayment = () => {
    if (!selectedSupp.value) return;

    isPayingSupplier.value = true;
    router.post(`/suppliers/${selectedSupp.value.id}/payment`, {
        amount: payAmount.value,
    }, {
        onSuccess: () => {
            paySupplierModal.value = false;
            success('Payment Recorded', `Recorded ${formatCurrency(payAmount.value)} paid to ${selectedSupp.value.name}.`);
        },
        onError: (errs) => error('Payment Failed', Object.values(errs)[0] as string),
        onFinish: () => (isPayingSupplier.value = false),
    });
};

const handleDeleteSupplier = (s: any) => {
    if (!confirm(`Are you sure you want to remove supplier ${s.name}?`)) return;

    router.delete(`/suppliers/${s.id}`, {
        onSuccess: () => success('Supplier Removed', 'Supplier deleted successfully.'),
        onError: (errs) => error('Error', Object.values(errs)[0] as string),
    });
};
</script>

<template>
    <Head title="Supplier Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-8 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground">Vendors & Wholesale Suppliers</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Directory of FMCG distributors, millers, bakeries, pharmaceutical labs, and trade payables.
                    </p>
                </div>

                <Button size="sm" class="h-9 text-xs gap-1.5 bg-primary" @click="newSupplierModal = true">
                    <Plus class="size-3.5" />
                    New Supplier
                </Button>
            </div>

            <!-- DataTable -->
            <DataTable
                :columns="columns"
                :data="supplierList"
                search-placeholder="Search supplier, contact, email..."
                :search-keys="['name', 'contactPerson', 'phone', 'email']"
            >
                <template #cell-name="{ row }">
                    <div>
                        <div class="font-bold text-foreground">{{ row.name }}</div>
                        <div class="text-[11px] text-muted-foreground flex items-center gap-1">
                            <Truck class="size-3" />
                            <span>{{ row.contactPerson }}</span>
                        </div>
                    </div>
                </template>

                <template #cell-phone="{ row }">
                    <div>
                        <span class="font-mono text-xs">{{ row.phone }}</span>
                        <div v-if="row.email" class="text-[11px] text-muted-foreground">{{ row.email }}</div>
                    </div>
                </template>

                <template #cell-totalPurchases="{ row }">
                    <span class="font-mono text-muted-foreground">{{ formatCurrency(row.totalPurchases) }}</span>
                </template>

                <template #cell-outstandingBalance="{ row }">
                    <span
                        :class="[
                            'font-mono font-bold',
                            row.outstandingBalance > 0 ? 'text-rose-600' : 'text-emerald-600',
                        ]"
                    >
                        {{ formatCurrency(row.outstandingBalance) }}
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <Badge :variant="row.status === 'Active' ? 'default' : 'secondary'">
                        {{ row.status }}
                    </Badge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-1.5">
                        <Button
                            v-if="row.outstandingBalance > 0"
                            size="sm"
                            class="h-7 text-xs bg-primary"
                            @click="openPayModal(row)"
                        >
                            Record Payment
                        </Button>
                        <span v-else class="text-xs text-muted-foreground">Settled ✓</span>

                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 text-muted-foreground hover:text-destructive"
                            @click="handleDeleteSupplier(row)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- New Supplier Modal -->
        <Dialog :open="newSupplierModal" @update:open="(v) => (newSupplierModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Register Vendor / Supplier</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Company Name</Label>
                        <Input v-model="newSupp.name" placeholder="e.g. Brookside Dairy Ltd" class="mt-1 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Contact Person</Label>
                            <Input v-model="newSupp.contact_person" placeholder="e.g. Martin Ndungu" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <Label class="text-xs">Phone Number</Label>
                            <Input v-model="newSupp.phone" placeholder="+254 7XX XXX XXX" class="mt-1 text-xs font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Email Address</Label>
                            <Input v-model="newSupp.email" type="email" placeholder="orders@supplier.co.ke" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <Label class="text-xs">Physical Address / Depot</Label>
                            <Input v-model="newSupp.address" placeholder="e.g. Industrial Area" class="mt-1 text-xs" />
                        </div>
                    </div>

                    <div>
                        <Label class="text-xs">Notes</Label>
                        <Input v-model="newSupp.notes" placeholder="Credit terms or delivery window" class="mt-1 text-xs" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="newSupplierModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isSavingSupplier" @click="handleCreateSupplier">Save Supplier</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Record Supplier Payment Modal -->
        <Dialog :open="paySupplierModal" @update:open="(v) => (paySupplierModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Record Supplier Disbursement</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <p class="font-semibold text-foreground">{{ selectedSupp?.name }}</p>
                    <p class="text-muted-foreground">Outstanding Payables: {{ formatCurrency(selectedSupp?.outstandingBalance || 0) }}</p>
                    <div>
                        <Label class="text-xs">Disbursed Amount (KES)</Label>
                        <Input v-model.number="payAmount" type="number" class="mt-1 font-mono font-bold" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="paySupplierModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isPayingSupplier" @click="handleRecordPayment">Record Disbursement</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
