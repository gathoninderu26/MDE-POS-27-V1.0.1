<script setup lang="ts">
import { useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Globe, Key, Lock, Radio, Search, ShieldAlert, ShieldCheck, Signal, Smartphone, Wifi } from 'lucide-vue-next';
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
        <div class="mx-auto max-w-[1600px] space-y-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-bold text-zinc-100">
                        <ShieldCheck class="size-5 text-violet-400" />
                        Device & Network Security Engine
                    </h1>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        Platform-wide VPN blocker, Store Wi-Fi & Hotspot restrictions, cellular SIM access gating, and hardware device binding
                        policies.
                    </p>
                </div>
            </div>

            <!-- Threat & Security KPIs -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <ShieldAlert class="size-3.5 text-rose-400" /> VPN Threats Blocked
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-rose-400">19 Attacks</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Blocked VPN & Proxy attempts (24h)</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Smartphone class="size-3.5 text-violet-400" /> Bound POS Hardware
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-violet-400">142 Terminals</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Sunmi, PAX & PC Cashier Desks</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Wifi class="size-3.5 text-emerald-400" /> Store Wi-Fi Nodes
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-emerald-400">38 Routers</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Whitelisted branch access points</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Signal class="size-3.5 text-sky-400" /> Cellular Gated Sessions
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-sky-400">24 Field Sessions</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Safaricom & Airtel Corporate APN</p>
                </div>
            </div>

            <!-- Global Security Defense Policies Grid -->
            <div class="space-y-4 rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Global Network & Hardware Defense Rules</h3>
                    <p class="text-[11px] text-zinc-500">Enable platform-wide enforcement rules automatically inherited by all registered tenants.</p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                        <div class="space-y-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-200">
                                <ShieldAlert class="size-3.5 text-rose-400" />
                                Block VPN & Proxy Tunnels
                            </p>
                            <p class="text-[10px] leading-relaxed text-zinc-500">
                                Automatically drop HTTP & WebSocket connections originating from known VPN ASNs, ExpressVPN, NordVPN, and WireGuard
                                relays.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.globalVpnBlocking"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('VPN & Proxy Guard')"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                        <div class="space-y-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-200">
                                <Wifi class="size-3.5 text-emerald-400" />
                                Mandatory Store Wi-Fi for Cashiers
                            </p>
                            <p class="text-[10px] leading-relaxed text-zinc-500">
                                Cashier role accounts cannot process checkout sales unless connected to an authorized in-store BSSID router subnet.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.requireStoreWifiOnCashiers"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Store Wi-Fi Enforcement')"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                        <div class="space-y-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-200">
                                <Radio class="size-3.5 text-amber-400" />
                                Block Mobile Hotspot Tethering
                            </p>
                            <p class="text-[10px] leading-relaxed text-zinc-500">
                                Detect network TTL hops and personal hotspot broadcasting to prevent running cashier registers off unauthorized mobile
                                connections.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.hotspotDetection"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Mobile Hotspot Blocker')"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                        <div class="space-y-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-200">
                                <Key class="size-3.5 text-violet-400" />
                                Hardware Device Fingerprint Binding
                            </p>
                            <p class="text-[10px] leading-relaxed text-zinc-500">
                                Every POS terminal registers a cryptographic device token based on MAC, CPU serial, and screen resolution.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.hardwareDeviceFingerprinting"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Hardware Device Fingerprinting')"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                        <div class="space-y-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-200">
                                <Lock class="size-3.5 text-sky-400" />
                                Single-Terminal Session Lock
                            </p>
                            <p class="text-[10px] leading-relaxed text-zinc-500">
                                Prevent sharing cashier login pins across multiple devices; subsequent logins immediately terminate prior sessions.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.singleSessionEnforcement"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Single Session Lock')"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-3.5">
                        <div class="space-y-1">
                            <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-200">
                                <Globe class="size-3.5 text-emerald-400" />
                                Tor Exit Node Quarantine
                            </p>
                            <p class="text-[10px] leading-relaxed text-zinc-500">
                                Real-time dynamic blocklist synchronization with Tor project exit relay directory.
                            </p>
                        </div>
                        <input
                            v-model="platformSecurity.torExitNodeBlock"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="handleToggleGlobalPolicy('Tor Exit Node Guard')"
                        />
                    </div>
                </div>
            </div>

            <!-- Cross-Tenant Security Audit Log Table -->
            <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="flex flex-col gap-3 border-b border-zinc-800 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Cross-Tenant Network & Intrusion Log</h3>
                        <p class="text-[11px] text-zinc-500">
                            Live surveillance stream of VPN interceptions, unapproved device logins, and verified terminals.
                        </p>
                    </div>

                    <div class="relative w-72">
                        <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-500" />
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
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Timestamp / Log ID</th>
                                <th class="px-4 py-3 font-semibold uppercase">Tenant & User</th>
                                <th class="px-4 py-3 font-semibold uppercase">Device & OS</th>
                                <th class="px-4 py-3 font-semibold uppercase">Network & Connection</th>
                                <th class="px-4 py-3 font-semibold uppercase">Threat / Detection</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Status</th>
                                <th class="px-4 py-3 text-right font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="log in filteredLogs" :key="log.id" class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-5 py-3.5">
                                    <div class="font-mono font-bold text-zinc-200">{{ log.id }}</div>
                                    <div class="text-[10px] text-zinc-500">{{ log.timestamp }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-zinc-100">{{ log.userName }}</div>
                                    <div class="text-[10px] text-zinc-400">{{ log.tenantName }}</div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-medium text-zinc-300">{{ log.device }}</div>
                                    <span class="mt-0.5 inline-block rounded bg-zinc-800 px-1.5 py-0 font-mono text-[9px] text-zinc-400">
                                        {{ log.deviceCategory }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="mb-0.5 flex items-center gap-1.5">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-bold',
                                                log.networkType === 'Store Wi-Fi (Verified)'
                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                                    : log.networkType === 'Cellular SIM (4G/5G)'
                                                      ? 'border-sky-500/20 bg-sky-500/10 text-sky-300'
                                                      : log.networkType === 'VPN / Proxy'
                                                        ? 'border-rose-500/20 bg-rose-500/10 text-rose-300'
                                                        : 'border-amber-500/20 bg-amber-500/10 text-amber-300',
                                            ]"
                                        >
                                            <Wifi v-if="log.networkType === 'Store Wi-Fi (Verified)'" class="size-3" />
                                            <Signal v-else-if="log.networkType === 'Cellular SIM (4G/5G)'" class="size-3" />
                                            <ShieldAlert v-else-if="log.networkType === 'VPN / Proxy'" class="size-3" />
                                            <Radio v-else class="size-3" />
                                            {{ log.networkType }}
                                        </span>
                                    </div>
                                    <p class="font-mono text-[10px] text-zinc-500">{{ log.ipAddress }}</p>
                                </td>

                                <td class="px-4 py-3.5">
                                    <p class="text-xs font-semibold text-zinc-300">{{ log.threatType }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ log.actionTaken }}</p>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'rounded border px-2 py-0.5 text-[10px] font-bold',
                                            log.status === 'Passed'
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                                : log.status === 'Blocked'
                                                  ? 'border-rose-500/20 bg-rose-500/10 text-rose-300'
                                                  : 'border-zinc-700 bg-zinc-800 text-zinc-400',
                                        ]"
                                    >
                                        {{ log.status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <button
                                        v-if="log.status === 'Blocked'"
                                        type="button"
                                        class="rounded bg-zinc-800 px-2 py-1 text-[10px] font-semibold text-zinc-300 transition-colors hover:bg-emerald-600/10 hover:text-emerald-400"
                                        @click="handleClearQuarantine(log)"
                                    >
                                        Resolve
                                    </button>
                                    <span v-else class="text-[10px] text-zinc-600">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
