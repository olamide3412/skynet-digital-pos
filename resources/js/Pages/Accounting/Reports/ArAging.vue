<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    rows: Array,
    total0To30: Number,
    total31To60: Number,
    total61To90: Number,
    totalOver90: Number,
    totalAr: Number,
    filters: Object,
    isConsolidated: Boolean,
    branchesIncluded: Array,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const asOfDate = ref(props.filters?.as_of_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.reports.ar-aging', { branch: branchSlug.value }),
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
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Accounts Receivable (AR) Aging Summary</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Customer debt breakdown categorized by days outstanding</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    v-if="isConsolidated"
                    class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300"
                >
                    Consolidated Organization View
                </span>
                <ExportDropdown
                    :base-url="route('pos.accounting.reports.export', { branch: branchSlug, type: 'ar-aging' })"
                    :params="{ as_of_date: asOfDate }"
                    title="Export AR Aging Summary"
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
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Bucket Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">Current (0–30 Days)</p>
                    <p class="text-lg font-bold mt-1 text-emerald-600 dark:text-emerald-400">{{ format(total0To30) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">31–60 Days</p>
                    <p class="text-lg font-bold mt-1 text-amber-600 dark:text-amber-400">{{ format(total31To60) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">61–90 Days</p>
                    <p class="text-lg font-bold mt-1 text-orange-600 dark:text-orange-400">{{ format(total61To90) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">90+ Days (Overdue)</p>
                    <p class="text-lg font-bold mt-1 text-rose-600 dark:text-rose-400">{{ format(totalOver90) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 shadow-sm col-span-2 md:col-span-1">
                    <p class="text-xs font-semibold text-indigo-800 dark:text-indigo-300">Total Outstanding AR</p>
                    <p class="text-xl font-black mt-1 text-indigo-900 dark:text-indigo-200">{{ format(totalAr) }}</p>
                </div>
            </div>

            <!-- Aging Table -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/80 text-xs font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-5 py-3 text-left">Customer / Debtor</th>
                            <th class="px-4 py-3 text-right">0–30 Days</th>
                            <th class="px-4 py-3 text-right">31–60 Days</th>
                            <th class="px-4 py-3 text-right">61–90 Days</th>
                            <th class="px-4 py-3 text-right">90+ Days</th>
                            <th class="px-5 py-3 text-right">Total Owed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="c in rows" :key="c.customer_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-5 py-3.5 font-medium text-slate-900 dark:text-white">{{ c.customer_name }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-300">{{ format(c.b0_30) }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-300">{{ format(c.b31_60) }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-300">{{ format(c.b61_90) }}</td>
                            <td class="px-4 py-3.5 text-right font-semibold text-rose-600 dark:text-rose-400">{{ format(c.bOver90) }}</td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-900 dark:text-white">{{ format(c.total) }}</td>
                        </tr>
                        <tr v-if="!rows?.length">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">No outstanding customer debt balances found as of {{ asOfDate }}.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows?.length" class="bg-slate-50 dark:bg-slate-800/80 border-t-2 border-slate-200 dark:border-slate-700 font-bold">
                        <tr>
                            <td class="px-5 py-3 text-slate-900 dark:text-white">Total</td>
                            <td class="px-4 py-3 text-right text-slate-800 dark:text-slate-200">{{ format(total0To30) }}</td>
                            <td class="px-4 py-3 text-right text-slate-800 dark:text-slate-200">{{ format(total31To60) }}</td>
                            <td class="px-4 py-3 text-right text-slate-800 dark:text-slate-200">{{ format(total61To90) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-400">{{ format(totalOver90) }}</td>
                            <td class="px-5 py-3 text-right text-indigo-600 dark:text-indigo-400">{{ format(totalAr) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>
