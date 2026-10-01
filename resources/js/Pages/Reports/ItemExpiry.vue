<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    items:      Object, // paginated items
    categories: Array,
    stats:      Object,
    filters:    Object,
    settings:   Object,
})

const page = usePage()
const { format } = useCurrency()

const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')

// Filter State
const search      = ref(props.filters?.search || '')
const categoryId  = ref(props.filters?.category_id || '')
const activeStatus= ref(props.filters?.status || 'all')
const activePreset= ref(props.filters?.preset || 'all')
const expiryFrom  = ref(props.filters?.expiry_from || '')
const expiryTo    = ref(props.filters?.expiry_to || '')

function applyFilters() {
    router.get(
        route('pos.reports.item-expiry', { branch: branchSlug.value }),
        {
            search:      search.value ? search.value.trim() : undefined,
            category_id: categoryId.value || undefined,
            status:      activeStatus.value !== 'all' ? activeStatus.value : undefined,
            preset:      activePreset.value !== 'all' ? activePreset.value : undefined,
            expiry_from: expiryFrom.value || undefined,
            expiry_to:   expiryTo.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}

function setStatusTab(status) {
    activeStatus.value = status
    if (status !== 'all') {
        activePreset.value = 'all'
        expiryFrom.value = ''
        expiryTo.value = ''
    }
    applyFilters()
}

function selectPreset(preset) {
    activePreset.value = preset
    activeStatus.value = 'all'
    const today = dayjs().format('YYYY-MM-DD')

    if (preset === 'next_7') {
        expiryFrom.value = today
        expiryTo.value = dayjs().add(7, 'day').format('YYYY-MM-DD')
    } else if (preset === 'next_30') {
        expiryFrom.value = today
        expiryTo.value = dayjs().add(30, 'day').format('YYYY-MM-DD')
    } else if (preset === 'next_60') {
        expiryFrom.value = today
        expiryTo.value = dayjs().add(60, 'day').format('YYYY-MM-DD')
    } else if (preset === 'next_90') {
        expiryFrom.value = today
        expiryTo.value = dayjs().add(90, 'day').format('YYYY-MM-DD')
    } else if (preset === 'expired') {
        expiryFrom.value = ''
        expiryTo.value = today
    } else {
        expiryFrom.value = ''
        expiryTo.value = ''
    }
    applyFilters()
}

function resetFilters() {
    search.value       = ''
    categoryId.value   = ''
    activeStatus.value = 'all'
    activePreset.value = 'all'
    expiryFrom.value   = ''
    expiryTo.value     = ''
    router.get(route('pos.reports.item-expiry', { branch: branchSlug.value }), {}, { preserveState: true, replace: true })
}

function exportReport() {
    const params = new URLSearchParams()
    if (search.value) params.set('search', search.value.trim())
    if (categoryId.value) params.set('category_id', categoryId.value)
    if (activeStatus.value !== 'all') params.set('status', activeStatus.value)
    if (activePreset.value !== 'all') params.set('preset', activePreset.value)
    if (expiryFrom.value) params.set('expiry_from', expiryFrom.value)
    if (expiryTo.value) params.set('expiry_to', expiryTo.value)

    window.location.href = `${route('pos.reports.item-expiry.export', { branch: branchSlug.value })}?${params.toString()}`
}

function formatDaysBadge(days) {
    if (days < 0) {
        return {
            label: `Expired (${Math.abs(days)}d ago)`,
            class: 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800'
        }
    }
    if (days <= (props.stats?.alert_days || 30)) {
        return {
            label: days === 0 ? 'Expires Today' : `${days} days left`,
            class: 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800'
        }
    }
    return {
        label: `${days} days left`,
        class: 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('pos.reports.index')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 text-sm">
                                📅
                            </span>
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Item Expiry Report</h1>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                Product Catalog
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Monitor product shelf life, detect expired items, and minimize inventory spoilage across items.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Switch to Batch Expiry Report if batch tracking is active -->
                    <Link
                        v-if="settings?.batch_tracking_enabled"
                        :href="route('pos.reports.batch-expiry', { branch: branchSlug })"
                        class="px-3 py-1.5 bg-violet-50 dark:bg-violet-950/40 hover:bg-violet-100 dark:hover:bg-violet-900/60 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/60 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition"
                    >
                        <span>🏷️ Switch to Batch Expiry Report</span>
                    </Link>

                    <button
                        @click="exportReport"
                        class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow-xs cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Export Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4.5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Tracked Items</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ stats?.total_tracked || 0 }}</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">With configured expiry date</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg font-bold">
                        📦
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4.5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Expiring Soon (≤ {{ stats?.alert_days || 30 }}d)</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ stats?.expiring_soon_count || 0 }}</p>
                        <p class="text-[11px] text-amber-600/80 dark:text-amber-400/80 mt-0.5 font-medium">In stock and needs attention</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg font-bold">
                        ⏳
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4.5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Expired Products</p>
                        <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ stats?.expired_count || 0 }}</p>
                        <p class="text-[11px] text-rose-600/80 dark:text-rose-400/80 mt-0.5 font-medium">Past expiration date</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center text-lg font-bold">
                        ⚠️
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4.5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Stock Value at Risk</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono mt-1">{{ format(stats?.value_at_risk || 0) }}</p>
                        <p class="text-[11px] text-purple-600/80 dark:text-purple-400/80 mt-0.5 font-medium">Cost of expiring + expired stock</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg font-bold font-mono">
                        ₦
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 shadow-xs space-y-3">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    
                    <!-- Search input -->
                    <div class="relative flex-1 min-w-[240px]">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Search by product name or barcode..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                        />
                    </div>

                    <!-- Category filter -->
                    <div class="min-w-[180px]">
                        <select
                            v-model="categoryId"
                            @change="applyFilters"
                            class="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                        >
                            <option value="">All Categories</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name || c.category_name }}</option>
                        </select>
                    </div>

                    <!-- Date Range Inputs -->
                    <div class="flex items-center gap-2 text-xs flex-wrap">
                        <input
                            v-model="expiryFrom"
                            @change="applyFilters"
                            type="date"
                            placeholder="From"
                            class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 text-xs focus:outline-none focus:border-amber-500"
                        />
                        <span class="text-slate-400 text-xs">to</span>
                        <input
                            v-model="expiryTo"
                            @change="applyFilters"
                            type="date"
                            placeholder="To"
                            class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <!-- Filter action buttons -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button
                            @click="applyFilters"
                            class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition cursor-pointer"
                        >
                            Apply
                        </button>
                        <button
                            @click="resetFilters"
                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer"
                        >
                            Reset
                        </button>
                    </div>

                </div>

                <!-- Timeline Quick Presets -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-medium text-slate-400 mr-1">Quick Horizon:</span>
                    <button
                        v-for="p in [
                            { key: 'all', label: 'All Dates' },
                            { key: 'next_7', label: 'Next 7 Days' },
                            { key: 'next_30', label: 'Next 30 Days' },
                            { key: 'next_60', label: 'Next 60 Days' },
                            { key: 'next_90', label: 'Next 90 Days' },
                            { key: 'expired', label: 'Already Expired' },
                        ]"
                        :key="p.key"
                        @click="selectPreset(p.key)"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer',
                            activePreset === p.key
                                ? 'bg-amber-500 text-white font-semibold shadow-xs'
                                : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300'
                        ]"
                    >
                        {{ p.label }}
                    </button>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                <button
                    @click="setStatusTab('all')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                        activeStatus === 'all'
                            ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    ]"
                >
                    All Items
                </button>
                <button
                    @click="setStatusTab('expiring_soon')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer',
                        activeStatus === 'expiring_soon'
                            ? 'bg-amber-500 text-white shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-amber-600 dark:hover:text-amber-400'
                    ]"
                >
                    <span>Expiring Soon</span>
                    <span v-if="stats?.expiring_soon_count > 0" class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ stats.expiring_soon_count }}
                    </span>
                </button>
                <button
                    @click="setStatusTab('expired')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer',
                        activeStatus === 'expired'
                            ? 'bg-rose-600 text-white shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400'
                    ]"
                >
                    <span>Expired</span>
                    <span v-if="stats?.expired_count > 0" class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                        {{ stats.expired_count }}
                    </span>
                </button>
                <button
                    @click="setStatusTab('good')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                        activeStatus === 'good'
                            ? 'bg-emerald-600 text-white shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400'
                    ]"
                >
                    Valid / Shelf-Safe
                </button>
            </div>

            <!-- Data Table -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700 uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-4 py-3">Product Name / Barcode</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Expiry Date</th>
                                <th class="px-4 py-3">Shelf Status</th>
                                <th class="px-4 py-3 text-right">Stock Qty</th>
                                <th class="px-4 py-3 text-right">Cost Price</th>
                                <th class="px-4 py-3 text-right">Selling Price</th>
                                <th class="px-4 py-3 text-right">Value at Risk</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr
                                v-for="item in items.data"
                                :key="item.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-750 transition"
                            >
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ item.item_name }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        {{ item.barcode_number || 'No barcode' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ item.category_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap font-medium text-slate-900 dark:text-white">
                                    {{ item.expiry_date }}
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border',
                                            formatDaysBadge(item.days_remaining).class
                                        ]"
                                    >
                                        {{ formatDaysBadge(item.days_remaining).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ item.total_qty }} {{ item.unit_label }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Front: {{ item.front_store_qty }} | Back: {{ item.back_store_qty }}
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-600 dark:text-slate-300">
                                    {{ format(item.buy_price) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-900 dark:text-white font-semibold">
                                    {{ format(item.price) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold whitespace-nowrap" :class="item.days_remaining <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-200'">
                                    {{ format(item.stock_worth) }}
                                </td>
                            </tr>

                            <tr v-if="!items.data?.length">
                                <td colspan="8" class="text-center py-12 px-4 text-slate-400 dark:text-slate-500">
                                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xl">
                                        🌿
                                    </div>
                                    <p class="font-medium text-slate-600 dark:text-slate-400">No items match your criteria.</p>
                                    <p class="text-xs text-slate-400 mt-1">Try resetting the date filters or selecting a different horizon preset.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="items.links && (items.total > items.per_page || items.links.length > 3)" class="border-t border-slate-100 dark:border-slate-700/60">
                    <PaginationLinks :links="items.links" :meta="items.meta ?? items" />
                </div>
            </div>

        </div>

    </div>
</template>
