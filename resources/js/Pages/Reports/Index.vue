<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import {
    Chart,
    BarController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
} from 'chart.js'

Chart.register(
    BarController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
)

defineOptions({ layout: PosLayout })

const props = defineProps({
    salesTrend:          Array,
    paymentDistribution: Object,
    topItems:            Array,
    returnsSummary:      Object,
    monthlyStats:        Object,
})

const { format } = useCurrency()

// Canvas Refs
const salesTrendCanvas = ref(null)
const paymentCanvas    = ref(null)
const topItemsCanvas   = ref(null)

let trendChart   = null
let paymentChart = null
let topChart     = null

function getThemeColors() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return { primary: '#7b00ff', secondary: '#fba43d', hover: '#6200cc', light: '#f2e6ff', text: '#5900b3' }
    }
    const computed   = getComputedStyle(document.documentElement)
    const primary   = computed.getPropertyValue('--color-theme').trim() || '#7b00ff'
    const secondary = computed.getPropertyValue('--color-theme-secondary').trim() || '#fba43d'
    const hover     = computed.getPropertyValue('--color-theme-hover').trim() || '#6200cc'
    const light     = computed.getPropertyValue('--color-theme-light').trim() || '#f2e6ff'
    const text      = computed.getPropertyValue('--color-theme-text').trim() || '#5900b3'

    return { primary, secondary, hover, light, text }
}

onMounted(async () => {
    await nextTick()
    renderSalesTrendChart()
    renderPaymentDistributionChart()
    renderTopItemsChart()
})

function renderSalesTrendChart() {
    if (!salesTrendCanvas.value) return
    const labels  = (props.salesTrend || []).map(d => d.day)
    const revenue = (props.salesTrend || []).map(d => d.revenue)
    const profit  = (props.salesTrend || []).map(d => d.profit)
    const theme   = getThemeColors()

    if (trendChart) trendChart.destroy()

    trendChart = new Chart(salesTrendCanvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Revenue (₦)',
                    data: revenue,
                    backgroundColor: theme.primary,
                    borderRadius: 6,
                },
                {
                    label: 'Gross Profit (₦)',
                    data: profit,
                    backgroundColor: theme.secondary,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { family: 'Poppins', size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ₦${Number(ctx.raw || 0).toLocaleString()}`
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    ticks: {
                        callback: (value) => '₦' + Number(value).toLocaleString()
                    }
                }
            }
        }
    })
}

function renderPaymentDistributionChart() {
    if (!paymentCanvas.value) return
    const dist   = props.paymentDistribution || {}
    const labels = Object.keys(dist)
    const data   = Object.values(dist)
    const theme  = getThemeColors()

    if (paymentChart) paymentChart.destroy()

    paymentChart = new Chart(paymentCanvas.value, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: [theme.primary, theme.secondary, '#ef4444'],
                borderWidth: 2,
                borderColor: '#ffffff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: 'Poppins', size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ₦${Number(ctx.raw || 0).toLocaleString()}`
                    }
                }
            }
        }
    })
}

function renderTopItemsChart() {
    if (!topItemsCanvas.value) return
    const items  = props.topItems || []
    const labels = items.map(i => i.item_name)
    const data   = items.map(i => Number(i.total_revenue || 0))
    const theme  = getThemeColors()

    if (topChart) topChart.destroy()

    topChart = new Chart(topItemsCanvas.value, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Revenue (₦)',
                data,
                backgroundColor: theme.primary,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `Revenue: ₦${Number(ctx.raw || 0).toLocaleString()}`
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: (value) => '₦' + Number(value).toLocaleString()
                    }
                },
                y: { grid: { display: false } }
            }
        }
    })
}

const reports = [
    {
        title: 'End of Day',
        description: "Summary of today's sales, payment modes, and register closeout.",
        route: 'pos.reports.end-of-day',
        iconClass: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    {
        title: 'Sales Report',
        description: 'View sales transactions for a specific day or date range.',
        route: 'pos.reports.daily-sales',
        iconClass: 'bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-400',
        icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
    },
    {
        title: 'Returned Items',
        description: 'Track returned items, quantities, units restocked, and total refund worth.',
        route: 'pos.reports.returns',
        iconClass: 'bg-purple-100 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400',
        icon: 'M16 15v-1a4 4 0 00-4-4H4m0 0l4-4m-4 4l4 4m6 4v1a3 3 0 003 3h3a3 3 0 003-3v-3a3 3 0 00-3-3h-3'
    },
    {
        title: 'Profit & Loss',
        description: 'Calculate gross profit based on items sold and their cost.',
        route: 'pos.reports.profit-loss',
        iconClass: 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400',
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
    },
    {
        title: 'Low Stock Alerts',
        description: 'Items that have fallen below their configured alert threshold.',
        route: 'pos.reports.low-stock',
        iconClass: 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400',
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
    },
    {
        title: 'Customer Debts',
        description: 'List of customers with outstanding balances.',
        route: 'pos.reports.customer-debt',
        iconClass: 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    {
        title: 'Audit & Activity Logs',
        description: 'Branch audit trail for sales, stock moves, staff actions, and errors.',
        route: 'pos.reports.logs',
        iconClass: 'bg-theme-light text-theme',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
    }
]
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Reports & Business Analytics</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Executive performance overview, charts, and detailed reports.</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- ── Monthly Metric Stat Cards ────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Monthly Revenue</p>
                        <p class="text-2xl font-bold text-theme font-mono">{{ format(monthlyStats?.monthly_revenue || 0) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-theme-light text-theme flex items-center justify-center font-bold text-lg">₦</div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Monthly Gross Profit</p>
                        <p class="text-2xl font-bold text-theme font-mono">{{ format(monthlyStats?.monthly_profit || 0) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-theme-light text-theme flex items-center justify-center font-bold text-lg">📈</div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Returns (This Month)</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono">{{ returnsSummary?.total_qty || 0 }} <span class="text-xs text-slate-500 font-normal">items</span></p>
                        <p class="text-[11px] text-red-500 font-medium font-mono mt-0.5">Worth: {{ format(returnsSummary?.refund_worth || 0) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-lg">↩</div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Outstanding Customer Debt</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 font-mono">{{ format(monthlyStats?.outstanding_debt || 0) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400 flex items-center justify-center font-bold text-lg">💳</div>
                </div>
            </div>

            <!-- ── Analytics Visualizations Grid (Charts) ───────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- 1. 7-Day Revenue & Profit Bar Chart -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white">7-Day Sales & Profit Performance</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Daily revenue and gross profit breakdown for the past week.</p>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas ref="salesTrendCanvas"></canvas>
                    </div>
                </div>

                <!-- 2. Payment Distribution Doughnut Chart -->
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs space-y-4">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Payment Method Breakdown</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Distribution of payments this month.</p>
                    </div>
                    <div class="h-64 relative flex items-center justify-center">
                        <canvas ref="paymentCanvas"></canvas>
                    </div>
                </div>

            </div>

            <!-- ── Top 5 Selling Products Bar Chart ─────────────────────────────── -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs space-y-4">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Top 5 Best-Selling Products (This Month)</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Products generating the highest total revenue.</p>
                </div>
                <div class="h-60 relative">
                    <canvas ref="topItemsCanvas"></canvas>
                </div>
            </div>

            <!-- ── Report Section Navigation Cards ──────────────────────────────── -->
            <div>
                <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-3">Detailed Reports Navigation</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <Link v-for="report in reports" :key="report.title" :href="route(report.route)"
                        class="group bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-700 rounded-xl p-5 transition flex items-start gap-3 shadow-xs">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-transform group-hover:scale-110" :class="report.iconClass">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="report.icon"/></svg>
                        </div>
                        <div>
                            <h4 class="text-slate-900 dark:text-white font-bold text-sm mb-0.5 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">{{ report.title }}</h4>
                            <p class="text-slate-500 dark:text-slate-400 text-xs leading-relaxed line-clamp-2">{{ report.description }}</p>
                        </div>
                    </Link>
                </div>
            </div>

        </div>
    </div>
</template>
