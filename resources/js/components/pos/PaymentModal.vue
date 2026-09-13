<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Customer, PaymentEntry } from '@/composables/useCart';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import { Banknote, Building2, CreditCard, DollarSign, Plus, Smartphone, Trash2, UserCheck, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    totalAmount: number;
    customer: Customer;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'complete', payload: { payments: PaymentEntry[]; tenderedAmount: number }): void;
}>();

const { error, info } = useToast();

const paymentType = ref<'single' | 'split'>('single');
const selectedMethod = ref<'Cash' | 'M-Pesa' | 'Card' | 'Bank' | 'Credit'>('Cash');
const cashTendered = ref<number>(0);
const mpesaPhone = ref<string>('');
const mpesaPrompting = ref<boolean>(false);
const referenceCode = ref<string>('');

// For split payments
const splitEntries = ref<PaymentEntry[]>([]);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            cashTendered.value = props.totalAmount;
            mpesaPhone.value = props.customer?.phone || '07';
            splitEntries.value = [{ method: 'Cash', amount: props.totalAmount }];
        }
    },
);

const singleTotalPaid = computed(() => {
    if (selectedMethod.value === 'Cash') {
        return Number(cashTendered.value) || 0;
    }
    return props.totalAmount;
});

const splitTotalPaid = computed(() => {
    return splitEntries.value.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
});

const remainingToPay = computed(() => {
    const paid = paymentType.value === 'single' ? singleTotalPaid.value : splitTotalPaid.value;
    return Math.max(0, props.totalAmount - paid);
});

const changeToReturn = computed(() => {
    const paid = paymentType.value === 'single' ? singleTotalPaid.value : splitTotalPaid.value;
    return Math.max(0, paid - props.totalAmount);
});

const isPaymentComplete = computed(() => {
    if (paymentType.value === 'single') {
        if (selectedMethod.value === 'Cash') {
            return Number(cashTendered.value) >= props.totalAmount;
        }
        if (selectedMethod.value === 'Credit') {
            if (props.customer.id === 'walk-in') return false;
            return props.customer.creditLimit - props.customer.currentBalance >= props.totalAmount;
        }
        return true;
    } else {
        return splitTotalPaid.value >= props.totalAmount;
    }
});

const addSplitRow = () => {
    const rem = remainingToPay.value;
    splitEntries.value.push({
        method: 'M-Pesa',
        amount: rem > 0 ? rem : 0,
        phone: props.customer?.phone,
    });
};

const removeSplitRow = (idx: number) => {
    splitEntries.value.splice(idx, 1);
};

const setQuickCash = (amount: number) => {
    cashTendered.value = amount;
};

const addQuickCash = (increment: number) => {
    cashTendered.value = (Number(cashTendered.value) || 0) + increment;
};

const simulateMpesaSTK = () => {
    mpesaPrompting.value = true;
    info('STK Push Sent', `M-Pesa payment prompt sent to ${mpesaPhone.value}`);
    setTimeout(() => {
        mpesaPrompting.value = false;
        referenceCode.value = 'QK' + Math.random().toString(36).substring(2, 8).toUpperCase();
        info('M-Pesa Confirmed', `Received ${formatCurrency(props.totalAmount)} from ${mpesaPhone.value}`);
    }, 2000);
};

const handleConfirmPayment = () => {
    if (!isPaymentComplete.value) {
        if (selectedMethod.value === 'Credit' && props.customer.id === 'walk-in') {
            error('Invalid Customer', 'Credit sales require selecting a registered customer account.');
            return;
        }
        error('Insufficient Amount', 'Total payment amount must cover the total bill.');
        return;
    }

    let finalPayments: PaymentEntry[] = [];
    let tendered = 0;

    if (paymentType.value === 'single') {
        if (selectedMethod.value === 'Cash') {
            tendered = Number(cashTendered.value);
            finalPayments = [{ method: 'Cash', amount: props.totalAmount }];
        } else if (selectedMethod.value === 'M-Pesa') {
            tendered = props.totalAmount;
            finalPayments = [{ method: 'M-Pesa', amount: props.totalAmount, phone: mpesaPhone.value, reference: referenceCode.value }];
        } else {
            tendered = props.totalAmount;
            finalPayments = [{ method: selectedMethod.value, amount: props.totalAmount, reference: referenceCode.value }];
        }
    } else {
        tendered = splitTotalPaid.value;
        finalPayments = splitEntries.value.map((e) => ({ ...e, amount: Number(e.amount) }));
    }

    emit('complete', {
        payments: finalPayments,
        tenderedAmount: tendered,
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="w-[95vw] sm:max-w-2xl p-0 max-h-[92vh] flex flex-col overflow-hidden">
            <DialogHeader class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border/70 flex flex-row items-center justify-between bg-muted/20 shrink-0">
                <div class="min-w-0 pr-2">
                    <DialogTitle class="text-base sm:text-lg font-bold">Process Payment</DialogTitle>
                    <p class="text-[11px] sm:text-xs text-muted-foreground truncate">Customer: {{ customer.name }} ({{ customer.group }})</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[10px] sm:text-xs text-muted-foreground uppercase font-semibold">Total Payable</span>
                    <div class="text-xl sm:text-2xl font-black text-primary font-mono">{{ formatCurrency(totalAmount) }}</div>
                </div>
            </DialogHeader>

            <div class="p-4 sm:p-6 space-y-4 sm:space-y-5 overflow-y-auto flex-1">
                <!-- Payment Mode Switcher -->
                <div class="flex rounded-lg border border-border/80 p-1 bg-muted/40 text-xs">
                    <button
                        type="button"
                        :class="[
                            'flex-1 py-1.5 font-semibold rounded-md transition-all',
                            paymentType === 'single' ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="paymentType = 'single'"
                    >
                        Single Payment
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex-1 py-1.5 font-semibold rounded-md transition-all',
                            paymentType === 'split' ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="paymentType = 'split'"
                    >
                        Split Payment (Multiple Methods)
                    </button>
                </div>

                <!-- Single Payment Mode -->
                <div v-if="paymentType === 'single'" class="space-y-4">
                    <!-- Method Selector Buttons -->
                    <div class="grid grid-cols-5 gap-2">
                        <button
                            type="button"
                            :class="[
                                'flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-semibold gap-1.5 transition-all',
                                selectedMethod === 'Cash'
                                    ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20'
                                    : 'border-border/80 hover:bg-muted/40 text-muted-foreground',
                            ]"
                            @click="selectedMethod = 'Cash'"
                        >
                            <Banknote class="size-5" />
                            <span>Cash</span>
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-semibold gap-1.5 transition-all',
                                selectedMethod === 'M-Pesa'
                                    ? 'border-emerald-600 bg-emerald-500/10 text-emerald-600 ring-2 ring-emerald-500/20'
                                    : 'border-border/80 hover:bg-muted/40 text-muted-foreground',
                            ]"
                            @click="selectedMethod = 'M-Pesa'"
                        >
                            <Smartphone class="size-5" />
                            <span>M-Pesa</span>
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-semibold gap-1.5 transition-all',
                                selectedMethod === 'Card'
                                    ? 'border-sky-600 bg-sky-500/10 text-sky-600 ring-2 ring-sky-500/20'
                                    : 'border-border/80 hover:bg-muted/40 text-muted-foreground',
                            ]"
                            @click="selectedMethod = 'Card'"
                        >
                            <CreditCard class="size-5" />
                            <span>Card</span>
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-semibold gap-1.5 transition-all',
                                selectedMethod === 'Bank'
                                    ? 'border-indigo-600 bg-indigo-500/10 text-indigo-600 ring-2 ring-indigo-500/20'
                                    : 'border-border/80 hover:bg-muted/40 text-muted-foreground',
                            ]"
                            @click="selectedMethod = 'Bank'"
                        >
                            <Building2 class="size-5" />
                            <span>Bank</span>
                        </button>

                        <button
                            type="button"
                            :class="[
                                'flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-semibold gap-1.5 transition-all',
                                selectedMethod === 'Credit'
                                    ? 'border-amber-600 bg-amber-500/10 text-amber-600 ring-2 ring-amber-500/20'
                                    : 'border-border/80 hover:bg-muted/40 text-muted-foreground',
                            ]"
                            @click="selectedMethod = 'Credit'"
                        >
                            <Wallet class="size-5" />
                            <span>Credit</span>
                        </button>
                    </div>

                    <!-- Cash Interface with Quick Tenders -->
                    <div v-if="selectedMethod === 'Cash'" class="space-y-3 bg-muted/20 p-4 rounded-xl border border-border/70">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-semibold">Cash Tendered (KES)</Label>
                            <span class="text-xs text-muted-foreground">Quick select denominations:</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <Input
                                v-model.number="cashTendered"
                                type="number"
                                step="10"
                                class="h-12 text-xl font-bold font-mono text-primary bg-background"
                            />
                        </div>

                        <!-- Quick Cash Note Buttons (Kenyan Shillings Notes) -->
                        <div class="flex flex-wrap gap-2 pt-1">
                            <Button variant="outline" size="sm" class="h-8 text-xs font-mono" @click="setQuickCash(totalAmount)">
                                Exact ({{ formatCurrency(totalAmount) }})
                            </Button>
                            <Button variant="outline" size="sm" class="h-8 text-xs font-mono" @click="setQuickCash(Math.ceil(totalAmount / 500) * 500)">
                                {{ formatCurrency(Math.ceil(totalAmount / 500) * 500) }}
                            </Button>
                            <Button variant="outline" size="sm" class="h-8 text-xs font-mono" @click="setQuickCash(Math.ceil(totalAmount / 1000) * 1000)">
                                {{ formatCurrency(Math.ceil(totalAmount / 1000) * 1000) }}
                            </Button>
                            <Button variant="outline" size="sm" class="h-8 text-xs font-mono" @click="addQuickCash(500)">
                                + KES 500
                            </Button>
                            <Button variant="outline" size="sm" class="h-8 text-xs font-mono" @click="addQuickCash(1000)">
                                + KES 1,000
                            </Button>
                        </div>
                    </div>

                    <!-- M-Pesa STK Prompt Simulation -->
                    <div v-if="selectedMethod === 'M-Pesa'" class="space-y-3 bg-emerald-500/5 p-4 rounded-xl border border-emerald-500/20">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-semibold text-emerald-900 dark:text-emerald-300">Customer Phone Number (M-Pesa STK Push)</Label>
                            <Badge variant="success">Express Online</Badge>
                        </div>
                        <div class="flex gap-2">
                            <Input
                                v-model="mpesaPhone"
                                placeholder="07XX XXX XXX or 2547XX XXX XXX"
                                class="h-10 text-sm bg-background font-mono"
                            />
                            <Button
                                variant="default"
                                class="h-10 bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-4"
                                :disabled="mpesaPrompting || !mpesaPhone"
                                @click="simulateMpesaSTK"
                            >
                                <span v-if="mpesaPrompting">Prompting...</span>
                                <span v-else>Send STK Push</span>
                            </Button>
                        </div>
                        <div v-if="referenceCode" class="text-xs text-emerald-700 dark:text-emerald-400 font-mono">
                            Auto-captured Ref: <span class="font-bold">{{ referenceCode }}</span>
                        </div>
                    </div>

                    <!-- Card / Bank Reference -->
                    <div v-if="selectedMethod === 'Card' || selectedMethod === 'Bank'" class="space-y-2 bg-muted/20 p-4 rounded-xl border border-border/70">
                        <Label class="text-xs font-semibold">Transaction / Auth Code Reference</Label>
                        <Input
                            v-model="referenceCode"
                            placeholder="Enter POS terminal auth code or bank ref"
                            class="h-10 text-sm bg-background font-mono"
                        />
                    </div>

                    <!-- Credit Account Info -->
                    <div v-if="selectedMethod === 'Credit'" class="space-y-2 bg-amber-500/10 p-4 rounded-xl border border-amber-500/20">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-amber-900 dark:text-amber-300">Customer Credit Account</span>
                            <Badge variant="warning">{{ customer.group }}</Badge>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                            <div>
                                <span class="text-muted-foreground">Credit Limit:</span>
                                <p class="font-bold font-mono">{{ formatCurrency(customer.creditLimit) }}</p>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Current Balance:</span>
                                <p class="font-bold font-mono text-rose-600">{{ formatCurrency(customer.currentBalance) }}</p>
                            </div>
                        </div>
                        <div v-if="customer.id === 'walk-in'" class="text-xs text-rose-600 font-semibold pt-1 flex items-center gap-1.5">
                            <Badge variant="destructive" class="text-[9px] px-1 py-0">Required</Badge>
                            <span>Credit sales require a registered customer account.</span>
                        </div>
                    </div>
                </div>

                <!-- Split Payment Mode -->
                <div v-else class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground">Split breakdown:</span>
                        <Button variant="outline" size="sm" class="h-7 text-xs gap-1" @click="addSplitRow">
                            <Plus class="size-3" />
                            Add Payment Method
                        </Button>
                    </div>

                    <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                        <div
                            v-for="(item, idx) in splitEntries"
                            :key="idx"
                            class="flex items-center gap-2 bg-muted/20 p-2.5 rounded-lg border border-border/70"
                        >
                            <select
                                v-model="item.method"
                                class="h-9 rounded-md border border-input bg-background px-2 text-xs font-semibold"
                            >
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Card">Card</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="Credit">Customer Credit</option>
                            </select>

                            <Input
                                v-model.number="item.amount"
                                type="number"
                                placeholder="Amount (KES)"
                                class="h-9 text-xs font-mono font-bold bg-background flex-1"
                            />

                            <Input
                                v-if="item.method === 'M-Pesa'"
                                v-model="item.phone"
                                placeholder="Phone"
                                class="h-9 text-xs font-mono bg-background w-28"
                            />

                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                                :disabled="splitEntries.length <= 1"
                                @click="removeSplitRow(idx)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Live Balance & Change Strip -->
                <div class="grid grid-cols-2 gap-3 bg-muted/40 p-3.5 rounded-xl border border-border/80 text-xs">
                    <div>
                        <span class="text-muted-foreground">Remaining to Pay:</span>
                        <div :class="['text-base font-bold font-mono', remainingToPay > 0 ? 'text-amber-600' : 'text-emerald-600']">
                            {{ formatCurrency(remainingToPay) }}
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-muted-foreground">Change to Return:</span>
                        <div class="text-base font-bold font-mono text-primary">
                            {{ formatCurrency(changeToReturn) }}
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="px-6 py-3.5 bg-muted/30 border-t border-border/70 flex sm:justify-between items-center gap-2">
                <Button variant="ghost" size="sm" @click="emit('update:open', false)">
                    Cancel (Esc)
                </Button>

                <Button
                    size="sm"
                    class="h-9 px-6 bg-primary font-bold text-xs"
                    :disabled="!isPaymentComplete"
                    @click="handleConfirmPayment"
                >
                    Complete & Print Receipt (F12)
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
