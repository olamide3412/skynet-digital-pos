<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/LaundryLayout.vue'

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
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Modals & Actions ────────────────────────────────────────────────────────
const showPaymentModal = ref(false)
const showStatusModal  = ref(false)
const showCollectModal = ref(false)
const showCancelModal  = ref(false)
const showPrintModal   = ref(false)

const paymentForm = ref({
    amount: props.order.balance || 0,
    method: 'Cash',
    reference: '',
    note: '',
})

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

const submitPayment = () => {
    router.post(
        safeRoute('laundry.orders.record-payment', { order: props.order.id }),
        paymentForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showPaymentModal.value = false
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
    router.post(
        safeRoute('laundry.orders.collect', { order: props.order.id }),
        collectForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showCollectModal.value = false
            },
        }
    )
}

const submitCancel = () => {
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

const printDirect = () => {
    window.print()
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
    <LaundryLayout :title="`Order #${order.order_number}`">
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Top Header & Workflow Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white">
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
                        @click="showPaymentModal = true"
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
                        @click="showCollectModal = true"
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

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- LEFT COLUMN: Items & Payments (8 Cols) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Garment Items Snapshot Table -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                            Order Items & Services
                        </h3>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-4 py-2.5">Service Item</th>
                                        <th class="px-4 py-2.5 text-center">Pricing</th>
                                        <th class="px-4 py-2.5 text-right">Unit Price</th>
                                        <th class="px-4 py-2.5 text-center">Quantity / Weight</th>
                                        <th class="px-4 py-2.5 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="item in order.items" :key="item.id" class="hover:bg-slate-50/50">
                                        <td class="px-4 py-3">
                                            <p class="font-bold text-slate-900 dark:text-white">{{ item.service_name }}</p>
                                            <p v-if="item.note" class="text-[11px] text-slate-400 italic">"{{ item.note }}"</p>
                                        </td>
                                        <td class="px-4 py-3 text-center capitalize text-slate-500">
                                            {{ item.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">
                                            {{ formatMoney(item.unit_price) }}
                                        </td>
                                        <td class="px-4 py-3 text-center font-bold text-slate-900 dark:text-white">
                                            {{ item.quantity }} {{ item.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">
                                            {{ formatMoney(item.line_total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Subtotal & Taxes Breakdown -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-1.5 text-xs text-right max-w-xs ml-auto">
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

                    <!-- Payments History Ledger -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                                Payments & Deposits ({{ order.payments.length }})
                            </h3>
                            <button
                                v-if="order.balance > 0 && order.status !== 'cancelled'"
                                @click="showPaymentModal = true"
                                class="text-xs font-bold text-indigo-600 hover:underline"
                            >
                                + Add Payment
                            </button>
                        </div>

                        <div v-if="order.payments.length === 0" class="py-6 text-center text-xs text-slate-400 border border-dashed rounded-xl">
                            No payments recorded yet for this order.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-4 py-2.5">Date & Time</th>
                                        <th class="px-4 py-2.5">Type</th>
                                        <th class="px-4 py-2.5">Method</th>
                                        <th class="px-4 py-2.5">Recorded By</th>
                                        <th class="px-4 py-2.5 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="p in order.payments" :key="p.id">
                                        <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ p.paid_at }}</td>
                                        <td class="px-4 py-2.5">
                                            <span :class="p.type === 'payment' ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold'">
                                                {{ p.type === 'payment' ? 'Payment' : 'Refund' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 font-medium">{{ p.method }}</td>
                                        <td class="px-4 py-2.5 text-slate-500">{{ p.recorded_by_name }}</td>
                                        <td class="px-4 py-2.5 text-right font-black" :class="p.type === 'payment' ? 'text-emerald-600' : 'text-rose-600'">
                                            {{ p.type === 'payment' ? '+' : '-' }}{{ formatMoney(p.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Workflow Status Audit History -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
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
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Customer Details & Timeline (4 Cols) -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Customer Snapshot Card -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3">
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
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3 text-xs">
                        <h3 class="font-bold uppercase tracking-wider text-slate-400">
                            Schedule & Pickup
                        </h3>

                        <div>
                            <span class="text-slate-400 block text-[11px]">Due Date & Time</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ order.due_at || '—' }}</span>
                        </div>

                        <div v-if="order.collected_at">
                            <span class="text-slate-400 block text-[11px]">Collected At</span>
                            <span class="font-bold text-emerald-600 text-sm">{{ order.collected_at }}</span>
                            <span v-if="order.collected_by_name" class="text-slate-400 block text-[10px]">Picked up by: {{ order.collected_by_name }}</span>
                        </div>

                        <div v-if="order.note">
                            <span class="text-slate-400 block text-[11px]">Intake Instructions</span>
                            <p class="italic text-slate-600 dark:text-slate-300">{{ order.note }}</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- 1. RECORD PAYMENT MODAL -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Record Balance Payment</h3>
                    <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Amount to Pay (Balance: {{ formatMoney(order.balance) }})</label>
                        <input v-model.number="paymentForm.amount" type="number" min="0.01" :max="order.balance" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 font-bold text-sm bg-slate-50 dark:bg-slate-900/50" />
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Payment Method</label>
                        <select v-model="paymentForm.method" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                            <option value="Cash">Cash</option>
                            <option value="Card / POS">Card / POS</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Reference / Note (Optional)</label>
                        <input v-model="paymentForm.reference" type="text" placeholder="e.g. transfer ref" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showPaymentModal = false" class="px-4 py-2 rounded-xl text-slate-600 font-semibold text-xs">Cancel</button>
                    <button @click="submitPayment" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700">Save Payment</button>
                </div>
            </div>
        </div>

        <!-- 2. STATUS UPDATE MODAL -->
        <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
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

        <!-- 3. MARK COLLECTED MODAL -->
        <div v-if="showCollectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Customer Pickup & Handover</h3>
                    <button @click="showCollectModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div v-if="order.balance > 0" class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 text-amber-800 text-xs space-y-1">
                    <p class="font-bold">Notice: Unpaid Balance Remaining ({{ formatMoney(order.balance) }})</p>
                    <p class="text-[11px]">Releasing an unpaid order will record the balance against the customer's store debt ledger.</p>
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
                    <button @click="showCollectModal = false" class="px-4 py-2 rounded-xl text-slate-600 font-semibold text-xs">Cancel</button>
                    <button @click="submitCollect" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700">Confirm Handover</button>
                </div>
            </div>
        </div>

        <!-- 4. CANCEL ORDER MODAL -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
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

        <!-- 5. PRINT DIRECT THERMAL TICKET MODAL -->
        <div v-if="showPrintModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="w-full max-w-sm bg-white text-slate-900 rounded-2xl shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="text-center space-y-1 pb-3 border-b border-dashed border-slate-300">
                    <h3 class="font-black text-lg uppercase tracking-wider">{{ branch.name }}</h3>
                    <p class="text-[11px] text-slate-500">{{ branch.address }}</p>
                    <p class="text-[11px] text-slate-500">Tel: {{ branch.phone }}</p>
                    <div class="pt-2">
                        <span class="px-3 py-1 rounded-md bg-slate-100 font-extrabold text-sm border border-slate-300 inline-block">
                            {{ order.order_number }}
                        </span>
                    </div>
                </div>

                <div class="text-xs space-y-1 pb-2 border-b border-dashed border-slate-300">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Customer:</span>
                        <span class="font-bold">{{ order.customer_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Phone:</span>
                        <span class="font-medium">{{ order.customer_phone }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Received:</span>
                        <span>{{ order.received_at }}</span>
                    </div>
                    <div class="flex justify-between font-bold">
                        <span>Due Date:</span>
                        <span>{{ order.due_at }}</span>
                    </div>
                </div>

                <!-- Ticket Line Items -->
                <div class="text-xs space-y-1.5 pb-2 border-b border-dashed border-slate-300">
                    <div v-for="item in order.items" :key="item.id" class="flex justify-between">
                        <div>
                            <p class="font-bold">{{ item.quantity }}x {{ item.service_name }}</p>
                            <p v-if="item.note" class="text-[10px] text-slate-400 italic">({{ item.note }})</p>
                        </div>
                        <span class="font-black">{{ formatMoney(item.line_total) }}</span>
                    </div>
                </div>

                <!-- Ticket Totals -->
                <div class="text-xs space-y-1 pb-3 border-b border-dashed border-slate-300 text-right">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal:</span>
                        <span>{{ formatMoney(order.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-black">
                        <span>Total:</span>
                        <span>{{ formatMoney(order.total) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-700">
                        <span>Paid:</span>
                        <span>{{ formatMoney(order.amount_paid) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-black text-rose-600">
                        <span>Balance Due:</span>
                        <span>{{ formatMoney(order.balance) }}</span>
                    </div>
                </div>

                <!-- Footer Disclaimer -->
                <div class="text-center text-[10px] text-slate-500 space-y-1">
                    <p class="font-semibold">{{ settings.receipt_footer || 'Thank you for choosing us! Please present this ticket at pickup.' }}</p>
                </div>

                <div class="flex items-center justify-between pt-3 gap-2">
                    <button @click="showPrintModal = false" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-semibold text-xs">
                        Close
                    </button>
                    <button @click="printDirect" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print Receipt</span>
                    </button>
                </div>
            </div>
        </div>

    </LaundryLayout>
</template>
