<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useCurrency } from '@/Composables/useCurrency'

const emit = defineEmits(['close'])
const { format } = useCurrency()

const receiptQuery = ref('')
const sale         = ref(null)
const saleLoading  = ref(false)
const saleError    = ref('')
const returnItems  = ref([])
const submitting   = ref(false)
const success      = ref('')

async function findSale() {
    saleLoading.value = true
    saleError.value   = ''
    sale.value        = null
    returnItems.value = []
    try {
        const res = await axios.get(route('pos.sales.show', receiptQuery.value))
        sale.value = res.data ?? null
        if (sale.value) {
            returnItems.value = (sale.value.sale_orders || []).map(o => ({
                item_id:  o.item_id,
                item_name: o.item_name,
                max_qty:  o.qty,
                qty:      0,
                reason:   '',
            }))
        }
    } catch {
        saleError.value = 'Sale not found. Check the receipt ID.'
    } finally {
        saleLoading.value = false
    }
}

async function submitReturn() {
    const selected = returnItems.value.filter(i => i.qty > 0)
    if (!selected.length) return
    submitting.value = true
    try {
        await axios.post(route('pos.sale-returns.store'), {
            sale_id: sale.value.id,
            items:   selected.map(i => ({ item_id: i.item_id, qty: i.qty, reason: i.reason })),
        })
        success.value = 'Return processed successfully. Inventory restocked.'
    } catch (e) {
        saleError.value = e.response?.data?.errors?.return ?? 'Return failed.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg border border-slate-700">
            <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-white">Process Return / Refund</h3>
                <button @click="emit('close')" class="text-slate-400 hover:text-white transition">✕</button>
            </div>

            <div class="px-5 py-4 space-y-4">
                <!-- Success -->
                <div v-if="success" class="bg-emerald-500/10 text-emerald-400 px-4 py-3 rounded-lg text-sm">{{ success }}</div>

                <!-- Search -->
                <div v-if="!success">
                    <label class="block text-xs text-slate-400 mb-1">Receipt ID</label>
                    <div class="flex gap-2">
                        <input v-model="receiptQuery" @keydown.enter="findSale" type="text"
                            placeholder="e.g. RC202504050001"
                            class="flex-1 bg-slate-700 text-white px-3 py-2 rounded-lg outline-none border border-slate-600 focus:border-blue-500 text-sm transition" />
                        <button @click="findSale" :disabled="saleLoading"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition disabled:opacity-40">
                            Search
                        </button>
                    </div>
                    <p v-if="saleError" class="text-red-400 text-xs mt-1">{{ saleError }}</p>
                </div>

                <!-- Return Items -->
                <div v-if="sale && !success" class="space-y-2 max-h-60 overflow-y-auto">
                    <p class="text-xs text-slate-400 font-medium">Select items and quantities to return:</p>
                    <div v-for="item in returnItems" :key="item.item_id"
                        class="flex items-center gap-3 bg-slate-700/50 rounded-lg px-3 py-2">
                        <div class="flex-1">
                            <p class="text-sm text-white">{{ item.item_name }}</p>
                            <p class="text-xs text-slate-500">Max: {{ item.max_qty }}</p>
                        </div>
                        <input v-model.number="item.qty" type="number" :min="0" :max="item.max_qty"
                            class="w-16 bg-slate-700 text-white text-center text-sm px-2 py-1 rounded border border-slate-600 outline-none" />
                        <input v-if="item.qty > 0" v-model="item.reason" type="text" placeholder="Reason"
                            class="w-28 bg-slate-700 text-white text-xs px-2 py-1 rounded border border-slate-600 outline-none" />
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div v-if="sale && !success" class="px-5 pb-5 flex gap-2">
                <button @click="emit('close')" class="flex-1 py-2 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600 transition">Cancel</button>
                <button @click="submitReturn" :disabled="submitting || !returnItems.some(i => i.qty > 0)"
                    class="flex-1 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg text-sm font-medium transition disabled:opacity-40">
                    {{ submitting ? 'Processing…' : 'Process Return' }}
                </button>
            </div>
        </div>
    </div>
</template>
