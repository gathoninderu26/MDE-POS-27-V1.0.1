import { usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

export type UserRole =
    | 'Owner'
    | 'Administrator'
    | 'Manager'
    | 'Cashier'
    | 'Inventory Manager'
    | 'Accountant'
    | 'Supervisor';

export interface PermissionMatrix {
    [module: string]: {
        view: boolean;
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}

const rolePermissions: Record<UserRole, PermissionMatrix> = {
    Owner: {
        pos: { view: true, create: true, edit: true, delete: true },
        sales: { view: true, create: true, edit: true, delete: true },
        products: { view: true, create: true, edit: true, delete: true },
        inventory: { view: true, create: true, edit: true, delete: true },
        purchases: { view: true, create: true, edit: true, delete: true },
        customers: { view: true, create: true, edit: true, delete: true },
        suppliers: { view: true, create: true, edit: true, delete: true },
        expenses: { view: true, create: true, edit: true, delete: true },
        reports: { view: true, create: true, edit: true, delete: true },
        users: { view: true, create: true, edit: true, delete: true },
        branches: { view: true, create: true, edit: true, delete: true },
        settings: { view: true, create: true, edit: true, delete: true },
    },
    Administrator: {
        pos: { view: true, create: true, edit: true, delete: true },
        sales: { view: true, create: true, edit: true, delete: true },
        products: { view: true, create: true, edit: true, delete: true },
        inventory: { view: true, create: true, edit: true, delete: true },
        purchases: { view: true, create: true, edit: true, delete: true },
        customers: { view: true, create: true, edit: true, delete: true },
        suppliers: { view: true, create: true, edit: true, delete: true },
        expenses: { view: true, create: true, edit: true, delete: true },
        reports: { view: true, create: true, edit: true, delete: true },
        users: { view: true, create: true, edit: true, delete: false },
        branches: { view: true, create: true, edit: true, delete: false },
        settings: { view: true, create: false, edit: true, delete: false },
    },
    Manager: {
        pos: { view: true, create: true, edit: true, delete: true },
        sales: { view: true, create: true, edit: true, delete: false },
        products: { view: true, create: true, edit: true, delete: false },
        inventory: { view: true, create: true, edit: true, delete: false },
        purchases: { view: true, create: true, edit: true, delete: false },
        customers: { view: true, create: true, edit: true, delete: false },
        suppliers: { view: true, create: true, edit: true, delete: false },
        expenses: { view: true, create: true, edit: true, delete: false },
        reports: { view: true, create: false, edit: false, delete: false },
        users: { view: true, create: false, edit: false, delete: false },
        branches: { view: true, create: false, edit: false, delete: false },
        settings: { view: false, create: false, edit: false, delete: false },
    },
    Cashier: {
        pos: { view: true, create: true, edit: false, delete: false },
        sales: { view: true, create: false, edit: false, delete: false },
        products: { view: false, create: false, edit: false, delete: false },
        inventory: { view: false, create: false, edit: false, delete: false },
        purchases: { view: false, create: false, edit: false, delete: false },
        customers: { view: true, create: true, edit: false, delete: false },
        suppliers: { view: false, create: false, edit: false, delete: false },
        expenses: { view: false, create: false, edit: false, delete: false },
        reports: { view: false, create: false, edit: false, delete: false },
        users: { view: false, create: false, edit: false, delete: false },
        branches: { view: false, create: false, edit: false, delete: false },
        settings: { view: false, create: false, edit: false, delete: false },
    },
    'Inventory Manager': {
        pos: { view: false, create: false, edit: false, delete: false },
        sales: { view: true, create: false, edit: false, delete: false },
        products: { view: true, create: true, edit: true, delete: false },
        inventory: { view: true, create: true, edit: true, delete: true },
        purchases: { view: true, create: true, edit: true, delete: false },
        customers: { view: false, create: false, edit: false, delete: false },
        suppliers: { view: true, create: true, edit: true, delete: false },
        expenses: { view: false, create: false, edit: false, delete: false },
        reports: { view: true, create: false, edit: false, delete: false },
        users: { view: false, create: false, edit: false, delete: false },
        branches: { view: true, create: false, edit: false, delete: false },
        settings: { view: false, create: false, edit: false, delete: false },
    },
    Accountant: {
        pos: { view: false, create: false, edit: false, delete: false },
        sales: { view: true, create: false, edit: false, delete: false },
        products: { view: true, create: false, edit: false, delete: false },
        inventory: { view: true, create: false, edit: false, delete: false },
        purchases: { view: true, create: false, edit: false, delete: false },
        customers: { view: true, create: true, edit: true, delete: false },
        suppliers: { view: true, create: true, edit: true, delete: false },
        expenses: { view: true, create: true, edit: true, delete: true },
        reports: { view: true, create: true, edit: true, delete: false },
        users: { view: false, create: false, edit: false, delete: false },
        branches: { view: true, create: false, edit: false, delete: false },
        settings: { view: false, create: false, edit: false, delete: false },
    },
    Supervisor: {
        pos: { view: true, create: true, edit: true, delete: false },
        sales: { view: true, create: true, edit: true, delete: false },
        products: { view: true, create: true, edit: false, delete: false },
        inventory: { view: true, create: true, edit: false, delete: false },
        purchases: { view: false, create: false, edit: false, delete: false },
        customers: { view: true, create: true, edit: true, delete: false },
        suppliers: { view: false, create: false, edit: false, delete: false },
        expenses: { view: true, create: true, edit: false, delete: false },
        reports: { view: true, create: false, edit: false, delete: false },
        users: { view: false, create: false, edit: false, delete: false },
        branches: { view: false, create: false, edit: false, delete: false },
        settings: { view: false, create: false, edit: false, delete: false },
    },
};

const state = reactive<{
    overrideRole: UserRole | null;
    customMatrix: Record<UserRole, PermissionMatrix>;
}>({
    overrideRole: null,
    customMatrix: rolePermissions,
});

export function usePermissions() {
    const page = usePage();

    const currentRole = computed<UserRole>(() => {
        if (state.overrideRole) {
            return state.overrideRole;
        }
        const serverUserRole = page?.props?.auth?.user?.role as UserRole | undefined;
        if (serverUserRole && rolePermissions[serverUserRole]) {
            return serverUserRole;
        }
        return 'Owner';
    });

    const setRole = (role: UserRole) => {
        state.overrideRole = role;
    };

    const can = (module: string, action: 'view' | 'create' | 'edit' | 'delete' = 'view') => {
        // If server provided permissions for this role and no client override is active, use server permissions
        const serverPerms = page?.props?.auth?.permissions as PermissionMatrix | undefined;
        if (!state.overrideRole && serverPerms && serverPerms[module]) {
            return serverPerms[module][action] ?? false;
        }
        const matrix = state.customMatrix[currentRole.value];
        if (!matrix || !matrix[module]) return false;
        return matrix[module][action] ?? false;
    };

    const getRoleMatrix = (role: UserRole) => {
        return state.customMatrix[role] ?? rolePermissions[role];
    };

    const updatePermission = (role: UserRole, module: string, action: 'view' | 'create' | 'edit' | 'delete', val: boolean) => {
        if (state.customMatrix[role] && state.customMatrix[role][module]) {
            state.customMatrix[role][module][action] = val;
        }
    };

    return {
        currentRole,
        availableRoles: ['Owner', 'Administrator', 'Manager', 'Cashier', 'Inventory Manager', 'Accountant', 'Supervisor'] as UserRole[],
        setRole,
        can,
        getRoleMatrix,
        updatePermission,
    };
}
