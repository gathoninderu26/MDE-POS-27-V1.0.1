import { computed, reactive } from 'vue';
import { useTenant } from '@/composables/useTenant';

export type BranchType = 'Main HQ' | 'Sub-Branch' | 'Kiosk Outlet' | 'Distribution Yard' | 'Satellite Store';
export type BranchStatus = 'Active' | 'Suspended' | 'Under Maintenance';

export interface BranchStaffMember {
    id: string;
    name: string;
    role: 'Branch Manager' | 'Head Cashier' | 'Pharmacist' | 'Sales Attendant' | 'Inventory Officer' | 'Security';
    phone: string;
    shift: 'Morning' | 'Evening' | 'Full Day';
    status: 'On Shift' | 'Off Duty' | 'On Leave';
}

export interface ColabLink {
    linkedTenantId: string;
    linkedTenantName: string;
    colabType: 'Cross-Store Concession' | 'Warehouse Fulfillment' | 'B2B Distribution Hub' | 'Joint Operating Facility';
    permission: 'Stock Visibility & Transfer' | 'Full POS & Inventory' | 'View Only';
    authorizedAt: string;
}

export interface Branch {
    id: string;
    tenantId: string;
    tenantName?: string;
    name: string;
    code: string;
    branchType: BranchType;
    parentBranchId?: string;
    parentBranchName?: string;
    location: string;
    phone: string;
    manager: string;
    currentStockValue: number;
    todaySales: number;
    monthlySales: number;
    transactionsToday: number;
    terminalsCount: number;
    storageUsedMB: number;
    storageLimitMB: number;
    operatingHours: string;
    wifiSSID?: string;
    status: BranchStatus;
    isMain: boolean;
    staffMembers: BranchStaffMember[];
    
    // Collaborative partner sharing
    isColabBranch?: boolean;
    colabLinks?: ColabLink[];
}

const state = reactive<{
    currentBranchId: string;
    branches: Branch[];
}>({
    currentBranchId: '',
    branches: [],
});

export function useBranch() {
    const { currentTenant } = useTenant();

    // 1. Own Tenant All Branches
    const tenantAllBranches = computed(() => {
        return state.branches.filter((b) => b.tenantId === currentTenant.value.id);
    });

    // 2. Own Tenant Active Branches (Main & Sub-Branches)
    const tenantActiveBranches = computed(() => {
        return state.branches.filter((b) => b.tenantId === currentTenant.value.id && b.status === 'Active');
    });

    // 3. Collaborative Partner Branches from other tenants authorized for this tenant
    const colabPartnerBranches = computed(() => {
        return state.branches
            .filter((b) => {
                if (b.tenantId === currentTenant.value.id || b.status !== 'Active') return false;
                return b.colabLinks?.some((l) => l.linkedTenantId === currentTenant.value.id);
            })
            .map((b) => ({
                ...b,
                isColabBranch: true,
            }));
    });

    // 4. Combined Accessible Branches (Own + Colabs)
    const allAccessibleBranches = computed(() => {
        return [...tenantAllBranches.value, ...colabPartnerBranches.value];
    });

    // Active Selected Operating Branch
    const currentBranch = computed<Branch>(() => {
        const accessible = allAccessibleBranches.value.filter((b) => b.status === 'Active');
        const found = accessible.find((b) => b.id === state.currentBranchId);
        return found || tenantActiveBranches.value[0] || state.branches[0] || {
            id: 'main',
            tenantId: currentTenant.value?.id || '',
            name: 'Main HQ',
            code: 'HQ-01',
            branchType: 'Main HQ',
            location: 'Main Store',
            phone: '',
            manager: 'Store Manager',
            currentStockValue: 0,
            todaySales: 0,
            monthlySales: 0,
            transactionsToday: 0,
            terminalsCount: 1,
            storageUsedMB: 0,
            storageLimitMB: 1024,
            operatingHours: '08:00 AM - 08:00 PM',
            status: 'Active',
            isMain: true,
            staffMembers: [],
        };
    });

    // Switch active operating branch with strict isolation check
    const setBranch = (branchId: string, isSuperAdmin: boolean = false) => {
        const found = state.branches.find((b) => b.id === branchId);
        if (!found) return false;

        if (found.status !== 'Active') {
            console.warn(`[Blocked] Cannot select suspended branch ${found.name}`);
            return false;
        }

        // Security check: Either belongs to own tenant OR is an authorized colab partner branch
        const isOwn = found.tenantId === currentTenant.value.id;
        const isColab = found.colabLinks?.some((l) => l.linkedTenantId === currentTenant.value.id);

        if (!isSuperAdmin && !isOwn && !isColab) {
            console.warn(`[Security Block] Tenant ${currentTenant.value.id} attempted unauthorized access to branch ${found.id}`);
            return false;
        }

        state.currentBranchId = branchId;
        return true;
    };

    // Toggle / Suspend Branch status
    const toggleBranchStatus = (branchId: string) => {
        const b = state.branches.find((x) => x.id === branchId);
        if (b) {
            b.status = b.status === 'Active' ? 'Suspended' : 'Active';
            return b.status;
        }
        return null;
    };

    // Add a Collaborative Partner Link to an existing branch
    const addColabLink = (branchId: string, colabLink: ColabLink) => {
        const b = state.branches.find((x) => x.id === branchId);
        if (b) {
            if (!b.colabLinks) b.colabLinks = [];
            b.colabLinks.push(colabLink);
            return true;
        }
        return false;
    };

    const branchesForCurrentTenant = (tenantId: string) => {
        return state.branches.filter((b) => b.tenantId === tenantId);
    };

    const setBranches = (branches: Branch[]) => {
        state.branches = branches;
        if (!state.currentBranchId && branches.length > 0) {
            const firstActive = branches.find((b) => b.status === 'Active') || branches[0];
            state.currentBranchId = firstActive.id;
        }
    };

    return {
        currentBranch,
        tenantAllBranches,
        tenantActiveBranches,
        colabPartnerBranches,
        allAccessibleBranches,
        allBranches: computed(() => state.branches),
        branchesForCurrentTenant,
        setBranch,
        setBranches,
        toggleBranchStatus,
        addColabLink,
    };
}
