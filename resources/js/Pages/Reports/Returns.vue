<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    returns:   Object,  // paginated
    summary:   Object,
    users:     Array,
    canSeeAll: Boolean,
    filters:   Object,
})

const { format } = useCurrency()
const startDate = ref(props.filters.start_date || props.filters.from || '')
const endDate   = ref(props.filters.end_date || props.filters.to || '')
const userId    = ref(props.filters.user_id ?? '')
const search    = ref(props.filters.search ?? '')

const exportMenuOpen = ref(false)
const exportDropdownRef = ref(null)

const activePreset = ref('today')

function checkActivePreset() {
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (!startDate.value && !endDate.value) {
        activePreset.value = 'all'
    } else if (startDate.value === today && endDate.value === today) {
        activePreset.value = 'today'
    } else if (startDate.value === yesterday && endDate.value === yesterday) {
        activePreset.value = 'yesterday'
    } else if (startDate.value === startOfWeek && endDate.value === today) {
        activePreset.value = 'week'
    } else if (startDate.value === startOfMonth && endDate.value === today) {
        activePreset.value = 'month'
    } else {
        activePreset.value = 'custom'
    }
}
checkActivePreset()

function selectPreset(preset) {
    activePreset.value = preset
    const today = dayjs().format('YYYY-MM-DD')

    if (preset === 'today') {
        startDate.value = today
        endDate.value = today
    } else if (preset === 'yesterday') {
        const y = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
        startDate.value = y
        endDate.value = y
    } else if (preset === 'week') {
        startDate.value = dayjs().startOf('week').format('YYYY-MM-DD')
        endDate.value = today
    } else if (preset === 'month') {
        startDate.value = dayjs().startOf('month').format('YYYY-MM-DD')
        endDate.value = today
    } else if (preset === 'all') {
        startDate.value = '2020-01-01'
        endDate.value = today
    }
    doFilter()
}

function doFilter() {
    router.get(route('pos.reports.returns'), {
        start_date: startDate.value || undefined,
        end_date:   endDate.value || undefined,
        user_id:    userId.value || undefined,
        search:     search.value ? search.value.trim() : undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    search.value = ''
    userId.value = ''
    selectPreset('today')
}

function getExportUrl(exportFormat = 'xlsx') {
    const params = new URLSearchParams()
    params.set('format', exportFormat)
    if (startDate.value) params.set('start_date', startDate.value)
    if (endDate.value) params.set('end_date', endDate.value)
    if (userId.value) params.set('user_id', userId.value)
    if (search.value) params.set('search', search.value.trim())
    return `${route('pos.reports.returns.export')}?${params.toString()}`
}

function downloadExport(exportFormat) {
    exportMenuOpen.value = false
    window.location.href = getExportUrl(exportFormat)
}

function handleClickOutside(e) {
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

function getUnitsPerUnitLevel(item, unitUsed = 'unit', rItem = null) {
    if (rItem && rItem.unit_multiplier) return Number(rItem.unit_multiplier) || 1
    if (!item) return 1
    const u = String(unitUsed).toLowerCase()
    if (item.units && Array.isArray(item.units)) {
        const found = item.units.find(unit => unit.unit_name && unit.unit_name.toLowerCase() === u)
        if (found) return Number(found.quantity_in_base_units) || 1
    }
    return 1
}

function getBaseUnits(rItem) {
    if (!rItem) return 0
    const qty = Number(rItem.qty || 0)
    const multiplier = getUnitsPerUnitLevel(rItem.item, rItem.unit_used, rItem)
    return qty * multiplier
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                        Returned Items Report
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Audit product return logs, restocked inventory levels, and customer refunds.</p>
                </div>
            </div>

            <!-- Export Dropdown -->
            <div class="relative" ref="exportDropdownRef">
                <button
                    type="button"
                    @click="exportMenuOpen = !exportMenuOpen"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition inline-flex items-center gap-1.5 shadow-xs cursor-pointer"
                    title="Export returned items report"
                >
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Export</span>
                    <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Menu -->
                <div
                    v-if="exportMenuOpen"
                    class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                >
                    <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        Export Returns Report
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

        <!-- Filters Toolbar -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 bg-slate-100/60 dark:bg-slate-800/50 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
            <!-- Preset Date Pills -->
            <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs">
                <button
                    v-for="p in [
                        { key: 'today', label: 'Today' },
                        { key: 'yesterday', label: 'Yesterday' },
                        { key: 'week', label: 'This Week' },
                        { key: 'month', label: 'This Month' },
                        { key: 'all', label: 'All Time' },
                    ]"
                    :key="p.key"
                    @click="selectPreset(p.key)"
                    type="button"
                    :class="[
                        'px-2.5 py-1 text-xs font-semibold rounded-lg transition cursor-pointer',
                        activePreset === p.key
                            ? 'bg-purple-600 text-white shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Custom Range & Staff Inputs -->
            <div class="flex flex-wrap items-center gap-2.5 text-xs">
                <!-- Search -->
                <div class="relative">
                    <input
                        v-model="search"
                        @keydown.enter="doFilter"
                        type="text"
                        placeholder="Search item, reason, receipt..."
                        class="w-48 sm:w-56 bg-white dark:bg-slate-700 text-slate-900 dark:text-white pl-8 pr-3 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition"
                    />
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <!-- Staff Selector -->
                <div v-if="canSeeAll" class="flex items-center gap-1">
                    <select v-model="userId" @change="doFilter" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition">
                        <option value="">All Cashiers</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.full_name || u.name || u.username }}</option>
                    </select>
                </div>

                <!-- Custom Dates -->
                <div class="flex items-center gap-1.5">
                    <input
                        v-model="startDate"
                        @change="activePreset = 'custom'"
                        type="date"
                        class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition"
                    />
                    <span class="text-slate-400">to</span>
                    <input
                        v-model="endDate"
                        @change="activePreset = 'custom'"
                        type="date"
                        class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition"
                    />
                </div>

                <button @click="doFilter" class="px-3.5 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg transition shadow-xs cursor-pointer">
                    Apply
                </button>
                <button @click="resetFilters" class="px-2.5 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 rounded-lg transition cursor-pointer font-medium">
                    Reset
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Summary Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Return Records</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white font-mono">{{ summary.total_returns }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Items Returned</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono">{{ summary.total_items_qty }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Restocked Base Units</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">+{{ summary.total_base_units }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Refund Worth</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 font-mono truncate" :title="format(summary.total_refund_worth)">{{ format(summary.total_refund_worth) }}</p>
                </div>
            </div>

            <!-- Return Items Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Date & Time</th>
                            <th class="text-left px-5 py-3 font-semibold">Receipt / Ref</th>
                            <th class="text-left px-5 py-3 font-semibold">Item Name</th>
                            <th class="text-center px-5 py-3 font-semibold">Qty & Unit</th>
                            <th class="text-center px-5 py-3 font-semibold">Restocked Stock</th>
                            <th class="text-right px-5 py-3 font-semibold">Refund Worth</th>
                            <th class="text-left px-5 py-3 font-semibold">Reason & Staff</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="item in returns.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                {{ dayjs(item.created_at).format('DD MMM YYYY HH:mm') }}
                            </td>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900 dark:text-white">
                                {{ item.sale?.receipt_id || 'Direct Return' }}
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-900 dark:text-white">
                                {{ item.item_name }}
                            </td>
                            <td class="px-5 py-3 text-center font-semibold text-slate-800 dark:text-slate-200">
                                {{ item.qty }} {{ item.unit_used || 'unit' }}(s)
                            </td>
                            <td class="px-5 py-3 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ getBaseUnits(item) }} Base Units
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-red-600 dark:text-red-400">
                                {{ format(item.refund_amount) }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-400">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ item.return_reason || 'No reason provided' }}</div>
                                <div class="text-[11px] text-slate-400">By: {{ item.user?.full_name || item.user?.name || 'Staff' }}</div>
                            </td>
                        </tr>
                        <tr v-if="!returns.data || !returns.data.length">
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500 text-sm">
                                No return transactions found for the selected date range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <PaginationLinks :links="returns.links" />
        </div>
    </div>
</template>
