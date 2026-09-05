<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'
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
    periodStats:         Object,
    users:               Array,
    canSeeAll:           Boolean,
    filters:             Object,
})

const { format } = useCurrency()

// Filter State
const from   = ref(props.filters?.from || dayjs().startOf('month').format('YYYY-MM-DD'))
const to     = ref(props.filters?.to || dayjs().format('YYYY-MM-DD'))
const userId = ref(props.filters?.user_id || '')

// Active date preset detection
const activeDatePreset = computed(() => {
    if (!from.value && !to.value) return 'all'
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (from.value === today && to.value === today) return 'today'
    if (from.value === yesterday && to.value === yesterday) return 'yesterday'
    if (from.value === startOfWeek && to.value === today) return 'week'
    if (from.value === startOfMonth && to.value === today) return 'month'
    return 'custom'
})

// Active Filter Counter
const activeFilterCount = computed(() => {
    let count = 0
    if (activeDatePreset.value !== 'month') count++
    if (userId.value) count++
    return count
})

// Narrative description banner
const filterDescription = computed(() => {
    let timeText = 'for All Time'
    const today = dayjs().format('YYYY-MM-DD')
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
    const startOfWeek = dayjs().startOf('week').format('YYYY-MM-DD')
    const startOfMonth = dayjs().startOf('month').format('YYYY-MM-DD')

    if (from.value && to.value) {
        if (from.value === today && to.value === today) {
            timeText = `for Today (${dayjs().format('DD MMM YYYY')})`
        } else if (from.value === yesterday && to.value === yesterday) {
            timeText = `for Yesterday (${dayjs(yesterday).format('DD MMM YYYY')})`
        } else if (from.value === startOfWeek && to.value === today) {
            timeText = `for This Week (${dayjs(startOfWeek).format('DD MMM')} – ${dayjs().format('DD MMM YYYY')})`
        } else if (from.value === startOfMonth && to.value === today) {
            timeText = `for This Month (${dayjs().format('MMMM YYYY')})`
        } else if (from.value === to.value) {
            timeText = `for ${dayjs(from.value).format('DD MMM YYYY')}`
        } else {
            timeText = `from ${dayjs(from.value).format('DD MMM YYYY')} to ${dayjs(to.value).format('DD MMM YYYY')}`
        }
    } else if (from.value) {
        timeText = `from ${dayjs(from.value).format('DD MMM YYYY')} onwards`
    } else if (to.value) {
        timeText = `up to ${dayjs(to.value).format('DD MMM YYYY')}`
    }

    let userText = 'across all cashiers & staff'
    if (userId.value) {
        const foundUser = props.users?.find(u => String(u.id) === String(userId.value))
        const name = foundUser ? (foundUser.full_name || foundUser.name || foundUser.username) : 'Selected Staff'
        userText = `processed by ${name}`
    } else if (!props.canSeeAll) {
        userText = 'processed by You'
    }

    return {
        timeText,
        userText,
        salesCount: props.periodStats?.sales_count || 0,
    }
})

function applyPreset(preset) {
    const today = dayjs().format('YYYY-MM-DD')
    if (preset === 'today') {
        from.value = today
        to.value   = today
    } else if (preset === 'yesterday') {
        const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD')
        from.value = yesterday
        to.value   = yesterday
    } else if (preset === 'week') {
        from.value = dayjs().startOf('week').format('YYYY-MM-DD')
        to.value   = today
    } else if (preset === 'month') {
        from.value = dayjs().startOf('month').format('YYYY-MM-DD')
        to.value   = today
    } else if (preset === 'all') {
        from.value = ''
        to.value   = ''
    }
    doFilter()
}

function doFilter() {
    router.get(
        route('pos.reports.index'),
        {
            from:    from.value || undefined,
            to:      to.value || undefined,
            user_id: userId.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}

function resetFilters() {
    from.value   = dayjs().startOf('month').format('YYYY-MM-DD')
    to.value     = dayjs().format('YYYY-MM-DD')
    userId.value = ''
    doFilter()
}

// Canvas Refs
const salesTrendCanvas = ref(null)
const paymentCanvas    = ref(null)
const topItemsCanvas   = ref(null)

let trendChart   = null
let paymentChart = null
let topChart     = null

function getThemeColors() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return { primary: '#059669', secondary: '#10b981', hover: '#047857', light: '#ecfdf5', text: '#065f46' }
    }
    const computed = getComputedStyle(document.documentElement)
    const primary   = computed.getPropertyValue('--color-theme').trim() || '#059669'
    const secondary = computed.getPropertyValue('--color-theme-secondary').trim() || '#10b981'
    const hover     = computed.getPropertyValue('--color-theme-hover').trim() || '#047857'
    const light     = computed.getPropertyValue('--color-theme-light').trim() || '#ecfdf5'
    const text      = computed.getPropertyValue('--color-theme-text').trim() || '#065f46'

    return { primary, secondary, hover, light, text }
}

async function renderAllCharts() {
    await nextTick()
    renderSalesTrendChart()
    renderPaymentDistributionChart()
    renderTopItemsChart()
}

onMounted(() => {
    renderAllCharts()
})

watch(() => [props.salesTrend, props.paymentDistribution, props.topItems], () => {
    renderAllCharts()
}, { deep: true })

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
                legend: { position: 'top', labels: { font: { family: 'Poppins', size: 11 } } },
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
                legend: { position: 'bottom', labels: { font: { family: 'Poppins', size: 11 } } },
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
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Top Header -->
        <div class="flex items-center justify-between px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📊</span>
                    <span>Reports & Business Analytics</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Executive performance overview, trends, and charts.</p>
            </div>
        </div>

        <!-- ── Filter Toolbar (Matching Sales History Style) ────────────────── -->
        <div class="px-4 sm:px-6 py-2.5 bg-slate-100/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <div class="flex flex-col gap-2">
                <!-- Primary Row: Date Preset Pills + Action Buttons -->
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <!-- Date Preset Pills -->
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mr-1 hidden sm:inline">Period:</span>
                        <button
                            type="button"
                            @click="applyPreset('today')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'today'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('yesterday')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'yesterday'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            Yesterday
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('week')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'week'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            This Week
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('month')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'month'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            @click="applyPreset('all')"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition border cursor-pointer"
                            :class="activeDatePreset === 'all'
                                ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900 border-slate-800 shadow-xs'
                                : 'bg-white dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                        >
                            All Time
                        </button>
                    </div>

                    <!-- Apply & Reset Buttons -->
                    <div class="flex items-center gap-1.5 ml-auto">
                        <button
                            type="button"
                            @click="doFilter"
                            class="px-3 py-1 bg-slate-800 dark:bg-slate-200 text-white dark:text-slate-900 hover:bg-slate-700 dark:hover:bg-white text-xs font-bold rounded-lg transition shadow-xs cursor-pointer"
                        >
                            Apply
                        </button>
                        <button
                            v-if="activeFilterCount > 0"
                            type="button"
                            @click="resetFilters"
                            class="px-2.5 py-1 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 text-xs font-semibold rounded-lg transition cursor-pointer"
                            title="Reset all filters"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Secondary Row: Staff Selector + Custom Date Range Inputs -->
                <div class="flex flex-wrap items-center gap-3 pt-1.5 border-t border-slate-200/60 dark:border-slate-700/50">
                    <!-- Staff selector -->
                    <div v-if="canSeeAll" class="flex items-center gap-1.5">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Staff:</span>
                        <select
                            v-model="userId"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        >
                            <option value="">All Cashiers / Staff</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.full_name || u.name || u.username }}
                            </option>
                        </select>
                    </div>

                    <!-- Custom Range Date Pickers -->
                    <div class="flex items-center gap-1.5 ml-auto">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Custom Range:</span>
                        <input
                            v-model="from"
                            type="date"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        />
                        <span class="text-slate-400 text-xs">to</span>
                        <input
                            v-model="to"
                            type="date"
                            class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1 rounded-md text-xs border border-slate-300 dark:border-slate-600 outline-none"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Filter Results Narrative Description Banner ──────────────────── -->
        <div class="px-4 sm:px-6 py-2 bg-emerald-50/70 dark:bg-slate-900/90 border-b border-emerald-100 dark:border-slate-700/80 text-xs text-slate-700 dark:text-slate-300 flex flex-wrap items-center justify-between gap-2 flex-shrink-0">
            <div class="flex items-center gap-2 flex-1 min-w-[260px]">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 text-xs font-bold flex-shrink-0">
                    📈
                </span>
                <p class="leading-relaxed text-xs">
                    Analytics report <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ filterDescription.timeText }}</span>
                    &bull; <span class="font-semibold text-slate-900 dark:text-white">{{ filterDescription.userText }}</span>
                </p>
            </div>
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                <span>{{ filterDescription.salesCount }} orders recorded</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- ── Dynamic Metric Stat Cards ────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1 truncate">Period Revenue</p>
                        <p class="text-xl sm:text-2xl font-bold text-theme font-mono truncate" :title="format(periodStats?.revenue || 0)">
                            {{ format(periodStats?.revenue || 0) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-theme-light text-theme flex-shrink-0 flex items-center justify-center font-bold text-lg">₦</div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1 truncate">Period Gross Profit</p>
                        <p class="text-xl sm:text-2xl font-bold text-theme font-mono truncate" :title="format(periodStats?.profit || 0)">
                            {{ format(periodStats?.profit || 0) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-theme-light text-theme flex-shrink-0 flex items-center justify-center font-bold text-lg">📈</div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1 truncate">Returns in Period</p>
                        <p class="text-xl sm:text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono">{{ returnsSummary?.total_qty || 0 }} <span class="text-xs text-slate-500 font-normal">items</span></p>
                        <p class="text-[11px] text-red-500 font-medium font-mono mt-0.5 truncate" :title="format(returnsSummary?.refund_worth || 0)">Worth: {{ format(returnsSummary?.refund_worth || 0) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex-shrink-0 flex items-center justify-center font-bold text-lg">↩</div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1 truncate">Outstanding Customer Debt</p>
                        <p class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-400 font-mono truncate" :title="format(periodStats?.outstanding_debt || 0)">
                            {{ format(periodStats?.outstanding_debt || 0) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400 flex-shrink-0 flex items-center justify-center font-bold text-lg">💳</div>
                </div>
            </div>

            <!-- ── Analytics Visualizations Grid (Charts) ───────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- 1. Revenue & Profit Performance Bar Chart -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Revenue & Gross Profit Performance</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Periodic revenue and gross profit breakdown for the selected timeline.</p>
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
                        <p class="text-xs text-slate-500 dark:text-slate-400">Distribution of payments received during this period.</p>
                    </div>
                    <div class="h-64 relative flex items-center justify-center">
                        <canvas ref="paymentCanvas"></canvas>
                    </div>
                </div>

            </div>

            <!-- ── Top 5 Selling Products Bar Chart ─────────────────────────────── -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 shadow-xs space-y-4">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Top 5 Best-Selling Products</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Products generating the highest total sales volume during this period.</p>
                </div>
                <div class="h-60 relative">
                    <canvas ref="topItemsCanvas"></canvas>
                </div>
            </div>

        </div>
    </div>
</template>
