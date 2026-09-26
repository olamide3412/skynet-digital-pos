<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import ExpenseModal from '@/Components/ExpenseModal.vue'
import axios from 'axios'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    expenses: Object,
    summary: Object,
    categories: Array,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')
const perms = computed(() => page.props.pos_permissions || {})
const { format } = useCurrency()

const activePreset = ref(props.filters?.preset || 'month')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const selectedCategoryId = ref(props.filters?.category_id || '')
const searchQuery = ref(props.filters?.search || '')

// Expense Modal State
const showExpenseModal = ref(false)
const selectedExpense = ref(null)

// Receipt Preview Modal
const showReceiptModal = ref(false)
const previewReceiptUrl = ref('')

// Delete Modal State
const showDeleteModal = ref(false)
const expenseToDelete = ref(null)
const deleteReason = ref('')
const isDeleting = ref(false)

// Revisions Modal State
const showRevisionsModal = ref(false)
const revisionsList = ref([])
const revisionsLoading = ref(false)

function selectPreset(preset) {
    activePreset.value = preset
    const today = dayjs().format('YYYY-MM-DD')

    if (preset === 'today') {
        startDate.value = today
        endDate.value = today
    } else if (preset === 'yesterday') {
        const y = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
        startDate.value = y
        endDate.value = y
    } else if (preset === 'week') {
        startDate.value = dayjs().startOf('week').format('YYYY-MM-DD')
        endDate.value = today
    } else if (preset === 'month') {
        startDate.value = dayjs().startOf('month').format('YYYY-MM-DD')
        endDate.value = today
    }
    applyFilter()
}

function applyFilter() {
    router.get(
        route('pos.reports.expenses', { branch: branchSlug.value }),
        {
            preset: activePreset.value,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
            category_id: selectedCategoryId.value || undefined,
            search: searchQuery.value ? searchQuery.value.trim() : undefined,
        },
        { preserveState: true, replace: true }
    )
}

function resetFilters() {
    activePreset.value = 'month'
    selectedCategoryId.value = ''
    searchQuery.value = ''
    selectPreset('month')
}

function openCreateModal() {
    selectedExpense.value = null
    showExpenseModal.value = true
}

function openEditModal(expense) {
    selectedExpense.value = expense
    showExpenseModal.value = true
}

function confirmDelete(expense) {
    expenseToDelete.value = expense
    deleteReason.value = ''
    showDeleteModal.value = true
}

async function executeDelete() {
    if (!expenseToDelete.value) return
    isDeleting.value = true

    try {
        await axios.delete(route('pos.expenses.destroy', {
            branch: branchSlug.value,
            expense: expenseToDelete.value.id,
        }), {
            data: { reason: deleteReason.value || 'Deleted by authorized staff' }
        })

        showDeleteModal.value = false
        expenseToDelete.value = null
        router.reload({ preserveScroll: true })
    } catch (err) {
        console.error('Delete failed:', err)
        alert(err.response?.data?.message || 'Failed to delete expense.')
    } finally {
        isDeleting.value = false
    }
}

function openReceipt(url) {
    if (!url) return
    previewReceiptUrl.value = url
    showReceiptModal.value = true
}

function exportExcel() {
    const params = new URLSearchParams()
    if (activePreset.value) params.set('preset', activePreset.value)
    if (startDate.value) params.set('start_date', startDate.value)
    if (endDate.value) params.set('end_date', endDate.value)
    if (selectedCategoryId.value) params.set('category_id', selectedCategoryId.value)
    if (searchQuery.value) params.set('search', searchQuery.value.trim())

    window.location.href = `${route('pos.reports.expenses.export', { branch: branchSlug.value })}?${params.toString()}`
}

function getCategoryColor(index) {
    const colors = [
        'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
        'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800',
        'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800',
        'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800',
    ]
    return colors[index % colors.length]
}
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
        <!-- ── Page Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🧾</span>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        Operating Expenses
                    </h1>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Log and monitor branch operational expenditures, category allocations, and receipts.
                </p>
            </div>

            <div class="flex items-center flex-wrap gap-2.5">
                <button
                    type="button"
                    @click="exportExcel"
                    class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/70 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export Excel</span>
                </button>

                <button
                    v-if="perms.canLogExpense"
                    type="button"
                    @click="openCreateModal"
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs rounded-xl shadow-md shadow-amber-500/20 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Record Expense</span>
                </button>
            </div>
        </div>

        <!-- ── Summary KPI Cards ────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Expenses -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Expenses</span>
                <span class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400">
                    {{ format(summary?.total_amount || 0) }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">
                    {{ summary?.total_count || 0 }} logged {{ summary?.total_count === 1 ? 'entry' : 'entries' }}
                </span>
            </div>

            <!-- Transaction Count -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Expense Entries</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                    {{ summary?.total_count || 0 }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">In selected timeframe</span>
            </div>

            <!-- Top Expense Category -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Top Category</span>
                <span class="text-lg sm:text-xl font-bold text-amber-600 dark:text-amber-400 truncate block">
                    {{ summary?.top_category_name || 'N/A' }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">
                    {{ format(summary?.top_category_amount || 0) }}
                </span>
            </div>

            <!-- Daily Average -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Daily Average</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                    {{ format(summary?.daily_average || 0) }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">Average per day</span>
            </div>
        </div>

        <!-- ── Category Breakdown Card ──────────────────────────────────────────── -->
        <div v-if="summary?.category_breakdown && summary.category_breakdown.length > 0" class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
            <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3">
                Category Spending Breakdown
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div
                    v-for="(cat, idx) in summary.category_breakdown"
                    :key="cat.category_id"
                    :class="getCategoryColor(idx)"
                    class="p-3 rounded-xl border flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="text-xs font-bold truncate">{{ cat.category_name }}</span>
                            <span class="text-[11px] font-extrabold">{{ cat.percentage }}%</span>
                        </div>
                        <span class="text-sm font-black block">{{ format(cat.total_amount) }}</span>
                    </div>
                    <span class="text-[10px] opacity-75 mt-1">{{ cat.count }} {{ cat.count === 1 ? 'record' : 'records' }}</span>
                </div>
            </div>
        </div>

        <!-- ── Filter & Search Bar ──────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3">
            <!-- Preset Buttons -->
            <div class="flex items-center flex-wrap gap-2">
                <button
                    v-for="p in [
                        { key: 'today', label: 'Today' },
                        { key: 'yesterday', label: 'Yesterday' },
                        { key: 'week', label: 'This Week' },
                        { key: 'month', label: 'This Month' },
                        { key: 'custom', label: 'Custom Range' },
                    ]"
                    :key="p.key"
                    type="button"
                    @click="selectPreset(p.key)"
                    :class="activePreset === p.key
                        ? 'bg-amber-500 text-slate-950 font-bold'
                        : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 font-medium'"
                    class="px-3 py-1.5 text-xs rounded-xl transition cursor-pointer"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Custom Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search description, category, staff…"
                        @keyup.enter="applyFilter"
                        class="w-full px-3.5 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                    />
                </div>

                <!-- Category Filter -->
                <div>
                    <select
                        v-model="selectedCategoryId"
                        @change="applyFilter"
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- Start Date -->
                <div>
                    <input
                        v-model="startDate"
                        type="date"
                        :disabled="activePreset !== 'custom'"
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 disabled:opacity-50"
                    />
                </div>

                <!-- End Date & Apply -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="endDate"
                        type="date"
                        :disabled="activePreset !== 'custom'"
                        class="flex-1 px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 disabled:opacity-50"
                    />
                    <button
                        type="button"
                        @click="applyFilter"
                        class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition cursor-pointer"
                    >
                        Filter
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        title="Reset"
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Expenses Table ───────────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-800/60 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-4">Date</th>
                            <th class="py-3.5 px-4">Category</th>
                            <th class="py-3.5 px-4">Description</th>
                            <th class="py-3.5 px-4 text-right">Amount</th>
                            <th class="py-3.5 px-4">Logged By</th>
                            <th class="py-3.5 px-4 text-center">Receipt</th>
                            <th v-if="perms.canManageExpenses" class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                        <tr
                            v-for="expense in expenses.data"
                            :key="expense.id"
                            class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition"
                        >
                            <!-- Date -->
                            <td class="py-3 px-4 font-semibold text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                {{ dayjs(expense.expense_date).format('DD MMM YYYY') }}
                            </td>

                            <!-- Category -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600">
                                    {{ expense.category?.name || 'Uncategorized' }}
                                </span>
                            </td>

                            <!-- Description -->
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                                {{ expense.description || '—' }}
                            </td>

                            <!-- Amount -->
                            <td class="py-3 px-4 text-right font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                {{ format(expense.amount) }}
                            </td>

                            <!-- Logged By -->
                            <td class="py-3 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                {{ expense.user?.name || expense.user?.username || 'System' }}
                            </td>

                            <!-- Receipt Attachment -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <button
                                    v-if="expense.receipt_url"
                                    type="button"
                                    @click="openReceipt(expense.receipt_url)"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                                >
                                    <span>📎 View</span>
                                </button>
                                <span v-else class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                            </td>

                            <!-- Actions -->
                            <td v-if="perms.canManageExpenses" class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        @click="openEditModal(expense)"
                                        class="p-1.5 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
                                        title="Edit Expense"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmDelete(expense)"
                                        class="p-1.5 text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 transition rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
                                        title="Delete Expense"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!expenses.data || expenses.data.length === 0">
                            <td :colspan="perms.canManageExpenses ? 7 : 6" class="py-12 text-center text-slate-400">
                                <span class="text-3xl block mb-2">🧾</span>
                                <span class="text-sm font-bold block text-slate-600 dark:text-slate-300">No expenses recorded for this period</span>
                                <span class="text-xs text-slate-400 block mt-1">Adjust filters or record a new expense.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="expenses.links && expenses.links.length > 3" class="p-4 border-t border-slate-100 dark:border-slate-700">
                <PaginationLinks :links="expenses.links" />
            </div>
        </div>

        <!-- ── Reusable Expense Create / Edit Modal ─────────────────────────────── -->
        <ExpenseModal
            v-model="showExpenseModal"
            :expense="selectedExpense"
            :categories="categories"
            @saved="router.reload({ preserveScroll: true })"
        />

        <!-- ── Receipt Preview Modal ────────────────────────────────────────────── -->
        <div
            v-if="showReceiptModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-xs animate-fade-in"
        >
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 max-w-xl w-full border border-slate-200 dark:border-slate-700 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Receipt Attachment</h3>
                    <button
                        type="button"
                        @click="showReceiptModal = false"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex justify-center max-h-[70vh] overflow-auto">
                    <img
                        v-if="!previewReceiptUrl.endsWith('.pdf')"
                        :src="previewReceiptUrl"
                        alt="Receipt Preview"
                        class="max-h-[65vh] object-contain rounded-xl border border-slate-200 dark:border-slate-700"
                    />
                    <iframe
                        v-else
                        :src="previewReceiptUrl"
                        class="w-full h-96 rounded-xl border border-slate-200 dark:border-slate-700"
                    ></iframe>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a
                        :href="previewReceiptUrl"
                        target="_blank"
                        download
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download Original</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Delete Expense Confirmation Modal ────────────────────────────────── -->
        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fade-in"
        >
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 max-w-md w-full border border-slate-200 dark:border-slate-700 shadow-2xl space-y-4">
                <div class="flex items-center gap-3 text-rose-600">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center font-bold text-xl">
                        ⚠️
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Delete Expense Record?</h3>
                        <p class="text-xs text-slate-500">This action will be audited in revision logs.</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-300">
                    Are you sure you want to delete this expense of <strong>{{ format(expenseToDelete?.amount) }}</strong> ({{ expenseToDelete?.category?.name }})?
                </p>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Reason for Deletion
                    </label>
                    <input
                        v-model="deleteReason"
                        type="text"
                        placeholder="e.g. Duplicate entry logged in error"
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none"
                    />
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition shadow-md shadow-rose-600/20 disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Confirm Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
