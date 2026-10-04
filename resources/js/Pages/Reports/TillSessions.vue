<script setup>
import { ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import TillSlipModal from '@/Components/POS/TillSlipModal.vue'
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    sessions:   Object, // paginated
    staffUsers: { type: Array, default: () => [] },
    canSeeAll:  Boolean,
    filters:    { type: Object, default: () => ({}) },
})

const page = usePage()
const { format } = useCurrency()

const filterCashier = ref(props.filters.cashier_id || '')
const filterStatus = ref(props.filters.status || '')
const filterStartDate = ref(props.filters.start_date || '')
const filterEndDate = ref(props.filters.end_date || '')

function applyFilters() {
    router.get(route('pos.till.sessions.index'), {
        cashier_id: filterCashier.value || undefined,
        status:     filterStatus.value || undefined,
        start_date: filterStartDate.value || undefined,
        end_date:   filterEndDate.value || undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    filterCashier.value = ''
    filterStatus.value = ''
    filterStartDate.value = ''
    filterEndDate.value = ''
    applyFilters()
}

// Till Slip Preview State
const activeSlipSession = ref(null)
function openSlip(session) {
    activeSlipSession.value = session
}

// Force-close Modal State
const forceCloseSession = ref(null)
const forceClosingCash = ref('')
const forceClosingNote = ref('')
const isForceClosing = ref(false)
const forceCloseError = ref('')

function openForceCloseModal(session) {
    forceCloseSession.value = session
    forceClosingCash.value = session.summary?.expected_cash ?? session.opening_amount
    forceClosingNote.value = 'Abandoned session force-closed by supervisor'
    forceCloseError.value = ''
}

async function submitForceClose() {
    if (!forceCloseSession.value) return
    if (forceClosingCash.value === '' || Number(forceClosingCash.value) < 0) {
        forceCloseError.value = 'Please enter a valid closing cash amount (0 or more).'
        return
    }

    isForceClosing.value = true
    forceCloseError.value = ''

    try {
        await axios.post(route('pos.till.sessions.force-close', forceCloseSession.value.id), {
            closing_amount: parseFloat(forceClosingCash.value) || 0,
            closing_note:   forceClosingNote.value,
        })
        isForceClosing.value = false
        forceCloseSession.value = null

        router.reload({ preserveScroll: true })
    } catch (err) {
        isForceClosing.value = false
        forceCloseError.value = err.response?.data?.message || err.response?.data?.error || 'Failed to force-close session.'
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>💼 Till Sessions & Cash Drawer Oversight</span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Audit cashier shifts, opening floats, cash drops, and shift variances
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    :href="route('pos.reports.end-of-day')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 transition"
                >
                    📊 End of Day Report
                </Link>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 bg-slate-100/50 dark:bg-slate-800/40 flex flex-wrap items-end gap-3 flex-shrink-0">
            <div v-if="canSeeAll" class="min-w-[180px]">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Cashier</label>
                <select
                    v-model="filterCashier"
                    @change="applyFilters"
                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none"
                >
                    <option value="">All Cashiers</option>
                    <option v-for="u in staffUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.username }})</option>
                </select>
            </div>

            <div class="min-w-[130px]">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Status</label>
                <select
                    v-model="filterStatus"
                    @change="applyFilters"
                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none"
                >
                    <option value="">All Sessions</option>
                    <option value="open">Active (Open)</option>
                    <option value="closed">Reconciled (Closed)</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">From Date</label>
                <input
                    v-model="filterStartDate"
                    type="date"
                    @change="applyFilters"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none font-mono"
                />
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">To Date</label>
                <input
                    v-model="filterEndDate"
                    type="date"
                    @change="applyFilters"
                    class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none font-mono"
                />
            </div>

            <button
                type="button"
                @click="resetFilters"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition"
            >
                Reset
            </button>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs min-w-[900px]">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-4 py-3 font-bold">Shift ID</th>
                                <th class="text-left px-4 py-3 font-bold">Cashier</th>
                                <th class="text-left px-4 py-3 font-bold">Opened At</th>
                                <th class="text-left px-4 py-3 font-bold">Closed At</th>
                                <th class="text-left px-4 py-3 font-bold">Status</th>
                                <th class="text-right px-4 py-3 font-bold">Opening Float</th>
                                <th class="text-right px-4 py-3 font-bold">Cash Sales</th>
                                <th class="text-right px-4 py-3 font-bold">Expected Cash</th>
                                <th class="text-right px-4 py-3 font-bold">Counted Cash</th>
                                <th class="text-right px-4 py-3 font-bold">Variance</th>
                                <th class="text-center px-4 py-3 font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                            <tr v-if="!sessions.data || sessions.data.length === 0">
                                <td colspan="11" class="text-center py-10 text-slate-400 text-sm">
                                    No till sessions found matching criteria.
                                </td>
                            </tr>
                            <tr
                                v-for="s in sessions.data"
                                :key="s.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition"
                            >
                                <td class="px-4 py-3 font-mono font-bold text-slate-900 dark:text-white">
                                    #{{ s.id }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                    {{ s.cashier?.name || 'Staff' }}
                                    <span class="block text-[10px] text-slate-400 font-mono font-normal">@{{ s.cashier?.username }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-mono">
                                    {{ dayjs(s.opened_at).format('DD MMM, HH:mm') }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-mono">
                                    {{ s.closed_at ? dayjs(s.closed_at).format('DD MMM, HH:mm') : '— Active' }}
                                    <span v-if="s.closed_by && s.closed_by.name !== s.cashier?.name" class="block text-[10px] text-amber-600 dark:text-amber-400">
                                        by {{ s.closed_by.name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                            s.status === 'open'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800'
                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        ]"
                                    >
                                        {{ s.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">
                                    {{ format(s.opening_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                                    +{{ format(s.summary?.cash_sales || 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ format(s.expected_amount ?? s.summary?.expected_cash ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold">
                                    {{ s.closing_amount !== null && s.closing_amount !== undefined ? format(s.closing_amount) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold">
                                    <span
                                        v-if="s.status === 'closed'"
                                        :class="[
                                            'px-1.5 py-0.5 rounded text-[11px]',
                                            s.variance == 0 ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50' :
                                            s.variance < 0 ? 'text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/50' : 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/50'
                                        ]"
                                    >
                                        {{ s.variance > 0 ? '+' : '' }}{{ format(s.variance) }}
                                    </span>
                                    <span v-else class="text-slate-400 text-[10px]">In Progress</span>
                                </td>
                                <td class="px-4 py-3 text-center space-x-1">
                                    <button
                                        type="button"
                                        @click="openSlip(s)"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-[11px] font-bold transition cursor-pointer"
                                        title="View & Print Shift Slip"
                                    >
                                        🧾 Slip
                                    </button>

                                    <button
                                        v-if="s.status === 'open' && canSeeAll"
                                        type="button"
                                        @click="openForceCloseModal(s)"
                                        class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-300 text-[11px] font-bold border border-rose-200 dark:border-rose-800 transition cursor-pointer"
                                        title="Force close abandoned session"
                                    >
                                        🔒 Force Close
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="sessions.links && sessions.links.length > 3" class="px-6 py-3 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-xs">
                    <span class="text-slate-500 dark:text-slate-400">
                        Showing {{ sessions.from }} to {{ sessions.to }} of {{ sessions.total }} till sessions
                    </span>
                    <PaginationLinks :links="sessions.links" />
                </div>
            </div>
        </div>

        <!-- Force Close Modal -->
        <div
            v-if="forceCloseSession"
            class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
        >
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 max-w-md w-full shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-200 dark:border-slate-800">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">Force Close Till Session #{{ forceCloseSession.id }}</h3>
                        <p class="text-xs text-slate-500">Cashier: {{ forceCloseSession.cashier?.name }}</p>
                    </div>
                </div>

                <div v-if="forceCloseError" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-xs text-rose-700 dark:text-rose-300">
                    {{ forceCloseError }}
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        Actual Counted Cash
                    </label>
                    <input
                        v-model.number="forceClosingCash"
                        type="number"
                        step="any"
                        min="0"
                        required
                        class="w-full px-3.5 py-2.5 text-base font-bold font-mono text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-rose-500 focus:outline-hidden"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                        Reason / Note for Force-Close
                    </label>
                    <input
                        v-model="forceClosingNote"
                        type="text"
                        class="w-full px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-rose-500 focus:outline-hidden"
                    />
                </div>

                <div class="pt-2 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="forceCloseSession = null"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitForceClose"
                        :disabled="isForceClosing"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-md transition flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <span v-if="isForceClosing" class="animate-spin">🔄</span>
                        <span>Confirm Force Close</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Slip Modal Preview -->
        <TillSlipModal
            v-if="activeSlipSession"
            :session="activeSlipSession"
            :summary="activeSlipSession.summary"
            @close="activeSlipSession = null"
        />
    </div>
</template>
