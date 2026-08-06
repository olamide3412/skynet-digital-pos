<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    orders: Object,
    filters: Object,
})

const { format } = useCurrency()
const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

function doFilter() {
    router.get(route('pos.purchases.index'), { search: search.value, status: status.value }, { preserveState: true, replace: true })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Purchase Orders</h1>
            <Link :href="route('pos.purchases.create')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5 shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New PO
            </Link>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex gap-2 flex-shrink-0">
            <input v-model="search" @keydown.enter="doFilter" type="text" placeholder="Search PO number…" class="w-56 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition" />
            <select v-model="status" @change="doFilter" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Partial">Partial</option>
                <option value="Received">Received</option>
            </select>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">PO Number</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Vendor</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Total</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Expected Date</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="order in orders.data" :key="order.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono text-slate-900 dark:text-white font-medium">
                                <Link :href="route('pos.purchases.show', order.id)" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">{{ order.po_number }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-medium">{{ order.vendor?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400 font-semibold text-right">{{ format(order.total_amount) }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ order.expected_date ? dayjs(order.expected_date).format('DD MMM YYYY') : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-0.5 text-xs rounded-full font-medium" :class="{
                                    'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400': order.status === 'Pending',
                                    'bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400': order.status === 'Partial',
                                    'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400': order.status === 'Received',
                                }">{{ order.status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link :href="route('pos.purchases.show', order.id)" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium transition">View</Link>
                                    <Link v-if="order.status !== 'Received'" :href="route('pos.purchases.receive', order.id)" class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 font-semibold transition">Receive Stock</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!orders.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">No purchase orders found.</td>
                        </tr>
                    </tbody>
                </table>
               <div v-if="orders.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ orders.from }}–{{ orders.to }} of {{ orders.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="orders.prev_page_url" :href="orders.prev_page_url" class="px-3 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition">Prev</Link>
                        <Link v-if="orders.next_page_url" :href="orders.next_page_url" class="px-3 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
