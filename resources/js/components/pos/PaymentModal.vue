<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Customer, PaymentEntry } from '@/composables/useCart';
import { formatCurrency, generateMpesaCode } from '@/composables/useCurrency';
import { isInputFocused } from '@/composables/useKeyboardNavigation';
import { useTenant } from '@/composables/useTenant';
import { useFeatures } from '@/composables/useFeatures';
import { useToast } from '@/composables/useToast';
import axios from 'axios';
import {
    AlertCircle,
    Banknote,
    Building2,
    CheckCircle2,
    CreditCard,
    DollarSign,
    Loader2,
    Plus,
    RotateCcw,
    Smartphone,
    Sparkles,
    Trash2,
    UserCheck,
    Wallet,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    totalAmount: number;
    customer: Customer;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'complete', payload: { payments: PaymentEntry[]; tenderedAmount: number }): void;
}>();

const { currentTenant, isDemo, activePaymentMethods } = useTenant();
const { hasFeature } = useFeatures();
const { error, info, success } = useToast();

const availableMethods = computed(() => {
    return activePaymentMethods.value.filter(pm => {
        if (pm.type === 'Credit' && !hasFeature('customer_credit')) return false;
        return true;
    });
});

const paymentType = ref<'single' | 'split'>('single');
const selectedMethod = ref<string>('Cash');
const cashTendered = ref<number>(0);
const mpesaPhone = ref<string>('');
const referenceCode = ref<string>('');
const mpesaPayerName = ref<string>('');
const mpesaConfirmedAt = ref<string>('');
const isConfirming = ref(false); // loading state while completing payment

const currentActiveMethod = computed(() => {
    return availableMethods.value.find(m => m.name === selectedMethod.value || m.type === selectedMethod.value || m.code === selectedMethod.value) || availableMethods.value[0];
});

const isCash = computed(() => currentActiveMethod.value?.type === 'Cash' || selectedMethod.value === 'Cash');
const isMpesa = computed(() => {
    const type = currentActiveMethod.value?.type;
    const name = selectedMethod.value?.toLowerCase() || '';
    return type === 'Mobile Money' || name.includes('mpesa') || name.includes('m-pesa') || name.includes('till') || name.includes('paybill');
});
const isCard = computed(() => currentActiveMethod.value?.type === 'Card' || selectedMethod.value === 'Card');
const isBank = computed(() => currentActiveMethod.value?.type === 'Bank' || selectedMethod.value === 'Bank');
const isCredit = computed(() => currentActiveMethod.value?.type === 'Credit' || selectedMethod.value === 'Credit');

const storeTillDetails = computed(() => {
    const details = currentActiveMethod.value?.details || {};
    return {
        tillNumber: details.till_number || currentTenant.value.settings?.mpesa_till_number || '523910',
        storeName: details.store_name || currentTenant.value.name || 'Store Till',
    };
});

const storePaybillDetails = computed(() => {
    const details = currentActiveMethod.value?.details || {};
    return {
        businessNumber: details.business_number || '247247',
        accountNumber: details.account_number || 'STORE',
    };
});

const getMethodIcon = (type: string) => {
    switch (type) {
        case 'Cash': return Banknote;
        case 'Mobile Money': return Smartphone;
        case 'Card': return CreditCard;
        case 'Bank': return Building2;
        case 'Credit': return Wallet;
        default: return Wallet;
    }
};

const getMethodActiveClass = (pm: any) => {
    switch (pm.type) {
        case 'Cash': return 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20';
        case 'Mobile Money': return 'border-emerald-600 bg-emerald-500/10 text-emerald-600 ring-2 ring-emerald-500/20';
        case 'Card': return 'border-sky-600 bg-sky-500/10 text-sky-600 ring-2 ring-sky-500/20';
        case 'Bank': return 'border-indigo-600 bg-indigo-500/10 text-indigo-600 ring-2 ring-indigo-500/20';
        case 'Credit': return 'border-amber-600 bg-amber-500/10 text-amber-600 ring-2 ring-amber-500/20';
        default: return 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20';
    }
};

// For split payments
const splitEntries = ref<PaymentEntry[]>([]);

// M-Pesa STK Push State
const stkModalOpen = ref(false);
const stkMode = ref<'single' | 'split'>('single');
const stkSplitIndex = ref<number>(0);
const stkAmount = ref<number>(0);
const stkTargetPhone = ref<string>('');
const stkStatus = ref<'idle' | 'sending' | 'waiting' | 'confirmed' | 'failed'>('idle');
const stkCheckoutId = ref<string | null>(null);
const stkErrorMessage = ref<string>('');
const stkPayerName = ref<string>('');
const stkConfirmedCode = ref<string>('');
let stkTimer: ReturnType<typeof setInterval> | null = null;
let stkPollTimer: ReturnType<typeof setInterval> | null = null;
let stkCountdownTimer: ReturnType<typeof setInterval> | null = null;
let stkPollAttempts = 0;
const STK_TIMEOUT_SECONDS = 120; // 120 seconds (2 minutes) timeout for customer to enter PIN
const STK_POLL_INTERVAL_MS = 2500; // Poll Daraja every 2.5 seconds
const STK_POLL_MAX = Math.ceil((STK_TIMEOUT_SECONDS * 1000) / STK_POLL_INTERVAL_MS); // ~48 attempts
const stkCountdown = ref<number>(STK_TIMEOUT_SECONDS);

const formatCountdown = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const playMpesaChime = () => {
    try {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;

        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, now);
        gain1.gain.setValueAtTime(0.15, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.18);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.18);

        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(1318.5, now + 0.12);
        gain2.gain.setValueAtTime(0.18, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.35);
    } catch {}
};

// Demo Simulation (waits realistically then auto-captures)
const runDemoSimulation = () => {
    stkStatus.value = 'waiting';
    stkCountdown.value = 15;
    if (stkTimer) { clearInterval(stkTimer); stkTimer = null; }
    if (stkCountdownTimer) { clearInterval(stkCountdownTimer); stkCountdownTimer = null; }

    stkCountdownTimer = setInterval(() => {
        if (stkCountdown.value > 0) {
            stkCountdown.value--;
        }
    }, 1000);

    // In demo simulation, wait 12s for customer to enter PIN on mobile, then auto-capture!
    stkTimer = setTimeout(() => {
        if (stkStatus.value === 'waiting') {
            completeMpesaStk(generateMpesaCode());
        }
    }, 12000);
};

const completeMpesaStk = (realCode?: string, payerPhone?: string) => {
    if (stkTimer)  { clearInterval(stkTimer);  stkTimer = null; }
    if (stkPollTimer) { clearInterval(stkPollTimer); stkPollTimer = null; }
    if (stkCountdownTimer) { clearInterval(stkCountdownTimer); stkCountdownTimer = null; }

    const code = realCode || stkConfirmedCode.value || generateMpesaCode();
    const payer = stkPayerName.value.trim() || payerPhone || stkTargetPhone.value;
    const confirmedAt = new Date().toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    stkStatus.value = 'confirmed';

    if (stkMode.value === 'single') {
        referenceCode.value = code;
        mpesaPayerName.value = payer;
        mpesaConfirmedAt.value = confirmedAt;

        playMpesaChime();
        success('M-Pesa Confirmed!', `Received ${formatCurrency(stkAmount.value)} from ${payer}. Code: ${code}`);

        setTimeout(() => {
            stkModalOpen.value = false;
            stkStatus.value = 'idle';
            stkPayerName.value = '';
            stkConfirmedCode.value = '';
            handleConfirmPayment(); // Immediately complete transaction and dispatch receipt print
        }, 800);
        return;
    } else {
        const item = splitEntries.value[stkSplitIndex.value];
        if (item) {
            item.reference = code;
            item.payerName = payer;
        }

        playMpesaChime();
        success('M-Pesa Confirmed!', `Received ${formatCurrency(stkAmount.value)} from ${payer}. Code: ${code}`);

        setTimeout(() => {
            stkModalOpen.value = false;
            stkStatus.value = 'idle';
            stkPayerName.value = '';
            stkConfirmedCode.value = '';
            if (remainingToPay.value <= 0) {
                handleConfirmPayment(); // Immediately complete transaction if all split parts paid
            }
        }, 800);
    }
};

const cancelMpesaStk = () => {
    if (stkTimer)  { clearInterval(stkTimer);  stkTimer = null; }
    if (stkPollTimer) { clearInterval(stkPollTimer); stkPollTimer = null; }
    if (stkCountdownTimer) { clearInterval(stkCountdownTimer); stkCountdownTimer = null; }
    stkStatus.value = 'idle';
    error('STK Prompt Cancelled', 'M-Pesa prompt was cancelled.');
    stkModalOpen.value = false;
};

// Polling (Automatic capture of customer payment status)
const pollStkConfirmation = () => {
    stkPollAttempts = 0;
    stkCountdown.value = STK_TIMEOUT_SECONDS;
    if (stkPollTimer) clearInterval(stkPollTimer);
    if (stkCountdownTimer) clearInterval(stkCountdownTimer);

    stkCountdownTimer = setInterval(() => {
        if (stkCountdown.value > 0) {
            stkCountdown.value--;
        }
    }, 1000);

    stkPollTimer = setInterval(async () => {
        stkPollAttempts++;

        if (stkPollAttempts > STK_POLL_MAX || stkCountdown.value <= 0) {
            clearInterval(stkPollTimer!);
            if (stkCountdownTimer) clearInterval(stkCountdownTimer);
            stkPollTimer = null;
            stkCountdownTimer = null;
            stkStatus.value = 'failed';
            stkErrorMessage.value = 'Payment confirmation timed out (120s). Customer did not enter PIN on phone in time.';
            error('STK Timeout', 'Payment confirmation timed out after 120 seconds. Please ask customer to retry.');
            return;
        }

        try {
            const { data } = await axios.post('/mpesa/stk-query', {
                checkout_request_id: stkCheckoutId.value,
            });

            if (data.confirmed) {
                // Customer payment confirmed! Automatically capture status:
                clearInterval(stkPollTimer!);
                if (stkCountdownTimer) clearInterval(stkCountdownTimer);
                stkPollTimer = null;
                stkCountdownTimer = null;
                completeMpesaStk(data.code || undefined, data.phone || undefined);
            } else if (data.result_code !== null && data.result_code !== undefined && data.result_code != 0) {
                // Definitive failure reported by Safaricom
                clearInterval(stkPollTimer!);
                if (stkCountdownTimer) clearInterval(stkCountdownTimer);
                stkPollTimer = null;
                stkCountdownTimer = null;
                stkStatus.value = 'failed';
                stkErrorMessage.value = data.description ?? 'M-Pesa transaction was cancelled or failed on the customer phone.';
                error('Payment Failed', stkErrorMessage.value);
            }
        } catch {
            // Keep polling on transient network hiccups
        }
    }, STK_POLL_INTERVAL_MS);
};

// â”€â”€â”€ Main STK Push entry point â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const startMpesaStk = async (mode: 'single' | 'split', splitIdx: number = 0, forceSimulation = false) => {
    stkMode.value = mode;
    stkSplitIndex.value = splitIdx;

    if (mode === 'single') {
        stkAmount.value = props.totalAmount;
        stkTargetPhone.value = mpesaPhone.value || props.customer?.phone || '0712 345 678';
        mpesaPhone.value = stkTargetPhone.value;
    } else {
        const item = splitEntries.value[splitIdx];
        stkAmount.value = Number(item?.amount) || 0;
        stkTargetPhone.value = item?.phone || mpesaPhone.value || props.customer?.phone || '0712 345 678';
        if (item) item.phone = stkTargetPhone.value;
    }

    if (stkAmount.value <= 0) {
        error('Invalid Amount', 'M-Pesa payment amount must be greater than zero.');
        return;
    }

    stkCheckoutId.value = null;
    stkErrorMessage.value = '';
    stkModalOpen.value = true;

    // Explicit Simulation Requested:
    if (forceSimulation) {
        runDemoSimulation();
        return;
    }

    // Live API call to Safaricom Daraja STK Push:
    stkStatus.value = 'sending';
    try {
        const { data } = await axios.post('/mpesa/stk-push', {
            phone:  stkTargetPhone.value,
            amount: stkAmount.value,
            ref:    `POS-${Date.now()}`,
        });

        if (data.success) {
            stkCheckoutId.value = data.checkout_request_id ?? null;
            stkStatus.value = 'waiting';
            info('STK Prompt Sent', `Awaiting PIN on ${stkTargetPhone.value}`);
            pollStkConfirmation();
        } else {
            stkStatus.value = 'failed';
            stkErrorMessage.value = data.message ?? 'Could not send M-Pesa prompt.';
            error('STK Failed', stkErrorMessage.value);
        }
    } catch (e: any) {
        stkStatus.value = 'failed';
        stkErrorMessage.value = e?.response?.data?.message ?? e?.message ?? 'Network error sending STK push.';
        error('STK Error', stkErrorMessage.value);
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            cashTendered.value = props.totalAmount;
            mpesaPhone.value = props.customer?.phone && props.customer.phone !== '+254 700 000 000'
                ? props.customer.phone
                : '0712 345 678';
            referenceCode.value = '';
            splitEntries.value = [{ method: 'Cash', amount: props.totalAmount }];
        } else {
            // Dialog closed by parent — clear the confirming spinner
            isConfirming.value = false;
        }
    },
);

const singleTotalPaid = computed(() => {
    if (isCash.value) {
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
        if (isCash.value) {
            return Number(cashTendered.value) >= props.totalAmount;
        }
        if (isCredit.value) {
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
    const defaultSplitMethod = availableMethods.value[0]?.name || 'Cash';
    splitEntries.value.push({
        method: defaultSplitMethod,
        amount: rem > 0 ? rem : 0,
        phone: mpesaPhone.value || props.customer?.phone || '0712 345 678',
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

const handleConfirmPayment = () => {
    if (!isPaymentComplete.value) {
        if (isCredit.value && props.customer.id === 'walk-in') {
            error('Invalid Customer', 'Credit sales require selecting a registered customer account.');
            return;
        }
        error('Insufficient Amount', 'Total payment amount must cover the total bill.');
        return;
    }

    let finalPayments: PaymentEntry[] = [];
    let tendered = 0;

    if (paymentType.value === 'single') {
        if (isCash.value) {
            tendered = Number(cashTendered.value);
            finalPayments = [{ method: selectedMethod.value || 'Cash', amount: props.totalAmount }];
        } else if (isMpesa.value) {
            tendered = props.totalAmount;
            finalPayments = [{
                method: selectedMethod.value || 'M-Pesa',
                amount: props.totalAmount,
                phone: mpesaPhone.value,
                reference: referenceCode.value || generateMpesaCode(),
                payerName: mpesaPayerName.value || undefined,
                confirmedAt: mpesaConfirmedAt.value || undefined,
            }];
        } else {
            tendered = props.totalAmount;
            finalPayments = [{ method: selectedMethod.value, amount: props.totalAmount, reference: referenceCode.value }];
        }
    } else {
        tendered = splitTotalPaid.value;
        finalPayments = splitEntries.value.map((e) => {
            const isRowMpesa = e.method?.toLowerCase().includes('mpesa') || e.method?.toLowerCase().includes('till') || e.method?.toLowerCase().includes('paybill');
            return {
                ...e,
                amount: Number(e.amount),
                reference: isRowMpesa && !e.reference ? generateMpesaCode() : e.reference,
            };
        });
    }

    emit('complete', {
        payments: finalPayments,
        tenderedAmount: tendered,
    });

    // Keep spinner active — cleared when the parent closes the dialog (props.open → false)
    isConfirming.value = true;
};

// Keyboard controls inside Payment Modal

const handleModalKeydown = (e: KeyboardEvent) => {
    if (!props.open) return;

    // Handle Escape or Cancel inside STK modal or Payment modal
    if (e.key === 'Escape') {
        if (stkModalOpen.value) {
            e.preventDefault();
            cancelMpesaStk();
        } else {
            e.preventDefault();
            emit('update:open', false);
        }
        return;
    }

    // If STK modal is active, allow 'c' / 'C' key to cancel prompt
    if (stkModalOpen.value && e.key.toLowerCase() === 'c') {
        e.preventDefault();
        cancelMpesaStk();
        return;
    }

    // Enter or Ctrl+Enter: confirm payment if complete
    if (e.key === 'Enter') {
        if (isPaymentComplete.value && !stkModalOpen.value && !isConfirming.value) {
            e.preventDefault();
            handleConfirmPayment();
        }
        return;
    }

    // Single-key shortcuts when not typing inside an input
    if (!isInputFocused()) {
        const key = e.key.toLowerCase();

        // 'm' key: Automatically choose M-Pesa payment method
        if (key === 'm') {
            e.preventDefault();
            paymentType.value = 'single';
            selectedMethod.value = 'M-Pesa';
            return;
        }

        // 's' key: Send STK Push prompt to phone
        if (key === 's') {
            e.preventDefault();
            if (!stkModalOpen.value) {
                if (paymentType.value === 'single') {
                    selectedMethod.value = 'M-Pesa';
                    startMpesaStk('single', 0, false);
                } else {
                    startMpesaStk('split', stkSplitIndex.value || 0, false);
                }
            } else if (stkStatus.value === 'failed') {
                startMpesaStk(stkMode.value, stkSplitIndex.value, false);
            }
            return;
        }

        // 'c' key: Cancel prompt / cancel payment modal
        if (key === 'c') {
            e.preventDefault();
            if (stkModalOpen.value) {
                cancelMpesaStk();
            } else {
                emit('update:open', false);
            }
            return;
        }

        // 'p' key: Complete & Print Receipt
        if (key === 'p') {
            if (isPaymentComplete.value && !stkModalOpen.value && !isConfirming.value) {
                e.preventDefault();
                handleConfirmPayment();
            }
            return;
        }

        // Number keys 1-9 dynamically bound to available payment methods
        const digit = parseInt(e.key, 10);
        if (!isNaN(digit) && digit >= 1 && digit <= availableMethods.value.length) {
            e.preventDefault();
            selectedMethod.value = availableMethods.value[digit - 1].name;
        } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            const names = availableMethods.value.map(m => m.name);
            const currentIndex = names.indexOf(selectedMethod.value);
            if (names.length > 0) {
                selectedMethod.value = names[(currentIndex + 1) % names.length];
            }
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            const names = availableMethods.value.map(m => m.name);
            const currentIndex = names.indexOf(selectedMethod.value);
            if (names.length > 0) {
                selectedMethod.value = names[(currentIndex - 1 + names.length) % names.length];
            }
        }
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            window.addEventListener('keydown', handleModalKeydown);
            if (props.customer?.phone && !mpesaPhone.value) {
                mpesaPhone.value = props.customer.phone;
            }
        } else {
            window.removeEventListener('keydown', handleModalKeydown);
        }
    },
);

onUnmounted(() => {
    window.removeEventListener('keydown', handleModalKeydown);
    if (stkTimer) clearInterval(stkTimer);
    if (stkPollTimer) clearInterval(stkPollTimer);
});
</script>

<template>
    <Dialog :open="open" spinnerText="Opening Payment Checkout..." @update:open="(v) => emit('update:open', v)">
        <DialogContent class="w-[95vw] sm:max-w-2xl p-0 max-h-[92vh] flex flex-col overflow-hidden">
            <DialogHeader class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border/70 flex flex-row items-center justify-between bg-muted/20 shrink-0">
                <div class="min-w-0 pr-2">
                    <DialogTitle class="text-base sm:text-lg font-bold">Process Payment</DialogTitle>
                    <DialogDescription class="text-[11px] sm:text-xs text-muted-foreground truncate">Customer: {{ customer.name }} ({{ customer.group }})</DialogDescription>
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
                    <div
                        class="grid gap-2"
                        :class="[
                            availableMethods.length <= 3 ? 'grid-cols-3' :
                            availableMethods.length === 4 ? 'grid-cols-4' :
                            availableMethods.length === 5 ? 'grid-cols-5' :
                            'grid-cols-3 sm:grid-cols-6'
                        ]"
                    >
                        <button
                            v-for="(pm, idx) in availableMethods"
                            :key="pm.id || pm.name"
                            type="button"
                            :class="[
                                'flex flex-col items-center justify-center p-3 rounded-xl border text-xs font-semibold gap-1.5 transition-all relative',
                                selectedMethod === pm.name
                                    ? getMethodActiveClass(pm)
                                    : 'border-border/80 hover:bg-muted/40 text-muted-foreground',
                            ]"
                            @click="selectedMethod = pm.name"
                        >
                            <span class="absolute top-1 right-1.5 text-[9px] font-mono text-muted-foreground/60 font-semibold">
                                {{ idx + 1 }}
                            </span>
                            <component :is="getMethodIcon(pm.type)" class="size-5" />
                            <span class="truncate max-w-full px-1">{{ pm.name }}</span>
                        </button>
                    </div>

                    <!-- Cash Interface with Quick Tenders -->
                    <div v-if="isCash" class="space-y-3 bg-muted/20 p-4 rounded-xl border border-border/70">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Label class="text-xs font-semibold">Cash Tendered (KES)</Label>
                                <Badge v-if="currentActiveMethod?.details?.drawer_label" variant="outline" class="text-[10px] font-normal">
                                    {{ currentActiveMethod.details.drawer_label }}
                                </Badge>
                            </div>
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

                    <!-- M-Pesa STK Prompt Simulation & Till / Paybill Reference -->
                    <div v-if="isMpesa" class="space-y-3 bg-emerald-500/5 p-4 rounded-xl border border-emerald-500/20">
                        <!-- Store Till / Paybill Identification Strip -->
                        <div v-if="currentActiveMethod?.details?.till_number" class="flex items-center justify-between p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/25 text-xs">
                            <div class="flex items-center gap-2">
                                <Smartphone class="size-4 text-emerald-600 shrink-0" />
                                <div>
                                    <span class="text-muted-foreground font-medium">Buy Goods Till: </span>
                                    <span class="font-mono font-black text-emerald-700 dark:text-emerald-300 text-sm tracking-wide">{{ currentActiveMethod.details.till_number }}</span>
                                </div>
                            </div>
                            <span v-if="currentActiveMethod.details.store_name" class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 bg-background/80 px-2 py-0.5 rounded border border-emerald-500/20">
                                {{ currentActiveMethod.details.store_name }}
                            </span>
                        </div>
                        <div v-else-if="currentActiveMethod?.details?.business_number" class="flex items-center justify-between p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/25 text-xs">
                            <div class="flex items-center gap-2">
                                <Smartphone class="size-4 text-emerald-600 shrink-0" />
                                <div>
                                    <span class="text-muted-foreground font-medium">Paybill: </span>
                                    <span class="font-mono font-black text-emerald-700 dark:text-emerald-300 text-sm tracking-wide">{{ currentActiveMethod.details.business_number }}</span>
                                    <span class="text-muted-foreground font-medium ml-2">Account: </span>
                                    <span class="font-mono font-bold">{{ currentActiveMethod.details.account_number || 'STORE' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-semibold text-emerald-900 dark:text-emerald-300">Customer Phone (M-Pesa STK Push)</Label>
                            <Badge variant="success" class="gap-1 text-[10px]">
                                <Sparkles class="size-3" />
                                Express STK
                            </Badge>
                        </div>
                        <div class="flex gap-2">
                            <Input
                                v-model="mpesaPhone"
                                placeholder="07XX XXX XXX or 2547XX XXX XXX"
                                class="h-10 text-sm bg-background font-mono flex-1"
                            />
                            <Button
                                type="button"
                                variant="default"
                                class="h-10 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 gap-1.5 shadow-sm"
                                :disabled="!mpesaPhone || totalAmount <= 0"
                                title="Send live STK push prompt to customer phone via Safaricom Daraja (Shortcut: S)"
                                @click="startMpesaStk('single', 0, false)"
                            >
                                <Smartphone class="size-4" />
                                <span>Send STK Push (S)</span>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 border-emerald-500/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/10 font-bold text-xs px-3 gap-1 shadow-sm"
                                :disabled="!mpesaPhone || totalAmount <= 0"
                                title="Simulate payment approval without calling Daraja"
                                @click="startMpesaStk('single', 0, true)"
                            >
                                <Sparkles class="size-3.5 text-amber-500" />
                                <span>Simulate</span>
                            </Button>
                        </div>

                        <!-- Confirmed M-Pesa State -->
                        <div v-if="referenceCode" class="p-3 bg-emerald-500/10 rounded-xl border border-emerald-500/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 class="size-4 text-emerald-600 shrink-0" />
                                    <p class="text-xs font-bold text-emerald-950 dark:text-emerald-200">M-Pesa Confirmed</p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-[10px] text-muted-foreground hover:text-foreground"
                                    @click="referenceCode = ''; mpesaPayerName = ''; mpesaConfirmedAt = ''"
                                >
                                    Re-prompt
                                </Button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                <div class="bg-background/70 rounded-lg p-2 border border-emerald-500/20">
                                    <p class="text-muted-foreground text-[10px] font-bold uppercase mb-0.5">Transaction Code</p>
                                    <p class="font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ referenceCode }}</p>
                                </div>
                                <div class="bg-background/70 rounded-lg p-2 border border-emerald-500/20">
                                    <p class="text-muted-foreground text-[10px] font-bold uppercase mb-0.5">Amount</p>
                                    <p class="font-mono font-bold text-foreground">{{ formatCurrency(totalAmount) }}</p>
                                </div>
                                <div class="bg-background/70 rounded-lg p-2 border border-emerald-500/20">
                                    <p class="text-muted-foreground text-[10px] font-bold uppercase mb-0.5">Payer</p>
                                    <p class="font-semibold text-foreground truncate">{{ mpesaPayerName || mpesaPhone || '—' }}</p>
                                </div>
                                <div class="bg-background/70 rounded-lg p-2 border border-emerald-500/20">
                                    <p class="text-muted-foreground text-[10px] font-bold uppercase mb-0.5">Time</p>
                                    <p class="font-mono text-foreground">{{ mpesaConfirmedAt || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Reference input fallback -->
                        <div v-else class="flex items-center gap-2 pt-1 text-[11px] text-muted-foreground">
                            <span>Or enter manual Till/Paybill code:</span>
                            <Input
                                v-model="referenceCode"
                                placeholder="e.g. QK8912..."
                                class="h-7 text-xs bg-background font-mono uppercase w-32"
                            />
                        </div>
                    </div>

                    <!-- Card / Bank Reference -->
                    <div v-if="isCard || isBank" class="space-y-2 bg-muted/20 p-4 rounded-xl border border-border/70">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-semibold">Transaction / Auth Code Reference</Label>
                            <span v-if="currentActiveMethod?.details?.terminal_id" class="text-[11px] font-mono text-muted-foreground">
                                Terminal: <strong class="text-foreground">{{ currentActiveMethod.details.terminal_id }}</strong>
                                <span v-if="currentActiveMethod?.details?.bank_name"> ({{ currentActiveMethod.details.bank_name }})</span>
                            </span>
                        </div>
                        <Input
                            v-model="referenceCode"
                            placeholder="Enter POS terminal auth code or bank ref"
                            class="h-10 text-sm bg-background font-mono"
                        />
                    </div>

                    <!-- Credit Account Info -->
                    <div v-if="isCredit" class="space-y-2 bg-amber-500/10 p-4 rounded-xl border border-amber-500/20">
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
                                <option v-for="pm in availableMethods" :key="pm.id || pm.name" :value="pm.name">
                                    {{ pm.name }}
                                </option>
                            </select>

                            <Input
                                v-model.number="item.amount"
                                type="number"
                                placeholder="Amount (KES)"
                                class="h-9 text-xs font-mono font-bold bg-background flex-1"
                            />

                            <!-- If M-Pesa row in split mode -->
                            <template v-if="item.method?.toLowerCase().includes('mpesa') || item.method?.toLowerCase().includes('till') || item.method?.toLowerCase().includes('paybill')">
                                <Input
                                    v-model="item.phone"
                                    placeholder="07XX..."
                                    class="h-9 text-xs font-mono bg-background w-24"
                                />

                                <div v-if="!item.reference" class="flex gap-1 shrink-0">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="default"
                                        class="h-9 px-2 text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-1 shrink-0"
                                        @click="startMpesaStk('split', idx, false)"
                                        title="Send live M-Pesa STK Push to phone"
                                    >
                                        <Smartphone class="size-3.5" />
                                        <span>Push</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="h-9 px-1.5 text-[11px] font-bold border-emerald-500/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/10 shrink-0"
                                        @click="startMpesaStk('split', idx, true)"
                                        title="Simulate split M-Pesa approval"
                                    >
                                        <Sparkles class="size-3 text-amber-500" />
                                    </Button>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-col gap-0.5 text-[11px] font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-1.5 rounded border border-emerald-500/30 shrink-0 min-w-[80px]"
                                    :title="`Code: ${item.reference}${item.payerName ? ' | Payer: ' + item.payerName : ''}`"
                                >
                                    <div class="flex items-center gap-1">
                                        <CheckCircle2 class="size-3.5 shrink-0" />
                                        <span>{{ item.reference }}</span>
                                    </div>
                                    <span v-if="item.payerName" class="text-[10px] font-sans font-semibold text-emerald-700 dark:text-emerald-300 truncate max-w-[100px]">{{ item.payerName }}</span>
                                </div>
                            </template>

                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive shrink-0"
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
                <Button variant="ghost" size="sm" :disabled="isConfirming" @click="emit('update:open', false)">
                    Cancel (C)
                </Button>

                <Button
                    size="sm"
                    class="h-9 px-6 bg-primary font-bold text-xs gap-2"
                    :disabled="!isPaymentComplete || isConfirming"
                    @click="handleConfirmPayment"
                >
                    <Loader2 v-if="isConfirming" class="size-3.5 animate-spin" />
                    <span>{{ isConfirming ? 'Processing…' : 'Complete &amp; Print Receipt (P / F12)' }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- M-Pesa STK Push Modal (Demo Simulation + Live Daraja) -->
    <Dialog :open="stkModalOpen" @update:open="(v) => (stkModalOpen = v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base font-bold flex items-center gap-2">
                    <Smartphone class="size-4 text-emerald-600" />
                    <span>M-Pesa STK Push</span>
                    <Badge variant="outline" class="font-mono text-[10px] ml-auto mr-6">
                        <span v-if="stkStatus === 'sending'">Sending…</span>
                        <span v-else-if="stkStatus === 'waiting'">Awaiting PIN</span>
                        <span v-else-if="stkStatus === 'confirmed'">Confirmed</span>
                        <span v-else-if="stkStatus === 'failed'">Failed</span>
                        <span v-else>Ready</span>
                    </Badge>
                </DialogTitle>
                <DialogDescription>
                    {{ isDemo ? 'Demo Simulator' : 'Safaricom Daraja' }} • Target: <strong class="font-mono text-foreground">{{ stkTargetPhone }}</strong>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-1 text-xs">
                <!-- Merchant & Amount Info Card -->
                <div class="bg-muted/40 border border-border/80 rounded-lg p-3 text-center space-y-1">
                    <p class="text-[11px] text-muted-foreground">Pay Merchant / Till: <strong class="text-foreground">{{ currentTenant.name }}</strong></p>
                    <p class="text-2xl font-black font-mono text-primary pt-0.5">
                        {{ formatCurrency(stkAmount) }}
                    </p>
                </div>

                <!-- Sending -->
                <div v-if="stkStatus === 'sending'" class="space-y-2 py-4 text-center">
                    <Loader2 class="size-6 text-primary animate-spin mx-auto" />
                    <p class="text-xs font-medium text-foreground">Contacting Safaricom Daraja gateway…</p>
                </div>

                <!-- Waiting PIN / Customer Payment (Automatic Capture Active) -->
                <div v-else-if="stkStatus === 'waiting'" class="space-y-3 py-2 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <Badge variant="outline" class="font-mono text-xs px-2.5 py-1 bg-muted font-bold">
                            {{ formatCurrency(stkAmount) }}
                        </Badge>
                        <Badge variant="outline" class="font-mono text-xs px-2.5 py-1 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 font-bold flex items-center gap-1.5">
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>{{ formatCountdown(stkCountdown) }}</span>
                        </Badge>
                    </div>

                    <div class="rounded-lg bg-muted/40 border border-border/80 p-3 text-left space-y-1">
                        <div class="flex items-center gap-1.5 font-bold text-foreground">
                            <span class="size-2 rounded-full bg-emerald-600 animate-pulse"></span>
                            <span>Automatic Status Capture Active</span>
                        </div>
                        <p class="text-[11px] text-muted-foreground leading-relaxed">
                            STK prompt sent to <span class="font-mono font-bold text-foreground">{{ stkTargetPhone }}</span>. Awaiting customer PIN on phone. Payment status and receipt code will capture automatically.
                        </p>
                    </div>
                </div>

                <!-- Confirmed -->
                <div v-else-if="stkStatus === 'confirmed'" class="space-y-2 py-3 text-center">
                    <CheckCircle2 class="size-8 text-emerald-600 mx-auto" />
                    <p class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Payment Confirmed!</p>
                </div>

                <!-- Failed -->
                <div v-else-if="stkStatus === 'failed'" class="space-y-2 py-2 text-center">
                    <AlertCircle class="size-8 text-destructive mx-auto" />
                    <p class="text-xs font-bold text-destructive">STK Push Not Sent / Failed</p>
                    <div v-if="stkErrorMessage" class="p-2.5 bg-destructive/10 border border-destructive/20 rounded-lg text-left text-[11px] space-y-1">
                        <p class="font-bold text-destructive">Daraja Response:</p>
                        <p class="font-mono text-[10px] leading-relaxed text-destructive">{{ stkErrorMessage }}</p>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <!-- Failed State Options -->
                <template v-if="stkStatus === 'failed'">
                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                        @click="startMpesaStk(stkMode, stkSplitIndex, true)"
                    >
                        <Sparkles class="size-3.5" />
                        <span>Run Demo Simulation</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="startMpesaStk(stkMode, stkSplitIndex, false)"
                    >
                        Retry Push
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="cancelMpesaStk"
                    >
                        Dismiss
                    </Button>
                </template>

                <!-- Waiting State Button -->
                <template v-else-if="stkStatus === 'waiting'">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="cancelMpesaStk"
                    >
                        Cancel Prompt (C)
                    </Button>
                </template>

                <Button
                    v-else-if="stkStatus === 'sending'"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="cancelMpesaStk"
                >
                    Cancel (C)
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
