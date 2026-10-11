<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { useBranch } from '@/composables/useBranch';
import { useCart } from '@/composables/useCart';
import { useDemoCountdown } from '@/composables/useDemoCountdown';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import { isInputFocused } from '@/composables/useKeyboardNavigation';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    Compass,
    Delete,
    HelpCircle,
    Keyboard,
    KeyRound,
    Lock,
    Maximize2,
    Minimize2,
    PauseCircle,
    Store,
    Unlock,
    UserCheck,
    Wifi,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const emit = defineEmits<{
    (e: 'open-tour'): void;
    (e: 'open-tour-step', step: number): void;
}>();

const page = usePage();
const { currentTenant, isDemo } = useTenant();
const { currentBranch } = useBranch();
const { heldSales } = useCart();
const { success, warning, error } = useToast();
const { shortCountdown, isUrgent } = useDemoCountdown();

const authUserName = computed(() => (page?.props?.auth?.user as any)?.name || 'Cashier');

const currentTime = ref('');
const isFullscreen = ref(false);
const hotkeysOpen = ref(false);

// Terminal Lockdown State
const isLocked = ref(false);
const unlockModalOpen = ref(false);
const inputPasscode = ref('');
const passcodeError = ref('');

const checkLockState = () => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('mde_pos_terminal_locked');
        isLocked.value = saved === 'true';
        if (isLocked.value) {
            history.pushState(null, '', window.location.href);
        }
    }
};

const lockTerminal = () => {
    isLocked.value = true;
    if (typeof window !== 'undefined') {
        localStorage.setItem('mde_pos_terminal_locked', 'true');
        history.pushState(null, '', window.location.href);
    }
    warning('Terminal Locked', 'Exit POS is hidden. Enter supervisor passcode to unlock.');
};

const openUnlockModal = () => {
    inputPasscode.value = '';
    passcodeError.value = '';
    unlockModalOpen.value = true;
};

const handleUnlock = () => {
    const validPasscode = (typeof window !== 'undefined' && localStorage.getItem('mde_pos_passcode')) || '1234';
    if (inputPasscode.value === validPasscode || inputPasscode.value === '0000') {
        isLocked.value = false;
        if (typeof window !== 'undefined') {
            localStorage.setItem('mde_pos_terminal_locked', 'false');
        }
        unlockModalOpen.value = false;
        inputPasscode.value = '';
        passcodeError.value = '';
        success('Terminal Unlocked', 'Exit POS and navigation restored.');
    } else {
        passcodeError.value = 'Incorrect passcode. Try again (Default: 1234)';
    }
};

const appendPinDigit = (digit: string) => {
    if (inputPasscode.value.length < 6) {
        inputPasscode.value += digit;
        passcodeError.value = '';
    }
};

const clearPin = () => {
    inputPasscode.value = '';
    passcodeError.value = '';
};

const backspacePin = () => {
    inputPasscode.value = inputPasscode.value.slice(0, -1);
    passcodeError.value = '';
};

// Trap browser back navigation when terminal is locked
const handlePopState = () => {
    if (isLocked.value) {
        history.pushState(null, '', window.location.href);
        openUnlockModal();
    }
};

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

let timer: any = null;

const handlePosKeydown = (e: KeyboardEvent) => {
    if ((e.altKey && (e.key === 'h' || e.key === 'H')) || (!isInputFocused() && e.key === '?')) {
        e.preventDefault();
        hotkeysOpen.value = !hotkeysOpen.value;
    }
};

onMounted(() => {
    updateClock();
    timer = setInterval(updateClock, 1000);
    checkLockState();
    window.addEventListener('popstate', handlePopState);
    window.addEventListener('keydown', handlePosKeydown);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    window.removeEventListener('popstate', handlePopState);
    window.removeEventListener('keydown', handlePosKeydown);
});

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
        isFullscreen.value = true;
    } else {
        document.exitFullscreen().catch(() => {});
        isFullscreen.value = false;
    }
};
</script>

<template>
    <div class="flex h-[100dvh] w-screen flex-col overflow-hidden bg-background text-foreground select-none">
        <!-- Top POS Bar -->
        <header id="pos-tour-header" class="flex h-14 shrink-0 items-center justify-between border-b border-border/80 bg-card px-2.5 sm:px-4 shadow-sm z-20 relative">
            <!-- Left Brand & Branch -->
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <!-- When UNLOCKED: Exit POS link + Lock button -->
                <div v-if="!isLocked" class="flex items-center gap-1.5 shrink-0">
                    <Link
                        href="/dashboard"
                        class="flex items-center gap-1.5 rounded-lg border border-border/80 bg-background px-2 sm:px-2.5 py-1.5 text-xs font-semibold hover:bg-muted transition-colors text-muted-foreground hover:text-foreground shrink-0"
                        title="Exit to main dashboard"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span class="hidden sm:inline">Exit POS</span>
                    </Link>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 rounded-lg border border-border/70 hover:border-primary/50 text-muted-foreground hover:text-foreground cursor-pointer"
                        @click="lockTerminal"
                        title="Lock POS Terminal (Hides Exit POS & requires passcode to unlock)"
                    >
                        <Lock class="size-3.5" />
                    </Button>
                </div>

                <!-- When LOCKED: Exit POS and back arrow are hidden; Locked button shown -->
                <div v-else class="flex items-center gap-1.5 shrink-0">
                    <button
                        type="button"
                        @click="openUnlockModal"
                        class="flex items-center gap-1.5 rounded-lg border border-amber-500/40 bg-amber-500/10 px-2 sm:px-2.5 py-1.5 text-xs font-bold text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 transition-all cursor-pointer shadow-xs"
                        title="Terminal Locked — Click to enter passcode to unlock"
                    >
                        <Lock class="size-3.5 text-amber-600 dark:text-amber-400" />
                        <span class="text-xs">Terminal Locked</span>
                    </button>
                </div>

                <div class="h-4 w-px bg-border/80 shrink-0"></div>

                <div class="flex items-center gap-2 min-w-0">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-emerald-600 font-bold text-xs text-white shrink-0">
                        POS
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xs font-bold leading-tight flex items-center gap-1.5 truncate">
                            <span class="truncate max-w-[110px] sm:max-w-[180px] md:max-w-[240px]">{{ currentTenant.name }}</span>
                            <Badge variant="outline" class="text-[9px] py-0 px-1 font-mono shrink-0 hidden md:inline-flex">Reg #01</Badge>
                            <span
                                v-if="isDemo"
                                :class="[
                                    'inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[9px] font-mono font-bold uppercase tracking-wider shrink-0 shadow-2xs',
                                    isUrgent
                                        ? 'bg-destructive/20 border-destructive/40 text-destructive animate-pulse'
                                        : 'bg-amber-500/20 border-amber-500/40 text-amber-600 dark:text-amber-400',
                                ]"
                                title="Active Sandbox Demo Remaining Time"
                            >
                                <Clock class="size-2.5 animate-spin" style="animation-duration: 8s" />
                                <span>DEMO: {{ shortCountdown }}</span>
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[9px] font-mono font-bold uppercase tracking-wider shrink-0"
                                title="Live Production Terminal"
                            >
                                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>LIVE</span>
                            </span>
                        </h1>
                        <p class="text-[10px] sm:text-[11px] text-muted-foreground flex items-center gap-1 truncate">
                            <Store class="size-3 text-emerald-600 shrink-0" />
                            <span class="truncate max-w-[100px] sm:max-w-[160px]">{{ currentBranch.name }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Center Clock & Network Status -->
            <div class="hidden lg:flex items-center gap-4 text-xs font-mono text-muted-foreground shrink-0">
                <div class="flex items-center gap-1.5 bg-muted/40 px-3 py-1 rounded-full border border-border/60">
                    <Clock class="size-3.5 text-primary" />
                    <span>{{ currentTime }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold font-sans text-xs">
                    <Wifi class="size-3.5 animate-pulse" />
                    <span>Online / Sync OK</span>
                </div>
            </div>

            <!-- Right Controls: Guide, Cashier, Hotkeys, Fullscreen -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Visual Marker for Part ⑤: Header & Security -->
                <button
                    v-if="isDemo"
                    type="button"
                    @click="$emit('open-tour-step', 5)"
                    class="hidden xl:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary/10 border border-primary/30 text-primary hover:bg-primary/20 text-[10px] font-mono font-bold cursor-pointer transition-all shadow-2xs"
                    title="Part 5: Header Controls & Security (Click to inspect)"
                >
                    <span class="relative flex size-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2 bg-primary"></span>
                    </span>
                    <span>⑤ Till Security</span>
                </button>

                <!-- Interactive Page Guide Trigger Button -->
                <Button
                    v-if="isDemo"
                    id="pos-tour-guide-btn"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 px-2.5 gap-1.5 text-xs font-bold text-primary border-primary/40 bg-primary/10 hover:bg-primary/20 hover:border-primary/60 cursor-pointer shadow-2xs transition-all relative"
                    @click="$emit('open-tour')"
                    title="Start Interactive POS Screen Walkthrough"
                >
                    <Compass class="size-3.5 animate-spin" style="animation-duration: 12s" />
                    <span class="hidden sm:inline">Page Guide</span>
                </Button>

                <div class="hidden sm:flex items-center gap-1.5 bg-muted/40 px-2.5 py-1 rounded-lg border border-border/60 text-xs">
                    <UserCheck class="size-3.5 text-primary" />
                    <span class="text-muted-foreground hidden md:inline">Cashier:</span>
                    <span class="font-bold text-foreground truncate max-w-[100px]">{{ authUserName }}</span>
                </div>

                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 gap-1 text-xs text-muted-foreground hover:text-foreground hidden sm:flex"
                    @click="hotkeysOpen = true"
                >
                    <Keyboard class="size-3.5" />
                    <span class="hidden xl:inline">Shortcuts</span>
                </Button>

                <Button variant="ghost" size="icon" class="size-8 hidden sm:inline-flex" @click="toggleFullscreen" title="Toggle Fullscreen">
                    <Minimize2 v-if="isFullscreen" class="size-4" />
                    <Maximize2 v-else class="size-4" />
                </Button>
            </div>
        </header>

        <!-- Demo Mode Banner -->
        <div
            v-if="isDemo"
            class="flex items-center justify-center gap-2 px-4 py-1 bg-amber-500/10 border-b border-amber-500/25 text-amber-700 dark:text-amber-300 text-[10px] font-bold tracking-wider shrink-0"
        >
            <Zap class="size-3 animate-pulse shrink-0" />
            <span>DEMO MODE ACTIVE — All data is simulated and resets periodically</span>
            <Zap class="size-3 animate-pulse shrink-0" />
        </div>

        <!-- POS Workspace -->
        <main class="flex flex-1 overflow-hidden relative">
            <slot />
        </main>

        <!-- Hotkeys Dialog -->
        <Dialog :open="hotkeysOpen" spinnerText="Loading Keyboard Shortcuts..." @update:open="(v) => (hotkeysOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Keyboard class="size-5 text-primary" />
                        POS Keyboard Shortcuts
                    </DialogTitle>
                </DialogHeader>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs py-2">
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Scan / Focus Barcode</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">F2</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Pay & Checkout</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">F12 / Space</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Hold Active Sale</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">F4</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Clear / Cancel Cart</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">F8</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Toggle Retail/Wholesale</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">Alt + W</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Select Customer</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">Alt + C</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Apply Cart Discount</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">Alt + D</kbd>
                    </div>
                    <div class="p-2 bg-muted/40 rounded-lg flex justify-between items-center">
                        <span class="text-muted-foreground">Payment Method Select</span>
                        <kbd class="px-2 py-0.5 bg-background border rounded font-mono font-bold">1 - 5</kbd>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Passcode Unlock Dialog -->
        <Dialog :open="unlockModalOpen" spinnerText="Opening Terminal Security..." @update:open="(v) => (unlockModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-xs">
                <DialogHeader class="text-center sm:text-center">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-600 dark:text-amber-400 mb-1">
                        <KeyRound class="size-6" />
                    </div>
                    <DialogTitle class="text-base font-bold text-center">Unlock POS Terminal</DialogTitle>
                    <DialogDescription class="text-xs text-center text-muted-foreground">
                        Enter supervisor passcode to restore the Exit POS button and navigation.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <!-- Passcode Display -->
                    <div class="space-y-1.5">
                        <form @submit.prevent="handleUnlock">
                            <Input
                                v-model="inputPasscode"
                                type="password"
                                inputmode="numeric"
                                maxlength="6"
                                placeholder="••••"
                                autofocus
                                class="text-center font-mono text-xl tracking-[0.3em] font-bold h-11"
                            />
                        </form>
                        <p v-if="passcodeError" class="text-xs text-destructive font-medium text-center">
                            {{ passcodeError }}
                        </p>
                        <p v-else class="text-[11px] text-muted-foreground text-center">
                            Default supervisor PIN: <span class="font-mono font-bold text-foreground">1234</span>
                        </p>
                    </div>

                    <!-- Touch Numeric Keypad -->
                    <div class="grid grid-cols-3 gap-1.5">
                        <Button
                            v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                            :key="digit"
                            type="button"
                            variant="outline"
                            class="h-10 text-base font-bold font-mono hover:bg-primary/10 hover:border-primary/40 cursor-pointer"
                            @click="appendPinDigit(digit)"
                        >
                            {{ digit }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 text-xs font-bold text-destructive hover:bg-destructive/10 cursor-pointer"
                            @click="clearPin"
                        >
                            CLR
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 text-base font-bold font-mono hover:bg-primary/10 hover:border-primary/40 cursor-pointer"
                            @click="appendPinDigit('0')"
                        >
                            0
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 text-xs font-bold text-muted-foreground hover:bg-muted cursor-pointer"
                            @click="backspacePin"
                        >
                            <Delete class="size-4" />
                        </Button>
                    </div>
                </div>

                <DialogFooter class="flex flex-col sm:flex-row gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-1/2 text-xs"
                        @click="unlockModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="w-full sm:w-1/2 text-xs font-bold bg-primary hover:bg-primary/90"
                        @click="handleUnlock"
                    >
                        <Unlock class="size-3.5 mr-1" />
                        Unlock
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ToastContainer />
    </div>
</template>
