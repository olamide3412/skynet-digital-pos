<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    revenues: Array,
    cogs: Array,
    expenses: Array,
    totalRevenue: Number,
    totalCogs: Number,
    grossProfit: Number,
    totalExpenses: Number,
    netProfit: Number,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.reports.profit-loss', { branch: branchSlug.value }),
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
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Income Statement (Profit & Loss)</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Accrual-basis revenue, direct costs, and operating expenses</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="px-3 py-1 rounded-full text-xs font-bold"
                    :class="netProfit >= 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                >
                    Net: {{ format(netProfit) }}
                </span>
                <ExportDropdown
                    :base-url="route('pos.accounting.reports.export', { branch: branchSlug, type: 'profit-loss' })"
                    :params="{ from_date: fromDate, to_date: toDate }"
                    title="Export Profit & Loss"
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

        <!-- Financial Statement Body -->
        <div class="flex-1 overflow-y-auto px-6 py-4 max-w-4xl mx-auto w-full space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-xs space-y-6 text-sm">
                <!-- Revenue Section -->
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Revenue</h3>
                    <div class="space-y-1">
                        <div v-for="r in revenues" :key="r.code" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-700 dark:text-slate-300">{{ r.code }} — {{ r.name }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(r.amount) }}</span>
                        </div>
                        <div v-if="!revenues || revenues.length === 0" class="text-slate-400 text-xs italic py-1">No revenue in this period.</div>
                    </div>
                    <div class="flex justify-between font-bold pt-2 border-t border-slate-300 dark:border-slate-600">
                        <span>Total Revenue</span>
                        <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ format(totalRevenue) }}</span>
                    </div>
                </div>

                <!-- Cost of Goods Sold Section -->
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Cost of Goods Sold (COGS)</h3>
                    <div class="space-y-1">
                        <div v-for="c in cogs" :key="c.code" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-700 dark:text-slate-300">{{ c.code }} — {{ c.name }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(c.amount) }}</span>
                        </div>
                        <div v-if="!cogs || cogs.length === 0" class="text-slate-400 text-xs italic py-1">No cost of goods sold recorded.</div>
                    </div>
                    <div class="flex justify-between font-bold pt-2 border-t border-slate-300 dark:border-slate-600">
                        <span>Total Cost of Goods Sold</span>
                        <span class="font-mono text-slate-900 dark:text-white">{{ format(totalCogs) }}</span>
                    </div>
                </div>

                <!-- Gross Profit Summary -->
                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 flex justify-between font-bold border border-slate-200 dark:border-slate-700">
                    <span class="text-slate-900 dark:text-white">Gross Profit</span>
                    <span class="font-mono text-base" :class="grossProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ format(grossProfit) }}
                    </span>
                </div>

                <!-- Operating Expenses Section -->
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Operating Expenses</h3>
                    <div class="space-y-1">
                        <div v-for="e in expenses" :key="e.code" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-700 dark:text-slate-300">{{ e.code }} — {{ e.name }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(e.amount) }}</span>
                        </div>
                        <div v-if="!expenses || expenses.length === 0" class="text-slate-400 text-xs italic py-1">No operating expenses in this period.</div>
                    </div>
                    <div class="flex justify-between font-bold pt-2 border-t border-slate-300 dark:border-slate-600">
                        <span>Total Operating Expenses</span>
                        <span class="font-mono text-slate-900 dark:text-white">{{ format(totalExpenses) }}</span>
                    </div>
                </div>

                <!-- Net Profit / Loss -->
                <div class="bg-slate-900 dark:bg-slate-950 text-white rounded-xl p-4 flex justify-between items-center font-bold">
                    <div>
                        <span class="text-base block">Net Profit / (Loss)</span>
                        <span class="text-xs text-slate-400 font-normal">Revenue − COGS − Operating Expenses</span>
                    </div>
                    <span class="font-mono text-xl" :class="netProfit >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                        {{ format(netProfit) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
