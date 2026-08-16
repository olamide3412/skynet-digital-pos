<script setup>
import { ref, computed } from 'vue'
import { useCartStore } from '@/Stores/cart'
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'

const props = defineProps({
    settings:         { type: Object, required: true },
    canApplyDiscount: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'completed'])

const cart     = useCartStore()
const { format } = useCurrency()
const tab      = ref('Cash')  // Cash | Bank Transfer | Split | Debt
const cashInput = ref(Math.round(cart.grandTotal * 100) / 100)
const bankInput = ref(0)
const isSubmitting = ref(false)
const errors   = ref({})

const tabs = ['Cash', 'Bank Transfer', 'Split', 'Debt']

function handleTabChange(t) {
    tab.value = t
    const cleanTotal = Math.round(cart.grandTotal * 100) / 100
    if (t === 'Cash') {
        cashInput.value = cleanTotal
        bankInput.value = 0
    } else if (t === 'Bank Transfer') {
        bankInput.value = cleanTotal
        cashInput.value = 0
    } else {
        cashInput.value = 0
        bankInput.value = 0
    }
}

const change = computed(() => {
    if (tab.value === 'Cash') {
        const diff = Number(cashInput.value || 0) - cart.grandTotal
        return diff > 0 ? Math.round(diff * 100) / 100 : 0
    }
    return 0
})

const amountPaid = computed(() => {
    let raw = 0
    if (tab.value === 'Cash')          raw = Number(cashInput.value || 0)
    else if (tab.value === 'Bank Transfer') raw = Number(bankInput.value || 0)
    else if (tab.value === 'Split')         raw = Number(cashInput.value || 0) + Number(bankInput.value || 0)
    else if (tab.value === 'Debt')          raw = Number(cashInput.value || 0)  // partial, rest is debt
    return Math.round(raw * 100) / 100
})

function isValid() {
    if (tab.value === 'Debt') return true  // allow partial/zero
    const targetCents = Math.round((cart.grandTotal || 0) * 100)
    const paidCents = Math.round((amountPaid.value || 0) * 100)
    return paidCents >= targetCents
}

async function submitSale() {
    if (isSubmitting.value) return
    if (!isValid()) {
        errors.value = { sale: 'Amount paid must be greater than or equal to the total amount due.' }
        return
    }
    isSubmitting.value = true
    errors.value = {}

    const payload = {
        ...cart.toPayload(),
        payment_method: tab.value,
        amount_paid:    amountPaid.value,
        cash:           tab.value === 'Cash' || tab.value === 'Split' ? Number(cashInput.value || 0) : 0,
        bank_transfer:  tab.value === 'Bank Transfer' || tab.value === 'Split' ? Number(bankInput.value || 0) : 0,
        is_debt:        tab.value === 'Debt',
    }

    try {
        const res = await axios.post(route('pos.sales.store'), payload, { timeout: 15000 })
        emit('completed', res.data?.sale ?? { receipt_id: res.data?.receipt_id, ...payload })
    } catch (err) {
        if (err.code === 'ECONNABORTED' || err.message?.includes('timeout')) {
            errors.value = { sale: 'Connection timed out. Please check network connection and try again.' }
        } else if (err.response?.status === 422) {
            const resErrors = err.response.data?.errors
            if (resErrors && typeof resErrors === 'object' && Object.keys(resErrors).length > 0) {
                const firstKey = Object.keys(resErrors)[0]
                const firstVal = Array.isArray(resErrors[firstKey]) ? resErrors[firstKey][0] : resErrors[firstKey]
                errors.value = { sale: firstVal }
            } else {
                errors.value = { sale: err.response.data?.message || 'Validation failed.' }
            }
        } else {
            errors.value = { sale: err.response?.data?.message || 'Sale processing failed. Please try again.' }
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700 transition-colors">

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Payment</h2>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Form Wrapper -->
            <form @submit.prevent="submitSale">
                <!-- Total -->
                <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-transparent">
                    <span class="text-slate-500 dark:text-slate-400 text-sm font-medium">Amount Due</span>
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ format(cart.grandTotal) }}</span>
                </div>

                <!-- Breakdown -->
                <div v-if="cart.taxAmount > 0" class="px-5 py-2 bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700/50 text-xs space-y-1">
                    <div class="flex justify-between text-slate-500 dark:text-slate-400">
                        <span>Subtotal:</span>
                        <span>{{ format(cart.subtotal) }}</span>
                    </div>
                    <div v-if="cart.discountAmount > 0" class="flex justify-between text-red-600 dark:text-red-400">
                        <span>Discount:</span>
                        <span>-{{ format(cart.discountAmount) }}</span>
                    </div>
                    <div class="flex justify-between text-amber-600 dark:text-amber-400 font-medium">
                        <span>Tax ({{ settings?.tax_percentage }}%):</span>
                        <span>+{{ format(cart.taxAmount) }}</span>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-transparent">
                    <button type="button" v-for="t in tabs" :key="t"
                        @click="handleTabChange(t)"
                        :class="tab === t ? 'border-b-2 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="flex-1 py-2 text-xs font-medium transition"
                    >{{ t }}</button>
                </div>

                <!-- Tab Content -->
                <div class="px-5 py-4 space-y-3">
                    <!-- Error -->
                    <p v-if="errors.sale" class="text-red-600 dark:text-red-400 text-sm bg-red-50 dark:bg-red-400/10 px-3 py-2 rounded border border-red-200 dark:border-red-500/20 font-medium">{{ errors.sale }}</p>

                    <!-- Cash -->
                    <template v-if="tab === 'Cash'">
                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">Cash Tendered</label>
                        <input v-model.number="cashInput" type="number" min="0" step="any"
                            class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white text-xl font-bold px-4 py-3 rounded-lg outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition font-mono"
                            autofocus />
                        <div v-if="cashInput > 0" class="flex justify-between text-sm mt-1">
                            <span class="text-slate-500 dark:text-slate-400">Change</span>
                            <span class="font-bold font-mono" :class="change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ format(change) }}</span>
                        </div>
                    </template>

                    <!-- Bank Transfer -->
                    <template v-if="tab === 'Bank Transfer'">
                        <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">Bank Transfer Amount</label>
                        <input v-model.number="bankInput" type="number" min="0" step="any"
                            class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white text-xl font-bold px-4 py-3 rounded-lg outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition font-mono"
                            autofocus />
                    </template>

                    <!-- Split -->
                    <template v-if="tab === 'Split'">
                        <div>
                            <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">Cash</label>
                            <input v-model.number="cashInput" type="number" min="0" step="any"
                                class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">Bank Transfer</label>
                            <input v-model.number="bankInput" type="number" min="0" step="any"
                                class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition font-mono" />
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Total Paid: <span class="font-mono font-bold" :class="amountPaid >= cart.grandTotal ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ format(amountPaid) }}</span></p>
                    </template>

                    <!-- Debt -->
                    <template v-if="tab === 'Debt'">
                        <p v-if="!cart.customer" class="text-amber-700 dark:text-amber-400 text-sm bg-amber-50 dark:bg-amber-400/10 px-3 py-2 rounded border border-amber-200 dark:border-amber-500/20 font-medium">⚠ Please select a customer for debt sales.</p>
                        <div v-else>
                            <p class="text-sm text-slate-600 dark:text-slate-300">Debt will be recorded against <strong class="text-slate-900 dark:text-white">{{ cart.customer.name }}</strong></p>
                            <label class="block text-xs text-slate-500 dark:text-slate-400 mt-3 mb-1 font-medium">Partial Payment (optional)</label>
                            <input v-model.number="cashInput" type="number" min="0" step="any" :max="cart.grandTotal"
                                class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition font-mono" />
                        </div>
                    </template>
                </div>

                <!-- Confirm Button -->
                <div class="px-5 pb-5">
                    <button
                        type="submit"
                        :disabled="isSubmitting || (tab === 'Debt' && !cart.customer)"
                        class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-base transition disabled:opacity-40 disabled:cursor-not-allowed shadow-sm flex items-center justify-center gap-2"
                    >
                        <svg v-if="isSubmitting" class="w-5 h-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ isSubmitting ? 'Processing Sale…' : 'Confirm Payment' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
