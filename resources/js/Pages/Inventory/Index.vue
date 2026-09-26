<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import CreateBatchModal from '@/Components/CreateBatchModal.vue'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    transactions: Object,
    filters: Object,
})

const type = ref(props.filters?.type ?? '')
const search = ref(props.filters?.search ?? '')
const from = ref(props.filters?.from ?? '')
const to = ref(props.filters?.to ?? '')
const exportMenuOpen = ref(false)
const exportDropdownRef = ref(null)

const page = usePage()
const showBatchModal = ref(false)
const batchPromptItem = ref(null)

function doFilter() {
    router.get(
        route('pos.inventory.index'),
        {
            type: type.value || undefined,
            search: search.value ? search.value.trim() : undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}

function resetFilters() {
    type.value = ''
    search.value = ''
    from.value = ''
    to.value = ''
    router.get(route('pos.inventory.index'), {}, { preserveState: true, replace: true })
}

function getExportUrl(format = 'xlsx') {
    const params = new URLSearchParams()
    params.set('format', format)
    if (type.value) params.set('type', type.value)
    if (search.value) params.set('search', search.value.trim())
    if (from.value) params.set('from', from.value)
    if (to.value) params.set('to', to.value)
    return `${route('pos.inventory.export')}?${params.toString()}`
}

function downloadExport(format) {
    exportMenuOpen.value = false
    window.location.href = getExportUrl(format)
}

function handleClickOutside(e) {
    if (exportDropdownRef.value && !exportDropdownRef.value.contains(e.target)) {
        exportMenuOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (page.props.flash?.batch_prompt) {
        batchPromptItem.value = page.props.flash.batch_prompt
        showBatchModal.value = true
    }
})

watch(() => page.props.flash?.batch_prompt, (prompt) => {
    if (prompt) {
        batchPromptItem.value = prompt
        showBatchModal.value = true
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

function isDeduction(log) {
    if (!log) return false
    if (log.previous_qty !== null && log.previous_qty !== undefined &&
        log.new_qty !== null && log.new_qty !== undefined &&
        Number(log.new_qty) < Number(log.previous_qty)) {
        return true
    }
    if (Number(log.qty) < 0) {
        return true
    }
    const t = String(log.transaction_type || log.type || log.notes || '').toLowerCase()
    if (t.includes('deduct') || t.includes('subtr') || t.includes('sale') || t.includes('damage') || t.includes('loss')) {
        return true
    }
    return false
}

function formatQty(log) {
    const absQty = Math.abs(Number(log.qty || 0))
    return isDeduction(log) ? `-${absQty}` : `+${absQty}`
}

function formatTypeBadge(rawType) {
    const t = String(rawType || '').toLowerCase()
    if (t === 'sale') return { label: 'Sale Deduction', class: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/60' }
    if (t === 'purchase') return { label: 'Purchase In', class: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/60' }
    if (t === 'transfer') return { label: 'Stock Transfer', class: 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800/60' }
    if (t === 'adjustment') return { label: 'Adjustment', class: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800/60' }
    if (t === 'return') return { label: 'Return Restock', class: 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800/60' }
    return { label: rawType || 'Other', class: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600' }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Inventory Transaction Logs</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Complete audit trail of sales, restocks, transfers, and adjustments</p>
            </div>
            
            <div class="flex items-center gap-2.5">
                <!-- Export Dropdown -->
                <div class="relative" ref="exportDropdownRef">
                    <button
                        type="button"
                        @click="exportMenuOpen = !exportMenuOpen"
                        class="px-3 py-2 bg-slate-100 dark:bg-slate-700/90 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-lg transition border border-slate-300 dark:border-slate-600 flex items-center gap-1.5 shadow-xs cursor-pointer"
                        title="Export filtered logs"
                    >
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export</span>
                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="exportMenuOpen"
                        class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                    >
                        <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            Export Filtered Logs
                        </div>
                        <button
                            type="button"
                            @click="downloadExport('xlsx')"
                            class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                                XLS
                            </span>
                            Excel (.xlsx)
                        </button>
                        <button
                            type="button"
                            @click="downloadExport('csv')"
                            class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-950/40 text-slate-700 dark:text-slate-200 hover:text-blue-700 dark:hover:text-blue-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-[10px]">
                                CSV
                            </span>
                            CSV (.csv)
                        </button>
                    </div>
                </div>

                <Link :href="route('pos.inventory.adjust')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-emerald-900/20">
                    + Make Adjustment
                </Link>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap items-center gap-3 flex-shrink-0 bg-slate-100/60 dark:bg-slate-800/40">
            <!-- Search -->
            <div class="flex-1 min-w-[200px] max-w-sm">
                <input
                    v-model="search"
                    @keydown.enter="doFilter"
                    type="text"
                    placeholder="Search item, barcode, notes, or ref…"
                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition"
                />
            </div>

            <!-- Type Filter Dropdown -->
            <div class="flex items-center gap-1.5">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Type:</span>
                <select
                    v-model="type"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition"
                >
                    <option value="">All Types</option>
                    <option value="sale">Sale Deduction</option>
                    <option value="purchase">Purchase In / Restock</option>
                    <option value="transfer">Stock Transfer</option>
                    <option value="adjustment">Stock Adjustment</option>
                    <option value="return">Return Restock</option>
                    <option value="Initial Stock">Initial Stock</option>
                </select>
            </div>

            <!-- Date Range Inputs -->
            <div class="flex items-center gap-1.5">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Date:</span>
                <input
                    v-model="from"
                    type="date"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600"
                />
                <span class="text-xs text-slate-400">to</span>
                <input
                    v-model="to"
                    type="date"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600"
                />
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2">
                <button
                    @click="doFilter"
                    type="button"
                    class="px-3 py-1.5 bg-slate-800 dark:bg-slate-200 hover:bg-slate-700 dark:hover:bg-white text-white dark:text-slate-900 text-xs font-bold rounded-lg transition cursor-pointer shadow-xs"
                >
                    Apply Filter
                </button>
                <button
                    v-if="type || search || from || to"
                    @click="resetFilters"
                    type="button"
                    class="px-2.5 py-1.5 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg transition cursor-pointer"
                >
                    Reset
                </button>
            </div>
        </div>

        <!-- Table Data -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 text-xs">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Date & Time</th>
                            <th class="text-left px-4 py-3 font-semibold">Item Details</th>
                            <th class="text-left px-4 py-3 font-semibold">Location</th>
                            <th class="text-left px-4 py-3 font-semibold">Transaction Type</th>
                            <th class="text-right px-4 py-3 font-semibold">Qty Change</th>
                            <th class="text-right px-4 py-3 font-semibold">Balance</th>
                            <th class="text-left px-4 py-3 font-semibold">Reason / Reference</th>
                            <th class="text-left px-4 py-3 font-semibold">Logged By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="log in transactions.data" :key="log.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition text-xs">
                            <!-- Date & Time -->
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono whitespace-nowrap">
                                {{ dayjs(log.created_at).format('DD MMM YYYY HH:mm') }}
                            </td>

                            <!-- Item -->
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900 dark:text-white">
                                    {{ log.item?.item_name ?? '#'+log.item_id }}
                                </div>
                                <div v-if="log.item?.barcode_number" class="text-[11px] text-slate-400 font-mono">
                                    {{ log.item.barcode_number }}
                                </div>
                            </td>

                            <!-- Location -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    {{ log.location === 'back_store' ? 'Back Store' : 'Front Store' }}
                                </span>
                            </td>

                            <!-- Transaction Type Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-xs font-semibold border inline-block',
                                        formatTypeBadge(log.transaction_type || log.type).class
                                    ]"
                                >
                                    {{ formatTypeBadge(log.transaction_type || log.type).label }}
                                </span>
                            </td>

                            <!-- Qty Change -->
                            <td class="px-4 py-3 text-right font-mono font-bold whitespace-nowrap" :class="isDeduction(log) ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                                {{ formatQty(log) }}
                            </td>

                            <!-- Balance (Previous -> New) -->
                            <td class="px-4 py-3 text-right font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                <span v-if="log.previous_qty !== null && log.new_qty !== null">
                                    {{ log.previous_qty }} &rarr; <span class="font-bold text-slate-800 dark:text-slate-200">{{ log.new_qty }}</span>
                                </span>
                                <span v-else>—</span>
                            </td>

                            <!-- Reason / Reference -->
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                <div class="truncate max-w-xs">{{ log.notes || '—' }}</div>
                                <div v-if="log.reference_id" class="text-[11px] text-blue-600 dark:text-blue-400 font-mono font-medium">
                                    Ref: {{ log.reference_id }}
                                </div>
                            </td>

                            <!-- Logged By -->
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-medium whitespace-nowrap">
                                {{ log.user?.full_name || log.user?.name || 'System' }}
                            </td>
                        </tr>

                        <tr v-if="!transactions.data.length">
                            <td colspan="8" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                No inventory transactions found for the selected filter.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination Footer -->
                <div v-if="transactions.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>Showing {{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }} logs</span>
                    <div class="flex items-center gap-1">
                        <Link
                            v-if="transactions.prev_page_url"
                            :href="transactions.prev_page_url"
                            preserve-scroll
                            class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition"
                        >
                            Prev
                        </Link>
                        <span class="px-2 py-1 text-slate-500">
                            Page {{ transactions.current_page }} of {{ transactions.last_page }}
                        </span>
                        <Link
                            v-if="transactions.next_page_url"
                            :href="transactions.next_page_url"
                            preserve-scroll
                            class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reusable Create Batch Modal (§5.2) -->
        <CreateBatchModal
            v-model="showBatchModal"
            :item="batchPromptItem"
        />
    </div>
</template>
