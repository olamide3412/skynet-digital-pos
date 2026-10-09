<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import Pagination from '@/Components/Pagination.vue'
import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    entries: Object,
    accounts: Array,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const canManage = computed(() => Boolean(page.props.pos_permissions?.canManageJournalEntries))
const { format } = useCurrency()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.journals.index', { branch: branchSlug.value }),
        { search: search.value, status: status.value, from_date: fromDate.value, to_date: toDate.value },
        { preserveState: true, replace: true }
    )
}

// Manual Journal Entry Modal
const showCreateModal = ref(false)
const createForm = useForm({
    entry_date: new Date().toISOString().split('T')[0],
    description: '',
    reference: '',
    lines: [
        { account_id: '', debit: 0, credit: 0, memo: '' },
        { account_id: '', debit: 0, credit: 0, memo: '' },
    ],
})

function addLine() {
    createForm.lines.push({ account_id: '', debit: 0, credit: 0, memo: '' })
}

function removeLine(idx) {
    if (createForm.lines.length > 2) {
        createForm.lines.splice(idx, 1)
    }
}

const totalDebit = computed(() => {
    return createForm.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0)
})

const totalCredit = computed(() => {
    return createForm.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0)
})

const isBalanced = computed(() => {
    const d = Math.round(totalDebit.value * 100)
    const c = Math.round(totalCredit.value * 100)
    return d > 0 && d === c
})

function submitCreate() {
    if (!isBalanced.value) return
    createForm.post(route('pos.accounting.journals.store', { branch: branchSlug.value }), {
        onSuccess: () => {
            showCreateModal.value = false
            createForm.reset()
        },
    })
}

// Reversal Modal
const showReverseModal = ref(false)
const reversingEntry = ref(null)
const reverseForm = useForm({
    reason: '',
})

function openReverse(entry) {
    reversingEntry.value = entry
    reverseForm.reason = ''
    showReverseModal.value = true
}

function submitReverse() {
    if (!reversingEntry.value) return
    reverseForm.post(route('pos.accounting.journals.reverse', { branch: branchSlug.value, journal: reversingEntry.value.id }), {
        onSuccess: () => {
            showReverseModal.value = false
            reversingEntry.value = null
        },
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Journal Entries</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Audit trail of all double-entry postings and financial adjustments</p>
            </div>
            <div class="flex items-center gap-2">
                <ExportDropdown
                    :base-url="route('pos.accounting.journals.export', { branch: branchSlug })"
                    :params="{ search: search, status: status, from_date: fromDate, to_date: toDate }"
                    title="Export Journal Entries"
                />
                <button
                    v-if="canManage"
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Manual Journal
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex flex-wrap gap-2 flex-shrink-0">
            <input
                v-model="search"
                @keydown.enter="doFilter"
                type="text"
                placeholder="Search entry #, desc, ref…"
                class="w-56 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
            <select
                v-model="status"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            >
                <option value="">All Statuses</option>
                <option value="posted">Posted</option>
                <option value="reversed">Reversed</option>
            </select>
            <input
                v-model="fromDate"
                type="date"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
            <input
                v-model="toDate"
                type="date"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
        </div>

        <!-- Entries Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Entry #</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Date</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Source / Event</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Description</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Debit / Credit</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Status</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">
                                <Link :href="route('pos.accounting.journals.show', { branch: branchSlug, journal: entry.id })" class="hover:text-emerald-600 transition">
                                    {{ entry.entry_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ entry.entry_date }}</td>
                            <td class="px-4 py-3">
                                <span class="capitalize font-medium text-slate-900 dark:text-white">{{ entry.source_type }}</span>
                                <span v-if="entry.event" class="text-xs text-slate-400 ml-1">({{ entry.event }})</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-slate-900 dark:text-white">{{ entry.description }}</div>
                                <div v-if="entry.reference" class="text-xs text-slate-400">Ref: {{ entry.reference }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-900 dark:text-white">
                                {{ format(entry.lines ? entry.lines.reduce((s, l) => s + parseFloat(l.debit || 0), 0) : 0) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-medium uppercase tracking-wider"
                                    :class="entry.status === 'posted' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
                                >
                                    {{ entry.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link
                                    :href="route('pos.accounting.journals.show', { branch: branchSlug, journal: entry.id })"
                                    class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium"
                                >
                                    View
                                </Link>
                                <button
                                    v-if="canManage && entry.status === 'posted' && !entry.is_reversal && !entry.reversed_by_id"
                                    @click="openReverse(entry)"
                                    class="text-xs text-rose-600 hover:text-rose-500 dark:text-rose-400 font-medium"
                                >
                                    Reverse
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!entries.data || entries.data.length === 0">
                            <td colspan="7" class="text-center py-8 text-slate-400">No journal entries found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4 flex justify-end" v-if="entries.links && entries.links.length > 3">
                <Pagination :links="entries.links" />
            </div>
        </div>

        <!-- Manual Journal Entry Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 max-w-3xl w-full p-6 max-h-[90vh] flex flex-col">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-3">Record Manual Journal Entry</h3>
                <form @submit.prevent="submitCreate" class="flex-1 overflow-y-auto space-y-4 pr-1">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Date</label>
                            <input v-model="createForm.entry_date" type="date" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" required />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Reference / Note</label>
                            <input v-model="createForm.reference" type="text" placeholder="e.g. INV-1049, Capital Deposit" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Description</label>
                        <input v-model="createForm.description" type="text" placeholder="e.g. Month-end depreciation adjustment" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" required />
                    </div>

                    <!-- Journal Lines -->
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Journal Lines (Debit must equal Credit)</span>
                            <button type="button" @click="addLine" class="text-xs text-emerald-600 hover:text-emerald-500 font-medium flex items-center gap-1">
                                + Add Line
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(line, idx) in createForm.lines" :key="idx" class="flex gap-2 items-center">
                                <select v-model="line.account_id" class="flex-1 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500" required>
                                    <option value="" disabled>Select Account</option>
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                        {{ acc.code }} — {{ acc.name }}
                                    </option>
                                </select>
                                <input v-model.number="line.debit" type="number" step="0.01" min="0" placeholder="Debit" class="w-28 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2.5 py-1.5 text-xs text-right font-mono text-slate-900 dark:text-white outline-none focus:border-emerald-500" />
                                <input v-model.number="line.credit" type="number" step="0.01" min="0" placeholder="Credit" class="w-28 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2.5 py-1.5 text-xs text-right font-mono text-slate-900 dark:text-white outline-none focus:border-emerald-500" />
                                <input v-model="line.memo" type="text" placeholder="Memo (optional)" class="w-36 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500" />
                                <button type="button" @click="removeLine(idx)" :disabled="createForm.lines.length <= 2" class="text-slate-400 hover:text-rose-500 p-1 disabled:opacity-30">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Balances Footer -->
                        <div class="mt-3 pt-2 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs">
                            <div class="flex gap-4">
                                <span>Total Debit: <strong class="font-mono">{{ format(totalDebit) }}</strong></span>
                                <span>Total Credit: <strong class="font-mono">{{ format(totalCredit) }}</strong></span>
                            </div>
                            <div>
                                <span v-if="isBalanced" class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                    Balanced
                                </span>
                                <span v-else class="text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                                    Difference: {{ format(Math.abs(totalDebit - totalCredit)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-slate-200 dark:border-slate-600 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="!isBalanced || createForm.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                            Post Journal Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reverse Modal -->
        <div v-if="showReverseModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Reverse Journal Entry</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    This will create an exact opposite reversing entry for <strong>{{ reversingEntry?.entry_number }}</strong>.
                </p>
                <form @submit.prevent="submitReverse" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Reason for Reversal</label>
                        <textarea v-model="reverseForm.reason" rows="3" placeholder="Provide audit reason for reversal…" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-rose-500" required></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showReverseModal = false" class="px-4 py-2 border border-slate-200 dark:border-slate-600 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="reverseForm.processing" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-sm font-medium">Confirm Reversal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
