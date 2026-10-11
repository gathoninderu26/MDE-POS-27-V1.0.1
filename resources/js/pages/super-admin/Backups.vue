<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
        <div class="mx-auto max-w-[1600px] space-y-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-bold text-zinc-100">
                        <Database class="size-5 text-violet-400" />
                        Disaster Recovery & System Backups
                    </h1>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        Automated multi-region snapshots, per-tenant data archives, and point-in-time database restoration.
                    </p>
                </div>

                <Button size="sm" class="h-8 gap-1.5 bg-violet-600 text-xs text-white hover:bg-violet-700" @click="backupModal = true">
                    <Plus class="size-3.5" />
                    Take Snapshot Now
                </Button>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <FolderArchive class="size-3.5 text-violet-400" /> Total Snapshots
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-zinc-100">{{ backups.length }}</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Stored across S3 & GCS</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <HardDrive class="size-3.5 text-sky-400" /> Total Vault Size
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-sky-400">{{ (totalStorageMB / 1024).toFixed(1) }} GB</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Compressed encrypted archives</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <Clock class="size-3.5 text-emerald-400" /> Last Auto Schedule
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-emerald-400">Today, 02:00</p>
                    <p class="mt-1 text-[10px] text-zinc-600">Daily cron schedule @ 02:00 EAT</p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase text-zinc-500">
                        <ShieldCheck class="size-3.5 text-emerald-400" /> Integrity Health
                    </div>
                    <p class="font-mono text-2xl font-extrabold text-emerald-400">100% Valid</p>
                    <p class="mt-1 text-[10px] text-zinc-600">SHA-256 Checksums verified</p>
                </div>
            </div>

            <!-- Schedule & Storage Policy Card -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="flex items-start gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-violet-600/20 text-violet-400">
                        <Cloud class="size-4.5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Cloud Storage Bucket</p>
                        <p class="mt-0.5 font-mono text-[11px] text-zinc-500">s3://mde-pos-vault-kenya-primary</p>
                        <span class="mt-1 inline-block rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-400">
                            AES-256 Encrypted
                        </span>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-sky-600/20 text-sky-400">
                        <RefreshCw class="size-4.5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Automated Cadence</p>
                        <p class="mt-0.5 text-[11px] text-zinc-500">Daily full DB snapshot + hourly transaction log shipping</p>
                        <span class="mt-1 inline-block rounded bg-sky-500/10 px-2 py-0.5 text-[10px] font-bold text-sky-400">
                            Zero-Downtime Hot Backup
                        </span>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-600/20 text-amber-400">
                        <Server class="size-4.5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-zinc-200">Retention & Purge Policy</p>
                        <p class="mt-0.5 text-[11px] text-zinc-500">30-day rolling daily snapshots · 12-month monthly archives</p>
                        <span class="mt-1 inline-block rounded bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-400">
                            Auto-Lifecycle Active
                        </span>
                    </div>
                </div>
            </div>

            <!-- Backups Table -->
            <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="flex flex-col gap-3 border-b border-zinc-800 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Snapshot Repository</h3>
                        <p class="text-[11px] text-zinc-500">
                            List of full system and individual tenant backup points ready for download or restore.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1 text-xs">
                            <span class="text-[11px] text-zinc-500">Type:</span>
                            <button
                                v-for="t in ['All', 'Full System', 'Tenant DB & Media', 'DB Only']"
                                :key="t"
                                type="button"
                                :class="[
                                    'rounded border px-2 py-0.5 text-[10px] font-semibold',
                                    filterType === t
                                        ? 'border-violet-600 bg-violet-600 text-white'
                                        : 'border-zinc-700 text-zinc-400 hover:text-zinc-200',
                                ]"
                                @click="filterType = t"
                            >
                                {{ t }}
                            </button>
                        </div>

                        <div class="relative w-56">
                            <Search class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-500" />
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
                        <thead class="border-b border-zinc-800 bg-zinc-800/60 text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold uppercase">Snapshot / Archive</th>
                                <th class="px-4 py-3 font-semibold uppercase">Target Scope</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Type</th>
                                <th class="px-4 py-3 text-right font-semibold uppercase">Size</th>
                                <th class="px-4 py-3 font-semibold uppercase">Destination</th>
                                <th class="px-4 py-3 text-center font-semibold uppercase">Status</th>
                                <th class="px-4 py-3 font-semibold uppercase">Created</th>
                                <th class="px-4 py-3 text-right font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="b in filteredBackups" :key="b.id" class="transition-colors hover:bg-zinc-800/40">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex size-7 shrink-0 items-center justify-center rounded bg-violet-600/20 text-violet-400">
                                            <Archive class="size-3.5" />
                                        </div>
                                        <div>
                                            <div class="font-bold text-zinc-100">{{ b.id }}</div>
                                            <div class="max-w-xs truncate font-mono text-[10px] text-zinc-500">{{ b.fileName }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 font-medium text-zinc-300">
                                    {{ b.tenantName }}
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'rounded border px-2 py-0.5 text-[10px] font-bold',
                                            b.type === 'Full System'
                                                ? 'border-violet-600/30 bg-violet-600/20 text-violet-300'
                                                : b.type === 'Tenant DB & Media'
                                                  ? 'border-sky-600/30 bg-sky-600/20 text-sky-300'
                                                  : 'border-amber-600/30 bg-amber-600/20 text-amber-300',
                                        ]"
                                    >
                                        {{ b.type }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-right font-mono font-bold text-zinc-200">{{ (b.sizeMB / 1024).toFixed(2) }} GB</td>

                                <td class="px-4 py-3.5 text-[11px] text-zinc-400">
                                    <div class="flex items-center gap-1.5">
                                        <Cloud class="size-3 text-sky-400" />
                                        <span>{{ b.destination }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-400"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        {{ b.status }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3.5 text-[11px] text-zinc-400">
                                    {{ b.createdAt }}
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="rounded p-1.5 text-zinc-400 transition-colors hover:bg-sky-600/10 hover:text-sky-400"
                                            title="Download Snapshot"
                                            @click="handleDownload(b)"
                                        >
                                            <Download class="size-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded p-1.5 text-zinc-400 transition-colors hover:bg-amber-600/10 hover:text-amber-400"
                                            title="Restore From Point"
                                            @click="openRestoreModal(b)"
                                        >
                                            <RotateCcw class="size-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded p-1.5 text-zinc-500 transition-colors hover:bg-rose-600/10 hover:text-rose-400"
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
            <DialogContent class="border-zinc-700 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <HardDriveDownload class="size-4 text-violet-400" />
                        Create Manual Backup Snapshot
                    </DialogTitle>
                    <DialogDescription class="sr-only">Select target, scope, and storage destination for a new backup snapshot</DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-[11px] text-zinc-400">Select Backup Target</Label>
                        <select
                            v-model="newBackupTarget"
                            class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200"
                        >
                            <option value="PLATFORM_ALL">All Tenants (Full Platform Disaster Recovery)</option>
                            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.id }})</option>
                        </select>
                    </div>

                    <div>
                        <Label class="text-[11px] text-zinc-400">Snapshot Scope</Label>
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
                        <Label class="text-[11px] text-zinc-400">Storage Destination</Label>
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
                    <Button size="sm" class="bg-violet-600 text-white hover:bg-violet-700" @click="handleCreateBackup"> Initiate Snapshot </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Restore Confirmation Modal -->
        <Dialog :open="restoreModal" @update:open="(v) => (restoreModal = v)">
            <DialogContent class="border-rose-900/60 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold text-rose-400">
                        <ShieldAlert class="size-4 text-rose-400" />
                        Confirm System Restoration
                    </DialogTitle>
                    <DialogDescription class="sr-only"
                        >Confirm rollback of {{ selectedBackup?.tenantName }} to a previous backup snapshot</DialogDescription
                    >
                </DialogHeader>

                <div v-if="selectedBackup" class="space-y-3 py-2 text-xs">
                    <p class="text-zinc-300">
                        You are about to roll back <strong class="text-white">{{ selectedBackup.tenantName }}</strong> to snapshot
                        <strong class="font-mono text-violet-300">{{ selectedBackup.id }}</strong> taken on
                        <strong class="text-white">{{ selectedBackup.createdAt }}</strong
                        >.
                    </p>

                    <div class="space-y-1 rounded-lg border border-rose-900/50 bg-rose-950/40 p-3 text-[11px] text-rose-300">
                        <p class="font-bold">⚠️ Caution: Potential Data Overwrite</p>
                        <p>Any transactions or ledger entries created after this timestamp will be superseded by the restored backup point.</p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="restoreModal = false">Cancel</Button>
                    <Button size="sm" class="bg-rose-600 text-white hover:bg-rose-700" @click="handleExecuteRestore"> Confirm & Restore Now </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
