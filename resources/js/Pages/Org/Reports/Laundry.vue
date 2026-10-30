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
    organization:         Object,
    branches:             Array,
    has_laundry_branches: Boolean,
    filters:              Object,
    kpis:                 Object,
    status_breakdown:     Array,
    payment_methods:      Array,
    revenue_trend:        Array,
    branch_comparison:    Array,
    top_services:         Array,
    overdue_count:        Number,
    unclaimed_count:      Number,
    recent_orders:        Object,
})

// ── Filter Reactive State ───────────────────────────────────────────────────
const selectedBranch = ref(props.filters?.branch_id ? String(props.filters.branch_id) : '')
const fromDate       = ref(props.filters?.from || '')
const toDate         = ref(props.filters?.to || '')
const searchQuery    = ref(props.filters?.search || '')
const selectedStatus = ref(props.filters?.status || 'all')

// ── Currency & Number Formatting Helpers ─────────────────────────────────────
const formatCurrency = (val) => {
    const num = Number(val || 0)
    return '₦' + num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatNumber = (val) => {
    const num = Number(val || 0)
    return num.toLocaleString('en-NG')
}

// ── Filter Narrative Summary ────────────────────────────────────────────────
const filterSummaryText = computed(() => {
    let branchLabel = 'All Laundry Branches (Consolidated)'
    if (selectedBranch.value) {
        const found = props.branches?.find(b => String(b.id) === String(selectedBranch.value))
        if (found) branchLabel = `Branch: ${found.name}`
    }

    let dateLabel = ''
    if (props.filters?.preset === 'today') dateLabel = 'Today'
    else if (props.filters?.preset === 'yesterday') dateLabel = 'Yesterday'
    else if (props.filters?.preset === 'week') dateLabel = 'This Week'
    else if (props.filters?.preset === 'month') dateLabel = 'This Month'
    else if (props.filters?.preset === 'last_month') dateLabel = 'Last Month'
    else if (props.filters?.preset === 'all') dateLabel = 'All Time'
    else if (fromDate.value && toDate.value) dateLabel = `${fromDate.value} to ${toDate.value}`
    else dateLabel = 'Custom Period'

    return `${branchLabel} • ${dateLabel}`
})

// ── Action Handlers ──────────────────────────────────────────────────────────
const applyFilters = () => {
    router.get(route('org.reports.laundry'), {
        branch_id: selectedBranch.value || undefined,
        from:      fromDate.value || undefined,
        to:        toDate.value || undefined,
        preset:    undefined,
        search:    searchQuery.value || undefined,
        status:    selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const setPreset = (preset) => {
    router.get(route('org.reports.laundry'), {
        branch_id: selectedBranch.value || undefined,
        preset:    preset,
        search:    searchQuery.value || undefined,
        status:    selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const filterByStatus = (st) => {
    selectedStatus.value = st
    applyFilters()
}

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams()
    if (selectedBranch.value) params.set('branch_id', selectedBranch.value)
    if (props.filters?.preset) params.set('preset', props.filters.preset)
    if (fromDate.value) params.set('from', fromDate.value)
    if (toDate.value) params.set('to', toDate.value)
    if (searchQuery.value) params.set('search', searchQuery.value)
    if (selectedStatus.value && selectedStatus.value !== 'all') params.set('status', selectedStatus.value)

    return route('org.reports.laundry.export') + (params.toString() ? '?' + params.toString() : '')
})

// ── Chart.js References & Lifecycle ──────────────────────────────────────────
const trendCanvas      = ref(null)
const branchCompCanvas = ref(null)
const paymentCanvas    = ref(null)
const statusCanvas     = ref(null)

let trendChartInstance      = null
let branchCompChartInstance = null
let paymentChartInstance    = null
let statusChartInstance     = null

const destroyCharts = () => {
    if (trendChartInstance)      { trendChartInstance.destroy(); trendChartInstance = null }
    if (branchCompChartInstance) { branchCompChartInstance.destroy(); branchCompChartInstance = null }
    if (paymentChartInstance)    { paymentChartInstance.destroy(); paymentChartInstance = null }
    if (statusChartInstance)     { statusChartInstance.destroy(); statusChartInstance = null }
}

const initCharts = () => {
    destroyCharts()

    // 1. Revenue & Order Volume Trend (Combined Line / Bar Chart)
    if (trendCanvas.value && props.revenue_trend?.length > 0) {
        const labels      = props.revenue_trend.map(t => t.label)
        const collections = props.revenue_trend.map(t => t.collections)
        const ordersCount = props.revenue_trend.map(t => t.orders_count)

        trendChartInstance = new Chart(trendCanvas.value, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Net Collections (₦)',
                        data: collections,
                        borderColor: '#7c3aed', // Purple 600
                        backgroundColor: 'rgba(124, 58, 237, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: '#7c3aed',
                        yAxisID: 'y',
                    },
                    {
                        label: 'Orders Intake',
                        data: ordersCount,
                        borderColor: '#10b981', // Emerald 500
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.25,
                        pointRadius: 3,
                        pointBackgroundColor: '#10b981',
                        yAxisID: 'y1',
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
                        labels: { font: { family: 'inherit', size: 11, weight: '600' }, usePointStyle: true, boxWidth: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                if (ctx.datasetIndex === 0) {
                                    return `Collections: ₦${Number(ctx.raw || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`
                                }
                                return `Intake: ${ctx.raw} orders`
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                        ticks: {
                            font: { size: 10 },
                            callback: (v) => '₦' + (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v))
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { font: { size: 10 }, precision: 0 }
                    }
                }
            }
        })
    }

    // 2. Branch Performance Comparison (When viewing multiple branches)
    if (branchCompCanvas.value && props.branch_comparison?.length > 1 && !selectedBranch.value) {
        const labels      = props.branch_comparison.map(b => b.branch_name)
        const collections = props.branch_comparison.map(b => b.net_collections)
        const ordersValue = props.branch_comparison.map(b => b.orders_value)

        branchCompChartInstance = new Chart(branchCompCanvas.value, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Net Collections (₦)',
                        data: collections,
                        backgroundColor: '#7c3aed',
                        borderRadius: 6,
                        maxBarThickness: 30,
                    },
                    {
                        label: 'Intake Value (₦)',
                        data: ordersValue,
                        backgroundColor: '#38bdf8', // Sky 400
                        borderRadius: 6,
                        maxBarThickness: 30,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'inherit', size: 11, weight: '600' }, usePointStyle: true, boxWidth: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ₦${Number(ctx.raw || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
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

    // 3. Payment Methods Doughnut Chart
    if (paymentCanvas.value && props.payment_methods?.length > 0) {
        const labels = props.payment_methods.map(p => p.method)
        const data   = props.payment_methods.map(p => p.amount)
        const colors = ['#7c3aed', '#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#64748b']

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

    // 4. Garment Pipeline Status Breakdown (Bar Chart)
    if (statusCanvas.value && props.status_breakdown?.length > 0) {
        const labels = props.status_breakdown.map(s => s.label)
        const counts = props.status_breakdown.map(s => s.count)
        const colors = [
            '#3b82f6', // Received - Blue
            '#f59e0b', // In Progress - Amber
            '#8b5cf6', // Ready - Purple
            '#10b981', // Collected - Emerald
            '#ef4444', // Cancelled - Red
        ]

        statusChartInstance = new Chart(statusCanvas.value, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Orders Count',
                    data: counts,
                    backgroundColor: colors.slice(0, labels.length),
                    borderRadius: 6,
                    maxBarThickness: 36,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.raw} orders in this stage`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: {
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                        ticks: { font: { size: 10 }, precision: 0 }
                    }
                }
            }
        })
    }
}

onMounted(() => {
    nextTick(() => initCharts())
})

watch(() => [props.revenue_trend, props.branch_comparison, props.payment_methods, props.status_breakdown], () => {
    nextTick(() => initCharts())
}, { deep: true })

onBeforeUnmount(() => {
    destroyCharts()
})
</script>

<template>
    <Head :title="`Laundry Reports & Analytics - ${organization.name}`" />

    <div class="max-w-7xl mx-auto space-y-7">
        <!-- ── Top Header & Tab Navigation ───────────────────────────────── -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>🧺</span>
                        <span>Organization Laundry Reports</span>
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300">
                        Multi-Branch
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Multi-branch laundry performance, stage pipeline health, cash collections, and customer debt ledger for {{ organization.name }}.
                </p>
            </div>

            <!-- Top Action Nav & Export -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Tab: Switch to Sales Analytics -->
                <Link
                    :href="route('org.reports', { branch_id: selectedBranch || undefined })"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>Sales & POS Reports</span>
                </Link>

                <!-- Tab: End of Day (Z-Report) -->
                <Link
                    :href="route('org.reports.end-of-day', { branch_id: selectedBranch || undefined })"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>End of Day (Z-Report)</span>
                </Link>

                <!-- Active Laundry Reports Tab -->
                <span class="px-3.5 py-2 rounded-xl text-xs font-black bg-purple-600 text-white shadow-md shadow-purple-600/20 flex items-center gap-1.5 cursor-default">
                    <span>🧺</span>
                    <span>Laundry Reports</span>
                </span>

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

        <!-- ── Zero Laundry Branches Onboarding Notice ───────────────────── -->
        <div v-if="!has_laundry_branches" class="p-8 rounded-3xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-center space-y-4">
            <div class="text-4xl">🧺</div>
            <h2 class="text-lg font-black text-slate-900 dark:text-white">No Laundry Operations Enabled Yet</h2>
            <p class="text-xs text-slate-600 dark:text-slate-400 max-w-lg mx-auto leading-relaxed">
                None of your organization's branches currently have the Laundry module activated. You can enable Laundry or Mixed Store mode for any branch under Branch Settings to begin tracking garment intake, pickup ticketing, and operations analytics.
            </p>
            <div>
                <Link
                    :href="route('org.branches')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md transition"
                >
                    <span>🏢</span>
                    <span>Manage My Branches</span>
                </Link>
            </div>
        </div>

        <template v-else>
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
                            ({{ formatNumber(kpis.total_orders) }} orders intake)
                        </span>
                    </div>

                    <!-- Date Range Preset Buttons -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl text-xs font-semibold overflow-x-auto max-w-full">
                        <button
                            v-for="p in [
                                { id: 'today',      label: 'Today' },
                                { id: 'yesterday',  label: 'Yesterday' },
                                { id: 'week',       label: 'This Week' },
                                { id: 'month',      label: 'This Month' },
                                { id: 'last_month', label: 'Last Month' },
                                { id: 'all',        label: 'All Time' },
                            ]"
                            :key="p.id"
                            @click="setPreset(p.id)"
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition whitespace-nowrap cursor-pointer',
                                filters.preset === p.id
                                    ? 'bg-white dark:bg-slate-700 font-black text-purple-700 dark:text-purple-300 shadow-xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <!-- Branch Selector -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Branch Scope</label>
                        <select
                            v-model="selectedBranch"
                            @change="applyFilters"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition cursor-pointer"
                        >
                            <option value="">All Branches (Consolidated View)</option>
                            <option v-for="b in branches" :key="b.id" :value="String(b.id)">
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                        <input
                            v-model="fromDate"
                            type="date"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                        <input
                            v-model="toDate"
                            type="date"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition"
                        />
                    </div>

                    <!-- Search Input & Apply -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Order / Customer</label>
                            <input
                                v-model="searchQuery"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="Order # or Customer..."
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white outline-none focus:border-purple-500 transition placeholder:text-slate-400"
                            />
                        </div>
                        <button
                            @click="applyFilters"
                            type="button"
                            class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition cursor-pointer shadow-md shadow-purple-600/20"
                        >
                            Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Executive KPI Cards ───────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Net Collections -->
                <div class="bg-gradient-to-br from-emerald-500 to-teal-700 rounded-3xl p-5 text-white shadow-lg shadow-emerald-500/15 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-wider text-emerald-100">Realized Collections</span>
                        <span class="p-2 rounded-xl bg-white/20 text-white text-xs">💰</span>
                    </div>
                    <div class="text-2xl font-black mt-2 font-mono">{{ formatCurrency(kpis.net_collections) }}</div>
                    <div class="text-xs text-emerald-100/90 mt-2 flex items-center justify-between pt-2 border-t border-white/20">
                        <span>Gross: {{ formatCurrency(kpis.gross_collections) }}</span>
                        <span v-if="kpis.refunds_total > 0" class="text-rose-200">(-{{ formatCurrency(kpis.refunds_total) }} refunds)</span>
                        <span v-else>0 refunds</span>
                    </div>
                </div>

                <!-- 2. Order Intake -->
                <div class="bg-gradient-to-br from-purple-600 to-indigo-800 rounded-3xl p-5 text-white shadow-lg shadow-purple-600/15 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-wider text-purple-100">Orders Intake Volume</span>
                        <span class="p-2 rounded-xl bg-white/20 text-white text-xs">🧺</span>
                    </div>
                    <div class="text-2xl font-black mt-2 font-mono">{{ formatNumber(kpis.total_orders) }} <span class="text-xs font-sans font-semibold">orders</span></div>
                    <div class="text-xs text-purple-100/90 mt-2 flex items-center justify-between pt-2 border-t border-white/20">
                        <span>Value: {{ formatCurrency(kpis.total_orders_value) }}</span>
                        <span>{{ formatNumber(kpis.total_items_count) }} items</span>
                    </div>
                </div>

                <!-- 3. Outstanding Receivables / Unpaid Balances -->
                <div class="bg-gradient-to-br from-amber-500 to-orange-700 rounded-3xl p-5 text-white shadow-lg shadow-amber-500/15 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-wider text-amber-100">Unpaid Debt (Receivables)</span>
                        <span class="p-2 rounded-xl bg-white/20 text-white text-xs">🧾</span>
                    </div>
                    <div class="text-2xl font-black mt-2 font-mono">{{ formatCurrency(kpis.outstanding_debt) }}</div>
                    <div class="text-xs text-amber-100/90 mt-2 flex items-center justify-between pt-2 border-t border-white/20">
                        <span>Awaiting payment on pickup tickets</span>
                    </div>
                </div>

                <!-- 4. Active Production Pipeline -->
                <div class="bg-gradient-to-br from-blue-600 to-cyan-800 rounded-3xl p-5 text-white shadow-lg shadow-blue-600/15 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-wider text-blue-100">Production Pipeline</span>
                        <span class="p-2 rounded-xl bg-white/20 text-white text-xs">⚙️</span>
                    </div>
                    <div class="text-2xl font-black mt-2 font-mono">{{ formatNumber(kpis.active_orders_count) }} <span class="text-xs font-sans font-semibold">active</span></div>
                    <div class="text-xs text-blue-100/90 mt-2 flex items-center justify-between pt-2 border-t border-white/20">
                        <span>Ready: <strong>{{ kpis.ready_orders_count }}</strong></span>
                        <span>Handover: <strong>{{ kpis.completed_pickups_count }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- ── Operational Alerts Strip (Overdue & Unclaimed) ───────────── -->
            <div v-if="overdue_count > 0 || unclaimed_count > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-if="overdue_count > 0" class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 flex items-center justify-between gap-3 text-xs text-rose-800 dark:text-rose-200">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg">⏰</span>
                        <div>
                            <div class="font-black text-rose-900 dark:text-rose-100">{{ overdue_count }} Overdue Order(s)</div>
                            <div class="text-[11px] text-rose-600 dark:text-rose-400">Past estimated delivery due date without ready status.</div>
                        </div>
                    </div>
                    <button @click="filterByStatus('in_progress')" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white font-bold text-[11px] hover:bg-rose-500 transition cursor-pointer shrink-0">
                        Inspect
                    </button>
                </div>

                <div v-if="unclaimed_count > 0" class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-between gap-3 text-xs text-amber-800 dark:text-amber-200">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg">📦</span>
                        <div>
                            <div class="font-black text-amber-900 dark:text-amber-100">{{ unclaimed_count }} Unclaimed Order(s)</div>
                            <div class="text-[11px] text-amber-600 dark:text-amber-400">Marked ready for 14+ days without customer pickup.</div>
                        </div>
                    </div>
                    <button @click="filterByStatus('ready')" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold text-[11px] hover:bg-amber-500 transition cursor-pointer shrink-0">
                        Inspect
                    </button>
                </div>
            </div>

            <!-- ── Visual Analytics (Charts Grid) ────────────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Chart 1: Daily Revenue & Intake Trend -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 class="font-black text-slate-900 dark:text-white text-sm">Collections & Order Intake Trend</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Daily cash collections and intake volume over the selected period</p>
                            </div>
                            <span class="text-lg">📈</span>
                        </div>
                        <div class="h-64 mt-4 relative">
                            <canvas ref="trendCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Chart 2: Branch Comparison (if Consolidated) OR Pipeline Status -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 class="font-black text-slate-900 dark:text-white text-sm">
                                    {{ !selectedBranch && branch_comparison?.length > 1 ? 'Branch Performance Comparison' : 'Garment Pipeline Status Distribution' }}
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ !selectedBranch && branch_comparison?.length > 1 ? 'Comparing collections and order value across branches' : 'Active orders across all production lifecycle stages' }}
                                </p>
                            </div>
                            <span class="text-lg">{{ !selectedBranch && branch_comparison?.length > 1 ? '🏢' : '🔄' }}</span>
                        </div>
                        <div class="h-64 mt-4 relative">
                            <canvas v-if="!selectedBranch && branch_comparison?.length > 1" ref="branchCompCanvas"></canvas>
                            <canvas v-else ref="statusCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Chart 3: Payment Method Distribution -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 class="font-black text-slate-900 dark:text-white text-sm">Payment Methods Breakdown</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Share of cash, card/POS, and bank transfers received</p>
                            </div>
                            <span class="text-lg">💳</span>
                        </div>
                        <div class="h-64 mt-4 relative flex items-center justify-center">
                            <canvas v-if="payment_methods?.length" ref="paymentCanvas"></canvas>
                            <div v-else class="text-xs text-slate-400 font-semibold">No payments recorded in this period</div>
                        </div>
                    </div>
                </div>

                <!-- Chart 4: Pipeline Stages (when Branch Comparison is shown above) OR Payment Method Summary List -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 class="font-black text-slate-900 dark:text-white text-sm">
                                    {{ !selectedBranch && branch_comparison?.length > 1 ? 'Garment Pipeline Stages' : 'Top Services Breakdown' }}
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ !selectedBranch && branch_comparison?.length > 1 ? 'Current count of orders across lifecycle stages' : 'Highest grossing laundry service offerings' }}
                                </p>
                            </div>
                            <span class="text-lg">🧺</span>
                        </div>
                        <div v-if="!selectedBranch && branch_comparison?.length > 1" class="h-64 mt-4 relative">
                            <canvas ref="statusCanvas"></canvas>
                        </div>
                        <div v-else class="mt-4 space-y-3">
                            <div v-for="srv in top_services.slice(0, 5)" :key="srv.service_name" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 text-xs">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ srv.service_name }}</div>
                                <div class="flex items-center gap-3">
                                    <span class="text-slate-400">{{ srv.total_qty }} items</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(srv.total_revenue) }}</span>
                                </div>
                            </div>
                            <div v-if="!top_services?.length" class="text-center py-10 text-xs text-slate-400">
                                No service breakdown records available.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Multi-Branch Comparison Table (Consolidated View) ───────────── -->
            <div v-if="!selectedBranch && branch_comparison?.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-sm">Multi-Branch Laundry Performance Matrix</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Comparative intake, revenue collections, and outstanding customer debt per location</p>
                    </div>
                    <span class="text-xs font-bold text-purple-600 dark:text-purple-400">{{ branch_comparison.length }} Laundry Branches</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 text-[10px]">
                                <th class="pb-3 font-bold">Branch Name</th>
                                <th class="pb-3 font-bold text-center">Orders Intake</th>
                                <th class="pb-3 font-bold text-right">Intake Value</th>
                                <th class="pb-3 font-bold text-right">Net Collections</th>
                                <th class="pb-3 font-bold text-right">Unpaid Debt</th>
                                <th class="pb-3 font-bold text-center">Active Pipeline</th>
                                <th class="pb-3 font-bold text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="b in branch_comparison" :key="b.branch_id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>🏪</span>
                                    <span>{{ b.branch_name }}</span>
                                </td>
                                <td class="py-3 text-center font-mono font-semibold">{{ formatNumber(b.orders_count) }}</td>
                                <td class="py-3 text-right font-mono text-slate-600 dark:text-slate-300">{{ formatCurrency(b.orders_value) }}</td>
                                <td class="py-3 text-right font-mono font-black text-emerald-600 dark:text-emerald-400">{{ formatCurrency(b.net_collections) }}</td>
                                <td class="py-3 text-right font-mono font-semibold" :class="b.outstanding_debt > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">
                                    {{ formatCurrency(b.outstanding_debt) }}
                                </td>
                                <td class="py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                        {{ b.active_orders }} active
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    <button
                                        @click="selectedBranch = String(b.branch_id); applyFilters()"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-purple-50 dark:bg-purple-950/50 hover:bg-purple-100 text-purple-700 dark:text-purple-300 transition cursor-pointer"
                                    >
                                        Filter
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Top Services Table ────────────────────────────────────────── -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-sm">Top Performing Laundry Services & Garment Types</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Most requested laundry items by volume and gross revenue generated</p>
                    </div>
                    <span class="text-lg">🏷️</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="srv in top_services" :key="srv.service_name" class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-extrabold text-xs text-slate-800 dark:text-slate-200 truncate">{{ srv.service_name }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">{{ srv.total_qty }} pieces processed</div>
                        </div>
                        <div class="font-mono font-black text-xs text-emerald-600 dark:text-emerald-400 shrink-0">
                            {{ formatCurrency(srv.total_revenue) }}
                        </div>
                    </div>
                </div>
                <div v-if="!top_services?.length" class="text-center py-6 text-xs text-slate-400 font-medium">
                    No individual service line records available for the selected period.
                </div>
            </div>

            <!-- ── Recent Orders Ledger & Search ────────────────────────────── -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-sm">Recent Laundry Orders Stream</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Detailed customer order ledger, balance reconciliations, and garment pickup tickets</p>
                    </div>

                    <!-- Pipeline Stage Filters Pills -->
                    <div class="flex items-center gap-1 overflow-x-auto max-w-full p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs font-semibold">
                        <button
                            v-for="st in [
                                { id: 'all',         label: 'All' },
                                { id: 'received',    label: 'Received' },
                                { id: 'in_progress', label: 'In Progress' },
                                { id: 'ready',       label: 'Ready' },
                                { id: 'collected',   label: 'Collected' },
                                { id: 'cancelled',   label: 'Cancelled' },
                            ]"
                            :key="st.id"
                            @click="filterByStatus(st.id)"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg transition whitespace-nowrap cursor-pointer text-[11px]',
                                selectedStatus === st.id
                                    ? 'bg-purple-600 text-white font-black shadow-xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            {{ st.label }}
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 text-[10px]">
                                <th class="pb-3 font-bold">Order #</th>
                                <th class="pb-3 font-bold">Branch</th>
                                <th class="pb-3 font-bold">Customer</th>
                                <th class="pb-3 font-bold text-center">Status</th>
                                <th class="pb-3 font-bold">Intake Date</th>
                                <th class="pb-3 font-bold">Due Date</th>
                                <th class="pb-3 font-bold text-right">Total</th>
                                <th class="pb-3 font-bold text-right">Paid</th>
                                <th class="pb-3 font-bold text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="order in recent_orders.data" :key="order.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 font-mono font-black text-purple-700 dark:text-purple-400">
                                    {{ order.order_number }}
                                </td>
                                <td class="py-3 font-bold text-slate-800 dark:text-slate-200">
                                    {{ order.branch?.name || 'Main' }}
                                </td>
                                <td class="py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ order.customer_name || 'Walk-in' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ order.customer_phone || '-' }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300': order.status === 'received',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': order.status === 'in_progress',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300': order.status === 'ready',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': order.status === 'collected',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': order.status === 'cancelled',
                                        }"
                                    >
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="py-3 text-slate-500 font-mono text-[11px]">
                                    {{ order.received_at ? new Date(order.received_at).toLocaleDateString() : '-' }}
                                </td>
                                <td class="py-3 text-slate-500 font-mono text-[11px]">
                                    {{ order.due_at ? new Date(order.due_at).toLocaleDateString() : '-' }}
                                </td>
                                <td class="py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ formatCurrency(order.total) }}
                                </td>
                                <td class="py-3 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(order.amount_paid) }}
                                </td>
                                <td class="py-3 text-right font-mono font-bold" :class="Number(order.balance) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'">
                                    {{ formatCurrency(order.balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!recent_orders.data?.length" class="text-center py-8 text-xs text-slate-400 font-medium">
                    No orders matching the current filter criteria found.
                </div>

                <!-- Pagination Links -->
                <div v-if="recent_orders.links?.length > 3" class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
                    <span class="text-slate-400">Showing page {{ recent_orders.current_page }} of {{ recent_orders.last_page }} ({{ formatNumber(recent_orders.total) }} total records)</span>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in recent_orders.links" :key="i">
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
                                class="px-3 py-1.5 rounded-lg text-slate-400 opacity-50 cursor-not-allowed"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

