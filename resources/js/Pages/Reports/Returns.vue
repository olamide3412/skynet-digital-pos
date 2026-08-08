<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    returns:   Object,  // paginated
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
    router.get(route('pos.reports.returns'), {
        start_date: startDate.value,
        end_date:   endDate.value,
        user_id:    userId.value,
    }, { preserveState: true, replace: true })
}

function getUnitsPerUnitLevel(item, unitUsed = 'unit') {
    if (!item) return 1
    const u = String(unitUsed).toLowerCase()
    const unitsPerPack = Math.max(1, Number(item.units_per_pack || 1))
    const packsPerCarton = Math.max(1, Number(item.packs_per_carton || 1))
    if (u === 'carton') return packsPerCarton * unitsPerPack
    if (u === 'pack') return unitsPerPack
    return 1
}

function getBaseUnits(rItem) {
    if (!rItem) return 0
    const qty = Number(rItem.qty || 0)
    const multiplier = getUnitsPerUnitLevel(rItem.item, rItem.unit_used)
    return qty * multiplier
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Returned Items Report</h1>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap items-end gap-4 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <div v-if="canSeeAll">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">Cashier / Staff</label>
                <select v-model="userId" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition">
                    <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">All Cashiers / Staff</option>
                    <option v-for="u in users" :key="u.id" :value="u.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ u.full_name || u.name || u.username }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">Start Date</label>
                <input v-model="startDate" type="date" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">End Date</label>
                <input v-model="endDate" type="date" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-purple-500 transition" />
            </div>
            <button @click="doFilter" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-purple-900/20">Filter</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Summary Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Return Records</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white font-mono">{{ summary.total_returns }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Items Returned</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono">{{ summary.total_items_qty }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Restocked Base Units</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">+{{ summary.total_base_units }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Total Refund Worth</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 font-mono">{{ format(summary.total_refund_worth) }}</p>
                </div>
            </div>

            <!-- Return Items Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold">Date & Time</th>
                            <th class="text-left px-5 py-3 font-semibold">Receipt / Ref</th>
                            <th class="text-left px-5 py-3 font-semibold">Item Name</th>
                            <th class="text-center px-5 py-3 font-semibold">Qty & Unit</th>
                            <th class="text-center px-5 py-3 font-semibold">Restocked Stock</th>
                            <th class="text-right px-5 py-3 font-semibold">Refund Worth</th>
                            <th class="text-left px-5 py-3 font-semibold">Reason & Staff</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="item in returns.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                {{ dayjs(item.created_at).format('DD MMM YYYY HH:mm') }}
                            </td>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900 dark:text-white">
                                {{ item.sale?.receipt_id || 'Direct Return' }}
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-900 dark:text-white">
                                {{ item.item_name }}
                            </td>
                            <td class="px-5 py-3 text-center font-semibold text-slate-800 dark:text-slate-200">
                                {{ item.qty }} {{ item.unit_used || 'unit' }}(s)
                            </td>
                            <td class="px-5 py-3 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ getBaseUnits(item) }} Base Units
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-red-600 dark:text-red-400">
                                {{ format(item.refund_amount) }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-400">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ item.return_reason || 'No reason provided' }}</div>
                                <div class="text-[11px] text-slate-400">By: {{ item.user?.full_name || item.user?.name || 'Staff' }}</div>
                            </td>
                        </tr>
                        <tr v-if="!returns.data || !returns.data.length">
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500 text-sm">
                                No return transactions found for the selected date range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <PaginationLinks :links="returns.links" />
        </div>
    </div>
</template>
