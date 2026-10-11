import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useFeatures() {
    const page = usePage();

    const tenant = computed(() => {
        return ((page?.props as any)?.tenant || (page?.props as any)?.auth?.tenant || {}) as Record<string, any>;
    });

    const enabledModules = computed<string[]>(() => {
        return (tenant.value.enabled_modules || tenant.value.enabledModules || []) as string[];
    });

    const isDemo = computed<boolean>(() => {
        if (typeof tenant.value.is_demo !== 'undefined') {
            return Boolean(tenant.value.is_demo);
        }
        return Boolean((page?.props as any)?.demo?.is_demo);
    });

    const hasModule = (moduleKey: string): boolean => {
        if (!enabledModules.value || enabledModules.value.length === 0) {
            // Default safe fallback if modules not yet loaded
            return true;
        }
        return enabledModules.value.includes(moduleKey);
    };

    /**
     * Determines whether the business operates in multi-branch mode.
     * If the tenant is a single-counter supermarket or does NOT have branches module enabled,
     * this evaluates to false, causing all branch UI artifacts across all pages to hide cleanly.
     */
    const isMultiBranch = computed<boolean>(() => {
        if (!hasModule('branches')) {
            return false;
        }

        // Check explicit flag from backend if present
        if (typeof tenant.value.is_multi_branch !== 'undefined') {
            return Boolean(tenant.value.is_multi_branch);
        }

        const count = Number(tenant.value.branch_count ?? tenant.value.branchCount ?? 1);
        return count > 1;
    });

    const devicePolicy = computed<Record<string, any>>(() => {
        return (tenant.value.device_policy || tenant.value.devicePolicy || {}) as Record<string, any>;
    });

    const networkPolicy = computed<Record<string, any>>(() => {
        return (tenant.value.network_policy || tenant.value.networkPolicy || {}) as Record<string, any>;
    });

    const settings = computed<Record<string, any>>(() => {
        return (tenant.value.settings || {}) as Record<string, any>;
    });

    /**
     * Fine-grained capability checks for sub-sections, tabs, and action buttons.
     */
    const hasFeature = (feature: string): boolean => {
        switch (feature) {
            case 'branches':
            case 'branch_transfers':
            case 'branch_filter':
            case 'branch_selector':
                return isMultiBranch.value;

            case 'purchases':
            case 'suppliers':
            case 'lpo':
                return hasModule('purchases') || hasModule('suppliers');

            case 'prescriptions':
                return hasModule('prescriptions') || tenant.value.business_type === 'Pharmacy';

            case 'tables':
            case 'kitchen':
                return hasModule('tables') || hasModule('kitchen') || tenant.value.business_type === 'Restaurant';

            case 'duty_roster':
                return tenant.value.plan === 'Pro' || tenant.value.plan === 'Enterprise';

            case 'device_binding':
                return Boolean(devicePolicy.value.require_device_binding) || tenant.value.plan === 'Enterprise';

            case 'wifi_whitelist':
                return Boolean(networkPolicy.value.require_store_wifi);

            case 'customer_credit':
                return hasModule('customers');

            default:
                return hasModule(feature);
        }
    };

    return {
        tenant,
        enabledModules,
        isDemo,
        hasModule,
        isMultiBranch,
        hasFeature,
        devicePolicy,
        networkPolicy,
        settings,
    };
}
