import { usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import type { BusinessType } from '@/composables/useSuperAdmin';

export interface PaymentMethodConfig {
    id: string;
    code: string;
    name: string;
    type: 'Cash' | 'Mobile Money' | 'Card' | 'Bank' | 'Credit' | 'Other';
    enabled: boolean;
    is_default?: boolean;
    fee_percent?: number;
    instructions?: string;
    accountRef?: string;
    details?: {
        till_number?: string;
        store_name?: string;
        business_number?: string;
        account_number?: string;
        account_type?: string;
        terminal_id?: string;
        bank_name?: string;
        account_name?: string;
        drawer_label?: string;
        [key: string]: any;
    };
}

export interface Tenant {
    id: string;
    name: string;
    slug: string;
    businessType: BusinessType;
    plan: 'Starter' | 'Pro' | 'Enterprise';
    currency: string;
    logo?: string;
    kraPin?: string;
    phone: string;
    email: string;
    address: string;
    enabledModules: string[];
    branchCount?: number;
    isMultiBranch?: boolean;
    expiresAt?: string;
    nextBillingDate?: string;
    isDemo?: boolean;
    settings?: Record<string, any>;
}

const state = reactive<{
    selectedTenantId: string;
}>({
    selectedTenantId: '',
});


export function useTenant() {
    const page = usePage();
    const DEMO_SLUGS = ['nairobi-fresh-mart', 'metro-wholesale-retail', 'apex-care-pharmacy', 'nairobi-fresh-clean', 'metro-wholesale-clean'];
    
    const isDemo = computed(() => {
        const serverTenant = (page?.props?.tenant || page?.props?.auth?.tenant) as any;
        if (serverTenant && typeof serverTenant.is_demo !== 'undefined') {
            return Boolean(serverTenant.is_demo);
        }
        if ((page?.props as any)?.demo?.is_demo !== undefined) {
            return Boolean((page?.props as any)?.demo?.is_demo);
        }
        const slug = currentTenant.value?.slug || '';
        return DEMO_SLUGS.includes(slug);
    });

    const currentTenant = computed<Tenant>(() => {
        // Priority 1: Top-level shared tenant (resolved via subdomain or auth)
        const serverTenant = (page?.props?.tenant || page?.props?.auth?.tenant) as any;
        if (serverTenant && serverTenant.name) {
            const isThisDemo = typeof serverTenant.is_demo !== 'undefined'
                ? Boolean(serverTenant.is_demo)
                : (DEMO_SLUGS.includes(serverTenant.slug) || !!(page?.props as any)?.demo?.is_demo);
            
            const rawName = String(serverTenant.name);
            const cleanName = rawName.replace(/^\[DEMO\]\s*/i, '');
            const displayName = isThisDemo ? `[DEMO] ${cleanName}` : cleanName;

            const branchCount = Number(serverTenant.branch_count ?? serverTenant.branchCount ?? 1);
            const isMulti = typeof serverTenant.is_multi_branch !== 'undefined'
                ? Boolean(serverTenant.is_multi_branch)
                : (branchCount > 1 && (serverTenant.enabled_modules || []).includes('branches'));

            return {
                id: String(serverTenant.id),
                name: displayName,
                slug: serverTenant.slug,
                businessType: (serverTenant.business_type || serverTenant.businessType || 'Supermarket') as BusinessType,
                plan: (serverTenant.plan || 'Pro') as 'Starter' | 'Pro' | 'Enterprise',
                currency: serverTenant.currency || 'KES',
                kraPin: serverTenant.kra_pin || serverTenant.kraPin,
                phone: serverTenant.phone || '',
                email: serverTenant.email || '',
                address: serverTenant.address || '',
                enabledModules: serverTenant.enabled_modules || serverTenant.enabledModules || [],
                branchCount,
                isMultiBranch: isMulti,
                expiresAt: serverTenant.expires_at || serverTenant.expiresAt,
                nextBillingDate: serverTenant.next_billing_date || serverTenant.nextBillingDate,
                isDemo: isThisDemo,
                settings: serverTenant.settings || {},
            };
        }

        return {
            id: '',
            name: 'Workspace',
            slug: '',
            businessType: 'Supermarket' as BusinessType,
            plan: 'Pro',
            currency: 'KES',
            phone: '',
            email: '',
            address: '',
            enabledModules: ['pos', 'sales', 'products', 'inventory', 'customers', 'settings'],
            branchCount: 1,
            isMultiBranch: false,
            isDemo: false,
            settings: {},
        };
    });

    const defaultPaymentMethods: PaymentMethodConfig[] = [
        {
            id: 'pm_cash',
            code: 'cash',
            name: 'Cash',
            type: 'Cash',
            enabled: true,
            is_default: true,
            fee_percent: 0,
            instructions: 'Accept cash notes into till drawer',
            details: { drawer_label: 'Store Cash Drawer' },
        },
        {
            id: 'pm_mpesa_till',
            code: 'mpesa_till',
            name: 'M-Pesa Buy Goods',
            type: 'Mobile Money',
            enabled: true,
            is_default: false,
            fee_percent: 0,
            instructions: 'Lipa na M-Pesa -> Buy Goods and Services',
            details: { till_number: '523910', store_name: 'Store Till', enable_stk: true },
        },
        {
            id: 'pm_mpesa_paybill',
            code: 'mpesa_paybill',
            name: 'M-Pesa Paybill',
            type: 'Mobile Money',
            enabled: false,
            is_default: false,
            fee_percent: 0,
            instructions: 'Lipa na M-Pesa -> Paybill',
            details: { business_number: '247247', account_number: 'STORE', account_type: 'fixed' },
        },
        {
            id: 'pm_card',
            code: 'card',
            name: 'Card (PDQ)',
            type: 'Card',
            enabled: true,
            is_default: false,
            fee_percent: 0,
            instructions: 'Swipe or tap card on terminal',
            details: { terminal_id: 'PDQ-8901', bank_name: 'Equity Bank' },
        },
        {
            id: 'pm_bank',
            code: 'bank',
            name: 'Bank Transfer',
            type: 'Bank',
            enabled: true,
            is_default: false,
            fee_percent: 0,
            instructions: 'Direct bank transfer or Pesalink deposit',
            details: { bank_name: 'KCB Bank', account_number: '' },
        },
        {
            id: 'pm_credit',
            code: 'credit',
            name: 'Customer Credit',
            type: 'Credit',
            enabled: true,
            is_default: false,
            fee_percent: 0,
            instructions: 'Billed to customer credit account',
            details: {},
        },
    ];

    const paymentMethods = computed<PaymentMethodConfig[]>(() => {
        const configured = currentTenant.value.settings?.payment_methods;
        if (Array.isArray(configured) && configured.length > 0) {
            return configured.map(pm => ({
                id: pm.id || `pm_${pm.code || Math.random()}`,
                code: pm.code || pm.type?.toLowerCase() || 'other',
                name: pm.name || pm.type,
                type: (pm.type === 'Cash' || pm.type === 'Mobile Money' || pm.type === 'Card' || pm.type === 'Bank' || pm.type === 'Credit') ? pm.type : 'Other',
                enabled: typeof pm.enabled !== 'undefined' ? Boolean(pm.enabled) : true,
                is_default: Boolean(pm.is_default),
                fee_percent: Number(pm.fee_percent ?? pm.feePercent ?? 0),
                instructions: pm.instructions || '',
                accountRef: pm.accountRef || '',
                details: pm.details || {},
            }));
        }
        return defaultPaymentMethods;
    });

    const activePaymentMethods = computed<PaymentMethodConfig[]>(() => {
        return paymentMethods.value.filter(pm => pm.enabled);
    });

    const isSubdomain = computed(() => !!page?.props?.isSubdomain || !!page?.props?.tenant);

    const getTenantSubdomainUrl = (tenantSlug: string, path: string = ''): string => {
        const protocol = window.location.protocol || 'http:';
        const port = window.location.port ? `:${window.location.port}` : '';
        const hostname = window.location.hostname;

        const cleanPath = path ? (path.startsWith('/') ? path : `/${path}`) : '/login';

        // Local development without wildcards
        if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.endsWith('.localhost')) {
            const separator = cleanPath.includes('?') ? '&' : '?';
            return `${protocol}//${hostname}${port}${cleanPath}${separator}tenant=${tenantSlug}`;
        }

        let baseDomain = hostname;
        if (hostname.includes('.')) {
            const parts = hostname.split('.');
            baseDomain = parts.length > 2 ? parts.slice(1).join('.') : hostname;
        }

        return `${protocol}//${tenantSlug}.${baseDomain}${port}${cleanPath}`;
    };

    const redirectToTenant = (tenantSlug: string, path: string = '/login') => {
        window.location.href = getTenantSubdomainUrl(tenantSlug, path);
    };

    const setTenant = (tenantId: string) => {
        state.selectedTenantId = tenantId;
    };

    const getTenantById = (_tenantId: string): Tenant | undefined => {
        // Tenant data comes from Inertia shared props — no local list maintained
        return undefined;
    };

    const setTenantBusinessType = (_businessType: BusinessType) => {
        // No-op: business type is managed server-side via Inertia props
    };

    const hasModule = (moduleKey: string) => {
        return currentTenant.value.enabledModules.includes(moduleKey);
    };

    return {
        currentTenant,
        allTenants: computed(() => [] as Tenant[]),
        isSubdomain,
        isDemo,
        getTenantSubdomainUrl,
        redirectToTenant,
        setTenant,
        getTenantById,
        setTenantBusinessType,
        hasModule,
        paymentMethods,
        activePaymentMethods,
    };
}
