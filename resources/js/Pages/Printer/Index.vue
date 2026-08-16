<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { usePrinterSetting } from '@/Composables/usePrinterSetting'
import { usePrint } from '@/Composables/usePrint'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    settings:      { type: Object, required: true },
    isBranchAdmin: { type: Boolean, default: false },
})

const page = usePage()
const { format } = useCurrency()
const { localOverride, saveLocalOverride, clearLocalOverride, resolvePrinter } = usePrinterSetting()
const { printReceipt } = usePrint()

// ── Workstation Form State ─────────────────────────────────────────────────
const form = ref({
    receipt_paper_size:   '80mm',
    receipt_printer_name: 'Default Thermal Printer',
    printer_connection:   'kiosk_direct',
    printer_ip_address:   '',
    receipt_copies:       1,
})

const saveSuccess = ref(false)
const isTestingPrint = ref(false)

// Populate initial state from local override or branch defaults
onMounted(() => {
    loadCurrentConfiguration()
})

function loadCurrentConfiguration() {
    const resolved = resolvePrinter(props.settings)
    form.value = {
        receipt_paper_size:   resolved.paper_size || '80mm',
        receipt_printer_name: resolved.printer_name || 'Default Thermal Printer',
        printer_connection:   resolved.printer_connection || 'kiosk_direct',
        printer_ip_address:   resolved.printer_ip_address || '',
        receipt_copies:       resolved.receipt_copies || 1,
    }
}

// ── Save Local Device Override (localStorage) ──────────────────────────────
function handleSaveLocal() {
    saveLocalOverride({
        is_active:            true,
        paper_size:           form.value.receipt_paper_size,
        receipt_paper_size:   form.value.receipt_paper_size,
        printer_name:         form.value.receipt_printer_name,
        receipt_printer_name: form.value.receipt_printer_name,
        printer_connection:   form.value.printer_connection,
        printer_ip_address:   form.value.printer_ip_address,
        receipt_copies:       form.value.receipt_copies,
        updated_at:           new Date().toISOString(),
    })

    saveSuccess.value = true
    setTimeout(() => {
        saveSuccess.value = false
    }, 3000)
}

function handleResetToBranchDefault() {
    if (confirm('Reset this terminal to use the branch default printer settings?')) {
        clearLocalOverride()
        loadCurrentConfiguration()
    }
}

// ── Save Branch-Wide Default (Admin Only) ──────────────────────────────────
const branchForm = useForm({
    receipt_paper_size:   '80mm',
    receipt_printer_name: '',
    printer_connection:   'kiosk_direct',
    printer_ip_address:   '',
    receipt_copies:       1,
})

function handleSaveBranchDefault() {
    branchForm.receipt_paper_size   = form.value.receipt_paper_size
    branchForm.receipt_printer_name = form.value.receipt_printer_name
    branchForm.printer_connection   = form.value.printer_connection
    branchForm.printer_ip_address   = form.value.printer_ip_address
    branchForm.receipt_copies       = form.value.receipt_copies

    branchForm.post(route('pos.printer-setup.save-branch-default'), {
        preserveScroll: true,
        onSuccess: () => {
            saveSuccess.value = true
            setTimeout(() => {
                saveSuccess.value = false
            }, 3000)
        }
    })
}

// ── Status Computed ────────────────────────────────────────────────────────
const hasLocalOverride = computed(() => {
    return Boolean(localOverride.value && localOverride.value.is_active)
})

const activeProfile = computed(() => {
    return resolvePrinter(props.settings)
})

// ── Test Print Trigger ─────────────────────────────────────────────────────
function executeTestPrint() {
    isTestingPrint.value = true

    const mockSale = {
        sale_num: 'TEST-' + Math.floor(1000 + Math.random() * 9000),
        created_at: new Date().toISOString(),
        customer: { name: 'Workstation Self-Test' },
        user: { name: page.props.auth?.user?.name || 'Staff User' },
        items: [
            {
                item: { item_name: 'Workstation Printer Test Item 1' },
                quantity: 2,
                unit_price: 1500,
                discount: 0,
                total_amount: 3000,
            },
            {
                item: { item_name: 'Workstation Printer Test Item 2' },
                quantity: 1,
                unit_price: 2500,
                discount: 0,
                total_amount: 2500,
            },
        ],
        subtotal: 5500,
        discount_amount: 0,
        tax_amount: 0,
        total_amount: 5500,
        amount_paid: 6000,
        change_returned: 500,
        payment_method: 'Cash',
    }

    const testContainer = document.getElementById('test-print-receipt-container')
    if (testContainer) {
        printReceipt(testContainer, {
            ...props.settings,
            receipt_paper_size:   form.value.receipt_paper_size,
            receipt_printer_name: form.value.receipt_printer_name,
            printer_connection:   form.value.printer_connection,
            printer_ip_address:   form.value.printer_ip_address,
        })
    }

    setTimeout(() => {
        isTestingPrint.value = false
    }, 600)
}
</script>

<template>
    <Head title="Workstation Printer Setup" />

    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">

        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 max-w-6xl mx-auto w-full">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xl">
                        🖨️
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white">Terminal & Printer Setup</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Configure your local device printer (80mm thermal or A4), connection mode, and test instant zero-dialogue printing.
                        </p>
                    </div>
                </div>

                <!-- Active Profile Badge -->
                <div class="flex items-center gap-2">
                    <span v-if="hasLocalOverride" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300 rounded-full text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        This Workstation Override Active
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-100 dark:bg-blue-950/60 border border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-300 rounded-full text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        Using Branch Default Profile
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column: Configuration Controls (7 cols) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- Main Setup Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-xs space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Workstation Printer Profile</h2>
                                <p class="text-xs text-slate-500">Settings saved here persist specifically on this device / browser.</p>
                            </div>
                            <button
                                v-if="hasLocalOverride"
                                @click="handleResetToBranchDefault"
                                type="button"
                                class="text-xs text-red-500 hover:text-red-600 font-semibold hover:underline cursor-pointer">
                                Reset to Branch Default
                            </button>
                        </div>

                        <!-- Paper Type / Format Selection -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                1. Receipt Paper Format
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    :class="['p-4 rounded-xl border flex flex-col justify-between cursor-pointer transition-all',
                                        form.receipt_paper_size === '80mm'
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 hover:border-slate-300 text-slate-700 dark:text-slate-300']">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm">🧾 80mm Thermal</span>
                                        <input type="radio" value="80mm" v-model="form.receipt_paper_size" class="accent-emerald-600 w-4 h-4" />
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">
                                        Standard POS roll receipt (Xprinter, Epson, POS-80C, Sunmi, Star).
                                    </p>
                                </label>

                                <label
                                    :class="['p-4 rounded-xl border flex flex-col justify-between cursor-pointer transition-all',
                                        form.receipt_paper_size === 'a4'
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 hover:border-slate-300 text-slate-700 dark:text-slate-300']">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm">📄 A4 Standard</span>
                                        <input type="radio" value="a4" v-model="form.receipt_paper_size" class="accent-emerald-600 w-4 h-4" />
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">
                                        Full-page invoice receipt on office LaserJet, InkJet, or PDF printer.
                                    </p>
                                </label>
                            </div>
                        </div>

                        <!-- Printer Connection Mode -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                2. Connection Method
                            </label>

                            <div class="space-y-2">
                                <label
                                    :class="['p-3 rounded-xl border flex items-start gap-3 cursor-pointer transition-all',
                                        form.printer_connection === 'kiosk_direct'
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300']">
                                    <input type="radio" value="kiosk_direct" v-model="form.printer_connection" class="accent-emerald-600 mt-1" />
                                    <div>
                                        <div class="font-bold text-xs flex items-center gap-2">
                                            <span>🖨️ Browser Kiosk Direct (Recommended for USB & Bluetooth)</span>
                                            <span class="text-[10px] px-1.5 py-0.5 bg-emerald-200 dark:bg-emerald-800 text-emerald-900 dark:text-emerald-100 rounded font-semibold">Zero Setup</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            Prints immediately in the background to your OS default printer. When browser is run in Kiosk Mode, receipts print instantly without any confirmation dialog.
                                        </p>
                                    </div>
                                </label>

                                <label
                                    :class="['p-3 rounded-xl border flex items-start gap-3 cursor-pointer transition-all',
                                        form.printer_connection === 'network_ip'
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300']">
                                    <input type="radio" value="network_ip" v-model="form.printer_connection" class="accent-emerald-600 mt-1" />
                                    <div>
                                        <div class="font-bold text-xs">🌐 Network LAN / Ethernet IP Printer</div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            Sends raw print jobs across your local Wi-Fi or Ethernet network to an IP printer (e.g. kitchen or shared cash counter).
                                        </p>
                                    </div>
                                </label>

                                <label
                                    :class="['p-3 rounded-xl border flex items-start gap-3 cursor-pointer transition-all',
                                        form.printer_connection === 'local_agent'
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300']">
                                    <input type="radio" value="local_agent" v-model="form.printer_connection" class="accent-emerald-600 mt-1" />
                                    <div>
                                        <div class="font-bold text-xs">🔌 Local Hardware Bridge / Agent</div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            Integrates with local background print service listening on localhost:9100 for hardware ESC/POS cash drawer kicking and paper cutting.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Number of Copies Selection -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    3. Default Receipt Copies (After Sales)
                                </label>
                                <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ form.receipt_copies }} {{ form.receipt_copies === 1 ? 'Copy' : 'Copies' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <button
                                    type="button"
                                    @click="form.receipt_copies = 1"
                                    :class="['p-3 rounded-xl border text-left transition-all cursor-pointer',
                                        form.receipt_copies === 1
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300']">
                                    <div class="font-bold text-xs">1 Copy</div>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Customer receipt</p>
                                </button>

                                <button
                                    type="button"
                                    @click="form.receipt_copies = 2"
                                    :class="['p-3 rounded-xl border text-left transition-all cursor-pointer',
                                        form.receipt_copies === 2
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300']">
                                    <div class="font-bold text-xs">2 Copies</div>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Customer + Store</p>
                                </button>

                                <button
                                    type="button"
                                    @click="form.receipt_copies = 3"
                                    :class="['p-3 rounded-xl border text-left transition-all cursor-pointer',
                                        form.receipt_copies === 3
                                            ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-900 dark:text-emerald-100'
                                            : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300']">
                                    <div class="font-bold text-xs">3 Copies</div>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Customer + Store + Extra</p>
                                </button>
                            </div>
                        </div>

                        <!-- Printer Details -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Printer Name / Hardware Label
                                </label>
                                <input
                                    v-model="form.receipt_printer_name"
                                    type="text"
                                    placeholder="e.g. POS-80C Terminal 1"
                                    class="w-full bg-slate-50 dark:bg-slate-700/70 text-slate-900 dark:text-white px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs focus:border-emerald-500 outline-none"
                                />
                            </div>

                            <div v-if="form.printer_connection === 'network_ip'">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Printer IP Address & Port
                                </label>
                                <input
                                    v-model="form.printer_ip_address"
                                    type="text"
                                    placeholder="192.168.1.100:9100"
                                    class="w-full bg-slate-50 dark:bg-slate-700/70 text-slate-900 dark:text-white px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-mono focus:border-emerald-500 outline-none"
                                />
                            </div>
                        </div>

                        <!-- Success Alert -->
                        <div v-if="saveSuccess" class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 rounded-xl text-xs font-semibold flex items-center gap-2">
                            <span>✅</span>
                            <span>Printer configuration saved successfully on this device!</span>
                        </div>

                        <!-- Actions -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row gap-3">
                            <button
                                @click="handleSaveLocal"
                                type="button"
                                class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                <span>💾 Save For This Workstation</span>
                            </button>

                            <button
                                v-if="isBranchAdmin"
                                @click="handleSaveBranchDefault"
                                :disabled="branchForm.processing"
                                type="button"
                                class="py-3 px-4 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-xs rounded-xl transition cursor-pointer flex items-center justify-center gap-1.5">
                                <span>👑 Save as Branch Default</span>
                            </button>
                        </div>
                    </div>

                    <!-- Kiosk Mode Zero-Dialogue Guide -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white">
                            <span class="text-xl">⚡</span>
                            <div>
                                <h3 class="text-sm font-bold">How to Enable 100% Zero-Dialogue Instant Printing</h3>
                                <p class="text-xs text-slate-500">Print receipts straight out of the printer the second a sale finishes.</p>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                            <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="font-bold text-slate-800 dark:text-slate-200 mb-1">Step 1: Set Default OS Printer</div>
                                <p class="text-[11px] text-slate-500">In Windows Settings → Printers & Scanners, set your 80mm thermal receipt printer (or A4 printer) as the <strong>Default Printer</strong>.</p>
                            </div>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="font-bold text-slate-800 dark:text-slate-200 mb-1">Step 2: Add Kiosk Flag to Browser Shortcut</div>
                                <p class="text-[11px] text-slate-500 mb-1.5">Right-click your Chrome or Edge desktop shortcut → Properties → in the <strong>Target</strong> box, append:</p>
                                <div class="p-2 bg-slate-900 text-emerald-400 font-mono rounded text-[11px] select-all">
                                    --kiosk-printing
                                </div>
                            </div>

                            <div class="p-3 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-xl border border-emerald-200 dark:border-emerald-800/60">
                                <div class="font-bold text-emerald-800 dark:text-emerald-300 mb-1">Done!</div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Whenever cashiers click <strong>"Print Receipt"</strong> or finish a sale on this computer, the receipt will spit out immediately without showing the browser print dialog.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Interactive Test Print Card (5 cols) -->
                <div class="lg:col-span-5 space-y-6">

                    <!-- Test Receipt Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-xs sticky top-4 space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Workstation Self-Test</h3>
                            <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                {{ form.receipt_paper_size === 'a4' ? 'A4 Format' : '80mm Thermal' }}
                            </span>
                        </div>

                        <!-- Simulated Receipt Canvas -->
                        <div class="p-4 bg-slate-100 dark:bg-slate-900/80 rounded-xl flex justify-center border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div
                                :class="[
                                    'bg-white text-black p-4 rounded shadow font-mono text-[11px] leading-snug',
                                    form.receipt_paper_size === 'a4' ? 'w-full text-xs' : 'w-[280px]'
                                ]">
                                <div class="text-center font-bold text-sm uppercase">
                                    {{ settings.business_name || page.props.current_branch?.name || 'Skynet Digital POS' }}
                                </div>
                                <div class="text-center text-[10px] text-slate-600">
                                    {{ settings.business_address || 'Workstation Terminal 1' }}
                                </div>
                                <div class="text-center text-[10px] text-slate-600">
                                    Tel: {{ settings.business_contact_number || '+234 800 000 0000' }}
                                </div>

                                <div class="border-t border-dashed border-slate-400 my-2"></div>

                                <div class="flex justify-between text-[10px]">
                                    <span>Rcpt #: TEST-8821</span>
                                    <span>{{ new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Terminal: {{ form.receipt_printer_name || 'Standard POS' }}
                                </div>

                                <div class="border-t border-dashed border-slate-400 my-2"></div>

                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span>2x Sample Item 1</span>
                                        <span>3,000.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>1x Sample Item 2</span>
                                        <span>2,500.00</span>
                                    </div>
                                </div>

                                <div class="border-t border-dashed border-slate-400 my-2"></div>

                                <div class="flex justify-between font-bold text-xs">
                                    <span>TOTAL:</span>
                                    <span>{{ format(5500) }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span>Cash Paid:</span>
                                    <span>{{ format(6000) }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span>Change:</span>
                                    <span>{{ format(500) }}</span>
                                </div>

                                <div class="border-t border-dashed border-slate-400 my-2"></div>

                                <div class="text-center text-[10px] font-bold">
                                    THANK YOU FOR YOUR PATRONAGE!
                                </div>
                                <div class="text-center text-[9px] text-slate-500 mt-1">
                                    * WORKSTATION TEST RECEIPT *
                                </div>
                            </div>
                        </div>

                        <!-- Print Test Receipt Button -->
                        <button
                            @click="executeTestPrint"
                            :disabled="isTestingPrint"
                            type="button"
                            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <svg v-if="!isTestingPrint" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ isTestingPrint ? 'Sending to Printer...' : '🖨️ Print Test Receipt Now' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden container for Test Print rendering -->
        <div class="hidden">
            <div id="test-print-receipt-container" class="receipt-print-wrapper">
                <div style="text-align: center; margin-bottom: 8px;">
                    <div style="font-size: 15px; font-weight: 800; text-transform: uppercase;">
                        {{ settings.business_name || page.props.current_branch?.name || 'Skynet Digital POS' }}
                    </div>
                    <div style="font-size: 11px; color: #444;">
                        {{ settings.business_address || 'Terminal 1' }}
                    </div>
                    <div style="font-size: 11px; color: #444;">
                        Tel: {{ settings.business_contact_number || '+234 800 000 0000' }}
                    </div>
                </div>

                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                <div style="display: flex; justify-content: space-between; font-size: 11px;">
                    <span>Rcpt: TEST-8821</span>
                    <span>{{ new Date().toLocaleString() }}</span>
                </div>
                <div style="font-size: 11px;">
                    Staff: {{ page.props.auth?.user?.name || 'Cashier' }} ({{ form.receipt_printer_name || 'Terminal' }})
                </div>

                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px dashed #000;">
                            <th style="text-align: left; padding: 2px 0;">Item</th>
                            <th style="text-align: center; padding: 2px 0;">Qty</th>
                            <th style="text-align: right; padding: 2px 0;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 2px 0;">Workstation Test Item 1</td>
                            <td style="text-align: center;">2</td>
                            <td style="text-align: right;">3,000.00</td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;">Workstation Test Item 2</td>
                            <td style="text-align: center;">1</td>
                            <td style="text-align: right;">2,500.00</td>
                        </tr>
                    </tbody>
                </table>

                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 800;">
                    <span>TOTAL:</span>
                    <span>{{ format(5500) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px;">
                    <span>Payment:</span>
                    <span>Cash</span>
                </div>

                <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>

                <div style="text-align: center; font-size: 11px; font-weight: 700;">
                    THANK YOU FOR YOUR PATRONAGE!
                </div>
                <div style="text-align: center; font-size: 10px; color: #666; margin-top: 3px;">
                    * SELF-TEST PRINT OK *
                </div>
            </div>
        </div>
    </div>
</template>
