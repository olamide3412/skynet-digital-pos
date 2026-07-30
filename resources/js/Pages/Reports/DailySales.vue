<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    sales:   Object,  // paginated
    summary: Object,
    filters: Object,
})

const { format } = useCurrency()
const startDate = ref(props.filters.start_date)
const endDate   = ref(props.filters.end_date)

function doFilter() {
    router.get(route('pos.reports.daily-sales'), {
        start_date: startDate.value,
        end_date:   endDate.value,
    }, { preserveState: true, replace: true })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Daily Sales Report</h1>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-700 flex items-end gap-4 flex-shrink-0 bg-slate-800/50">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Start Date</label>
                <input v-model="startDate" type="date" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">End Date</label>
                <input v-model="endDate" type="date" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <button @click="doFilter" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium">Filter</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Summary -->
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <p class="text-xs text-slate-400 mb-1">Total Transactions</p>
                    <p class="text-2xl font-bold text-white">{{ summary.total_sales }}</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <p class="text-xs text-slate-400 mb-1">Total Revenue</p>
                    <p class="text-2xl font-bold text-emerald-400">{{ format(summary.total_revenue) }}</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <p class="text-xs text-slate-400 mb-1">Discounts Given</p>
                    <p class="text-2xl font-bold text-red-400">{{ format(summary.total_discount) }}</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                    <p class="text-xs text-slate-400 mb-1">Tax Collected</p>
                    <p class="text-2xl font-bold text-amber-400">{{ format(summary.total_tax) }}</p>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Date & Time</th>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Receipt ID</th>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Customer</th>
                            <th class="text-right px-5 py-3 text-slate-400 font-medium">Amount</th>
                            <th class="text-right px-5 py-3 text-slate-400 font-medium">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-400 text-xs">{{ dayjs(sale.created_at).format('DD MMM YYYY HH:mm') }}</td>
                            <td class="px-5 py-3 font-mono text-white">
                                <Link :href="route('pos.sales.show', sale.id)" class="hover:text-emerald-400 transition">{{ sale.receipt_id }}</Link>
                            </td>
                            <td class="px-5 py-3 text-slate-300">{{ sale.customer?.name ?? 'Walk-in Customer' }}</td>
                            <td class="px-5 py-3 text-emerald-400 font-medium text-right">{{ format(sale.final_total) }}</td>
                            <td class="px-5 py-3 text-slate-400 text-right capitalize">{{ sale.payment_method }}</td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">No sales recorded in this period.</td>
                        </tr>
                    </tbody>
                </table>
                <PaginationLinks :links="sales.links" :meta="sales.meta ?? sales" />
            </div>
        </div>
    </div>
</template>
