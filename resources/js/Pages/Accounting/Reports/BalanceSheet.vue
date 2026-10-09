<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    assets: Array,
    liabilities: Array,
    equity: Array,
    retainedEarnings: Number,
    totalAssets: Number,
    totalLiabilities: Number,
    totalEquity: Number,
    totalLiabilitiesAndEquity: Number,
    isBalanced: Boolean,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const asOfDate = ref(props.filters?.as_of_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.reports.balance-sheet', { branch: branchSlug.value }),
        { as_of_date: asOfDate.value },
        { preserveState: true, replace: true }
    )
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Balance Sheet</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Financial position: Assets = Liabilities + Equity</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5"
                    :class="isBalanced ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                >
                    <span class="w-2 h-2 rounded-full" :class="isBalanced ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    {{ isBalanced ? 'Balance Sheet Balanced' : 'Out of Balance' }}
                </span>
                <ExportDropdown
                    :base-url="route('pos.accounting.reports.export', { branch: branchSlug, type: 'balance-sheet' })"
                    :params="{ as_of_date: asOfDate }"
                    title="Export Balance Sheet"
                />
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex items-center gap-2 flex-shrink-0">
            <span class="text-xs text-slate-500">As of Date:</span>
            <input
                v-model="asOfDate"
                type="date"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
        </div>

        <!-- Statement Body -->
        <div class="flex-1 overflow-y-auto px-6 py-4 max-w-5xl mx-auto w-full grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Side: Assets -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-xs flex flex-col justify-between text-sm">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4 border-b pb-2">Assets</h3>
                    <div class="space-y-1">
                        <div v-for="a in assets" :key="a.code" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-700 dark:text-slate-300">{{ a.code }} — {{ a.name }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(a.amount) }}</span>
                        </div>
                        <div v-if="!assets || assets.length === 0" class="text-slate-400 text-xs italic py-2">No assets recorded.</div>
                    </div>
                </div>
                <div class="pt-4 border-t-2 border-slate-300 dark:border-slate-600 flex justify-between font-bold text-base mt-4">
                    <span>Total Assets</span>
                    <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ format(totalAssets) }}</span>
                </div>
            </div>

            <!-- Right Side: Liabilities & Equity -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-xs flex flex-col justify-between text-sm space-y-4">
                <div>
                    <!-- Liabilities -->
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 border-b pb-2">Liabilities</h3>
                    <div class="space-y-1 mb-4">
                        <div v-for="l in liabilities" :key="l.code" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-700 dark:text-slate-300">{{ l.code }} — {{ l.name }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(l.amount) }}</span>
                        </div>
                        <div v-if="!liabilities || liabilities.length === 0" class="text-slate-400 text-xs italic py-1">No liabilities recorded.</div>
                        <div class="flex justify-between font-semibold pt-1 text-slate-600 dark:text-slate-400 text-xs">
                            <span>Total Liabilities</span>
                            <span class="font-mono">{{ format(totalLiabilities) }}</span>
                        </div>
                    </div>

                    <!-- Equity -->
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 border-b pb-2">Equity</h3>
                    <div class="space-y-1">
                        <div v-for="eq in equity" :key="eq.code" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-700 dark:text-slate-300">{{ eq.code }} — {{ eq.name }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(eq.amount) }}</span>
                        </div>
                        <!-- Retained earnings / current net income -->
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50 font-medium">
                            <span class="text-slate-700 dark:text-slate-300">Retained Earnings / Current Income</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ format(retainedEarnings) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold pt-1 text-slate-600 dark:text-slate-400 text-xs">
                            <span>Total Equity</span>
                            <span class="font-mono">{{ format(totalEquity) }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t-2 border-slate-300 dark:border-slate-600 flex justify-between font-bold text-base mt-4">
                    <span>Total Liabilities & Equity</span>
                    <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ format(totalLiabilitiesAndEquity) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
