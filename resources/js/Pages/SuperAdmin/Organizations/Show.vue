<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const page = usePage()

const props = defineProps({
    organization: Object,
    branches: Array,
    adminUsers: Array,
    activityLogs: Array,
    allOrganizations: Array,
    subscription: Object,
    subscriptionHistories: Array,
    pricingPlans: Array,
    ecommerceStats: Object,
    ecommerceReservations: Array,
    ecommerceMovements: Array,
    ecommerceMappings: Array,
    aiCreditSummary: Object,
    aiCreditTopups: Array,
})

const activeTab = ref('branches')
const ecommerceSubTab = ref('reservations')
const ecommerceBranchFilter = ref('all')

const filteredEcommerceReservations = computed(() => {
    if (ecommerceBranchFilter.value === 'all') return props.ecommerceReservations || []
    return (props.ecommerceReservations || []).filter(r => r.branch_id == ecommerceBranchFilter.value)
})

const filteredEcommerceMovements = computed(() => {
    if (ecommerceBranchFilter.value === 'all') return props.ecommerceMovements || []
    return (props.ecommerceMovements || []).filter(m => m.branch_id == ecommerceBranchFilter.value)
})

const filteredEcommerceMappings = computed(() => {
    if (ecommerceBranchFilter.value === 'all') return props.ecommerceMappings || []
    return (props.ecommerceMappings || []).filter(map => map.branch_id == ecommerceBranchFilter.value)
})

// Ecommerce Subtabs Pagination (15 items per page)
const resPerPage = 15
const resPage = ref(1)
const movPerPage = 15
const movPage = ref(1)
const mapPerPage = 15
const mapPage = ref(1)

watch([ecommerceBranchFilter, ecommerceSubTab], () => {
    resPage.value = 1
    movPage.value = 1
    mapPage.value = 1
})

const paginatedEcommerceReservations = computed(() => {
    const list = filteredEcommerceReservations.value
    const start = (resPage.value - 1) * resPerPage
    return list.slice(start, start + resPerPage)
})
const totalResPages = computed(() => Math.ceil(filteredEcommerceReservations.value.length / resPerPage) || 1)

const paginatedEcommerceMovements = computed(() => {
    const list = filteredEcommerceMovements.value
    const start = (movPage.value - 1) * movPerPage
    return list.slice(start, start + movPerPage)
})
const totalMovPages = computed(() => Math.ceil(filteredEcommerceMovements.value.length / movPerPage) || 1)

const paginatedEcommerceMappings = computed(() => {
    const list = filteredEcommerceMappings.value
    const start = (mapPage.value - 1) * mapPerPage
    return list.slice(start, start + mapPerPage)
})
const totalMapPages = computed(() => Math.ceil(filteredEcommerceMappings.value.length / mapPerPage) || 1)

// Subscription Management Form
const defaultPlanId = props.subscription?.pricing_plan_id || (props.pricingPlans?.[2]?.id || props.pricingPlans?.[0]?.id || '')
const subscriptionForm = useForm({
    pricing_plan_id: props.subscription?.pricing_plan_id || defaultPlanId,
    started_at: props.subscription?.started_at || new Date().toISOString().split('T')[0],
    renewal_date: props.subscription?.renewal_date || new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    status: props.subscription?.status || 'active',
    notes: props.subscription?.notes || '',
})

const submitSubscription = () => {
    subscriptionForm.post(route('superadmin.organizations.subscription.update', props.organization.id), {
        preserveScroll: true,
    })
}

// Edit Organization Modal
const showEditModal = ref(false)
const editForm = useForm({
    name: props.organization.name,
    contact_name: props.organization.contact_name || '',
    contact_email: props.organization.contact_email || '',
    contact_phone: props.organization.contact_phone || '',
    address: props.organization.address || '',
    status: props.organization.status || 'active',
    can_create_branches: props.organization.can_create_branches ?? true,
    notes: props.organization.notes || '',
})

const submitEdit = () => {
    editForm.put(route('superadmin.organizations.update', props.organization.id), {
        onSuccess: () => {
            showEditModal.value = false
        },
    })
}

const toggleBranchCreation = () => {
    router.post(route('superadmin.organizations.toggle-branch-creation', props.organization.id), {}, {
        preserveScroll: true,
    })
}

// Move Branch Modal
const showMoveModal = ref(false)
const movingBranch = ref(null)
const moveForm = useForm({
    organization_id: '',
})

const openMoveModal = (branch) => {
    movingBranch.value = branch
    moveForm.organization_id = ''
    showMoveModal.value = true
}

const submitMoveBranch = () => {
    if (!movingBranch.value) return
    moveForm.post(route('superadmin.branches.move-organization', { branch: movingBranch.value.slug }), {
        onSuccess: () => {
            showMoveModal.value = false
            movingBranch.value = null
        },
    })
}

// Create Organization Admin User Modal
const showCreateUserModal = ref(false)
const userForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
})

const submitCreateUser = () => {
    const token = page.props.csrf_token || (typeof document !== 'undefined' ? document.head.querySelector('meta[name="csrf-token"]')?.content : '') || ''
    userForm
        .transform((data) => ({
            ...data,
            _token: token,
        }))
        .post(route('superadmin.organizations.users.store', { organization: props.organization.id }), {
            preserveScroll: true,
            headers: token ? { 'X-CSRF-TOKEN': token } : {},
            onSuccess: () => {
                showCreateUserModal.value = false
                userForm.reset()
            },
        })
}

// Toggle Admin User Status
const toggleUser = (user) => {
    router.post(route('superadmin.organizations.users.toggle', {
        organization: props.organization.id,
        user: user.id,
    }))
}

// Create Branch for this Organization Modal
const showCreateBranchModal = ref(false)
const showBranchPass = ref(false)
const branchForm = useForm({
    organization_id: props.organization.id,
    name: '',
    slug: '',
    address: '',
    phone: '',
    email: '',
    is_offline_enabled: false,
    is_offline_qty_deduction: false,
    held_sales_visibility: 'device_only',
    is_turnstile_enabled: true,
    admin_name: '',
    admin_username: '',
    admin_email: '',
    admin_password: '',
})

const openCreateBranchModal = () => {
    branchForm.reset()
    branchForm.organization_id = props.organization.id
    showCreateBranchModal.value = true
}

const generateBranchSlug = () => {
    branchForm.slug = branchForm.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '')
}

const submitCreateBranch = () => {
    branchForm.post(route('superadmin.branches.store'), {
        onSuccess: () => {
            showCreateBranchModal.value = false
            branchForm.reset()
        },
    })
}

// Reset Admin User Password Modal
const showResetPasswordModal = ref(false)
const resettingUser = ref(null)
const resetPasswordForm = useForm({
    password: '',
})

const openResetPasswordModal = (user) => {
    resettingUser.value = user
    resetPasswordForm.password = ''
    showResetPasswordModal.value = true
}

const submitResetPassword = () => {
    if (!resettingUser.value) return
    resetPasswordForm.post(route('superadmin.organizations.users.password', {
        organization: props.organization.id,
        user: resettingUser.value.id,
    }), {
        onSuccess: () => {
            showResetPasswordModal.value = false
            resettingUser.value = null
            resetPasswordForm.reset()
        },
    })
}

// AI Credit Top-up Modal & Form
const showAddCreditsModal = ref(false)
const creditForm = useForm({
    credits: 1000,
    branch_id: '',
    type: 'manual_grant',
    expires_option: 'never',
    expires_at: '',
    notes: '',
})

const openAddCreditsModal = (branchId = '') => {
    creditForm.reset()
    creditForm.credits = 1000
    creditForm.branch_id = branchId || ''
    creditForm.type = 'manual_grant'
    creditForm.expires_option = 'never'
    creditForm.expires_at = ''
    creditForm.notes = ''
    showAddCreditsModal.value = true
}

const submitAddCredits = () => {
    let expiryVal = null
    if (creditForm.expires_option === 'month_end') {
        const now = new Date()
        const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0)
        expiryVal = endOfMonth.toISOString().split('T')[0]
    } else if (creditForm.expires_option === 'custom') {
        expiryVal = creditForm.expires_at
    }

    creditForm.transform((data) => ({
        ...data,
        branch_id: data.branch_id || null,
        expires_at: expiryVal,
    })).post(route('superadmin.organizations.ai-credits.topup', props.organization.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddCreditsModal.value = false
            creditForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="`${organization.name} — Super Admin`" />

    <div class="space-y-6">
        <!-- Breadcrumb & Top Bar -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <Link :href="route('superadmin.organizations.index')" class="hover:text-theme font-medium">Organizations</Link>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ organization.name }}</span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="toggleBranchCreation"
                    type="button"
                    :class="organization.can_create_branches !== false
                        ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border-rose-200 dark:border-rose-800 hover:bg-rose-100'
                        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100'"
                    class="px-3 py-1.5 border rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1.5"
                    :title="organization.can_create_branches !== false ? 'Click to disable branch creation for this org' : 'Click to enable branch creation for this org'"
                >
                    <span>{{ organization.can_create_branches !== false ? '🔒 Disable Branch Creation' : '🔓 Enable Branch Creation' }}</span>
                </button>

                <button
                    @click="showEditModal = true"
                    type="button"
                    class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition cursor-pointer"
                >
                    Edit Profile
                </button>
            </div>
        </div>

        <!-- Organization Overview Banner Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800/80 pb-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-2xl font-bold">
                        🏢
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ organization.name }}</h1>
                            <span
                                :class="organization.status === 'active' ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold border capitalize"
                            >
                                {{ organization.status }}
                            </span>
                            <span
                                :class="organization.can_create_branches !== false ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/30' : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/30'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold border flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="organization.can_create_branches !== false ? 'bg-indigo-600' : 'bg-rose-600'"></span>
                                {{ organization.can_create_branches !== false ? 'Branch Creation: Allowed' : 'Branch Creation: Disabled' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                            Organization ID #{{ organization.id }} &bull; Created {{ new Date(organization.created_at).toLocaleDateString() }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <div class="text-center sm:text-right">
                        <div class="text-xs text-slate-400 font-medium">Branches</div>
                        <div class="text-xl font-black text-indigo-600 dark:text-indigo-400">{{ branches.length }}</div>
                    </div>
                    <div class="text-center sm:text-right">
                        <div class="text-xs text-slate-400 font-medium">Org Admins</div>
                        <div class="text-xl font-black text-purple-600 dark:text-purple-400">{{ adminUsers.length }}</div>
                    </div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 block font-medium">Primary Contact</span>
                    <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ organization.contact_name || '—' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Contact Email</span>
                    <span class="text-slate-800 dark:text-slate-200 font-mono">{{ organization.contact_email || '—' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Phone</span>
                    <span class="text-slate-800 dark:text-slate-200">{{ organization.contact_phone || '—' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Address</span>
                    <span class="text-slate-800 dark:text-slate-200 line-clamp-2">{{ organization.address || '—' }}</span>
                </div>
            </div>

            <div v-if="organization.notes" class="text-xs bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-400">
                <strong class="text-slate-700 dark:text-slate-300">Notes:</strong> {{ organization.notes }}
            </div>
        </div>

        <!-- Section Navigation Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
            <button
                @click="activeTab = 'branches'"
                :class="activeTab === 'branches' ? 'border-theme text-theme font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2.5 text-xs border-b-2 transition cursor-pointer flex items-center gap-1.5"
            >
                <span>🏪 Attached Branches</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold">
                    {{ branches.length }}
                </span>
            </button>

            <button
                @click="activeTab = 'subscription'"
                :class="activeTab === 'subscription' ? 'border-theme text-theme font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2.5 text-xs border-b-2 transition cursor-pointer flex items-center gap-1.5"
            >
                <span>💳 Subscription & Plan</span>
                <span
                    :class="subscription ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400'"
                    class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                >
                    {{ subscription?.plan_name || 'Unset' }}
                </span>
            </button>

            <button
                @click="activeTab = 'admins'"
                :class="activeTab === 'admins' ? 'border-theme text-theme font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2.5 text-xs border-b-2 transition cursor-pointer flex items-center gap-1.5"
            >
                <span>👤 Organization Admins</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 font-bold">
                    {{ adminUsers.length }}
                </span>
            </button>

            <button
                @click="activeTab = 'logs'"
                :class="activeTab === 'logs' ? 'border-theme text-theme font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2.5 text-xs border-b-2 transition cursor-pointer flex items-center gap-1.5"
            >
                <span>📋 Audit & Activity History</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold">
                    {{ activityLogs.length }}
                </span>
            </button>

            <button
                @click="activeTab = 'ecommerce'"
                :class="activeTab === 'ecommerce' ? 'border-theme text-theme font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2.5 text-xs border-b-2 transition cursor-pointer flex items-center gap-1.5"
            >
                <span>🛍️ Ecommerce Activity</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold">
                    {{ ecommerceStats?.connected_branches_count || 0 }} linked
                </span>
            </button>

            <button
                @click="activeTab = 'ai_credits'"
                :class="activeTab === 'ai_credits' ? 'border-theme text-theme font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2.5 text-xs border-b-2 transition cursor-pointer flex items-center gap-1.5"
            >
                <span>⚡ AI Credits & Quota</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 font-bold">
                    {{ (aiCreditSummary?.remaining ?? 0).toLocaleString() }} available
                </span>
            </button>
        </div>

        <!-- ── TAB 1: Branches ──────────────────────────────────────────────── -->
        <div v-if="activeTab === 'branches'" class="space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Branches in {{ organization.name }}</h3>
                    <p class="text-xs text-slate-500">Business locations assigned to this organization client account.</p>
                </div>
                <button
                    @click="openCreateBranchModal"
                    type="button"
                    class="px-4 py-2 bg-theme hover:opacity-90 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Branch in this Org</span>
                </button>
            </div>

            <div v-if="branches.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="branch in branches"
                    :key="branch.id"
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                {{ branch.name }}
                            </h3>
                            <span class="text-xs text-indigo-600 dark:text-indigo-400 font-mono">/{{ branch.slug }}</span>
                        </div>
                        <span
                            :class="branch.is_active ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/30'"
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
                        >
                            {{ branch.is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </div>

                    <div class="text-xs text-slate-500 space-y-1">
                        <div v-if="branch.address">📍 {{ branch.address }}</div>
                        <div v-if="branch.phone">📞 {{ branch.phone }}</div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-500">
                        <span>Staff: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.users_count }}</strong></span>
                        <span>Items: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.items_count }}</strong></span>
                        <span>Sales: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.sales_count }}</strong></span>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                        <button
                            v-if="allOrganizations.length > 0"
                            @click="openMoveModal(branch)"
                            type="button"
                            class="px-2.5 py-1.5 text-xs bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 rounded-lg font-semibold hover:bg-purple-100 transition cursor-pointer"
                        >
                            Move to Another Org
                        </button>
                        <div v-else></div>

                        <Link
                            :href="route('superadmin.branches.show', { branch: branch.slug })"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition shadow-xs"
                        >
                            Open Workspace &rarr;
                        </Link>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-10 text-center space-y-3">
                <div class="text-2xl">🏪</div>
                <h4 class="font-bold text-slate-900 dark:text-white text-sm">No Branches Assigned</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    This organization currently has no branches. Create a new branch under this organization or move an existing branch here.
                </p>
                <Link
                    :href="route('superadmin.branches.index')"
                    class="inline-block px-4 py-2 bg-theme text-white text-xs font-bold rounded-xl transition"
                >
                    Manage Branches
                </Link>
            </div>
        </div>

        <!-- ── TAB 2: Organization Admins ──────────────────────────────────── -->
        <div v-if="activeTab === 'admins'" class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Organization Admin Accounts</h3>
                    <p class="text-xs text-slate-500">Administrators designated by the client to manage their organization's branches.</p>
                </div>
                <button
                    @click="showCreateUserModal = true"
                    type="button"
                    class="px-3 py-2 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5 cursor-pointer"
                >
                    <span>+ Add Org Admin</span>
                </button>
            </div>

            <div v-if="adminUsers.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Name / Username</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Added</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="user in adminUsers" :key="user.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-slate-100">{{ user.name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">@{{ user.username }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="user.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400'"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                >
                                    {{ user.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-[11px]">
                                {{ new Date(user.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openResetPasswordModal(user)"
                                        type="button"
                                        class="px-2 py-1 text-[11px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded font-semibold transition cursor-pointer"
                                    >
                                        Reset Pass
                                    </button>
                                    <button
                                        @click="toggleUser(user)"
                                        type="button"
                                        :class="user.is_active ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
                                        class="px-2 py-1 text-[11px] rounded font-bold transition cursor-pointer"
                                    >
                                        {{ user.is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-10 text-center space-y-3">
                <div class="text-2xl">👤</div>
                <h4 class="font-bold text-slate-900 dark:text-white text-sm">No Organization Admins Yet</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    Create an Organization Admin account to allow the client to log in and administer this organization.
                </p>
                <button
                    @click="showCreateUserModal = true"
                    type="button"
                    class="px-4 py-2 bg-theme text-white text-xs font-bold rounded-xl transition cursor-pointer"
                >
                    Create Organization Admin
                </button>
            </div>
        </div>

        <!-- ── TAB 3: Activity Logs ────────────────────────────────────────── -->
        <div v-if="activeTab === 'logs'" class="space-y-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Organization Audit Trail</h3>
                <p class="text-xs text-slate-500">Record of branch assignments, moves, admin additions, and profile updates.</p>
            </div>

            <div v-if="activityLogs.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Timestamp</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono text-[11px]">
                        <tr v-for="log in activityLogs" :key="log.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-2.5 text-slate-400 whitespace-nowrap">
                                {{ new Date(log.created_at).toLocaleString() }}
                            </td>
                            <td class="px-4 py-2.5 font-bold text-indigo-600 dark:text-indigo-400">
                                {{ log.action }}
                            </td>
                            <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">
                                {{ log.actor?.name || 'System / Artisan' }}
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 font-sans text-xs">
                                <span v-if="log.action === 'ai_credit_topup_granted'">
                                    ⚡ <strong>+{{ log.details?.credits?.toLocaleString() }} AI credits</strong> granted to <strong>{{ log.details?.branch_name || 'Organization' }}</strong>
                                    <span v-if="log.details?.expires_at" class="text-slate-400"> (Expires: {{ log.details.expires_at }})</span>
                                    <span v-if="log.details?.notes" class="text-slate-400 block italic">"{{ log.details.notes }}"</span>
                                </span>
                                <span v-else-if="log.details?.branch_name">
                                    Branch: <strong>{{ log.details.branch_name }}</strong>
                                    <span v-if="log.details.from_organization_name"> (moved from {{ log.details.from_organization_name }})</span>
                                </span>
                                <span v-else-if="log.details?.username">
                                    Admin: <strong>@{{ log.details.username }}</strong>
                                </span>
                                <span v-else>
                                    {{ JSON.stringify(log.details) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-xs text-slate-500">
                No activity records logged for this organization yet.
            </div>
        </div>

        <!-- ── TAB 4: Subscription & Plan ────────────────────────────────────── -->
        <div v-if="activeTab === 'subscription'" class="space-y-6">
            <!-- 1. Notice / Status Alert Banners -->
            <div v-if="!subscription" class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <h4 class="text-xs font-bold text-amber-900 dark:text-amber-200">No Active Plan Assigned</h4>
                    <p class="text-xs text-amber-700 dark:text-amber-300/90 mt-0.5">
                        This organization does not currently have a subscription plan recorded. Assign a plan below to track renewal cycles and feature entitlements.
                    </p>
                </div>
            </div>

            <div v-else-if="subscription.is_past_due" class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 flex items-start gap-3">
                <span class="text-xl">🚨</span>
                <div>
                    <h4 class="text-xs font-bold text-rose-900 dark:text-rose-200">Renewal Overdue (Soft Policy Active)</h4>
                    <p class="text-xs text-rose-700 dark:text-rose-300/90 mt-0.5">
                        Renewal date was <strong>{{ subscription.renewal_date_human }}</strong> ({{ Math.abs(subscription.days_until_renewal) }} days ago). Under our non-blocking policy, branches and services remain completely functional. Update the renewal date below once the client completes manual renewal.
                    </p>
                </div>
            </div>

            <div v-else-if="subscription.is_expiring_soon" class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-start gap-3">
                <span class="text-xl">⏰</span>
                <div>
                    <h4 class="text-xs font-bold text-amber-900 dark:text-amber-200">Subscription Renewal Approaching</h4>
                    <p class="text-xs text-amber-700 dark:text-amber-300/90 mt-0.5">
                        This plan renews in <strong>{{ subscription.days_until_renewal }} day(s)</strong> on <strong>{{ subscription.renewal_date_human }}</strong>.
                    </p>
                </div>
            </div>

            <!-- 2. Current Active Plan Hero Card -->
            <div v-if="subscription" class="bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
                <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-3 max-w-xl">
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-white/10 text-indigo-200 backdrop-blur-xs border border-white/10">
                                Current Organization Plan
                            </span>
                            <span
                                :class="{
                                    'bg-emerald-500/20 text-emerald-300 border-emerald-500/40': subscription.computed_status === 'active',
                                    'bg-amber-500/20 text-amber-300 border-amber-500/40': subscription.computed_status === 'grace_period',
                                    'bg-rose-500/20 text-rose-300 border-rose-500/40': subscription.computed_status === 'expired'
                                }"
                                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border capitalize"
                            >
                                {{ subscription.computed_status.replace('_', ' ') }}
                            </span>
                        </div>

                        <div>
                            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">
                                {{ subscription.plan_name }} Plan
                            </h2>
                            <p class="text-sm text-indigo-200/80 mt-1">
                                {{ subscription.plan_tagline || 'Configured commercial tier for this client account.' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-4 text-xs text-indigo-200/70 pt-2">
                            <div>📅 Started: <strong class="text-white">{{ subscription.started_at }}</strong></div>
                            <div>•</div>
                            <div>🔄 Renewal: <strong class="text-white">{{ subscription.renewal_date_human }}</strong></div>
                            <div>•</div>
                            <div>👤 Managed By: <strong class="text-white">{{ subscription.changed_by || 'Super Admin' }}</strong></div>
                        </div>

                        <div v-if="subscription.notes" class="text-xs bg-white/5 border border-white/10 rounded-xl p-3 text-indigo-100 italic">
                            "{{ subscription.notes }}"
                        </div>
                    </div>

                    <!-- Countdown Pill / Quick Metrics -->
                    <div class="flex flex-col sm:flex-row lg:flex-col gap-3 min-w-[200px]">
                        <div class="bg-white/10 backdrop-blur-xs border border-white/15 rounded-2xl p-4 text-center">
                            <span class="text-[11px] uppercase tracking-wider text-indigo-200/80 font-semibold block">Time to Renewal</span>
                            <span class="text-2xl font-black text-white mt-1 block">
                                {{ subscription.days_until_renewal >= 0 ? `${subscription.days_until_renewal} Days` : `${Math.abs(subscription.days_until_renewal)}d Overdue` }}
                            </span>
                        </div>

                        <div class="bg-white/5 backdrop-blur-xs border border-white/10 rounded-2xl p-4 text-xs space-y-1 text-indigo-200/80">
                            <div class="flex justify-between">
                                <span>Attached Branches:</span>
                                <strong class="text-white">{{ branches.length }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Org Admins:</span>
                                <strong class="text-white">{{ adminUsers.length }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Assign / Modify Subscription Form -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⚙️</span>
                        <span>Assign or Update Subscription Plan</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Manual trusted action for Platform Super Admin. Selecting a new plan immediately updates this client's active entitlement and logs an immutable entry to the subscription history.
                    </p>
                </div>

                <form @submit.prevent="submitSubscription" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Plan Selector -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Select Pricing Plan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="subscriptionForm.pricing_plan_id"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition cursor-pointer"
                            >
                                <option v-for="plan in pricingPlans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }} Plan — {{ plan.renewal_price }} ({{ plan.branches_limit }})
                                </option>
                            </select>
                            <div v-if="subscriptionForm.errors.pricing_plan_id" class="text-rose-500 text-xs mt-1">
                                {{ subscriptionForm.errors.pricing_plan_id }}
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Subscription Status <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="subscriptionForm.status"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition cursor-pointer capitalize"
                            >
                                <option value="active">Active</option>
                                <option value="grace_period">Grace Period</option>
                                <option value="expired">Expired</option>
                            </select>
                            <div v-if="subscriptionForm.errors.status" class="text-rose-500 text-xs mt-1">
                                {{ subscriptionForm.errors.status }}
                            </div>
                        </div>

                        <!-- Started At -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Contract Start Date <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="subscriptionForm.started_at"
                                type="date"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                            />
                            <div v-if="subscriptionForm.errors.started_at" class="text-rose-500 text-xs mt-1">
                                {{ subscriptionForm.errors.started_at }}
                            </div>
                        </div>

                        <!-- Renewal Date -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Renewal Due Date <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="subscriptionForm.renewal_date"
                                type="date"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                            />
                            <div v-if="subscriptionForm.errors.renewal_date" class="text-rose-500 text-xs mt-1">
                                {{ subscriptionForm.errors.renewal_date }}
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2 lg:col-span-3">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Billing / Administrative Notes (Optional)
                            </label>
                            <input
                                v-model="subscriptionForm.notes"
                                type="text"
                                placeholder="e.g. Annual renewal paid via direct transfer; renewed through October 2027"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                            />
                            <div v-if="subscriptionForm.errors.notes" class="text-rose-500 text-xs mt-1">
                                {{ subscriptionForm.errors.notes }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="submit"
                            :disabled="subscriptionForm.processing"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20 cursor-pointer flex items-center gap-2"
                        >
                            <span>{{ subscription ? 'Save Subscription Changes' : 'Activate Subscription Plan' }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 4. Subscription History Audit Ledger -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📜</span>
                            <span>Subscription History & Audit Log</span>
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Immutable chronological record of plan assignments, renewals, and administrator changes for this organization.
                        </p>
                    </div>
                    <span class="text-xs font-mono font-bold text-slate-400">
                        {{ subscriptionHistories.length }} change(s) recorded
                    </span>
                </div>

                <div v-if="subscriptionHistories.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Logged At</th>
                                <th class="px-4 py-3">Plan Assigned</th>
                                <th class="px-4 py-3">Renewal Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Changed By</th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <tr v-for="sh in subscriptionHistories" :key="sh.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400 whitespace-nowrap">
                                    {{ sh.created_at }}
                                </td>
                                <td class="px-4 py-2.5 font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ sh.plan_name }}
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    {{ sh.renewal_date }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400': sh.status === 'active',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400': sh.status === 'grace_period',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400': sh.status === 'expired'
                                        }"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                    >
                                        {{ sh.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">
                                    {{ sh.changed_by }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 italic">
                                    {{ sh.notes || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-6 text-center text-xs text-slate-500 italic">
                    No historical changes recorded yet for this organization.
                </div>
            </div>
        </div>

        <!-- ── TAB 5: Ecommerce Activity Aggregation (§3.1) ────────────────── -->
        <div v-if="activeTab === 'ecommerce'" class="space-y-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Connected Branches</span>
                    <div class="text-xl font-black text-indigo-600 dark:text-indigo-400 mt-1">
                        {{ ecommerceStats?.connected_branches_count || 0 }} / {{ branches.length }}
                    </div>
                    <span class="text-[10px] text-slate-400">Store-linked branches</span>
                </div>
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Active Stock Holds</span>
                    <div class="text-xl font-black text-amber-600 dark:text-amber-400 mt-1">
                        {{ ecommerceStats?.active_reservations_count || 0 }}
                    </div>
                    <span class="text-[10px] text-amber-500">Pending checkout holds</span>
                </div>
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Confirmed Orders</span>
                    <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                        {{ ecommerceStats?.confirmed_orders_count || 0 }}
                    </div>
                    <span class="text-[10px] text-emerald-500">Fulfilled sales</span>
                </div>
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Mapped Products</span>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-1">
                        {{ ecommerceStats?.active_mappings_count || 0 }}
                    </div>
                    <span class="text-[10px] text-slate-400">Live online sync</span>
                </div>
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs col-span-2 sm:col-span-1">
                    <span class="text-xs text-slate-500 font-medium">Inventory Logs</span>
                    <div class="text-xl font-black text-purple-600 dark:text-purple-400 mt-1">
                        {{ ecommerceStats?.total_movements_count || 0 }}
                    </div>
                    <span class="text-[10px] text-slate-400">All stock events</span>
                </div>
            </div>

            <!-- Subtab Navigation & Branch Filter -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            @click="ecommerceSubTab = 'reservations'"
                            :class="ecommerceSubTab === 'reservations' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-bold border border-indigo-200 dark:border-indigo-800' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                        >
                            🕒 Reservations ({{ filteredEcommerceReservations.length }})
                        </button>
                        <button
                            @click="ecommerceSubTab = 'movements'"
                            :class="ecommerceSubTab === 'movements' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-bold border border-indigo-200 dark:border-indigo-800' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                        >
                            📦 Movements ({{ filteredEcommerceMovements.length }})
                        </button>
                        <button
                            @click="ecommerceSubTab = 'mappings'"
                            :class="ecommerceSubTab === 'mappings' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-bold border border-indigo-200 dark:border-indigo-800' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                        >
                            🔗 Mappings ({{ filteredEcommerceMappings.length }})
                        </button>
                    </div>

                    <!-- Branch Selector -->
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-slate-400 font-medium">Filter Branch:</span>
                        <select
                            v-model="ecommerceBranchFilter"
                            class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="all">All Branches (Aggregated)</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">
                                {{ b.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Sub-tab 1: Reservations -->
                <div v-if="ecommerceSubTab === 'reservations'" class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Branch</th>
                                <th class="px-4 py-3">Order Ref</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Items</th>
                                <th class="px-4 py-3">Total Qty</th>
                                <th class="px-4 py-3">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="res in paginatedEcommerceReservations" :key="res.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                    {{ res.branch_name }}
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ res.external_order_reference }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400': res.status === 'confirmed',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400': res.status === 'pending',
                                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400': res.status === 'released'
                                        }"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold capitalize"
                                    >
                                        {{ res.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-0.5 max-w-xs">
                                        <div v-for="item in res.items" :key="item.name" class="truncate text-[11px]">
                                            {{ item.quantity }}x {{ item.name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold">{{ res.total_qty }}</td>
                                <td class="px-4 py-3 text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ res.created_at ? new Date(res.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }}
                                </td>
                            </tr>
                            <tr v-if="!filteredEcommerceReservations.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">
                                    No reservations found for this selection.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Reservations Pagination Footer -->
                    <div v-if="filteredEcommerceReservations.length > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/40">
                        <div>
                            Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ ((resPage - 1) * resPerPage) + 1 }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ Math.min(resPage * resPerPage, filteredEcommerceReservations.length) }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ filteredEcommerceReservations.length }}</span> reservations
                        </div>
                        <div v-if="totalResPages > 1" class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="resPage = Math.max(1, resPage - 1)"
                                :disabled="resPage <= 1"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                &laquo; Previous
                            </button>
                            <template v-for="p in totalResPages" :key="p">
                                <button
                                    v-if="p === 1 || p === totalResPages || Math.abs(p - resPage) <= 1"
                                    type="button"
                                    @click="resPage = p"
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer"
                                    :class="p === resPage
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                        : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                >
                                    {{ p }}
                                </button>
                                <span
                                    v-else-if="(p === 2 && resPage > 3) || (p === totalResPages - 1 && resPage < totalResPages - 2)"
                                    class="px-1 text-slate-400 select-none"
                                >
                                    ...
                                </span>
                            </template>
                            <button
                                type="button"
                                @click="resPage = Math.min(totalResPages, resPage + 1)"
                                :disabled="resPage >= totalResPages"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sub-tab 2: Movements -->
                <div v-if="ecommerceSubTab === 'movements'" class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Branch</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Item Details</th>
                                <th class="px-4 py-3">Qty Change</th>
                                <th class="px-4 py-3">Stock Impact</th>
                                <th class="px-4 py-3">Order Ref</th>
                                <th class="px-4 py-3">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="m in paginatedEcommerceMovements" :key="m.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                    {{ m.branch_name }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ m.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                    {{ m.item_name }}
                                </td>
                                <td class="px-4 py-3 font-mono font-bold" :class="m.quantity < 0 ? 'text-rose-600' : 'text-emerald-600'">
                                    {{ m.quantity }}
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-400">
                                    <span v-if="m.previous_stock !== null && m.current_stock !== null">
                                        {{ m.previous_stock }} &rarr; {{ m.current_stock }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-300 font-bold">
                                    {{ m.external_reference || '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ m.created_at ? new Date(m.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }}
                                </td>
                            </tr>
                            <tr v-if="!filteredEcommerceMovements.length">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400 italic">
                                    No inventory movements found for this selection.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Movements Pagination Footer -->
                    <div v-if="filteredEcommerceMovements.length > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/40">
                        <div>
                            Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ ((movPage - 1) * movPerPage) + 1 }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ Math.min(movPage * movPerPage, filteredEcommerceMovements.length) }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ filteredEcommerceMovements.length }}</span> movements
                        </div>
                        <div v-if="totalMovPages > 1" class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="movPage = Math.max(1, movPage - 1)"
                                :disabled="movPage <= 1"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                &laquo; Previous
                            </button>
                            <template v-for="p in totalMovPages" :key="p">
                                <button
                                    v-if="p === 1 || p === totalMovPages || Math.abs(p - movPage) <= 1"
                                    type="button"
                                    @click="movPage = p"
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer"
                                    :class="p === movPage
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                        : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                >
                                    {{ p }}
                                </button>
                                <span
                                    v-else-if="(p === 2 && movPage > 3) || (p === totalMovPages - 1 && movPage < totalMovPages - 2)"
                                    class="px-1 text-slate-400 select-none"
                                >
                                    ...
                                </span>
                            </template>
                            <button
                                type="button"
                                @click="movPage = Math.min(totalMovPages, movPage + 1)"
                                :disabled="movPage >= totalMovPages"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sub-tab 3: Mappings -->
                <div v-if="ecommerceSubTab === 'mappings'" class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Branch</th>
                                <th class="px-4 py-3">Item Name</th>
                                <th class="px-4 py-3">Price</th>
                                <th class="px-4 py-3">Online Product ID</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Registered / Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="map in paginatedEcommerceMappings" :key="map.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                    {{ map.branch_name }}
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                    {{ map.item_name }}
                                </td>
                                <td class="px-4 py-3 font-mono font-bold">
                                    ₦{{ (parseFloat(map.price) || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-4 py-3 font-mono text-indigo-600 dark:text-indigo-400 font-bold">
                                    {{ map.external_product_id }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="map.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                    >
                                        {{ map.is_active ? 'Active Online' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-400 text-[11px] whitespace-nowrap">
                                    <div>Reg: {{ map.registered_at ? new Date(map.registered_at).toLocaleDateString() : '—' }}</div>
                                    <div>Seen: {{ map.last_seen_at ? new Date(map.last_seen_at).toLocaleDateString() : '—' }}</div>
                                </td>
                            </tr>
                            <tr v-if="!filteredEcommerceMappings.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">
                                    No item mappings found for this selection.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mappings Pagination Footer -->
                    <div v-if="filteredEcommerceMappings.length > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/40">
                        <div>
                            Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ ((mapPage - 1) * mapPerPage) + 1 }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ Math.min(mapPage * mapPerPage, filteredEcommerceMappings.length) }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ filteredEcommerceMappings.length }}</span> mappings
                        </div>
                        <div v-if="totalMapPages > 1" class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="mapPage = Math.max(1, mapPage - 1)"
                                :disabled="mapPage <= 1"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                &laquo; Previous
                            </button>
                            <template v-for="p in totalMapPages" :key="p">
                                <button
                                    v-if="p === 1 || p === totalMapPages || Math.abs(p - mapPage) <= 1"
                                    type="button"
                                    @click="mapPage = p"
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer"
                                    :class="p === mapPage
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                        : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                >
                                    {{ p }}
                                </button>
                                <span
                                    v-else-if="(p === 2 && mapPage > 3) || (p === totalMapPages - 1 && mapPage < totalMapPages - 2)"
                                    class="px-1 text-slate-400 select-none"
                                >
                                    ...
                                </span>
                            </template>
                            <button
                                type="button"
                                @click="mapPage = Math.min(totalMapPages, mapPage + 1)"
                                :disabled="mapPage >= totalMapPages"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB 6: AI Credits & Quota ───────────────────────────────────── -->
        <div v-if="activeTab === 'ai_credits'" class="space-y-6">
            <!-- Header bar with Grant button -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-xs">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⚡</span>
                        <span>AI Quota & Purchased Top-ups</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Manage monthly plan quotas, purchased credit top-ups, and review automated rollover consumption.
                    </p>
                </div>
                <button
                    @click="openAddCreditsModal()"
                    type="button"
                    class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-purple-600/20 flex items-center gap-2 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add AI Credit Top-up</span>
                </button>
            </div>

            <!-- Important Policy & Rollover Callout Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 flex flex-col md:flex-row items-start gap-3.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-600/10 dark:bg-indigo-400/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 text-lg font-bold">
                    ℹ️
                </div>
                <div class="space-y-2 text-xs flex-1">
                    <div class="font-bold text-indigo-950 dark:text-indigo-200 text-sm">
                        Credit Policy & Consumption Hierarchy
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-slate-600 dark:text-slate-300">
                        <div class="bg-white/80 dark:bg-slate-900/80 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                            <span class="font-bold text-slate-800 dark:text-slate-200 block mb-1">1. Monthly Plan Quota</span>
                            <p class="text-[11px] leading-relaxed">
                                Refreshes on the 1st of every month. <strong>Expires strictly at the end of each month</strong> and does not carry over.
                            </p>
                        </div>
                        <div class="bg-white/80 dark:bg-slate-900/80 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                            <span class="font-bold text-slate-800 dark:text-slate-200 block mb-1">2. Top-up Packs (Rollover)</span>
                            <p class="text-[11px] leading-relaxed">
                                Purchased packs or manual admin grants <strong>roll over month-to-month</strong> without conflict until fully depleted.
                            </p>
                        </div>
                        <div class="bg-white/80 dark:bg-slate-900/80 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                            <span class="font-bold text-slate-800 dark:text-slate-200 block mb-1">3. Quota-First Deduction</span>
                            <p class="text-[11px] leading-relaxed">
                                Usage draws from the expiring <strong>Plan Quota first</strong>. Top-ups are only touched when monthly quota is exhausted.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview 4 Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Available -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Available</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">Live</span>
                    </div>
                    <div class="text-3xl font-black text-slate-900 dark:text-white mt-2">
                        {{ (aiCreditSummary?.remaining ?? 0).toLocaleString() }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                        <span class="text-emerald-600 font-bold">{{ (aiCreditSummary?.plan_remaining ?? 0).toLocaleString() }} plan</span>
                        <span>+</span>
                        <span class="text-indigo-600 font-bold">{{ (aiCreditSummary?.topup_remaining ?? 0).toLocaleString() }} top-up</span>
                    </div>
                </div>

                <!-- Monthly Plan Quota -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Monthly Plan Quota</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">Expires Monthly</span>
                    </div>
                    <div class="text-3xl font-black text-slate-900 dark:text-white mt-2">
                        {{ (aiCreditSummary?.plan_remaining ?? 0).toLocaleString() }}
                        <span class="text-sm font-normal text-slate-400">/ {{ (aiCreditSummary?.monthly_quota ?? 0).toLocaleString() }}</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1">
                        {{ (aiCreditSummary?.used ?? 0).toLocaleString() }} used this month &bull; Resets {{ aiCreditSummary?.resets_at ? new Date(aiCreditSummary.resets_at).toLocaleDateString() : 'end of month' }}
                    </div>
                </div>

                <!-- Top-up Balance -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Top-up Balance</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">Rollover Active</span>
                    </div>
                    <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-2">
                        {{ (aiCreditSummary?.topup_remaining ?? 0).toLocaleString() }}
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1">
                        Carries over month-to-month until consumed
                    </div>
                </div>

                <!-- Plan Tier & Allocation -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Plan & Scope</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 capitalize">{{ aiCreditSummary?.pool_type || 'organization' }}</span>
                    </div>
                    <div class="text-lg font-black text-slate-900 dark:text-white mt-2 truncate">
                        {{ aiCreditSummary?.plan_name || 'Standard Plan' }}
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1">
                        {{ aiCreditSummary?.pool_type === 'branch' ? 'Allocated per individual branch' : 'Shared pool across all branches' }}
                    </div>
                </div>
            </div>

            <!-- Branch Breakdown Grid / Table -->
            <div v-if="aiCreditSummary?.branch_breakdown && aiCreditSummary.branch_breakdown.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs space-y-3 p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>🏪</span>
                            <span>Branch-by-Branch Usage & Dedicated Top-ups</span>
                        </h4>
                        <p class="text-xs text-slate-500">Breakdown of AI consumption this month and branch-dedicated top-up balances.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-2.5">Branch</th>
                                <th class="px-4 py-2.5">Used This Month</th>
                                <th class="px-4 py-2.5">Dedicated Top-up Balance</th>
                                <th class="px-4 py-2.5 text-right">Quick Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="b in aiCreditSummary.branch_breakdown" :key="b.branch_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ b.name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">/{{ b.slug }}</div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-300">
                                    {{ b.used_this_month.toLocaleString() }} credits
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="b.topup_remaining > 0" class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                                        {{ b.topup_remaining.toLocaleString() }} credits
                                    </span>
                                    <span v-else class="text-slate-400 text-xs italic">
                                        Uses shared pool
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="openAddCreditsModal(b.branch_id)"
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition cursor-pointer"
                                    >
                                        + Top Up Branch
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top-Up History Ledger -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📜</span>
                            <span>Top-up Credit Grants Ledger</span>
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Immutable record of all purchased and granted top-up credit packs.
                        </p>
                    </div>
                    <span class="text-xs font-mono font-bold text-slate-400">
                        {{ (aiCreditTopups || []).length }} grant(s)
                    </span>
                </div>

                <div v-if="aiCreditTopups && aiCreditTopups.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-2.5">Date</th>
                                <th class="px-4 py-2.5">Target</th>
                                <th class="px-4 py-2.5">Credits Granted</th>
                                <th class="px-4 py-2.5">Remaining</th>
                                <th class="px-4 py-2.5">Expiration / Rollover</th>
                                <th class="px-4 py-2.5">Granted By</th>
                                <th class="px-4 py-2.5">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="topup in aiCreditTopups" :key="topup.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-2.5 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                    {{ topup.created_at }}
                                </td>
                                <td class="px-4 py-2.5 font-bold text-slate-800 dark:text-slate-200">
                                    {{ topup.branch_name }}
                                </td>
                                <td class="px-4 py-2.5 font-bold text-indigo-600 dark:text-indigo-400">
                                    +{{ topup.credits.toLocaleString() }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span
                                        :class="topup.credits_remaining > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-400 line-through'"
                                    >
                                        {{ topup.credits_remaining.toLocaleString() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <span v-if="topup.expires_at" class="text-xs">
                                        <span v-if="topup.is_expired" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400">
                                            Expired ({{ topup.expires_at }})
                                        </span>
                                        <span v-else class="text-slate-600 dark:text-slate-300">
                                            Expires {{ topup.expires_at }}
                                        </span>
                                    </span>
                                    <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                                        Rollover (No Expiry)
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">
                                    {{ topup.granted_by }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 italic">
                                    {{ topup.notes || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-8 text-center text-xs text-slate-500 italic space-y-2">
                    <p>No credit top-up packs have been granted yet to this organization.</p>
                    <button
                        @click="openAddCreditsModal()"
                        type="button"
                        class="text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline cursor-pointer"
                    >
                        + Grant First Top-up
                    </button>
                </div>
            </div>
        </div>

        <!-- ── MODALS ──────────────────────────────────────────────────────── -->

        <!-- Move Branch Modal -->
        <div v-if="showMoveModal && movingBranch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl my-auto">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-sm">Move Branch Organization</h2>
                    <button @click="showMoveModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    Move branch <strong class="text-slate-900 dark:text-slate-100">{{ movingBranch.name }}</strong> from <strong>{{ organization.name }}</strong> to another client organization.
                </p>

                <form @submit.prevent="submitMoveBranch" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Destination Organization *</label>
                        <select
                            v-model="moveForm.organization_id"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition cursor-pointer"
                        >
                            <option value="" disabled>-- Select Destination Organization --</option>
                            <option v-for="org in allOrganizations" :key="org.id" :value="org.id">
                                {{ org.name }}
                            </option>
                        </select>
                        <div v-if="moveForm.errors.organization_id" class="text-rose-500 text-xs mt-1">{{ moveForm.errors.organization_id }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2.5">
                        <button type="button" @click="showMoveModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="moveForm.processing || !moveForm.organization_id" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-sm disabled:opacity-50 cursor-pointer">
                            <span v-if="moveForm.processing">Moving...</span>
                            <span v-else>Confirm & Move</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Organization Admin Modal -->
        <div v-if="showCreateUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">New Organization Admin</h2>
                    <button @click="showCreateUserModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitCreateUser" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                        <input
                            v-model="userForm.name"
                            type="text"
                            required
                            placeholder="e.g. Jane Administrator"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="userForm.errors.name" class="text-rose-500 text-xs mt-1">{{ userForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                        <input
                            v-model="userForm.username"
                            type="text"
                            required
                            placeholder="admin_username"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="userForm.errors.username" class="text-rose-500 text-xs mt-1">{{ userForm.errors.username }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                        <input
                            v-model="userForm.email"
                            type="email"
                            required
                            placeholder="admin@client.com"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="userForm.errors.email" class="text-rose-500 text-xs mt-1">{{ userForm.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password *</label>
                        <input
                            v-model="userForm.password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="userForm.errors.password" class="text-rose-500 text-xs mt-1">{{ userForm.errors.password }}</div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                        <button type="button" @click="showCreateUserModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="userForm.processing" class="px-5 py-2.5 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-md shadow-theme/20 cursor-pointer disabled:opacity-50">
                            <span v-if="userForm.processing">Creating...</span>
                            <span v-else>Create Org Admin</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div v-if="showResetPasswordModal && resettingUser" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-sm">Reset Password for @{{ resettingUser.username }}</h2>
                    <button @click="showResetPasswordModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitResetPassword" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password (min 8 chars) *</label>
                        <input
                            v-model="resetPasswordForm.password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-theme transition"
                        />
                        <div v-if="resetPasswordForm.errors.password" class="text-rose-500 text-xs mt-1">{{ resetPasswordForm.errors.password }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-2.5">
                        <button type="button" @click="showResetPasswordModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="resetPasswordForm.processing" class="px-4 py-2 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-sm disabled:opacity-50 cursor-pointer">
                            <span v-if="resetPasswordForm.processing">Updating...</span>
                            <span v-else>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Profile Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
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
                    <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700/80">
                        <label class="flex items-center justify-between cursor-pointer">
                            <div class="space-y-0.5 pr-4">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Allow Organization Branch Creation</span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    When checked, Org Admins can create new branches from the Org Portal within plan limits. Uncheck to disable.
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
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-5 py-2.5 bg-theme hover:bg-theme/90 text-white rounded-xl text-xs font-bold transition shadow-md shadow-theme/20 cursor-pointer disabled:opacity-50">
                            <span v-if="editForm.processing">Saving...</span>
                            <span v-else>Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Branch in this Organization Modal -->
        <div v-if="showCreateBranchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Create Branch for {{ organization.name }}</h2>
                        <p class="text-xs text-slate-500">Adds a new location and its initial branch administrator</p>
                    </div>
                    <button @click="showCreateBranchModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreateBranch" class="space-y-4">
                    <!-- Validation Error Summary Banner -->
                    <div v-if="Object.keys(branchForm.errors).length > 0" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs space-y-1">
                        <p class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Unable to create branch. Please correct errors below:</span>
                        </p>
                        <ul class="list-disc list-inside space-y-0.5 pl-1 text-[11px]">
                            <li v-for="(err, field) in branchForm.errors" :key="field">{{ err }}</li>
                        </ul>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">1. Branch Location Details</h3>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Name *</label>
                            <input
                                v-model="branchForm.name"
                                @input="generateBranchSlug"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                                placeholder="e.g. Ikeja Branch"
                            />
                            <div v-if="branchForm.errors.name" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Slug (URL Identifier) *</label>
                            <input
                                v-model="branchForm.slug"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-indigo-600 dark:text-indigo-300 outline-none focus:border-indigo-500 transition"
                                placeholder="ikeja-branch"
                            />
                            <span class="text-[11px] text-slate-500">POS Access URL: skynetpos.com/{{ branchForm.slug || 'slug' }}</span>
                            <div v-if="branchForm.errors.slug" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.slug }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                            <input v-model="branchForm.address" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone</label>
                                <input v-model="branchForm.phone" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                                <input v-model="branchForm.email" type="email" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">2. Initial Branch Administrator</h3>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Admin Full Name *</label>
                            <input v-model="branchForm.admin_name" type="text" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                            <div v-if="branchForm.errors.admin_name" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_name }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                                <input v-model="branchForm.admin_username" type="text" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition font-mono" />
                                <div v-if="branchForm.errors.admin_username" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_username }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email *</label>
                                <input v-model="branchForm.admin_email" type="email" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                                <div v-if="branchForm.errors.admin_email" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_email }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Initial Password (min 8 chars) *</label>
                            <div class="relative flex items-center">
                                <input v-model="branchForm.admin_password" :type="showBranchPass ? 'text' : 'password'" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 pr-9 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                                <button type="button" @click="showBranchPass = !showBranchPass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" :title="showBranchPass ? 'Hide password' : 'Show password'">
                                    <svg v-if="!showBranchPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                                </button>
                            </div>
                            <div v-if="branchForm.errors.admin_password" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_password }}</div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showCreateBranchModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="branchForm.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20 cursor-pointer disabled:opacity-50">
                            Create Branch in {{ organization.name }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add AI Credits Modal -->
        <div v-if="showAddCreditsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Grant AI Credit Top-up</h2>
                        <p class="text-xs text-slate-500">Allocate top-up credits to organization shared pool or a specific branch</p>
                    </div>
                    <button @click="showAddCreditsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitAddCredits" class="space-y-4">
                    <!-- Target Selection -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Recipient *</label>
                        <select
                            v-model="creditForm.branch_id"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition cursor-pointer"
                        >
                            <option value="">🏢 Entire Organization Pool (Shared across all branches)</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                🏪 {{ branch.name }} (Dedicated to this branch)
                            </option>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Organization pool credits can be consumed by any branch in this client account.
                        </p>
                    </div>

                    <!-- Credit Amount -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Credits to Grant *</label>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    v-for="amt in [500, 1000, 2500, 5000, 10000]"
                                    :key="amt"
                                    @click="creditForm.credits = amt"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer"
                                    :class="creditForm.credits === amt
                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200'"
                                >
                                    +{{ amt.toLocaleString() }}
                                </button>
                            </div>
                        </div>
                        <input
                            v-model.number="creditForm.credits"
                            type="number"
                            min="1"
                            required
                            placeholder="e.g. 1000"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                        />
                        <div v-if="creditForm.errors.credits" class="text-rose-500 text-xs mt-1">{{ creditForm.errors.credits }}</div>
                    </div>

                    <!-- Expiration Policy -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Expiration & Rollover Policy *</label>
                        <div class="space-y-2">
                            <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer" :class="{ 'border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/20': creditForm.expires_option === 'never' }">
                                <input
                                    v-model="creditForm.expires_option"
                                    type="radio"
                                    value="never"
                                    class="mt-0.5 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">🟢 Rollover Indefinitely (Recommended)</div>
                                    <div class="text-[11px] text-slate-500">Credits never expire; they carry over month-to-month until consumed.</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer" :class="{ 'border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/20': creditForm.expires_option === 'month_end' }">
                                <input
                                    v-model="creditForm.expires_option"
                                    type="radio"
                                    value="month_end"
                                    class="mt-0.5 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">⏳ Expire at End of Current Month</div>
                                    <div class="text-[11px] text-slate-500">Expires along with monthly plan reset (no carryover).</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer" :class="{ 'border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/20': creditForm.expires_option === 'custom' }">
                                <input
                                    v-model="creditForm.expires_option"
                                    type="radio"
                                    value="custom"
                                    class="mt-0.5 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div class="w-full">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">📅 Custom Expiration Date</div>
                                    <div class="text-[11px] text-slate-500">Specify an exact expiration date for this pack.</div>
                                    <div v-if="creditForm.expires_option === 'custom'" class="mt-2">
                                        <input
                                            v-model="creditForm.expires_at"
                                            type="date"
                                            required
                                            class="w-full bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason / Notes (Optional)</label>
                        <input
                            v-model="creditForm.notes"
                            type="text"
                            placeholder="e.g. Paid top-up package 5K, promo bonus, or customer service compensation"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                        />
                        <div v-if="creditForm.errors.notes" class="text-rose-500 text-xs mt-1">{{ creditForm.errors.notes }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2.5">
                        <button type="button" @click="showAddCreditsModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">Cancel</button>
                        <button
                            type="submit"
                            :disabled="creditForm.processing"
                            class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-purple-600/20 disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                        >
                            <span v-if="creditForm.processing">Granting Credits...</span>
                            <span v-else>Confirm & Grant Credits</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
