<script setup>
import { ref, computed } from 'vue'
import { useForm, Link, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    customer:     Object,
    transactions: Array,
    summary:      Object,
})

// ── Transaction form ─────────────────────────────────────────────────────────
const showModal  = ref(false)
const modalType  = ref('credit') // 'credit' | 'debit'

const form = useForm({
    type:      'credit',
    amount:    '',
    narration: '',
})

function openModal(type) {
    form.reset()
    form.clearErrors()
    form.type    = type
    modalType.value = type
    showModal.value = true
}

// Live front-end validation: credit cannot exceed current balance
const amountError = computed(() => {
    if (!form.amount) return null
    const amt = parseFloat(form.amount)
    if (isNaN(amt) || amt <= 0) return 'Amount must be greater than zero.'
    if (modalType.value === 'credit' && amt > parseFloat(props.customer.debt_bal)) {
        return `Payment cannot exceed the outstanding balance of ${fmt(props.customer.debt_bal)}.`
    }
    return form.errors.amount ?? null  // server-side error fallback
})

const canSubmit = computed(() => !amountError.value && form.amount && !form.processing)

function submitTransaction() {
    if (!canSubmit.value) return
    form.post(route('pos.customers.debt', props.customer.id), {
        // Server redirects to this same ledger page — Inertia will follow it automatically
        onError: () => {
            // Errors populate form.errors — modal stays open so user can fix them
        },
        onSuccess: () => {
            showModal.value = false
            form.reset()
        },
    })
}

// ── Filter / search ───────────────────────────────────────────────────────────
const filterType   = ref('all')
const searchQuery  = ref('')

const filtered = computed(() => {
    return props.transactions.filter(t => {
        const matchType   = filterType.value === 'all' || t.type === filterType.value
        const matchSearch = !searchQuery.value ||
            t.narration?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            t.reference?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            t.user?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
        return matchType && matchSearch
    })
})

// ── Formatting ────────────────────────────────────────────────────────────────
function fmt(val) {
    return '₦' + Number(val ?? 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })
}

function fmtDate(val) {
    if (!val) return '—'
    return new Date(val).toLocaleString('en-NG', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <div class="flex flex-col h-full bg-slate-900 text-slate-100 overflow-hidden">

        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex-shrink-0 px-6 py-4 border-b border-slate-700 flex items-center justify-between bg-slate-800">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.customers.index')" class="text-slate-400 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div>
                    <h1 class="text-lg font-bold text-white leading-tight">Debt Ledger</h1>
                    <p class="text-xs text-slate-400">{{ customer.name }} · {{ customer.phone }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="openModal('credit')"
                    class="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Credit (Payment)
                </button>
                <button @click="openModal('debit')"
                    class="flex items-center gap-1.5 px-4 py-2 bg-red-600/80 hover:bg-red-500 text-white rounded-lg text-sm font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                    </svg>
                    Debit (Charge)
                </button>
            </div>
        </div>

        <!-- ── Summary cards ───────────────────────────────────────────────── -->
        <div class="flex-shrink-0 grid grid-cols-2 md:grid-cols-4 gap-4 px-6 py-4 border-b border-slate-700 bg-slate-800/40">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                <p class="text-xs text-slate-400 mb-1">Outstanding Balance</p>
                <p :class="['text-xl font-black', summary.current_balance > 0 ? 'text-red-400' : 'text-emerald-400']">
                    {{ fmt(summary.current_balance) }}
                </p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                <p class="text-xs text-slate-400 mb-1">Total Charged (Debits)</p>
                <p class="text-xl font-bold text-orange-400">{{ fmt(summary.total_debited) }}</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                <p class="text-xs text-slate-400 mb-1">Total Paid (Credits)</p>
                <p class="text-xl font-bold text-emerald-400">{{ fmt(summary.total_credited) }}</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                <p class="text-xs text-slate-400 mb-1">Total Entries</p>
                <p class="text-xl font-bold text-white">{{ summary.total_entries }}</p>
            </div>
        </div>

        <!-- ── Filter bar ──────────────────────────────────────────────────── -->
        <div class="flex-shrink-0 px-6 py-3 border-b border-slate-700 flex items-center gap-3 bg-slate-800/20">
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input v-model="searchQuery" placeholder="Search narration, reference, user…"
                    class="w-full pl-9 pr-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-500 outline-none focus:border-emerald-500 transition"/>
            </div>
            <div class="flex gap-1">
                <button v-for="opt in [{v:'all',l:'All'},{v:'credit',l:'Credits'},{v:'debit',l:'Debits'}]" :key="opt.v"
                    @click="filterType = opt.v"
                    :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition', filterType === opt.v
                        ? 'bg-emerald-600 text-white'
                        : 'bg-slate-800 text-slate-400 hover:text-white border border-slate-700']">
                    {{ opt.l }}
                </button>
            </div>
            <p class="ml-auto text-xs text-slate-500">{{ filtered.length }} entries</p>
        </div>

        <!-- ── Ledger table ────────────────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-800 border-b border-slate-700 z-10">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider w-36">Date & Time</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider w-32">Reference</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider">Narration</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider w-28">Processed By</th>
                        <th class="text-right px-4 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider w-28">Debit</th>
                        <th class="text-right px-4 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider w-28">Credit</th>
                        <th class="text-right px-6 py-3 text-xs text-slate-400 font-semibold uppercase tracking-wider w-32">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filtered.length === 0">
                        <td colspan="7" class="text-center py-20 text-slate-500">
                            <svg class="w-10 h-10 mx-auto mb-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            No transactions found
                        </td>
                    </tr>
                    <tr v-for="tx in filtered" :key="tx.id"
                        class="border-b border-slate-800 hover:bg-slate-800/40 transition group">
                        <!-- Date -->
                        <td class="px-6 py-3 text-xs text-slate-400 whitespace-nowrap">
                            {{ fmtDate(tx.created_at) }}
                        </td>
                        <!-- Reference -->
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs bg-slate-800 border border-slate-700 px-2 py-0.5 rounded text-slate-300">
                                {{ tx.reference ?? '—' }}
                            </span>
                        </td>
                        <!-- Narration -->
                        <td class="px-4 py-3 text-slate-200 max-w-xs">
                            <p class="truncate">{{ tx.narration }}</p>
                            <p v-if="tx.sale_id" class="text-xs text-slateald-500 mt-0.5">
                                Linked to Sale #{{ tx.sale_id }}
                            </p>
                        </td>
                        <!-- User -->
                        <td class="px-4 py-3 text-xs text-slate-400">{{ tx.user?.name ?? '—' }}</td>
                        <!-- Debit -->
                        <td class="px-4 py-3 text-right">
                            <span v-if="tx.type === 'debit'" class="text-red-400 font-semibold">
                                {{ fmt(tx.amount) }}
                            </span>
                            <span v-else class="text-slate-700">—</span>
                        </td>
                        <!-- Credit -->
                        <td class="px-4 py-3 text-right">
                            <span v-if="tx.type === 'credit'" class="text-emerald-400 font-semibold">
                                {{ fmt(tx.amount) }}
                            </span>
                            <span v-else class="text-slate-700">—</span>
                        </td>
                        <!-- Balance after -->
                        <td class="px-6 py-3 text-right">
                            <span :class="['font-bold text-sm', tx.balance_after > 0 ? 'text-orange-400' : 'text-emerald-400']">
                                {{ fmt(tx.balance_after) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Transaction Modal ───────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                @click.self="showModal = false">
                <Transition
                    enter-active-class="transition duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                >
                    <div v-if="showModal" class="bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-md">
                        <!-- Modal header -->
                        <div :class="['flex items-center gap-3 px-6 py-4 border-b border-slate-700 rounded-t-2xl',
                            modalType === 'credit' ? 'bg-emerald-600/10' : 'bg-red-600/10']">
                            <div :class="['w-9 h-9 rounded-xl flex items-center justify-center',
                                modalType === 'credit' ? 'bg-emerald-500/20' : 'bg-red-500/20']">
                                <svg :class="['w-5 h-5', modalType === 'credit' ? 'text-emerald-400' : 'text-red-400']"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        :d="modalType === 'credit' ? 'M12 4v16m8-8H4' : 'M20 12H4'"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-bold text-white">
                                    {{ modalType === 'credit' ? 'Record Payment (Credit)' : 'Add Charge (Debit)' }}
                                </h2>
                                <p class="text-xs text-slate-400">{{ customer.name }} · Balance: {{ fmt(customer.debt_bal) }}</p>
                            </div>
                            <button @click="showModal = false" class="ml-auto text-slate-500 hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Modal body -->
                        <form @submit.prevent="submitTransaction" class="px-6 py-5 space-y-4">
                            <!-- Amount -->
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">
                                    Amount (₦)
                                    <span v-if="modalType === 'credit'" class="ml-1 text-slate-500">
                                        · max {{ fmt(customer.debt_bal) }}
                                    </span>
                                </label>
                                <input v-model="form.amount" type="number" step="0.01" min="0.01"
                                    :max="modalType === 'credit' ? customer.debt_bal : undefined"
                                    :class="['w-full px-4 py-3 rounded-xl border bg-slate-900 text-white text-lg font-bold outline-none transition',
                                        amountError ? 'border-red-500 focus:border-red-500' : 'border-slate-700 focus:border-emerald-500']"
                                    placeholder="0.00" required/>
                                <p v-if="amountError" class="mt-1 text-xs text-red-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ amountError }}
                                </p>
                            </div>

                            <!-- Narration -->
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">Narration / Note</label>
                                <textarea v-model="form.narration" rows="3"
                                    :class="['w-full px-4 py-3 rounded-xl border bg-slate-900 text-white text-sm outline-none transition resize-none',
                                        form.errors.narration ? 'border-red-500' : 'border-slate-700 focus:border-emerald-500']"
                                    :placeholder="modalType === 'credit' ? 'e.g. Cash payment received, Bank transfer ref: …' : 'e.g. Additional charges for delivery'"/>
                                <p v-if="form.errors.narration" class="mt-1 text-xs text-red-400">{{ form.errors.narration }}</p>
                            </div>

                            <!-- Preview -->
                            <div v-if="form.amount" :class="['rounded-xl p-3 border text-sm',
                                modalType === 'credit' ? 'bg-emerald-500/5 border-emerald-500/20' : 'bg-red-500/5 border-red-500/20']">
                                <div class="flex justify-between text-slate-400 mb-1">
                                    <span>Current Balance</span>
                                    <span class="font-mono">{{ fmt(customer.debt_bal) }}</span>
                                </div>
                                <div class="flex justify-between font-bold">
                                    <span :class="modalType === 'credit' ? 'text-emerald-400' : 'text-red-400'">
                                        {{ modalType === 'credit' ? '− Payment' : '+ Charge' }}
                                    </span>
                                    <span :class="['font-mono', modalType === 'credit' ? 'text-emerald-400' : 'text-red-400']">
                                        {{ fmt(form.amount || 0) }}
                                    </span>
                                </div>
                                <hr class="border-slate-700 my-2"/>
                                <div class="flex justify-between text-white font-bold">
                                    <span>New Balance</span>
                                    <span class="font-mono">
                                        {{ fmt(Math.max(0, (parseFloat(customer.debt_bal) || 0) + (modalType === 'credit' ? -1 : 1) * (parseFloat(form.amount) || 0))) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="showModal = false"
                                    class="flex-1 py-3 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-300 font-medium text-sm transition">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="!canSubmit"
                                    :class="['flex-1 py-3 rounded-xl font-bold text-sm text-white transition',
                                        modalType === 'credit'
                                            ? 'bg-emerald-600 hover:bg-emerald-500 disabled:bg-emerald-900 disabled:text-emerald-700 disabled:cursor-not-allowed'
                                            : 'bg-red-600 hover:bg-red-500 disabled:bg-red-900 disabled:text-red-700 disabled:cursor-not-allowed']">
                                    {{ form.processing ? 'Saving…' : (modalType === 'credit' ? 'Record Payment' : 'Add Charge') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
