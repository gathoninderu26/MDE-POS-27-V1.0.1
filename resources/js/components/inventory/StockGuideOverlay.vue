<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    Boxes,
    Building2,
    Check,
    CheckCircle2,
    Clock,
    Compass,
    DollarSign,
    FileSpreadsheet,
    History,
    Info,
    Layers,
    Package,
    Printer,
    Scale,
    ScanBarcode,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Tag,
    Truck,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

export type InventoryTabType = 'overview' | 'audit' | 'adjustments' | 'transfers';

interface Props {
    open: boolean;
    currentStep?: number;
    activeTab?: InventoryTabType;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    currentStep: 0,
    activeTab: 'overview',
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:currentStep', step: number): void;
    (e: 'action', actionType: string): void;
    (e: 'switch-tab', tab: InventoryTabType): void;
}>();

export interface TourSection {
    id: string;
    targetId: string;
    fallbackTargetId?: string;
    stepNumber: number;
    title: string;
    tag: string;
    tab: InventoryTabType;
    shape: 'rect' | 'circle';
    rx: number;
    icon: any;
    usedFor: string;
    capabilities: string[];
    howToUse: string[];
    proTip: string;
    actionLabel?: string;
    actionType?: string;
    preferredPlacement?: 'bottom-right' | 'bottom-left' | 'left' | 'right' | 'bottom' | 'top';
    activeTabRequired?: InventoryTabType;
}

export interface TabCategory {
    key: InventoryTabType;
    label: string;
    icon: any;
    startStep: number;
    count: number;
    badgeColor: string;
}

const tabCategories: TabCategory[] = [
    {
        key: 'overview',
        label: 'Stock Catalog',
        icon: Package,
        startStep: 0,
        count: 13,
        badgeColor: 'bg-primary/10 text-primary border-primary/30',
    },
    {
        key: 'audit',
        label: 'Stock Take & Audit',
        icon: ScanBarcode,
        startStep: 13,
        count: 5,
        badgeColor: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
    },
    {
        key: 'adjustments',
        label: 'Adjustments & Shrinkage',
        icon: History,
        startStep: 18,
        count: 3,
        badgeColor: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
    },
    {
        key: 'transfers',
        label: 'Inter-Branch Transfers',
        icon: ArrowLeftRight,
        startStep: 21,
        count: 4,
        badgeColor: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
    },
];

const sections: TourSection[] = [
    // =========================================================================
    // TAB 1: STOCK CATALOG & OVERVIEW (STEPS 0 TO 12)
    // =========================================================================
    {
        id: 'start',
        targetId: 'stock-tour-guide-btn',
        stepNumber: 0,
        title: 'Stock Control & Inventory Master Guide',
        tag: 'ORIENTATION & MASTER WORKSPACES',
        tab: 'overview',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        preferredPlacement: 'bottom-right',
        usedFor:
            'Welcome to your Multi-Branch Stock Control & Valuation Center! This interactive tour guides you through every single operational tool: asset valuation telemetry, reorder triggers, thermal barcodes and shelf tags, live wireless barcode stock-take audits, audited shrinkage write-offs, and inter-branch logistics transfers.',
        capabilities: [
            'Real-Time Capital Valuation at Cost',
            'Live USB/Bluetooth Barcode Audits',
            'Permanent Loss Prevention Ledgers',
            'Thermal Barcode & Shelf Price Tags',
            'Multi-Branch Logistics State Machine',
            '1-Click RFC-4180 CSV Audit Export',
        ],
        howToUse: [
            'Click "Start Tour" or press → on your keyboard to navigate step by step.',
            "Use the tab categories at the top of this card to jump directly to any tab's guide.",
            'Press Esc at any time to exit the guide.',
        ],
        proTip: 'The "Page Guide" button remains pinned in the header toolbar at all times so managers and auditors can re-launch it on demand.',
        actionLabel: 'Start Walkthrough',
        actionType: 'start-walkthrough',
    },
    {
        id: 'valuation-kpi',
        targetId: 'stock-tour-kpi-valuation',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 1,
        title: 'Capital Valuation & Retail Profit Potential',
        tag: 'CAPITAL VALUATION & PROFIT POTENTIAL',
        tab: 'overview',
        shape: 'rect',
        rx: 16,
        icon: Boxes,
        preferredPlacement: 'right',
        activeTabRequired: 'overview',
        usedFor:
            'Instant financial asset telemetry: "Total Stock Valuation" measures the exact capital invested at wholesale cost across all branch shelves, while "Retail Potential" calculates total expected gross revenue and projected gross margin % upon complete stock turnover.',
        capabilities: [
            'Wholesale Working Capital at Cost',
            'Projected Gross Retail Sales Revenue',
            'Catalog-Wide Gross Margin % Telemetry',
            'Automated Live Asset Re-computation',
        ],
        howToUse: [
            'Compare Total Stock Valuation against Retail Potential to evaluate overall inventory asset value and gross margin potential.',
            'Monitor the projected margin percentage to guarantee selling prices maintain target business profitability.',
            'Valuations automatically adjust whenever sales are settled at POS or incoming purchase order deliveries are confirmed.',
        ],
        proTip: 'Stock cost valuations follow the weighted average cost or latest purchase price recorded from supplier goods receipts.',
    },
    {
        id: 'health-kpi',
        targetId: 'stock-tour-kpi-health',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 2,
        title: 'Depletion Warnings & Stockout Alerts',
        tag: 'STOCK HEALTH & REORDER TRIGGERS',
        tab: 'overview',
        shape: 'rect',
        rx: 16,
        icon: AlertTriangle,
        preferredPlacement: 'left',
        activeTabRequired: 'overview',
        usedFor:
            'Real-time inventory depletion monitor. "Low Stock Items" flags all SKUs that have reached or dropped below their safety reorder thresholds, while "Out of Stock" alerts management to zero-balance items requiring immediate replenishment.',
        capabilities: [
            'Safety Reorder Threshold Depletion Flags',
            'Zero-Unit Out-of-Stock Emergency Counters',
            'High-Visibility Amber & Rose Risk Indicators',
            'Direct Restock Priority Queueing',
        ],
        howToUse: [
            'Check the Low Stock card to anticipate restocking requirements before customer shelves run dry.',
            'Review Out of Stock SKUs daily to prioritize supplier purchase orders and prevent missed sales opportunities.',
            'Click the action button below to immediately isolate low stock products in the table.',
        ],
        proTip: 'Setting accurate reorder points on each product ensures that automated low-stock warnings fire with sufficient lead time for supplier delivery.',
        actionLabel: 'Filter Low Stock Items',
        actionType: 'filter-low-stock',
    },
    {
        id: 'tabs-filter',
        targetId: 'stock-tour-tabs',
        stepNumber: 3,
        title: 'Operational Modules & Health Filters',
        tag: 'MODULE WORKSPACES & STATUS FILTERS',
        tab: 'overview',
        shape: 'rect',
        rx: 14,
        icon: Layers,
        preferredPlacement: 'bottom-left',
        activeTabRequired: 'overview',
        usedFor:
            'Central module switcher navigating between 4 dedicated workspaces: (1) Stock Catalog, (2) Barcode Stock Take, (3) Adjustments & Shrinkage, and (4) Branch Transfers — paired with instant 1-click stock health filter pills (All, In Stock, Low Stock, Out of Stock).',
        capabilities: [
            'Zero-Latency Tabbed Workspace Navigation',
            'Instant 1-Click Status Filtering (Low / Out of Stock)',
            'Dynamic Item Counters on Every Workspace Tab',
            'Live Pulsing Indicator when Stock Take is Active',
        ],
        howToUse: [
            'Click any module button to switch your operational workspace immediately.',
            'Use the health filter pills on the right to instantly filter the master table by stock availability status.',
            'Watch the live green pulse on the Stock Take tab whenever an active audit session is in progress.',
        ],
        proTip: 'Clicking the "Low Stock" pill instantly filters the master catalog without reloading the page, enabling rapid generation of purchase orders.',
        actionLabel: 'Show All Products',
        actionType: 'filter-all',
    },
    {
        id: 'search',
        targetId: 'stock-tour-search',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 4,
        title: 'Universal Search & Barcode Gun Lookup',
        tag: 'CATALOG REGISTRY SEARCH',
        tab: 'overview',
        shape: 'rect',
        rx: 14,
        icon: Search,
        preferredPlacement: 'left',
        activeTabRequired: 'overview',
        usedFor:
            'High-speed multi-field search engine. Instantly filters the stock inventory table across item names, SKU codes, manufacturer barcodes, and branch warehouse locations as you type or scan.',
        capabilities: [
            'Multi-Field Fuzzy Search across 4 Attributes',
            'Hardware Barcode Scanner Auto-Lookup',
            'Sub-10ms Client-Side Filter Latency',
            'Full SKU, Name, and Branch Matching',
        ],
        howToUse: [
            'Type any product name, SKU number, barcode, or branch name to filter the stock table in real time.',
            'Plug in any USB or Bluetooth barcode scanner and pull the trigger on any item barcode to jump directly to its stock line.',
            'Clear the search input to restore the full multi-branch catalog view.',
        ],
        proTip: 'Hardware barcode scanners act as a keyboard wedge, automatically populating this input and filtering to the scanned product instantly.',
        actionLabel: 'Focus Catalog Search',
        actionType: 'focus-search',
    },
    {
        id: 'col-product',
        targetId: 'stock-tour-col-product',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 5,
        title: 'Product Identity, SKU & Barcode Registry',
        tag: 'ITEM IDENTIFIERS & SCAN CODES',
        tab: 'overview',
        shape: 'rect',
        rx: 12,
        icon: ScanBarcode,
        preferredPlacement: 'right',
        activeTabRequired: 'overview',
        usedFor:
            'Master product identification showing the official product name, internal warehouse SKU code for picking, and the scannable EAN/UPC barcode with visual scanner indicator.',
        capabilities: [
            'High-Visibility Product Nomenclature',
            'Monospaced Alphanumeric Warehouse SKU',
            'EAN-13 / UPC Barcode with Scan Indicator',
            'Multi-Branch Consistency Across Stores',
        ],
        howToUse: [
            'Verify product details and confirm that internal SKUs match warehouse storage bin labels.',
            'The barcode shown here is identical to the one read by frontline POS checkout scanners.',
        ],
        proTip: 'Ensure every tracked product has an accurate barcode registered to allow seamless scanning across POS, receiving, and shelf audits.',
    },
    {
        id: 'col-branch',
        targetId: 'stock-tour-col-branch',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 6,
        title: 'Multi-Branch & Warehouse Location',
        tag: 'PHYSICAL INVENTORY ALLOCATION',
        tab: 'overview',
        shape: 'rect',
        rx: 12,
        icon: Building2,
        preferredPlacement: 'right',
        activeTabRequired: 'overview',
        usedFor:
            'Displays the exact branch store, distribution center, or warehouse facility holding physical custody of the stock line. Supports multi-store retail chains and regional depots.',
        capabilities: [
            'Location-Specific Inventory Segregation',
            'Multi-Branch Warehouse Tracking',
            'Automatic Tenant & Branch Context Filtering',
            'Transfer Dispatch Origin & Destination Verification',
        ],
        howToUse: [
            'Check the branch badge to confirm which store location currently holds the physical units.',
            'When managing multi-branch businesses, switch the active branch in the global top header to view location-specific inventory.',
        ],
        proTip: "You can type a branch name into the search bar to filter the stock table to that specific location's inventory.",
    },
    {
        id: 'col-stock',
        targetId: 'stock-tour-col-stock',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 7,
        title: 'Stock Levels, Units of Measure & Safety Minimums',
        tag: 'STOCK BALANCES & REORDER LEVELS',
        tab: 'overview',
        shape: 'rect',
        rx: 12,
        icon: Package,
        preferredPlacement: 'right',
        activeTabRequired: 'overview',
        usedFor:
            'Numerical inventory balance tracking: displays on-hand physical units, custom units of measurement (Pcs, Kg, Bales, Liters, Cartons), and the configured minimum safety reorder threshold.',
        capabilities: [
            'Real-Time Available Quantity Display',
            'Custom Metric & Imperial Units of Measurement',
            'Minimum Safety Reorder Threshold Tag (Min: X)',
            'Auto-Decrement on Every POS Checkout',
        ],
        howToUse: [
            'Compare the bold available units against the "Min:" reorder threshold shown below it.',
            'When available stock dips to or below the minimum, the item automatically triggers low-stock alerts.',
            'Quantities update instantly whenever transactions are finalized at cashier terminals.',
        ],
        proTip: 'Tailor units of measure (e.g. "bales" for flour, "kg" for sugar, "pcs" for dairy) to match your physical storage and selling standards.',
    },
    {
        id: 'col-cost',
        targetId: 'stock-tour-col-cost',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 8,
        title: 'Unit Cost, Selling Price & Asset Valuation',
        tag: 'FINANCIAL VALUATION & POTENTIAL',
        tab: 'overview',
        shape: 'rect',
        rx: 12,
        icon: DollarSign,
        preferredPlacement: 'left',
        activeTabRequired: 'overview',
        usedFor:
            'Comprehensive line-item financial breakdown: displays the wholesale unit cost, active retail selling price, total stock valuation at cost (Available × Cost), and potential retail revenue (Available × Retail).',
        capabilities: [
            'Unit Cost Price & Retail Price Display',
            'Line Cost Valuation (Available × Unit Cost)',
            'Potential Retail Gross Revenue (Available × Retail)',
            'Instant Gross Profit Contribution Visibility',
        ],
        howToUse: [
            'Inspect the line valuation in bold to understand how much capital is tied up in each inventory item.',
            'Compare Cost vs Retail prices to verify markup and profit margins before adjusting prices.',
        ],
        proTip: 'The green "Pot: KES" figure represents the gross revenue that will flow into your cash registers once all on-hand units sell at retail price.',
    },
    {
        id: 'btn-label',
        targetId: 'stock-tour-btn-label',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 9,
        title: 'Thermal Barcode Label Generator',
        tag: 'THERMAL PRINTING ENGINE',
        tab: 'overview',
        shape: 'rect',
        rx: 10,
        icon: Printer,
        preferredPlacement: 'left',
        activeTabRequired: 'overview',
        usedFor:
            'Direct-to-printer thermal sticker generator. Creates compliant EAN-13, UPC, and Code-128 barcode labels with item title, store branding, and KES retail price for 50x30mm or 40x25mm thermal sticker rolls.',
        capabilities: [
            'EAN-13, UPC & Code-128 Scannable Barcode Generation',
            'Standard 50x30mm & 40x25mm Label Sizes',
            'Batch Print Quantity Multiplier (e.g. 50 stickers)',
            'Live SVG Barcode Preview with Store Branding',
        ],
        howToUse: [
            'Click the "Label" button on any product row to open the Thermal Barcode Label Generator.',
            'Choose your label roll dimensions and enter how many stickers to print.',
            'Click "Print Barcode Labels" to stream formatted stickers directly to your ESC/POS thermal printer.',
        ],
        proTip: 'Stick thermal barcode labels onto unbarcoded bulk goods or unpackaged items so cashiers can scan them with standard barcode guns.',
        actionLabel: 'Open Barcode Label Modal',
        actionType: 'open-barcode-label',
    },
    {
        id: 'btn-tag',
        targetId: 'stock-tour-btn-tag',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 10,
        title: 'Shelf Price Tag Generator (Gondola Edge)',
        tag: 'SHELF-EDGE PRICING CARDS',
        tab: 'overview',
        shape: 'rect',
        rx: 10,
        icon: Tag,
        preferredPlacement: 'left',
        activeTabRequired: 'overview',
        usedFor:
            'Specialized price card generator designed specifically for retail supermarket gondolas, pharmacy shelf edges, and boutique display strips. Formats large high-contrast prices without barcodes for clean customer browsing.',
        capabilities: [
            'Bold High-Contrast Price & Unit of Measure',
            'Store Branding & Branch Location Watermark',
            'Gondola Shelf-Edge Strip Formatting',
            'Standard Thermal or Laser Cardboard Printing',
        ],
        howToUse: [
            'Click the "Price Tag" button on any product row to open the Shelf Price Tag Generator.',
            'Preview the formatted card with item title, category, measurement unit, and bold retail price.',
            'Print and insert the cards into your physical shelf gondola channels.',
        ],
        proTip: 'Use Shelf Price Tags whenever items already possess manufacturer barcodes on their packages, keeping customer shelf browsing clear and professional.',
        actionLabel: 'Open Shelf Price Tag Modal',
        actionType: 'open-shelf-tag',
    },
    {
        id: 'btn-adjust',
        targetId: 'stock-tour-btn-adjust',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 11,
        title: 'Quick Stock Adjustment & Shrinkage Recording',
        tag: 'AUDITED WRITE-OFFS & LOSS PREVENTION',
        tab: 'overview',
        shape: 'rect',
        rx: 10,
        icon: SlidersHorizontal,
        preferredPlacement: 'left',
        activeTabRequired: 'overview',
        usedFor:
            'Rapid stock correction dialog for recording physical warehouse count variances, receiving unbilled vendor surplus, or writing off damaged goods, expired batches, theft, and store sampling with audited reason codes.',
        capabilities: [
            'Dual Shift Direction (- Deduct Loss / + Add Surplus)',
            '7 Standardized Warehouse Audit Reason Codes',
            'Live Before-and-After Resulting Stock Preview',
            'Internal Audit Notes & Timestamp Logging',
        ],
        howToUse: [
            'Click "Adjust" on any product row (or "Adjust Stock" in the top toolbar) to open the dialog.',
            'Select "- Deduct Stock (Loss)" or "+ Add Stock (Surplus)" and enter the quantity.',
            'Select an audited reason code (e.g. Damaged in Store, Expired / Spoilage, Theft) and add optional notes.',
            'Click "Save Adjustment" to update live inventory and write a permanent record to the audit ledger.',
        ],
        proTip: 'Every adjustment updates the stock balance immediately and permanently records the auditor name, timestamp, and financial impact in the Adjustments ledger.',
        actionLabel: 'Open Adjustment Modal',
        actionType: 'open-stock-adjust',
    },
    {
        id: 'btn-export',
        targetId: 'stock-tour-btn-export',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 12,
        title: 'Full Catalog CSV Export & Offline Backup',
        tag: 'EXECUTIVE REPORTING & DATA BACKUP',
        tab: 'overview',
        shape: 'rect',
        rx: 10,
        icon: FileSpreadsheet,
        preferredPlacement: 'bottom-right',
        activeTabRequired: 'overview',
        usedFor:
            'Instant 1-click full catalog CSV snapshot. Downloads an RFC-4180 compliant spreadsheet containing all product names, SKUs, barcodes, branch locations, available units, unit costs, selling prices, line valuations, reorder levels, and health statuses.',
        capabilities: [
            '1-Click Complete Inventory CSV Export',
            'Full Accounting & Financial Valuation Data Fields',
            'Microsoft Excel & Google Sheets Compatible',
            'Zero-Server-Load Instant Client-Side Generation',
        ],
        howToUse: [
            'Click "Export CSV" to immediately download a dated spreadsheet snapshot of your inventory.',
            'Open the file in Excel or Google Sheets to perform executive financial modeling, tax reporting, or offline audits.',
        ],
        proTip: 'Always export a CSV snapshot before running major physical stock takes or bulk price updates to have an immediate offline reference point.',
        actionLabel: 'Export CSV Report',
        actionType: 'export-csv',
    },

    // =========================================================================
    // TAB 2: PHYSICAL STOCK TAKE & BARCODE AUDIT (STEPS 13 TO 17)
    // =========================================================================
    {
        id: 'audit-box',
        targetId: 'stock-tour-audit-box',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 13,
        title: 'Live Physical Stock Take Mode',
        tag: 'BARCODE AUDIT TERMINAL',
        tab: 'audit',
        shape: 'rect',
        rx: 14,
        icon: ScanBarcode,
        preferredPlacement: 'bottom-right',
        activeTabRequired: 'audit',
        usedFor:
            'Turns any POS terminal, laptop, or tablet into a dedicated physical inventory verification workstation. Hardware scanners are armed by default with instant variance calculations, acoustic feedback, and financial discrepancy tracking.',
        capabilities: [
            'Hardware Barcode Scanner Listener Armed',
            'Real-Time Expected vs Counted Comparison',
            'Auditory Confirmation Beeps on Every Scan',
            'Live Discrepancy & Valuation Variance Telemetry',
        ],
        howToUse: [
            'Aim any USB or Bluetooth barcode scanner at shelf goods and pull the trigger.',
            'The system finds the product, increments physical counted units, and recalculates variances live.',
            'Review discrepancies in the summary bar before committing updates.',
        ],
        proTip: 'Wireless Bluetooth barcode scanners let auditors walk down supermarket aisles counting shelf stock hands-free without touching a keyboard.',
        actionLabel: 'Focus Barcode Scanner',
        actionType: 'focus-audit-scan',
    },
    {
        id: 'audit-scan',
        targetId: 'stock-tour-audit-scan',
        fallbackTargetId: 'stock-tour-audit-box',
        stepNumber: 14,
        title: 'Fast Continuous Barcode & SKU Gun Input',
        tag: 'RAPID SCANNER ENGINE',
        tab: 'audit',
        shape: 'rect',
        rx: 12,
        icon: Search,
        preferredPlacement: 'bottom',
        activeTabRequired: 'audit',
        usedFor:
            'Dedicated hands-free barcode scanner wedge input. Whenever a scanner pulls a barcode, it matches the SKU, increments physical count by +1, flashes confirmation, and resets for the next scan without requiring mouse clicks.',
        capabilities: [
            'Auto-Enter Keystroke Trapping & Rapid Reset',
            'Continuous Multi-Item Scanning (30+ items/min)',
            'Manual SKU & Barcode Search Fallback',
            'Zero-Latency Client-Side Matching',
        ],
        howToUse: [
            'Keep cursor in this field while walking aisles with your scanner.',
            "Scan items continuously — each scan auto-increments that product's counted units.",
            'If a barcode label is unreadable, type the numeric SKU and press Enter.',
        ],
        proTip: 'Hardware barcode guns send an Enter keypress after every scan, enabling rapid hands-free auditing at shelf racks.',
        actionLabel: 'Focus Barcode Input',
        actionType: 'focus-audit-scan',
    },
    {
        id: 'audit-kpis',
        targetId: 'stock-tour-audit-kpis',
        fallbackTargetId: 'stock-tour-audit-box',
        stepNumber: 15,
        title: 'Discrepancy & Valuation Variance Telemetry',
        tag: 'AUDIT VARIANCE FINANCIAL TELEMETRY',
        tab: 'audit',
        shape: 'rect',
        rx: 12,
        icon: Scale,
        preferredPlacement: 'bottom',
        activeTabRequired: 'audit',
        usedFor:
            'Executive telemetry measuring count accuracy and discrepancy impact in real time: tracks Total SKUs Audited, Discrepancy Lines (mismatches), Net Unit Shift (+/- count), and Total Variance Financial Valuation in KES at wholesale cost.',
        capabilities: [
            'Total Lines Audited Telemetry',
            'Discrepancy Line Isolation & Highlighting',
            'Color-Coded Unit Shift (+ Emerald / - Rose)',
            'Net Valuation Financial Impact in KES',
        ],
        howToUse: [
            'Monitor Discrepancy Lines to understand how many products deviate from expected computer records.',
            'Check Variance Financial Impact to determine total cost of physical shrinkage or unrecorded surplus.',
            'Aim for zero net financial variance on stock audit completion.',
        ],
        proTip: 'Green numbers indicate unexpected surplus found on shelves; red numbers indicate physical shrinkage or unaccounted loss.',
    },
    {
        id: 'audit-table',
        targetId: 'stock-tour-audit-table',
        fallbackTargetId: 'stock-tour-audit-box',
        stepNumber: 16,
        title: 'Physical Count Steppers & Comparison Ledger',
        tag: 'LIVE COUNT ADJUSTMENT TABLE',
        tab: 'audit',
        shape: 'rect',
        rx: 14,
        icon: Boxes,
        preferredPlacement: 'top',
        activeTabRequired: 'audit',
        usedFor:
            'Interactive counting table comparing System Expected against Physical Counted: allows instant 1-click quantity nudging with `+` and `-` stepper buttons, direct numerical typing, line-by-line financial variance, and real-time status badges (Exact Match, Surplus, Shortage).',
        capabilities: [
            'Precision Stepper Increment/Decrement Buttons',
            'Direct Numerical Keyboard Input for Full Cartons',
            'Automated Variance Difference Math',
            'Visual Status Badges (Match, Surplus, Shortage)',
        ],
        howToUse: [
            'Use the + and - buttons to adjust physical count for bulk goods without scanning each piece individually.',
            'Type exact quantities directly into the box for full warehouse cartons or pallets.',
            'Check the Status badge to immediately identify shortages vs surplus.',
        ],
        proTip: 'Rows with count variances automatically highlight with amber backgrounds to keep discrepancies visible.',
    },
    {
        id: 'audit-reconcile',
        targetId: 'stock-tour-audit-reconcile',
        fallbackTargetId: 'stock-tour-audit-box',
        stepNumber: 17,
        title: 'Automated Discrepancy Reconciliation',
        tag: '1-CLICK LEDGER RECONCILIATION',
        tab: 'audit',
        shape: 'rect',
        rx: 10,
        icon: CheckCircle2,
        preferredPlacement: 'left',
        activeTabRequired: 'audit',
        usedFor:
            'One-click reconciliation engine. Analyzes all physical count variances, automatically posts compensatory adjustment entries into the ledger, and synchronizes live stock balances to match physical shelf reality in one single transaction.',
        capabilities: [
            '1-Click Multi-SKU Discrepancy Reconciliation',
            'Automatic Ledger Entry Generation for Variances',
            'Zero-Math System Stock Balancing',
            'Permanent Accounting Audit Log Generation',
        ],
        howToUse: [
            'Complete physical scanning of all shelf items in the audit table.',
            'Review Discrepancy Lines and Net Financial Impact in the summary bar.',
            'Click "Reconcile Inventory Discrepancies" to commit all changes to the inventory database in one single transaction.',
        ],
        proTip: 'Items with exact matching counts (Variance = 0) are preserved without creating unnecessary adjustment noise in your ledger.',
        actionLabel: 'Reconcile Discrepancies',
        actionType: 'reconcile-discrepancies',
    },

    // =========================================================================
    // TAB 3: STOCK ADJUSTMENTS & SHRINKAGE (STEPS 18 TO 20)
    // =========================================================================
    {
        id: 'adj-search',
        targetId: 'stock-tour-tab-adjustments',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 18,
        title: 'Shrinkage History & Loss Prevention Ledger',
        tag: 'AUDIT TRAILS & LOSS PREVENTION',
        tab: 'adjustments',
        shape: 'rect',
        rx: 12,
        icon: History,
        preferredPlacement: 'bottom-left',
        activeTabRequired: 'adjustments',
        usedFor:
            'Permanent, tamper-evident transaction ledger recording every single stock adjustment, audit reconciliation, breakage write-off, and shrinkage event. Displays unique adjustment ID, timestamp, product name, quantity shift, reason code, and author.',
        capabilities: [
            'Permanent Audited Stock Movement Ledger',
            'Loss Prevention & Shrinkage Pattern Detection',
            'Color-Coded Quantity Shifts (+ Emerald / - Rose)',
            'Staff & Manager Accountability Attribution',
        ],
        howToUse: [
            'Review this ledger weekly to identify recurring loss patterns, high-breakage departments, or frequent count discrepancies.',
            'Use the table search box to filter by specific adjustment ID (e.g. ADJ-101), product name, or reason code.',
        ],
        proTip: 'Audit records generated during physical stock takes are clearly tagged with "Physical Stock Take Audit Reconciliation" for clean accounting classification.',
        actionLabel: 'Open Adjustment Modal',
        actionType: 'open-stock-adjust',
    },
    {
        id: 'adj-shift',
        targetId: 'stock-tour-adj-shift',
        fallbackTargetId: 'stock-tour-tab-adjustments',
        stepNumber: 19,
        title: 'Audited Stock Shift & Quantity Deltas',
        tag: 'STOCK QUANTITY DELTA LOG',
        tab: 'adjustments',
        shape: 'rect',
        rx: 10,
        icon: SlidersHorizontal,
        preferredPlacement: 'right',
        activeTabRequired: 'adjustments',
        usedFor:
            'Monitors the exact direction and magnitude of physical inventory adjustments: displays emerald positive shifts (+ units) for surplus stock discoveries and rose negative shifts (- units) for damaged goods, expired batches, and shrinkage write-offs.',
        capabilities: [
            'High-Contrast Color-Coded Quantity Deltas',
            'Precise Unit-of-Measure Alignment',
            'Cumulative Shift Accounting Logs',
            'Instant Visual Identification of Loss vs Gain',
        ],
        howToUse: [
            'Check negative unit counts to track total written-off stock volume.',
            'Positive numbers show inventory unbilled surplus or stock-take upward adjustments.',
            'Deltas directly sync with warehouse physical balances.',
        ],
        proTip: 'Unusually high negative shifts on high-cost items trigger internal loss prevention reviews.',
    },
    {
        id: 'adj-reason',
        targetId: 'stock-tour-adj-reason',
        fallbackTargetId: 'stock-tour-tab-adjustments',
        stepNumber: 20,
        title: 'Standardized Reason Codes & Staff Attribution',
        tag: 'REASON CODES & ACCOUNTABILITY',
        tab: 'adjustments',
        shape: 'rect',
        rx: 10,
        icon: ShieldCheck,
        preferredPlacement: 'left',
        activeTabRequired: 'adjustments',
        usedFor:
            'Mandatory reason classification: every adjustment is tagged with one of 7 standardized warehouse audit reason codes (Breakage in Store, Expired / Spoilage, Theft / Missing, Physical Stock Take Reconciliation, Sampling, Supplier Delivery Variance, Internal Branch Shift) along with the staff member who authorized the change.',
        capabilities: [
            '7 Standardized Inventory Audit Reason Codes',
            'Mandatory Accountability & User Attribution',
            'Detailed Explanatory Auditor Notes',
            'Loss Prevention Compliance Reporting',
        ],
        howToUse: [
            'Scan the reason code column to see whether stock losses stem from handling breakage, shelf expiry, or count reconciliations.',
            'Use the search box to filter by reason (e.g. "Expired") to total up all spoilage for tax write-off deductions.',
        ],
        proTip: 'Mandatory reason tagging discourages casual adjustments and gives store managers full forensic visibility into shrinkage.',
    },

    // =========================================================================
    // TAB 4: INTER-BRANCH LOGISTICS & TRANSFERS (STEPS 21 TO 24)
    // =========================================================================
    {
        id: 'transfer-search',
        targetId: 'stock-tour-tab-transfers',
        fallbackTargetId: 'stock-tour-tabs',
        stepNumber: 21,
        title: 'Inter-Branch Logistics & In-Transit Transfers',
        tag: 'MULTI-STORE LOGISTICS & TRANSFERS',
        tab: 'transfers',
        shape: 'rect',
        rx: 12,
        icon: ArrowLeftRight,
        preferredPlacement: 'bottom-left',
        activeTabRequired: 'transfers',
        usedFor:
            'Multi-branch stock transfer hub. Facilitates dispatching goods between central warehouses and satellite branch stores, tracks transfers currently in-transit, and provides a 1-click "Receive & Stock" verification button at the destination branch.',
        capabilities: [
            'Origin-to-Destination Branch Routing',
            'In-Transit State Machine (Pending -> Approved -> Completed)',
            '1-Click Destination Stock Crediting ("Receive & Stock")',
            'Transfer Reference Numbers (TRF-xxxx) and Timestamps',
        ],
        howToUse: [
            'Filter transfers by branch name, transfer reference (e.g. TRF-4091), or item name.',
            'Track pending dispatches to ensure goods arrive at destination stores on schedule.',
        ],
        proTip: 'Goods in transit cannot be accidentally sold at either location until the receiving branch officially acknowledges physical delivery.',
        actionLabel: 'Dispatch New Transfer',
        actionType: 'open-transfer-modal',
    },
    {
        id: 'transfer-dispatch',
        targetId: 'stock-tour-tab4-new-transfer',
        fallbackTargetId: 'stock-tour-btn-transfer',
        stepNumber: 22,
        title: 'Dispatching Inter-Branch Goods Transfers',
        tag: 'TRANSFER DISPATCH WORKFLOW',
        tab: 'transfers',
        shape: 'rect',
        rx: 10,
        icon: Truck,
        preferredPlacement: 'left',
        activeTabRequired: 'transfers',
        usedFor:
            'Creates an official transfer manifest for moving stock between stores. Select source warehouse, destination branch, product SKU, and quantity. Deducts dispatched units from origin and tags them as in-transit.',
        capabilities: [
            'Inter-Store Stock Movement Manifest',
            'Origin Warehouse Stock Reservation',
            'Automatic Transfer ID (TRF-xxxx) Assignment',
            'Multi-Store Inventory Balancing',
        ],
        howToUse: [
            'Click "New Transfer" to open the creation dialog.',
            'Choose the origin branch sending the goods and destination branch receiving them.',
            'Enter the quantity and submit to dispatch the transfer manifest.',
        ],
        proTip: 'Use branch transfers to rebalance stock from overstocked central warehouses to fast-selling satellite outlets without incurring new supplier purchase costs.',
        actionLabel: 'Open Transfer Modal',
        actionType: 'open-transfer-modal',
    },
    {
        id: 'transfer-status',
        targetId: 'stock-tour-transfer-status',
        fallbackTargetId: 'stock-tour-tab-transfers',
        stepNumber: 23,
        title: 'Logistics State Machine & In-Transit Tracking',
        tag: 'TRANSFER STATUS LIFECYCLE',
        tab: 'transfers',
        shape: 'rect',
        rx: 10,
        icon: Clock,
        preferredPlacement: 'right',
        activeTabRequired: 'transfers',
        usedFor:
            'Tracks physical consignment status in real time: "Pending" (awaiting warehouse picking/dispatch), "Approved" / "In-Transit" (goods loaded onto delivery truck en route), and "Completed" (delivered and acknowledged by destination branch).',
        capabilities: [
            '3-Stage Real-Time Logistics Lifecycle',
            'Color-Coded Status Badges (Amber / Blue / Green)',
            'Driver / Courier Consignment Verification',
            'Double-Allocation Prevention',
        ],
        howToUse: [
            'Warehouse dispatchers set transfers to Approved once loaded onto delivery trucks.',
            'Destination store managers check the list for incoming deliveries.',
        ],
        proTip: 'The system prevents ghost stock by keeping in-transit units isolated until officially acknowledged.',
    },
    {
        id: 'transfer-receive',
        targetId: 'stock-tour-transfer-receive',
        fallbackTargetId: 'stock-tour-tab-transfers',
        stepNumber: 24,
        title: '1-Click "Receive & Stock" Destination Verification',
        tag: 'RECEIVING & STOCK CREDITING',
        tab: 'transfers',
        shape: 'rect',
        rx: 10,
        icon: CheckCircle2,
        preferredPlacement: 'left',
        activeTabRequired: 'transfers',
        usedFor:
            'Destination verification button. When physical delivery vehicles arrive at the satellite branch, the receiving clerk inspects the physical cartons and clicks "Receive & Stock" to finalize the transfer and credit local shelf inventory.',
        capabilities: [
            'Instant Destination Inventory Crediting',
            'Automatic Transfer Completion Timestamping',
            'Zero-Paper Delivery Confirmation',
            'Live Stock Sync Across All POS Terminals',
        ],
        howToUse: [
            'Inspect goods upon physical arrival at your branch store.',
            'Click "Receive & Stock" to acknowledge physical delivery.',
            "The units immediately become available for checkout at this branch's POS cash registers.",
        ],
        proTip: 'As soon as "Receive & Stock" is clicked, local cashier barcode guns can immediately ring up the newly arrived items at checkout.',
        actionLabel: 'View Transfers Workspace',
        actionType: 'view-transfers',
    },
];

const internalStep = ref(props.currentStep || 0);
const targetRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const cardRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const windowSize = ref({ width: 0, height: 0 });
const cardRef = ref<HTMLElement | null>(null);
let measurePollTimer: any = null;

watch(
    () => props.currentStep,
    (newStep) => {
        if (newStep !== undefined && newStep !== internalStep.value) {
            internalStep.value = newStep;
            updateTargetPosition();
        }
    },
);

const currentSection = computed(() => {
    return sections[internalStep.value] || sections[0];
});

const currentTabCategory = computed(() => {
    const sec = currentSection.value;
    const tabKey = sec.activeTabRequired || sec.tab || 'overview';
    return tabCategories.find((t) => t.key === tabKey) || tabCategories[0];
});

const currentStepInCat = computed(() => {
    const cat = currentTabCategory.value;
    return Math.max(1, internalStep.value - cat.startStep + 1);
});

const jumpToTabCategory = (tabKey: InventoryTabType) => {
    const cat = tabCategories.find((c) => c.key === tabKey);
    if (cat) {
        emit('switch-tab', tabKey);
        goToStep(cat.startStep);
    }
};

const updateTargetPosition = () => {
    if (typeof window === 'undefined') return;

    if (measurePollTimer) {
        clearTimeout(measurePollTimer);
        measurePollTimer = null;
    }

    windowSize.value = {
        width: window.innerWidth,
        height: window.innerHeight,
    };

    const sec = currentSection.value;
    if (!sec) return;

    // Automatically switch tabs if required for this tour step
    if (sec.activeTabRequired) {
        emit('switch-tab', sec.activeTabRequired);
    }

    // Polling retry to guarantee element is in DOM after tab transition / rendering
    let attempts = 0;
    const findAndMeasure = () => {
        const el = document.getElementById(sec.targetId) || (sec.fallbackTargetId ? document.getElementById(sec.fallbackTargetId) : null);

        if (!el && attempts < 12) {
            attempts++;
            measurePollTimer = setTimeout(findAndMeasure, 45);
            return;
        }

        if (!el) {
            targetRect.value = null;
            return;
        }

        // Scroll into view if out of comfortable view
        const r = el.getBoundingClientRect();
        const isOutOfView = r.top < 70 || r.bottom > window.innerHeight - 70;
        if (isOutOfView) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        const measure = () => {
            if (!el) return;
            const cur = el.getBoundingClientRect();
            targetRect.value = {
                top: Math.max(0, cur.top),
                left: Math.max(0, cur.left),
                width: cur.width,
                height: cur.height,
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
        requestAnimationFrame(measure);
        measurePollTimer = setTimeout(measure, 160);
    };

    nextTick(() => {
        findAndMeasure();
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
            if (measurePollTimer) clearTimeout(measurePollTimer);
            window.removeEventListener('resize', updateTargetPosition);
            window.removeEventListener('scroll', updateTargetPosition, true);
            window.removeEventListener('keydown', handleKeyDown);
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.open) {
        updateTargetPosition();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        if (measurePollTimer) clearTimeout(measurePollTimer);
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
            maxWidth: '430px',
            width: 'calc(100% - 32px)',
        };
    }

    const { top, left, width, height } = targetRect.value;
    const isMobile = windowSize.value.width < 768;
    const cardWidth = isMobile ? Math.min(350, windowSize.value.width - 24) : 430;
    const cardHeight = Math.min(cardRect.value?.height || 430, windowSize.value.height - 40);
    const targetCenterX = left + width / 2;
    const targetCenterY = top + height / 2;
    const maxSafeTop = Math.max(16, windowSize.value.height - cardHeight - 16);
    const maxSafeLeft = Math.max(16, windowSize.value.width - cardWidth - 16);

    // On mobile screens: position above or below target
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

    // PREFERRED 1: Side placement (RIGHT)
    if (placement === 'right') {
        if (left + width + cardWidth + 24 <= windowSize.value.width) {
            const idealTop = targetCenterY - cardHeight / 2;
            const safeTop = Math.max(16, Math.min(maxSafeTop, idealTop));
            return {
                top: `${safeTop}px`,
                left: `${left + width + 18}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // PREFERRED 2: Side placement (LEFT)
    if (placement === 'left') {
        if (left - cardWidth - 24 >= 0) {
            const idealTop = targetCenterY - cardHeight / 2;
            const safeTop = Math.max(16, Math.min(maxSafeTop, idealTop));
            return {
                top: `${safeTop}px`,
                left: `${left - cardWidth - 18}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // PREFERRED 3: BOTTOM-RIGHT
    if (placement === 'bottom-right') {
        const topPos = top + height + 16;
        const safeTop = Math.min(maxSafeTop, topPos);
        const rightOffset = Math.max(20, Math.min(maxSafeLeft, windowSize.value.width - (left + width)));
        return {
            top: `${safeTop}px`,
            right: `${rightOffset}px`,
            width: `${cardWidth}px`,
        };
    }

    // PREFERRED 4: BOTTOM-LEFT
    if (placement === 'bottom-left') {
        const topPos = top + height + 16;
        const safeTop = Math.min(maxSafeTop, topPos);
        const leftOffset = Math.max(20, Math.min(maxSafeLeft, left));
        return {
            top: `${safeTop}px`,
            left: `${leftOffset}px`,
            width: `${cardWidth}px`,
        };
    }

    // PREFERRED 5: TOP -> above target
    if (placement === 'top') {
        if (top - cardHeight - 24 >= 0) {
            const bottomOffset = windowSize.value.height - top + 16;
            const idealLeft = targetCenterX - cardWidth / 2;
            const safeLeft = Math.max(16, Math.min(maxSafeLeft, idealLeft));
            return {
                bottom: `${bottomOffset}px`,
                left: `${safeLeft}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // PREFERRED 6: BOTTOM -> below target centered
    if (placement === 'bottom') {
        if (top + height + cardHeight + 24 <= windowSize.value.height) {
            const topPos = top + height + 16;
            const idealLeft = targetCenterX - cardWidth / 2;
            const safeLeft = Math.max(16, Math.min(maxSafeLeft, idealLeft));
            return {
                top: `${topPos}px`,
                left: `${safeLeft}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // FALLBACK 1: Elements in upper header (top < 140)
    if (top < 140) {
        const topPos = top + height + 16;
        const safeTop = Math.min(maxSafeTop, topPos);
        if (targetCenterX > windowSize.value.width / 2) {
            const rightOffset = Math.max(20, Math.min(maxSafeLeft, windowSize.value.width - (left + width)));
            return {
                top: `${safeTop}px`,
                right: `${rightOffset}px`,
                width: `${cardWidth}px`,
            };
        } else {
            const leftOffset = Math.max(20, Math.min(maxSafeLeft, left));
            return {
                top: `${safeTop}px`,
                left: `${leftOffset}px`,
                width: `${cardWidth}px`,
            };
        }
    }

    // FALLBACK 2: Left-side placement if enough horizontal room
    if (left - cardWidth - 24 >= 0) {
        const idealTop = targetCenterY - cardHeight / 2;
        const safeTop = Math.max(16, Math.min(maxSafeTop, idealTop));
        return {
            top: `${safeTop}px`,
            left: `${left - cardWidth - 18}px`,
            width: `${cardWidth}px`,
        };
    }

    // FALLBACK 3: Right-side placement if enough room
    if (left + width + cardWidth + 24 <= windowSize.value.width) {
        const idealTop = targetCenterY - cardHeight / 2;
        const safeTop = Math.max(16, Math.min(maxSafeTop, idealTop));
        return {
            top: `${safeTop}px`,
            left: `${left + width + 18}px`,
            width: `${cardWidth}px`,
        };
    }

    // FALLBACK 4: Top or Bottom based on vertical room
    if (top + height + cardHeight + 24 <= windowSize.value.height) {
        const topPos = top + height + 16;
        const idealLeft = targetCenterX - cardWidth / 2;
        const safeLeft = Math.max(16, Math.min(maxSafeLeft, idealLeft));
        return {
            top: `${topPos}px`,
            left: `${safeLeft}px`,
            width: `${cardWidth}px`,
        };
    }

    // Absolute fallback: bottom of screen
    return {
        bottom: '24px',
        left: '50%',
        transform: 'translateX(-50%)',
        maxWidth: `${cardWidth}px`,
        width: 'calc(100% - 32px)',
    };
});

// Calculate speech bubble directional tail position
const speechBubbleTail = computed(() => {
    if (!targetRect.value || !cardRect.value) return null;
    const t = targetRect.value;
    const c = cardRect.value;
    const targetCenterX = t.left + t.width / 2;
    const targetCenterY = t.top + t.height / 2;

    // Target is above card
    if (t.top + t.height <= c.top + 24) {
        const offset = Math.max(28, Math.min((c.width || 430) - 28, targetCenterX - c.left));
        return {
            placement: 'top' as const,
            offset,
        };
    }
    // Target is below card
    if (t.top >= c.top + (c.height || 430) - 24) {
        const offset = Math.max(28, Math.min((c.width || 430) - 28, targetCenterX - c.left));
        return {
            placement: 'bottom' as const,
            offset,
        };
    }
    // Card is on LEFT, Target is on RIGHT
    if (c.left + (c.width || 430) <= t.left + 24) {
        const offset = Math.max(24, Math.min((c.height || 430) - 24, targetCenterY - c.top));
        return {
            placement: 'right' as const,
            offset,
        };
    }
    // Card is on RIGHT, Target is on LEFT
    const offset = Math.max(24, Math.min((c.height || 430) - 24, targetCenterY - c.top));
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
            <div v-if="open" class="pointer-events-none fixed inset-0 z-50 select-none font-sans">
                <!-- SVG Dimming Backdrop with Cutout Hole -->
                <svg class="pointer-events-none absolute inset-0 h-full w-full transition-all duration-300" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- Cutout Mask Hole -->
                        <mask id="stock-guide-mask">
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
                        mask="url(#stock-guide-mask)"
                        class="pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole: clicking highlighted area advances tour -->
                <div
                    v-if="targetRect"
                    class="pointer-events-auto absolute z-40 cursor-pointer"
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
                    class="pointer-events-none absolute border-2 border-primary shadow-[0_0_35px_rgba(16,185,129,0.55)] ring-4 ring-primary/30 transition-all duration-300 ease-out"
                    :style="{
                        top: `${targetRect.top - 8}px`,
                        left: `${targetRect.left - 8}px`,
                        width: `${targetRect.width + 16}px`,
                        height: `${targetRect.height + 16}px`,
                        borderRadius: `${currentSection.rx}px`,
                    }"
                ></div>

                <!-- Guided Callout Text Bubble (Speech bubble dialog pointing directly to target) -->
                <div ref="cardRef" class="pointer-events-auto absolute z-50 transition-all duration-300 ease-out" :style="cardStyle" @click.stop>
                    <!-- Speech Bubble Tail: Top (points UP to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'top'"
                        class="pointer-events-none absolute -top-3.5 z-30"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path d="M 0,15 L 12,0 L 24,15" class="fill-muted/95 stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="1" y="14" width="22" height="3" class="fill-muted/95" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Bottom (points DOWN to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'bottom'"
                        class="pointer-events-none absolute -bottom-3.5 z-30"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path d="M 0,0 L 12,15 L 24,0" class="fill-muted/80 stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="1" y="-2" width="22" height="3" class="fill-muted/80" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Left (points LEFT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'left'"
                        class="pointer-events-none absolute -left-3.5 z-30"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path d="M 15,0 L 0,12 L 15,24" class="fill-card stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="14" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Right (points RIGHT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'right'"
                        class="pointer-events-none absolute -right-3.5 z-30"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path d="M 0,0 L 15,12 L 0,24" class="fill-card stroke-border" stroke-width="2" stroke-linejoin="round" />
                            <rect x="-2" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Text Bubble Container Card -->
                    <div
                        class="flex max-h-[calc(100vh-80px)] flex-col overflow-hidden rounded-2xl border-2 border-border bg-card text-card-foreground shadow-2xl ring-1 ring-black/10 dark:ring-white/15"
                    >
                        <!-- Card Header -->
                        <div class="flex shrink-0 items-center justify-between border-b border-border bg-muted/80 px-4 py-3">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div
                                    class="shadow-2xs flex size-8 shrink-0 items-center justify-center rounded-lg border border-primary/25 bg-primary/15 text-xs font-bold text-primary"
                                >
                                    <component :is="currentSection.icon" class="size-4.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            class="h-4.5 shrink-0 border-primary/40 bg-primary/10 px-2 py-0.5 font-mono text-[9.5px] font-bold uppercase text-primary"
                                        >
                                            {{
                                                currentSection.id === 'start'
                                                    ? 'ORIENTATION'
                                                    : `Part ${currentSection.stepNumber}/${sections.length - 1}`
                                            }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            :class="[
                                                'shrink-0 px-1.5 py-0.5 font-mono text-[9px] font-bold uppercase',
                                                currentTabCategory.badgeColor,
                                            ]"
                                        >
                                            {{ currentTabCategory.label }}
                                        </Badge>
                                        <span class="truncate text-[10px] font-bold uppercase tracking-wider text-muted-foreground sm:text-[11px]">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="mt-0.5 text-sm font-bold leading-snug text-foreground sm:text-[15px]">
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
                                class="ml-2 h-7 shrink-0 cursor-pointer gap-1 rounded-lg px-2 text-xs font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                title="Exit Tour (Esc)"
                            >
                                <span>Exit</span>
                                <X class="size-3.5" />
                            </Button>
                        </div>

                        <!-- Tab Category Quick-Jump Bar -->
                        <div class="scrollbar-none flex shrink-0 items-center gap-1.5 overflow-x-auto border-b border-border bg-muted/40 px-3 py-1.5">
                            <span class="mr-0.5 shrink-0 text-[10px] font-bold uppercase tracking-wider text-muted-foreground"> Tab Guides: </span>
                            <button
                                v-for="t in tabCategories"
                                :key="t.key"
                                type="button"
                                @click="jumpToTabCategory(t.key)"
                                :class="[
                                    'flex shrink-0 cursor-pointer items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold transition-all',
                                    currentTabCategory.key === t.key
                                        ? 'shadow-xs border-primary bg-primary font-bold text-primary-foreground'
                                        : 'border-border/80 bg-card text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                ]"
                            >
                                <component :is="t.icon" class="size-3.5" />
                                <span>{{ t.label }}</span>
                                <span
                                    :class="[
                                        'py-0.2 rounded-full px-1 font-mono text-[9px]',
                                        currentTabCategory.key === t.key
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    {{ t.count }}
                                </span>
                            </button>
                        </div>

                        <!-- Card Body -->
                        <div class="max-h-[min(420px,calc(100vh-230px))] space-y-3 overflow-y-auto p-3.5 text-xs">
                            <!-- Purpose & Function -->
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary sm:text-xs">
                                    <Info class="size-3.5" />
                                    <span>Purpose & Function:</span>
                                </div>
                                <p class="text-xs font-normal leading-relaxed text-foreground sm:text-[13px]">
                                    {{ currentSection.usedFor }}
                                </p>

                                <!-- Capabilities -->
                                <div v-if="currentSection.capabilities && currentSection.capabilities.length > 0" class="flex flex-wrap gap-1 pt-0.5">
                                    <span
                                        v-for="cap in currentSection.capabilities"
                                        :key="cap"
                                        class="shadow-2xs inline-flex items-center gap-1.5 rounded-md border border-border/80 bg-muted/80 px-2 py-0.5 text-[10px] font-semibold text-foreground"
                                    >
                                        <span class="size-1.5 shrink-0 rounded-full bg-primary"></span>
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Guidance Steps -->
                            <div class="space-y-2 rounded-xl border border-border/80 bg-muted/40 p-2.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-foreground/70">How to use:</span>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, idx) in currentSection.howToUse"
                                        :key="idx"
                                        class="flex items-start gap-2 text-[11.5px] text-foreground"
                                    >
                                        <div
                                            class="size-4.5 shadow-2xs mt-0.5 flex shrink-0 items-center justify-center rounded-full bg-primary font-mono text-[9px] font-bold text-primary-foreground"
                                        >
                                            {{ idx + 1 }}
                                        </div>
                                        <span class="flex-1 break-words font-normal leading-relaxed text-foreground/95">
                                            {{ step }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pro Tip -->
                            <div class="space-y-1 rounded-xl border border-amber-500/30 bg-amber-500/10 p-2.5 text-amber-950 dark:text-amber-100">
                                <span
                                    class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:text-amber-300"
                                >
                                    <Zap class="size-3 shrink-0 fill-amber-500/30 text-amber-600 dark:text-amber-400" />
                                    <span>Tip</span>
                                </span>
                                <p class="text-[11px] font-medium leading-relaxed">
                                    {{ currentSection.proTip }}
                                </p>
                            </div>

                            <!-- Interactive Action Shortcut -->
                            <div v-if="currentSection.actionLabel" class="pt-1">
                                <button
                                    type="button"
                                    @click="handleAction"
                                    class="shadow-2xs flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-primary/30 bg-primary/15 px-4 py-2.5 text-xs font-bold text-primary transition-colors hover:bg-primary/25 active:scale-[0.99] sm:text-[13px]"
                                >
                                    <span>{{ currentSection.actionLabel }}</span>
                                    <ArrowRight class="size-4 shrink-0" />
                                </button>
                            </div>
                        </div>

                        <!-- Footer Navigation -->
                        <div class="flex shrink-0 items-center justify-between border-t border-border bg-muted/70 px-4 py-3">
                            <!-- Prev Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 cursor-pointer gap-1 px-3 text-xs font-semibold text-muted-foreground hover:text-foreground"
                                :disabled="internalStep === 0"
                                @click="prevStep"
                            >
                                <ArrowLeft class="size-3.5" />
                                <span>Prev</span>
                            </Button>

                            <!-- Current Step Badge / Dots -->
                            <div class="flex flex-col items-center justify-center gap-0.5">
                                <span class="font-mono text-[11px] font-bold text-foreground">
                                    {{ currentTabCategory.label }}: {{ currentStepInCat }} of {{ currentTabCategory.count }}
                                </span>
                                <span class="font-mono text-[9.5px] text-muted-foreground">
                                    Overall Step {{ internalStep + 1 }} / {{ sections.length }}
                                </span>
                            </div>

                            <!-- Next / Done Button -->
                            <Button
                                type="button"
                                size="sm"
                                class="shadow-xs h-8 cursor-pointer gap-1.5 bg-primary px-3.5 text-xs font-bold text-primary-foreground hover:bg-primary/90"
                                @click="nextStep"
                            >
                                <span>{{
                                    currentSection.id === 'start' ? 'Start Tour' : internalStep === sections.length - 1 ? 'Done' : 'Next'
                                }}</span>
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
