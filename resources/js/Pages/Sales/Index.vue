<script setup>
import { ref } from 'vue'
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
const from   = ref(props.filters?.from ?? '')
const to     = ref(props.filters?.to ?? '')
const userId = ref(props.filters?.user_id ?? '')

const selectedSale     = ref(null)
const showReceiptModal = ref(false)

function doFilter() {
    router.get(route('pos.sales.index'), {
        from:    from.value,
        to:      to.value,
        user_id: userId.value,
    }, { preserveState: true, replace: true })
}

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
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Sales History</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">View transaction history and filter by date range or cashier staff.</p>
            </div>
            
            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <select v-if="canSeeAll" v-model="userId" class="bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 outline-none">
                    <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">All Cashiers / Staff</option>
                    <option v-for="u in users" :key="u.id" :value="u.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ u.full_name || u.name || u.username }}</option>
                </select>

                <input v-model="from" type="date" class="bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 outline-none" />
                <span class="text-slate-500 dark:text-slate-400 text-xs">to</span>
                <input v-model="to" type="date" class="bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 outline-none" />
                <button @click="doFilter" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-emerald-900/20">
                    Filter
                </button>
            </div>
        </div>

        <!-- Filtered Summary KPI Cards -->
        <div v-if="summary" class="px-6 py-3 bg-slate-100/50 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-700/60 grid grid-cols-2 md:grid-cols-4 gap-4 flex-shrink-0">
            <div class="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 shadow-xs">
                <span class="text-slate-500 dark:text-slate-400 text-xs block font-medium">Transactions</span>
                <span class="text-lg font-bold text-slate-900 dark:text-white font-mono">{{ summary.total_sales }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 shadow-xs">
                <span class="text-slate-500 dark:text-slate-400 text-xs block font-medium">Total Revenue</span>
                <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ format(summary.total_revenue) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 shadow-xs">
                <span class="text-slate-500 dark:text-slate-400 text-xs block font-medium">Total Discounts</span>
                <span class="text-lg font-bold text-red-600 dark:text-red-400 font-mono">{{ format(summary.total_discount) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 shadow-xs">
                <span class="text-slate-500 dark:text-slate-400 text-xs block font-medium">Total Gross Profit</span>
                <span class="text-lg font-bold text-blue-600 dark:text-blue-400 font-mono">{{ format(summary.total_profit) }}</span>
            </div>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Receipt</th>
                            <th class="text-left px-4 py-3 font-semibold">Customer</th>
                            <th class="text-left px-4 py-3 font-semibold">Cashier / Staff</th>
                            <th class="text-left px-4 py-3 font-semibold">Method</th>
                            <th class="text-right px-4 py-3 font-semibold">Total</th>
                            <th class="text-right px-4 py-3 font-semibold">Profit</th>
                            <th class="text-left px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3">
                                <Link :href="route('pos.sales.show', sale.id)"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-500 font-mono text-xs font-bold transition">
                                    {{ sale.receipt_id }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300 text-xs">{{ sale.customer?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-900 dark:text-white text-xs font-medium">
                                {{ sale.user?.full_name || sale.user?.name || sale.user?.username || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400': sale.payment_method === 'Cash',
                                        'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400': sale.payment_method === 'Bank Transfer',
                                        'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400': sale.payment_method === 'Split',
                                        'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400': sale.payment_method === 'Debt',
                                    }">
                                    {{ sale.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-white font-mono">{{ format(sale.final_total) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 text-sm font-mono font-bold">{{ format(sale.profit_made) }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs whitespace-nowrap font-mono">
                                {{ dayjs(sale.created_at).format('DD-MMM-YY HH:mm') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button @click="openReceipt(sale)" class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 font-semibold transition flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Print
                                    </button>
                                    <Link :href="route('pos.sales.show', sale.id)"
                                        class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">View</Link>
                                    <button @click="destroy(sale.id, sale.receipt_id)"
                                        class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition">Del</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No sales matching current filters.</td>
                        </tr>
                    </tbody>
                </table>
                <!-- Pagination -->
                <div v-if="sales.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ sales.from }}–{{ sales.to }} of {{ sales.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="sales.prev_page_url" :href="sales.prev_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Prev</Link>
                        <Link v-if="sales.next_page_url" :href="sales.next_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Next</Link>
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
