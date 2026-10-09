<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    periods: Array,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const canClose = computed(() => Boolean(page.props.pos_permissions?.canCloseFinancialPeriods))

function closePeriod(period) {
    if (!confirm(`Are you sure you want to CLOSE the financial period "${period.name}"? Transactions posted for this month will post into the current open period with a memo.`)) return
    router.post(route('pos.accounting.periods.close', { branch: branchSlug.value, period: period.id }))
}

function reopenPeriod(period) {
    if (!confirm(`Are you sure you want to REOPEN the financial period "${period.name}"?`)) return
    router.post(route('pos.accounting.periods.reopen', { branch: branchSlug.value, period: period.id }))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Financial Periods</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Control accounting month-end closing and book locking</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Period Name</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Date Range</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Status</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Closed At / By</th>
                            <th v-if="canClose" class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="period in periods" :key="period.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ period.name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ period.starts_on || period.start_date }} → {{ period.ends_on || period.end_date }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-medium uppercase tracking-wider"
                                    :class="period.status === 'open' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300'"
                                >
                                    {{ period.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="period.status === 'closed'">
                                    {{ period.closed_at }} ({{ period.closed_by_user?.name || 'Admin' }})
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td v-if="canClose" class="px-4 py-3 text-right">
                                <button
                                    v-if="period.status === 'open'"
                                    @click="closePeriod(period)"
                                    class="text-xs text-amber-600 hover:text-amber-500 dark:text-amber-400 font-medium"
                                >
                                    Close Period
                                </button>
                                <button
                                    v-else
                                    @click="reopenPeriod(period)"
                                    class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium"
                                >
                                    Reopen
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!periods || periods.length === 0">
                            <td colspan="5" class="text-center py-8 text-slate-400">No financial periods found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
