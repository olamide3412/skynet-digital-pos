<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    transactions: Object,
    filters: Object,
})

const type = ref(props.filters?.type ?? '')

function doFilter() {
    router.get(route('pos.inventory.index'), { type: type.value }, { preserveState: true, replace: true })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Inventory Logs</h1>
            <Link :href="route('pos.inventory.adjust')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-emerald-900/20">
                Make Adjustment
            </Link>
        </div>

        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex gap-2 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <select v-model="type" @change="doFilter" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition">
                <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">All Transactions</option>
                <option value="Initial Stock" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Initial Stock</option>
                <option selected disabled class="bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold">── Purchases ──</option>
                <option value="Purchase Receive" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Purchase Receive</option>
                <option selected disabled class="bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold">── Sales & Returns ──</option>
                <option value="Sale Deduction" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Sale Deduction</option>
                <option value="Return Restock" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Return Restock</option>
                <option selected disabled class="bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold">── Manual ──</option>
                <option value="Manual Addition" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Manual Addition</option>
                <option value="Manual Deduction" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Manual Deduction</option>
            </select>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Date</th>
                            <th class="text-left px-4 py-3 font-semibold">Item</th>
                            <th class="text-left px-4 py-3 font-semibold">Type</th>
                            <th class="text-right px-4 py-3 font-semibold">Qty</th>
                            <th class="text-left px-4 py-3 font-semibold">Reason/Ref</th>
                            <th class="text-left px-4 py-3 font-semibold">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="log in transactions.data" :key="log.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs font-mono">{{ dayjs(log.created_at).format('DD MMM YYYY HH:mm') }}</td>
                            <td class="px-4 py-3 text-slate-900 dark:text-white font-medium">{{ log.item?.item_name ?? '#'+log.item_id }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-600/50 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    {{ log.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold" :class="log.qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ log.qty > 0 ? '+' : '' }}{{ log.qty }}
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">
                                {{ log.notes }} 
                                <span v-if="log.reference_id" class="text-blue-600 dark:text-blue-400 font-mono">({{ log.reference_id }})</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs font-medium">{{ log.user?.name }}</td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No inventory transactions found.</td>
                        </tr>
                    </tbody>
                </table>
               <div v-if="transactions.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="transactions.prev_page_url" :href="transactions.prev_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Prev</Link>
                        <Link v-if="transactions.next_page_url" :href="transactions.next_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
