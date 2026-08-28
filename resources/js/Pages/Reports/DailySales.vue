<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    sales:     Object,  // paginated
    summary:   Object,
    users:     Array,
    canSeeAll: Boolean,
    filters:   Object,
})

const { format } = useCurrency()
const startDate = ref(props.filters.start_date)
const endDate   = ref(props.filters.end_date)
const userId    = ref(props.filters.user_id ?? '')

function doFilter() {
    router.get(route('pos.reports.daily-sales'), {
        start_date: startDate.value,
        end_date:   endDate.value,
        user_id:    userId.value,
    }, { preserveState: true, replace: true })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Sales Report</h1>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap items-end gap-4 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <div v-if="canSeeAll">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">Cashier / Staff</label>
                <select v-model="userId" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition">
                    <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">All Cashiers / Staff</option>
                    <option v-for="u in users" :key="u.id" :value="u.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ u.full_name || u.name || u.username }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">Start Date</label>
                <input v-model="startDate" type="date" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">End Date</label>
                <input v-model="endDate" type="date" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <button @click="doFilter" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-emerald-900/20">Filter</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Summary Cards -->
            <div class="space-y-4">
                <!-- Row 1: Sales Performance (4 Cards) -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Sales</p>
                            <span class="text-base text-slate-400">🧾</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">{{ summary.total_sales }}</p>
                    </div>

                    <div class="bg-white dark:bg-slate-800 border border-emerald-200/80 dark:border-emerald-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-bold">Total Revenue</p>
                            <span class="text-base text-emerald-500">💰</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono truncate" :title="format(summary.total_revenue)">
                            {{ format(summary.total_revenue) }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-slate-800 border border-blue-200/80 dark:border-blue-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-blue-700 dark:text-blue-400 font-bold">Gross Profit</p>
                            <span class="text-base text-blue-500">📈</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-blue-600 dark:text-blue-400 font-mono truncate" :title="format(summary.total_profit)">
                            {{ format(summary.total_profit) }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-slate-800 border border-amber-200/80 dark:border-amber-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-amber-700 dark:text-amber-400 font-bold">Discounts Given</p>
                            <span class="text-base text-amber-500">🏷️</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono truncate" :title="format(summary.total_discount)">
                            {{ format(summary.total_discount) }}
                        </p>
                    </div>
                </div>

                <!-- Row 2: Returns & Refunds (2 Cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-purple-700 dark:text-purple-400 font-semibold">Returned Items</p>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 font-medium">
                                {{ summary.total_return_count || 0 }} {{ (summary.total_return_count || 0) === 1 ? 'record' : 'records' }}
                            </span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-purple-600 dark:text-purple-300 font-mono">{{ summary.total_return_qty || 0 }} <span class="text-xs font-normal text-purple-500">units</span></p>
                    </div>

                    <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-rose-700 dark:text-rose-400 font-semibold">Return Refund Worth</p>
                            <span class="text-base text-rose-500">💸</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-rose-600 dark:text-rose-400 font-mono truncate" :title="format(summary.total_return_worth || 0)">
                            {{ format(summary.total_return_worth || 0) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Date & Time</th>
                            <th class="text-left px-5 py-3 font-semibold">Receipt ID</th>
                            <th class="text-left px-5 py-3 font-semibold">Customer</th>
                            <th class="text-left px-5 py-3 font-semibold">Cashier / Staff</th>
                            <th class="text-right px-5 py-3 font-semibold">Amount</th>
                            <th class="text-right px-5 py-3 font-semibold">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs font-mono">{{ dayjs(sale.created_at).format('DD MMM YYYY HH:mm') }}</td>
                            <td class="px-5 py-3 font-mono text-slate-900 dark:text-white font-bold">
                                <Link :href="route('pos.sales.show', sale.id)" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">{{ sale.receipt_id }}</Link>
                            </td>
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-300 text-xs">{{ sale.customer?.name ?? 'Walk-in Customer' }}</td>
                            <td class="px-5 py-3 text-slate-900 dark:text-white text-xs font-medium">
                                {{ sale.user?.full_name || sale.user?.name || sale.user?.username || '—' }}
                            </td>
                            <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 font-bold font-mono text-right">{{ format(sale.final_total) }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-400 text-right capitalize text-xs font-medium">{{ sale.payment_method }}</td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">No sales recorded matching current filters.</td>
                        </tr>
                    </tbody>
                </table>
                <PaginationLinks :links="sales.links" :meta="sales.meta ?? sales" />
            </div>
        </div>
    </div>
</template>
