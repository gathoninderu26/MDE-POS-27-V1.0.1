import { computed } from 'vue';
import { useTenant } from '@/composables/useTenant';
import type { BusinessType } from '@/composables/useSuperAdmin';
import type { ProductItem } from '@/composables/useCart';

export interface BusinessTermsConfig {
    businessType: BusinessType;
    themeColor: 'emerald' | 'teal' | 'amber' | 'orange' | 'violet' | 'blue' | 'pink' | 'yellow' | 'slate' | 'cyan';
    badgeLabel: string;
    
    // Core Terminology
    itemLabel: string;
    itemsPlural: string;
    codeLabel: string;
    clientLabel: string;
    clientPlural: string;
    staffLabel: string;
    checkoutAction: string;
    holdOrderAction: string;
    heldDrawerTitle: string;
    receiptTitle: string;
    catalogTitle: string;
    categoryLabel: string;
    unitLabel: string;
    
    // Feature & UI Slot Flags
    showWholesaleToggle: boolean;
    showBatchExpiry: boolean;
    showPrescriptions: boolean;
    showDimensionsAndSite: boolean;
    showTableManagement: boolean;
    showModifiers: boolean;
    showSerialWarranty: boolean;
    showPumpMeters: boolean;
    
    // Placeholder & Contextual Texts
    searchPlaceholder: string;
    clientPlaceholder: string;
    emptyCartMessage: string;
    quickShortcutsHint: string;
}

// Preset Mock Catalogs for each vertical
export const verticalCatalogs: Record<string, ProductItem[]> = {
    Supermarket: [
        { id: 'sm-1', sku: 'DAI-001', barcode: '616110123001', name: 'Brookside Whole Milk 500ml', category: 'Dairy', brand: 'Brookside', unit: 'Pouch', costPrice: 65, retailPrice: 80, wholesalePrice: 72, stock: 48, minStock: 20, taxRate: 16 },
        { id: 'sm-2', sku: 'BAK-002', barcode: '616110123002', name: 'Broadways White Bread 400g', category: 'Bakery', brand: 'Broadways', unit: 'Loaf', costPrice: 55, retailPrice: 70, wholesalePrice: 62, stock: 35, minStock: 15, taxRate: 16 },
        { id: 'sm-3', sku: 'GRO-003', barcode: '616110123003', name: 'Mumias Pure Sugar 1kg', category: 'Grocery', brand: 'Mumias', unit: 'Pack', costPrice: 155, retailPrice: 190, wholesalePrice: 175, stock: 80, minStock: 30, taxRate: 16 },
        { id: 'sm-4', sku: 'GRO-004', barcode: '616110123004', name: 'Pembe Maize Flour 2kg', category: 'Grain & Flours', brand: 'Pembe', unit: 'Packet', costPrice: 140, retailPrice: 170, wholesalePrice: 155, stock: 120, minStock: 40, taxRate: 16 },
        { id: 'sm-5', sku: 'OIL-006', barcode: '616110123006', name: 'Fresh Fri Vegetable Oil 2L', category: 'Oils & Fats', brand: 'Pwani', unit: 'Bottle', costPrice: 580, retailPrice: 690, wholesalePrice: 630, stock: 24, minStock: 10, taxRate: 16 },
        { id: 'sm-6', sku: 'BEV-007', barcode: '616110123007', name: 'Ketepa Pride Tea Leaves 500g', category: 'Beverages', brand: 'Ketepa', unit: 'Pack', costPrice: 240, retailPrice: 290, wholesalePrice: 265, stock: 18, minStock: 10, taxRate: 16 },
    ],
    Pharmacy: [
        { id: 'ph-1', sku: 'DRG-101', barcode: '890123400101', name: 'Augmentin 625mg (Amoxicillin/Clavulanate)', category: 'Antibiotics', brand: 'GSK', unit: 'Box (14s)', costPrice: 1100, retailPrice: 1450, wholesalePrice: 1300, stock: 32, minStock: 10, taxRate: 0, genericName: 'Amoxicillin + Clavulanic Acid', dosage: '1 Tab BD x 7 days', batchNumber: 'AUG-9021', expiryDate: '12/2027', isPrescriptionOnly: true },
        { id: 'ph-2', sku: 'DRG-102', barcode: '890123400102', name: 'Panadol Extra 500mg (Paracetamol + Caffeine)', category: 'Analgesics', brand: 'Haleon', unit: 'Blister (20s)', costPrice: 180, retailPrice: 260, wholesalePrice: 220, stock: 85, minStock: 25, taxRate: 0, genericName: 'Paracetamol 500mg + Caffeine 65mg', dosage: '2 Tabs QDS PRN', batchNumber: 'PAN-4410', expiryDate: '09/2028', isPrescriptionOnly: false },
        { id: 'ph-3', sku: 'DRG-103', barcode: '890123400103', name: 'Ventolin Inhaler 100mcg (Salbutamol)', category: 'Respiratory', brand: 'Glaxo', unit: 'Canister (200 doses)', costPrice: 420, retailPrice: 580, wholesalePrice: 500, stock: 19, minStock: 8, taxRate: 0, genericName: 'Salbutamol Sulphate', dosage: '2 Puffs PRN for SOB', batchNumber: 'VEN-3392', expiryDate: '06/2027', isPrescriptionOnly: true },
        { id: 'ph-4', sku: 'DRG-104', barcode: '890123400104', name: 'Cetirizine 10mg Tablets', category: 'Antihistamines', brand: 'Dawa Pharma', unit: 'Strip (10s)', costPrice: 60, retailPrice: 120, wholesalePrice: 90, stock: 64, minStock: 15, taxRate: 0, genericName: 'Cetirizine Hydrochloride', dosage: '1 Tab OD at night', batchNumber: 'CET-1180', expiryDate: '03/2028', isPrescriptionOnly: false },
        { id: 'ph-5', sku: 'DRG-105', barcode: '890123400105', name: 'Omeprazole 20mg Capsules', category: 'Gastrointestinal', brand: 'Cosmos', unit: 'Box (28s)', costPrice: 220, retailPrice: 350, wholesalePrice: 290, stock: 40, minStock: 12, taxRate: 0, genericName: 'Omeprazole', dosage: '1 Cap OD 30min before breakfast', batchNumber: 'OMP-8801', expiryDate: '11/2027', isPrescriptionOnly: true },
        { id: 'ph-6', sku: 'DRG-106', barcode: '890123400106', name: 'Seven Seas Cod Liver Oil Plus 60s', category: 'Supplements', brand: 'Seven Seas', unit: 'Bottle', costPrice: 850, retailPrice: 1150, wholesalePrice: 990, stock: 22, minStock: 6, taxRate: 16, genericName: 'Omega-3 + Vitamins A, D, E', dosage: '1 Cap Daily', batchNumber: 'SSC-5012', expiryDate: '08/2027', isPrescriptionOnly: false },
    ],
    Hardware: [
        { id: 'hw-1', sku: 'STL-001', barcode: '616220110001', name: 'D12 Deformed High-Yield Rebar (12m)', category: 'Steel & Metals', brand: 'Mabati Rolling Mills', unit: 'Length (12m)', costPrice: 1100, retailPrice: 1350, wholesalePrice: 1240, stock: 150, minStock: 30, taxRate: 16, specifications: 'BS 4449 Grade 500B | 12mm x 12m', dimensionUnit: 'Lengths' },
        { id: 'hw-2', sku: 'CEM-002', barcode: '616220110002', name: 'Bamburi Tembo Cement 32.5N (50kg)', category: 'Cement & Aggregate', brand: 'Bamburi', unit: '50kg Bag', costPrice: 680, retailPrice: 780, wholesalePrice: 730, stock: 400, minStock: 100, taxRate: 16, specifications: '32.5N All-purpose Portland Pozzolana', dimensionUnit: 'Bags' },
        { id: 'hw-3', sku: 'PNT-003', barcode: '616220110003', name: 'Crown Covermatt Emulsion Brilliant White 20L', category: 'Paints & Finishes', brand: 'Crown Paints', unit: '20L Bucket', costPrice: 3800, retailPrice: 4600, wholesalePrice: 4200, stock: 35, minStock: 10, taxRate: 16, specifications: 'Interior Matt Emulsion | 20 Litres', dimensionUnit: 'Buckets' },
        { id: 'hw-4', sku: 'TIM-004', barcode: '616220110004', name: 'Treated Cypress Timber 4" x 2" (18ft)', category: 'Timber & Boards', brand: 'Local Mill', unit: 'Piece (18ft)', costPrice: 450, retailPrice: 620, wholesalePrice: 540, stock: 80, minStock: 25, taxRate: 16, specifications: 'Vacuum CCA Pressure Treated 4x2x18', dimensionUnit: 'Running Ft' },
        { id: 'hw-5', sku: 'PLM-005', barcode: '616220110005', name: 'PPR Hot/Cold Pipe 25mm (4m Length)', category: 'Plumbing & Pipes', brand: 'Doshi Pipes', unit: 'Length (4m)', costPrice: 320, retailPrice: 450, wholesalePrice: 380, stock: 65, minStock: 20, taxRate: 16, specifications: 'PN20 High Pressure 25mm x 4m', dimensionUnit: 'Lengths' },
        { id: 'hw-6', sku: 'TLS-006', barcode: '616220110006', name: 'Bosch Professional 750W Angle Grinder 115mm', category: 'Power Tools', brand: 'Bosch', unit: 'Kit', costPrice: 6200, retailPrice: 7800, wholesalePrice: 7100, stock: 12, minStock: 4, taxRate: 16, specifications: 'GWS 750-115 | 11,000 RPM Heavy Duty', dimensionUnit: 'Units' },
    ],
    Restaurant: [
        { id: 'res-1', sku: 'KIT-01', barcode: 'RES001', name: 'Prime Aged Beef Ribeye Steak (350g)', category: 'Mains & Grills', brand: 'Kitchen Station', unit: 'Plate', costPrice: 950, retailPrice: 1850, wholesalePrice: 1850, stock: 25, minStock: 5, taxRate: 16, modifiers: 'Rare / Med-Rare / Well Done | Mushroom Sauce' },
        { id: 'res-2', sku: 'KIT-02', barcode: 'RES002', name: 'Grilled Norwegian Salmon with Herb Butter', category: 'Seafood', brand: 'Kitchen Station', unit: 'Plate', costPrice: 1200, retailPrice: 2200, wholesalePrice: 2200, stock: 18, minStock: 4, taxRate: 16, modifiers: 'Served with Mashed Potato & Asparagus' },
        { id: 'res-3', sku: 'KIT-03', barcode: 'RES003', name: 'Wood-Fired Pepperoni & Mozzarella Pizza 12"', category: 'Pizzas', brand: 'Pizza Oven', unit: 'Pan', costPrice: 450, retailPrice: 1100, wholesalePrice: 1100, stock: 40, minStock: 10, taxRate: 16, modifiers: 'Extra Cheese +150 | Thin Crust' },
        { id: 'res-4', sku: 'BAR-04', barcode: 'RES004', name: 'Tusker Lager 500ml Bottle (Ice Cold)', category: 'Bar & Beers', brand: 'Bar Dispenser', unit: 'Bottle', costPrice: 180, retailPrice: 300, wholesalePrice: 300, stock: 120, minStock: 24, taxRate: 16, modifiers: 'Cold / Room Temp' },
        { id: 'res-5', sku: 'BAR-05', barcode: 'RES005', name: 'Classic Mojito Cocktail (Bacardi Rum + Lime)', category: 'Cocktails', brand: 'Bar Dispenser', unit: 'Glass', costPrice: 220, retailPrice: 650, wholesalePrice: 650, stock: 50, minStock: 10, taxRate: 16, modifiers: 'Fresh Mint Leaves, Crushed Ice' },
        { id: 'res-6', sku: 'KIT-06', barcode: 'RES006', name: 'Warm Chocolate Lava Cake with Gelato', category: 'Desserts', brand: 'Pastry Station', unit: 'Plate', costPrice: 200, retailPrice: 550, wholesalePrice: 550, stock: 30, minStock: 8, taxRate: 16, modifiers: 'Vanilla Gelato Scoop' },
    ],
    Electronics: [
        { id: 'el-1', sku: 'APL-15P', barcode: '195949012345', name: 'Apple iPhone 15 Pro Max 256GB Natural Titanium', category: 'Smartphones', brand: 'Apple', unit: 'Box', costPrice: 165000, retailPrice: 195000, wholesalePrice: 182000, stock: 8, minStock: 2, taxRate: 16, serialNumber: 'SN-F2L99014Q', imeiNumber: '358941098124012', warrantyMonths: 12 },
        { id: 'el-2', sku: 'SAM-S24', barcode: '880609123456', name: 'Samsung Galaxy S24 Ultra 512GB Titanium Black', category: 'Smartphones', brand: 'Samsung', unit: 'Box', costPrice: 155000, retailPrice: 185000, wholesalePrice: 172000, stock: 6, minStock: 2, taxRate: 16, serialNumber: 'SN-R5CW3081J', imeiNumber: '356701089124551', warrantyMonths: 24 },
        { id: 'el-3', sku: 'DEL-XPS', barcode: '884116123457', name: 'Dell XPS 15 9530 (i7-13700H / 32GB RAM / 1TB SSD)', category: 'Laptops & Computers', brand: 'Dell', unit: 'Box', costPrice: 210000, retailPrice: 245000, wholesalePrice: 230000, stock: 4, minStock: 1, taxRate: 16, serialNumber: 'SN-DELL-88201X', warrantyMonths: 12 },
        { id: 'el-4', sku: 'SNY-WH1000', barcode: '454873123458', name: 'Sony WH-1000XM5 Wireless Noise-Canceling Headphones', category: 'Audio & Sound', brand: 'Sony', unit: 'Box', costPrice: 34000, retailPrice: 42000, wholesalePrice: 38000, stock: 14, minStock: 3, taxRate: 16, serialNumber: 'SN-SONY-99014A', warrantyMonths: 12 },
        { id: 'el-5', sku: 'ANK-PW65', barcode: '194644123459', name: 'Anker 735 GaNPrime 65W Fast Charger (3 Ports)', category: 'Accessories & Cables', brand: 'Anker', unit: 'Pack', costPrice: 4200, retailPrice: 5800, wholesalePrice: 5000, stock: 25, minStock: 5, taxRate: 16, warrantyMonths: 18 },
        { id: 'el-6', sku: 'LG-C3-55', barcode: '880609999991', name: 'LG 55" C3 4K OLED Smart TV (OLED55C36LA)', category: 'TVs & Displays', brand: 'LG', unit: 'Box', costPrice: 145000, retailPrice: 178000, wholesalePrice: 164000, stock: 3, minStock: 1, taxRate: 16, serialNumber: 'SN-LG-TV55C3-009', warrantyMonths: 24 },
    ],
    Wholesale: [
        { id: 'ws-1', sku: 'BLK-001', barcode: '616110990001', name: 'Brookside Whole Milk 500ml (Bale of 24 Pouches)', category: 'Dairy Bulk', brand: 'Brookside', unit: 'Bale (24s)', costPrice: 1560, retailPrice: 1920, wholesalePrice: 1728, stock: 80, minStock: 20, taxRate: 16 },
        { id: 'ws-2', sku: 'BLK-002', barcode: '616110990002', name: 'Pembe Maize Flour 2kg (Bale of 12 Packets)', category: 'Grain & Flours', brand: 'Pembe', unit: 'Bale (12s)', costPrice: 1680, retailPrice: 2040, wholesalePrice: 1860, stock: 120, minStock: 30, taxRate: 16 },
        { id: 'ws-3', sku: 'BLK-003', barcode: '616110990003', name: 'Fresh Fri Vegetable Oil 2L (Carton of 6 Bottles)', category: 'Edible Oils', brand: 'Pwani', unit: 'Carton (6s)', costPrice: 3480, retailPrice: 4140, wholesalePrice: 3780, stock: 45, minStock: 10, taxRate: 16 },
        { id: 'ws-4', sku: 'BLK-004', barcode: '616110990004', name: 'Mumias Sugar 1kg (Outer Bale of 20 Packets)', category: 'Sugar & Sweeteners', brand: 'Mumias', unit: 'Bale (20s)', costPrice: 3100, retailPrice: 3800, wholesalePrice: 3500, stock: 60, minStock: 15, taxRate: 16 },
        { id: 'ws-5', sku: 'BLK-005', barcode: '616110990005', name: 'Omo Handwash Washing Powder 1kg (Box of 12)', category: 'Detergents & Soap', brand: 'Unilever', unit: 'Box (12s)', costPrice: 2640, retailPrice: 3360, wholesalePrice: 2980, stock: 40, minStock: 10, taxRate: 16 },
        { id: 'ws-6', sku: 'BLK-006', barcode: '616110990006', name: 'Ketepa Tea Leaves 500g (Master Carton of 24)', category: 'Beverages', brand: 'Ketepa', unit: 'Carton (24s)', costPrice: 5760, retailPrice: 6960, wholesalePrice: 6360, stock: 30, minStock: 8, taxRate: 16 },
    ],
};

export function useBusinessTerms() {
    const { currentTenant } = useTenant();

    const terms = computed<BusinessTermsConfig>(() => {
        const type = (currentTenant.value?.businessType || 'Supermarket') as BusinessType;

        switch (type) {
            case 'Pharmacy':
                return {
                    businessType: 'Pharmacy',
                    themeColor: 'teal',
                    badgeLabel: 'Pharmacy & Dispensary',
                    itemLabel: 'Medication',
                    itemsPlural: 'Medications & Drugs',
                    codeLabel: 'Drug Code / Batch',
                    clientLabel: 'Patient',
                    clientPlural: 'Patients',
                    staffLabel: 'Pharmacist',
                    checkoutAction: 'Dispense & Bill',
                    holdOrderAction: 'Hold Prescription',
                    heldDrawerTitle: 'Pending Prescriptions',
                    receiptTitle: 'Prescription & Dispensation Receipt',
                    catalogTitle: 'Pharmaceutical Formulary',
                    categoryLabel: 'Drug Category',
                    unitLabel: 'Dosage Form',
                    showWholesaleToggle: false,
                    showBatchExpiry: true,
                    showPrescriptions: true,
                    showDimensionsAndSite: false,
                    showTableManagement: false,
                    showModifiers: false,
                    showSerialWarranty: false,
                    showPumpMeters: false,
                    searchPlaceholder: 'Search medication by Generic, Brand, or Drug Code (F1)...',
                    clientPlaceholder: 'Select or register patient (Name, Phone, Insurance ID)...',
                    emptyCartMessage: 'No medications in dispensation queue. Search above or scan barcode to dispense.',
                    quickShortcutsHint: 'F1: Scan Drug | F2: Patient | F4: Hold Rx | Space/F9: Dispense & Bill',
                };

            case 'Hardware':
                return {
                    businessType: 'Hardware',
                    themeColor: 'amber',
                    badgeLabel: 'Hardware & Building Supplies',
                    itemLabel: 'Material',
                    itemsPlural: 'Materials & Supplies',
                    codeLabel: 'SKU / Spec / Gauge',
                    clientLabel: 'Contractor / Client',
                    clientPlural: 'Contractors & Clients',
                    staffLabel: 'Sales Attendant',
                    checkoutAction: 'Dispatch & Invoice',
                    holdOrderAction: 'Save Proforma / Quote',
                    heldDrawerTitle: 'Saved Quotations & Proformas',
                    receiptTitle: 'Delivery Note & Tax Invoice',
                    catalogTitle: 'Materials & Equipment Master',
                    categoryLabel: 'Trade Department',
                    unitLabel: 'Measurement Unit',
                    showWholesaleToggle: true,
                    showBatchExpiry: false,
                    showPrescriptions: false,
                    showDimensionsAndSite: true,
                    showTableManagement: false,
                    showModifiers: false,
                    showSerialWarranty: false,
                    showPumpMeters: false,
                    searchPlaceholder: 'Search materials by Spec, Gauge, Brand, or SKU (F1)...',
                    clientPlaceholder: 'Select builder, contractor or site credit account...',
                    emptyCartMessage: 'No construction materials added. Search materials catalog or scan barcode.',
                    quickShortcutsHint: 'F1: Search Spec | F2: Contractor | F4: Save Quote | F8: Bulk Tier | Space: Dispatch & Invoice',
                };

            case 'Restaurant':
                return {
                    businessType: 'Restaurant',
                    themeColor: 'orange',
                    badgeLabel: 'Restaurant, Cafe & Bar',
                    itemLabel: 'Menu Item',
                    itemsPlural: 'Menu Items & Drinks',
                    codeLabel: 'Dish / Item Code',
                    clientLabel: 'Guest / Table',
                    clientPlural: 'Guests & Tables',
                    staffLabel: 'Server / Waiter',
                    checkoutAction: 'Settle Bill',
                    holdOrderAction: 'Open Table / KOT',
                    heldDrawerTitle: 'Active Dining Tables & Tabs',
                    receiptTitle: 'Restaurant & Bar Bill',
                    catalogTitle: 'Kitchen & Bar Menu',
                    categoryLabel: 'Menu Category',
                    unitLabel: 'Portion / Serving',
                    showWholesaleToggle: false,
                    showBatchExpiry: false,
                    showPrescriptions: false,
                    showDimensionsAndSite: false,
                    showTableManagement: true,
                    showModifiers: true,
                    showSerialWarranty: false,
                    showPumpMeters: false,
                    searchPlaceholder: 'Search dishes, drinks, or station code (F1)...',
                    clientPlaceholder: 'Assign Guest Name / Loyalty Member...',
                    emptyCartMessage: 'No dining orders added. Click menu categories or search dishes.',
                    quickShortcutsHint: 'F1: Menu Search | F2: Guest | F4: Active Tables | Space: Settle Bill',
                };

            case 'Electronics':
                return {
                    businessType: 'Electronics',
                    themeColor: 'violet',
                    badgeLabel: 'Electronics & Mobiles',
                    itemLabel: 'Device',
                    itemsPlural: 'Devices & Electronics',
                    codeLabel: 'Serial / IMEI / Model',
                    clientLabel: 'Customer',
                    clientPlural: 'Customers',
                    staffLabel: 'Sales Advisor',
                    checkoutAction: 'Checkout & Warranty',
                    holdOrderAction: 'Hold Layaway / Job',
                    heldDrawerTitle: 'Held Layaways & Repair Jobs',
                    receiptTitle: 'Tax Invoice & Warranty Certificate',
                    catalogTitle: 'Electronics & Gadget Catalog',
                    categoryLabel: 'Device Category',
                    unitLabel: 'Packaging',
                    showWholesaleToggle: true,
                    showBatchExpiry: false,
                    showPrescriptions: false,
                    showDimensionsAndSite: false,
                    showTableManagement: false,
                    showModifiers: false,
                    showSerialWarranty: true,
                    showPumpMeters: false,
                    searchPlaceholder: 'Scan IMEI, Serial Number, Model, or SKU (F1)...',
                    clientPlaceholder: 'Select customer for warranty registration...',
                    emptyCartMessage: 'Cart is empty. Scan device barcode or search serial number to begin.',
                    quickShortcutsHint: 'F1: Scan Serial | F2: Customer | F4: Hold Job | F8: Dealer Price | Space: Checkout & Warranty',
                };

            case 'Wholesale':
                return {
                    businessType: 'Wholesale',
                    themeColor: 'blue',
                    badgeLabel: 'Wholesale & B2B Distribution',
                    itemLabel: 'Merchandise',
                    itemsPlural: 'B2B Wholesale Catalog',
                    codeLabel: 'Carton / Outer Barcode',
                    clientLabel: 'Retail Account / Buyer',
                    clientPlural: 'Retail Accounts',
                    staffLabel: 'Billing Officer',
                    checkoutAction: 'Invoice & Dispatch',
                    holdOrderAction: 'Park LPO Order',
                    heldDrawerTitle: 'Parked Purchase Orders',
                    receiptTitle: 'Commercial Tax Invoice & Delivery Slip',
                    catalogTitle: 'Wholesale Distribution Catalog',
                    categoryLabel: 'Trade Line',
                    unitLabel: 'Bale / Outer Carton',
                    showWholesaleToggle: true,
                    showBatchExpiry: false,
                    showPrescriptions: false,
                    showDimensionsAndSite: false,
                    showTableManagement: false,
                    showModifiers: false,
                    showSerialWarranty: false,
                    showPumpMeters: false,
                    searchPlaceholder: 'Search bulk carton SKU, Bale barcode, or product line (F1)...',
                    clientPlaceholder: 'Select wholesale retailer or distributor account...',
                    emptyCartMessage: 'No wholesale lines selected. Scan carton barcode or pick product bales.',
                    quickShortcutsHint: 'F1: Scan Bale | F2: Retailer Acc | F4: Park LPO | F8: Tier Switch | Space: Invoice & Dispatch',
                };

            default: // Supermarket, General Trade, Retail
                return {
                    businessType: 'Supermarket',
                    themeColor: 'emerald',
                    badgeLabel: 'Supermarket & Retail POS',
                    itemLabel: 'Product',
                    itemsPlural: 'Products',
                    codeLabel: 'Barcode / SKU',
                    clientLabel: 'Customer',
                    clientPlural: 'Customers',
                    staffLabel: 'Cashier',
                    checkoutAction: 'Tender & Pay',
                    holdOrderAction: 'Hold Cart',
                    heldDrawerTitle: 'Held Sales Carts',
                    receiptTitle: 'Sales Receipt',
                    catalogTitle: 'Products Catalog',
                    categoryLabel: 'Category',
                    unitLabel: 'Unit of Measure',
                    showWholesaleToggle: true,
                    showBatchExpiry: false,
                    showPrescriptions: false,
                    showDimensionsAndSite: false,
                    showTableManagement: false,
                    showModifiers: false,
                    showSerialWarranty: false,
                    showPumpMeters: false,
                    searchPlaceholder: 'Scan barcode or search product name / SKU (F1)...',
                    clientPlaceholder: 'Search or select customer (F2)...',
                    emptyCartMessage: 'Scan a barcode or click products to begin ringing up sales.',
                    quickShortcutsHint: 'F1: Scan | F2: Customer | F4: Hold | F8: Retail/Wholesale | Space: Tender',
                };
        }
    });

    const activeCatalog = computed<ProductItem[]>(() => {
        const type = currentTenant.value?.businessType || 'Supermarket';
        return verticalCatalogs[type] || verticalCatalogs.Supermarket;
    });

    return {
        terms,
        activeCatalog,
    };
}
