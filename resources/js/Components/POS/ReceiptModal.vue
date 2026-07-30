<script setup>
import { useCurrency } from '@/Composables/useCurrency'
import { usePrint } from '@/Composables/usePrint'
import { computed } from 'vue'
import dayjs from 'dayjs'

const props = defineProps({
    sale:     { type: Object, required: true },
    settings: { type: Object, required: true },
})
const emit = defineEmits(['close'])

const { format }     = useCurrency()
const { printElement } = usePrint()

const saleDate = computed(() =>
    dayjs(props.sale.created_at || new Date()).format('DD-MMM-YYYY HH:mm')
)
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="bg-white text-gray-900 rounded-xl shadow-2xl w-full max-w-sm overflow-hidden">
            <!-- Actions (not printed) -->
            <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50 no-print">
                <h3 class="font-bold text-gray-800">Receipt</h3>
                <div class="flex gap-2">
                    <button @click="printElement()" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-500 transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>
                    <button @click="emit('close')" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs rounded-lg hover:bg-gray-300 transition">Close</button>
                </div>
            </div>

            <!-- Receipt Content -->
            <div class="receipt-print-area p-4 font-mono text-xs overflow-y-auto max-h-[70vh]">
                <!-- Header -->
                <div class="text-center mb-3">
                    <p class="font-bold text-base uppercase">{{ settings.business_name }}</p>
                    <p v-if="settings.business_address" class="text-gray-600">{{ settings.business_address }}</p>
                    <p v-if="settings.business_contact_number" class="text-gray-600">{{ settings.business_contact_number }}</p>
                    <div class="border-t border-dashed border-gray-400 mt-2 pt-2">
                        <p>Receipt: <strong>{{ sale.receipt_id }}</strong></p>
                        <p>Date: {{ saleDate }}</p>
                        <p v-if="sale.customer">Customer: {{ sale.customer.name }}</p>
                        <p>Cashier: {{ sale.user?.full_name || sale.user?.name || 'N/A' }}</p>
                    </div>
                </div>

                <!-- Items -->
                <div class="border-t border-dashed border-gray-400 pt-2 mb-2">
                    <div v-for="item in (sale.sale_orders || sale.items || [])" :key="item.id"
                        class="flex justify-between mb-1">
                        <div class="flex-1">
                            <p class="font-medium">{{ item.item_name }}</p>
                            <p class="text-gray-500">{{ item.qty }} × {{ format(item.selling_price) }}</p>
                        </div>
                        <p class="font-medium">{{ format(item.total_selling_price) }}</p>
                    </div>
                </div>

                <!-- Totals -->
                <div class="border-t border-dashed border-gray-400 pt-2 space-y-0.5">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span>{{ format(sale.amount_cost) }}</span>
                    </div>
                    <div v-if="sale.discount_amount > 0" class="flex justify-between text-red-600">
                        <span>Discount</span>
                        <span>-{{ format(sale.discount_amount) }}</span>
                    </div>
                    <div v-if="sale.consultation_fee > 0" class="flex justify-between">
                        <span>Consult Fee</span>
                        <span>{{ format(sale.consultation_fee) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-sm border-t border-dashed border-gray-400 mt-1 pt-1">
                        <span>TOTAL</span>
                        <span>{{ format(sale.final_total) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>{{ sale.payment_method }}</span>
                        <span>{{ format(sale.amount_paid) }}</span>
                    </div>
                    <div v-if="sale.change_bal > 0" class="flex justify-between">
                        <span>Change</span>
                        <span>{{ format(sale.change_bal) }}</span>
                    </div>
                    <div v-if="sale.is_debt" class="flex justify-between text-red-600 font-bold">
                        <span>DEBT</span>
                        <span>{{ format(sale.final_total - sale.amount_paid) }}</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-3 border-t border-dashed border-gray-400 pt-2 text-gray-500">
                    <p>Thank you for your purchase!</p>
                    <p>Please keep this receipt.</p>
                </div>
            </div>
        </div>
    </div>
</template>
