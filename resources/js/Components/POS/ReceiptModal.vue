<script setup>
import { usePage }        from '@inertiajs/vue3'
import { useCurrency }  from '@/Composables/useCurrency'
import { usePrint }     from '@/Composables/usePrint'
import { usePrinterSetting } from '@/Composables/usePrinterSetting'
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import dayjs            from 'dayjs'
import JsBarcode        from 'jsbarcode'

const props = defineProps({
    sale:         { type: Object, required: true },
    settings:     { type: Object, default: () => ({}) },
    isReprint:    { type: Boolean, default: false },
    customCopies: { type: Number, default: null },
})
const emit = defineEmits(['close'])

const page = usePage()
const { resolvePrinter } = usePrinterSetting()
const effectivePrinter = computed(() => resolvePrinter(props.settings))

// Paper format selection
const selectedPaperSize = ref(effectivePrinter.value.paper_size || '80mm')

// Determine copies: if reprinting from history, default to 1 copy; otherwise use workstation setting
const printCopies = ref(
    props.isReprint
        ? (props.customCopies ?? 1)
        : (props.customCopies ?? effectivePrinter.value.receipt_copies ?? 1)
)

const poweredByName = computed(() => {
    return page.props.system_config?.company_name || page.props.store_settings?.company_name || props.settings?.business_name || 'SkyNet Digital POS'
})

const { format }       = useCurrency()
const { printElement } = usePrint()
const barcodeSvg       = ref(null)

const saleDate = computed(() =>
    dayjs(props.sale.created_at || new Date()).format('DD MMM YYYY  HH:mm')
)

const printerName = computed(() => {
    return effectivePrinter.value.printer_name || 'Default POS Printer'
})

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

function printSelected() {
    printElement('.receipt-print-area', {
        paperSize: selectedPaperSize.value,
        printerConnection: effectivePrinter.value.printer_connection,
        printerIpAddress: effectivePrinter.value.printer_ip_address,
        printerName: effectivePrinter.value.printer_name,
        copies: printCopies.value || 1,
    })
}

function handleKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault()
        printSelected()
    } else if (e.key === 'Escape') {
        e.preventDefault()
        emit('close')
    }
}

onMounted(() => {
    nextTick(renderBarcode)
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})

watch(() => props.sale.receipt_id, () => {
    nextTick(renderBarcode)
})

const items = computed(() => props.sale.sale_orders || props.sale.saleOrders || props.sale.items || [])

const subtotal     = computed(() => items.value.reduce((s, i) => s + parseFloat(i.total_selling_price || 0), 0))
const amountPaid   = computed(() => parseFloat(props.sale.amount_paid  || 0))
const finalTotal   = computed(() => parseFloat(props.sale.final_total  || 0))
const discountAmt  = computed(() => parseFloat(props.sale.discount_amount || 0))
const taxAmt       = computed(() => parseFloat(props.sale.tax_amount   || 0))
const changeBal    = computed(() => parseFloat(props.sale.change_bal   || 0))
const debtAmt      = computed(() => props.sale.is_debt ? Math.max(0, finalTotal.value - amountPaid.value) : 0)
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <!-- Modal shell -->
        <div class="bg-white text-gray-900 rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[92vh] overflow-hidden border border-slate-200 dark:border-slate-700">

            <!-- ── Top Header (Clean & Spacious) ────────────────────────── -->
            <div class="no-print flex items-center justify-between px-5 py-3.5 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center font-bold text-lg flex-shrink-0">
                        🧾
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-sm text-slate-900 dark:text-white">Sales Receipt Preview</h2>
                            <span v-if="isReprint" class="text-[10px] px-2 py-0.5 bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 font-bold rounded-full">
                                Reprint
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-mono font-medium text-slate-700 dark:text-slate-300">#{{ sale.receipt_id }}</span>
                            <span v-if="sale.is_offline_sale || sale.offline_sale_id" class="px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-950/70 text-rose-700 dark:text-rose-300 text-[10px] font-bold uppercase">
                                Offline (Pending Sync)
                            </span>
                            <span>•</span>
                            <span class="truncate max-w-[180px]">{{ printerName }}</span>
                        </div>
                    </div>
                </div>

                <!-- Close 'X' Button -->
                <button
                    @click="emit('close')"
                    type="button"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition cursor-pointer"
                    title="Close (Esc)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- ── Receipt printable area (Scrollable middle body) ───────────── -->
            <div class="receipt-print-area overflow-y-auto flex-1 p-6 bg-white"
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
                    <tbody>
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
                        <tr v-if="sale.is_offline_sale || sale.offline_sale_id">
                            <td style="color:#000000; font-size:10px;">Mode</td>
                            <td style="text-align:right; font-weight:800; color:#000000; font-size:10px;">OFFLINE (PENDING SYNC)</td>
                        </tr>
                    </tbody>
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
                                <div v-if="item.imei_or_device_id"
                                     style="font-size:10px; font-family:monospace; font-weight:700; color:#000000; margin-top:1px;">
                                    IMEI: {{ item.imei_or_device_id }}
                                </div>
                                <div v-else-if="item.unit_used && item.unit_used !== 'unit'"
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
                    <tbody>
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
                    </tbody>
                </table>

                <!-- Divider -->
                <div style="margin: 6px 0;"></div>

                <!-- ══ PAYMENT SUMMARY ═══════════════════════════════════════ -->
                <table style="width:100%; border-collapse:collapse; font-size:11.5px; font-weight:600;">
                    <tbody>
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
                    </tbody>
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
                        Powered by {{ poweredByName }}
                    </p>
                </div>

            </div><!-- end receipt-print-area -->

            <!-- ── Bottom Action Toolbar (Spacious & Clean) ──────────────────── -->
            <div class="no-print bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
                <!-- Left: Paper format toggle & Copies selector -->
                <div class="flex items-center gap-3">
                    <!-- Paper Format Toggle -->
                    <div class="flex items-center bg-slate-200/80 dark:bg-slate-700 p-0.5 rounded-lg text-xs font-semibold">
                        <button
                            type="button"
                            @click="selectedPaperSize = '80mm'"
                            :class="['px-2.5 py-1 rounded-md transition cursor-pointer',
                                selectedPaperSize === '80mm'
                                    ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold'
                                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900']">
                            80mm Thermal
                        </button>
                        <button
                            type="button"
                            @click="selectedPaperSize = 'A4'"
                            :class="['px-2.5 py-1 rounded-md transition cursor-pointer',
                                selectedPaperSize === 'A4'
                                    ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold'
                                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900']">
                            A4 Page
                        </button>
                    </div>

                    <!-- Copies Stepper -->
                    <div class="flex items-center border border-slate-300 dark:border-slate-600 rounded-lg overflow-hidden bg-white dark:bg-slate-700 text-xs">
                        <button
                            type="button"
                            @click="printCopies = Math.max(1, printCopies - 1)"
                            class="px-2 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 font-bold cursor-pointer select-none">
                            -
                        </button>
                        <span class="px-2 py-1 font-mono font-bold text-xs text-slate-800 dark:text-slate-200">
                            {{ printCopies }} {{ printCopies === 1 ? 'Copy' : 'Copies' }}
                        </span>
                        <button
                            type="button"
                            @click="printCopies = Math.min(10, printCopies + 1)"
                            class="px-2 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 font-bold cursor-pointer select-none">
                            +
                        </button>
                    </div>
                </div>

                <!-- Right: Print and Done buttons -->
                <div class="flex items-center gap-2">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="px-3.5 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition cursor-pointer">
                        Done (Esc)
                    </button>

                    <button
                        @click="printSelected"
                        type="button"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition shadow-md shadow-emerald-600/20 active:scale-95 flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print ({{ selectedPaperSize }}{{ printCopies > 1 ? ' · ' + printCopies + 'x' : '' }})</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
