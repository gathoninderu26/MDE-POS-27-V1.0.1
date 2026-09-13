<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSuperAdmin } from '@/composables/useSuperAdmin';
import { useToast } from '@/composables/useToast';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    KeyRound,
    Lock,
    Plus,
    Search,
    Shield,
    ShieldCheck,
    ShieldOff,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { systemUsers } = useSuperAdmin();
const { success, warning, error } = useToast();

const searchQuery = ref('');
const addUserModal = ref(false);
const newUser = ref({ name: '', email: '', role: 'Support Admin' });

const filteredUsers = computed(() =>
    systemUsers.filter(
        (u) =>
            !searchQuery.value ||
            u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            u.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            u.role.toLowerCase().includes(searchQuery.value.toLowerCase()),
    ),
);

const handleAddUser = () => {
    systemUsers.push({
        id: `SU-${Date.now()}`,
        name: newUser.value.name,
        email: newUser.value.email,
        role: newUser.value.role,
        status: 'Active',
        lastLogin: 'Never',
        mfaEnabled: false,
        createdAt: new Date().toISOString().split('T')[0],
    });
    success('System User Created', `${newUser.value.name} has been granted ${newUser.value.role} access.`);
    addUserModal.value = false;
    newUser.value = { name: '', email: '', role: 'Support Admin' };
};

const handleDeactivate = (user: any) => {
    user.status = user.status === 'Active' ? 'Inactive' : 'Active';
    if (user.status === 'Inactive') {
        warning('User Deactivated', `${user.name} can no longer access the admin console.`);
    } else {
        success('User Reactivated', `${user.name} has been restored to active access.`);
    }
};

const handleResetMfa = (user: any) => {
    user.mfaEnabled = !user.mfaEnabled;
    success('MFA Reset', `MFA for ${user.name} has been ${user.mfaEnabled ? 'enabled' : 'disabled'}.`);
};

const handleDelete = (user: any) => {
    const idx = systemUsers.indexOf(user);
    if (idx !== -1) systemUsers.splice(idx, 1);
    error('User Removed', `${user.name} has been permanently removed from system access.`);
};

const roleColor: Record<string, string> = {
    'Super Admin': 'text-violet-300 bg-violet-600/20 border-violet-600/30',
    'Support Admin': 'text-sky-300 bg-sky-600/20 border-sky-600/30',
    'Billing Admin': 'text-amber-300 bg-amber-600/20 border-amber-600/30',
};
</script>

<template>
    <Head title="Super Admin — System Users" />
    <SuperAdminLayout>
        <div class="p-6 space-y-6 max-w-[1600px] mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-zinc-100 flex items-center gap-2">
                        <Users class="size-5 text-violet-400" />
                        Platform System Users
                    </h1>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Manage super admin accounts and support personnel with access to the platform console.
                    </p>
                </div>
                <Button
                    size="sm"
                    class="h-8 text-xs bg-violet-600 hover:bg-violet-700 text-white gap-1.5"
                    @click="addUserModal = true"
                >
                    <Plus class="size-3.5" />
                    Add System User
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 uppercase font-semibold mb-1">Total Admin Accounts</p>
                    <p class="text-2xl font-extrabold text-zinc-100 font-mono">{{ systemUsers.length }}</p>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 uppercase font-semibold mb-1">Active Sessions</p>
                    <p class="text-2xl font-extrabold text-emerald-400 font-mono">{{ systemUsers.filter(u => u.status === 'Active').length }}</p>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-[11px] text-zinc-500 uppercase font-semibold mb-1">MFA Protected</p>
                    <p class="text-2xl font-extrabold text-violet-400 font-mono">{{ systemUsers.filter(u => u.mfaEnabled).length }}/{{ systemUsers.length }}</p>
                </div>
            </div>

            <!-- Search -->
            <div class="relative w-72">
                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-500" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search system users..."
                    class="h-9 w-full rounded-lg border border-zinc-700 bg-zinc-800 pl-8 pr-3 text-xs text-zinc-200 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                />
            </div>

            <!-- Users Table -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <table class="w-full text-xs">
                    <thead class="bg-zinc-800/60 border-b border-zinc-800 text-zinc-400">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold uppercase">User / Email</th>
                            <th class="px-4 py-3 text-left font-semibold uppercase">System Role</th>
                            <th class="px-4 py-3 text-center font-semibold uppercase">Status</th>
                            <th class="px-4 py-3 text-center font-semibold uppercase">MFA</th>
                            <th class="px-4 py-3 text-left font-semibold uppercase">Last Login</th>
                            <th class="px-4 py-3 text-left font-semibold uppercase">Created</th>
                            <th class="px-4 py-3 text-right font-semibold uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <tr
                            v-for="user in filteredUsers"
                            :key="user.id"
                            :class="['hover:bg-zinc-800/30 transition-colors', user.status !== 'Active' && 'opacity-50']"
                        >
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-violet-600/30 border border-violet-600/30 flex items-center justify-center text-[10px] font-bold text-violet-300 shrink-0">
                                        {{ user.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-100">{{ user.name }}</p>
                                        <p class="text-[10px] text-zinc-500">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex px-2 py-0.5 rounded border text-[10px] font-bold', roleColor[user.role] || 'text-zinc-300 bg-zinc-700/60 border-zinc-600/30']">
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['text-[10px] font-bold px-2 py-0.5 rounded', user.status === 'Active' ? 'bg-emerald-600/20 text-emerald-300' : 'bg-zinc-700/60 text-zinc-400']">
                                    {{ user.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <ShieldCheck v-if="user.mfaEnabled" class="size-4 text-emerald-400 mx-auto" />
                                <ShieldOff v-else class="size-4 text-zinc-600 mx-auto" />
                            </td>
                            <td class="px-4 py-3 text-zinc-400">{{ user.lastLogin }}</td>
                            <td class="px-4 py-3 text-zinc-500 font-mono">{{ user.createdAt }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="p-1.5 rounded text-zinc-500 hover:text-amber-400 hover:bg-amber-600/10 transition-colors"
                                        title="Toggle MFA"
                                        @click="handleResetMfa(user)"
                                    >
                                        <KeyRound class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="p-1.5 rounded text-zinc-500 hover:text-sky-400 hover:bg-sky-600/10 transition-colors"
                                        title="Toggle Active"
                                        @click="handleDeactivate(user)"
                                    >
                                        <Lock class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="p-1.5 rounded text-zinc-500 hover:text-rose-400 hover:bg-rose-600/10 transition-colors"
                                        title="Delete User"
                                        @click="handleDelete(user)"
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

        <!-- Add System User Modal -->
        <Dialog :open="addUserModal" @update:open="(v) => (addUserModal = v)">
            <DialogContent class="sm:max-w-sm bg-zinc-900 border-zinc-700 text-zinc-100">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Add Platform System User</DialogTitle>
                    <DialogDescription class="sr-only">Create a new platform-level system user with an assigned admin role</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2 text-xs">
                    <div>
                        <Label class="text-xs text-zinc-400">Full Name</Label>
                        <Input v-model="newUser.name" placeholder="e.g. Support Engineer" class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100" />
                    </div>
                    <div>
                        <Label class="text-xs text-zinc-400">Work Email</Label>
                        <Input v-model="newUser.email" placeholder="user@mdepos.co.ke" class="mt-1 text-xs bg-zinc-800 border-zinc-700 text-zinc-100" />
                    </div>
                    <div>
                        <Label class="text-xs text-zinc-400">System Role</Label>
                        <select v-model="newUser.role" class="mt-1 h-9 w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-200">
                            <option value="Super Admin">Super Admin (Full Platform Access)</option>
                            <option value="Support Admin">Support Admin (Tenant Viewing Only)</option>
                            <option value="Billing Admin">Billing Admin (Subscriptions & Billing)</option>
                        </select>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="ghost" size="sm" class="text-zinc-400" @click="addUserModal = false">Cancel</Button>
                    <Button size="sm" class="bg-violet-600 hover:bg-violet-700 text-white" @click="handleAddUser">Create System User</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SuperAdminLayout>
</template>
