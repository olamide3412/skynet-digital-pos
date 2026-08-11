<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({ order: Object })
const { format } = useCurrency()

const form = useForm({
    items: props.order.items.map(i => ({
        id: i.id,
        receive_qty: 0,
        max: i.qty - i.received_qty
    }))
})

function submit() {
    form.post(route('pos.purchases.process-receive', props.order.id))
}

function receiveAll() {
    form.items.forEach(i => i.receive_qty = i.max)
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.purchases.show', order.id)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Receive Inventory</h1>
            </div>
            <span class="font-mono text-emerald-700 dark:text-emerald-400 font-bold bg-emerald-100 dark:bg-emerald-400/10 px-3 py-1 rounded-lg">
                {{ order.po_number }}
            </span>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-4xl mx-auto space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 flex flex-col sm:flex-row gap-4 sm:gap-10 sm:items-center shadow-xs">
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Vendor</p>
                        <p class="text-sm text-slate-900 dark:text-white font-medium">{{ order.vendor?.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Status</p>
                        <span class="px-2.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 font-medium">{{ order.status }}</span>
                    </div>
                    <div class="sm:ml-auto">
                        <button type="button" @click="receiveAll" class="px-4 py-2 text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-white rounded-lg transition">
                            Receive All Remaining
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-left">
                            <tr>
                                <th class="px-5 py-3 text-slate-500 dark:text-slate-400 font-medium">Item</th>
                                <th class="px-5 py-3 text-slate-500 dark:text-slate-400 font-medium text-right">Cost</th>
                                <th class="px-5 py-3 text-slate-500 dark:text-slate-400 font-medium text-right">Ordered</th>
                                <th class="px-5 py-3 text-slate-500 dark:text-slate-400 font-medium text-right">Received</th>
                                <th class="px-5 py-3 text-emerald-600 dark:text-emerald-400 font-bold text-right bg-emerald-50 dark:bg-emerald-900/20 w-40">Qty to Receive</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="(item, index) in order.items" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/20 transition">
                                <td class="px-5 py-3 text-slate-900 dark:text-white font-medium">{{ item.item_name }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300 text-right">{{ format(item.cost) }}</td>
                                <td class="px-5 py-3 text-slate-900 dark:text-white font-medium text-right">{{ item.qty }}</td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 text-right">{{ item.received_qty }}</td>
                                <td class="px-5 py-3 text-right bg-emerald-50/50 dark:bg-emerald-900/10">
                                    <input 
                                        v-model.number="form.items[index].receive_qty" 
                                        type="number" 
                                        min="0" 
                                        :max="form.items[index].max"
                                        :disabled="form.items[index].max === 0"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded border border-slate-300 dark:border-slate-600 text-right outline-none focus:border-emerald-500 disabled:opacity-50 transition" 
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-md transition disabled:opacity-40">
                        {{ form.processing ? 'Processing...' : 'Confirm Receipt' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
