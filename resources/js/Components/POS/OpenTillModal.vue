<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useCurrency } from '@/Composables/useCurrency'
import { useOfflineSync } from '@/Composables/useOfflineSync'
import { saveLocalTillSession } from '@/Services/offlineDb'
import axios from 'axios'

const props = defineProps({
    show:      { type: Boolean, default: false },
    mandatory: { type: Boolean, default: false },
    settings:  { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'opened'])

const page = usePage()
const { format } = useCurrency()
const { isOnline } = useOfflineSync()

const openingAmount = ref(0)
const openingNote = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')

const cashierName = computed(() => {
    return page.props.auth?.user?.name || page.props.auth?.user?.full_name || 'Cashier'
})

const branchName = computed(() => {
    return page.props.current_branch?.name || props.settings?.business_name || 'Store Branch'
})

const quickAmounts = [0, 5000, 10000, 20000, 50000, 100000]

function setQuickAmount(amt) {
    openingAmount.value = amt
    errorMessage.value = ''
}

async function handleOpenTill() {
    if (openingAmount.value === null || openingAmount.value === undefined || Number(openingAmount.value) < 0) {
        errorMessage.value = 'Please enter a valid opening float amount (minimum 0).'
        return
    }

    isSubmitting.value = true
    errorMessage.value = ''

    const isDeviceOffline = !isOnline.value || (typeof navigator !== 'undefined' && !navigator.onLine)
    const floatVal = parseFloat(openingAmount.value) || 0
    const noteVal = openingNote.value.trim() || null

    if (isDeviceOffline) {
        // Offline Open Till Workflow
        const offlineSessionId = 'offline_till_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7)
        const localSession = {
            offline_session_id: offlineSessionId,
            id: null,
            cashier_id: page.props.auth?.user?.id,
            cashier_name: cashierName.value,
            opening_amount: floatVal,
            opened_at: new Date().toISOString(),
            status: 'open',
            closing_amount: null,
            expected_amount: floatVal,
            variance: 0,
            opening_note: noteVal,
            closing_note: null,
            sync_status: 'pending',
            summary: {
                opening_amount: floatVal,
                cash_sales: 0,
                cash_refunds: 0,
                cash_added: 0,
                cash_removed: 0,
                expected_cash: floatVal,
                movements: [],
            },
        }

        await saveLocalTillSession(localSession)
        isSubmitting.value = false
        emit('opened', localSession)
        return
    }

    // Online Open Till Workflow
    try {
        const payload = {
            opening_amount: floatVal,
            opening_note: noteVal,
        }
        const res = await axios.post(route('pos.till.open'), payload)
        const session = res.data?.session

        if (session) {
            await saveLocalTillSession(session)
        }

        isSubmitting.value = false
        emit('opened', session)
    } catch (err) {
        isSubmitting.value = false
        console.error('Failed to open till session:', err)
        errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Failed to open till session. Please verify and try again.'
    }
}

function handleBackdropClick() {
    if (!props.mandatory) {
        emit('close')
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 transition-all"
        @click.self="handleBackdropClick"
    >
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 px-6 py-5 text-white relative">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center text-2xl shadow-inner border border-white/20">
                        💼
                    </div>
                    <div>
                        <h2 class="text-lg font-bold tracking-tight">Open Till / Cash Drawer</h2>
                        <p class="text-xs text-emerald-100/90 font-medium">
                            {{ branchName }} • {{ cashierName }}
                        </p>
                    </div>
                </div>

                <button
                    v-if="!mandatory"
                    type="button"
                    @click="$emit('close')"
                    class="absolute top-4 right-4 text-white/70 hover:text-white rounded-full p-1 transition cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <!-- Modal Body -->
            <form @submit.prevent="handleOpenTill" class="p-6 space-y-5">
                <div v-if="mandatory" class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2.5">
                    <span class="text-base flex-shrink-0">⚠️</span>
                    <span>
                        <strong>Opening Float Required:</strong> Branch policy requires cashiers to declare starting float before processing sales on this terminal.
                    </span>
                </div>

                <div v-if="errorMessage" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-xl text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2">
                    <span class="text-base flex-shrink-0">❌</span>
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Opening Amount Field -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        Starting Cash Float (Amount in Drawer)
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-lg select-none">
                            ₦
                        </span>
                        <input
                            v-model.number="openingAmount"
                            type="number"
                            step="any"
                            min="0"
                            placeholder="0.00"
                            autofocus
                            required
                            class="w-full pl-10 pr-4 py-3 text-2xl font-bold font-mono text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:outline-hidden transition"
                        />
                    </div>
                </div>

                <!-- Quick Amount Chips -->
                <div>
                    <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-2">
                        Quick Select Starting Float:
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="amt in quickAmounts"
                            :key="amt"
                            type="button"
                            @click="setQuickAmount(amt)"
                            :class="[
                                'py-1.5 px-2 rounded-xl text-xs font-semibold font-mono border transition cursor-pointer',
                                openingAmount === amt
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                                    : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-emerald-500'
                            ]"
                        >
                            {{ format(amt) }}
                        </button>
                    </div>
                </div>

                <!-- Opening Note (Optional) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Shift / Opening Note <span class="text-slate-400 font-normal">(Optional)</span>
                    </label>
                    <input
                        v-model="openingNote"
                        type="text"
                        maxlength="250"
                        placeholder="e.g. Morning Shift, Float verified by Supervisor"
                        class="w-full px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-hidden transition"
                    />
                </div>

                <!-- Actions -->
                <div class="pt-2 flex items-center justify-end gap-3">
                    <button
                        v-if="!mandatory"
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="flex-1 px-5 py-3 rounded-2xl text-sm font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <span v-if="isSubmitting" class="animate-spin text-base">🔄</span>
                        <span v-else>🚀</span>
                        <span>{{ isSubmitting ? 'Opening Till...' : 'Confirm & Open Till' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
