<script setup>
import { ref, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    branch: Object,
    users: Array,
    roles: Array,
    all_permissions: Array,
})

// ── Add User ──────────────────────────────────────────────────────────────────
const showCreateModal = ref(false)

const showCreatePass       = ref(false)
const showResetPass        = ref(false)
const showResetConfirmPass = ref(false)

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'branch-admin',
    permissions: [],
    is_active: true,
})

const selectAllCreate = () => {
    form.permissions = props.all_permissions.filter(p => p.name !== 'canResetPassword').map(p => p.name)
}
const deselectAllCreate = () => {
    form.permissions = []
}

// Preset permissions based on role
watch(() => form.role, (newRole) => {
    if (newRole === 'branch-admin') {
        selectAllCreate()
    } else if (newRole === 'cashier') {
        form.permissions = [
            'canAccessPos',
            'canViewSales',
            'canViewEndOfDay',
            'canApplyDiscount',
            'canProcessReturn',
            'canManageCustomers',
        ]
    }
}, { immediate: true })

const submitCreate = () => {
    form.post(route('superadmin.branches.users.store', props.branch.slug), {
        onSuccess: () => {
            showCreateModal.value = false
            form.reset()
        },
    })
}

// ── Edit User ─────────────────────────────────────────────────────────────────
const showEditModal = ref(false)
const editingUser   = ref(null)

const editForm = useForm({
    name:        '',
    username:    '',
    email:       '',
    role:        '',
    permissions: [],
    is_active:   true,
})

const selectAllEdit = () => {
    editForm.permissions = props.all_permissions.filter(p => p.name !== 'canResetPassword').map(p => p.name)
}
const deselectAllEdit = () => {
    editForm.permissions = []
}

const openEdit = (user) => {
    editingUser.value    = user
    editForm.name        = user.name
    editForm.username    = user.username
    editForm.email       = user.email ?? ''
    editForm.role        = user.roles?.[0]?.name ?? 'cashier'
    editForm.permissions = [...(user.permissions ?? [])]
    editForm.is_active   = user.is_active
    showEditModal.value  = true
}

const submitEdit = () => {
    editForm.put(
        route('superadmin.branches.users.update', [props.branch.slug, editingUser.value.id]),
        {
            onSuccess: () => {
                showEditModal.value = false
                editingUser.value   = null
            },
        }
    )
}

// ── Toggle & Reset password ───────────────────────────────────────────────────
const toggleUser = (userId) => {
    router.post(route('superadmin.branches.users.toggle', [props.branch.slug, userId]))
}

const showResetModal = ref(false)
const resetUserId    = ref(null)
const resetForm      = useForm({ password: '', password_confirmation: '' })

const openReset = (userId) => {
    resetUserId.value    = userId
    resetForm.reset()
    showResetModal.value = true
}

const submitReset = () => {
    resetForm.post(
        route('superadmin.branches.users.reset-password', [props.branch.slug, resetUserId.value]),
        {
            onSuccess: () => {
                showResetModal.value = false
                resetUserId.value    = null
            },
        }
    )
}

const destroyUser = (userId, name) => {
    if (!confirm(`Remove "${name}" from this branch? This cannot be undone.`)) return
    router.delete(route('superadmin.branches.users.destroy', [props.branch.slug, userId]))
}

const togglePermInForm = (targetForm, permName) => {
    const idx = targetForm.permissions.indexOf(permName)
    if (idx === -1) {
        targetForm.permissions.push(permName)
    } else {
        targetForm.permissions.splice(idx, 1)
    }
}
</script>

<template>
    <Head :title="`Users — ${branch.name}`" />
    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans p-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('superadmin.branches.index')"
                        class="text-xs text-indigo-400 hover:text-indigo-300 mb-1 inline-block">
                        &larr; Back to Branches
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-100">Staff & Permissions in {{ branch.name }}</h1>
                    <p class="text-xs text-slate-400 font-mono">{{ branch.slug }}</p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('superadmin.branches.roles.index', branch.slug)"
                        class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 font-semibold rounded-xl text-xs transition-colors flex items-center space-x-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        <span>Role Templates</span>
                    </Link>
                    <button
                        @click="showCreateModal = true; selectAllCreate()"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition-colors shadow-lg shadow-indigo-600/20 flex items-center space-x-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Staff User</span>
                    </button>
                </div>

            </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success"
                class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error"
                class="bg-rose-500/10 border border-rose-500/30 text-rose-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <!-- Empty State -->
            <div v-if="!users.length"
                class="bg-slate-900 border border-slate-800 rounded-xl p-12 text-center text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="font-semibold">No staff yet</p>
                <p class="text-xs mt-1">Click "Add Staff User" to create the first account.</p>
            </div>

            <!-- Users Table -->
            <div v-else class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase font-semibold text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Username</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Permissions Granted</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-100">
                                <div>{{ user.name }}</div>
                                <div class="text-xs text-slate-500">{{ user.email || 'No email' }}</div>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-300 text-xs">{{ user.username }}</td>
                            <td class="px-6 py-4">
                                <span v-if="user.roles?.length"
                                    :class="user.roles.some(r => r.name === 'branch-admin') ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30' : 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'"
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wider">
                                    {{ user.roles.map(r => r.name).join(', ') }}
                                </span>
                                <span v-else class="text-rose-400 text-xs italic">No Role</span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="user.roles?.some(r => r.name === 'branch-admin')"
                                    class="text-xs text-indigo-300 font-semibold bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">
                                    Full Access (All Permissions)
                                </span>
                                <span v-else-if="user.permissions?.length"
                                    class="text-xs text-emerald-300 font-medium bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                                    {{ user.permissions.length }} of {{ all_permissions.length }} permissions
                                </span>
                                <span v-else class="text-slate-500 text-xs italic">No custom permissions</span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="user.is_active
                                        ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20'
                                        : 'text-rose-400 bg-rose-500/10 border-rose-500/20'"
                                    class="px-2 py-0.5 rounded text-xs font-semibold border"
                                >
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Edit -->
                                    <button @click="openEdit(user)"
                                        class="px-2.5 py-1 text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700 transition-colors">
                                        Edit / Permissions
                                    </button>
                                    <!-- Toggle -->
                                    <button @click="toggleUser(user.id)"
                                        :class="user.is_active
                                            ? 'text-rose-400 hover:text-rose-300'
                                            : 'text-emerald-400 hover:text-emerald-300'"
                                        class="text-xs font-medium px-2 py-1 transition-colors">
                                        {{ user.is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <!-- Reset Password -->
                                    <button @click="openReset(user.id)"
                                        class="text-xs font-medium text-amber-400 hover:text-amber-300 px-2 py-1 transition-colors">
                                        Reset PW
                                    </button>
                                    <!-- Delete -->
                                    <button @click="destroyUser(user.id, user.name)"
                                        class="text-xs font-medium text-slate-500 hover:text-rose-400 px-2 py-1 transition-colors">
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- ── Add User Modal ────────────────────────────────────────────────────── -->
        <div v-if="showCreateModal"
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-100">Add Staff to {{ branch.name }}</h2>
                        <p class="text-xs text-slate-400">Assign role and select specific POS permissions</p>
                    </div>
                    <button @click="showCreateModal = false; form.reset()" class="text-slate-500 hover:text-slate-300 text-xl">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Full Name *</label>
                            <input v-model="form.name" type="text" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="form.errors.name" class="text-rose-400 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Username *</label>
                            <input v-model="form.username" type="text" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="form.errors.username" class="text-rose-400 text-xs mt-1">{{ form.errors.username }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Email</label>
                            <input v-model="form.email" type="email"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Password *</label>
                            <div class="relative flex items-center">
                                <input v-model="form.password" :type="showCreatePass ? 'text' : 'password'" required
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 pr-9 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                                <button type="button" @click="showCreatePass = !showCreatePass" class="absolute right-2.5 text-slate-400 hover:text-slate-200 transition focus:outline-none" :title="showCreatePass ? 'Hide password' : 'Show password'">
                                    <svg v-if="!showCreatePass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                                </button>
                            </div>
                            <div v-if="form.errors.password" class="text-rose-400 text-xs mt-1">{{ form.errors.password }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Base Role *</label>
                        <select v-model="form.role" required
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                        <div v-if="form.errors.role" class="text-rose-400 text-xs mt-1">{{ form.errors.role }}</div>
                    </div>

                    <!-- Granular Permission Checkboxes -->
                    <div class="pt-2 border-t border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs uppercase font-bold text-indigo-400 tracking-wider">Granular Feature Permissions</h3>
                                <p class="text-xs text-slate-500">Check/uncheck specific menu features for this user</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" @click="selectAllCreate" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold px-2 py-1 rounded bg-slate-800 border border-slate-700">
                                    Select All
                                </button>
                                <button type="button" @click="deselectAllCreate" class="text-xs text-slate-400 hover:text-slate-200 px-2 py-1 rounded bg-slate-800 border border-slate-700">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-1 bg-slate-950/60 rounded-xl border border-slate-800">
                            <label
                                v-for="perm in all_permissions"
                                :key="perm.name"
                                class="flex items-center space-x-2.5 p-2 rounded-lg cursor-pointer transition-colors text-xs"
                                :class="form.permissions.includes(perm.name) ? 'bg-indigo-600/10 text-slate-100 font-medium' : 'text-slate-400 hover:bg-slate-900'"
                            >
                                <input
                                    type="checkbox"
                                    :value="perm.name"
                                    :checked="form.permissions.includes(perm.name)"
                                    @change="togglePermInForm(form, perm.name)"
                                    class="rounded border-slate-700 bg-slate-900 text-indigo-500 focus:ring-0"
                                />
                                <span>{{ perm.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-1">
                        <input type="checkbox" id="create_active" v-model="form.is_active"
                            class="rounded border-slate-700 bg-slate-950 text-indigo-500" />
                        <label for="create_active" class="text-xs text-slate-400">Account active immediately</label>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-end space-x-3 flex-shrink-0">
                        <button type="button" @click="showCreateModal = false; form.reset()"
                            class="px-4 py-2 text-xs text-slate-400 hover:text-slate-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-indigo-600/20">
                            {{ form.processing ? 'Adding User…' : 'Create User & Assign Permissions' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- ── Edit User Modal ───────────────────────────────────────────────────── -->
        <div v-if="showEditModal"
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-100">Edit Staff Permissions — {{ editingUser?.username }}</h2>
                        <p class="text-xs text-slate-400">Update account details, role, and granular permissions</p>
                    </div>
                    <button @click="showEditModal = false" class="text-slate-500 hover:text-slate-300 text-xl">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Full Name *</label>
                            <input v-model="editForm.name" type="text" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="editForm.errors.name" class="text-rose-400 text-xs mt-1">{{ editForm.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Username *</label>
                            <input v-model="editForm.username" type="text" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="editForm.errors.username" class="text-rose-400 text-xs mt-1">{{ editForm.errors.username }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Email</label>
                            <input v-model="editForm.email" type="email"
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Role *</label>
                            <select v-model="editForm.role" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                            </select>
                            <div v-if="editForm.errors.role" class="text-rose-400 text-xs mt-1">{{ editForm.errors.role }}</div>
                        </div>
                    </div>

                    <!-- Granular Permission Checkboxes -->
                    <div class="pt-2 border-t border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs uppercase font-bold text-indigo-400 tracking-wider">Assigned Feature Permissions</h3>
                                <p class="text-xs text-slate-500">Customize feature access for {{ editingUser?.name }}</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" @click="selectAllEdit" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold px-2 py-1 rounded bg-slate-800 border border-slate-700">
                                    Select All
                                </button>
                                <button type="button" @click="deselectAllEdit" class="text-xs text-slate-400 hover:text-slate-200 px-2 py-1 rounded bg-slate-800 border border-slate-700">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-1 bg-slate-950/60 rounded-xl border border-slate-800">
                            <label
                                v-for="perm in all_permissions"
                                :key="perm.name"
                                class="flex items-center space-x-2.5 p-2 rounded-lg cursor-pointer transition-colors text-xs"
                                :class="editForm.permissions.includes(perm.name) ? 'bg-indigo-600/10 text-slate-100 font-medium' : 'text-slate-400 hover:bg-slate-900'"
                            >
                                <input
                                    type="checkbox"
                                    :value="perm.name"
                                    :checked="editForm.permissions.includes(perm.name)"
                                    @change="togglePermInForm(editForm, perm.name)"
                                    class="rounded border-slate-700 bg-slate-900 text-indigo-500 focus:ring-0"
                                />
                                <span>{{ perm.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-1">
                        <input type="checkbox" id="edit_active" v-model="editForm.is_active"
                            class="rounded border-slate-700 bg-slate-950 text-indigo-500" />
                        <label for="edit_active" class="text-xs text-slate-400">Account active</label>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-end space-x-3 flex-shrink-0">
                        <button type="button" @click="showEditModal = false"
                            class="px-4 py-2 text-xs text-slate-400 hover:text-slate-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="editForm.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-indigo-600/20">
                            {{ editForm.processing ? 'Saving…' : 'Save User & Permissions' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- ── Reset Password Modal ─────────────────────────────────────────────── -->
        <div v-if="showResetModal"
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-100">Reset Password</h2>
                    <button @click="showResetModal = false" class="text-slate-500 hover:text-slate-300 text-xl">&times;</button>
                </div>
                <form @submit.prevent="submitReset" class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">New Password *</label>
                        <div class="relative flex items-center">
                            <input v-model="resetForm.password" :type="showResetPass ? 'text' : 'password'" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 pr-9 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <button type="button" @click="showResetPass = !showResetPass" class="absolute right-2.5 text-slate-400 hover:text-slate-200 transition focus:outline-none" :title="showResetPass ? 'Hide password' : 'Show password'">
                                <svg v-if="!showResetPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <div v-if="resetForm.errors.password" class="text-rose-400 text-xs mt-1">{{ resetForm.errors.password }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Confirm Password *</label>
                        <div class="relative flex items-center">
                            <input v-model="resetForm.password_confirmation" :type="showResetConfirmPass ? 'text' : 'password'" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 pr-9 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <button type="button" @click="showResetConfirmPass = !showResetConfirmPass" class="absolute right-2.5 text-slate-400 hover:text-slate-200 transition focus:outline-none" :title="showResetConfirmPass ? 'Hide password' : 'Show password'">
                                <svg v-if="!showResetConfirmPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showResetModal = false"
                            class="px-4 py-2 text-xs text-slate-400 hover:text-slate-200">
                            Cancel
                        </button>
                        <button type="submit" :disabled="resetForm.processing"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition-colors">
                            {{ resetForm.processing ? 'Resetting…' : 'Reset Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
