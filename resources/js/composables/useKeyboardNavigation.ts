import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

export interface ShortcutItem {
    key: string;
    altKey?: boolean;
    ctrlKey?: boolean;
    path?: string;
    label: string;
    description: string;
    category: 'navigation' | 'actions' | 'pos';
}

export const NAVIGATION_SHORTCUTS: ShortcutItem[] = [
    { key: '1', altKey: true, path: '/dashboard', label: 'Alt + 1', description: 'Go to Dashboard', category: 'navigation' },
    { key: '2', altKey: true, path: '/pos', label: 'Alt + 2', description: 'Go to POS Terminal', category: 'navigation' },
    { key: '3', altKey: true, path: '/products', label: 'Alt + 3', description: 'Go to Products Catalog', category: 'navigation' },
    { key: '4', altKey: true, path: '/products/categories', label: 'Alt + 4', description: 'Go to Product Categories', category: 'navigation' },
    { key: '5', altKey: true, path: '/inventory', label: 'Alt + 5', description: 'Go to Inventory & Stock', category: 'navigation' },
    { key: '6', altKey: true, path: '/sales', label: 'Alt + 6', description: 'Go to Sales & Receipts', category: 'navigation' },
    { key: '7', altKey: true, path: '/purchases', label: 'Alt + 7', description: 'Go to Purchases & Suppliers', category: 'navigation' },
    { key: '8', altKey: true, path: '/customers', label: 'Alt + 8', description: 'Go to Customers & CRM', category: 'navigation' },
    { key: '9', altKey: true, path: '/reports', label: 'Alt + 9', description: 'Go to Reports & Analytics', category: 'navigation' },
    { key: 'B', altKey: true, path: '/branches', label: 'Alt + B', description: 'Go to Branches & Outlets', category: 'navigation' },
    { key: 'U', altKey: true, path: '/users', label: 'Alt + U', description: 'Go to Staff & Permissions', category: 'navigation' },
    { key: 'E', altKey: true, path: '/expenses', label: 'Alt + E', description: 'Go to Expenses & Overhead', category: 'navigation' },
    { key: 'S', altKey: true, path: '/settings', label: 'Alt + S', description: 'Go to System Settings', category: 'navigation' },
];

export const ACTION_SHORTCUTS: ShortcutItem[] = [
    { key: '/', label: '/', description: 'Focus Search / Filter Bar on active page', category: 'actions' },
    { key: 'K', ctrlKey: true, label: 'Ctrl + K', description: 'Quick Search / Focus Filter input', category: 'actions' },
    { key: 'N', altKey: true, label: 'Alt + N', description: 'Create New Item (Product, Branch, User, etc.)', category: 'actions' },
    { key: 'Enter', ctrlKey: true, label: 'Ctrl + Enter', description: 'Save & Submit active modal dialog', category: 'actions' },
    { key: 'Esc', label: 'Esc', description: 'Close modal dialog or cancel active popup', category: 'actions' },
    { key: '[', label: '[ / ]', description: 'Navigate Previous / Next table page', category: 'actions' },
    { key: 'B', ctrlKey: true, label: 'Ctrl + B', description: 'Toggle Left Sidebar collapse', category: 'actions' },
    { key: '?', label: '?', description: 'Open Keyboard Shortcuts Cheatsheet', category: 'actions' },
];

export const POS_SHORTCUTS: ShortcutItem[] = [
    { key: 'F2', label: 'F2', description: 'Focus Barcode Scanner / Search Bar', category: 'pos' },
    { key: 'F12', label: 'F12 / Space', description: 'Proceed to Pay & Checkout', category: 'pos' },
    { key: 'F4', label: 'F4', description: 'Hold Active Sale / Suspend Cart', category: 'pos' },
    { key: 'F8', label: 'F8', description: 'Clear / Cancel Active Cart', category: 'pos' },
    { key: 'W', altKey: true, label: 'Alt + W', description: 'Toggle Retail / Wholesale Pricing', category: 'pos' },
    { key: 'C', altKey: true, label: 'Alt + C', description: 'Select or Register Customer', category: 'pos' },
    { key: 'D', altKey: true, label: 'Alt + D', description: 'Apply Cart Discount', category: 'pos' },
    { key: '1-5', label: '1 - 5', description: 'Select Payment Method inside Payment Modal', category: 'pos' },
];

export function isInputFocused(): boolean {
    if (typeof document === 'undefined') return false;
    const active = document.activeElement;
    if (!active) return false;
    const tag = active.tagName.toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || (active as HTMLElement).isContentEditable;
}

export function focusSearchInput(): boolean {
    if (typeof document === 'undefined') return false;
    const searchEl = document.querySelector<HTMLInputElement>(
        'input[type="search"], input[data-search-input], input[id*="search" i], input[placeholder*="search" i], input[placeholder*="filter" i], input[placeholder*="find" i]',
    );
    if (searchEl) {
        searchEl.focus();
        searchEl.select();
        return true;
    }
    return false;
}

export function triggerNewItem(): boolean {
    if (typeof window === 'undefined') return false;
    // Dispatch custom app event first so current page can handle it directly
    const defaultPrevented = !window.dispatchEvent(new CustomEvent('app:new-item', { cancelable: true }));
    if (defaultPrevented) return true;

    // Fallback: look for primary create button
    const btn = document.querySelector<HTMLButtonElement | HTMLAnchorElement>(
        '[data-action="create"], [data-action="new"], [id*="tour-create" i], [id*="create-btn" i], [id*="add-btn" i], button:has(svg.lucide-plus):not([role="dialog"] button)',
    );
    if (btn) {
        btn.click();
        return true;
    }
    return false;
}

export function triggerSubmitForm(): boolean {
    if (typeof document === 'undefined') return false;
    const defaultPrevented = !window.dispatchEvent(new CustomEvent('app:submit-form', { cancelable: true }));
    if (defaultPrevented) return true;

    const dialogSubmit = document.querySelector<HTMLButtonElement>(
        '[role="dialog"] button[type="submit"], [role="dialog"] .bg-primary:not(:disabled), [role="dialog"] button:has(svg.lucide-check):not(:disabled)',
    );
    if (dialogSubmit) {
        dialogSubmit.click();
        return true;
    }
    return false;
}

const shortcutsDialogOpen = ref(false);

export function useKeyboardNavigation() {
    const openShortcuts = () => {
        shortcutsDialogOpen.value = true;
    };
    const closeShortcuts = () => {
        shortcutsDialogOpen.value = false;
    };
    const toggleShortcuts = () => {
        shortcutsDialogOpen.value = !shortcutsDialogOpen.value;
    };

    const handleGlobalKeydown = (e: KeyboardEvent) => {
        // Ignore if modifier combinations with shift or other keys not expected
        const isCtrl = e.metaKey || e.ctrlKey;

        // 1. Ctrl + K -> Focus Search
        if (isCtrl && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            focusSearchInput();
            return;
        }

        // 2. Ctrl + / or ? (when not typing in an input) -> Toggle Shortcuts Cheatsheet
        if ((isCtrl && e.key === '/') || (!isInputFocused() && e.key === '?')) {
            e.preventDefault();
            toggleShortcuts();
            return;
        }

        // 3. Alt + Key -> Quick Page Navigation & Primary Page Actions
        if (e.altKey && !isCtrl && !e.metaKey) {
            const keyLower = e.key.toLowerCase();

            // Alt + N -> New Item on current page
            if (keyLower === 'n') {
                e.preventDefault();
                triggerNewItem();
                return;
            }

            const match = NAVIGATION_SHORTCUTS.find((s) => s.key.toLowerCase() === keyLower);
            if (match && match.path) {
                e.preventDefault();
                router.visit(match.path);
                return;
            }
        }

        // 4. Ctrl + Enter -> Submit Active Dialog Form
        if (isCtrl && e.key === 'Enter') {
            const dialog = document.querySelector('[role="dialog"]');
            if (dialog) {
                e.preventDefault();
                triggerSubmitForm();
                return;
            }
        }

        // 5. Single-key shortcuts when NOT typing in an input
        if (!isInputFocused()) {
            // Forward Slash '/' -> Focus Search Input
            if (e.key === '/') {
                e.preventDefault();
                focusSearchInput();
                return;
            }

            // '[' for Previous Page in pagination
            if (e.key === '[') {
                const prev = document.querySelector<HTMLButtonElement>(
                    'button[aria-label*="previous" i], button:has(svg.lucide-chevron-left):not(:disabled)',
                );
                if (prev) {
                    e.preventDefault();
                    prev.click();
                }
            }
            // ']' for Next Page in pagination
            else if (e.key === ']') {
                const next = document.querySelector<HTMLButtonElement>(
                    'button[aria-label*="next" i], button:has(svg.lucide-chevron-right):not(:disabled)',
                );
                if (next) {
                    e.preventDefault();
                    next.click();
                }
            }
        }
    };

    return {
        shortcutsDialogOpen,
        openShortcuts,
        closeShortcuts,
        toggleShortcuts,
        handleGlobalKeydown,
        NAVIGATION_SHORTCUTS,
        ACTION_SHORTCUTS,
        POS_SHORTCUTS,
        isInputFocused,
        focusSearchInput,
        triggerNewItem,
        triggerSubmitForm,
    };
}
