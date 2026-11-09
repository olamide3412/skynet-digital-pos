<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import PaginationLinks from '@/Components/PaginationLinks.vue'

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    counts: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')

const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const searchQuery = ref(props.filters.search || '')
const selectedStatus = ref(props.filters.status || '')
const filterOverdue = ref(Boolean(props.filters.overdue))
const filterDueToday = ref(Boolean(props.filters.due_today))
const filterUnclaimed = ref(Boolean(props.filters.unclaimed))
const filterUnpaid = ref(Boolean(props.filters.unpaid))

const exportMenuOpen = ref(false)
const exportDropdownRef = ref(null)

const getExportUrl = (exportFormat = 'xlsx') => {
    const params = new URLSearchParams()
    params.set('format', exportFormat)
    if (searchQuery.value) params.set('search', searchQuery.value.trim())
    if (selectedStatus.value) params.set('status', selectedStatus.value)
    if (filterOverdue.value) params.set('overdue', '1')
    if (filterDueToday.value) params.set('due_today', '1')
    if (filterUnclaimed.value) params.set('unclaimed', '1')
    if (filterUnpaid.value) params.set('unpaid', '1')
    if (props.filters?.start_date) params.set('start_date', props.filters.start_date)
    if (props.filters?.end_date) params.set('end_date', props.filters.end_date)
    return `${safeRoute('laundry.orders.export')}?${params.toString()}`
}

const downloadExport = (exportFormat) => {
    exportMenuOpen.value = false
    window.location.href = getExportUrl(exportFormat)
}

const handleClickOutside = (e) => {
    if (exportDropdownRef.value && !exportDropdownRef.value.contains(e.target)) {
        exportMenuOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const hasActiveFilters = computed(() => {
    return Boolean(
        searchQuery.value ||
        selectedStatus.value ||
        filterOverdue.value ||
        filterDueToday.value ||
        filterUnclaimed.value ||
        filterUnpaid.value
    )
})

const clearAllFilters = () => {
    searchQuery.value = ''
    selectedStatus.value = ''
    filterOverdue.value = false
    filterDueToday.value = false
    filterUnclaimed.value = false
    filterUnpaid.value = false
    applyFilters()
}

const applyFilters = () => {
    router.get(
        safeRoute('laundry.orders.index'),
        {
            search: searchQuery.value || undefined,
            status: selectedStatus.value || undefined,
            overdue: filterOverdue.value ? 1 : undefined,
            due_today: filterDueToday.value ? 1 : undefined,
            unclaimed: filterUnclaimed.value ? 1 : undefined,
            unpaid: filterUnpaid.value ? 1 : undefined,
        },
        { preserveState: true, replace: true }
    )
}

let searchTimer = null
const onSearchInput = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        applyFilters()
    }, 350)
}

const toggleFilter = (filterKey) => {
    if (filterKey === 'overdue') filterOverdue.value = !filterOverdue.value
    if (filterKey === 'due_today') filterDueToday.value = !filterDueToday.value
    if (filterKey === 'unclaimed') filterUnclaimed.value = !filterUnclaimed.value
    if (filterKey === 'unpaid') filterUnpaid.value = !filterUnpaid.value
    applyFilters()
}

const setStatusTab = (statusKey) => {
    selectedStatus.value = statusKey
    applyFilters()
}

const ordersList = computed(() => props.orders?.data || [])

const statusBadgeClass = (status) => {
    switch (status) {
        case 'received': return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800'
        case 'in_progress': return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800'
        case 'ready': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        case 'collected': return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
        case 'cancelled': return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800'
        default: return 'bg-slate-100 text-slate-700'
    }
}

const statusLabel = (status) => {
    switch (status) {
        case 'received': return 'Received'
        case 'in_progress': return 'In Progress'
        case 'ready': return 'Ready'
        case 'collected': return 'Collected'
        case 'cancelled': return 'Cancelled'
        default: return status
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 sm:gap-4 px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="min-w-0">
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>All Laundry Orders</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                        {{ counts.all || 0 }} Total
                    </span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1 sm:line-clamp-none">
                    Comprehensive searchable database of intake, active, ready, and collected laundry orders
                </p>
            </div>

            <!-- Action Buttons: Responsive Mobile-Friendly Layout -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
                <!-- Primary Action: New Intake Order (Full-width prominent button on mobile) -->
                <Link
                    :href="safeRoute('laundry.orders.create')"
                    class="order-1 sm:order-3 w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20 active:scale-[0.99] cursor-pointer whitespace-nowrap"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Intake Order</span>
                </Link>

                <!-- Secondary Actions: 2-Column Grid on Mobile, Inline on Desktop -->
                <div class="order-2 grid grid-cols-2 sm:flex sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
                    <!-- Workflow Board -->
                    <Link
                        :href="safeRoute('laundry.board.index')"
                        class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold transition flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-600 active:scale-95"
                    >
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                        </svg>
                        <span class="sm:hidden whitespace-nowrap">Workflow Board</span>
                        <span class="hidden sm:inline whitespace-nowrap">Open Workflow Board</span>
                    </Link>

                    <!-- Export Dropdown -->
                    <div class="relative w-full sm:w-auto" ref="exportDropdownRef">
                        <button
                            type="button"
                            @click="exportMenuOpen = !exportMenuOpen"
                            class="w-full sm:w-auto px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition flex items-center justify-center gap-1.5 shadow-xs cursor-pointer active:scale-95"
                            title="Export laundry orders"
                        >
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Export</span>
                            <svg class="w-3.5 h-3.5 opacity-60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="exportMenuOpen"
                            class="absolute right-0 mt-1.5 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                        >
                            <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                Export Laundry Orders
                            </div>
                            <button
                                type="button"
                                @click="downloadExport('xlsx')"
                                class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
                            >
                                <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                                    XLS
                                </span>
                                Excel (.xlsx)
                            </button>
                            <button
                                type="button"
                                @click="downloadExport('csv')"
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
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-5">
            <div class="max-w-7xl mx-auto space-y-5">

                <!-- Control Bar (Search & Urgency Filters) -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3">
                    <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">

                        <!-- Search Input -->
                        <div class="relative flex-1 max-w-md">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                type="text"
                                placeholder="Search by Order #, Customer, or Phone..."
                                class="w-full pl-10 pr-9 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''; applyFilters()"
                                type="button"
                                class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                title="Clear search"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Right Control: Link to Workflow Board (Desktop shortcut) -->
                        <div class="hidden md:flex items-center gap-2 justify-end">
                            <Link
                                :href="safeRoute('laundry.board.index')"
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition border bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 border-slate-200 dark:border-slate-700"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                                </svg>
                                <span>Switch to Workflow Board</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Urgency Filter Chips Row -->
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                        <span class="text-xs font-medium text-slate-500 mr-1">Quick Filters:</span>

                        <button
                            @click="toggleFilter('overdue')"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5',
                                filterOverdue
                                    ? 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800 shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                            ]"
                        >
                            <span>Overdue</span>
                            <span v-if="counts.overdue" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-rose-200 dark:bg-rose-900 text-rose-800 dark:text-rose-200">
                                {{ counts.overdue }}
                            </span>
                        </button>

                        <button
                            @click="toggleFilter('due_today')"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5',
                                filterDueToday
                                    ? 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800 shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                            ]"
                        >
                            <span>Due Today</span>
                            <span v-if="counts.due_today" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-indigo-200 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                                {{ counts.due_today }}
                            </span>
                        </button>

                        <button
                            @click="toggleFilter('unpaid')"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5',
                                filterUnpaid
                                    ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800 shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                            ]"
                        >
                            <span>Has Balance Due</span>
                            <span v-if="counts.unpaid" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-200 dark:bg-amber-900 text-amber-800 dark:text-amber-200">
                                {{ counts.unpaid }}
                            </span>
                        </button>

                        <button
                            @click="toggleFilter('unclaimed')"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5',
                                filterUnclaimed
                                    ? 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800 shadow-xs'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                            ]"
                        >
                            <span>Unclaimed Ready Orders</span>
                            <span v-if="counts.unclaimed" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-purple-200 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                {{ counts.unclaimed }}
                            </span>
                        </button>

                        <!-- Clear Filters Action -->
                        <button
                            v-if="hasActiveFilters"
                            @click="clearAllFilters"
                            class="ml-auto px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Clear Filters</span>
                        </button>
                    </div>
                </div>

                <!-- TABLE LIST VIEW (With Filter Status Tabs) -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                    <!-- Status Tabs -->
                    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-2 bg-slate-50/70 dark:bg-slate-900/40">
                        <span class="text-xs font-semibold text-slate-500 mr-1">Status:</span>
                        <button
                            v-for="st in [
                                { key: '', label: 'All Orders', count: counts.all },
                                { key: 'received', label: 'Received', count: counts.active ? undefined : undefined },
                                { key: 'in_progress', label: 'In Progress', count: undefined },
                                { key: 'ready', label: 'Ready for Pickup', count: counts.ready },
                                { key: 'collected', label: 'Collected History', count: counts.collected },
                                { key: 'cancelled', label: 'Cancelled', count: counts.cancelled },
                            ]"
                            :key="st.key"
                            @click="setStatusTab(st.key)"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition flex items-center gap-1.5',
                                selectedStatus === st.key
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'
                            ]"
                        >
                            <span>{{ st.label }}</span>
                            <span
                                v-if="st.count !== undefined"
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                :class="selectedStatus === st.key ? 'bg-indigo-700 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300'"
                            >
                                {{ st.count || 0 }}
                            </span>
                        </button>
                    </div>

                    <!-- Empty State -->
                    <div v-if="ordersList.length === 0" class="py-16 text-center text-slate-400">
                        <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No orders matching the active filters.</p>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearAllFilters"
                            class="mt-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Reset all filters
                        </button>
                    </div>

                    <!-- Orders Data Table with Sticky Header (Desktop View: md and up) -->
                    <div v-else class="hidden md:block overflow-auto max-h-[calc(100vh-270px)] min-h-[420px] scrollbar-thin relative">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="sticky top-0 z-20 bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-xs">
                                <tr>
                                    <th class="px-5 py-3.5 sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Order #</th>
                                    <th class="px-5 py-3.5 sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Customer</th>
                                    <th class="px-5 py-3.5 sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Items</th>
                                    <th class="px-5 py-3.5 sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Status</th>
                                    <th class="px-5 py-3.5 text-right sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Total</th>
                                    <th class="px-5 py-3.5 text-right sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Balance Due</th>
                                    <th class="px-5 py-3.5 sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Due Date</th>
                                    <th class="px-5 py-3.5 text-right sticky top-0 bg-slate-100 dark:bg-slate-900 z-20 whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <tr
                                    v-for="order in ordersList"
                                    :key="order.id"
                                    class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition"
                                >
                                    <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <Link :href="safeRoute('laundry.orders.show', { order: order.id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                {{ order.order_number }}
                                            </Link>
                                            <span v-if="order.is_express" class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-amber-500 text-white shadow-2xs">
                                                ⚡ RUSH
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-bold text-slate-900 dark:text-white">{{ order.customer_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ order.customer_phone }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 max-w-xs truncate text-slate-600 dark:text-slate-300">
                                        {{ order.items_summary || `${order.items_count} items` }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span :class="['px-2.5 py-1 rounded-full text-[11px] font-semibold border', statusBadgeClass(order.status)]">
                                            {{ statusLabel(order.status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                        {{ formatMoney(order.total) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-bold whitespace-nowrap">
                                        <span :class="order.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600'">
                                            {{ order.balance > 0 ? formatMoney(order.balance) : 'Paid' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <span>{{ order.due_at || '—' }}</span>
                                            <span v-if="order.is_overdue" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                OVERDUE
                                            </span>
                                            <span v-else-if="order.is_unclaimed" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                                UNCLAIMED
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <Link
                                            :href="safeRoute('laundry.orders.show', { order: order.id })"
                                            class="px-2.5 py-1 rounded-md text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition"
                                        >
                                            Manage →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Custom Mobile Card List View (Visible on Mobile: screens < md) -->
                    <div v-if="ordersList.length > 0" class="md:hidden p-3 space-y-3 bg-slate-50/50 dark:bg-slate-900/40">
                        <div
                            v-for="order in ordersList"
                            :key="'mob-' + order.id"
                            class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs relative overflow-hidden transition"
                        >
                            <!-- Status color stripe at top -->
                            <div
                                class="absolute top-0 left-0 right-0 h-1"
                                :class="{
                                    'bg-rose-500': order.is_overdue || order.status === 'cancelled',
                                    'bg-purple-500': order.is_unclaimed,
                                    'bg-emerald-500': order.status === 'ready' && !order.is_overdue && !order.is_unclaimed,
                                    'bg-slate-400': order.status === 'collected',
                                    'bg-amber-500': order.status === 'in_progress',
                                    'bg-blue-500': order.status === 'received',
                                }"
                            />

                            <!-- Top Row: Order Number, Status Badge, Due/Overdue Tag -->
                            <div class="flex items-start justify-between gap-2 mb-2.5 pt-0.5">
                                <div class="min-w-0">
                                    <Link
                                        :href="safeRoute('laundry.orders.show', { order: order.id })"
                                        class="font-black text-sm text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1.5"
                                    >
                                        <span>#{{ order.order_number }}</span>
                                        <span v-if="order.is_express" class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-amber-500 text-white shadow-2xs">
                                            ⚡ RUSH
                                        </span>
                                    </Link>
                                    <span class="text-[10px] text-slate-400 block mt-0.5">
                                        Received: {{ order.received_at || '—' }}
                                    </span>
                                </div>
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border', statusBadgeClass(order.status)]">
                                        {{ statusLabel(order.status) }}
                                    </span>
                                    <span v-if="order.is_overdue" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                        OVERDUE
                                    </span>
                                    <span v-else-if="order.is_unclaimed" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                        UNCLAIMED
                                    </span>
                                </div>
                            </div>

                            <!-- Customer Info Row with 1-Tap Call -->
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2 mb-2.5 text-xs">
                                <div class="min-w-0 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ (order.customer_name || 'C').charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-900 dark:text-white truncate text-xs">{{ order.customer_name }}</p>
                                        <p v-if="order.customer_phone" class="text-[11px] text-slate-400 truncate">{{ order.customer_phone }}</p>
                                    </div>
                                </div>
                                <a
                                    v-if="order.customer_phone"
                                    :href="`tel:${order.customer_phone}`"
                                    class="p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-slate-700 transition shrink-0 shadow-xs active:scale-95"
                                    title="Call Customer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </a>
                            </div>

                            <!-- Items Summary -->
                            <div class="mb-3 text-xs">
                                <div class="flex items-start gap-1.5 text-slate-600 dark:text-slate-300">
                                    <span class="text-sm shrink-0">🧺</span>
                                    <span class="font-medium line-clamp-2 text-[11px] leading-relaxed">
                                        {{ order.items_summary || `${order.items_count} items` }}
                                    </span>
                                </div>
                            </div>

                            <!-- Financials & Turnaround Grid -->
                            <div class="grid grid-cols-3 gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 mb-3 text-[11px]">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Total Order</span>
                                    <span class="font-extrabold text-slate-900 dark:text-white font-mono text-xs">
                                        {{ formatMoney(order.total) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Balance Due</span>
                                    <span
                                        :class="order.balance > 0 ? 'text-amber-600 dark:text-amber-400 font-extrabold font-mono text-xs' : 'text-emerald-600 font-bold text-xs'"
                                    >
                                        {{ order.balance > 0 ? formatMoney(order.balance) : 'Paid' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Due Date</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300 text-[10px] truncate block" :title="order.due_at || '—'">
                                        {{ order.due_at || '—' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Action Button Row -->
                            <div class="flex items-center gap-2 pt-1 border-t border-slate-100 dark:border-slate-700/60">
                                <Link
                                    :href="safeRoute('laundry.orders.show', { order: order.id })"
                                    class="flex-1 py-2 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-[0.98]"
                                >
                                    <span>Manage Order</span>
                                    <span>→</span>
                                </Link>
                                <Link
                                    :href="`${safeRoute('laundry.orders.show', { order: order.id })}?print=1`"
                                    class="py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-xs font-semibold flex items-center gap-1 transition active:scale-95 shrink-0"
                                    title="Quick Print Ticket"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    <span>Print</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <PaginationLinks
                        :links="orders.links || []"
                        :meta="orders.meta ?? orders"
                    />
                </div>

            </div>
        </div>
    </div>
</template>
