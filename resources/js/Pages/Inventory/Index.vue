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
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">Inventory Logs</h1>
            <Link :href="route('pos.inventory.adjust')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium">
                Make Adjustment
            </Link>
        </div>

        <div class="px-6 py-3 border-b border-slate-700 flex gap-2 flex-shrink-0">
            <select v-model="type" @change="doFilter" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition">
                <option value="">All Transactions</option>
                <option value="Initial Stock">Initial Stock</option>
                <option selected disabled>── Purchases ──</option>
                <option value="Purchase Receive">Purchase Receive</option>
                <option selected disabled>── Sales & Returns ──</option>
                <option value="Sale Deduction">Sale Deduction</option>
                <option value="Return Restock">Return Restock</option>
                <option selected disabled>── Manual ──</option>
                <option value="Manual Addition">Manual Addition</option>
                <option value="Manual Deduction">Manual Deduction</option>
            </select>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Date</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Item</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Type</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-medium">Qty</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Reason/Ref</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="log in transactions.data" :key="log.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-400 text-xs">{{ dayjs(log.created_at).format('DD MMM YYYY HH:mm') }}</td>
                            <td class="px-4 py-3 text-white font-medium">{{ log.item?.item_name ?? '#'+log.item_id }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 text-xs rounded-full bg-slate-600/50 text-slate-300">
                                    {{ log.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono" :class="log.qty > 0 ? 'text-emerald-400' : 'text-red-400'">
                                {{ log.qty > 0 ? '+' : '' }}{{ log.qty }}
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs">
                                {{ log.notes }} 
                                <span v-if="log.reference_id" class="text-blue-400">({{ log.reference_id }})</span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs">{{ log.user?.name }}</td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">No inventory transactions found.</td>
                        </tr>
                    </tbody>
                </table>
               <div v-if="transactions.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-700 text-xs text-slate-400">
                    <span>{{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="transactions.prev_page_url" :href="transactions.prev_page_url" class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Prev</Link>
                        <Link v-if="transactions.next_page_url" :href="transactions.next_page_url" class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
