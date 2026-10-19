<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import EcommerceTab from './Partials/EcommerceTab.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branch: Object,
    all_organizations: {
        type: Array,
        default: () => [],
    },
    settings: Object,
    stats: Object,
    users: Array,
    roles: Array,
    all_permissions: Array,
    branch_items: Object,
    global_items: Array,
    import_library_files: Array,
    import_item_types: Array,
    categories: Array,
    recent_sales: Object,
    activity_logs: Object,
    commerce_integration: Object,
    commerce_api_base_url: String,
    ecommerce_capability_enabled: Boolean,
    new_commerce_token: String,
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

// ── Organization & Move Action ────────────────────────────────────────────────
const showMoveOrgModal = ref(false)
const moveOrgForm = useForm({
    organization_id: props.branch.organization?.id || '',
})

const submitMoveOrg = () => {
    moveOrgForm.post(route('superadmin.branches.move-organization', { branch: props.branch.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            showMoveOrgModal.value = false
        }
    })
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

const formatRoleName = (name) => {
    if (!name) return ''
    const map = {
        'branch-admin': 'Branch Administrator',
        'cashier': 'Cashier / Sales Staff',
        'manager': 'Store Manager',
        'inventory-manager': 'Inventory / Stock Manager',
    }
    if (map[name]) return map[name]
    return name.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const userForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role: props.roles?.[0]?.name || 'branch-admin',
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
            'canProcessReturn',
            'canManageCustomers',
        ]
    } else if (newRole === 'manager') {
        userForm.permissions = [
            'canAccessPos',
            'canViewSales',
            'canProcessReturn',
            'canApplyDiscount',
            'canEditPrice',
            'canViewBuyPrice',
            'canViewEndOfDay',
            'canViewReports',
            'canViewProfitLoss',
            'canManageDebt',
            'canGiveDebt',
            'canAdjustStock',
            'canTransferStock',
            'canTransferInterBranchStock',
            'canViewReorderPoints',
            'canManagePurchases',
            'canManageItems',
            'canManageBarcodes',
            'canManageCustomers',
            'canManageUsers',
            'canLogExpense',
            'canManageExpenses',
            'canViewExpenseReports',
            'canViewExpiryReport',
            'canViewBatchExpiryReport',
        ]
    } else if (newRole === 'inventory-manager') {
        userForm.permissions = [
            'canAdjustStock',
            'canTransferStock',
            'canTransferInterBranchStock',
            'canViewReorderPoints',
            'canManagePurchases',
            'canManageItems',
            'canManageBarcodes',
            'canViewExpiryReport',
            'canViewBatchExpiryReport',
        ]
    }
}, { immediate: true })

const closeCreateUserModal = () => {
    showCreateUserModal.value = false
    showCreatePass.value = false
    userForm.reset()
    userForm.clearErrors()
}

const submitCreateUser = () => {
    userForm.post(route('superadmin.branches.users.store', { branch: props.branch.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateUserModal()
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

const closeEditUserModal = () => {
    showEditUserModal.value = false
    editingUser.value = null
    editUserForm.reset()
    editUserForm.clearErrors()
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
            preserveScroll: true,
            onSuccess: () => {
                closeEditUserModal()
            },
        }
    )
}

const showResetUserModal = ref(false)
const resetUserId = ref(null)
const resetUserForm = useForm({ password: '', password_confirmation: '' })

const closeResetUserModal = () => {
    showResetUserModal.value = false
    resetUserId.value = null
    showResetPass.value = false
    showResetConfirmPass.value = false
    resetUserForm.reset()
    resetUserForm.clearErrors()
}

const openResetUser = (userId) => {
    resetUserId.value = userId
    resetUserForm.reset()
    showResetUserModal.value = true
}

const submitResetUser = () => {
    resetUserForm.post(
        route('superadmin.branches.users.reset-password', { branch: props.branch.slug, user: resetUserId.value }),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeResetUserModal()
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
    is_accounting_vat_enabled: Boolean(props.settings?.is_accounting_vat_enabled),
    receipt_paper_size: props.settings?.receipt_paper_size || '80mm',
    receipt_printer_name: props.settings?.receipt_printer_name || 'Default POS Printer',
    printer_type: props.settings?.printer_type || 'thermal_80mm',
    printer_connection: props.settings?.printer_connection || 'kiosk_direct',
    printer_ip_address: props.settings?.printer_ip_address || '',
    receipt_copies: Number(props.settings?.receipt_copies ?? 1),
    auto_print_receipt: Boolean(props.settings?.auto_print_receipt),
    show_receipt_preview: Boolean(props.settings?.show_receipt_preview),
    is_offline_enabled: Boolean(props.settings?.is_offline_enabled),
    is_turnstile_enabled: Boolean(props.settings?.is_turnstile_enabled ?? true),
    is_auto_fullscreen_enabled: Boolean(props.settings?.is_auto_fullscreen_enabled),
    held_sales_visibility: props.settings?.held_sales_visibility || 'device_only',
    offline_receipt_prefix: props.settings?.offline_receipt_prefix || 'OFF',
    is_price_editable: Boolean(props.settings?.is_price_editable),
    is_qty_deduction: Boolean(props.settings?.is_qty_deduction),
    is_offline_qty_deduction: Boolean(props.settings?.is_offline_qty_deduction),
    out_of_stock: Number(props.settings?.out_of_stock ?? 25),
    is_check_expiration: Boolean(props.settings?.is_check_expiration),
    batch_tracking_enabled: Boolean(props.settings?.batch_tracking_enabled),
    expiry_alert_days: Number(props.settings?.expiry_alert_days ?? 30),
    is_imei_enabled: Boolean(props.settings?.is_imei_enabled),
    is_camera_scanner_enabled: Boolean(props.settings?.is_camera_scanner_enabled ?? true),
    is_show_buy_price: Boolean(props.settings?.is_show_buy_price),
    is_use_profit_percentage: Boolean(props.settings?.is_use_profit_percentage),
    wholesale_profit_percent: Number(props.settings?.wholesale_profit_percent ?? 0),
    consumer_profit_percent: Number(props.settings?.consumer_profit_percent ?? 0),
    sell_interface: props.settings?.sell_interface || 'classic',
    business_sector: props.settings?.business_sector || 'commerce',
    require_till_opening: Boolean(props.settings?.require_till_opening ?? true),
    print_till_slip: Boolean(props.settings?.print_till_slip ?? false),
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
const salesStartDate = ref(props.filters?.sales_start_date ?? '')
const salesEndDate = ref(props.filters?.sales_end_date ?? '')
const activeSalesPreset = ref('custom')

const setSalesPreset = (preset) => {
    activeSalesPreset.value = preset
    const today = new Date()
    const fmt = (d) => {
        const year = d.getFullYear()
        const month = String(d.getMonth() + 1).padStart(2, '0')
        const day = String(d.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    if (preset === 'today') {
        salesStartDate.value = fmt(today)
        salesEndDate.value = fmt(today)
    } else if (preset === 'yesterday') {
        const y = new Date()
        y.setDate(y.getDate() - 1)
        salesStartDate.value = fmt(y)
        salesEndDate.value = fmt(y)
    } else if (preset === 'week') {
        const curr = new Date()
        const day = curr.getDay()
        const diff = curr.getDate() - day + (day === 0 ? -6 : 1)
        const mon = new Date(curr.setDate(diff))
        salesStartDate.value = fmt(mon)
        salesEndDate.value = fmt(new Date())
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)
        salesStartDate.value = fmt(firstDay)
        salesEndDate.value = fmt(new Date())
    } else if (preset === 'all') {
        salesStartDate.value = ''
        salesEndDate.value = ''
    }
    doSalesSearch()
}

const doSalesSearch = () => {
    router.get(
        route('superadmin.branches.show', { branch: props.branch.slug }),
        {
            tab: 'reports',
            sales_search: salesSearch.value,
            sales_start_date: salesStartDate.value,
            sales_end_date: salesEndDate.value,
        },
        { preserveState: true, replace: true }
    )
}

const resetSalesFilters = () => {
    salesSearch.value = ''
    salesStartDate.value = ''
    salesEndDate.value = ''
    activeSalesPreset.value = 'all'
    doSalesSearch()
}

// ── Tab 6: Audit Logs Search ──────────────────────────────────────────────────
const logSearch = ref(props.filters?.log_search ?? '')
const logStartDate = ref(props.filters?.log_start_date ?? '')
const logEndDate = ref(props.filters?.log_end_date ?? '')
const activeLogPreset = ref('custom')

const setLogPreset = (preset) => {
    activeLogPreset.value = preset
    const today = new Date()
    const fmt = (d) => {
        const year = d.getFullYear()
        const month = String(d.getMonth() + 1).padStart(2, '0')
        const day = String(d.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    if (preset === 'today') {
        logStartDate.value = fmt(today)
        logEndDate.value = fmt(today)
    } else if (preset === 'yesterday') {
        const y = new Date()
        y.setDate(y.getDate() - 1)
        logStartDate.value = fmt(y)
        logEndDate.value = fmt(y)
    } else if (preset === 'week') {
        const curr = new Date()
        const day = curr.getDay()
        const diff = curr.getDate() - day + (day === 0 ? -6 : 1)
        const mon = new Date(curr.setDate(diff))
        logStartDate.value = fmt(mon)
        logEndDate.value = fmt(new Date())
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)
        logStartDate.value = fmt(firstDay)
        logEndDate.value = fmt(new Date())
    } else if (preset === 'all') {
        logStartDate.value = ''
        logEndDate.value = ''
    }
    doLogSearch()
}

const doLogSearch = () => {
    router.get(
        route('superadmin.branches.show', { branch: props.branch.slug }),
        {
            tab: 'audit',
            log_search: logSearch.value,
            log_start_date: logStartDate.value,
            log_end_date: logEndDate.value,
        },
        { preserveState: true, replace: true }
    )
}

const resetLogFilters = () => {
    logSearch.value = ''
    logStartDate.value = ''
    logEndDate.value = ''
    activeLogPreset.value = 'all'
    doLogSearch()
}

// ── Export Handlers (Tabs 4, 5, 6) ───────────────────────────────────────────
const showExportModal = ref(false)
const exportForm = ref({
    format: 'xlsx',
    quantity_option: 'total',
})

const openExportModal = () => {
    showExportModal.value = true
}

const triggerExport = () => {
    showExportModal.value = false
    const params = new URLSearchParams()
    params.set('format', exportForm.value.format)
    params.set('quantity_option', exportForm.value.quantity_option)
    if (itemSearch.value) params.set('item_search', itemSearch.value.trim())
    window.location.href = `${route('superadmin.branches.export-items', { branch: props.branch.slug })}?${params.toString()}`
}

const exportSalesMenuOpen = ref(false)
const exportSalesDropdownRef = ref(null)

const downloadSalesExport = (exportFormat = 'xlsx') => {
    exportSalesMenuOpen.value = false
    const params = new URLSearchParams()
    params.set('format', exportFormat)
    if (salesSearch.value) params.set('sales_search', salesSearch.value.trim())
    if (salesStartDate.value) params.set('sales_start_date', salesStartDate.value)
    if (salesEndDate.value) params.set('sales_end_date', salesEndDate.value)
    window.location.href = `${route('superadmin.branches.export-sales', { branch: props.branch.slug })}?${params.toString()}`
}

const exportLogsMenuOpen = ref(false)
const exportLogsDropdownRef = ref(null)

const downloadLogsExport = (exportFormat = 'xlsx') => {
    exportLogsMenuOpen.value = false
    const params = new URLSearchParams()
    params.set('format', exportFormat)
    if (logSearch.value) params.set('log_search', logSearch.value.trim())
    if (logStartDate.value) params.set('log_start_date', logStartDate.value)
    if (logEndDate.value) params.set('log_end_date', logEndDate.value)
    window.location.href = `${route('superadmin.branches.export-logs', { branch: props.branch.slug })}?${params.toString()}`
}

const handleGlobalClickOutside = (e) => {
    if (exportSalesDropdownRef.value && !exportSalesDropdownRef.value.contains(e.target)) {
        exportSalesMenuOpen.value = false
    }
    if (exportLogsDropdownRef.value && !exportLogsDropdownRef.value.contains(e.target)) {
        exportLogsMenuOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleGlobalClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleGlobalClickOutside)
})

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

// ── Tab 4: CSV / Excel Import Library Flow ──────────────────────────────────
const showCsvImportModal = ref(false)
const csvImportTab = ref('library') // 'library' or 'upload'
const isCsvAddingCustomType = ref(false)
const csvFileRef = ref(null)
const csvFileName = ref('')

const csvImportForm = useForm({
    branch_id: props.branch.id,
    source_type: 'library',
    library_file_id: '',
    file: null,
    save_to_library: false,
    library_name: '',
    item_type_id: '',
    custom_type_name: '',
    on_duplicate_barcode: 'skip',
    default_stock_location: 'front_store',
})

const csvPreviewLoading = ref(false)
const csvPreviewData = ref(null)

const openCsvImportModal = () => {
    csvImportTab.value = 'library'
    csvImportForm.branch_id = props.branch.id
    csvImportForm.source_type = 'library'
    csvImportForm.library_file_id = props.import_library_files?.[0]?.id || ''
    csvImportForm.file = null
    csvImportForm.save_to_library = false
    csvImportForm.library_name = ''
    csvImportForm.item_type_id = ''
    csvImportForm.custom_type_name = ''
    csvImportForm.on_duplicate_barcode = 'skip'
    csvImportForm.default_stock_location = 'front_store'
    csvFileName.value = ''
    csvPreviewData.value = null
    showCsvImportModal.value = true

    if (csvImportForm.library_file_id) {
        fetchCsvPreview()
    }
}

const handleCsvFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    csvImportForm.file = file
    csvFileName.value = file.name
    if (!csvImportForm.library_name) {
        csvImportForm.library_name = file.name.replace(/\.[^/.]+$/, '').replace(/[_\-+]+/g, ' ')
    }
    fetchCsvPreview()
}

const fetchCsvPreview = async () => {
    csvPreviewLoading.value = true
    try {
        const formData = new FormData()
        formData.append('branch_id', props.branch.id)
        if (csvImportTab.value === 'library') {
            if (!csvImportForm.library_file_id) {
                csvPreviewLoading.value = false
                return
            }
            formData.append('library_file_id', csvImportForm.library_file_id)
        } else {
            if (!csvImportForm.file) {
                csvPreviewLoading.value = false
                return
            }
            formData.append('file', csvImportForm.file)
        }

        const res = await window.axios.post(route('superadmin.import-library.preview'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        csvPreviewData.value = res.data
    } catch (e) {
        console.error('Preview error:', e)
    } finally {
        csvPreviewLoading.value = false
    }
}

const submitCsvImport = () => {
    csvImportForm.source_type = csvImportTab.value
    csvImportForm.post(route('superadmin.import-library.import-to-branch'), {
        preserveScroll: true,
        onSuccess: () => {
            showCsvImportModal.value = false
            csvPreviewData.value = null
        },
    })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatNaira = (val) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val || 0)
const toggleBranchStatus = () => router.post(route('superadmin.branches.toggle', { branch: props.branch.slug }))
const toggleBranchOffline = () => router.post(route('superadmin.branches.toggle-offline', { branch: props.branch.slug }))
const toggleBranchOfflineQty = () => router.post(route('superadmin.branches.toggle-offline-qty-deduction', { branch: props.branch.slug }))
const toggleBranchAi = () => router.post(route('superadmin.branches.toggle-ai', { branch: props.branch.slug }))
const toggleTillOpening = () => router.post(route('superadmin.branches.toggle-till-opening', { branch: props.branch.slug }), {}, { preserveScroll: true })
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
                            <span
                                v-if="settings.is_offline_enabled"
                                :class="settings.is_offline_qty_deduction ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold border flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="settings.is_offline_qty_deduction ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                {{ settings.is_offline_qty_deduction ? 'Offline Stock Deduction Active' : 'Offline Stock Deduction Skipped' }}
                            </span>
                            <span
                                :class="settings.require_till_opening ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold border flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="settings.require_till_opening ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                {{ settings.require_till_opening ? 'Till Float Required' : 'Direct Checkout (No Till)' }}
                            </span>
                            <Link
                                v-if="$page.props.organizations_enabled && branch.organization"
                                :href="route('superadmin.organizations.show', branch.organization.id)"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition flex items-center gap-1.5"
                                title="View parent organization"
                            >
                                <span>🏢</span>
                                <span>Org: <strong>{{ branch.organization.name }}</strong></span>
                            </Link>
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
                            <span v-if="branch.created_at">📅 Created: <strong class="text-slate-700 dark:text-slate-200">{{ branch.created_at }}</strong></span>
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
                    <button
                        v-if="settings.is_offline_enabled"
                        @click="toggleBranchOfflineQty"
                        :class="settings.is_offline_qty_deduction ? 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900 hover:bg-amber-100' : 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                        :title="settings.is_offline_qty_deduction ? 'Click to skip offline quantity deduction' : 'Click to enforce offline quantity deduction'"
                    >
                        {{ settings.is_offline_qty_deduction ? 'Skip Offline Qty Deduction' : 'Enforce Offline Qty Deduction' }}
                    </button>
                    <button
                        type="button"
                        @click="toggleBranchAi"
                        :class="branch.is_ai_enabled ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                    >
                        {{ branch.is_ai_enabled ? 'Disable AI' : 'Enable AI' }}
                    </button>
                    <button
                        type="button"
                        @click="toggleTillOpening"
                        :class="settings.require_till_opening ? 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900 hover:bg-amber-100' : 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                        :title="settings.require_till_opening ? 'Click to allow direct checkout without opening till' : 'Click to require cashiers to declare opening float'"
                    >
                        {{ settings.require_till_opening ? 'Disable Till Requirement' : 'Require Till Float' }}
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
                <button
                    @click="setActiveTab('ecommerce')"
                    :class="activeTab === 'ecommerce'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                    class="py-3 px-3.5 border-b-2 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer"
                >
                    <span>🛒 E-Commerce</span>
                    <span v-if="commerce_integration" class="w-2 h-2 rounded-full" :class="commerce_integration.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
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
                        <div v-if="$page.props.organizations_enabled" class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800/60">
                            <span class="text-slate-400">Organization:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-purple-700 dark:text-purple-300">
                                    {{ branch.organization?.name || 'Unassigned' }}
                                </span>
                                <button
                                    @click="showMoveOrgModal = true"
                                    type="button"
                                    class="px-2 py-0.5 text-[11px] font-bold bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-950/50 dark:hover:bg-purple-900/50 dark:text-purple-300 border border-purple-200 dark:border-purple-800 rounded-md transition cursor-pointer"
                                >
                                    Move
                                </button>
                            </div>
                        </div>
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
                                    <span v-if="user.permissions?.length && user.permissions.length >= all_permissions.length"
                                        class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-1 rounded border border-indigo-200 dark:border-indigo-500/20">
                                        Full Access (All Permissions)
                                    </span>
                                    <span v-else-if="user.permissions?.length"
                                        class="text-xs text-emerald-700 dark:text-emerald-300 font-medium bg-emerald-50 dark:bg-emerald-500/10 px-2.5 py-1 rounded border border-emerald-200 dark:border-emerald-500/20">
                                        {{ user.permissions.length }} of {{ all_permissions.length }} permissions
                                    </span>
                                    <span v-else-if="user.roles?.some(r => r.name === 'branch-admin')"
                                        class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-1 rounded border border-indigo-200 dark:border-indigo-500/20">
                                        Full Access (All Permissions)
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

                            <div class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Track VAT in General Ledger (Double-Entry)</div>
                                    <div class="text-[11px] text-slate-500">Post collected VAT to Account 2100 (VAT Payable) for periodic VAT ledger reporting</div>
                                </div>
                                <input
                                    v-model="settingsForm.is_accounting_vat_enabled"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer"
                                />
                            </div>

                            <div v-if="settings?.accounting_started_at" class="p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                                <span class="text-slate-500">Books Start Date:</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ new Date(settings.accounting_started_at).toLocaleDateString() }}</span>
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

                            <div class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div class="pr-4">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Local Quantity Deduction when Offline</div>
                                    <div class="text-[11px] text-slate-500">Enforce stock validation on Payment Modal and deduct local inventory when offline. Uncheck to skip offline quantity deduction (server validates stock during sync).</div>
                                </div>
                                <input
                                    v-model="settingsForm.is_offline_qty_deduction"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer shrink-0"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Held Sales Visibility Mode</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                                    <label
                                        :class="[
                                            'p-3 rounded-2xl border text-xs cursor-pointer transition flex items-start gap-2.5',
                                            settingsForm.held_sales_visibility === 'device_only'
                                                ? 'bg-indigo-50/50 dark:bg-indigo-950/30 border-indigo-500/50 text-slate-900 dark:text-white'
                                                : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            value="device_only"
                                            v-model="settingsForm.held_sales_visibility"
                                            class="mt-0.5 accent-indigo-600"
                                        />
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white">Device Only (Default)</div>
                                            <div class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                                Held carts stay strictly local to the device/till that held them. Zero cross-device sync.
                                            </div>
                                        </div>
                                    </label>

                                    <label
                                        :class="[
                                            'p-3 rounded-2xl border text-xs cursor-pointer transition flex items-start gap-2.5',
                                            settingsForm.held_sales_visibility === 'shared_across_branch'
                                                ? 'bg-indigo-50/50 dark:bg-indigo-950/30 border-indigo-500/50 text-slate-900 dark:text-white'
                                                : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            value="shared_across_branch"
                                            v-model="settingsForm.held_sales_visibility"
                                            class="mt-0.5 accent-indigo-600"
                                        />
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white">Shared Across Branch</div>
                                            <div class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                                Syncs to server; other tills can view and resume via the manual Refresh button.
                                            </div>
                                        </div>
                                    </label>
                                </div>
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

                    <!-- Cloudflare Turnstile & Low-Network Security Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4 lg:col-span-2">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">Cloudflare CAPTCHA & Login Security</h3>
                                <p class="text-xs text-slate-500">Configure login bot protection and slow network bypass for this branch.</p>
                            </div>
                            <span class="text-xl">🛡️</span>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div class="pr-4">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>Cloudflare Turnstile CAPTCHA</span>
                                        <span
                                            class="text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider"
                                            :class="settingsForm.is_turnstile_enabled
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800'"
                                        >
                                            {{ settingsForm.is_turnstile_enabled ? 'Active (Protected)' : 'Bypassed (Low Network / Fast Login)' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                        {{ settingsForm.is_turnstile_enabled
                                            ? 'Enforces Cloudflare bot verification before staff login.'
                                            : 'Turnstile is disabled for this branch. Cashiers and staff can log in instantly without waiting for Cloudflare challenge scripts on slow or unstable networks.' }}
                                    </div>
                                </div>
                                <input
                                    v-model="settingsForm.is_turnstile_enabled"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer flex-shrink-0"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Fullscreen POS Mode Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4 lg:col-span-2">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">Terminal Fullscreen Display Mode</h3>
                                <p class="text-xs text-slate-500">Configure auto-fullscreen takeover for POS registers on any device.</p>
                            </div>
                            <span class="text-xl">🖥️</span>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div class="pr-4">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>Auto-Fullscreen on Terminal Login</span>
                                        <span
                                            class="text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider"
                                            :class="settingsForm.is_auto_fullscreen_enabled
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700'"
                                        >
                                            {{ settingsForm.is_auto_fullscreen_enabled ? 'Auto-Fullscreen Enabled' : 'Standard Windowed' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                        {{ settingsForm.is_auto_fullscreen_enabled
                                            ? 'When cashiers or branch staff log in on any device (desktop, tablet, or mobile), the POS will automatically expand to fill the entire screen without browser borders. Automatically exits upon logout.'
                                            : 'Cashiers will start in standard windowed mode and can manually toggle fullscreen using the topbar icon or F11.' }}
                                    </div>
                                </div>
                                <input
                                    v-model="settingsForm.is_auto_fullscreen_enabled"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer flex-shrink-0"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Till Sessions & Cash Drawer Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4 lg:col-span-2">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">Till Sessions & Cash Drawer Accountability</h3>
                                <p class="text-xs text-slate-500">Require opening float declaration before checkout, shift reconciliations, and cash drawer management.</p>
                            </div>
                            <span class="text-xl">💼</span>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div class="pr-4">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>Require Opening Cash Float Before Checkout</span>
                                        <span
                                            class="text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider"
                                            :class="settingsForm.require_till_opening
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800'"
                                        >
                                            {{ settingsForm.require_till_opening ? 'Active (Till Opening Required)' : 'Disabled (Direct Checkout Allowed)' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                        {{ settingsForm.require_till_opening
                                            ? 'Cashiers cannot checkout until they declare their opening float. Every sale is linked to their till session for complete cash drawer auditing.'
                                            : 'Cashiers can checkout directly without opening a till session. Till drawer prompts are hidden on the POS screen.' }}
                                    </div>
                                </div>
                                <input
                                    v-model="settingsForm.require_till_opening"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer flex-shrink-0"
                                />
                            </div>

                            <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                                <div class="pr-4">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Print Till Reconciliation Slip on Shift Close</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                        Automatically generates and previews thermal till summary slip when a cashier or manager closes a till session.
                                    </div>
                                </div>
                                <input
                                    v-model="settingsForm.print_till_slip"
                                    type="checkbox"
                                    class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer flex-shrink-0"
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
                            <input v-model="settingsForm.is_offline_qty_deduction" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <div>
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Deduct Qty When Offline</span>
                                <p class="text-[10px] text-slate-400">Enforce local stock limit & deduction offline.</p>
                            </div>
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
                            <input v-model="settingsForm.is_camera_scanner_enabled" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <div>
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Camera Barcode Scanner</span>
                                <p class="text-[10px] text-slate-400">Shows camera scan button on POS selling page for cashiers.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.is_check_expiration" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Track Expiration Dates</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
                            <input v-model="settingsForm.batch_tracking_enabled" type="checkbox" class="w-4 h-4 rounded text-indigo-600" />
                            <div>
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Enable Item Expiry & Batch Tracking</span>
                                <p class="text-[10px] text-slate-400">Supports multiple batches per item with individual expiry dates (Pharmacy / Perishables).</p>
                            </div>
                        </label>
                        <div v-if="settingsForm.batch_tracking_enabled" class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                            <label class="block text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">Expiry Alert Warning Threshold (Days)</label>
                            <input v-model="settingsForm.expiry_alert_days" type="number" min="1" max="365" class="w-full px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white" />
                            <p class="text-[10px] text-slate-400 mt-1">Batches expiring within this number of days will trigger dashboard warnings & notifications.</p>
                        </div>
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
                <div class="flex items-center flex-wrap gap-2.5">
                    <button
                        @click="openExportModal"
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center space-x-1.5 cursor-pointer"
                        title="Download current branch catalog in CSV or Excel format"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Export Catalog</span>
                    </button>
                    <button
                        @click="openCsvImportModal"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 flex items-center space-x-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Import from CSV / Excel</span>
                    </button>
                    <button
                        @click="showImportModal = true"
                        class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition border border-slate-200 dark:border-slate-700 flex items-center space-x-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span>Global Master Pool</span>
                    </button>
                    <button
                        @click="submitImportAll"
                        :disabled="importAllForm.processing"
                        class="px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 disabled:opacity-50 flex items-center space-x-1.5 cursor-pointer"
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
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('superadmin.reports.expenses', { branch_id: branch.id })"
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition flex items-center space-x-1.5 cursor-pointer border border-slate-200 dark:border-slate-700"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span>Branch Expenses</span>
                    </Link>

                    <!-- Export Dropdown -->
                    <div class="relative" ref="exportSalesDropdownRef">
                    <button
                        type="button"
                        @click="exportSalesMenuOpen = !exportSalesMenuOpen"
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center space-x-1.5 cursor-pointer"
                        title="Export sales records"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export Sales</span>
                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="exportSalesMenuOpen"
                        class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                    >
                        <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            Export Sales List
                        </div>
                        <button
                            type="button"
                            @click="downloadSalesExport('xlsx')"
                            class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                                XLS
                            </span>
                            Excel (.xlsx)
                        </button>
                        <button
                            type="button"
                            @click="downloadSalesExport('csv')"
                            class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-950/40 text-slate-700 dark:text-slate-200 hover:text-blue-700 dark:hover:text-blue-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-[10px]">
                                CSV
                            </span>
                            CSV Document (.csv)
                        </button>
                    </div>
                </div>
            </div>
        </div>

            <!-- Sales Filter Bar with Preset Pills, Start & End Date Pickers, Search, and Reset -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-3">
                <!-- Preset Pills -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mr-1">Date Presets:</span>
                    <button
                        type="button"
                        @click="setSalesPreset('all')"
                        :class="activeSalesPreset === 'all' && !salesStartDate && !salesEndDate
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        All Time
                    </button>
                    <button
                        type="button"
                        @click="setSalesPreset('today')"
                        :class="activeSalesPreset === 'today'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        @click="setSalesPreset('yesterday')"
                        :class="activeSalesPreset === 'yesterday'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        Yesterday
                    </button>
                    <button
                        type="button"
                        @click="setSalesPreset('week')"
                        :class="activeSalesPreset === 'week'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        This Week
                    </button>
                    <button
                        type="button"
                        @click="setSalesPreset('month')"
                        :class="activeSalesPreset === 'month'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        This Month
                    </button>
                </div>

                <!-- Custom Range & Keyword Search Controls -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 pt-1">
                    <!-- Search Input -->
                    <div class="lg:col-span-5 relative">
                        <input
                            v-model="salesSearch"
                            @keydown.enter="doSalesSearch"
                            type="text"
                            placeholder="Search receipt #, customer, cashier, payment method…"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        />
                        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Start Date -->
                    <div class="lg:col-span-3">
                        <div class="relative">
                            <input
                                v-model="salesStartDate"
                                @change="activeSalesPreset = 'custom'; doSalesSearch()"
                                type="date"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- End Date -->
                    <div class="lg:col-span-3">
                        <div class="relative">
                            <input
                                v-model="salesEndDate"
                                @change="activeSalesPreset = 'custom'; doSalesSearch()"
                                type="date"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Filter / Reset Actions -->
                    <div class="lg:col-span-1 flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="doSalesSearch"
                            class="w-full px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center justify-center"
                            title="Apply Filter"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </button>
                        <button
                            v-if="salesSearch || salesStartDate || salesEndDate"
                            type="button"
                            @click="resetSalesFilters"
                            class="px-2.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 rounded-xl text-xs font-bold transition cursor-pointer"
                            title="Reset Filters"
                        >
                            &times;
                        </button>
                    </div>
                </div>
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
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Branch Activity & Change Audit Trail</h2>
                    <p class="text-xs text-slate-500">Complete timestamped history of actions and configuration overrides in {{ branch.name }}.</p>
                </div>
                <!-- Export Dropdown -->
                <div class="relative" ref="exportLogsDropdownRef">
                    <button
                        type="button"
                        @click="exportLogsMenuOpen = !exportLogsMenuOpen"
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center space-x-1.5 cursor-pointer"
                        title="Export audit logs"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export Logs</span>
                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="exportLogsMenuOpen"
                        class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                    >
                        <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            Export Audit Logs
                        </div>
                        <button
                            type="button"
                            @click="downloadLogsExport('xlsx')"
                            class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                                XLS
                            </span>
                            Excel (.xlsx)
                        </button>
                        <button
                            type="button"
                            @click="downloadLogsExport('csv')"
                            class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-950/40 text-slate-700 dark:text-slate-200 hover:text-blue-700 dark:hover:text-blue-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-[10px]">
                                CSV
                            </span>
                            CSV Document (.csv)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Audit Logs Filter Bar with Preset Pills, Start & End Date Pickers, Search, and Reset -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-3">
                <!-- Preset Pills -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mr-1">Date Presets:</span>
                    <button
                        type="button"
                        @click="setLogPreset('all')"
                        :class="activeLogPreset === 'all' && !logStartDate && !logEndDate
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        All Time
                    </button>
                    <button
                        type="button"
                        @click="setLogPreset('today')"
                        :class="activeLogPreset === 'today'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        @click="setLogPreset('yesterday')"
                        :class="activeLogPreset === 'yesterday'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        Yesterday
                    </button>
                    <button
                        type="button"
                        @click="setLogPreset('week')"
                        :class="activeLogPreset === 'week'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        This Week
                    </button>
                    <button
                        type="button"
                        @click="setLogPreset('month')"
                        :class="activeLogPreset === 'month'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                    >
                        This Month
                    </button>
                </div>

                <!-- Custom Range & Keyword Search Controls -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 pt-1">
                    <!-- Search Input -->
                    <div class="lg:col-span-5 relative">
                        <input
                            v-model="logSearch"
                            @keydown.enter="doLogSearch"
                            type="text"
                            placeholder="Search audit trail by action, user, or IP…"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        />
                        <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Start Date -->
                    <div class="lg:col-span-3">
                        <div class="relative">
                            <input
                                v-model="logStartDate"
                                @change="activeLogPreset = 'custom'; doLogSearch()"
                                type="date"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- End Date -->
                    <div class="lg:col-span-3">
                        <div class="relative">
                            <input
                                v-model="logEndDate"
                                @change="activeLogPreset = 'custom'; doLogSearch()"
                                type="date"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Filter / Reset Actions -->
                    <div class="lg:col-span-1 flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="doLogSearch"
                            class="w-full px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center justify-center"
                            title="Apply Filter"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </button>
                        <button
                            v-if="logSearch || logStartDate || logEndDate"
                            type="button"
                            @click="resetLogFilters"
                            class="px-2.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 rounded-xl text-xs font-bold transition cursor-pointer"
                            title="Reset Filters"
                        >
                            &times;
                        </button>
                    </div>
                </div>
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

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 7: E-COMMERCE INTEGRATION                                          -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'ecommerce'" class="space-y-6">
            <EcommerceTab
                :branch="branch"
                :commerce-integration="commerce_integration"
                :ecommerce-capability-enabled="ecommerce_capability_enabled"
                :commerce-api-base-url="commerce_api_base_url"
                :new-commerce-token="new_commerce_token"
            />
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
        <div v-if="showCreateUserModal" @click.self="closeCreateUserModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Add Staff User to {{ branch.name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Assign role and select specific POS permissions</p>
                    </div>
                    <button
                        type="button"
                        @click="closeCreateUserModal"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer text-xl font-bold"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>

                <!-- Global Validation Error Banner -->
                <div v-if="userForm.hasErrors" class="p-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-xl text-xs text-rose-700 dark:text-rose-300 font-medium flex-shrink-0">
                    ⚠️ Please correct the errors highlighted below before creating this staff account.
                </div>

                <form @submit.prevent="submitCreateUser" class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input v-model="userForm.name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="userForm.errors.name" class="text-rose-500 text-xs mt-1 font-medium">{{ userForm.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                            <input v-model="userForm.username" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="userForm.errors.username" class="text-rose-500 text-xs mt-1 font-medium">{{ userForm.errors.username }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email <span class="text-slate-400 font-normal">(Optional)</span></label>
                            <input v-model="userForm.email" type="email"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="userForm.errors.email" class="text-rose-500 text-xs mt-1 font-medium">{{ userForm.errors.email }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password *</label>
                            <div class="relative flex items-center">
                                <input v-model="userForm.password" :type="showCreatePass ? 'text' : 'password'" required
                                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 pr-9 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                                <button type="button" @click="showCreatePass = !showCreatePass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 transition focus:outline-none cursor-pointer">
                                    <span v-if="!showCreatePass">👁️</span>
                                    <span v-else>🙈</span>
                                </button>
                            </div>
                            <div v-if="userForm.errors.password" class="text-rose-500 text-xs mt-1 font-medium">{{ userForm.errors.password }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Role *</label>
                        <select v-model="userForm.role" required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                            <option value="" disabled>-- Select Base Role --</option>
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ formatRoleName(r.name) }}</option>
                        </select>
                        <div v-if="userForm.errors.role" class="text-rose-500 text-xs mt-1 font-medium">{{ userForm.errors.role }}</div>
                    </div>

                    <!-- Permissions Grid -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">Granular Feature Permissions</h3>
                                <p class="text-xs text-slate-500">Assign menu and operational rights</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" @click="selectAllCreate" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                    Select All
                                </button>
                                <button type="button" @click="deselectAllCreate" class="text-xs text-slate-600 dark:text-slate-400 hover:underline px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
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
                        <div v-if="userForm.errors.permissions" class="text-rose-500 text-xs mt-1 font-medium">{{ userForm.errors.permissions }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="closeCreateUserModal" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="userForm.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 cursor-pointer disabled:opacity-50">
                            {{ userForm.processing ? 'Creating User…' : 'Create User & Assign Permissions' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL: EDIT STAFF USER ────────────────────────────────────────── -->
        <div v-if="showEditUserModal" @click.self="closeEditUserModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Edit Staff User — @{{ editingUser?.username }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Update account details and feature permissions</p>
                    </div>
                    <button
                        type="button"
                        @click="closeEditUserModal"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer text-xl font-bold"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>

                <!-- Global Validation Error Banner -->
                <div v-if="editUserForm.hasErrors" class="p-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-xl text-xs text-rose-700 dark:text-rose-300 font-medium flex-shrink-0">
                    ⚠️ Please correct the errors highlighted below before saving changes.
                </div>

                <form @submit.prevent="submitEditUser" class="space-y-4 overflow-y-auto pr-1 flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input v-model="editUserForm.name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="editUserForm.errors.name" class="text-rose-500 text-xs mt-1 font-medium">{{ editUserForm.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                            <input v-model="editUserForm.username" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="editUserForm.errors.username" class="text-rose-500 text-xs mt-1 font-medium">{{ editUserForm.errors.username }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email <span class="text-slate-400 font-normal">(Optional)</span></label>
                            <input v-model="editUserForm.email" type="email"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                            <div v-if="editUserForm.errors.email" class="text-rose-500 text-xs mt-1 font-medium">{{ editUserForm.errors.email }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Role *</label>
                            <select v-model="editUserForm.role" required
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="" disabled>-- Select Base Role --</option>
                                <option v-for="r in roles" :key="r.id" :value="r.name">{{ formatRoleName(r.name) }}</option>
                            </select>
                            <div v-if="editUserForm.errors.role" class="text-rose-500 text-xs mt-1 font-medium">{{ editUserForm.errors.role }}</div>
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
                                <button type="button" @click="selectAllEdit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                    Select All
                                </button>
                                <button type="button" @click="deselectAllEdit" class="text-xs text-slate-600 dark:text-slate-400 hover:underline px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
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
                        <div v-if="editUserForm.errors.permissions" class="text-rose-500 text-xs mt-1 font-medium">{{ editUserForm.errors.permissions }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="closeEditUserModal" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="editUserForm.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 cursor-pointer disabled:opacity-50">
                            {{ editUserForm.processing ? 'Saving…' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL: RESET PASSWORD ─────────────────────────────────────────── -->
        <div v-if="showResetUserModal" @click.self="closeResetUserModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Reset Password</h2>
                    <button
                        type="button"
                        @click="closeResetUserModal"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer text-xl font-bold"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>
                <form @submit.prevent="submitResetUser" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
                        <input v-model="resetUserForm.password" :type="showResetPass ? 'text' : 'password'" required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        <div v-if="resetUserForm.errors.password" class="text-rose-500 text-xs mt-1 font-medium">{{ resetUserForm.errors.password }}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm Password *</label>
                        <input v-model="resetUserForm.password_confirmation" :type="showResetConfirmPass ? 'text' : 'password'" required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                        <div v-if="resetUserForm.errors.password_confirmation" class="text-rose-500 text-xs mt-1 font-medium">{{ resetUserForm.errors.password_confirmation }}</div>
                    </div>
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="closeResetUserModal" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="resetUserForm.processing" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer disabled:opacity-50">
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

        <!-- ── MODAL: IMPORT FROM CSV / EXCEL LIBRARY OR FILE ────────────────── -->
        <div v-if="showCsvImportModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-base text-slate-900 dark:text-slate-100">Import Catalog into {{ branch.name }}</h2>
                        <p class="text-xs text-slate-500">Pick from stored library catalogs or upload a fresh CSV/Excel spreadsheet.</p>
                    </div>
                    <button @click="showCsvImportModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="space-y-4 overflow-y-auto pr-1 flex-1 text-xs">
                    <!-- Source Switcher (Library vs Upload Fresh) -->
                    <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 font-bold">
                        <button
                            type="button"
                            @click="csvImportTab = 'library'; csvImportForm.source_type = 'library'; fetchCsvPreview()"
                            :class="csvImportTab === 'library' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                            class="flex-1 py-2 rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>📚 Pick from Stored Library</span>
                        </button>
                        <button
                            type="button"
                            @click="csvImportTab = 'upload'; csvImportForm.source_type = 'upload'; fetchCsvPreview()"
                            :class="csvImportTab === 'upload' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                            class="flex-1 py-2 rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>📂 Upload Fresh Spreadsheet</span>
                        </button>
                    </div>

                    <!-- TAB 1: PICK FROM LIBRARY -->
                    <div v-if="csvImportTab === 'library'" class="space-y-3">
                        <div v-if="!import_library_files?.length" class="p-6 text-center text-slate-400 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                            No active library files found. Switch to "Upload Fresh Spreadsheet" or visit the Import Library.
                        </div>
                        <div v-else>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Select Stored Catalog File *</label>
                            <select
                                v-model="csvImportForm.library_file_id"
                                @change="fetchCsvPreview"
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 font-semibold focus:outline-none focus:border-indigo-500"
                            >
                                <option v-for="f in import_library_files" :key="f.id" :value="f.id">
                                    📄 {{ f.name }} ({{ f.row_count }} items) — {{ f.item_type_tag }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- TAB 2: UPLOAD FRESH SPREADSHEET -->
                    <div v-else class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Select Spreadsheet File (.xlsx, .csv) *</label>
                            <div
                                @click="$refs.csvFileRef.click()"
                                class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 rounded-2xl p-4 text-center cursor-pointer transition bg-slate-50/60 dark:bg-slate-950/40 space-y-1"
                            >
                                <input
                                    ref="csvFileRef"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    @change="handleCsvFileChange"
                                    class="hidden"
                                />
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ csvFileName || 'Click to select CSV or Excel file from computer' }}
                                </div>
                                <p class="text-[11px] text-slate-400">Max 20MB (.xlsx or .csv)</p>
                            </div>
                        </div>

                        <!-- Checkbox: Save to Library for future reuse -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input
                                    v-model="csvImportForm.save_to_library"
                                    type="checkbox"
                                    class="w-4 h-4 rounded text-indigo-600 border-slate-300 dark:border-slate-700"
                                />
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white text-xs">Also save this file to the Import Library</div>
                                    <div class="text-[11px] text-slate-500">Make it reusable to quickly stock other branches in the future</div>
                                </div>
                            </label>

                            <div v-if="csvImportForm.save_to_library" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200 dark:border-slate-800">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Library Catalog Name *</label>
                                    <input
                                        v-model="csvImportForm.library_name"
                                        type="text"
                                        placeholder="e.g. Asaba Supermarket Initial Stock"
                                        class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                                    />
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">Category Tag</label>
                                        <button
                                            type="button"
                                            @click="isCsvAddingCustomType = !isCsvAddingCustomType"
                                            class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            {{ isCsvAddingCustomType ? '← Pick' : '+ New' }}
                                        </button>
                                    </div>
                                    <select
                                        v-if="!isCsvAddingCustomType"
                                        v-model="csvImportForm.item_type_id"
                                        class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                                    >
                                        <option value="">Select Category Tag</option>
                                        <option v-for="t in import_item_types" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                    <input
                                        v-else
                                        v-model="csvImportForm.custom_type_name"
                                        type="text"
                                        placeholder="New Category Tag"
                                        class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Strategy Options -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">On Duplicate Barcode:</label>
                            <select
                                v-model="csvImportForm.on_duplicate_barcode"
                                class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                            >
                                <option value="skip">Skip row (Do not overwrite)</option>
                                <option value="leave_empty">Import item with empty barcode (Leave null)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Initial Stock Destination:</label>
                            <select
                                v-model="csvImportForm.default_stock_location"
                                class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                            >
                                <option value="front_store">Front Store (Ready to sell)</option>
                                <option value="back_store">Back Store / Warehouse</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Validation Preview Box -->
                    <div v-if="csvPreviewLoading" class="p-6 text-center text-slate-400 space-y-2">
                        <div class="inline-block animate-spin text-2xl">⏳</div>
                        <div>Validating catalog rows…</div>
                    </div>

                    <div v-else-if="csvPreviewData" class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-2.5 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl border border-emerald-200 dark:border-emerald-500/20 text-center">
                                <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 uppercase">Valid Rows Ready</div>
                                <div class="text-base font-black text-emerald-800 dark:text-emerald-300">{{ csvPreviewData.valid_rows }}</div>
                            </div>
                            <div class="p-2.5 bg-amber-50 dark:bg-amber-500/10 rounded-xl border border-amber-200 dark:border-amber-500/20 text-center">
                                <div class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase">Barcode Conflicts</div>
                                <div class="text-base font-black text-amber-800 dark:text-amber-300">{{ csvPreviewData.barcode_conflicts_count }}</div>
                            </div>
                            <div class="p-2.5 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 text-center">
                                <div class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase">Invalid Rows</div>
                                <div class="text-base font-black text-slate-800 dark:text-slate-200">{{ csvPreviewData.invalid_rows }}</div>
                            </div>
                        </div>

                        <!-- Sample Rows Preview Table -->
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Sample Items (First 5 Rows):</div>
                            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                                <table class="w-full text-left text-[11px]">
                                    <thead class="bg-slate-50 dark:bg-slate-800/80 font-semibold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                        <tr>
                                            <th class="px-3 py-1.5">Item Name</th>
                                            <th class="px-3 py-1.5">Category</th>
                                            <th class="px-3 py-1.5">Unit Price</th>
                                            <th class="px-3 py-1.5">Packaging Units</th>
                                            <th class="px-3 py-1.5">IMEI</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        <tr v-for="(row, idx) in csvPreviewData.sample_rows.slice(0, 5)" :key="idx" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                            <td class="px-3 py-1.5 font-bold text-slate-900 dark:text-white">{{ row.data.item_name }}</td>
                                            <td class="px-3 py-1.5 text-slate-500">{{ row.data.category || '—' }}</td>
                                            <td class="px-3 py-1.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">₦{{ Number(row.data.base_unit_price).toLocaleString() }}</td>
                                            <td class="px-3 py-1.5 font-mono text-slate-500 text-[10px]">
                                                <span v-if="row.data.pack_price">
                                                    {{ row.data.pack_unit_name || 'Pack' }}: ₦{{ Number(row.data.pack_price).toLocaleString() }} ({{ row.data.pack_quantity }})
                                                </span>
                                                <span v-else-if="row.data.carton_price">
                                                    {{ row.data.carton_unit_name || 'Carton' }}: ₦{{ Number(row.data.carton_price).toLocaleString() }}
                                                </span>
                                                <span v-else class="text-slate-400">—</span>
                                            </td>
                                            <td class="px-3 py-1.5">
                                                <span v-if="row.data.track_by_imei" class="px-1.5 py-0.5 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 rounded font-bold text-[9px]">IMEI</span>
                                                <span v-else class="text-slate-400">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links to Download Blank/Sample Template -->
                    <div class="flex items-center justify-between p-3 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-2xl border border-indigo-100 dark:border-indigo-900/30 text-[11px]">
                        <span class="text-slate-600 dark:text-slate-400">Need the standard spreadsheet format?</span>
                        <div class="flex items-center gap-2">
                            <a :href="route('superadmin.import-library.template', { format: 'xlsx' })" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                📊 Blank Excel
                            </a>
                            <span class="text-slate-300">·</span>
                            <a :href="route('superadmin.import-library.template', { format: 'xlsx', sample: 1 })" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                ✨ Sample Excel
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800 flex-shrink-0">
                    <div class="text-[11px] text-slate-500">
                        {{ csvPreviewData ? `${csvPreviewData.valid_rows} item(s) will be imported into ${branch.name}.` : '' }}
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="showCsvImportModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitCsvImport"
                            :disabled="csvImportForm.processing || csvPreviewLoading || (csvImportTab === 'library' && !csvImportForm.library_file_id) || (csvImportTab === 'upload' && !csvImportForm.file)"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ csvImportForm.processing ? 'Importing…' : 'Execute Branch Import' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Branch Items Modal -->
        <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-5 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Export Branch Catalog</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Download current item inventory for <strong>{{ branch.name }}</strong></p>
                        </div>
                    </div>
                    <button @click="showExportModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <div class="space-y-4">
                    <!-- Format selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">1. Choose File Format:</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-center gap-3',
                                    exportForm.format === 'xlsx'
                                        ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/50'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.format" value="xlsx" class="sr-only" />
                                <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">
                                    XLS
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Excel (.xlsx)</div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Best for Excel / Spreadsheets</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-center gap-3',
                                    exportForm.format === 'csv'
                                        ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/50'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.format" value="csv" class="sr-only" />
                                <div class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-xs">
                                    CSV
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">CSV (.csv)</div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Universal comma-separated</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Quantity option selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">2. Choose Stock Quantity Level:</label>
                        <div class="space-y-2">
                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'total'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="total" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Total Stock (Front + Back Store)</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Exports combined quantity across floor & warehouse locations</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'front_store'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="front_store" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Front Store Quantity Only</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Exports only floor stock currently available for retail sale</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'back_store'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="back_store" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Back Store / Warehouse Quantity Only</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Exports reserve stock in warehouse storage</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'none'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="none" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Catalog Only (No Quantities / 0 Qty)</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Perfect for cloning product catalog into other branches or saving to Import Library</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <button
                        type="button"
                        @click="showExportModal = false"
                        class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="triggerExport"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-emerald-600/20 flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download {{ exportForm.format.toUpperCase() }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Move Branch Organization Modal -->
        <div v-if="showMoveOrgModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 flex items-center justify-center text-sm font-bold">
                            🏢
                        </div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-sm">Move Branch Organization</h2>
                    </div>
                    <button @click="showMoveOrgModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1.5 leading-relaxed bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200 dark:border-slate-700/60">
                    <div>Branch: <strong class="text-slate-900 dark:text-slate-100 font-bold">{{ branch.name }}</strong> (<code>{{ branch.slug }}</code>)</div>
                    <div>Current Org: <strong class="text-purple-600 dark:text-purple-400 font-semibold">{{ branch.organization?.name || 'Unassigned' }}</strong></div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 pt-1">
                        Moving only reassigns this branch to a different client organization. All transactional sales, stock, staff accounts, and history stay intact.
                    </p>
                </div>

                <form @submit.prevent="submitMoveOrg" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Destination Organization <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="moveOrgForm.organization_id"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition cursor-pointer"
                        >
                            <option value="" disabled>-- Select Destination Organization --</option>
                            <option v-for="org in all_organizations" :key="org.id" :value="org.id">
                                {{ org.name }}
                            </option>
                        </select>
                        <div v-if="moveOrgForm.errors.organization_id" class="text-rose-500 text-xs mt-1">{{ moveOrgForm.errors.organization_id }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="showMoveOrgModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="moveOrgForm.processing || !moveOrgForm.organization_id"
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition shadow-sm disabled:opacity-50 cursor-pointer"
                        >
                            <span v-if="moveOrgForm.processing">Moving...</span>
                            <span v-else>Confirm & Move</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
