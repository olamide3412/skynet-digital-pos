<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import dayjs from 'dayjs'
import { useCurrency } from '@/Composables/useCurrency'
import CreateBatchModal from '@/Components/CreateBatchModal.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    batches: Object,
    variance_items: Array,
    all_tracked_items: Array,
    sync_issues: Object,
    stats: Object,
    filters: Object,
    settings: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')
const { format } = useCurrency()

const activeTab = ref(props.filters?.tab || 'all')
const searchQuery = ref(props.filters?.search || '')
const activePreset = ref(props.filters?.preset || 'all')
const expiryFrom = ref(props.filters?.expiry_from || '')
const expiryTo = ref(props.filters?.expiry_to || '')

const showBatchModal = ref(false)
const selectedItemForBatch = ref(null)

function setTab(tab) {
    activeTab.value = tab
    if (tab === 'expiring_soon' || tab === 'expired') {
        activePreset.value = 'all'
        expiryFrom.value = ''
        expiryTo.value = ''
    }
    doFilter()
}

function selectPreset(preset) {
    activePreset.value = preset
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
    } else if (preset === 'expired') {
        expiryFrom.value = ''
        expiryTo.value = today
    } else if (preset === 'all') {
        expiryFrom.value = ''
        expiryTo.value = ''
    }
    doFilter()
}

function doFilter() {
    router.get(
        route('pos.reports.expiry-overview', { branch: branchSlug.value }),
        {
            tab: activeTab.value,
            preset: activePreset.value,
            expiry_from: expiryFrom.value || undefined,
            expiry_to: expiryTo.value || undefined,
            search: searchQuery.value ? searchQuery.value.trim() : undefined,
        },
        { preserveState: true, replace: true }
    )
}

function resetSearch() {
    searchQuery.value = ''
    expiryFrom.value = ''
    expiryTo.value = ''
    activePreset.value = 'all'
    doFilter()
}

function getDaysRemaining(expiryDate) {
    if (!expiryDate) return 0
    return dayjs(expiryDate).startOf('day').diff(dayjs().startOf('day'), 'day')
}

function getBatchBadge(batch) {
    if (batch.status === 'cleared') {
        return { label: 'Cleared', class: 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600' }
    }
    const days = getDaysRemaining(batch.expiry_date)
    if (days < 0 || batch.status === 'expired') {
        return { label: 'Expired', class: 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-300 dark:border-rose-800' }
    }
    if (days <= (props.stats?.alert_days || 30)) {
        return { label: `Expiring Soon (${days}d)`, class: 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-800' }
    }
    return { label: `Active (${days}d)`, class: 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800' }
}

function openCreateBatchFor(item) {
    selectedItemForBatch.value = {
        id: item.id,
        item_name: item.item_name,
        added_qty: item.untracked_units || 1,
        cost_price: 0,
    }
    showBatchModal.value = true
}

function resolveIssue(issue) {
    router.post(
        route('pos.batches.resolve-issue', { branch: branchSlug.value, issue: issue.id }),
        {},
        { preserveScroll: true }
    )
}

function exportExcel() {
    const params = new URLSearchParams()
    if (activePreset.value) params.set('preset', activePreset.value)
    if (expiryFrom.value) params.set('expiry_from', expiryFrom.value)
    if (expiryTo.value) params.set('expiry_to', expiryTo.value)
    if (searchQuery.value) params.set('search', searchQuery.value.trim())
    if (activeTab.value) params.set('tab', activeTab.value)

    window.location.href = `${route('pos.reports.expiry-overview.export', { branch: branchSlug.value })}?${params.toString()}`
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.reports.index')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-violet-100 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 text-sm">
                            🏷️
                        </span>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Batch Expiry Report</h1>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
                            Lot & Batch Tracking
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Track batch lot shelf-life, audit untracked inventory variances, and monitor batch synchronization.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <Link
                    :href="route('pos.reports.item-expiry', { branch: branchSlug })"
                    class="px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition"
                >
                    <span>📦 Switch to Item Expiry Report</span>
                </Link>

                <button
                    type="button"
                    @click="exportExcel"
                    class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow-xs cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export Excel</span>
                </button>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="p-6 pb-2 grid grid-cols-2 md:grid-cols-5 gap-3 flex-shrink-0">
            <!-- Active Batches -->
            <div
                @click="setTab('all')"
                class="p-4 rounded-xl border transition cursor-pointer shadow-xs"
                :class="activeTab === 'all' ? 'bg-white dark:bg-slate-800 border-emerald-500 ring-2 ring-emerald-500/20' : 'bg-white/80 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 hover:border-slate-300'"
            >
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Active Batches</span>
                <div class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ stats.total_batches }}</div>
            </div>

            <!-- Expiring Soon -->
            <div
                @click="setTab('expiring_soon')"
                class="p-4 rounded-xl border transition cursor-pointer shadow-xs"
                :class="activeTab === 'expiring_soon' ? 'bg-white dark:bg-slate-800 border-amber-500 ring-2 ring-amber-500/20' : 'bg-white/80 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 hover:border-slate-300'"
            >
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Expiring in ≤{{ stats.alert_days }}d</span>
                <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ stats.expiring_soon_count }}</div>
            </div>

            <!-- Expired Batches -->
            <div
                @click="setTab('expired')"
                class="p-4 rounded-xl border transition cursor-pointer shadow-xs"
                :class="activeTab === 'expired' ? 'bg-white dark:bg-slate-800 border-rose-500 ring-2 ring-rose-500/20' : 'bg-white/80 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 hover:border-slate-300'"
            >
                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Expired Batches</span>
                <div class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ stats.expired_count }}</div>
            </div>

            <!-- Untracked Variances -->
            <div
                @click="setTab('variances')"
                class="p-4 rounded-xl border transition cursor-pointer shadow-xs"
                :class="activeTab === 'variances' ? 'bg-white dark:bg-slate-800 border-purple-500 ring-2 ring-purple-500/20' : 'bg-white/80 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 hover:border-slate-300'"
            >
                <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Stock Variances</span>
                <div class="text-xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ stats.variance_items_count }}</div>
            </div>

            <!-- Sync Issues Log -->
            <div
                @click="setTab('sync_issues')"
                class="p-4 rounded-xl border transition cursor-pointer shadow-xs"
                :class="activeTab === 'sync_issues' ? 'bg-white dark:bg-slate-800 border-indigo-500 ring-2 ring-indigo-500/20' : 'bg-white/80 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 hover:border-slate-300'"
            >
                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Sync Issues Log</span>
                <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.sync_issues_count }}</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="px-6 py-2 flex flex-wrap items-center justify-between gap-3 flex-shrink-0">
            <!-- Tabs -->
            <div class="flex items-center gap-1.5 p-1 bg-slate-200/80 dark:bg-slate-800 rounded-xl">
                <button
                    type="button"
                    @click="setTab('all')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    :class="activeTab === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                >
                    All Batches
                </button>
                <button
                    type="button"
                    @click="setTab('expiring_soon')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    :class="activeTab === 'expiring_soon' ? 'bg-white dark:bg-slate-700 text-amber-600 dark:text-amber-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                >
                    Expiring Soon
                </button>
                <button
                    type="button"
                    @click="setTab('expired')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    :class="activeTab === 'expired' ? 'bg-white dark:bg-slate-700 text-rose-600 dark:text-rose-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                >
                    Expired
                </button>
                <button
                    type="button"
                    @click="setTab('variances')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    :class="activeTab === 'variances' ? 'bg-white dark:bg-slate-700 text-purple-600 dark:text-purple-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                >
                    Stock Variances ({{ stats.variance_items_count }})
                </button>
                <button
                    type="button"
                    @click="setTab('sync_issues')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    :class="activeTab === 'sync_issues' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                >
                    Sync Issues ({{ stats.sync_issues_count }})
                </button>
            </div>

            <!-- Search Bar -->
            <div class="relative w-full sm:w-72">
                <input
                    v-model="searchQuery"
                    @keyup.enter="doFilter"
                    type="text"
                    placeholder="Search by item, barcode, or lot #..."
                    class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-1.5 pl-9 rounded-xl border border-slate-300 dark:border-slate-700 text-xs outline-none focus:border-emerald-500 transition shadow-xs"
                />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <button v-if="searchQuery" type="button" @click="resetSearch" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">✕</button>
            </div>
        </div>

        <!-- Expiry Date Range Query Bar (§4.2) -->
        <div class="px-6 py-2 flex flex-wrap items-center justify-between gap-3 bg-slate-100/60 dark:bg-slate-800/40 border-y border-slate-200/60 dark:border-slate-700/60 flex-shrink-0">
            <div class="flex items-center flex-wrap gap-1.5">
                <span class="text-[11px] font-bold text-slate-400 mr-1 uppercase">Expiry Date Range:</span>
                <button
                    v-for="p in [
                        { key: 'all', label: 'All Dates' },
                        { key: 'next_7', label: 'Next 7 Days' },
                        { key: 'next_30', label: 'Next 30 Days' },
                        { key: 'next_60', label: 'Next 60 Days' },
                        { key: 'expired', label: 'Already Expired' },
                        { key: 'custom', label: 'Custom Range' },
                    ]"
                    :key="p.key"
                    type="button"
                    @click="selectPreset(p.key)"
                    :class="activePreset === p.key
                        ? 'bg-emerald-600 text-white font-bold'
                        : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 font-medium'"
                    class="px-2.5 py-1 text-[11px] rounded-lg border border-slate-200 dark:border-slate-600 transition cursor-pointer"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Custom Expiry Date Inputs -->
            <div v-if="activePreset === 'custom'" class="flex items-center gap-2">
                <input
                    v-model="expiryFrom"
                    type="date"
                    class="px-2.5 py-1 text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                />
                <span class="text-xs text-slate-400">to</span>
                <input
                    v-model="expiryTo"
                    type="date"
                    class="px-2.5 py-1 text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                />
                <button
                    type="button"
                    @click="doFilter"
                    class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition cursor-pointer"
                >
                    Apply
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <!-- TAB: Stock Variances (§6.1) -->
            <div v-if="activeTab === 'variances'" class="space-y-4">
                <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800 text-xs text-purple-800 dark:text-purple-300 space-y-1">
                    <strong class="font-bold block">Informational Untracked Stock Visibility:</strong>
                    <span>When stock is adjusted or received without creating a batch, the core stock count safely increases while leaving batches untouched. Use this table to spot items where batch totals differ from total physical stock.</span>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                                <th class="px-4 py-3">Tracked Item</th>
                                <th class="px-4 py-3">Barcode</th>
                                <th class="px-4 py-3 text-right">Physical Stock</th>
                                <th class="px-4 py-3 text-right">Active Batches Total</th>
                                <th class="px-4 py-3 text-center">Variance Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr v-if="!all_tracked_items?.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    No batch-tracked items found in this branch.
                                </td>
                            </tr>
                            <tr
                                v-for="item in all_tracked_items"
                                :key="item.id"
                                class="hover:bg-slate-100/70 dark:hover:bg-slate-700/60 transition"
                            >
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                    <Link :href="route('pos.items.edit', { branch: branchSlug, item: item.id })" class="hover:underline text-emerald-600 dark:text-emerald-400 font-bold">
                                        {{ item.item_name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">
                                    {{ item.barcode_number || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ item.actual_stock }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-700 dark:text-slate-300">
                                    {{ item.batch_total }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        v-if="item.is_synced"
                                        class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800"
                                    >
                                        ✓ Synced ({{ item.actual_stock }})
                                    </span>
                                    <span
                                        v-else-if="item.variance > 0"
                                        class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800"
                                    >
                                        {{ item.untracked_units }} units untracked
                                    </span>
                                    <span
                                        v-else
                                        class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-300 dark:border-rose-800"
                                    >
                                        {{ item.excess_units }} excess batch units
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button
                                        type="button"
                                        @click="openCreateBatchFor(item)"
                                        class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-semibold"
                                    >
                                        + Add Batch
                                    </button>
                                    <Link
                                        :href="route('pos.items.edit', { branch: branchSlug, item: item.id })"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                                    >
                                        View Batches
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Sync Issues Log (§6.2) -->
            <div v-else-if="activeTab === 'sync_issues'" class="space-y-4">
                <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-800 dark:text-indigo-300">
                    <strong class="font-bold block">Batch Auto-Deduction Issues:</strong>
                    <span>These quiet audit entries record instances where a checkout sale or adjustment attempted to deduct from active batches, but found insufficient batch quantities. Checkouts are never interrupted; issues are logged here for admin review.</span>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                                <th class="px-4 py-3">Logged Date</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Trigger / Ref</th>
                                <th class="px-4 py-3 text-right">Requested Qty</th>
                                <th class="px-4 py-3 text-right">Batch Available</th>
                                <th class="px-4 py-3">Reason / Details</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr v-if="!sync_issues?.data?.length">
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                                    No sync issues logged. All batch auto-deductions completed cleanly!
                                </td>
                            </tr>
                            <tr
                                v-for="issue in sync_issues?.data"
                                :key="issue.id"
                                class="hover:bg-slate-100/70 dark:hover:bg-slate-700/60 transition"
                            >
                                <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">
                                    {{ dayjs(issue.created_at).format('DD MMM YYYY, HH:mm') }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                    {{ issue.item?.item_name || 'Item #' + issue.item_id }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    <span class="uppercase font-bold text-slate-700 dark:text-slate-300">{{ issue.action_type }}</span>
                                    <span v-if="issue.reference_id" class="text-slate-400 block text-[11px]">#{{ issue.reference_id }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ issue.requested_qty }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-rose-600 dark:text-rose-400 font-semibold">
                                    {{ issue.available_batch_qty }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 max-w-xs">
                                    {{ issue.reason }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                        :class="issue.status === 'resolved' ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-300' : 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-300'"
                                    >
                                        {{ issue.status === 'resolved' ? 'Resolved' : 'Logged' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="issue.status !== 'resolved'"
                                        type="button"
                                        @click="resolveIssue(issue)"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[11px] font-bold transition cursor-pointer"
                                    >
                                        Mark Resolved
                                    </button>
                                    <span v-else class="text-slate-400 text-xs">Closed</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Sync Issues Pagination -->
                <div v-if="sync_issues?.links && (sync_issues.total > sync_issues.per_page || sync_issues.links.length > 3)" class="border-t border-slate-200 dark:border-slate-700">
                    <PaginationLinks :links="sync_issues.links" :meta="sync_issues.meta ?? sync_issues" />
                </div>
            </div>

            <!-- TABS: All Batches / Expiring Soon / Expired -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                            <th class="px-4 py-3">Batch / Lot #</th>
                            <th class="px-4 py-3">Item Name</th>
                            <th class="px-4 py-3">Expiry Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Batch Qty</th>
                            <th class="px-4 py-3 text-right">Cost Price</th>
                            <th class="px-4 py-3">Received Date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr v-if="!batches?.data?.length">
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                                No batches found matching the selected filter.
                            </td>
                        </tr>
                        <tr
                            v-for="batch in batches?.data"
                            :key="batch.id"
                            class="hover:bg-slate-100/70 dark:hover:bg-slate-700/60 transition"
                        >
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                {{ batch.batch_label || '—' }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">
                                <Link
                                    v-if="batch.item"
                                    :href="route('pos.items.edit', { branch: branchSlug, item: batch.item_id })"
                                    class="hover:underline text-emerald-600 dark:text-emerald-400 font-bold"
                                >
                                    {{ batch.item.item_name }}
                                </Link>
                                <span v-else>Item #{{ batch.item_id }}</span>
                                <span v-if="batch.item?.barcode_number" class="text-[11px] text-slate-400 font-mono block">
                                    {{ batch.item.barcode_number }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono font-medium text-slate-800 dark:text-slate-200">
                                {{ dayjs(batch.expiry_date).format('DD MMM YYYY') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                    :class="getBatchBadge(batch).class"
                                >
                                    {{ getBatchBadge(batch).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ batch.quantity }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">
                                {{ batch.cost_price ? format(batch.cost_price) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ batch.received_date ? dayjs(batch.received_date).format('DD MMM YYYY') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    v-if="batch.item_id"
                                    :href="route('pos.items.edit', { branch: branchSlug, item: batch.item_id })"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                                >
                                    View / Edit
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Batches Pagination -->
                <div v-if="batches?.links && (batches.total > batches.per_page || batches.links.length > 3)" class="border-t border-slate-200 dark:border-slate-700">
                    <PaginationLinks :links="batches.links" :meta="batches.meta ?? batches" />
                </div>
            </div>
        </div>

        <!-- Create Batch Modal -->
        <CreateBatchModal
            v-model="showBatchModal"
            :item="selectedItemForBatch"
            @created="doFilter"
        />
    </div>
</template>
