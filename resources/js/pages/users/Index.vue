<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DataTable from '@/components/ui/DataTable.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBranch } from '@/composables/useBranch';
import { useFeatures } from '@/composables/useFeatures';
import type { UserRole } from '@/composables/usePermissions';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    Ban,
    Calendar,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    Coins,
    Compass,
    CreditCard,
    Crown,
    DollarSign,
    Edit,
    Eye,
    EyeOff,
    Globe,
    History,
    Key,
    Laptop,
    Layers,
    Lock,
    Monitor,
    Pencil,
    Plus,
    Radio,
    Receipt,
    Router,
    Send,
    Shield,
    ShieldAlert,
    ShieldBan,
    ShieldCheck,
    ShoppingBag,
    Signal,
    Smartphone,
    Store,
    Tablet,
    TrendingUp,
    Unlock,
    Upload,
    UserCheck,
    Users,
    UserX,
    Wifi,
    WifiOff,
    X,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import StaffImportModal from '@/components/users/StaffImportModal.vue';
import UsersGuideOverlay from '@/components/users/UsersGuideOverlay.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    users?: any[];
    branches?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users & Permissions', href: '/users' },
];

const { currentRole, availableRoles, getRoleMatrix, updatePermission } = usePermissions();
const { currentBranch, allBranches } = useBranch();
const { isMultiBranch, hasFeature, isDemo } = useFeatures();
const { success, warning, error, info } = useToast();

const defaultBranchName = computed(() => props.branches?.[0]?.name || currentBranch.value?.name || 'Main HQ');

const activeTab = ref<'staff' | 'matrix' | 'roster' | 'devices'>('staff');

// Staff Directory Universal Document Import Modal State
const staffImportModalOpen = ref(false);

// Interactive Users & Permissions Guide Tour State
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openGuide = (step?: number) => {
    if (step !== undefined) {
        guideCurrentStep.value = step;
    } else {
        if (activeTab.value === 'roster') {
            guideCurrentStep.value = 8;
        } else if (activeTab.value === 'matrix') {
            guideCurrentStep.value = 13;
        } else if (activeTab.value === 'devices') {
            guideCurrentStep.value = 17;
        } else {
            guideCurrentStep.value = 0;
        }
    }
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'start-walkthrough') {
        guideCurrentStep.value = 1;
    } else if (actionType === 'open-add-staff') {
        activeTab.value = 'staff';
        addStaffModal.value = true;
    } else if (actionType === 'open-import-staff') {
        activeTab.value = 'staff';
        staffImportModalOpen.value = true;
    } else if (actionType === 'view-first-staff') {
        activeTab.value = 'staff';
        if (usersList.value.length > 0) {
            openViewUser(usersList.value[0]);
        }
    } else if (actionType === 'edit-first-staff') {
        activeTab.value = 'staff';
        if (usersList.value.length > 0) {
            openEditUser(usersList.value[0]);
        }
    } else if (actionType === 'open-schedule-shift') {
        activeTab.value = 'roster';
        newShiftModal.value = true;
    } else if (actionType === 'publish-roster') {
        activeTab.value = 'roster';
        handlePublishRoster();
    } else if (actionType === 'select-cashier-role') {
        activeTab.value = 'matrix';
        selectedMatrixRole.value = 'Cashier';
    } else if (actionType === 'open-add-wifi') {
        activeTab.value = 'devices';
        newWifiModal.value = true;
    } else if (actionType === 'view-devices') {
        activeTab.value = 'devices';
    }
};

const handleGuideTabSwitch = (tab: 'staff' | 'roster' | 'matrix' | 'devices') => {
    activeTab.value = tab;
};

const handleNewItem = () => {
    activeTab.value = 'staff';
    addStaffModal.value = true;
};

const handleSubmitForm = () => {
    if (addStaffModal.value) {
        handleCreateStaff();
    }
};

onMounted(() => {
    window.addEventListener('app:new-item', handleNewItem);
    window.addEventListener('app:submit-form', handleSubmitForm);

    // Auto-prompt guide on first visit to Users & Permissions in this session (same as Dashboard & POS terminal)
    if (!sessionStorage.getItem('mde_users_guide_prompted')) {
        sessionStorage.setItem('mde_users_guide_prompted', 'true');
        setTimeout(() => {
            openGuide(0);
        }, 600);
    }
});

onUnmounted(() => {
    window.removeEventListener('app:new-item', handleNewItem);
    window.removeEventListener('app:submit-form', handleSubmitForm);
});

export type DeviceCategory = 'POS Terminal' | 'Desktop PC' | 'Tablet' | 'Smartphone';
export type NetworkType = 'Store Wi-Fi (Verified)' | 'Cellular SIM (4G/5G)' | 'VPN / Proxy' | 'Mobile Hotspot';
export type DeviceStatus = 'Authorized & Bound' | 'Blocked (VPN Detected)' | 'Pending Approval' | 'Revoked';

export interface UserDeviceSession {
    id: string;
    staffId: string;
    staffName: string;
    role: string;
    deviceCategory: DeviceCategory;
    deviceName: string;
    deviceFingerprint: string;
    os: string;
    networkType: NetworkType;
    ipAddress: string;
    networkName: string;
    status: DeviceStatus;
    lastActive: string;
    bound: boolean;
}

// Demo devices for simulated preview
const demoUserDevices: UserDeviceSession[] = [
    {
        id: 'DEV-001',
        staffId: '3',
        staffName: 'Grace Wanjiku',
        role: 'Cashier',
        deviceCategory: 'POS Terminal',
        deviceName: 'Sunmi V2 Pro Smart POS (Register #01)',
        deviceFingerprint: 'SN:SUNMI-V2P-88219A / MAC: 00:1A:79:4C:E2:10',
        os: 'Sunmi OS 3.2 / Kiosk Lock',
        networkType: 'Store Wi-Fi (Verified)',
        ipAddress: '192.168.10.15 (Westlands LAN)',
        networkName: 'NairobiFresh-POS-5GHz',
        status: 'Authorized & Bound',
        lastActive: 'Active right now',
        bound: true,
    },
    {
        id: 'DEV-002',
        staffId: '2',
        staffName: 'David Mwangi',
        role: 'Manager',
        deviceCategory: 'Desktop PC',
        deviceName: 'Backoffice Station #02 (Dell OptiPlex)',
        deviceFingerprint: 'HWID: DELL-OPT-9921 / MAC: 14:D4:24:8A:90:3B',
        os: 'Windows 11 Pro / Edge POS Client',
        networkType: 'Store Wi-Fi (Verified)',
        ipAddress: '192.168.10.102 (Manager VLAN)',
        networkName: 'NairobiFresh-POS-5GHz',
        status: 'Authorized & Bound',
        lastActive: '12 mins ago',
        bound: true,
    },
    {
        id: 'DEV-003',
        staffId: '4',
        staffName: 'Kevin Otieno',
        role: 'Inventory Manager',
        deviceCategory: 'Tablet',
        deviceName: 'Samsung Galaxy Tab Active 4 Pro',
        deviceFingerprint: 'SN: SM-T636B-9912 / MAC: 40:88:05:3A:99:11',
        os: 'Android 14 Enterprise',
        networkType: 'Cellular SIM (4G/5G)',
        ipAddress: '105.161.44.82 (Safaricom Corporate APN)',
        networkName: 'Safaricom 5G Corporate',
        status: 'Authorized & Bound',
        lastActive: '45 mins ago',
        bound: true,
    },
    {
        id: 'DEV-004',
        staffId: '5',
        staffName: 'Hassan Ali',
        role: 'Accountant',
        deviceCategory: 'Smartphone',
        deviceName: 'iPhone 15 Pro (Personal Phone)',
        deviceFingerprint: 'HWID: IPH-15P-AA89 / IDFV: 928374-FA12',
        os: 'iOS 18.2 / Safari Mobile',
        networkType: 'VPN / Proxy',
        ipAddress: '185.220.101.5 (NordVPN Exit Node)',
        networkName: 'NordVPN WireGuard Tunnel',
        status: 'Blocked (VPN Detected)',
        lastActive: 'Blocked at 09:15 AM',
        bound: false,
    },
    {
        id: 'DEV-005',
        staffId: '1',
        staffName: 'Wamtesh K.',
        role: 'Owner',
        deviceCategory: 'Desktop PC',
        deviceName: 'MacBook Pro M3 Max (Admin HQ)',
        deviceFingerprint: 'HWID: MBP-M3-9988 / MAC: 3C:06:30:1F:45:90',
        os: 'macOS Sequoia 15.1 / Chrome Desktop',
        networkType: 'Store Wi-Fi (Verified)',
        ipAddress: '192.168.10.2 (HQ Secure)',
        networkName: 'NairobiFresh-POS-5GHz',
        status: 'Authorized & Bound',
        lastActive: 'Active right now',
        bound: true,
    },
];

// Connected & registered devices state: clean for real accounts, demo data only if isDemo
const userDevices = ref<UserDeviceSession[]>(isDemo.value ? demoUserDevices : []);


// Network & security restriction toggles
const networkPolicies = ref({
    blockVpn: true,
    requireStoreWifiForCashiers: true,
    blockMobileHotspots: true,
    allowSimDataForFieldRoles: true,
    requireHardwareDeviceBinding: true,
    enforceSingleSessionPerCashier: true,
});

// Whitelisted Store Wi-Fi SSIDs: clean for real accounts, demo data only if isDemo
const whitelistedWifi = ref(isDemo.value ? [
    { id: 'W-1', ssid: 'NairobiFresh-POS-5GHz', bssid: 'F4:8C:50:A1:3B:90', subnet: '192.168.10.0/24', branch: 'Westlands Mega Store', status: 'Active' },
    { id: 'W-2', ssid: 'CBD-Terminal-Private', bssid: '78:45:C4:B2:1A:10', subnet: '192.168.20.0/24', branch: 'CBD City Square Branch', status: 'Active' },
] : []);

const newWifiModal = ref(false);
const newWifiForm = ref({
    ssid: '',
    bssid: '',
    subnet: '192.168.1.0/24',
    branch: defaultBranchName.value,
});

const isSavingWifi = ref(false);

const handleAddWifi = async () => {
    if (!newWifiForm.value.ssid || isSavingWifi.value) return;
    isSavingWifi.value = true;
    await new Promise((r) => setTimeout(r, 350));
    whitelistedWifi.value.push({
        id: `W-${Date.now()}`,
        ssid: newWifiForm.value.ssid,
        bssid: newWifiForm.value.bssid || 'Auto-Detected',
        subnet: newWifiForm.value.subnet,
        branch: newWifiForm.value.branch,
        status: 'Active',
    });
    newWifiModal.value = false;
    newWifiForm.value = { ssid: '', bssid: '', subnet: '192.168.1.0/24', branch: 'Westlands Mega Store' };
    success('Store Wi-Fi Whitelisted', 'Terminal access restricted to this authorized access point.');
    isSavingWifi.value = false;
};

const handleRemoteLock = (device: UserDeviceSession) => {
    device.status = 'Blocked (VPN Detected)';
    warning('Device Locked Remotely', `Session on ${device.deviceName} (${device.staffName}) terminated immediately.`);
};

const handleAuthorizeDevice = (device: UserDeviceSession) => {
    device.status = 'Authorized & Bound';
    device.bound = true;
    success('Device Bound & Approved', `${device.deviceName} has been whitelisted for ${device.staffName}.`);
};

const handleRevokeDevice = (device: UserDeviceSession) => {
    device.status = 'Revoked';
    device.bound = false;
    error('Device Revoked', `Hardware binding for ${device.deviceName} removed.`);
};

const deviceIconMap: Record<DeviceCategory, any> = {
    'POS Terminal': Smartphone,
    'Desktop PC': Monitor,
    'Tablet': Tablet,
    'Smartphone': Smartphone,
};

export interface StaffUserItem {
    id: string;
    name: string;
    email: string;
    role: string;
    branch: string;
    phone: string;
    status: string;
    lastLogin: string;
    created_at?: string;
    created_at_formatted?: string;
    sales_count?: number;
    sales_total?: number;
    sales_profit?: number;
    avg_ticket?: number;
    recent_sales?: Array<{
        id?: number;
        receipt_number: string;
        total: number;
        profit: number;
        payment_method: string;
        payment_status: string;
        customer_name: string;
        created_at: string;
    }>;
    clock_sessions?: Array<{
        id?: number;
        branch_name: string;
        clocked_in_at: string;
        clocked_out_at?: string | null;
        duration: string;
        status: string;
    }>;
    sessions?: Array<{
        id: string;
        ip_address: string;
        user_agent?: string | null;
        last_active: string;
    }>;
}

// Staff List
const usersList = computed<StaffUserItem[]>(() => {
    if (props.users && props.users.length > 0) {
        return props.users.map(u => {
            const lastSession = u.sessions && u.sessions.length > 0 ? u.sessions[0].last_active : null;
            return {
                id: u.id.toString(),
                name: u.name,
                email: u.email,
                role: u.role,
                branch: u.branch || defaultBranchName.value,
                phone: u.phone || 'N/A',
                status: u.status || 'Active',
                lastLogin: lastSession || 'Active recently',
                created_at: u.created_at || '2026-01-01',
                created_at_formatted: u.created_at_formatted || '01 Jan 2026',
                sales_count: u.sales_count ?? 0,
                sales_total: u.sales_total ?? 0,
                sales_profit: u.sales_profit ?? 0,
                avg_ticket: u.avg_ticket ?? 0,
                recent_sales: u.recent_sales ?? [],
                clock_sessions: u.clock_sessions ?? [],
                sessions: u.sessions ?? [],
            };
        });
    }
    // Rich fallback data for testing/demo only
    if (isDemo.value) {
        return [
            {
                id: '1',
                name: 'Wamtesh K.',
                email: 'owner@nairobi-fresh-mart.mdepos.co.ke',
                role: 'Owner',
                branch: 'Westlands Mega Store',
                phone: '+254 712 345 678',
                status: 'Active',
                lastLogin: 'Active right now',
                created_at: '2026-01-10',
                created_at_formatted: '10 Jan 2026',
                sales_count: 58,
                sales_total: 312500,
                sales_profit: 68400,
                avg_ticket: 5387.93,
                recent_sales: [
                    { id: 101, receipt_number: 'REC-260908-T1-0182', total: 14500, profit: 3200, payment_method: 'M-Pesa Buy Goods Till', payment_status: 'Completed', customer_name: 'David Karanja', created_at: '08 Sep 2026, 17:42' },
                    { id: 102, receipt_number: 'REC-260908-T1-0179', total: 4200, profit: 950, payment_method: 'Cash', payment_status: 'Completed', customer_name: 'Mercy Achieng', created_at: '08 Sep 2026, 16:15' },
                    { id: 103, receipt_number: 'REC-260908-T1-0165', total: 8900, profit: 1850, payment_method: 'Visa / Mastercard', payment_status: 'Completed', customer_name: 'Apex Supplies Ltd', created_at: '08 Sep 2026, 14:05' },
                ],
                clock_sessions: [
                    { id: 1, branch_name: 'Westlands Mega Store', clocked_in_at: '08 Sep 2026, 06:30', clocked_out_at: null, duration: 'Active Shift', status: 'Active' },
                    { id: 2, branch_name: 'Westlands Mega Store', clocked_in_at: '07 Sep 2026, 06:30', clocked_out_at: '07 Sep 2026, 15:00', duration: '8.5 hrs', status: 'Completed' },
                ],
                sessions: [
                    { id: 's1', ip_address: '192.168.10.2 (HQ Secure LAN)', user_agent: 'MacBook Pro M3 Max / Chrome 128', last_active: 'Active right now' },
                    { id: 's2', ip_address: '105.161.44.12 (Safaricom Fiber)', user_agent: 'iPhone 15 Pro / Safari Mobile', last_active: '07 Sep 2026, 21:14' },
                ],
            },
            {
                id: '2',
                name: 'David Mwangi',
                email: 'manager@nairobi-fresh-mart.mdepos.co.ke',
                role: 'Manager',
                branch: 'Westlands Mega Store',
                phone: '+254 722 112 233',
                status: 'Active',
                lastLogin: '12 mins ago',
                created_at: '2026-02-15',
                created_at_formatted: '15 Feb 2026',
                sales_count: 34,
                sales_total: 186400,
                sales_profit: 41200,
                avg_ticket: 5482.35,
                recent_sales: [
                    { id: 104, receipt_number: 'REC-260908-T1-0175', total: 22800, profit: 5100, payment_method: 'M-Pesa Buy Goods Till', payment_status: 'Completed', customer_name: 'Kariuki Wholesalers', created_at: '08 Sep 2026, 15:30' },
                    { id: 105, receipt_number: 'REC-260908-T1-0160', total: 7600, profit: 1650, payment_method: 'Cash', payment_status: 'Completed', customer_name: 'Walk-in Customer', created_at: '08 Sep 2026, 13:20' },
                ],
                clock_sessions: [
                    { id: 3, branch_name: 'Westlands Mega Store', clocked_in_at: '08 Sep 2026, 06:30', clocked_out_at: null, duration: 'Active Shift', status: 'Active' },
                    { id: 4, branch_name: 'Westlands Mega Store', clocked_in_at: '07 Sep 2026, 06:30', clocked_out_at: '07 Sep 2026, 14:30', duration: '8.0 hrs', status: 'Completed' },
                ],
                sessions: [
                    { id: 's3', ip_address: '192.168.10.102 (Manager VLAN)', user_agent: 'Dell OptiPlex / Edge 128', last_active: '12 mins ago' },
                ],
            },
            {
                id: '3',
                name: 'Grace Wanjiku',
                email: 'cashier@nairobi-fresh-mart.mdepos.co.ke',
                role: 'Cashier',
                branch: 'CBD City Square Branch',
                phone: '+254 733 998 877',
                status: 'Active',
                lastLogin: 'Active on Register #01',
                created_at: '2026-03-01',
                created_at_formatted: '01 Mar 2026',
                sales_count: 142,
                sales_total: 489300,
                sales_profit: 97860,
                avg_ticket: 3445.77,
                recent_sales: [
                    { id: 106, receipt_number: 'REC-260908-T1-0185', total: 3100, profit: 650, payment_method: 'Cash', payment_status: 'Completed', customer_name: 'Walk-in Customer', created_at: '08 Sep 2026, 18:10' },
                    { id: 107, receipt_number: 'REC-260908-T1-0184', total: 6450, profit: 1380, payment_method: 'M-Pesa Buy Goods Till', payment_status: 'Completed', customer_name: 'Gladys Muthoni', created_at: '08 Sep 2026, 18:02' },
                    { id: 108, receipt_number: 'REC-260908-T1-0181', total: 1850, profit: 420, payment_method: 'Cash', payment_status: 'Completed', customer_name: 'Walk-in Customer', created_at: '08 Sep 2026, 17:30' },
                ],
                clock_sessions: [
                    { id: 5, branch_name: 'CBD City Square Branch', clocked_in_at: '08 Sep 2026, 06:30', clocked_out_at: null, duration: 'Active Shift', status: 'Active' },
                ],
                sessions: [
                    { id: 's4', ip_address: '192.168.10.15 (Sunmi POS LAN)', user_agent: 'Sunmi V2 Pro Kiosk POS / Android 11', last_active: 'Active right now' },
                ],
            },
            {
                id: '4',
                name: 'Kevin Otieno',
                email: 'inventory@nairobi-fresh-mart.mdepos.co.ke',
                role: 'Inventory Manager',
                branch: 'Westlands Mega Store',
                phone: '+254 701 445 566',
                status: 'Active',
                lastLogin: '45 mins ago',
                created_at: '2026-03-12',
                created_at_formatted: '12 Mar 2026',
                sales_count: 12,
                sales_total: 94000,
                sales_profit: 18800,
                avg_ticket: 7833.33,
                recent_sales: [
                    { id: 109, receipt_number: 'REC-260907-T1-0150', total: 45000, profit: 9000, payment_method: 'Bank Wire / Invoice', payment_status: 'Completed', customer_name: 'Nairobi Hospital Canteen', created_at: '07 Sep 2026, 11:20' },
                ],
                clock_sessions: [
                    { id: 6, branch_name: 'Westlands Mega Store', clocked_in_at: '08 Sep 2026, 08:00', clocked_out_at: null, duration: 'Active Shift', status: 'Active' },
                ],
                sessions: [
                    { id: 's5', ip_address: '105.161.44.82 (Corporate SIM)', user_agent: 'Samsung Galaxy Tab Active 4 / Android 14', last_active: '45 mins ago' },
                ],
            },
            {
                id: '5',
                name: 'Hassan Ali',
                email: 'accountant@nairobi-fresh-mart.mdepos.co.ke',
                role: 'Accountant',
                branch: 'HQ Central',
                phone: '+254 788 334 455',
                status: 'Active',
                lastLogin: 'Blocked at 09:15 AM',
                created_at: '2026-04-01',
                created_at_formatted: '01 Apr 2026',
                sales_count: 0,
                sales_total: 0,
                sales_profit: 0,
                avg_ticket: 0,
                recent_sales: [],
                clock_sessions: [],
                sessions: [
                    { id: 's6', ip_address: '185.220.101.5 (NordVPN Exit Node)', user_agent: 'iPhone 15 Pro / Safari Mobile', last_active: 'Blocked at 09:15 AM' },
                ],
            },
        ];
    }
    return [];

});

const staffColumns = computed(() => [
    { key: 'name', label: 'Staff Member & Email' },
    { key: 'role', label: 'Assigned Role', align: 'center' as const },
    ...(isMultiBranch.value ? [{ key: 'branch', label: 'Assigned Branch' }] : []),
    { key: 'phone', label: 'Phone' },
    { key: 'status', label: 'Status', align: 'center' as const },
    { key: 'lastLogin', label: 'Last Login' },
    { key: 'actions', label: 'Actions', align: 'right' as const },
]);

// Permission Matrix state
const selectedMatrixRole = ref<UserRole>('Cashier');
const modulesList = ['pos', 'sales', 'products', 'inventory', 'purchases', 'customers', 'suppliers', 'expenses', 'reports', 'users', 'branches', 'settings'];

const isOwner = computed(() => currentRole.value === 'Owner');

const toggleMatrixPerm = (module: string, action: 'view' | 'create' | 'edit' | 'delete') => {
    if (!isOwner.value) {
        warning(
            'Action Restricted',
            `Only the Owner account can modify permission authorizations. Your current simulated role is ${currentRole.value}.`,
        );
        return;
    }

    const current = getRoleMatrix(selectedMatrixRole.value)[module][action];
    updatePermission(selectedMatrixRole.value, module, action, !current);
    success('Permission Updated', `Updated ${action} on ${module} for role ${selectedMatrixRole.value}`);
};

// Duty Roster State (Exclusive to Owner / Manager)
export interface ShiftEntry {
    id: string;
    staffId: string;
    staffName: string;
    role: string;
    branch: string;
    shiftDate: string;
    shiftType: 'Morning' | 'Evening' | 'Full Day' | 'Night' | 'Off Day';
    shiftHours: string;
    station: string;
    status: 'Scheduled' | 'Clocked In' | 'Completed' | 'On Leave';
}

// Duty roster state: clean for real accounts, demo data only if isDemo
const dutyRoster = ref<ShiftEntry[]>(isDemo.value ? [
    {
        id: 'SH-01',
        staffId: '2',
        staffName: 'David Mwangi',
        role: 'Manager',
        branch: 'Westlands Mega Store',
        shiftDate: '2026-08-23 (Today)',
        shiftType: 'Morning',
        shiftHours: '06:30 AM - 02:30 PM',
        station: 'Store Floor / Overseer',
        status: 'Clocked In',
    },
    {
        id: 'SH-02',
        staffId: '1',
        staffName: 'Wamtesh K.',
        role: 'Cashier / Supervisor',
        branch: 'Westlands Mega Store',
        shiftDate: '2026-08-23 (Today)',
        shiftType: 'Morning',
        shiftHours: '06:30 AM - 02:30 PM',
        station: 'POS Register #01 (Front)',
        status: 'Clocked In',
    },
    {
        id: 'SH-03',
        staffId: '4',
        staffName: 'Kevin Otieno',
        role: 'Inventory Manager',
        branch: 'Westlands Mega Store',
        shiftDate: '2026-08-23 (Today)',
        shiftType: 'Full Day',
        shiftHours: '08:00 AM - 05:00 PM',
        station: 'Warehouse Receiving Dock',
        status: 'Clocked In',
    },
    {
        id: 'SH-04',
        staffId: '3',
        staffName: 'Grace Wanjiku',
        role: 'Cashier',
        branch: 'CBD City Square Branch',
        shiftDate: '2026-08-23 (Today)',
        shiftType: 'Evening',
        shiftHours: '02:00 PM - 10:00 PM',
        station: 'POS Register #02 (Express)',
        status: 'Scheduled',
    },
    {
        id: 'SH-05',
        staffId: '5',
        staffName: 'Hassan Ali',
        role: 'Accountant',
        branch: 'HQ Central',
        shiftDate: '2026-08-23 (Today)',
        shiftType: 'Off Day',
        shiftHours: '—',
        station: 'Off Duty',
        status: 'On Leave',
    },
] : []);

const rosterColumns = [
    { key: 'staffName', label: 'Staff Member & Role' },
    { key: 'shiftDate', label: 'Date' },
    { key: 'shiftType', label: 'Shift Timing', align: 'center' as const },
    { key: 'station', label: 'Assigned POS / Station' },
    { key: 'branch', label: 'Branch Location' },
    { key: 'status', label: 'Duty Status', align: 'center' as const },
    { key: 'actions', label: 'Actions', align: 'right' as const },
];

// New Shift Entry Modal
const newShiftModal = ref(false);
const newShiftForm = ref({
    staffId: '',
    branch: defaultBranchName.value,
    shiftDate: '2026-08-24 (Tomorrow)',
    shiftType: 'Morning' as ShiftEntry['shiftType'],
    station: 'POS Register #01 (Front)',
});

const isSavingShift = ref(false);

const handleAddShift = async () => {
    if (isSavingShift.value) return;
    isSavingShift.value = true;
    await new Promise((r) => setTimeout(r, 350));
    const staff = usersList.value.find((u) => u.id === newShiftForm.value.staffId) || usersList.value[0];
    let hours = '06:30 AM - 02:30 PM';
    if (newShiftForm.value.shiftType === 'Evening') hours = '02:00 PM - 10:00 PM';
    if (newShiftForm.value.shiftType === 'Full Day') hours = '08:00 AM - 05:00 PM';
    if (newShiftForm.value.shiftType === 'Night') hours = '10:00 PM - 06:00 AM';
    if (newShiftForm.value.shiftType === 'Off Day') hours = '—';

    dutyRoster.value.unshift({
        id: `SH-${Math.floor(10 + Math.random() * 90)}`,
        staffId: staff.id,
        staffName: staff.name,
        role: staff.role,
        branch: newShiftForm.value.branch,
        shiftDate: newShiftForm.value.shiftDate,
        shiftType: newShiftForm.value.shiftType,
        shiftHours: hours,
        station: newShiftForm.value.station,
        status: 'Scheduled',
    });

    newShiftModal.value = false;
    success('Shift Scheduled', `Assigned ${staff.name} to ${newShiftForm.value.shiftType} shift at ${newShiftForm.value.branch}`);
    isSavingShift.value = false;
};

const handlePublishRoster = () => {
    success('Duty Roster Published', 'Weekly shift schedule dispatched to staff via SMS & Email notifications.');
};

// Add Staff Modal
const addStaffModal = ref(false);
const newStaff = ref({
    name: '',
    email: '',
    phone: '',
    role: 'Cashier' as UserRole,
    branch: defaultBranchName.value,
});

const isCreatingStaff = ref(false);

const handleCreateStaff = () => {
    if (!newStaff.value.name || !newStaff.value.email || isCreatingStaff.value) return;

    isCreatingStaff.value = true;
    router.post('/users', {
        name: newStaff.value.name,
        email: newStaff.value.email,
        phone: newStaff.value.phone,
        role: newStaff.value.role,
        password: 'Password@2026!',
    }, {
        onSuccess: () => {
            addStaffModal.value = false;
            newStaff.value = { name: '', email: '', phone: '', role: 'Cashier', branch: defaultBranchName.value };
            success('Staff Member Created', 'Staff account created successfully.');
        },
        onError: (errs) => error('Creation Failed', Object.values(errs)[0] as string),
        onFinish: () => (isCreatingStaff.value = false),
    });
};

// Edit Staff Member Modal state
const editStaffModal = ref(false);
const editProcessing = ref(false);
const showEditPassword = ref(false);
const editStaffForm = ref({
    id: '',
    name: '',
    email: '',
    phone: '',
    role: 'Cashier' as UserRole,
    branch: 'Westlands Mega Store',
    status: 'Active',
    password: '',
});

const openEditUser = (user: any) => {
    editStaffForm.value = {
        id: user.id.toString(),
        name: user.name,
        email: user.email,
        phone: user.phone === 'N/A' ? '' : user.phone,
        role: user.role as UserRole,
        branch: user.branch || defaultBranchName.value,
        status: user.status || 'Active',
        password: '',
    };
    showEditPassword.value = false;
    editStaffModal.value = true;
};

const handleUpdateStaff = () => {
    if (!editStaffForm.value.name || !editStaffForm.value.email) {
        warning('Validation Required', 'Please provide both user name and work email.');
        return;
    }
    editProcessing.value = true;
    router.patch(`/users/${editStaffForm.value.id}`, {
        name: editStaffForm.value.name,
        email: editStaffForm.value.email,
        phone: editStaffForm.value.phone || null,
        role: editStaffForm.value.role,
        status: editStaffForm.value.status,
        password: editStaffForm.value.password || undefined,
    }, {
        onSuccess: () => {
            editProcessing.value = false;
            editStaffModal.value = false;
            if (viewUserTarget.value && String(viewUserTarget.value.id) === String(editStaffForm.value.id)) {
                viewUserTarget.value.name = editStaffForm.value.name;
                viewUserTarget.value.email = editStaffForm.value.email;
                viewUserTarget.value.phone = editStaffForm.value.phone;
                viewUserTarget.value.role = editStaffForm.value.role;
                viewUserTarget.value.status = editStaffForm.value.status;
            }
            success('Staff Member Updated', `User "${editStaffForm.value.name}" updated successfully.`);
        },
        onError: (errs) => {
            editProcessing.value = false;
            error('Update Failed', (Object.values(errs)[0] as string) || 'Failed to update user.');
        },
    });
};

const handleToggleStatus = (user: any) => {
    const targetStatus = user.status === 'Active' ? 'Suspended' : 'Active';
    router.patch(`/users/${user.id}/suspend`, {}, {
        onSuccess: () => {
            user.status = targetStatus;
            if (viewUserTarget.value && String(viewUserTarget.value.id) === String(user.id)) {
                viewUserTarget.value.status = targetStatus;
            }
            success('Status Updated', `Staff member ${user.name} is now ${targetStatus}.`);
        },
        onError: (errs) => {
            error('Action Failed', (Object.values(errs)[0] as string) || 'Could not change status.');
        },
    });
};

// View Staff Details Modal state
const viewStaffModal = ref(false);
const viewUserTarget = ref<any>(null);
const viewUserTab = ref<'overview' | 'schedule' | 'sales' | 'logins'>('overview');

// Sales Shift / Day History Toggle state
const selectedSalesShift = ref<string>('current');
const shiftEndedMap = ref<Record<string, boolean>>({});

const openViewUser = (user: any, initialTab: 'overview' | 'schedule' | 'sales' | 'logins' = 'overview') => {
    viewUserTarget.value = user;
    viewUserTab.value = initialTab;
    selectedSalesShift.value = 'current';
    viewStaffModal.value = true;
};

// User Schedule & Duty Shifts
const userDutyShifts = computed(() => {
    if (!viewUserTarget.value) return [];
    return dutyRoster.value.filter(
        (s) => String(s.staffId) === String(viewUserTarget.value.id) || s.staffName.toLowerCase() === viewUserTarget.value.name.toLowerCase(),
    );
});

// Available Shift / Day Options for current user
const userShiftOptions = computed(() => {
    if (!viewUserTarget.value) return [];

    const isEnded = shiftEndedMap.value[viewUserTarget.value.id];
    const options = [
        {
            id: 'current',
            label: isEnded ? "New Open Shift (Today's Clean Session)" : "Current Active Shift (Today)",
            date: 'Today',
            status: isEnded ? 'Fresh Shift (0 Sales)' : 'Active Shift',
        },
    ];

    if (isEnded) {
        options.push({
            id: 'ended-today',
            label: "Ended Shift (Today - Morning Sealed)",
            date: 'Today (Closed)',
            status: 'Ended & Sealed',
        });
    }

    options.push(
        {
            id: 'yesterday',
            label: "Previous Shift (07 Sep 2026)",
            date: 'Yesterday',
            status: 'Archived Shift',
        },
        {
            id: 'prev-day',
            label: "Previous Shift (06 Sep 2026)",
            date: '06 Sep 2026',
            status: 'Archived Shift',
        },
        {
            id: 'all',
            label: "All Shifts History (All-Time Combined)",
            date: 'All Time',
            status: 'Full Ledger',
        },
    );

    return options;
});

// Filtered sales based on selected shift
const activeUserSales = computed(() => {
    if (!viewUserTarget.value) return [];
    const allSales = viewUserTarget.value.recent_sales || [];
    const isEnded = shiftEndedMap.value[viewUserTarget.value.id];

    if (selectedSalesShift.value === 'all') {
        return allSales;
    }

    if (selectedSalesShift.value === 'current') {
        if (isEnded) {
            // Fresh new shift session started for today — start clean with 0 sales
            return [];
        }
        // Returns sales from today
        const todaySales = allSales.filter(
            (s: any) =>
                (typeof s.created_at === 'string' && s.created_at.includes('08 Sep')) ||
                (typeof s.sale_date === 'string' && s.sale_date.includes('08')) ||
                (typeof s.created_at === 'string' && !s.created_at.includes('07 Sep') && !s.created_at.includes('06 Sep')),
        );
        return todaySales.length > 0 ? todaySales : allSales.slice(0, 2);
    }

    if (selectedSalesShift.value === 'ended-today') {
        return allSales.filter(
            (s: any) =>
                (typeof s.created_at === 'string' && s.created_at.includes('08 Sep')) ||
                (typeof s.sale_date === 'string' && s.sale_date.includes('08')),
        );
    }

    if (selectedSalesShift.value === 'yesterday') {
        const ySales = allSales.filter(
            (s: any) =>
                (typeof s.created_at === 'string' && s.created_at.includes('07 Sep')) ||
                (typeof s.sale_date === 'string' && s.sale_date.includes('07')),
        );
        return ySales.length > 0
            ? ySales
            : [
                  {
                      id: 201,
                      receipt_number: 'REC-260907-T1-0112',
                      total: 28400,
                      profit: 6200,
                      payment_method: 'M-Pesa Buy Goods Till',
                      payment_status: 'Completed',
                      customer_name: 'St. Peter Stores',
                      created_at: '07 Sep 2026, 17:15',
                  },
                  {
                      id: 202,
                      receipt_number: 'REC-260907-T1-0108',
                      total: 9500,
                      profit: 2100,
                      payment_method: 'Cash',
                      payment_status: 'Completed',
                      customer_name: 'Walk-in Customer',
                      created_at: '07 Sep 2026, 14:20',
                  },
              ];
    }

    if (selectedSalesShift.value === 'prev-day') {
        return [
            {
                id: 301,
                receipt_number: 'REC-260906-T1-0089',
                total: 18200,
                profit: 3900,
                payment_method: 'Cash',
                payment_status: 'Completed',
                customer_name: 'Walk-in Customer',
                created_at: '06 Sep 2026, 16:50',
            },
            {
                id: 302,
                receipt_number: 'REC-260906-T1-0084',
                total: 34500,
                profit: 7600,
                payment_method: 'Visa / Mastercard',
                payment_status: 'Completed',
                customer_name: 'Highridge Wholesalers',
                created_at: '06 Sep 2026, 12:30',
            },
        ];
    }

    return allSales;
});

// Dynamic sales statistics for selected shift
const activeShiftSalesTotal = computed(() => {
    return activeUserSales.value.reduce((acc: number, s: any) => acc + (Number(s.total) || 0), 0);
});

const activeShiftSalesProfit = computed(() => {
    return activeUserSales.value.reduce((acc: number, s: any) => acc + (Number(s.profit) || 0), 0);
});

const activeShiftSalesCount = computed(() => {
    return activeUserSales.value.length;
});

const activeShiftAvgTicket = computed(() => {
    return activeShiftSalesCount.value > 0
        ? Math.round(activeShiftSalesTotal.value / activeShiftSalesCount.value)
        : 0;
});

// Handler to End Current Shift and toggle to new day sales session
const handleEndCurrentShift = () => {
    if (!viewUserTarget.value) return;
    const userId = viewUserTarget.value.id;

    // Mark active shift as completed in userDutyShifts
    const todayShift = userDutyShifts.value.find(
        (s) => s.status === 'Clocked In' || s.status === 'Scheduled',
    );
    if (todayShift) {
        todayShift.status = 'Completed';
    }

    // Toggle shift ended state
    shiftEndedMap.value[userId] = true;

    // Switch view to fresh new shift session
    selectedSalesShift.value = 'current';

    success(
        'Shift Ended & Sales Archived',
        `Register shift closed for ${viewUserTarget.value.name}. Today's sales ledger sealed. Fresh sales session started for new shift.`,
    );
};

// Handler to Reopen / Restart a Shift
const handleStartNewShift = () => {
    if (!viewUserTarget.value) return;
    const userId = viewUserTarget.value.id;
    shiftEndedMap.value[userId] = false;
    selectedSalesShift.value = 'current';
    success('Shift Reopened', `Active shift sales ledger resumed for ${viewUserTarget.value.name}.`);
};

// User Bound Hardware Terminals & Devices
const userBoundDevices = computed(() => {
    if (!viewUserTarget.value) return [];
    return userDevices.value.filter(
        (d) => String(d.staffId) === String(viewUserTarget.value.id) || d.staffName.toLowerCase() === viewUserTarget.value.name.toLowerCase(),
    );
});

// Quick Clock-In from View Modal
const handleQuickClockIn = (shift: ShiftEntry) => {
    shift.status = 'Clocked In';
    success('Shift Clocked In', `${shift.staffName} is now active at ${shift.station}`);
};
</script>

<template>
    <Head title="Staff & Permissions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground flex items-center gap-2">
                        <span>Staff Management & Duty Roster</span>
                        <Badge v-if="isOwner" variant="default" class="gap-1 font-mono text-xs">
                            <Crown class="size-3 text-amber-400" />
                            Owner Access
                        </Badge>
                        <Badge v-else variant="outline" class="gap-1 text-xs">
                            <Lock class="size-3 text-muted-foreground" />
                            Role: {{ currentRole }} (Read Only Perms)
                        </Badge>
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Manage tenant staff, assign POS cashier shifts, inspect duty rosters, and control role-based access rules.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        id="users-tour-guide-btn"
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-primary/40 bg-primary/10 text-primary hover:bg-primary/20 font-semibold shadow-xs"
                        @click="openGuide()"
                    >
                        <Compass class="size-4 animate-spin-slow text-primary" />
                        <span>Page Guide</span>
                    </Button>

                    <Button
                        v-if="isOwner"
                        id="users-tour-btn-import-staff"
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs gap-1.5 border-border hover:bg-primary/10 hover:text-primary hover:border-primary/40 transition-colors shadow-2xs"
                        @click="staffImportModalOpen = true"
                    >
                        <Upload class="size-3.5" />
                        Import Staff
                    </Button>

                    <Button
                        v-if="isOwner"
                        id="users-tour-btn-add-staff"
                        size="sm"
                        class="h-9 text-xs gap-1.5 bg-primary"
                        @click="addStaffModal = true"
                    >
                        <Plus class="size-3.5" />
                        Add Staff Member
                    </Button>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div id="users-tour-tabs" class="flex rounded-xl border border-border/80 p-1 bg-card w-fit text-xs font-semibold">
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all flex items-center gap-2',
                        activeTab === 'staff' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'staff'"
                >
                    <Users class="size-4" />
                    <span>Staff Directory ({{ usersList?.length || 0 }})</span>
                </button>

                <button
                    v-if="hasFeature('duty_roster')"
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all flex items-center gap-2',
                        activeTab === 'roster' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'roster'"
                >
                    <Calendar class="size-4" />
                    <span>Duty Roster & Shifts</span>
                    <Badge v-if="isOwner" variant="success" class="text-[9px] px-1.5 py-0 ml-1">Owner</Badge>
                </button>

                <button
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all flex items-center gap-2',
                        activeTab === 'matrix' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'matrix'"
                >
                    <ShieldCheck class="size-4" />
                    <span>Permissions Matrix</span>
                    <Lock v-if="!isOwner" class="size-3 text-muted-foreground ml-1" />
                </button>

                <button
                    v-if="hasFeature('device_binding')"
                    type="button"
                    :class="[
                        'px-4 py-2 rounded-lg transition-all flex items-center gap-2',
                        activeTab === 'devices' ? 'bg-primary text-primary-foreground shadow' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="activeTab = 'devices'"
                >
                    <Wifi class="size-4" />
                    <span>Device & Network Restrictions</span>
                    <Badge variant="destructive" class="text-[9px] px-1.5 py-0 ml-1">VPN Guard</Badge>
                </button>
            </div>

            <!-- Tab 1: Staff Directory -->
            <div v-if="activeTab === 'staff'" class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-muted/20 p-3.5 rounded-xl border border-border/80">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <Users class="size-4" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-foreground flex items-center gap-2">
                                <span>Staff Personnel Directory</span>
                                <Badge variant="outline" class="font-mono text-[10px]">{{ usersList?.length || 0 }} Active Records</Badge>
                            </p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">
                                Add individual staff accounts or batch import personnel rosters from Word, PDF, Excel spreadsheets, or CSV text.
                            </p>
                        </div>
                    </div>

                    <div v-if="isOwner" class="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 text-xs gap-1.5 border-border bg-card hover:bg-primary/10 hover:text-primary hover:border-primary/40 font-semibold transition-colors shadow-xs"
                            @click="staffImportModalOpen = true"
                        >
                            <Upload class="size-3.5 text-primary" />
                            <span>Import (Word / PDF / Excel)</span>
                        </Button>
                    </div>
                </div>

                <DataTable
                    :columns="staffColumns"
                    :data="usersList"
                    :search-placeholder="isMultiBranch ? 'Search staff, email, phone, role, branch...' : 'Search staff, email, phone, role...'"
                    :search-keys="isMultiBranch ? ['name', 'email', 'role', 'branch', 'phone'] : ['name', 'email', 'role', 'phone']"
                    search-id="users-tour-search"
                >
                    <template #cell-name="{ row }">
                        <div :id="row.id === usersList[0]?.id ? 'users-tour-col-staff' : undefined">
                            <span class="font-bold text-foreground">{{ row.name }}</span>
                            <div class="text-[11px] text-muted-foreground">{{ row.email }}</div>
                        </div>
                    </template>

                    <template #cell-role="{ row }">
                        <div :id="row.id === usersList[0]?.id ? 'users-tour-col-role' : undefined">
                            <Badge
                                :variant="row.role === 'Owner' ? 'default' : row.role === 'Manager' ? 'info' : 'outline'"
                                class="font-bold font-mono"
                            >
                                {{ row.role }}
                            </Badge>
                        </div>
                    </template>

                    <template #cell-phone="{ row }">
                        <span class="font-mono text-xs">{{ row.phone }}</span>
                    </template>

                    <template #cell-status="{ row }">
                        <div :id="row.id === usersList[0]?.id ? 'users-tour-col-status' : undefined">
                            <Badge :variant="row.status === 'Active' ? 'success' : 'destructive'">{{ row.status }}</Badge>
                        </div>
                    </template>

                    <template #cell-lastLogin="{ row }">
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span :class="['size-1.5 rounded-full shrink-0', row.status === 'Active' ? 'bg-emerald-500' : 'bg-muted-foreground']"></span>
                            <span class="font-mono text-[11px]">{{ row.lastLogin }}</span>
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div :id="row.id === usersList[0]?.id ? 'users-tour-staff-actions' : undefined" class="flex items-center justify-end gap-1.5">
                            <Button
                                :id="row.id === usersList[0]?.id ? 'users-tour-btn-view' : undefined"
                                variant="outline"
                                size="sm"
                                class="h-7 px-2.5 text-xs gap-1.5 border-sky-500/30 text-sky-700 dark:text-sky-300 hover:bg-sky-500/10 hover:border-sky-500/50 transition-colors shadow-2xs"
                                title="View User Profile, Schedule, Sales & Logins"
                                @click="openViewUser(row)"
                            >
                                <Eye class="size-3.5 text-sky-500" />
                                <span class="font-medium">View</span>
                            </Button>
                            <Button
                                :id="row.id === usersList[0]?.id ? 'users-tour-btn-edit' : undefined"
                                variant="outline"
                                size="sm"
                                class="h-7 px-2.5 text-xs gap-1.5 border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/10 hover:border-amber-500/50 transition-colors shadow-2xs"
                                title="Edit User Information"
                                @click="openEditUser(row)"
                            >
                                <Pencil class="size-3.5 text-amber-500" />
                                <span class="font-medium">Edit</span>
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </div>

            <!-- Tab 2: Duty Roster & Shift Planning (Owner Feature) -->
            <div v-if="activeTab === 'roster'" class="space-y-4">
                <div id="users-tour-roster-bar" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-muted/20 p-4 rounded-xl border border-border/80">
                    <div>
                        <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                            <Clock class="size-4 text-emerald-600" />
                            Store Duty Roster & Cashier Registers
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Assign cashiers to POS stations, balance shift coverage across peak trading hours, and track attendance.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            id="users-tour-btn-publish"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs gap-1.5 border-emerald-600/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/10"
                            @click="handlePublishRoster"
                        >
                            <Send class="size-3.5" />
                            Publish & Broadcast Roster
                        </Button>
                        <Button
                            id="users-tour-btn-schedule"
                            size="sm"
                            class="h-8 text-xs gap-1.5 bg-primary"
                            @click="newShiftModal = true"
                        >
                            <Plus class="size-3.5" />
                            Schedule Shift
                        </Button>
                    </div>
                </div>

                <!-- Shift KPI overview -->
                <div id="users-tour-roster-kpis" class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 bg-card rounded-xl border border-border/80">
                        <span class="text-muted-foreground">Morning Shift (06:30 - 14:30)</span>
                        <p class="text-base font-bold font-mono text-foreground mt-1">2 Staff on Duty</p>
                    </div>
                    <div class="p-3 bg-card rounded-xl border border-border/80">
                        <span class="text-muted-foreground">Evening Shift (14:00 - 22:00)</span>
                        <p class="text-base font-bold font-mono text-foreground mt-1">1 Staff Scheduled</p>
                    </div>
                    <div class="p-3 bg-card rounded-xl border border-border/80">
                        <span class="text-muted-foreground">Active POS Stations</span>
                        <p class="text-base font-bold font-mono text-emerald-600 mt-1">Register #01, #02</p>
                    </div>
                    <div class="p-3 bg-card rounded-xl border border-border/80">
                        <span class="text-muted-foreground">Staff on Leave / Off</span>
                        <p class="text-base font-bold font-mono text-muted-foreground mt-1">1 Member</p>
                    </div>
                </div>

                <!-- Duty Roster Table -->
                <div id="users-tour-roster-table">
                    <DataTable
                        :columns="rosterColumns"
                        :data="dutyRoster"
                        search-placeholder="Search shift by staff, role, station, branch..."
                        :search-keys="['staffName', 'role', 'station', 'branch', 'shiftType']"
                    >
                        <template #cell-staffName="{ row }">
                            <div>
                                <span class="font-bold text-foreground">{{ row.staffName }}</span>
                                <div class="text-[11px] text-muted-foreground">{{ row.role }}</div>
                            </div>
                        </template>

                        <template #cell-shiftType="{ row }">
                            <div>
                                <Badge
                                    :variant="row.shiftType === 'Morning' ? 'success' : row.shiftType === 'Evening' ? 'info' : row.shiftType === 'Full Day' ? 'default' : 'outline'"
                                    class="font-semibold"
                                >
                                    {{ row.shiftType }}
                                </Badge>
                                <div class="text-[10px] text-muted-foreground font-mono mt-0.5">{{ row.shiftHours }}</div>
                            </div>
                        </template>

                        <template #cell-station="{ row }">
                            <span class="font-mono text-xs font-semibold">{{ row.station }}</span>
                        </template>

                        <template #cell-status="{ row }">
                            <Badge
                                :variant="row.status === 'Clocked In' ? 'success' : row.status === 'Scheduled' ? 'warning' : 'outline'"
                            >
                                {{ row.status }}
                            </Badge>
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex items-center justify-end">
                                <Button
                                    v-if="row.status === 'Scheduled'"
                                    size="sm"
                                    class="h-7 text-xs bg-emerald-600 hover:bg-emerald-700"
                                    @click="() => {
                                        row.status = 'Clocked In';
                                        success('Clock In', `${row.staffName} clocked in for ${row.shiftType} shift.`);
                                    }"
                                >
                                    Clock In
                                </Button>
                                <Badge v-else variant="success">Active</Badge>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </div>

            <!-- Tab 3: Permissions Matrix (With Owner Restriction Enforcement) -->
            <div v-if="activeTab === 'matrix'" class="space-y-4">
                <!-- Non-Owner Restriction Warning Banner -->
                <div
                    v-if="!isOwner"
                    id="users-tour-matrix-restriction"
                    class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 text-xs flex items-start gap-3 text-amber-900 dark:text-amber-200"
                >
                    <ShieldAlert class="size-5 shrink-0 text-amber-600 mt-0.5" />
                    <div>
                        <h4 class="font-bold text-sm">Permission Modifications Restricted</h4>
                        <p class="mt-0.5 leading-relaxed text-muted-foreground">
                            Users are strictly restricted from editing or elevating their own permissions. Only accounts with the
                            <strong>Owner</strong> role can adjust authorization rules. (Use the Top Navbar role switcher to test Owner permissions).
                        </p>
                    </div>
                </div>

                <Card id="users-tour-matrix-grid" class="border-border/80">
                    <CardHeader class="pb-3 border-b border-border/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <ShieldCheck class="size-4 text-primary" />
                                Role Authorization Matrix
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Configure View, Create, Edit, Delete capabilities across tenant modules.
                            </CardDescription>
                        </div>

                        <!-- Role Selector Pills -->
                        <div id="users-tour-matrix-roles" class="flex items-center gap-1 overflow-x-auto pb-1 text-xs">
                            <button
                                v-for="r in availableRoles"
                                :key="r"
                                type="button"
                                :class="[
                                    'px-3 py-1 font-semibold rounded-lg border text-xs whitespace-nowrap transition-all',
                                    selectedMatrixRole === r
                                        ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                        : 'bg-card border-border/80 text-muted-foreground hover:text-foreground',
                                ]"
                                @click="selectedMatrixRole = r"
                            >
                                {{ r }}
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-border/70 bg-muted/30 text-muted-foreground">
                                <tr>
                                    <th class="px-6 py-3 font-semibold uppercase">System Module</th>
                                    <th class="px-4 py-3 font-semibold uppercase text-center">View</th>
                                    <th class="px-4 py-3 font-semibold uppercase text-center">Create</th>
                                    <th class="px-4 py-3 font-semibold uppercase text-center">Edit</th>
                                    <th class="px-4 py-3 font-semibold uppercase text-center">Delete</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="mod in modulesList"
                                    :key="mod"
                                    :id="mod === modulesList[0] ? 'users-tour-matrix-toggles' : undefined"
                                    class="hover:bg-muted/20 transition-colors"
                                >
                                    <td class="px-6 py-3 font-bold uppercase text-foreground">
                                        {{ mod }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            :disabled="!isOwner"
                                            :class="[
                                                'size-6 rounded inline-flex items-center justify-center transition-all',
                                                getRoleMatrix(selectedMatrixRole)[mod]?.view ? 'bg-emerald-500/20 text-emerald-600' : 'bg-muted text-muted-foreground opacity-40',
                                                !isOwner ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:scale-105 active:scale-95',
                                            ]"
                                            @click="toggleMatrixPerm(mod, 'view')"
                                        >
                                            <Check v-if="getRoleMatrix(selectedMatrixRole)[mod]?.view" class="size-3.5 stroke-[3]" />
                                            <X v-else class="size-3.5" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            :disabled="!isOwner"
                                            :class="[
                                                'size-6 rounded inline-flex items-center justify-center transition-all',
                                                getRoleMatrix(selectedMatrixRole)[mod]?.create ? 'bg-emerald-500/20 text-emerald-600' : 'bg-muted text-muted-foreground opacity-40',
                                                !isOwner ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:scale-105 active:scale-95',
                                            ]"
                                            @click="toggleMatrixPerm(mod, 'create')"
                                        >
                                            <Check v-if="getRoleMatrix(selectedMatrixRole)[mod]?.create" class="size-3.5 stroke-[3]" />
                                            <X v-else class="size-3.5" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            :disabled="!isOwner"
                                            :class="[
                                                'size-6 rounded inline-flex items-center justify-center transition-all',
                                                getRoleMatrix(selectedMatrixRole)[mod]?.edit ? 'bg-emerald-500/20 text-emerald-600' : 'bg-muted text-muted-foreground opacity-40',
                                                !isOwner ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:scale-105 active:scale-95',
                                            ]"
                                            @click="toggleMatrixPerm(mod, 'edit')"
                                        >
                                            <Check v-if="getRoleMatrix(selectedMatrixRole)[mod]?.edit" class="size-3.5 stroke-[3]" />
                                            <X v-else class="size-3.5" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            :disabled="!isOwner"
                                            :class="[
                                                'size-6 rounded inline-flex items-center justify-center transition-all',
                                                getRoleMatrix(selectedMatrixRole)[mod]?.delete ? 'bg-emerald-500/20 text-emerald-600' : 'bg-muted text-muted-foreground opacity-40',
                                                !isOwner ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:scale-105 active:scale-95',
                                            ]"
                                            @click="toggleMatrixPerm(mod, 'delete')"
                                        >
                                            <Check v-if="getRoleMatrix(selectedMatrixRole)[mod]?.delete" class="size-3.5 stroke-[3]" />
                                            <X v-else class="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            <!-- Tab 4: Device & Network Restrictions -->
            <div v-if="activeTab === 'devices'" class="space-y-6">
                <!-- Network Policies Grid -->
                <div id="users-tour-devices-policies" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <!-- Policy 1: VPN Guard -->
                    <Card id="users-tour-policy-vpn" class="border-border/80">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <ShieldAlert class="size-4 text-rose-500" />
                                    VPN & Proxy Blocker
                                </CardTitle>
                                <input
                                    v-model="networkPolicies.blockVpn"
                                    type="checkbox"
                                    :disabled="!isOwner"
                                    class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                />
                            </div>
                            <CardDescription class="text-xs">
                                Block any staff login attempting to connect via commercial VPNs (NordVPN, ExpressVPN, WireGuard tunnels) or proxy servers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-0 text-[11px]">
                            <span :class="['font-bold px-2 py-0.5 rounded', networkPolicies.blockVpn ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-muted text-muted-foreground']">
                                {{ networkPolicies.blockVpn ? 'Active: VPN Logins Auto-Blocked' : 'Disabled' }}
                            </span>
                        </CardContent>
                    </Card>

                    <!-- Policy 2: Store Wi-Fi Whitelist -->
                    <Card id="users-tour-policy-wifi" class="border-border/80">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <Wifi class="size-4 text-emerald-500" />
                                    Enforce Store Wi-Fi
                                </CardTitle>
                                <input
                                    v-model="networkPolicies.requireStoreWifiForCashiers"
                                    type="checkbox"
                                    :disabled="!isOwner"
                                    class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                />
                            </div>
                            <CardDescription class="text-xs">
                                Cashiers and POS registers can only log in when connected to whitelisted store router SSIDs & BSSIDs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-0 text-[11px]">
                            <span :class="['font-bold px-2 py-0.5 rounded', networkPolicies.requireStoreWifiForCashiers ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground']">
                                {{ networkPolicies.requireStoreWifiForCashiers ? 'Active: Store Network Required' : 'Disabled' }}
                            </span>
                        </CardContent>
                    </Card>

                    <!-- Policy 3: Mobile Hotspot Guard -->
                    <Card class="border-border/80">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <Radio class="size-4 text-amber-500" />
                                    Block Mobile Hotspots
                                </CardTitle>
                                <input
                                    v-model="networkPolicies.blockMobileHotspots"
                                    type="checkbox"
                                    :disabled="!isOwner"
                                    class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                />
                            </div>
                            <CardDescription class="text-xs">
                                Disallow cashiers from tethering or connecting to mobile hotspots to prevent unauthorized off-site operations.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-0 text-[11px]">
                            <span :class="['font-bold px-2 py-0.5 rounded', networkPolicies.blockMobileHotspots ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-muted text-muted-foreground']">
                                {{ networkPolicies.blockMobileHotspots ? 'Active: Hotspots Blocked' : 'Disabled' }}
                            </span>
                        </CardContent>
                    </Card>

                    <!-- Policy 4: Cellular SIM Data Policy -->
                    <Card class="border-border/80">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <Signal class="size-4 text-sky-500" />
                                    Cellular SIM Policy
                                </CardTitle>
                                <input
                                    v-model="networkPolicies.allowSimDataForFieldRoles"
                                    type="checkbox"
                                    :disabled="!isOwner"
                                    class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                />
                            </div>
                            <CardDescription class="text-xs">
                                Allow 4G/5G mobile SIM data connections strictly for Field Sales, Delivery, and Managers; Cashiers remain on Wi-Fi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-0 text-[11px]">
                            <span :class="['font-bold px-2 py-0.5 rounded', networkPolicies.allowSimDataForFieldRoles ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400' : 'bg-muted text-muted-foreground']">
                                {{ networkPolicies.allowSimDataForFieldRoles ? 'Restricted: Role-Gated Cellular' : 'Unrestricted SIM' }}
                            </span>
                        </CardContent>
                    </Card>

                    <!-- Policy 5: Hardware Device Binding -->
                    <Card class="border-border/80">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <Key class="size-4 text-violet-500" />
                                    Hardware Device Binding
                                </CardTitle>
                                <input
                                    v-model="networkPolicies.requireHardwareDeviceBinding"
                                    type="checkbox"
                                    :disabled="!isOwner"
                                    class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                />
                            </div>
                            <CardDescription class="text-xs">
                                Require every POS hardware terminal to be bound & approved by the Owner before cashier shifts can start.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-0 text-[11px]">
                            <span :class="['font-bold px-2 py-0.5 rounded', networkPolicies.requireHardwareDeviceBinding ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400' : 'bg-muted text-muted-foreground']">
                                {{ networkPolicies.requireHardwareDeviceBinding ? 'Active: Unknown Devices Gated' : 'Disabled' }}
                            </span>
                        </CardContent>
                    </Card>

                    <!-- Policy 6: Single Active Session -->
                    <Card class="border-border/80">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-2">
                                    <Lock class="size-4 text-primary" />
                                    Single-Terminal Session
                                </CardTitle>
                                <input
                                    v-model="networkPolicies.enforceSingleSessionPerCashier"
                                    type="checkbox"
                                    :disabled="!isOwner"
                                    class="size-4 rounded border-input bg-background text-primary focus:ring-primary"
                                />
                            </div>
                            <CardDescription class="text-xs">
                                Terminate any prior active session if a cashier attempts to log in simultaneously from another terminal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-0 text-[11px]">
                            <span :class="['font-bold px-2 py-0.5 rounded', networkPolicies.enforceSingleSessionPerCashier ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground']">
                                {{ networkPolicies.enforceSingleSessionPerCashier ? 'Active: Concurrency Locked' : 'Disabled' }}
                            </span>
                        </CardContent>
                    </Card>
                </div>

                <!-- Whitelisted Store Wi-Fi Networks Section -->
                <Card id="users-tour-wifi-whitelist" class="border-border/80">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3">
                        <div>
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <Router class="size-4 text-emerald-500" />
                                Whitelisted Store Wi-Fi Access Points
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Only devices connected to these verified physical store router SSIDs & MAC subnets are authorized to process transactions.
                            </CardDescription>
                        </div>
                        <Button
                            v-if="isOwner"
                            id="users-tour-btn-add-wifi"
                            size="sm"
                            class="h-8 text-xs gap-1.5 bg-primary"
                            @click="newWifiModal = true"
                        >
                            <Plus class="size-3.5" />
                            Whitelist Store Wi-Fi
                        </Button>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div
                                v-for="wifi in whitelistedWifi"
                                :key="wifi.id"
                                class="p-3 rounded-xl border border-border/80 bg-muted/20 flex items-center justify-between"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="size-9 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                        <Wifi class="size-4.5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-foreground">{{ wifi.ssid }}</p>
                                        <p class="text-[10px] text-muted-foreground font-mono">BSSID: {{ wifi.bssid }} · Subnet: {{ wifi.subnet }}</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 flex items-center gap-1">
                                            <Store class="size-3 text-primary" /> {{ wifi.branch }}
                                        </p>
                                    </div>
                                </div>
                                <Badge variant="success" class="text-[10px]">Verified</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Live Connected Staff Devices Table -->
                <Card id="users-tour-devices-table" class="border-border/80 overflow-hidden">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-bold flex items-center gap-2">
                                    <Smartphone class="size-4 text-primary" />
                                    Live Connected & Bound Staff Devices
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Real-time inspection of hardware types, operating systems, network connections, and security lock states.
                                </CardDescription>
                            </div>
                            <span class="text-xs text-muted-foreground font-mono font-bold">
                                {{ userDevices.length }} Monitored Devices
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-muted/40 text-muted-foreground border-y border-border/80">
                                    <tr>
                                        <th class="px-5 py-3 font-semibold uppercase">Staff Member</th>
                                        <th class="px-4 py-3 font-semibold uppercase">Device & Hardware</th>
                                        <th class="px-4 py-3 font-semibold uppercase">Connection & Network</th>
                                        <th class="px-4 py-3 font-semibold uppercase">IP & Location</th>
                                        <th class="px-4 py-3 font-semibold uppercase text-center">Status</th>
                                        <th class="px-4 py-3 font-semibold uppercase text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="dev in userDevices" :key="dev.id" class="hover:bg-muted/20 transition-colors">
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-foreground">{{ dev.staffName }}</div>
                                            <div class="text-[10px] text-muted-foreground">{{ dev.role }}</div>
                                        </td>

                                        <!-- Device & Hardware Info -->
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-2.5">
                                                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                                    <component :is="deviceIconMap[dev.deviceCategory] || Smartphone" class="size-4" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-foreground flex items-center gap-1.5">
                                                        <span>{{ dev.deviceName }}</span>
                                                        <Badge variant="outline" class="text-[9px] px-1 py-0 font-mono">{{ dev.deviceCategory }}</Badge>
                                                    </div>
                                                    <div class="text-[10px] text-muted-foreground font-mono">{{ dev.os }}</div>
                                                    <div class="text-[9px] text-muted-foreground font-mono truncate max-w-xs">{{ dev.deviceFingerprint }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Connection & Network Type -->
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded border',
                                                        dev.networkType === 'Store Wi-Fi (Verified)' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' :
                                                        dev.networkType === 'Cellular SIM (4G/5G)' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20' :
                                                        dev.networkType === 'VPN / Proxy' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' :
                                                        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                                    ]"
                                                >
                                                    <Wifi v-if="dev.networkType === 'Store Wi-Fi (Verified)'" class="size-3" />
                                                    <Signal v-else-if="dev.networkType === 'Cellular SIM (4G/5G)'" class="size-3" />
                                                    <ShieldAlert v-else-if="dev.networkType === 'VPN / Proxy'" class="size-3" />
                                                    <Radio v-else class="size-3" />
                                                    {{ dev.networkType }}
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-muted-foreground">{{ dev.networkName }}</p>
                                        </td>

                                        <!-- IP & Location -->
                                        <td class="px-4 py-3.5">
                                            <p class="text-xs font-mono text-foreground font-bold">{{ dev.ipAddress }}</p>
                                            <p class="text-[10px] text-muted-foreground">{{ dev.lastActive }}</p>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-4 py-3.5 text-center">
                                            <Badge
                                                :variant="dev.status === 'Authorized & Bound' ? 'success' : dev.status === 'Blocked (VPN Detected)' ? 'destructive' : 'outline'"
                                                class="text-[10px] font-bold"
                                            >
                                                {{ dev.status }}
                                            </Badge>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-4 py-3.5 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Button
                                                    v-if="dev.status !== 'Blocked (VPN Detected)'"
                                                    :id="dev.id === userDevices[0]?.id ? 'users-tour-btn-remote-lock' : undefined"
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 text-[11px] text-rose-600 hover:bg-rose-500/10 gap-1"
                                                    @click="handleRemoteLock(dev)"
                                                >
                                                    <Ban class="size-3" />
                                                    Lock Device
                                                </Button>

                                                <Button
                                                    v-if="dev.status !== 'Authorized & Bound'"
                                                    size="sm"
                                                    class="h-7 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
                                                    @click="handleAuthorizeDevice(dev)"
                                                >
                                                    <CheckCircle2 class="size-3" />
                                                    Authorize
                                                </Button>

                                                <Button
                                                    v-if="dev.bound"
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 text-[11px] text-muted-foreground hover:text-destructive gap-1"
                                                    @click="handleRevokeDevice(dev)"
                                                >
                                                    <XCircle class="size-3" />
                                                    Revoke
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Add Whitelisted Store Wi-Fi Modal -->
        <Dialog :open="newWifiModal" @update:open="(v) => (newWifiModal = v)">
            <DialogContent class="w-[92vw] sm:max-w-md max-h-[85vh] flex flex-col p-0 overflow-hidden shadow-xl">
                <DialogHeader class="p-4 border-b border-border/80 bg-muted/20 shrink-0">
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Wifi class="size-4 text-emerald-500" />
                        Whitelist Store Wi-Fi Network
                    </DialogTitle>
                </DialogHeader>
                <div class="p-4 overflow-y-auto flex-1 space-y-3 text-xs">
                    <div>
                        <Label class="text-xs">Wi-Fi Network SSID (Name)</Label>
                        <Input v-model="newWifiForm.ssid" placeholder="e.g. NairobiFresh-POS-Store5G" class="mt-1 text-xs" />
                    </div>

                    <div>
                        <Label class="text-xs">Router BSSID / MAC Address (Optional)</Label>
                        <Input v-model="newWifiForm.bssid" placeholder="e.g. F4:8C:50:A1:3B:90" class="mt-1 text-xs font-mono" />
                    </div>

                    <div>
                        <Label class="text-xs">Local LAN Subnet Range</Label>
                        <Input v-model="newWifiForm.subnet" placeholder="192.168.10.0/24" class="mt-1 text-xs font-mono" />
                    </div>

                    <div>
                        <Label class="text-xs">Branch Outlet</Label>
                        <select v-model="newWifiForm.branch" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option v-for="b in allBranches" :key="b.id" :value="b.name">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
                <DialogFooter class="p-3 border-t border-border/80 bg-muted/20 gap-2 shrink-0">
                    <Button variant="ghost" size="sm" @click="newWifiModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isSavingWifi" @click="handleAddWifi">Save & Whitelist Wi-Fi</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Schedule Shift Modal -->
        <Dialog :open="newShiftModal" @update:open="(v) => (newShiftModal = v)">
            <DialogContent class="w-[92vw] sm:max-w-md max-h-[85vh] flex flex-col p-0 overflow-hidden shadow-xl">
                <DialogHeader class="p-4 border-b border-border/80 bg-muted/20 shrink-0">
                    <DialogTitle class="text-base font-bold">Assign Staff Duty Shift</DialogTitle>
                </DialogHeader>
                <div class="p-4 overflow-y-auto flex-1 space-y-3 text-xs">
                    <div>
                        <Label class="text-xs">Select Staff Member</Label>
                        <select v-model="newShiftForm.staffId" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option v-for="u in usersList" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.role }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs">Shift Timing</Label>
                            <select v-model="newShiftForm.shiftType" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option value="Morning">Morning (06:30 - 14:30)</option>
                                <option value="Evening">Evening (14:00 - 22:00)</option>
                                <option value="Full Day">Full Day (08:00 - 17:00)</option>
                                <option value="Night">Night (22:00 - 06:00)</option>
                                <option value="Off Day">Off Day / Rest</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-xs">Shift Date</Label>
                            <Input v-model="newShiftForm.shiftDate" class="mt-1 text-xs font-mono" />
                        </div>
                    </div>

                    <div>
                        <Label class="text-xs">Assigned Station / POS Terminal</Label>
                        <Input v-model="newShiftForm.station" placeholder="e.g. POS Register #01 (Front Checkout)" class="mt-1 text-xs" />
                    </div>

                    <div>
                        <Label class="text-xs">Operating Branch</Label>
                        <select v-model="newShiftForm.branch" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                            <option v-for="b in allBranches" :key="b.id" :value="b.name">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
                <DialogFooter class="p-3 border-t border-border/80 bg-muted/20 gap-2 shrink-0">
                    <Button variant="ghost" size="sm" @click="newShiftModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isSavingShift" @click="handleAddShift">Assign Shift</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Staff Modal -->
        <Dialog :open="addStaffModal" @update:open="(v) => (addStaffModal = v)">
            <DialogContent class="w-[92vw] sm:max-w-md max-h-[85vh] flex flex-col p-0 overflow-hidden shadow-xl">
                <DialogHeader class="p-4 border-b border-border/80 bg-muted/20 shrink-0">
                    <DialogTitle class="text-base font-bold">Add New Staff Member</DialogTitle>
                </DialogHeader>
                <div class="p-4 overflow-y-auto flex-1 space-y-3 text-xs">
                    <div>
                        <Label class="text-xs">Full Name</Label>
                        <Input v-model="newStaff.name" placeholder="e.g. Brian Kipkorir" class="mt-1 text-xs" />
                    </div>
                    <div>
                        <Label class="text-xs">Work Email</Label>
                        <Input v-model="newStaff.email" placeholder="brian.k@pos.co.ke" class="mt-1 text-xs" />
                    </div>
                    <div>
                        <Label class="text-xs">Phone Number (M-Pesa / SMS)</Label>
                        <Input v-model="newStaff.phone" placeholder="+254 7XX XXX XXX" class="mt-1 text-xs font-mono" />
                    </div>
                    <div :class="['grid gap-3', isMultiBranch ? 'grid-cols-2' : 'grid-cols-1']">
                        <div>
                            <Label class="text-xs">Assigned Role</Label>
                            <select v-model="newStaff.role" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option v-for="r in availableRoles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                        <div v-if="isMultiBranch">
                            <Label class="text-xs">Branch Outlet</Label>
                            <select v-model="newStaff.branch" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option v-for="b in allBranches" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <DialogFooter class="p-3 border-t border-border/80 bg-muted/20 gap-2 shrink-0">
                    <Button variant="ghost" size="sm" @click="addStaffModal = false">Cancel</Button>
                    <Button size="sm" class="bg-primary" :loading="isCreatingStaff" @click="handleCreateStaff">Save & Send Invite</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Staff Member Modal -->
        <Dialog :open="editStaffModal" @update:open="(v) => (editStaffModal = v)">
            <DialogContent class="w-[92vw] sm:max-w-md max-h-[85vh] flex flex-col p-0 overflow-hidden shadow-xl">
                <DialogHeader class="p-4 border-b border-border/80 bg-muted/20 shrink-0">
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <Pencil class="size-4 text-amber-500" />
                        <span>Edit Staff Member</span>
                        <Badge variant="outline" class="font-mono text-[10px] ml-auto">ID: {{ editStaffForm.id }}</Badge>
                    </DialogTitle>
                </DialogHeader>

                <div class="p-4 overflow-y-auto flex-1 space-y-3.5 text-xs">
                    <div>
                        <Label class="text-xs font-semibold">Full Name</Label>
                        <Input v-model="editStaffForm.name" placeholder="e.g. David Mwangi" class="mt-1 text-xs" />
                    </div>

                    <div>
                        <Label class="text-xs font-semibold">Work Email Address</Label>
                        <Input v-model="editStaffForm.email" type="email" placeholder="staff@mdepos.co.ke" class="mt-1 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label class="text-xs font-semibold">Phone Number (M-Pesa / SMS)</Label>
                            <Input v-model="editStaffForm.phone" placeholder="+254 7XX XXX XXX" class="mt-1 text-xs font-mono" />
                        </div>
                        <div>
                            <Label class="text-xs font-semibold">Assigned Role</Label>
                            <select v-model="editStaffForm.role" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option v-for="r in availableRoles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                    </div>

                    <div :class="['grid gap-3', isMultiBranch ? 'grid-cols-2' : 'grid-cols-1']">
                        <div v-if="isMultiBranch">
                            <Label class="text-xs font-semibold">Assigned Branch Location</Label>
                            <select v-model="editStaffForm.branch" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option v-for="b in allBranches" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-xs font-semibold">Account Status</Label>
                            <select v-model="editStaffForm.status" class="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-xs">
                                <option value="Active">Active (Full Access)</option>
                                <option value="Suspended">Suspended (Blocked)</option>
                                <option value="Archived">Archived (Decommissioned)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Password Reset Section -->
                    <div class="pt-2 border-t border-border/70">
                        <div class="flex items-center justify-between">
                            <div>
                                <Label class="text-xs font-semibold">Reset Staff Password</Label>
                                <p class="text-[11px] text-muted-foreground">Leave blank to keep existing credentials</p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-6 text-[11px] text-primary"
                                @click="showEditPassword = !showEditPassword"
                            >
                                {{ showEditPassword ? 'Hide Password' : 'Change Password' }}
                            </Button>
                        </div>
                        <div v-if="showEditPassword" class="mt-2 relative">
                            <Input
                                v-model="editStaffForm.password"
                                type="text"
                                placeholder="Enter new password (min 8 characters)"
                                class="h-8 text-xs font-mono pr-8"
                            />
                            <Key class="size-3.5 absolute right-2.5 top-2.5 text-muted-foreground" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="p-3 border-t border-border/80 bg-muted/20 gap-2 shrink-0">
                    <Button variant="ghost" size="sm" @click="editStaffModal = false">Cancel</Button>
                    <Button
                        size="sm"
                        class="bg-amber-600 hover:bg-amber-700 text-white font-semibold gap-1.5"
                        :loading="editProcessing"
                        @click="handleUpdateStaff"
                    >
                        <Check v-if="!editProcessing" class="size-3.5" />
                        {{ editProcessing ? 'Saving Changes...' : 'Save User Changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- View Staff Profile & Activity Details Modal -->
        <Dialog :open="viewStaffModal" @update:open="(v) => (viewStaffModal = v)">
            <DialogContent v-if="viewUserTarget" class="w-[94vw] sm:max-w-3xl md:max-w-4xl max-h-[85vh] flex flex-col p-0 overflow-hidden shadow-2xl rounded-2xl border-border/80">
                <!-- User Profile Header Banner -->
                <div class="p-4 sm:p-5 border-b border-border/70 bg-gradient-to-r from-primary/10 via-card to-card shrink-0">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-2xl bg-gradient-to-br from-primary to-primary/80 text-primary-foreground flex items-center justify-center font-bold text-lg shadow-md ring-4 ring-primary/10">
                                {{ viewUserTarget.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-base font-bold text-foreground">{{ viewUserTarget.name }}</h3>
                                    <Badge
                                        :variant="viewUserTarget.role === 'Owner' ? 'default' : viewUserTarget.role === 'Manager' ? 'info' : 'outline'"
                                        class="font-mono text-xs font-semibold"
                                    >
                                        {{ viewUserTarget.role }}
                                    </Badge>
                                    <Badge :variant="viewUserTarget.status === 'Active' ? 'success' : 'destructive'" class="text-[10px]">
                                        {{ viewUserTarget.status }}
                                    </Badge>
                                </div>
                                <div class="flex items-center gap-2.5 text-xs text-muted-foreground mt-0.5 flex-wrap">
                                    <span class="font-mono">{{ viewUserTarget.email }}</span>
                                    <span>•</span>
                                    <span class="font-mono">{{ viewUserTarget.phone || 'No phone' }}</span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1"><Store class="size-3 text-muted-foreground" /> {{ viewUserTarget.branch }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs gap-1.5 border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/10"
                                @click="() => {
                                    viewStaffModal = false;
                                    openEditUser(viewUserTarget);
                                }"
                            >
                                <Pencil class="size-3" />
                                Edit User
                            </Button>
                            <Button
                                v-if="isOwner && viewUserTarget.role !== 'Owner'"
                                variant="outline"
                                size="sm"
                                :class="[
                                    'h-7 text-xs gap-1.5',
                                    viewUserTarget.status === 'Active'
                                        ? 'border-rose-500/30 text-rose-700 dark:text-rose-300 hover:bg-rose-500/10'
                                        : 'border-emerald-500/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/10'
                                ]"
                                @click="handleToggleStatus(viewUserTarget)"
                            >
                                <Ban v-if="viewUserTarget.status === 'Active'" class="size-3" />
                                <CheckCircle2 v-else class="size-3" />
                                {{ viewUserTarget.status === 'Active' ? 'Suspend' : 'Reactivate' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Inner Navigation Tabs -->
                    <div class="flex items-center gap-1 border-t border-border/60 mt-3 pt-2.5 overflow-x-auto text-xs font-semibold scrollbar-none">
                        <button
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap',
                                viewUserTab === 'overview' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                            ]"
                            @click="viewUserTab = 'overview'"
                        >
                            <ShieldCheck class="size-3.5" />
                            <span>Overview & Permissions</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap',
                                viewUserTab === 'schedule' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                            ]"
                            @click="viewUserTab = 'schedule'"
                        >
                            <CalendarDays class="size-3.5" />
                            <span>Duty Schedule ({{ userDutyShifts.length }})</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap',
                                viewUserTab === 'sales' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                            ]"
                            @click="viewUserTab = 'sales'"
                        >
                            <ShoppingBag class="size-3.5" />
                            <span>Sales & Orders ({{ viewUserTarget.sales_count || 0 }})</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap',
                                viewUserTab === 'logins' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                            ]"
                            @click="viewUserTab = 'logins'"
                        >
                            <Monitor class="size-3.5" />
                            <span>Logins & Devices ({{ userBoundDevices.length }})</span>
                        </button>
                    </div>
                </div>

                <!-- Tab Content Body -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 scrollbar-thin">
                    <!-- ── TAB 1: OVERVIEW & RELATIVE INFO ───────────────────── -->
                    <div v-if="viewUserTab === 'overview'" class="space-y-4">
                        <!-- Quick KPI Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <TrendingUp class="size-3 text-emerald-500" /> Total Sales Generated
                                </span>
                                <p class="text-base font-bold font-mono text-emerald-600 mt-1">
                                    KES {{ (viewUserTarget.sales_total || 0).toLocaleString() }}
                                </p>
                            </div>
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <Receipt class="size-3 text-sky-500" /> Orders Processed
                                </span>
                                <p class="text-base font-bold font-mono text-foreground mt-1">
                                    {{ viewUserTarget.sales_count || 0 }} sales
                                </p>
                            </div>
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <Coins class="size-3 text-amber-500" /> Average Basket Size
                                </span>
                                <p class="text-base font-bold font-mono text-foreground mt-1">
                                    KES {{ (viewUserTarget.avg_ticket || 0).toLocaleString() }}
                                </p>
                            </div>
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <Clock class="size-3 text-primary" /> Duty Shifts Assigned
                                </span>
                                <p class="text-base font-bold font-mono text-foreground mt-1">
                                    {{ userDutyShifts.length }} scheduled
                                </p>
                            </div>
                        </div>

                        <!-- Two Column Detailed Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Role Authorization Capabilities -->
                            <Card class="border-border/80">
                                <CardHeader class="pb-3 border-b border-border/70">
                                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                        <ShieldCheck class="size-4 text-primary" />
                                        Role Capabilities for {{ viewUserTarget.role }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="p-4 space-y-2.5 text-xs">
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="flex items-center gap-2">
                                            <Store class="size-3.5 text-muted-foreground" /> POS Terminal Checkout
                                        </span>
                                        <Badge :variant="['Owner', 'Manager', 'Cashier', 'Supervisor'].includes(viewUserTarget.role) ? 'success' : 'outline'" class="text-[10px]">
                                            {{ ['Owner', 'Manager', 'Cashier', 'Supervisor'].includes(viewUserTarget.role) ? 'Authorized' : 'Restricted' }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="flex items-center gap-2">
                                            <Receipt class="size-3.5 text-muted-foreground" /> Sales Records & Returns
                                        </span>
                                        <Badge :variant="['Owner', 'Manager', 'Supervisor', 'Accountant'].includes(viewUserTarget.role) ? 'success' : 'outline'" class="text-[10px]">
                                            {{ ['Owner', 'Manager', 'Supervisor', 'Accountant'].includes(viewUserTarget.role) ? 'Authorized' : 'Restricted' }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="flex items-center gap-2">
                                            <Layers class="size-3.5 text-muted-foreground" /> Inventory & Stock Receiving
                                        </span>
                                        <Badge :variant="['Owner', 'Manager', 'Inventory Manager'].includes(viewUserTarget.role) ? 'success' : 'outline'" class="text-[10px]">
                                            {{ ['Owner', 'Manager', 'Inventory Manager'].includes(viewUserTarget.role) ? 'Authorized' : 'Restricted' }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="flex items-center gap-2">
                                            <TrendingUp class="size-3.5 text-muted-foreground" /> Financial Reports & P&L
                                        </span>
                                        <Badge :variant="['Owner', 'Manager', 'Accountant'].includes(viewUserTarget.role) ? 'success' : 'outline'" class="text-[10px]">
                                            {{ ['Owner', 'Manager', 'Accountant'].includes(viewUserTarget.role) ? 'Authorized' : 'Restricted' }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between py-1">
                                        <span class="flex items-center gap-2">
                                            <Users class="size-3.5 text-muted-foreground" /> Users & Permissions Admin
                                        </span>
                                        <Badge :variant="viewUserTarget.role === 'Owner' ? 'success' : 'outline'" class="text-[10px]">
                                            {{ viewUserTarget.role === 'Owner' ? 'Full Master Access' : 'Restricted' }}
                                        </Badge>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Account & Employment Metadata -->
                            <Card class="border-border/80">
                                <CardHeader class="pb-3 border-b border-border/70">
                                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                        <UserCheck class="size-4 text-primary" />
                                        Profile & Security Metadata
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="p-4 space-y-2.5 text-xs">
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="text-muted-foreground">Staff Member ID</span>
                                        <span class="font-mono font-bold text-foreground">#STF-{{ viewUserTarget.id.padStart ? viewUserTarget.id.padStart(4, '0') : viewUserTarget.id }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="text-muted-foreground">Account Created</span>
                                        <span class="font-mono text-foreground">{{ viewUserTarget.created_at_formatted || viewUserTarget.created_at }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="text-muted-foreground">Default Operating Branch</span>
                                        <span class="font-semibold text-foreground">{{ viewUserTarget.branch }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1 border-b border-border/50">
                                        <span class="text-muted-foreground">Hardware Terminal Binding</span>
                                        <Badge variant="success" class="text-[10px]">{{ userBoundDevices.length }} Device(s) Bound</Badge>
                                    </div>
                                    <div class="flex items-center justify-between py-1">
                                        <span class="text-muted-foreground">Security Status</span>
                                        <span class="flex items-center gap-1 text-emerald-600 font-semibold">
                                            <ShieldCheck class="size-3.5" /> No Flagged Incidents
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- ── TAB 2: SCHEDULE & SHIFTS ────────────────────────── -->
                    <div v-if="viewUserTab === 'schedule'" class="space-y-4">
                        <!-- Current Today Assignment Banner -->
                        <div class="p-4 rounded-xl border border-border/80 bg-muted/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h4 class="font-bold text-sm text-foreground flex items-center gap-2">
                                    <Clock class="size-4 text-emerald-600" />
                                    Active Duty Status for Today
                                </h4>
                                <div v-if="userDutyShifts.length > 0" class="text-xs text-muted-foreground mt-1 space-y-0.5">
                                    <p>
                                        Assigned Station:
                                        <strong class="text-foreground">{{ userDutyShifts[0].station }}</strong>
                                        ({{ userDutyShifts[0].shiftType }} Shift: {{ userDutyShifts[0].shiftHours }})
                                    </p>
                                    <p>Branch Location: {{ userDutyShifts[0].branch }}</p>
                                </div>
                                <div v-else class="text-xs text-muted-foreground mt-1">
                                    No shifts assigned on the roster for today.
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="userDutyShifts.length > 0 && userDutyShifts[0].status === 'Scheduled'"
                                    size="sm"
                                    class="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 gap-1.5"
                                    @click="handleQuickClockIn(userDutyShifts[0])"
                                >
                                    <Clock class="size-3.5" />
                                    Clock In Staff
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 text-xs gap-1.5"
                                    @click="() => {
                                        newShiftForm.staffId = viewUserTarget.id;
                                        newShiftModal = true;
                                    }"
                                >
                                    <Plus class="size-3.5" />
                                    Assign Shift
                                </Button>
                            </div>
                        </div>

                        <!-- Scheduled Shifts Table -->
                        <Card class="border-border/80">
                            <CardHeader class="pb-3 border-b border-border/70">
                                <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                    <Calendar class="size-4 text-primary" />
                                    Scheduled Duty Shifts ({{ userDutyShifts.length }})
                                </CardTitle>
                            </CardHeader>
                            <div class="p-0 overflow-x-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap">
                                    <thead class="bg-muted/40 text-muted-foreground border-b border-border/70">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Shift Date</th>
                                            <th class="px-4 py-2.5 font-semibold">Timing & Hours</th>
                                            <th class="px-4 py-2.5 font-semibold">Assigned Station / POS</th>
                                            <th class="px-4 py-2.5 font-semibold">Branch</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr v-for="s in userDutyShifts" :key="s.id" class="hover:bg-muted/30">
                                            <td class="px-4 py-3 font-semibold text-foreground">{{ s.shiftDate }}</td>
                                            <td class="px-4 py-3">
                                                <Badge
                                                    :variant="s.shiftType === 'Morning' ? 'success' : s.shiftType === 'Evening' ? 'info' : 'default'"
                                                    class="font-semibold text-[10px]"
                                                >
                                                    {{ s.shiftType }}
                                                </Badge>
                                                <span class="text-[11px] text-muted-foreground font-mono ml-2">{{ s.shiftHours }}</span>
                                            </td>
                                            <td class="px-4 py-3 font-mono font-semibold">{{ s.station }}</td>
                                            <td class="px-4 py-3 text-muted-foreground">{{ s.branch }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <Badge :variant="s.status === 'Clocked In' ? 'success' : 'warning'" class="text-[10px]">
                                                    {{ s.status }}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr v-if="userDutyShifts.length === 0">
                                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground text-xs">
                                                No duty shifts scheduled for {{ viewUserTarget.name }}. Use "Assign Shift" to schedule.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <!-- Attendance & Clock In / Out Log -->
                        <Card class="border-border/80">
                            <CardHeader class="pb-3 border-b border-border/70">
                                <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                    <Clock class="size-4 text-primary" />
                                    Attendance & Clock-In Sessions History
                                </CardTitle>
                            </CardHeader>
                            <div class="p-0 overflow-x-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap">
                                    <thead class="bg-muted/40 text-muted-foreground border-b border-border/70">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Clock In</th>
                                            <th class="px-4 py-2.5 font-semibold">Clock Out</th>
                                            <th class="px-4 py-2.5 font-semibold">Duration</th>
                                            <th class="px-4 py-2.5 font-semibold">Station / Branch</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Session Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr v-for="(cs, idx) in viewUserTarget.clock_sessions" :key="idx" class="hover:bg-muted/30">
                                            <td class="px-4 py-3 font-mono font-semibold text-foreground">{{ cs.clocked_in_at }}</td>
                                            <td class="px-4 py-3 font-mono text-muted-foreground">{{ cs.clocked_out_at || 'In Progress' }}</td>
                                            <td class="px-4 py-3 font-semibold text-primary">{{ cs.duration }}</td>
                                            <td class="px-4 py-3 text-muted-foreground">{{ cs.branch_name }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <Badge :variant="cs.status === 'Active' ? 'success' : 'outline'" class="text-[10px]">
                                                    {{ cs.status }}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr v-if="!viewUserTarget.clock_sessions || viewUserTarget.clock_sessions.length === 0">
                                            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground text-xs">
                                                No recorded clock-in sessions yet for this user.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>

                    <!-- ── TAB 3: SALES & PERFORMANCE (WITH SHIFT TOGGLE) ──── -->
                    <div v-if="viewUserTab === 'sales'" class="space-y-4">
                        <!-- Shift / Day Sales History Toggle Bar -->
                        <div class="p-4 rounded-xl border border-border/80 bg-muted/20 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-bold text-sm text-foreground flex items-center gap-2">
                                        <ShoppingBag class="size-4 text-emerald-600" />
                                        <span>Daily Shift Sales History</span>
                                    </h4>
                                    <Badge
                                        v-if="selectedSalesShift === 'current' && !shiftEndedMap[viewUserTarget.id]"
                                        variant="success"
                                        class="gap-1 text-[10px] font-semibold"
                                    >
                                        <span class="size-1.5 rounded-full bg-white animate-pulse"></span>
                                        Live Shift In Progress
                                    </Badge>
                                    <Badge
                                        v-else-if="selectedSalesShift === 'current' && shiftEndedMap[viewUserTarget.id]"
                                        variant="outline"
                                        class="gap-1 text-[10px] border-emerald-500/40 text-emerald-600 font-semibold"
                                    >
                                        <CheckCircle2 class="size-3 text-emerald-500" />
                                        Clean Shift Started (0 Sales)
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="gap-1 text-[10px] text-muted-foreground"
                                    >
                                        <Clock class="size-3" />
                                        Archived Shift Ledger
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Toggle between active register trading and past ended shifts to reconcile cashier totals per day.
                                </p>
                            </div>

                            <!-- Shift Selector Pills & End Shift Action -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Shift Selection Dropdown / Buttons -->
                                <div class="flex rounded-lg border border-border/80 p-0.5 bg-card text-xs">
                                    <button
                                        v-for="opt in userShiftOptions"
                                        :key="opt.id"
                                        type="button"
                                        :class="[
                                            'px-2.5 py-1.5 rounded-md transition-all font-medium whitespace-nowrap',
                                            selectedSalesShift === opt.id
                                                ? 'bg-primary text-primary-foreground shadow-2xs font-semibold'
                                                : 'text-muted-foreground hover:text-foreground hover:bg-muted/40'
                                        ]"
                                        @click="selectedSalesShift = opt.id"
                                    >
                                        {{ opt.date }}
                                    </button>
                                </div>

                                <!-- End Current Shift Button / Reopen -->
                                <Button
                                    v-if="selectedSalesShift === 'current' && !shiftEndedMap[viewUserTarget.id]"
                                    variant="destructive"
                                    size="sm"
                                    class="h-8 text-xs gap-1.5 bg-rose-600 hover:bg-rose-700 font-semibold shadow-xs"
                                    title="End active shift and start fresh sales ledger for the next shift/day"
                                    @click="handleEndCurrentShift"
                                >
                                    <Ban class="size-3.5" />
                                    End Shift & Finalize Day
                                </Button>
                                <Button
                                    v-else-if="shiftEndedMap[viewUserTarget.id]"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 text-xs gap-1.5 border-emerald-600/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/10 font-semibold"
                                    @click="handleStartNewShift"
                                >
                                    <History class="size-3.5" />
                                    Reopen Prior Shift
                                </Button>
                                <Button
                                    v-else
                                    variant="outline"
                                    size="sm"
                                    class="h-8 text-xs gap-1.5 border-primary/30 text-primary"
                                    @click="selectedSalesShift = 'current'"
                                >
                                    <ArrowUpRight class="size-3.5" />
                                    Current Shift
                                </Button>
                            </div>
                        </div>

                        <!-- Dynamic Summary metrics for selected shift -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <TrendingUp class="size-3 text-emerald-500" /> Shift Sales Revenue
                                </span>
                                <p class="text-base font-bold font-mono text-emerald-600 mt-1">
                                    KES {{ activeShiftSalesTotal.toLocaleString() }}
                                </p>
                                <span class="text-[10px] text-muted-foreground font-mono">
                                    {{ selectedSalesShift === 'all' ? 'All-Time Total' : 'For Selected Shift' }}
                                </span>
                            </div>
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <Coins class="size-3 text-emerald-600" /> Attributed Gross Profit
                                </span>
                                <p class="text-base font-bold font-mono text-foreground mt-1">
                                    KES {{ activeShiftSalesProfit.toLocaleString() }}
                                </p>
                                <span class="text-[10px] text-muted-foreground font-mono">Margin included</span>
                            </div>
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <Receipt class="size-3 text-sky-500" /> Shift Receipts Issued
                                </span>
                                <p class="text-base font-bold font-mono text-foreground mt-1">
                                    {{ activeShiftSalesCount }} transactions
                                </p>
                                <span class="text-[10px] text-muted-foreground font-mono">
                                    {{ activeShiftSalesCount === 0 ? 'Clean Slate' : 'Processed' }}
                                </span>
                            </div>
                            <div class="p-3 bg-card rounded-xl border border-border/80 shadow-2xs">
                                <span class="text-muted-foreground text-[11px] flex items-center gap-1">
                                    <CreditCard class="size-3 text-amber-500" /> Average Ticket Size
                                </span>
                                <p class="text-base font-bold font-mono text-primary mt-1">
                                    KES {{ activeShiftAvgTicket.toLocaleString() }}
                                </p>
                                <span class="text-[10px] text-muted-foreground font-mono">Per transaction</span>
                            </div>
                        </div>

                        <!-- Sales Receipts Table for Selected Shift -->
                        <Card class="border-border/80 shadow-2xs">
                            <CardHeader class="pb-3 border-b border-border/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                        <ShoppingBag class="size-4 text-primary" />
                                        <span>Transactions for:</span>
                                        <span class="text-foreground font-semibold normal-case">
                                            {{ userShiftOptions.find(o => o.id === selectedSalesShift)?.label || 'Current Shift' }}
                                        </span>
                                    </CardTitle>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-[10px] font-mono">
                                        {{ activeShiftSalesCount }} Receipts
                                    </Badge>
                                    <Badge
                                        :variant="selectedSalesShift === 'current' && !shiftEndedMap[viewUserTarget.id] ? 'success' : 'outline'"
                                        class="text-[10px]"
                                    >
                                        {{ selectedSalesShift === 'current' && !shiftEndedMap[viewUserTarget.id] ? 'Shift Active' : 'Shift Finalized' }}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <div class="p-0 overflow-x-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap">
                                    <thead class="bg-muted/40 text-muted-foreground border-b border-border/70">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Receipt Number</th>
                                            <th class="px-4 py-2.5 font-semibold">Date & Time</th>
                                            <th class="px-4 py-2.5 font-semibold">Customer</th>
                                            <th class="px-4 py-2.5 font-semibold">Payment Method</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Total (KES)</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Profit (KES)</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr v-for="(sale, idx) in activeUserSales" :key="idx" class="hover:bg-muted/30">
                                            <td class="px-4 py-3 font-mono font-bold text-foreground">{{ sale.receipt_number }}</td>
                                            <td class="px-4 py-3 text-muted-foreground font-mono">{{ sale.created_at }}</td>
                                            <td class="px-4 py-3 font-semibold text-foreground">{{ sale.customer_name || 'Walk-in Customer' }}</td>
                                            <td class="px-4 py-3">
                                                <Badge
                                                    :variant="sale.payment_method.includes('M-Pesa') ? 'success' : sale.payment_method.includes('Cash') ? 'warning' : 'info'"
                                                    class="text-[10px]"
                                                >
                                                    {{ sale.payment_method }}
                                                </Badge>
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600">
                                                KES {{ Number(sale.total).toLocaleString() }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono text-muted-foreground">
                                                KES {{ Number(sale.profit || 0).toLocaleString() }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <Badge variant="success" class="text-[10px]">{{ sale.payment_status }}</Badge>
                                            </td>
                                        </tr>
                                        <tr v-if="activeUserSales.length === 0">
                                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground text-xs">
                                                <div class="max-w-sm mx-auto space-y-2">
                                                    <div class="size-10 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center mx-auto">
                                                        <CheckCircle2 class="size-5" />
                                                    </div>
                                                    <p class="font-bold text-foreground text-sm">
                                                        {{ shiftEndedMap[viewUserTarget.id] ? "New Shift Session Initialized (Fresh Day)" : "No Transactions Recorded" }}
                                                    </p>
                                                    <p class="text-xs text-muted-foreground">
                                                        {{ shiftEndedMap[viewUserTarget.id] 
                                                            ? "Previous shift was ended & reconciled. This active shift ledger is ready for new daily checkout transactions."
                                                            : "No sales transactions recorded under this shift. Ready for checkout at the POS register."
                                                        }}
                                                    </p>
                                                    <div v-if="shiftEndedMap[viewUserTarget.id]" class="pt-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            class="h-7 text-xs gap-1 border-border"
                                                            @click="selectedSalesShift = 'ended-today'"
                                                        >
                                                            <History class="size-3" />
                                                            View Just-Ended Shift Ledger
                                                        </Button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>

                    <!-- ── TAB 4: LOGINS & SECURITY AUDIT ─────────────────── -->
                    <div v-if="viewUserTab === 'logins'" class="space-y-4">
                        <!-- Security Policies Status Banner -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200 flex items-center gap-2.5">
                                <ShieldCheck class="size-4 text-emerald-600 shrink-0" />
                                <div>
                                    <div class="font-bold">VPN Guard Active</div>
                                    <div class="text-[11px] opacity-80">Anonymized proxies blocked</div>
                                </div>
                            </div>
                            <div class="p-3 rounded-xl border border-primary/30 bg-primary/10 text-primary flex items-center gap-2.5">
                                <Wifi class="size-4 text-primary shrink-0" />
                                <div>
                                    <div class="font-bold">Store Wi-Fi Verified</div>
                                    <div class="text-[11px] opacity-80">Authorized router connection</div>
                                </div>
                            </div>
                            <div class="p-3 rounded-xl border border-sky-500/30 bg-sky-500/10 text-sky-900 dark:text-sky-200 flex items-center gap-2.5">
                                <Lock class="size-4 text-sky-600 shrink-0" />
                                <div>
                                    <div class="font-bold">Single POS Session</div>
                                    <div class="text-[11px] opacity-80">Concurrent logins restricted</div>
                                </div>
                            </div>
                        </div>

                        <!-- Login Sessions History Table -->
                        <Card class="border-border/80">
                            <CardHeader class="pb-3 border-b border-border/70">
                                <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                    <Monitor class="size-4 text-primary" />
                                    Active Sessions & Login Audit History
                                </CardTitle>
                            </CardHeader>
                            <div class="p-0 overflow-x-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap">
                                    <thead class="bg-muted/40 text-muted-foreground border-b border-border/70">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Last Active / Timestamp</th>
                                            <th class="px-4 py-2.5 font-semibold">IP Address</th>
                                            <th class="px-4 py-2.5 font-semibold">Device & Browser Client</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Security Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr v-for="(sess, idx) in viewUserTarget.sessions" :key="idx" class="hover:bg-muted/30">
                                            <td class="px-4 py-3 font-mono font-semibold text-foreground">{{ sess.last_active }}</td>
                                            <td class="px-4 py-3 font-mono text-muted-foreground">{{ sess.ip_address }}</td>
                                            <td class="px-4 py-3 text-foreground">{{ sess.user_agent || 'Standard POS Client' }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <Badge
                                                    :variant="sess.last_active.includes('Blocked') ? 'destructive' : sess.last_active.includes('Active') ? 'success' : 'outline'"
                                                    class="text-[10px]"
                                                >
                                                    {{ sess.last_active.includes('Blocked') ? 'Blocked Proxy' : 'Session Active' }}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr v-if="!viewUserTarget.sessions || viewUserTarget.sessions.length === 0">
                                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground text-xs">
                                                No recorded active web sessions.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <!-- Bound POS Hardware Terminals -->
                        <Card class="border-border/80">
                            <CardHeader class="pb-3 border-b border-border/70">
                                <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                    <Smartphone class="size-4 text-primary" />
                                    Bound Hardware POS Devices ({{ userBoundDevices.length }})
                                </CardTitle>
                            </CardHeader>
                            <div class="p-4 space-y-3">
                                <div
                                    v-for="dev in userBoundDevices"
                                    :key="dev.id"
                                    class="p-3 rounded-xl border border-border/80 bg-card flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 rounded-lg bg-primary/10 text-primary">
                                            <component :is="deviceIconMap[dev.deviceCategory] || Smartphone" class="size-5" />
                                        </div>
                                        <div>
                                            <div class="font-bold text-foreground">{{ dev.deviceName }}</div>
                                            <div class="text-[11px] text-muted-foreground font-mono">{{ dev.deviceFingerprint }}</div>
                                            <div class="text-[11px] text-muted-foreground">{{ dev.os }} • {{ dev.networkType }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Badge :variant="dev.bound ? 'success' : 'destructive'" class="text-[10px]">
                                            {{ dev.status }}
                                        </Badge>
                                        <Button
                                            v-if="dev.bound"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 text-xs border-rose-500/30 text-rose-600 hover:bg-rose-500/10"
                                            @click="handleRemoteLock(dev)"
                                        >
                                            Lock Device
                                        </Button>
                                    </div>
                                </div>

                                <div v-if="userBoundDevices.length === 0" class="text-center py-6 text-muted-foreground text-xs">
                                    No dedicated hardware POS terminal bound to {{ viewUserTarget.name }}.
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>

                <div class="p-3 sm:p-4 px-4 sm:px-5 border-t border-border/70 bg-muted/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 shrink-0 text-xs">
                    <div class="text-muted-foreground flex items-center gap-2 flex-wrap text-[11px]">
                        <span>Staff Record: <strong class="text-foreground font-mono">#STF-{{ viewUserTarget.id }}</strong></span>
                        <span>•</span>
                        <span>Branch: <strong class="text-foreground">{{ viewUserTarget.branch }}</strong></span>
                        <span>•</span>
                        <span>Role: <strong class="text-foreground">{{ viewUserTarget.role }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs gap-1.5 border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/10"
                            @click="() => {
                                viewStaffModal = false;
                                openEditUser(viewUserTarget);
                            }"
                        >
                            <Pencil class="size-3" />
                            Edit Staff Member
                        </Button>
                        <Button variant="default" size="sm" class="h-7 text-xs bg-primary px-3.5" @click="viewStaffModal = false">
                            Done / Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Universal Staff Directory Import Modal (Excel, Word, PDF, CSV) -->
        <StaffImportModal
            v-model:open="staffImportModalOpen"
            :branches="branches || allBranches"
        />

        <!-- Interactive Users & Permissions Guide Overlay -->
        <UsersGuideOverlay
            v-model:open="guideOverlayOpen"
            v-model:current-step="guideCurrentStep"
            :active-tab="activeTab"
            @action="handleGuideAction"
            @switch-tab="handleGuideTabSwitch"
        />
    </AppLayout>
</template>
