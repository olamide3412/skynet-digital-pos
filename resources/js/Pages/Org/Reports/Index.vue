<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue'
import OrgLayout from '@/Layouts/OrgLayout.vue'
import {
    Chart,
    BarController,
    DoughnutController,
    LineController,
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
} from 'chart.js'

Chart.register(
    BarController,
    DoughnutController,
    LineController,
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
)

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization: Object,
    branches: Array,
    filters: Object,
    kpis: Object,
    payments_breakdown: Array,
    expenses_breakdown: Array,
    sales_trend: Array,
    peak_hours: Array,
    branch_comparison: Array,
    top_items: Array,
    sales: Object,
})

// Filter reactive state
const selectedBranch = ref(props.filters.branch_id ? String(props.filters.branch_id) : '')
const fromDate       = ref(props.filters.from || '')
const toDate         = ref(props.filters.to || '')
const searchQuery    = ref(props.filters.search || '')

// Active preset calculation
const activePreset = computed(() => {
    const today = new Date().toISOString().split('T')[0]
    const yesterdayDate = new Date()
    yesterdayDate.setDate(yesterdayDate.getDate() - 1)
    const yesterday = yesterdayDate.toISOString().split('T')[0]

    const curr = new Date()
    const day = curr.getDay()
    const diffToMonday = curr.getDate() - day + (day === 0 ? -6 : 1)
    const monday = new Date(new Date().setDate(diffToMonday)).toISOString().split('T')[0]

    const firstOfMonth = new Date(curr.getFullYear(), curr.getMonth(), 1).toISOString().split('T')[0]

    if (fromDate.value === today && toDate.value === today) return 'today'
    if (fromDate.value === yesterday && toDate.value === yesterday) return 'yesterday'
    if (fromDate.value === monday && toDate.value === today) return 'week'
    if (fromDate.value === firstOfMonth && toDate.value === today) return 'month'
    return 'custom'
})

// Narrative context banner
const filterSummaryText = computed(() => {
    let branchLabel = 'All Branches (Consolidated)'
    if (selectedBranch.value) {
        const found = props.branches.find(b => String(b.id) === String(selectedBranch.value))
        if (found) branchLabel = `Branch: ${found.name}`
    }

    let dateLabel = ''
    if (activePreset.value === 'today') dateLabel = 'Today'
    else if (activePreset.value === 'yesterday') dateLabel = 'Yesterday'
    else if (activePreset.value === 'week') dateLabel = 'This Week'
    else if (activePreset.value === 'month') dateLabel = 'This Month'
    else if (fromDate.value && toDate.value) dateLabel = `${fromDate.value} to ${toDate.value}`
    else dateLabel = 'Custom Range'

    return `${branchLabel} • ${dateLabel}`
})

const applyFilters = () => {
    router.get(route('org.reports'), {
        branch_id: selectedBranch.value || undefined,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const setPreset = (preset) => {
    const today = new Date().toISOString().split('T')[0]
    if (preset === 'today') {
        fromDate.value = today
        toDate.value   = today
    } else if (preset === 'yesterday') {
        const d = new Date()
        d.setDate(d.getDate() - 1)
        const y = d.toISOString().split('T')[0]
        fromDate.value = y
        toDate.value   = y
    } else if (preset === 'week') {
        const d = new Date()
        const day = d.getDay()
        const diff = d.getDate() - day + (day === 0 ? -6 : 1)
        const monday = new Date(d.setDate(diff)).toISOString().split('T')[0]
        fromDate.value = monday
        toDate.value   = today
    } else if (preset === 'month') {
        const d = new Date()
        const firstDay = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
        fromDate.value = firstDay
        toDate.value   = today
    } else if (preset === '30_days') {
        const d = new Date()
        d.setDate(d.getDate() - 29)
        fromDate.value = d.toISOString().split('T')[0]
        toDate.value   = today
    }
    applyFilters()
}

const resetFilters = () => {
    selectedBranch.value = ''
    searchQuery.value    = ''
    setPreset('month')
}

const exportCsvUrl = computed(() => {
    return route('org.reports.export', {
        branch_id: selectedBranch.value || undefined,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
    })
})

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formatNumber = (val) => Number(val || 0).toLocaleString()

// ── Chart.js Instances & Canvas Refs ──────────────────────────────────────────
const trendCanvas      = ref(null)
const branchCompCanvas = ref(null)
const paymentCanvas    = ref(null)
const peakHoursCanvas  = ref(null)

let trendChartInstance       = null
let branchCompChartInstance  = null
let paymentChartInstance     = null
let peakHoursChartInstance   = null

const destroyCharts = () => {
    if (trendChartInstance) { trendChartInstance.destroy(); trendChartInstance = null }
    if (branchCompChartInstance) { branchCompChartInstance.destroy(); branchCompChartInstance = null }
    if (paymentChartInstance) { paymentChartInstance.destroy(); paymentChartInstance = null }
    if (peakHoursChartInstance) { peakHoursChartInstance.destroy(); peakHoursChartInstance = null }
}

const renderCharts = async () => {
    await nextTick()

    // 1. Sales & Profit Trend Chart
    if (trendCanvas.value && props.sales_trend?.length) {
        if (trendChartInstance) trendChartInstance.destroy()

        const labels  = props.sales_trend.map(t => t.label)
        const revenue = props.sales_trend.map(t => t.revenue)
        const profit  = props.sales_trend.map(t => t.profit)

        trendChartInstance = new Chart(trendCanvas.value, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Gross Revenue (₦)',
                        data: revenue,
                        backgroundColor: '#7c3aed', // Purple 600
                        hoverBackgroundColor: '#6d28d9',
                        borderRadius: 6,
                        maxBarThickness: 32,
                    },
                    {
                        label: 'Gross Profit (₦)',
                        data: profit,
                        backgroundColor: '#10b981', // Emerald 500
                        hoverBackgroundColor: '#059669',
                        borderRadius: 6,
                        maxBarThickness: 32,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'inherit', size: 11, weight: '600' },
                            usePointStyle: true,
                            boxWidth: 8,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ₦${Number(ctx.raw || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                        ticks: {
                            font: { size: 10 },
                            callback: (v) => '₦' + (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v))
                        }
                    }
                }
            }
        })
    }

    // 2. Branch Performance Comparison Chart (When viewing multiple branches)
    if (branchCompCanvas.value && props.branch_comparison?.length > 1 && !selectedBranch.value) {
        if (branchCompChartInstance) branchCompChartInstance.destroy()

        const labels   = props.branch_comparison.map(b => b.branch_name)
        const revenues = props.branch_comparison.map(b => b.revenue)
        const profits  = props.branch_comparison.map(b => b.gross_profit)

        branchCompChartInstance = new Chart(branchCompCanvas.value, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Revenue (₦)',
                        data: revenues,
                        backgroundColor: '#6366f1', // Indigo 500
                        borderRadius: 6,
                        maxBarThickness: 36,
                    },
                    {
                        label: 'Profit (₦)',
                        data: profits,
                        backgroundColor: '#059669', // Emerald 600
                        borderRadius: 6,
                        maxBarThickness: 36,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'inherit', size: 11, weight: '600' }, usePointStyle: true }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ₦${Number(ctx.raw || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                        ticks: {
                            callback: (v) => '₦' + (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v))
                        }
                    }
                }
            }
        })
    }

    // 3. Payment Distribution Doughnut Chart
    if (paymentCanvas.value && props.payments_breakdown?.length) {
        if (paymentChartInstance) paymentChartInstance.destroy()

        const labels = props.payments_breakdown.map(p => p.method)
        const data   = props.payments_breakdown.map(p => p.amount)
        const colors = ['#7c3aed', '#059669', '#3b82f6', '#f59e0b', '#ec4899', '#64748b']

        paymentChartInstance = new Chart(paymentCanvas.value, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'inherit', size: 11, weight: '600' }, boxWidth: 10, padding: 12 }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ₦${Number(ctx.raw || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                cutout: '68%'
            }
        })
    }

    // 4. Peak Trading Hours Histogram
    if (peakHoursCanvas.value && props.peak_hours?.length) {
        if (peakHoursChartInstance) peakHoursChartInstance.destroy()

        const hoursLabels = props.peak_hours.map(h => h.hour)
        const ordersData  = props.peak_hours.map(h => h.orders)

        peakHoursChartInstance = new Chart(peakHoursCanvas.value, {
            type: 'line',
            data: {
                labels: hoursLabels,
                datasets: [{
                    label: 'Orders Count',
                    data: ordersData,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#7c3aed',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.raw} orders processed`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                        ticks: { stepSize: 1 }
                    }
                }
            }
        })
    }
}

onMounted(() => {
    renderCharts()
})

watch(() => [props.sales_trend, props.branch_comparison, props.payments_breakdown, props.peak_hours, selectedBranch.value], () => {
    renderCharts()
}, { deep: true })

onBeforeUnmount(() => {
    destroyCharts()
})
</script>

<template>
    <Head :title="`Analytics & Reports - ${organization.name}`" />

    <div class="max-w-7xl mx-auto space-y-7">
        <!-- ── Top Header & Tab Navigation ───────────────────────────────── -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Organization Analytics & Reports</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300">
                        Live Data
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Visual graphical insights, multi-branch performance metrics, and financial reporting for {{ organization.name }}.
                </p>
            </div>

            <!-- Top Action Nav & Export -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Tab: Performance & Analytics (Active) -->
                <span class="px-3.5 py-2 rounded-xl text-xs font-black bg-purple-600 text-white shadow-md shadow-purple-600/20 flex items-center gap-1.5 cursor-default">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>Analytics Dashboard</span>
                </span>

                <!-- Tab: End of Day (Z-Report) -->
                <Link
                    :href="route('org.reports.end-of-day', { branch_id: selectedBranch || undefined })"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>End of Day (Z-Report)</span>
                </Link>

                <!-- Export CSV Button -->
                <a
                    :href="exportCsvUrl"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-800 transition flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </div>

        <!-- ── Filter & Scope Controller ─────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                <!-- Narrative summary badge -->
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-500 animate-pulse"></span>
                    <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200">
                        {{ filterSummaryText }}
                    </span>
                    <span class="text-xs text-slate-400">
                        ({{ formatNumber(kpis.total_orders) }} transactions)
                    </span>
                </div>

                <!-- Date Range Preset Buttons -->
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl text-xs font-semibold overflow-x-auto max-w-full">
                    <button
                        v-for="p in [
                            { id: 'today', label: 'Today' },
                            { id: 'yesterday', label: 'Yesterday' },
                            { id: 'week', label: 'This Week' },
                            { id: 'month', label: 'This Month' },
                            { id: '30_days', label: 'Last 30 Days' }
                        ]"
                        :key="p.id"
                        @click="setPreset(p.id)"
                        type="button"
                        :class="[
                            'px-3 py-1.5 rounded-lg transition cursor-pointer whitespace-nowrap',
                            activePreset === p.id
                                ? 'bg-white dark:bg-slate-700 font-black text-purple-700 dark:text-purple-300 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        {{ p.label }}
                    </button>
                </div>
            </div>

            <!-- Granular Scope Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <!-- Branch Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                        Filter by Branch
                    </label>
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

                <!-- From Date -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                    <input
                        v-model="fromDate"
                        type="date"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition"
                    />
                </div>

                <!-- To Date -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                    <input
                        v-model="toDate"
                        type="date"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition"
                    />
                </div>

                <!-- Actions: Apply & Reset -->
                <div class="flex items-end gap-2">
                    <button
                        @click="applyFilters"
                        type="button"
                        class="flex-1 py-2 px-3 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span>Apply</span>
                    </button>
                    <button
                        v-if="selectedBranch || activePreset !== 'month'"
                        @click="resetFilters"
                        type="button"
                        class="py-2 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer"
                        title="Reset all filters"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Executive P&L Financial Cards (KPIs) ─────────────────────── -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Revenue -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <span>Total Revenue</span>
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-1.5">
                    {{ formatCurrency(kpis.total_revenue) }}
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span>{{ formatNumber(kpis.total_orders) }} orders</span>
                    <span>Avg {{ formatCurrency(kpis.avg_basket_size) }}</span>
                </div>
            </div>

            <!-- Gross Profit -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <span>Gross Profit</span>
                    <span class="text-[10px] font-black px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                        {{ kpis.gross_margin_pct }}% Margin
                    </span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1.5">
                    {{ formatCurrency(kpis.total_gross_profit) }}
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span>Sales margin</span>
                    <span class="text-slate-400">Discounts: {{ formatCurrency(kpis.total_discounts) }}</span>
                </div>
            </div>

            <!-- Operating Expenses -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <span>Store Expenses</span>
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 mt-1.5">
                    {{ formatCurrency(kpis.total_expenses) }}
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span>Branch overheads</span>
                    <span class="text-slate-400">Returns: {{ formatCurrency(kpis.returns_worth) }}</span>
                </div>
            </div>

            <!-- Net Profit -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs relative overflow-hidden">
                <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <span>Net Operating Profit</span>
                    <span :class="[
                        'text-[10px] font-black px-1.5 py-0.5 rounded',
                        kpis.net_profit >= 0 ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-rose-50 text-rose-600'
                    ]">
                        {{ kpis.net_margin_pct }}% Net
                    </span>
                </div>
                <div :class="[
                    'text-xl sm:text-2xl font-black mt-1.5',
                    kpis.net_profit >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-600'
                ]">
                    {{ formatCurrency(kpis.net_profit) }}
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 mt-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span>Gross Profit − Expenses</span>
                    <span class="font-bold text-slate-700 dark:text-slate-300">Clean Takings</span>
                </div>
            </div>
        </div>

        <!-- ── Primary Graphical Visualizations ─────────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Visual Chart 1: Revenue & Profit Trend -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white">Revenue & Profit Progression</h2>
                        <p class="text-xs text-slate-500">Real-time comparison of sales vs gross margin generated over time.</p>
                    </div>
                    <span class="text-[11px] font-semibold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 rounded-lg">
                        {{ activePreset === 'today' ? 'Hourly View' : 'Daily View' }}
                    </span>
                </div>
                <div class="relative flex-1 min-h-[300px]">
                    <canvas ref="trendCanvas"></canvas>
                    <div v-if="!sales_trend?.length" class="absolute inset-0 flex items-center justify-center text-xs text-slate-400">
                        No transaction activity recorded for this period.
                    </div>
                </div>
            </div>

            <!-- Visual Chart 2: Payment Method Breakdown -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white">Payment Method Distribution</h2>
                        <p class="text-xs text-slate-500">Tender split across transactions.</p>
                    </div>
                </div>
                <div class="relative flex-1 min-h-[220px]">
                    <canvas ref="paymentCanvas"></canvas>
                    <div v-if="!payments_breakdown?.length" class="absolute inset-0 flex items-center justify-center text-xs text-slate-400">
                        No payment records found.
                    </div>
                </div>
                <!-- Payment Breakdown Mini Table -->
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                    <div v-for="pm in payments_breakdown" :key="pm.method" class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600 dark:text-slate-400 capitalize">{{ pm.method }}</span>
                        <div class="text-right">
                            <span class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(pm.amount) }}</span>
                            <span class="text-[10px] text-slate-400 ml-1.5">({{ pm.share_pct }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Multi-Branch Comparative Analytics & Leaderboard ─────────── -->
        <div v-if="!selectedBranch && branch_comparison.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">Branch Performance Comparison & Leaderboard</h2>
                    <p class="text-xs text-slate-500">Benchmark all branches within {{ organization.name }} side-by-side.</p>
                </div>
                <span class="text-xs font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-xl">
                    {{ branch_comparison.length }} Active Branches
                </span>
            </div>

            <!-- Comparison Bar Chart -->
            <div v-if="branch_comparison.length > 1" class="relative h-64 w-full">
                <canvas ref="branchCompCanvas"></canvas>
            </div>

            <!-- Branch Comparative Leaderboard Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-850 text-slate-500 dark:text-slate-400 font-bold uppercase border-y border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">Rank & Branch</th>
                            <th class="py-3 px-4 text-right">Orders</th>
                            <th class="py-3 px-4 text-right">Gross Revenue</th>
                            <th class="py-3 px-4 text-right">Gross Profit</th>
                            <th class="py-3 px-4 text-right">Expenses</th>
                            <th class="py-3 px-4 text-right">Net Profit</th>
                            <th class="py-3 px-4 text-right">Org Share</th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="(b, idx) in branch_comparison"
                            :key="b.branch_id"
                            class="hover:bg-slate-50/70 dark:hover:bg-slate-850/50 transition"
                        >
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span :class="[
                                    'w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-black flex-shrink-0',
                                    idx === 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' :
                                    idx === 1 ? 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' :
                                    idx === 2 ? 'bg-amber-700/20 text-amber-800 dark:text-amber-400' :
                                    'bg-slate-100 dark:bg-slate-800 text-slate-500'
                                ]">
                                    {{ idx + 1 }}
                                </span>
                                <div>
                                    <span class="block text-slate-900 dark:text-white font-bold">{{ b.branch_name }}</span>
                                    <span class="block text-[10px] text-slate-400">Avg Basket: {{ formatCurrency(b.avg_basket) }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300">{{ formatNumber(b.orders) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-slate-900 dark:text-white">{{ formatCurrency(b.revenue) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(b.gross_profit) }}</td>
                            <td class="py-3 px-4 text-right font-medium text-rose-500">{{ formatCurrency(b.expenses) }}</td>
                            <td class="py-3 px-4 text-right font-bold" :class="b.net_profit >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-600'">
                                {{ formatCurrency(b.net_profit) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-16 h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                        <div class="h-full bg-purple-600 rounded-full" :style="{ width: `${b.share_pct}%` }"></div>
                                    </div>
                                    <span class="font-bold text-slate-700 dark:text-slate-300 w-10 text-right">{{ b.share_pct }}%</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button
                                    @click="selectedBranch = String(b.branch_id); applyFilters()"
                                    class="text-[11px] font-bold text-purple-600 dark:text-purple-400 hover:underline cursor-pointer"
                                >
                                    Filter Branch
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Peak Trading Hours & Top Selling Items ───────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Peak Trading Hours Analysis -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white">Peak Trading Customer Traffic</h2>
                        <p class="text-xs text-slate-500">Transaction volume by hour of day (8:00 AM – 10:00 PM).</p>
                    </div>
                </div>
                <div class="relative flex-1 min-h-[220px]">
                    <canvas ref="peakHoursCanvas"></canvas>
                </div>
            </div>

            <!-- Top 10 Selling Products -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white">Top 10 Best Selling Items</h2>
                        <p class="text-xs text-slate-500">Highest grossing inventory items in selected scope.</p>
                    </div>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase font-semibold border-b border-slate-100 dark:border-slate-800 pb-2">
                            <tr>
                                <th class="pb-2">Product</th>
                                <th class="pb-2 text-right">Units</th>
                                <th class="pb-2 text-right">Gross Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(item, idx) in top_items" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                                <td class="py-2.5 font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-md bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-bold text-[10px] flex items-center justify-center flex-shrink-0">
                                        {{ idx + 1 }}
                                    </span>
                                    <span class="truncate max-w-[200px]">{{ item.name }}</span>
                                </td>
                                <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ formatNumber(item.qty_sold) }}</td>
                                <td class="py-2.5 text-right font-bold text-purple-600 dark:text-purple-400">{{ formatCurrency(item.revenue) }}</td>
                            </tr>
                            <tr v-if="!top_items.length">
                                <td colspan="3" class="py-6 text-center text-slate-400">No items sold during this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Detailed Filtered Sales Log ──────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white">Transaction Log & Audit Records</h2>
                    <p class="text-xs text-slate-500">Live feed of completed receipts and invoices across branches.</p>
                </div>
                <!-- Search within sales -->
                <div class="w-full sm:w-64">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="applyFilters"
                        placeholder="Search receipt, cashier, customer..."
                        type="text"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"
                    />
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
                            <th class="px-4 py-3 text-right">Total Amount</th>
                            <th class="px-4 py-3 text-right">Profit</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                            <td class="px-4 py-3 font-mono font-bold text-purple-600 dark:text-purple-400">{{ sale.receipt_number }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ sale.branch_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ sale.cashier_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ sale.customer_name }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">{{ formatCurrency(sale.final_total) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(sale.profit_made) }}</td>
                            <td class="px-4 py-3">
                                <span :class="[
                                    'px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border',
                                    sale.is_debt
                                        ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
                                ]">
                                    {{ sale.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ sale.created_at }}</td>
                        </tr>
                        <tr v-if="!sales.data.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                                No sales transactions match the current filter criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div v-if="sales.links?.length > 3" class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
                <span class="text-slate-400">Showing page {{ sales.current_page }} of {{ sales.last_page }} ({{ formatNumber(sales.total) }} total records)</span>
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
    </div>
</template>
