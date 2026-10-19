<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    organization: Object,
    branches: Array,
    groups: [Object, Array],
    totalGroups: Number,
    conflictCount: Number,
    unlinkedStats: Object,
    allGroupOptions: {
        type: Array,
        default: () => [],
    },
    recentBatches: {
        type: Array,
        default: () => [],
    },
    filters: Object,
})

// Normalized group list (supports both paginated object and plain array)
const groupList = computed(() => {
    if (Array.isArray(props.groups)) return props.groups
    return props.groups?.data || []
})

const paginationMeta = computed(() => {
    if (Array.isArray(props.groups)) {
        return {
            from: groupList.value.length ? 1 : 0,
            to: groupList.value.length,
            total: groupList.value.length,
        }
    }
    return props.groups || {}
})

const paginationLinks = computed(() => {
    return props.groups?.links || []
})

// Search & Filter state
const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || 'all')
const perPage = ref(Number(props.filters?.per_page) || 25)

const applyFilters = () => {
    router.get(
        route('superadmin.organizations.product-links.index', props.organization.id),
        {
            search: search.value,
            status: statusFilter.value,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true }
    )
}

const clearFilters = () => {
    search.value = ''
    statusFilter.value = 'all'
    perPage.value = 25
    applyFilters()
}

// Options for merge dropdown across all groups in org
const mergeGroupOptions = computed(() => {
    return props.allGroupOptions?.length ? props.allGroupOptions : groupList.value
})

// ── Modals State ─────────────────────────────────────────────────────────────
const showCreateGroupModal = ref(false)
const showLinkModal = ref(false)
const showSuggestModal = ref(false)
const showCopyModal = ref(false)
const showMergeModal = ref(false)
const showUndoModal = ref(false)

// Active Context for Modals
const selectedGroup = ref(null)
const selectedBranch = ref(null)
const selectedItem = ref(null)

// 1. Create Group Form
const createGroupForm = useForm({
    name: '',
    initial_item_id: null,
})

const submitCreateGroup = () => {
    createGroupForm.post(route('superadmin.organizations.product-links.create-group', props.organization.id), {
        onSuccess: () => {
            showCreateGroupModal.value = false
            createGroupForm.reset()
        },
    })
}

const showEditGroupModal = ref(false)

// 1b. Edit Group Form
const editGroupForm = useForm({
    product_group_id: null,
    name: '',
})

const openEditGroupModal = (group) => {
    editGroupForm.product_group_id = group.id
    editGroupForm.name = group.name
    editGroupForm.clearErrors()
    showEditGroupModal.value = true
}

const submitEditGroup = () => {
    editGroupForm.post(route('admin.organizations.product-links.update-group', { organization: props.organization.id }), {
        onSuccess: () => {
            showEditGroupModal.value = false
            editGroupForm.reset()
        },
    })
}

// 2. Link Item Modal & Search
const linkForm = useForm({
    product_group_id: null,
    branch_id: null,
    item_id: null,
    link_source: 'manual',
})

const unlinkedItemsList = ref([])
const loadingUnlinked = ref(false)
const itemSearchQuery = ref('')
let unlinkedSearchTimeout = null
let unlinkedAbortController = null

const openLinkModal = (group, branch) => {
    selectedGroup.value = group
    selectedBranch.value = branch
    linkForm.product_group_id = group.id
    linkForm.branch_id = branch.id
    linkForm.item_id = null
    itemSearchQuery.value = ''
    unlinkedItemsList.value = []
    showLinkModal.value = true
    fetchUnlinkedItems(branch.id, '')
}

const fetchUnlinkedItems = async (branchId, q = '') => {
    if (!branchId) return

    if (unlinkedAbortController) {
        unlinkedAbortController.abort()
    }
    unlinkedAbortController = new AbortController()

    loadingUnlinked.value = true
    try {
        const res = await fetch(
            `/superadmin/organizations/${props.organization.id}/product-links/unlinked-items/${branchId}?search=${encodeURIComponent(q || '')}&limit=25`,
            { signal: unlinkedAbortController.signal }
        )
        if (!res.ok) throw new Error('Failed to fetch items')
        unlinkedItemsList.value = await res.json()
    } catch (err) {
        if (err.name !== 'AbortError') {
            unlinkedItemsList.value = []
        }
    } finally {
        loadingUnlinked.value = false
    }
}

// Watch itemSearchQuery with 250ms debounce
watch(itemSearchQuery, (newQuery) => {
    if (!showLinkModal.value || !selectedBranch.value) return
    if (unlinkedSearchTimeout) clearTimeout(unlinkedSearchTimeout)
    unlinkedSearchTimeout = setTimeout(() => {
        fetchUnlinkedItems(selectedBranch.value.id, newQuery)
    }, 250)
})

const clearItemSearch = () => {
    itemSearchQuery.value = ''
    if (unlinkedSearchTimeout) clearTimeout(unlinkedSearchTimeout)
    if (selectedBranch.value) {
        fetchUnlinkedItems(selectedBranch.value.id, '')
    }
}

const submitLink = (itemId) => {
    linkForm.item_id = itemId
    linkForm.post(route('superadmin.organizations.product-links.link', props.organization.id), {
        preserveScroll: true,
        onSuccess: () => {
            showLinkModal.value = false
        },
    })
}

// Unlink action
const confirmUnlink = (item) => {
    if (!confirm(`Are you sure you want to unlink '${item.name}' from this group? This will affect future transfers only.`)) {
        return
    }
    router.post(
        route('superadmin.organizations.product-links.unlink', props.organization.id),
        { item_id: item.item_id },
        { preserveScroll: true }
    )
}

// 3. Copy to Other Branches
const copyError = ref('')
const copyForm = useForm({
    source_item_id: null,
    target_branch_ids: [],
    include_prices: true,
})

const openCopyModal = (group, item) => {
    selectedGroup.value = group
    selectedItem.value = item
    copyError.value = ''
    copyForm.source_item_id = item.item_id
    // Pre-check all branches missing this item
    const missingBranches = props.branches.filter(b => !group.items_by_branch[b.id]).map(b => b.id)
    copyForm.target_branch_ids = missingBranches
    copyForm.include_prices = true
    showCopyModal.value = true
}

const submitCopy = () => {
    copyError.value = ''
    copyForm.post(route('superadmin.organizations.product-links.copy', props.organization.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.flash?.error) {
                copyError.value = page.props.flash.error
            } else {
                showCopyModal.value = false
                copyForm.reset()
            }
        },
        onError: (errors) => {
            copyError.value = Object.values(errors).flat().join(' ') || 'An error occurred while copying.'
        },
    })
}

// 4. Auto-Suggest Modal
const templateBranchId = ref(props.branches?.[0]?.id || null)
const suggestLoading = ref(false)
const suggestData = ref({ barcode_matches: [], name_matches: [] })
const suggestTab = ref('barcode')
const selectedBarcodeMatches = ref([])
const selectedNameMatches = ref([])
const barcodeFilterQuery = ref('')
const nameFilterQuery = ref('')
const barcodeDisplayLimit = ref(50)
const nameDisplayLimit = ref(50)
const confirmingBulk = ref(false)

const filteredBarcodeMatches = computed(() => {
    const list = suggestData.value.barcode_matches || []
    const q = barcodeFilterQuery.value.trim().toLowerCase()
    if (!q) return list
    return list.filter(m =>
        (m.template_item_name && m.template_item_name.toLowerCase().includes(q)) ||
        (m.target_item_name && m.target_item_name.toLowerCase().includes(q)) ||
        (m.template_barcode && m.template_barcode.toLowerCase().includes(q)) ||
        (m.target_branch_name && m.target_branch_name.toLowerCase().includes(q))
    )
})

const displayedBarcodeMatches = computed(() => {
    return filteredBarcodeMatches.value.slice(0, barcodeDisplayLimit.value)
})

const filteredNameMatches = computed(() => {
    const list = suggestData.value.name_matches || []
    const q = nameFilterQuery.value.trim().toLowerCase()
    if (!q) return list
    return list.filter(m =>
        (m.template_item_name && m.template_item_name.toLowerCase().includes(q)) ||
        (m.target_item_name && m.target_item_name.toLowerCase().includes(q)) ||
        (m.target_branch_name && m.target_branch_name.toLowerCase().includes(q))
    )
})

const displayedNameMatches = computed(() => {
    return filteredNameMatches.value.slice(0, nameDisplayLimit.value)
})

const openSuggestModal = () => {
    showSuggestModal.value = true
    barcodeDisplayLimit.value = 50
    nameDisplayLimit.value = 50
    barcodeFilterQuery.value = ''
    nameFilterQuery.value = ''
    if (templateBranchId.value) {
        fetchSuggestions()
    }
}

const fetchSuggestions = async () => {
    if (!templateBranchId.value) return
    suggestLoading.value = true
    barcodeDisplayLimit.value = 50
    nameDisplayLimit.value = 50
    try {
        const res = await fetch(`/superadmin/organizations/${props.organization.id}/product-links/suggest?template_branch_id=${templateBranchId.value}`)
        suggestData.value = await res.json()
        selectedBarcodeMatches.value = [...(suggestData.value.barcode_matches || [])]
        selectedNameMatches.value = []
    } catch (e) {
        suggestData.value = { barcode_matches: [], name_matches: [] }
    } finally {
        suggestLoading.value = false
    }
}

const page = usePage()
const copiedBatchId = ref(false)
const lastConfirmedBatch = ref(null)

const extractedBatchId = computed(() => {
    const flashMsg = page.props?.flash?.success || ''
    const m = flashMsg.match(/Batch:\s*([a-f0-9\-]+)/i)
    return m ? m[1] : null
})

const activeBatchCount = computed(() => {
    return (props.recentBatches || []).filter(b => !b.is_undone).length
})

const copyToClipboard = (text) => {
    if (!text) return
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text)
    } else {
        const textarea = document.createElement('textarea')
        textarea.value = text
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
    }
    copiedBatchId.value = true
    setTimeout(() => {
        copiedBatchId.value = false
    }, 2500)
}

const openUndoModalWithBatch = (batchId) => {
    undoForm.batch_id = batchId
    showUndoModal.value = true
}

const selectBatchForUndo = (b) => {
    if (b.is_undone) return
    undoForm.batch_id = b.batch_id
}

const confirmSelectedMatches = (matches) => {
    if (!matches || matches.length === 0 || confirmingBulk.value) return
    confirmingBulk.value = true
    const payload = matches.map(m => ({
        template_item_id: m.template_item_id,
        target_item_id: m.target_item_id,
        target_branch_id: m.target_branch_id,
        match_type: m.match_type,
    }))
    router.post(
        route('superadmin.organizations.product-links.bulk-confirm', props.organization.id),
        { matches: payload },
        {
            preserveScroll: true,
            onSuccess: (pageObj) => {
                const flashMsg = pageObj?.props?.flash?.success || ''
                const match = flashMsg.match(/Batch:\s*([a-f0-9\-]+)/i)
                const batchId = match ? match[1] : (pageObj?.props?.recentBatches?.[0]?.batch_id || null)
                if (batchId) {
                    lastConfirmedBatch.value = {
                        batch_id: batchId,
                        count: payload.length,
                    }
                }
                showSuggestModal.value = false
            },
            onFinish: () => {
                confirmingBulk.value = false
            },
        }
    )
}

// 5. Merge Groups Modal
const mergeForm = useForm({
    keep_group_id: null,
    merge_group_id: null,
    branch_resolutions: {},
})

const openMergeModal = () => {
    mergeForm.keep_group_id = mergeGroupOptions.value[0]?.id || null
    mergeForm.merge_group_id = mergeGroupOptions.value[1]?.id || null
    mergeForm.branch_resolutions = {}
    showMergeModal.value = true
}

const submitMerge = () => {
    mergeForm.post(route('superadmin.organizations.product-links.merge', props.organization.id), {
        onSuccess: () => {
            showMergeModal.value = false
            mergeForm.reset()
        },
    })
}

// 6. Undo Batch Modal
const undoForm = useForm({
    batch_id: '',
})

const openUndoModal = () => {
    if (!undoForm.batch_id) {
        if (lastConfirmedBatch.value?.batch_id) {
            undoForm.batch_id = lastConfirmedBatch.value.batch_id
        } else {
            const firstActive = props.recentBatches?.find(b => !b.is_undone)
            if (firstActive) {
                undoForm.batch_id = firstActive.batch_id
            }
        }
    }
    showUndoModal.value = true
}

const submitUndo = () => {
    undoForm.post(route('superadmin.organizations.product-links.undo-batch', props.organization.id), {
        onSuccess: () => {
            showUndoModal.value = false
            if (lastConfirmedBatch.value?.batch_id === undoForm.batch_id) {
                lastConfirmedBatch.value = null
            }
            undoForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="`Product Links — ${organization.name}`" />

    <div class="space-y-6 pb-12">
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                    <Link :href="route('superadmin.organizations.index')" class="hover:text-indigo-600 transition">Organizations</Link>
                    <span>/</span>
                    <Link :href="route('superadmin.organizations.show', organization.id)" class="hover:text-indigo-600 transition">{{ organization.name }}</Link>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Product Links (Inter-Branch)</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                    Product Links Catalog Grid
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ totalGroups }} Product Groups
                    </span>
                    <span v-if="conflictCount > 0" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-300 flex items-center gap-1 animate-pulse">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        {{ conflictCount }} Unit Mismatch Warning{{ conflictCount > 1 ? 's' : '' }}
                    </span>
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Shared product groups linking each branch's independent catalog rows for warehouse-style inter-branch transfers.
                </p>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    @click="openSuggestModal"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold rounded-lg bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 active:scale-95 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Auto-Suggest Matches
                </button>

                <button
                    @click="showCreateGroupModal = true"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white shadow-sm hover:bg-indigo-700 active:scale-95 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Group
                </button>

                <div class="relative inline-block text-left">
                    <button
                        @click="openMergeModal"
                        title="Merge duplicate product groups"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition"
                    >
                        Merge Groups
                    </button>
                </div>

                <button
                    @click="openUndoModal"
                    title="Undo a bulk auto-linking batch"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-white border border-slate-300 text-slate-600 hover:text-red-600 hover:border-red-300 transition"
                >
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <span>Undo Batch</span>
                    <span v-if="activeBatchCount > 0" class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-700">
                        {{ activeBatchCount }}
                    </span>
                </button>

                <Link
                    :href="route('superadmin.organizations.show', organization.id)"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                >
                    Back to Org
                </Link>
            </div>
        </div>

        <!-- Flash Messages Banner -->
        <div v-if="$page.props.flash?.success" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm shadow-xs">
            <div class="flex items-center gap-2 flex-wrap">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="font-medium">{{ $page.props.flash.success }}</span>
                <template v-if="extractedBatchId">
                    <button
                        type="button"
                        @click="copyToClipboard(extractedBatchId)"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-900 transition"
                    >
                        <span>{{ copiedBatchId ? 'Copied UUID!' : 'Copy Batch UUID' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="openUndoModalWithBatch(extractedBatchId)"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white transition shadow-2xs"
                    >
                        Undo This Batch
                    </button>
                </template>
            </div>
            <button @click="$page.props.flash.success = null" class="text-emerald-600 hover:text-emerald-800 self-end sm:self-center">✕</button>
        </div>

        <!-- Last Confirmed Batch Reminder Card -->
        <div
            v-if="lastConfirmedBatch && !$page.props.flash?.success"
            class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm shadow-xs"
        >
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-lg">🎉</span>
                <span>Auto-suggest bulk link confirmed ({{ lastConfirmedBatch.count }} item{{ lastConfirmedBatch.count === 1 ? '' : 's' }} linked). Batch:</span>
                <code class="px-2 py-0.5 rounded bg-white border border-emerald-300 font-mono text-xs font-bold text-slate-900">
                    {{ lastConfirmedBatch.batch_id }}
                </code>
                <button
                    type="button"
                    @click="copyToClipboard(lastConfirmedBatch.batch_id)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-900 transition"
                >
                    <span>{{ copiedBatchId ? 'Copied UUID!' : 'Copy Batch UUID' }}</span>
                </button>
                <button
                    type="button"
                    @click="openUndoModalWithBatch(lastConfirmedBatch.batch_id)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white transition shadow-2xs"
                >
                    Undo This Batch
                </button>
            </div>
            <button @click="lastConfirmedBatch = null" class="text-emerald-600 hover:text-emerald-800 self-end sm:self-center">✕</button>
        </div>

        <div v-if="$page.props.flash?.warning" class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-between text-sm shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span class="font-medium">{{ $page.props.flash.warning }}</span>
            </div>
            <button @click="$page.props.flash.warning = null" class="text-amber-600 hover:text-amber-800">✕</button>
        </div>

        <div v-if="$page.props.flash?.error" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between text-sm shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span class="font-medium">{{ $page.props.flash.error }}</span>
            </div>
            <button @click="$page.props.flash.error = null" class="text-rose-600 hover:text-rose-800">✕</button>
        </div>

        <!-- Filter & Search Section -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 divide-y divide-slate-100">
            <!-- Main Filter Toolbar -->
            <div class="p-4 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3">
                <!-- Search Box with Guaranteed Width -->
                <div class="relative flex-1 min-w-[240px] md:min-w-[320px]">
                    <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search by group name, item name, or barcode..."
                        class="w-full pl-9 pr-9 py-2 text-sm rounded-lg border border-slate-300 text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-2xs"
                    />
                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''; applyFilters()"
                        class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 p-0.5 rounded transition"
                        title="Clear search"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Select Filters & Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <select
                        v-model="statusFilter"
                        @change="applyFilters"
                        class="text-sm rounded-lg border border-slate-300 text-slate-900 py-2 pl-3 pr-8 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shrink-0 shadow-2xs"
                    >
                        <option value="all">All Groups</option>
                        <option value="incomplete">Incomplete Links (Missing branches)</option>
                        <option value="complete">Fully Linked in All Branches</option>
                        <option value="conflicts">Unit Conflicts Only</option>
                    </select>

                    <select
                        v-model="perPage"
                        @change="applyFilters"
                        class="text-sm rounded-lg border border-slate-300 text-slate-900 py-2 pl-3 pr-8 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shrink-0 shadow-2xs"
                        title="Groups per page"
                    >
                        <option :value="15">15 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                        <option :value="100">100 / page</option>
                    </select>

                    <button
                        @click="applyFilters"
                        class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs active:scale-95 transition shrink-0"
                    >
                        Filter
                    </button>
                    <button
                        v-if="search || statusFilter !== 'all' || perPage !== 25"
                        @click="clearFilters"
                        class="text-sm font-medium text-slate-500 hover:text-slate-800 px-2 shrink-0 transition"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Dedicated Catalog Health Sub-Bar (Accommodates 10+ branches gracefully) -->
            <div class="px-4 py-2.5 bg-slate-50/60 flex flex-wrap items-center gap-2 text-xs">
                <div class="flex items-center gap-1.5 font-semibold text-slate-700 shrink-0 mr-1">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Catalog Health:</span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 overflow-x-auto max-w-full">
                    <span
                        v-for="b in branches"
                        :key="b.id"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white text-slate-700 border border-slate-200 shadow-2xs transition hover:border-slate-300"
                        :title="`${b.name}: ${unlinkedStats[b.id] ?? 0} items unlinked`"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="(unlinkedStats[b.id] ?? 0) > 0 ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                        <span class="font-medium max-w-[150px] truncate">{{ b.name }}</span>
                        <span
                            class="px-1.5 py-0.5 rounded text-[10px] font-semibold"
                            :class="(unlinkedStats[b.id] ?? 0) > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                        >
                            {{ unlinkedStats[b.id] ?? 0 }} unlinked
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Matrix Grid Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="py-3 px-4 font-semibold text-slate-700 w-64 min-w-[240px] sticky left-0 bg-slate-50 z-10 shadow-[1px_0_0_0_#e2e8f0]">
                                Shared Product Group
                            </th>
                            <th
                                v-for="branch in branches"
                                :key="branch.id"
                                class="py-3 px-4 font-semibold text-slate-700 min-w-[260px]"
                            >
                                <div class="flex items-center justify-between">
                                    <span>{{ branch.name }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 font-normal">
                                        {{ unlinkedStats[branch.id] ?? 0 }} unlinked
                                    </span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="groupList.length === 0">
                            <td :colspan="branches.length + 1" class="py-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <p class="font-medium text-slate-700">No product groups found.</p>
                                <p class="text-xs text-slate-500 mt-1">Click "Auto-Suggest Matches" or "New Group" above to start linking products across branches.</p>
                            </td>
                        </tr>

                        <tr
                            v-for="group in groupList"
                            :key="group.id"
                            class="hover:bg-slate-50/70 transition"
                        >
                            <!-- Group Row Header -->
                            <td class="py-4 px-4 sticky left-0 bg-white group-hover:bg-slate-50/70 z-10 shadow-[1px_0_0_0_#e2e8f0]">
                                <div class="space-y-1">
                                    <div class="font-semibold text-slate-900 flex items-center justify-between group/header gap-1.5">
                                        <span class="truncate" :title="group.name">{{ group.name }}</span>
                                        <button
                                            type="button"
                                            @click="openEditGroupModal(group)"
                                            class="opacity-0 group-hover/header:opacity-100 hover:opacity-100 p-1 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-md transition cursor-pointer flex-shrink-0"
                                            title="Edit product group name"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full" :class="group.is_complete ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'">
                                            {{ group.linked_branches }} / {{ group.total_branches }} branches
                                        </span>
                                        <span
                                            v-if="group.has_unit_conflict"
                                            class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-300 flex items-center gap-1"
                                            :title="`Conflicting base units: ${group.conflicting_units.join(' vs ')}. Transfers will be blocked until units align.`"
                                        >
                                            ⚠️ Unit Mismatch
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Branch Columns -->
                            <td
                                v-for="branch in branches"
                                :key="branch.id"
                                class="py-3 px-3 align-top"
                            >
                                <!-- Cell: Item Linked -->
                                <div
                                    v-if="group.items_by_branch[branch.id]"
                                    class="p-3 rounded-lg border bg-slate-50/60 transition relative group/cell"
                                    :class="group.has_unit_conflict ? 'border-amber-200 bg-amber-50/20' : 'border-slate-200 hover:border-indigo-300 hover:shadow-sm'"
                                >
                                    <div class="flex items-start justify-between gap-1">
                                        <div class="font-medium text-slate-900 text-xs leading-snug line-clamp-2">
                                            {{ group.items_by_branch[branch.id].name }}
                                        </div>
                                        <button
                                            @click="confirmUnlink(group.items_by_branch[branch.id])"
                                            class="opacity-0 group-hover/cell:opacity-100 text-slate-400 hover:text-red-600 transition p-0.5 rounded"
                                            title="Unlink from group"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                        <span class="font-mono text-[10px] bg-white px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ group.items_by_branch[branch.id].barcode || 'No barcode' }}
                                        </span>
                                        <span
                                            class="font-semibold px-1.5 py-0.5 rounded"
                                            :class="group.has_unit_conflict ? 'bg-amber-100 text-amber-800' : 'bg-slate-200/70 text-slate-700'"
                                        >
                                            {{ group.items_by_branch[branch.id].unit_label }}
                                        </span>
                                    </div>

                                    <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                                        <span class="text-slate-500">
                                            Stock: <strong class="text-slate-800">{{ group.items_by_branch[branch.id].stock_total }}</strong>
                                        </span>
                                        <span class="text-slate-500">
                                            Sell: <strong class="text-emerald-700">${{ group.items_by_branch[branch.id].price }}</strong>
                                        </span>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between pt-1">
                                        <button
                                            @click="openCopyModal(group, group.items_by_branch[branch.id])"
                                            class="text-[10px] font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5"
                                            title="Copy this product with prices to other branches"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                                            Copy to others
                                        </button>
                                        <span class="text-[9px] uppercase tracking-wider text-slate-400">
                                            {{ group.items_by_branch[branch.id].link_source }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Cell: Unlinked / Empty -->
                                <div
                                    v-else
                                    class="p-3 rounded-lg border border-dashed border-slate-200 bg-slate-50/30 flex flex-col items-center justify-center min-h-[110px] text-center hover:bg-slate-50/80 transition"
                                >
                                    <span class="text-xs text-slate-400 mb-2">Not stocked / linked</span>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openLinkModal(group, branch)"
                                            class="px-2.5 py-1 text-xs font-semibold rounded bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 shadow-xs transition"
                                        >
                                            + Link
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <PaginationLinks
                v-if="paginationLinks.length > 3"
                :links="paginationLinks"
                :meta="paginationMeta"
            />
        </div>

        <!-- ── MODAL: Edit Product Group Name ─────────────────────────────────── -->
        <div v-if="showEditGroupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Edit Product Group Name</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Rename this shared product group across all branches</p>
                    </div>
                    <button @click="showEditGroupModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <form @submit.prevent="submitEditGroup" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Group Name</label>
                        <input
                            v-model="editGroupForm.name"
                            type="text"
                            placeholder="e.g. Peak Evaporated Milk 160g"
                            required
                            autofocus
                            class="w-full text-sm rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <div v-if="editGroupForm.errors.name" class="text-xs text-red-500 mt-1">{{ editGroupForm.errors.name }}</div>
                        <p class="text-xs text-slate-500 mt-1">This shared product name is used for inter-branch linking and stock transfers.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button
                            type="button"
                            @click="showEditGroupModal = false"
                            class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editGroupForm.processing || !editGroupForm.name.trim()"
                            class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 shadow-xs transition"
                        >
                            {{ editGroupForm.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL 1: Create Product Group ────────────────────────────────────── -->
        <div v-if="showCreateGroupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">Create New Product Group</h3>
                    <button @click="showCreateGroupModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <form @submit.prevent="submitCreateGroup" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Group Name</label>
                        <input
                            v-model="createGroupForm.name"
                            type="text"
                            placeholder="e.g. Peak Evaporated Milk 160g"
                            required
                            class="w-full text-sm rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p class="text-xs text-slate-500 mt-1">This represents the real-world product name across all branches.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="showCreateGroupModal = false" class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                        <button type="submit" :disabled="createGroupForm.processing" class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">
                            Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL 2: Link Item to Group ──────────────────────────────────────── -->
        <div v-if="showLinkModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Link Item in {{ selectedBranch?.name }}</h3>
                        <p class="text-xs text-slate-500">Group: <strong class="text-slate-800">{{ selectedGroup?.name }}</strong></p>
                    </div>
                    <button @click="showLinkModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <!-- Search Input with Icon and Clear Button -->
                <div class="space-y-1.5">
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="itemSearchQuery"
                            type="text"
                            placeholder="Type to search items by name or barcode..."
                            class="w-full pl-9 pr-9 py-2 text-sm rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autofocus
                        />
                        <button
                            v-if="itemSearchQuery"
                            @click="clearItemSearch"
                            type="button"
                            class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs px-1 rounded transition"
                            title="Clear search"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Search Count / Loading Indicator -->
                    <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                        <span v-if="loadingUnlinked" class="flex items-center gap-1.5 text-indigo-600 font-medium">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Searching catalog...
                        </span>
                        <span v-else-if="itemSearchQuery">
                            Found {{ unlinkedItemsList.length }} match{{ unlinkedItemsList.length === 1 ? '' : 'es' }} (showing max 25)
                        </span>
                        <span v-else>
                            Showing first {{ unlinkedItemsList.length }} unlinked items (type above to search)
                        </span>
                    </div>
                </div>

                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 border rounded-lg">
                    <div v-if="loadingUnlinked" class="p-8 text-center text-xs text-slate-400 flex flex-col items-center justify-center gap-2">
                        <svg class="animate-spin w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Searching matching unlinked items...
                    </div>
                    <div v-else-if="unlinkedItemsList.length === 0" class="p-6 text-center text-xs text-slate-500">
                        <span v-if="itemSearchQuery">
                            No unlinked items matching "<strong>{{ itemSearchQuery }}</strong>" found in {{ selectedBranch?.name }}.
                        </span>
                        <span v-else>
                            No unlinked items found in {{ selectedBranch?.name }}. All items in this branch are already linked to product groups.
                        </span>
                    </div>
                    <div
                        v-for="item in unlinkedItemsList"
                        :key="item.id"
                        class="p-3 flex items-center justify-between hover:bg-slate-50 transition"
                    >
                        <div>
                            <div class="text-xs font-semibold text-slate-900">{{ item.item_name }}</div>
                            <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                <span>Barcode: {{ item.barcode_number || 'None' }}</span>
                                <span>Unit: <strong>{{ item.unit_label || 'pcs' }}</strong></span>
                                <span>Stock: {{ (item.front_store_qty || 0) + (item.back_store_qty || 0) }}</span>
                            </div>
                        </div>
                        <button
                            @click="submitLink(item.id)"
                            :disabled="linkForm.processing"
                            class="px-3 py-1.5 text-xs font-semibold rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition"
                        >
                            Link Item
                        </button>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="showLinkModal = false" class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100 transition">Close</button>
                </div>
            </div>
        </div>

        <!-- ── MODAL 3: Copy to Other Branches ──────────────────────────────────── -->
        <div v-if="showCopyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Create in Other Branches (Copy)</h3>
                        <p class="text-xs text-slate-500">Template item: <strong class="text-slate-800">{{ selectedItem?.name }}</strong></p>
                    </div>
                    <button @click="showCopyModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-800 space-y-1">
                    <div class="font-semibold">⚠️ "Copied once, not synced."</div>
                    <p>Copies item name, barcode, units, and initial prices into destination branches. After copying, each branch manages its catalog independently.</p>
                </div>

                <!-- Error notice in modal -->
                <div v-if="copyError || $page.props.flash?.error" class="p-3 text-xs rounded-lg bg-rose-50 text-rose-700 border border-rose-200 flex items-start gap-2">
                    <span class="font-bold shrink-0">⚠️</span>
                    <span>{{ copyError || $page.props.flash?.error }}</span>
                </div>

                <form @submit.prevent="submitCopy" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Destination Branches</label>
                        <div class="space-y-2 max-h-48 overflow-y-auto border p-3 rounded-lg">
                            <label
                                v-for="b in branches"
                                :key="b.id"
                                class="flex items-center gap-2.5 text-sm cursor-pointer hover:bg-slate-50 p-1 rounded"
                            >
                                <input
                                    type="checkbox"
                                    :value="b.id"
                                    v-model="copyForm.target_branch_ids"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>{{ b.name }}</span>
                                <span v-if="selectedGroup?.items_by_branch[b.id]" class="text-[11px] text-amber-600 ml-auto font-medium">
                                    Already linked
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-1">
                        <label class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="copyForm.include_prices"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="font-medium">Include cost and selling prices (default ON)</span>
                        </label>
                        <p class="text-xs text-slate-500 ml-6 mt-0.5">
                            If the destination branch has profit-percentage pricing enabled, only cost is copied and selling price is calculated automatically.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="showCopyModal = false" class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                        <button type="submit" :disabled="copyForm.processing || copyForm.target_branch_ids.length === 0" class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">
                            Create & Link in Branches
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL 4: Auto-Suggest Matches ────────────────────────────────────── -->
        <div v-if="showSuggestModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-3xl w-full p-6 space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-2 border-b">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Auto-Suggest Matches Across Branches</h3>
                        <p class="text-xs text-slate-500">Scan candidate items in other branches matching your template branch catalog.</p>
                    </div>
                    <button @click="showSuggestModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <!-- Template branch picker -->
                <div class="flex items-center gap-3">
                    <label class="text-xs font-semibold text-slate-700 whitespace-nowrap">Template Branch (Fullest catalog):</label>
                    <select
                        v-model="templateBranchId"
                        @change="fetchSuggestions"
                        class="text-sm rounded-lg border-slate-300 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <button
                        @click="fetchSuggestions"
                        :disabled="suggestLoading"
                        class="px-3 py-1.5 text-xs font-semibold rounded bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                    >
                        {{ suggestLoading ? 'Scanning...' : 'Rescan' }}
                    </button>
                </div>

                <!-- Tabs: Exact Barcode vs Similar Name -->
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <div class="flex gap-4 text-xs font-semibold">
                        <button
                            @click="suggestTab = 'barcode'"
                            class="pb-2 transition relative"
                            :class="suggestTab === 'barcode' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Exact Barcode Matches ({{ suggestData.barcode_matches?.length || 0 }})
                            <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">1-Click Bulk Safe</span>
                        </button>
                        <button
                            @click="suggestTab = 'name'"
                            class="pb-2 transition relative"
                            :class="suggestTab === 'name' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Similar Name Matches ({{ suggestData.name_matches?.length || 0 }})
                            <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800">Manual Review Required</span>
                        </button>
                    </div>

                    <!-- Quick search in preview -->
                    <div class="relative w-48">
                        <input
                            v-if="suggestTab === 'barcode' && (suggestData.barcode_matches?.length || 0) > 10"
                            v-model="barcodeFilterQuery"
                            type="text"
                            placeholder="Filter matches..."
                            class="w-full text-xs rounded-md border-slate-300 bg-white py-1 px-2 text-slate-800"
                        />
                        <input
                            v-else-if="suggestTab === 'name' && (suggestData.name_matches?.length || 0) > 10"
                            v-model="nameFilterQuery"
                            type="text"
                            placeholder="Filter matches..."
                            class="w-full text-xs rounded-md border-slate-300 bg-white py-1 px-2 text-slate-800"
                        />
                    </div>
                </div>

                <!-- Matches List Container -->
                <div class="flex-1 overflow-y-auto min-h-[250px] border rounded-lg p-3 divide-y divide-slate-100">
                    <div v-if="suggestLoading" class="p-8 text-center text-xs text-slate-400 flex flex-col items-center justify-center gap-2">
                        <svg class="animate-spin h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Scanning catalog databases for barcode & name correlations...</span>
                    </div>

                    <!-- TAB 1: Barcode Matches -->
                    <template v-else-if="suggestTab === 'barcode'">
                        <div v-if="(suggestData.barcode_matches?.length || 0) === 0" class="p-8 text-center text-xs text-slate-500">
                            No unlinked items with identical barcodes found in other branches.
                        </div>
                        <div v-else-if="displayedBarcodeMatches.length === 0" class="p-8 text-center text-xs text-slate-500">
                            No matches match filter "{{ barcodeFilterQuery }}".
                        </div>
                        <template v-else>
                            <div
                                v-for="(m, idx) in displayedBarcodeMatches"
                                :key="idx"
                                class="py-2.5 flex items-center justify-between text-xs"
                            >
                                <div class="space-y-0.5">
                                    <div class="font-semibold text-slate-900">{{ m.template_item_name }}</div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                        <span class="font-mono bg-slate-100 px-1 rounded">{{ m.template_barcode }}</span>
                                        <span>Branch: <strong>{{ m.target_branch_name }}</strong></span>
                                        <span>Match: <strong class="text-slate-800">{{ m.target_item_name }}</strong></span>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                    100% Barcode Match
                                </span>
                            </div>

                            <div v-if="filteredBarcodeMatches.length > displayedBarcodeMatches.length" class="pt-3 text-center">
                                <button
                                    type="button"
                                    @click="barcodeDisplayLimit += 50"
                                    class="text-xs font-semibold text-indigo-600 hover:underline"
                                >
                                    Showing {{ displayedBarcodeMatches.length }} of {{ filteredBarcodeMatches.length }} matches. Click to load 50 more...
                                </button>
                            </div>
                        </template>
                    </template>

                    <!-- TAB 2: Name Matches -->
                    <template v-else>
                        <div v-if="(suggestData.name_matches?.length || 0) === 0" class="p-8 text-center text-xs text-slate-500">
                            No high-confidence name similarities found.
                        </div>
                        <div v-else-if="displayedNameMatches.length === 0" class="p-8 text-center text-xs text-slate-500">
                            No matches match filter "{{ nameFilterQuery }}".
                        </div>
                        <template v-else>
                            <div
                                v-for="(m, idx) in displayedNameMatches"
                                :key="idx"
                                class="py-2.5 flex items-center justify-between text-xs"
                            >
                                <div class="space-y-0.5">
                                    <div class="font-semibold text-slate-900">{{ m.template_item_name }}</div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                        <span>Branch: <strong>{{ m.target_branch_name }}</strong></span>
                                        <span>Candidate: <strong class="text-slate-800">{{ m.target_item_name }}</strong></span>
                                        <span>Barcode: {{ m.target_barcode || 'None' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                                        {{ m.confidence }}% Match
                                    </span>
                                    <button
                                        @click="confirmSelectedMatches([m])"
                                        :disabled="confirmingBulk"
                                        class="px-2.5 py-1 text-xs font-medium rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        Confirm Link
                                    </button>
                                </div>
                            </div>

                            <div v-if="filteredNameMatches.length > displayedNameMatches.length" class="pt-3 text-center">
                                <button
                                    type="button"
                                    @click="nameDisplayLimit += 50"
                                    class="text-xs font-semibold text-indigo-600 hover:underline"
                                >
                                    Showing {{ displayedNameMatches.length }} of {{ filteredNameMatches.length }} matches. Click to load 50 more...
                                </button>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between pt-2 border-t">
                    <span class="text-xs text-slate-500">
                        All bulk links generate an undo batch ID.
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showSuggestModal = false" class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100 transition">Close</button>
                        <button
                            v-if="suggestTab === 'barcode' && (suggestData.barcode_matches?.length || 0) > 0"
                            type="button"
                            :disabled="confirmingBulk"
                            @click="confirmSelectedMatches(suggestData.barcode_matches)"
                            class="px-4 py-2 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 transition flex items-center gap-2"
                        >
                            <svg v-if="confirmingBulk" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ confirmingBulk ? 'Linking items...' : `Confirm All ${suggestData.barcode_matches.length} Exact Barcode Matches` }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL 5: Merge Groups ────────────────────────────────────────────── -->
        <div v-if="showMergeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">Merge Two Product Groups</h3>
                    <button @click="showMergeModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <form @submit.prevent="submitMerge" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Group to Keep (Primary)</label>
                        <select v-model="mergeForm.keep_group_id" required class="w-full text-sm rounded-lg border-slate-300">
                            <option v-for="g in mergeGroupOptions" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Group to Merge (Will be deleted)</label>
                        <select v-model="mergeForm.merge_group_id" required class="w-full text-sm rounded-lg border-slate-300">
                            <option v-for="g in mergeGroupOptions.filter(g => g.id !== mergeForm.keep_group_id)" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <button type="button" @click="showMergeModal = false" class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100">Cancel</button>
                        <button type="submit" :disabled="mergeForm.processing || !mergeForm.keep_group_id || !mergeForm.merge_group_id" class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            Merge Groups
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL 6: Undo Batch ──────────────────────────────────────────────── -->
        <div v-if="showUndoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <span>↩️</span> Undo Bulk Link Batch
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Revert all product links created in a specific auto-suggest bulk operation.
                        </p>
                    </div>
                    <button @click="showUndoModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
                </div>

                <!-- Recent Bulk Batches Selection -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            Recent Bulk Batches
                        </label>
                        <span class="text-[11px] text-slate-400">Click a batch to select</span>
                    </div>

                    <div v-if="recentBatches && recentBatches.length > 0" class="max-h-48 overflow-y-auto space-y-1.5 pr-1 border border-slate-200 rounded-lg p-2 bg-slate-50">
                        <div
                            v-for="b in recentBatches"
                            :key="b.batch_id"
                            @click="selectBatchForUndo(b)"
                            :class="[
                                'p-2.5 rounded-lg border transition text-xs flex items-center justify-between gap-3',
                                b.is_undone
                                    ? 'opacity-60 bg-slate-100 border-slate-200 cursor-not-allowed'
                                    : undoForm.batch_id === b.batch_id
                                        ? 'bg-indigo-50 border-indigo-400 shadow-2xs cursor-pointer'
                                        : 'bg-white border-slate-200 hover:border-indigo-300 cursor-pointer'
                            ]"
                        >
                            <div class="space-y-0.5 min-w-0">
                                <div class="font-mono text-[11px] font-bold text-slate-900 flex items-center gap-1.5">
                                    <span class="truncate">{{ b.batch_id }}</span>
                                    <button
                                        type="button"
                                        @click.stop="copyToClipboard(b.batch_id)"
                                        class="text-slate-400 hover:text-indigo-600 p-0.5 shrink-0"
                                        title="Copy UUID"
                                    >
                                        📋
                                    </button>
                                </div>
                                <div class="text-[10px] text-slate-500 flex items-center gap-2">
                                    <span class="font-medium text-slate-700">{{ b.linked_count }} links</span>
                                    <span>•</span>
                                    <span>{{ b.created_at_human || 'Recent' }}</span>
                                    <span>•</span>
                                    <span>{{ b.user_name }}</span>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <span
                                    v-if="b.is_undone"
                                    class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-200 text-slate-600 uppercase tracking-wider"
                                >
                                    Already Undone
                                </span>
                                <span
                                    v-else
                                    :class="[
                                        'px-2.5 py-0.5 text-[10px] font-bold rounded-full border transition uppercase tracking-wider',
                                        undoForm.batch_id === b.batch_id
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-2xs'
                                            : 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-indigo-600 hover:text-white'
                                    ]"
                                >
                                    {{ undoForm.batch_id === b.batch_id ? 'Selected' : 'Select' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-3 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg">
                        No bulk linking batches recorded yet.
                    </div>
                </div>

                <form @submit.prevent="submitUndo" class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                                Batch UUID to Revert
                            </label>
                            <Link :href="route('superadmin.logs.index')" class="text-[11px] font-medium text-indigo-600 hover:underline">
                                View in Activity Logs ↗
                            </Link>
                        </div>
                        <div class="relative">
                            <input
                                v-model="undoForm.batch_id"
                                type="text"
                                placeholder="Paste or select the batch UUID..."
                                required
                                class="w-full text-xs font-mono rounded-lg border-slate-300 bg-white text-slate-900 focus:ring-indigo-500 focus:border-indigo-500 pr-10"
                            />
                            <button
                                v-if="undoForm.batch_id"
                                type="button"
                                @click="copyToClipboard(undoForm.batch_id)"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-indigo-600 p-1"
                                title="Copy"
                            >
                                📋
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Reverting this batch will unlink only the target items linked during this batch.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <button type="button" @click="showUndoModal = false" class="px-3.5 py-2 text-sm font-medium rounded-lg text-slate-600 hover:bg-slate-100">Cancel</button>
                        <button type="submit" :disabled="undoForm.processing || !undoForm.batch_id" class="px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 transition flex items-center gap-1.5">
                            <svg v-if="undoForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ undoForm.processing ? 'Reverting...' : 'Revert Batch Links' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
