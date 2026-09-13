import { usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import type { BusinessType } from '@/composables/useSuperAdmin';

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
}

const mockTenants: Tenant[] = [
    {
        id: '1',
        name: 'Nairobi Fresh Mart Supermarket',
        slug: 'nairobi-fresh-mart',
        businessType: 'Supermarket',
        plan: 'Enterprise',
        currency: 'KES',
        kraPin: 'P051234567Z',
        phone: '+254 712 345 678',
        email: 'info@nairobifreshmart.co.ke',
        address: 'Woodvale Grove, Westlands, Nairobi',
        enabledModules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'branches', 'settings'],
    },
    {
        id: '2',
        name: 'Metro Wholesale & Retail Distributors',
        slug: 'metro-wholesale-retail',
        businessType: 'Wholesale',
        plan: 'Pro',
        currency: 'KES',
        kraPin: 'P058822334K',
        phone: '+254 722 334 455',
        email: 'orders@metrowholesale.co.ke',
        address: 'Commercial Street, Industrial Area, Nairobi',
        enabledModules: ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'branches', 'settings'],
    },
];

const state = reactive<{
    selectedTenantId: string;
    tenants: Tenant[];
}>({
    selectedTenantId: '1',
    tenants: mockTenants,
});

export function useTenant() {
    const page = usePage();
    const DEMO_SLUGS = ['nairobi-fresh-mart', 'metro-wholesale-retail', 'apex-care-pharmacy'];
    const isDemo = computed(() => {
        const slug = currentTenant.value?.slug || '';
        return DEMO_SLUGS.includes(slug) || !!(page?.props as any)?.demo?.is_demo;
    });

    const currentTenant = computed<Tenant>(() => {
        // Priority 1: Top-level shared tenant (resolved via subdomain or auth)
        const serverTenant = (page?.props?.tenant || page?.props?.auth?.tenant) as any;
        if (serverTenant && serverTenant.name) {
            const isThisDemo = DEMO_SLUGS.includes(serverTenant.slug) || !!(page?.props as any)?.demo?.is_demo;
            const rawName = serverTenant.name;
            const displayName = isThisDemo && !rawName.startsWith('[DEMO]') ? `[DEMO] ${rawName}` : rawName;
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
            };
        }
        const fallback = state.tenants.find((t) => t.id === state.selectedTenantId || t.slug === state.selectedTenantId) || state.tenants[0];
        const isFallbackDemo = DEMO_SLUGS.includes(fallback.slug);
        const displayName = isFallbackDemo && !fallback.name.startsWith('[DEMO]') ? `[DEMO] ${fallback.name}` : fallback.name;
        return {
            ...fallback,
            name: displayName,
        };
    });

    const isSubdomain = computed(() => !!page?.props?.isSubdomain || !!page?.props?.tenant);

    const getTenantSubdomainUrl = (tenantSlug: string, path: string = ''): string => {
        const protocol = window.location.protocol || 'http:';
        const port = window.location.port ? `:${window.location.port}` : '';
        const hostname = window.location.hostname;

        let baseDomain = 'localhost';
        if (hostname.endsWith('.localhost') || hostname === 'localhost') {
            baseDomain = 'localhost';
        } else if (hostname.includes('.')) {
            const parts = hostname.split('.');
            // If hostname is something like "store.mdepos.co.ke" or "store.mdepos.test"
            baseDomain = parts.length > 2 ? parts.slice(1).join('.') : hostname;
        } else {
            baseDomain = hostname;
        }

        const cleanPath = path.startsWith('/') ? path : `/${path}`;
        return `${protocol}//${tenantSlug}.${baseDomain}${port}${cleanPath}`;
    };

    const redirectToTenant = (tenantSlug: string, path: string = '/login') => {
        window.location.href = getTenantSubdomainUrl(tenantSlug, path);
    };

    const setTenant = (tenantId: string) => {
        const found = state.tenants.find((t) => t.id === tenantId || t.slug === tenantId);
        if (found) {
            state.selectedTenantId = found.id;
        }
    };

    const getTenantById = (tenantId: string): Tenant | undefined => {
        return state.tenants.find((t) => t.id === tenantId || t.slug === tenantId);
    };

    const setTenantBusinessType = (businessType: BusinessType) => {
        const t = state.tenants.find((t) => t.id === state.selectedTenantId);
        if (t) {
            t.businessType = businessType;
        }
    };

    const hasModule = (moduleKey: string) => {
        return currentTenant.value.enabledModules.includes(moduleKey);
    };

    return {
        currentTenant,
        allTenants: computed(() => state.tenants),
        isSubdomain,
        isDemo,
        getTenantSubdomainUrl,
        redirectToTenant,
        setTenant,
        getTenantById,
        setTenantBusinessType,
        hasModule,
    };
}
