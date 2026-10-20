<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import OrgLayout from '@/Layouts/OrgLayout.vue'
import axios from 'axios'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization: Object,
    users: Object,
    branches: Array,
    filters: Object,
    can_manage_staff: Boolean,
})

const page = usePage()

// Search & Filter State
const searchInput = ref(props.filters.search || '')
const selectedBranch = ref(props.filters.branch_id || '')
const selectedRole = ref(props.filters.role || '')
const selectedStatus = ref(props.filters.status || 'all')
let searchTimeout = null

const applyFilters = () => {
    router.get(
        route('org.users.index'),
        {
            search: searchInput.value || undefined,
            branch_id: selectedBranch.value || undefined,
            role: selectedRole.value || undefined,
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        },
        { preserveState: true, replace: true }
    )
}

watch(searchInput, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
})

const resetFilters = () => {
    searchInput.value = ''
    selectedBranch.value = ''
    selectedRole.value = ''
    selectedStatus.value = 'all'
    applyFilters()
}

// ── User Detail Drawer ────────────────────────────────────────────────────────
const isDetailDrawerOpen = ref(false)
const isLoadingDetail = ref(false)
const userDetail = ref(null)

const openDetailDrawer = async (user) => {
    isDetailDrawerOpen.value = true
    isLoadingDetail.value = true
    userDetail.value = null
    try {
        const res = await axios.get(route('org.users.show', user.id))
        userDetail.value = res.data
    } catch (e) {
        console.error('Failed to load user details', e)
    } finally {
        isLoadingDetail.value = false
    }
}

const closeDetailDrawer = () => {
    isDetailDrawerOpen.value = false
    userDetail.value = null
}

// ── Move User Modal ───────────────────────────────────────────────────────────
const isMoveModalOpen = ref(false)
const targetUserForMove = ref(null)
const isCheckingMove = ref(false)
const movePrecheck = ref({ can_move: false, blocks: [], warnings: [], roles: [], suggested_role: '' })

const moveForm = useForm({
    destination_branch_id: '',
    destination_role: '',
    reason: '',
})

// Destination branches must be active and cannot be the user's current branch
const availableDestinationBranches = computed(() => {
    if (!targetUserForMove.value) return []
    return props.branches.filter(b => b.id !== targetUserForMove.value.branch?.id)
})

const openMoveModal = (user) => {
    targetUserForMove.value = user
    moveForm.reset()
    movePrecheck.value = { can_move: false, blocks: [], warnings: [], roles: [], suggested_role: '' }
    isMoveModalOpen.value = true
}

const closeMoveModal = () => {
    isMoveModalOpen.value = false
    targetUserForMove.value = null
    moveForm.reset()
}

const handleDestinationBranchChange = async () => {
    if (!moveForm.destination_branch_id || !targetUserForMove.value) {
        movePrecheck.value = { can_move: false, blocks: [], warnings: [], roles: [], suggested_role: '' }
        return
    }

    isCheckingMove.value = true
    try {
        const res = await axios.get(route('org.users.precheck-move', targetUserForMove.value.id), {
            params: { destination_branch_id: moveForm.destination_branch_id },
        })
        movePrecheck.value = res.data
        if (res.data.suggested_role && res.data.roles.some(r => r.name === res.data.suggested_role)) {
            moveForm.destination_role = res.data.suggested_role
        } else if (res.data.roles.length > 0) {
            moveForm.destination_role = res.data.roles[0].name
        }
    } catch (err) {
        const data = err.response?.data
        movePrecheck.value = {
            can_move: false,
            blocks: data?.blocks || [data?.message || 'Unable to check destination branch.'],
            warnings: [],
            roles: [],
        }
    } finally {
        isCheckingMove.value = false
    }
}

const submitMoveUser = () => {
    if (!targetUserForMove.value) return
    moveForm.post(route('org.users.move', targetUserForMove.value.id), {
        onSuccess: () => {
            closeMoveModal()
            // Check if transfer URL was set in session flash
            if (page.props.flash?.new_login_url) {
                transferredLoginUrl.value = page.props.flash.new_login_url
                transferredUserName.value = page.props.flash.transferred_user_name || 'Staff'
                showTransferSuccessModal.value = true
            }
        },
    })
}

// ── Transfer Success Modal (Link Copy) ────────────────────────────────────────
const showTransferSuccessModal = ref(false)
const transferredLoginUrl = ref('')
const transferredUserName = ref('')
const copiedSuccess = ref(false)

const copyLoginLink = async () => {
    try {
        await navigator.clipboard.writeText(transferredLoginUrl.value)
        copiedSuccess.value = true
        setTimeout(() => { copiedSuccess.value = false }, 2500)
    } catch (e) {
        console.error('Clipboard copy failed', e)
    }
}

// ── Disable User Modal ────────────────────────────────────────────────────────
const isDisableModalOpen = ref(false)
const targetUserForDisable = ref(null)
const disableWarning = ref('')

const disableForm = useForm({
    reason: '',
})

const openDisableModal = (user) => {
    targetUserForDisable.value = user
    disableForm.reset()

    // Check if last active branch admin warning
    disableWarning.value = ''
    if (user.roles?.some(r => r.name === 'branch-admin')) {
        disableWarning.value = "Warning: This user has the Branch Admin role. Please confirm before disabling."
    }
    isDisableModalOpen.value = true
}

const closeDisableModal = () => {
    isDisableModalOpen.value = false
    targetUserForDisable.value = null
    disableForm.reset()
}

const submitDisableUser = () => {
    if (!targetUserForDisable.value) return
    disableForm.post(route('org.users.disable', targetUserForDisable.value.id), {
        onSuccess: () => closeDisableModal(),
    })
}

// ── Enable User Modal ─────────────────────────────────────────────────────────
const isEnableModalOpen = ref(false)
const targetUserForEnable = ref(null)

const enableForm = useForm({
    reason: '',
})

const openEnableModal = (user) => {
    targetUserForEnable.value = user
    enableForm.reset()
    isEnableModalOpen.value = true
}

const closeEnableModal = () => {
    isEnableModalOpen.value = false
    targetUserForEnable.value = null
    enableForm.reset()
}

const submitEnableUser = () => {
    if (!targetUserForEnable.value) return
    enableForm.post(route('org.users.enable', targetUserForEnable.value.id), {
        onSuccess: () => closeEnableModal(),
    })
}

const formatDate = (isoStr) => {
    if (!isoStr) return '—'
    const d = new Date(isoStr)
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <Head :title="`Staff & Users — ${organization.name}`" />

    <div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- ── Page Header ──────────────────────────────────────────────────── -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        Staff & User Directory
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700 dark:bg-purple-950/80 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                        {{ users.total }} Total Staff
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Manage staff accounts, transfer personnel across {{ organization.name }}'s branches, and audit branch assignment histories.
                </p>
            </div>
        </div>

        <!-- ── Delegation Alert (If toggle turned off by Platform Admin) ────── -->
        <div v-if="!can_manage_staff" class="rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80 p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-xs text-amber-800 dark:text-amber-300 space-y-1">
                <strong class="font-bold block text-sm">Read-Only Mode Active</strong>
                <p>
                    Platform Super Admin has delegated staff management as view-only for this organization. Moving staff and toggling account statuses are temporarily restricted. Contact your platform administrator to restore staff management powers.
                </p>
            </div>
        </div>

        <!-- ── Search & Filter Controls ─────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search Input -->
                <div class="lg:col-span-2 relative">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Search by name, username, or email..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500"
                    />
                </div>

                <!-- Branch Filter -->
                <div>
                    <select
                        v-model="selectedBranch"
                        @change="applyFilters"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
                    >
                        <option value="">All Branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <!-- Role Filter -->
                <div>
                    <select
                        v-model="selectedRole"
                        @change="applyFilters"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
                    >
                        <option value="">All Roles</option>
                        <option value="branch-admin">Branch Admin</option>
                        <option value="cashier">Cashier</option>
                        <option value="store-manager">Store Manager</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="flex items-center gap-2">
                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="disabled">Disabled Only</option>
                    </select>

                    <button
                        v-if="searchInput || selectedBranch || selectedRole || selectedStatus !== 'all'"
                        @click="resetFilters"
                        title="Reset Filters"
                        class="px-2.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 text-xs transition shrink-0 cursor-pointer"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Staff Users Table ────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-3.5 px-4 sm:px-6">Staff Member</th>
                            <th class="py-3.5 px-4">Branch</th>
                            <th class="py-3.5 px-4">Role(s)</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Added On</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition group"
                        >
                            <!-- User Name & Identity -->
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-extrabold text-xs flex items-center justify-center shrink-0 uppercase border border-purple-200 dark:border-purple-800/80">
                                        {{ user.name.substring(0, 2) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white truncate">
                                            {{ user.name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate flex items-center gap-2">
                                            <span>@{{ user.username }}</span>
                                            <span v-if="user.email" class="text-slate-300 dark:text-slate-600">&bull;</span>
                                            <span v-if="user.email">{{ user.email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Branch Assignment -->
                            <td class="py-3.5 px-4">
                                <div v-if="user.branch" class="flex items-center gap-1.5">
                                    <a
                                        :href="user.branch.pos_url"
                                        target="_blank"
                                        class="font-semibold text-slate-800 dark:text-slate-200 hover:text-purple-600 dark:hover:text-purple-400 transition inline-flex items-center gap-1"
                                        title="Open Branch POS"
                                    >
                                        <span>{{ user.branch.name }}</span>
                                        <svg class="w-3 h-3 text-slate-400 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                                <span v-else class="text-slate-400 italic">Unassigned</span>
                            </td>

                            <!-- Roles -->
                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="r in user.roles"
                                        :key="r.id"
                                        :class="[
                                            'px-2 py-0.5 rounded-md text-[10px] font-bold capitalize',
                                            r.name === 'branch-admin'
                                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800'
                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700'
                                        ]"
                                    >
                                        {{ r.name.replace('-', ' ') }}
                                    </span>
                                </div>
                            </td>

                            <!-- Active Status -->
                            <td class="py-3.5 px-4">
                                <span
                                    v-if="user.is_active"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Active</span>
                                </span>
                                <div v-else class="space-y-0.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span>Disabled</span>
                                    </span>
                                    <p v-if="user.disabled_reason" class="text-[10px] text-slate-400 dark:text-slate-500 line-clamp-1 max-w-[140px]" :title="user.disabled_reason">
                                        {{ user.disabled_reason }}
                                    </p>
                                </div>
                            </td>

                            <!-- Created At -->
                            <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">
                                {{ formatDate(user.created_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- View Details / History -->
                                    <button
                                        type="button"
                                        @click="openDetailDrawer(user)"
                                        class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition cursor-pointer"
                                        title="View profile and branch assignment history"
                                    >
                                        Details
                                    </button>

                                    <!-- Move Branch -->
                                    <button
                                        v-if="user.can_move && can_manage_staff"
                                        type="button"
                                        @click="openMoveModal(user)"
                                        class="px-2.5 py-1.5 rounded-xl bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/50 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/80 font-semibold text-xs transition cursor-pointer flex items-center gap-1"
                                        title="Transfer user to another branch in this organization"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        <span>Move</span>
                                    </button>

                                    <!-- Disable Button -->
                                    <button
                                        v-if="user.is_active && user.can_disable && can_manage_staff"
                                        type="button"
                                        @click="openDisableModal(user)"
                                        class="px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/80 font-semibold text-xs transition cursor-pointer"
                                        title="Disable user account"
                                    >
                                        Disable
                                    </button>

                                    <!-- Enable Button -->
                                    <button
                                        v-if="!user.is_active && user.can_enable && can_manage_staff"
                                        type="button"
                                        @click="openEnableModal(user)"
                                        class="px-2.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80 font-semibold text-xs transition cursor-pointer"
                                        title="Re-enable user account"
                                    >
                                        Enable
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="py-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 mx-auto flex items-center justify-center text-slate-400">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <div class="font-bold text-slate-700 dark:text-slate-300 text-sm">No staff users found</div>
                                    <p class="text-xs text-slate-500">No users match your active filters or search keyword.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Table Pagination ─────────────────────────────────────────── -->
            <div v-if="users.total > 0" class="p-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <div>
                    Showing <strong>{{ users.from }}–{{ users.to }}</strong> of <strong>{{ users.total }}</strong> staff members
                </div>

                <div class="flex items-center gap-1.5">
                    <Link
                        v-for="(link, i) in users.links"
                        :key="i"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 rounded-xl font-semibold transition',
                            link.active
                                ? 'bg-purple-600 text-white shadow-xs'
                                : link.url
                                    ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                                    : 'text-slate-300 dark:text-slate-600 cursor-not-allowed'
                        ]"
                    />
                </div>
            </div>
        </div>

        <!-- ══ DETAIL & BRANCH HISTORY DRAWER ═════════════════════════════════ -->
        <div
            v-if="isDetailDrawerOpen"
            class="fixed inset-0 z-50 overflow-hidden bg-slate-900/60 backdrop-blur-xs flex justify-end"
            @click.self="closeDetailDrawer"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-900 h-full shadow-2xl flex flex-col border-l border-slate-200 dark:border-slate-800 transform transition-transform duration-300 ease-in-out">
                
                <!-- Drawer Header -->
                <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Staff Member Profile</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Account status & assignment history</p>
                    </div>
                    <button
                        @click="closeDetailDrawer"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Drawer Content -->
                <div class="flex-1 overflow-y-auto p-5 space-y-6">
                    <div v-if="isLoadingDetail" class="py-12 text-center text-slate-400 space-y-2">
                        <div class="w-6 h-6 border-2 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
                        <p class="text-xs">Loading profile & history...</p>
                    </div>

                    <template v-else-if="userDetail">
                        <!-- User Card -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/80 space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-purple-600 text-white font-extrabold text-base flex items-center justify-center shrink-0 uppercase shadow-xs">
                                    {{ userDetail.name.substring(0, 2) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ userDetail.name }}</h4>
                                    <p class="text-xs text-slate-500">@{{ userDetail.username }}</p>
                                    <p v-if="userDetail.email" class="text-xs text-slate-500 truncate">{{ userDetail.email }}</p>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-200 dark:border-slate-700/80 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Current Branch</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ userDetail.branch?.name || 'Unassigned' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Account Status</span>
                                    <span :class="userDetail.is_active ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'">
                                        {{ userDetail.is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Disabled Audit Info -->
                            <div v-if="!userDetail.is_active" class="pt-3 border-t border-slate-200 dark:border-slate-700/80 text-xs space-y-1">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span>Disabled On: {{ formatDate(userDetail.disabled_at) }}</span>
                                    <span v-if="userDetail.disabled_by">By: {{ userDetail.disabled_by.name }}</span>
                                </div>
                                <p v-if="userDetail.disabled_reason" class="text-xs bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 p-2.5 rounded-xl border border-rose-200/80 dark:border-rose-800/80">
                                    <strong>Reason:</strong> {{ userDetail.disabled_reason }}
                                </p>
                            </div>
                        </div>

                        <!-- Branch History Timeline (§10) -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center justify-between">
                                <span>Branch Assignment History</span>
                                <span class="text-[10px] lowercase text-slate-400">({{ userDetail.history?.length || 0 }} transfers)</span>
                            </h4>

                            <div v-if="userDetail.history && userDetail.history.length > 0" class="relative pl-6 space-y-4 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                                <div
                                    v-for="item in userDetail.history"
                                    :key="item.id"
                                    class="relative space-y-1 text-xs"
                                >
                                    <!-- Timeline Dot -->
                                    <span
                                        :class="[
                                            'absolute -left-6 top-1 w-2.5 h-2.5 rounded-full border-2 border-white dark:border-slate-900',
                                            item.is_current ? 'bg-purple-600 ring-4 ring-purple-100 dark:ring-purple-950' : 'bg-slate-300 dark:bg-slate-700'
                                        ]"
                                    ></span>

                                    <div class="flex items-center justify-between">
                                        <strong class="font-bold text-slate-900 dark:text-white">
                                            {{ item.branch?.name || 'Branch' }}
                                        </strong>
                                        <span v-if="item.is_current" class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                            Current
                                        </span>
                                    </div>

                                    <div class="text-[11px] text-slate-400">
                                        <span>From {{ formatDate(item.started_at) }}</span>
                                        <span v-if="item.ended_at"> &rarr; {{ formatDate(item.ended_at) }}</span>
                                        <span v-else> &rarr; Present</span>
                                    </div>

                                    <div v-if="item.reason" class="text-[11px] text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/80 p-2 rounded-lg border border-slate-200/60 dark:border-slate-700/60 mt-1">
                                        <span class="font-medium text-slate-400">Note:</span> {{ item.reason }}
                                    </div>

                                    <div v-if="item.moved_by" class="text-[10px] text-slate-400">
                                        Moved by {{ item.moved_by.name }}
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 text-center text-xs text-slate-400">
                                This user has remained at their original branch since account creation.
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Drawer Footer -->
                <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex justify-end">
                    <button
                        @click="closeDetailDrawer"
                        class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 font-semibold text-xs transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>

            </div>
        </div>

        <!-- ══ MOVE USER TO ANOTHER BRANCH MODAL ══════════════════════════════ -->
        <div
            v-if="isMoveModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden transform transition-all">
                
                <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Transfer Staff to Another Branch</h3>
                        <p class="text-xs text-slate-500">Atomic relocation within {{ organization.name }}</p>
                    </div>
                    <button @click="closeMoveModal" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitMoveUser" class="p-5 space-y-4">
                    <!-- Current User & Source Branch Summary -->
                    <div class="p-3.5 rounded-2xl bg-purple-50 dark:bg-purple-950/40 border border-purple-100 dark:border-purple-800/60 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Staff Member</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ targetUserForMove?.name }} (@{{ targetUserForMove?.username }})</span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Current Branch</span>
                            <span class="font-bold text-purple-700 dark:text-purple-300">{{ targetUserForMove?.branch?.name }}</span>
                        </div>
                    </div>

                    <!-- Destination Branch Select -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Destination Branch <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="moveForm.destination_branch_id"
                            @change="handleDestinationBranchChange"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer"
                        >
                            <option value="">Select destination branch...</option>
                            <option
                                v-for="b in availableDestinationBranches"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Pre-check Blocks & Warnings Feedback -->
                    <div v-if="isCheckingMove" class="py-2 text-center text-xs text-slate-400 flex items-center justify-center gap-2">
                        <div class="w-3.5 h-3.5 border-2 border-purple-600 border-t-transparent rounded-full animate-spin"></div>
                        <span>Evaluating transfer compatibility...</span>
                    </div>

                    <div v-else-if="moveForm.destination_branch_id">
                        <!-- Blocking Errors -->
                        <div v-if="movePrecheck.blocks?.length > 0" class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                            <strong class="font-bold block">Transfer Blocked:</strong>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <li v-for="(b, idx) in movePrecheck.blocks" :key="idx">{{ b }}</li>
                            </ul>
                        </div>

                        <!-- Warnings -->
                        <div v-if="movePrecheck.warnings?.length > 0" class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                            <strong class="font-bold block">Important Notice:</strong>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <li v-for="(w, idx) in movePrecheck.warnings" :key="idx">{{ w }}</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Destination Role Select (Populated dynamically from destination branch) -->
                    <div v-if="movePrecheck.roles?.length > 0 && movePrecheck.can_move" class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Role at Destination Branch <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="moveForm.destination_role"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer capitalize"
                        >
                            <option v-for="r in movePrecheck.roles" :key="r.id" :value="r.name">
                                {{ r.name.replace('-', ' ') }}
                            </option>
                        </select>
                        <p class="text-[11px] text-slate-400">Permissions are automatically re-scoped to destination branch's RBAC team.</p>
                    </div>

                    <!-- Transfer Reason Textarea -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Reason for Transfer <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="moveForm.reason"
                            rows="2"
                            required
                            placeholder="State the operational reason for this staff relocation (audit logged)..."
                            class="w-full p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500"
                        ></textarea>
                    </div>

                    <div class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl text-[11px] text-slate-500 dark:text-slate-400">
                        &bull; Active sessions for this user will be ended immediately.<br />
                        &bull; Staff member will receive access to destination branch and must log in via its portal link.
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="closeMoveModal"
                            class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!movePrecheck.can_move || !moveForm.destination_branch_id || !moveForm.destination_role || !moveForm.reason || moveForm.processing"
                            class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs transition shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ moveForm.processing ? 'Transferring...' : 'Confirm Transfer' }}
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- ══ POST-TRANSFER SUCCESS MODAL WITH COPYABLE URL ══════════════════ -->
        <div
            v-if="showTransferSuccessModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <div class="space-y-1">
                    <h3 class="font-extrabold text-base text-slate-900 dark:text-white">Transfer Completed Successfully</h3>
                    <p class="text-xs text-slate-500">
                        {{ transferredUserName }} has been relocated. Because staff sign in through their assigned branch URL, their previous login link will no longer authenticate them.
                    </p>
                </div>

                <!-- Copyable URL Box -->
                <div class="p-3 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2 text-xs font-mono">
                    <span class="truncate text-slate-700 dark:text-slate-300 text-left select-all">{{ transferredLoginUrl }}</span>
                    <button
                        type="button"
                        @click="copyLoginLink"
                        class="px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-sans text-xs font-bold transition shrink-0 cursor-pointer"
                    >
                        {{ copiedSuccess ? 'Copied!' : 'Copy Link' }}
                    </button>
                </div>

                <div>
                    <button
                        type="button"
                        @click="showTransferSuccessModal = false"
                        class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-100 text-white dark:text-slate-900 font-bold text-xs transition"
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ DISABLE USER CONFIRMATION MODAL ════════════════════════════════ -->
        <div
            v-if="isDisableModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Disable Staff Account</h3>
                        <p class="text-xs text-slate-500">Revoke terminal access immediately</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-300">
                    Are you sure you want to disable <strong>{{ targetUserForDisable?.name }} (@{{ targetUserForDisable?.username }})</strong>? Their active sessions will be terminated and they will be barred from signing in. Historical sales records remain intact.
                </p>

                <div v-if="disableWarning" class="p-3 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 rounded-xl text-xs border border-amber-200 dark:border-amber-800/80">
                    {{ disableWarning }}
                </div>

                <form @submit.prevent="submitDisableUser" class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Reason for Disabling <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="disableForm.reason"
                            rows="2"
                            required
                            placeholder="Provide reason for disabling this account (audit logged)..."
                            class="w-full p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-rose-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button
                            type="button"
                            @click="closeDisableModal"
                            class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!disableForm.reason || disableForm.processing"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition shadow-xs disabled:opacity-50"
                        >
                            {{ disableForm.processing ? 'Disabling...' : 'Confirm Disable' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ ENABLE USER CONFIRMATION MODAL ═════════════════════════════════ -->
        <div
            v-if="isEnableModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Re-Enable Staff Account</h3>
                        <p class="text-xs text-slate-500">Restore terminal login access</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-300">
                    Re-enable access for <strong>{{ targetUserForEnable?.name }} (@{{ targetUserForEnable?.username }})</strong>? They will be able to log in to their assigned branch once more.
                </p>

                <form @submit.prevent="submitEnableUser" class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Optional Note / Reason
                        </label>
                        <input
                            v-model="enableForm.reason"
                            type="text"
                            placeholder="Optional reinstatement note..."
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button
                            type="button"
                            @click="closeEnableModal"
                            class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="enableForm.processing"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs disabled:opacity-50"
                        >
                            {{ enableForm.processing ? 'Re-Enabling...' : 'Confirm Re-Enable' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
