<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Ban,
    CheckCircle2,
    Clock,
    Globe,
    Key,
    Laptop,
    Lock,
    Monitor,
    Radio,
    Router,
    Search,
    Shield,
    ShieldAlert,
    ShieldBan,
    ShieldCheck,
    Signal,
    Smartphone,
    Tablet,
    Wifi,
    WifiOff,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { tenants } = useSuperAdmin();
const { success, warning, error, info } = useToast();

const searchQuery = ref('');

// Platform global security switches
const platformSecurity = ref({
    globalVpnBlocking: true,
    torExitNodeBlock: true,
    hotspotDetection: true,
    hardwareDeviceFingerprinting: true,
    singleSessionEnforcement: true,
    requireStoreWifiOnCashiers: true,
    geoFencingRadiusKM: 5,
});

// Platform security audit log
const securityAuditLogs = ref([
    {
        id: 'LOG-4491',
        timestamp: 'Today, 11:22 AM',
        tenantName: 'Westlands Electronics',
        userName: 'Hassan Ali (Accountant)',
        device: 'iPhone 15 Pro (Personal Phone)',
        deviceCategory: 'Smartphone',
        networkType: 'VPN / Proxy',
        ipAddress: '185.220.101.5 (NordVPN Exit Node)',
        threatType: 'Commercial VPN Detected',
        actionTaken: 'Session Terminated & Account Alert Dispatched',
        status: 'Blocked',
    },
    {
        id: 'LOG-4490',
        timestamp: 'Today, 10:45 AM',
        tenantName: 'Nairobi Fresh Mart',
        userName: 'John Kamau (Cashier)',
        device: 'Android Generic Phone (Tecno Spark 10)',
        deviceCategory: 'Smartphone',
        networkType: 'Mobile Hotspot',
        ipAddress: '197.232.14.88 (Mobile Tether)',
        threatType: 'Unbound Device on Personal Hotspot',
        actionTaken: 'Login Rejected (Store Wi-Fi Required)',
        status: 'Blocked',
    },
    {
        id: 'LOG-4489',
        timestamp: 'Today, 09:15 AM',
        tenantName: 'Sunset Pharmacy Ltd',
        userName: 'Grace Wanjiku (Cashier)',
        device: 'Sunmi V2 Pro Smart POS (Register #01)',
        deviceCategory: 'POS Terminal',
        networkType: 'Store Wi-Fi (Verified)',
        ipAddress: '192.168.10.15 (Sunset LAN)',
        threatType: 'Legitimate Store Network & Bound Hardware',
        actionTaken: 'Session Authorized (Kiosk Mode)',
        status: 'Passed',
    },
    {
        id: 'LOG-4488',
        timestamp: 'Today, 08:30 AM',
        tenantName: 'ABC Wholesale Ltd',
        userName: 'Kevin Otieno (Inventory)',
        device: 'Samsung Galaxy Tab Active 4 Pro',
        deviceCategory: 'Tablet',
        networkType: 'Cellular SIM (4G/5G)',
        ipAddress: '105.161.44.82 (Safaricom APN)',
        threatType: 'Field Role Cellular Session',
        actionTaken: 'Authorized via Corporate APN',
        status: 'Passed',
    },
    {
        id: 'LOG-4487',
        timestamp: 'Yesterday, 07:12 PM',
        tenantName: 'Coast Hardware & Steel',
        userName: 'Ali Mohamed (Cashier)',
        device: 'Windows Laptop (Personal PC)',
        deviceCategory: 'Desktop PC',
        networkType: 'VPN / Proxy',
        ipAddress: '194.26.29.112 (ExpressVPN Germany)',
        threatType: 'Offshore IP on Domestic POS Endpoint',
        actionTaken: 'Terminal Quarantined',
        status: 'Blocked',
    },
]);

const filteredLogs = computed(() => {
    return securityAuditLogs.value.filter((l) => {
        return (
            !searchQuery.value ||
            l.tenantName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            l.userName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            l.device.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            l.threatType.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            l.ipAddress.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    });
});

const handleToggleGlobalPolicy = (policyName: string) => {
    success('Platform Policy Updated', `Global security defense rule "${policyName}" updated across all tenant nodes.`);
};

const handleClearQuarantine = (log: any) => {
    log.status = 'Resolved';
    info('Threat Resolved', `Quarantine cleared for ${log.userName} (${log.tenantName}).`);
};
</script>

<template>
    <Head title="Super Admin — Device & Network Security Policies" />
    <SuperAdminLayout>
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                        <ShieldCheck class="size-5 text-violet-400" />
                        Device & Network Security Engine
                    </h1>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Platform-wide VPN blocker, Store Wi-Fi & Hotspot restrictions, cellular SIM access gating, and hardware device binding policies.
                    </p>
                </div>
            </div>

            <!-- Threat & Security KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <ShieldAlert class="size-3.5 text-rose-400" /> VPN Threats Blocked
                    </div>
                    <p class="text-2xl font-extrabold text-rose-400 font-mono">19 Attacks</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Blocked VPN & Proxy attempts (24h)</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <Smartphone class="size-3.5 text-violet-400" /> Bound POS Hardware
                    </div>
                    <p class="text-2xl font-extrabold text-violet-400 font-mono">142 Terminals</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Sunmi, PAX & PC Cashier Desks</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <Wifi class="size-3.5 text-emerald-400" /> Store Wi-Fi Nodes
                    </div>
                    <p class="text-2xl font-extrabold text-emerald-400 font-mono">38 Routers</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Whitelisted branch access points</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <Signal class="size-3.5 text-sky-400" /> Cellular Gated Sessions
                    </div>
                    <p class="text-2xl font-extrabold text-sky-400 font-mono">24 Field Sessions</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Safaricom & Airtel Corporate APN</p>
                </div>
            </div>

            <!-- Global Security Defense Policies Grid -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Global Network & Hardware Defense Rules</h3>
                    <p class="text-[11px] text-zinc-500">Enable platform-wide enforcement rules automatically inherited by all registered tenants.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-950/60 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-zinc-200 flex items-center gap-1.5">
                                <ShieldAlert class="size-3.5 text-rose-400" />
                                Block VPN & Proxy Tunnels
                            </p>
                            <p class="text-[10px] text-zinc-500 leading-relaxed">
                                Automatically drop HTTP & WebSocket connections originating from known VPN ASNs, ExpressVPN, NordVPN, and WireGuard relays.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.globalVpnBlocking"
                            type="checkbox"
                            class="size-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('VPN & Proxy Guard')"
                        />
                    </div>

                    <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-950/60 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-zinc-200 flex items-center gap-1.5">
                                <Wifi class="size-3.5 text-emerald-400" />
                                Mandatory Store Wi-Fi for Cashiers
                            </p>
                            <p class="text-[10px] text-zinc-500 leading-relaxed">
                                Cashier role accounts cannot process checkout sales unless connected to an authorized in-store BSSID router subnet.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.requireStoreWifiOnCashiers"
                            type="checkbox"
                            class="size-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Store Wi-Fi Enforcement')"
                        />
                    </div>

                    <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-950/60 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-zinc-200 flex items-center gap-1.5">
                                <Radio class="size-3.5 text-amber-400" />
                                Block Mobile Hotspot Tethering
                            </p>
                            <p class="text-[10px] text-zinc-500 leading-relaxed">
                                Detect network TTL hops and personal hotspot broadcasting to prevent running cashier registers off unauthorized mobile connections.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.hotspotDetection"
                            type="checkbox"
                            class="size-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Mobile Hotspot Blocker')"
                        />
                    </div>

                    <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-950/60 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-zinc-200 flex items-center gap-1.5">
                                <Key class="size-3.5 text-violet-400" />
                                Hardware Device Fingerprint Binding
                            </p>
                            <p class="text-[10px] text-zinc-500 leading-relaxed">
                                Every POS terminal registers a cryptographic device token based on MAC, CPU serial, and screen resolution.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.hardwareDeviceFingerprinting"
                            type="checkbox"
                            class="size-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Hardware Device Fingerprinting')"
                        />
                    </div>

                    <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-950/60 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-zinc-200 flex items-center gap-1.5">
                                <Lock class="size-3.5 text-sky-400" />
                                Single-Terminal Session Lock
                            </p>
                            <p class="text-[10px] text-zinc-500 leading-relaxed">
                                Prevent sharing cashier login pins across multiple devices; subsequent logins immediately terminate prior sessions.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.singleSessionEnforcement"
                            type="checkbox"
                            class="size-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Single Session Lock')"
                        />
                    </div>

                    <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-950/60 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-zinc-200 flex items-center gap-1.5">
                                <Globe class="size-3.5 text-emerald-400" />
                                Tor Exit Node Quarantine
                            </p>
                            <p class="text-[10px] text-zinc-500 leading-relaxed">
                                Real-time dynamic blocklist synchronization with Tor project exit relay directory.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.torExitNodeBlock"
                            type="checkbox"
                            class="size-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Tor Exit Node Guard')"
                        />
                    </div>
                </div>
            </div>

            <!-- Cross-Tenant Security Audit Log Table -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-zinc-800">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Cross-Tenant Network & Intrusion Log</h3>
                        <p class="text-[11px] text-zinc-500">Live surveillance stream of VPN interceptions, unapproved device logins, and verified terminals.</p>
                    </div>

                    <div class="relative w-72">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-500" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Filter by user, IP, tenant, device..."
                            class="h-8 w-full rounded-lg border border-zinc-700 bg-zinc-800 pl-8 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-800/60 text-zinc-400 border-b border-zinc-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Timestamp / Log ID</th>
                                <th class="px-4 py-3 font-semibold uppercase">Tenant & User</th>
                                <th class="px-4 py-3 font-semibold uppercase">Device & OS</th>
                                <th class="px-4 py-3 font-semibold uppercase">Network & Connection</th>
                                <th class="px-4 py-3 font-semibold uppercase">Threat / Detection</th>
                                <th class="px-4 py-3 font-semibold uppercase text-center">Status</th>
                                <th class="px-4 py-3 font-semibold uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-zinc-800/40 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-zinc-200 font-mono">{{ log.id }}</div>
                                    <div class="text-[10px] text-zinc-500">{{ log.timestamp }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-zinc-100">{{ log.userName }}</div>
                                    <div class="text-[10px] text-zinc-400">{{ log.tenantName }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-medium text-zinc-300">{{ log.device }}</div>
                                    <span class="inline-block text-[9px] px-1.5 py-0 rounded bg-zinc-800 text-zinc-400 font-mono mt-0.5">
                                        {{ log.deviceCategory }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded border',
                                                log.networkType === 'Store Wi-Fi (Verified)' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' :
                                                log.networkType === 'Cellular SIM (4G/5G)' ? 'bg-sky-500/10 text-sky-300 border-sky-500/20' :
                                                log.networkType === 'VPN / Proxy' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' :
                                                'bg-amber-500/10 text-amber-300 border-amber-500/20',
                                            ]"
                                        >
                                            <Wifi v-if="log.networkType === 'Store Wi-Fi (Verified)'" class="size-3" />
                                            <Signal v-else-if="log.networkType === 'Cellular SIM (4G/5G)'" class="size-3" />
                                            <ShieldAlert v-else-if="log.networkType === 'VPN / Proxy'" class="size-3" />
                                            <Radio v-else class="size-3" />
                                            {{ log.networkType }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-zinc-500 font-mono">{{ log.ipAddress }}</p>
                                </td>

                                <td class="px-4 py-3.5">
                                    <p class="text-xs font-semibold text-zinc-300">{{ log.threatType }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ log.actionTaken }}</p>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'text-[10px] font-bold px-2 py-0.5 rounded border',
                                            log.status === 'Passed' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' :
                                            log.status === 'Blocked' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' :
                                            'bg-zinc-800 text-zinc-400 border-zinc-700',
                                        ]"
                                    >
                                        {{ log.status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <button
                                        v-if="log.status === 'Blocked'"
                                        type="button"
                                        class="px-2 py-1 rounded text-[10px] font-semibold bg-zinc-800 text-zinc-300 hover:text-emerald-400 hover:bg-emerald-600/10 transition-colors"
                                        @click="handleClearQuarantine(log)"
                                    >
                                        Resolve
                                    </button>
                                    <span v-else class="text-zinc-600 text-[10px]">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
