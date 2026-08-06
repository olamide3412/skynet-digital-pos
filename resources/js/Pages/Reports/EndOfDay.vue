<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    sales:         Object,  // paginated
    debtPayments:  Object,  // paginated
    summary:       Object,
    itemBreakdown: Array,
    users:         Array,
    canSeeAll:     Boolean,
    filters:       Object,
})

const { format } = useCurrency()
const selectedUser = ref(props.filters.user_id || '')

function doFilter() {
    router.get(route('pos.reports.end-of-day'), {
        user_id: selectedUser.value,
    }, { preserveState: true, replace: true })
}

function fmtTime(val) {
    return dayjs(val).format('HH:mm')
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">

        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">End of Day Report</h1>
            <span class="ml-auto text-xs text-slate-500 dark:text-slate-400 font-mono">{{ dayjs().format('dddd, DD MMM YYYY') }}</span>
        </div>

        <!-- Filter Bar -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex items-end justify-between flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <div class="flex items-center gap-4">
                <div v-if="canSeeAll">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">Cashier / Staff User</label>
                    <select v-model="selectedUser" @change="doFilter"
                        class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition min-w-[220px]">
                        <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">All Cashiers & Staff</option>
                        <option v-for="u in users" :key="u.id" :value="u.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ u.full_name || u.name || u.username }}</option>
                    </select>
                </div>
                <div v-else class="flex items-center gap-2 bg-white dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 px-3 py-2 rounded-lg">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span class="text-sm text-slate-800 dark:text-slate-300 font-medium">My Sales Only</span>
                </div>
            </div>
            <div class="text-slate-500 dark:text-slate-400 text-xs font-mono">
                Today · {{ dayjs().format('DD MMM YYYY') }}
            </div>
        </div>

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- ── Summary Cards ─────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Transactions</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white font-mono">{{ summary.total_sales }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Revenue</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ format(summary.total_revenue) }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Cash Collected</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white font-mono">{{ format(summary.cash_collected) }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Bank Transfers</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400 font-mono">{{ format(summary.bank_collected) }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Debt Recorded</p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-400 font-mono">{{ format(summary.debt_recorded) }}</p>
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700/40 rounded-xl p-4 col-span-1 shadow-xs">
                    <p class="text-xs text-emerald-700 dark:text-emerald-400 mb-1 font-semibold">Debt Recovered</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-300 font-mono">{{ format(summary.debt_recovered) }}</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5 font-medium">{{ debtPayments.total ?? 0 }} payment{{ (debtPayments.total ?? 0) !== 1 ? 's' : '' }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Discounts Given</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400 font-mono">-{{ format(summary.total_discount) }}</p>
                </div>
            </div>

            <!-- ── Items Sold Breakdown Table ───────────────────────────────────── -->
            <div v-if="itemBreakdown && itemBreakdown.length">
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Items Sold Breakdown ({{ selectedUser ? (users.find(u => u.id == selectedUser)?.full_name || users.find(u => u.id == selectedUser)?.name || users.find(u => u.id == selectedUser)?.username || 'Selected Staff') : 'All Staff' }})
                </h2>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold">Item Name</th>
                                <th class="text-left px-5 py-3 font-semibold">Unit Level</th>
                                <th class="text-right px-5 py-3 font-semibold">Quantity Sold</th>
                                <th class="text-right px-5 py-3 font-semibold">Total Amount Made</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="item in itemBreakdown" :key="`${item.item_id}-${item.unit_used}`" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-5 py-3 text-slate-900 dark:text-white font-medium">{{ item.item_name }}</td>
                                <td class="px-5 py-3 text-xs uppercase font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ item.unit_used }}</td>
                                <td class="px-5 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">{{ item.total_qty }}</td>
                                <td class="px-5 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ format(item.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Sales Table ────────────────────────────────────────────────── -->
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Sales Transactions ({{ sales.total ?? sales.data?.length ?? 0 }})
                </h2>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold">Time</th>
                                <th class="text-left px-5 py-3 font-semibold">Receipt ID</th>
                                <th class="text-left px-5 py-3 font-semibold">Cashier / Staff</th>
                                <th class="text-left px-5 py-3 font-semibold">Customer</th>
                                <th class="text-right px-5 py-3 font-semibold">Amount</th>
                                <th class="text-right px-5 py-3 font-semibold">Method</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs font-mono">{{ fmtTime(sale.created_at) }}</td>
                                <td class="px-5 py-3 font-mono text-slate-900 dark:text-white font-bold">
                                    <Link :href="route('pos.sales.show', sale.id)" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                                        {{ sale.receipt_id }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-300 text-xs font-medium">
                                    {{ sale.user?.full_name || sale.user?.name || sale.user?.username || '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-300 text-xs">{{ sale.customer?.name ?? 'Walk-in' }}</td>
                                <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 font-bold font-mono text-right">{{ format(sale.final_total) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span v-if="sale.is_debt" class="text-red-600 dark:text-red-400 text-xs font-bold">Debt</span>
                                    <span v-else class="text-slate-600 dark:text-slate-400 text-xs capitalize font-medium">{{ sale.payment_method }}</span>
                                </td>
                            </tr>
                            <tr v-if="!sales.data?.length">
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400 text-sm">No sales recorded today for selected filters.</td>
                            </tr>
                        </tbody>
                    </table>
                    <PaginationLinks :links="sales.links" :meta="sales.meta ?? sales" />
                </div>
            </div>

            <!-- ── Debt Recovery Table ────────────────────────────────────────── -->
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Debt Recoveries Today ({{ debtPayments.total ?? 0 }})
                    <span v-if="debtPayments.total" class="ml-auto text-emerald-600 dark:text-emerald-400 font-bold text-sm font-mono">
                        Total: {{ format(summary.debt_recovered) }}
                    </span>
                </h2>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-emerald-700/30 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-emerald-900/20 border-b border-slate-200 dark:border-emerald-700/30 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold">Time</th>
                                <th class="text-left px-5 py-3 font-semibold">Reference</th>
                                <th class="text-left px-5 py-3 font-semibold">Customer</th>
                                <th class="text-left px-5 py-3 font-semibold">Processed By</th>
                                <th class="text-left px-5 py-3 font-semibold">Narration</th>
                                <th class="text-right px-5 py-3 font-semibold">Amount Paid</th>
                                <th class="text-right px-5 py-3 font-semibold">Balance After</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="dp in debtPayments.data" :key="dp.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs font-mono">{{ fmtTime(dp.created_at) }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-slate-700 dark:text-slate-300">
                                        {{ dp.reference ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-900 dark:text-slate-300 font-medium text-xs">{{ dp.customer?.name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-400 text-xs font-medium">
                                    {{ dp.user?.full_name || dp.user?.name || dp.user?.username || '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-400 text-xs max-w-[200px] truncate">{{ dp.narration }}</td>
                                <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 font-bold font-mono text-right">{{ format(dp.amount) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span :class="['text-xs font-mono font-bold', dp.balance_after > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-emerald-600 dark:text-emerald-400']">
                                        {{ format(dp.balance_after) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!debtPayments.data?.length">
                                <td colspan="7" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400 text-sm">
                                    No debt recoveries recorded today for selected filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <PaginationLinks :links="debtPayments.links" :meta="debtPayments.meta ?? debtPayments" />
                </div>
            </div>

        </div>
    </div>
</template>
