<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    beginningCash: Number,
    operatingInflows: Array,
    operatingOutflows: Array,
    totalInflows: Number,
    totalOutflows: Number,
    netCashChange: Number,
    endingCash: Number,
    filters: Object,
    isConsolidated: Boolean,
    branchesIncluded: Array,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.reports.cash-flow', { branch: branchSlug.value }),
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
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Statement of Cash Flows (Direct Method)</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Cash inflows and outflows across cash registers and bank accounts</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    v-if="isConsolidated"
                    class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300"
                >
                    Consolidated Organization View
                </span>
                <ExportDropdown
                    :base-url="route('pos.accounting.reports.export', { branch: branchSlug, type: 'cash-flow' })"
                    :params="{ from_date: fromDate, to_date: toDate }"
                    title="Export Cash Flow Statement"
                />
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex flex-wrap items-center gap-3 flex-shrink-0">
            <span class="text-xs text-slate-500">Date Range:</span>
            <input
                v-model="fromDate"
                type="date"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
            <span class="text-xs text-slate-400">to</span>
            <input
                v-model="toDate"
                type="date"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">Beginning Cash</p>
                    <p class="text-lg font-bold mt-1 text-slate-800 dark:text-slate-100">{{ format(beginningCash) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Cash Inflows</p>
                    <p class="text-lg font-bold mt-1 text-emerald-600 dark:text-emerald-400">+{{ format(totalInflows) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-rose-600 dark:text-rose-400">Cash Outflows</p>
                    <p class="text-lg font-bold mt-1 text-rose-600 dark:text-rose-400">-{{ format(totalOutflows) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">Net Cash Change</p>
                    <p class="text-lg font-bold mt-1" :class="netCashChange >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ netCashChange >= 0 ? '+' : '' }}{{ format(netCashChange) }}
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 shadow-sm col-span-2 md:col-span-1">
                    <p class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">Ending Cash Balance</p>
                    <p class="text-xl font-black mt-1 text-emerald-900 dark:text-emerald-200">{{ format(endingCash) }}</p>
                </div>
            </div>

            <!-- Inflows & Outflows Columns -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Inflows -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
                    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 bg-emerald-50/50 dark:bg-emerald-950/20 flex justify-between items-center">
                        <h2 class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Operating Cash Inflows</h2>
                        <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">+{{ format(totalInflows) }}</span>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 sticky top-0 text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left">Date</th>
                                    <th class="px-4 py-2 text-left">Activity / Memo</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr v-for="(item, idx) in operatingInflows" :key="'in_'+idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                    <td class="px-4 py-2 text-slate-400 font-mono text-[11px]">{{ item.date }}</td>
                                    <td class="px-4 py-2 text-slate-800 dark:text-slate-200">{{ item.label }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-emerald-600 dark:text-emerald-400">{{ format(item.amount) }}</td>
                                </tr>
                                <tr v-if="!operatingInflows?.length">
                                    <td colspan="3" class="px-4 py-6 text-center text-slate-400">No cash inflows in this period.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Outflows -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
                    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 bg-rose-50/50 dark:bg-rose-950/20 flex justify-between items-center">
                        <h2 class="text-sm font-bold text-rose-800 dark:text-rose-300">Operating Cash Outflows</h2>
                        <span class="text-xs font-bold text-rose-700 dark:text-rose-400">-{{ format(totalOutflows) }}</span>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 sticky top-0 text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left">Date</th>
                                    <th class="px-4 py-2 text-left">Activity / Memo</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr v-for="(item, idx) in operatingOutflows" :key="'out_'+idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                    <td class="px-4 py-2 text-slate-400 font-mono text-[11px]">{{ item.date }}</td>
                                    <td class="px-4 py-2 text-slate-800 dark:text-slate-200">{{ item.label }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-rose-600 dark:text-rose-400">{{ format(item.amount) }}</td>
                                </tr>
                                <tr v-if="!operatingOutflows?.length">
                                    <td colspan="3" class="px-4 py-6 text-center text-slate-400">No cash outflows in this period.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
