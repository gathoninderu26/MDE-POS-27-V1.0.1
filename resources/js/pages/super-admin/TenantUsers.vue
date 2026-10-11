<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    Copy,
    Crown,
    ExternalLink,
    Eye,
    EyeOff,
    KeyRound,
    Lock,
    Mail,
    Phone,
    Plus,
    RefreshCw,
    Search,
    Shield,
    ShieldCheck,
    Trash2,
    User,
    UserCheck,
    UserCog,
    Users,
    Wallet,
    Wrench,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TenantUser {
    id: number;
    name: string;
    email: string;
    role: string;
    phone?: string;
    status: string;
    created_at: string;
    last_login: string;
}

interface TenantInfo {
    id: number;
    name: string;
    slug: string;
    email: string;
    business_type: string;
    plan: string;
    status: string;
    subdomain_url?: string;
}

const props = defineProps<{
    tenant: TenantInfo;
    users: TenantUser[];
}>();

const { success, warning, error, info } = useToast();
const page = usePage();

const copyTenantUrl = (url?: string) => {
    if (!url) return;
    navigator.clipboard.writeText(url);
    success('Copied!', 'Tenant login URL copied to clipboard.');
};

const searchQuery = ref('');

// ── Add User Modal ─────────────────────────────────────────────────────────────
const addModal = ref(false);
const showPassword = ref(false);
const addForm = ref({
    name: '',
    email: '',
    role: 'Cashier',
    phone: '',
    password: '',
});
const addProcessing = ref(false);

// ── Edit User Modal ────────────────────────────────────────────────────────────
const editModal = ref(false);
const editTarget = ref<TenantUser | null>(null);
const editForm = ref({ name: '', role: '', phone: '', status: '' });
const editProcessing = ref(false);

// ── Reset Password Modal ───────────────────────────────────────────────────────
const resetModal = ref(false);
const resetTarget = ref<TenantUser | null>(null);
const resetResultPassword = ref('');
const resetProcessing = ref(false);

// ── Delete Confirm Modal ───────────────────────────────────────────────────────
const deleteModal = ref(false);
const deleteTarget = ref<TenantUser | null>(null);
const deleteProcessing = ref(false);

const allRoles = ['Owner', 'Administrator', 'Manager', 'Supervisor', 'Cashier', 'Inventory Manager', 'Accountant'];

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    const q = searchQuery.value.toLowerCase();
    return props.users.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.role.toLowerCase().includes(q));
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const roleColor: Record<string, string> = {
    Owner: 'bg-violet-600/20 text-violet-300 border-violet-600/30',
    Administrator: 'bg-blue-600/20 text-blue-300 border-blue-600/30',
    Manager: 'bg-emerald-600/20 text-emerald-300 border-emerald-600/30',
    Supervisor: 'bg-amber-600/20 text-amber-300 border-amber-600/30',
    Cashier: 'bg-sky-600/20 text-sky-300 border-sky-600/30',
    'Inventory Manager': 'bg-orange-600/20 text-orange-300 border-orange-600/30',
    Accountant: 'bg-teal-600/20 text-teal-300 border-teal-600/30',
};

const roleIcon: Record<string, any> = {
    Owner: Crown,
    Administrator: Shield,
    Manager: ShieldCheck,
    Supervisor: UserCheck,
    Cashier: User,
    'Inventory Manager': Wrench,
    Accountant: Wallet,
};

const statusBadge = (status: string) =>
    status === 'Active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20';

const planBadge: Record<string, string> = {
    Basic: 'bg-zinc-600/20 text-zinc-300',
    Pro: 'bg-blue-600/20 text-blue-300',
    Enterprise: 'bg-violet-600/20 text-violet-300',
};

const initials = (name: string) =>
    name
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

const avatarColors = ['bg-violet-600', 'bg-blue-600', 'bg-emerald-600', 'bg-amber-600', 'bg-rose-600', 'bg-teal-600', 'bg-sky-600'];
const avatarColor = (id: number) => avatarColors[id % avatarColors.length];

// ── Actions ───────────────────────────────────────────────────────────────────
const handleAdd = () => {
    if (!addForm.value.name || !addForm.value.email || !addForm.value.password) {
        warning('Missing Fields', 'Name, email, and password are required.');
        return;
    }
    addProcessing.value = true;
    router.post(`/super-admin/tenants/${props.tenant.id}/users`, addForm.value, {
        onSuccess: () => {
            success('User Added', `${addForm.value.name} has been added to ${props.tenant.name}.`);
            addModal.value = false;
            addForm.value = { name: '', email: '', role: 'Cashier', phone: '', password: '' };
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (addProcessing.value = false),
    });
};

const openEdit = (user: TenantUser) => {
    editTarget.value = user;
    editForm.value = { name: user.name, role: user.role, phone: user.phone ?? '', status: user.status };
    editModal.value = true;
};

const handleEdit = () => {
    if (!editTarget.value) return;
    editProcessing.value = true;
    router.patch(`/super-admin/tenants/${props.tenant.id}/users/${editTarget.value.id}`, editForm.value, {
        onSuccess: () => {
            success('User Updated', `${editTarget.value!.name} has been updated.`);
            editModal.value = false;
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (editProcessing.value = false),
    });
};

const openReset = (user: TenantUser) => {
    resetTarget.value = user;
    resetResultPassword.value = '';
    resetModal.value = true;
};

const handleReset = () => {
    if (!resetTarget.value) return;
    resetProcessing.value = true;
    router.post(
        `/super-admin/tenants/${props.tenant.id}/users/${resetTarget.value.id}/reset-password`,
        {},
        {
            onSuccess: () => {
                const flash = (page.props as any).flash?.success ?? '';
                const match = flash.match(/Temporary password: (.+)$/);
                resetResultPassword.value = match ? match[1] : 'Check server logs';
                success('Password Reset', `Temporary password generated for ${resetTarget.value!.name}.`);
            },
            onError: () => error('Failed', 'Could not reset password.'),
            onFinish: () => (resetProcessing.value = false),
        },
    );
};

const openDelete = (user: TenantUser) => {
    deleteTarget.value = user;
    deleteModal.value = true;
};

const handleDelete = () => {
    if (!deleteTarget.value) return;
    deleteProcessing.value = true;
    router.delete(`/super-admin/tenants/${props.tenant.id}/users/${deleteTarget.value.id}`, {
        onSuccess: () => {
            success('User Removed', `${deleteTarget.value!.name} has been deleted.`);
            deleteModal.value = false;
        },
        onError: (errs) => error('Failed', Object.values(errs)[0] as string),
        onFinish: () => (deleteProcessing.value = false),
    });
};
</script>

<template>
    <SuperAdminLayout>
        <Head :title="`${tenant.name} — Staff Management`" />

        <div class="space-y-6 p-6">
            <!-- ── Header ─────────────────────────────────────────────────── -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <a
                        href="/super-admin/tenants"
                        class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-400 transition-colors hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                    </a>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-white">{{ tenant.name }}</h1>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', planBadge[tenant.plan] ?? planBadge['Basic']]">
                                {{ tenant.plan }}
                            </span>
                            <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold', statusBadge(tenant.status)]">
                                {{ tenant.status }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-400">
                            <span class="flex items-center gap-1">
                                <Building2 class="size-3 opacity-60" />
                                {{ tenant.business_type }}
                            </span>
                            <span class="text-zinc-600">•</span>
                            <span
                                v-if="tenant.subdomain_url"
                                class="inline-flex items-center gap-1.5 rounded border border-zinc-700/60 bg-zinc-800/80 px-2 py-0.5 font-mono text-[11px] text-violet-300"
                            >
                                <span>{{ tenant.subdomain_url }}</span>
                                <button
                                    type="button"
                                    class="text-zinc-400 transition-colors hover:text-white"
                                    title="Copy login URL"
                                    @click="copyTenantUrl(tenant.subdomain_url)"
                                >
                                    <Copy class="size-3" />
                                </button>
                                <a
                                    :href="tenant.subdomain_url"
                                    target="_blank"
                                    class="text-violet-400 transition-colors hover:text-violet-200"
                                    title="Open tenant login"
                                >
                                    <ExternalLink class="size-3" />
                                </a>
                            </span>
                            <span v-else class="font-mono text-zinc-500">{{ tenant.slug }}</span>
                        </div>
                    </div>
                </div>

                <!-- Stats + Add Button -->
                <div class="flex shrink-0 items-center gap-3">
                    <div class="text-right">
                        <p class="text-2xl font-black text-white">{{ users.length }}</p>
                        <p class="text-[11px] font-semibold text-zinc-400">Staff Members</p>
                    </div>
                    <Button class="h-9 gap-2 bg-violet-600 text-xs font-bold text-white shadow-lg hover:bg-violet-500" @click="addModal = true">
                        <Plus class="size-4" />
                        Add Staff Member
                    </Button>
                </div>
            </div>

            <!-- ── Search ─────────────────────────────────────────────────── -->
            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-zinc-500" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by name, email or role..."
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800/60 py-2 pl-9 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/30"
                />
            </div>

            <!-- ── User Table ──────────────────────────────────────────────── -->
            <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-xl">
                <!-- Table Header -->
                <div
                    class="grid grid-cols-[auto_1fr_1fr_auto_auto_auto] gap-4 border-b border-zinc-800 px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-zinc-500"
                >
                    <span>User</span>
                    <span>Contact</span>
                    <span>Role</span>
                    <span>Status</span>
                    <span>Joined</span>
                    <span class="text-right">Actions</span>
                </div>

                <!-- Empty State -->
                <div v-if="filteredUsers.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                    <Users class="mb-3 size-10 text-zinc-700" />
                    <p class="text-sm font-semibold text-zinc-400">No staff members found</p>
                    <p class="mt-1 text-xs text-zinc-600">
                        {{ searchQuery ? 'Try a different search term.' : 'Add the first staff member to this tenant.' }}
                    </p>
                </div>

                <!-- User Rows -->
                <div
                    v-for="user in filteredUsers"
                    :key="user.id"
                    class="grid grid-cols-[auto_1fr_1fr_auto_auto_auto] items-center gap-4 border-b border-zinc-800/60 px-5 py-3.5 transition-colors last:border-0 hover:bg-zinc-800/30"
                >
                    <!-- Avatar + Name -->
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            :class="[
                                'flex size-9 shrink-0 items-center justify-center rounded-xl text-xs font-black text-white shadow-sm',
                                avatarColor(user.id),
                            ]"
                        >
                            {{ initials(user.name) }}
                        </div>
                        <div class="hidden min-w-0 sm:block">
                            <p class="truncate text-xs font-semibold text-white">{{ user.name }}</p>
                            <p class="truncate font-mono text-[10px] text-zinc-500">ID: {{ user.id }}</p>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="min-w-0">
                        <p class="flex items-center gap-1 truncate text-xs text-zinc-300">
                            <Mail class="size-3 shrink-0 opacity-50" />
                            {{ user.email }}
                        </p>
                        <p v-if="user.phone" class="mt-0.5 flex items-center gap-1 text-[10px] text-zinc-500">
                            <Phone class="size-3 shrink-0 opacity-50" />
                            {{ user.phone }}
                        </p>
                    </div>

                    <!-- Role -->
                    <div>
                        <span
                            :class="[
                                'inline-flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[10px] font-bold',
                                roleColor[user.role] ?? roleColor['Cashier'],
                            ]"
                        >
                            <component :is="roleIcon[user.role] ?? User" class="size-3" />
                            {{ user.role }}
                        </span>
                    </div>

                    <!-- Status -->
                    <div>
                        <span :class="['inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold', statusBadge(user.status)]">
                            {{ user.status }}
                        </span>
                    </div>

                    <!-- Joined -->
                    <div class="whitespace-nowrap font-mono text-[10px] text-zinc-500">
                        {{ user.created_at }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-1.5">
                        <button
                            class="flex size-7 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-400 transition-all hover:border-violet-500 hover:text-violet-400"
                            title="Edit user"
                            @click="openEdit(user)"
                        >
                            <UserCog class="size-3.5" />
                        </button>
                        <button
                            class="flex size-7 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-400 transition-all hover:border-amber-500 hover:text-amber-400"
                            title="Reset password"
                            @click="openReset(user)"
                        >
                            <KeyRound class="size-3.5" />
                        </button>
                        <button
                            class="flex size-7 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-400 transition-all hover:border-rose-500 hover:text-rose-400"
                            title="Delete user"
                            @click="openDelete(user)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════ ADD USER MODAL ════════════════ -->
        <Dialog :open="addModal" @update:open="(v) => (addModal = v)">
            <DialogContent class="border-zinc-800 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Plus class="size-4 text-violet-400" />
                        Add Staff Member to {{ tenant.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only">Add a new staff member to {{ tenant.name }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs text-zinc-400">Full Name *</Label>
                            <Input v-model="addForm.name" placeholder="Jane Mwangi" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs text-zinc-400">Phone</Label>
                            <Input v-model="addForm.phone" placeholder="+254 7xx xxx xxx" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Email Address *</Label>
                        <Input
                            v-model="addForm.email"
                            type="email"
                            placeholder="jane@nairobi-store.mdepos.co.ke"
                            class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                        />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Role *</Label>
                        <select
                            v-model="addForm.role"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                        >
                            <option v-for="role in allRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Temporary Password *</Label>
                        <div class="relative">
                            <Input
                                v-model="addForm.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Min. 8 characters"
                                class="border-zinc-700 bg-zinc-800 pr-9 text-xs text-zinc-100"
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300"
                                @click="showPassword = !showPassword"
                            >
                                <component :is="showPassword ? EyeOff : Eye" class="size-3.5" />
                            </button>
                        </div>
                        <p class="text-[10px] text-zinc-500">User will be prompted to change this on first login.</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="addModal = false">Cancel</Button>
                    <Button class="gap-1.5 bg-violet-600 text-xs text-white hover:bg-violet-500" :disabled="addProcessing" @click="handleAdd">
                        <Plus class="size-3.5" />
                        {{ addProcessing ? 'Adding…' : 'Add Staff Member' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════════ EDIT USER MODAL ════════════════ -->
        <Dialog :open="editModal" @update:open="(v) => (editModal = v)">
            <DialogContent class="border-zinc-800 bg-zinc-900 text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <UserCog class="size-4 text-violet-400" />
                        Edit — {{ editTarget?.name }}
                    </DialogTitle>
                    <DialogDescription class="sr-only">Edit staff member profile, role and status</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Full Name</Label>
                        <Input v-model="editForm.name" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Phone</Label>
                        <Input v-model="editForm.phone" class="border-zinc-700 bg-zinc-800 text-xs text-zinc-100" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Role</Label>
                        <select
                            v-model="editForm.role"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                        >
                            <option v-for="role in allRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs text-zinc-400">Account Status</Label>
                        <select
                            v-model="editForm.status"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 focus:border-violet-500 focus:outline-none"
                        >
                            <option value="Active">Active</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="editModal = false">Cancel</Button>
                    <Button class="gap-1.5 bg-violet-600 text-xs text-white hover:bg-violet-500" :disabled="editProcessing" @click="handleEdit">
                        <RefreshCw class="size-3.5" :class="editProcessing ? 'animate-spin' : ''" />
                        {{ editProcessing ? 'Saving…' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════════ RESET PASSWORD MODAL ════════════════ -->
        <Dialog :open="resetModal" @update:open="(v) => (resetModal = v)">
            <DialogContent class="border-zinc-800 bg-zinc-900 text-zinc-100 sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <KeyRound class="size-4 text-amber-400" />
                        Reset Password
                    </DialogTitle>
                    <DialogDescription class="sr-only">Generate a temporary password for {{ resetTarget?.name }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <p class="text-xs text-zinc-400">
                        Generate a secure temporary password for <strong class="text-white">{{ resetTarget?.name }}</strong
                        >. Share it securely — the user should change it after logging in.
                    </p>
                    <!-- Result -->
                    <div v-if="resetResultPassword" class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3.5 text-center">
                        <p class="mb-1.5 text-[10px] font-bold uppercase tracking-widest text-amber-400">Temporary Password</p>
                        <p class="font-mono text-lg font-black tracking-widest text-white">{{ resetResultPassword }}</p>
                        <p class="mt-1.5 text-[10px] text-zinc-500">Copy this now — it won't be shown again.</p>
                    </div>
                    <div v-else class="rounded-xl border border-zinc-700 bg-zinc-800/50 p-3 text-[11px] text-zinc-400">
                        <Lock class="mr-1 inline size-3.5 text-zinc-500" />
                        Click <strong class="text-white">Generate Password</strong> to proceed. The user's current password will be immediately
                        invalidated.
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="resetModal = false">{{
                        resetResultPassword ? 'Close' : 'Cancel'
                    }}</Button>
                    <Button
                        v-if="!resetResultPassword"
                        class="gap-1.5 bg-amber-600 text-xs text-white hover:bg-amber-500"
                        :disabled="resetProcessing"
                        @click="handleReset"
                    >
                        <KeyRound class="size-3.5" />
                        {{ resetProcessing ? 'Generating…' : 'Generate Password' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ════════════════ DELETE CONFIRM MODAL ════════════════ -->
        <Dialog :open="deleteModal" @update:open="(v) => (deleteModal = v)">
            <DialogContent class="border-zinc-800 bg-zinc-900 text-zinc-100 sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold text-rose-400">
                        <AlertTriangle class="size-4" />
                        Remove Staff Member?
                    </DialogTitle>
                    <DialogDescription class="sr-only">Permanently remove {{ deleteTarget?.name }} from {{ tenant.name }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <p class="text-xs leading-relaxed text-zinc-400">
                        You are about to permanently remove
                        <strong class="text-white">{{ deleteTarget?.name }}</strong>
                        (<span class="font-mono text-zinc-300">{{ deleteTarget?.role }}</span
                        >) from <strong class="text-white">{{ tenant.name }}</strong
                        >. This action cannot be undone.
                    </p>
                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3 text-[11px] text-rose-400">
                        <AlertTriangle class="mr-1 inline size-3.5" />
                        All sales records, shift data, and activity logs for this user will remain but will be attributed to a deleted account.
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" class="text-xs text-zinc-400" @click="deleteModal = false">Cancel</Button>
                    <Button class="gap-1.5 bg-rose-600 text-xs text-white hover:bg-rose-500" :disabled="deleteProcessing" @click="handleDelete">
                        <Trash2 class="size-3.5" />
                        {{ deleteProcessing ? 'Removing…' : 'Remove Permanently' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
