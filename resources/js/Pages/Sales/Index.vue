<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import ReceiptModal from '@/Components/POS/ReceiptModal.vue'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const page = usePage()
const props = defineProps({
    sales:     Object,
    summary:   Object,
    users:     Array,
    canSeeAll: Boolean,
    filters:   Object,
    settings:  Object,
})

const { format } = useCurrency()

// ── Filter State ─────────────────────────────────────────────────────────────
const search        = ref(props.filters?.search ?? '')
const from          = ref(props.filters?.from ?? '')
const to            = ref(props.filters?.to ?? '')
const userId        = ref(props.filters?.user_id ?? '')
const paymentMethod = ref(props.filters?.payment_method ?? '')
const showMobileFilters = ref(false)
const showExportMenu    = ref(false)

// Active filter count
const activeFilterCount = computed(() => {
    let count = 0
    if (search.value) count++
    if (from.value || to.value) count++
    if (userId.value) count++
    if (paymentMethod.value) count++
    return count
})

// Check which preset is active
const activeDatePreset = computed(() => {
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (!from.value && !to.value) return 'all'
    if (from.value === today && to.value === today) return 'today'
    if (from.value === yesterday && to.value === yesterday) return 'yesterday'
    if (from.value === startOfWeek && to.value === today) return 'week'
    if (from.value === startOfMonth && to.value === today) return 'month'
    return 'custom'
})

// Human-readable narrative description of loaded filtered data
const filterDescription = computed(() => {
    // 1. Time description
    let timeText = 'for All Time'
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (from.value && to.value) {
        if (from.value === today && to.value === today) {
            timeText = `for Today (${dayjs().format('DD MMM YYYY')})`
        } else if (from.value === yesterday && to.value === yesterday) {
            timeText = `for Yesterday (${dayjs(yesterday).format('DD MMM YYYY')})`
        } else if (from.value === startOfWeek && to.value === today) {
            timeText = `for This Week (${dayjs(startOfWeek).format('DD MMM')} – ${dayjs().format('DD MMM YYYY')})`
        } else if (from.value === startOfMonth && to.value === today) {
            timeText = `for This Month (${dayjs().format('MMMM YYYY')})`
        } else if (from.value === to.value) {
            timeText = `for ${dayjs(from.value).format('DD MMM YYYY')}`
        } else {
            timeText = `from ${dayjs(from.value).format('DD MMM YYYY')} to ${dayjs(to.value).format('DD MMM YYYY')}`
        }
    } else if (from.value) {
        timeText = `from ${dayjs(from.value).format('DD MMM YYYY')} onwards`
    } else if (to.value) {
        timeText = `up to ${dayjs(to.value).format('DD MMM YYYY')}`
    }

    // 2. User description
    let userText = 'processed by All Staff'
    if (userId.value) {
        const foundUser = props.users?.find(u => String(u.id) === String(userId.value))
        const name = foundUser ? (foundUser.full_name || foundUser.name || foundUser.username) : 'Selected Staff'
        userText = `processed by ${name}`
    } else if (!props.canSeeAll) {
        userText = 'processed by You'
    }

    // 3. Payment method description
    let methodText = 'across All Payment Methods'
    if (paymentMethod.value) {
        methodText = `via ${paymentMethod.value}`
    }

    return {
        timeText,
        userText,
        methodText,
        searchText: search.value ? search.value.trim() : null,
    }
})

function applyPreset(preset) {
    const today = dayjs().format('YYYY-MM-DD')
    if (preset === 'today') {
        from.value = today
        to.value   = today
    } else if (preset === 'yesterday') {
        const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
        from.value = yesterday
        to.value   = yesterday
    } else if (preset === 'week') {
        from.value = dayjs().startOf('week').format('YYYY-MM-DD')
        to.value   = today
    } else if (preset === 'month') {
        from.value = dayjs().startOf('month').format('YYYY-MM-DD')
        to.value   = today
    } else if (preset === 'all') {
        from.value = ''
        to.value   = ''
    }
    doFilter()
}

function doFilter() {
    router.get(route('pos.sales.index'), {
        search:         search.value || undefined,
        from:           from.value || undefined,
        to:             to.value || undefined,
        user_id:        userId.value || undefined,
        payment_method: paymentMethod.value || undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    search.value        = ''
    from.value          = ''
    to.value            = ''
    userId.value        = ''
    paymentMethod.value = ''
    doFilter()
}

// ── Export Handling ──────────────────────────────────────────────────────────
function exportData(formatType = 'xlsx') {
    showExportMenu.value = false
    const params = new URLSearchParams()
    if (search.value) params.append('search', search.value)
    if (from.value) params.append('from', from.value)
    if (to.value) params.append('to', to.value)
    if (userId.value) params.append('user_id', userId.value)
    if (paymentMethod.value) params.append('payment_method', paymentMethod.value)
    params.append('format', formatType)

    const url = `${route('pos.sales.export')}?${params.toString()}`
    window.open(url, '_blank')
}

// ── Receipt Modal ────────────────────────────────────────────────────────────
const selectedSale     = ref(null)
const showReceiptModal = ref(false)

function openReceipt(sale) {
    selectedSale.value     = sale
    showReceiptModal.value = true
}

function destroy(id, receiptId) {
    if (confirm(`Delete sale #${receiptId}? This cannot be undone.`)) {
        router.delete(route('pos.sales.destroy', id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Sales History</span>
                        <span v-if="sales.total > 0" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-mono">
                            {{ sales.total }} records
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">View transaction records, filter by cashier or payment type, and export audit sheets.</p>
                </div>

                <!-- Action Controls: Export & Mobile Filter Toggle -->
                <div class="flex items-center gap-2">
                    <!-- Export Dropdown -->
                    <div class="relative">
                        <button
                            type="button"
                            @click="showExportMenu = !showExportMenu"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Export</span>
                            <svg class="w-3 h-3 transition-transform" :class="showExportMenu ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div
                            v-if="showExportMenu"
                            @click.outside="showExportMenu = false"
                            class="absolute right-0 mt-1.5 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 z-50 overflow-hidden py-1"
                        >
                            <button
                                type="button"
                                @click="exportData('xlsx')"
                                class="w-full text-left px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2 transition cursor-pointer"
                            >
                                <span class="text-emerald-600 font-bold">📊</span>
                                <span>Export to Excel (.xlsx)</span>
                            </button>
                            <button
                                type="button"
                                @click="exportData('csv')"
                                class="w-full text-left px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2 transition cursor-pointer border-t border-slate-100 dark:border-slate-700/50"
                            >
                                <span class="text-blue-600 font-bold">📄</span>
                                <span>Export to CSV (.csv)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile Filter Toggle Button -->
                    <button
                        type="button"
                        @click="showMobileFilters = !showMobileFilters"
                        class="sm:hidden px-3 py-1.5 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5 cursor-pointer"
                        :class="activeFilterCount > 0
                            ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700'
                            : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-600'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        <span>Filters</span>
                        <span v-if="activeFilterCount > 0" class="w-4 h-4 rounded-full bg-emerald-600 text-white text-[10px] font-bold flex items-center justify-center">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Filters Bar (Compact & Responsive) ────────────────────────────── -->
        <div class="px-4 sm:px-6 py-2.5 bg-slate-100/70 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700/80 flex-shrink-0">
            <!-- Desktop Filter Bar & Mobile Accordion Content -->
            <div :class="showMobileFilters ? 'block' : 'hidden sm:block'" class="space-y-2.5">
                
                <!-- Top Row: Search + Quick Date Pills + Clear Button -->
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <!-- Search Input -->
                    <div class="relative flex-1 min-w-[200px] max-w-sm">
                        <input
                            v-model="search"
                            @keydown.enter="doFilter"
                            type="text"
                            placeholder="Search receipt #, customer, phone…"
                            class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition"
                        />
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <!-- Date Preset Pills -->
                    <div class="flex flex-wrap items-center gap-1">
                        <button
                            type="button"
                            @click="applyPreset('today')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'today'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('yesterday')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'yesterday'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            Yesterday
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('week')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'week'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            This Week
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('month')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'month'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('all')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'all'
                                ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900 border-slate-800 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            All Time
                        </button>
                    </div>

                    <!-- Filter / Clear Buttons -->
                    <div class="flex items-center gap-1.5 ml-auto">
                        <button
                            type="button"
                            @click="doFilter"
                            class="px-3 py-1 bg-slate-800 dark:bg-slate-200 text-white dark:text-slate-900 hover:bg-slate-700 dark:hover:bg-white text-xs font-bold rounded-lg transition shadow-xs cursor-pointer"
                        >
                            Apply
                        </button>
                        <button
                            v-if="activeFilterCount > 0"
                            type="button"
                            @click="resetFilters"
                            class="px-2.5 py-1 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 text-xs font-semibold rounded-lg transition cursor-pointer"
                            title="Reset all filters"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Secondary Row: Cashier Selector + Payment Method + Custom Date Inputs -->
                <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-200/60 dark:border-slate-700/50">
                    <!-- Cashier dropdown -->
                    <div v-if="canSeeAll" class="flex items-center gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Staff:</span>
                        <select
                            v-model="userId"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        >
                            <option value="">All Cashiers / Staff</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.full_name || u.name || u.username }}
                            </option>
                        </select>
                    </div>

                    <!-- Payment Method dropdown -->
                    <div class="flex items-center gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Method:</span>
                        <select
                            v-model="paymentMethod"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        >
                            <option value="">All Methods</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Split">Split</option>
                            <option value="Debt">Debt (Credit)</option>
                        </select>
                    </div>

                    <!-- Custom Range Date Pickers -->
                    <div class="flex items-center gap-1.5 ml-auto">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Range:</span>
                        <input
                            v-model="from"
                            type="date"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        />
                        <span class="text-slate-400 text-xs">to</span>
                        <input
                            v-model="to"
                            type="date"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Filter Results Narrative Description Banner ──────────────────── -->
        <div class="px-4 sm:px-6 py-2.5 bg-emerald-50/70 dark:bg-slate-900/90 border-b border-emerald-100 dark:border-slate-700/80 text-xs text-slate-700 dark:text-slate-300 flex flex-wrap items-center justify-between gap-2 flex-shrink-0">
            <div class="flex items-center gap-2 flex-1 min-w-[260px]">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 text-xs font-bold flex-shrink-0">
                    📊
                </span>
                <p class="leading-relaxed text-xs">
                    Sales history <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ filterDescription.timeText }}</span>
                    &bull; <span class="font-semibold text-slate-900 dark:text-white">{{ filterDescription.userText }}</span>
                    &bull; <span class="font-semibold text-slate-900 dark:text-white">{{ filterDescription.methodText }}</span>
                    <template v-if="filterDescription.searchText">
                        &bull; matching <span class="font-bold text-indigo-600 dark:text-indigo-400">"{{ filterDescription.searchText }}"</span>
                    </template>
                </p>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono flex items-center gap-1.5 ml-auto">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span><strong class="text-slate-900 dark:text-white font-bold">{{ summary?.total_sales ?? 0 }}</strong> orders loaded</span>
            </div>
        </div>

        <!-- ── Summary KPI Cards (Compact 4-Grid) ────────────────────────────── -->
        <div v-if="summary" class="px-4 sm:px-6 py-3 bg-white dark:bg-slate-800/70 border-b border-slate-200 dark:border-slate-700/60 grid grid-cols-2 md:grid-cols-4 gap-3 flex-shrink-0">
            <div class="p-2.5 sm:p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/60">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Total Orders</span>
                <span class="text-base sm:text-lg font-bold text-slate-900 dark:text-white font-mono">{{ summary.total_sales }}</span>
            </div>
            <div class="p-2.5 sm:p-3 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40">
                <span class="text-[11px] text-emerald-700 dark:text-emerald-300 block font-medium">Total Revenue</span>
                <span class="text-base sm:text-lg font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ format(summary.total_revenue) }}</span>
            </div>
            <div class="p-2.5 sm:p-3 rounded-xl bg-red-50/60 dark:bg-red-950/20 border border-red-200/60 dark:border-red-900/40">
                <span class="text-[11px] text-red-700 dark:text-red-300 block font-medium">Total Discounts</span>
                <span class="text-base sm:text-lg font-bold text-red-600 dark:text-red-400 font-mono">{{ format(summary.total_discount) }}</span>
            </div>
            <div class="p-2.5 sm:p-3 rounded-xl bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200/60 dark:border-blue-900/40">
                <span class="text-[11px] text-blue-700 dark:text-blue-300 block font-medium">Gross Profit</span>
                <span class="text-base sm:text-lg font-bold text-blue-600 dark:text-blue-400 font-mono">{{ format(summary.total_profit) }}</span>
            </div>
        </div>

        <!-- ── Sales Table ──────────────────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold">Receipt #</th>
                                <th class="text-left px-4 py-3 font-semibold">Customer</th>
                                <th class="text-left px-4 py-3 font-semibold">Cashier / Staff</th>
                                <th class="text-left px-4 py-3 font-semibold">Method</th>
                                <th class="text-right px-4 py-3 font-semibold">Total Paid</th>
                                <th class="text-right px-4 py-3 font-semibold">Profit</th>
                                <th class="text-left px-4 py-3 font-semibold">Date & Time</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-4 py-3">
                                    <Link :href="route('pos.sales.show', sale.id)"
                                        class="text-blue-600 dark:text-blue-400 hover:underline font-mono font-bold transition">
                                        {{ sale.receipt_id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                    <span v-if="sale.customer" class="font-medium text-slate-900 dark:text-white">{{ sale.customer.name }}</span>
                                    <span v-else class="text-slate-400 dark:text-slate-500 italic">Walk-in</span>
                                </td>
                                <td class="px-4 py-3 text-slate-800 dark:text-slate-200 font-medium">
                                    {{ sale.user?.full_name || sale.user?.name || sale.user?.username || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-[11px] px-2 py-0.5 rounded-full font-medium"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400': sale.payment_method === 'Cash',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400': sale.payment_method === 'Bank Transfer',
                                            'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400': sale.payment_method === 'Split',
                                            'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400': sale.payment_method === 'Debt',
                                        }">
                                        {{ sale.payment_method }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ format(sale.final_total) }}
                                </td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 font-mono font-bold">
                                    {{ format(sale.profit_made) }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap font-mono">
                                    {{ dayjs(sale.created_at).format('DD-MMM-YY HH:mm') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2.5">
                                        <button
                                            type="button"
                                            @click="openReceipt(sale)"
                                            class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 font-semibold transition flex items-center gap-1 cursor-pointer"
                                            title="Print / View Receipt"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            <span>Print</span>
                                        </button>
                                        <Link
                                            :href="route('pos.sales.show', sale.id)"
                                            class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition"
                                        >
                                            View
                                        </Link>
                                        <button
                                            type="button"
                                            @click="destroy(sale.id, sale.receipt_id)"
                                            class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition cursor-pointer"
                                        >
                                            Del
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!sales.data.length">
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                    <div class="space-y-1">
                                        <div class="text-2xl">🧾</div>
                                        <div class="font-semibold text-slate-700 dark:text-slate-300">No sales transactions found</div>
                                        <div class="text-xs text-slate-400">Try changing the date range, cashier, or search criteria.</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div v-if="sales.total > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/40">
                    <div>
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ sales.from }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ sales.to }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ sales.total }}</span> sales
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in sales.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer"
                                :class="link.active
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                    : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-600'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-lg text-xs text-slate-400 dark:text-slate-600 border border-slate-200/50 dark:border-slate-700/50 select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Modal -->
        <ReceiptModal
            v-if="showReceiptModal && selectedSale"
            :sale="selectedSale"
            :settings="settings || {}"
            :is-reprint="true"
            @close="showReceiptModal = false"
        />
    </div>
</template>
