<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    ACTION_SHORTCUTS,
    NAVIGATION_SHORTCUTS,
    POS_SHORTCUTS,
    useKeyboardNavigation,
} from '@/composables/useKeyboardNavigation';
import { Command, Compass, Keyboard, Navigation, ShoppingBag, Sparkles, X } from 'lucide-vue-next';
import { ref } from 'vue';

const { shortcutsDialogOpen, closeShortcuts } = useKeyboardNavigation();
const activeTab = ref<'navigation' | 'actions' | 'pos'>('navigation');
</script>

<template>
    <Dialog :open="shortcutsDialogOpen" spinnerText="Loading Keyboard Shortcuts..." @update:open="(v) => (shortcutsDialogOpen = v)">
        <DialogContent class="w-[95vw] sm:max-w-2xl p-0 overflow-hidden border border-border/80 shadow-2xl bg-card rounded-2xl">
            <DialogHeader class="px-5 py-4 border-b border-border/70 bg-muted/20 flex flex-row items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 text-primary">
                        <Keyboard class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold text-foreground flex items-center gap-2">
                            Keyboard Shortcuts & Navigation
                            <Badge variant="outline" class="text-[10px] font-mono py-0 font-normal">Global</Badge>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Operate the entire POS and backoffice using keyboard keys
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Category Switcher Tabs -->
            <div class="flex border-b border-border/70 bg-background/50 px-4 pt-2 gap-1 text-xs">
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-2 border-b-2 font-semibold transition-all cursor-pointer',
                        activeTab === 'navigation'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'navigation'"
                >
                    <Navigation class="size-3.5" />
                    <span>Quick Navigation</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-muted font-mono font-normal">13</span>
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-2 border-b-2 font-semibold transition-all cursor-pointer',
                        activeTab === 'actions'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'actions'"
                >
                    <Command class="size-3.5" />
                    <span>Page Actions & Modals</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-muted font-mono font-normal">8</span>
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-2 border-b-2 font-semibold transition-all cursor-pointer',
                        activeTab === 'pos'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'pos'"
                >
                    <ShoppingBag class="size-3.5" />
                    <span>POS Terminal Keys</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-muted font-mono font-normal">8</span>
                </button>
            </div>

            <!-- Tab Content -->
            <div class="p-4 sm:p-5 max-h-[60vh] overflow-y-auto">
                <!-- Navigation Shortcuts List -->
                <div v-if="activeTab === 'navigation'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div
                        v-for="s in NAVIGATION_SHORTCUTS"
                        :key="s.label"
                        class="flex items-center justify-between p-2.5 rounded-xl border border-border/70 bg-card hover:bg-muted/30 transition-all text-xs"
                    >
                        <span class="text-foreground font-medium">{{ s.description }}</span>
                        <kbd class="px-2 py-1 rounded-md border border-border/80 bg-background font-mono text-[11px] font-bold text-foreground shadow-2xs">
                            {{ s.label }}
                        </kbd>
                    </div>
                </div>

                <!-- Page Actions List -->
                <div v-else-if="activeTab === 'actions'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div
                        v-for="s in ACTION_SHORTCUTS"
                        :key="s.label"
                        class="flex items-center justify-between p-2.5 rounded-xl border border-border/70 bg-card hover:bg-muted/30 transition-all text-xs"
                    >
                        <span class="text-foreground font-medium">{{ s.description }}</span>
                        <kbd class="px-2 py-1 rounded-md border border-border/80 bg-background font-mono text-[11px] font-bold text-foreground shadow-2xs">
                            {{ s.label }}
                        </kbd>
                    </div>
                </div>

                <!-- POS Keys List -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div
                        v-for="s in POS_SHORTCUTS"
                        :key="s.label"
                        class="flex items-center justify-between p-2.5 rounded-xl border border-border/70 bg-card hover:bg-muted/30 transition-all text-xs"
                    >
                        <span class="text-foreground font-medium">{{ s.description }}</span>
                        <kbd class="px-2 py-1 rounded-md border border-emerald-500/30 bg-emerald-500/10 font-mono text-[11px] font-bold text-emerald-700 dark:text-emerald-400 shadow-2xs">
                            {{ s.label }}
                        </kbd>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-border/70 bg-muted/20 flex items-center justify-between text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5">
                    <Sparkles class="size-3.5 text-primary" />
                    Press <kbd class="px-1.5 py-0.5 rounded bg-background border font-mono text-[10px]">?</kbd> anywhere to toggle this guide
                </span>
                <Button variant="outline" size="sm" class="h-7 text-xs" @click="closeShortcuts">
                    Close (Esc)
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
