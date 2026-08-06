<script setup>
import { ref, watch } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    users: Object,
    roles: Array,
    all_permissions: Array,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const isEditing = ref(false)
const currentUser = ref(null)

const showUserPass = ref(false)
const showResetPass = ref(false)
const showResetConfirmPass = ref(false)

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'cashier',
    permissions: [],
    is_active: true,
    status: 'Active',
})

const selectAll = () => {
    form.permissions = props.all_permissions?.map(p => p.name) ?? []
}
const deselectAll = () => {
    form.permissions = []
}

watch(() => form.role, (newRole) => {
    if (newRole === 'branch-admin') {
        selectAll()
    } else if (newRole === 'cashier' && !isEditing.value) {
        form.permissions = [
            'canAccessPos',
            'canViewSales',
            'canViewEndOfDay',
            'canApplyDiscount',
            'canProcessReturn',
            'canManageCustomers',
        ]
    }
})

function doSearch() {
    router.get(route('pos.users.index'), { search: search.value }, { preserveState: true, replace: true })
}

function openCreateModal() {
    isEditing.value = false
    currentUser.value = null
    form.reset()
    form.role = 'cashier'
    form.permissions = [
        'canAccessPos',
        'canViewSales',
        'canViewEndOfDay',
        'canApplyDiscount',
        'canProcessReturn',
        'canManageCustomers',
    ]
    document.getElementById('user-modal').showModal()
}

function openEditModal(user) {
    isEditing.value = true
    currentUser.value = user
    form.name = user.name
    form.username = user.username
    form.email = user.email || ''
    form.password = ''
    form.role = user.roles?.[0]?.name ?? 'cashier'
    form.permissions = [...(user.permissions ?? [])]
    form.is_active = user.is_active
    form.status = user.is_active ? 'Active' : 'Inactive'
    document.getElementById('user-modal').showModal()
}

function togglePerm(permName) {
    const idx = form.permissions.indexOf(permName)
    if (idx === -1) {
        form.permissions.push(permName)
    } else {
        form.permissions.splice(idx, 1)
    }
}

function submit() {
    form.is_active = form.status === 'Active'
    if (isEditing.value) {
        form.put(route('pos.users.update', currentUser.value.id), {
            onSuccess: () => document.getElementById('user-modal').close(),
        })
    } else {
        form.post(route('pos.users.store'), {
            onSuccess: () => document.getElementById('user-modal').close(),
        })
    }
}

function destroy(user) {
    if (confirm(`Are you sure you want to delete user "${user.username}"?`)) {
        router.delete(route('pos.users.destroy', user.id))
    }
}

const userToReset = ref(null)
const resetForm = useForm({
    password: '',
    password_confirmation: '',
})

function toggleUserStatus(user) {
    const action = user.is_active ? 'disable' : 'enable'
    if (confirm(`Are you sure you want to ${action} user account "${user.username}"?`)) {
        router.post(route('pos.users.toggle', user.id))
    }
}

function openResetModal(user) {
    userToReset.value = user
    resetForm.reset()
    document.getElementById('reset-modal').showModal()
}

function submitResetPassword() {
    resetForm.post(route('pos.users.reset-password', userToReset.value.id), {
        onSuccess: () => document.getElementById('reset-modal').close(),
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">System Users & Staff Permissions</h1>
            <div class="flex gap-2">
                <Link :href="route('pos.roles.index')" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition">
                    Role Templates
                </Link>
                <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Staff User
                </button>
            </div>
        </div>

        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex gap-2 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <input v-model="search" @keydown.enter="doSearch" type="text" placeholder="Search name or username…" class="flex-1 max-w-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            <button @click="doSearch" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm font-semibold rounded-lg transition">Search</button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Name</th>
                            <th class="text-left px-5 py-3 font-semibold">Username</th>
                            <th class="text-left px-5 py-3 font-semibold">Role</th>
                            <th class="text-left px-5 py-3 font-semibold">Granted Permissions</th>
                            <th class="text-left px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-900 dark:text-white font-medium">
                                {{ user.name }}
                                <div v-if="user.email" class="text-xs text-slate-500 dark:text-slate-400 font-normal">{{ user.email }}</div>
                            </td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-300 font-mono text-xs">{{ user.username }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in user.roles" :key="role.id"
                                        :class="role.name === 'branch-admin' ? 'bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-500/20 dark:text-indigo-300 dark:border-indigo-500/40' : 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-300 dark:border-emerald-500/40'"
                                        class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider border">
                                        {{ role.name }}
                                    </span>
                                    <span v-if="!user.roles.length" class="text-xs text-slate-500 italic">None</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="user.roles?.some(r => r.name === 'branch-admin')" class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-500/20">
                                    Full Access (All Permissions)
                                </span>
                                <span v-else-if="user.permissions?.length" class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-500/20">
                                    {{ user.permissions.length }} of {{ all_permissions?.length ?? 19 }} permissions
                                </span>
                                <span v-else class="text-slate-500 text-xs italic">Default Role Permissions</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full" :class="user.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'">
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    <button @click="openEditModal(user)" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 transition font-semibold">Edit</button>
                                    <button v-if="$page.props.auth.user.id !== user.id" @click="toggleUserStatus(user)"
                                        :class="user.is_active ? 'text-amber-600 dark:text-amber-400 hover:text-amber-500' : 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-500'"
                                        class="transition font-semibold">
                                        {{ user.is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <button v-if="$page.props.auth?.permissions?.canResetPassword" @click="openResetModal(user)" class="text-purple-600 dark:text-purple-400 hover:text-purple-500 transition font-semibold">Reset Pass</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">No users found.</td>
                        </tr>
                    </tbody>
                </table>
               <div v-if="users.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ users.from }}–{{ users.to }} of {{ users.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Prev</Link>
                        <Link v-if="users.next_page_url" :href="users.next_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="user-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95 w-full max-w-2xl">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden rounded-xl flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 flex-shrink-0">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ isEditing ? 'Edit User & Permissions' : 'Create New Staff User' }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Assign role and grant specific feature permissions</p>
                    </div>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input v-model="form.name" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                                <input v-model="form.username" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition font-mono" />
                                <p v-if="form.errors.username" class="text-red-500 text-xs mt-1">{{ form.errors.username }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password <span v-if="isEditing" class="text-slate-500">(Leave blank to keep)</span><span v-else>*</span></label>
                                <div class="relative flex items-center">
                                    <input v-model="form.password" :type="showUserPass ? 'text' : 'password'" :required="!isEditing" minlength="8" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 pr-9 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                                    <button type="button" @click="showUserPass = !showUserPass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" :title="showUserPass ? 'Hide password' : 'Show password'">
                                        <svg v-if="!showUserPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                                    </button>
                                </div>
                                <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email <span class="text-slate-500">(Optional)</span></label>
                                <input v-model="form.email" type="email" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                                <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Role *</label>
                                <select v-model="form.role" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                    <option v-for="role in roles" :key="role.id" :value="role.name" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ role.name }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Granular Permissions Grid -->
                        <div class="border-t border-slate-200 dark:border-slate-700 pt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-xs uppercase font-bold text-emerald-600 dark:text-emerald-400 tracking-wider">Feature Permissions</h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Toggle individual features for this user</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="selectAll" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded border border-slate-300 dark:border-slate-600">Select All</button>
                                    <button type="button" @click="deselectAll" class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded border border-slate-300 dark:border-slate-600">Deselect All</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-700">
                                <label v-for="perm in all_permissions" :key="perm.name"
                                    class="flex items-center gap-2.5 p-2 rounded cursor-pointer text-xs transition select-none"
                                    :class="form.permissions.includes(perm.name) ? 'bg-emerald-50 dark:bg-emerald-500/10 text-slate-900 dark:text-white font-medium' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-700/50'"
                                >
                                    <input
                                        type="checkbox"
                                        :value="perm.name"
                                        :checked="form.permissions.includes(perm.name)"
                                        @change="togglePerm(perm.name)"
                                        class="accent-emerald-500 rounded cursor-pointer"
                                    />
                                    <span>{{ perm.label }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 dark:border-slate-700 pt-3">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account Status</label>
                            <select v-model="form.status" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                <option value="Active" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Active</option>
                                <option value="Inactive" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Inactive</option>
                            </select>
                        </div>

                        <div class="pt-4 flex gap-3 flex-shrink-0">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40 shadow-md shadow-emerald-900/20">
                                {{ form.processing ? 'Saving...' : 'Save User & Permissions' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>

        <!-- Reset Password Modal -->
        <dialog id="reset-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm w-full max-w-md">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-900 dark:text-white">Reset Password for {{ userToReset?.username }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <form @submit.prevent="submitResetPassword" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
                        <div class="relative flex items-center">
                            <input v-model="resetForm.password" :type="showResetPass ? 'text' : 'password'" required minlength="8" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 pr-9 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-purple-500 transition" />
                            <button type="button" @click="showResetPass = !showResetPass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" :title="showResetPass ? 'Hide password' : 'Show password'">
                                <svg v-if="!showResetPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <p v-if="resetForm.errors.password" class="text-red-500 text-xs mt-1">{{ resetForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
                        <div class="relative flex items-center">
                            <input v-model="resetForm.password_confirmation" :type="showResetConfirmPass ? 'text' : 'password'" required minlength="8" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 pr-9 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-purple-500 transition" />
                            <button type="button" @click="showResetConfirmPass = !showResetConfirmPass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" :title="showResetConfirmPass ? 'Hide password' : 'Show password'">
                                <svg v-if="!showResetConfirmPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="pt-2 flex gap-3">
                        <form method="dialog" class="flex-1">
                            <button class="w-full py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</button>
                        </form>
                        <button type="submit" :disabled="resetForm.processing" class="flex-1 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40 shadow-md">
                            {{ resetForm.processing ? 'Resetting...' : 'Set Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>
</template>
