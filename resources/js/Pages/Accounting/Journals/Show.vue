<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    entry: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const totalDebit = computed(() => {
    return props.entry.lines?.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0) || 0
})

const totalCredit = computed(() => {
    return props.entry.lines?.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0) || 0
})
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('pos.accounting.journals.index', { branch: branchSlug })"
                    class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </Link>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Journal Entry: {{ entry.entry_number }}</span>
                        <span
                            class="px-2 py-0.5 rounded text-xs font-medium uppercase tracking-wider"
                            :class="entry.status === 'posted' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
                        >
                            {{ entry.status }}
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Posted on {{ entry.entry_date }} by {{ entry.user?.name || 'System' }}</p>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            <!-- Metadata Card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-xs text-slate-400 block">Source</span>
                    <span class="font-medium text-slate-900 dark:text-white capitalize">{{ entry.source_type }}</span>
                    <span v-if="entry.event" class="text-xs text-slate-400 ml-1">({{ entry.event }})</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Reference</span>
                    <span class="font-medium text-slate-900 dark:text-white">{{ entry.reference || '—' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Period</span>
                    <span class="font-medium text-slate-900 dark:text-white">{{ entry.financial_period_id ? `Period #${entry.financial_period_id}` : 'General' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Reversal Linkage</span>
                    <span v-if="entry.reversed_by" class="text-xs text-rose-500 font-medium">
                        Reversed by {{ entry.reversed_by.entry_number }}
                    </span>
                    <span v-else-if="entry.reversal_of" class="text-xs text-indigo-500 font-medium">
                        Reversal of {{ entry.reversal_of.entry_number }}
                    </span>
                    <span v-else class="text-slate-400 text-xs">None</span>
                </div>
                <div class="col-span-2 sm:col-span-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <span class="text-xs text-slate-400 block">Description</span>
                    <span class="text-slate-900 dark:text-white">{{ entry.description }}</span>
                </div>
            </div>

            <!-- Lines Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Account Code</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Account Name</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Memo</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Debit</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="line in entry.lines" :key="line.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">{{ line.account?.code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ line.account?.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ line.memo || '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ parseFloat(line.debit) > 0 ? format(line.debit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">
                                {{ parseFloat(line.credit) > 0 ? format(line.credit) : '—' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-700/50 border-t border-slate-200 dark:border-slate-700 font-bold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">Totals:</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">{{ format(totalDebit) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 dark:text-white">{{ format(totalCredit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>
