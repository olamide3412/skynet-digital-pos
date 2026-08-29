<script setup>
import { ref } from 'vue'
import { useCartStore } from '@/Stores/cart'
import axios from 'axios'

const emit = defineEmits(['close', 'held'])
const cart  = useCartStore()
const holdName  = ref('')
const saving    = ref(false)
const error     = ref('')

async function holdCart() {
    if (!cart.items.length) return
    saving.value = true
    error.value  = ''
    try {
        await axios.post(route('pos.api.held-sales.store'), {
            hold_name:   holdName.value || null,
            customer_id: cart.customer?.id ?? null,
            items: cart.items.map(i => ({
                item_id:       i.item_id,
                qty:           i.qty,
                price:         i.unit_price,
                item_name:     i.item_name,
                purchase_type: i.purchase_type,
            })),
        })
        cart.clearCart()
        emit('held')
    } catch (e) {
        error.value = 'Failed to hold cart. Please try again.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-700 transition-colors overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base flex items-center gap-2">
                    <span>📌</span>
                    <span>Hold Current Cart</span>
                </h3>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer text-xs"
                >
                    ✕
                </button>
            </div>
            <div class="px-5 py-4 space-y-3">
                <p class="text-slate-500 dark:text-slate-400 text-xs leading-relaxed">
                    <strong class="text-slate-700 dark:text-slate-200">{{ cart.items.length }} item(s)</strong> will be held in memory. Add an optional label to identify this customer's cart quickly.
                </p>
                <input
                    v-model="holdName"
                    type="text"
                    placeholder="e.g. Table 4 / Blue Shirt Customer"
                    class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl outline-none border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 text-sm transition placeholder-slate-400"
                />
                <p v-if="error" class="text-rose-600 dark:text-rose-400 text-xs font-semibold">{{ error }}</p>
            </div>
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex gap-2">
                <button
                    @click="emit('close')"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition hover:bg-slate-200 dark:hover:bg-slate-600 cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    @click="holdCart"
                    :disabled="saving"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition disabled:opacity-40 shadow-xs cursor-pointer"
                >
                    {{ saving ? 'Holding…' : 'Hold Cart' }}
                </button>
            </div>
        </div>
    </div>
</template>
