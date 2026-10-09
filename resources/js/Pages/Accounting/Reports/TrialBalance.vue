<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    rows: Array,
    totalDebit: Number,
    totalCredit: Number,
    isBalanced: Boolean,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const asOfDate = ref(props.filters?.as_of_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.reports.trial-balance', { branch: branchSlug.value }),
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
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Trial Balance</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Verification that total debits equal total credits</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5"
                    :class="isBalanced ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                >
                    <span class="w-2 h-2 rounded-full" :class="isBalanced ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    {{ isBalanced ? 'Books Balanced' : 'Out of Balance' }}
                </span>
                <ExportDropdown
                    :base-url="route('pos.accounting.reports.export', { branch: branchSlug, type: 'trial-balance' })"
                    :params="{ as_of_date: asOfDate }"
                    title="Export Trial Balance"
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

        <!-- Report Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Account Code</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Account Name</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Classification</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Debit Balance</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Credit Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="r in rows" :key="r.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">{{ r.code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ r.name }}</td>
                            <td class="px-4 py-3 uppercase text-xs font-semibold text-slate-400">{{ r.type }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ r.debit > 0 ? format(r.debit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ r.credit > 0 ? format(r.credit) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="!rows || rows.length === 0">
                            <td colspan="5" class="text-center py-8 text-slate-400">No transactions recorded up to this date.</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-700/50 border-t-2 border-slate-300 dark:border-slate-600 font-bold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">Total:</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">{{ format(totalDebit) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">{{ format(totalCredit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>
