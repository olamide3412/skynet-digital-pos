<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    board_orders: {
        type: Object,
        default: () => ({}),
    },
    board_counts: {
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
const filterOverdue = ref(Boolean(props.filters.overdue))
const filterDueToday = ref(Boolean(props.filters.due_today))
const filterUnclaimed = ref(Boolean(props.filters.unclaimed))
const filterUnpaid = ref(Boolean(props.filters.unpaid))
const filterExpress = ref(Boolean(props.filters.express))

const hasActiveFilters = computed(() => {
    return Boolean(
        searchQuery.value ||
        filterOverdue.value ||
        filterDueToday.value ||
        filterUnclaimed.value ||
        filterUnpaid.value ||
        filterExpress.value
    )
})

const clearAllFilters = () => {
    searchQuery.value = ''
    filterOverdue.value = false
    filterDueToday.value = false
    filterUnclaimed.value = false
    filterUnpaid.value = false
    filterExpress.value = false
    applyFilters()
}

const applyFilters = () => {
    router.get(
        safeRoute('laundry.board.index'),
        {
            search: searchQuery.value || undefined,
            overdue: filterOverdue.value ? 1 : undefined,
            due_today: filterDueToday.value ? 1 : undefined,
            unclaimed: filterUnclaimed.value ? 1 : undefined,
            unpaid: filterUnpaid.value ? 1 : undefined,
            express: filterExpress.value ? 1 : undefined,
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
    if (filterKey === 'express') filterExpress.value = !filterExpress.value
    applyFilters()
}

const quickAdvanceStatus = (order, nextStatus) => {
    router.post(
        safeRoute('laundry.orders.update-status', { order: order.id }),
        { status: nextStatus, note: 'Quick stage advance from Workflow Board' },
        { preserveScroll: true }
    )
}

const columns = [
    { key: 'received', label: 'Received (Intake)', shortLabel: 'Received', color: 'blue' },
    { key: 'in_progress', label: 'In Progress', shortLabel: 'In Progress', color: 'amber' },
    { key: 'ready', label: 'Ready for Pickup', shortLabel: 'Ready', color: 'emerald' },
]

const activeMobileTab = ref('received')

const localBoardOrders = ref({
    received: props.board_orders?.received ? [...props.board_orders.received] : [],
    in_progress: props.board_orders?.in_progress ? [...props.board_orders.in_progress] : [],
    ready: props.board_orders?.ready ? [...props.board_orders.ready] : [],
})

const columnPages = ref({
    received: 1,
    in_progress: 1,
    ready: 1,
})

const loadingColumn = ref({
    received: false,
    in_progress: false,
    ready: false,
})

watch(() => props.board_orders, (newVal) => {
    if (newVal) {
        localBoardOrders.value = {
            received: newVal.received ? [...newVal.received] : [],
            in_progress: newVal.in_progress ? [...newVal.in_progress] : [],
            ready: newVal.ready ? [...newVal.ready] : [],
        }
        columnPages.value = { received: 1, in_progress: 1, ready: 1 }
    }
}, { deep: true })

const getColumnOrders = (statusKey) => {
    return localBoardOrders.value[statusKey] || []
}

const getColumnTotal = (statusKey) => {
    return Number(props.board_counts?.[statusKey] || 0)
}

const canLoadMore = (statusKey) => {
    return getColumnTotal(statusKey) > getColumnOrders(statusKey).length
}

const loadMoreColumn = async (statusKey) => {
    if (loadingColumn.value[statusKey]) return
    loadingColumn.value[statusKey] = true
    const nextPage = (columnPages.value[statusKey] || 1) + 1

    try {
        const res = await axios.get(safeRoute('laundry.board.column-orders'), {
            params: {
                status: statusKey,
                page: nextPage,
                per_page: 10,
                search: searchQuery.value || undefined,
                overdue: filterOverdue.value ? 1 : undefined,
                due_today: filterDueToday.value ? 1 : undefined,
                unclaimed: filterUnclaimed.value ? 1 : undefined,
                unpaid: filterUnpaid.value ? 1 : undefined,
                express: filterExpress.value ? 1 : undefined,
                start_date: props.filters?.start_date || undefined,
                end_date: props.filters?.end_date || undefined,
            }
        })

        if (res.data?.orders) {
            localBoardOrders.value[statusKey] = [
                ...localBoardOrders.value[statusKey],
                ...res.data.orders
            ]
            columnPages.value[statusKey] = nextPage
        }
    } catch (err) {
        console.error('Failed to load more orders for column ' + statusKey, err)
    } finally {
        loadingColumn.value[statusKey] = false
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Workflow Board</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        Live Production
                    </span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Operational Kanban board tracking garments through Intake, Washing, and Ready stages
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <Link
                    :href="safeRoute('laundry.orders.index')"
                    class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>Orders Table List</span>
                </Link>

                <Link
                    :href="safeRoute('laundry.orders.create')"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-md shadow-indigo-900/20"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Intake Order</span>
                </Link>
            </div>
        </div>

        <!-- Board Workspace Area (Scrollable body so control bar can scroll away on demand) -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-5 lg:p-6 space-y-4">

            <!-- Control Bar (Search, Urgency Filters & Quick Links) -->
            <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-2.5 flex-shrink-0">
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

                    <!-- Right Controls (Collected Orders Shortcut & Orders Table Link) -->
                    <div class="flex flex-wrap items-center gap-2.5 justify-end">
                        <Link
                            :href="safeRoute('laundry.orders.index', { status: 'collected' })"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition border bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-400"
                            title="View all completed and collected orders in paginated table"
                        >
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                            <span>Collected Orders</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                {{ board_counts.collected || 0 }}
                            </span>
                        </Link>

                        <Link
                            :href="safeRoute('laundry.orders.index')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition border bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 border-slate-200 dark:border-slate-700"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <span>Switch to Table List</span>
                        </Link>
                    </div>
                </div>

                <!-- Filter Chips Row -->
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs font-medium text-slate-500 mr-1">Quick Filters:</span>

                    <button
                        @click="toggleFilter('express')"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5',
                            filterExpress
                                ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800 shadow-xs'
                                : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        <span>⚡ Express</span>
                        <span v-if="board_counts.express" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-200 dark:bg-amber-900 text-amber-800 dark:text-amber-200">
                            {{ board_counts.express }}
                        </span>
                    </button>

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
                        <span v-if="board_counts.overdue" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-rose-200 dark:bg-rose-900 text-rose-800 dark:text-rose-200">
                            {{ board_counts.overdue }}
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
                        <span v-if="board_counts.due_today" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-indigo-200 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                            {{ board_counts.due_today }}
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
                        <span v-if="board_counts.unpaid" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-200 dark:bg-amber-900 text-amber-800 dark:text-amber-200">
                            {{ board_counts.unpaid }}
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
                        <span v-if="board_counts.unclaimed" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-purple-200 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                            {{ board_counts.unclaimed }}
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

            <!-- KANBAN BOARD VIEW (Active Production Stages: Received, In-Progress, Ready) -->
            <div class="space-y-3">
                <!-- Mobile Column Switcher Tabs (Visible only on mobile < md) -->
                <div class="flex md:hidden p-1.5 bg-slate-200/80 dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 gap-1.5 shadow-2xs mb-2 flex-shrink-0">
                    <button
                        v-for="col in columns"
                        :key="col.key"
                        type="button"
                        @click="activeMobileTab = col.key"
                        :class="[
                            'flex-1 py-2 px-1 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition text-center',
                            activeMobileTab === col.key
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs ring-1 ring-slate-200/80 dark:ring-slate-700'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <span class="w-2 h-2 rounded-full shrink-0" :class="{
                            'bg-blue-500': col.color === 'blue',
                            'bg-amber-500': col.color === 'amber',
                            'bg-emerald-500': col.color === 'emerald',
                        }"></span>
                        <span class="truncate">{{ col.shortLabel || col.label }}</span>
                        <span
                            class="px-1.5 py-0.2 rounded-full text-[10px] font-black shrink-0"
                            :class="activeMobileTab === col.key ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
                        >
                            {{ board_counts[col.key] || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Columns Grid (Fills generous height, showing 3-4+ cards per column) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 h-[calc(100vh-85px)] min-h-[640px]">
                    <div
                        v-for="col in columns"
                        :key="col.key"
                        :class="[
                            'rounded-2xl bg-slate-100/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 p-3.5 flex flex-col h-full min-h-0 overflow-hidden shadow-xs',
                            activeMobileTab === col.key ? 'flex' : 'hidden md:flex'
                        ]"
                    >
                        <!-- Column Header (flex-shrink-0) -->
                        <div class="flex items-center justify-between px-1 pb-3 flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" :class="{
                                    'bg-blue-500': col.color === 'blue',
                                    'bg-amber-500': col.color === 'amber',
                                    'bg-emerald-500': col.color === 'emerald',
                                }"></span>
                                <h3 class="font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ col.label }}
                                </h3>
                            </div>
                            <span class="text-xs font-black px-2.5 py-0.5 rounded-full bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-2xs">
                                {{ board_counts[col.key] || 0 }}
                            </span>
                        </div>

                        <!-- Column Cards (flex-1 min-h-0: scrolls from top to bottom, filling the full column height!) -->
                        <div class="space-y-2.5 flex-1 min-h-0 overflow-y-auto pr-1 scrollbar-thin">
                            <div
                                v-if="getColumnOrders(col.key).length === 0"
                                class="h-40 flex items-center justify-center text-xs text-slate-400 dark:text-slate-500 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl"
                            >
                                No orders in this stage
                            </div>

                            <div
                                v-for="order in getColumnOrders(col.key)"
                                :key="order.id"
                                :class="[
                                    'p-3.5 rounded-xl bg-white dark:bg-slate-800 border shadow-xs hover:shadow-md transition cursor-pointer group flex-shrink-0 relative overflow-hidden',
                                    order.is_express
                                        ? 'border-amber-300 dark:border-amber-600/80 ring-1 ring-amber-400/40 dark:ring-amber-500/30'
                                        : 'border-slate-200 dark:border-slate-700'
                                ]"
                                @click="router.visit(safeRoute('laundry.orders.show', { order: order.id }))"
                            >
                                <!-- Left amber accent indicator bar for express rush orders -->
                                <div v-if="order.is_express" class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500"></div>

                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-extrabold text-xs text-indigo-600 dark:text-indigo-400 group-hover:underline">
                                            {{ order.order_number }}
                                        </span>
                                        <!-- Flash Sign Express Badge -->
                                        <span
                                            v-if="order.is_express"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wide bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-2xs"
                                            title="⚡ Express Order (Rush Priority)"
                                        >
                                            <span class="text-xs leading-none">⚡</span>
                                            <span>EXPRESS</span>
                                        </span>
                                    </div>
                                    <span v-if="order.is_overdue" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                        OVERDUE
                                    </span>
                                    <span v-else-if="order.is_unclaimed" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                        UNCLAIMED
                                    </span>
                                </div>

                                <p class="font-bold text-sm text-slate-900 dark:text-white mt-1 truncate">
                                    {{ order.customer_name }}
                                </p>
                                <p class="text-[11px] text-slate-400 truncate">
                                    {{ order.customer_phone }}
                                </p>

                                <p v-if="order.items_summary" class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2 bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg">
                                    {{ order.items_summary }}
                                </p>

                                <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700 text-xs">
                                    <div>
                                        <span class="text-slate-400 text-[10px] block">Total</span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(order.total) }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-slate-400 text-[10px] block">Balance</span>
                                        <span :class="order.balance > 0 ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-emerald-600 font-semibold'">
                                            {{ order.balance > 0 ? formatMoney(order.balance) : 'Paid' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Quick Action Stage Advancer -->
                                <div class="mt-3 pt-2 flex items-center justify-between border-t border-slate-100 dark:border-slate-700/60" @click.stop>
                                    <span class="text-[11px] text-slate-400">Due: {{ order.due_at || '—' }}</span>

                                    <button
                                        v-if="order.status === 'received'"
                                        @click="quickAdvanceStatus(order, 'in_progress')"
                                        class="px-2 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 transition"
                                    >
                                        Start Washing →
                                    </button>
                                    <button
                                        v-else-if="order.status === 'in_progress'"
                                        @click="quickAdvanceStatus(order, 'ready')"
                                        class="px-2 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 transition"
                                    >
                                        Mark Ready →
                                    </button>
                                    <Link
                                        v-else-if="order.status === 'ready'"
                                        :href="safeRoute('laundry.orders.show', { order: order.id })"
                                        class="px-2 py-1 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 transition"
                                    >
                                        Pickup Flow →
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Column Footer (Load More & Stats, flex-shrink-0) -->
                        <div v-if="getColumnOrders(col.key).length > 0" class="pt-2 flex-shrink-0">
                            <!-- Load More Button -->
                            <div
                                v-if="canLoadMore(col.key)"
                                class="p-2 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 text-center space-y-1 shadow-xs"
                            >
                                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 px-1 font-medium">
                                    <span>Showing {{ getColumnOrders(col.key).length }} of {{ getColumnTotal(col.key) }}</span>
                                    <span>{{ getColumnTotal(col.key) - getColumnOrders(col.key).length }} remaining</span>
                                </div>
                                <button
                                    type="button"
                                    @click="loadMoreColumn(col.key)"
                                    :disabled="loadingColumn[col.key]"
                                    class="w-full py-1.5 px-3 rounded-lg text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900/60 transition flex items-center justify-center gap-1.5 disabled:opacity-60"
                                >
                                    <svg v-if="loadingColumn[col.key]" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    <span>{{ loadingColumn[col.key] ? 'Loading...' : `Load ${Math.min(10, getColumnTotal(col.key) - getColumnOrders(col.key).length)} More` }}</span>
                                </button>
                                <div class="text-center pt-0.5">
                                    <Link
                                        :href="safeRoute('laundry.orders.index', { status: col.key })"
                                        class="text-[11px] text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 underline decoration-slate-300"
                                    >
                                        View all in Orders Table →
                                    </Link>
                                </div>
                            </div>

                            <!-- All Caught Up / All Shown -->
                            <div
                                v-else
                                class="py-1 text-center text-[11px] text-slate-400 dark:text-slate-500 font-medium"
                            >
                                ✓ All {{ getColumnOrders(col.key).length }} orders shown
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
