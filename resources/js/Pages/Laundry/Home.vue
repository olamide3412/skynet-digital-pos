<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recent_orders: {
        type: Array,
        default: () => [],
    },
    user_role: {
        type: String,
        default: null,
    },
})

const page = usePage()
const authUser = computed(() => page.props.auth?.user ?? {})
const userRole = computed(() => {
    return props.user_role || authUser.value?.role_display || (authUser.value?.is_super_admin ? 'Admin' : 'Laundry Cashier')
})
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')

const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const statusBadgeClass = (status) => {
    switch (status) {
        case 'received':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800'
        case 'in_progress':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800'
        case 'ready':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        case 'collected':
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
        case 'cancelled':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800'
        default:
            return 'bg-slate-100 text-slate-700'
    }
}

const statusLabel = (status) => {
    switch (status) {
        case 'received': return 'Received'
        case 'in_progress': return 'In Progress'
        case 'ready': return 'Ready for Pickup'
        case 'collected': return 'Collected'
        case 'cancelled': return 'Cancelled'
        default: return status
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white">Laundry Operations Overview</h1>
                    <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shadow-2xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-slate-500 dark:text-slate-400 font-normal">Active User:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ authUser.name || 'User' }}</span>
                        <span class="text-indigo-300 dark:text-indigo-600">|</span>
                        <span class="uppercase tracking-wider text-[10px] font-black text-indigo-700 dark:text-indigo-300">{{ userRole }}</span>
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Branch: <span class="font-medium text-slate-700 dark:text-slate-300">{{ page.props.current_branch?.name }}</span> • Garment intake, live stages pipeline, and pickup collections
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <Link
                    v-if="$page.props.auth?.permissions?.canViewEndOfDay"
                    :href="safeRoute('laundry.reports.end-of-day')"
                    class="px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-emerald-200 dark:border-emerald-800/40"
                    title="View Cashier End of Day Reconciliation"
                >
                    <span>🧾 End of Day</span>
                </Link>
                <Link
                    :href="safeRoute('laundry.orders.create')"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-indigo-900/20"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Intake Order</span>
                </Link>
                <Link
                    :href="safeRoute('laundry.board.index')"
                    class="px-3.5 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                >
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z"/></svg>
                    <span>Workflow Board</span>
                </Link>
            </div>
        </div>

        <!-- Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-6">

            <!-- Welcome / Quick Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-indigo-700 via-indigo-600 to-cyan-600 text-white shadow-xl shadow-indigo-600/10">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-white/20 text-white backdrop-blur-xs border border-white/30 shadow-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                            <span>{{ userRole }}</span>
                        </span>
                        <span class="text-xs text-indigo-100 font-medium">
                            Signed in as <strong class="text-white">{{ authUser.name || 'Staff' }}</strong>
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                        Laundry Operations
                    </h2>
                    <p class="text-xs sm:text-sm text-indigo-100">
                        Intake garments, monitor washing & pressing progress, and process customer pickups seamlessly.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <Link
                        :href="safeRoute('laundry.orders.create')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-indigo-700 font-bold text-xs sm:text-sm hover:bg-indigo-50 shadow-md transition transform active:scale-95"
                    >
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>New Intake Order</span>
                    </Link>

                    <Link
                        :href="safeRoute('laundry.board.index')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-800/60 hover:bg-indigo-800/80 text-white font-medium text-xs sm:text-sm backdrop-blur-xs border border-white/20 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                        </svg>
                        <span>Workflow Board</span>
                    </Link>

                    <Link
                        :href="safeRoute('laundry.orders.index')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-medium text-xs sm:text-sm backdrop-blur-xs border border-white/20 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>All Orders</span>
                    </Link>
                </div>
            </div>

            <!-- Stats Metric Cards Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5 sm:gap-4">
                <!-- Received Today -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Received Today</span>
                        <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.received_today }}</span>
                    </div>
                </div>

                <!-- In Progress -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">In Progress</span>
                        <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.in_progress }}</span>
                    </div>
                </div>

                <!-- Ready for Pickup -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Ready for Pickup</span>
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.ready }}</span>
                    </div>
                </div>

                <!-- Overdue -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Overdue</span>
                        <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black" :class="stats.overdue > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'">
                            {{ stats.overdue }}
                        </span>
                    </div>
                </div>

                <!-- Cash Collected Today -->
                <div class="col-span-2 sm:col-span-1 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Cash Collected Today</span>
                            <span class="block text-[10px] text-cyan-600 dark:text-cyan-400 font-medium">My Cash Intake ({{ userRole }})</span>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xl sm:text-2xl font-black text-cyan-700 dark:text-cyan-300">
                            {{ formatMoney(stats.cash_today) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Section -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">
                            Recent Laundry Orders
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Latest intake orders registered for this branch
                        </p>
                    </div>

                    <Link
                        :href="safeRoute('laundry.orders.index')"
                        class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 flex items-center gap-1 transition"
                    >
                        <span>View All Orders</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <div v-if="recent_orders.length === 0" class="py-12 text-center text-slate-400 dark:text-slate-500">
                    <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="text-sm font-medium">No laundry orders yet today.</p>
                    <Link
                        :href="safeRoute('laundry.orders.create')"
                        class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition"
                    >
                        Create First Order
                    </Link>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3">Order #</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Total</th>
                                <th class="px-5 py-3 text-right">Balance Due</th>
                                <th class="px-5 py-3">Due Date</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr
                                v-for="order in recent_orders"
                                :key="order.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition"
                            >
                                <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    <Link :href="safeRoute('laundry.orders.show', { order: order.id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ order.order_number }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-slate-800 dark:text-slate-200">{{ order.customer_name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ order.customer_phone }}</p>
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span :class="['px-2.5 py-1 rounded-full text-[11px] font-semibold border', statusBadgeClass(order.status)]">
                                        {{ statusLabel(order.status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                    {{ formatMoney(order.total) }}
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap font-bold">
                                    <span :class="order.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ order.balance > 0 ? formatMoney(order.balance) : 'Paid' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-slate-600 dark:text-slate-300">{{ order.due_at || '—' }}</span>
                                        <span v-if="order.is_overdue" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                            OVERDUE
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <Link
                                        :href="safeRoute('laundry.orders.show', { order: order.id })"
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition"
                                    >
                                        Details →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            </div>
        </div>
    </div>
</template>
