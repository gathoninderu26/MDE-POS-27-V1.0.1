<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Check,
    Clock,
    Compass,
    DollarSign,
    Download,
    Edit,
    FileSpreadsheet,
    FolderTree,
    Info,
    Keyboard,
    Package,
    Plus,
    Printer,
    RefreshCcw,
    ScanBarcode,
    Search,
    ShieldCheck,
    Tag,
    TrendingUp,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    open: boolean;
    currentStep?: number;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    currentStep: 0,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:currentStep', step: number): void;
    (e: 'action', actionType: string): void;
}>();

export interface TourSection {
    id: string;
    targetId: string;
    fallbackTargetId?: string;
    stepNumber: number;
    title: string;
    tag: string;
    shape: 'rect' | 'circle';
    rx: number;
    icon: any;
    usedFor: string;
    capabilities: string[];
    howToUse: string[];
    proTip: string;
    shortcuts?: string;
    actionLabel?: string;
    actionType?: string;
    preferredPlacement?: 'bottom-right' | 'bottom-left' | 'left' | 'right' | 'bottom' | 'top';
}

const sections: TourSection[] = [
    {
        id: 'start',
        targetId: 'product-tour-guide-btn',
        stepNumber: 0,
        title: 'Product Catalog Master Guide',
        tag: 'INTERACTIVE WALKTHROUGH',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        preferredPlacement: 'bottom-right',
        usedFor: 'Welcome to your Product Master Catalog! This interactive walkthrough covers every section: stock valuation telemetry, hardware barcode auto-lookup, category management, universal search, tier pricing, live stock health, 1-click audited adjustments, and thermal shelf label printing.',
        capabilities: [
            'Master Inventory Control & Stock Valuation',
            'Hardware Barcode Scanner Gun Detection',
            'Dual Tier Pricing & Gross Margin Telemetry',
            'Audited Adjustments & Thermal Label Printing',
        ],
        howToUse: [
            'Click "Start Tour" or press → on your keyboard to navigate through each section.',
            'Press Esc at any time to exit or click any interactive button to try features directly.',
        ],
        proTip: 'You can re-launch this interactive tour anytime by clicking "Page Guide" in the top-right toolbar.',
        shortcuts: 'Click "Start Tour" • Esc (Exit) • ← / → (Browse)',
        actionLabel: 'Start Tour',
        actionType: 'start-walkthrough',
    },
    {
        id: 'stats',
        targetId: 'product-step-stats',
        stepNumber: 1,
        title: 'Inventory Valuation & Margin Telemetry',
        tag: 'CAPITAL & PROFITABILITY',
        shape: 'rect',
        rx: 16,
        icon: TrendingUp,
        preferredPlacement: 'bottom',
        usedFor: 'Live financial breakdown of your active catalog: Total SKU Count, Stock Cost Value (capital tied up at cost), Total Projected Retail Revenue, Catalog Average Gross Margin %, and Out-of-Stock Counters.',
        capabilities: [
            'Working Capital at Cost Valuation',
            'Projected Total Retail Sales Value',
            'Catalog-Wide Gross Margin % Calculation',
            'Stock Depletion & Out-of-Stock Counters',
        ],
        howToUse: [
            'Compare Stock Cost Value against Retail Value to understand overall markup and capital investment.',
            'Monitor the Gross Margin Potential % to ensure store pricing targets maintain healthy profitability.',
            'Keep an eye on the Stock Health card to identify depleted items needing immediate restock.',
        ],
        proTip: 'Valuation figures automatically re-calculate whenever incoming supplier purchase invoices are confirmed or sales occur at POS terminals.',
    },
    {
        id: 'alerts',
        targetId: 'product-step-alerts',
        fallbackTargetId: 'product-step-stats',
        stepNumber: 2,
        title: 'Reorder Triggers & 90-Day Expiry Warnings',
        tag: 'EARLY WARNING INTELLIGENCE',
        shape: 'rect',
        rx: 14,
        icon: AlertTriangle,
        preferredPlacement: 'bottom',
        usedFor: 'Automated early-warning safety system that flags items dipped below minimum reorder thresholds, plus perishable products expiring within the next 90 days for retail, pharmacy, and grocery.',
        capabilities: [
            'Safety Stock Threshold Depletion Warnings',
            'Real-Time Remaining Stock Pill Counters',
            '90-Day Expiry Countdown (Pharmacy / Perishables)',
            'Prevents Lost Sales & Shelf Stockouts',
        ],
        howToUse: [
            'Review low-stock badges to prioritize purchase orders before shelves run completely empty.',
            'For healthcare and perishables, review the 90-day countdown to rotate older batches (FEFO) or run clearances.',
        ],
        proTip: 'Maintain realistic minimum safety stock levels per fast-moving SKU in the product drawer to guarantee 100% customer fulfillment.',
    },
    {
        id: 'categories',
        targetId: 'product-step-categories',
        stepNumber: 3,
        title: 'Aisle & Department Category Pills',
        tag: 'INSTANT AISLE FILTERING',
        shape: 'rect',
        rx: 14,
        icon: FolderTree,
        preferredPlacement: 'bottom',
        usedFor: 'Ultra-fast department filter displaying live SKU counts and color-coded department tags. Switches product views in under 50 milliseconds with zero page reload latency.',
        capabilities: [
            'Instant 1-Click Client-Side Filtering',
            'Real-Time SKU Count per Category',
            'Color-Coded Department Badges',
            'Direct Shortcut to Category Hierarchy',
        ],
        howToUse: [
            'Click any department pill to immediately isolate that category in the catalog table.',
            'Click "All" to view your complete multi-department inventory catalog.',
            'Click "Manage Categories →" on the right to edit category colors, tax rates, or default units.',
        ],
        proTip: 'Category tags and color indicators created here automatically propagate to your POS cashier checkout screens.',
    },
    {
        id: 'table',
        targetId: 'product-step-table',
        stepNumber: 4,
        title: 'Universal Search & Master Directory',
        tag: 'CATALOG REGISTRY',
        shape: 'rect',
        rx: 16,
        icon: Search,
        preferredPlacement: 'bottom',
        usedFor: 'Central catalog datatable featuring multi-field instantaneous search matching item name, SKU, barcode, manufacturer brand, generic medical composition, and hardware specifications.',
        capabilities: [
            'Multi-Field Fuzzy Search across 7 attributes',
            'Direct Barcode Scanner Gun Input',
            'Paginated 12-Item Density View',
            'Instant Sorting & Reactive Filtering',
        ],
        howToUse: [
            'Type any item name, SKU, barcode, or brand into the search field to filter rows immediately.',
            'Plug in a barcode gun and scan any product to instantly navigate to its inventory record.',
            'Click on any product row to open the complete Product Insight modal with historical stock movements.',
        ],
        proTip: 'The search field accepts barcode gun scans directly, making item price and stock lookups instant for store clerks.',
        actionLabel: 'Focus Catalog Search',
        actionType: 'focus-search',
    },
    {
        id: 'product-col',
        targetId: 'product-step-col-product',
        fallbackTargetId: 'product-step-table',
        stepNumber: 5,
        title: 'Product Identity & Vertical Metadata',
        tag: 'ITEM IDENTIFIERS & SPECS',
        shape: 'rect',
        rx: 12,
        icon: Tag,
        preferredPlacement: 'right',
        usedFor: 'Master product identity display showing product name, SKU, manufacturer barcode, and industry-tailored metadata (Rx prescription flags & generic names for pharmacy, technical dimensions for hardware, IMEI/serial numbers for electronics).',
        capabilities: [
            'Industry Vertical Metadata (Rx Only, Generic Names, IMEI)',
            'Dual SKU and Barcode Display with Scanner Icon',
            'Dynamic Vertical Category Icons (Pill, Wrench, Package)',
            'Click-to-Open Movement Audit Trail Modal',
        ],
        howToUse: [
            'Click the product name or icon to open the complete Product Insight modal.',
            'Review vertical-specific secondary badges for pharmaceutical prescriptions, technical specs, or device serials.',
        ],
        proTip: 'Clicking any product row opens its full stock ledger, detailing who sold, received, or adjusted the item and when.',
        actionLabel: 'View Product Details',
        actionType: 'open-product-detail',
    },
    {
        id: 'price-col',
        targetId: 'product-step-col-price',
        fallbackTargetId: 'product-step-table',
        stepNumber: 6,
        title: 'Tiered Pricing & Profit Margins',
        tag: 'RETAIL & WHOLESALE TIERS',
        shape: 'rect',
        rx: 12,
        icon: Zap,
        preferredPlacement: 'left',
        usedFor: 'Multi-tier price management displaying cost price, retail selling price, wholesale trade price, and live calculated gross margin % with visual profitability indicators.',
        capabilities: [
            'Dual Retail vs. Wholesale Selling Prices',
            'Unit Cost Price Display for Staff Transparency',
            'Live Calculated Gross Profit Margin %',
            'Visual Profit Trend Indicators (Trending Up/Down)',
        ],
        howToUse: [
            'Compare retail price against cost price to assess item profit contribution.',
            'Wholesale prices automatically apply when ringing trade customers or bulk orders at checkout.',
        ],
        proTip: 'Gross margins above 30% display in emerald green, 15–29% in amber, and below 15% in rose red to instantly spot margin risks.',
    },
    {
        id: 'stock-col',
        targetId: 'product-step-stock-col',
        fallbackTargetId: 'product-step-table',
        stepNumber: 7,
        title: 'Stock Balances & Visual Health Bars',
        tag: 'INVENTORY BALANCES',
        shape: 'rect',
        rx: 12,
        icon: Package,
        preferredPlacement: 'left',
        usedFor: 'Live on-hand stock quantity display with measurement units (Pcs, Kg, Liters, Boxes) paired with a visual progress bar comparing current stock against 3× safety stock.',
        capabilities: [
            'Real-Time Physical Stock Count Display',
            'Tailored Unit of Measurement Badges',
            'Visual Depletion Health Progress Bar',
            'Color-Coded Status (In Stock, Low Stock, Depleted)',
        ],
        howToUse: [
            'Watch the progress bar beneath the quantity: a short amber bar signals stock is below the safety threshold.',
            'Out-of-stock items display a high-visibility red badge to prompt instant reorder.',
        ],
        proTip: 'Physical stock counts update in real time every time a cashier completes a sale or an invoice is uploaded.',
    },
    {
        id: 'expiry-col',
        targetId: 'product-step-col-expiry',
        fallbackTargetId: 'product-step-table',
        stepNumber: 8,
        title: 'Batch Expiry & Shelf Life Tracking',
        tag: 'EXPIRATION INTELLIGENCE',
        shape: 'rect',
        rx: 12,
        icon: Clock,
        preferredPlacement: 'left',
        usedFor: 'Batch expiration date tracking for healthcare, pharmacy, food, and perishables. Displays exact expiration date with an active remaining days countdown.',
        capabilities: [
            'Remaining Days Countdown (e.g. 45d left)',
            'Color-Coded Expiry Urgency (Rose ≤ 30 days, Amber ≤ 90 days)',
            'First-Expiry First-Out (FEFO) Shelf Rotation',
            'Prevents Expired Goods from Reaching Customers',
        ],
        howToUse: [
            'Check the countdown badge to identify aging batches nearing expiration.',
            'Products with ≤ 30 days remaining should be prioritized for shelf clearance or supplier credit return.',
        ],
        proTip: 'Batch numbers and expiry dates captured during invoice upload or purchase orders automatically feed into this column.',
    },
    {
        id: 'adjust-btn',
        targetId: 'product-step-adjust-btn',
        fallbackTargetId: 'product-step-table',
        stepNumber: 9,
        title: 'Audited Stock Adjustments & Corrections',
        tag: 'WAREHOUSE AUDIT & WRITE-OFFS',
        shape: 'rect',
        rx: 12,
        icon: ShieldCheck,
        preferredPlacement: 'left',
        usedFor: 'Instant stock correction modal for reconciling physical warehouse counts, receiving spot deliveries, or writing off damaged, expired, or stolen items.',
        capabilities: [
            '3 Correction Modes (Exact Count, Add Stock, Deduct Damage)',
            'Audited Reason Tracking (Count, Breakage, Return, Expiry)',
            'Live Before-and-After Quantity Preview',
            'Manager Signature Stamp & Timestamp Logs',
        ],
        howToUse: [
            'Click the "Stock" button on any product row to launch the adjustment modal.',
            'Select the adjustment mode and enter the quantity change.',
            'Choose an audited reason (e.g. Physical Count, Damage) and click "Save Adjustment".',
        ],
        proTip: 'Every adjustment updates the inventory ledger immediately and records the manager ID, timestamp, and audit reason in the movement history.',
        actionLabel: 'Try Stock Adjustment',
        actionType: 'open-stock-adjust',
    },
    {
        id: 'barcode-col',
        targetId: 'product-step-barcode-col',
        fallbackTargetId: 'product-step-table',
        stepNumber: 10,
        title: 'Thermal Shelf Price Tags & Barcodes',
        tag: 'THERMAL PRINTING ENGINE',
        shape: 'rect',
        rx: 12,
        icon: Printer,
        preferredPlacement: 'left',
        usedFor: 'Integrated thermal barcode generator. Formats compliant EAN-13, UPC, and Code-128 barcodes and shelf-edge price tags with store branding and KES currency for 58mm or 80mm thermal printers.',
        capabilities: [
            'EAN-13, UPC & Code-128 Barcode Generation',
            'Standard Thermal Paper Sizes (50x30mm, 40x25mm)',
            'Batch Label Quantity Multiplier',
            'Direct Web Browser Thermal Printing',
        ],
        howToUse: [
            'Click the printer icon on any row to open the Thermal Label Printing dialog.',
            'Set the number of labels to print and select label roll dimensions.',
            'Click "Print Labels" to stream formatted price tags directly to your ESC/POS label printer.',
        ],
        proTip: 'Print shelf price tags to affix directly on store shelf gondolas so cashiers or customers can scan items with mobile or handheld barcode guns.',
        actionLabel: 'Open Label Printing Modal',
        actionType: 'open-barcode-label',
    },
    {
        id: 'edit-btn',
        targetId: 'product-step-edit-btn',
        fallbackTargetId: 'product-step-table',
        stepNumber: 11,
        title: 'Product Lifecycle Editing & Audit Logs',
        tag: 'LIFECYCLE MANAGEMENT',
        shape: 'rect',
        rx: 12,
        icon: Edit,
        preferredPlacement: 'left',
        usedFor: 'Complete product lifecycle control: update pricing tiers, reorder levels, barcodes, tax rates, or supplier links via the slide-over drawer, or safely archive obsolete items.',
        capabilities: [
            'Full Slide-Over Edit Drawer with Live Validation',
            'Instant Margin & Markup Re-Calculation',
            'Supplier Linkage & Unit Configuration',
            'Historical Stock Movement Timeline',
        ],
        howToUse: [
            'Click the Edit pencil button on any product row to modify specifications, prices, or reorder levels.',
            'Click Save Changes to synchronize changes across all checkout terminals instantly.',
        ],
        proTip: 'Updating a price in the product drawer updates the checkout price immediately across all active POS terminals without needing a restart.',
        actionLabel: 'Edit First Product',
        actionType: 'open-edit-drawer',
    },
    {
        id: 'actions',
        targetId: 'product-step-export-btn',
        fallbackTargetId: 'product-step-actions',
        stepNumber: 12,
        title: 'Catalog Operations & CSV Export',
        tag: 'OFFLINE AUDIT & BACKUP',
        shape: 'rect',
        rx: 12,
        icon: FileSpreadsheet,
        preferredPlacement: 'bottom-right',
        usedFor: 'Export your complete master catalog to CSV with 1 click. Generates an offline Excel-compatible spreadsheet capturing SKUs, barcodes, pricing, and stock levels for periodic audits or executive accounting.',
        capabilities: [
            'Full Catalog CSV Export in 1-Click',
            'Excel & Google Sheets Compatible Format',
            'Complete Snapshot of All Product Fields',
            'Safe Version Rollback Backup',
        ],
        howToUse: [
            'Click "Export CSV" to immediately download a spreadsheet backup of your inventory.',
            'Review or modify product costs and prices offline in Excel as needed.',
        ],
        proTip: 'Always export an updated CSV backup before importing supplier batches or performing annual inventory write-offs.',
        shortcuts: 'Alt + E (Export CSV)',
    },
    {
        id: 'import-btn',
        targetId: 'product-step-import-btn',
        fallbackTargetId: 'product-step-actions',
        stepNumber: 13,
        title: 'Bulk Spreadsheet Import & Migration',
        tag: 'SUPPLIER CATALOG ONBOARDING',
        shape: 'rect',
        rx: 12,
        icon: Download,
        preferredPlacement: 'bottom-right',
        usedFor: 'Bulk-upload supplier product catalogs, price lists, or legacy POS exports directly via Excel or CSV spreadsheets. Automatically maps columns, detects duplicate barcodes, and registers hundreds of SKUs simultaneously.',
        capabilities: [
            'CSV & Excel Spreadsheet File Uploader',
            'Supplier Catalog Template Downloader',
            'Automatic Duplicate SKU Prevention',
            'Batch Category & Tax Assignment',
        ],
        howToUse: [
            'Click "Import" in the toolbar to launch the bulk spreadsheet upload modal.',
            'Download the sample CSV template to format supplier data correctly.',
            'Upload the filled spreadsheet to populate your catalog in seconds.',
        ],
        proTip: 'Use bulk import when migrating from another POS system or onboarding a new vendor shipment with 50+ items.',
        actionLabel: 'Open Import Modal',
        actionType: 'open-import-modal',
    },
    {
        id: 'add-product',
        targetId: 'product-step-add-btn',
        fallbackTargetId: 'product-step-actions',
        stepNumber: 14,
        title: 'Add New Product & Barcode Auto-Fill',
        tag: 'RAPID SKU ONBOARDING',
        shape: 'rect',
        rx: 12,
        icon: Plus,
        preferredPlacement: 'bottom-right',
        usedFor: 'Rapidly enroll individual new products into your master catalog. Connect any USB or Bluetooth barcode gun: scanning a physical product barcode automatically queries international databases to auto-populate the item name, brand, and category.',
        capabilities: [
            'Hardware Barcode Gun Auto-Detection',
            'Global Database Barcode Auto-Lookup',
            'Automatic SKU Number Generator',
            'Category VAT & Unit Inheritance',
            'Dual Pricing (Retail & Wholesale Tiers)',
        ],
        howToUse: [
            'Click "Add New Product" to slide open the product registration drawer.',
            'Scan any barcode with your handheld scanner; details auto-fill in milliseconds.',
            'Set your Cost, Retail, and optional Wholesale price, then click Save Product.',
        ],
        proTip: 'Selecting a category automatically applies its pre-configured VAT tax rate (e.g. 16%) and default measurement unit (Piece, Kg, Box), saving repetitive typing.',
        actionLabel: 'Open Add Product Drawer',
        actionType: 'open-add-drawer',
    },
];

const internalStep = ref(props.currentStep || 0);
const targetRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const cardRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const windowSize = ref({ width: 0, height: 0 });
const cardRef = ref<HTMLElement | null>(null);

watch(
    () => props.currentStep,
    (newStep) => {
        if (newStep !== undefined && newStep !== internalStep.value) {
            internalStep.value = newStep;
            updateTargetPosition();
        }
    }
);

const currentSection = computed(() => {
    return sections[internalStep.value] || sections[0];
});

const updateTargetPosition = () => {
    if (typeof window === 'undefined') return;

    windowSize.value = {
        width: window.innerWidth,
        height: window.innerHeight,
    };

    const sec = currentSection.value;
    if (!sec) return;

    nextTick(() => {
        const el = document.getElementById(sec.targetId) || (sec.fallbackTargetId ? document.getElementById(sec.fallbackTargetId) : null);

        if (!el) {
            targetRect.value = null;
            return;
        }

        // Check if element is currently outside comfortable view
        const initialRect = el.getBoundingClientRect();
        const isOutOfView = initialRect.top < 80 || initialRect.bottom > window.innerHeight - 80;

        if (isOutOfView) {
            // Instant scroll avoids the 400ms smooth animation lag where coordinates are stale
            el.scrollIntoView({ behavior: 'auto', block: 'center' });
        }

        const measure = () => {
            if (!el) return;
            const r = el.getBoundingClientRect();
            targetRect.value = {
                top: Math.max(0, r.top),
                left: Math.max(0, r.left),
                width: r.width,
                height: r.height,
            };
            nextTick(() => {
                if (cardRef.value) {
                    const c = cardRef.value.getBoundingClientRect();
                    cardRect.value = {
                        top: c.top,
                        left: c.left,
                        width: c.width,
                        height: c.height,
                    };
                }
            });
        };

        measure();
        // Secondary confirmation pass after layout settles
        requestAnimationFrame(measure);
    });
};

const nextStep = () => {
    if (internalStep.value < sections.length - 1) {
        internalStep.value++;
        emit('update:currentStep', internalStep.value);
        updateTargetPosition();
    } else {
        close();
    }
};

const prevStep = () => {
    if (internalStep.value > 0) {
        internalStep.value--;
        emit('update:currentStep', internalStep.value);
        updateTargetPosition();
    }
};

const goToStep = (idx: number) => {
    internalStep.value = idx;
    emit('update:currentStep', idx);
    updateTargetPosition();
};

const close = () => {
    emit('update:open', false);
};

const handleAction = () => {
    if (currentSection.value.actionType === 'start-walkthrough') {
        nextStep();
        return;
    }
    if (currentSection.value.actionType) {
        emit('action', currentSection.value.actionType);
    }
};

const handleTargetClick = () => {
    if (currentSection.value.id === 'start') {
        nextStep();
    } else {
        handleAction();
    }
};

// Keyboard navigation
const handleKeyDown = (e: KeyboardEvent) => {
    if (!props.open) return;
    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowRight') {
        nextStep();
    } else if (e.key === 'ArrowLeft') {
        prevStep();
    }
};

watch(
    () => props.open,
    (val) => {
        if (val) {
            updateTargetPosition();
            window.addEventListener('resize', updateTargetPosition);
            window.addEventListener('scroll', updateTargetPosition, true);
            window.addEventListener('keydown', handleKeyDown);
        } else {
            window.removeEventListener('resize', updateTargetPosition);
            window.removeEventListener('scroll', updateTargetPosition, true);
            window.removeEventListener('keydown', handleKeyDown);
        }
    },
    { immediate: true }
);

onMounted(() => {
    if (props.open) {
        updateTargetPosition();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateTargetPosition);
        window.removeEventListener('scroll', updateTargetPosition, true);
        window.removeEventListener('keydown', handleKeyDown);
    }
});

// Calculate intelligent placement for the callout card ensuring optimal viewing and zero overlap with highlighted button/target
const cardStyle = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return {
            bottom: '24px',
            left: '50%',
            transform: 'translateX(-50%)',
            maxWidth: '380px',
            width: 'calc(100% - 32px)',
        };
    }

    const { top, left, width, height } = targetRect.value;
    const isMobile = windowSize.value.width < 768;
    const cardWidth = isMobile ? Math.min(330, windowSize.value.width - 24) : 380;
    const cardHeight = Math.min(cardRect.value?.height || 420, windowSize.value.height - 40);
    const targetCenterX = left + width / 2;
    const targetCenterY = top + height / 2;
    const maxSafeTop = Math.max(16, windowSize.value.height - cardHeight - 16);

    // On mobile screens: smartly position above or below the target button
    if (isMobile) {
        if (targetCenterY > windowSize.value.height / 2) {
            return {
                top: '12px',
                left: '12px',
                right: '12px',
                width: 'auto',
                maxWidth: '360px',
                margin: '0 auto',
            };
        }
        return {
            bottom: '12px',
            left: '12px',
            right: '12px',
            width: 'auto',
            maxWidth: '360px',
            margin: '0 auto',
        };
    }

    const sec = currentSection.value;
    const placement = sec?.preferredPlacement;

    // RULE 1: Side placement (LEFT) -> used for table row buttons (adjust-btn, barcode-col, edit-btn, stock-col, price-col, expiry-col)
    if (placement === 'left') {
        if (left > cardWidth + 20) {
            const idealTop = targetCenterY - cardHeight / 2;
            const safeTop = Math.max(16, Math.min(maxSafeTop, idealTop));
            return {
                top: `${safeTop}px`,
                left: `${left - cardWidth - 16}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // RULE 2: Side placement (RIGHT) -> used for product-col
    if (placement === 'right') {
        if (left + width + cardWidth + 20 < windowSize.value.width) {
            const idealTop = targetCenterY - cardHeight / 2;
            const safeTop = Math.max(16, Math.min(maxSafeTop, idealTop));
            return {
                top: `${safeTop}px`,
                left: `${left + width + 16}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // RULE 3: Top Toolbar buttons (start, import-btn, add-product, actions/export)
    if (top < 140) {
        const topPos = top + height + 14;
        const safeTop = Math.min(maxSafeTop, topPos);

        if (targetCenterX > windowSize.value.width / 2) {
            const rightOffset = Math.max(16, Math.min(windowSize.value.width - cardWidth - 16, windowSize.value.width - (left + width)));
            return {
                top: `${safeTop}px`,
                right: `${rightOffset}px`,
                width: `${cardWidth}px`,
            };
        } else {
            const leftOffset = Math.max(16, Math.min(windowSize.value.width - cardWidth - 16, left));
            return {
                top: `${safeTop}px`,
                left: `${leftOffset}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // RULE 4: Elements in lower half of screen -> Place ABOVE target so card is 100% visible
    if (targetCenterY > windowSize.value.height / 2) {
        const bottomOffset = windowSize.value.height - top + 14;
        const idealLeft = targetCenterX - cardWidth / 2;
        const safeLeft = Math.max(16, Math.min(windowSize.value.width - cardWidth - 16, idealLeft));
        return {
            bottom: `${bottomOffset}px`,
            left: `${safeLeft}px`,
            width: `${cardWidth}px`,
        };
    }

    // RULE 5: Elements in upper half -> Place BELOW target
    const idealTop = top + height + 14;
    const safeTop = Math.min(maxSafeTop, idealTop);
    const idealLeft = targetCenterX - cardWidth / 2;
    const safeLeft = Math.max(16, Math.min(windowSize.value.width - cardWidth - 16, idealLeft));
    return {
        top: `${safeTop}px`,
        left: `${safeLeft}px`,
        width: `${cardWidth}px`,
    };
});

// Speech / Text Bubble Tail computation
const speechBubbleTail = computed(() => {
    if (!targetRect.value || windowSize.value.width === 0) {
        return null;
    }

    const t = targetRect.value;
    const c = cardRect.value || {
        left: 0,
        top: 0,
        width: 380,
        height: 420,
    };

    const targetCenterX = t.left + t.width / 2;
    const targetCenterY = t.top + t.height / 2;

    // CASE 1: Target is above the card
    if (t.top + t.height <= c.top + 24) {
        const offset = Math.max(28, Math.min((c.width || 380) - 28, targetCenterX - c.left));
        return {
            placement: 'top' as const,
            offset,
        };
    }
    // CASE 2: Target is below the card
    if (t.top >= c.top + (c.height || 420) - 24) {
        const offset = Math.max(28, Math.min((c.width || 380) - 28, targetCenterX - c.left));
        return {
            placement: 'bottom' as const,
            offset,
        };
    }
    // CASE 3: Card is on the LEFT, Target is on the RIGHT
    if (c.left + (c.width || 380) <= t.left + 24) {
        const offset = Math.max(24, Math.min((c.height || 420) - 24, targetCenterY - c.top));
        return {
            placement: 'right' as const,
            offset,
        };
    }
    // CASE 4: Card is on the RIGHT, Target is on the LEFT
    const offset = Math.max(24, Math.min((c.height || 420) - 24, targetCenterY - c.top));
    return {
        placement: 'left' as const,
        offset,
    };
});

</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 pointer-events-none select-none font-sans"
            >
                <!-- SVG Dimming Backdrop with Cutout Hole -->
                <svg
                    class="absolute inset-0 w-full h-full pointer-events-none transition-all duration-300"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <!-- Cutout Mask Hole -->
                        <mask id="product-guide-mask">
                            <rect width="100%" height="100%" fill="white" />
                            <rect
                                v-if="targetRect"
                                :x="targetRect.left - 8"
                                :y="targetRect.top - 8"
                                :width="targetRect.width + 16"
                                :height="targetRect.height + 16"
                                :rx="currentSection.rx"
                                fill="black"
                                class="transition-all duration-300 ease-out"
                            />
                        </mask>
                    </defs>

                    <!-- Darkened Canvas Backdrop with Cutout Mask -->
                    <rect
                        width="100%"
                        height="100%"
                        fill="#000000"
                        fill-opacity="0.75"
                        mask="url(#product-guide-mask)"
                        class="pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole: clicking highlighted area advances tour -->
                <div
                    v-if="targetRect"
                    class="absolute pointer-events-auto cursor-pointer z-40"
                    :style="{
                        top: `${targetRect.top - 4}px`,
                        left: `${targetRect.left - 4}px`,
                        width: `${targetRect.width + 8}px`,
                        height: `${targetRect.height + 8}px`,
                    }"
                    @click="handleTargetClick"
                    :title="currentSection.id === 'start' ? 'Click Guide to begin walkthrough' : 'Click highlighted area'"
                ></div>

                <!-- Glowing Target Boundary Ring -->
                <div
                    v-if="targetRect"
                    class="absolute pointer-events-none transition-all duration-300 ease-out border-2 border-primary shadow-[0_0_35px_rgba(16,185,129,0.5)] ring-4 ring-primary/30"
                    :style="{
                        top: `${targetRect.top - 8}px`,
                        left: `${targetRect.left - 8}px`,
                        width: `${targetRect.width + 16}px`,
                        height: `${targetRect.height + 16}px`,
                        borderRadius: `${currentSection.rx}px`,
                    }"
                ></div>

                <!-- Guided Callout Text Bubble (Speech bubble dialog pointing directly to target) -->
                <div
                    ref="cardRef"
                    class="absolute z-50 pointer-events-auto transition-all duration-300 ease-out"
                    :style="cardStyle"
                    @click.stop
                >
                    <!-- Speech Bubble Tail: Top (points UP to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'top'"
                        class="absolute -top-3.5 z-30 pointer-events-none"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,15 L 12,0 L 24,15"
                                class="fill-muted/95 stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="1" y="14" width="22" height="3" class="fill-muted/95" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Bottom (points DOWN to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'bottom'"
                        class="absolute -bottom-3.5 z-30 pointer-events-none"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,0 L 12,15 L 24,0"
                                class="fill-muted/80 stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="1" y="-2" width="22" height="3" class="fill-muted/80" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Left (points LEFT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'left'"
                        class="absolute -left-3.5 z-30 pointer-events-none"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 15,0 L 0,12 L 15,24"
                                class="fill-card stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="14" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Right (points RIGHT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'right'"
                        class="absolute -right-3.5 z-30 pointer-events-none"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,0 L 15,12 L 0,24"
                                class="fill-card stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="-2" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Text Bubble Container Card -->
                    <div class="rounded-2xl border-2 border-border bg-card shadow-2xl overflow-hidden text-card-foreground flex flex-col max-h-[calc(100vh-90px)] ring-1 ring-black/10 dark:ring-white/15">
                        <!-- Card Header -->
                        <div class="px-4 py-3 bg-muted/80 border-b border-border flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="size-8 rounded-lg bg-primary/15 text-primary border border-primary/25 flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
                                    <component :is="currentSection.icon" class="size-4.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Badge variant="outline" class="text-[9.5px] px-2 py-0.5 h-4.5 border-primary/40 bg-primary/10 text-primary uppercase font-mono font-bold shrink-0">
                                            {{ currentSection.id === 'start' ? 'ORIENTATION' : `Part ${currentSection.stepNumber}/${sections.length - 1}` }}
                                        </Badge>
                                        <span class="text-[10px] sm:text-[11px] font-bold text-muted-foreground uppercase tracking-wider truncate">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="text-sm sm:text-[15px] font-bold text-foreground leading-snug mt-0.5">
                                        {{ currentSection.title }}
                                    </h3>
                                </div>
                            </div>

                            <!-- Close Button -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="close"
                                class="h-7 px-2.5 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 shrink-0 gap-1 rounded-lg ml-2 cursor-pointer"
                                title="Exit Tour (Esc)"
                            >
                                <span>Exit</span>
                                <X class="size-3.5" />
                            </Button>
                        </div>

                        <!-- Card Body -->
                        <div class="p-3.5 space-y-3 overflow-y-auto max-h-[min(400px,calc(100vh-190px))] text-xs">
                            <!-- Purpose & Function -->
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-1.5 text-[11px] sm:text-xs font-bold uppercase tracking-wider text-primary">
                                    <Info class="size-3.5" />
                                    <span>Purpose & Function:</span>
                                </div>
                                <p class="text-xs sm:text-[13px] text-foreground font-normal leading-relaxed">
                                    {{ currentSection.usedFor }}
                                </p>

                                <!-- Capabilities -->
                                <div v-if="currentSection.capabilities && currentSection.capabilities.length > 0" class="flex flex-wrap gap-1 pt-0.5">
                                    <span
                                        v-for="cap in currentSection.capabilities"
                                        :key="cap"
                                        class="inline-flex items-center gap-1.5 text-[10px] px-2 py-0.5 rounded-md font-semibold bg-muted/80 border border-border/80 text-foreground shadow-2xs"
                                    >
                                        <span class="size-1.5 rounded-full bg-primary shrink-0"></span>
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Guidance Steps -->
                            <div class="p-2.5 rounded-xl bg-muted/40 border border-border/80 space-y-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-foreground/70">How to use:</span>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, idx) in currentSection.howToUse"
                                        :key="idx"
                                        class="flex items-start gap-2 text-[11.5px] text-foreground"
                                    >
                                        <div class="size-4.5 rounded-full bg-primary text-primary-foreground font-mono font-bold text-[9px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                                            {{ idx + 1 }}
                                        </div>
                                        <span class="leading-relaxed font-normal text-foreground/95 flex-1 break-words">
                                            {{ step }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pro Tip -->
                            <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-950 dark:text-amber-100 space-y-1">
                                <span class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-300 text-[10px] uppercase tracking-wide">
                                    <Zap class="size-3 text-amber-600 dark:text-amber-400 fill-amber-500/30 shrink-0" />
                                    <span>Tip</span>
                                </span>
                                <p class="text-[11px] leading-relaxed font-medium">
                                    {{ currentSection.proTip }}
                                </p>
                            </div>

                            <!-- Interactive Action Shortcut -->
                            <div v-if="currentSection.actionLabel" class="pt-1">
                                <button
                                    type="button"
                                    @click="handleAction"
                                    class="w-full py-2.5 px-4 rounded-xl bg-primary/15 hover:bg-primary/25 text-primary text-xs sm:text-[13px] font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer border border-primary/30 shadow-2xs active:scale-[0.99]"
                                >
                                    <span>{{ currentSection.actionLabel }}</span>
                                    <ArrowRight class="size-4 shrink-0" />
                                </button>
                            </div>
                        </div>

                        <!-- Footer Navigation -->
                        <div class="px-4 py-3 bg-muted/70 border-t border-border flex items-center justify-between shrink-0">
                            <!-- Prev Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs px-3 gap-1 text-muted-foreground hover:text-foreground font-semibold cursor-pointer"
                                :disabled="internalStep === 0"
                                @click="prevStep"
                            >
                                <ArrowLeft class="size-3.5" />
                                <span>Prev</span>
                            </Button>

                            <!-- Dot indicators -->
                            <div class="flex items-center justify-center gap-1 max-w-[140px] sm:max-w-[190px] flex-wrap">
                                <button
                                    v-for="(_, idx) in sections"
                                    :key="idx"
                                    type="button"
                                    @click="goToStep(idx)"
                                    class="size-1.5 sm:size-2 rounded-full transition-all cursor-pointer"
                                    :class="idx === internalStep ? 'bg-primary w-3 sm:w-4' : 'bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                                    :title="idx === 0 ? 'Start: Product Guide' : `Part ${idx}`"
                                ></button>
                            </div>

                            <!-- Next / Done Button -->
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs px-3.5 gap-1.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold shadow-xs cursor-pointer"
                                @click="nextStep"
                            >
                                <span>{{ currentSection.id === 'start' ? 'Start Tour' : internalStep === sections.length - 1 ? 'Done' : 'Next' }}</span>
                                <ArrowRight v-if="internalStep < sections.length - 1" class="size-3.5" />
                                <Check v-else class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
