<script setup>
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({ order: Object })
const { format } = useCurrency()

function destroy() {
    if (confirm('Are you sure you want to delete this pending purchase order?')) {
        router.delete(route('pos.purchases.destroy', props.order.id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.purchases.index')" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">PO #{{ order.po_number }}</h1>
            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full ml-2" :class="{
                'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400': order.status === 'Pending',
                'bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400': order.status === 'Partial',
                'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400': order.status === 'Received',
            }">{{ order.status }}</span>

            <div class="ml-auto flex gap-2">
                <Link v-if="order.status !== 'Received'" :href="route('pos.purchases.receive', order.id)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium shadow-xs">
                    Receive Items
                </Link>
                <button v-if="order.status === 'Pending'" @click="destroy" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-red-600 dark:text-red-400 text-sm rounded-lg transition font-medium">
                    Delete
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 flex flex-col md:flex-row items-start gap-6">
            <!-- Left: Items -->
            <div class="flex-1 w-full space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">Order Items</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 text-left border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3 font-medium">Item</th>
                                <th class="px-5 py-3 font-medium text-right">Cost</th>
                                <th class="px-5 py-3 font-medium text-right">Ord. Qty</th>
                                <th class="px-5 py-3 font-medium text-right">Rec. Qty</th>
                                <th class="px-5 py-3 font-medium text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="item in order.items" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-5 py-3 text-slate-900 dark:text-white font-medium">{{ item.item_name }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-400 text-right">{{ format(item.cost) }}</td>
                                <td class="px-5 py-3 text-slate-900 dark:text-white font-medium text-right">{{ item.qty }}</td>
                                <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 text-right font-medium">{{ item.received_qty }}</td>
                                <td class="px-5 py-3 text-slate-800 dark:text-slate-300 text-right font-semibold">{{ format(item.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Received Logs -->
                <div v-if="order.received_items?.length" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white text-sm">Receiving History</h2>
                    </div>
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 text-left border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-2 font-medium">Date</th>
                                <th class="px-5 py-2 font-medium">Item</th>
                                <th class="px-5 py-2 font-medium text-right">Qty Received</th>
                                <th class="px-5 py-2 font-medium">Received By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="log in order.received_items" :key="log.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-5 py-2 text-slate-500 dark:text-slate-400">{{ dayjs(log.created_at).format('DD MMM, HH:mm') }}</td>
                                <td class="px-5 py-2 text-slate-800 dark:text-slate-300 font-medium">{{ order.items.find(i => i.item_id === log.item_id)?.item_name }}</td>
                                <td class="px-5 py-2 text-emerald-600 dark:text-emerald-400 text-right font-mono font-bold">{{ log.qty }}</td>
                                <td class="px-5 py-2 text-slate-600 dark:text-slate-400">{{ log.user?.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Meta Summary -->
            <div class="w-full md:w-80 space-y-4 shrink-0">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-3 text-sm shadow-xs">
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Vendor</p>
                        <p class="text-slate-900 dark:text-white font-medium">{{ order.vendor?.name }} <span class="text-slate-500 dark:text-slate-400 text-xs">({{ order.vendor?.company_name }})</span></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Expected Date</p>
                        <p class="text-slate-700 dark:text-slate-300 font-medium">{{ order.expected_date ? dayjs(order.expected_date).format('DD MMM YYYY') : 'Not specified' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Created By</p>
                        <p class="text-slate-700 dark:text-slate-300 font-medium">{{ order.user?.name }}</p>
                    </div>
                    <div v-if="order.notes">
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Notes</p>
                        <p class="text-slate-600 dark:text-slate-300 italic">"{{ order.notes }}"</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-2 text-sm shadow-xs">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Items Total</span>
                        <span>{{ format(order.total_amount - order.shipping_cost + order.discount) }}</span>
                    </div>
                    <div v-if="order.shipping_cost > 0" class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Shipping</span>
                        <span>+{{ format(order.shipping_cost) }}</span>
                    </div>
                    <div v-if="order.discount > 0" class="flex justify-between text-red-600 dark:text-red-400">
                        <span>Discount</span>
                        <span>-{{ format(order.discount) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-900 dark:text-white font-bold pt-3 border-t border-slate-200 dark:border-slate-700 text-lg">
                        <span>Grand Total</span>
                        <span class="text-theme font-black">{{ format(order.total_amount) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
