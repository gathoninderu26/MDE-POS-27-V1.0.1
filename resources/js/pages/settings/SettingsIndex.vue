<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBusinessTerms } from '@/composables/useBusinessTerms';
import type { BusinessType } from '@/composables/useSuperAdmin';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    Check,
    CreditCard,
    DollarSign,
    Package,
    Pencil,
    Pill,
    Plus,
    Printer,
    Save,
    Settings,
    Shield,
    Smartphone,
    Store,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Truck,
    Utensils,
    Wallet,
    Wrench,
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    tenant?: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/settings' },
];

const { currentTenant, setTenantBusinessType } = useTenant();
const { terms } = useBusinessTerms();
const { success, error } = useToast();

const activeTab = ref<'business' | 'payments' | 'pos' | 'inventory'>('business');

const availableBusinessTypes: { type: BusinessType; label: string; desc: string }[] = [
    { type: 'Supermarket', label: 'Supermarket & Grocery', desc: 'Fast barcode scanning, multi-tier pricing, KRA eTIMS, wholesale/retail switcher' },
    { type: 'Pharmacy', label: 'Pharmacy & Chemist', desc: 'Drug generic/brand, batch & expiry alerts, Rx prescription numbers, patient dosage' },
    { type: 'Hardware', label: 'Hardware & Building Supplies', desc: 'Material specs, gauge/dimensions, contractor accounts, delivery site dispatches' },
    { type: 'Restaurant', label: 'Restaurant, Cafe & Lounge', desc: 'Table management, KOT kitchen tickets, dish modifiers, split billing' },
    { type: 'Electronics', label: 'Electronics & Mobiles', desc: 'IMEI/Serial number tracking, manufacturer warranty periods, repair job tracking' },
    { type: 'Wholesale', label: 'Wholesale & Distribution', desc: 'B2B bulk cartons & bales, credit accounts, parked purchase orders' },
    { type: 'Clothing & Apparel', label: 'Clothing & Apparel', desc: 'Size/color variants, garment barcodes, loyalty reward points' },
    { type: 'Bakery', label: 'Bakery & Confectionery', desc: 'Freshness batches, daily production sheets, custom celebration cakes' },
    { type: 'Petrol Station', label: 'Petrol Station', desc: 'Pump & nozzle selectors, start/end meter tracking, fuel grades, fleet cards' },
    { type: 'General Trade', label: 'General Trade & Retail', desc: 'Cash-and-carry general goods, standard VAT, rapid checkout' },
];

// Tenant Configurable Payment Methods
const defaultPaymentMethods = [
    { id: '1', name: 'Cash', type: 'Cash', enabled: true, feePercent: 0, accountRef: 'Store Till Drawer' },
    { id: '2', name: 'M-Pesa Buy Goods Till', type: 'Mobile Money', enabled: true, feePercent: 0, accountRef: 'Till # 523910' },
    { id: '3', name: 'M-Pesa Paybill Express', type: 'Mobile Money', enabled: true, feePercent: 0, accountRef: 'Paybill # 247247 Acc # 01102938' },
    { id: '4', name: 'Visa & Mastercard Debit/Credit', type: 'Card', enabled: true, feePercent: 1.5, accountRef: 'PDQ Terminal # 8901' },
    { id: '5', name: 'Bank Wire / RTGS / Pesalink', type: 'Bank', enabled: true, feePercent: 0, accountRef: 'KCB Bank Acc 1122334455' },
    { id: '6', name: 'Customer Credit Account', type: 'Credit', enabled: true, feePercent: 0, accountRef: 'Ledger 102' },
];

const paymentMethods = ref<any[]>(
    props.tenant?.settings?.payment_methods && props.tenant.settings.payment_methods.length > 0
        ? props.tenant.settings.payment_methods
        : defaultPaymentMethods
);

const newMethodModal = ref(false);
const newMethod = ref({
    name: '',
    type: 'Mobile Money',
    accountRef: '',
    feePercent: 0,
});

const editingMethodModal = ref(false);
const editingMethod = ref<any>(null);

const getMethodDetailsSummary = (pm: any) => {
    if (pm.details?.till_number) return `Till # ${pm.details.till_number} (${pm.details.store_name || currentTenant.value?.name || 'Store'})`;
    if (pm.details?.business_number) return `Paybill # ${pm.details.business_number} Acc # ${pm.details.account_number || 'STORE'}`;
    if (pm.details?.terminal_id) return `PDQ ${pm.details.terminal_id} (${pm.details.bank_name || 'Bank'})`;
    if (pm.details?.bank_name) return `${pm.details.bank_name} Acc # ${pm.details.account_number || ''}`;
    if (pm.accountRef) return pm.accountRef;
    if (pm.type === 'Cash') return pm.details?.drawer_label || 'Store Cash Drawer';
    return 'Active Channel';
};

const openEditMethod = (pm: any) => {
    editingMethod.value = JSON.parse(JSON.stringify(pm));
    if (!editingMethod.value.details) {
        editingMethod.value.details = {};
    }
    editingMethodModal.value = true;
};

const handleSaveEditedMethod = () => {
    if (!editingMethod.value) return;
    const index = paymentMethods.value.findIndex(p => p.id === editingMethod.value.id);
    if (index !== -1) {
        paymentMethods.value[index] = { ...editingMethod.value };
    }
    editingMethodModal.value = false;
    editingMethod.value = null;
    success('Channel Updated', 'Payment method updated. Click "Save Changes" to save to store database.');
};

const handleDeleteMethod = (pmId: string) => {
    if (paymentMethods.value.length <= 1) {
        error('Cannot Remove', 'You must maintain at least one payment method.');
        return;
    }
    paymentMethods.value = paymentMethods.value.filter(p => p.id !== pmId);
    success('Removed', 'Payment method removed. Click "Save Changes" to persist.');
};

const handleAddMethod = () => {
    if (!newMethod.value.name) return;
    paymentMethods.value.push({
        id: `pm-${Date.now()}`,
        code: newMethod.value.name.toLowerCase().replace(/[^a-z0-9]/g, '_'),
        name: newMethod.value.name,
        type: newMethod.value.type,
        accountRef: newMethod.value.accountRef,
        details: {
            account_number: newMethod.value.accountRef,
        },
        feePercent: newMethod.value.feePercent,
        enabled: true,
    });
    newMethodModal.value = false;
    newMethod.value = { name: '', type: 'Mobile Money', accountRef: '', feePercent: 0 };
    success('Payment Method Added', 'New payment channel added. Click "Save Changes" to persist.');
};

const handleSelectBusinessType = (type: BusinessType) => {
    setTenantBusinessType(type);
    success('Business Vertical Updated', `Store adapted to ${type} configuration and vocabulary.`);
};

const isSaving = ref(false);

const handleSaveSettings = () => {
    isSaving.value = true;
    router.patch('/settings', {
        name: currentTenant.value.name,
        kra_pin: currentTenant.value.kraPin || null,
        email: currentTenant.value.email,
        phone: currentTenant.value.phone || null,
        address: currentTenant.value.address || null,
        business_type: currentTenant.value.businessType,
        settings: {
            payment_methods: paymentMethods.value,
        },
    }, {
        onSuccess: () => {
            isSaving.value = false;
            success('Settings Saved', 'Store configuration and payment preferences persisted to database.');
        },
        onError: (errs) => {
            isSaving.value = false;
            error('Save Failed', Object.values(errs)[0] as string);
        },
    });
};
</script>

<template>
    <Head title="System Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-foreground">Store Profile & Vertical Settings</h2>
                        <Badge variant="outline" class="font-bold text-xs uppercase bg-primary/10 text-primary border-primary/20">
                            {{ terms.badgeLabel }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Configure business vertical, tax compliance, payment channels, receipt notes, and POS terminal rules.
                    </p>
                </div>

                <Button size="sm" class="h-9 text-xs gap-1.5 bg-primary" @click="handleSaveSettings">
                    <Save class="size-3.5" />
                    Save Changes
                </Button>
            </div>

            <!-- Tab Buttons -->
            <div class="flex rounded-xl border border-border/80 p-1 bg-card w-fit text-xs font-semibold overflow-x-auto max-w-full">
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all whitespace-nowrap',
                        activeTab === 'business' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'business'"
                >
                    Business Profile & Vertical
                </button>
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all whitespace-nowrap',
                        activeTab === 'payments' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'payments'"
                >
                    Payment Methods ({{ paymentMethods.length }})
                </button>
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all whitespace-nowrap',
                        activeTab === 'pos' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'pos'"
                >
                    POS & Receipts
                </button>
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all whitespace-nowrap',
                        activeTab === 'inventory' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'inventory'"
                >
                    Inventory Rules
                </button>
            </div>

            <!-- Tab 1: Business Profile & Business Vertical Switcher -->
            <div v-if="activeTab === 'business'" class="space-y-6">
                <!-- Business Vertical Selector Card -->
                <Card class="border-primary/30 bg-primary/5">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-bold flex items-center gap-2 text-foreground">
                            <Store class="size-4 text-primary" />
                            Business Vertical & Industry Preset
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Selecting an industry preset dynamically morphs the POS language (e.g. Products vs Medications vs Materials vs Menu Items), cart fields, and receipt layout.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="b in availableBusinessTypes"
                                :key="b.type"
                                :class="[
                                    'p-3.5 rounded-xl border transition-all cursor-pointer select-none space-y-1.5',
                                    currentTenant.businessType === b.type
                                        ? 'bg-card border-primary ring-2 ring-primary/20 shadow-sm'
                                        : 'bg-card/70 border-border/70 hover:border-primary/40 hover:bg-card',
                                ]"
                                @click="handleSelectBusinessType(b.type)"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Pill v-if="b.type === 'Pharmacy'" class="size-4 text-teal-600" />
                                        <Wrench v-else-if="b.type === 'Hardware'" class="size-4 text-amber-600" />
                                        <Utensils v-else-if="b.type === 'Restaurant'" class="size-4 text-orange-600" />
                                        <Smartphone v-else-if="b.type === 'Electronics'" class="size-4 text-violet-600" />
                                        <Truck v-else-if="b.type === 'Wholesale'" class="size-4 text-blue-600" />
                                        <Package v-else class="size-4 text-emerald-600" />
                                        <span class="font-bold text-xs text-foreground">{{ b.label }}</span>
                                    </div>
                                    <Check v-if="currentTenant.businessType === b.type" class="size-4 text-primary font-bold" />
                                </div>
                                <p class="text-[11px] text-muted-foreground leading-relaxed">{{ b.desc }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Organization & Tax Credentials -->
                <Card class="border-border/80">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-bold">Organization & Tax Profile</CardTitle>
                        <CardDescription class="text-xs">Official credentials for receipts, invoices, and KRA eTIMS tax compliance.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <Label class="text-xs">Business Legal Name</Label>
                                <Input v-model="currentTenant.name" class="mt-1 text-xs font-semibold" />
                            </div>
                            <div>
                                <Label class="text-xs">KRA Tax PIN</Label>
                                <Input v-model="currentTenant.kraPin" class="mt-1 text-xs font-mono" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <Label class="text-xs">Primary Telephone</Label>
                                <Input v-model="currentTenant.phone" class="mt-1 text-xs" />
                            </div>
                            <div>
                                <Label class="text-xs">Official Email</Label>
                                <Input v-model="currentTenant.email" class="mt-1 text-xs" />
                            </div>
                        </div>

                        <div>
                            <Label class="text-xs">Physical Address / Headquarters</Label>
                            <Input v-model="currentTenant.address" class="mt-1 text-xs" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <Label class="text-xs">Operating Currency</Label>
                                <Input v-model="currentTenant.currency" disabled class="mt-1 text-xs font-mono font-bold bg-muted" />
                            </div>
                            <div>
                                <Label class="text-xs">Standard VAT Tax Rate (%)</Label>
                                <Input value="16" type="number" class="mt-1 text-xs font-mono" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tab 2: Tenant-Configurable Payment Methods -->
            <div v-if="activeTab === 'payments'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-foreground">Configurable Payment Methods</h3>
                        <p class="text-xs text-muted-foreground">Enable, disable, or add custom merchant payment channels.</p>
                    </div>
                    <Button size="sm" class="h-8 text-xs gap-1.5 bg-primary" @click="newMethodModal = true">
                        <Plus class="size-3.5" />
                        Add Payment Method
                    </Button>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="pm in paymentMethods"
                        :key="pm.id"
                        class="flex items-center justify-between p-4 rounded-xl border border-border/80 bg-card hover:border-primary/50 transition-all text-xs"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <component
                                    :is="
                                        pm.type === 'Cash' ? Banknote : pm.type === 'Mobile Money' ? Smartphone : pm.type === 'Card' ? CreditCard : pm.type === 'Bank' ? Building2 : Wallet
                                    "
                                    class="size-4"
                                />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-foreground">{{ pm.name }}</span>
                                    <Badge variant="outline" class="text-[10px]">{{ pm.type }}</Badge>
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-0.5">{{ getMethodDetailsSummary(pm) }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div v-if="pm.feePercent > 0 || pm.fee_percent > 0" class="text-right text-[11px] text-muted-foreground font-mono mr-1">
                                Fee: {{ pm.feePercent || pm.fee_percent }}%
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 px-2.5 text-xs gap-1 border-border/80"
                                @click="openEditMethod(pm)"
                            >
                                <Pencil class="size-3" />
                                Edit
                            </Button>
                            <button
                                type="button"
                                :class="[
                                    'px-3 py-1 rounded-full text-xs font-semibold border transition-all',
                                    pm.enabled ? 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30' : 'bg-muted text-muted-foreground',
                                ]"
                                @click="pm.enabled = !pm.enabled"
                            >
                                {{ pm.enabled ? 'Enabled' : 'Disabled' }}
                            </button>
                            <Button
                                v-if="pm.type !== 'Cash'"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-muted-foreground hover:text-rose-600"
                                title="Delete Method"
                                @click="handleDeleteMethod(pm.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: POS & Thermal Receipts -->
            <div v-if="activeTab === 'pos'" class="space-y-4">
                <Card class="border-border/80">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-bold">POS Terminal & Receipt Preferences</CardTitle>
                        <CardDescription class="text-xs">Thermal receipt headers, footers, barcode printing, and audio triggers.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-xs">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 rounded-lg border bg-muted/20">
                                <div>
                                    <span class="font-bold text-foreground">Play Sound on Barcode Scan</span>
                                    <p class="text-[11px] text-muted-foreground">Audio confirmation beep on valid item scan</p>
                                </div>
                                <input type="checkbox" checked class="rounded border-input text-primary focus:ring-primary size-4" />
                            </div>

                            <div class="flex items-center justify-between p-3 rounded-lg border bg-muted/20">
                                <div>
                                    <span class="font-bold text-foreground">KRA eTIMS QR Code Auto-Print</span>
                                    <p class="text-[11px] text-muted-foreground">Attach fiscal signature QR code on thermal receipts</p>
                                </div>
                                <input type="checkbox" checked class="rounded border-input text-primary focus:ring-primary size-4" />
                            </div>

                            <div class="flex items-center justify-between p-3 rounded-lg border bg-muted/20">
                                <div>
                                    <span class="font-bold text-foreground">Auto-Open Cash Drawer on Cash Tender</span>
                                    <p class="text-[11px] text-muted-foreground">Trigger ESC/POS pulse to connected RJ11 cash till</p>
                                </div>
                                <input type="checkbox" checked class="rounded border-input text-primary focus:ring-primary size-4" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tab 4: Inventory Rules -->
            <div v-if="activeTab === 'inventory'" class="space-y-4">
                <Card class="border-border/80">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-bold">Stock Policies & Low Stock Thresholds</CardTitle>
                        <CardDescription class="text-xs">Configure negative stock prevention and automatic reorder alerts.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <Label class="text-xs">Default Low Stock Alert Threshold (Units)</Label>
                                <Input value="10" type="number" class="mt-1 text-xs font-mono font-bold" />
                            </div>
                            <div>
                                <Label class="text-xs">Allow Sale When Stock is Zero (Negative Stock)</Label>
                                <select class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                    <option value="allow">Allow with Warning Badge</option>
                                    <option value="block">Strict Block (Prevent Ring-up)</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Add Payment Method Modal -->
        <Dialog :open="newMethodModal" @update:open="(v) => (newMethodModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Add Payment Channel</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Payment Method Name</Label>
                        <Input v-model="newMethod.name" placeholder="e.g. Stanbic Paybill, Pesalink" class="mt-1 text-xs" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Channel Type</Label>
                            <select v-model="newMethod.type" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option value="Mobile Money">Mobile Money (M-Pesa/Airtel)</option>
                                <option value="Card">Credit/Debit Card (PDQ)</option>
                                <option value="Bank">Bank Wire / EFT</option>
                                <option value="Credit">Store Credit</option>
                                <option value="Cash">Cash Drawer</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-xs">Surcharge Fee (%)</Label>
                            <Input v-model.number="newMethod.feePercent" type="number" step="0.1" class="mt-1 text-xs font-mono" />
                        </div>
                    </div>
                    <div>
                        <Label class="text-xs">Account / Till / Paybill Reference</Label>
                        <Input v-model="newMethod.accountRef" placeholder="e.g. Paybill # 123456 Acc # 001" class="mt-1 text-xs font-mono" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="newMethodModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleAddMethod">Add Channel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Payment Method Modal -->
        <Dialog :open="editingMethodModal" @update:open="(v) => (editingMethodModal = v)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Edit Payment Channel: {{ editingMethod?.name }}</DialogTitle>
                </DialogHeader>
                <div v-if="editingMethod" class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs">Channel Display Name</Label>
                        <Input v-model="editingMethod.name" class="mt-1 text-xs font-semibold" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Channel Type</Label>
                            <Input :value="editingMethod.type" disabled class="mt-1 text-xs bg-muted" />
                        </div>
                        <div>
                            <Label class="text-xs">Surcharge Fee (%)</Label>
                            <Input v-model.number="editingMethod.feePercent" type="number" step="0.1" class="mt-1 text-xs font-mono" />
                        </div>
                    </div>

                    <!-- M-Pesa Buy Goods Till specific fields -->
                    <template v-if="editingMethod.type === 'Mobile Money' && (editingMethod.code === 'mpesa_till' || editingMethod.name?.toLowerCase().includes('till'))">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Safaricom Till Number</Label>
                                <Input v-model="editingMethod.details.till_number" placeholder="523910" class="mt-1 text-xs font-mono font-bold" />
                            </div>
                            <div>
                                <Label class="text-xs">Store Name on Phone</Label>
                                <Input v-model="editingMethod.details.store_name" placeholder="Store Name" class="mt-1 text-xs" />
                            </div>
                        </div>
                    </template>

                    <!-- M-Pesa Paybill specific fields -->
                    <template v-else-if="editingMethod.type === 'Mobile Money' && (editingMethod.code === 'mpesa_paybill' || editingMethod.name?.toLowerCase().includes('paybill'))">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Business Number (Paybill)</Label>
                                <Input v-model="editingMethod.details.business_number" placeholder="247247" class="mt-1 text-xs font-mono font-bold" />
                            </div>
                            <div>
                                <Label class="text-xs">Account Number</Label>
                                <Input v-model="editingMethod.details.account_number" placeholder="STORE" class="mt-1 text-xs font-mono" />
                            </div>
                        </div>
                    </template>

                    <!-- Card specific fields -->
                    <template v-else-if="editingMethod.type === 'Card'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">PDQ Terminal ID</Label>
                                <Input v-model="editingMethod.details.terminal_id" placeholder="EQ-PDQ-8901" class="mt-1 text-xs font-mono" />
                            </div>
                            <div>
                                <Label class="text-xs">Acquiring Bank</Label>
                                <Input v-model="editingMethod.details.bank_name" placeholder="Equity Bank / KCB" class="mt-1 text-xs" />
                            </div>
                        </div>
                    </template>

                    <!-- Bank Transfer specific fields -->
                    <template v-else-if="editingMethod.type === 'Bank'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Bank Name</Label>
                                <Input v-model="editingMethod.details.bank_name" placeholder="KCB Bank" class="mt-1 text-xs" />
                            </div>
                            <div>
                                <Label class="text-xs">Bank Account Number</Label>
                                <Input v-model="editingMethod.details.account_number" placeholder="1122334455" class="mt-1 text-xs font-mono" />
                            </div>
                        </div>
                    </template>

                    <!-- Cash drawer specific field -->
                    <template v-else-if="editingMethod.type === 'Cash'">
                        <div>
                            <Label class="text-xs">Cash Drawer Reference</Label>
                            <Input v-model="editingMethod.details.drawer_label" placeholder="Store Cash Drawer" class="mt-1 text-xs" />
                        </div>
                    </template>

                    <!-- Generic Account Reference fallback -->
                    <div>
                        <Label class="text-xs">Summary Reference Code</Label>
                        <Input v-model="editingMethod.accountRef" placeholder="e.g. Till # 523910" class="mt-1 text-xs font-mono" />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" @click="editingMethodModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" @click="handleSaveEditedMethod">Save Channel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
