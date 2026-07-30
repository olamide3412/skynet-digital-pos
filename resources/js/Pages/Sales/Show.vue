<script setup>
import { Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import { usePrint } from '@/Composables/usePrint'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({ sale: Object, settings: Object })
const { format } = useCurrency()
const { printElement } = usePrint()
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.sales.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Sale #{{ sale.receipt_id }}</h1>
            <div class="ml-auto flex gap-2">
                <button @click="printElement()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-2xl mx-auto space-y-4">
                <!-- Meta Card -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-slate-400 text-xs mb-0.5">Receipt ID</p>
                        <p class="font-mono font-bold text-white">{{ sale.receipt_id }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs mb-0.5">Date</p>
                        <p class="text-white">{{ dayjs(sale.created_at).format('DD-MMM-YYYY HH:mm') }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs mb-0.5">Cashier</p>
                        <p class="text-white">{{ (sale.user?.full_name || sale.user?.name) ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs mb-0.5">Customer</p>
                        <p class="text-white">{{ sale.customer?.name ?? 'Walk-in' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs mb-0.5">Purchase Type</p>
                        <p class="text-white">{{ sale.purchase_type }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs mb-0.5">Payment Method</p>
                        <p class="text-white">{{ sale.payment_method }}</p>
                    </div>
                    <div v-if="sale.is_debt" class="col-span-2">
                        <p class="text-xs text-red-400 bg-red-400/10 px-3 py-2 rounded-lg">⚠ This sale has an outstanding debt of {{ format(sale.final_total - sale.amount_paid) }}</p>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-700">
                        <h2 class="text-sm font-semibold text-white">Items Sold</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-slate-700/40 text-xs text-slate-400">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Item</th>
                                <th class="text-right px-4 py-2 font-medium">Price</th>
                                <th class="text-right px-4 py-2 font-medium">Qty</th>
                                <th class="text-right px-4 py-2 font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <tr v-for="order in sale.sale_orders" :key="order.id" class="hover:bg-slate-700/20">
                                <td class="px-4 py-2 text-white">{{ order.item_name }}</td>
                                <td class="px-4 py-2 text-right text-slate-300">{{ format(order.selling_price) }}</td>
                                <td class="px-4 py-2 text-right text-slate-300">{{ order.qty }}</td>
                                <td class="px-4 py-2 text-right text-emerald-400 font-medium">{{ format(order.total_selling_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-4 space-y-2 text-sm">
                    <div class="flex justify-between text-slate-400"><span>Subtotal</span><span>{{ format(sale.amount_cost) }}</span></div>
                    <div v-if="sale.consultation_fee > 0" class="flex justify-between text-slate-400"><span>Consultation Fee</span><span>{{ format(sale.consultation_fee) }}</span></div>
                    <div v-if="sale.discount_amount > 0" class="flex justify-between text-red-400"><span>Discount</span><span>-{{ format(sale.discount_amount) }}</span></div>
                    <div class="flex justify-between text-white font-bold text-base border-t border-slate-700 pt-2"><span>Grand Total</span><span class="text-emerald-400">{{ format(sale.final_total) }}</span></div>
                    <div class="flex justify-between text-slate-400"><span>Amount Paid</span><span>{{ format(sale.amount_paid) }}</span></div>
                    <div v-if="sale.change_bal > 0" class="flex justify-between text-slate-300"><span>Change</span><span>{{ format(sale.change_bal) }}</span></div>
                    <div class="flex justify-between text-emerald-400 pt-1 border-t border-slate-700"><span>Profit Made</span><span>{{ format(sale.profit_made) }}</span></div>
                </div>
            </div>
        </div>
    </div>
</template>
