<script setup>
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useCurrency } from '@/Composables/useCurrency'
import { usePrint } from '@/Composables/usePrint'
import { usePrinterSetting } from '@/Composables/usePrinterSetting'
import JsBarcode from 'jsbarcode'

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    branch: {
        type: Object,
        default: () => ({}),
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    isReprint: {
        type: Boolean,
        default: false,
    },
    customCopies: {
        type: Number,
        default: null,
    },
})

const emit = defineEmits(['close'])

const page = usePage()
const { resolvePrinter } = usePrinterSetting()
const effectivePrinter = computed(() => resolvePrinter(props.settings))

// Paper format selection: 80mm Thermal or A4
const selectedPaperSize = ref(effectivePrinter.value.paper_size || '80mm')

// Print copies
const printCopies = ref(
    props.customCopies ?? (effectivePrinter.value.receipt_copies || 1)
)

const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const { format } = useCurrency(currencySymbol.value)
const { printElement } = usePrint()

const barcodeSvg = ref(null)
const isDownloading = ref(false)

const poweredByName = computed(() => {
    return page.props.system_config?.company_name || page.props.store_settings?.company_name || props.settings?.business_name || 'SkyNet Digital POS'
})

const branchName = computed(() => {
    return props.branch?.name || page.props.current_branch?.name || props.settings?.business_name || 'Laundry & Dry Cleaning'
})

const branchAddress = computed(() => {
    return props.branch?.address || page.props.current_branch?.address || props.settings?.business_address || ''
})

const branchPhone = computed(() => {
    return props.branch?.phone || page.props.current_branch?.phone || props.settings?.business_contact_number || ''
})

const branchEmail = computed(() => {
    return props.branch?.email || page.props.current_branch?.email || props.settings?.business_email || ''
})

const printerName = computed(() => {
    return effectivePrinter.value.printer_name || 'Default POS Printer'
})

const formatQty = (val) => {
    const num = Number(val || 0)
    return num % 1 === 0 ? num.toString() : num.toString()
}

const totalPieces = computed(() => {
    if (!props.order.items) return 0
    const sum = props.order.items.reduce((s, item) => s + (Number(item.quantity) || 0), 0)
    return formatQty(sum)
})

const paymentMethodsList = computed(() => {
    if (!props.order.payments || props.order.payments.length === 0) return ''
    const methods = props.order.payments.map(p => p.method).filter(Boolean)
    return [...new Set(methods)].join(', ')
})

function renderBarcode() {
    if (!barcodeSvg.value || !props.order?.order_number) return
    try {
        JsBarcode(barcodeSvg.value, props.order.order_number, {
            format: 'CODE128',
            width: 1.6,
            height: 48,
            displayValue: true,
            fontSize: 11,
            fontOptions: 'bold',
            margin: 4,
            background: '#ffffff',
            lineColor: '#000000',
            textAlign: 'center',
            textMargin: 4,
        })
    } catch (e) {
        console.warn('Barcode render failed:', e)
    }
}

function printSelected() {
    printElement('.laundry-ticket-print-area', {
        paperSize: selectedPaperSize.value,
        printerConnection: effectivePrinter.value.printer_connection,
        printerIpAddress: effectivePrinter.value.printer_ip_address,
        printerName: effectivePrinter.value.printer_name,
        copies: printCopies.value || 1,
    })
}

async function downloadPdf() {
    if (isDownloading.value) return
    isDownloading.value = true
    try {
        const html2pdfModule = await import('html2pdf.js')
        const html2pdf = html2pdfModule.default || html2pdfModule
        const element = document.querySelector('.laundry-ticket-print-area')
        if (!element) {
            console.error('Print area not found')
            return
        }

        const isA4 = selectedPaperSize.value === 'A4'
        const orderNumber = props.order?.order_number || 'order'
        const filename = `laundry-ticket-${orderNumber}.pdf`

        element.classList.add('pdf-mode')

        let opt
        if (isA4) {
            opt = {
                margin: [10, 10, 10, 10],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', windowWidth: 800 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            }
        } else {
            // 80mm thermal roll: 1px = 0.264583mm
            const heightMm = Math.max(140, Math.ceil(element.offsetHeight * 0.264583) + 15)
            opt = {
                margin: [3, 3, 3, 3],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', width: 340, windowWidth: 400 },
                jsPDF: { unit: 'mm', format: [80, heightMm], orientation: 'portrait' },
            }
        }

        await html2pdf().set(opt).from(element).save()
        element.classList.remove('pdf-mode')
    } catch (e) {
        console.error('Failed to generate PDF:', e)
    } finally {
        isDownloading.value = false
    }
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

watch(() => props.order?.order_number, () => {
    nextTick(renderBarcode)
})

watch(selectedPaperSize, () => {
    nextTick(renderBarcode)
})
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
        <!-- Modal Shell -->
        <div
            :class="[
                'bg-white text-gray-900 rounded-2xl shadow-2xl w-full flex flex-col max-h-[94vh] overflow-hidden border border-slate-200 dark:border-slate-700 transition-all duration-200',
                selectedPaperSize === 'A4' ? 'max-w-3xl' : 'max-w-lg'
            ]"
        >
            <!-- ── Top Header Toolbar (no-print) ────────────────────────── -->
            <div class="no-print flex items-center justify-between px-5 py-3.5 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center font-bold text-lg flex-shrink-0">
                        🧾
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-bold text-sm text-slate-900 dark:text-white">Laundry Ticket & Receipt Preview</h2>
                            <span v-if="isReprint" class="text-[10px] px-2 py-0.5 bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 font-bold rounded-full">
                                Reprint
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 truncate">
                            <span class="font-mono font-medium text-slate-700 dark:text-slate-300">#{{ order.order_number }}</span>
                            <span>•</span>
                            <span class="truncate max-w-[200px]">{{ printerName }}</span>
                        </div>
                    </div>
                </div>

                <!-- Close Button -->
                <button
                    @click="emit('close')"
                    type="button"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition cursor-pointer shrink-0"
                    title="Close (Esc)"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- ── Printable Ticket Preview Body ────────────────────────── -->
            <div class="overflow-y-auto flex-1 p-4 sm:p-6 bg-slate-100 dark:bg-slate-900/80 flex justify-center">
                <!-- Paper Container -->
                <div
                    :class="[
                        'bg-white text-black shadow-md border border-slate-200/80 rounded-sm transition-all',
                        selectedPaperSize === 'A4' ? 'w-full max-w-[680px]' : 'w-[330px] max-w-full'
                    ]"
                >
                    <div
                        class="laundry-ticket-print-area p-5 sm:p-6 bg-white"
                        style="font-family: 'Consolas', 'Lucida Console', 'Segoe UI', Arial, 'Courier New', monospace, sans-serif; font-size: 12px; font-weight: 600; line-height: 1.4; color: #000000; background-color: #ffffff;"
                    >
                        <!-- ══ BUSINESS HEADER ══════════════════════════════════ -->
                        <div style="text-align: center; margin-bottom: 8px;">
                            <div style="font-size: 16px; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 3px; color: #000000;">
                                {{ branchName }}
                            </div>
                            <div v-if="branchAddress" style="font-size: 11px; font-weight: 600; color: #000000; margin-bottom: 2px;">
                                📍 {{ branchAddress }}
                            </div>
                            <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; font-size: 10.5px; font-weight: 600; color: #000000; margin-top: 2px;">
                                <span v-if="branchPhone">📞 {{ branchPhone }}</span>
                                <span v-if="branchEmail">✉ {{ branchEmail }}</span>
                            </div>
                            <div style="font-size: 11.5px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; margin-top: 6px; padding: 2px 0; border-top: 1px dashed #000000; border-bottom: 1px dashed #000000;">
                                LAUNDRY INTAKE / PICKUP TICKET
                            </div>
                        </div>

                        <!-- ══ TICKET META ══════════════════════════════════════ -->
                        <table style="width: 100%; font-size: 11.5px; font-weight: 600; border-collapse: collapse; margin-top: 6px;">
                            <tbody>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Ticket #</td>
                                    <td style="text-align: right; font-weight: 900; font-size: 13px; color: #000000; padding: 2px 0;">{{ order.order_number }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Intake Date</td>
                                    <td style="text-align: right; color: #000000; padding: 2px 0;">{{ order.received_at || '—' }}</td>
                                </tr>
                                <tr style="background-color: #f3f4f6; font-weight: 800;">
                                    <td style="color: #000000; padding: 3px 4px;">⏰ Pickup Due</td>
                                    <td style="text-align: right; color: #000000; padding: 3px 4px; font-weight: 900;">{{ order.due_at || 'Standard Turnaround' }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Customer</td>
                                    <td style="text-align: right; font-weight: 800; color: #000000; padding: 2px 0;">{{ order.customer_name }}</td>
                                </tr>
                                <tr v-if="order.customer_phone">
                                    <td style="color: #000000; padding: 2px 0;">Phone</td>
                                    <td style="text-align: right; color: #000000; padding: 2px 0;">{{ order.customer_phone }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Attendant</td>
                                    <td style="text-align: right; color: #000000; padding: 2px 0;">{{ order.created_by_name || 'Staff' }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Status</td>
                                    <td style="text-align: right; text-transform: uppercase; font-weight: 800; color: #000000; padding: 2px 0;">{{ order.status }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Divider -->
                        <div style="border-top: 1.5px solid #000000; margin: 8px 0 6px;"></div>

                        <!-- ══ GARMENTS & SERVICES TABLE ═══════════════════════ -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <thead>
                                <tr style="border-bottom: 1.5px solid #000000;">
                                    <th style="text-align: left; padding: 0 0 4px 0; font-weight: 800; color: #000000; width: 18px;">#</th>
                                    <th style="text-align: left; padding: 0 4px 4px 0; font-weight: 800; color: #000000;">Item / Service</th>
                                    <th style="text-align: center; padding: 0 4px 4px; font-weight: 800; color: #000000; white-space: nowrap;">Qty</th>
                                    <th style="text-align: right; padding: 0 4px 4px; font-weight: 800; color: #000000; white-space: nowrap;">Rate</th>
                                    <th style="text-align: right; padding: 0 0 4px 4px; font-weight: 800; color: #000000; white-space: nowrap;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, idx) in order.items"
                                    :key="item.id || idx"
                                    style="border-bottom: 1px dashed #000000;"
                                >
                                    <td style="padding: 4px 2px 4px 0; font-weight: 800; color: #000000; vertical-align: top; width: 18px;">
                                        {{ idx + 1 }}.
                                    </td>
                                    <td style="padding: 4px 4px 4px 0; font-weight: 600; color: #000000; word-break: break-word;">
                                        <div>{{ item.service_name }}</div>
                                        <div v-if="item.pricing_type" style="font-size: 9.5px; color: #4b5563; font-weight: 500;">
                                            [{{ item.pricing_type === 'kg' ? 'Per KG' : 'Per Piece' }}]
                                        </div>
                                        <div v-if="item.discount_amount > 0" style="font-size: 9.5px; color: #000000; font-weight: 700;">
                                            🏷 Promo: {{ item.discount_name || 'Discount' }} (-{{ format(item.discount_amount) }})
                                        </div>
                                        <div v-if="item.note" style="font-size: 9.5px; font-style: italic; color: #111827; margin-top: 1.5px; font-weight: 700;">
                                            🏷 Note: {{ item.note }}
                                        </div>
                                    </td>
                                    <td style="text-align: center; padding: 4px 4px; font-weight: 700; color: #000000; vertical-align: top; white-space: nowrap;">
                                        {{ formatQty(item.quantity) }} {{ item.pricing_type === 'kg' ? 'kg' : 'pcs' }}
                                    </td>
                                    <td style="text-align: right; padding: 4px 4px; font-weight: 600; color: #000000; white-space: nowrap; vertical-align: top;">
                                        {{ format(item.unit_price) }}
                                    </td>
                                    <td style="text-align: right; padding: 4px 0 4px 4px; font-weight: 800; color: #000000; white-space: nowrap; vertical-align: top;">
                                        {{ format(item.line_total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- ══ SPECIAL INSTRUCTIONS / NOTE ═══════════════════════ -->
                        <div v-if="order.note" style="border: 1.5px dashed #000000; padding: 6px 8px; margin: 8px 0; background-color: #f9fafb; font-size: 10.5px; color: #000000; border-radius: 3px;">
                            <div style="font-weight: 900; text-transform: uppercase; font-size: 9.5px; letter-spacing: 0.5px;">📝 Special Instructions:</div>
                            <div style="font-style: italic; margin-top: 2px; word-break: break-word; font-weight: 600;">"{{ order.note }}"</div>
                        </div>

                        <!-- Divider -->
                        <div style="border-top: 1.5px solid #000000; margin: 8px 0 6px;"></div>

                        <!-- ══ TOTALS BREAKDOWN ═════════════════════════════════ -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px; font-weight: 600;">
                            <tbody>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Total Garments / Qty</td>
                                    <td style="text-align: right; color: #000000; font-weight: 700; padding: 2px 0;">
                                        {{ order.items?.length || 0 }} items ({{ totalPieces }} total)
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">Subtotal</td>
                                    <td style="text-align: right; color: #000000; padding: 2px 0;">{{ format(order.subtotal) }}</td>
                                </tr>
                                <tr v-if="order.discount_amount > 0">
                                    <td style="color: #000000; padding: 2px 0;">
                                        Discount {{ order.general_discount_name ? `(${order.general_discount_name})` : '' }}
                                    </td>
                                    <td style="text-align: right; color: #000000; padding: 2px 0; font-weight: 800;">- {{ format(order.discount_amount) }}</td>
                                </tr>
                                <tr v-if="order.tax_amount > 0">
                                    <td style="color: #000000; padding: 2px 0;">Tax (VAT)</td>
                                    <td style="text-align: right; color: #000000; padding: 2px 0;">+ {{ format(order.tax_amount) }}</td>
                                </tr>
                                <!-- TOTAL ROW -->
                                <tr style="border-top: 2px solid #000000; border-bottom: 2px solid #000000;">
                                    <td style="font-size: 14px; font-weight: 900; color: #000000; padding: 6px 0;">TOTAL ORDER</td>
                                    <td style="text-align: right; font-size: 14px; font-weight: 900; color: #000000; padding: 6px 0;">
                                        {{ format(order.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Divider -->
                        <div style="margin: 6px 0;"></div>

                        <!-- ══ PAYMENT & BALANCE SUMMARY ════════════════════════ -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px; font-weight: 600;">
                            <tbody>
                                <tr>
                                    <td style="color: #000000; padding: 2px 0;">
                                        Deposit / Total Paid <span v-if="paymentMethodsList" style="font-size: 10.5px; font-weight: normal;">({{ paymentMethodsList }})</span>
                                    </td>
                                    <td style="text-align: right; font-weight: 800; color: #000000; padding: 2px 0;">
                                        {{ format(order.amount_paid) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="order.balance > 0"
                                    style="border: 1.5px solid #000000; background-color: #fef3c7;"
                                >
                                    <td style="color: #000000; font-weight: 900; padding: 4px; white-space: nowrap;">
                                        ⚠ BALANCE DUE:
                                    </td>
                                    <td style="text-align: right; color: #000000; font-weight: 900; padding: 4px; font-size: 12px; white-space: nowrap;">
                                        {{ format(order.balance) }}
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    style="border: 1px solid #10b981; background-color: #ecfdf5;"
                                >
                                    <td style="color: #047857; font-weight: 900; padding: 4px; white-space: nowrap;">
                                        ✓ BALANCE:
                                    </td>
                                    <td style="text-align: right; color: #047857; font-weight: 900; padding: 4px; white-space: nowrap;">
                                        {{ format(0) }} (Fully Paid)
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- ══ BARCODE ══════════════════════════════════════════ -->
                        <div style="display: flex; justify-content: center; align-items: center; margin: 12px 0 4px;">
                            <svg ref="barcodeSvg" style="display: block; margin: 0 auto; max-width: 100%; height: auto;"></svg>
                        </div>

                        <!-- ══ FOOTER & DISCLAIMERS ═════════════════════════════ -->
                        <div style="border-top: 1.5px solid #000000; margin: 10px 0 4px; text-align: center;">
                            <p style="font-size: 11.5px; font-weight: 800; color: #000000; margin: 6px 0 2px; letter-spacing: 0.5px;">
                                ★ Please present this ticket upon garment pickup ★
                            </p>
                            <p style="font-size: 10px; font-weight: 600; color: #000000; margin: 2px 0 4px;">
                                {{ settings.receipt_footer || 'Thank you for your patronage! Please inspect all garments upon collection.' }}
                            </p>
                            <p style="font-size: 9.5px; font-weight: 500; color: #374151; margin: 2px 0;">
                                Note: Garments unclaimed after {{ settings.unclaimed_after_days || 14 }} days may attract storage demurrage.
                            </p>
                            <p v-if="branchPhone || branchEmail" style="font-size: 9.5px; font-weight: 600; color: #000000; margin-top: 3px;">
                                For enquiries: {{ branchPhone }} <span v-if="branchPhone && branchEmail">·</span> {{ branchEmail }}
                            </p>
                            <p style="font-size: 9px; font-weight: 700; color: #000000; margin-top: 6px; letter-spacing: 0.3px;">
                                Powered by {{ poweredByName }}
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Bottom Action Toolbar (no-print) ─────────────────────── -->
            <div class="no-print bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
                <!-- Left: Paper Format Toggle & Copies Stepper -->
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Paper Format Toggle (80mm vs A4) -->
                    <div class="flex items-center bg-slate-200/80 dark:bg-slate-700 p-0.5 rounded-lg text-xs font-semibold">
                        <button
                            type="button"
                            @click="selectedPaperSize = '80mm'"
                            :class="[
                                'px-2.5 py-1 rounded-md transition cursor-pointer flex items-center gap-1.5',
                                selectedPaperSize === '80mm'
                                    ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold'
                                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                            ]"
                        >
                            <span>80mm Thermal</span>
                        </button>
                        <button
                            type="button"
                            @click="selectedPaperSize = 'A4'"
                            :class="[
                                'px-2.5 py-1 rounded-md transition cursor-pointer flex items-center gap-1.5',
                                selectedPaperSize === 'A4'
                                    ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold'
                                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                            ]"
                        >
                            <span>A4 Page</span>
                        </button>
                    </div>

                    <!-- Copies Stepper -->
                    <div class="flex items-center border border-slate-300 dark:border-slate-600 rounded-lg overflow-hidden bg-white dark:bg-slate-700 text-xs">
                        <button
                            type="button"
                            @click="printCopies = Math.max(1, printCopies - 1)"
                            class="px-2 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 font-bold cursor-pointer select-none"
                            title="Decrease copies"
                        >
                            -
                        </button>
                        <span class="px-2 py-1 font-mono font-bold text-xs text-slate-800 dark:text-slate-200 min-w-[55px] text-center">
                            {{ printCopies }} {{ printCopies === 1 ? 'Copy' : 'Copies' }}
                        </span>
                        <button
                            type="button"
                            @click="printCopies = Math.min(10, printCopies + 1)"
                            class="px-2 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 font-bold cursor-pointer select-none"
                            title="Increase copies"
                        >
                            +
                        </button>
                    </div>
                </div>

                <!-- Right: Action Buttons (Download, Print, Close) -->
                <div class="flex items-center gap-2 flex-wrap w-full sm:w-auto justify-end">
                    <!-- Download PDF Button -->
                    <button
                        @click="downloadPdf"
                        :disabled="isDownloading"
                        type="button"
                        class="px-3.5 py-2 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-bold rounded-xl transition cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-50 flex-1 sm:flex-initial"
                        title="Download ticket as PDF"
                    >
                        <svg v-if="!isDownloading" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ isDownloading ? 'Downloading...' : 'Download PDF' }}</span>
                    </button>

                    <!-- Print Button -->
                    <button
                        @click="printSelected"
                        type="button"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition shadow-md shadow-emerald-600/20 active:scale-95 flex items-center justify-center gap-2 cursor-pointer flex-1 sm:flex-initial"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print ({{ selectedPaperSize }}{{ printCopies > 1 ? ' · ' + printCopies + 'x' : '' }})</span>
                    </button>

                    <!-- Close / Done Button -->
                    <button
                        @click="emit('close')"
                        type="button"
                        class="px-3.5 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition cursor-pointer"
                    >
                        Done (Esc)
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.laundry-ticket-print-area {
    box-sizing: border-box;
}
.laundry-ticket-print-area * {
    box-sizing: border-box;
}
.pdf-mode {
    background-color: #ffffff !important;
    color: #000000 !important;
}
</style>

<style>
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
    }
    .no-print {
        display: none !important;
    }
}
</style>
