<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import StatCard from '@/components/ui/StatCard.vue';
import { useBranch } from '@/composables/useBranch';
import { useFeatures } from '@/composables/useFeatures';
import { formatCurrency } from '@/composables/useCurrency';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import DashboardGuideOverlay from '@/components/dashboard/DashboardGuideOverlay.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    BarChart3,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    Clock,
    Coffee,
    Compass,
    CreditCard,
    DollarSign,
    Filter,
    LogIn,
    LogOut,
    MapPin,
    Receipt,
    RotateCcw,
    Search,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Star,
    TrendingUp,
    UserCheck,
    Users,
    UserX,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    kpis?: {
        today: { sales: number; profit: number; transactions: number; stock_value: number; expenses: number };
        seven_days: { sales: number; profit: number; transactions: number; stock_value: number; expenses: number };
        thirty_days: { sales: number; profit: number; transactions: number; stock_value: number; expenses: number };
    };
    paymentMix?: { method: string; amount: number; percent: number; count: number }[];
    recentSales?: any[];
    activeSessions?: any[];
    branches?: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const page = usePage();
const { currentTenant, isDemo } = useTenant();
const { currentBranch } = useBranch();
const { isMultiBranch, hasModule } = useFeatures();
const { currentRole, can } = usePermissions();

const authUser = computed(() => page?.props?.auth?.user as any);
const isCashier = computed(() => currentRole.value === 'Cashier' || authUser.value?.role === 'Cashier');

// ─────────────────────────────────────────────────────────────
// OWNER / MANAGEMENT DATA
// ─────────────────────────────────────────────────────────────
const ownerTimeFilter = ref<'Today' | '7 Days' | '30 Days' | 'This Month' | 'This Year'>('Today');

const ownerKpis = computed(() => {
    const k = props.kpis;
    if (!k) {
        return { sales: 0, profit: 0, transactions: 0, customers: 0, custBalance: 0, suppBalance: 0, stockValue: 0, expenses: 0, salesGrowth: 0, profitGrowth: 0, transGrowth: 0 };
    }
    switch (ownerTimeFilter.value) {
        case '7 Days':
            return { sales: k.seven_days.sales, profit: k.seven_days.profit, transactions: k.seven_days.transactions, customers: 0, custBalance: 0, suppBalance: 0, stockValue: k.seven_days.stock_value, expenses: k.seven_days.expenses, salesGrowth: 0, profitGrowth: 0, transGrowth: 0 };
        case '30 Days':
        case 'This Month':
        case 'This Year':
            return { sales: k.thirty_days.sales, profit: k.thirty_days.profit, transactions: k.thirty_days.transactions, customers: 0, custBalance: 0, suppBalance: 0, stockValue: k.thirty_days.stock_value, expenses: k.thirty_days.expenses, salesGrowth: 0, profitGrowth: 0, transGrowth: 0 };
        default:
            return { sales: k.today.sales, profit: k.today.profit, transactions: k.today.transactions, customers: 0, custBalance: 0, suppBalance: 0, stockValue: k.today.stock_value, expenses: k.today.expenses, salesGrowth: 0, profitGrowth: 0, transGrowth: 0 };
    }
});

const paymentMix = computed(() => {
    if (props.paymentMix && props.paymentMix.length > 0) {
        return props.paymentMix.map(p => ({
            method: p.method,
            amount: p.amount,
            percent: p.percent,
            color: p.method === 'Cash' ? 'bg-amber-500' : p.method.includes('M-Pesa') ? 'bg-emerald-500' : 'bg-sky-500',
            icon: p.method === 'Cash' ? Banknote : p.method.includes('M-Pesa') ? Smartphone : CreditCard,
        }));
    }
    return [
        { method: 'M-Pesa Express / Till', amount: 0, percent: 0, color: 'bg-emerald-500', icon: Smartphone },
        { method: 'Cash', amount: 0, percent: 0, color: 'bg-amber-500', icon: Banknote },
        { method: 'Card (Visa / Mastercard)', amount: 0, percent: 0, color: 'bg-sky-500', icon: CreditCard },
    ];
});

const topProducts: any[] = [];
const lowStockItems: any[] = [];

const recentSales = computed(() => {
    if (props.recentSales && props.recentSales.length > 0) {
        return props.recentSales.map(s => ({
            receipt: s.receipt_number,
            time: s.time,
            customer: s.customer_name || 'Walk-in Customer',
            cashier: s.cashier,
            amount: s.total,
            method: s.payment_method,
            status: s.payment_status,
        }));
    }
    return [];
});

// Branch performance for multi-branch owners
const branchPerformance = computed(() => {
    if (props.branches && props.branches.length > 0) {
        return props.branches.map(b => ({
            name: b.name,
            revenue: b.today_sales,
            target: 50000,
            staff: 1,
            status: b.status === 'Active' ? 'Active' : 'Suspended',
        }));
    }
    return [];
});

// ─────────────────────────────────────────────────────────────
// CASHIER-SPECIFIC DATA
// ─────────────────────────────────────────────────────────────

// Cashier's personal shift stats (scoped to their terminal/shift): clean for real accounts
const cashierShiftStats = computed(() => {
    if (isDemo.value) {
        return {
            shiftSales: 38450,
            shiftOrders: 47,
            avgBasket: 818,
            cashInDrawer: 8460,
            mpesaCollected: 22300,
            cardCollected: 5840,
            creditSales: 1850,
            refundsCount: 1,
            refundsAmount: 890,
            shiftStartTime: '08:00 AM',
            currentTime: '10:45 AM',
            shiftDuration: '2h 45m',
            targetSales: 80000,
            targetCompletion: 48.1,
        };
    }
    const todaySales = props.kpis?.today?.sales ?? 0;
    const todayOrders = props.kpis?.today?.transactions ?? 0;
    const avgBasket = todayOrders > 0 ? Math.round(todaySales / todayOrders) : 0;
    return {
        shiftSales: todaySales,
        shiftOrders: todayOrders,
        avgBasket,
        cashInDrawer: 0,
        mpesaCollected: 0,
        cardCollected: 0,
        creditSales: 0,
        refundsCount: 0,
        refundsAmount: 0,
        shiftStartTime: '08:00 AM',
        currentTime: 'Now',
        shiftDuration: 'Active Shift',
        targetSales: 0,
        targetCompletion: 0,
    };
});

// Cashier's personal recent receipts (their own terminal only)
const myRecentReceipts = computed(() => {
    if (props.recentSales && props.recentSales.length > 0) {
        return props.recentSales.map((s) => ({
            receipt: s.receipt_number,
            time: s.time,
            customer: s.customer_name || 'Walk-in Customer',
            items: s.items_count || 1,
            amount: s.total,
            method: s.payment_method,
            status: s.payment_status,
        }));
    }
    if (isDemo.value) {
        return [
            { receipt: 'REC-2026-904128', time: '10:32 AM', customer: 'Walk-in Customer', items: 5, amount: 4850, method: 'M-Pesa', status: 'Completed' },
            { receipt: 'REC-2026-904126', time: '10:05 AM', customer: 'Grace Muthoni', items: 3, amount: 1250, method: 'Cash', status: 'Completed' },
            { receipt: 'REC-2026-904125', time: '09:44 AM', customer: 'Sarit Cafe Supplies', items: 12, amount: 18900, method: 'Credit', status: 'Completed' },
            { receipt: 'REC-2026-904121', time: '09:12 AM', customer: 'Walk-in Customer', items: 2, amount: 680, method: 'M-Pesa', status: 'Completed' },
            { receipt: 'REC-2026-904119', time: '08:48 AM', customer: 'Peter Kimani', items: 8, amount: 12770, method: 'Card', status: 'Completed' },
        ];
    }
    return [];
});

// Cashier's hourly performance on their terminal
const cashierHourlyBars = [
    { label: '08:00', h: '30%', amount: 5200 },
    { label: '09:00', h: '55%', amount: 9600 },
    { label: '10:00', h: '85%', amount: 14800 },
    { label: '11:00', h: '45%', amount: 7900 },
    { label: '12:00', h: '70%', amount: 12200 },
    { label: '13:00', h: '60%', amount: 10500 },
    { label: '14:00', h: '40%', amount: 7100 },
    { label: '15:00', h: '50%', amount: 8800 },
    { label: '16:00', h: '75%', amount: 13200 },
    { label: '17:00', h: '100%', amount: 18400 },
    { label: '18:00', h: '90%', amount: 15900 },
    { label: '19:00', h: '35%', amount: 6100 },
];

// Top items this cashier has sold today
const myCashierTopItems = computed(() => {
    if (isDemo.value) {
        return [
            { name: 'Brookside Whole Milk 500ml', qty: 48, amount: 3840 },
            { name: 'Pembe Maize Flour 2kg', qty: 32, amount: 5440 },
            { name: 'Mumias Sugar 1kg', qty: 27, amount: 4050 },
            { name: 'Ariel Washing Powder 1kg', qty: 19, amount: 6840 },
            { name: 'Broadways White Bread', qty: 41, amount: 2870 },
        ];
    }
    return [];
});

// Owner bar chart data
const ownerHourlyBars = [
    { label: '08:00', h: '25%', val: '18k' }, { label: '09:00', h: '45%', val: '32k' },
    { label: '10:00', h: '85%', val: '64k' }, { label: '11:00', h: '60%', val: '45k' },
    { label: '12:00', h: '95%', val: '78k' }, { label: '13:00', h: '70%', val: '52k' },
    { label: '14:00', h: '50%', val: '38k' }, { label: '15:00', h: '65%', val: '49k' },
    { label: '16:00', h: '80%', val: '62k' }, { label: '17:00', h: '100%', val: '86k' },
    { label: '18:00', h: '90%', val: '72k' }, { label: '19:00', h: '40%', val: '30k' },
];

// ─────────────────────────────────────────────────────────────
// CASHIER SHIFT CALENDAR
// ─────────────────────────────────────────────────────────────
type ShiftStatus = 'completed' | 'active' | 'scheduled' | 'absent' | 'off';
interface ShiftDay {
    date: number;
    status: ShiftStatus;
    shiftLabel?: string;
    clockIn?: string;
    clockOut?: string;
    shiftSales?: number;
    orders?: number;
    note?: string;
}

const calendarYear = ref(new Date().getFullYear());
const calendarMonth = ref(new Date().getMonth()); // 0-indexed

const calendarMonthLabel = computed(() =>
    new Date(calendarYear.value, calendarMonth.value, 1).toLocaleString('default', { month: 'long', year: 'numeric' })
);

function prevMonth() {
    if (calendarMonth.value === 0) { calendarMonth.value = 11; calendarYear.value--; }
    else calendarMonth.value--;
}
function nextMonth() {
    if (calendarMonth.value === 11) { calendarMonth.value = 0; calendarYear.value++; }
    else calendarMonth.value++;
}

// Shift data for the month (demo only — real accounts compute dynamically)
const demoShiftData: Record<string, ShiftDay> = {
    '2026-08-01': { date: 1, status: 'completed', shiftLabel: 'Morning', clockIn: '08:01 AM', clockOut: '04:03 PM', shiftSales: 42300, orders: 88 },
    '2026-08-02': { date: 2, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-03': { date: 3, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-04': { date: 4, status: 'completed', shiftLabel: 'Morning', clockIn: '07:58 AM', clockOut: '04:01 PM', shiftSales: 38900, orders: 81 },
    '2026-08-05': { date: 5, status: 'completed', shiftLabel: 'Morning', clockIn: '08:03 AM', clockOut: '04:10 PM', shiftSales: 51200, orders: 104 },
    '2026-08-06': { date: 6, status: 'completed', shiftLabel: 'Afternoon', clockIn: '12:00 PM', clockOut: '08:05 PM', shiftSales: 44800, orders: 96 },
    '2026-08-07': { date: 7, status: 'completed', shiftLabel: 'Morning', clockIn: '07:55 AM', clockOut: '04:00 PM', shiftSales: 36700, orders: 74 },
    '2026-08-08': { date: 8, status: 'completed', shiftLabel: 'Morning', clockIn: '08:00 AM', clockOut: '04:02 PM', shiftSales: 48100, orders: 99 },
    '2026-08-09': { date: 9, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-10': { date: 10, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-11': { date: 11, status: 'completed', shiftLabel: 'Afternoon', clockIn: '12:02 PM', clockOut: '08:00 PM', shiftSales: 53400, orders: 112 },
    '2026-08-12': { date: 12, status: 'completed', shiftLabel: 'Morning', clockIn: '08:00 AM', clockOut: '04:00 PM', shiftSales: 40100, orders: 83 },
    '2026-08-13': { date: 13, status: 'absent', shiftLabel: 'Morning', note: 'No clock-in recorded' },
    '2026-08-14': { date: 14, status: 'completed', shiftLabel: 'Morning', clockIn: '08:05 AM', clockOut: '04:10 PM', shiftSales: 39600, orders: 79 },
    '2026-08-15': { date: 15, status: 'completed', shiftLabel: 'Morning', clockIn: '07:59 AM', clockOut: '04:00 PM', shiftSales: 47200, orders: 91 },
    '2026-08-16': { date: 16, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-17': { date: 17, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-18': { date: 18, status: 'completed', shiftLabel: 'Morning', clockIn: '08:01 AM', clockOut: '04:05 PM', shiftSales: 44000, orders: 88 },
    '2026-08-19': { date: 19, status: 'completed', shiftLabel: 'Afternoon', clockIn: '12:00 PM', clockOut: '07:58 PM', shiftSales: 49800, orders: 103 },
    '2026-08-20': { date: 20, status: 'completed', shiftLabel: 'Morning', clockIn: '08:00 AM', clockOut: '04:00 PM', shiftSales: 41500, orders: 85 },
    '2026-08-21': { date: 21, status: 'completed', shiftLabel: 'Morning', clockIn: '08:04 AM', clockOut: '04:02 PM', shiftSales: 38200, orders: 76 },
    '2026-08-22': { date: 22, status: 'completed', shiftLabel: 'Morning', clockIn: '07:57 AM', clockOut: '04:00 PM', shiftSales: 43700, orders: 90 },
    '2026-08-23': { date: 23, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-24': { date: 24, status: 'off', shiftLabel: 'Day Off' },
    '2026-08-25': { date: 25, status: 'completed', shiftLabel: 'Morning', clockIn: '08:00 AM', clockOut: '04:00 PM', shiftSales: 46900, orders: 94 },
    '2026-08-26': { date: 26, status: 'completed', shiftLabel: 'Morning', clockIn: '08:02 AM', clockOut: '04:08 PM', shiftSales: 35800, orders: 72 },
    '2026-08-27': { date: 27, status: 'completed', shiftLabel: 'Afternoon', clockIn: '11:59 AM', clockOut: '08:02 PM', shiftSales: 52600, orders: 108 },
    '2026-08-28': { date: 28, status: 'completed', shiftLabel: 'Morning', clockIn: '08:00 AM', clockOut: '04:00 PM', shiftSales: 40800, orders: 83 },
    '2026-08-29': { date: 29, status: 'active', shiftLabel: 'Morning', clockIn: '08:00 AM', shiftSales: 38450, orders: 47 },
    '2026-08-30': { date: 30, status: 'scheduled', shiftLabel: 'Morning' },
    '2026-08-31': { date: 31, status: 'scheduled', shiftLabel: 'Afternoon' },
};

const shiftData = computed<Record<string, ShiftDay>>(() => isDemo.value ? demoShiftData : {});

const calendarDays = computed(() => {
    const year = calendarYear.value;
    const month = calendarMonth.value;
    const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const days: Array<{ day: number | null; key: string | null; shift: ShiftDay | null }> = [];
    // Leading empty slots (week starts Sunday)
    for (let i = 0; i < firstDay; i++) days.push({ day: null, key: null, shift: null });
    for (let d = 1; d <= daysInMonth; d++) {
        const mm = String(month + 1).padStart(2, '0');
        const dd = String(d).padStart(2, '0');
        const key = `${year}-${mm}-${dd}`;
        days.push({ day: d, key, shift: shiftData.value[key] ?? null });
    }
    return days;
});

const selectedShiftDay = ref<ShiftDay | null>(null);

function selectDay(shift: ShiftDay | null) {
    if (shift && shift.status !== 'off' && shift.status !== 'scheduled') {
        selectedShiftDay.value = selectedShiftDay.value?.date === shift.date ? null : shift;
    }
}

const shiftStatusConfig: Record<ShiftStatus, { bg: string; text: string; dot: string; label: string }> = {
    completed: { bg: 'bg-emerald-500/15 border border-emerald-500/40', text: 'text-emerald-700 dark:text-emerald-400', dot: 'bg-emerald-500', label: 'Completed' },
    active:    { bg: 'bg-sky-500/15 border border-sky-500/40 ring-2 ring-sky-500/30', text: 'text-sky-700 dark:text-sky-400', dot: 'bg-sky-500 animate-pulse', label: 'Active Now' },
    scheduled: { bg: 'bg-muted/40 border border-border/60', text: 'text-muted-foreground', dot: 'bg-muted-foreground/40', label: 'Scheduled' },
    absent:    { bg: 'bg-rose-500/10 border border-rose-500/30', text: 'text-rose-600', dot: 'bg-rose-500', label: 'Absent' },
    off:       { bg: 'bg-transparent', text: 'text-muted-foreground/40', dot: '', label: 'Day Off' },
};

const calendarMonthSummary = computed(() => {
    const month = String(calendarMonth.value + 1).padStart(2, '0');
    const year = calendarYear.value;
    const prefix = `${year}-${month}-`;
    const relevant = Object.entries(shiftData.value)
        .filter(([k]) => k.startsWith(prefix))
        .map(([, v]) => v);
    return {
        worked: relevant.filter(d => d.status === 'completed' || d.status === 'active').length,
        absent: relevant.filter(d => d.status === 'absent').length,
        scheduled: relevant.filter(d => d.status === 'scheduled').length,
        totalSales: relevant.reduce((s, d) => s + (d.shiftSales ?? 0), 0),
        totalOrders: relevant.reduce((s, d) => s + (d.orders ?? 0), 0),
    };
});

const showCalendar = ref(false);

// ─────────────────────────────────────────────────────────────
// OWNER / MANAGEMENT: BUSINESS-WIDE STAFF SHIFTS & CLOCK-IN TRACKER
// ─────────────────────────────────────────────────────────────
interface StaffAttendanceSession {
    id: string;
    name: string;
    role: string;
    avatar: string;
    branchId: string;
    branchName: string;
    terminal: string;
    shiftName: string;
    shiftTime: string;
    status: 'clocked_in' | 'on_break' | 'scheduled' | 'completed' | 'absent';
    clockInTime?: string;
    clockOutTime?: string;
    punctuality: 'On Time' | 'Late' | 'Early' | 'Pending' | 'N/A';
    lateMinutes?: number;
    salesToday: number;
    salesTarget: number;
    receiptsCount: number;
    avgOrderValue: number;
}

const showOwnerSchedule = ref(false);
const ownerBranchFilter = ref<string>('all');
const ownerStaffSearch = ref<string>('');

const ownerBranchTabs = computed(() => {
    if (props.branches && props.branches.length > 0) {
        return [
            { id: 'all', name: 'All Branches' },
            ...props.branches.map((b) => ({ id: String(b.id), name: b.name })),
        ];
    }
    if (isDemo.value) {
        return [
            { id: 'all', name: 'All Branches' },
            { id: 'nairobi-central', name: 'Nairobi Central (HQ)' },
            { id: 'westlands-outlet', name: 'Westlands Outlet' },
            { id: 'mombasa-rd', name: 'Mombasa Road' },
        ];
    }
    return [{ id: 'all', name: 'All Branches' }];
});

const demoOwnerStaffMembers: StaffAttendanceSession[] = [
    {
        id: 'st-01',
        name: 'Wamtesh K.',
        role: 'Lead Cashier',
        avatar: 'WK',
        branchId: 'nairobi-central',
        branchName: 'Nairobi Central (HQ)',
        terminal: 'Terminal #01',
        shiftName: 'Morning Shift',
        shiftTime: '08:00 AM - 04:00 PM',
        status: 'clocked_in',
        clockInTime: '07:58 AM',
        punctuality: 'On Time',
        salesToday: 38450,
        salesTarget: 80000,
        receiptsCount: 47,
        avgOrderValue: 818,
    },
    {
        id: 'st-02',
        name: 'David M.',
        role: 'Cashier',
        avatar: 'DM',
        branchId: 'nairobi-central',
        branchName: 'Nairobi Central (HQ)',
        terminal: 'Terminal #02',
        shiftName: 'Afternoon Shift',
        shiftTime: '12:00 PM - 08:00 PM',
        status: 'scheduled',
        punctuality: 'Pending',
        salesToday: 0,
        salesTarget: 75000,
        receiptsCount: 0,
        avgOrderValue: 0,
    },
    {
        id: 'st-03',
        name: 'Grace Muthoni',
        role: 'Cashier',
        avatar: 'GM',
        branchId: 'westlands-outlet',
        branchName: 'Westlands Outlet',
        terminal: 'Terminal #01',
        shiftName: 'Morning Shift',
        shiftTime: '08:00 AM - 04:00 PM',
        status: 'clocked_in',
        clockInTime: '08:02 AM',
        punctuality: 'On Time',
        salesToday: 34200,
        salesTarget: 65000,
        receiptsCount: 41,
        avgOrderValue: 834,
    },
    {
        id: 'st-04',
        name: 'Peter Kimani',
        role: 'Supervisor / Cashier',
        avatar: 'PK',
        branchId: 'westlands-outlet',
        branchName: 'Westlands Outlet',
        terminal: 'Terminal #02',
        shiftName: 'Full Day Shift',
        shiftTime: '08:00 AM - 06:00 PM',
        status: 'on_break',
        clockInTime: '07:55 AM',
        punctuality: 'Early',
        salesToday: 28200,
        salesTarget: 50000,
        receiptsCount: 29,
        avgOrderValue: 972,
    },
    {
        id: 'st-05',
        name: 'Mercy Achieng',
        role: 'Cashier',
        avatar: 'MA',
        branchId: 'mombasa-rd',
        branchName: 'Mombasa Road',
        terminal: 'Terminal #01',
        shiftName: 'Morning Shift',
        shiftTime: '08:00 AM - 04:00 PM',
        status: 'clocked_in',
        clockInTime: '08:14 AM',
        punctuality: 'Late',
        lateMinutes: 14,
        salesToday: 22800,
        salesTarget: 50000,
        receiptsCount: 24,
        avgOrderValue: 950,
    },
    {
        id: 'st-06',
        name: 'Brian Otieno',
        role: 'Relief Cashier',
        avatar: 'BO',
        branchId: 'mombasa-rd',
        branchName: 'Mombasa Road',
        terminal: 'Terminal #02',
        shiftName: 'Morning Shift',
        shiftTime: '08:00 AM - 04:00 PM',
        status: 'clocked_in',
        clockInTime: '07:50 AM',
        punctuality: 'Early',
        salesToday: 16000,
        salesTarget: 35000,
        receiptsCount: 18,
        avgOrderValue: 888,
    },
];

const ownerStaffMembers = computed<StaffAttendanceSession[]>(() => {
    if (props.activeSessions && props.activeSessions.length > 0) {
        return props.activeSessions.map((s, idx) => ({
            id: `st-${s.id || idx}`,
            name: s.name,
            role: s.role,
            avatar: s.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase(),
            branchId: String(s.branch_id || 'main'),
            branchName: s.branch || 'Main HQ',
            terminal: 'Terminal #01',
            shiftName: 'Active Shift',
            shiftTime: '08:00 AM - 05:00 PM',
            status: 'clocked_in',
            clockInTime: s.clocked_in_at || '08:00 AM',
            punctuality: 'On Time',
            salesToday: 0,
            salesTarget: 50000,
            receiptsCount: 0,
            avgOrderValue: 0,
        }));
    }
    if (isDemo.value) {
        return demoOwnerStaffMembers;
    }
    return [];
});

const filteredOwnerStaff = computed(() => {
    return ownerStaffMembers.value.filter((staff) => {
        const matchesBranch = ownerBranchFilter.value === 'all' || staff.branchId === ownerBranchFilter.value;
        const matchesSearch =
            !ownerStaffSearch.value.trim() ||
            staff.name.toLowerCase().includes(ownerStaffSearch.value.toLowerCase()) ||
            staff.role.toLowerCase().includes(ownerStaffSearch.value.toLowerCase()) ||
            staff.branchName.toLowerCase().includes(ownerStaffSearch.value.toLowerCase()) ||
            staff.terminal.toLowerCase().includes(ownerStaffSearch.value.toLowerCase());
        return matchesBranch && matchesSearch;
    });
});

const ownerScheduleSummary = computed(() => {
    const list = ownerBranchFilter.value === 'all'
        ? ownerStaffMembers.value
        : ownerStaffMembers.value.filter(s => s.branchId === ownerBranchFilter.value);
    
    const active = list.filter(s => s.status === 'clocked_in').length;
    const onBreak = list.filter(s => s.status === 'on_break').length;
    const scheduled = list.filter(s => s.status === 'scheduled').length;
    const absent = list.filter(s => s.status === 'absent').length;
    const totalSales = list.reduce((sum, s) => sum + s.salesToday, 0);
    const totalTarget = list.reduce((sum, s) => sum + s.salesTarget, 0);
    const targetPercent = totalTarget > 0 ? Math.round((totalSales / totalTarget) * 100) : 0;
    const totalOrders = list.reduce((sum, s) => sum + s.receiptsCount, 0);

    return {
        totalStaff: list.length,
        active,
        onBreak,
        scheduled,
        absent,
        totalSales,
        totalTarget,
        targetPercent,
        totalOrders,
    };
});

// Interactive Dashboard Guide & Walkthrough State
const guideOverlayOpen = ref(false);
const guideCurrentStep = ref(0);

const openGuide = (step = 0) => {
    guideCurrentStep.value = step;
    guideOverlayOpen.value = true;
};

const handleGuideAction = (actionType: string) => {
    if (actionType === 'open-pos') {
        guideOverlayOpen.value = false;
        router.visit('/pos');
    }
};

onMounted(() => {
    // Auto-prompt guide on first visit or when initiated after login
    const shouldAutoOpen =
        sessionStorage.getItem('mde_auto_open_guide') === 'true' ||
        !sessionStorage.getItem('mde_dashboard_guide_prompted');

    if (shouldAutoOpen) {
        sessionStorage.setItem('mde_dashboard_guide_prompted', 'true');
        sessionStorage.removeItem('mde_auto_open_guide');
        setTimeout(() => {
            openGuide(0);
        }, 700);
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- CASHIER DASHBOARD                                       -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div v-if="isCashier" class="space-y-6">

                <!-- Header: Cashier Identity & Shift Status -->
                <div id="cashier-step-header" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="size-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center shadow-lg shrink-0">
                            <ShoppingCart class="size-7 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-xl font-bold text-foreground">My Register Dashboard</h2>
                                <Badge class="bg-emerald-500/15 text-emerald-700 border-emerald-500/30 font-mono text-[11px]">
                                    <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse inline-block"></span>
                                    Active Shift
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                <strong>{{ authUser?.name || 'Lead Cashier' }}</strong> • Terminal #01 • {{ currentBranch.name }} •
                                Started {{ cashierShiftStats.shiftStartTime }} ({{ cashierShiftStats.shiftDuration }} elapsed)
                            </p>
                        </div>
                    </div>
                    <!-- Quick Actions -->
                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <Button
                            id="cashier-tour-guide-btn"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 px-3 gap-1.5 text-xs font-bold text-primary border-primary/40 bg-primary/10 hover:bg-primary/20 hover:border-primary/60 cursor-pointer shadow-2xs transition-all relative"
                            @click="openGuide(0)"
                            title="Start Interactive Register Walkthrough"
                        >
                            <Compass class="size-3.5 animate-spin" style="animation-duration: 12s" />
                            <span>Dashboard Guide</span>
                        </Button>
                        <Link href="/sales">
                            <Button variant="outline" size="sm" class="gap-1.5 text-xs h-9">
                                <Receipt class="size-3.5" />
                                My Sales Log
                            </Button>
                        </Link>
                        <Link href="/pos">
                            <Button id="cashier-step-pos-action" size="sm" class="gap-1.5 text-xs h-9 bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow">
                                <Zap class="size-3.5" />
                                Open POS Register
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- ── COMPACT SHIFT CALENDAR TOGGLE STRIP ────────────────── -->
                <div id="cashier-step-calendar" class="rounded-xl border border-border/80 bg-card shadow-sm overflow-hidden">
                    <!-- Toggle trigger row -->
                    <button
                        type="button"
                        @click="showCalendar = !showCalendar"
                        class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-muted/30 transition-colors group"
                    >
                        <div class="flex items-center gap-3 text-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                <span class="font-semibold text-emerald-700">{{ calendarMonthSummary.worked }} shifts worked</span>
                            </div>
                            <span class="text-border">|</span>
                            <div class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-rose-500"></span>
                                <span class="font-semibold text-rose-600">{{ calendarMonthSummary.absent }} absent</span>
                            </div>
                            <span class="text-border">|</span>
                            <div class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-muted-foreground/40"></span>
                                <span class="font-medium text-muted-foreground">{{ calendarMonthSummary.scheduled }} upcoming</span>
                            </div>
                            <span class="hidden sm:block text-border">|</span>
                            <span class="hidden sm:block font-mono font-bold text-primary">{{ formatCurrency(calendarMonthSummary.totalSales) }} this month</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[11px] font-medium text-muted-foreground group-hover:text-foreground transition-colors">
                                {{ showCalendar ? 'Hide' : 'My Shift Calendar' }}
                            </span>
                            <div class="size-5 rounded-md bg-muted/60 flex items-center justify-center transition-transform duration-200" :class="showCalendar ? 'rotate-180' : ''">
                                <ChevronRight class="size-3 rotate-90" />
                            </div>
                        </div>
                    </button>

                    <!-- Collapsible calendar body -->
                    <transition name="collapse">
                        <div v-if="showCalendar" class="border-t border-border/60 px-4 pb-4 pt-3">
                            <!-- Month navigator + header -->
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold text-foreground">{{ calendarMonthLabel }}</span>
                                <div class="flex items-center gap-1.5">
                                    <button @click="prevMonth" class="size-6 rounded-md border border-border/80 hover:bg-muted/50 flex items-center justify-center transition-colors">
                                        <ChevronLeft class="size-3" />
                                    </button>
                                    <button @click="nextMonth" class="size-6 rounded-md border border-border/80 hover:bg-muted/50 flex items-center justify-center transition-colors">
                                        <ChevronRight class="size-3" />
                                    </button>
                                </div>
                            </div>

                            <!-- Day-of-week headers -->
                            <div class="grid grid-cols-7 text-center text-[10px] font-bold text-muted-foreground mb-1.5">
                                <span v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d">{{ d }}</span>
                            </div>

                            <!-- Calendar grid (compact) -->
                            <div class="grid grid-cols-7 gap-1">
                                <div
                                    v-for="(cell, idx) in calendarDays"
                                    :key="idx"
                                    @click="selectDay(cell.shift)"
                                    :class="[
                                        'relative rounded-md p-1 min-h-[50px] transition-all duration-150 text-xs',
                                        cell.day === null ? '' : 'cursor-pointer hover:scale-[1.04]',
                                        cell.shift ? shiftStatusConfig[cell.shift.status].bg : '',
                                        cell.shift && selectedShiftDay?.date === cell.shift.date ? 'ring-2 ring-primary ring-offset-1' : '',
                                    ]"
                                >
                                    <template v-if="cell.day !== null">
                                        <div class="flex items-start justify-between">
                                            <span :class="['font-bold text-[10px] leading-none', cell.shift ? shiftStatusConfig[cell.shift.status].text : 'text-muted-foreground']">{{ cell.day }}</span>
                                            <span v-if="cell.shift && cell.shift.status !== 'off'" :class="['size-1.5 rounded-full mt-0.5 shrink-0', shiftStatusConfig[cell.shift.status].dot]"></span>
                                        </div>
                                        <div v-if="cell.shift && cell.shift.status !== 'off'" class="mt-1">
                                            <p :class="['text-[9px] font-semibold leading-tight truncate', shiftStatusConfig[cell.shift.status].text]">{{ cell.shift.shiftLabel }}</p>
                                            <p v-if="cell.shift.clockIn" class="text-[9px] text-muted-foreground leading-tight">{{ cell.shift.clockIn }}</p>
                                        </div>
                                        <p v-else-if="cell.shift?.status === 'off'" class="text-[9px] text-muted-foreground/40 mt-1">Off</p>
                                    </template>
                                </div>
                            </div>

                            <!-- Selected day detail panel -->
                            <transition name="fade-slide">
                                <div v-if="selectedShiftDay" class="mt-3 rounded-lg border border-border/80 bg-muted/30 p-3 text-xs space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-foreground flex items-center gap-1.5">
                                            <CalendarDays class="size-3.5 text-primary" />
                                            Day {{ selectedShiftDay.date }} — {{ selectedShiftDay.shiftLabel }}
                                            <Badge :class="[
                                                'text-[10px] ml-1',
                                                selectedShiftDay.status === 'completed' ? 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30' :
                                                selectedShiftDay.status === 'active' ? 'bg-sky-500/15 text-sky-700 border-sky-500/30' :
                                                'bg-rose-500/15 text-rose-700 border-rose-500/30'
                                            ]">{{ shiftStatusConfig[selectedShiftDay.status].label }}</Badge>
                                        </span>
                                        <button @click="selectedShiftDay = null" class="text-muted-foreground hover:text-foreground text-[10px] underline">✕</button>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        <div class="rounded-md bg-card border border-border/60 p-2">
                                            <div class="flex items-center gap-1 text-muted-foreground mb-0.5 text-[10px]"><LogIn class="size-2.5" /> Clock In</div>
                                            <p class="font-bold font-mono text-foreground text-xs">{{ selectedShiftDay.clockIn ?? 'N/A' }}</p>
                                        </div>
                                        <div class="rounded-md bg-card border border-border/60 p-2">
                                            <div class="flex items-center gap-1 text-muted-foreground mb-0.5 text-[10px]"><LogOut class="size-2.5" /> Clock Out</div>
                                            <p class="font-bold font-mono text-foreground text-xs">{{ selectedShiftDay.clockOut ?? (selectedShiftDay.status === 'active' ? 'Ongoing' : 'N/A') }}</p>
                                        </div>
                                        <div class="rounded-md bg-card border border-border/60 p-2">
                                            <div class="flex items-center gap-1 text-muted-foreground mb-0.5 text-[10px]"><BarChart3 class="size-2.5" /> Shift Sales</div>
                                            <p class="font-bold font-mono text-emerald-700 text-xs">{{ selectedShiftDay.shiftSales ? formatCurrency(selectedShiftDay.shiftSales) : '—' }}</p>
                                        </div>
                                        <div class="rounded-md bg-card border border-border/60 p-2">
                                            <div class="flex items-center gap-1 text-muted-foreground mb-0.5 text-[10px]"><Receipt class="size-2.5" /> Orders</div>
                                            <p class="font-bold font-mono text-foreground text-xs">{{ selectedShiftDay.orders ?? '—' }} receipts</p>
                                        </div>
                                    </div>
                                    <p v-if="selectedShiftDay.note" class="text-rose-600 font-semibold flex items-center gap-1 text-[10px]">
                                        <AlertTriangle class="size-3" /> {{ selectedShiftDay.note }}
                                    </p>
                                </div>
                            </transition>

                            <!-- Legend -->
                            <div class="mt-3 flex flex-wrap gap-2.5 text-[10px] text-muted-foreground">
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Completed</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-sky-500 animate-pulse"></span> Active</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-muted-foreground/40"></span> Scheduled</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> Absent</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-transparent border border-muted-foreground/30"></span> Day Off</span>
                            </div>
                        </div>
                    </transition>
                </div>

                <!-- Shift Progress Bar -->
                <div id="cashier-step-target" class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between mb-2 text-xs">
                        <span class="font-semibold text-foreground flex items-center gap-1.5">
                            <BarChart3 class="size-3.5 text-emerald-600" />
                            Today's Shift Target Progress
                        </span>
                        <span class="font-mono font-bold text-emerald-700">
                            {{ formatCurrency(cashierShiftStats.shiftSales) }} / {{ formatCurrency(cashierShiftStats.targetSales) }}
                            <span class="text-muted-foreground font-normal ml-1">({{ cashierShiftStats.targetCompletion }}%)</span>
                        </span>
                    </div>
                    <div class="h-3 w-full rounded-full bg-muted/60 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-700 shadow-sm"
                            :style="{ width: `${cashierShiftStats.targetCompletion}%` }"
                        ></div>
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1.5">{{ formatCurrency(cashierShiftStats.targetSales - cashierShiftStats.shiftSales) }} remaining to hit today's shift target</p>
                </div>

                <!-- 4 Personal KPI Cards -->
                <div id="cashier-step-kpis" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard
                        title="My Shift Sales"
                        :value="formatCurrency(cashierShiftStats.shiftSales)"
                        change-text="since 08:00 AM"
                        :icon="TrendingUp"
                        icon-color="text-emerald-600"
                        icon-bg="bg-emerald-500/10"
                    />
                    <StatCard
                        title="Orders Processed"
                        :value="cashierShiftStats.shiftOrders"
                        sub-value="receipts issued"
                        change-text="on this terminal"
                        :icon="Receipt"
                        icon-color="text-sky-600"
                        icon-bg="bg-sky-500/10"
                    />
                    <StatCard
                        title="Average Basket"
                        :value="formatCurrency(cashierShiftStats.avgBasket)"
                        change-text="per transaction"
                        :icon="ShoppingCart"
                        icon-color="text-amber-600"
                        icon-bg="bg-amber-500/10"
                    />
                    <StatCard
                        title="Refunds Issued"
                        :value="cashierShiftStats.refundsCount"
                        sub-value="items returned"
                        :change="-cashierShiftStats.refundsAmount"
                        change-text="value reversed"
                        :icon="RotateCcw"
                        icon-color="text-rose-600"
                        icon-bg="bg-rose-500/10"
                    />
                </div>

                <!-- Payment Breakdown: Cash / M-Pesa / Card / Credit -->
                <div id="cashier-step-payments" class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-xs">
                    <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="size-7 rounded-lg bg-emerald-500/15 flex items-center justify-center">
                                <Smartphone class="size-3.5 text-emerald-600" />
                            </div>
                            <span class="font-semibold text-muted-foreground">M-Pesa Collected</span>
                        </div>
                        <div class="text-xl font-bold font-mono text-emerald-700">{{ formatCurrency(cashierShiftStats.mpesaCollected) }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">58% of shift revenue</p>
                    </div>
                    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="size-7 rounded-lg bg-amber-500/15 flex items-center justify-center">
                                <Banknote class="size-3.5 text-amber-600" />
                            </div>
                            <span class="font-semibold text-muted-foreground">Cash in Drawer</span>
                        </div>
                        <div class="text-xl font-bold font-mono text-amber-700">{{ formatCurrency(cashierShiftStats.cashInDrawer) }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">22% of shift revenue</p>
                    </div>
                    <div class="rounded-2xl border border-sky-500/30 bg-sky-500/5 p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="size-7 rounded-lg bg-sky-500/15 flex items-center justify-center">
                                <CreditCard class="size-3.5 text-sky-600" />
                            </div>
                            <span class="font-semibold text-muted-foreground">Card Payments</span>
                        </div>
                        <div class="text-xl font-bold font-mono text-sky-700">{{ formatCurrency(cashierShiftStats.cardCollected) }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">15% of shift revenue</p>
                    </div>
                    <div class="rounded-2xl border border-purple-500/30 bg-purple-500/5 p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="size-7 rounded-lg bg-purple-500/15 flex items-center justify-center">
                                <Wallet class="size-3.5 text-purple-600" />
                            </div>
                            <span class="font-semibold text-muted-foreground">Credit Sales</span>
                        </div>
                        <div class="text-xl font-bold font-mono text-purple-700">{{ formatCurrency(cashierShiftStats.creditSales) }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">5% of shift revenue</p>
                    </div>
                </div>

                <!-- Middle: Hourly Chart + Top Items Sold -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Hourly terminal chart -->
                    <Card id="cashier-step-chart" class="lg:col-span-2 border-border/80">
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <div>
                                <CardTitle class="text-base font-bold">My Hourly Terminal Activity</CardTitle>
                                <CardDescription class="text-xs">Sales rung up on this register by hour</CardDescription>
                            </div>
                            <Badge variant="outline" class="text-xs text-emerald-600 bg-emerald-500/10 border-emerald-500/20 font-mono">
                                Peak: 10:00–11:00
                            </Badge>
                        </CardHeader>
                        <CardContent class="pt-2">
                            <div class="h-52 w-full flex items-end justify-between gap-1.5 pt-6 pb-2 px-2 border-b border-border/60">
                                <div
                                    v-for="(bar, idx) in cashierHourlyBars"
                                    :key="idx"
                                    class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end"
                                >
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-7 text-[10px] font-mono font-bold bg-emerald-600 text-white px-1.5 py-0.5 rounded shadow whitespace-nowrap z-10">
                                        {{ formatCurrency(bar.amount) }}
                                    </div>
                                    <div
                                        class="w-full rounded-t-md bg-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-300 relative overflow-hidden"
                                        :style="{ height: bar.h }"
                                    >
                                        <div class="absolute inset-0 bg-gradient-to-t from-transparent to-emerald-400/40"></div>
                                    </div>
                                    <span class="text-[10px] font-mono text-muted-foreground mt-1">{{ bar.label }}</span>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                                    Hourly Register Revenue (KES)
                                </span>
                                <span class="font-medium">{{ cashierShiftStats.shiftOrders }} receipts issued this shift</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Top items I sold today -->
                    <Card id="cashier-step-top-items" class="border-border/80">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <Star class="size-4 text-amber-500" />
                                My Top-Selling Items
                            </CardTitle>
                            <CardDescription class="text-xs">Highest volume rung on this terminal</CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="divide-y divide-border/60 text-xs">
                                <div v-for="(item, idx) in myCashierTopItems" :key="idx" class="flex items-center justify-between p-3.5 hover:bg-muted/20">
                                    <div class="flex items-center gap-3">
                                        <span class="size-5 rounded-full bg-muted flex items-center justify-center font-bold text-[10px] text-muted-foreground shrink-0">{{ idx + 1 }}</span>
                                        <div>
                                            <p class="font-semibold text-foreground truncate max-w-[130px]">{{ item.name }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ item.qty }} units sold</p>
                                        </div>
                                    </div>
                                    <span class="font-bold font-mono text-foreground">{{ formatCurrency(item.amount) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Bottom: My Recent Receipts + Shift Summary -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- My recent receipts (2 cols) -->
                    <Card id="cashier-step-recent-receipts" class="lg:col-span-2 border-border/80">
                        <CardHeader class="pb-3 flex flex-row items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-bold">My Recent Receipts</CardTitle>
                                <CardDescription class="text-xs">Transactions from this terminal only</CardDescription>
                            </div>
                            <Link href="/sales" class="text-xs text-primary hover:underline font-semibold">Full Sales Log</Link>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="max-h-[308px] overflow-y-auto divide-y divide-border/60 text-xs scrollbar-thin overscroll-contain">
                                <div v-for="(sale, idx) in myRecentReceipts" :key="idx" class="flex items-center justify-between p-3.5 hover:bg-muted/20 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-lg bg-muted/60 flex items-center justify-center shrink-0">
                                            <Receipt class="size-4 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono font-bold text-primary text-[11px]">{{ sale.receipt }}</span>
                                                <span class="text-muted-foreground text-[10px]">{{ sale.time }}</span>
                                            </div>
                                            <p class="text-muted-foreground">{{ sale.customer }} • {{ sale.items }} items • {{ sale.method }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold font-mono text-foreground">{{ formatCurrency(sale.amount) }}</p>
                                        <Badge :variant="sale.status === 'Completed' ? 'success' : 'destructive'" class="text-[10px] py-0">
                                            {{ sale.status }}
                                        </Badge>
                                    </div>
                                </div>
                                <div v-if="myRecentReceipts.length === 0" class="p-6 text-center text-muted-foreground text-xs">
                                    No receipts issued yet this shift
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Shift Summary Card -->
                    <Card id="cashier-step-shift-summary" class="border-border/80">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <ShieldCheck class="size-4 text-sky-500" />
                                Shift Summary
                            </CardTitle>
                            <CardDescription class="text-xs">My cash register reconciliation</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3 text-xs">
                            <div class="flex justify-between py-2 border-b border-border/60">
                                <span class="text-muted-foreground flex items-center gap-2"><Clock class="size-3" /> Shift Start</span>
                                <span class="font-bold font-mono">{{ cashierShiftStats.shiftStartTime }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-border/60">
                                <span class="text-muted-foreground flex items-center gap-2"><BarChart3 class="size-3" /> Total Orders</span>
                                <span class="font-bold font-mono">{{ cashierShiftStats.shiftOrders }} receipts</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-border/60">
                                <span class="text-muted-foreground flex items-center gap-2"><Banknote class="size-3" /> Cash Drawer</span>
                                <span class="font-bold font-mono text-amber-600">{{ formatCurrency(cashierShiftStats.cashInDrawer) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-border/60">
                                <span class="text-muted-foreground flex items-center gap-2"><Smartphone class="size-3" /> M-Pesa Total</span>
                                <span class="font-bold font-mono text-emerald-600">{{ formatCurrency(cashierShiftStats.mpesaCollected) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-border/60">
                                <span class="text-muted-foreground flex items-center gap-2"><RotateCcw class="size-3" /> Refunds</span>
                                <span class="font-bold font-mono text-rose-600">-{{ formatCurrency(cashierShiftStats.refundsAmount) }}</span>
                            </div>
                            <div class="flex justify-between py-2 pt-3 mt-1 border-t-2 border-emerald-500/40">
                                <span class="font-bold text-foreground">Net Shift Revenue</span>
                                <span class="font-bold font-mono text-emerald-700">{{ formatCurrency(cashierShiftStats.shiftSales - cashierShiftStats.refundsAmount) }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

            </div>
            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- END CASHIER DASHBOARD                                   -->
            <!-- ═══════════════════════════════════════════════════════ -->


            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- OWNER / MANAGEMENT DASHBOARD                            -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div v-else class="space-y-6">

                <!-- Header & Time Filter -->
                <div id="dashboard-step-header" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <span class="truncate max-w-[220px] sm:max-w-none">{{ currentTenant.name }} Dashboard</span>
                            <Badge variant="outline" class="font-mono text-xs shrink-0">{{ currentBranch.code }}</Badge>
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Real-time sales, inventory valuation, and multi-branch analytics for {{ currentBranch.name }}.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Quick POS Launcher Button -->
                        <Link href="/pos">
                            <Button id="dashboard-step-pos-action" size="sm" class="gap-1.5 text-xs h-9 bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm">
                                <Zap class="size-3.5" />
                                <span>Open POS Register</span>
                            </Button>
                        </Link>

                        <!-- Dashboard Guide Button -->
                        <Button
                            id="dashboard-tour-guide-btn"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 px-3 gap-1.5 text-xs font-bold text-primary border-primary/40 bg-primary/10 hover:bg-primary/20 hover:border-primary/60 cursor-pointer shadow-2xs transition-all relative"
                            @click="openGuide(0)"
                            title="Start Interactive Dashboard Walkthrough"
                        >
                            <Compass class="size-3.5 animate-spin" style="animation-duration: 12s" />
                            <span>Dashboard Guide</span>
                        </Button>

                        <!-- Time Filter -->
                        <div class="flex items-center gap-1 sm:gap-1.5 rounded-xl border border-border/80 bg-card p-1 shadow-sm text-xs overflow-x-auto scrollbar-none">
                            <button
                                v-for="t in ['Today', '7 Days', '30 Days', 'This Month', 'This Year']"
                                :key="t"
                                type="button"
                                :class="[
                                    'px-2.5 sm:px-3 py-1 sm:py-1.5 font-semibold rounded-lg transition-all whitespace-nowrap text-xs',
                                    ownerTimeFilter === t
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/40',
                                ]"
                                @click="ownerTimeFilter = t as any"
                            >
                                {{ t }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── OWNER COMPACT STAFF SHIFTS, CLOCK-IN & TARGETS STRIP ── -->
                <div id="dashboard-step-shifts" class="rounded-xl border border-border/80 bg-card shadow-sm overflow-hidden">
                    <!-- Toggle trigger row -->
                    <button
                        type="button"
                        @click="showOwnerSchedule = !showOwnerSchedule"
                        class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-muted/30 transition-colors group"
                    >
                        <div class="flex items-center gap-3 text-xs flex-wrap">
                            <div class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ ownerScheduleSummary.active }} / {{ ownerScheduleSummary.totalStaff }} Staff Active Now
                                </span>
                            </div>
                            <span class="text-border">|</span>
                            <div v-if="ownerScheduleSummary.onBreak > 0" class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-amber-500"></span>
                                <span class="font-semibold text-amber-600">{{ ownerScheduleSummary.onBreak }} On Break</span>
                                <span class="text-border">|</span>
                            </div>
                            <div v-if="ownerScheduleSummary.absent > 0" class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-rose-500"></span>
                                <span class="font-semibold text-rose-600">{{ ownerScheduleSummary.absent }} Absent Exception</span>
                                <span class="text-border">|</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-sky-500"></span>
                                <span class="font-medium text-sky-700 dark:text-sky-400">3 Branches Active</span>
                            </div>
                            <span class="hidden md:inline text-border">|</span>
                            <div class="hidden md:flex items-center gap-2">
                                <span class="text-muted-foreground">Combined Staff Target:</span>
                                <span class="font-mono font-bold text-primary">{{ formatCurrency(ownerScheduleSummary.totalSales) }} / {{ formatCurrency(ownerScheduleSummary.totalTarget) }}</span>
                                <Badge variant="outline" class="text-[10px] py-0 font-mono text-emerald-600 border-emerald-500/30">
                                    {{ ownerScheduleSummary.targetPercent }}%
                                </Badge>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[11px] font-medium text-muted-foreground group-hover:text-foreground transition-colors flex items-center gap-1">
                                <Users class="size-3.5 text-primary" />
                                <span>{{ showOwnerSchedule ? 'Hide Schedule' : 'Branch Staff & Clock-In Tracker' }}</span>
                            </span>
                            <div class="size-5 rounded-md bg-muted/60 flex items-center justify-center transition-transform duration-200" :class="showOwnerSchedule ? 'rotate-180' : ''">
                                <ChevronRight class="size-3 rotate-90" />
                            </div>
                        </div>
                    </button>

                    <!-- Collapsible owner schedule body -->
                    <transition name="collapse">
                        <div v-if="showOwnerSchedule" class="border-t border-border/60 px-4 pb-4 pt-3 space-y-4">
                            <!-- Filter & Search Controls Bar -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-muted/30 p-3 rounded-lg border border-border/60">
                                <!-- Branch filter buttons (only shown in multi-branch mode) -->
                                <div v-if="isMultiBranch && ownerBranchTabs.length > 2" class="flex items-center gap-1.5 overflow-x-auto scrollbar-none text-xs">
                                    <span class="text-muted-foreground font-semibold flex items-center gap-1 shrink-0 mr-1">
                                        <Building2 class="size-3.5 text-primary" /> Branch:
                                    </span>
                                    <button
                                        v-for="b in ownerBranchTabs"
                                        :key="b.id"
                                        type="button"
                                        @click="ownerBranchFilter = b.id"
                                        :class="[
                                            'px-2.5 py-1 rounded-md font-semibold text-xs whitespace-nowrap transition-colors',
                                            ownerBranchFilter === b.id
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-card border border-border/80 text-muted-foreground hover:text-foreground',
                                        ]"
                                    >
                                        {{ b.name }}
                                    </button>
                                </div>

                                <!-- Search Input -->
                                <div class="relative min-w-[200px] sm:w-64">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                    <input
                                        v-model="ownerStaffSearch"
                                        type="text"
                                        placeholder="Search staff, role, terminal..."
                                        class="w-full pl-8 pr-3 py-1 bg-card border border-border/80 rounded-md text-xs placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                                    />
                                </div>
                            </div>

                            <!-- Branch Attendance & Sales Summary Cards (only in multi-branch mode) -->
                            <div v-if="isMultiBranch && props.branches && props.branches.length > 1" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div v-for="b in props.branches" :key="b.id" class="rounded-xl border border-border/80 bg-card p-3.5 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground font-medium flex items-center gap-1.5">
                                            <Building2 class="size-3.5 text-primary" /> {{ b.name }}
                                        </span>
                                        <Badge variant="outline" class="text-[10px] py-0">{{ b.status || 'Active' }}</Badge>
                                    </div>
                                    <div class="mt-2 flex items-baseline justify-between">
                                        <div class="text-base font-bold font-mono text-foreground">{{ formatCurrency(b.today_sales ?? 0) }}</div>
                                        <span class="text-[11px] text-muted-foreground">Today's Sales</span>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="isDemo && (!props.branches || props.branches.length === 0)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div class="rounded-xl border border-border/80 bg-card p-3.5 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground font-medium flex items-center gap-1.5">
                                            <Building2 class="size-3.5 text-emerald-600" /> Nairobi Central (HQ)
                                        </span>
                                        <Badge variant="success" class="text-[10px] py-0">2 / 3 Present</Badge>
                                    </div>
                                    <div class="mt-2 flex items-baseline justify-between">
                                        <div class="text-base font-bold font-mono text-foreground">{{ formatCurrency(38450) }}</div>
                                        <span class="text-[11px] text-muted-foreground">Target: {{ formatCurrency(215000) }} (17.8%)</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-muted/60 mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: 17.8%"></div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-border/80 bg-card p-3.5 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground font-medium flex items-center gap-1.5">
                                            <Building2 class="size-3.5 text-sky-600" /> Westlands Outlet
                                        </span>
                                        <Badge variant="success" class="text-[10px] py-0">2 / 2 Present</Badge>
                                    </div>
                                    <div class="mt-2 flex items-baseline justify-between">
                                        <div class="text-base font-bold font-mono text-foreground">{{ formatCurrency(62400) }}</div>
                                        <span class="text-[11px] text-muted-foreground">Target: {{ formatCurrency(115000) }} (54.2%)</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-muted/60 mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full bg-sky-500" style="width: 54.2%"></div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-border/80 bg-card p-3.5 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground font-medium flex items-center gap-1.5">
                                            <Building2 class="size-3.5 text-indigo-600" /> Mombasa Road
                                        </span>
                                        <Badge variant="success" class="text-[10px] py-0">2 / 2 Present</Badge>
                                    </div>
                                    <div class="mt-2 flex items-baseline justify-between">
                                        <div class="text-base font-bold font-mono text-foreground">{{ formatCurrency(38800) }}</div>
                                        <span class="text-[11px] text-muted-foreground">Target: {{ formatCurrency(85000) }} (45.6%)</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-muted/60 mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: 45.6%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Staff Attendance & Target Table -->
                            <div class="rounded-xl border border-border/80 bg-card overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-muted/40 text-muted-foreground font-semibold border-b border-border/60">
                                            <tr>
                                                <th class="py-2.5 px-3.5">Staff Member</th>
                                                <th class="py-2.5 px-3">Branch & Terminal</th>
                                                <th class="py-2.5 px-3">Scheduled Shift</th>
                                                <th class="py-2.5 px-3">Clock-In Session</th>
                                                <th class="py-2.5 px-3">Today's Sales vs Target</th>
                                                <th class="py-2.5 px-3 text-right">Orders Processed</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border/60">
                                            <tr
                                                v-for="staff in filteredOwnerStaff"
                                                :key="staff.id"
                                                class="hover:bg-muted/20 transition-colors"
                                            >
                                                <!-- Staff Member -->
                                                <td class="py-3 px-3.5">
                                                    <div class="flex items-center gap-2.5">
                                                        <div class="size-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0">
                                                            {{ staff.avatar }}
                                                        </div>
                                                        <div>
                                                            <p class="font-bold text-foreground">{{ staff.name }}</p>
                                                            <p class="text-[11px] text-muted-foreground">{{ staff.role }}</p>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Branch & Terminal -->
                                                <td class="py-3 px-3">
                                                    <p class="font-medium text-foreground">{{ staff.branchName }}</p>
                                                    <Badge variant="outline" class="font-mono text-[10px] py-0 mt-0.5">{{ staff.terminal }}</Badge>
                                                </td>

                                                <!-- Scheduled Shift -->
                                                <td class="py-3 px-3">
                                                    <p class="font-medium text-foreground">{{ staff.shiftName }}</p>
                                                    <p class="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                                        <Clock class="size-3" /> {{ staff.shiftTime }}
                                                    </p>
                                                </td>

                                                <!-- Clock-In Session -->
                                                <td class="py-3 px-3">
                                                    <!-- Clocked In Active -->
                                                    <div v-if="staff.status === 'clocked_in'" class="space-y-0.5">
                                                        <div class="flex items-center gap-1.5">
                                                            <Badge class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 text-[10px] py-0">
                                                                <span class="size-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse inline-block"></span>
                                                                Active ({{ staff.clockInTime }})
                                                            </Badge>
                                                        </div>
                                                        <span :class="['text-[10px]', staff.punctuality === 'Late' ? 'text-amber-600 font-semibold' : 'text-muted-foreground']">
                                                            {{ staff.punctuality === 'Late' ? `Late +${staff.lateMinutes}m` : staff.punctuality }}
                                                        </span>
                                                    </div>

                                                    <!-- On Break -->
                                                    <div v-else-if="staff.status === 'on_break'" class="space-y-0.5">
                                                        <Badge class="bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30 text-[10px] py-0 flex items-center gap-1 w-fit">
                                                            <Coffee class="size-3" /> On Break
                                                        </Badge>
                                                        <p class="text-[10px] text-muted-foreground">Clocked: {{ staff.clockInTime }}</p>
                                                    </div>

                                                    <!-- Scheduled (Upcoming) -->
                                                    <div v-else-if="staff.status === 'scheduled'" class="space-y-0.5">
                                                        <Badge variant="outline" class="text-muted-foreground text-[10px] py-0">
                                                            Scheduled
                                                        </Badge>
                                                        <p class="text-[10px] text-muted-foreground">Starts 12:00 PM</p>
                                                    </div>

                                                    <!-- Absent -->
                                                    <div v-else-if="staff.status === 'absent'" class="space-y-0.5">
                                                        <Badge class="bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/30 text-[10px] py-0">
                                                            Absent (No Clock-In)
                                                        </Badge>
                                                        <p class="text-[10px] text-rose-600 font-medium">Shift unattended</p>
                                                    </div>
                                                </td>

                                                <!-- Today's Sales vs Target -->
                                                <td class="py-3 px-3">
                                                    <div class="space-y-1 max-w-[180px]">
                                                        <div class="flex items-center justify-between text-xs">
                                                            <span class="font-bold font-mono text-foreground">{{ formatCurrency(staff.salesToday) }}</span>
                                                            <span class="text-[11px] text-muted-foreground">/ {{ formatCurrency(staff.salesTarget) }}</span>
                                                        </div>
                                                        <div class="h-1.5 w-full rounded-full bg-muted/60 overflow-hidden">
                                                            <div
                                                                :class="[
                                                                    'h-full rounded-full transition-all duration-500',
                                                                    staff.salesToday >= staff.salesTarget ? 'bg-emerald-500' :
                                                                    staff.salesToday > 0 ? 'bg-primary' : 'bg-muted-foreground/30'
                                                                ]"
                                                                :style="{ width: `${staff.salesTarget > 0 ? Math.min(100, Math.round((staff.salesToday / staff.salesTarget) * 100)) : 0}%` }"
                                                            ></div>
                                                        </div>
                                                        <div class="flex items-center justify-between text-[10px] text-muted-foreground">
                                                            <span>{{ staff.salesTarget > 0 ? Math.round((staff.salesToday / staff.salesTarget) * 100) : 0 }}% completed</span>
                                                            <span v-if="staff.salesToday < staff.salesTarget" class="text-amber-600 font-medium">
                                                                {{ formatCurrency(staff.salesTarget - staff.salesToday) }} left
                                                            </span>
                                                            <span v-else class="text-emerald-600 font-bold">Goal Met!</span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Orders Processed -->
                                                <td class="py-3 px-3 text-right">
                                                    <p class="font-bold font-mono text-foreground">{{ staff.receiptsCount }} orders</p>
                                                    <p v-if="staff.receiptsCount > 0" class="text-[11px] text-muted-foreground">
                                                        Avg: {{ formatCurrency(staff.avgOrderValue) }}
                                                    </p>
                                                    <p v-else class="text-[11px] text-muted-foreground/50">—</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>

                <!-- Primary KPIs: Revenue / Profit / Transactions / Stock -->
                <div id="dashboard-step-kpis" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Gross Revenue"
                        :value="formatCurrency(ownerKpis.sales)"
                        :change="ownerKpis.salesGrowth"
                        change-text="vs prior period"
                        :icon="TrendingUp"
                        icon-color="text-emerald-600"
                        icon-bg="bg-emerald-500/10"
                    />
                    <StatCard
                        title="Estimated Gross Profit"
                        :value="formatCurrency(ownerKpis.profit)"
                        :change="ownerKpis.profitGrowth"
                        change-text="~29.4% margin"
                        :icon="CircleDollarSign"
                        icon-color="text-amber-600"
                        icon-bg="bg-amber-500/10"
                    />
                    <StatCard
                        title="Total Transactions"
                        :value="ownerKpis.transactions"
                        sub-value="orders"
                        :change="ownerKpis.transGrowth"
                        change-text="vs prior period"
                        :icon="Receipt"
                        icon-color="text-sky-600"
                        icon-bg="bg-sky-500/10"
                    />
                    <StatCard
                        v-if="hasModule('expenses')"
                        title="Operating Expenses"
                        :value="formatCurrency(ownerKpis.expenses)"
                        change-text="incl. payroll & utilities"
                        :icon="DollarSign"
                        icon-color="text-rose-600"
                        icon-bg="bg-rose-500/10"
                    />
                </div>

                <!-- Secondary KPIs: Balance Sheet -->
                <div id="dashboard-step-balances" class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-xs">
                    <div v-if="hasModule('customers')" class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
                        <span class="text-muted-foreground font-medium">Customer Receivables</span>
                        <div class="mt-1 text-lg font-bold font-mono text-amber-600">{{ formatCurrency(ownerKpis.custBalance) }}</div>
                        <p class="text-[11px] text-muted-foreground">outstanding credit accounts</p>
                    </div>
                    <div v-if="hasModule('suppliers') || hasModule('purchases')" class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
                        <span class="text-muted-foreground font-medium">Supplier Payables</span>
                        <div class="mt-1 text-lg font-bold font-mono text-rose-600">{{ formatCurrency(ownerKpis.suppBalance) }}</div>
                        <p class="text-[11px] text-muted-foreground">due to suppliers</p>
                    </div>
                    <div class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
                        <span class="text-muted-foreground font-medium">Stock Valuation</span>
                        <div class="mt-1 text-lg font-bold font-mono text-foreground">{{ formatCurrency(ownerKpis.stockValue) }}</div>
                        <p class="text-[11px] text-muted-foreground">at purchase cost</p>
                    </div>
                    <div v-if="hasModule('customers')" class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
                        <span class="text-muted-foreground font-medium">Active Customers</span>
                        <div class="mt-1 text-lg font-bold font-mono text-primary">{{ ownerKpis.customers.toLocaleString() }}</div>
                        <p class="text-[11px] text-muted-foreground">registered accounts</p>
                    </div>
                </div>

                <!-- Chart + Payment Mix -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Sales Velocity Chart (2 cols) -->
                    <Card id="dashboard-step-chart" class="lg:col-span-2 border-border/80">
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <div>
                                <CardTitle class="text-base font-bold">Branch Sales Trajectory ({{ ownerTimeFilter }})</CardTitle>
                                <CardDescription class="text-xs">All-cashier combined hourly volume across {{ currentBranch.name }}</CardDescription>
                            </div>
                            <Badge variant="outline" class="font-mono text-xs text-emerald-600 bg-emerald-500/10 border-emerald-500/20">
                                +12.8% Avg Basket
                            </Badge>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="h-56 w-full flex items-end justify-between gap-2 pt-6 pb-2 px-2 border-b border-border/60">
                                <div
                                    v-for="(bar, idx) in ownerHourlyBars"
                                    :key="idx"
                                    class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end"
                                >
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-7 text-[10px] font-mono font-bold bg-primary text-primary-foreground px-1.5 py-0.5 rounded shadow whitespace-nowrap z-10">
                                        {{ bar.val }}
                                    </div>
                                    <div
                                        class="w-full rounded-t-md bg-primary/20 group-hover:bg-primary transition-all duration-300 relative overflow-hidden"
                                        :style="{ height: bar.h }"
                                    >
                                        <div class="absolute inset-0 bg-gradient-to-t from-transparent to-primary/40"></div>
                                    </div>
                                    <span class="text-[10px] font-mono text-muted-foreground mt-1">{{ bar.label }}</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2.5 rounded-full bg-primary"></span>
                                    Hourly Sales Volume (KES) — All Cashiers
                                </span>
                                <span class="font-medium">Peak: 17:00–18:00 (Evening Rush)</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Payment Channels -->
                    <Card id="dashboard-step-payments" class="border-border/80">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base font-bold">Payment Channels</CardTitle>
                            <CardDescription class="text-xs">M-Pesa, Cash, Card & Credit split</CardDescription>
                        </CardHeader>
                        <CardContent class="pt-2 space-y-4">
                            <div v-for="(item, idx) in paymentMix" :key="idx" class="space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <component :is="item.icon" class="size-3.5 text-muted-foreground" />
                                        <span class="font-medium">{{ item.method }}</span>
                                    </div>
                                    <div class="text-right font-mono">
                                        <span class="font-bold">{{ formatCurrency(item.amount) }}</span>
                                        <span class="text-muted-foreground ml-1">({{ item.percent }}%)</span>
                                    </div>
                                </div>
                                <div class="h-2 w-full rounded-full bg-muted/60 overflow-hidden">
                                    <div :class="['h-full rounded-full transition-all duration-500', item.color]" :style="{ width: `${item.percent}%` }"></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Multi-Branch Performance (Owner only, below management level) -->
                <Card v-if="can('branches', 'view')" id="dashboard-step-branches" class="border-border/80">
                    <CardHeader class="pb-3 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <Building2 class="size-4 text-sky-500" />
                                Branch Performance Tracker
                            </CardTitle>
                            <CardDescription class="text-xs">Today's revenue vs targets across all branches</CardDescription>
                        </div>
                        <Link href="/branches" class="text-xs text-primary hover:underline font-semibold">Manage Branches</Link>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="divide-y divide-border/60 text-xs">
                            <div v-for="(branch, idx) in branchPerformance" :key="idx" class="p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-foreground">{{ branch.name }}</span>
                                        <Badge :variant="branch.status === 'On Track' ? 'success' : 'warning'" class="text-[10px]">{{ branch.status }}</Badge>
                                    </div>
                                    <div class="font-mono text-right">
                                        <span class="font-bold">{{ formatCurrency(branch.revenue) }}</span>
                                        <span class="text-muted-foreground"> / {{ formatCurrency(branch.target) }}</span>
                                    </div>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-muted/60 overflow-hidden">
                                    <div
                                        :class="['h-full rounded-full transition-all duration-500', branch.status === 'On Track' ? 'bg-emerald-500' : 'bg-amber-500']"
                                        :style="{ width: `${Math.round((branch.revenue / branch.target) * 100)}%` }"
                                    ></div>
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-1">{{ branch.staff }} active staff • {{ Math.round((branch.revenue / branch.target) * 100) }}% of target reached</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Bottom Row: Products / Low Stock / Recent Transactions -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Top Products -->
                    <Card id="dashboard-step-top-products" class="border-border/80">
                        <CardHeader class="pb-3 flex flex-row items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-bold">Top Selling Products</CardTitle>
                                <CardDescription class="text-xs">By volume & revenue (all cashiers)</CardDescription>
                            </div>
                            <Link v-if="can('products', 'view')" href="/products" class="text-xs text-primary hover:underline font-semibold">View Catalog</Link>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="divide-y divide-border/60 text-xs">
                                <div v-for="(p, idx) in topProducts" :key="idx" class="flex items-center justify-between p-3.5 hover:bg-muted/20">
                                    <div class="max-w-[170px] truncate">
                                        <p class="font-semibold text-foreground truncate">{{ p.name }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ p.category }} • {{ p.sold }} sold</p>
                                    </div>
                                    <div class="text-right font-mono">
                                        <p class="font-bold text-foreground">{{ formatCurrency(p.revenue) }}</p>
                                        <p class="text-[11px] text-emerald-600 font-semibold">+{{ formatCurrency(p.profit) }} ({{ p.margin }}%)</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Low Stock -->
                    <Card id="dashboard-step-low-stock" class="border-border/80">
                        <CardHeader class="pb-3 flex flex-row items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-bold flex items-center gap-2">
                                    <AlertTriangle class="size-4 text-amber-500" />
                                    Low Stock Reorder
                                </CardTitle>
                                <CardDescription class="text-xs">Threshold alerts for {{ currentBranch.name }}</CardDescription>
                            </div>
                            <Link v-if="can('inventory', 'view')" href="/inventory" class="text-xs text-primary hover:underline font-semibold">Stock Mgmt</Link>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="divide-y divide-border/60 text-xs">
                                <div v-for="(item, idx) in lowStockItems" :key="idx" class="flex items-center justify-between p-3.5 hover:bg-muted/20">
                                    <div>
                                        <p class="font-semibold text-foreground">{{ item.name }}</p>
                                        <p class="text-[11px] text-muted-foreground">Min stock: {{ item.min }} units</p>
                                    </div>
                                    <Badge :variant="item.status === 'Critical' ? 'destructive' : 'warning'" class="font-mono text-[10px]">
                                        {{ item.current }} Left
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Transactions (All Cashiers) -->
                    <Card id="dashboard-step-recent-sales" class="border-border/80">
                        <CardHeader class="pb-3 flex flex-row items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-bold">Recent Transactions</CardTitle>
                                <CardDescription class="text-xs">All cashiers live feed</CardDescription>
                            </div>
                            <Link href="/sales" class="text-xs text-primary hover:underline font-semibold">Sales Log</Link>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="max-h-[308px] overflow-y-auto divide-y divide-border/60 text-xs scrollbar-thin overscroll-contain">
                                <div v-for="(sale, idx) in recentSales" :key="idx" class="flex items-center justify-between p-3.5 hover:bg-muted/20 transition-colors">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono font-bold text-primary text-[11px]">{{ sale.receipt }}</span>
                                            <span class="text-muted-foreground text-[10px]">{{ sale.time }}</span>
                                        </div>
                                        <p class="text-muted-foreground">{{ sale.customer }} • {{ sale.cashier }} • {{ sale.method }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold font-mono text-foreground">{{ formatCurrency(sale.amount) }}</p>
                                        <Badge :variant="sale.status === 'Completed' ? 'success' : 'destructive'" class="text-[10px] py-0">
                                            {{ sale.status }}
                                        </Badge>
                                    </div>
                                </div>
                                <div v-if="recentSales.length === 0" class="p-6 text-center text-muted-foreground text-xs">
                                    No recent transactions found
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

            </div>
            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- END OWNER / MANAGEMENT DASHBOARD                        -->
            <!-- ═══════════════════════════════════════════════════════ -->

        </div>

        <!-- Interactive Dashboard Guide Overlay with Text Bubbles -->
        <DashboardGuideOverlay
            v-model:open="guideOverlayOpen"
            v-model:current-step="guideCurrentStep"
            :is-cashier="isCashier"
            @action="handleGuideAction"
        />
    </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.collapse-enter-active,
.collapse-leave-active {
    transition: all 0.25s ease-out;
    max-height: 800px;
    opacity: 1;
    overflow: hidden;
}
.collapse-enter-from,
.collapse-leave-to {
    max-height: 0px;
    opacity: 0;
    overflow: hidden;
}
</style>
