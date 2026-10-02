<script setup>
import { ref, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    organizations: Object,
    stats: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
let searchTimeout = null

watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('superadmin.organizations.index'), { search: val }, {
            preserveState: true,
            replace: true,
        })
    }, 300)
})

// Create Organization Modal State
const showCreateModal = ref(false)
const createForm = useForm({
    name: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    address: '',
    can_create_branches: true,
    notes: '',
})

const submitCreate = () => {
    createForm.post(route('superadmin.organizations.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            createForm.reset()
        },
    })
}

// Edit Organization Modal State
const showEditModal = ref(false)
const editingOrg = ref(null)
const editForm = useForm({
    name: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    address: '',
    status: 'active',
    can_create_branches: true,
    notes: '',
})

const openEditModal = (org) => {
    editingOrg.value = org
    editForm.name = org.name
    editForm.contact_name = org.contact_name || ''
    editForm.contact_email = org.contact_email || ''
    editForm.contact_phone = org.contact_phone || ''
    editForm.address = org.address || ''
    editForm.status = org.status || 'active'
    editForm.can_create_branches = org.can_create_branches ?? true
    editForm.notes = org.notes || ''
    showEditModal.value = true
}

const submitEdit = () => {
    if (!editingOrg.value) return
    editForm.put(route('superadmin.organizations.update', editingOrg.value.id), {
        onSuccess: () => {
            showEditModal.value = false
            editingOrg.value = null
        },
    })
}

// Delete Organization
const deleteOrg = (org) => {
    if (org.branches_count > 0) {
        alert(`Cannot delete '${org.name}' because it has ${org.branches_count} branch(es) attached. Please move or delete branches first.`)
        return
    }
    if (confirm(`Are you sure you want to permanently delete organization '${org.name}'?`)) {
        router.delete(route('superadmin.organizations.destroy', org.id))
    }
}
</script>

<template>
    <Head title="Client Organizations — Super Admin" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    Client Organizations
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Manage multi-branch business accounts, assign branches, and oversee client administrative access.
                </p>
            </div>
            <button
                @click="showCreateModal = true"
                type="button"
                class="px-4 py-2.5 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-md shadow-theme/20 flex items-center justify-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Organization</span>
            </button>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Organizations</span>
                    <span class="text-lg">🏢</span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stats?.total_organizations ?? 0 }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Paying client businesses</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active</span>
                    <span class="text-lg">✅</span>
                </div>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats?.active_organizations ?? 0 }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Operational accounts</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Suspended</span>
                    <span class="text-lg">⏸️</span>
                </div>
                <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ stats?.suspended_organizations ?? 0 }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Paused client accounts</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Branches</span>
                    <span class="text-lg">🏪</span>
                </div>
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">{{ stats?.total_branches ?? 0 }}</div>
                <div class="text-[11px] text-slate-400 mt-1">System branches attached</div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex items-center justify-between gap-4">
            <div class="relative flex-1 max-w-sm">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search organizations, contacts, emails..."
                    class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-none focus:border-theme transition"
                />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <!-- Organizations Grid / List -->
        <div v-if="organizations.data?.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div
                v-for="org in organizations.data"
                :key="org.id"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition space-y-4 flex flex-col justify-between"
            >
                <div class="space-y-3">
                    <!-- Title & Status Header -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-lg font-bold">
                                🏢
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base leading-tight">
                                    {{ org.name }}
                                </h3>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    Client ID #{{ org.id }}
                                </div>
                            </div>
                        </div>

                        <span
                            :class="org.status === 'active' ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30'"
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold border capitalize"
                        >
                            {{ org.status }}
                        </span>
                    </div>

                    <!-- Contact Details -->
                    <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                        <div v-if="org.contact_name" class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">👤 Contact:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ org.contact_name }}</span>
                        </div>
                        <div v-if="org.contact_email" class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">✉️ Email:</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300">{{ org.contact_email }}</span>
                        </div>
                        <div v-if="org.contact_phone" class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">📞 Phone:</span>
                            <span>{{ org.contact_phone }}</span>
                        </div>
                        <div v-if="org.address" class="flex items-center gap-2 line-clamp-1">
                            <span class="text-slate-400 font-medium">📍 Address:</span>
                            <span>{{ org.address }}</span>
                        </div>
                    </div>

                    <!-- Attached Branches & Admins summary -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                <span>🏪 Branches ({{ org.branches_count ?? 0 }}):</span>
                            </span>
                            <span class="text-[11px] text-slate-400">
                                Organization Admins: <strong class="text-indigo-600 dark:text-indigo-400 font-bold">{{ org.users_count ?? 0 }}</strong>
                            </span>
                        </div>

                        <!-- Branch chips -->
                        <div v-if="org.branches?.length > 0" class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="branch in org.branches"
                                :key="branch.id"
                                :href="route('superadmin.branches.show', { branch: branch.slug })"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800 dark:hover:bg-indigo-950/60 text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-300 border border-slate-200 dark:border-slate-700 transition"
                            >
                                <span>🏪</span>
                                <span class="font-semibold">{{ branch.name }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">(/{{ branch.slug }})</span>
                            </Link>
                        </div>
                        <div v-else class="text-[11px] text-slate-400 italic">
                            No branches assigned yet. Create a branch or move an existing branch to this organization.
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 mt-4">
                    <div class="flex items-center gap-2">
                        <button
                            @click="openEditModal(org)"
                            type="button"
                            class="px-2.5 py-1.5 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg font-semibold transition cursor-pointer"
                        >
                            Edit
                        </button>
                        <button
                            v-if="org.branches_count === 0"
                            @click="deleteOrg(org)"
                            type="button"
                            class="px-2.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg font-semibold transition cursor-pointer"
                        >
                            Delete
                        </button>
                    </div>

                    <Link
                        :href="route('superadmin.organizations.show', org.id)"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition shadow-xs flex items-center gap-1"
                    >
                        <span>Manage & Details</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl">
                🏢
            </div>
            <h3 class="font-bold text-slate-900 dark:text-white text-base">No Organizations Found</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                No client organizations match your filter. Create a new organization to start grouping branches by client.
            </p>
            <button
                @click="showCreateModal = true"
                type="button"
                class="px-4 py-2 bg-theme text-white text-xs font-bold rounded-xl transition cursor-pointer"
            >
                Create Organization
            </button>
        </div>

        <!-- Create Organization Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">New Client Organization</h2>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Organization Name *</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="e.g. Acme Retailers Ltd"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="createForm.errors.name" class="text-rose-500 text-xs mt-1">{{ createForm.errors.name }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Person</label>
                            <input
                                v-model="createForm.contact_name"
                                type="text"
                                placeholder="e.g. John Doe"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Phone</label>
                            <input
                                v-model="createForm.contact_phone"
                                type="text"
                                placeholder="+234..."
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Email</label>
                        <input
                            v-model="createForm.contact_email"
                            type="email"
                            placeholder="billing@client.com"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="createForm.errors.contact_email" class="text-rose-500 text-xs mt-1">{{ createForm.errors.contact_email }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Registered Address</label>
                        <textarea
                            v-model="createForm.address"
                            rows="2"
                            placeholder="Physical business or corporate address..."
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        ></textarea>
                    </div>

                    <!-- Branch Creation Feature Control -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700/80">
                        <label class="flex items-center justify-between cursor-pointer">
                            <div class="space-y-0.5 pr-4">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Allow Branch Creation</span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Enabled by default. Org Admins can create branches in the Org Portal within plan limits.
                                </p>
                            </div>
                            <input
                                v-model="createForm.can_create_branches"
                                type="checkbox"
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600 cursor-pointer"
                            />
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Administrative Notes</label>
                        <textarea
                            v-model="createForm.notes"
                            rows="2"
                            placeholder="Internal notes regarding client contract, special SLA, etc."
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        ></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-5 py-2.5 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-md shadow-theme/20 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="createForm.processing">Saving...</span>
                            <span v-else>Create Organization</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Organization Modal -->
        <div v-if="showEditModal && editingOrg" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Edit Organization Profile</h2>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Organization Name *</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="editForm.errors.name" class="text-rose-500 text-xs mt-1">{{ editForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account Status *</label>
                        <select
                            v-model="editForm.status"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition cursor-pointer"
                        >
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>

                    <!-- Branch Creation Feature Control -->
                    <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700/80">
                        <label class="flex items-center justify-between cursor-pointer">
                            <div class="space-y-0.5 pr-4">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Allow Branch Creation</span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Permits Org Admins to create new branches from the Org Portal within plan limits.
                                </p>
                            </div>
                            <input
                                v-model="editForm.can_create_branches"
                                type="checkbox"
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600 cursor-pointer"
                            />
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Person</label>
                            <input
                                v-model="editForm.contact_name"
                                type="text"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Phone</label>
                            <input
                                v-model="editForm.contact_phone"
                                type="text"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Email</label>
                        <input
                            v-model="editForm.contact_email"
                            type="email"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                        <textarea
                            v-model="editForm.address"
                            rows="2"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                        <textarea
                            v-model="editForm.notes"
                            rows="2"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        ></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2.5 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-md shadow-theme/20 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="editForm.processing">Saving...</span>
                            <span v-else>Update Organization</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
