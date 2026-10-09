<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    accounts: Array,
    selectedAccount: Object,
    openingBalance: Number,
    closingBalance: Number,
    totalDebits: Number,
    totalCredits: Number,
    lines: Array,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const accountId = ref(props.filters?.account_id ?? '')
const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.ledger.index', { branch: branchSlug.value }),
        { account_id: accountId.value, from_date: fromDate.value, to_date: toDate.value },
        { preserveState: true, replace: true }
    )
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">General Ledger</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Account ledger movements and audit-trail running balances</p>
            </div>
            <div>
                <ExportDropdown
                    :base-url="route('pos.accounting.ledger.export', { branch: branchSlug })"
                    :params="{ account_id: accountId, from_date: fromDate, to_date: toDate }"
                    title="Export General Ledger"
                />
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex flex-wrap gap-3 items-center flex-shrink-0">
            <div class="w-72">
                <select
                    v-model="accountId"
                    @change="doFilter"
                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
                >
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                        {{ acc.code }} — {{ acc.name }} ({{ acc.normal_balance }})
                    </option>
                </select>
            </div>
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

        <!-- Summary KPI Cards -->
        <div class="px-6 py-3 grid grid-cols-2 sm:grid-cols-4 gap-3 flex-shrink-0">
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Opening Balance</span>
                <span class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ format(openingBalance) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Total Debits</span>
                <span class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ format(totalDebits) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Total Credits</span>
                <span class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ format(totalCredits) }}</span>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400 block">Closing Balance</span>
                <span class="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">{{ format(closingBalance) }}</span>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="flex-1 overflow-y-auto px-6 py-2">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Date</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Entry #</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Description / Memo</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Source</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Debit</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Credit</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <!-- Opening balance row -->
                        <tr class="bg-slate-50/50 dark:bg-slate-800/40 text-xs italic">
                            <td class="px-4 py-2 font-medium" colspan="4">Opening Balance (prior to {{ fromDate }})</td>
                            <td class="px-4 py-2 text-right">—</td>
                            <td class="px-4 py-2 text-right">—</td>
                            <td class="px-4 py-2 text-right font-mono font-bold">{{ format(openingBalance) }}</td>
                        </tr>

                        <tr v-for="line in lines" :key="line.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ line.date }}</td>
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">{{ line.entry_number }}</td>
                            <td class="px-4 py-3">
                                <div class="text-slate-900 dark:text-white">{{ line.description }}</div>
                                <div v-if="line.memo" class="text-xs text-slate-400">{{ line.memo }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="capitalize text-xs text-slate-500 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">
                                    {{ line.source_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ line.debit > 0 ? format(line.debit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ line.credit > 0 ? format(line.credit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                {{ format(line.balance) }}
                            </td>
                        </tr>

                        <tr v-if="!lines || lines.length === 0">
                            <td colspan="7" class="text-center py-8 text-slate-400">No transactions recorded in this period for this account.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
