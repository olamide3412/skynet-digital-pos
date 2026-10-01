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
    item:     { type: Object, required: true },
    batches:  { type: Object, required: true },
    stats:    { type: Object, default: () => ({}) },
    filters:  { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')
const { format } = useCurrency()

const searchQuery = ref(props.filters?.search || '')
const activeStatus = ref(props.filters?.status || 'all')

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingBatch = ref(null)

const alertDays = computed(() => Number(props.stats?.alert_days || props.settings?.expiry_alert_days || 30))

function doFilter() {
    router.get(
        route('pos.items.batches', { branch: branchSlug.value, item: props.item.id }),
        {
            search: searchQuery.value ? searchQuery.value.trim() : undefined,
            status: activeStatus.value !== 'all' ? activeStatus.value : undefined,
        },
        { preserveState: true, replace: true }
    )
}

function setStatus(status) {
    activeStatus.value = status
    doFilter()
}

function resetFilters() {
    searchQuery.value = ''
    activeStatus.value = 'all'
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
    if (days <= alertDays.value) {
        return { label: `Expiring Soon (${days}d)`, class: 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-800' }
    }
    return { label: `Active (${days}d)`, class: 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800' }
}

function openEditBatch(batch) {
    editingBatch.value = {
        id: batch.id,
        batch_label: batch.batch_label || '',
        quantity: batch.quantity,
        expiry_date: dayjs(batch.expiry_date).format('YYYY-MM-DD'),
        received_date: batch.received_date ? dayjs(batch.received_date).format('YYYY-MM-DD') : '',
        cost_price: batch.cost_price || '',
        status: batch.status || 'active',
    }
    showEditModal.value = true
}

function saveEditBatch() {
    if (!editingBatch.value) return
    router.put(
        route('pos.batches.update', { branch: branchSlug.value, batch: editingBatch.value.id }),
        editingBatch.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false
                editingBatch.value = null
            },
        }
    )
}

function clearBatch(batch) {
    if (!confirm(`Are you sure you want to clear / dispose batch ${batch.batch_label || '#' + batch.id}? This marks it as removed from stock.`)) {
        return
    }

    router.delete(
        route('pos.batches.destroy', { branch: branchSlug.value, batch: batch.id }),
        {
            preserveScroll: true,
        }
    )
}

function onBatchCreated() {
    router.reload({ preserveScroll: true })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('pos.items.edit', { branch: branchSlug, item: item.id })"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                    title="Back to Item Edit"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-sm">
                            📦
                        </span>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            Item Batches & Expiry — {{ item.item_name }}
                        </h1>
                        <span v-if="item.barcode_number" class="px-2 py-0.5 rounded-full text-xs font-mono font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                            {{ item.barcode_number }}
                        </span>
                        <span v-if="item.category_name" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
                            {{ item.category_name }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Manage all lots, expiry dates, quantities, and audit batch reconciliation with FEFO dispatch.
                    </p>
                </div>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex items-center gap-2.5">
                <Link
                    :href="route('pos.items.edit', { branch: branchSlug, item: item.id })"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                >
                    Edit Item Details
                </Link>
                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-900/20 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create New Batch</span>
                </button>
            </div>
        </div>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 max-w-7xl mx-auto w-full">
            <!-- 1. Variance Indicator Card -->
            <div
                class="rounded-2xl border p-5 shadow-xs transition-all flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
                :class="{
                    'bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/60': stats.batch_variance === 0,
                    'bg-amber-50/60 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/60': stats.batch_variance > 0,
                    'bg-rose-50/60 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800/60': stats.batch_variance < 0
                }"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-2.5 h-2.5 rounded-full"
                            :class="{
                                'bg-emerald-500 animate-pulse': stats.batch_variance === 0,
                                'bg-amber-500': stats.batch_variance > 0,
                                'bg-rose-500': stats.batch_variance < 0
                            }"
                        ></span>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            Batch Tracking & Stock Variance
                        </h3>
                    </div>

                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        <span v-if="stats.batch_variance === 0" class="text-emerald-700 dark:text-emerald-400 font-bold">
                            Synced: Active batches total ({{ stats.active_batches_qty }}) matches total physical stock ({{ stats.actual_stock }} {{ item.unit_label }}s).
                        </span>
                        <span v-else-if="stats.batch_variance > 0" class="text-amber-700 dark:text-amber-400 font-bold">
                            Active batches total: {{ stats.active_batches_qty }} / Physical stock: {{ stats.actual_stock }} — {{ stats.batch_variance }} untracked {{ item.unit_label }}{{ stats.batch_variance > 1 ? 's' : '' }}.
                        </span>
                        <span v-else class="text-rose-700 dark:text-rose-400 font-bold">
                            Active batches total: {{ stats.active_batches_qty }} / Physical stock: {{ stats.actual_stock }} — {{ Math.abs(stats.batch_variance) }} excess batch unit{{ Math.abs(stats.batch_variance) > 1 ? 's' : '' }}.
                        </span>
                    </p>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ stats.batch_variance === 0
                            ? 'All stock on hand is accounted for across active expiring batches.'
                            : 'Stock adjustments or purchases without a batch record create untracked variances.'
                        }}
                    </p>
                </div>

                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-900/20 transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Batch
                </button>
            </div>

            <!-- 2. KPI Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Batches</p>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ stats.total_batches }}</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                            {{ stats.active_batches }} active
                        </span>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tracked Units</p>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ stats.active_batches_qty }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            / {{ stats.actual_stock }} total
                        </span>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Expiring Soon</p>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">{{ stats.expiring_soon_count }}</span>
                        <span class="text-xs text-amber-600/80 dark:text-amber-400/80 font-medium">Within {{ alertDays }}d</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Expired Batches</p>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">{{ stats.expired_count }}</span>
                        <span class="text-xs text-rose-600/80 dark:text-rose-400/80 font-medium">Past due date</span>
                    </div>
                </div>
            </div>

            <!-- 3. Batches Table with Search & Status Tabs -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <!-- Toolbar -->
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <!-- Status Filter Tabs -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                        <button
                            type="button"
                            @click="setStatus('all')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap cursor-pointer"
                            :class="activeStatus === 'all'
                                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-2xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            All ({{ stats.total_batches }})
                        </button>

                        <button
                            type="button"
                            @click="setStatus('active')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap cursor-pointer"
                            :class="activeStatus === 'active'
                                ? 'bg-emerald-600 text-white shadow-2xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            Active ({{ stats.active_batches }})
                        </button>

                        <button
                            type="button"
                            @click="setStatus('expiring_soon')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap cursor-pointer"
                            :class="activeStatus === 'expiring_soon'
                                ? 'bg-amber-600 text-white shadow-2xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            Expiring Soon ({{ stats.expiring_soon_count }})
                        </button>

                        <button
                            type="button"
                            @click="setStatus('expired')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap cursor-pointer"
                            :class="activeStatus === 'expired'
                                ? 'bg-rose-600 text-white shadow-2xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            Expired ({{ stats.expired_count }})
                        </button>

                        <button
                            type="button"
                            @click="setStatus('cleared')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap cursor-pointer"
                            :class="activeStatus === 'cleared'
                                ? 'bg-slate-700 text-white shadow-2xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-700/60 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            Cleared ({{ stats.cleared_count }})
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <input
                                v-model="searchQuery"
                                @keydown.enter.prevent="doFilter"
                                type="text"
                                placeholder="Search batch / lot #..."
                                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-emerald-500"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <button
                            type="button"
                            @click="doFilter"
                            class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 transition"
                        >
                            Filter
                        </button>
                        <button
                            v-if="searchQuery || activeStatus !== 'all'"
                            type="button"
                            @click="resetFilters"
                            class="px-2.5 py-1.5 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition"
                            title="Reset filters"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!batches.data || batches.data.length === 0" class="p-12 text-center text-slate-400 space-y-3">
                    <div class="text-4xl">📦</div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No batches match the selected criteria.</p>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        {{ searchQuery || activeStatus !== 'all' ? 'Try adjusting your search or filters.' : 'Click "Create New Batch" to add the first batch for this item.' }}
                    </p>
                    <div v-if="searchQuery || activeStatus !== 'all'" class="pt-2">
                        <button
                            type="button"
                            @click="resetFilters"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-300"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                                <th class="px-5 py-3.5">Batch / Lot #</th>
                                <th class="px-5 py-3.5">Expiry Date</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Remaining Qty</th>
                                <th class="px-5 py-3.5 text-right">Cost Price</th>
                                <th class="px-5 py-3.5">Received Date</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr
                                v-for="batch in batches.data"
                                :key="batch.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-750 transition"
                            >
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-900 dark:text-white">
                                        {{ batch.batch_label || '#' + batch.id }}
                                    </span>
                                    <span v-if="batch.batch_label" class="block text-[11px] text-slate-400 font-mono">
                                        ID #{{ batch.id }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-mono font-medium text-slate-800 dark:text-slate-200">
                                    {{ dayjs(batch.expiry_date).format('DD MMM YYYY') }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                        :class="getBatchBadge(batch).class"
                                    >
                                        {{ getBatchBadge(batch).label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ batch.quantity }} <span class="text-[11px] font-normal text-slate-400">{{ item.unit_label }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono text-slate-600 dark:text-slate-300">
                                    {{ batch.cost_price ? format(batch.cost_price) : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400">
                                    {{ batch.received_date ? dayjs(batch.received_date).format('DD MMM YYYY') : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right space-x-2">
                                    <button
                                        type="button"
                                        @click="openEditBatch(batch)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="batch.status !== 'cleared'"
                                        type="button"
                                        @click="clearBatch(batch)"
                                        class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-semibold cursor-pointer"
                                        title="Mark batch as cleared/disposed"
                                    >
                                        Clear
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <PaginationLinks
                    :links="batches.links || []"
                    :meta="batches"
                />
            </div>
        </div>

        <!-- Create Batch Modal -->
        <CreateBatchModal
            v-model="showCreateModal"
            :item="{ id: item.id, item_name: item.item_name, added_qty: item.front_store_qty, cost_price: item.buy_price }"
            @created="onBatchCreated"
        />

        <!-- Edit Batch Modal -->
        <div
            v-if="showEditModal && editingBatch"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fade-in"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Edit Batch #{{ editingBatch.id }}</h3>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                </div>

                <form @submit.prevent="saveEditBatch" class="p-5 space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Batch / Lot #</label>
                            <input v-model="editingBatch.batch_label" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Quantity</label>
                            <input v-model.number="editingBatch.quantity" type="number" min="0" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-mono outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expiry Date *</label>
                            <input v-model="editingBatch.expiry_date" type="date" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-mono outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                            <select v-model="editingBatch.status" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none">
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="cleared">Cleared / Disposed</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cost Price</label>
                            <input v-model.number="editingBatch.cost_price" type="number" step="0.01" min="0" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-mono outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Received Date</label>
                            <input v-model="editingBatch.received_date" type="date" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-mono outline-none" />
                        </div>
                    </div>

                    <div class="pt-3 flex gap-2.5">
                        <button type="button" @click="showEditModal = false" class="flex-1 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer">Cancel</button>
                        <button type="submit" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
