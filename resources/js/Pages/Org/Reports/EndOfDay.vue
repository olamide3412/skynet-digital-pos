<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import OrgLayout from '@/Layouts/OrgLayout.vue'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization: Object,
    branches: Array,
    filters: Object,
    reconciliation: Object,
    branch_breakdown: Array,
    cashier_stats: Array,
    hourly_distribution: Array,
    item_breakdown: Array,
    expenses_list: Array,
    sales: Object,
})

const selectedBranch = ref(props.filters.branch_id ? String(props.filters.branch_id) : '')
const selectedDate   = ref(props.filters.date || new Date().toISOString().split('T')[0])

const applyFilters = () => {
    router.get(route('org.reports.end-of-day'), {
        branch_id: selectedBranch.value || undefined,
        date: selectedDate.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const setDatePreset = (preset) => {
    const today = new Date().toISOString().split('T')[0]
    if (preset === 'today') {
        selectedDate.value = today
    } else if (preset === 'yesterday') {
        const d = new Date()
        d.setDate(d.getDate() - 1)
        selectedDate.value = d.toISOString().split('T')[0]
    }
    applyFilters()
}

const printZReport = () => {
    window.print()
}

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formatNumber = (val) => Number(val || 0).toLocaleString()

const branchNameLabel = computed(() => {
    if (!selectedBranch.value) return 'All Branches (Consolidated)'
    const b = props.branches.find(x => String(x.id) === String(selectedBranch.value))
    return b ? b.name : 'Selected Branch'
})
</script>

<template>
    <Head :title="`End of Day (Z-Report) - ${organization.name}`" />

    <div class="max-w-7xl mx-auto space-y-7">
        <!-- ── Top Header & Tab Navigation (Hidden on Print) ─────────────── -->
        <div class="print:hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Daily Close-out & Z-Report</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300">
                        Daily Reconciliation
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    End-of-day audit, cashier float balancing, cash drawer count, and branch revenue reconciliation.
                </p>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Tab: Switch to Analytics -->
                <Link
                    :href="route('org.reports', { branch_id: selectedBranch || undefined })"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>Analytics Dashboard</span>
                </Link>

                <!-- Active Z-Report Tab -->
                <span class="px-3.5 py-2 rounded-xl text-xs font-black bg-purple-600 text-white shadow-md shadow-purple-600/20 flex items-center gap-1.5 cursor-default">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>End of Day (Z-Report)</span>
                </span>

                <!-- Print Z-Report Button -->
                <button
                    @click="printZReport"
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Print Z-Report</span>
                </button>
            </div>
        </div>

        <!-- ── Printable Header (Only Visible on Print) ──────────────────── -->
        <div class="hidden print:block text-center border-b-2 border-slate-900 pb-4 mb-4">
            <h1 class="text-xl font-black uppercase tracking-wider">{{ organization.name }}</h1>
            <h2 class="text-sm font-bold uppercase tracking-widest text-slate-700">Official Daily Z-Report / End-of-Day Audit</h2>
            <div class="flex items-center justify-center gap-4 text-xs mt-2 font-mono">
                <span>Scope: <strong>{{ branchNameLabel }}</strong></span>
                <span>Date: <strong>{{ selectedDate }}</strong></span>
                <span>Generated: <strong>{{ new Date().toLocaleString() }}</strong></span>
            </div>
        </div>

        <!-- ── Filter & Date Controller (Hidden on Print) ────────────────── -->
        <div class="print:hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-black text-slate-800 dark:text-slate-200">
                        {{ branchNameLabel }} • {{ selectedDate }}
                    </span>
                    <span class="text-xs text-slate-400">
                        ({{ formatNumber(reconciliation.total_sales_count) }} sales)
                    </span>
                </div>

                <!-- Quick Date Switchers -->
                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl text-xs font-semibold">
                    <button
                        @click="setDatePreset('today')"
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-lg transition cursor-pointer',
                            selectedDate === new Date().toISOString().split('T')[0]
                                ? 'bg-white dark:bg-slate-700 font-black text-purple-700 dark:text-purple-300 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        Today
                    </button>
                    <button
                        @click="setDatePreset('yesterday')"
                        type="button"
                        class="px-3 py-1.5 rounded-lg transition cursor-pointer text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                    >
                        Yesterday
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <!-- Branch Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Branch Scope</label>
                    <select
                        v-model="selectedBranch"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition cursor-pointer"
                    >
                        <option value="">🏢 All Branches (Consolidated)</option>
                        <option v-for="b in branches" :key="b.id" :value="String(b.id)">
                            📍 {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Date Picker -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Reconciliation Date</label>
                    <input
                        v-model="selectedDate"
                        type="date"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition"
                    />
                </div>

                <!-- Apply Button -->
                <div class="flex items-end">
                    <button
                        @click="applyFilters"
                        type="button"
                        class="w-full py-2 px-3 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Update Audit Date</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Daily Cash Drawer Balancing Card (Hero Section) ──────────── -->
        <div class="bg-gradient-to-br from-slate-900 to-purple-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden border border-purple-800/40">
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pb-6 border-b border-purple-800/40">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-purple-300 bg-purple-900/60 px-3 py-1 rounded-full border border-purple-700/50">
                        Official Day Close Cash Balance
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-black mt-2 tracking-tight">
                        {{ formatCurrency(reconciliation.net_cash_position) }}
                    </h2>
                    <p class="text-xs text-purple-200/80 mt-1">
                        Expected Physical Cash in Register = (Cash Sales + Debt Recovered) − (Refunds + Cash Expenses).
                    </p>
                </div>

                <!-- Daily Takings Summary -->
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10 text-right min-w-[200px]">
                    <div class="text-[10px] font-bold uppercase text-purple-200">Total Net Takings</div>
                    <div class="text-xl font-black mt-0.5 text-emerald-400">
                        {{ formatCurrency(reconciliation.net_daily_takings) }}
                    </div>
                    <div class="text-[10px] text-purple-300 mt-1">
                        Gross Sales + Collections − Outflows
                    </div>
                </div>
            </div>

            <!-- Cash Flow Formula Strip -->
            <div class="relative z-10 grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 text-xs">
                <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block">1. Cash Sales</span>
                    <span class="text-base font-black text-emerald-400 block mt-0.5">{{ formatCurrency(reconciliation.cash_sales) }}</span>
                    <span class="text-[10px] text-slate-400">{{ formatNumber(reconciliation.total_sales_count) }} completed sales</span>
                </div>

                <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block">2. Debt Collections</span>
                    <span class="text-base font-black text-emerald-400 block mt-0.5">{{ formatCurrency(reconciliation.debt_recovered) }}</span>
                    <span class="text-[10px] text-slate-400">Recovered from debtors</span>
                </div>

                <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block">3. Returns & Refunds</span>
                    <span class="text-base font-black text-rose-400 block mt-0.5">{{ formatCurrency(reconciliation.returns_worth) }}</span>
                    <span class="text-[10px] text-slate-400">{{ reconciliation.returns_count }} items returned</span>
                </div>

                <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block">4. Store Expenses</span>
                    <span class="text-base font-black text-rose-400 block mt-0.5">{{ formatCurrency(reconciliation.expenses_total) }}</span>
                    <span class="text-[10px] text-slate-400">Logged operations cost</span>
                </div>
            </div>
        </div>

        <!-- ── Daily Financial Breakdown Grid ───────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Gross Sales Revenue</div>
                <div class="text-xl font-black text-slate-900 dark:text-white mt-1">{{ formatCurrency(reconciliation.total_sales_revenue) }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Discounts: {{ formatCurrency(reconciliation.total_discounts) }}</div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Bank Transfer / Card</div>
                <div class="text-xl font-black text-indigo-600 dark:text-indigo-400 mt-1">{{ formatCurrency(reconciliation.bank_transfer_sales) }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Digital payments collected</div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Unpaid Credit / Debt</div>
                <div class="text-xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ formatCurrency(reconciliation.debt_recorded) }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Credit sales issued today</div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Gross Profit Realized</div>
                <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ formatCurrency(reconciliation.gross_profit) }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Net profit: {{ formatCurrency(reconciliation.net_profit) }}</div>
            </div>
        </div>

        <!-- ── Branch-by-Branch Daily Breakdown Table ────────────────────── -->
        <div v-if="!selectedBranch && branch_breakdown.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white">Branch-by-Branch Daily Close-out</h2>
                    <p class="text-xs text-slate-500">Summary of today's sales, cash collections, and expenses for each branch.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-850 uppercase font-bold text-slate-500 dark:text-slate-400 border-y border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">Branch</th>
                            <th class="py-3 px-4 text-right">Orders</th>
                            <th class="py-3 px-4 text-right">Total Revenue</th>
                            <th class="py-3 px-4 text-right">Cash Collected</th>
                            <th class="py-3 px-4 text-right">Bank / Card</th>
                            <th class="py-3 px-4 text-right">Refunds</th>
                            <th class="py-3 px-4 text-right">Expenses</th>
                            <th class="py-3 px-4 text-right">Net Cash in Drawer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="b in branch_breakdown" :key="b.branch_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span>📍 {{ b.branch_name }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold">{{ formatNumber(b.sales_count) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-slate-900 dark:text-white">{{ formatCurrency(b.revenue) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(b.cash) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-indigo-600 dark:text-indigo-400">{{ formatCurrency(b.bank) }}</td>
                            <td class="py-3 px-4 text-right text-rose-500">{{ formatCurrency(b.returns) }}</td>
                            <td class="py-3 px-4 text-right text-rose-500">{{ formatCurrency(b.expenses) }}</td>
                            <td class="py-3 px-4 text-right font-black" :class="b.net_cash >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600'">
                                {{ formatCurrency(b.net_cash) }}
                            </td>
                        </tr>
                        <tr v-if="!branch_breakdown.length">
                            <td colspan="8" class="py-6 text-center text-slate-400">No branch activity recorded today.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Cashier Shift Breakdown & Items Sold ─────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Cashier / Staff Shift Performance -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col space-y-4">
                <div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white">Cashier Shift Summary</h2>
                    <p class="text-xs text-slate-500">Sales volume and cash collected per staff member today.</p>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase font-semibold border-b border-slate-100 dark:border-slate-800 pb-2">
                            <tr>
                                <th class="pb-2">Cashier</th>
                                <th class="pb-2 text-right">Orders</th>
                                <th class="pb-2 text-right">Cash</th>
                                <th class="pb-2 text-right">Bank / Card</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="c in cashier_stats" :key="c.user_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                                <td class="py-2.5 font-bold text-slate-800 dark:text-slate-200">
                                    <div>{{ c.name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ c.branch_name }}</div>
                                </td>
                                <td class="py-2.5 text-right font-semibold">{{ formatNumber(c.orders_count) }}</td>
                                <td class="py-2.5 text-right font-medium text-emerald-600 dark:text-emerald-400">{{ formatCurrency(c.cash) }}</td>
                                <td class="py-2.5 text-right font-medium text-indigo-600 dark:text-indigo-400">{{ formatCurrency(c.bank) }}</td>
                                <td class="py-2.5 text-right font-black text-slate-900 dark:text-white">{{ formatCurrency(c.total_sales) }}</td>
                            </tr>
                            <tr v-if="!cashier_stats.length">
                                <td colspan="5" class="py-6 text-center text-slate-400">No cashier shift records for this day.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Items Sold Today -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col space-y-4">
                <div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white">Daily Products Sold Breakdown</h2>
                    <p class="text-xs text-slate-500">Top selling inventory items sold today.</p>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase font-semibold border-b border-slate-100 dark:border-slate-800 pb-2">
                            <tr>
                                <th class="pb-2">Item Name</th>
                                <th class="pb-2 text-right">Qty Sold</th>
                                <th class="pb-2 text-right">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(it, idx) in item_breakdown" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                                <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200 truncate max-w-[200px]">
                                    {{ it.name }}
                                </td>
                                <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">
                                    {{ formatNumber(it.qty) }} {{ it.unit }}
                                </td>
                                <td class="py-2.5 text-right font-bold text-purple-600 dark:text-purple-400">
                                    {{ formatCurrency(it.total_amount) }}
                                </td>
                            </tr>
                            <tr v-if="!item_breakdown.length">
                                <td colspan="3" class="py-6 text-center text-slate-400">No items sold today.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Daily Expenses Audited ────────────────────────────────────── -->
        <div v-if="expenses_list.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
            <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white">Day's Operating Expenses Audited</h2>
                <p class="text-xs text-slate-500">Itemized disbursements and branch expenses logged today.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-850 uppercase font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Logged By</th>
                            <th class="px-4 py-3 text-right">Disbursed Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="exp in expenses_list" :key="exp.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                            <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ exp.branch_name }}</td>
                            <td class="px-4 py-3 font-semibold text-purple-600 dark:text-purple-400">{{ exp.category }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ exp.description }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ exp.logged_by }}</td>
                            <td class="px-4 py-3 text-right font-bold text-rose-600 dark:text-rose-400">{{ formatCurrency(exp.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Paginated Completed Sales for Day Close ───────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white">Day's Transaction Ledger</h2>
                    <p class="text-xs text-slate-500">All receipts processed during this day's trading hours.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-850 uppercase font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Receipt ID</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Cashier</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3 text-right">Cash</th>
                            <th class="px-4 py-3 text-right">Bank / Card</th>
                            <th class="px-4 py-3 text-right">Total Amount</th>
                            <th class="px-4 py-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in sales.data" :key="s.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                            <td class="px-4 py-3 font-mono font-bold text-purple-600 dark:text-purple-400">{{ s.receipt_number }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ s.branch_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ s.cashier_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ s.customer_name }}</td>
                            <td class="px-4 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">{{ formatCurrency(s.cash) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-indigo-600 dark:text-indigo-400">{{ formatCurrency(s.bank_transfer) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">{{ formatCurrency(s.final_total) }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ s.created_at }}</td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400">No transactions recorded for this day.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="sales.links?.length > 3" class="print:hidden flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
                <span class="text-slate-400">Page {{ sales.current_page }} of {{ sales.last_page }} ({{ formatNumber(sales.total) }} transactions)</span>
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in sales.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-bold transition',
                                link.active
                                    ? 'bg-purple-600 text-white shadow-xs'
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                            ]"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2 py-1.5 text-slate-400 cursor-not-allowed"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ── Sign-off Section for Physical Print Out (Print Only) ─────── -->
        <div class="hidden print:grid grid-cols-2 gap-8 pt-12 text-xs">
            <div class="border-t border-slate-900 pt-2 text-center">
                <p class="font-bold">Prepared By / Branch Supervisor</p>
                <p class="text-slate-500 mt-1">Signature & Date</p>
            </div>
            <div class="border-t border-slate-900 pt-2 text-center">
                <p class="font-bold">Audited & Verified By (Org Admin)</p>
                <p class="text-slate-500 mt-1">Signature & Date</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    body {
        background-color: white !important;
        color: black !important;
    }
}
</style>
