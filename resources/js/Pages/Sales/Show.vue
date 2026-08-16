<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import ReceiptModal from '@/Components/POS/ReceiptModal.vue'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    sale:     { type: Object, required: true },
    settings: { type: Object, default: () => ({}) },
})

const { format } = useCurrency()
const showReceiptModal = ref(false)
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.sales.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Sale #{{ sale.receipt_id }}</h1>
            <div class="ml-auto flex gap-2">
                <button
                    @click="showReceiptModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print Receipt / Invoice
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto space-y-4">
                <!-- Meta Card -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 grid grid-cols-2 gap-3 text-sm shadow-xs">
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mb-0.5 font-medium">Receipt ID</p>
                        <p class="font-mono font-bold text-slate-900 dark:text-white">{{ sale.receipt_id }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mb-0.5 font-medium">Date</p>
                        <p class="text-slate-900 dark:text-white font-medium">{{ dayjs(sale.created_at).format('DD-MMM-YYYY HH:mm') }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mb-0.5 font-medium">Cashier</p>
                        <p class="text-slate-900 dark:text-white font-medium">{{ (sale.user?.full_name || sale.user?.name) ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mb-0.5 font-medium">Customer</p>
                        <p class="text-slate-900 dark:text-white font-medium">{{ sale.customer?.name ?? 'Walk-in' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mb-0.5 font-medium">Purchase Type</p>
                        <p class="text-slate-900 dark:text-white font-medium">{{ sale.purchase_type }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mb-0.5 font-medium">Payment Method</p>
                        <p class="text-slate-900 dark:text-white font-medium">{{ sale.payment_method }}</p>
                    </div>
                    <div v-if="sale.is_debt" class="col-span-2">
                        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-400/10 border border-red-200 dark:border-red-500/20 px-3 py-2 rounded-lg font-bold">
                            ⚠ This sale has an outstanding debt balance of {{ format(sale.final_total - sale.amount_paid) }}
                        </p>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Items Sold</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-700/40 text-xs text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold">Item</th>
                                <th class="text-right px-4 py-2 font-semibold">Price</th>
                                <th class="text-right px-4 py-2 font-semibold">Qty</th>
                                <th class="text-right px-4 py-2 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="order in sale.sale_orders" :key="order.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                                <td class="px-4 py-2 text-slate-900 dark:text-white font-medium">{{ order.item_name }}</td>
                                <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-300 font-mono">{{ format(order.selling_price) }}</td>
                                <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-300 font-mono">{{ order.qty }}</td>
                                <td class="px-4 py-2 text-right text-emerald-600 dark:text-emerald-400 font-bold font-mono">{{ format(order.total_selling_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2 text-sm shadow-xs">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400 font-medium"><span>Subtotal</span><span class="font-mono">{{ format(sale.amount_cost || sale.final_total) }}</span></div>
                    <div v-if="sale.consultation_fee > 0" class="flex justify-between text-slate-600 dark:text-slate-400 font-medium"><span>Consultation Fee</span><span class="font-mono">{{ format(sale.consultation_fee) }}</span></div>
                    <div v-if="sale.discount_amount > 0" class="flex justify-between text-red-600 dark:text-red-400 font-semibold"><span>Discount</span><span class="font-mono">-{{ format(sale.discount_amount) }}</span></div>
                    <div class="flex justify-between text-slate-900 dark:text-white font-black text-base border-t border-slate-200 dark:border-slate-700 pt-2"><span>Grand Total</span><span class="text-emerald-600 dark:text-emerald-400 font-mono">{{ format(sale.final_total) }}</span></div>
                    <div class="flex justify-between text-slate-600 dark:text-slate-400 font-medium"><span>Amount Paid</span><span class="font-mono">{{ format(sale.amount_paid) }}</span></div>
                    <div v-if="sale.change_bal > 0" class="flex justify-between text-slate-700 dark:text-slate-300 font-medium"><span>Change</span><span class="font-mono">{{ format(sale.change_bal) }}</span></div>
                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-bold pt-1 border-t border-slate-200 dark:border-slate-700"><span>Profit Made</span><span class="font-mono">{{ format(sale.profit_made) }}</span></div>
                </div>
            </div>
        </div>

        <!-- Receipt Print Modal -->
        <ReceiptModal
            v-if="showReceiptModal"
            :sale="sale"
            :settings="settings"
            :is-reprint="true"
            @close="showReceiptModal = false"
        />
    </div>
</template>
