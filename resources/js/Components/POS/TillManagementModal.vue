<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useCurrency } from '@/Composables/useCurrency'
import { useOfflineSync } from '@/Composables/useOfflineSync'
import { saveLocalTillSession, closeLocalTillSession } from '@/Services/offlineDb'
import axios from 'axios'
import dayjs from 'dayjs'

const props = defineProps({
    show:                   { type: Boolean, default: false },
    session:                { type: Object, required: true },
    settings:               { type: Object, default: () => ({}) },
    canManageCashMovements: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'updated', 'closed', 'printSlip'])

const page = usePage()
const { format } = useCurrency()
const { isOnline } = useOfflineSync()

const activeTab = ref('overview') // 'overview' | 'add_cash' | 'remove_cash' | 'close_till'
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

// Movement Form
const movementAmount = ref('')
const movementReason = ref('')

// Close Till Form
const countedCash = ref('')
const closingNote = ref('')

// Local live summary state
const localSummary = ref(props.session.summary || {
    opening_amount: props.session.opening_amount || 0,
    cash_sales:     0,
    cash_refunds:   0,
    cash_added:     0,
    cash_removed:   0,
    expected_cash:  props.session.opening_amount || 0,
    movements:      [],
})

const localMovements = ref(props.session.cash_movements || props.session.cashMovements || [])

watch(() => props.session, (newS) => {
    if (newS?.summary) {
        localSummary.value = newS.summary
    }
    if (newS?.cash_movements || newS?.cashMovements) {
        localMovements.value = newS.cash_movements || newS.cashMovements || []
    }
}, { immediate: true, deep: true })

// Expected Cash calculation formula (§5):
// Opening Amount + Cash Sales − Cash Refunds + Cash Added − Cash Removed
const calculatedExpectedCash = computed(() => {
    const s = localSummary.value
    const openAmt = parseFloat(props.session.opening_amount || s.opening_amount || 0)
    const sales = parseFloat(s.cash_sales || 0)
    const refunds = parseFloat(s.cash_refunds || 0)
    const added = parseFloat(s.cash_added || 0)
    const removed = parseFloat(s.cash_removed || 0)

    return Math.round((openAmt + sales - refunds + added - removed) * 100) / 100
})

const calculatedVariance = computed(() => {
    if (countedCash.value === '' || countedCash.value === null || countedCash.value === undefined) {
        return 0
    }
    const counted = parseFloat(countedCash.value) || 0
    return Math.round((counted - calculatedExpectedCash.value) * 100) / 100
})

const cashierName = computed(() => {
    return props.session.cashier?.name || props.session.cashier_name || page.props.auth?.user?.name || 'Cashier'
})

const openedAtFormatted = computed(() => {
    return props.session.opened_at ? dayjs(props.session.opened_at).format('DD MMM YYYY, HH:mm') : '—'
})

async function fetchLiveSummary() {
    if (!props.session?.id || !isOnline.value) return
    try {
        const res = await axios.get(route('pos.till.active'))
        if (res.data?.session?.summary) {
            localSummary.value = res.data.session.summary
            emit('updated', res.data.session)
        }
    } catch (e) {
        console.warn('Could not refresh till summary:', e)
    }
}

onMounted(() => {
    fetchLiveSummary()
})

async function submitCashMovement(type) {
    const amt = parseFloat(movementAmount.value)
    if (!amt || amt <= 0) {
        errorMessage.value = 'Please enter a valid amount greater than 0.'
        return
    }

    isSubmitting.value = true
    errorMessage.value = ''
    successMessage.value = ''

    const reason = movementReason.value.trim() || null

    if (!isOnline.value || !props.session.id) {
        // Offline cash movement record
        const newMovement = {
            id: 'local_mov_' + Date.now(),
            till_session_id: props.session.id || props.session.offline_session_id,
            type: type,
            amount: amt,
            reason: reason,
            performed_by: page.props.auth?.user?.id,
            performer: { id: page.props.auth?.user?.id, name: cashierName.value },
            created_at: new Date().toISOString(),
        }

        localMovements.value.unshift(newMovement)

        if (type === 'add') {
            localSummary.value.cash_added = (localSummary.value.cash_added || 0) + amt
        } else {
            localSummary.value.cash_removed = (localSummary.value.cash_removed || 0) + amt
        }
        localSummary.value.expected_cash = calculatedExpectedCash.value

        await saveLocalTillSession({
            ...props.session,
            summary: localSummary.value,
            cash_movements: localMovements.value,
        })

        isSubmitting.value = false
        movementAmount.value = ''
        movementReason.value = ''
        successMessage.value = `Cash ${type === 'add' ? 'added' : 'removed'} successfully.`
        activeTab.value = 'overview'
        return
    }

    try {
        const payload = {
            till_session_id: props.session.id,
            type:            type,
            amount:          amt,
            reason:          reason,
        }

        const res = await axios.post(route('pos.till.movements.store'), payload)
        if (res.data?.summary) {
            localSummary.value = res.data.summary
        }
        if (res.data?.movement) {
            localMovements.value.unshift(res.data.movement)
        }

        isSubmitting.value = false
        movementAmount.value = ''
        movementReason.value = ''
        successMessage.value = `Cash ${type === 'add' ? 'added' : 'removed'} successfully.`
        activeTab.value = 'overview'
        emit('updated', {
            ...props.session,
            summary: localSummary.value,
            cash_movements: localMovements.value,
        })
    } catch (err) {
        isSubmitting.value = false
        errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Failed to record cash movement.'
    }
}

async function handleCloseTill() {
    if (countedCash.value === '' || countedCash.value === null || countedCash.value === undefined || Number(countedCash.value) < 0) {
        errorMessage.value = 'Please enter the actual counted cash amount in the drawer (0 or more).'
        return
    }

    isSubmitting.value = true
    errorMessage.value = ''

    const closingAmt = parseFloat(countedCash.value) || 0
    const note = closingNote.value.trim() || null
    const varAmt = calculatedVariance.value
    const expAmt = calculatedExpectedCash.value

    if (!isOnline.value || !props.session.id) {
        // Offline Close Till
        const key = props.session.offline_session_id || props.session.id
        const closedSession = {
            ...props.session,
            status: 'closed',
            closing_amount: closingAmt,
            expected_amount: expAmt,
            variance: varAmt,
            closing_note: note,
            closed_at: new Date().toISOString(),
            closed_by: page.props.auth?.user?.id,
            summary: localSummary.value,
        }

        await closeLocalTillSession(key, {
            closing_amount: closingAmt,
            expected_amount: expAmt,
            variance: varAmt,
            closing_note: note,
        })

        isSubmitting.value = false
        emit('closed', closedSession)
        return
    }

    try {
        const payload = {
            closing_amount: closingAmt,
            closing_note:   note,
        }

        const res = await axios.post(route('pos.till.close'), payload)
        const closedSession = res.data?.session || {
            ...props.session,
            status: 'closed',
            closing_amount: closingAmt,
            expected_amount: expAmt,
            variance: varAmt,
            closing_note: note,
            closed_at: new Date().toISOString(),
        }

        await closeLocalTillSession(props.session.id, {
            closing_amount: closingAmt,
            expected_amount: expAmt,
            variance: varAmt,
            closing_note: note,
        })

        isSubmitting.value = false
        emit('closed', closedSession)
    } catch (err) {
        isSubmitting.value = false
        errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Failed to close till session.'
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 transition-all"
        @click.self="$emit('close')"
    >
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 max-w-xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 px-6 py-5 text-white border-b border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-2xl shadow-inner">
                        💼
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold tracking-tight">Active Till Session</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                Open
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Cashier: <strong class="text-slate-200">{{ cashierName }}</strong> • Started: {{ openedAtFormatted }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="fetchLiveSummary"
                        title="Refresh numbers"
                        class="p-2 text-slate-400 hover:text-white rounded-xl transition cursor-pointer"
                    >
                        🔄
                    </button>
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="p-2 text-slate-400 hover:text-white rounded-xl transition cursor-pointer"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 px-6 pt-3 pb-2 bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-xs font-semibold">
                <button
                    type="button"
                    @click="activeTab = 'overview'"
                    :class="[
                        'px-3 py-1.5 rounded-xl transition cursor-pointer',
                        activeTab === 'overview'
                            ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs font-bold'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                    ]"
                >
                    📊 Overview
                </button>

                <button
                    v-if="canManageCashMovements"
                    type="button"
                    @click="activeTab = 'add_cash'"
                    :class="[
                        'px-3 py-1.5 rounded-xl transition cursor-pointer',
                        activeTab === 'add_cash'
                            ? 'bg-emerald-600 text-white shadow-xs font-bold'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                    ]"
                >
                    ➕ Add Cash
                </button>

                <button
                    v-if="canManageCashMovements"
                    type="button"
                    @click="activeTab = 'remove_cash'"
                    :class="[
                        'px-3 py-1.5 rounded-xl transition cursor-pointer',
                        activeTab === 'remove_cash'
                            ? 'bg-amber-600 text-white shadow-xs font-bold'
                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                    ]"
                >
                    ➖ Remove Cash
                </button>

                <button
                    type="button"
                    @click="activeTab = 'close_till'"
                    :class="[
                        'ml-auto px-3.5 py-1.5 rounded-xl transition cursor-pointer',
                        activeTab === 'close_till'
                            ? 'bg-rose-600 text-white shadow-xs font-bold'
                            : 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                    ]"
                >
                    🔒 Close Till
                </button>
            </div>

            <!-- Modal Content Body -->
            <div class="p-6 overflow-y-auto flex-1 space-y-5">
                <div v-if="successMessage" class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-xs text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
                    <span>{{ successMessage }}</span>
                    <button type="button" @click="successMessage = ''" class="text-emerald-600">✕</button>
                </div>

                <div v-if="errorMessage" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-xl text-xs text-rose-700 dark:text-rose-300 flex items-center justify-between">
                    <span>{{ errorMessage }}</span>
                    <button type="button" @click="errorMessage = ''" class="text-rose-600">✕</button>
                </div>

                <!-- ════════ TAB 1: OVERVIEW ════════ -->
                <div v-if="activeTab === 'overview'" class="space-y-5">
                    <!-- Expected Cash Hero Card -->
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-emerald-600/10 via-teal-500/5 to-slate-900/5 dark:from-emerald-950/40 dark:via-slate-900 dark:to-slate-900 border border-emerald-500/20 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                Live Expected Cash in Drawer
                            </span>
                            <div class="text-3xl font-black text-slate-900 dark:text-white font-mono mt-0.5">
                                {{ format(calculatedExpectedCash) }}
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Formula: Float + Cash Sales − Refunds + Cash In − Cash Out
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Starting Float</span>
                            <div class="text-sm font-bold font-mono text-slate-800 dark:text-slate-200">
                                {{ format(session.opening_amount) }}
                            </div>
                        </div>
                    </div>

                    <!-- Shift Metrics Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Cash Sales</span>
                            <span class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400">
                                +{{ format(localSummary.cash_sales) }}
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Cash Refunds</span>
                            <span class="text-base font-bold font-mono text-rose-600 dark:text-rose-400">
                                -{{ format(localSummary.cash_refunds) }}
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Cash Added</span>
                            <span class="text-base font-bold font-mono text-blue-600 dark:text-blue-400">
                                +{{ format(localSummary.cash_added) }}
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                            <span class="text-slate-500 dark:text-slate-400 block text-[11px]">Cash Removed</span>
                            <span class="text-base font-bold font-mono text-amber-600 dark:text-amber-400">
                                -{{ format(localSummary.cash_removed) }}
                            </span>
                        </div>
                    </div>

                    <!-- Recent Cash Movements Log -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                Mid-Shift Cash Movements ({{ localMovements.length }})
                            </span>
                            <span v-if="!canManageCashMovements" class="text-[11px] text-slate-400 italic">
                                Read-only (Admin/Manager permissions required to adjust)
                            </span>
                        </div>

                        <div v-if="localMovements.length === 0" class="p-6 text-center text-slate-400 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-dashed border-slate-200 dark:border-slate-700">
                            No mid-shift cash movements recorded for this session.
                        </div>

                        <div v-else class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <div
                                v-for="m in localMovements"
                                :key="m.id"
                                class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="text-base">{{ m.type === 'add' ? '🟢' : '🔴' }}</span>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200">
                                            {{ m.type === 'add' ? 'Cash Added (Top-Up)' : 'Cash Removed (Drop)' }}
                                            <span class="font-normal text-slate-500 dark:text-slate-400">— {{ m.reason || 'No reason specified' }}</span>
                                        </div>
                                        <div class="text-[11px] text-slate-400">
                                            By {{ m.performer?.name || cashierName }} • {{ dayjs(m.created_at).format('HH:mm:ss') }}
                                        </div>
                                    </div>
                                </div>
                                <div :class="['font-bold font-mono text-sm', m.type === 'add' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400']">
                                    {{ m.type === 'add' ? '+' : '-' }}{{ format(m.amount) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════ TAB 2: ADD CASH ════════ -->
                <form v-else-if="activeTab === 'add_cash'" @submit.prevent="submitCashMovement('add')" class="space-y-4">
                    <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs text-blue-800 dark:text-blue-300">
                        <strong>Float Top-Up / Cash Addition:</strong> Record additional cash introduced into the cash drawer mid-shift (e.g. change float delivery). This increases the expected cash.
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                            Cash Amount to Add
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-lg select-none">₦</span>
                            <input
                                v-model.number="movementAmount"
                                type="number"
                                step="any"
                                min="0.01"
                                placeholder="0.00"
                                required
                                autofocus
                                class="w-full pl-10 pr-4 py-3 text-xl font-bold font-mono text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:outline-hidden"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Reason / Description
                        </label>
                        <input
                            v-model="movementReason"
                            type="text"
                            placeholder="e.g. ₦10,000 in ₦500 denominations for change"
                            class="w-full px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-hidden"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="activeTab = 'overview'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                        >
                            Back
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="isSubmitting" class="animate-spin">🔄</span>
                            <span>{{ isSubmitting ? 'Recording...' : 'Record Cash Addition' }}</span>
                        </button>
                    </div>
                </form>

                <!-- ════════ TAB 3: REMOVE CASH ════════ -->
                <form v-else-if="activeTab === 'remove_cash'" @submit.prevent="submitCashMovement('remove')" class="space-y-4">
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs text-amber-800 dark:text-amber-300">
                        <strong>Cash Drop / Removal:</strong> Record cash taken out of the drawer mid-shift (e.g. excess cash drop to safe or petty expense). This decreases the expected cash.
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                            Cash Amount to Remove
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-lg select-none">₦</span>
                            <input
                                v-model.number="movementAmount"
                                type="number"
                                step="any"
                                min="0.01"
                                placeholder="0.00"
                                required
                                autofocus
                                class="w-full pl-10 pr-4 py-3 text-xl font-bold font-mono text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 rounded-2xl focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Reason / Description
                        </label>
                        <input
                            v-model="movementReason"
                            type="text"
                            placeholder="e.g. Mid-day safe drop of excess notes"
                            class="w-full px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="activeTab = 'overview'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                        >
                            Back
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white shadow-md transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="isSubmitting" class="animate-spin">🔄</span>
                            <span>{{ isSubmitting ? 'Recording...' : 'Record Cash Removal' }}</span>
                        </button>
                    </div>
                </form>

                <!-- ════════ TAB 4: CLOSE TILL ════════ -->
                <form v-else-if="activeTab === 'close_till'" @submit.prevent="handleCloseTill" class="space-y-4">
                    <div class="p-3 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-xs flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">System Expected Cash:</span>
                        <span class="font-bold font-mono text-sm text-slate-900 dark:text-white">
                            {{ format(calculatedExpectedCash) }}
                        </span>
                    </div>

                    <!-- Counted Cash Input -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                            Actual Counted Cash in Drawer
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-lg select-none">₦</span>
                            <input
                                v-model.number="countedCash"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0.00"
                                required
                                autofocus
                                class="w-full pl-10 pr-4 py-3 text-2xl font-bold font-mono text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 rounded-2xl focus:ring-2 focus:ring-rose-500 focus:outline-hidden"
                            />
                        </div>
                    </div>

                    <!-- Live Variance Indicator -->
                    <div
                        v-if="countedCash !== '' && countedCash !== null && countedCash !== undefined"
                        :class="[
                            'p-3.5 rounded-xl border text-xs font-bold flex items-center justify-between transition',
                            calculatedVariance === 0
                                ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
                                : calculatedVariance < 0
                                ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300'
                                : 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-800 text-blue-800 dark:text-blue-300'
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-base">{{ calculatedVariance === 0 ? '✅' : calculatedVariance < 0 ? '⚠️' : 'ℹ️' }}</span>
                            <span>
                                {{ calculatedVariance === 0 ? 'Drawer Balanced Exactly' : calculatedVariance < 0 ? 'Cash Shortage' : 'Cash Surplus (Over)' }}
                            </span>
                        </div>
                        <div class="font-mono text-sm">
                            {{ calculatedVariance > 0 ? '+' : '' }}{{ format(calculatedVariance) }}
                        </div>
                    </div>

                    <!-- Closing Note -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Closing Note / Remarks <span class="text-slate-400 font-normal">(Optional)</span>
                        </label>
                        <input
                            v-model="closingNote"
                            type="text"
                            placeholder="e.g. End of morning shift, handover to Next Cashier"
                            class="w-full px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-rose-500 focus:outline-hidden"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="activeTab = 'overview'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-5 py-3 rounded-2xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/30 transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="isSubmitting" class="animate-spin">🔄</span>
                            <span v-else>🔒</span>
                            <span>{{ isSubmitting ? 'Closing Till...' : 'Confirm & Close Till Session' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
