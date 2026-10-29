<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import dayjs from 'dayjs'

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    users: {
        type: Array,
        default: () => [],
    },
    selected_user: {
        type: Object,
        default: null,
    },
    metrics: {
        type: Object,
        required: true,
    },
    status_breakdown: {
        type: Object,
        default: () => ({}),
    },
    methods_summary: {
        type: Array,
        default: () => [],
    },
    top_services: {
        type: Array,
        default: () => [],
    },
    overdue_orders: {
        type: Array,
        default: () => [],
    },
    unclaimed_orders: {
        type: Array,
        default: () => [],
    },
    outstanding_by_customer: {
        type: [Object, Array],
        default: () => [],
    },
    user_activity_log: {
        type: Object,
        default: null,
    },
    staff_summary: {
        type: Array,
        default: () => [],
    },
    discounts_performance: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + '\u00A0' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatNumber = (val) => {
    const num = Number(val || 0)
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatQty = (val) => {
    const num = Number(val || 0)
    return num % 1 === 0 ? num.toString() : num.toString()
}

// ── Filter State ─────────────────────────────────────────────────────────────
const range  = ref(props.filters?.range || 'this_month')
const from   = ref(props.filters?.from || props.filters?.start_date || '')
const to     = ref(props.filters?.to || props.filters?.end_date || '')
const userId = ref(props.filters?.user_id || '')

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        range.value  = newFilters.range || 'this_month'
        from.value   = newFilters.from || newFilters.start_date || ''
        to.value     = newFilters.to || newFilters.end_date || ''
        userId.value = newFilters.user_id ? String(newFilters.user_id) : ''
    }
}, { deep: true })

// Active preset pill computation
const activeDatePreset = computed(() => {
    if (range.value === 'all' || (!from.value && !to.value && range.value === 'all')) return 'all'
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (range.value === 'today' || (from.value === today && to.value === today)) return 'today'
    if (range.value === 'yesterday' || (from.value === yesterday && to.value === yesterday)) return 'yesterday'
    if (range.value === 'this_week' || range.value === 'week' || (from.value === startOfWeek && to.value === today)) return 'this_week'
    if (range.value === 'this_month' || range.value === 'month' || (from.value === startOfMonth && to.value === today)) return 'this_month'
    if (range.value === 'last_month') return 'last_month'
    if (range.value === 'custom') return 'custom'
    return 'custom'
})

const activeFilterCount = computed(() => {
    let count = 0
    if (activeDatePreset.value !== 'this_month') count++
    if (userId.value) count++
    return count
})

// Narrative description banner
const filterDescription = computed(() => {
    let timeText = 'for All Time'
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (range.value === 'all' || (!from.value && !to.value)) {
        timeText = 'for All Time'
    } else if (from.value && to.value) {
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

    let userText = 'across all cashiers & staff'
    if (props.selected_user) {
        userText = `processed by ${props.selected_user.name} (${props.selected_user.role})`
    } else if (userId.value) {
        const found = props.users?.find(u => String(u.id) === String(userId.value))
        userText = `processed by ${found?.name || 'Selected Staff'}`
    }

    return {
        timeText,
        userText,
        ordersCount: props.metrics?.orders_count || 0,
    }
})

function applyPreset(preset) {
    const today = dayjs().format('YYYY-MM-DD')
    range.value = preset
    if (preset === 'today') {
        from.value = today
        to.value   = today
    } else if (preset === 'yesterday') {
        const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
        from.value = yesterday
        to.value   = yesterday
    } else if (preset === 'this_week' || preset === 'week') {
        from.value = dayjs().startOf('week').format('YYYY-MM-DD')
        to.value   = today
    } else if (preset === 'this_month' || preset === 'month') {
        from.value = dayjs().startOf('month').format('YYYY-MM-DD')
        to.value   = today
    } else if (preset === 'last_month') {
        from.value = dayjs().subtract(1, 'month').startOf('month').format('YYYY-MM-DD')
        to.value   = dayjs().subtract(1, 'month').endOf('month').format('YYYY-MM-DD')
    } else if (preset === 'all') {
        from.value = ''
        to.value   = ''
    }
    doFilter()
}

function doFilter() {
    router.get(
        safeRoute('laundry.reports.index'),
        {
            range:   range.value || undefined,
            from:    from.value || undefined,
            to:      to.value || undefined,
            start_date: from.value || undefined,
            end_date:   to.value || undefined,
            user_id: userId.value || undefined,
        },
        { preserveState: true }
    )
}

function resetFilters() {
    range.value  = 'this_month'
    from.value   = dayjs().startOf('month').format('YYYY-MM-DD')
    to.value     = dayjs().format('YYYY-MM-DD')
    userId.value = ''
    doFilter()
}

function filterByUser(id) {
    userId.value = id ? String(id) : ''
    doFilter()
}

// Outstanding Debts List
const customerDebtsList = computed(() => {
    if (Array.isArray(props.outstanding_by_customer)) {
        return props.outstanding_by_customer
    }
    return props.outstanding_by_customer?.data || []
})

// User Activity List
const userActivitiesList = computed(() => {
    if (!props.user_activity_log) return []
    return props.user_activity_log.data || []
})
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- ── Top Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🧺</span>
                    <span>Laundry Analytics & Staff Reports</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Query revenue, order volume, cashier shift collections, and staff productivity by date range
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    :href="safeRoute('laundry.reports.end-of-day')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40 transition flex items-center gap-1.5"
                >
                    <span>🧾 End of Day</span>
                </Link>
                <Link
                    :href="safeRoute('laundry.orders.index')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition"
                >
                    Orders Board
                </Link>
                <Link
                    :href="safeRoute('laundry.orders.create')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-xs transition"
                >
                    + Intake Order
                </Link>
            </div>
        </div>

        <!-- ── Filter Toolbar (Matching POS Sales Report Style) ──────────────── -->
        <div class="px-4 sm:px-6 py-3 bg-slate-100/90 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <div class="flex flex-col gap-2.5">
                <!-- Primary Row: Date Preset Pills + Action Buttons -->
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <!-- Date Preset Pills -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-1 hidden sm:inline">Period:</span>
                        <button
                            type="button"
                            @click="applyPreset('today')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'today'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('yesterday')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'yesterday'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                        >
                            Yesterday
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('this_week')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'this_week'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                        >
                            This Week
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('this_month')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'this_month'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('last_month')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'last_month'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                        >
                            Last Month
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('all')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'all'
                                ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 border-slate-900 dark:border-slate-100 shadow-xs'
                                : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                        >
                            All Time
                        </button>
                    </div>

                    <!-- Apply & Reset Buttons -->
                    <div class="flex items-center gap-2 ml-auto">
                        <button
                            type="button"
                            @click="doFilter"
                            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition shadow-xs cursor-pointer flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span>Filter</span>
                        </button>
                        <button
                            v-if="activeFilterCount > 0"
                            type="button"
                            @click="resetFilters"
                            class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 text-xs font-semibold rounded-lg transition cursor-pointer"
                            title="Reset all filters"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Secondary Row: Staff Selector + Custom Date Range Inputs -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-200/80 dark:border-slate-700/60">
                    <!-- Staff / Cashier Query Dropdown -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Staff / Cashier:</span>
                        </span>
                        <select
                            v-model="userId"
                            @change="doFilter"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 outline-none focus:border-indigo-500 font-medium min-w-[220px]"
                        >
                            <option value="">All Cashiers & Staff</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.role }})
                            </option>
                        </select>
                    </div>

                    <!-- Custom Range Date Pickers -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Custom Range:</span>
                        <input
                            v-model="from"
                            @input="range = 'custom'"
                            type="date"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 outline-none focus:border-indigo-500"
                        />
                        <span class="text-slate-400 text-xs font-semibold">to</span>
                        <input
                            v-model="to"
                            @input="range = 'custom'"
                            type="date"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 outline-none focus:border-indigo-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Filter Results Narrative Banner ────────────────────────────────── -->
        <div class="px-4 sm:px-6 py-2.5 bg-indigo-50/70 dark:bg-slate-900/90 border-b border-indigo-100 dark:border-slate-700/80 text-xs text-slate-700 dark:text-slate-300 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
            <div class="flex items-center gap-2 flex-1 min-w-[280px]">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 text-xs font-bold flex-shrink-0">
                    📈
                </span>
                <p class="leading-relaxed text-xs">
                    Laundry report <span class="font-bold text-indigo-700 dark:text-indigo-400">{{ filterDescription.timeText }}</span>
                    &bull; <span class="font-semibold text-slate-900 dark:text-white">{{ filterDescription.userText }}</span>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span v-if="selected_user" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300 text-[11px] font-semibold border border-indigo-200 dark:border-indigo-800">
                    <span>Filtering: {{ selected_user.name }}</span>
                    <button @click="filterByUser('')" class="hover:text-red-500 transition font-bold" title="Clear Staff Filter">&times;</button>
                </span>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    {{ filterDescription.ordersCount }} orders logged
                </span>
            </div>
        </div>

        <!-- ── Scrollable Body ────────────────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- ── Dynamic Key Metrics Cards (User-Friendly Executive Layout) ──────────────── -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                    <!-- Net Collections (Primary Financial Highlight — Spans 3 cols on desktop) -->
                    <div class="lg:col-span-3 p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-emerald-50/70 via-white to-white dark:from-emerald-950/25 dark:via-slate-800 dark:to-slate-800 border border-emerald-200/90 dark:border-emerald-800/50 shadow-xs relative overflow-hidden transition">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Net Collections</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    {{ selected_user ? 'Cashier Shift Revenue' : 'Realized Revenue' }}
                                </span>
                            </div>
                            <span class="p-2 rounded-xl bg-emerald-100/80 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 text-sm">💰</span>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1 font-mono tracking-tight whitespace-nowrap overflow-hidden text-ellipsis" :title="formatMoney(metrics.net_collections)">
                            <span class="text-base sm:text-lg font-bold text-emerald-600 dark:text-emerald-400 select-none">{{ currencySymbol }}</span>
                            <span class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ formatNumber(metrics.net_collections) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-2.5 pt-2 border-t border-emerald-100/90 dark:border-emerald-900/40">
                            <span class="font-medium whitespace-nowrap">Gross: <strong class="text-slate-700 dark:text-slate-200 font-mono">{{ formatMoney(metrics.gross_collections) }}</strong></span>
                            <span v-if="metrics.refunds_total > 0" class="text-rose-600 dark:text-rose-400 font-semibold font-mono whitespace-nowrap">(-{{ formatMoney(metrics.refunds_total) }} refunds)</span>
                            <span v-else class="text-slate-400 dark:text-slate-500 font-normal">Zero refunds deducted</span>
                        </div>
                    </div>

                    <!-- Total Unpaid Balances / Orders Handed Over (Primary Highlight — Spans 3 cols on desktop) -->
                    <div v-if="selected_user" class="lg:col-span-3 p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-sky-50/70 via-white to-white dark:from-sky-950/25 dark:via-slate-800 dark:to-slate-800 border border-sky-200/90 dark:border-sky-800/50 shadow-xs relative overflow-hidden transition">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-sky-700 dark:text-sky-400">Orders Handed Over</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-900/60 text-sky-800 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                    Completed Pickups
                                </span>
                            </div>
                            <span class="p-2 rounded-xl bg-sky-100/80 dark:bg-sky-950 text-sky-600 dark:text-sky-400 text-sm">🤝</span>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1.5 font-mono tracking-tight whitespace-nowrap">
                            <span class="text-2xl sm:text-3xl font-black text-sky-600 dark:text-sky-400">{{ metrics.handover_count }}</span>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">orders released to clients</span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-2.5 pt-2 border-t border-sky-100/90 dark:border-sky-900/40">
                            Clean garments collected and confirmed by customer
                        </div>
                    </div>
                    <div v-else class="lg:col-span-3 p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-amber-50/70 via-white to-white dark:from-amber-950/25 dark:via-slate-800 dark:to-slate-800 border border-amber-200/90 dark:border-amber-800/50 shadow-xs relative overflow-hidden transition">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Total Unpaid Balances</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    Receivables
                                </span>
                            </div>
                            <span class="p-2 rounded-xl bg-amber-100/80 dark:bg-amber-950 text-amber-600 dark:text-amber-400 text-sm">🧾</span>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1 font-mono tracking-tight whitespace-nowrap overflow-hidden text-ellipsis" :title="formatMoney(metrics.outstanding_debt)">
                            <span class="text-base sm:text-lg font-bold text-amber-600 dark:text-amber-400 select-none">{{ currencySymbol }}</span>
                            <span class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400">{{ formatNumber(metrics.outstanding_debt) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400 mt-2.5 pt-2 border-t border-amber-100/90 dark:border-amber-900/40">
                            <span>Outstanding customer debt ledger balances</span>
                            <Link
                                v-if="$page.props.auth?.permissions?.canManageDebt"
                                :href="safeRoute('pos.reports.customer-debt')"
                                class="text-amber-700 dark:text-amber-400 font-bold hover:underline inline-flex items-center gap-1"
                            >
                                <span>Customer Debt Report</span>
                                <span>&rarr;</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Orders Intake (Spans 2 cols on desktop) -->
                    <div class="lg:col-span-2 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Orders Intake</span>
                            <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 text-xs">📦</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1.5 font-mono">{{ metrics.orders_count }}</p>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 font-mono" :title="'Net value of orders'">
                            <span>Net Value: <strong class="text-slate-800 dark:text-slate-200">{{ formatMoney(metrics.orders_value) }}</strong></span>
                        </div>
                    </div>

                    <!-- Promo Discounts & Savings (Spans 2 cols on desktop) -->
                    <div class="lg:col-span-2 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-pink-600 dark:text-pink-400">Discounts & Savings</span>
                            <span class="p-1.5 rounded-lg bg-pink-50 dark:bg-pink-950 text-pink-600 dark:text-pink-400 text-xs">🏷️</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-0.5 font-mono tracking-tight whitespace-nowrap overflow-hidden text-ellipsis" :title="formatMoney(metrics.total_discounts || 0)">
                            <span class="text-sm font-bold text-pink-600 dark:text-pink-400 select-none">{{ currencySymbol }}</span>
                            <span class="text-2xl sm:text-3xl font-black text-pink-600 dark:text-pink-400">{{ formatNumber(metrics.total_discounts || 0) }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 truncate font-mono">
                            <span>{{ metrics.discounted_orders_count || 0 }} order(s) discounted</span>
                            <span v-if="metrics.discount_rate_percent > 0" class="text-pink-600 dark:text-pink-400 font-bold">({{ metrics.discount_rate_percent }}%)</span>
                        </div>
                    </div>

                    <!-- Workflow Status Updates or Overdue/Unclaimed (Spans 2 cols on desktop) -->
                    <div v-if="selected_user" class="lg:col-span-2 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Workflow Updates</span>
                            <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400 text-xs">⚡</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-purple-600 dark:text-purple-400 mt-1.5 font-mono">{{ metrics.status_changes_count }}</p>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 truncate">
                            Stage progression logs recorded
                        </div>
                    </div>
                    <div v-else class="lg:col-span-2 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Overdue / Unclaimed</span>
                            <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 text-xs">⚠️</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400 mt-1.5 font-mono">{{ metrics.overdue_count }} / {{ metrics.unclaimed_count }}</p>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/60 truncate">
                            Pending customer pickup follow-up
                        </div>
                    </div>
                </div>

                <!-- ── VIEW A: WHEN SPECIFIC USER/CASHIER IS SELECTED ───────────── -->
                <div v-if="selected_user" class="space-y-6">

                    <!-- Cashier Highlight Header Banner -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-indigo-900 to-slate-900 text-white flex flex-wrap items-center justify-between gap-4 shadow-md">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-xl font-black text-indigo-300">
                                {{ (selected_user.name || selected_user.username).charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-bold">{{ selected_user.name }}</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-indigo-500/30 border border-indigo-400/40 text-indigo-200">
                                        {{ selected_user.role }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-300 mt-0.5">
                                    Cashier shift performance query &bull; Username: <span class="font-mono text-indigo-200">{{ selected_user.username }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="filterByUser('')"
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-white/10 hover:bg-white/20 text-white transition flex items-center gap-1.5"
                            >
                                <span>&larr; Back to All Staff Overview</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2-Col Grid: Cashier Collections by Method + Orders Status Distribution -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <!-- Collections by Payment Method -->
                        <div class="lg:col-span-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>💳</span>
                                <span>Collections by Method ({{ selected_user.name }})</span>
                            </h3>

                            <div v-if="methods_summary.length === 0" class="py-8 text-center text-xs text-slate-400">
                                No payments recorded by this staff member in this timeframe.
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                        <tr>
                                            <th class="px-4 py-2.5">Method</th>
                                            <th class="px-4 py-2.5 text-center">Txns</th>
                                            <th class="px-4 py-2.5 text-right">Payments</th>
                                            <th class="px-4 py-2.5 text-right font-black">Net Cash</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <tr v-for="m in methods_summary" :key="m.method">
                                            <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ m.method }}</td>
                                            <td class="px-4 py-3 text-center text-slate-500">{{ m.count }}</td>
                                            <td class="px-4 py-3 text-right text-emerald-600 font-semibold">{{ formatMoney(m.payments) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">{{ formatMoney(m.net) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Orders Status Breakdown -->
                        <div class="lg:col-span-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                <span>📊</span>
                                <span>Orders Created Status Breakdown</span>
                            </h3>

                            <div v-if="Object.keys(status_breakdown).length === 0" class="py-8 text-center text-xs text-slate-400">
                                No orders intake created by this staff member in this timeframe.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="(st, key) in status_breakdown"
                                    :key="key"
                                    class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between"
                                >
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wide text-slate-800 dark:text-slate-200">
                                            {{ key.replace('_', ' ') }}
                                        </span>
                                        <p class="text-[11px] text-slate-400 mt-0.5">
                                            {{ st.count }} order{{ st.count > 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                    <span class="font-black text-sm text-slate-900 dark:text-white">
                                        {{ formatMoney(st.total_amount) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Promotions & Discounts Performance (Cashier Scope) -->
                    <div v-if="discounts_performance && discounts_performance.length > 0" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 pb-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center text-lg">
                                    🏷️
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                                        Promotions & Discounts Given ({{ selected_user.name }})
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Promotional discounts and savings applied by this staff member in this timeframe
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-pink-50 text-pink-700 dark:bg-pink-950/40 dark:text-pink-300 border border-pink-200 dark:border-pink-800">
                                Total Savings: {{ formatMoney(metrics.total_discounts || 0) }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3">Campaign / Promo Name</th>
                                        <th class="px-5 py-3">Scope & Target</th>
                                        <th class="px-5 py-3 text-center">Type / Rate</th>
                                        <th class="px-5 py-3 text-center">Volume Applied</th>
                                        <th class="px-5 py-3 text-right font-black">Customer Savings</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="d in discounts_performance" :key="d.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-5 py-3">
                                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span>{{ d.scope === 'general' ? '🏷️' : (d.scope === 'service' ? '🧺' : '✂️') }}</span>
                                                <span>{{ d.name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                                :class="{
                                                    'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800': d.scope === 'general',
                                                    'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800': d.scope === 'service',
                                                    'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600': d.scope === 'manual',
                                                }"
                                            >
                                                {{ d.scope_label }}
                                            </span>
                                            <span v-if="d.target" class="text-[11px] text-slate-400 block mt-0.5 truncate max-w-xs">
                                                Target: {{ d.target }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-center font-medium text-slate-700 dark:text-slate-300">
                                            <span v-if="d.type === 'percentage'">{{ d.rate }}% Off</span>
                                            <span v-else-if="d.type === 'fixed'">{{ formatMoney(d.rate) }} Off</span>
                                            <span v-else class="text-slate-400">Custom</span>
                                        </td>
                                        <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-400 font-semibold">
                                            {{ d.usage_count }} {{ d.unit_label }}
                                        </td>
                                        <td class="px-5 py-3 text-right font-black text-pink-600 dark:text-pink-400">
                                            {{ formatMoney(d.total_savings) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Chronological Cashier Activity Log & Audit Feed ────── -->
                    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 pb-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                    <span>🕒</span>
                                    <span>Cashier Shift Activity Log ({{ selected_user.name }})</span>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Chronological timeline of all order intakes, payments recorded, status changes, and order deliveries
                                </p>
                            </div>
                            <span v-if="user_activity_log?.total" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                                {{ user_activity_log.total }} Action{{ user_activity_log.total > 1 ? 's' : '' }} Logged
                            </span>
                        </div>

                        <div v-if="userActivitiesList.length === 0" class="py-12 text-center text-xs text-slate-400">
                            No recorded actions found for this staff member in the selected date range.
                        </div>

                        <div v-else>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                        <tr>
                                            <th class="px-5 py-3">Timestamp</th>
                                            <th class="px-5 py-3">Action</th>
                                            <th class="px-5 py-3">Order / Reference</th>
                                            <th class="px-5 py-3">Details</th>
                                            <th class="px-5 py-3 text-right">Amount</th>
                                            <th class="px-5 py-3 text-right">View</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <tr v-for="act in userActivitiesList" :key="act.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                            <td class="px-5 py-3 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                                {{ act.formatted_time }}
                                            </td>
                                            <td class="px-5 py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border"
                                                    :class="{
                                                        'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800': act.action_type === 'order_created',
                                                        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800': act.action_type === 'payment_recorded',
                                                        'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800': act.action_type === 'refund_issued',
                                                        'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800': act.action_type === 'order_collected',
                                                        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800': act.action_type === 'status_changed',
                                                    }"
                                                >
                                                    {{ act.badge }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 font-bold text-slate-900 dark:text-white">
                                                <Link
                                                    v-if="act.order_id"
                                                    :href="safeRoute('laundry.orders.show', { order: act.order_id })"
                                                    class="text-indigo-600 dark:text-indigo-400 hover:underline font-mono"
                                                >
                                                    #{{ act.order_number }}
                                                </Link>
                                                <span v-else class="text-slate-400">—</span>
                                            </td>
                                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                                <div class="font-medium text-slate-900 dark:text-white">{{ act.title }}</div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ act.description }}</div>
                                            </td>
                                            <td class="px-5 py-3 text-right font-black">
                                                <span v-if="act.amount !== null" :class="act.action_type === 'refund_issued' ? 'text-rose-600' : 'text-slate-900 dark:text-white'">
                                                    {{ formatMoney(act.amount) }}
                                                </span>
                                                <span v-else class="text-slate-400 text-xs">—</span>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <Link
                                                    v-if="act.order_id"
                                                    :href="safeRoute('laundry.orders.show', { order: act.order_id })"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition"
                                                >
                                                    <span>View</span>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Activity Paginator -->
                            <PaginationLinks
                                v-if="user_activity_log?.links"
                                :links="user_activity_log.links"
                                :meta="user_activity_log"
                            />
                        </div>
                    </div>

                </div>

                <!-- ── VIEW B: WHEN ALL STAFF ARE SELECTED (BRANCH OVERVIEW) ────── -->
                <div v-else class="space-y-6">

                    <!-- Staff & Cashier Contribution Comparison Table -->
                    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 pb-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                    <span>👥</span>
                                    <span>Staff & Cashier Contribution Breakdown</span>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Breakdown of orders created, collections recorded, and handovers per cashier in this timeframe
                                </p>
                            </div>
                            <span v-if="staff_summary.length > 0" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                {{ staff_summary.length }} Active Staff
                            </span>
                        </div>

                        <div v-if="staff_summary.length === 0" class="py-10 text-center text-xs text-slate-400">
                            No cashier or staff operations recorded for the selected date range.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3">Cashier / Staff</th>
                                        <th class="px-5 py-3 text-center">Orders Created</th>
                                        <th class="px-5 py-3 text-right">Intake Value</th>
                                        <th class="px-5 py-3 text-right">Discounts Given</th>
                                        <th class="px-5 py-3 text-right font-black">Net Collections</th>
                                        <th class="px-5 py-3 text-right">Cash</th>
                                        <th class="px-5 py-3 text-right">Bank / Transfer</th>
                                        <th class="px-5 py-3 text-center">Handovers</th>
                                        <th class="px-5 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="staff in staff_summary" :key="staff.user_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-5 py-3">
                                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span>{{ staff.name }}</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                                    {{ staff.role }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 font-mono">{{ staff.username }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-center font-semibold text-slate-800 dark:text-slate-200">
                                            {{ staff.orders_count }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-300 font-medium whitespace-nowrap font-mono">
                                            {{ formatMoney(staff.orders_value) }}
                                        </td>
                                        <td class="px-5 py-3 text-right font-semibold whitespace-nowrap font-mono" :class="staff.discounts_given > 0 ? 'text-pink-600 dark:text-pink-400' : 'text-slate-400'">
                                            {{ staff.discounts_given > 0 ? '-' + formatMoney(staff.discounts_given) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right font-black text-emerald-600 dark:text-emerald-400 whitespace-nowrap font-mono">
                                            {{ formatMoney(staff.net_collections) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-slate-600 dark:text-slate-400 font-medium whitespace-nowrap font-mono">
                                            {{ formatMoney(staff.cash_collected) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-slate-600 dark:text-slate-400 font-medium whitespace-nowrap font-mono">
                                            {{ formatMoney(staff.bank_collected) }}
                                        </td>
                                        <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-400 font-semibold">
                                            {{ staff.collected_count }}
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <button
                                                type="button"
                                                @click="filterByUser(staff.user_id)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 transition cursor-pointer"
                                            >
                                                <span>Audit Actions</span>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Promotional Discounts & Savings Performance ──────────────── -->
                    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 pb-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center text-lg">
                                    🏷️
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                                        Promotions & Discounts Performance
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Summary of promo campaigns, line-item service deductions, and customer savings
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Link
                                    :href="safeRoute('laundry.discounts.index')"
                                    class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-pink-50 hover:bg-pink-100 dark:bg-pink-950/40 dark:hover:bg-pink-900/40 text-pink-700 dark:text-pink-300 transition flex items-center gap-1.5"
                                >
                                    <span>Manage Promos</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </Link>
                            </div>
                        </div>

                        <!-- 3 Quick Summary Pills -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 bg-slate-50/60 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700">
                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700">
                                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Total Discounts Granted</span>
                                <div class="text-lg font-black text-pink-600 dark:text-pink-400 mt-0.5">{{ formatMoney(metrics.total_discounts || 0) }}</div>
                                <span class="text-[11px] text-slate-500">{{ metrics.discounted_orders_count || 0 }} order(s) received savings</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700">
                                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">General Order Promos</span>
                                <div class="text-lg font-black text-indigo-600 dark:text-indigo-400 mt-0.5">{{ formatMoney(metrics.order_discounts_total || 0) }}</div>
                                <span class="text-[11px] text-slate-500">{{ metrics.general_discount_orders_count || 0 }} order-wide deduction(s)</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700">
                                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Service-Level Promos</span>
                                <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ formatMoney(metrics.service_discounts_total || 0) }}</div>
                                <span class="text-[11px] text-slate-500">{{ metrics.service_discount_items_count || 0 }} service line deduction(s)</span>
                            </div>
                        </div>

                        <!-- Campaigns Performance Table -->
                        <div v-if="!discounts_performance || discounts_performance.length === 0" class="py-10 text-center text-xs text-slate-400">
                            No promotional campaign discounts were recorded for this timeframe.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3">Campaign / Promo Name</th>
                                        <th class="px-5 py-3">Scope & Target</th>
                                        <th class="px-5 py-3 text-center">Type / Rate</th>
                                        <th class="px-5 py-3 text-center">Volume Applied</th>
                                        <th class="px-5 py-3 text-right font-black">Total Customer Savings</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="d in discounts_performance" :key="d.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-5 py-3">
                                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span>{{ d.scope === 'general' ? '🏷️' : (d.scope === 'service' ? '🧺' : '✂️') }}</span>
                                                <span>{{ d.name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                                :class="{
                                                    'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800': d.scope === 'general',
                                                    'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800': d.scope === 'service',
                                                    'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600': d.scope === 'manual',
                                                }"
                                            >
                                                {{ d.scope_label }}
                                            </span>
                                            <span v-if="d.target" class="text-[11px] text-slate-400 block mt-0.5 truncate max-w-xs">
                                                Target: {{ d.target }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-center font-medium text-slate-700 dark:text-slate-300">
                                            <span v-if="d.type === 'percentage'">{{ d.rate }}% Off</span>
                                            <span v-else-if="d.type === 'fixed'">{{ formatMoney(d.rate) }} Off</span>
                                            <span v-else class="text-slate-400">Custom</span>
                                        </td>
                                        <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-400 font-semibold">
                                            {{ d.usage_count }} {{ d.unit_label }}
                                            <span v-if="d.units_count && d.units_count !== d.usage_count" class="text-[10px] text-slate-400 block">
                                                ({{ d.units_count }} pcs/kg)
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-black text-pink-600 dark:text-pink-400">
                                            {{ formatMoney(d.total_savings) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2-Col Grid: Collections by Method + Top Services -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                        <!-- Collections by Payment Method -->
                        <div class="lg:col-span-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs flex flex-col justify-between space-y-4">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                        <span>💳</span>
                                        <span>Collections by Payment Method</span>
                                    </h3>
                                    <span v-if="methods_summary.length > 0" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ methods_summary.length }} Methods
                                    </span>
                                </div>

                                <div v-if="methods_summary.length === 0" class="py-8 text-center text-xs text-slate-400">
                                    No payments recorded in this period.
                                </div>

                                <div v-else class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                            <tr>
                                                <th class="px-4 py-2.5">Method</th>
                                                <th class="px-4 py-2.5 text-right">Payments</th>
                                                <th class="px-4 py-2.5 text-right">Refunds</th>
                                                <th class="px-4 py-2.5 text-right font-black">Net Cash</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            <tr v-for="m in methods_summary" :key="m.method">
                                                <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ m.method }}</td>
                                                <td class="px-4 py-3 text-right text-emerald-600 font-semibold">{{ formatMoney(m.payments) }}</td>
                                                <td class="px-4 py-3 text-right text-rose-600 font-semibold">{{ formatMoney(m.refunds) }}</td>
                                                <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">{{ formatMoney(m.net) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-slate-50/70 dark:bg-slate-900/40 font-bold border-t border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                                            <tr>
                                                <td class="px-4 py-2.5">Total</td>
                                                <td class="px-4 py-2.5 text-right text-emerald-600">{{ formatMoney(metrics.gross_collections) }}</td>
                                                <td class="px-4 py-2.5 text-right text-rose-600">{{ formatMoney(metrics.refunds_total) }}</td>
                                                <td class="px-4 py-2.5 text-right font-black text-slate-900 dark:text-white">{{ formatMoney(metrics.net_collections) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Top Performing Services -->
                        <div class="lg:col-span-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs flex flex-col justify-between space-y-4">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                        <span>🏆</span>
                                        <span>Top Performing Services</span>
                                    </h3>
                                    <span v-if="top_services.length > 0" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                                        Top {{ top_services.length }}
                                    </span>
                                </div>

                                <div v-if="top_services.length === 0" class="py-8 text-center text-xs text-slate-400">
                                    No services data available for this range.
                                </div>

                                <div v-else class="overflow-auto max-h-[260px] scrollbar-thin relative border border-slate-100 dark:border-slate-700/60 rounded-xl">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700 shadow-xs">
                                            <tr>
                                                <th class="px-4 py-2.5 sticky top-0 bg-slate-50 dark:bg-slate-900">Service</th>
                                                <th class="px-4 py-2.5 text-center sticky top-0 bg-slate-50 dark:bg-slate-900">Volume</th>
                                                <th class="px-4 py-2.5 text-right font-black sticky top-0 bg-slate-50 dark:bg-slate-900">Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            <tr v-for="srv in top_services" :key="srv.service_name" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                                                <td class="px-4 py-2.5 font-bold text-slate-900 dark:text-white">{{ srv.service_name }}</td>
                                                <td class="px-4 py-2.5 text-center text-slate-500">
                                                    {{ formatQty(srv.total_qty) }} {{ srv.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-black text-indigo-600 dark:text-indigo-400">
                                                    {{ formatMoney(srv.total_revenue) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outstanding Balances by Customer (Paginated) -->
                    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 pb-3">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                                    <span>🧾</span>
                                    <span>Outstanding Debt by Customer</span>
                                </h3>
                                <span v-if="customerDebtsList.length > 0" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                    {{ outstanding_by_customer?.total ?? customerDebtsList.length }} Debtors
                                </span>
                            </div>
                        </div>

                        <div v-if="customerDebtsList.length === 0" class="py-8 text-center text-xs text-slate-400">
                            No customers with unpaid laundry balances.
                        </div>

                        <div v-else>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                        <tr>
                                            <th class="px-5 py-3">Customer Name</th>
                                            <th class="px-5 py-3">Phone</th>
                                            <th class="px-5 py-3 text-center">Unpaid Orders</th>
                                            <th class="px-5 py-3 text-right font-black">Total Amount Owed</th>
                                            <th class="px-5 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <tr v-for="cust in customerDebtsList" :key="cust.customer_id || cust.customer_name" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                            <td class="px-5 py-3 font-bold text-slate-900 dark:text-white">{{ cust.customer_name }}</td>
                                            <td class="px-5 py-3 text-slate-500 font-mono">{{ cust.customer_phone || '—' }}</td>
                                            <td class="px-5 py-3 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                    {{ cust.orders_count }} order{{ cust.orders_count > 1 ? 's' : '' }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-right font-black text-rose-600 dark:text-rose-400">{{ formatMoney(cust.total_balance) }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <Link
                                                    v-if="cust.customer_id"
                                                    :href="safeRoute('pos.customers.debt-ledger', { customer: cust.customer_id })"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition"
                                                >
                                                    <span>View Ledger</span>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <PaginationLinks
                                v-if="outstanding_by_customer?.links"
                                :links="outstanding_by_customer.links"
                                :meta="outstanding_by_customer.meta ?? outstanding_by_customer"
                            />
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</template>
