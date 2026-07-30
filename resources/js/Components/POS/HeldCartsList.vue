<script setup>
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'

const props = defineProps({ heldSales: { type: Array, default: () => [] } })
const emit  = defineEmits(['close', 'load', 'deleted'])
const { format } = useCurrency()

async function resumeCart(held) {
    // Delete from server then emit to load into cart
    await axios.delete(route('pos.api.held-sales.destroy', held.id))
    emit('load', held)
}

async function discardCart(held) {
    if (confirm('Are you sure you want to discard this held cart?')) {
        await axios.delete(route('pos.api.held-sales.destroy', held.id))
        emit('deleted')
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="bg-slate-800 rounded-xl shadow-2xl w-full max-w-md border border-slate-700">
            <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-white">Held Carts ({{ heldSales.length }})</h3>
                <button @click="emit('close')" class="text-slate-400 hover:text-white transition">✕</button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <div v-if="!heldSales.length" class="text-center text-slate-500 py-10 text-sm">No held carts.</div>
                <div v-for="held in heldSales" :key="held.id"
                    class="flex items-center justify-between px-5 py-3 border-b border-slate-700 hover:bg-slate-750 transition">
                    <div>
                        <p class="text-sm font-medium text-white">{{ held.hold_name || 'Unnamed Hold' }}</p>
                        <p class="text-xs text-slate-400">{{ held.items?.length }} item(s)
                            <span v-if="held.customer"> · {{ held.customer.name }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="discardCart(held)"
                            class="px-3 py-1.5 bg-red-650 hover:bg-red-650 text-white text-xs rounded-lg transition">
                            Discard
                        </button>
                        <button @click="resumeCart(held)"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs rounded-lg transition">
                            Resume
                        </button>
                    </div>
                </div>
            </div>
            <div class="px-5 py-3">
                <button @click="emit('close')" class="w-full py-2 rounded-lg bg-slate-700 text-slate-300 text-sm hover:bg-slate-600 transition">Close</button>
            </div>
        </div>
    </div>
</template>
