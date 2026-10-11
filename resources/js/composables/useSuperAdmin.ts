import { reactive, ref } from 'vue';

export type SubscriptionPlan = 'Basic' | 'Pro' | 'Enterprise';
export type TenantStatus = 'Active' | 'Suspended' | 'Trial' | 'Expired';
export type BusinessType =
    | 'Supermarket'
    | 'Wholesale'
    | 'Pharmacy'
    | 'Restaurant'
    | 'Hardware'
    | 'General Trade'
    | 'Electronics'
    | 'Clothing & Apparel'
    | 'Bakery'
    | 'Petrol Station';

export type BillingCycle = 'Weekly' | 'Monthly' | 'Quarterly' | 'Semi-Annual' | 'Annual';

export interface TenantRegistrationInput {
    name: string;
    kraPin: string;
    email: string;
    phone: string;
    country: string;
    currency: string;
    businessType: BusinessType;
    plan: SubscriptionPlan;
    billingCycle: BillingCycle;
    branchCount: number;
    userCount: number;
    // Authorized Devices Configuration
    selectedDeviceTypes: string[];
    requireDeviceBinding: boolean;
    posHardwareCount: number;
    // Network & Connection Restrictions
    blockVpn: boolean;
    requireStoreWifi: boolean;
    primaryStoreWifiSSID: string;
    blockHotspot: boolean;
    cellularPolicy: 'allow_all' | 'field_roles_only' | 'block_cellular';
    singleSessionPerCashier: boolean;
}

export interface PlatformTenant {
    id: string;
    name: string;
    kraPin: string;
    email: string;
    phone: string;
    country: string;
    currency: string;
    plan: SubscriptionPlan;
    status: TenantStatus;
    businessType: BusinessType;
    userCount: number;
    branchCount: number;
    monthlyRevenue: number;
    billingCycle: BillingCycle;
    nextBillingDate: string;
    registeredAt: string;
    lastActive: string;
    storageUsedMB: number;
    storageQuotaMB: number;
    enabledModules: string[];
    customModules: string[];
}

export interface TenantSubscriptionInvoice {
    id: string;
    invoiceNumber: string;
    tenantId: string;
    tenantName: string;
    plan: SubscriptionPlan;
    billingCycle: BillingCycle;
    amount: number;
    currency: string;
    issuedDate: string;
    dueDate: string;
    paidDate?: string;
    status: 'Paid' | 'Pending' | 'Overdue' | 'Grace Period';
    paymentMethod: 'M-Pesa Express (STK)' | 'M-Pesa Paybill' | 'Bank Wire / RTGS' | 'Credit Card' | 'Cash / Cheque';
    autoRenew: boolean;
}

export const billingCycleProfiles: Record<BillingCycle, { label: string; days: number; discount: string }> = {
    Weekly: { label: 'Weekly (7 Days)', days: 7, discount: 'Standard' },
    Monthly: { label: 'Monthly (30 Days)', days: 30, discount: 'Standard' },
    Quarterly: { label: 'Quarterly (3 Months)', days: 90, discount: 'Save ~8%' },
    'Semi-Annual': { label: 'Semi-Annual (6 Months)', days: 180, discount: 'Save ~12%' },
    Annual: { label: 'Annual (12 Months)', days: 365, discount: 'Save ~17% (2 Months Free)' },
};

export interface TenantPaymentConfig {
    tenantId: string;
    mpesaStk: boolean;
    mpesaPaybill: boolean;
    mpesaTill: boolean;
    cardGateway: boolean;
    cash: boolean;
    bankTransfer: boolean;
    creditLedger: boolean;
    giftVouchers: boolean;
    etimsAutoInvoice: boolean;
    mpesaEnvironment: 'sandbox' | 'production';
    paybillNumber: string;
    tillNumber: string;
    customSurchargePercent: number;
    allowPartialPayment: boolean;
}

export interface TenantActivityEvent {
    id: string;
    timestamp: string;
    actor: string;
    role: string;
    branch: string;
    action: string;
    type: 'sale' | 'payment' | 'inventory' | 'auth' | 'compliance' | 'report';
    detail: string;
}

export interface TenantTelemetry {
    tenantId: string;
    pulseRateRPS: number; // live requests / second
    queryVelocityQPM: number; // DB queries / minute
    activeCashiersCount: number;
    activeSessionsCount: number;
    latencyMS: number;
    errorRatePercent: number;
    cpuUsagePercent: number;
    memoryUsageMB: number;
    memoryQuotaMB: number;
    bandwidthTodayGB: number;
    databaseSizeMB: number;
    mediaUploadsSizeMB: number;
    liveActivities: TenantActivityEvent[];
}

export interface BackupSnapshot {
    id: string;
    tenantId: string | 'PLATFORM_ALL';
    tenantName: string;
    fileName: string;
    type: 'Full System' | 'Tenant DB & Media' | 'DB Only';
    sizeMB: number;
    destination: 'AWS S3 (eu-west-1)' | 'Local Storage' | 'Google Cloud Storage';
    status: 'Completed' | 'In Progress' | 'Failed';
    checksum: string;
    createdAt: string;
    autoScheduled: boolean;
}

export interface PlanFeature {
    name: string;
    basic: boolean | string;
    pro: boolean | string;
    enterprise: boolean | string;
}

// All available platform modules
export const allModules: { key: string; label: string; description: string; icon: string }[] = [
    { key: 'pos', label: 'POS Register', description: 'Cashier terminal, barcode scan, receipt printing', icon: 'ShoppingCart' },
    { key: 'sales', label: 'Sales & Ledger', description: 'Transaction history, voiding, daily summaries', icon: 'Receipt' },
    { key: 'products', label: 'Product Catalog', description: 'SKU management, pricing tiers, barcode generation', icon: 'Package' },
    { key: 'inventory', label: 'Inventory Control', description: 'Stock levels, transfers, shrinkage adjustments', icon: 'Boxes' },
    { key: 'purchases', label: 'Purchase Orders', description: 'LPOs, receiving notes, supplier invoices', icon: 'ClipboardList' },
    { key: 'customers', label: 'Customer Accounts', description: 'Customer registry, loyalty, credit balances', icon: 'Users' },
    { key: 'suppliers', label: 'Supplier Management', description: 'Vendor contacts, payables, payment scheduling', icon: 'Truck' },
    { key: 'expenses', label: 'Expense Tracking', description: 'Overhead costs, petty cash, staff allowances', icon: 'DollarSign' },
    { key: 'reports', label: 'Reports & Analytics', description: 'P&L, VAT, sales trends, inventory valuation', icon: 'BarChart3' },
    { key: 'users', label: 'Staff & Permissions', description: 'Role matrix, duty roster, staff invitations', icon: 'ShieldCheck' },
    { key: 'branches', label: 'Branch Outlets', description: 'Multi-location management and branch KPIs', icon: 'Store' },
    { key: 'settings', label: 'System Settings', description: 'Business profile, payment channels, KRA config', icon: 'Settings' },
    { key: 'prescriptions', label: 'Prescriptions (Rx)', description: 'Pharmacy prescription tracking and dispensing log', icon: 'Pill' },
    { key: 'tables', label: 'Table Management', description: 'Restaurant floor plan, order routing, bill splitting', icon: 'UtensilsCrossed' },
    { key: 'kitchen', label: 'Kitchen Display (KDS)', description: 'Real-time kitchen order queue and ticket printing', icon: 'ChefHat' },
    { key: 'fuel', label: 'Fuel Pump Control', description: 'Pump dispenser integration and fuel sales tracking', icon: 'Fuel' },
    { key: 'loyalty', label: 'Loyalty & Rewards', description: 'Points accumulation, redemption, and tier programs', icon: 'Star' },
    { key: 'delivery', label: 'Delivery & Orders', description: 'Delivery dispatch, order tracking, rider assignment', icon: 'MapPin' },
];

// Business type presets
export const businessTypeProfiles: Record<BusinessType, { modules: string[]; description: string; color: string }> = {
    Supermarket: {
        description: 'Full retail grocery — multi-department, high SKU count, fast cashier throughput',
        color: 'emerald',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
            'loyalty',
        ],
    },
    Wholesale: {
        description: 'Bulk distribution — wholesale pricing tiers, LPOs, credit accounts',
        color: 'blue',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
    Pharmacy: {
        description: 'Pharmaceutical retail — prescription tracking, batch/expiry management, controlled substances',
        color: 'rose',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'settings',
            'prescriptions',
        ],
    },
    Restaurant: {
        description: 'Food & beverage — table management, kitchen display, menu modifiers, split billing',
        color: 'orange',
        modules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'expenses', 'reports', 'users', 'settings', 'tables', 'kitchen'],
    },
    Hardware: {
        description: 'Building materials & hardware — heavy SKU catalog, quotations, project billing',
        color: 'amber',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
    'General Trade': {
        description: 'Mixed retail — broad product range, cash-and-carry, basic inventory management',
        color: 'teal',
        modules: ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings'],
    },
    Electronics: {
        description: 'Electronics & gadgets — serial number tracking, warranties, service & repair',
        color: 'violet',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
    'Clothing & Apparel': {
        description: 'Fashion retail — size/color variants, seasonal stock, loyalty programs',
        color: 'pink',
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'settings',
            'loyalty',
        ],
    },
    Bakery: {
        description: 'Bakery & confectionery — production tracking, daily output, perishable stock',
        color: 'yellow',
        modules: ['pos', 'sales', 'products', 'inventory', 'customers', 'expenses', 'reports', 'users', 'settings'],
    },
    'Petrol Station': {
        description: 'Fuel retail — pump dispenser integration, fuel stock, lubes & accessories',
        color: 'slate',
        modules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'expenses', 'reports', 'users', 'settings', 'fuel'],
    },
};

// Platform payment methods
export const platformPaymentMethods = [
    {
        key: 'mpesaStk',
        label: 'M-Pesa STK Push',
        description: 'Prompt customer phone directly for instant PIN checkout',
        category: 'Mobile Money',
        icon: 'Smartphone',
    },
    {
        key: 'mpesaPaybill',
        label: 'M-Pesa Paybill (C2B)',
        description: 'Automated IPN confirmation on business Paybill number',
        category: 'Mobile Money',
        icon: 'PhoneCall',
    },
    {
        key: 'mpesaTill',
        label: 'M-Pesa Buy Goods Till',
        description: 'Till number validation and instant cash register sync',
        category: 'Mobile Money',
        icon: 'QrCode',
    },
    {
        key: 'cardGateway',
        label: 'Card Gateway / PDQ',
        description: 'Visa / Mastercard online and connected POS terminal processing',
        category: 'Card & Bank',
        icon: 'CreditCard',
    },
    {
        key: 'bankTransfer',
        label: 'Bank Transfer / RTGS / EFT',
        description: 'Direct bank slip reference check & account crediting',
        category: 'Card & Bank',
        icon: 'Landmark',
    },
    {
        key: 'cash',
        label: 'Cash Tender',
        description: 'Physical cash drawer balancing & change calculation',
        category: 'Cash & Credit',
        icon: 'Banknote',
    },
    {
        key: 'creditLedger',
        label: 'Customer Store Credit',
        description: 'Post-paid debtor ledger with credit limit enforcement',
        category: 'Cash & Credit',
        icon: 'BookOpen',
    },
    {
        key: 'giftVouchers',
        label: 'Gift Cards & Vouchers',
        description: 'Barcoded voucher redemption & promotional discount codes',
        category: 'Value & Promo',
        icon: 'Gift',
    },
    {
        key: 'etimsAutoInvoice',
        label: 'KRA eTIMS Auto-Sync',
        description: 'Generate cryptographically signed fiscal receipt with QR code',
        category: 'Compliance',
        icon: 'FileCheck',
    },
];

export const subscriptionPlans = {
    Basic: {
        price: 2500,
        label: 'Basic',
        maxBranches: 1,
        maxUsers: 5,
        storageMB: 500,
        modules: ['pos', 'sales', 'products', 'inventory', 'customers'],
    },
    Pro: {
        price: 6500,
        label: 'Pro',
        maxBranches: 5,
        maxUsers: 20,
        storageMB: 5000,
        modules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports'],
    },
    Enterprise: {
        price: 15000,
        label: 'Enterprise',
        maxBranches: -1,
        maxUsers: -1,
        storageMB: 50000,
        modules: [
            'pos',
            'sales',
            'products',
            'inventory',
            'purchases',
            'customers',
            'suppliers',
            'expenses',
            'reports',
            'users',
            'branches',
            'settings',
        ],
    },
};

export const planFeatures: PlanFeature[] = [
    { name: 'POS Register', basic: true, pro: true, enterprise: true },
    { name: 'Sales & Returns', basic: true, pro: true, enterprise: true },
    { name: 'Product Catalog', basic: true, pro: true, enterprise: true },
    { name: 'Inventory Management', basic: true, pro: true, enterprise: true },
    { name: 'Customer Accounts', basic: true, pro: true, enterprise: true },
    { name: 'Purchase Orders', basic: false, pro: true, enterprise: true },
    { name: 'Supplier Management', basic: false, pro: true, enterprise: true },
    { name: 'Expense Tracking', basic: false, pro: true, enterprise: true },
    { name: 'Reports & Analytics', basic: false, pro: true, enterprise: true },
    { name: 'Multi-Branch Support', basic: '1 Branch', pro: 'Up to 5', enterprise: 'Unlimited' },
    { name: 'Staff User Accounts', basic: 'Up to 5', pro: 'Up to 20', enterprise: 'Unlimited' },
    { name: 'Role Permissions Matrix', basic: false, pro: true, enterprise: true },
    { name: 'Duty Roster & Shifts', basic: false, pro: true, enterprise: true },
    { name: 'M-Pesa STK Push', basic: true, pro: true, enterprise: true },
    { name: 'Storage Quota', basic: '500 MB', pro: '5 GB', enterprise: '50 GB' },
    { name: 'API Access', basic: false, pro: false, enterprise: true },
    { name: 'Priority Support', basic: false, pro: false, enterprise: true },
    { name: 'Custom Branding', basic: false, pro: false, enterprise: true },
];

// Platform data stores
const tenantsData = reactive<PlatformTenant[]>([]);
const tenantPaymentConfigs = reactive<Record<string, TenantPaymentConfig>>({});
const backupsData = reactive<BackupSnapshot[]>([]);
const systemUsersData = reactive<any[]>([]);
const subscriptionInvoicesData = reactive<TenantSubscriptionInvoice[]>([]);

const selectedTenantId = ref<string | null>(null);

export function useSuperAdmin() {
    const totalTenants = tenantsData.length;
    const activeTenants = tenantsData.filter((t) => t.status === 'Active').length;
    const suspendedTenants = tenantsData.filter((t) => t.status === 'Suspended').length;
    const trialTenants = tenantsData.filter((t) => t.status === 'Trial').length;
    const totalMRR = tenantsData.reduce((s, t) => s + t.monthlyRevenue, 0);
    const totalUsers = tenantsData.reduce((s, t) => s + t.userCount, 0);
    const totalBranches = tenantsData.reduce((s, t) => s + t.branchCount, 0);

    const suspendTenant = (id: string) => {
        const t = tenantsData.find((x) => x.id === id);
        if (t) t.status = 'Suspended';
    };

    const activateTenant = (id: string) => {
        const t = tenantsData.find((x) => x.id === id);
        if (t) t.status = 'Active';
    };

    const upgradePlan = (id: string, plan: SubscriptionPlan) => {
        const t = tenantsData.find((x) => x.id === id);
        if (t) {
            t.plan = plan;
            t.monthlyRevenue = subscriptionPlans[plan].price;
        }
    };

    const deleteTenant = (id: string) => {
        const idx = tenantsData.findIndex((x) => x.id === id);
        if (idx !== -1) tenantsData.splice(idx, 1);
    };

    const updateTenantModules = (id: string, modules: string[]) => {
        const t = tenantsData.find((x) => x.id === id);
        if (t) t.enabledModules = [...modules];
    };

    const applyBusinessTypePreset = (id: string, businessType: BusinessType) => {
        const t = tenantsData.find((x) => x.id === id);
        if (t) {
            t.businessType = businessType;
            t.enabledModules = [...businessTypeProfiles[businessType].modules];
            t.customModules = [];
        }
    };

    const toggleTenantModule = (id: string, moduleKey: string) => {
        const t = tenantsData.find((x) => x.id === id);
        if (!t) return;
        const idx = t.enabledModules.indexOf(moduleKey);
        if (idx !== -1) {
            t.enabledModules.splice(idx, 1);
        } else {
            t.enabledModules.push(moduleKey);
        }
    };

    const getTenantPaymentConfig = (tenantId: string): TenantPaymentConfig => {
        if (!tenantPaymentConfigs[tenantId]) {
            tenantPaymentConfigs[tenantId] = {
                tenantId,
                mpesaStk: true,
                mpesaPaybill: false,
                mpesaTill: true,
                cardGateway: false,
                cash: true,
                bankTransfer: false,
                creditLedger: false,
                giftVouchers: false,
                etimsAutoInvoice: false,
                mpesaEnvironment: 'sandbox',
                paybillNumber: '',
                tillNumber: '',
                customSurchargePercent: 0,
                allowPartialPayment: false,
            };
        }
        return tenantPaymentConfigs[tenantId];
    };

    const updateTenantPaymentConfig = (tenantId: string, updates: Partial<TenantPaymentConfig>) => {
        const existing = getTenantPaymentConfig(tenantId);
        Object.assign(existing, updates);
    };

    const toggleTenantPaymentMethod = (tenantId: string, methodKey: keyof TenantPaymentConfig) => {
        const cfg = getTenantPaymentConfig(tenantId);
        if (typeof cfg[methodKey] === 'boolean') {
            (cfg[methodKey] as boolean) = !cfg[methodKey];
        }
    };

    const createBackup = (
        tenantId: string,
        type: 'Full System' | 'Tenant DB & Media' | 'DB Only',
        destination: 'AWS S3 (eu-west-1)' | 'Local Storage' | 'Google Cloud Storage',
    ) => {
        const tenant = tenantId === 'PLATFORM_ALL' ? null : tenantsData.find((t) => t.id === tenantId);
        const name = tenant ? tenant.name : 'Platform Full Snapshot (All Tenants)';
        const newBackup: BackupSnapshot = {
            id: `BKP-${Math.floor(1000 + Math.random() * 9000)}`,
            tenantId,
            tenantName: name,
            fileName: tenant ? `tenant_${tenant.id.toLowerCase()}_manual_${Date.now()}.zip` : `mde_full_snapshot_${Date.now()}.sql.gz`,
            type,
            sizeMB: tenant ? Math.max(12, Math.round(tenant.storageUsedMB * 0.8)) : 5120,
            destination,
            status: 'Completed',
            checksum: `sha256:${Math.random().toString(36).substring(2, 15)}...`,
            createdAt: 'Just now',
            autoScheduled: false,
        };
        backupsData.unshift(newBackup);
        return newBackup;
    };

    const deleteBackup = (backupId: string) => {
        const idx = backupsData.findIndex((b) => b.id === backupId);
        if (idx !== -1) backupsData.splice(idx, 1);
    };

    const restoreBackup = (backupId: string) => {
        const backup = backupsData.find((b) => b.id === backupId);
        return backup;
    };

    // liveTenant supports both camelCase (mock) and snake_case (DB) field names
    const getTenantTelemetry = (tenantId: string | number, liveTenant?: Record<string, any>): TenantTelemetry => {
        // Resolve tenant: prefer the passed liveTenant, else look up in mock data
        const mockTenant = tenantsData.find((t) => String(t.id) === String(tenantId));

        const plan: string = liveTenant?.plan ?? mockTenant?.plan ?? 'Basic';
        const userCount: number = liveTenant?.user_count ?? liveTenant?.userCount ?? mockTenant?.userCount ?? 5;
        const storageUsedMB: number = liveTenant?.storage_used_mb ?? liveTenant?.storageUsedMB ?? mockTenant?.storageUsedMB ?? 512;

        const multiplier = plan === 'Enterprise' ? 3.5 : plan === 'Pro' ? 1.8 : 0.6;

        return {
            tenantId: String(tenantId),
            pulseRateRPS: Math.round(14 * multiplier + Math.random() * 8),
            queryVelocityQPM: Math.round(180 * multiplier + Math.random() * 50),
            activeCashiersCount: Math.max(1, Math.round(userCount * 0.6)),
            activeSessionsCount: Math.max(1, Math.round(userCount * 0.8)),
            latencyMS: Math.round(12 + Math.random() * 8),
            errorRatePercent: 0.0,
            cpuUsagePercent: Math.round(18 * multiplier + Math.random() * 5),
            memoryUsageMB: Math.round(512 * multiplier + Math.random() * 128),
            memoryQuotaMB: plan === 'Enterprise' ? 8192 : plan === 'Pro' ? 4096 : 2048,
            bandwidthTodayGB: parseFloat((1.2 * multiplier + Math.random() * 0.8).toFixed(2)),
            databaseSizeMB: Math.round(storageUsedMB * 0.35),
            mediaUploadsSizeMB: Math.round(storageUsedMB * 0.65),
            liveActivities: [
                {
                    id: `ACT-${Date.now()}-1`,
                    timestamp: 'Just now',
                    actor: 'Grace Wanjiku',
                    role: 'Cashier',
                    branch: 'Main Branch POS #01',
                    action: 'POS Barcode Scan & Checkout',
                    type: 'sale',
                    detail: 'Processed cart item SKU-0081 (KES 1,450.00)',
                },
                {
                    id: `ACT-${Date.now()}-2`,
                    timestamp: '1 min ago',
                    actor: 'M-Pesa Gateway',
                    role: 'System Bot',
                    branch: 'Daraja STK Endpoint',
                    action: 'Instant STK Payment Received',
                    type: 'payment',
                    detail: 'STK Push confirmation received: Code QK992104 (KES 3,200.00)',
                },
                {
                    id: `ACT-${Date.now()}-3`,
                    timestamp: '3 mins ago',
                    actor: 'David Mwangi',
                    role: 'Manager',
                    branch: 'Backoffice Station #02',
                    action: 'Z-Report Generation',
                    type: 'report',
                    detail: 'Calculated daily shift revenue breakdown for Register #01 & #02',
                },
                {
                    id: `ACT-${Date.now()}-4`,
                    timestamp: '6 mins ago',
                    actor: 'KRA eTIMS Fiscal Node',
                    role: 'Compliance API',
                    branch: 'Cloud VSCU',
                    action: 'Cryptographic QR Invoiced',
                    type: 'compliance',
                    detail: 'Fiscal receipt invoice #INV-9921 transmitted to KRA tax server (200 OK)',
                },
                {
                    id: `ACT-${Date.now()}-5`,
                    timestamp: '12 mins ago',
                    actor: 'Kevin Otieno',
                    role: 'Inventory Manager',
                    branch: 'Central Warehouse',
                    action: 'Stock Receiving (LPO #402)',
                    type: 'inventory',
                    detail: 'Received 120 units SKU-1120 (Cooking Oil 2L) from Bidco Africa',
                },
            ],
        };
    };

    // Mock subscription invoices repository
    const subscriptionInvoicesData = reactive<TenantSubscriptionInvoice[]>([
        {
            id: 'INV-9021',
            invoiceNumber: 'MDE-SUB-2026-0801',
            tenantId: 'T-001',
            tenantName: 'Nairobi Fresh Mart',
            plan: 'Pro',
            billingCycle: 'Monthly',
            amount: 6500,
            currency: 'KES',
            issuedDate: '2026-08-01',
            dueDate: '2026-09-01',
            paidDate: '2026-08-01',
            status: 'Paid',
            paymentMethod: 'M-Pesa Express (STK)',
            autoRenew: true,
        },
        {
            id: 'INV-9020',
            invoiceNumber: 'MDE-SUB-2026-0115',
            tenantId: 'T-002',
            tenantName: 'Westlands Electronics',
            plan: 'Enterprise',
            billingCycle: 'Annual',
            amount: 150000,
            currency: 'KES',
            issuedDate: '2026-01-15',
            dueDate: '2027-01-15',
            paidDate: '2026-01-15',
            status: 'Paid',
            paymentMethod: 'Bank Wire / RTGS',
            autoRenew: true,
        },
        {
            id: 'INV-9019',
            invoiceNumber: 'MDE-SUB-2026-0630',
            tenantId: 'T-003',
            tenantName: 'ABC Wholesale Ltd',
            plan: 'Enterprise',
            billingCycle: 'Quarterly',
            amount: 42000,
            currency: 'KES',
            issuedDate: '2026-06-30',
            dueDate: '2026-09-30',
            paidDate: '2026-06-30',
            status: 'Paid',
            paymentMethod: 'Credit Card',
            autoRenew: true,
        },
        {
            id: 'INV-9018',
            invoiceNumber: 'MDE-SUB-2026-0810',
            tenantId: 'T-005',
            tenantName: 'Sunset Pharmacy Ltd',
            plan: 'Pro',
            billingCycle: 'Weekly',
            amount: 1800,
            currency: 'KES',
            issuedDate: '2026-08-10',
            dueDate: '2026-08-17',
            paidDate: '2026-08-10',
            status: 'Paid',
            paymentMethod: 'M-Pesa Paybill',
            autoRenew: false,
        },
        {
            id: 'INV-9017',
            invoiceNumber: 'MDE-SUB-2026-0801-OVR',
            tenantId: 'T-006',
            tenantName: 'Coast Hardware & Steel',
            plan: 'Basic',
            billingCycle: 'Monthly',
            amount: 2500,
            currency: 'KES',
            issuedDate: '2026-07-01',
            dueDate: '2026-08-01',
            status: 'Overdue',
            paymentMethod: 'M-Pesa Express (STK)',
            autoRenew: false,
        },
    ]);

    const assignTenantSubscription = (
        tenantId: string,
        plan: SubscriptionPlan,
        cycle: BillingCycle,
        customAmount: number,
        paymentMethod: TenantSubscriptionInvoice['paymentMethod'],
        autoRenew: boolean,
    ) => {
        const tenant = tenantsData.find((t) => t.id === tenantId);
        if (!tenant) return;

        const cycleDays = billingCycleProfiles[cycle].days;
        const now = new Date();
        const nextDate = new Date(now.getTime() + cycleDays * 24 * 60 * 60 * 1000);
        const nextDateStr = nextDate.toISOString().split('T')[0];

        tenant.plan = plan;
        tenant.billingCycle = cycle;
        tenant.status = 'Active';
        tenant.monthlyRevenue =
            cycle === 'Weekly'
                ? customAmount * 4
                : cycle === 'Annual'
                  ? Math.round(customAmount / 12)
                  : cycle === 'Quarterly'
                    ? Math.round(customAmount / 3)
                    : customAmount;
        tenant.nextBillingDate = nextDateStr;

        const newInvoice: TenantSubscriptionInvoice = {
            id: `INV-${Math.floor(1000 + Math.random() * 9000)}`,
            invoiceNumber: `MDE-SUB-${Date.now().toString().slice(-6)}`,
            tenantId: tenant.id,
            tenantName: tenant.name,
            plan,
            billingCycle: cycle,
            amount: customAmount,
            currency: 'KES',
            issuedDate: new Date().toISOString().split('T')[0],
            dueDate: nextDateStr,
            paidDate: new Date().toISOString().split('T')[0],
            status: 'Paid',
            paymentMethod,
            autoRenew,
        };

        subscriptionInvoicesData.unshift(newInvoice);
        return newInvoice;
    };

    const registerTenant = (input: TenantRegistrationInput): PlatformTenant => {
        const nextNum = tenantsData.length + 1;
        const newId = `T-${nextNum.toString().padStart(3, '0')}`;
        const basePrice = subscriptionPlans[input.plan].price;
        const cycleDays = billingCycleProfiles[input.billingCycle].days;
        const now = new Date();
        const nextDate = new Date(now.getTime() + cycleDays * 24 * 60 * 60 * 1000);
        const presetModules = [...(businessTypeProfiles[input.businessType]?.modules || [])];

        const newTenant: PlatformTenant = {
            id: newId,
            name: input.name,
            kraPin: input.kraPin || `P05${Math.floor(10000000 + Math.random() * 90000000)}Z`,
            email: input.email,
            phone: input.phone,
            country: input.country || 'Kenya',
            currency: input.currency || 'KES',
            plan: input.plan,
            status: 'Active',
            businessType: input.businessType,
            userCount: input.userCount || 3,
            branchCount: input.branchCount || 1,
            monthlyRevenue: basePrice,
            billingCycle: input.billingCycle,
            nextBillingDate: nextDate.toISOString().split('T')[0],
            registeredAt: 'Today',
            lastActive: 'Just now',
            storageUsedMB: 120,
            storageQuotaMB: input.plan === 'Enterprise' ? 102400 : input.plan === 'Pro' ? 20480 : 5120,
            enabledModules: presetModules,
            customModules: [],
        };

        tenantsData.unshift(newTenant);

        // Register default payment gateway config
        tenantPaymentConfigs[newId] = {
            tenantId: newId,
            mpesaStk: true,
            mpesaPaybill: true,
            mpesaTill: true,
            cardGateway: input.plan === 'Enterprise',
            cash: true,
            bankTransfer: input.plan === 'Enterprise',
            creditLedger: true,
            giftVouchers: input.plan !== 'Basic',
            etimsAutoInvoice: true,
            mpesaEnvironment: 'sandbox',
            paybillNumber: `88${Math.floor(1000 + Math.random() * 9000)}`,
            tillNumber: `55${Math.floor(1000 + Math.random() * 9000)}`,
            customSurchargePercent: 0,
            allowPartialPayment: true,
        };

        // Create initial invoice
        const initialInvoice: TenantSubscriptionInvoice = {
            id: `INV-${Math.floor(1000 + Math.random() * 9000)}`,
            invoiceNumber: `MDE-SUB-${Date.now().toString().slice(-6)}`,
            tenantId: newId,
            tenantName: input.name,
            plan: input.plan,
            billingCycle: input.billingCycle,
            amount: basePrice,
            currency: 'KES',
            issuedDate: new Date().toISOString().split('T')[0],
            dueDate: nextDate.toISOString().split('T')[0],
            paidDate: new Date().toISOString().split('T')[0],
            status: 'Paid',
            paymentMethod: 'M-Pesa Express (STK)',
            autoRenew: true,
        };
        subscriptionInvoicesData.unshift(initialInvoice);

        return newTenant;
    };

    const extendTenantSubscription = (tenantId: string, extraDays: number) => {
        const tenant = tenantsData.find((t) => t.id === tenantId);
        if (!tenant) return;

        const currentExpiry = new Date(tenant.nextBillingDate || Date.now());
        const extended = new Date(currentExpiry.getTime() + extraDays * 24 * 60 * 60 * 1000);
        tenant.nextBillingDate = extended.toISOString().split('T')[0];
        tenant.status = 'Active';
    };

    return {
        tenants: tenantsData,
        systemUsers: systemUsersData,
        selectedTenantId,
        totalTenants,
        activeTenants,
        suspendedTenants,
        trialTenants,
        totalMRR,
        totalUsers,
        totalBranches,
        suspendTenant,
        activateTenant,
        upgradePlan,
        deleteTenant,
        updateTenantModules,
        applyBusinessTypePreset,
        toggleTenantModule,
        tenantPaymentConfigs,
        platformPaymentMethods,
        getTenantPaymentConfig,
        updateTenantPaymentConfig,
        toggleTenantPaymentMethod,
        getTenantTelemetry,
        backups: backupsData,
        createBackup,
        deleteBackup,
        restoreBackup,
        // Subscriptions & Billing management
        subscriptionInvoices: subscriptionInvoicesData,
        registerTenant,
        assignTenantSubscription,
        extendTenantSubscription,
        billingCycleProfiles,
        subscriptionPlans,
        planFeatures,
        allModules,
        businessTypeProfiles,
    };
}
