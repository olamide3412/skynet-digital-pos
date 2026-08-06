<script setup>
import { useCurrency }  from '@/Composables/useCurrency'
import { usePrint }     from '@/Composables/usePrint'
import { computed, ref, onMounted, watch, nextTick } from 'vue'
import dayjs            from 'dayjs'
import JsBarcode        from 'jsbarcode'

const props = defineProps({
    sale:     { type: Object, required: true },
    settings: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close'])

const { format }       = useCurrency()
const { printElement } = usePrint()
const barcodeSvg       = ref(null)

const saleDate = computed(() =>
    dayjs(props.sale.created_at || new Date()).format('DD MMM YYYY  HH:mm')
)

function renderBarcode() {
    if (!barcodeSvg.value || !props.sale.receipt_id) return
    try {
        JsBarcode(barcodeSvg.value, props.sale.receipt_id, {
            format:      'CODE128',
            width:       1.6,
            height:      48,
            displayValue: true,
            fontSize:    11,
            fontOptions: 'bold',
            margin:      4,
            background:  '#ffffff',
            lineColor:   '#000000',
            textAlign:   'center',
            textMargin:  4,
        })
    } catch (e) {
        console.warn('Barcode render failed:', e)
    }
}

onMounted(() => nextTick(renderBarcode))
watch(() => props.sale.receipt_id, () => nextTick(renderBarcode))

const items = computed(() => props.sale.sale_orders || props.sale.items || [])

const subtotal     = computed(() => items.value.reduce((s, i) => s + parseFloat(i.total_selling_price || 0), 0))
const amountPaid   = computed(() => parseFloat(props.sale.amount_paid  || 0))
const finalTotal   = computed(() => parseFloat(props.sale.final_total  || 0))
const discountAmt  = computed(() => parseFloat(props.sale.discount_amount || 0))
const taxAmt       = computed(() => parseFloat(props.sale.tax_amount   || 0))
const changeBal    = computed(() => parseFloat(props.sale.change_bal   || 0))
const debtAmt      = computed(() => props.sale.is_debt ? Math.max(0, finalTotal.value - amountPaid.value) : 0)

function printThermal() {
    printElement('.receipt-print-area', { paperSize: '80mm' })
}

function printA4() {
    printElement('.receipt-print-area', { paperSize: 'A4' })
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <!-- Modal shell -->
        <div class="bg-white text-gray-900 rounded-2xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden">

            <!-- ── Action bar (not printed) ────────────────────────────────── -->
            <div class="no-print flex items-center justify-between px-4 py-3 bg-slate-50 border-b border-slate-200">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="font-bold text-sm text-slate-800">Sales Receipt</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <!-- Print 80mm Thermal -->
                    <button @click="printThermal"
                        title="Print 80mm Thermal Receipt"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        80mm Thermal
                    </button>

                    <!-- Print A4 Invoice -->
                    <button @click="printA4"
                        title="Print A4 Invoice Paper"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        A4 Paper
                    </button>

                    <button @click="emit('close')"
                        class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition">
                        Close
                    </button>
                </div>
            </div>

            <!-- ── Receipt printable area ───────────────────────────────────── -->
            <div class="receipt-print-area overflow-y-auto max-h-[75vh] px-5 py-4"
                 style="font-family: 'Consolas', 'Lucida Console', 'Segoe UI', Arial, 'Courier New', monospace, sans-serif; font-size: 12px; font-weight: 600; line-height: 1.4; color: #000000;">

                <!-- ══ BUSINESS HEADER ══════════════════════════════════════ -->
                <div style="text-align:center; margin-bottom: 8px;">
                    <div style="font-size:16px; font-weight:900; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:3px; color:#000000;">
                        {{ settings.business_name || 'Business Name' }}
                    </div>
                    <div v-if="settings.business_address"
                         style="font-size:11px; font-weight:600; color:#000000; margin-bottom:2px;">
                        📍 {{ settings.business_address }}
                    </div>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; font-size:10.5px; font-weight:600; color:#000000; margin-top:2px;">
                        <span v-if="settings.business_contact_number">📞 {{ settings.business_contact_number }}</span>
                        <span v-if="settings.business_email">✉ {{ settings.business_email }}</span>
                    </div>
                </div>

                <!-- Divider -->
                <div style="border-top: 1.5px solid #000000; margin: 6px 0;"></div>

                <!-- ══ RECEIPT META ══════════════════════════════════════════ -->
                <table style="width:100%; font-size:11.5px; font-weight:600; border-collapse:collapse;">
                    <tr>
                        <td style="color:#000000;">Receipt #</td>
                        <td style="text-align:right; font-weight:800; color:#000000;">{{ sale.receipt_id }}</td>
                    </tr>
                    <tr>
                        <td style="color:#000000;">Date</td>
                        <td style="text-align:right; color:#000000;">{{ saleDate }}</td>
                    </tr>
                    <tr v-if="sale.customer">
                        <td style="color:#000000;">Customer</td>
                        <td style="text-align:right; color:#000000;">{{ sale.customer.name }}</td>
                    </tr>
                    <tr>
                        <td style="color:#000000;">Cashier</td>
                        <td style="text-align:right; color:#000000;">{{ sale.user?.full_name || sale.user?.name || '—' }}</td>
                    </tr>
                    <tr>
                        <td style="color:#000000;">Payment</td>
                        <td style="text-align:right; text-transform:capitalize; font-weight:700; color:#000000;">
                            {{ sale.payment_method || '—' }}
                        </td>
                    </tr>
                </table>

                <!-- Divider -->
                <div style="border-top: 1.5px solid #000000; margin: 6px 0;"></div>

                <!-- ══ ITEMS TABLE ═══════════════════════════════════════════ -->
                <table style="width:100%; border-collapse:collapse; font-size:11.5px;">
                    <thead>
                        <tr style="border-bottom: 1.5px solid #000000;">
                            <th style="text-align:left; padding-bottom:4px; font-weight:800; color:#000000;">Item</th>
                            <th style="text-align:center; padding-bottom:4px; font-weight:800; color:#000000; white-space:nowrap;">Qty</th>
                            <th style="text-align:right; padding-bottom:4px; font-weight:800; color:#000000; white-space:nowrap;">Unit</th>
                            <th style="text-align:right; padding-bottom:4px; font-weight:800; color:#000000; white-space:nowrap;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id"
                            style="border-bottom: 1px dashed #000000;">
                            <td style="padding: 4px 0; font-weight:600; color:#000000; word-break:break-word; max-width:100px;">
                                {{ item.item_name }}
                                <div v-if="item.unit_used && item.unit_used !== 'unit'"
                                     style="font-size:10px; font-weight:600; color:#000000; text-transform:capitalize;">
                                    ({{ item.unit_used }})
                                </div>
                            </td>
                            <td style="text-align:center; padding: 4px; font-weight:700; color:#000000;">{{ item.qty }}</td>
                            <td style="text-align:right; padding: 4px 0; font-weight:600; color:#000000; white-space:nowrap;">{{ format(item.selling_price) }}</td>
                            <td style="text-align:right; padding: 4px 0; font-weight:800; color:#000000; white-space:nowrap;">{{ format(item.total_selling_price) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Divider -->
                <div style="border-top: 1.5px solid #000000; margin: 6px 0;"></div>

                <!-- ══ TOTALS ════════════════════════════════════════════════ -->
                <table style="width:100%; border-collapse:collapse; font-size:11.5px; font-weight:600;">
                    <tr>
                        <td style="color:#000000;">Subtotal</td>
                        <td style="text-align:right; color:#000000;">{{ format(subtotal) }}</td>
                    </tr>
                    <tr v-if="discountAmt > 0">
                        <td style="color:#000000;">Discount</td>
                        <td style="text-align:right; color:#000000;">- {{ format(discountAmt) }}</td>
                    </tr>
                    <tr v-if="taxAmt > 0">
                        <td style="color:#000000;">Tax ({{ sale.tax_percentage }}%)</td>
                        <td style="text-align:right; color:#000000;">+ {{ format(taxAmt) }}</td>
                    </tr>
                    <tr v-if="(sale.consultation_fee || 0) > 0">
                        <td style="color:#000000;">Consult Fee</td>
                        <td style="text-align:right; color:#000000;">{{ format(sale.consultation_fee) }}</td>
                    </tr>
                    <!-- TOTAL row -->
                    <tr style="border-top: 2px solid #000000; border-bottom: 2px solid #000000;">
                        <td style="font-size:14px; font-weight:900; color:#000000; padding: 5px 0;">TOTAL</td>
                        <td style="text-align:right; font-size:14px; font-weight:900; color:#000000; padding: 5px 0;">{{ format(finalTotal) }}</td>
                    </tr>
                </table>

                <!-- Divider -->
                <div style="margin: 6px 0;"></div>

                <!-- ══ PAYMENT SUMMARY ═══════════════════════════════════════ -->
                <table style="width:100%; border-collapse:collapse; font-size:11.5px; font-weight:600;">
                    <tr>
                        <td style="color:#000000; text-transform:capitalize;">
                            Paid ({{ sale.payment_method }})
                        </td>
                        <td style="text-align:right; font-weight:800; color:#000000;">{{ format(amountPaid) }}</td>
                    </tr>
                    <tr v-if="changeBal > 0">
                        <td style="color:#000000;">Change</td>
                        <td style="text-align:right; color:#000000; font-weight:800;">{{ format(changeBal) }}</td>
                    </tr>
                    <tr v-if="sale.is_debt && debtAmt > 0"
                        style="border: 1.5px solid #000000; padding: 4px;">
                        <td style="color:#000000; font-weight:900; padding: 3px;">⚠ DEBT BALANCE</td>
                        <td style="text-align:right; color:#000000; font-weight:900; padding: 3px;">{{ format(debtAmt) }}</td>
                    </tr>
                </table>

                <!-- ══ BARCODE ═══════════════════════════════════════════════ -->
                <div style="display:flex; justify-content:center; align-items:center; margin: 10px 0 2px;">
                    <svg ref="barcodeSvg" style="display:block; margin:0 auto; max-width:100%; height:auto;"></svg>
                </div>

                <!-- ══ FOOTER ════════════════════════════════════════════════ -->
                <div style="border-top: 1.5px solid #000000; margin: 8px 0 4px; text-align:center;">
                    <p style="font-size:11.5px; font-weight:800; color:#000000; margin: 6px 0 2px; letter-spacing:0.5px;">
                        ★ Thank you for your patronage! ★
                    </p>
                    <p style="font-size:10px; font-weight:600; color:#000000; margin: 0 0 4px;">
                        Please keep this receipt for reference.
                    </p>
                    <p v-if="settings.business_contact_number || settings.business_email"
                       style="font-size:9.5px; font-weight:600; color:#000000; margin-top:3px;">
                        For enquiries:
                        <span v-if="settings.business_contact_number">{{ settings.business_contact_number }}</span>
                        <span v-if="settings.business_contact_number && settings.business_email"> · </span>
                        <span v-if="settings.business_email">{{ settings.business_email }}</span>
                    </p>
                    <p style="font-size:9px; font-weight:700; color:#000000; margin-top:6px; letter-spacing:0.3px;">
                        Powered by SkyNet POS
                    </p>
                </div>

            </div><!-- end receipt-print-area -->
        </div>
    </div>
</template>
