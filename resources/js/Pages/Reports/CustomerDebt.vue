<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    customers:  Object,   // paginated
    total_debt: Number,
    filters:    Object,
})

const { format } = useCurrency()

const search    = ref(props.filters?.search ?? '')
const minDebt   = ref(props.filters?.min_debt ?? '')
const watchlist = ref(props.filters?.watchlist ?? '')

const exportMenuOpen = ref(false)
const exportDropdownRef = ref(null)

function applyFilters() {
    router.get(route('pos.reports.customer-debt'), {
        search:    search.value ? search.value.trim() : undefined,
        min_debt:  minDebt.value !== '' ? minDebt.value : undefined,
        watchlist: watchlist.value !== '' ? watchlist.value : undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    search.value    = ''
    minDebt.value   = ''
    watchlist.value = ''
    router.get(route('pos.reports.customer-debt'), {}, { preserveState: true, replace: true })
}

function getExportUrl(exportFormat = 'xlsx') {
    const params = new URLSearchParams()
    params.set('format', exportFormat)
    if (search.value) params.set('search', search.value.trim())
    if (minDebt.value !== '') params.set('min_debt', minDebt.value)
    if (watchlist.value !== '') params.set('watchlist', watchlist.value)
    return `${route('pos.reports.customer-debt.export')}?${params.toString()}`
}

function downloadExport(exportFormat) {
    exportMenuOpen.value = false
    window.location.href = getExportUrl(exportFormat)
}

function handleClickOutside(e) {
    if (exportDropdownRef.value && !exportDropdownRef.value.contains(e.target)) {
        exportMenuOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                            Customer Debts Report
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400 text-xs font-bold font-mono">
                            {{ customers.total }} customers
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Track outstanding receivables, watchlists, and debtor ledgers across customers.</p>
                </div>
            </div>

            <!-- Export Dropdown -->
            <div class="relative" ref="exportDropdownRef">
                <button
                    type="button"
                    @click="exportMenuOpen = !exportMenuOpen"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition inline-flex items-center gap-1.5 shadow-xs cursor-pointer"
                    title="Export customer debts"
                >
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Export</span>
                    <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Menu -->
                <div
                    v-if="exportMenuOpen"
                    class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                >
                    <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        Export Debtors List
                    </div>
                    <button
                        type="button"
                        @click="downloadExport('xlsx')"
                        class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
                    >
                        <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                            XLS
                        </span>
                        Excel (.xlsx)
                    </button>
                    <button
                        type="button"
                        @click="downloadExport('csv')"
                        class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-950/40 text-slate-700 dark:text-slate-200 hover:text-blue-700 dark:hover:text-blue-300 flex items-center gap-2 transition cursor-pointer font-medium"
                    >
                        <span class="w-5 h-5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-[10px]">
                            CSV
                        </span>
                        CSV Document (.csv)
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Toolbar -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 bg-slate-100/60 dark:bg-slate-800/50 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
            <div class="flex flex-wrap items-center gap-2.5 text-xs w-full sm:w-auto">
                <!-- Search -->
                <div class="relative">
                    <input
                        v-model="search"
                        @keydown.enter="applyFilters"
                        type="text"
                        placeholder="Search name, phone, email..."
                        class="w-52 sm:w-64 bg-white dark:bg-slate-700 text-slate-900 dark:text-white pl-8 pr-3 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-rose-500 transition"
                    />
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <!-- Watchlist Filter -->
                <div>
                    <select
                        v-model="watchlist"
                        @change="applyFilters"
                        class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-rose-500 transition"
                    >
                        <option value="">All Customers</option>
                        <option value="1">⚠️ Watchlist Only</option>
                        <option value="0">Standard Customers</option>
                    </select>
                </div>

                <!-- Min Debt -->
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Min Debt ₦:</span>
                    <input
                        v-model.number="minDebt"
                        @keydown.enter="applyFilters"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-24 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-rose-500 font-mono text-center transition"
                    />
                </div>

                <button @click="applyFilters" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-lg transition shadow-xs cursor-pointer">
                    Apply Filter
                </button>
                <button @click="resetFilters" class="px-2.5 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 rounded-lg transition cursor-pointer font-medium">
                    Reset
                </button>
            </div>

            <!-- Total Debt Banner -->
            <div class="flex items-center gap-2 bg-rose-500/10 border border-rose-500/20 px-3.5 py-1.5 rounded-xl">
                <span class="text-xs font-semibold text-rose-700 dark:text-rose-400">Total Outstanding:</span>
                <span class="text-sm font-black text-rose-600 dark:text-rose-400 font-mono">{{ format(total_debt) }}</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-left text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Customer Name</th>
                            <th class="px-6 py-3 font-semibold">Contact Details</th>
                            <th class="px-6 py-3 font-semibold text-center">Watchlist</th>
                            <th class="px-6 py-3 font-semibold text-right">Outstanding Debt</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="c in customers.data" :key="c.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-6 py-3 text-slate-900 dark:text-white font-medium">
                                {{ c.name }}
                            </td>
                            <td class="px-6 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                <p>{{ c.phone || '—' }}</p>
                                <p v-if="c.email" class="text-xs text-slate-500">{{ c.email }}</p>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <span v-if="c.watch_list" class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                    ⚠️ WATCHLIST
                                </span>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-6 py-3 font-bold text-rose-600 dark:text-rose-400 text-right text-base font-mono">
                                {{ format(c.debt_bal) }}
                            </td>
                            <td class="px-6 py-3 text-right">
                                <Link :href="route('pos.customers.debt-ledger', c.id)"
                                    class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition inline-flex shadow-xs cursor-pointer">
                                    Ledger
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!customers.data?.length">
                            <td colspan="5" class="px-6 py-16 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-sm">No customers found matching the search and debt criteria.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <PaginationLinks :links="customers.links" :meta="customers.meta ?? customers" />
            </div>
        </div>
    </div>
</template>

