<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    sales:     Object,
    summary:   Object,
    users:     Array,
    canSeeAll: Boolean,
    filters:   Object,
})

const { format } = useCurrency()
const from   = ref(props.filters?.from ?? '')
const to     = ref(props.filters?.to ?? '')
const userId = ref(props.filters?.user_id ?? '')

function doFilter() {
    router.get(route('pos.sales.index'), {
        from:    from.value,
        to:      to.value,
        user_id: userId.value,
    }, { preserveState: true, replace: true })
}

function destroy(id, receiptId) {
    if (confirm(`Delete sale #${receiptId}? This cannot be undone.`)) {
        router.delete(route('pos.sales.destroy', id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-white">Sales History</h1>
                <p class="text-xs text-slate-400">View transaction history and filter by date range or cashier staff.</p>
            </div>
            
            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <select v-if="canSeeAll" v-model="userId" class="bg-slate-700 text-white px-3 py-1.5 rounded-lg text-xs border border-slate-600 outline-none">
                    <option value="">All Cashiers / Staff</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.full_name || u.name || u.username }}</option>
                </select>

                <input v-model="from" type="date" class="bg-slate-700 text-white px-3 py-1.5 rounded-lg text-xs border border-slate-600 outline-none" />
                <span class="text-slate-500 text-xs">to</span>
                <input v-model="to" type="date" class="bg-slate-700 text-white px-3 py-1.5 rounded-lg text-xs border border-slate-600 outline-none" />
                <button @click="doFilter" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-emerald-900/20">
                    Filter
                </button>
            </div>
        </div>

        <!-- Filtered Summary KPI Cards -->
        <div v-if="summary" class="px-6 py-3 bg-slate-800/40 border-b border-slate-700/60 grid grid-cols-2 md:grid-cols-4 gap-4 flex-shrink-0">
            <div class="bg-slate-800 p-3 rounded-xl border border-slate-700/80">
                <span class="text-slate-400 text-xs block">Transactions</span>
                <span class="text-lg font-bold text-white font-mono">{{ summary.total_sales }}</span>
            </div>
            <div class="bg-slate-800 p-3 rounded-xl border border-slate-700/80">
                <span class="text-slate-400 text-xs block">Total Revenue</span>
                <span class="text-lg font-bold text-emerald-400 font-mono">{{ format(summary.total_revenue) }}</span>
            </div>
            <div class="bg-slate-800 p-3 rounded-xl border border-slate-700/80">
                <span class="text-slate-400 text-xs block">Total Discounts</span>
                <span class="text-lg font-bold text-red-400 font-mono">{{ format(summary.total_discount) }}</span>
            </div>
            <div class="bg-slate-800 p-3 rounded-xl border border-slate-700/80">
                <span class="text-slate-400 text-xs block">Total Gross Profit</span>
                <span class="text-lg font-bold text-blue-400 font-mono">{{ format(summary.total_profit) }}</span>
            </div>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Receipt</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Customer</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Cashier / Staff</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Method</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-medium">Total</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-medium">Profit</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3">
                                <Link :href="route('pos.sales.show', sale.id)"
                                    class="text-blue-400 hover:text-blue-300 font-mono text-xs transition">
                                    {{ sale.receipt_id }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-300 text-xs">{{ sale.customer?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-white text-xs font-medium">
                                {{ sale.user?.full_name || sale.user?.name || sale.user?.username || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-emerald-500/20 text-emerald-400': sale.payment_method === 'Cash',
                                        'bg-blue-500/20 text-blue-400': sale.payment_method === 'Bank Transfer',
                                        'bg-purple-500/20 text-purple-400': sale.payment_method === 'Split',
                                        'bg-red-500/20 text-red-400': sale.payment_method === 'Debt',
                                    }">
                                    {{ sale.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-white font-mono">{{ format(sale.final_total) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-400 text-sm font-mono">{{ format(sale.profit_made) }}</td>
                            <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap font-mono">
                                {{ dayjs(sale.created_at).format('DD-MMM-YY HH:mm') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('pos.sales.show', sale.id)"
                                        class="text-xs text-slate-400 hover:text-white transition">View</Link>
                                    <button @click="destroy(sale.id, sale.receipt_id)"
                                        class="text-xs text-red-400 hover:text-red-300 transition">Del</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="px-4 py-12 text-center text-slate-500">No sales matching current filters.</td>
                        </tr>
                    </tbody>
                </table>
                <!-- Pagination -->
                <div v-if="sales.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-700 text-xs text-slate-400">
                    <span>{{ sales.from }}–{{ sales.to }} of {{ sales.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="sales.prev_page_url" :href="sales.prev_page_url" class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Prev</Link>
                        <Link v-if="sales.next_page_url" :href="sales.next_page_url" class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
