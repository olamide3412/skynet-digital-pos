<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({ order: Object })
const { format } = useCurrency()

const form = useForm({
    items: props.order.items.map(i => ({
        id: i.id,
        receive_qty: 0,
        location: 'back_store',
        max: i.qty - i.received_qty,
        is_imei_tracked: !!i.item?.is_imei_tracked,
        item_name: i.item_name,
        raw_imeis: '',
        imeis: [],
    }))
})

function updateImeis(itemIndex) {
    const item = form.items[itemIndex]
    const raw = item.raw_imeis || ''
    const lines = raw.split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean)
    // remove duplicates
    item.imeis = Array.from(new Set(lines))
}

const canSubmit = computed(() => {
    let hasReceiving = false
    for (const item of form.items) {
        if (item.receive_qty > 0) {
            hasReceiving = true
            if (item.is_imei_tracked) {
                if (item.imeis.length !== item.receive_qty) {
                    return false
                }
            }
        }
    }
    return hasReceiving
})

function submit() {
    form.post(route('pos.purchases.process-receive', props.order.id))
}

function receiveAll() {
    form.items.forEach(i => i.receive_qty = i.max)
}

function setAllLocation(loc) {
    form.items.forEach(i => i.location = loc)
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
            <form @submit.prevent="submit" class="max-w-5xl mx-auto space-y-6">
                <!-- Top Summary Card -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 flex flex-col sm:flex-row gap-4 sm:gap-8 sm:items-center shadow-xs">
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Vendor</p>
                        <p class="text-sm text-slate-900 dark:text-white font-medium">{{ order.vendor?.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5 uppercase tracking-wider font-semibold">Status</p>
                        <span class="px-2.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 font-medium">{{ order.status }}</span>
                    </div>

                    <!-- Quick Batch Actions -->
                    <div class="sm:ml-auto flex flex-wrap items-center gap-2">
                        <button type="button" @click="setAllLocation('back_store')" class="px-3 py-1.5 text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-650 text-slate-700 dark:text-slate-300 rounded-lg transition">
                            📦 All to Back Store
                        </button>
                        <button type="button" @click="setAllLocation('front_store')" class="px-3 py-1.5 text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-650 text-slate-700 dark:text-slate-300 rounded-lg transition">
                            🛒 All to Front Store
                        </button>
                        <button type="button" @click="receiveAll" class="px-3.5 py-1.5 text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 text-emerald-700 dark:text-emerald-300 rounded-lg transition">
                            Receive All Remaining
                        </button>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-left">
                            <tr>
                                <th class="px-5 py-3 text-slate-500 dark:text-slate-400 font-medium">Item</th>
                                <th class="px-4 py-3 text-slate-500 dark:text-slate-400 font-medium text-right">Cost</th>
                                <th class="px-4 py-3 text-slate-500 dark:text-slate-400 font-medium text-right">Ordered</th>
                                <th class="px-4 py-3 text-slate-500 dark:text-slate-400 font-medium text-right">Received</th>
                                <th class="px-4 py-3 text-slate-500 dark:text-slate-400 font-medium w-48">Receive To</th>
                                <th class="px-4 py-3 text-emerald-600 dark:text-emerald-400 font-bold text-right bg-emerald-50 dark:bg-emerald-900/20 w-36">Qty to Receive</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <template v-for="(item, index) in order.items" :key="item.id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20 transition">
                                    <td class="px-5 py-3 text-slate-900 dark:text-white font-medium">
                                        <div class="flex items-center gap-2">
                                            <span>{{ item.item_name }}</span>
                                            <span v-if="form.items[index].is_imei_tracked" class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                                📱 IMEI Tracked
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300 text-right">{{ format(item.cost) }}</td>
                                    <td class="px-4 py-3 text-slate-900 dark:text-white font-medium text-right">{{ item.qty }}</td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-right">{{ item.received_qty }}</td>

                                    <!-- Receive Location Dropdown (Default: Back Store) -->
                                    <td class="px-4 py-3">
                                        <select
                                            v-model="form.items[index].location"
                                            :disabled="form.items[index].max === 0"
                                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold outline-none focus:border-indigo-500 disabled:opacity-50 transition"
                                        >
                                            <option value="back_store">📦 Back Store (Warehouse)</option>
                                            <option value="front_store">🛒 Front Store (POS Floor)</option>
                                        </select>
                                    </td>

                                    <!-- Qty to receive input -->
                                    <td class="px-4 py-3 text-right bg-emerald-50/50 dark:bg-emerald-900/10">
                                        <input 
                                            v-model.number="form.items[index].receive_qty" 
                                            type="number" 
                                            min="0" 
                                            :max="form.items[index].max"
                                            :disabled="form.items[index].max === 0"
                                            class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded border border-slate-300 dark:border-slate-600 text-right outline-none focus:border-emerald-500 disabled:opacity-50 font-bold transition" 
                                        />
                                    </td>
                                </tr>

                                <!-- Sub-row for IMEI Entry when item is IMEI Tracked & receive_qty > 0 -->
                                <tr v-if="form.items[index].is_imei_tracked && form.items[index].receive_qty > 0" class="bg-indigo-50/50 dark:bg-indigo-950/20">
                                    <td colspan="6" class="px-5 py-3.5 border-t border-dashed border-indigo-200 dark:border-indigo-800">
                                        <div class="space-y-2 max-w-2xl">
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs font-bold text-indigo-950 dark:text-indigo-200">
                                                    Enter / Scan {{ form.items[index].receive_qty }} Serial / IMEI Numbers to be stored in <u>{{ form.items[index].location === 'front_store' ? 'Front Store' : 'Back Store' }}</u>:
                                                </label>
                                                <span 
                                                    class="text-xs font-bold font-mono px-2 py-0.5 rounded"
                                                    :class="form.items[index].imeis.length === form.items[index].receive_qty ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
                                                >
                                                    {{ form.items[index].imeis.length }} / {{ form.items[index].receive_qty }} IMEIs Entered
                                                </span>
                                            </div>
                                            <textarea
                                                v-model="form.items[index].raw_imeis"
                                                @input="updateImeis(index)"
                                                rows="3"
                                                placeholder="Scan or paste IMEIs here (e.g. 359128091284910)..."
                                                class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-indigo-200 dark:border-indigo-800 focus:border-indigo-500 outline-none text-xs font-mono transition"
                                            ></textarea>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span v-if="!canSubmit" class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                        ⚠️ Please ensure all IMEI-tracked items have exactly the matching number of IMEIs entered.
                    </span>
                    <div v-else></div>

                    <button 
                        type="submit" 
                        :disabled="form.processing || !canSubmit" 
                        class="px-8 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-md transition disabled:opacity-40 cursor-pointer"
                    >
                        {{ form.processing ? 'Processing...' : 'Confirm Receipt' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
