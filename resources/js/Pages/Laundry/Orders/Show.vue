<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryTicketModal from '@/Components/Laundry/LaundryTicketModal.vue'
import OpenTillModal from '@/Components/POS/OpenTillModal.vue'
import TillManagementModal from '@/Components/POS/TillManagementModal.vue'
import TillSlipModal from '@/Components/POS/TillSlipModal.vue'

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    whatsapp_url: {
        type: String,
        default: null,
    },
    branch: {
        type: Object,
        required: true,
    },
    settings: {
        type: Object,
        required: true,
    },
    canReleaseUnpaid: {
        type: Boolean,
        default: false,
    },
    activeTillSession: {
        type: Object,
        default: null,
    },
    canManageTillCashMovements: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()

// ── Till Session State & Modals ──────────────────────────────────────────────
const activeTill = ref(props.activeTillSession || page.props.active_till_session || null)
watch(() => props.activeTillSession, (val) => { activeTill.value = val })
watch(() => page.props.active_till_session, (val) => { if (!activeTill.value) activeTill.value = val })

const isTillMandatory = computed(() => {
    const req = props.settings?.require_till_opening ?? page.props.pos_settings?.require_till_opening ?? false
    return Boolean(req && !activeTill.value)
})

const canReleaseUnpaid = computed(() => {
    return Boolean(
        props.canReleaseUnpaid ||
        props.settings?.allow_unpaid_pickup ||
        page.props.auth?.user?.is_super_admin ||
        page.props.auth?.user?.is_branch_admin
    )
})

const showOpenTillModal = ref(false)
const showTillManagementModal = ref(false)
const showTillSlipModal = ref(false)
const slipSession = ref(null)
const pendingPaymentModalAfterTill = ref(false)

function onTillOpened(session) {
    activeTill.value = session
    showOpenTillModal.value = false
    paymentErrors.value = ''
    if (pendingPaymentModalAfterTill.value) {
        pendingPaymentModalAfterTill.value = false
        openPaymentModal()
    }
}

function onTillUpdated(updatedSession) {
    activeTill.value = updatedSession
}

function onTillClosed(closedSession) {
    activeTill.value = null
    showTillManagementModal.value = false
    if (props.settings?.print_till_slip) {
        slipSession.value = closedSession
        showTillSlipModal.value = true
    }
}
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatQty = (val) => {
    const num = Number(val || 0)
    return num % 1 === 0 ? num.toString() : num.toString()
}

// ── Modals & Actions ────────────────────────────────────────────────────────
const isItemsExpanded    = ref(false)
const isPaymentsExpanded = ref(true)
const isOtherDetailsExpanded = ref(false)
const showPaymentModal   = ref(false)
const showStatusModal    = ref(false)
const showCollectModal   = ref(false)
const showCancelModal    = ref(false)
const showPrintModal     = ref(false)
const showUnpaidWarningModal = ref(false)

const printDirect = () => {
    showPrintModal.value = true
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search)
        if (params.get('print') === '1' || params.get('print') === 'true') {
            showPrintModal.value = true
        }
    }
})

const paymentForm = ref({
    amount: props.order.balance || 0,
    method: 'Cash',
    reference: '',
    note: '',
})

const paymentDisplayAmount = ref('')

const formatWithCommas = (val) => {
    if (val === null || val === undefined || val === '') return ''
    const str = String(val).replace(/[^0-9.]/g, '')
    const parts = str.split('.')
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    if (parts.length > 1) {
        return parts[0] + '.' + parts.slice(1).join('').slice(0, 2)
    }
    return parts[0]
}

const parseMoney = (val) => {
    if (!val) return 0
    const clean = String(val).replace(/[^0-9.]/g, '')
    const parts = clean.split('.')
    const normalized = parts[0] + (parts.length > 1 ? '.' + parts.slice(1).join('') : '')
    const num = parseFloat(normalized)
    return isNaN(num) ? 0 : Math.round(num * 100) / 100
}

const formatNumberWithDecimals = (val) => {
    const num = parseMoney(val)
    if (num <= 0) return ''
    return num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const onPaymentAmountInput = (event) => {
    const input = event.target
    const origVal = input.value
    const origCursor = input.selectionStart || 0
    const commasBefore = (origVal.slice(0, origCursor).match(/,/g) || []).length

    const formatted = formatWithCommas(origVal)
    paymentDisplayAmount.value = formatted
    paymentForm.value.amount = parseMoney(formatted)

    nextTick(() => {
        const newCommasBefore = (formatted.slice(0, origCursor).match(/,/g) || []).length
        const shift = newCommasBefore - commasBefore
        const newCursor = Math.max(0, Math.min(formatted.length, origCursor + shift))
        input.setSelectionRange(newCursor, newCursor)
    })
}

const onPaymentAmountBlur = () => {
    const num = parseMoney(paymentDisplayAmount.value)
    if (num > 0) {
        paymentForm.value.amount = num
        paymentDisplayAmount.value = formatNumberWithDecimals(num)
    } else {
        paymentForm.value.amount = 0
        paymentDisplayAmount.value = ''
    }
}

const statusForm = ref({
    status: props.order.status,
    note: '',
})

const collectForm = ref({
    collected_by_name: '',
    note: '',
})

const cancelForm = ref({
    reason: '',
    refund_amount: props.order.amount_paid || 0,
    refund_method: 'Cash',
})

const paymentErrors = ref('')
const collectErrors = ref('')
const isSubmittingCollect = ref(false)
const isSubmittingPayment = ref(false)

const handleMarkPickedUpClick = () => {
    collectErrors.value = ''
    if (Number(props.order.balance || 0) > 0.01) {
        showUnpaidWarningModal.value = true
    } else {
        showCollectModal.value = true
    }
}

const proceedToPaymentFromWarning = () => {
    showUnpaidWarningModal.value = false
    openPaymentModal()
}

const proceedToHandoverFromWarning = () => {
    showUnpaidWarningModal.value = false
    showCollectModal.value = true
}

const openPaymentModal = () => {
    if (isTillMandatory.value) {
        pendingPaymentModalAfterTill.value = true
        showOpenTillModal.value = true
        return
    }
    const bal = Number(props.order.balance || 0)
    paymentForm.value = {
        amount: bal,
        method: 'Cash',
        reference: '',
        note: '',
    }
    paymentDisplayAmount.value = formatNumberWithDecimals(bal)
    paymentErrors.value = ''
    isSubmittingPayment.value = false
    showPaymentModal.value = true
}

const setPaymentPreset = (fraction) => {
    const bal = Number(props.order.balance || 0)
    if (fraction === 1) {
        paymentForm.value.amount = bal
        paymentDisplayAmount.value = formatNumberWithDecimals(bal)
    } else if (fraction === 0.5) {
        const half = Math.round((bal / 2) * 100) / 100
        paymentForm.value.amount = half
        paymentDisplayAmount.value = formatNumberWithDecimals(half)
    } else {
        paymentForm.value.amount = 0
        paymentDisplayAmount.value = ''
    }
}

const submitPayment = () => {
    paymentErrors.value = ''
    if (isSubmittingPayment.value) return

    const amt = parseMoney(paymentDisplayAmount.value)
    paymentForm.value.amount = amt
    if (amt > 0) {
        paymentDisplayAmount.value = formatNumberWithDecimals(amt)
    }

    if (amt <= 0) {
        paymentErrors.value = 'Please enter a valid payment amount greater than zero.'
        return
    }
    if (amt > props.order.balance + 0.001) {
        paymentErrors.value = `Payment amount cannot exceed the order balance (${formatMoney(props.order.balance)}).`
        return
    }

    if (isTillMandatory.value) {
        showOpenTillModal.value = true
        return
    }

    isSubmittingPayment.value = true

    router.post(
        safeRoute('laundry.orders.record-payment', { order: props.order.id }),
        {
            ...paymentForm.value,
            amount: amt,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showPaymentModal.value = false
            },
            onError: (errs) => {
                paymentErrors.value = Object.values(errs).flat().join(' ')
                if (errs.till || paymentErrors.value.toLowerCase().includes('till')) {
                    showOpenTillModal.value = true
                }
            },
            onFinish: () => {
                isSubmittingPayment.value = false
            },
        }
    )
}

const submitStatusChange = () => {
    router.post(
        safeRoute('laundry.orders.update-status', { order: props.order.id }),
        statusForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showStatusModal.value = false
            },
        }
    )
}

const submitCollect = () => {
    collectErrors.value = ''
    isSubmittingCollect.value = true
    router.post(
        safeRoute('laundry.orders.collect', { order: props.order.id }),
        collectForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showCollectModal.value = false
                isSubmittingCollect.value = false
            },
            onError: (errs) => {
                isSubmittingCollect.value = false
                collectErrors.value = Object.values(errs).flat().join(' ')
            },
            onFinish: () => {
                isSubmittingCollect.value = false
            },
        }
    )
}

const submitCancel = () => {
    if (Number(cancelForm.value.refund_amount || 0) > 0 && isTillMandatory.value) {
        showOpenTillModal.value = true
        return
    }
    router.post(
        safeRoute('laundry.orders.cancel', { order: props.order.id }),
        cancelForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelModal.value = false
            },
        }
    )
}

const statusBadgeClass = (status) => {
    switch (status) {
        case 'received': return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800'
        case 'in_progress': return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800'
        case 'ready': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        case 'collected': return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
        case 'cancelled': return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800'
        default: return 'bg-slate-100 text-slate-700'
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <Link
                    :href="safeRoute('laundry.orders.index')"
                    class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 text-xs font-semibold transition flex-shrink-0"
                    title="Back to Orders"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </Link>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white leading-tight truncate">Order {{ order.order_number }}</h1>
                        <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-extrabold uppercase border', statusBadgeClass(order.status)]">
                            {{ order.status }}
                        </span>
                        <span v-if="order.is_overdue" class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">
                            OVERDUE
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Customer: <strong class="text-slate-700 dark:text-slate-300">{{ order.customer_name }}</strong> • Due: {{ order.due_at || '—' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- Till Session Status Badge / Open Till Trigger -->
                <button
                    v-if="activeTill"
                    @click="showTillManagementModal = true"
                    type="button"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer hover:bg-emerald-100"
                    title="Click to view till shift details, add/remove cash, or close till"
                >
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="hidden sm:inline">Till:</span>
                    <span class="font-mono">{{ formatMoney(activeTill.summary?.expected_cash ?? activeTill.opening_amount ?? 0) }}</span>
                </button>
                <button
                    v-else-if="props.settings?.require_till_opening || isTillMandatory"
                    @click="showOpenTillModal = true"
                    type="button"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
                    title="Click to declare starting float and open till session"
                >
                    <span>⚠️</span>
                    <span>Open Till</span>
                </button>

                <button
                    @click="printDirect"
                    type="button"
                    class="px-3 sm:px-3.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600 cursor-pointer active:scale-95"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Print Ticket</span>
                </button>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">
            <div class="max-w-6xl mx-auto space-y-4 sm:space-y-6">

            <!-- Top Header & Workflow Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap">
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                            {{ order.order_number }}
                        </h2>
                        <span :class="['px-3 py-1 rounded-full text-xs font-extrabold uppercase border', statusBadgeClass(order.status)]">
                            {{ order.status }}
                        </span>
                        <span v-if="order.is_overdue" class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">
                            OVERDUE
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Received on <span class="font-medium text-slate-700 dark:text-slate-300">{{ order.received_at }}</span> by {{ order.created_by_name }}
                    </p>
                </div>

                <!-- Action Buttons Group -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- WhatsApp Click-to-Chat Button (When Ready or has phone) -->
                    <a
                        v-if="whatsapp_url && order.status !== 'cancelled'"
                        :href="whatsapp_url"
                        target="_blank"
                        rel="noopener"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition"
                        title="Send ready notification via WhatsApp"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.101.005.241-.038.376.289.145.347.491 1.2.534 1.288.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.087-.179.182-.077.357.101.173.453.747.973 1.21.669.596 1.232.781 1.405.868.173.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z" />
                        </svg>
                        <span>WhatsApp Customer</span>
                    </a>

                    <!-- Print Ticket Button -->
                    <button
                        @click="showPrintModal = true"
                        type="button"
                        class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-xs flex items-center gap-1.5 transition"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print Ticket</span>
                    </button>

                    <!-- Add Payment / Settle Balance -->
                    <button
                        v-if="order.balance > 0 && order.status !== 'cancelled'"
                        @click="openPaymentModal"
                        type="button"
                        class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Record Payment</span>
                    </button>

                    <!-- Mark Collected Pickup -->
                    <button
                        v-if="order.status === 'ready'"
                        @click="handleMarkPickedUpClick"
                        type="button"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Mark Picked Up</span>
                    </button>

                    <!-- Change Stage Dropdown/Button -->
                    <button
                        v-if="order.status !== 'collected' && order.status !== 'cancelled'"
                        @click="showStatusModal = true"
                        type="button"
                        class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition"
                    >
                        <span>Change Status</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Cancel Button -->
                    <button
                        v-if="order.status !== 'collected' && order.status !== 'cancelled'"
                        @click="showCancelModal = true"
                        type="button"
                        class="px-2.5 py-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-xs font-semibold transition"
                        title="Cancel Order"
                    >
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Handover Status Alert Banner if Collected -->
            <div v-if="order.status === 'collected'" class="p-4 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-950/40 dark:to-teal-950/30 border border-emerald-200 dark:border-emerald-800/80 flex flex-wrap items-center justify-between gap-3 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-extrabold text-sm text-emerald-950 dark:text-emerald-100">Order Collected & Handed Over</h4>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-600 text-white">Collected</span>
                        </div>
                        <p class="text-xs text-emerald-900 dark:text-emerald-200 mt-0.5">
                            Collected / Picked up by: <strong class="font-black text-slate-900 dark:text-white underline decoration-emerald-500">{{ order.collected_by_name || order.customer_name }}</strong>
                            <span v-if="order.collected_by_name && order.collected_by_name !== order.customer_name" class="text-slate-600 dark:text-slate-300 font-medium"> (on behalf of {{ order.customer_name }})</span>
                            • Handed over on: <strong>{{ order.collected_at }}</strong>
                            <span v-if="order.collected_by_staff_name"> • Staff: <strong>{{ order.collected_by_staff_name }}</strong></span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- LEFT COLUMN: Items & Payments (8 Cols) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Garment Items Snapshot Table (Collapsible) -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div 
                            @click="isItemsExpanded = !isItemsExpanded"
                            class="flex items-center justify-between cursor-pointer select-none group gap-2"
                        >
                            <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap">
                                <h3 class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white uppercase tracking-wider group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition flex items-center gap-2">
                                    <span>Order Items & Services</span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ order.items.length }}
                                    </span>
                                </h3>
                                <span v-if="!isItemsExpanded" class="text-xs text-slate-500 dark:text-slate-400 font-mono font-semibold">
                                    • Total: {{ formatMoney(order.total) }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 sm:gap-2 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200 transition flex-shrink-0">
                                <span class="text-[11px] font-medium hidden sm:inline">{{ isItemsExpanded ? 'Collapse' : 'Expand' }}</span>
                                <svg 
                                    class="w-4 h-4 transition-transform duration-200"
                                    :class="{ 'rotate-180': isItemsExpanded }"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <div v-show="isItemsExpanded" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                            <!-- Mobile Cards View (< sm) -->
                            <div class="sm:hidden space-y-2.5">
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-700/80 space-y-2"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <p class="font-bold text-slate-900 dark:text-white text-sm leading-snug">
                                                    {{ item.service_name }}
                                                </p>
                                                <span v-if="item.has_discount || item.discount_amount > 0" class="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                                                    🏷️ {{ item.discount_name || 'Promo' }}
                                                </span>
                                            </div>
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-200/70 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 capitalize">
                                                {{ item.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}
                                            </span>
                                        </div>
                                        <span class="text-base font-black text-slate-900 dark:text-white font-mono flex-shrink-0">
                                            {{ formatMoney(item.line_total) }}
                                        </span>
                                    </div>

                                    <div v-if="item.note" class="text-[11px] text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 p-2 rounded-lg border border-amber-200/80 dark:border-amber-800/60 flex items-start gap-1.5">
                                        <span class="font-bold flex-shrink-0">🏷️ Garment Note:</span>
                                        <span class="italic font-medium">"{{ item.note }}"</span>
                                    </div>

                                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60 text-xs text-slate-500">
                                        <span>Qty: <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ formatQty(item.quantity) }} {{ item.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}</strong></span>
                                        <span class="flex items-center gap-1.5 font-mono">
                                            <span v-if="item.discount_amount > 0" class="line-through text-slate-400 text-[11px]">
                                                {{ formatMoney(item.original_price) }}
                                            </span>
                                            <span :class="item.discount_amount > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                                {{ formatMoney(item.unit_price) }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop & Tablet Table View (sm+) -->
                            <div class="hidden sm:block overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                        <tr>
                                            <th class="px-4 py-2.5">Service Item</th>
                                            <th class="px-3 py-2.5 text-center">Pricing</th>
                                            <th class="px-4 py-2.5 text-right whitespace-nowrap">Unit Price</th>
                                            <th class="px-3 py-2.5 text-center whitespace-nowrap">Quantity / Weight</th>
                                            <th class="px-4 py-2.5 text-right whitespace-nowrap">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <tr v-for="item in order.items" :key="item.id" class="hover:bg-slate-50/50">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <p class="font-bold text-slate-900 dark:text-white">{{ item.service_name }}</p>
                                                    <span v-if="item.has_discount || item.discount_amount > 0" class="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                                                        🏷️ {{ item.discount_name || 'Promo' }}
                                                    </span>
                                                </div>
                                                <div v-if="item.note" class="mt-1 flex items-center gap-1.5 text-[11px] text-amber-800 dark:text-amber-300 bg-amber-50/80 dark:bg-amber-950/40 px-2 py-0.5 rounded-md border border-amber-200/80 dark:border-amber-800/60 w-fit">
                                                    <span class="font-bold">🏷️ Note:</span>
                                                    <span class="italic font-medium">"{{ item.note }}"</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 text-center capitalize text-slate-500 whitespace-nowrap">
                                                {{ item.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300 whitespace-nowrap font-mono">
                                                <div v-if="item.discount_amount > 0" class="flex flex-col items-end">
                                                    <span class="line-through text-slate-400 text-[10px]">{{ formatMoney(item.original_price) }}</span>
                                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ formatMoney(item.unit_price) }}</span>
                                                </div>
                                                <span v-else>{{ formatMoney(item.unit_price) }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-center font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                                {{ formatQty(item.quantity) }} {{ item.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white whitespace-nowrap font-mono">
                                                {{ formatMoney(item.line_total) }}
                                                <div v-if="item.discount_amount > 0" class="text-[9px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                    Saved {{ formatMoney(item.discount_amount * item.quantity) }}
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Order Overall Intake Notes / Special Instructions -->
                            <div v-if="order.note" class="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 text-xs flex items-start gap-2.5">
                                <span class="text-base flex-shrink-0">📝</span>
                                <div class="space-y-0.5">
                                    <span class="font-black text-amber-900 dark:text-amber-200 uppercase tracking-wide text-[10px] block">Order Instructions & Attendant Remarks</span>
                                    <p class="text-slate-800 dark:text-slate-200 italic font-medium">"{{ order.note }}"</p>
                                </div>
                            </div>

                            <!-- Subtotal & Taxes Breakdown -->
                            <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-1.5 text-xs text-right w-full sm:max-w-xs ml-auto">
                                <div class="flex justify-between text-slate-500">
                                    <span>Subtotal:</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(order.subtotal) }}</span>
                                </div>
                                <div v-if="order.discount_amount > 0" class="flex justify-between text-emerald-600 font-semibold">
                                    <span>Discount:</span>
                                    <span>-{{ formatMoney(order.discount_amount) }}</span>
                                </div>
                                <div v-if="order.tax_amount > 0" class="flex justify-between text-slate-500">
                                    <span>VAT / Tax:</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(order.tax_amount) }}</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-700 text-sm font-black text-slate-900 dark:text-white">
                                    <span>Total:</span>
                                    <span class="text-indigo-600 text-base">{{ formatMoney(order.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments History Ledger (Collapsible) -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div 
                                @click="isPaymentsExpanded = !isPaymentsExpanded"
                                class="flex items-center gap-2 sm:gap-2.5 cursor-pointer select-none group flex-1 flex-wrap"
                            >
                                <h3 class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white uppercase tracking-wider group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition flex items-center gap-2">
                                    <span>Payments & Deposits</span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ order.payments.length }}
                                    </span>
                                </h3>
                                <span v-if="!isPaymentsExpanded" class="text-xs font-mono font-semibold" :class="order.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                    • Paid: {{ formatMoney(order.amount_paid) }} | Bal: {{ order.balance > 0 ? formatMoney(order.balance) : '₦0.00' }}
                                </span>
                                <div class="flex items-center gap-1 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200 transition ml-1 flex-shrink-0">
                                    <span class="text-[11px] font-medium hidden sm:inline">{{ isPaymentsExpanded ? 'Collapse' : 'Expand' }}</span>
                                    <svg 
                                        class="w-4 h-4 transition-transform duration-200"
                                        :class="{ 'rotate-180': isPaymentsExpanded }"
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>

                            <button
                                v-if="order.balance > 0 && order.status !== 'cancelled'"
                                @click.stop="openPaymentModal"
                                class="text-xs font-bold text-indigo-600 hover:underline flex-shrink-0"
                            >
                                + Add Payment
                            </button>
                        </div>

                        <div v-show="isPaymentsExpanded" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                            <div v-if="order.payments.length === 0" class="py-6 text-center text-xs text-slate-400 border border-dashed rounded-xl">
                                No payments recorded yet for this order.
                            </div>

                            <div v-else class="space-y-4">
                                <!-- Mobile Cards View (< sm) -->
                                <div class="sm:hidden space-y-2.5">
                                    <div
                                        v-for="p in order.payments"
                                        :key="p.id"
                                        class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-700/80 space-y-2.5"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    :class="p.type === 'payment' ? 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800'"
                                                >
                                                    {{ p.type === 'payment' ? 'Payment' : 'Refund' }}
                                                </span>
                                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                                    {{ p.method }}
                                                </span>
                                            </div>
                                            <span
                                                class="text-base font-black font-mono flex-shrink-0"
                                                :class="p.type === 'payment' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                            >
                                                {{ p.type === 'payment' ? '+' : '-' }}{{ formatMoney(p.amount) }}
                                            </span>
                                        </div>

                                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                            <span>{{ p.paid_at }}</span>
                                            <span>By: <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ p.recorded_by_name }}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Desktop & Tablet Table View (sm+) -->
                                <div class="hidden sm:block overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                            <tr>
                                                <th class="px-4 py-2.5">Date & Time</th>
                                                <th class="px-3 py-2.5 text-center">Type</th>
                                                <th class="px-4 py-2.5">Method</th>
                                                <th class="px-4 py-2.5">Recorded By</th>
                                                <th class="px-4 py-2.5 text-right whitespace-nowrap">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            <tr v-for="p in order.payments" :key="p.id">
                                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ p.paid_at }}</td>
                                                <td class="px-3 py-2.5 text-center">
                                                    <span :class="p.type === 'payment' ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold'">
                                                        {{ p.type === 'payment' ? 'Payment' : 'Refund' }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2.5 font-medium whitespace-nowrap">{{ p.method }}</td>
                                                <td class="px-4 py-2.5 text-slate-500">{{ p.recorded_by_name }}</td>
                                                <td class="px-4 py-2.5 text-right font-black whitespace-nowrap" :class="p.type === 'payment' ? 'text-emerald-600' : 'text-rose-600'">
                                                    {{ p.type === 'payment' ? '+' : '-' }}{{ formatMoney(p.amount) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 text-xs">
                                            <tr>
                                                <td colspan="5" class="px-4 py-2.5">
                                                    <div class="flex items-center justify-end gap-6">
                                                        <span class="font-bold text-slate-700 dark:text-slate-300">Total Payment Made:</span>
                                                        <span class="font-black text-emerald-600 dark:text-emerald-400 font-mono text-sm whitespace-nowrap">
                                                            {{ formatMoney(order.amount_paid) }}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="px-4 py-2.5 border-t border-slate-200/50 dark:border-slate-700/50">
                                                    <div class="flex items-center justify-end gap-6">
                                                        <span class="font-bold text-slate-700 dark:text-slate-300">Balance Left:</span>
                                                        <span class="font-black font-mono text-sm whitespace-nowrap" :class="order.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                                            {{ order.balance > 0 ? formatMoney(order.balance) : (formatMoney(0) + ' (Fully Paid)') }}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Summary Cards Below Payments & Deposits Table -->
                            <div class="pt-3 border-t border-slate-200 dark:border-slate-700 grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 text-xs">
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700/80">
                                    <span class="text-slate-500 dark:text-slate-400 text-[11px] font-semibold block uppercase tracking-wider">Total Order Amount</span>
                                    <span class="text-base font-black text-slate-900 dark:text-white font-mono mt-0.5 block whitespace-nowrap">
                                        {{ formatMoney(order.total) }}
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80">
                                    <span class="text-emerald-700 dark:text-emerald-400 text-[11px] font-bold block uppercase tracking-wider">Total Payment Made</span>
                                    <span class="text-base font-black text-emerald-700 dark:text-emerald-300 font-mono mt-0.5 block whitespace-nowrap">
                                        {{ formatMoney(order.amount_paid) }}
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl border" :class="order.balance > 0 ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/80' : 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/80'">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold uppercase tracking-wider block" :class="order.balance > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400'">
                                            Balance Left
                                        </span>
                                        <span v-if="order.balance <= 0" class="text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-emerald-200 text-emerald-800 dark:bg-emerald-800 dark:text-emerald-200">
                                            CLEARED
                                        </span>
                                        <span v-else class="text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-amber-200 text-amber-800 dark:bg-amber-800 dark:text-amber-200">
                                            DUE
                                        </span>
                                    </div>
                                    <span class="text-base font-black font-mono mt-0.5 block whitespace-nowrap" :class="order.balance > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'">
                                        {{ order.balance > 0 ? formatMoney(order.balance) : (formatMoney(0) + ' (Fully Paid)') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Status Audit History -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <h3 class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                            Lifecycle History
                        </h3>

                        <div class="space-y-3 relative pl-6 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-700">
                            <div v-for="h in order.history" :key="h.id" class="relative">
                                <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-indigo-600 ring-4 ring-white dark:ring-slate-800"></span>
                                <div class="text-xs">
                                    <p class="font-bold text-slate-900 dark:text-white">
                                        Status changed to <span class="uppercase font-black text-indigo-600">{{ h.to_status }}</span>
                                    </p>
                                    <p class="text-slate-400 text-[11px]">{{ h.changed_at }} by {{ h.changed_by_name }}</p>
                                    <p v-if="h.note" class="text-slate-500 italic mt-0.5">"{{ h.note }}"</p>

                                    <!-- Prominently show who collected it! -->
                                    <div v-if="h.to_status === 'collected' && (h.collected_by_name || order.collected_by_name)" class="mt-2 p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 space-y-0.5">
                                        <div class="flex items-center gap-1.5 font-bold text-xs">
                                            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>Collected / Picked up by:</span>
                                            <span class="font-black text-emerald-700 dark:text-emerald-300 underline decoration-emerald-400 decoration-2">{{ h.collected_by_name || order.collected_by_name }}</span>
                                        </div>
                                        <p v-if="order.customer_name && (h.collected_by_name || order.collected_by_name) !== order.customer_name" class="text-[11px] text-slate-500 dark:text-slate-400 pl-5.5">
                                            (Collected on behalf of customer: <strong>{{ order.customer_name }}</strong>)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Details (Collapsible) -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div
                            @click="isOtherDetailsExpanded = !isOtherDetailsExpanded"
                            class="flex items-center justify-between cursor-pointer select-none group gap-2"
                        >
                            <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap">
                                <h3 class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white uppercase tracking-wider group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition flex items-center gap-2">
                                    <span>Other Details</span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        Order #{{ order.order_number }}
                                    </span>
                                </h3>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-medium text-slate-400 group-hover:text-indigo-600 transition hidden sm:inline">
                                    {{ isOtherDetailsExpanded ? 'Collapse' : 'Expand' }}
                                </span>
                                <svg
                                    class="w-4 h-4 text-slate-400 group-hover:text-indigo-600 transition-transform duration-200"
                                    :class="{ 'rotate-180': isOtherDetailsExpanded }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <div v-show="isOtherDetailsExpanded" class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-4">
                            <!-- Grid of all details -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs">
                                <!-- 1. Order Number & Status -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/60 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Order Number</span>
                                    <p class="font-black text-sm text-slate-900 dark:text-white font-mono">{{ order.order_number }}</p>
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                                        {{ order.status }}
                                    </span>
                                </div>

                                <!-- 2. Intake Attendant & Date -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/60 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Intake Registration</span>
                                    <p class="font-bold text-slate-900 dark:text-white">{{ order.received_at || '—' }}</p>
                                    <p class="text-[11px] text-slate-500">By: <strong class="text-slate-700 dark:text-slate-300">{{ order.created_by_name }}</strong></p>
                                </div>

                                <!-- 3. Promised Turnaround -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/60 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Promised Turnaround Due</span>
                                    <p class="font-bold text-slate-900 dark:text-white">{{ order.due_at || '—' }}</p>
                                    <span v-if="order.is_overdue" class="text-[10px] font-bold text-rose-600 bg-rose-50 dark:bg-rose-950/40 px-1.5 py-0.5 rounded">⚠️ Overdue</span>
                                    <span v-else-if="order.status !== 'collected'" class="text-[10px] font-medium text-emerald-600">On Track</span>
                                    <span v-else class="text-[10px] font-medium text-slate-400">Completed</span>
                                </div>

                                <!-- 4. Customer Info -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/60 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Customer Information</span>
                                    <p class="font-bold text-slate-900 dark:text-white">{{ order.customer_name }}</p>
                                    <p class="text-[11px] text-indigo-600 font-semibold">{{ order.customer_phone || 'No phone' }}</p>
                                    <p v-if="order.customer_debt > 0" class="text-[10px] text-amber-600 font-semibold">Store Debt: {{ formatMoney(order.customer_debt) }}</p>
                                </div>

                                <!-- 5. Collection / Pickup Info -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/60 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Collection / Pickup</span>
                                    <p class="font-bold text-slate-900 dark:text-white">
                                        {{ order.collected_at ? order.collected_at : 'Pending Pickup' }}
                                    </p>
                                    <div v-if="order.collected_by_name" class="text-[11px] space-y-0.5">
                                        <p class="text-slate-600 dark:text-slate-300">
                                            Picked up by: <strong class="text-emerald-700 dark:text-emerald-400 font-black">{{ order.collected_by_name }}</strong>
                                        </p>
                                        <p v-if="order.collected_by_name !== order.customer_name" class="text-[10px] text-slate-400 italic">
                                            (On behalf of {{ order.customer_name }})
                                        </p>
                                        <p v-if="order.collected_by_staff_name" class="text-[10px] text-slate-500">
                                            Released by staff: {{ order.collected_by_staff_name }}
                                        </p>
                                    </div>
                                </div>

                                <!-- 6. Financial Summary -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/60 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Financial Summary</span>
                                    <p class="text-slate-700 dark:text-slate-300 text-[11px]">Subtotal: <strong class="font-mono">{{ formatMoney(order.subtotal) }}</strong></p>
                                    <p v-if="order.discount_amount > 0" class="text-emerald-600 dark:text-emerald-400 text-[11px]">
                                        Discount <span v-if="order.general_discount_name">({{ order.general_discount_name }})</span>:
                                        <strong class="font-mono">-{{ formatMoney(order.discount_amount) }}</strong>
                                    </p>
                                    <p v-if="order.tax_amount > 0" class="text-slate-500 text-[11px]">Tax: <strong class="font-mono">+{{ formatMoney(order.tax_amount) }}</strong></p>
                                    <p class="font-bold text-slate-900 dark:text-white">Total: <span class="font-mono">{{ formatMoney(order.total) }}</span></p>
                                    <p class="text-emerald-600 font-semibold text-[11px]">Paid: <span class="font-mono">{{ formatMoney(order.amount_paid) }}</span></p>
                                    <p class="font-bold" :class="order.balance > 0 ? 'text-amber-600' : 'text-emerald-600'">Balance: <span class="font-mono">{{ formatMoney(order.balance) }}</span></p>
                                </div>
                            </div>

                            <!-- Garment Special Instructions / Notes -->
                            <div v-if="order.note" class="p-3 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 text-xs">
                                <span class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300 uppercase block mb-0.5">Garment Intake Instructions & Care Notes</span>
                                <p class="text-slate-700 dark:text-slate-200 italic font-medium">"{{ order.note }}"</p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Customer Details & Timeline (4 Cols) -->
                <div class="lg:col-span-4 space-y-4 sm:space-y-6">

                    <!-- Customer Snapshot Card -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3">
                        <h3 class="font-bold text-xs uppercase tracking-wider text-slate-400">
                            Customer Profile
                        </h3>

                        <div class="space-y-1">
                            <p class="font-black text-base text-slate-900 dark:text-white">{{ order.customer_name }}</p>
                            <p class="text-xs text-indigo-600 font-semibold">{{ order.customer_phone }}</p>
                        </div>

                        <div v-if="order.customer_debt > 0" class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                            <span class="text-[10px] font-bold text-amber-700 uppercase block">Total Store Debt</span>
                            <span class="text-sm font-black text-amber-800 dark:text-amber-300">{{ formatMoney(order.customer_debt) }}</span>
                        </div>
                    </div>

                    <!-- Due & Collection Information -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3 text-xs">
                        <h3 class="font-bold uppercase tracking-wider text-slate-400">
                            Schedule & Pickup
                        </h3>

                        <div>
                            <span class="text-slate-400 block text-[11px]">Due Date & Time</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ order.due_at || '—' }}</span>
                        </div>

                        <div v-if="order.collected_at" class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 space-y-1.5">
                            <span class="text-emerald-800 dark:text-emerald-300 font-bold block text-[11px] uppercase tracking-wide">Pickup & Handover Details</span>
                            <div class="text-xs space-y-1">
                                <div>
                                    <span class="text-slate-500 dark:text-slate-400 block text-[10px]">Collected At</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-300">{{ order.collected_at }}</span>
                                </div>
                                <div v-if="order.collected_by_name">
                                    <span class="text-slate-500 dark:text-slate-400 block text-[10px]">Picked Up By</span>
                                    <span class="font-extrabold text-slate-900 dark:text-white text-xs">{{ order.collected_by_name }}</span>
                                    <span v-if="order.collected_by_name !== order.customer_name" class="text-[10px] text-slate-500 block">
                                        (on behalf of {{ order.customer_name }})
                                    </span>
                                </div>
                                <div v-if="order.collected_by_staff_name">
                                    <span class="text-slate-500 dark:text-slate-400 block text-[10px]">Staff Attendant</span>
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ order.collected_by_staff_name }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="order.note">
                            <span class="text-slate-400 block text-[11px]">Intake Instructions</span>
                            <p class="italic text-slate-600 dark:text-slate-300">{{ order.note }}</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>
        </div>

        <!-- 1. RECORD PAYMENT MODAL -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Record Balance Payment</h3>
                    <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600 text-base font-bold">✕</button>
                </div>

                <!-- Balance Summary Banner -->
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[11px] font-semibold text-amber-800 dark:text-amber-300 block">Outstanding Order Balance</span>
                        <span class="text-xs text-slate-500">Total Order: {{ formatMoney(order.total) }}</span>
                    </div>
                    <span class="font-black text-base text-amber-700 dark:text-amber-400 font-mono">
                        {{ formatMoney(order.balance) }}
                    </span>
                </div>

                <!-- Active Till Required Warning -->
                <div v-if="isTillMandatory" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-600 font-bold">⚠️</span>
                        <span class="text-amber-800 dark:text-amber-200 font-medium text-[11px]">Active till session required to process payments.</span>
                    </div>
                    <button
                        type="button"
                        @click="showOpenTillModal = true"
                        class="px-2.5 py-1 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] shrink-0 cursor-pointer shadow-xs active:scale-95"
                    >
                        Open Till
                    </button>
                </div>

                <!-- Error Alert if any -->
                <div v-if="paymentErrors" class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-rose-700 dark:text-rose-300 text-xs font-medium flex items-center justify-between gap-2">
                    <span>{{ paymentErrors }}</span>
                    <button
                        v-if="paymentErrors.toLowerCase().includes('till')"
                        type="button"
                        @click="showOpenTillModal = true"
                        class="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-[11px] shrink-0 cursor-pointer"
                    >
                        Open Till Now
                    </button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <!-- Amount Input with auto-select on click and formatted currency -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-slate-800 dark:text-slate-200">Payment Amount (₦)</label>
                            <span class="text-[11px] text-slate-400">Max: {{ formatMoney(order.balance) }}</span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-base pointer-events-none select-none">
                                ₦
                            </span>
                            <input
                                v-model="paymentDisplayAmount"
                                @input="onPaymentAmountInput"
                                @blur="onPaymentAmountBlur"
                                @focus="$event.target.select()"
                                type="text"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="w-full pl-8 pr-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 font-extrabold text-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition font-mono tracking-wide"
                            />
                        </div>
                        <!-- Live helper feedback -->
                        <div v-if="paymentForm.amount > 0" class="flex items-center justify-between mt-1.5 px-1 text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Paying: <strong class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">{{ formatMoney(paymentForm.amount) }}</strong></span>
                            <span v-if="order.balance - paymentForm.amount > 0.009" class="text-amber-600 dark:text-amber-400 font-semibold">
                                Remaining: {{ formatMoney(order.balance - paymentForm.amount) }}
                            </span>
                            <span v-else class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                                <span>✓</span> Fully Settled
                            </span>
                        </div>
                        <!-- Preset Quick Buttons -->
                        <div class="flex items-center gap-1.5 mt-2">
                            <button
                                type="button"
                                @click="setPaymentPreset(1)"
                                class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition cursor-pointer"
                            >
                                Pay Full ({{ formatMoney(order.balance) }})
                            </button>
                            <button
                                type="button"
                                @click="setPaymentPreset(0.5)"
                                class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                            >
                                50% ({{ formatMoney(order.balance / 2) }})
                            </button>
                            <button
                                type="button"
                                @click="setPaymentPreset(0)"
                                class="px-2.5 py-1 rounded-md text-[11px] font-semibold text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Payment Method</label>
                        <select v-model="paymentForm.method" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 font-medium">
                            <option value="Cash">Cash</option>
                            <option value="Card / POS">Card / POS</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <!-- Reference clearly distinct from amount -->
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Bank / POS Slip Reference (Optional)</label>
                        <input
                            v-model="paymentForm.reference"
                            type="text"
                            placeholder="e.g. POS Auth Code, Transfer Ref..."
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <button
                        @click="showPaymentModal = false"
                        :disabled="isSubmittingPayment"
                        class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-700 transition disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitPayment"
                        :disabled="isSubmittingPayment"
                        class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 shadow-sm transition disabled:opacity-50 flex items-center justify-center gap-1.5 min-w-[120px]"
                    >
                        <svg v-if="isSubmittingPayment" class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ isSubmittingPayment ? 'Saving Payment...' : 'Save Payment' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. STATUS UPDATE MODAL -->
        <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Update Workflow Stage</h3>
                    <button @click="showStatusModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Target Stage</label>
                        <select v-model="statusForm.status" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold">
                            <option value="received">Received</option>
                            <option value="in_progress">In Progress (Washing / Pressing)</option>
                            <option value="ready">Ready for Pickup</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Remarks / Note</label>
                        <input v-model="statusForm.note" type="text" placeholder="Stage transition note..." class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showStatusModal = false" class="px-4 py-2 rounded-xl text-slate-600 font-semibold text-xs">Cancel</button>
                    <button @click="submitStatusChange" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700">Update Stage</button>
                </div>
            </div>
        </div>

        <!-- 3. UNPAID BALANCE PROMPT MODAL -->
        <div v-if="showUnpaidWarningModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs transition-opacity animate-in fade-in duration-150">
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl border border-amber-200 dark:border-amber-800/60 shadow-2xl overflow-hidden p-6 text-center space-y-5">
                <!-- Icon Header -->
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 dark:bg-amber-950/60 border-2 border-amber-300 dark:border-amber-700/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <!-- Text Headings -->
                <div class="space-y-1">
                    <h3 class="text-xl font-black text-slate-900 dark:text-white">
                        Unpaid Balance Notice
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        This order has an outstanding pending balance remaining.
                    </p>
                </div>

                <!-- Breakdown Alert Card -->
                <div class="p-4 rounded-2xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/50 text-left space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-amber-200/60 dark:border-amber-800/40 text-xs">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Order #{{ order.order_number }}</span>
                        <span class="font-semibold text-slate-600 dark:text-slate-400 truncate max-w-[180px]">{{ order.customer_name }}</span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2 rounded-xl bg-white/70 dark:bg-slate-900/50 border border-amber-100 dark:border-amber-900/30">
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Total Bill</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(order.total) }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/70 dark:bg-slate-900/50 border border-amber-100 dark:border-amber-900/30">
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Paid</span>
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ formatMoney(order.amount_paid) }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-amber-100/70 dark:bg-amber-900/40 border border-amber-300/80 dark:border-amber-700/60">
                            <span class="block text-[10px] text-amber-800 dark:text-amber-300 uppercase font-black">Unpaid Due</span>
                            <span class="text-xs font-black text-rose-600 dark:text-rose-400">{{ formatMoney(order.balance) }}</span>
                        </div>
                    </div>

                    <div v-if="canReleaseUnpaid" class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/40 text-[11px] text-emerald-900 dark:text-emerald-200 leading-relaxed flex items-start gap-2">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>
                            <strong>Unpaid Pickup Policy Active:</strong> Garments can be released without settling now. Confirming handover will automatically post the remaining balance of <strong class="font-black text-rose-700 dark:text-rose-300">{{ formatMoney(order.balance) }}</strong> directly to <strong>{{ order.customer_name }}</strong>'s customer debt ledger.
                        </span>
                    </div>
                    <div v-else class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/40 text-[11px] text-rose-900 dark:text-rose-200 leading-relaxed flex items-start gap-2">
                        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>
                            <strong>Unpaid Pickup Disabled:</strong> Branch policy requires full payment before garments can be released. Please record the balance payment of <strong class="font-black text-rose-700 dark:text-rose-300">{{ formatMoney(order.balance) }}</strong> below, or obtain supervisor authorization.
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2 pt-1">
                    <button
                        @click="proceedToPaymentFromWarning"
                        type="button"
                        class="w-full py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 active:scale-[0.99] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Record Payment First ({{ formatMoney(order.balance) }})</span>
                    </button>

                    <button
                        v-if="canReleaseUnpaid"
                        @click="proceedToHandoverFromWarning"
                        type="button"
                        class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 active:scale-[0.99] text-slate-800 dark:text-slate-200 font-semibold text-xs border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-2 transition cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Proceed to Pickup & Transfer to Debt</span>
                    </button>
                    <div
                        v-else
                        class="w-full py-2.5 px-3 rounded-xl bg-slate-100 dark:bg-slate-800/60 text-slate-400 text-center text-[11px] font-medium border border-dashed border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Unpaid pickup transfer restricted (Disabled in Settings)</span>
                    </div>

                    <button
                        @click="showUnpaidWarningModal = false"
                        type="button"
                        class="w-full py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition cursor-pointer"
                    >
                        Cancel & Keep Pending
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. MARK COLLECTED MODAL -->
        <div v-if="showCollectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Customer Pickup & Handover</h3>
                    <button @click="showCollectModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <!-- Error alert if validation failed -->
                <div v-if="collectErrors" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs">
                    <p class="font-bold mb-0.5">Pickup Release Warning</p>
                    <p>{{ collectErrors }}</p>
                </div>

                <div v-if="order.balance > 0" class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 text-amber-900 dark:text-amber-200 text-xs space-y-2">
                    <div class="flex items-center justify-between font-bold">
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            Unpaid Balance: {{ formatMoney(order.balance) }}
                        </span>
                        <button
                            type="button"
                            @click="showCollectModal = false; openPaymentModal()"
                            class="px-2 py-1 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] transition shadow-xs"
                        >
                            Pay Balance Instead
                        </button>
                    </div>
                    <p class="text-[11px] text-amber-800 dark:text-amber-300">
                        Confirming pickup will release garments and transfer <strong>{{ formatMoney(order.balance) }}</strong> to <strong>{{ order.customer_name }}</strong>'s store debt balance.
                    </p>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Collected By (Person's name)</label>
                        <input v-model="collectForm.collected_by_name" type="text" :placeholder="order.customer_name" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Handover Notes</label>
                        <input v-model="collectForm.note" type="text" placeholder="Remarks..." class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showCollectModal = false" :disabled="isSubmittingCollect" class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-700 transition">Cancel</button>
                    <button @click="submitCollect" :disabled="isSubmittingCollect" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 shadow-sm transition disabled:opacity-50 flex items-center gap-1.5">
                        <svg v-if="isSubmittingCollect" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span>{{ order.balance > 0 ? 'Confirm Pickup & Transfer to Debt' : 'Confirm Handover' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. CANCEL ORDER MODAL -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-rose-600">Cancel Laundry Order</h3>
                    <button @click="showCancelModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Reason for Cancellation *</label>
                        <textarea v-model="cancelForm.reason" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" placeholder="State reason..."></textarea>
                    </div>

                    <div v-if="order.amount_paid > 0" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 space-y-2">
                        <span class="font-bold text-slate-700 block">Refund Customer Deposit:</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-slate-400">Refund Amount</label>
                                <input v-model.number="cancelForm.refund_amount" type="number" min="0" :max="order.amount_paid" class="w-full px-2.5 py-1.5 rounded-lg border font-bold" />
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400">Refund Method</label>
                                <select v-model="cancelForm.refund_method" class="w-full px-2.5 py-1.5 rounded-lg border">
                                    <option value="Cash">Cash</option>
                                    <option value="Card / POS">Card / POS</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showCancelModal = false" class="px-4 py-2 rounded-xl text-slate-600 font-semibold text-xs">Keep Order</button>
                    <button @click="submitCancel" :disabled="!cancelForm.reason.trim()" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-bold text-xs hover:bg-rose-700 disabled:opacity-50">Confirm Cancellation</button>
                </div>
            </div>
        </div>

        <!-- 5. POS-STYLE LAUNDRY TICKET MODAL (80mm & A4 + PDF Download) -->
        <LaundryTicketModal
            v-if="showPrintModal"
            :order="order"
            :branch="branch"
            :settings="settings"
            @close="showPrintModal = false"
        />

        <!-- Till Opening Modal -->
        <OpenTillModal
            v-if="showOpenTillModal"
            :show="showOpenTillModal"
            :mandatory="isTillMandatory"
            :settings="settings"
            @close="showOpenTillModal = false"
            @opened="onTillOpened"
        />

        <!-- Till Management Modal (Dashboard, Cash Movements, Close Till) -->
        <TillManagementModal
            v-if="showTillManagementModal && activeTill"
            :show="showTillManagementModal"
            :session="activeTill"
            :settings="settings"
            :can-manage-cash-movements="canManageTillCashMovements"
            @close="showTillManagementModal = false"
            @updated="onTillUpdated"
            @closed="onTillClosed"
        />

        <!-- Till Slip Printable Modal -->
        <TillSlipModal
            v-if="showTillSlipModal && slipSession"
            :session="slipSession"
            :summary="slipSession.summary"
            :settings="settings"
            @close="showTillSlipModal = false; slipSession = null"
        />
    </div>
</template>
