import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type AccountTier = 'super_admin' | 'admin' | 'user';

export function useAccountTier() {
    const page = usePage();

    const isSuperAdmin = computed(() => {
        const user = page?.props?.auth?.user as { is_super_admin?: boolean } | undefined;
        return Boolean(user?.is_super_admin);
    });

    const isAdmin = computed(() => {
        const user = page?.props?.auth?.user as { is_super_admin?: boolean; role?: string } | undefined;
        if (user?.is_super_admin) return false;
        return user?.role === 'Owner' || user?.role === 'Administrator' || user?.role === 'Manager';
    });

    const isUser = computed(() => {
        return !isSuperAdmin.value && !isAdmin.value;
    });

    const accountTier = computed<AccountTier>(() => {
        if (isSuperAdmin.value) return 'super_admin';
        if (isAdmin.value) return 'admin';
        return 'user';
    });

    const tierLabel = computed(() => {
        if (isSuperAdmin.value) return 'Super Admin';
        if (isAdmin.value) return 'Tenant Admin';
        return 'Staff User';
    });

    const tierColor = computed(() => {
        if (isSuperAdmin.value) return 'text-violet-600 dark:text-violet-400';
        if (isAdmin.value) return 'text-blue-600 dark:text-blue-400';
        return 'text-emerald-600 dark:text-emerald-400';
    });

    // Kept for backward compatibility if any legacy component calls switchTier
    const switchTier = (_tier: AccountTier) => {
        // No-op: Tier is dictated by server authentication
    };

    const superAdminEmail = computed(() => {
        const user = page?.props?.auth?.user as { email?: string; is_super_admin?: boolean } | undefined;
        return user?.is_super_admin ? user.email || 'superadmin@mdepos.co.ke' : 'superadmin@mdepos.co.ke';
    });

    const superAdminName = computed(() => {
        const user = page?.props?.auth?.user as { name?: string; is_super_admin?: boolean } | undefined;
        return user?.is_super_admin ? user.name || 'Platform Super Admin' : 'Platform Super Admin';
    });

    return {
        accountTier,
        isSuperAdmin,
        isAdmin,
        isUser,
        tierLabel,
        tierColor,
        switchTier,
        superAdminEmail,
        superAdminName,
    };
}
