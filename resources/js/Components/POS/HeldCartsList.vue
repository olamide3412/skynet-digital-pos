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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-700 transition-colors overflow-hidden">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm sm:text-base">
                    <span>📂</span>
                    <span>Held Carts ({{ heldSales.length }})</span>
                </h3>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer text-xs"
                >
                    ✕
                </button>
            </div>

            <!-- Content List -->
            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                <div v-if="!heldSales.length" class="text-center text-slate-400 dark:text-slate-500 py-10 text-sm">
                    No held carts currently saved.
                </div>
                <div
                    v-for="held in heldSales"
                    :key="held.id"
                    class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition"
                >
                    <div class="min-w-0 pr-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                            {{ held.hold_name || 'Unnamed Hold' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ held.items?.length }} item(s)
                            <span v-if="held.customer" class="font-medium text-slate-700 dark:text-slate-300"> · {{ held.customer.name }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button
                            @click="discardCart(held)"
                            type="button"
                            class="px-2.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60 text-xs font-semibold rounded-xl transition cursor-pointer"
                        >
                            Discard
                        </button>
                        <button
                            @click="resumeCart(held)"
                            type="button"
                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition cursor-pointer shadow-xs"
                        >
                            Resume
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <button
                    @click="emit('close')"
                    type="button"
                    class="w-full py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-600 transition cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>
