<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/LaundryLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'

const props = defineProps({
    orders: {
        type: Object,
        required: true,
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

const currentView = ref(props.filters.view || 'board')
const searchQuery = ref(props.filters.search || '')
const selectedStatus = ref(props.filters.status || '')
const filterOverdue = ref(Boolean(props.filters.overdue))
const filterDueToday = ref(Boolean(props.filters.due_today))
const filterUnclaimed = ref(Boolean(props.filters.unclaimed))
const filterUnpaid = ref(Boolean(props.filters.unpaid))

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
            view: currentView.value,
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

const setView = (view) => {
    currentView.value = view
    applyFilters()
}

const quickAdvanceStatus = (order, nextStatus) => {
    router.post(
        safeRoute('laundry.orders.update-status', { order: order.id }),
        { status: nextStatus, note: 'Quick stage advance' },
        { preserveScroll: true }
    )
}

const columns = [
    { key: 'received', label: 'Received', color: 'blue' },
    { key: 'in_progress', label: 'In Progress', color: 'amber' },
    { key: 'ready', label: 'Ready for Pickup', color: 'emerald' },
    { key: 'collected', label: 'Collected', color: 'slate' },
]

const ordersList = computed(() => props.orders.data || [])

const getColumnOrders = (statusKey) => {
    return ordersList.value.filter(o => o.status === statusKey)
}

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
</script>

<template>
    <LaundryLayout title="Orders & Board">
        <div class="max-w-7xl mx-auto space-y-5">

            <!-- Control Bar (Search, Filters & View Toggle) -->
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
                            class="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Right Controls (View Toggle & New Order) -->
                    <div class="flex items-center gap-2.5 justify-end">
                        <!-- View Mode Switcher -->
                        <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                            <button
                                @click="setView('board')"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition',
                                    currentView === 'board'
                                        ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                ]"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                                </svg>
                                <span>Board</span>
                            </button>

                            <button
                                @click="setView('list')"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition',
                                    currentView === 'list'
                                        ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                ]"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                                <span>List</span>
                            </button>
                        </div>

                        <!-- New Order Button -->
                        <Link
                            :href="safeRoute('laundry.orders.create')"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm flex items-center gap-1.5 shadow-sm transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>New Intake</span>
                        </Link>
                    </div>
                </div>

                <!-- Filter Chips Row -->
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs font-medium text-slate-500 mr-1">Quick Filters:</span>

                    <button
                        @click="toggleFilter('overdue')"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold border transition flex items-center gap-1',
                            filterOverdue
                                ? 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800'
                                : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        <span>Overdue</span>
                        <span v-if="board_counts.overdue" class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-200 dark:bg-rose-900 text-rose-800 dark:text-rose-200">
                            {{ board_counts.overdue }}
                        </span>
                    </button>

                    <button
                        @click="toggleFilter('due_today')"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold border transition',
                            filterDueToday
                                ? 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800'
                                : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        Due Today
                    </button>

                    <button
                        @click="toggleFilter('unpaid')"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold border transition',
                            filterUnpaid
                                ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800'
                                : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        Has Balance Due
                    </button>

                    <button
                        @click="toggleFilter('unclaimed')"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold border transition',
                            filterUnclaimed
                                ? 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800'
                                : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/50 dark:text-slate-400 dark:border-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        Unclaimed Ready Orders
                    </button>
                </div>
            </div>

            <!-- 1. KANBAN BOARD VIEW -->
            <div v-if="currentView === 'board'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    class="rounded-2xl bg-slate-100/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 p-3 space-y-3 flex flex-col min-h-[480px]"
                >
                    <!-- Column Header -->
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full" :class="{
                                'bg-blue-500': col.color === 'blue',
                                'bg-amber-500': col.color === 'amber',
                                'bg-emerald-500': col.color === 'emerald',
                                'bg-slate-500': col.color === 'slate',
                            }"></span>
                            <h3 class="font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ col.label }}
                            </h3>
                        </div>
                        <span class="text-xs font-black px-2 py-0.5 rounded-full bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 shadow-2xs">
                            {{ board_counts[col.key] || 0 }}
                        </span>
                    </div>

                    <!-- Column Cards -->
                    <div class="space-y-2.5 flex-1 overflow-y-auto max-h-[calc(100vh-280px)] pr-0.5 scrollbar-thin">
                        <div
                            v-if="getColumnOrders(col.key).length === 0"
                            class="py-10 text-center text-xs text-slate-400 dark:text-slate-500 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl"
                        >
                            No orders in this stage
                        </div>

                        <div
                            v-for="order in getColumnOrders(col.key)"
                            :key="order.id"
                            class="p-3.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs hover:shadow-md transition cursor-pointer group"
                            @click="router.visit(safeRoute('laundry.orders.show', { order: order.id }))"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-extrabold text-xs text-indigo-600 dark:text-indigo-400 group-hover:underline">
                                    {{ order.order_number }}
                                </span>
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
                </div>
            </div>

            <!-- 2. TABLE LIST VIEW -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                <div v-if="ordersList.length === 0" class="py-16 text-center text-slate-400">
                    <p class="text-sm font-medium">No orders matching the active filters.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 font-semibold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3">Order #</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Items</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Total</th>
                                <th class="px-5 py-3 text-right">Balance Due</th>
                                <th class="px-5 py-3">Due Date</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr
                                v-for="order in ordersList"
                                :key="order.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition"
                            >
                                <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    <Link :href="safeRoute('laundry.orders.show', { order: order.id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ order.order_number }}
                                    </Link>
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
                                        {{ order.status }}
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
                                        <span v-if="order.is_overdue" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">
                                            OVERDUE
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

                <!-- Pagination -->
                <div v-if="orders.links && orders.links.length > 3" class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <PaginationLinks :links="orders.links" />
                </div>
            </div>

        </div>
    </LaundryLayout>
</template>
