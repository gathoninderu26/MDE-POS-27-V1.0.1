<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    ArrowLeftRight,
    Ban,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Compass,
    Eye,
    History,
    Info,
    Key,
    Layers,
    Lock,
    Pencil,
    Plus,
    Radio,
    Router,
    Send,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Signal,
    Smartphone,
    Store,
    Users,
    Wifi,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

export type UsersTabType = 'staff' | 'roster' | 'matrix' | 'devices';

interface Props {
    open: boolean;
    currentStep?: number;
    activeTab?: UsersTabType;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    currentStep: 0,
    activeTab: 'staff',
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:currentStep', step: number): void;
    (e: 'action', actionType: string): void;
    (e: 'switch-tab', tab: UsersTabType): void;
}>();

export interface TourSection {
    id: string;
    targetId: string;
    fallbackTargetId?: string;
    stepNumber: number;
    title: string;
    tag: string;
    tab: UsersTabType;
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
    activeTabRequired?: UsersTabType;
}

export interface TabCategory {
    key: UsersTabType;
    label: string;
    icon: any;
    startStep: number;
    count: number;
    badgeColor: string;
}

const tabCategories: TabCategory[] = [
    {
        key: 'staff',
        label: 'Staff Directory',
        icon: Users,
        startStep: 0,
        count: 8,
        badgeColor: 'bg-primary/10 text-primary border-primary/30',
    },
    {
        key: 'roster',
        label: 'Duty Roster & Shifts',
        icon: Calendar,
        startStep: 8,
        count: 5,
        badgeColor: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
    },
    {
        key: 'matrix',
        label: 'RBAC Permissions Matrix',
        icon: ShieldCheck,
        startStep: 13,
        count: 4,
        badgeColor: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
    },
    {
        key: 'devices',
        label: 'Device & Network Security',
        icon: Wifi,
        startStep: 17,
        count: 6,
        badgeColor: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
    },
];

const sections: TourSection[] = [
    // =========================================================================
    // TAB 1: STAFF DIRECTORY & ACCOUNT MANAGEMENT (STEPS 0 TO 7)
    // =========================================================================
    {
        id: 'start',
        targetId: 'users-tour-guide-btn',
        stepNumber: 0,
        title: 'Users & Permissions Control Center',
        tag: 'MASTER ORIENTATION & WORKSPACES',
        tab: 'staff',
        shape: 'rect',
        rx: 10,
        icon: Compass,
        preferredPlacement: 'bottom-right',
        usedFor: 'Welcome to the Multi-Branch Users, Access Governance & POS Hardware Security Center! This interactive walkthrough covers every operational feature: staff account provisioning, store duty rosters & cashier station scheduling, granular RBAC permissions matrices, zero-trust network policies (VPN blocking & store Wi-Fi enforcement), and hardware POS terminal binding.',
        capabilities: [
            'Tenant Staff Provisioning & Role Assignment',
            'Cashier Shift Scheduling & POS Station Allocation',
            'Granular RBAC CRUD Capability Matrix',
            'Zero-Trust Commercial VPN & Hotspot Blocker',
            'Whitelisted Store Wi-Fi Router Enforcer',
            'Hardware Device Fingerprinting & Remote Session Kill',
        ],
        howToUse: [
            'Click "Start Tour" or press → on your keyboard to navigate through each security module.',
            'Use the tab categories at the top of this card to jump directly to any tab\'s guide.',
            'Press Esc at any time to exit the guide and return to user management.',
        ],
        proTip: 'The "Page Guide" button remains pinned in the header toolbar at all times so owners, store managers, and IT administrators can reference it whenever needed.',
        actionLabel: 'Start Walkthrough',
        actionType: 'start-walkthrough',
    },
    {
        id: 'btn-add-staff',
        targetId: 'users-tour-btn-add-staff',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 1,
        title: 'Provisioning New Staff Accounts',
        tag: 'USER ONBOARDING & CREDENTIALS',
        tab: 'staff',
        shape: 'rect',
        rx: 10,
        icon: Plus,
        preferredPlacement: 'left',
        activeTabRequired: 'staff',
        usedFor: 'Onboards new store personnel into the tenant organization. Generates secure credentials with automated default temporary passwords, assigns business operational roles (Cashier, Manager, Inventory Manager, Accountant), and locks staff to specific store branch locations.',
        capabilities: [
            'Instant Staff Member Account Creation',
            'Universal Document Import (Word, PDF, Excel, CSV)',
            'Intelligent Auto-Arranging Column Mapper',
            'Pre-Configured Default Credentials Delivery',
            'Role Pre-Assignment with RBAC Enforcement',
        ],
        howToUse: [
            'Click "Add Staff Member" to open the registration dialog, or click "Import Staff" to upload employee rosters from Excel, Word tables, or PDF documents.',
            'Provide full name, work email address, contact phone, and assigned primary branch.',
            'Select their business role — permissions immediately inherit based on the active matrix.',
        ],
        proTip: 'The auto-arranger engine automatically detects and maps column headers across Excel, Word, and PDF files.',
        actionLabel: 'Open Import Modal',
        actionType: 'open-import-staff',
    },
    {
        id: 'tabs-bar',
        targetId: 'users-tour-tabs',
        stepNumber: 2,
        title: '4 Operational Security Workspaces',
        tag: 'MODULAR WORKSPACE NAVIGATION',
        tab: 'staff',
        shape: 'rect',
        rx: 14,
        icon: Users,
        preferredPlacement: 'bottom-left',
        activeTabRequired: 'staff',
        usedFor: 'Seamlessly toggles between 4 specialized enterprise control panels: (1) Staff Directory, (2) Duty Roster & Cashier Shifts, (3) Role-Based Permissions Matrix, and (4) Hardware Device & Network Restrictions.',
        capabilities: [
            'Instant Sub-50ms Workspace Switching',
            'Dynamic Monitored Staff Counter Display',
            'Owner Governance Badges & Lock Indicators',
            'Live Hardware VPN Guard Status Flag',
        ],
        howToUse: [
            'Click any tab to switch operational focus without losing your unsaved work.',
            'Review the live badge indicators to monitor active security guards.',
        ],
        proTip: 'The "Duty Roster" and "Permissions Matrix" workspaces contain Owner-only governance locks to ensure that staff cannot tamper with their own access privileges.',
    },
    {
        id: 'staff-search',
        targetId: 'users-tour-search',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 3,
        title: 'Universal Multi-Attribute Staff Search',
        tag: 'STAFF REGISTRY SEARCH',
        tab: 'staff',
        shape: 'rect',
        rx: 10,
        icon: Users,
        preferredPlacement: 'left',
        activeTabRequired: 'staff',
        usedFor: 'High-speed fuzzy search filter: instantly filters staff accounts across names, work email addresses, phone numbers, assigned roles, and physical branch warehouse locations in real time.',
        capabilities: [
            'Multi-Attribute Client-Side Search Engine',
            'Instant Filter by Role (e.g., "Cashier" or "Manager")',
            'Branch Location Filtering across Regional Stores',
            'Sub-10ms Filter Latency with Zero Server Overload',
        ],
        howToUse: [
            'Type any staff name, email, or role into the search box to isolate matching accounts.',
            'Clear the box to restore the complete employee directory.',
        ],
        proTip: 'Typing a branch name like "Westlands" instantly isolates all staff assigned to that specific store location.',
    },
    {
        id: 'staff-identity',
        targetId: 'users-tour-col-staff',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 4,
        title: 'Staff Identity, Role & Branch Allocation',
        tag: 'PERSONNEL IDENTIFICATION & ROLES',
        tab: 'staff',
        shape: 'rect',
        rx: 12,
        icon: Users,
        preferredPlacement: 'right',
        activeTabRequired: 'staff',
        usedFor: 'Displays official staff nomenclature, unique employee ID tag (#STF-xxx), work email, and role badge (Owner, Manager, Cashier, Inventory Manager, Accountant) tied to their primary store facility.',
        capabilities: [
            'High-Visibility Personnel Nomenclature',
            'Distinctive Role Badges (Owner, Manager, Cashier)',
            'Strict Branch Facility Association',
            'Contact Details for Immediate Communication',
        ],
        howToUse: [
            'Verify staff roles to confirm appropriate access levels across your enterprise.',
            'Confirm that personnel are assigned to their correct operational store branch.',
        ],
        proTip: 'Owners have unrestricted system-wide authority across all branches; Cashiers and Managers are confined to their designated store locations.',
    },
    {
        id: 'staff-status',
        targetId: 'users-tour-col-status',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 5,
        title: 'Account Status & Recent Login Activity',
        tag: 'ACCOUNT HEALTH & SESSION TELEMETRY',
        tab: 'staff',
        shape: 'rect',
        rx: 12,
        icon: Activity,
        preferredPlacement: 'left',
        activeTabRequired: 'staff',
        usedFor: 'Real-time account health and authentication activity monitor. Shows active vs suspended account status and the exact timestamp of their latest sign-in or active POS session.',
        capabilities: [
            'Real-Time Active vs Suspended State Flags',
            'Pulsing Green Activity Indicators for Online Staff',
            'Precise Last Login Timestamps',
            'Immediate Dormant Account Detection',
        ],
        howToUse: [
            'Check the green pulse indicator to identify staff members currently active on terminals.',
            'Review last login timestamps to detect inactive or abandoned accounts.',
        ],
        proTip: 'Suspended accounts are immediately blocked at authentication and cannot process sales at checkout registers.',
    },
    {
        id: 'staff-view',
        targetId: 'users-tour-btn-view',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 6,
        title: 'Staff 360° Profile & Sales Audit Slide-Over',
        tag: 'COMPREHENSIVE AUDIT SLIDE-OVER',
        tab: 'staff',
        shape: 'rect',
        rx: 10,
        icon: Eye,
        preferredPlacement: 'left',
        activeTabRequired: 'staff',
        usedFor: 'Opens an in-depth audit slide-over dialog showcasing the complete 360° employee dossier: personal profile details, duty shift schedule, sales performance ledger with receipt breakdown, and all bound hardware POS devices.',
        capabilities: [
            'Complete Staff Personal Dossier & Contact Info',
            'Live Shift Sales Total, Margin & Average Ticket Telemetry',
            'Receipt Breakdown for Selected Shift Sessions',
            '1-Click "End Shift & Seal Ledger" Trigger',
            'Hardware Terminal Binding & Session Inspection',
        ],
        howToUse: [
            'Click "View" on any staff row to open the complete employee dashboard.',
            'Inspect their shift sales performance, transaction receipts, and profit contribution.',
            'Click "End Shift" when cashier balancing is complete to archive the daily sales session.',
        ],
        proTip: 'Store managers use this slide-over during evening register reconciliations to compare physical drawer cash with the cashier\'s sales total.',
        actionLabel: 'Open Staff Profile Modal',
        actionType: 'view-first-staff',
    },
    {
        id: 'staff-edit',
        targetId: 'users-tour-btn-edit',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 7,
        title: 'Editing Personnel & Password Resets',
        tag: 'CREDENTIAL MANAGEMENT & MODIFICATIONS',
        tab: 'staff',
        shape: 'rect',
        rx: 10,
        icon: Pencil,
        preferredPlacement: 'left',
        activeTabRequired: 'staff',
        usedFor: 'Quick maintenance dialog for updating staff names, contact information, re-assigning branch store locations, promoting or demoting roles, and executing direct administrator password resets.',
        capabilities: [
            '1-Click Administrator Password Reset Engine',
            'Role Re-Assignment (Promote / Demote)',
            'Inter-Branch Personnel Transfer',
            'Instant Account Suspension / Activation Toggle',
        ],
        howToUse: [
            'Click "Edit" on any employee row to open the modification modal.',
            'To reset a forgotten password, enter the new secure password and save.',
            'Switch the status to "Inactive" to temporarily revoke access without deleting audit history.',
        ],
        proTip: 'Never delete staff members who have processed transactions; mark them as "Inactive" instead to preserve accounting and sales receipt audit trails.',
        actionLabel: 'Open Edit Staff Modal',
        actionType: 'edit-first-staff',
    },

    // =========================================================================
    // TAB 2: DUTY ROSTER & CASHIER SHIFTS (STEPS 8 TO 12)
    // =========================================================================
    {
        id: 'roster-bar',
        targetId: 'users-tour-roster-bar',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 8,
        title: 'Store Duty Roster & Cashier Scheduling Hub',
        tag: 'ROSTER SCHEDULING & ATTENDANCE',
        tab: 'roster',
        shape: 'rect',
        rx: 14,
        icon: Clock,
        preferredPlacement: 'bottom-right',
        activeTabRequired: 'roster',
        usedFor: 'Central labor coordination terminal: allows store owners and floor managers to schedule cashier registers, assign staff to specific store stations, balance shift coverage during peak trading hours, and monitor live clock-in attendance.',
        capabilities: [
            'Multi-Shift Staff Allocation Engine',
            'Dedicated POS Register & Station Binding',
            'Shift Timing Presets (Morning, Evening, Full Day, Night)',
            'Live Shift Attendance & Clock-In Tracking',
        ],
        howToUse: [
            'Review scheduled staff across morning, evening, and weekend shifts.',
            'Ensure every open POS terminal has an assigned, clocked-in cashier.',
            'Click "Schedule Shift" to create new duty assignments.',
        ],
        proTip: 'Ensuring balanced coverage during lunch hours (12:00-14:00) and evening rush (17:00-20:00) prevents long customer checkout queues.',
        actionLabel: 'Schedule New Shift',
        actionType: 'open-schedule-shift',
    },
    {
        id: 'roster-kpis',
        targetId: 'users-tour-roster-kpis',
        fallbackTargetId: 'users-tour-roster-bar',
        stepNumber: 9,
        title: 'Shift Coverage & Peak Hours Telemetry',
        tag: 'LABOR COVERAGE TELEMETRY',
        tab: 'roster',
        shape: 'rect',
        rx: 12,
        icon: Activity,
        preferredPlacement: 'bottom',
        activeTabRequired: 'roster',
        usedFor: 'High-level operational shift dashboard: displays real-time counts for Morning Shift personnel (06:30-14:30), Evening Shift scheduled (14:00-22:00), Active POS Stations in use, and Staff on Leave / Off-Duty.',
        capabilities: [
            'Live Shift Staffing Count Displays',
            'Active Cashier Register Utilization Counter',
            'Staff On-Leave & Off-Duty Tracking',
            'Understaffing Warning Visibility',
        ],
        howToUse: [
            'Glance at the shift cards each morning to confirm adequate cashier presence on the sales floor.',
            'Verify that all primary checkout registers (#01, #02, Express) have assigned personnel.',
        ],
        proTip: 'If active POS stations outnumber scheduled cashiers, immediately reassign floor staff to prevent bottlenecks at checkout.',
    },
    {
        id: 'roster-table',
        targetId: 'users-tour-roster-table',
        fallbackTargetId: 'users-tour-roster-bar',
        stepNumber: 10,
        title: 'Cashier Register & Station Assignment Table',
        tag: 'LIVE ROSTER & STATION ALLOCATION',
        tab: 'roster',
        shape: 'rect',
        rx: 14,
        icon: Calendar,
        preferredPlacement: 'top',
        activeTabRequired: 'roster',
        usedFor: 'Master shift schedule table showing staff name, assigned role, shift timing (with hours), exact POS station (e.g., Register #01 Front, Express Checkout, Warehouse Dock), branch store, and real-time duty status.',
        capabilities: [
            'Granular Station & Register Mapping',
            'Visual Shift Badges (Morning, Evening, Full Day)',
            'Duty Status States (Scheduled, Clocked In, Active, On Leave)',
            '1-Click "Clock In" Authorization Button',
        ],
        howToUse: [
            'Verify which register station each cashier is currently operating.',
            'Cashiers scheduled for today can be manually clocked in or clock themselves in from their POS register.',
        ],
        proTip: 'Station mapping prevents cashiers from hopping between registers, guaranteeing 100% drawer accountability at shift end.',
    },
    {
        id: 'roster-schedule',
        targetId: 'users-tour-btn-schedule',
        fallbackTargetId: 'users-tour-roster-bar',
        stepNumber: 11,
        title: 'Scheduling Morning, Evening & Full-Day Shifts',
        tag: 'SHIFT CREATION ENGINE',
        tab: 'roster',
        shape: 'rect',
        rx: 10,
        icon: Plus,
        preferredPlacement: 'left',
        activeTabRequired: 'roster',
        usedFor: 'Creates new duty shift entries: select any employee from your staff directory, choose the target branch, date, shift timing preset (Morning, Evening, Full Day, Night, or Off Day), and designate their exact checkout register or floor station.',
        capabilities: [
            '5 Standardized Shift Timing Presets',
            'Dedicated Terminal/Register Allocation',
            'Cross-Branch Shift Scheduling',
            'Instant Calendar Addition',
        ],
        howToUse: [
            'Click "Schedule Shift" in the toolbar.',
            'Select the employee and specify date and shift type.',
            'Specify the station (e.g. "Register #01 Front") and click Create.',
        ],
        proTip: 'Schedule shifts a week in advance so staff receive their upcoming rosters with ample notice.',
        actionLabel: 'Open Shift Modal',
        actionType: 'open-schedule-shift',
    },
    {
        id: 'roster-publish',
        targetId: 'users-tour-btn-publish',
        fallbackTargetId: 'users-tour-roster-bar',
        stepNumber: 12,
        title: '1-Click Multi-Channel Roster Broadcast',
        tag: 'AUTOMATED DISPATCH (SMS / EMAIL)',
        tab: 'roster',
        shape: 'rect',
        rx: 10,
        icon: Send,
        preferredPlacement: 'bottom-right',
        activeTabRequired: 'roster',
        usedFor: 'Dispatches the published shift calendar directly to scheduled staff via automated SMS text notifications and email alerts, ensuring every employee knows their assigned shift times and register stations.',
        capabilities: [
            '1-Click Store-Wide Schedule Broadcast',
            'Automated SMS & Email Notification Dispatch',
            'Elimination of Shift Confusion and No-Shows',
            'Audit Trail of Published Schedule Revisions',
        ],
        howToUse: [
            'Once you finish scheduling shifts for the upcoming week, click "Publish & Broadcast Roster".',
            'The system sends individual shift digests to all scheduled staff members.',
        ],
        proTip: 'Publishing weekly rosters on Fridays reduces weekend absenteeism and guarantees smooth Monday morning handovers.',
        actionLabel: 'Publish & Broadcast',
        actionType: 'publish-roster',
    },

    // =========================================================================
    // TAB 3: RBAC PERMISSIONS MATRIX (STEPS 13 TO 16)
    // =========================================================================
    {
        id: 'matrix-roles',
        targetId: 'users-tour-matrix-roles',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 13,
        title: 'Granular Role Selection & Hierarchy',
        tag: 'ROLE-BASED ACCESS CONTROL (RBAC)',
        tab: 'matrix',
        shape: 'rect',
        rx: 14,
        icon: ShieldCheck,
        preferredPlacement: 'bottom',
        activeTabRequired: 'matrix',
        usedFor: 'Selects the business role to inspect or configure: Owner (full system governance), Manager (floor oversight & inventory management), Cashier (point of sale checkout only), Inventory Manager (stock audits & write-offs), and Accountant (financial reports & ledger).',
        capabilities: [
            'Role Selector Navigation Pills',
            'Role-Specific Capability Isolation',
            'System-Wide Preset Inheritance',
            'Zero-Latency Dynamic Matrix Rendering',
        ],
        howToUse: [
            'Click any role pill (e.g. "Cashier" or "Manager") to view their active permission settings across all system modules.',
            'Compare permissions between roles to ensure the principle of least privilege is maintained.',
        ],
        proTip: 'Cashiers should only have "Create" access on POS and Sales; all other administrative modules should remain disabled.',
        actionLabel: 'Inspect Cashier Role',
        actionType: 'select-cashier-role',
    },
    {
        id: 'matrix-grid',
        targetId: 'users-tour-matrix-grid',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 14,
        title: 'Modular CRUD Access Matrix',
        tag: 'CRUD PERMISSION MATRIX',
        tab: 'matrix',
        shape: 'rect',
        rx: 14,
        icon: Layers,
        preferredPlacement: 'top',
        activeTabRequired: 'matrix',
        usedFor: 'Granular capability control grid. Configures View, Create, Edit, and Delete privileges individually across all 12 system modules: POS, Sales, Products, Inventory, Purchases, Customers, Suppliers, Expenses, Reports, Users, Branches, and Settings.',
        capabilities: [
            '12 Core Business Module Security Scopes',
            'Granular 4-Way Capability Flags (View, Create, Edit, Delete)',
            'Instant Visual State Indicators (Green Check / Gray Cross)',
            'Strict Tenant Data Segregation Guarantee',
        ],
        howToUse: [
            'Inspect the rows to verify what actions this role is permitted to perform in each module.',
            'Notice that Cashiers have View & Create for POS, but no Delete or Edit permissions.',
        ],
        proTip: 'Restricting "Delete" permissions on Sales and Inventory is the #1 industry deterrent against frontline shrinkage and unauthorized transaction voids.',
    },
    {
        id: 'matrix-restriction',
        targetId: 'users-tour-matrix-restriction',
        fallbackTargetId: 'users-tour-matrix-roles',
        stepNumber: 15,
        title: 'Tamper-Proof Self-Elevation Shield',
        tag: 'OWNER GOVERNANCE & SECURITY SHIELD',
        tab: 'matrix',
        shape: 'rect',
        rx: 12,
        icon: ShieldAlert,
        preferredPlacement: 'bottom',
        activeTabRequired: 'matrix',
        usedFor: 'Enforces strict Owner governance. Non-owner staff (Managers, Cashiers, Accountants) can inspect their granted capabilities, but are strictly blocked from editing or elevating their own permissions. Only verified Owner accounts can toggle matrix rules.',
        capabilities: [
            'Anti-Privilege Escalation Guard',
            'Tamper-Proof Read-Only Enforcement for Non-Owners',
            'Clear Visual Warning Banner & Explanation',
            'Role Impersonation Switcher Support for Testing',
        ],
        howToUse: [
            'When logged in as a non-owner, all capability toggles are locked in read-only mode.',
            'To test permission modifications, switch your simulated role to "Owner" in the top navigation bar.',
        ],
        proTip: 'This tamper-proof architecture prevents rogue staff from elevating their privileges to delete audit records or modify sales receipts.',
    },
    {
        id: 'matrix-toggles',
        targetId: 'users-tour-matrix-toggles',
        fallbackTargetId: 'users-tour-matrix-grid',
        stepNumber: 16,
        title: 'Real-Time Capability Toggle Engine',
        tag: 'CAPABILITY TOGGLE ENGINE',
        tab: 'matrix',
        shape: 'rect',
        rx: 10,
        icon: Key,
        preferredPlacement: 'left',
        activeTabRequired: 'matrix',
        usedFor: 'Interactive permission toggles: when authorized as an Owner, clicking any cell instantly toggles that role\'s capability between Granted (green checkmark) and Revoked (gray cross mark), updating authorization rules across all active sessions.',
        capabilities: [
            '1-Click Instant Permission Toggling',
            'Real-Time In-Memory & Database Synchronization',
            'Visual Click Feedback & Animation',
            'Zero Page Reloads Required',
        ],
        howToUse: [
            'Click any green check or gray cross button to grant or revoke that capability.',
            'Changes take effect immediately across all users assigned to that role.',
        ],
        proTip: 'Carefully consider granting "Edit" or "Delete" capabilities on Purchases and Expenses to ensure strict financial separation of duties.',
    },

    // =========================================================================
    // TAB 4: DEVICE & NETWORK SECURITY (STEPS 17 TO 22)
    // =========================================================================
    {
        id: 'devices-policies',
        targetId: 'users-tour-devices-policies',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 17,
        title: '6 Zero-Trust POS Network & Hardware Policies',
        tag: 'ZERO-TRUST SECURITY POLICIES',
        tab: 'devices',
        shape: 'rect',
        rx: 14,
        icon: Wifi,
        preferredPlacement: 'bottom',
        activeTabRequired: 'devices',
        usedFor: 'Enterprise security policy suite enforcing zero-trust perimeter defense: (1) Commercial VPN & Proxy Blocker, (2) Enforce Store Wi-Fi, (3) Block Mobile Hotspots, (4) Role-Gated Cellular SIM Policy, (5) Hardware Device Binding, and (6) Single Active Terminal Session.',
        capabilities: [
            '6 Configurable Zero-Trust Defense Toggles',
            'Real-Time Policy Enforcement on Every API Request',
            'Active / Disabled Visual Indicator Badges',
            'Owner-Only Policy Modification Controls',
        ],
        howToUse: [
            'Review the active policies protecting your business from unauthorized off-site operations.',
            'Owners can toggle policies on or off based on operational requirements.',
        ],
        proTip: 'Keeping "VPN Guard" and "Enforce Store Wi-Fi" enabled prevents cashiers from logging in from home or spoofing their physical location.',
    },
    {
        id: 'policy-vpn',
        targetId: 'users-tour-policy-vpn',
        fallbackTargetId: 'users-tour-devices-policies',
        stepNumber: 18,
        title: 'Commercial VPN & Proxy Auto-Blocker',
        tag: 'VPN & PROXY GUARD',
        tab: 'devices',
        shape: 'rect',
        rx: 12,
        icon: ShieldAlert,
        preferredPlacement: 'right',
        activeTabRequired: 'devices',
        usedFor: 'Automatically detects and terminates connection attempts originating from commercial VPN providers (NordVPN, ExpressVPN, Surfshark, WireGuard tunnels) or anonymous datacenter proxies. Flags the session as "Blocked (VPN Detected)".',
        capabilities: [
            'Automated Exit Node & Datacenter IP Detection',
            'Immediate Session Termination upon VPN Detection',
            'High-Visibility Red Security Alert Tagging',
            'Protection Against Location Masking & Remote Tampering',
        ],
        howToUse: [
            'Keep this policy active to ensure staff are physically on premise when ringing up sales.',
            'Any staff attempting to use VPN tunnels will be blocked and logged in the devices table below.',
        ],
        proTip: 'VPN blocking ensures all POS sales transactions correlate with verifiable physical ISP connections in your store.',
    },
    {
        id: 'policy-wifi',
        targetId: 'users-tour-policy-wifi',
        fallbackTargetId: 'users-tour-devices-policies',
        stepNumber: 19,
        title: 'Store Wi-Fi Whitelist Enforcement',
        tag: 'STORE NETWORK ENFORCER',
        tab: 'devices',
        shape: 'rect',
        rx: 12,
        icon: Wifi,
        preferredPlacement: 'left',
        activeTabRequired: 'devices',
        usedFor: 'Restricts cashier logins strictly to verified physical store Wi-Fi access points. Prevents cashiers and registers from connecting through unauthorized mobile hotspots or neighbor networks.',
        capabilities: [
            'Hardware SSID & BSSID Verification',
            'Local LAN Subnet Confinement (e.g. 192.168.10.0/24)',
            'Prevention of Off-Site Cellular Tethering',
            'Branch-Specific Router Mapping',
        ],
        howToUse: [
            'Cashiers must connect their POS terminal to the store router specified in your whitelist.',
            'Connections from unrecognized Wi-Fi networks will be rejected at login.',
        ],
        proTip: 'Pairing this policy with hidden router SSIDs creates a highly secure, isolated POS network bubble.',
    },
    {
        id: 'wifi-whitelist',
        targetId: 'users-tour-wifi-whitelist',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 20,
        title: 'Verified Store Wi-Fi Routers & Access Points',
        tag: 'ROUTER WHITELIST REGISTRY',
        tab: 'devices',
        shape: 'rect',
        rx: 14,
        icon: Router,
        preferredPlacement: 'top',
        activeTabRequired: 'devices',
        usedFor: 'Maintains the authorized hardware router whitelist: displays verified network SSID, hardware MAC BSSID, LAN subnet, assigned branch store, and verification status badge. Owners can whitelist new store routers with 1 click.',
        capabilities: [
            'Multi-Branch Router Whitelisting Registry',
            'Hardware MAC BSSID & Subnet Binding',
            '1-Click "Whitelist Store Wi-Fi" Creation Dialog',
            'Instant Verification Tagging',
        ],
        howToUse: [
            'Review all authorized store access points across your branch network.',
            'Click "Whitelist Store Wi-Fi" when setting up a new retail branch or installing a new store router.',
        ],
        proTip: 'Entering the router BSSID prevents attackers from spoofing your store SSID with a rogue mobile hotspot.',
        actionLabel: 'Whitelist Store Wi-Fi',
        actionType: 'open-add-wifi',
    },
    {
        id: 'devices-table',
        targetId: 'users-tour-devices-table',
        fallbackTargetId: 'users-tour-tabs',
        stepNumber: 21,
        title: 'Real-Time Hardware Fingerprinting & Sessions',
        tag: 'LIVE DEVICE MONITORING & FINGERPRINTS',
        tab: 'devices',
        shape: 'rect',
        rx: 14,
        icon: Smartphone,
        preferredPlacement: 'top',
        activeTabRequired: 'devices',
        usedFor: 'Live telemetry monitor inspecting every device accessing your business: shows staff member name, hardware category (POS Terminal, Desktop, Tablet, Smartphone), operating system, unique hardware fingerprint (Serial & MAC), connection type, IP address, and security lock state.',
        capabilities: [
            'Hardware Serial & MAC Fingerprint Tracking',
            'Operating System & Browser Engine Inspection',
            'Live Connection Type Classification (Store Wi-Fi, 4G/5G, VPN)',
            'Real-Time Status Badges (Authorized, Blocked, Revoked)',
        ],
        howToUse: [
            'Inspect active devices to confirm that cashiers are operating company-approved hardware.',
            'Look out for "Blocked (VPN Detected)" badges to catch security violations.',
        ],
        proTip: 'Hardware fingerprinting binds smart POS terminals (like Sunmi or Pax devices) to specific cashier accounts, preventing unauthorized device swapping.',
    },
    {
        id: 'device-lock',
        targetId: 'users-tour-btn-remote-lock',
        fallbackTargetId: 'users-tour-devices-table',
        stepNumber: 22,
        title: 'Instant Remote Session Kill & Hardware Binding',
        tag: 'REMOTE LOCK & BINDING ACTIONS',
        tab: 'devices',
        shape: 'rect',
        rx: 10,
        icon: Ban,
        preferredPlacement: 'left',
        activeTabRequired: 'devices',
        usedFor: 'Executive administrative action triggers: "Lock Device" immediately kills the active session on that terminal and freezes login access; "Authorize" whitelists and binds the hardware; "Revoke" detaches hardware registration.',
        capabilities: [
            'Sub-Second Remote Session Termination',
            'Emergency Terminal Lockout for Stolen or Compromised Devices',
            'Hardware Binding Authorization Workflow',
            '1-Click Access Revocation',
        ],
        howToUse: [
            'If a POS tablet is misplaced or a cashier account exhibits suspicious activity, click "Lock Device" immediately.',
            'For new store registers, click "Authorize" to bind the device to that staff member.',
        ],
        proTip: 'Remote locking terminates the session on the terminal in real time, preventing any unauthorized sales or refunds from being processed.',
        actionLabel: 'View Connected Devices',
        actionType: 'view-devices',
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
    }
);

const currentSection = computed(() => {
    return sections[internalStep.value] || sections[0];
});

const currentTabCategory = computed(() => {
    const sec = currentSection.value;
    const tabKey = sec.activeTabRequired || sec.tab || 'staff';
    return tabCategories.find((t) => t.key === tabKey) || tabCategories[0];
});

const currentStepInCat = computed(() => {
    const cat = currentTabCategory.value;
    return Math.max(1, internalStep.value - cat.startStep + 1);
});

const jumpToTabCategory = (tabKey: UsersTabType) => {
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
    { immediate: true }
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
            <div
                v-if="open"
                class="fixed inset-0 z-50 pointer-events-none select-none font-sans"
            >
                <!-- SVG Dimming Backdrop with Cutout Hole -->
                <svg
                    class="absolute inset-0 w-full h-full pointer-events-none transition-all duration-300"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <!-- Cutout Mask Hole -->
                        <mask id="users-guide-mask">
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
                        mask="url(#users-guide-mask)"
                        class="pointer-events-auto"
                        @click="nextStep"
                    />
                </svg>

                <!-- Clickable Spotlight Hole -->
                <div
                    v-if="targetRect"
                    class="absolute pointer-events-auto cursor-pointer z-40"
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
                    class="absolute pointer-events-none transition-all duration-300 ease-out border-2 border-primary shadow-[0_0_35px_rgba(16,185,129,0.55)] ring-4 ring-primary/30"
                    :style="{
                        top: `${targetRect.top - 8}px`,
                        left: `${targetRect.left - 8}px`,
                        width: `${targetRect.width + 16}px`,
                        height: `${targetRect.height + 16}px`,
                        borderRadius: `${currentSection.rx}px`,
                    }"
                ></div>

                <!-- Guided Callout Text Bubble -->
                <div
                    ref="cardRef"
                    class="absolute z-50 pointer-events-auto transition-all duration-300 ease-out"
                    :style="cardStyle"
                    @click.stop
                >
                    <!-- Speech Bubble Tail: Top (points UP to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'top'"
                        class="absolute -top-3.5 z-30 pointer-events-none"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,15 L 12,0 L 24,15"
                                class="fill-muted/95 stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="1" y="14" width="22" height="3" class="fill-muted/95" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Bottom (points DOWN to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'bottom'"
                        class="absolute -bottom-3.5 z-30 pointer-events-none"
                        :style="{ left: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="24" height="15" viewBox="0 0 24 15" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,0 L 12,15 L 24,0"
                                class="fill-muted/80 stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="1" y="-2" width="22" height="3" class="fill-muted/80" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Left (points LEFT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'left'"
                        class="absolute -left-3.5 z-30 pointer-events-none"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 15,0 L 0,12 L 15,24"
                                class="fill-card stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="14" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Speech Bubble Tail: Right (points RIGHT to target) -->
                    <div
                        v-if="speechBubbleTail && speechBubbleTail.placement === 'right'"
                        class="absolute -right-3.5 z-30 pointer-events-none"
                        :style="{ top: `${speechBubbleTail.offset - 12}px` }"
                    >
                        <svg width="15" height="24" viewBox="0 0 15 24" class="overflow-visible drop-shadow-sm">
                            <path
                                d="M 0,0 L 15,12 L 0,24"
                                class="fill-card stroke-border"
                                stroke-width="2"
                                stroke-linejoin="round"
                            />
                            <rect x="-2" y="1" width="3" height="22" class="fill-card" />
                        </svg>
                    </div>

                    <!-- Text Bubble Container Card -->
                    <div class="rounded-2xl border-2 border-border bg-card shadow-2xl overflow-hidden text-card-foreground flex flex-col max-h-[calc(100vh-80px)] ring-1 ring-black/10 dark:ring-white/15">
                        <!-- Card Header -->
                        <div class="px-4 py-3 bg-muted/80 border-b border-border flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="size-8 rounded-lg bg-primary/15 text-primary border border-primary/25 flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
                                    <component :is="currentSection.icon" class="size-4.5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Badge
                                            variant="outline"
                                            class="text-[9.5px] px-2 py-0.5 h-4.5 border-primary/40 bg-primary/10 text-primary uppercase font-mono font-bold shrink-0"
                                        >
                                            {{ currentSection.id === 'start' ? 'ORIENTATION' : `Part ${currentSection.stepNumber}/${sections.length - 1}` }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            :class="['text-[9px] px-1.5 py-0.5 font-mono uppercase font-bold shrink-0', currentTabCategory.badgeColor]"
                                        >
                                            {{ currentTabCategory.label }}
                                        </Badge>
                                        <span class="text-[10px] sm:text-[11px] font-bold text-muted-foreground uppercase tracking-wider truncate">
                                            {{ currentSection.tag }}
                                        </span>
                                    </div>
                                    <h3 class="text-sm sm:text-[15px] font-bold text-foreground leading-snug mt-0.5">
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
                                class="h-7 px-2 text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 shrink-0 gap-1 rounded-lg ml-2 cursor-pointer"
                                title="Exit Tour (Esc)"
                            >
                                <span>Exit</span>
                                <X class="size-3.5" />
                            </Button>
                        </div>

                        <!-- Tab Category Quick-Jump Bar -->
                        <div class="px-3 py-1.5 bg-muted/40 border-b border-border flex items-center gap-1.5 overflow-x-auto scrollbar-none shrink-0">
                            <span class="text-[10px] uppercase tracking-wider font-bold text-muted-foreground shrink-0 mr-0.5">
                                Workspaces:
                            </span>
                            <button
                                v-for="t in tabCategories"
                                :key="t.key"
                                type="button"
                                @click="jumpToTabCategory(t.key)"
                                :class="[
                                    'px-2 py-1 rounded-md text-[11px] font-semibold transition-all shrink-0 flex items-center gap-1.5 cursor-pointer border',
                                    currentTabCategory.key === t.key
                                        ? 'bg-primary text-primary-foreground border-primary shadow-xs font-bold'
                                        : 'bg-card text-muted-foreground hover:text-foreground hover:bg-muted/80 border-border/80'
                                ]"
                            >
                                <component :is="t.icon" class="size-3.5" />
                                <span>{{ t.label }}</span>
                                <span
                                    :class="[
                                        'text-[9px] px-1 py-0.2 rounded-full font-mono',
                                        currentTabCategory.key === t.key ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-muted text-muted-foreground'
                                    ]"
                                >
                                    {{ t.count }}
                                </span>
                            </button>
                        </div>

                        <!-- Card Body -->
                        <div class="p-3.5 space-y-3 overflow-y-auto max-h-[min(420px,calc(100vh-230px))] text-xs">
                            <!-- Purpose & Function -->
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-1.5 text-[11px] sm:text-xs font-bold uppercase tracking-wider text-primary">
                                    <Info class="size-3.5" />
                                    <span>Purpose & Function:</span>
                                </div>
                                <p class="text-xs sm:text-[13px] text-foreground font-normal leading-relaxed">
                                    {{ currentSection.usedFor }}
                                </p>

                                <!-- Capabilities -->
                                <div v-if="currentSection.capabilities && currentSection.capabilities.length > 0" class="flex flex-wrap gap-1 pt-0.5">
                                    <span
                                        v-for="cap in currentSection.capabilities"
                                        :key="cap"
                                        class="inline-flex items-center gap-1.5 text-[10px] px-2 py-0.5 rounded-md font-semibold bg-muted/80 border border-border/80 text-foreground shadow-2xs"
                                    >
                                        <span class="size-1.5 rounded-full bg-primary shrink-0"></span>
                                        <span>{{ cap }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Guidance Steps -->
                            <div class="p-2.5 rounded-xl bg-muted/40 border border-border/80 space-y-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-foreground/70">How to use:</span>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, idx) in currentSection.howToUse"
                                        :key="idx"
                                        class="flex items-start gap-2 text-[11.5px] text-foreground"
                                    >
                                        <div class="size-4.5 rounded-full bg-primary text-primary-foreground font-mono font-bold text-[9px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                                            {{ idx + 1 }}
                                        </div>
                                        <span class="leading-relaxed font-normal text-foreground/95 flex-1 break-words">
                                            {{ step }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pro Tip -->
                            <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-950 dark:text-amber-100 space-y-1">
                                <span class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-300 text-[10px] uppercase tracking-wide">
                                    <Zap class="size-3 text-amber-600 dark:text-amber-400 fill-amber-500/30 shrink-0" />
                                    <span>Tip</span>
                                </span>
                                <p class="text-[11px] leading-relaxed font-medium">
                                    {{ currentSection.proTip }}
                                </p>
                            </div>

                            <!-- Interactive Action Shortcut -->
                            <div v-if="currentSection.actionLabel" class="pt-1">
                                <button
                                    type="button"
                                    @click="handleAction"
                                    class="w-full py-2.5 px-4 rounded-xl bg-primary/15 hover:bg-primary/25 text-primary text-xs sm:text-[13px] font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer border border-primary/30 shadow-2xs active:scale-[0.99]"
                                >
                                    <span>{{ currentSection.actionLabel }}</span>
                                    <ArrowRight class="size-4 shrink-0" />
                                </button>
                            </div>
                        </div>

                        <!-- Footer Navigation -->
                        <div class="px-4 py-3 bg-muted/70 border-t border-border flex items-center justify-between shrink-0">
                            <!-- Prev Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs px-3 gap-1 text-muted-foreground hover:text-foreground font-semibold cursor-pointer"
                                :disabled="internalStep === 0"
                                @click="prevStep"
                            >
                                <ArrowLeft class="size-3.5" />
                                <span>Prev</span>
                            </Button>

                            <!-- Current Step Badge / Counter -->
                            <div class="flex flex-col items-center justify-center gap-0.5">
                                <span class="text-[11px] font-mono font-bold text-foreground">
                                    {{ currentTabCategory.label }}: {{ currentStepInCat }} of {{ currentTabCategory.count }}
                                </span>
                                <span class="text-[9.5px] font-mono text-muted-foreground">
                                    Overall Step {{ internalStep + 1 }} / {{ sections.length }}
                                </span>
                            </div>

                            <!-- Next / Done Button -->
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs px-3.5 gap-1.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold shadow-xs cursor-pointer"
                                @click="nextStep"
                            >
                                <span>{{ currentSection.id === 'start' ? 'Start Tour' : internalStep === sections.length - 1 ? 'Done' : 'Next' }}</span>
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
