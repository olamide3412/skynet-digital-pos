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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-700 transition-colors overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">Hold Cart</h3>
                <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition">✕</button>
            </div>
            <div class="px-5 py-4 space-y-3">
                <p class="text-slate-500 dark:text-slate-400 text-sm">{{ cart.items.length }} item(s) will be saved. Add a name to find it easily.</p>
                <input
                    v-model="holdName"
                    type="text"
                    placeholder="Hold name (optional)"
                    class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg outline-none border border-slate-200 dark:border-slate-600 focus:border-amber-500 text-sm transition"
                />
                <p v-if="error" class="text-red-600 dark:text-red-400 text-sm font-medium">{{ error }}</p>
            </div>
            <div class="px-5 pb-5 flex gap-2">
                <button @click="emit('close')" class="flex-1 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium transition hover:bg-slate-200 dark:hover:bg-slate-600">Cancel</button>
                <button @click="holdCart" :disabled="saving"
                    class="flex-1 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium transition disabled:opacity-40"
                >{{ saving ? 'Saving…' : 'Hold Cart' }}</button>
            </div>
        </div>
    </div>
</template>
