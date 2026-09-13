<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BackupSnapshot } from '@/composables/useSuperAdmin';
import { useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Archive,
    CheckCircle2,
    Clock,
    Cloud,
    Database,
    Download,
    FolderArchive,
    HardDrive,
    HardDriveDownload,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    Server,
    ShieldAlert,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { tenants, backups, createBackup, deleteBackup, restoreBackup } = useSuperAdmin();
const { success, warning, error, info } = useToast();

const searchQuery = ref('');
const filterType = ref('All');
const backupModal = ref(false);
const restoreModal = ref(false);
const selectedBackup = ref<BackupSnapshot | null>(null);

// Form for new backup
const newBackupTarget = ref('PLATFORM_ALL');
const newBackupType = ref<'Full System' | 'Tenant DB & Media' | 'DB Only'>('Full System');
const newBackupDestination = ref<'AWS S3 (eu-west-1)' | 'Local Storage' | 'Google Cloud Storage'>('AWS S3 (eu-west-1)');

const totalStorageMB = computed(() => backups.reduce((s, b) => s + b.sizeMB, 0));

const filteredBackups = computed(() => {
    return backups.filter((b) => {
        const matchSearch =
            !searchQuery.value ||
            b.tenantName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            b.fileName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            b.id.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchType = filterType.value === 'All' || b.type === filterType.value;
        return matchSearch && matchType;
    });
});

const handleCreateBackup = () => {
    const created = createBackup(newBackupTarget.value, newBackupType.value, newBackupDestination.value);
    success('Backup Created', `Snapshot ${created.id} (${(created.sizeMB / 1024).toFixed(2)} GB) has been stored on ${created.destination}.`);
    backupModal.value = false;
};

const handleDownload = (b: BackupSnapshot) => {
    info('Download Initialized', `Downloading ${b.fileName} (${(b.sizeMB / 1024).toFixed(2)} GB)...`);
};

const openRestoreModal = (b: BackupSnapshot) => {
    selectedBackup.value = b;
    restoreModal.value = true;
};

const handleExecuteRestore = () => {
    if (!selectedBackup.value) return;
    restoreBackup(selectedBackup.value.id);
    warning('Restore Initiated', `Platform state rolling back to snapshot ${selectedBackup.value.id} (${selectedBackup.value.createdAt}).`);
    restoreModal.value = false;
};

const handleDelete = (b: BackupSnapshot) => {
    deleteBackup(b.id);
    error('Backup Deleted', `Snapshot ${b.id} has been permanently purged from storage.`);
};
</script>

<template>
    <Head title="Super Admin — System & Tenant Backups" />
    <SuperAdminLayout>
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                        <Database class="size-5 text-violet-400" />
                        Disaster Recovery & System Backups
                    </h1>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Automated multi-region snapshots, per-tenant data archives, and point-in-time database restoration.
                    </p>
                </div>

                <Button
                    size="sm"
                    class="h-8 text-xs bg-violet-600 hover:bg-violet-700 text-white gap-1.5"
                    @click="backupModal = true"
                >
                    <Plus class="size-3.5" />
                    Take Snapshot Now
                </Button>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <FolderArchive class="size-3.5 text-violet-400" /> Total Snapshots
                    </div>
                    <p class="text-2xl font-extrabold text-zinc-100 font-mono">{{ backups.length }}</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Stored across S3 & GCS</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <HardDrive class="size-3.5 text-sky-400" /> Total Vault Size
                    </div>
                    <p class="text-2xl font-extrabold text-sky-400 font-mono">{{ (totalStorageMB / 1024).toFixed(1) }} GB</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Compressed encrypted archives</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <Clock class="size-3.5 text-emerald-400" /> Last Auto Schedule
                    </div>
                    <p class="text-2xl font-extrabold text-emerald-400 font-mono">Today, 02:00</p>
                    <p class="text-[10px] text-zinc-600 mt-1">Daily cron schedule @ 02:00 EAT</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex items-center gap-2 text-[11px] text-zinc-500 font-semibold uppercase mb-1">
                        <ShieldCheck class="size-3.5 text-emerald-400" /> Integrity Health
                    </div>
                    <p class="text-2xl font-extrabold text-emerald-400 font-mono">100% Valid</p>
                    <p class="text-[10px] text-zinc-600 mt-1">SHA-256 Checksums verified</p>
                </div>
            </div>

            <!-- Schedule & Storage Policy Card -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 flex items-start gap-3">
                    <div class="size-9 rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center shrink-0">
                        <Cloud class="size-4.5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Cloud Storage Bucket</p>
                        <p class="text-[11px] text-zinc-500 font-mono mt-0.5">s3://mde-pos-vault-kenya-primary</p>
                        <span class="inline-block mt-1 text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded">
                            AES-256 Encrypted
                        </span>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 flex items-start gap-3">
                    <div class="size-9 rounded-xl bg-sky-600/20 text-sky-400 flex items-center justify-center shrink-0">
                        <RefreshCw class="size-4.5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Automated Cadence</p>
                        <p class="text-[11px] text-zinc-500 mt-0.5">Daily full DB snapshot + hourly transaction log shipping</p>
                        <span class="inline-block mt-1 text-[10px] font-bold text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded">
                            Zero-Downtime Hot Backup
                        </span>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 flex items-start gap-3">
                    <div class="size-9 rounded-xl bg-amber-600/20 text-amber-400 flex items-center justify-center shrink-0">
                        <Server class="size-4.5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Retention & Purge Policy</p>
                        <p class="text-[11px] text-zinc-500 mt-0.5">30-day rolling daily snapshots · 12-month monthly archives</p>
                        <span class="inline-block mt-1 text-[10px] font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded">
                            Auto-Lifecycle Active
                        </span>
                    </div>
                </div>
            </div>

            <!-- Backups Table -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-zinc-800">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Snapshot Repository</h3>
                        <p class="text-[11px] text-zinc-500">List of full system and individual tenant backup points ready for download or restore.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1 text-xs">
                            <span class="text-zinc-500 text-[11px]">Type:</span>
                            <button
                                v-for="t in ['All', 'Full System', 'Tenant DB & Media', 'DB Only']"
                                :key="t"
                                type="button"
                                :class="[
                                    'px-2 py-0.5 rounded border text-[10px] font-semibold',
                                    filterType === t ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-700 text-zinc-400 hover:text-zinc-200',
                                ]"
                                @click="filterType = t"
                            >
                                {{ t }}
                            </button>
                        </div>

                        <div class="relative w-56">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-500" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search snapshots..."
                                class="h-8 w-full rounded-lg border border-zinc-700 bg-zinc-800 pl-8 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-800/60 text-zinc-400 border-b border-zinc-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Snapshot / Archive</th>
                                <th class="px-4 py-3 font-semibold uppercase">Target Scope</th>
                                <th class="px-4 py-3 font-semibold uppercase text-center">Type</th>
                                <th class="px-4 py-3 font-semibold uppercase text-right">Size</th>
                                <th class="px-4 py-3 font-semibold uppercase">Destination</th>
                                <th class="px-4 py-3 font-semibold uppercase text-center">Status</th>
                                <th class="px-4 py-3 font-semibold uppercase">Created</th>
                                <th class="px-4 py-3 font-semibold uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="b in filteredBackups" :key="b.id" class="hover:bg-zinc-800/40 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="size-7 rounded bg-violet-600/20 text-violet-400 flex items-center justify-center shrink-0">
                                            <Archive class="size-3.5" />
                                        </div>
                                        <div>
                                            <div class="font-bold text-zinc-100">{{ b.id }}</div>
                                            <div class="text-[10px] text-zinc-500 font-mono truncate max-w-xs">{{ b.fileName }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 text-zinc-300 font-medium">
                                    {{ b.tenantName }}
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'text-[10px] font-bold px-2 py-0.5 rounded border',
                                            b.type === 'Full System' ? 'text-violet-300 bg-violet-600/20 border-violet-600/30' :
                                            b.type === 'Tenant DB & Media' ? 'text-sky-300 bg-sky-600/20 border-sky-600/30' :
                                            'text-amber-300 bg-amber-600/20 border-amber-600/30',
                                        ]"
                                    >
                                        {{ b.type }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-right font-mono font-bold text-zinc-200">
                                    {{ (b.sizeMB / 1024).toFixed(2) }} GB
                                </td>

                                <td class="px-4 py-3.5 text-[11px] text-zinc-400">
                                    <div class="flex items-center gap-1.5">
                                        <Cloud class="size-3 text-sky-400" />
                                        <span>{{ b.destination }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded">
                                        <CheckCircle2 class="size-3" />
                                        {{ b.status }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-zinc-400 text-[11px] whitespace-nowrap">
                                    {{ b.createdAt }}
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-zinc-400 hover:text-sky-400 hover:bg-sky-600/10 transition-colors"
                                            title="Download Snapshot"
                                            @click="handleDownload(b)"
                                        >
                                            <Download class="size-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-zinc-400 hover:text-amber-400 hover:bg-amber-600/10 transition-colors"
                                            title="Restore From Point"
                                            @click="openRestoreModal(b)"
                                        >
                                            <RotateCcw class="size-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-zinc-500 hover:text-rose-400 hover:bg-rose-600/10 transition-colors"
                                            title="Delete Snapshot"
                                            @click="handleDelete(b)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Backup Modal -->
        <Dialog :open="backupModal" @update:open="(v) => (backupModal = v)">
            <DialogContent class="sm:max-w-md bg-zinc-900 border-zinc-700 text-zinc-100">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        <HardDriveDownload class="size-4 text-violet-400" />
                        Create Manual Backup Snapshot
                    </DialogTitle>
                    <DialogDescription class="sr-only">Select target, scope, and storage destination for a new backup snapshot</DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-zinc-400 text-[11px]">Select Backup Target</Label>
                        <select
                            v-model="newBackupTarget"
                            class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                        >
                            <option value="PLATFORM_ALL">All Tenants (Full Platform Disaster Recovery)</option>
                            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.id }})</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-zinc-400 text-[11px]">Snapshot Scope</Label>
                        <select
                            v-model="newBackupType"
                            class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                        >
                            <option value="Full System">Full Database, Ledger & Storage Media</option>
                            <option value="Tenant DB & Media">Tenant Isolated DB Schema + Uploads</option>
                            <option value="DB Only">Structured SQL DB Dump Only (Lightweight)</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-zinc-400 text-[11px]">Storage Destination</Label>
                        <select
                            v-model="newBackupDestination"
                            class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                        >
                            <option value="AWS S3 (eu-west-1)">AWS S3 Primary Vault (eu-west-1)</option>
                            <option value="Google Cloud Storage">Google Cloud Coldline Storage</option>
                            <option value="Local Storage">Local Server Encrypted Disk</option>
                        </select>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="backupModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleCreateBackup">
                        Initiate Snapshot
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Restore Confirmation Modal -->
        <Dialog :open="restoreModal" @update:open="(v) => (restoreModal = v)">
            <DialogContent class="sm:max-w-md bg-zinc-900 border-rose-900/60 text-zinc-100">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold text-rose-400 flex items-center gap-2">
                        <ShieldAlert class="size-4 text-rose-400" />
                        Confirm System Restoration
                    </DialogTitle>
                    <DialogDescription class="sr-only">Confirm rollback of {{ selectedBackup?.tenantName }} to a previous backup snapshot</DialogDescription>
                </DialogHeader>

                <div v-if="selectedBackup" class="space-y-3 py-2 text-xs">
                    <p class="text-zinc-300">
                        You are about to roll back <strong class="text-white">{{ selectedBackup.tenantName }}</strong> to snapshot
                        <strong class="font-mono text-violet-300">{{ selectedBackup.id }}</strong> taken on
                        <strong class="text-white">{{ selectedBackup.createdAt }}</strong>.
                    </p>

                    <div class="p-3 rounded-lg bg-rose-950/40 border border-rose-900/50 text-[11px] text-rose-300 space-y-1">
                        <p class="font-bold">⚠️ Caution: Potential Data Overwrite</p>
                        <p>Any transactions or ledger entries created after this timestamp will be superseded by the restored backup point.</p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="restoreModal = false">Cancel</Button>
                    <Button size="sm" class="bg-rose-600 hover:bg-rose-700 text-white" @click="handleExecuteRestore">
                        Confirm & Restore Now
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
