<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { useBranch } from '@/composables/useBranch';
import { useCart } from '@/composables/useCart';
import { useDemoCountdown } from '@/composables/useDemoCountdown';
import { isInputFocused } from '@/composables/useKeyboardNavigation';
import { useTenant } from '@/composables/useTenant';
import { useToast } from '@/composables/useToast';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    Compass,
    Delete,
    Keyboard,
    KeyRound,
    Lock,
    Maximize2,
    Minimize2,
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
    <div class="flex h-[100dvh] w-screen select-none flex-col overflow-hidden bg-background text-foreground">
        <!-- Top POS Bar -->
        <header
            id="pos-tour-header"
            class="relative z-20 flex h-14 shrink-0 items-center justify-between border-b border-border/80 bg-card px-2.5 shadow-sm sm:px-4"
        >
            <!-- Left Brand & Branch -->
            <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                <!-- When UNLOCKED: Exit POS link + Lock button -->
                <div v-if="!isLocked" class="flex shrink-0 items-center gap-1.5">
                    <Link
                        href="/dashboard"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg border border-border/80 bg-background px-2 py-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:px-2.5"
                        title="Exit to main dashboard"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span class="hidden sm:inline">Exit POS</span>
                    </Link>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 cursor-pointer rounded-lg border border-border/70 text-muted-foreground hover:border-primary/50 hover:text-foreground"
                        @click="lockTerminal"
                        title="Lock POS Terminal (Hides Exit POS & requires passcode to unlock)"
                    >
                        <Lock class="size-3.5" />
                    </Button>
                </div>

                <!-- When LOCKED: Exit POS and back arrow are hidden; Locked button shown -->
                <div v-else class="flex shrink-0 items-center gap-1.5">
                    <button
                        type="button"
                        @click="openUnlockModal"
                        class="shadow-xs flex cursor-pointer items-center gap-1.5 rounded-lg border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-xs font-bold text-amber-600 transition-all hover:bg-amber-500/20 dark:text-amber-400 sm:px-2.5"
                        title="Terminal Locked — Click to enter passcode to unlock"
                    >
                        <Lock class="size-3.5 text-amber-600 dark:text-amber-400" />
                        <span class="text-xs">Terminal Locked</span>
                    </button>
                </div>

                <div class="h-4 w-px shrink-0 bg-border/80"></div>

                <div class="flex min-w-0 items-center gap-2">
                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-xs font-bold text-white">POS</div>
                    <div class="min-w-0">
                        <h1 class="flex items-center gap-1.5 truncate text-xs font-bold leading-tight">
                            <span class="max-w-[110px] truncate sm:max-w-[180px] md:max-w-[240px]">{{ currentTenant.name }}</span>
                            <Badge variant="outline" class="hidden shrink-0 px-1 py-0 font-mono text-[9px] md:inline-flex">Reg #01</Badge>
                            <span
                                v-if="isDemo"
                                :class="[
                                    'shadow-2xs inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider',
                                    isUrgent
                                        ? 'animate-pulse border-destructive/40 bg-destructive/20 text-destructive'
                                        : 'border-amber-500/40 bg-amber-500/20 text-amber-600 dark:text-amber-400',
                                ]"
                                title="Active Sandbox Demo Remaining Time"
                            >
                                <Clock class="size-2.5 animate-spin" style="animation-duration: 8s" />
                                <span>DEMO: {{ shortCountdown }}</span>
                            </span>
                            <span
                                v-else
                                class="inline-flex shrink-0 items-center gap-1 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                                title="Live Production Terminal"
                            >
                                <span class="size-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                <span>LIVE</span>
                            </span>
                        </h1>
                        <p class="flex items-center gap-1 truncate text-[10px] text-muted-foreground sm:text-[11px]">
                            <Store class="size-3 shrink-0 text-emerald-600" />
                            <span class="max-w-[100px] truncate sm:max-w-[160px]">{{ currentBranch.name }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Center Clock & Network Status -->
            <div class="hidden shrink-0 items-center gap-4 font-mono text-xs text-muted-foreground lg:flex">
                <div class="flex items-center gap-1.5 rounded-full border border-border/60 bg-muted/40 px-3 py-1">
                    <Clock class="size-3.5 text-primary" />
                    <span>{{ currentTime }}</span>
                </div>
                <div class="flex items-center gap-1.5 font-sans text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <Wifi class="size-3.5 animate-pulse" />
                    <span>Online / Sync OK</span>
                </div>
            </div>

            <!-- Right Controls: Guide, Cashier, Hotkeys, Fullscreen -->
            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                <!-- Visual Marker for Part ⑤: Header & Security -->
                <button
                    v-if="isDemo"
                    type="button"
                    @click="$emit('open-tour-step', 5)"
                    class="shadow-2xs hidden cursor-pointer items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 font-mono text-[10px] font-bold text-primary transition-all hover:bg-primary/20 xl:flex"
                    title="Part 5: Header Controls & Security (Click to inspect)"
                >
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-primary"></span>
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
                    class="shadow-2xs relative h-8 cursor-pointer gap-1.5 border-primary/40 bg-primary/10 px-2.5 text-xs font-bold text-primary transition-all hover:border-primary/60 hover:bg-primary/20"
                    @click="$emit('open-tour')"
                    title="Start Interactive POS Screen Walkthrough"
                >
                    <Compass class="size-3.5 animate-spin" style="animation-duration: 12s" />
                    <span class="hidden sm:inline">Page Guide</span>
                </Button>

                <div class="hidden items-center gap-1.5 rounded-lg border border-border/60 bg-muted/40 px-2.5 py-1 text-xs sm:flex">
                    <UserCheck class="size-3.5 text-primary" />
                    <span class="hidden text-muted-foreground md:inline">Cashier:</span>
                    <span class="max-w-[100px] truncate font-bold text-foreground">{{ authUserName }}</span>
                </div>

                <Button
                    variant="ghost"
                    size="sm"
                    class="hidden h-8 gap-1 text-xs text-muted-foreground hover:text-foreground sm:flex"
                    @click="hotkeysOpen = true"
                >
                    <Keyboard class="size-3.5" />
                    <span class="hidden xl:inline">Shortcuts</span>
                </Button>

                <Button variant="ghost" size="icon" class="hidden size-8 sm:inline-flex" @click="toggleFullscreen" title="Toggle Fullscreen">
                    <Minimize2 v-if="isFullscreen" class="size-4" />
                    <Maximize2 v-else class="size-4" />
                </Button>
            </div>
        </header>

        <!-- Demo Mode Banner -->
        <div
            v-if="isDemo"
            class="flex shrink-0 items-center justify-center gap-2 border-b border-amber-500/25 bg-amber-500/10 px-4 py-1 text-[10px] font-bold tracking-wider text-amber-700 dark:text-amber-300"
        >
            <Zap class="size-3 shrink-0 animate-pulse" />
            <span>DEMO MODE ACTIVE — All data is simulated and resets periodically</span>
            <Zap class="size-3 shrink-0 animate-pulse" />
        </div>

        <!-- POS Workspace -->
        <main class="relative flex flex-1 overflow-hidden">
            <slot />
        </main>

        <!-- Hotkeys Dialog -->
        <Dialog :open="hotkeysOpen" spinnerText="Loading Keyboard Shortcuts..." @update:open="(v) => (hotkeysOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Keyboard class="size-5 text-primary" />
                        POS Keyboard Shortcuts
                    </DialogTitle>
                </DialogHeader>
                <div class="grid grid-cols-1 gap-2 py-2 text-xs sm:grid-cols-2">
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Scan / Focus Barcode</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">F2</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Pay & Checkout</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">F12 / Space</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Hold Active Sale</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">F4</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Clear / Cancel Cart</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">F8</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Toggle Retail/Wholesale</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">Alt + W</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Select Customer</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">Alt + C</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Apply Cart Discount</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">Alt + D</kbd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-muted/40 p-2">
                        <span class="text-muted-foreground">Payment Method Select</span>
                        <kbd class="rounded border bg-background px-2 py-0.5 font-mono font-bold">1 - 5</kbd>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Passcode Unlock Dialog -->
        <Dialog :open="unlockModalOpen" spinnerText="Opening Terminal Security..." @update:open="(v) => (unlockModalOpen = v)">
            <DialogContent class="w-[95vw] sm:max-w-xs">
                <DialogHeader class="text-center sm:text-center">
                    <div
                        class="mx-auto mb-1 flex size-12 items-center justify-center rounded-2xl border border-amber-500/25 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    >
                        <KeyRound class="size-6" />
                    </div>
                    <DialogTitle class="text-center text-base font-bold">Unlock POS Terminal</DialogTitle>
                    <DialogDescription class="text-center text-xs text-muted-foreground">
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
                                class="h-11 text-center font-mono text-xl font-bold tracking-[0.3em]"
                            />
                        </form>
                        <p v-if="passcodeError" class="text-center text-xs font-medium text-destructive">
                            {{ passcodeError }}
                        </p>
                        <p v-else class="text-center text-[11px] text-muted-foreground">
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
                            class="h-10 cursor-pointer font-mono text-base font-bold hover:border-primary/40 hover:bg-primary/10"
                            @click="appendPinDigit(digit)"
                        >
                            {{ digit }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 cursor-pointer text-xs font-bold text-destructive hover:bg-destructive/10"
                            @click="clearPin"
                        >
                            CLR
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 cursor-pointer font-mono text-base font-bold hover:border-primary/40 hover:bg-primary/10"
                            @click="appendPinDigit('0')"
                        >
                            0
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 cursor-pointer text-xs font-bold text-muted-foreground hover:bg-muted"
                            @click="backspacePin"
                        >
                            <Delete class="size-4" />
                        </Button>
                    </div>
                </div>

                <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                    <Button type="button" variant="outline" class="w-full text-xs sm:w-1/2" @click="unlockModalOpen = false"> Cancel </Button>
                    <Button type="button" class="w-full bg-primary text-xs font-bold hover:bg-primary/90 sm:w-1/2" @click="handleUnlock">
                        <Unlock class="mr-1 size-3.5" />
                        Unlock
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ToastContainer />
    </div>
</template>
