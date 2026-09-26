<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    totalExpenses: Number,
    totalCount: Number,
    branchBreakdown: Array,
    categoryBreakdown: Array,
    branches: Array,
    categories: Array,
    filters: Object,
})

const { format } = useCurrency()

const activePreset = ref(props.filters?.preset || 'month')
const startDate = ref(props.filters?.start_date || '')
const endDate = ref(props.filters?.end_date || '')
const selectedBranchId = ref(props.filters?.branch_id || '')
const selectedCategoryId = ref(props.filters?.category_id || '')

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
        route('superadmin.reports.expenses'),
        {
            preset: activePreset.value,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
            branch_id: selectedBranchId.value || undefined,
            category_id: selectedCategoryId.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}

function resetFilters() {
    activePreset.value = 'month'
    selectedBranchId.value = ''
    selectedCategoryId.value = ''
    selectPreset('month')
}

function exportExcel() {
    const params = new URLSearchParams()
    if (activePreset.value) params.set('preset', activePreset.value)
    if (startDate.value) params.set('start_date', startDate.value)
    if (endDate.value) params.set('end_date', endDate.value)
    if (selectedBranchId.value) params.set('branch_id', selectedBranchId.value)
    if (selectedCategoryId.value) params.set('category_id', selectedCategoryId.value)

    window.location.href = `${route('superadmin.reports.expenses.export')}?${params.toString()}`
}
</script>

<template>
    <div class="p-6 max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                    <span>🏢</span>
                    <span>Cross-Branch Expense Comparison</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Compare operating costs, spending patterns, and category allocations across all branches.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    @click="exportExcel"
                    class="px-4 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/70 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export Excel</span>
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Company Total Expenses</span>
                <span class="text-2xl font-black text-rose-600 dark:text-rose-400">
                    {{ format(totalExpenses) }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">
                    {{ totalCount }} logged entries across all branches
                </span>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Active Branches</span>
                <span class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ branchBreakdown.length }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">Operating branch locations</span>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Top Corporate Category</span>
                <span class="text-xl font-bold text-amber-600 dark:text-amber-400 truncate block">
                    {{ categoryBreakdown[0]?.category_name || 'N/A' }}
                </span>
                <span class="text-[11px] text-slate-400 block mt-1">
                    {{ format(categoryBreakdown[0]?.total_amount || 0) }} ({{ categoryBreakdown[0]?.percentage || 0 }}%)
                </span>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3">
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
                        ? 'bg-indigo-600 text-white font-bold'
                        : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 font-medium'"
                    class="px-3 py-1.5 text-xs rounded-xl transition cursor-pointer"
                >
                    {{ p.label }}
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                <!-- Branch Filter -->
                <div>
                    <select
                        v-model="selectedBranchId"
                        @change="applyFilter"
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none"
                    >
                        <option value="">All Branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <select
                        v-model="selectedCategoryId"
                        @change="applyFilter"
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none"
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
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none disabled:opacity-50"
                    />
                </div>

                <!-- End Date & Actions -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="endDate"
                        type="date"
                        :disabled="activePreset !== 'custom'"
                        class="flex-1 px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none disabled:opacity-50"
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
                        class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
                        title="Reset"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Branch Comparison Breakdown -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="p-5 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    Branch Operating Cost Leaderboard
                </h3>
                <p class="text-xs text-slate-400">Ranked by total expense expenditure during selected period.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700 text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/60 dark:bg-slate-800/40">
                            <th class="py-3 px-4">Branch</th>
                            <th class="py-3 px-4">Share of Total</th>
                            <th class="py-3 px-4 text-center">Entries</th>
                            <th class="py-3 px-4 text-right">Total Expenses</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                        <tr
                            v-for="b in branchBreakdown"
                            :key="b.id"
                            class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition"
                        >
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                {{ b.name }}
                            </td>
                            <td class="py-3 px-4 w-48">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-rose-500 rounded-full"
                                            :style="{ width: `${b.percentage}%` }"
                                        ></div>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-500 w-10 text-right">{{ b.percentage }}%</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center text-slate-500">
                                {{ b.count }}
                            </td>
                            <td class="py-3 px-4 text-right font-black text-rose-600 dark:text-rose-400">
                                {{ format(b.total_amount) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a
                                    :href="`/${b.slug}/reports/expenses`"
                                    target="_blank"
                                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1"
                                >
                                    <span>Branch Report</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </td>
                        </tr>

                        <tr v-if="!branchBreakdown.length">
                            <td colspan="5" class="py-8 text-center text-slate-400">
                                No branch expense data found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
