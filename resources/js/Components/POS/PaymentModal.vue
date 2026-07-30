<script setup>
import { ref, computed } from 'vue'
import { useCartStore } from '@/Stores/cart'
import { useCurrency } from '@/Composables/useCurrency'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    settings:         { type: Object, required: true },
    canApplyDiscount: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'completed'])

const cart     = useCartStore()
const { format } = useCurrency()
const tab      = ref('Cash')  // Cash | BankTransfer | Split | Debt
const cashInput = ref(cart.grandTotal)
const bankInput = ref(0)
const isSubmitting = ref(false)
const errors   = ref({})

const tabs = ['Cash', 'Bank Transfer', 'Split', 'Debt']

function handleTabChange(t) {
    tab.value = t
    if (t === 'Cash') {
        cashInput.value = cart.grandTotal
        bankInput.value = 0
    } else if (t === 'Bank Transfer') {
        bankInput.value = cart.grandTotal
        cashInput.value = 0
    } else {
        cashInput.value = 0
        bankInput.value = 0
    }
}

const change = computed(() => {
    if (tab.value === 'Cash') return Math.max(0, cashInput.value - cart.grandTotal)
    return 0
})

const amountPaid = computed(() => {
    if (tab.value === 'Cash')         return cashInput.value
    if (tab.value === 'Bank Transfer') return bankInput.value
    if (tab.value === 'Split')        return cashInput.value + bankInput.value
    if (tab.value === 'Debt')         return cashInput.value  // partial, rest is debt
    return 0
})

function isValid() {
    if (tab.value === 'Debt') return true  // allow partial
    return amountPaid.value >= cart.grandTotal
}

async function submitSale() {
    if (!isValid()) { errors.value = { sale: 'Amount paid must be ≥ total.' }; return }
    isSubmitting.value = true
    errors.value = {}

    const payload = {
        ...cart.toPayload(),
        payment_method: tab.value,
        amount_paid:    amountPaid.value,
        cash:           tab.value === 'Cash' || tab.value === 'Split' ? cashInput.value : 0,
        bank_transfer:  tab.value === 'Bank Transfer' || tab.value === 'Split' ? bankInput.value : 0,
        is_debt:        tab.value === 'Debt',
    }

    try {
        const res = await axios.post(route('pos.sales.store'), payload)
        // Inertia returns a redirect — we need to check flash
        emit('completed', res.data?.sale ?? { receipt_id: res.data?.receipt_id, ...payload })
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors ?? { sale: err.response.data.message }
        } else {
            errors.value = { sale: 'Sale failed. Please try again.' }
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <!-- Backdrop -->
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-700">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
                <h2 class="text-lg font-bold text-white">Payment</h2>
                <button @click="emit('close')" class="text-slate-400 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Total -->
            <div class="px-5 py-3 border-b border-slate-700 flex justify-between items-center">
                <span class="text-slate-400 text-sm">Amount Due</span>
                <span class="text-2xl font-bold text-emerald-400">{{ format(cart.grandTotal) }}</span>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-slate-700">
                <button v-for="t in tabs" :key="t"
                    @click="handleTabChange(t)"
                    :class="tab === t ? 'border-b-2 border-emerald-500 text-emerald-400' : 'text-slate-400 hover:text-white'"
                    class="flex-1 py-2 text-xs font-medium transition"
                >{{ t }}</button>
            </div>

            <!-- Tab Content -->
            <div class="px-5 py-4 space-y-3">
                <!-- Error -->
                <p v-if="errors.sale" class="text-red-400 text-sm bg-red-400/10 px-3 py-2 rounded">{{ errors.sale }}</p>

                <!-- Cash -->
                <template v-if="tab === 'Cash'">
                    <label class="block text-xs text-slate-400 mb-1">Cash Tendered</label>
                    <input v-model.number="cashInput" type="number" min="0"
                        class="w-full bg-slate-700 text-white text-xl font-bold px-4 py-3 rounded-lg outline-none border border-slate-600 focus:border-emerald-500 transition"
                        autofocus />
                    <div v-if="cashInput > 0" class="flex justify-between text-sm">
                        <span class="text-slate-400">Change</span>
                        <span class="font-bold" :class="change >= 0 ? 'text-emerald-400' : 'text-red-400'">{{ format(change) }}</span>
                    </div>
                </template>

                <!-- Bank Transfer -->
                <template v-if="tab === 'Bank Transfer'">
                    <label class="block text-xs text-slate-400 mb-1">Bank Transfer Amount</label>
                    <input v-model.number="bankInput" type="number" min="0"
                        class="w-full bg-slate-700 text-white text-xl font-bold px-4 py-3 rounded-lg outline-none border border-slate-600 focus:border-emerald-500 transition"
                        autofocus />
                </template>

                <!-- Split -->
                <template v-if="tab === 'Split'">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Cash</label>
                        <input v-model.number="cashInput" type="number" min="0"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg outline-none border border-slate-600 focus:border-emerald-500 transition" />
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Bank Transfer</label>
                        <input v-model.number="bankInput" type="number" min="0"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg outline-none border border-slate-600 focus:border-emerald-500 transition" />
                    </div>
                    <p class="text-xs text-slate-400">Total: <span :class="amountPaid >= cart.grandTotal ? 'text-emerald-400' : 'text-red-400'">{{ format(amountPaid) }}</span></p>
                </template>

                <!-- Debt -->
                <template v-if="tab === 'Debt'">
                    <p v-if="!cart.customer" class="text-amber-400 text-sm bg-amber-400/10 px-3 py-2 rounded">⚠ Please select a customer for debt sales.</p>
                    <div v-else>
                        <p class="text-sm text-slate-300">Debt will be recorded against <strong class="text-white">{{ cart.customer.name }}</strong></p>
                        <label class="block text-xs text-slate-400 mt-3 mb-1">Partial Payment (optional)</label>
                        <input v-model.number="cashInput" type="number" min="0" :max="cart.grandTotal"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg outline-none border border-slate-600 focus:border-emerald-500 transition" />
                    </div>
                </template>
            </div>

            <!-- Confirm Button -->
            <div class="px-5 pb-5">
                <button
                    @click="submitSale"
                    :disabled="isSubmitting || (tab === 'Debt' && !cart.customer)"
                    class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-base transition disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <span v-if="isSubmitting">Processing…</span>
                    <span v-else>Confirm Payment</span>
                </button>
            </div>
        </div>
    </div>
</template>
