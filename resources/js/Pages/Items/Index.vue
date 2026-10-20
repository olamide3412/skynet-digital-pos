<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const page = usePage()
const props = defineProps({
    items:          Object,
    categories:     Array,
    groupAddresses: Array,
    filters:        Object,
    totalWorth:     Number,
})

const branchSlug = computed(() => {
    return page.props.current_branch?.slug
        || page.props.branch_slug
        || page.props.current_branch_slug
        || page.props.branch?.slug
        || page.props.auth?.user?.branch?.slug
        || (typeof window !== 'undefined' ? (window.__CURRENT_BRANCH_SLUG__ || sessionStorage.getItem('active_branch_slug')) : '')
        || ''
})

const { format } = useCurrency()
const search          = ref(props.filters?.search ?? '')
const selectedCat     = ref(props.filters?.category_id ?? '')
const selectedGroup   = ref(props.filters?.group_address_id ?? '')
const perPage         = ref(String(props.filters?.per_page ?? '25'))
const showWorth       = ref(false)

const printCatalogUrl = computed(() => {
    const params = { branch: branchSlug.value }
    if (selectedIds.value.length) {
        params.ids = selectedIds.value.join(',')
    } else {
        if (selectedCat.value) params.category_id = selectedCat.value
        if (search.value) params.search = search.value
    }
    return route('pos.items.print-catalog', params)
})

// ── Multi-Item Selection ──────────────────────────────────────────────────────
const selectedIds = ref([])

const isAllSelected = computed(() => {
    const data = props.items?.data || []
    return data.length > 0 && data.every(i => selectedIds.value.includes(i.id))
})

const isIndeterminate = computed(() => {
    const data = props.items?.data || []
    const count = data.filter(i => selectedIds.value.includes(i.id)).length
    return count > 0 && count < data.length
})

function toggleSelectAll() {
    const data = props.items?.data || []
    if (isAllSelected.value) {
        // Deselect all on current page
        const pageIds = data.map(i => i.id)
        selectedIds.value = selectedIds.value.filter(id => !pageIds.includes(id))
    } else {
        // Select all on current page
        const newIds = new Set([...selectedIds.value, ...data.map(i => i.id)])
        selectedIds.value = Array.from(newIds)
    }
}

function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id)
    if (idx > -1) {
        selectedIds.value.splice(idx, 1)
    } else {
        selectedIds.value.push(id)
    }
}

function clearSelection() {
    selectedIds.value = []
}

// ── Bulk Actions State & Modals ───────────────────────────────────────────────
const showBulkCategoryModal = ref(false)
const showBulkGroupModal    = ref(false)
const showBulkPriceModal    = ref(false)
const showBulkDeleteModal   = ref(false)

const bulkCategoryForm = useForm({
    action: 'update_category',
    item_ids: [],
    category_id: '',
})

const bulkGroupForm = useForm({
    action: 'update_group',
    item_ids: [],
    group_address_id: '',
})

const bulkPriceForm = useForm({
    action: 'adjust_prices',
    item_ids: [],
    price_change_type: 'increase_percent',
    price_change_value: 0,
})

const bulkDeleteForm = useForm({
    action: 'delete',
    item_ids: [],
})

function openBulkCategoryModal() {
    bulkCategoryForm.item_ids = [...selectedIds.value]
    bulkCategoryForm.category_id = props.categories?.[0]?.id || ''
    showBulkCategoryModal.value = true
}

function submitBulkCategory() {
    bulkCategoryForm.post(route('pos.items.bulk-action'), {
        onSuccess: () => {
            showBulkCategoryModal.value = false
            clearSelection()
        }
    })
}

function openBulkGroupModal() {
    bulkGroupForm.item_ids = [...selectedIds.value]
    bulkGroupForm.group_address_id = props.groupAddresses?.[0]?.id || ''
    showBulkGroupModal.value = true
}

function submitBulkGroup() {
    bulkGroupForm.post(route('pos.items.bulk-action'), {
        onSuccess: () => {
            showBulkGroupModal.value = false
            clearSelection()
        }
    })
}

function openBulkPriceModal() {
    bulkPriceForm.item_ids = [...selectedIds.value]
    bulkPriceForm.price_change_type = 'increase_percent'
    bulkPriceForm.price_change_value = 5
    showBulkPriceModal.value = true
}

function submitBulkPrice() {
    bulkPriceForm.post(route('pos.items.bulk-action'), {
        onSuccess: () => {
            showBulkPriceModal.value = false
            clearSelection()
        }
    })
}

function openBulkDeleteModal() {
    bulkDeleteForm.item_ids = [...selectedIds.value]
    showBulkDeleteModal.value = true
}

function submitBulkDelete() {
    bulkDeleteForm.post(route('pos.items.bulk-action'), {
        onSuccess: () => {
            showBulkDeleteModal.value = false
            clearSelection()
        }
    })
}

function bulkPrintBarcodes() {
    if (!selectedIds.value.length) return
    router.get(route('pos.items.barcodes', { branch: branchSlug.value }), { item_ids: selectedIds.value.join(',') })
}

function exportSelectedCsv() {
    if (!selectedIds.value.length) return
    window.location.href = route('pos.items.export') + '?ids=' + selectedIds.value.join(',')
}

// ── Duplicate Single Item ─────────────────────────────────────────────────────
const isDuplicating = ref(null)

function duplicateItem(item) {
    if (!confirm(`Duplicate item "${item.item_name}"? A new copy record will be created.`)) return
    isDuplicating.value = item.id
    router.post(route('pos.items.duplicate', item.id), {}, {
        onFinish: () => {
            isDuplicating.value = null
        }
    })
}

// ── Single Item Delete ────────────────────────────────────────────────────────
const selectedLinkedItemsCount = computed(() => {
    const data = props.items?.data || []
    return data.filter(i => selectedIds.value.includes(i.id) && i.is_linked_to_ecommerce).length
})

function destroy(id) {
    const item = (props.items?.data || []).find(i => i.id === id)
    if (item?.is_linked_to_ecommerce) {
        alert(`Cannot delete item "${item.item_name}". It is currently linked to an active product in your online Ecommerce store. Please unlink it from Ecommerce first.`)
        return
    }
    if (confirm('Delete this item?')) {
        router.delete(route('pos.items.destroy', id), {
            onSuccess: () => {
                selectedIds.value = selectedIds.value.filter(i => i !== id)
            }
        })
    }
}

// ── Filter & Search ───────────────────────────────────────────────────────────
function doFilter() {
    router.get(route('pos.items.index', { branch: branchSlug.value }), {
        search: search.value || undefined,
        category_id: selectedCat.value || undefined,
        group_address_id: selectedGroup.value || undefined,
        per_page: perPage.value !== '25' ? perPage.value : undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    search.value = ''
    selectedCat.value = ''
    selectedGroup.value = ''
    perPage.value = '25'
    doFilter()
}

// ── Import CSV State ──────────────────────────────────────────────────────────
const showImportModal = ref(false)
const importType      = ref('native')
const importForm      = useForm({ csv_file: null })

const importReport = computed(() => page.props.flash?.importReport || null)

function submitImport() {
    if (!importForm.csv_file) return
    const routeName = importType.value === 'medfusion' ? 'pos.items.import-medfusion' : 'pos.items.import'

    importForm.post(route(routeName), {
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
        },
    })
}

function expiryClass(date) {
    if (!date) return ''
    const diff = (new Date(date) - new Date()) / (1000 * 60 * 60 * 24)
    if (diff < 0)  return 'text-red-500 font-bold'
    if (diff < 30) return 'text-amber-500 font-semibold'
    return 'text-emerald-500'
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors relative">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0 gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Items Catalog</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium">
                        {{ items.total ?? items.data?.length ?? 0 }} total
                    </span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage products, pricing, bulk operations, and inventory records</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <!-- Download Sample Template -->
                <a
                    :href="route('pos.items.export-template', { branch: branchSlug })"
                    download
                    class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                    title="Download CSV Template matching current item schema"
                >
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Template
                </a>

                <!-- Export CSV (All or filtered) -->
                <a
                    :href="route('pos.items.export', { branch: branchSlug })"
                    class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                    title="Export current branch items to CSV"
                >
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export CSV
                </a>

                <!-- Barcode Studio -->
                <Link
                    :href="route('pos.items.barcodes', { branch: branchSlug })"
                    class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                    title="Barcode Generator & Printing Studio"
                >
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    Barcodes
                </Link>

                <!-- Print Price List / Catalog -->
                <a
                    :href="printCatalogUrl"
                    target="_blank"
                    class="px-3 py-2 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-indigo-200 dark:border-indigo-800 shadow-2xs"
                    title="Print Beautiful Offline Price List & Catalog for Customers"
                >
                    <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Print Price List</span>
                    <span v-if="selectedIds.length" class="ml-1 px-1.5 py-0.2 bg-indigo-600 text-white text-[10px] rounded-full">
                        {{ selectedIds.length }}
                    </span>
                </a>

                <!-- Import CSV Modal Trigger -->
                <button
                    @click="showImportModal = true"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import CSV
                </button>

                <!-- New Item Button -->
                <Link :href="route('pos.items.create', { branch: branchSlug })"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs rounded-lg transition font-bold flex items-center gap-1.5 shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Item
                </Link>
            </div>
        </div>

        <!-- Import Results Progress Report Card (If Just Imported) -->
        <div v-if="importReport" class="px-6 pt-4 flex-shrink-0">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm">Import Results & Progress Report</h3>
                    </div>
                    <span class="text-xs text-slate-400">Total Rows Processed: {{ importReport.total }}</span>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center text-xs">
                    <div class="bg-slate-100 dark:bg-slate-700/50 p-2.5 rounded-lg border border-slate-200 dark:border-slate-600">
                        <span class="text-slate-500 dark:text-slate-400 block font-medium">Total Rows</span>
                        <span class="text-base font-bold text-slate-900 dark:text-white">{{ importReport.total }}</span>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-500/10 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-500/20">
                        <span class="text-emerald-700 dark:text-emerald-400 block font-medium">Successfully Uploaded</span>
                        <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ importReport.success }}</span>
                    </div>
                    <div class="bg-red-50 dark:bg-red-500/10 p-2.5 rounded-lg border border-red-200 dark:border-red-500/20">
                        <span class="text-red-700 dark:text-red-400 block font-medium">Failed / Skipped</span>
                        <span class="text-base font-bold text-red-600 dark:text-red-400">{{ importReport.failed.length }}</span>
                    </div>
                </div>

                <!-- Failed Items List Table -->
                <div v-if="importReport.failed.length" class="mt-2">
                    <p class="text-xs font-bold text-red-600 dark:text-red-400 mb-1">Failed Items Breakdown:</p>
                    <div class="max-h-36 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                <tr>
                                    <th class="px-3 py-1.5">Line #</th>
                                    <th class="px-3 py-1.5">Item Name</th>
                                    <th class="px-3 py-1.5">Reason for Failure</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="fail in importReport.failed" :key="fail.line" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-3 py-1.5 font-mono text-slate-500">Row {{ fail.line }}</td>
                                    <td class="px-3 py-1.5 font-medium text-slate-900 dark:text-slate-100">{{ fail.item_name }}</td>
                                    <td class="px-3 py-1.5 text-red-500 font-mono">{{ fail.reason }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + Filters + Stock Worth Banner -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 flex-shrink-0 space-y-3 bg-white dark:bg-slate-800">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Search -->
                <div class="flex-1 min-w-[240px] relative">
                    <input
                        v-model="search"
                        @keydown.enter="doFilter"
                        type="text"
                        placeholder="Search by item name or barcode…"
                        class="w-full bg-slate-100 dark:bg-slate-700/70 text-slate-900 dark:text-white placeholder-slate-400 pl-9 pr-3 py-2 rounded-xl text-xs outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <!-- Category Filter -->
                <select
                    v-model="selectedCat"
                    @change="doFilter"
                    class="bg-slate-100 dark:bg-slate-700/70 text-slate-900 dark:text-white text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 outline-none focus:border-emerald-500 min-w-[150px]"
                >
                    <option value="">All Categories</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>

                <!-- Storage Location / Group Address Filter -->
                <select
                    v-if="groupAddresses?.length"
                    v-model="selectedGroup"
                    @change="doFilter"
                    class="bg-slate-100 dark:bg-slate-700/70 text-slate-900 dark:text-white text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 outline-none focus:border-emerald-500 min-w-[150px]"
                >
                    <option value="">All Shelf Locations</option>
                    <option v-for="g in groupAddresses" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>

                <!-- Rows Per Page Filter -->
                <select
                    v-model="perPage"
                    @change="doFilter"
                    class="bg-slate-100 dark:bg-slate-700/70 text-slate-900 dark:text-white text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 outline-none focus:border-emerald-500 min-w-[130px]"
                    title="Rows to load per page"
                >
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                    <option value="all">All items</option>
                </select>

                <!-- Action button -->
                <button
                    @click="doFilter"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl transition cursor-pointer"
                >
                    Filter
                </button>

                <!-- Reset button if filters active -->
                <button
                    v-if="search || selectedCat || selectedGroup || perPage !== '25'"
                    @click="resetFilters"
                    class="px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition cursor-pointer"
                    title="Clear filters"
                >
                    Reset
                </button>
            </div>

            <!-- Total Stock Worth card -->
            <div class="flex items-center gap-3 bg-slate-100/80 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 shadow-xs">
                <div class="w-8 h-8 rounded-lg bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Total Stock Worth (Cost Price)</p>
                    <p class="text-base font-bold font-mono tracking-wide" :class="showWorth ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500'">
                        <span v-if="showWorth">{{ format(totalWorth) }}</span>
                        <span v-else class="tracking-widest select-none">&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;</span>
                    </p>
                </div>
                <button @click="showWorth = !showWorth"
                    :title="showWorth ? 'Hide worth' : 'Show worth'"
                    class="w-7 h-7 rounded-lg flex items-center justify-center transition cursor-pointer"
                    :class="showWorth ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 hover:bg-amber-500/30' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-600 hover:text-slate-900 dark:hover:text-white'">
                    <svg v-if="showWorth" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Floating Multi-Select Bulk Actions Toolbar (Properly styled for Light & Dark Mode) -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform -translate-y-4 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform -translate-y-4 opacity-0"
        >
            <div
                v-if="selectedIds.length > 0"
                class="px-6 py-2.5 bg-indigo-50/90 dark:bg-slate-800 text-slate-800 dark:text-slate-100 flex-shrink-0 shadow-sm border-b border-indigo-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3 z-30 transition-colors"
            >
                <!-- Selected count & clear -->
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-bold text-xs border border-indigo-200 dark:border-indigo-700/50">
                        {{ selectedIds.length }} {{ selectedIds.length === 1 ? 'item' : 'items' }} selected
                    </span>
                    <button
                        type="button"
                        @click="clearSelection"
                        class="text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white underline cursor-pointer"
                    >
                        Deselect All
                    </button>
                </div>

                <!-- Action buttons -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <!-- Move Category -->
                    <button
                        type="button"
                        @click="openBulkCategoryModal"
                        class="px-3 py-1.5 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold border border-slate-300 dark:border-slate-600 transition flex items-center gap-1 cursor-pointer shadow-2xs"
                        title="Change category for selected items"
                    >
                        📁 Change Category
                    </button>

                    <!-- Move Location -->
                    <button
                        v-if="groupAddresses?.length"
                        type="button"
                        @click="openBulkGroupModal"
                        class="px-3 py-1.5 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold border border-slate-300 dark:border-slate-600 transition flex items-center gap-1 cursor-pointer shadow-2xs"
                        title="Change storage shelf location"
                    >
                        📍 Change Shelf Location
                    </button>

                    <!-- Adjust Prices -->
                    <button
                        type="button"
                        @click="openBulkPriceModal"
                        class="px-3 py-1.5 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold border border-slate-300 dark:border-slate-600 transition flex items-center gap-1 cursor-pointer shadow-2xs"
                        title="Bulk increase or decrease prices"
                    >
                        💰 Adjust Prices
                    </button>

                    <!-- Barcode Studio -->
                    <button
                        type="button"
                        @click="bulkPrintBarcodes"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1 cursor-pointer shadow-xs"
                        title="Send selected to barcode label studio"
                    >
                        🏷️ Print Barcodes
                    </button>

                    <!-- Export Selected CSV -->
                    <button
                        type="button"
                        @click="exportSelectedCsv"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1 cursor-pointer shadow-xs"
                        title="Export selected items to CSV"
                    >
                        📥 Export Selected
                    </button>

                    <!-- Bulk Delete -->
                    <button
                        type="button"
                        @click="openBulkDeleteModal"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1 cursor-pointer shadow-xs"
                        title="Delete selected items"
                    >
                        🗑️ Delete Selected
                    </button>
                </div>
            </div>
        </transition>

        <!-- Items Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <div class="overflow-x-auto min-w-full">
                    <table class="w-full text-xs min-w-[680px] lg:min-w-full">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <!-- Multi-Select Checkbox -->
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        :indeterminate.prop="isIndeterminate"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500 bg-white dark:bg-slate-900 cursor-pointer"
                                        title="Select all items on this page"
                                    />
                                </th>
                                <th class="text-left px-4 py-3 min-w-[160px]">Item Name</th>
                                <th class="text-left px-4 py-3 hidden xl:table-cell">Barcode</th>
                                <th class="text-left px-4 py-3 hidden md:table-cell">Category</th>
                                <th class="text-left px-4 py-3 hidden 2xl:table-cell">Location</th>
                                <th class="text-right px-4 py-3 whitespace-nowrap">Price</th>
                                <th class="text-right px-4 py-3 hidden lg:table-cell whitespace-nowrap">Cost Price</th>
                                <th class="text-right px-4 py-3 whitespace-nowrap">Qty</th>
                                <th class="text-right px-4 py-3 hidden xl:table-cell whitespace-nowrap">Stock Worth</th>
                                <th class="text-left px-4 py-3 hidden 2xl:table-cell whitespace-nowrap">Expiry</th>
                                <th class="text-right px-4 py-3 sticky right-0 z-10 bg-slate-50 dark:bg-slate-700 shadow-[-6px_0_12px_-4px_rgba(0,0,0,0.08)] dark:shadow-[-6px_0_12px_-4px_rgba(0,0,0,0.3)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr
                                v-for="item in items.data"
                                :key="item.id"
                                class="hover:bg-slate-100/70 dark:hover:bg-slate-700/60 transition group"
                                :class="selectedIds.includes(item.id) ? 'bg-indigo-50/50 dark:bg-indigo-900/20' : ''"
                            >
                                <!-- Checkbox -->
                                <td class="px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(item.id)"
                                        @change="toggleSelect(item.id)"
                                        class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500 bg-white dark:bg-slate-900 cursor-pointer"
                                    />
                                </td>

                                <!-- Item Name & Thumbnail -->
                                <td class="px-4 py-3 text-slate-900 dark:text-white font-medium min-w-[160px]">
                                    <div class="flex items-center gap-2.5">
                                        <div v-if="item.image_url" class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700 overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-600 flex-shrink-0">
                                            <img :src="item.image_url" class="object-cover w-full h-full" alt="Item Thumbnail" />
                                        </div>
                                        <div v-else class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center border border-slate-200 dark:border-slate-700 flex-shrink-0 text-slate-400 dark:text-slate-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-bold">{{ item.item_name }}</span>
                                                <span
                                                    v-if="item.is_linked_to_ecommerce"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                                    title="Linked to active Online Ecommerce Store product. Deletion is locked."
                                                >
                                                    <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                    </svg>
                                                    Online Store
                                                </span>
                                            </div>
                                            <span v-if="item.unit_label" class="text-[10px] text-slate-400">{{ item.unit_label }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Barcode -->
                                <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-300 hidden xl:table-cell">
                                    <span v-if="item.barcode_number" class="bg-slate-100 dark:bg-slate-700/60 px-1.5 py-0.5 rounded text-[11px]">
                                        {{ item.barcode_number }}
                                    </span>
                                    <span v-else class="text-slate-400 text-[11px] italic">No barcode</span>
                                </td>

                                <!-- Category -->
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 hidden md:table-cell">
                                    <span v-if="item.category?.name" class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium text-[11px]">
                                        {{ item.category.name }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>

                                <!-- Storage / Shelf Location -->
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 hidden 2xl:table-cell">
                                    <span v-if="item.group_address?.name" class="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        <span>📍</span> {{ item.group_address.name }}
                                    </span>
                                    <span v-else class="text-slate-400 text-[11px]">—</span>
                                </td>

                                <!-- Selling Price -->
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 font-bold whitespace-nowrap">
                                    {{ format(item.price) }}
                                </td>

                                <!-- Buy/Cost Price -->
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400 font-mono hidden lg:table-cell whitespace-nowrap">
                                    {{ format(item.buy_price || 0) }}
                                </td>

                                <!-- Qty -->
                                <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                    <span :class="(item.total_qty ?? item.front_store_qty ?? 0) <= 5 ? 'text-red-500 dark:text-red-400 font-bold' : 'text-slate-900 dark:text-white'">
                                        {{ item.total_qty ?? item.front_store_qty ?? 0 }}
                                    </span>
                                </td>

                                <!-- Stock Worth -->
                                <td class="px-4 py-3 text-right font-mono hidden xl:table-cell whitespace-nowrap">
                                    <span v-if="showWorth" class="text-amber-600 dark:text-amber-400 font-semibold">{{ format(item.stock_worth) }}</span>
                                    <span v-else class="text-slate-400 dark:text-slate-600 tracking-widest select-none">&#9679;&#9679;&#9679;&#9679;</span>
                                </td>

                                <!-- Expiry Date -->
                                <td class="px-4 py-3 font-medium hidden 2xl:table-cell whitespace-nowrap" :class="expiryClass(item.expiry_date)">
                                    {{ item.expiry_date ? dayjs(item.expiry_date).format('DD-MMM-YYYY') : '—' }}
                                </td>

                                <!-- Actions (Edit, Clone & Edit, Delete) - Sticky Right Column -->
                                <td
                                    class="px-4 py-3 text-right whitespace-nowrap sticky right-0 z-10 bg-white dark:bg-slate-800 group-hover:bg-slate-100/70 dark:group-hover:bg-slate-700/60 shadow-[-6px_0_12px_-4px_rgba(0,0,0,0.08)] dark:shadow-[-6px_0_12px_-4px_rgba(0,0,0,0.3)]"
                                    :class="selectedIds.includes(item.id) ? 'bg-indigo-50 dark:bg-indigo-950/80' : ''"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <!-- Clone & Edit (Create form with prefill) -->
                                        <Link
                                            :href="route('pos.items.create', { branch: branchSlug, clone_from: item.id })"
                                            class="p-1.5 text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition"
                                            title="Duplicate & Edit (Open form prefilled with this item)"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </Link>

                                        <!-- Edit -->
                                        <Link
                                            :href="route('pos.items.edit', { branch: branchSlug, item: item.id })"
                                            class="px-2.5 py-1 text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg font-semibold transition"
                                        >
                                            Edit
                                        </Link>

                                        <!-- Delete or Padlocked -->
                                        <span
                                            v-if="item.is_linked_to_ecommerce"
                                            class="p-1.5 text-amber-500/80 dark:text-amber-400/80 cursor-not-allowed inline-flex items-center"
                                            title="Cannot delete: Linked to active Online Ecommerce product"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                        </span>
                                        <button
                                            v-else
                                            type="button"
                                            @click="destroy(item.id)"
                                            class="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition cursor-pointer"
                                            title="Delete Item"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!items.data?.length">
                                <td colspan="11" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                                    <div class="text-3xl mb-2">📦</div>
                                    <div class="font-bold">No items found</div>
                                    <div class="text-xs text-slate-400 mt-1">Try adjusting your search or filters, or add a new item.</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center gap-3">
                        <span>Showing {{ items.from ?? (items.data?.length ? 1 : 0) }}–{{ items.to ?? items.data?.length ?? 0 }} of {{ items.total ?? items.data?.length ?? 0 }} items</span>
                        <div class="flex items-center gap-1.5 pl-3 border-l border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 font-medium">Rows:</span>
                            <select
                                v-model="perPage"
                                @change="doFilter"
                                class="bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-600 outline-none focus:border-emerald-500 cursor-pointer"
                            >
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="items.last_page > 1" class="flex gap-1">
                        <Link v-if="items.prev_page_url" :href="items.prev_page_url"
                            class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition">Prev</Link>
                        <Link v-if="items.next_page_url" :href="items.next_page_url"
                            class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- BULK MODALS                                                         -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->

        <!-- 1. Bulk Category Modal -->
        <div v-if="showBulkCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Move {{ selectedIds.length }} Items to Category</h3>
                    <button @click="showBulkCategoryModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">✕</button>
                </div>
                <form @submit.prevent="submitBulkCategory" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Select Target Category *</label>
                        <select
                            v-model="bulkCategoryForm.category_id"
                            class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500"
                            required
                        >
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showBulkCategoryModal = false" class="flex-1 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-semibold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="bulkCategoryForm.processing" class="flex-1 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition disabled:opacity-50 cursor-pointer">
                            {{ bulkCategoryForm.processing ? 'Updating…' : 'Move Items' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Bulk Group / Shelf Location Modal -->
        <div v-if="showBulkGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Move {{ selectedIds.length }} Items to Shelf Location</h3>
                    <button @click="showBulkGroupModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">✕</button>
                </div>
                <form @submit.prevent="submitBulkGroup" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Select Target Location / Group Address *</label>
                        <select
                            v-model="bulkGroupForm.group_address_id"
                            class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500"
                            required
                        >
                            <option v-for="g in groupAddresses" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showBulkGroupModal = false" class="flex-1 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-semibold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="bulkGroupForm.processing" class="flex-1 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition disabled:opacity-50 cursor-pointer">
                            {{ bulkGroupForm.processing ? 'Updating…' : 'Move Items' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. Bulk Adjust Prices Modal -->
        <div v-if="showBulkPriceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Adjust Prices for {{ selectedIds.length }} Items</h3>
                    <button @click="showBulkPriceModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">✕</button>
                </div>
                <form @submit.prevent="submitBulkPrice" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Adjustment Action</label>
                        <select
                            v-model="bulkPriceForm.price_change_type"
                            class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500"
                        >
                            <option value="increase_percent">Increase selling price by percentage (%)</option>
                            <option value="decrease_percent">Decrease selling price by percentage (%)</option>
                            <option value="set_fixed">Set exact fixed selling price</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            {{ bulkPriceForm.price_change_type === 'set_fixed' ? 'New Fixed Price' : 'Percentage Value (%)' }}
                        </label>
                        <input
                            v-model.number="bulkPriceForm.price_change_value"
                            type="number"
                            step="any"
                            min="0"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div class="flex gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showBulkPriceModal = false" class="flex-1 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-semibold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="bulkPriceForm.processing" class="flex-1 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition disabled:opacity-50 cursor-pointer">
                            {{ bulkPriceForm.processing ? 'Adjusting…' : 'Apply Price Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 4. Bulk Delete Confirmation Modal -->
        <div v-if="showBulkDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-red-200 dark:border-red-800">
                <div class="p-6 space-y-4 text-center">
                    <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 flex items-center justify-center mx-auto text-2xl font-bold">
                        ⚠️
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Delete {{ selectedIds.length }} Selected Items?</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">This action cannot be undone. Selected unlinked items will be permanently removed from this branch catalog.</p>
                        
                        <div v-if="selectedLinkedItemsCount > 0" class="mt-3 p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-xs text-amber-800 dark:text-amber-300 text-left flex items-start gap-2">
                            <span class="text-base">🔒</span>
                            <span><strong>{{ selectedLinkedItemsCount }} item(s)</strong> are linked to the active online store and will be skipped to protect your ecommerce store from drift.</span>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showBulkDeleteModal = false" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-semibold hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                        <button type="button" @click="submitBulkDelete" :disabled="bulkDeleteForm.processing" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold transition disabled:opacity-50 shadow-md shadow-red-600/20 cursor-pointer">
                            {{ bulkDeleteForm.processing ? 'Deleting…' : 'Yes, Delete Selected' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- CSV IMPORT MODAL                                                    -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 dark:border-slate-700 transition-colors">
                
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Import Items CSV</h3>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition font-bold cursor-pointer">✕</button>
                </div>

                <!-- Import Mode Tabs -->
                <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
                    <button
                        type="button"
                        @click="importType = 'native'"
                        class="flex-1 py-2.5 text-xs font-bold transition border-b-2 cursor-pointer"
                        :class="importType === 'native' ? 'border-blue-600 text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                    >
                        Standard Items CSV (Current Schema)
                    </button>
                    <button
                        type="button"
                        @click="importType = 'medfusion'"
                        class="flex-1 py-2.5 text-xs font-bold transition border-b-2 cursor-pointer"
                        :class="importType === 'medfusion' ? 'border-amber-600 text-amber-600 dark:text-amber-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                    >
                        Import Medfusion CSV (Legacy POS)
                    </button>
                </div>

                <form @submit.prevent="submitImport" class="px-5 py-4 space-y-4">
                    
                    <!-- Mode Description -->
                    <div v-if="importType === 'native'" class="space-y-2">
                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            Upload a CSV matching the current items table schema. Download the sample template to see required columns.
                        </p>
                        <a
                            :href="route('pos.items.export-template', { branch: branchSlug })"
                            download
                            class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                        >
                            📥 Download Items CSV Template
                        </a>
                    </div>

                    <div v-else class="space-y-2">
                        <p class="text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 p-2.5 rounded-lg border border-amber-200 dark:border-amber-500/20">
                            <strong>Import Medfusion CSV:</strong> Supports legacy old POS format (<code class="font-mono">barcode_number</code>, <code class="font-mono">item_name</code>, <code class="font-mono">category_name</code>, <code class="font-mono">qty</code>, <code class="font-mono">Buy_price</code>, <code class="font-mono">price</code>, <code class="font-mono">wholesale</code>, <code class="font-mono">expiry_date</code>). Missing categories will be created automatically.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Select CSV File</label>
                        <input
                            type="file"
                            accept=".csv, .txt"
                            @change="importForm.csv_file = $event.target.files[0]"
                            class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white text-xs px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 outline-none file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white cursor-pointer"
                            required
                        />
                        <p v-if="importForm.errors.csv_file" class="text-red-500 text-xs mt-1">{{ importForm.errors.csv_file }}</p>
                    </div>

                    <div class="flex gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showImportModal = false" class="flex-1 py-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-600 transition cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="importForm.processing || !importForm.csv_file" class="flex-1 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition disabled:opacity-40 shadow-xs cursor-pointer">
                            {{ importForm.processing ? 'Uploading & Processing…' : (importType === 'medfusion' ? 'Import Medfusion CSV' : 'Import Items CSV') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
