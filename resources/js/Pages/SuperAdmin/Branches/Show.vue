<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branch: Object,
    settings: Object,
    stats: Object,
    users: Array,
    roles: Array,
    all_permissions: Array,
    branch_items: Object,
    global_items: Array,
    categories: Array,
    recent_sales: Object,
    activity_logs: Object,
    filters: Object,
})

// ── Tab Management ─────────────────────────────────────────────────────────────
const activeTab = ref(props.filters?.active_tab || 'overview')
const setActiveTab = (tab) => {
    activeTab.value = tab
    const url = new URL(window.location.href)
    url.searchParams.set('tab', tab)
    window.history.replaceState({}, '', url.toString())
}

// ── Copied Feedback & Share Link ───────────────────────────────────────────
const copiedUrl = ref(false)
const fullBranchUrl = computed(() => {
    if (typeof window !== 'undefined' && window.location?.origin) {
        return `${window.location.origin}/${props.branch.slug}`
    }
    return `https://skynetpos.com/${props.branch.slug}`
})

const copyPosUrl = () => {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(fullBranchUrl.value)
    }
    copiedUrl.value = true
    setTimeout(() => { copiedUrl.value = false }, 2500)
}

// ── Tab 1: Profile & Identity Form ───────────────────────────────────────────
const profileForm = useForm({
    name: props.branch.name,
    slug: props.branch.slug,
    address: props.branch.address || '',
    phone: props.branch.phone || '',
    email: props.branch.email || '',
    is_active: props.branch.is_active,
})

const showSlugWarningModal = ref(false)

const handleProfileSubmit = () => {
    if (profileForm.slug !== props.branch.slug) {
        showSlugWarningModal.value = true
    } else {
        submitProfile()
    }
}

const submitProfile = () => {
    showSlugWarningModal.value = false
    profileForm.put(route('superadmin.branches.update', { branch: props.branch.slug }))
}

// ── Logo Upload ───────────────────────────────────────────────────────────────
const logoInput = ref(null)
const logoForm = useForm({
    logo: null,
})
const logoPreview = ref(props.branch.logo_url)

const handleLogoChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    logoForm.logo = file
    logoPreview.value = URL.createObjectURL(file)
}

const submitLogo = () => {
    if (!logoForm.logo) return
    logoForm.post(route('superadmin.branches.logo.update', { branch: props.branch.slug }), {
        onSuccess: () => {
            logoForm.reset()
        }
    })
}

// ── Tab 2: Staff & Access Management ──────────────────────────────────────────
const showCreateUserModal = ref(false)
const showCreatePass = ref(false)
const showResetPass = ref(false)
const showResetConfirmPass = ref(false)

const userForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'branch-admin',
    permissions: [],
    is_active: true,
})

const selectAllCreate = () => {
    userForm.permissions = props.all_permissions.filter(p => p.name !== 'canResetPassword').map(p => p.name)
}
const deselectAllCreate = () => {
    userForm.permissions = []
}

watch(() => userForm.role, (newRole) => {
    if (newRole === 'branch-admin') {
        selectAllCreate()
    } else if (newRole === 'cashier') {
        userForm.permissions = [
            'canAccessPos',
            'canViewSales',
            'canViewEndOfDay',
            'canApplyDiscount',
            'canProcessReturn',
            'canManageCustomers',
        ]
    }
}, { immediate: true })

const submitCreateUser = () => {
    userForm.post(route('superadmin.branches.users.store', { branch: props.branch.slug }), {
        onSuccess: () => {
            showCreateUserModal.value = false
            userForm.reset()
        },
    })
}

const showEditUserModal = ref(false)
const editingUser = ref(null)
const editUserForm = useForm({
    name: '',
    username: '',
    email: '',
    role: '',
    permissions: [],
    is_active: true,
})

const selectAllEdit = () => {
    editUserForm.permissions = props.all_permissions.filter(p => p.name !== 'canResetPassword').map(p => p.name)
}
const deselectAllEdit = () => {
    editUserForm.permissions = []
}

const openEditUser = (user) => {
    editingUser.value = user
    editUserForm.name = user.name
    editUserForm.username = user.username
    editUserForm.email = user.email ?? ''
    editUserForm.role = user.roles?.[0]?.name ?? 'cashier'
    editUserForm.permissions = [...(user.permissions ?? [])]
    editUserForm.is_active = user.is_active
    showEditUserModal.value = true
}

const submitEditUser = () => {
    editUserForm.put(
        route('superadmin.branches.users.update', { branch: props.branch.slug, user: editingUser.value.id }),
        {
            onSuccess: () => {
                showEditUserModal.value = false
                editingUser.value = null
            },
        }
    )
}

const showResetUserModal = ref(false)
const resetUserId = ref(null)
const resetUserForm = useForm({ password: '', password_confirmation: '' })

const openResetUser = (userId) => {
    resetUserId.value = userId
    resetUserForm.reset()
    showResetUserModal.value = true
}

const submitResetUser = () => {
    resetUserForm.post(
        route('superadmin.branches.users.reset-password', { branch: props.branch.slug, user: resetUserId.value }),
        {
            onSuccess: () => {
                showResetUserModal.value = false
                resetUserId.value = null
            },
        }
    )
}

const toggleUser = (userId) => {
    router.post(route('superadmin.branches.users.toggle', { branch: props.branch.slug, user: userId }))
}

const destroyUser = (userId, name) => {
    if (!confirm(`Remove "${name}" from this branch? This cannot be undone.`)) return
    router.delete(route('superadmin.branches.users.destroy', { branch: props.branch.slug, user: userId }))
}

const togglePermInForm = (targetForm, permName) => {
    const idx = targetForm.permissions.indexOf(permName)
    if (idx === -1) targetForm.permissions.push(permName)
    else targetForm.permissions.splice(idx, 1)
}

// ── Tab 3: Settings & Configuration Form ──────────────────────────────────────
const settingsForm = useForm({
    is_tax_enabled: Boolean(props.settings?.is_tax_enabled),
    tax_percentage: Number(props.settings?.tax_percentage ?? 0),
    receipt_paper_size: props.settings?.receipt_paper_size || '80mm',
    receipt_printer_name: props.settings?.receipt_printer_name || 'Default POS Printer',
    printer_type: props.settings?.printer_type || 'thermal_80mm',
    printer_connection: props.settings?.printer_connection || 'kiosk_direct',
    printer_ip_address: props.settings?.printer_ip_address || '',
    receipt_copies: Number(props.settings?.receipt_copies ?? 1),
    auto_print_receipt: Boolean(props.settings?.auto_print_receipt),
    show_receipt_preview: Boolean(props.settings?.show_receipt_preview),
    is_offline_enabled: Boolean(props.settings?.is_offline_enabled),
    offline_receipt_prefix: props.settings?.offline_receipt_prefix || 'OFF',
    is_price_editable: Boolean(props.settings?.is_price_editable),
    is_qty_deduction: Boolean(props.settings?.is_qty_deduction),
    out_of_stock: Number(props.settings?.out_of_stock ?? 25),
    is_check_expiration: Boolean(props.settings?.is_check_expiration),
    is_imei_enabled: Boolean(props.settings?.is_imei_enabled),
    is_show_buy_price: Boolean(props.settings?.is_show_buy_price),
    is_use_profit_percentage: Boolean(props.settings?.is_use_profit_percentage),
    wholesale_profit_percent: Number(props.settings?.wholesale_profit_percent ?? 0),
    consumer_profit_percent: Number(props.settings?.consumer_profit_percent ?? 0),
    sell_interface: props.settings?.sell_interface || 'classic',
    business_sector: props.settings?.business_sector || 'commerce',
})

const submitSettings = () => {
    settingsForm.put(route('superadmin.branches.settings.update', { branch: props.branch.slug }), {
        preserveScroll: true,
    })
}

// ── Tab 4: Inventory & Master Import ──────────────────────────────────────────
const itemSearch = ref(props.filters?.item_search ?? '')
const doItemSearch = () => {
    router.get(
        route('superadmin.branches.show', { branch: props.branch.slug }),
        { tab: 'inventory', item_search: itemSearch.value },
        { preserveState: true, replace: true }
    )
}

// ── Tab 5: Financials & Sales Search ──────────────────────────────────────────
const salesSearch = ref(props.filters?.sales_search ?? '')
const doSalesSearch = () => {
    router.get(
        route('superadmin.branches.show', { branch: props.branch.slug }),
        { tab: 'reports', sales_search: salesSearch.value },
        { preserveState: true, replace: true }
    )
}

// ── Tab 6: Audit Logs Search ──────────────────────────────────────────────────
const logSearch = ref(props.filters?.log_search ?? '')
const doLogSearch = () => {
    router.get(
        route('superadmin.branches.show', { branch: props.branch.slug }),
        { tab: 'audit', log_search: logSearch.value },
        { preserveState: true, replace: true }
    )
}

const showImportModal = ref(false)
const masterSearch = ref('')
const selectedGlobalIds = ref([])

const filteredMasterItems = computed(() => {
    if (!masterSearch.value) return props.global_items
    const q = masterSearch.value.toLowerCase()
    return props.global_items.filter(item =>
        item.item_name.toLowerCase().includes(q) ||
        (item.barcode_number && item.barcode_number.toLowerCase().includes(q)) ||
        (item.category_hint && item.category_hint.toLowerCase().includes(q))
    )
})

const availableMasterItems = computed(() => filteredMasterItems.value.filter(i => !i.is_imported))

const toggleGlobalItem = (id) => {
    const idx = selectedGlobalIds.value.indexOf(id)
    if (idx === -1) selectedGlobalIds.value.push(id)
    else selectedGlobalIds.value.splice(idx, 1)
}
const selectAllAvailable = () => {
    selectedGlobalIds.value = availableMasterItems.value.map(i => i.id)
}
const deselectAllMaster = () => {
    selectedGlobalIds.value = []
}

const importBatchForm = useForm({ global_item_ids: [] })
const submitImportBatch = () => {
    if (!selectedGlobalIds.value.length) return
    importBatchForm.global_item_ids = selectedGlobalIds.value
    importBatchForm.post(route('superadmin.branches.items.import-batch', { branch: props.branch.slug }), {
        onSuccess: () => {
            showImportModal.value = false
            selectedGlobalIds.value = []
        }
    })
}

const importAllForm = useForm({})
const submitImportAll = () => {
    if (!confirm(`Import ALL ${props.global_items.length} Master Pool items into ${props.branch.name}?`)) return
    importAllForm.post(route('superadmin.branches.items.import-all', { branch: props.branch.slug }), {
        onSuccess: () => {
            showImportModal.value = false
            selectedGlobalIds.value = []
        }
    })
}

const removeItem = (item) => {
    if (confirm(`Remove "${item.item_name}" from ${props.branch.name} catalog?`)) {
        router.delete(route('superadmin.branches.items.destroy', { branch: props.branch.slug, item: item.id }))
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatNaira = (val) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val || 0)
const toggleBranchStatus = () => router.post(route('superadmin.branches.toggle', { branch: props.branch.slug }))
const toggleBranchOffline = () => router.post(route('superadmin.branches.toggle-offline', { branch: props.branch.slug }))
</script>

<template>
    <Head :title="`${branch.name} — Branch Management Hub`" />
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Top Navigation / Breadcrumb -->
        <div class="flex items-center justify-between">
            <Link
                :href="route('superadmin.branches.index')"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Branches Directory
            </Link>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-medium">Branch ID: #{{ branch.id }}</span>
            </div>
        </div>

        <!-- Flash Messages -->
        <div v-if="$page.props.flash?.success"
            class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 rounded-2xl px-4 py-3 text-sm font-medium flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ $page.props.flash.success }}</span>
            </div>
        </div>
        <div v-if="$page.props.flash?.error"
            class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-300 rounded-2xl px-4 py-3 text-sm font-medium flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ $page.props.flash.error }}</span>
            </div>
        </div>

        <!-- ── HERO / BRANCH IDENTITY HEADER ──────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <!-- Left: Branch Logo & Primary Identity -->
                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <div class="w-18 h-18 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white text-2xl font-black shadow-md overflow-hidden border-2 border-white dark:border-slate-800">
                            <img v-if="branch.logo_url" :src="branch.logo_url" :alt="branch.name" class="w-full h-full object-cover" />
                            <span v-else>{{ branch.name.substring(0, 2).toUpperCase() }}</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center flex-wrap gap-2">
                            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ branch.name }}</h1>
                            <span
                                :class="branch.is_active ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-500/30'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase tracking-wider"
                            >
                                {{ branch.is_active ? 'Active' : 'Disabled' }}
                            </span>
                            <span
                                :class="settings.is_offline_enabled ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold border flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="settings.is_offline_enabled ? 'bg-indigo-500' : 'bg-slate-400'"></span>
                                {{ settings.is_offline_enabled ? 'Offline Sales Active' : 'Offline Disabled' }}
                            </span>
                        </div>

                        <!-- Full Shareable Link & Contact Row -->
                        <div class="flex items-center flex-wrap gap-2 pt-1">
                            <div class="inline-flex items-center bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-xl p-1 max-w-full">
                                <a :href="`/${branch.slug}`" target="_blank" class="px-2 py-0.5 text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 hover:underline truncate max-w-[200px] sm:max-w-xs md:max-w-md" title="Open Branch Link">
                                    {{ fullBranchUrl }}
                                </a>
                                <button
                                    @click="copyPosUrl"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg transition cursor-pointer flex-shrink-0"
                                    :class="copiedUrl
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-slate-600 shadow-2xs border border-slate-200/80 dark:border-slate-600'"
                                    title="Click to copy full branch link to share"
                                >
                                    <template v-if="copiedUrl">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Copied!</span>
                                    </template>
                                    <template v-else>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <span>Copy Full Link</span>
                                    </template>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400 pt-0.5">
                            <span v-if="branch.phone">📞 {{ branch.phone }}</span>
                            <span v-if="branch.email">✉️ {{ branch.email }}</span>
                            <span v-if="branch.address">📍 {{ branch.address }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Quick Direct Actions -->
                <div class="flex items-center flex-wrap gap-2 w-full lg:w-auto">
                    <Link
                        :href="route('pos.index', { branch: branch.slug })"
                        target="_blank"
                        class="flex-1 lg:flex-none px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 flex items-center justify-center gap-1.5"
                    >
                        <span>Open POS Terminal</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </Link>
                    <button
                        @click="toggleBranchStatus"
                        :class="branch.is_active ? 'bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-900 hover:bg-rose-100' : 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                    >
                        {{ branch.is_active ? 'Disable Branch' : 'Enable Branch' }}
                    </button>
                    <button
                        @click="toggleBranchOffline"
                        :class="settings.is_offline_enabled ? 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900 hover:bg-amber-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                    >
                        {{ settings.is_offline_enabled ? 'Disable Offline' : 'Enable Offline' }}
                    </button>
                </div>
            </div>

            <!-- ── Top KPI Stat Cards ────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mt-6 pt-6 border-t border-slate-100 dark:border-slate-800/80">
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Today's Sales</div>
                    <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white mt-1">{{ formatNaira(stats.today_revenue) }}</div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">This Month</div>
                    <div class="text-sm sm:text-base font-black text-indigo-600 dark:text-indigo-400 mt-1">{{ formatNaira(stats.month_revenue) }}</div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">All-Time Revenue</div>
                    <div class="text-sm sm:text-base font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ formatNaira(stats.total_revenue) }}</div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Transactions</div>
                    <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white mt-1">{{ stats.sales_count }}</div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Catalog Items</div>
                    <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white mt-1">{{ stats.items_count }}</div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Staff Users</div>
                    <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white mt-1">{{ stats.users_count }}</div>
                </div>
            </div>
        </div>

        <!-- ── TAB NAVIGATION BAR ────────────────────────────────────────────── -->
        <div class="border-b border-slate-200 dark:border-slate-800 overflow-x-auto">
            <nav class="flex space-x-1 sm:space-x-4 min-w-max">
                <button
                    @click="setActiveTab('overview')"
                    :class="activeTab === 'overview'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>📊 Overview & Profile</span>
                </button>
                <button
                    @click="setActiveTab('staff')"
                    :class="activeTab === 'staff'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>👥 Staff & Permissions ({{ users.length }})</span>
                </button>
                <button
                    @click="setActiveTab('settings')"
                    :class="activeTab === 'settings'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>⚙️ Settings (Tax & Printer)</span>
                </button>
                <button
                    @click="setActiveTab('inventory')"
                    :class="activeTab === 'inventory'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>📦 Inventory & Import</span>
                </button>
                <button
                    @click="setActiveTab('reports')"
                    :class="activeTab === 'reports'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>📈 Financials & Reports</span>
                </button>
                <button
                    @click="setActiveTab('audit')"
                    :class="activeTab === 'audit'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>📜 Audit Log</span>
                </button>
            </nav>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 1: OVERVIEW & PROFILE                                             -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile Edit Card (Left 2 cols) -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Branch Identity & Profile</h2>
                        <p class="text-xs text-slate-500">Edit core branch details and public URLs.</p>
                    </div>

                    <form @submit.prevent="handleProfileSubmit" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Branch Name *</label>
                                <input
                                    v-model="profileForm.name"
                                    type="text"
                                    required
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                />
                                <div v-if="profileForm.errors.name" class="text-rose-500 text-xs mt-1">{{ profileForm.errors.name }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Branch Slug (Login Identifier) *
                                </label>
                                <input
                                    v-model="profileForm.slug"
                                    type="text"
                                    required
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-indigo-600 dark:text-indigo-400 focus:outline-none focus:border-indigo-500"
                                />
                                <span class="text-[11px] text-slate-400">URL: skynetpos.com/{{ profileForm.slug }}</span>
                                <div v-if="profileForm.errors.slug" class="text-rose-500 text-xs mt-1">{{ profileForm.errors.slug }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
                                <input
                                    v-model="profileForm.phone"
                                    type="text"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="e.g. +234 803 000 0000"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                                <input
                                    v-model="profileForm.email"
                                    type="email"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="branch@company.com"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Physical Address</label>
                            <textarea
                                v-model="profileForm.address"
                                rows="2"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                placeholder="Store street address, city, state"
                            ></textarea>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input
                                v-model="profileForm.is_active"
                                type="checkbox"
                                id="branch_active_check"
                                class="w-4 h-4 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950"
                            />
                            <label for="branch_active_check" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                                Branch is active and accessible for daily terminal operations
                            </label>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <button
                                type="submit"
                                :disabled="profileForm.processing"
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 cursor-pointer disabled:opacity-50"
                            >
                                {{ profileForm.processing ? 'Saving Changes…' : 'Save Profile Details' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Logo & Meta Information (Right col) -->
                <div class="space-y-6">
                    <!-- Logo Upload Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Branch Logo</h2>
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-center overflow-hidden">
                                <img v-if="logoPreview" :src="logoPreview" alt="Logo Preview" class="w-full h-full object-cover" />
                                <span v-else class="text-xs text-slate-400 font-medium">No Logo</span>
                            </div>
                            <div class="space-y-2">
                                <input
                                    type="file"
                                    ref="logoInput"
                                    @change="handleLogoChange"
                                    accept="image/*"
                                    class="hidden"
                                />
                                <button
                                    type="button"
                                    @click="$refs.logoInput.click()"
                                    class="px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold rounded-xl text-xs border border-slate-200 dark:border-slate-700 transition cursor-pointer"
                                >
                                    Select Image
                                </button>
                                <button
                                    v-if="logoForm.logo"
                                    type="button"
                                    @click="submitLogo"
                                    :disabled="logoForm.processing"
                                    class="block px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-xs cursor-pointer"
                                >
                                    Upload Logo
                                </button>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400">PNG, JPG, or WebP up to 2MB. Appears on receipts and terminal logins.</p>
                    </div>

                    <!-- Meta Information Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-3 text-xs">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">Branch Meta</h2>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/60">
                            <span class="text-slate-400">Created On:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ branch.created_at }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/60">
                            <span class="text-slate-400">Last Modified:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ branch.updated_at }}</span>
                        </div>
                        <div class="space-y-1.5 pt-2 border-t border-slate-100 dark:border-slate-800/60">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Shareable POS Link</div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    :value="fullBranchUrl"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs font-mono text-indigo-600 dark:text-indigo-400 select-all"
                                />
                                <button
                                    @click="copyPosUrl"
                                    type="button"
                                    class="px-3 py-1.5 text-xs font-bold rounded-xl border transition cursor-pointer flex-shrink-0 flex items-center gap-1"
                                    :class="copiedUrl
                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                        : 'bg-indigo-50 dark:bg-indigo-600/20 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/30 hover:bg-indigo-100'"
                                    title="Copy full shareable link"
                                >
                                    <svg v-if="!copiedUrl" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>{{ copiedUrl ? 'Copied!' : 'Copy' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 2: STAFF & ACCESS MANAGEMENT                                      -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'staff'" class="space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Staff Accounts & Granular Permissions</h2>
                    <p class="text-xs text-slate-500">Manage cashier and admin accounts assigned to {{ branch.name }}.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('superadmin.branches.roles.index', { branch: branch.slug })"
                        class="px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold rounded-xl text-xs transition shadow-xs flex items-center space-x-2"
                    >
                        <span>Role Templates</span>
                    </Link>
                    <button
                        @click="showCreateUserModal = true; selectAllCreate()"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 flex items-center space-x-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Staff User</span>
                    </button>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-6 py-3.5">Name</th>
                                <th class="px-6 py-3.5">Username</th>
                                <th class="px-6 py-3.5">Role</th>
                                <th class="px-6 py-3.5">Permissions Granted</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100">
                                    <div>{{ user.name }}</div>
                                    <div class="text-xs text-slate-400 dark:text-slate-500 font-normal">{{ user.email || 'No email' }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-300 text-xs">{{ user.username }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="user.roles?.length"
                                        :class="user.roles.some(r => r.name === 'branch-admin') ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/30' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'"
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wider">
                                        {{ user.roles.map(r => r.name).join(', ') }}
                                    </span>
                                    <span v-else class="text-rose-500 text-xs italic font-medium">No Role</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="user.roles?.some(r => r.name === 'branch-admin')"
                                        class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-1 rounded border border-indigo-200 dark:border-indigo-500/20">
                                        Full Access (All Permissions)
                                    </span>
                                    <span v-else-if="user.permissions?.length"
                                        class="text-xs text-emerald-700 dark:text-emerald-300 font-medium bg-emerald-50 dark:bg-emerald-500/10 px-2.5 py-1 rounded border border-emerald-200 dark:border-emerald-500/20">
                                        {{ user.permissions.length }} of {{ all_permissions.length }} permissions
                                    </span>
                                    <span v-else class="text-slate-400 dark:text-slate-500 text-xs italic">No custom permissions</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="user.is_active
                                            ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20'
                                            : 'text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/20'"
                                        class="px-2.5 py-0.5 rounded text-xs font-semibold border"
                                    >
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEditUser(user)"
                                            class="px-2.5 py-1.5 text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 transition cursor-pointer">
                                            Edit / Permissions
                                        </button>
                                        <button @click="toggleUser(user.id)"
                                            :class="user.is_active
                                                ? 'text-rose-600 dark:text-rose-400 hover:text-rose-700'
                                                : 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-700'"
                                            class="text-xs font-semibold px-2 py-1 transition cursor-pointer">
                                            {{ user.is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                        <button @click="openResetUser(user.id)"
                                            class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:text-amber-700 px-2 py-1 transition cursor-pointer">
                                            Reset PW
                                        </button>
                                        <button @click="destroyUser(user.id, user.name)"
                                            class="text-xs font-semibold text-slate-400 hover:text-rose-600 px-2 py-1 transition cursor-pointer">
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 3: SETTINGS & CONFIGURATION (TAX, PRINTER, OFFLINE, TERMINAL)     -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'settings'" class="space-y-6">
            <form @submit.prevent="submitSettings" class="space-y-6">
                <!-- Row 1: Tax & Offline Settings -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Tax Settings Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">Tax & VAT Configuration</h3>
                                <p class="text-xs text-slate-500">Apply sales tax to customer receipts.</p>
                            </div>
                            <span class="text-xl">💰</span>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Enable Sales Tax / VAT</div>
                                    <div class="text-[11px] text-slate-500">Calculate tax on taxable line items during checkout</div>
                                </div>
                                <input
                                    v-model="settingsForm.is_tax_enabled"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tax Rate Percentage (%)</label>
                                <div class="relative">
                                    <input
                                        v-model.number="settingsForm.tax_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        :disabled="!settingsForm.is_tax_enabled"
                                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-900 dark:text-white disabled:opacity-40"
                                    />
                                    <span class="absolute right-3 top-2.5 text-xs text-slate-400 font-bold">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Offline Sales Mode Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">Offline Terminal Settings</h3>
                                <p class="text-xs text-slate-500">Enable cashiers to sell offline and sync later.</p>
                            </div>
                            <span class="text-xl">📡</span>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Enable Offline Checkout</div>
                                    <div class="text-[11px] text-slate-500">Allows offline IndexedDB checkout when network drops</div>
                                </div>
                                <input
                                    v-model="settingsForm.is_offline_enabled"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Offline Receipt Prefix</label>
                                <input
                                    v-model="settingsForm.offline_receipt_prefix"
                                    type="text"
                                    maxlength="10"
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-900 dark:text-white font-mono"
                                    placeholder="OFF"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Receipt & Printer Configuration -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">Receipt & Thermal Printer Configuration</h3>
                            <p class="text-xs text-slate-500">Custom printer paper width, auto-print behaviors, and copies.</p>
                        </div>
                        <span class="text-xl">🖨️</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Paper Size</label>
                            <select v-model="settingsForm.receipt_paper_size"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white">
                                <option value="80mm">80mm (Standard Thermal)</option>
                                <option value="58mm">58mm (Small Thermal)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Printer Connection</label>
                            <select v-model="settingsForm.printer_connection"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white">
                                <option value="kiosk_direct">Direct Browser / Kiosk Print</option>
                                <option value="network_escpos">Network ESC/POS (TCP/IP)</option>
                                <option value="bluetooth">Bluetooth Thermal</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Default Copies</label>
                            <input
                                v-model.number="settingsForm.receipt_copies"
                                type="number"
                                min="1"
                                max="5"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Printer Device Name</label>
                            <input
                                v-model="settingsForm.receipt_printer_name"
                                type="text"
                                placeholder="e.g. EPSON TM-T20"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <label class="flex items-center gap-2.5 p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.auto_print_receipt" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Auto-Print on Checkout</div>
                                <div class="text-[11px] text-slate-500">Automatically trigger print dialog when sale completes</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.show_receipt_preview" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Show Receipt Preview Modal</div>
                                <div class="text-[11px] text-slate-500">Display onscreen interactive receipt preview</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Row 3: Terminal Behavior & Stock Settings -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">Terminal Behavior & Stock Flags</h3>
                            <p class="text-xs text-slate-500">Selling interface, low stock thresholds, and operational rules.</p>
                        </div>
                        <span class="text-xl">🛠️</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Sell Interface Layout</label>
                            <select v-model="settingsForm.sell_interface"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white">
                                <option value="classic">Classic POS Terminal</option>
                                <option value="gallery">Visual Gallery Interface</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Low Stock Warning Threshold</label>
                            <input
                                v-model.number="settingsForm.out_of_stock"
                                type="number"
                                min="0"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Business Sector</label>
                            <select v-model="settingsForm.business_sector"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-white">
                                <option value="commerce">General Commerce / Retail</option>
                                <option value="health">Health / Pharmacy</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_price_editable" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Allow Price Edits on Cart</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_qty_deduction" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Auto Deduct Stock Qty on Sale</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_show_buy_price" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Show Buy/Cost Price to Authorized Staff</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_imei_enabled" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Enable Serial / IMEI Tracking</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_check_expiration" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Track Expiration Dates</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_use_profit_percentage" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Use Profit Margin Percentages</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="settingsForm.processing"
                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 cursor-pointer disabled:opacity-50"
                    >
                        {{ settingsForm.processing ? 'Saving Settings…' : 'Save All Settings & Configuration' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 4: INVENTORY & GLOBAL MASTER IMPORT                               -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'inventory'" class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Branch Catalog & Inventory</h2>
                    <p class="text-xs text-slate-500">View items active in this branch or import from the Global Master Pool.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        @click="showImportModal = true"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 flex items-center space-x-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Import from Global Master Pool</span>
                    </button>
                    <button
                        @click="submitImportAll"
                        :disabled="importAllForm.processing"
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 disabled:opacity-50 flex items-center space-x-2 cursor-pointer"
                    >
                        <span>Import All ({{ global_items.length }}) Items</span>
                    </button>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="itemSearch"
                        @keydown.enter="doItemSearch"
                        type="text"
                        placeholder="Search items in this branch catalog…"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button @click="doItemSearch" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 transition cursor-pointer">
                    Search
                </button>
            </div>

            <!-- Inventory Table -->
            <div v-if="!branch_items.data.length" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-12 text-center text-slate-500 space-y-3">
                <svg class="w-12 h-12 mx-auto text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                <div class="font-bold text-slate-800 dark:text-slate-200">No items found in this branch catalog</div>
                <p class="text-xs text-slate-400">Click "Import from Global Master Pool" above to stock this branch.</p>
            </div>
            <div v-else class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-6 py-3.5">Item Name</th>
                                <th class="px-6 py-3.5">Category</th>
                                <th class="px-6 py-3.5">Barcode</th>
                                <th class="px-6 py-3.5">Cost Price</th>
                                <th class="px-6 py-3.5">Selling Price</th>
                                <th class="px-6 py-3.5">Front Store</th>
                                <th class="px-6 py-3.5">Back Store</th>
                                <th class="px-6 py-3.5">Total Qty</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="item in branch_items.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ item.item_name }}</td>
                                <td class="px-6 py-4 text-xs">
                                    <span v-if="item.category" class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 rounded border border-indigo-200 dark:border-indigo-500/20 font-medium">
                                        {{ item.category.name }}
                                    </span>
                                    <span v-else class="text-slate-400 dark:text-slate-500 italic">General</span>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ item.barcode_number || 'N/A' }}</td>
                                <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-300">{{ formatNaira(item.buy_price) }}</td>
                                <td class="px-6 py-4 font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{{ formatNaira(item.price) }}</td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-700 dark:text-slate-200">{{ item.front_store_qty }}</td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-700 dark:text-slate-200">{{ item.back_store_qty }}</td>
                                <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-slate-100 text-xs">{{ item.total_qty }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="removeItem(item)" class="text-xs font-semibold text-slate-400 hover:text-rose-600 transition px-2 py-1 cursor-pointer">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="branch_items.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 gap-3 bg-slate-50/50 dark:bg-slate-950/40">
                    <div>
                        Showing <span class="font-semibold text-slate-900 dark:text-slate-200">{{ branch_items.from }}</span> to <span class="font-semibold text-slate-900 dark:text-slate-200">{{ branch_items.to }}</span> of <span class="font-semibold text-slate-900 dark:text-slate-200">{{ branch_items.total }}</span> items
                    </div>
                    <div class="flex items-center space-x-1">
                        <template v-for="(link, key) in branch_items.links" :key="key">
                            <div v-if="link.url === null"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 cursor-not-allowed select-none bg-slate-100 dark:bg-slate-900"
                                v-html="link.label"
                            />
                            <Link v-else
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition"
                                :class="link.active
                                    ? 'bg-indigo-600 border-indigo-600 text-white font-bold'
                                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 5: FINANCIALS & RECENT TRANSACTIONS SNAPSHOT                      -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'reports'" class="space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Sales & Financial Activity</h2>
                    <p class="text-xs text-slate-500">Live sales receipts logged from {{ branch.name }} terminals.</p>
                </div>
            </div>

            <!-- Sales Search Bar -->
            <div class="flex items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="salesSearch"
                        @keydown.enter="doSalesSearch"
                        type="text"
                        placeholder="Search by receipt #, customer, cashier, payment method…"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button @click="doSalesSearch" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 transition cursor-pointer">
                    Search
                </button>
            </div>

            <!-- Recent Sales Ledger -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
                <div v-if="!recent_sales.data?.length" class="p-12 text-center text-slate-400 text-xs">
                    No sales records found for this branch matching your query.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-6 py-3.5">Receipt #</th>
                                <th class="px-6 py-3.5">Cashier</th>
                                <th class="px-6 py-3.5">Customer</th>
                                <th class="px-6 py-3.5">Method</th>
                                <th class="px-6 py-3.5">Items</th>
                                <th class="px-6 py-3.5">Total Amount</th>
                                <th class="px-6 py-3.5 text-right">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="sale in recent_sales.data" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400 text-xs">{{ sale.receipt_number }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-900 dark:text-slate-200">{{ sale.cashier_name }}</td>
                                <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-400">{{ sale.customer_name }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ sale.payment_method }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono">{{ sale.items_count }} items</td>
                                <td class="px-6 py-4 font-mono font-black text-emerald-600 dark:text-emerald-400">{{ formatNaira(sale.total_amount) }}</td>
                                <td class="px-6 py-4 text-right text-xs text-slate-500">{{ sale.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Sales Pagination Footer -->
                <div v-if="recent_sales.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 gap-3 bg-slate-50/50 dark:bg-slate-950/40">
                    <div>
                        Showing <span class="font-semibold text-slate-900 dark:text-slate-200">{{ recent_sales.from }}</span> to <span class="font-semibold text-slate-900 dark:text-slate-200">{{ recent_sales.to }}</span> of <span class="font-semibold text-slate-900 dark:text-slate-200">{{ recent_sales.total }}</span> sales
                    </div>
                    <div class="flex items-center space-x-1">
                        <template v-for="(link, key) in recent_sales.links" :key="key">
                            <div v-if="link.url === null"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 cursor-not-allowed select-none bg-slate-100 dark:bg-slate-900"
                                v-html="link.label"
                            />
                            <Link v-else
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition"
                                :class="link.active
                                    ? 'bg-indigo-600 border-indigo-600 text-white font-bold'
                                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 6: AUDIT & ACTIVITY LOG                                           -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'audit'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Branch Activity & Change Audit Trail</h2>
                    <p class="text-xs text-slate-500">Complete timestamped history of actions and configuration overrides in {{ branch.name }}.</p>
                </div>
            </div>

            <!-- Audit Logs Search Bar -->
            <div class="flex items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="logSearch"
                        @keydown.enter="doLogSearch"
                        type="text"
                        placeholder="Search audit trail by action, user, or IP…"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button @click="doLogSearch" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 transition cursor-pointer">
                    Search
                </button>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
                <div v-if="!activity_logs.data?.length" class="p-12 text-center text-slate-400 text-xs">
                    No activity logs recorded for this branch matching your query.
                </div>
                <div v-else class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <div v-for="log in activity_logs.data" :key="log.id" class="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ log.action_type }}
                                </span>
                                <span class="text-xs font-bold text-slate-900 dark:text-slate-100">{{ log.log }}</span>
                            </div>
                            <div class="text-[11px] text-slate-400 flex items-center gap-3">
                                <span>By: <strong class="text-slate-700 dark:text-slate-300">{{ log.user_name }}</strong></span>
                                <span v-if="log.ip_address">IP: {{ log.ip_address }}</span>
                            </div>
                        </div>
                        <span class="text-xs font-mono text-slate-400 whitespace-nowrap">{{ log.created_at }}</span>
                    </div>
                </div>

                <!-- Audit Logs Pagination Footer -->
                <div v-if="activity_logs.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 gap-3 bg-slate-50/50 dark:bg-slate-950/40">
                    <div>
                        Showing <span class="font-semibold text-slate-900 dark:text-slate-200">{{ activity_logs.from }}</span> to <span class="font-semibold text-slate-900 dark:text-slate-200">{{ activity_logs.to }}</span> of <span class="font-semibold text-slate-900 dark:text-slate-200">{{ activity_logs.total }}</span> entries
                    </div>
                    <div class="flex items-center space-x-1">
                        <template v-for="(link, key) in activity_logs.links" :key="key">
                            <div v-if="link.url === null"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 cursor-not-allowed select-none bg-slate-100 dark:bg-slate-900"
                                v-html="link.label"
                            />
                            <Link v-else
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition"
                                :class="link.active
                                    ? 'bg-indigo-600 border-indigo-600 text-white font-bold'
                                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL: SLUG CHANGE WARNING ────────────────────────────────────── -->
        <div v-if="showSlugWarningModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700/60 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mx-auto">
                    ⚠️
                </div>
                <div class="text-center space-y-2">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Warning: Changing Branch Login URL</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        You are changing this branch's URL from <strong class="font-mono text-indigo-600 dark:text-indigo-400">/{{ branch.slug }}</strong> to <strong class="font-mono text-indigo-600 dark:text-indigo-400">/{{ profileForm.slug }}</strong>.
                    </p>
                    <p class="text-xs text-amber-700 dark:text-amber-400 font-medium bg-amber-50 dark:bg-amber-500/10 p-2.5 rounded-xl border border-amber-200 dark:border-amber-500/20">
                        Staff with saved bookmarks will need to use the new URL. Are you sure you want to proceed?
                    </p>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button @click="showSlugWarningModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                        Cancel
                    </button>
                    <button @click="submitProfile" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-amber-600/20 cursor-pointer">
                        Yes, Update Slug & URL
                    </button>
                </div>
            </div>
        </div>

        <!-- ── MODAL: ADD STAFF USER ─────────────────────────────────────────── -->
        <div v-if="showCreateUserModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Add Staff User to {{ branch.name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Assign role and select specific POS permissions</p>
                    </div>
                    <button @click="showCreateUserModal = false; userForm.reset()" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitCreateUser" class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input v-model="userForm.name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                            <input v-model="userForm.username" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                            <input v-model="userForm.email" type="email"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password *</label>
                            <div class="relative flex items-center">
                                <input v-model="userForm.password" :type="showCreatePass ? 'text' : 'password'" required
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 pr-9 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                                <button type="button" @click="showCreatePass = !showCreatePass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 transition focus:outline-none">
                                    <span v-if="!showCreatePass">👁️</span>
                                    <span v-else>🙈</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Role *</label>
                        <select v-model="userForm.role" required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                    </div>

                    <!-- Permissions Grid -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">Granular Feature Permissions</h3>
                                <p class="text-xs text-slate-500">Assign menu and operational rights</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" @click="selectAllCreate" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                    Select All
                                </button>
                                <button type="button" @click="deselectAllCreate" class="text-xs text-slate-600 dark:text-slate-400 hover:underline px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-200 dark:border-slate-800">
                            <label
                                v-for="perm in all_permissions"
                                :key="perm.name"
                                class="flex items-center space-x-2.5 p-2 rounded-lg cursor-pointer transition-colors text-xs"
                                :class="userForm.permissions.includes(perm.name) ? 'bg-indigo-50 dark:bg-indigo-600/10 text-indigo-900 dark:text-slate-100 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900'"
                            >
                                <input
                                    type="checkbox"
                                    :value="perm.name"
                                    :checked="userForm.permissions.includes(perm.name)"
                                    @change="togglePermInForm(userForm, perm.name)"
                                    class="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-indigo-600 focus:ring-0"
                                />
                                <span>{{ perm.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showCreateUserModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400">Cancel</button>
                        <button type="submit" :disabled="userForm.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 cursor-pointer">
                            {{ userForm.processing ? 'Creating User…' : 'Create User & Assign Permissions' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL: EDIT STAFF USER ────────────────────────────────────────── -->
        <div v-if="showEditUserModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Edit Staff User — @{{ editingUser?.username }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Update account details and feature permissions</p>
                    </div>
                    <button @click="showEditUserModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitEditUser" class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input v-model="editUserForm.name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                            <input v-model="editUserForm.username" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                            <input v-model="editUserForm.email" type="email"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Role *</label>
                            <select v-model="editUserForm.role" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Permissions Grid -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">Assigned Feature Permissions</h3>
                                <p class="text-xs text-slate-500">Customize feature access</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" @click="selectAllEdit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                    Select All
                                </button>
                                <button type="button" @click="deselectAllEdit" class="text-xs text-slate-600 dark:text-slate-400 hover:underline px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-200 dark:border-slate-800">
                            <label
                                v-for="perm in all_permissions"
                                :key="perm.name"
                                class="flex items-center space-x-2.5 p-2 rounded-lg cursor-pointer transition-colors text-xs"
                                :class="editUserForm.permissions.includes(perm.name) ? 'bg-indigo-50 dark:bg-indigo-600/10 text-indigo-900 dark:text-slate-100 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900'"
                            >
                                <input
                                    type="checkbox"
                                    :value="perm.name"
                                    :checked="editUserForm.permissions.includes(perm.name)"
                                    @change="togglePermInForm(editUserForm, perm.name)"
                                    class="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-indigo-600 focus:ring-0"
                                />
                                <span>{{ perm.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showEditUserModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400">Cancel</button>
                        <button type="submit" :disabled="editUserForm.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 cursor-pointer">
                            {{ editUserForm.processing ? 'Saving…' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL: RESET PASSWORD ─────────────────────────────────────────── -->
        <div v-if="showResetUserModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Reset Password</h2>
                    <button @click="showResetUserModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold">&times;</button>
                </div>
                <form @submit.prevent="submitResetUser" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
                        <input v-model="resetUserForm.password" :type="showResetPass ? 'text' : 'password'" required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm Password *</label>
                        <input v-model="resetUserForm.password_confirmation" :type="showResetConfirmPass ? 'text' : 'password'" required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                    </div>
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showResetUserModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400">Cancel</button>
                        <button type="submit" :disabled="resetUserForm.processing" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">
                            {{ resetUserForm.processing ? 'Resetting…' : 'Reset Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL: GLOBAL MASTER IMPORT ───────────────────────────────────── -->
        <div v-if="showImportModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-3xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-base text-slate-900 dark:text-slate-100">Import Master Pool Items to {{ branch.name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Select items from the Global Master Pool to push into this branch</p>
                    </div>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold">&times;</button>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0">
                    <input
                        v-model="masterSearch"
                        type="text"
                        placeholder="Filter master items by name, barcode, or category…"
                        class="w-full sm:w-72 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        <button @click="selectAllAvailable" type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer">
                            Select Available ({{ availableMasterItems.length }})
                        </button>
                        <button @click="deselectAllMaster" type="button" class="text-xs text-slate-600 dark:text-slate-400 hover:underline px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer">
                            Deselect All
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto max-h-80 border border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50/50 dark:bg-slate-950/60 p-2 divide-y divide-slate-100 dark:divide-slate-800/40">
                    <div v-for="gItem in filteredMasterItems" :key="gItem.id"
                        class="flex items-center justify-between p-2.5 rounded-xl text-xs transition-colors"
                        :class="gItem.is_imported ? 'opacity-60 bg-slate-100/50 dark:bg-slate-900/40' : selectedGlobalIds.includes(gItem.id) ? 'bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-200 dark:border-indigo-500/30' : 'hover:bg-white dark:hover:bg-slate-900'"
                    >
                        <div class="flex items-center space-x-3">
                            <input
                                v-if="!gItem.is_imported"
                                type="checkbox"
                                :value="gItem.id"
                                :checked="selectedGlobalIds.includes(gItem.id)"
                                @change="toggleGlobalItem(gItem.id)"
                                class="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-indigo-600 focus:ring-0"
                            />
                            <div v-else class="text-emerald-600 dark:text-emerald-400 font-bold text-xs">✓</div>

                            <div>
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ gItem.item_name }}</div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
                                    Barcode: {{ gItem.barcode_number || 'N/A' }} · Category: {{ gItem.category_hint || 'General' }}
                                </div>
                            </div>
                        </div>

                        <div class="text-right flex items-center space-x-4">
                            <div>
                                <div class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ formatNaira(gItem.price) }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">Cost: {{ formatNaira(gItem.buy_price) }}</div>
                            </div>
                            <span v-if="gItem.is_imported" class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 text-[10px] font-semibold rounded">
                                Already in Branch
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
                    <div class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ selectedGlobalIds.length }}</span> item(s) selected
                    </div>
                    <div class="flex items-center space-x-3">
                        <button type="button" @click="showImportModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 transition">
                            Cancel
                        </button>
                        <button
                            @click="submitImportBatch"
                            :disabled="!selectedGlobalIds.length || importBatchForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 cursor-pointer"
                        >
                            {{ importBatchForm.processing ? 'Importing…' : `Import Selected (${selectedGlobalIds.length})` }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
