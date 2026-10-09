<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    accounts: Array,
    existingEntry: Object,
    existingLines: Object,
    accountingStartedAt: String,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

// Initialize form balances
const balances = ref({})
props.accounts.forEach(acc => {
    const existing = props.existingLines?.[acc.id]
    balances.value[acc.id] = {
        account_id: acc.id,
        debit: existing?.debit || 0,
        credit: existing?.credit || 0,
        memo: 'Opening balance',
    }
})

const totalDebit = computed(() => {
    return Object.values(balances.value).reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0)
})

const totalCredit = computed(() => {
    return Object.values(balances.value).reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0)
})

const isBalanced = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value) < 0.01 && (totalDebit.value > 0)
})

const submitting = ref(false)

function submit() {
    if (!isBalanced.value) {
        alert('Debits and Credits must balance before submitting.')
        return
    }

    submitting.value = true
    const payloadLines = Object.values(balances.value).filter(line => (line.debit > 0 || line.credit > 0))

    router.post(route('pos.accounting.opening-balances.store', { branch: branchSlug.value }), {
        lines: payloadLines
    }, {
        onFinish: () => submitting.value = false
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Opening Balances Wizard</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Enter initial balances dated on books activation ({{ accountingStartedAt || 'Initial' }})
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="px-3 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5"
                    :class="isBalanced ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                >
                    <span class="w-2 h-2 rounded-full" :class="isBalanced ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    {{ isBalanced ? 'Balanced' : 'Out of Balance' }}
                </span>
                <button
                    @click="submit"
                    :disabled="!isBalanced || submitting"
                    class="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white transition flex items-center gap-1.5"
                >
                    {{ submitting ? 'Saving...' : 'Post Opening Balances' }}
                </button>
            </div>
        </div>

        <!-- Notification if already posted -->
        <div v-if="existingEntry" class="px-6 py-3 bg-amber-50 dark:bg-amber-950/30 border-b border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-200 flex items-center justify-between flex-shrink-0">
            <span>Opening balances have already been posted (Entry #{{ existingEntry.entry_number }}). Submitting new values will automatically reverse and update the previous entry.</span>
        </div>

        <!-- Table Form -->
        <div class="flex-1 overflow-y-auto p-6">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/80 text-xs font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Account Name</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Debit Balance</th>
                            <th class="px-4 py-3 text-right">Credit Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ acc.code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ acc.name }}</td>
                            <td class="px-4 py-3 text-xs capitalize text-slate-500">{{ acc.type }}</td>
                            <td class="px-4 py-2 text-right">
                                <input
                                    v-model.number="balances[acc.id].debit"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-36 text-right px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs outline-none focus:border-emerald-500"
                                    placeholder="0.00"
                                />
                            </td>
                            <td class="px-4 py-2 text-right">
                                <input
                                    v-model.number="balances[acc.id].credit"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-36 text-right px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs outline-none focus:border-emerald-500"
                                    placeholder="0.00"
                                />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-800/80 border-t-2 border-slate-200 dark:border-slate-700 font-bold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-slate-900 dark:text-white">Totals:</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ format(totalDebit) }}</td>
                            <td class="px-4 py-3 text-right text-indigo-600 dark:text-indigo-400">{{ format(totalCredit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>
