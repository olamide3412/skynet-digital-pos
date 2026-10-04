<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useCurrency } from '@/Composables/useCurrency'
import { usePrint } from '@/Composables/usePrint'
import { usePrinterSetting } from '@/Composables/usePrinterSetting'
import dayjs from 'dayjs'

const props = defineProps({
    session:  { type: Object, required: true },
    summary:  { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close'])

const page = usePage()
const { format } = useCurrency()
const { printElement } = usePrint()
const { resolvePrinter } = usePrinterSetting()

const effectivePrinter = computed(() => resolvePrinter(props.settings))
const selectedPaperSize = ref(effectivePrinter.value.paper_size || '80mm')

const branch = computed(() => {
    return page.props.current_branch || {
        name:    props.settings?.business_name || 'Skynet POS Store',
        address: props.settings?.business_address || '',
        phone:   props.settings?.business_contact_number || '',
    }
})

const openedAtFormatted = computed(() => {
    return props.session.opened_at ? dayjs(props.session.opened_at).format('DD MMM YYYY, HH:mm') : '—'
})

const closedAtFormatted = computed(() => {
    return props.session.closed_at ? dayjs(props.session.closed_at).format('DD MMM YYYY, HH:mm') : dayjs().format('DD MMM YYYY, HH:mm')
})

const effectiveSummary = computed(() => {
    return props.summary || props.session.summary || {
        opening_amount:  props.session.opening_amount || 0,
        cash_sales:      0,
        cash_refunds:    0,
        cash_added:      0,
        cash_removed:    0,
        expected_cash:   props.session.expected_amount || props.session.opening_amount || 0,
        movements:       [],
    }
})

const countedCash = computed(() => {
    return parseFloat(props.session.closing_amount || 0)
})

const expectedCash = computed(() => {
    return parseFloat(props.session.expected_amount ?? effectiveSummary.value.expected_cash ?? 0)
})

const variance = computed(() => {
    return parseFloat(props.session.variance ?? (countedCash.value - expectedCash.value))
})

const cashierName = computed(() => {
    return props.session.cashier?.name || props.session.cashier_name || page.props.auth?.user?.name || 'Cashier'
})

const closedByName = computed(() => {
    return props.session.closed_by_user?.name || props.session.closed_by?.name || cashierName.value
})

function printSlip() {
    printElement('.till-slip-print-area', {
        paperSize: selectedPaperSize.value,
        printerConnection: effectivePrinter.value.printer_connection,
        printerIpAddress: effectivePrinter.value.printer_ip_address,
        printerName: effectivePrinter.value.printer_name,
        copies: 1,
    })
}

function handleKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault()
        printSlip()
    } else if (e.key === 'Escape') {
        e.preventDefault()
        emit('close')
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <div
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 transition-all"
        @click.self="$emit('close')"
    >
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-200">
            <!-- Header Bar -->
            <div class="px-6 py-4 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">🧾</span>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white text-base">Till Summary Slip</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Session #{{ session.id || session.offline_session_id }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="printSlip"
                        class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm flex items-center gap-1.5 transition cursor-pointer"
                    >
                        <span>🖨️ Print Slip</span>
                    </button>
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl transition cursor-pointer"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Scrollable Slip Preview Body -->
            <div class="p-6 overflow-y-auto flex-1 bg-slate-50 dark:bg-slate-950 flex justify-center">
                <!-- PRINT AREA (Thermal 80mm format) -->
                <div class="till-slip-print-area w-full max-w-[340px] bg-white text-slate-900 p-5 rounded-2xl shadow-sm border border-slate-200 font-mono text-xs">
                    <!-- Store Branding -->
                    <div class="text-center pb-3 border-b border-dashed border-slate-400 space-y-0.5">
                        <div class="font-black text-sm uppercase tracking-wide">{{ branch.name }}</div>
                        <div v-if="branch.address" class="text-[11px] text-slate-600">{{ branch.address }}</div>
                        <div v-if="branch.phone" class="text-[11px] text-slate-600">Tel: {{ branch.phone }}</div>
                        <div class="pt-2 font-bold text-xs tracking-wider uppercase border-t border-slate-300 mt-2">
                            *** TILL CLOSING REPORT ***
                        </div>
                    </div>

                    <!-- Shift Meta -->
                    <div class="py-2.5 border-b border-dashed border-slate-400 space-y-1 text-[11px]">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Session ID:</span>
                            <span class="font-bold">#{{ session.id || session.offline_session_id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Cashier:</span>
                            <span class="font-bold">{{ cashierName }}</span>
                        </div>
                        <div v-if="closedByName && closedByName !== cashierName" class="flex justify-between">
                            <span class="text-slate-500">Closed By:</span>
                            <span class="font-bold">{{ closedByName }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Opened:</span>
                            <span>{{ openedAtFormatted }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Closed:</span>
                            <span>{{ closedAtFormatted }}</span>
                        </div>
                    </div>

                    <!-- Cash Reconciliation Table -->
                    <div class="py-2.5 border-b border-dashed border-slate-400 space-y-1 text-xs">
                        <div class="font-bold pb-1 text-[11px] uppercase tracking-wider text-slate-700">Cash Reconciliation</div>
                        <div class="flex justify-between">
                            <span>Opening Float:</span>
                            <span>{{ format(effectiveSummary.opening_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-700">
                            <span>(+) Cash Sales:</span>
                            <span>+{{ format(effectiveSummary.cash_sales) }}</span>
                        </div>
                        <div v-if="effectiveSummary.cash_refunds > 0" class="flex justify-between text-rose-700">
                            <span>(-) Cash Refunds:</span>
                            <span>-{{ format(effectiveSummary.cash_refunds) }}</span>
                        </div>
                        <div v-if="effectiveSummary.cash_added > 0" class="flex justify-between text-blue-700">
                            <span>(+) Cash In (Added):</span>
                            <span>+{{ format(effectiveSummary.cash_added) }}</span>
                        </div>
                        <div v-if="effectiveSummary.cash_removed > 0" class="flex justify-between text-amber-700">
                            <span>(-) Cash Out (Drops):</span>
                            <span>-{{ format(effectiveSummary.cash_removed) }}</span>
                        </div>
                        <div class="flex justify-between font-bold border-t border-slate-300 pt-1.5 mt-1">
                            <span>(=) Expected Cash:</span>
                            <span>{{ format(expectedCash) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-sm bg-slate-100 p-1.5 rounded mt-1">
                            <span>Counted Cash:</span>
                            <span>{{ format(countedCash) }}</span>
                        </div>

                        <!-- Variance Indicator -->
                        <div
                            :class="[
                                'flex justify-between font-bold text-xs p-1.5 rounded mt-1',
                                variance === 0 ? 'bg-emerald-50 text-emerald-800' :
                                variance < 0 ? 'bg-rose-50 text-rose-800' : 'bg-blue-50 text-blue-800'
                            ]"
                        >
                            <span>Variance (Difference):</span>
                            <span>{{ variance > 0 ? '+' : '' }}{{ format(variance) }} ({{ variance === 0 ? 'BALANCED' : variance < 0 ? 'SHORTAGE' : 'OVER' }})</span>
                        </div>
                    </div>

                    <!-- Cash Movements Log if present -->
                    <div v-if="effectiveSummary.movements && effectiveSummary.movements.length > 0" class="py-2.5 border-b border-dashed border-slate-400 space-y-1">
                        <div class="font-bold text-[11px] uppercase tracking-wider text-slate-700">Mid-Shift Cash Movements</div>
                        <div
                            v-for="(m, i) in effectiveSummary.movements"
                            :key="i"
                            class="flex justify-between text-[10px] text-slate-700"
                        >
                            <span class="capitalize">{{ m.type === 'add' ? '➕ In' : '➖ Out' }} ({{ m.reason || 'No reason' }}):</span>
                            <span class="font-bold font-mono">{{ format(m.amount) }}</span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="session.opening_note || session.closing_note" class="py-2.5 border-b border-dashed border-slate-400 space-y-1 text-[11px]">
                        <div v-if="session.opening_note">
                            <span class="text-slate-500 font-bold">Open Note:</span> {{ session.opening_note }}
                        </div>
                        <div v-if="session.closing_note">
                            <span class="text-slate-500 font-bold">Close Note:</span> {{ session.closing_note }}
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="pt-6 space-y-6 text-[10px] text-slate-600">
                        <div class="flex justify-between items-end">
                            <div>
                                <div class="w-32 border-b border-slate-400"></div>
                                <div class="mt-1">Cashier Signature</div>
                            </div>
                            <div>
                                <div class="w-32 border-b border-slate-400"></div>
                                <div class="mt-1">Manager Signature</div>
                            </div>
                        </div>

                        <div class="text-center text-[10px] text-slate-400 pt-2 border-t border-slate-200">
                            Generated by Skynet Digital POS • {{ dayjs().format('YYYY-MM-DD HH:mm:ss') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body > *:not(.till-slip-print-area) { display: none !important; }
    .till-slip-print-area {
        display: block !important;
        width: 80mm;
        max-width: 80mm;
        margin: 0 auto;
        font-size: 11px;
        font-family: monospace;
        color: #000 !important;
        background: #fff !important;
    }
}
</style>
