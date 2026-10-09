<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    vatAccount: Object,
    lines: Array,
    totalOutputVat: Number,
    totalInputVatOrReturns: Number,
    netVatPayable: Number,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.reports.vat', { branch: branchSlug.value }),
        { from_date: fromDate.value, to_date: toDate.value },
        { preserveState: true, replace: true }
    )
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">VAT / Tax Summary</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Value Added Tax collected on sales less returns</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                    Net VAT Payable: {{ format(netVatPayable) }}
                </span>
                <ExportDropdown
                    :base-url="route('pos.accounting.reports.export', { branch: branchSlug, type: 'vat' })"
                    :params="{ from_date: fromDate, to_date: toDate }"
                    title="Export VAT Report"
                />
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex items-center gap-3 flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500">From:</span>
                <input
                    v-model="fromDate"
                    type="date"
                    @change="doFilter"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
                />
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500">To:</span>
                <input
                    v-model="toDate"
                    type="date"
                    @change="doFilter"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
                />
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-3 gap-3 flex-shrink-0">
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Output VAT (Collected on Sales)</span>
                <span class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ format(totalOutputVat) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Returns / Input VAT Relieved</span>
                <span class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ format(totalInputVatOrReturns) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Net VAT Due / Payable</span>
                <span class="font-mono font-bold text-sm text-indigo-600 dark:text-indigo-400">{{ format(netVatPayable) }}</span>
            </div>
        </div>

        <!-- Lines Table -->
        <div class="flex-1 overflow-y-auto px-6 py-2">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Date</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Entry #</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Description</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Reference</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">VAT Relief (Debit)</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">VAT Collected (Credit)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="l in lines" :key="l.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ l.date }}</td>
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">{{ l.entry_number }}</td>
                            <td class="px-4 py-3 text-slate-900 dark:text-white">{{ l.description }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ l.reference || '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ l.debit > 0 ? format(l.debit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ l.credit > 0 ? format(l.credit) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="!lines || lines.length === 0">
                            <td colspan="6" class="text-center py-8 text-slate-400">No VAT postings in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
