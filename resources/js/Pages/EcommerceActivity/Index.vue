<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    branch: Object,
    integration: Object,
    activeTab: {
        type: String,
        default: 'reservations',
    },
    stats: {
        type: Object,
        default: () => ({
            active_reservations: 0,
            confirmed_reservations: 0,
            active_mappings: 0,
            total_movements: 0,
        }),
    },
    reservations: Object,
    movements: Object,
    mappings: Object,
    filters: Object,
})

const currentTab = ref(props.activeTab || 'reservations')

// Filter state
const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')
const type = ref(props.filters?.type || '')
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

// Modals
const showReleaseModal = ref(false)
const selectedReservation = ref(null)
const isSubmitting = ref(false)

const showDeactivateModal = ref(false)
const selectedMapping = ref(null)

function switchTab(tab) {
    currentTab.value = tab
    search.value = ''
    status.value = ''
    type.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    applyFilters(tab)
}

function applyFilters(overrideTab = null) {
    const tabToUse = overrideTab || currentTab.value
    router.get(
        route('pos.ecommerce-activity.index'),
        {
            tab: tabToUse,
            search: search.value ? search.value.trim() : undefined,
            status: status.value || undefined,
            type: type.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}

function resetFilters() {
    search.value = ''
    status.value = ''
    type.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    applyFilters()
}

// Action: Release Reservation
function openReleaseModal(reservation) {
    selectedReservation.value = reservation
    showReleaseModal.value = true
}

function confirmReleaseReservation() {
    if (!selectedReservation.value) return
    isSubmitting.value = true
    router.post(
        route('pos.ecommerce-activity.reservations.release', { reservation: selectedReservation.value.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false
                showReleaseModal.value = false
                selectedReservation.value = null
            },
        }
    )
}

// Action: Deactivate Mapping
function openDeactivateModal(mapping) {
    selectedMapping.value = mapping
    showDeactivateModal.value = true
}

function confirmDeactivateMapping() {
    if (!selectedMapping.value) return
    isSubmitting.value = true
    router.post(
        route('pos.ecommerce-activity.mappings.deactivate', { mapping: selectedMapping.value.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false
                showDeactivateModal.value = false
                selectedMapping.value = null
            },
        }
    )
}

function formatCurrency(val) {
    const num = parseFloat(val) || 0
    return '₦' + num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function isExpired(reservation) {
    if (reservation.status !== 'pending') return false
    return new Date(reservation.expires_at) <= new Date()
}
</script>

<template>
    <Head title="Ecommerce Activity — Branch Operations" />

    <div class="flex-1 h-full overflow-y-auto min-h-0 bg-slate-50/50 dark:bg-slate-950/50">
        <div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- ── HEADER BANNER ────────────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <div class="flex items-center gap-4">
                <div class="w-13 h-13 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-2xl shadow-xs">
                    🛍️
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-black text-slate-900 dark:text-white">Ecommerce Activity</h1>
                        <span
                            v-if="integration"
                            :class="integration.is_active ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30'"
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold border capitalize"
                        >
                            {{ integration.platform }} &bull; {{ integration.status }}
                        </span>
                        <span
                            v-else
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700"
                        >
                            No Active Store Linked
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Operational visibility into branch inventory reservations, stock movements, and item catalog mappings.
                    </p>
                </div>
            </div>

            <!-- Connected Store Meta Badge -->
            <div v-if="integration" class="text-left sm:text-right text-xs bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
                <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5 sm:justify-end">
                    <span>🌐 {{ integration.store_name || 'Online Store' }}</span>
                </div>
                <div class="text-slate-400 font-mono text-[11px] mt-0.5 truncate max-w-xs">
                    {{ integration.store_url || 'Connected POS Branch' }}
                </div>
                <div v-if="integration.last_synced_at" class="text-[10px] text-slate-400 mt-1">
                    Last catalog sync: {{ integration.last_synced_at }}
                </div>
            </div>
        </div>

        <!-- ── STATS CARDS ──────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <!-- Active Holds -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Active Holds</span>
                    <span class="p-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">⏳</span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-2">
                    {{ stats.active_reservations }}
                </div>
                <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-0.5">Reserved front-store stock</p>
            </div>

            <!-- Confirmed Orders -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Confirmed Orders</span>
                    <span class="p-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">✅</span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-2">
                    {{ stats.confirmed_reservations }}
                </div>
                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">Fulfilled online checkouts</p>
            </div>

            <!-- Active Mappings -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Mapped Items</span>
                    <span class="p-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">🔗</span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-2">
                    {{ stats.active_mappings }}
                </div>
                <p class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-0.5">Active online product links</p>
            </div>

            <!-- Total Movements -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Inventory Logs</span>
                    <span class="p-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">📦</span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-2">
                    {{ stats.total_movements }}
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">Stock deductions & returns</p>
            </div>
        </div>

        <!-- ── TAB BAR ──────────────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="flex items-center border-b border-slate-200 dark:border-slate-800 px-4 gap-2 overflow-x-auto">
                <button
                    @click="switchTab('reservations')"
                    :class="currentTab === 'reservations' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-3 px-3 text-xs border-b-2 transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                >
                    <span>🕒 Reservations</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold">
                        {{ stats.active_reservations }} active
                    </span>
                </button>

                <button
                    @click="switchTab('movements')"
                    :class="currentTab === 'movements' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-3 px-3 text-xs border-b-2 transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                >
                    <span>📦 Inventory Movements</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold">
                        Read-Only Log
                    </span>
                </button>

                <button
                    @click="switchTab('mappings')"
                    :class="currentTab === 'mappings' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="py-3 px-3 text-xs border-b-2 transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                >
                    <span>🔗 Item Mappings</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold">
                        {{ stats.active_mappings }} mapped
                    </span>
                </button>
            </div>

            <!-- ── FILTERS BAR ──────────────────────────────────────────────── -->
            <div class="p-4 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-800">
                <form @submit.prevent="applyFilters()" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                    <!-- Search Input -->
                    <div class="md:col-span-2">
                        <label class="block text-slate-500 dark:text-slate-400 mb-1 font-medium">Search</label>
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="currentTab === 'reservations' ? 'Search order ref, item name or barcode...' : (currentTab === 'movements' ? 'Search order ref, notes or item...' : 'Search item name, barcode, product ID or SKU...')"
                                class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-xs"
                            />
                        </div>
                    </div>

                    <!-- Status Filter (for Reservations or Mappings) -->
                    <div v-if="currentTab === 'reservations'">
                        <label class="block text-slate-500 dark:text-slate-400 mb-1 font-medium">Status</label>
                        <select
                            v-model="status"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-xs"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active Holds</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="released">Released</option>
                            <option value="expired">Expired Pending</option>
                        </select>
                    </div>

                    <!-- Type Filter (for Movements) -->
                    <div v-if="currentTab === 'movements'">
                        <label class="block text-slate-500 dark:text-slate-400 mb-1 font-medium">Movement Type</label>
                        <select
                            v-model="type"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-xs"
                        >
                            <option value="">All Movement Types</option>
                            <option value="reservation_hold">Hold Reserved Stock</option>
                            <option value="reservation_confirm">Confirmed / Deducted</option>
                            <option value="reservation_release">Hold Released</option>
                            <option value="stock_deduction">Direct Stock Deduction</option>
                        </select>
                    </div>

                    <!-- Status Filter (for Mappings) -->
                    <div v-if="currentTab === 'mappings'">
                        <label class="block text-slate-500 dark:text-slate-400 mb-1 font-medium">Mapping Status</label>
                        <select
                            v-model="status"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-xs"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active Online</option>
                            <option value="inactive">Deactivated</option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div v-if="currentTab !== 'mappings'">
                        <label class="block text-slate-500 dark:text-slate-400 mb-1 font-medium">From Date</label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-xs"
                        />
                    </div>

                    <!-- Date To / Buttons -->
                    <div class="flex items-end gap-2">
                        <div v-if="currentTab !== 'mappings'" class="flex-1">
                            <label class="block text-slate-500 dark:text-slate-400 mb-1 font-medium">To Date</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-xs"
                            />
                        </div>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold transition cursor-pointer whitespace-nowrap"
                        >
                            Filter
                        </button>
                        <button
                            @click="resetFilters"
                            type="button"
                            class="px-3 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer"
                            title="Reset filters"
                        >
                            ✕
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── TAB CONTENT: RESERVATIONS ─────────────────────────────────── -->
            <div v-if="currentTab === 'reservations'">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Order Ref</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Reserved Items</th>
                                <th class="px-4 py-3">Total Qty</th>
                                <th class="px-4 py-3">Created</th>
                                <th class="px-4 py-3">Expires / Resolved</th>
                                <th class="px-4 py-3 text-right">Corrective Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="res in reservations?.data" :key="res.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ res.external_order_reference }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="res.status === 'confirmed'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30"
                                    >
                                        Confirmed
                                    </span>
                                    <span
                                        v-else-if="res.status === 'released'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700"
                                    >
                                        Released
                                    </span>
                                    <span
                                        v-else-if="isExpired(res)"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30"
                                    >
                                        Expired Pending
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30"
                                    >
                                        Pending Hold
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1 max-w-xs">
                                        <div v-for="item in res.items" :key="item.id" class="flex items-center justify-between text-[11px] gap-2">
                                            <span class="truncate font-medium text-slate-800 dark:text-slate-200">{{ item.item?.item_name || item.item?.name || ('Item #' + item.item_id) }}</span>
                                            <span class="text-slate-400 font-mono">{{ item.quantity }}x</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-bold font-mono">
                                    {{ res.items?.reduce((sum, i) => sum + (parseInt(i.quantity) || 0), 0) }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ formatDate(res.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    <span v-if="res.status === 'confirmed'" class="text-emerald-600 dark:text-emerald-400">
                                        Confirmed {{ formatDate(res.confirmed_at) }}
                                    </span>
                                    <span v-else-if="res.status === 'released'" class="text-slate-500">
                                        Released {{ formatDate(res.released_at) }}
                                    </span>
                                    <span v-else :class="isExpired(res) ? 'text-rose-600 font-bold' : 'text-amber-600'">
                                        Expires {{ formatDate(res.expires_at) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="res.status === 'pending'"
                                        @click="openReleaseModal(res)"
                                        type="button"
                                        class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/60 hover:bg-amber-100 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 rounded-lg text-xs font-semibold transition cursor-pointer"
                                    >
                                        Release Stock Hold
                                    </button>
                                    <span v-else class="text-slate-400 text-xs italic">None</span>
                                </td>
                            </tr>
                            <tr v-if="!reservations?.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                    No reservations found matching the selected filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="reservations?.total > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/40">
                    <div>
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ reservations.from || 0 }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ reservations.to || 0 }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ reservations.total || 0 }}</span> reservations
                    </div>

                    <div v-if="reservations.links && reservations.links.length > 3" class="flex items-center gap-1">
                        <template v-for="(link, idx) in reservations.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer"
                                :class="link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                    : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-md text-xs text-slate-400 dark:text-slate-600 border border-slate-200/50 dark:border-slate-800 select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── TAB CONTENT: INVENTORY MOVEMENTS (READ-ONLY) ────────────────── -->
            <div v-if="currentTab === 'movements'">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Timestamp</th>
                                <th class="px-4 py-3">Movement Type</th>
                                <th class="px-4 py-3">Item Details</th>
                                <th class="px-4 py-3">Qty Change</th>
                                <th class="px-4 py-3">Stock Impact</th>
                                <th class="px-4 py-3">Order Reference</th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="m in movements?.data" :key="m.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ formatDate(m.created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="m.type === 'reservation_confirm' || m.type === 'stock_deduction'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30"
                                    >
                                        Stock Deducted
                                    </span>
                                    <span
                                        v-else-if="m.type === 'reservation_hold'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30"
                                    >
                                        Hold Reserved
                                    </span>
                                    <span
                                        v-else-if="m.type === 'reservation_release'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30"
                                    >
                                        Hold Released
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 border border-slate-200"
                                    >
                                        {{ m.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ m.item?.item_name || m.item?.name || ('Item #' + m.item_id) }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        Barcode: {{ m.item?.barcode_number || m.item?.barcode || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold" :class="m.quantity < 0 ? 'text-rose-600' : (m.type === 'reservation_release' ? 'text-emerald-600' : 'text-slate-900 dark:text-white')">
                                    {{ m.quantity }}
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-500">
                                    <span v-if="m.previous_stock !== null && m.current_stock !== null">
                                        {{ m.previous_stock }} &rarr; <span class="font-bold text-slate-900 dark:text-white">{{ m.current_stock }}</span>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ m.external_reference || '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                    {{ m.notes || '—' }}
                                </td>
                            </tr>
                            <tr v-if="!movements?.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                    No inventory movements recorded for ecommerce activity yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="movements?.total > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/40">
                    <div>
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ movements.from || 0 }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ movements.to || 0 }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ movements.total || 0 }}</span> inventory movements
                    </div>

                    <div v-if="movements.links && movements.links.length > 3" class="flex items-center gap-1">
                        <template v-for="(link, idx) in movements.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer"
                                :class="link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                    : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-md text-xs text-slate-400 dark:text-slate-600 border border-slate-200/50 dark:border-slate-800 select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── TAB CONTENT: ITEM MAPPINGS ────────────────────────────────── -->
            <div v-if="currentTab === 'mappings'">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3">POS Item</th>
                                <th class="px-4 py-3">POS Price / Stock</th>
                                <th class="px-4 py-3">Online Product ID</th>
                                <th class="px-4 py-3">Variant / SKU</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Registered / Last Seen</th>
                                <th class="px-4 py-3 text-right">Corrective Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="map in mappings?.data" :key="map.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ map.item?.item_name || map.item?.name || ('Item #' + map.item_id) }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        Barcode: {{ map.item?.barcode_number || map.item?.barcode || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ formatCurrency(map.item?.price) }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        {{ map.item?.front_store_qty ?? 0 }} in front store
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ map.external_product_id }}
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-500">
                                    <div>Var: {{ map.external_variant_id || 'default' }}</div>
                                    <div>SKU: {{ map.external_sku || '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="map.is_active"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30"
                                    >
                                        Live Online
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700"
                                    >
                                        Deactivated
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">
                                    <div>Reg: {{ formatDate(map.registered_at || map.created_at) }}</div>
                                    <div>Seen: {{ formatDate(map.last_seen_at || map.last_synced_at) }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="map.is_active"
                                        @click="openDeactivateModal(map)"
                                        type="button"
                                        class="px-2.5 py-1 bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 rounded-lg text-xs font-semibold transition cursor-pointer"
                                    >
                                        Deactivate
                                    </button>
                                    <span v-else class="text-slate-400 text-xs italic">Deactivated</span>
                                </td>
                            </tr>
                            <tr v-if="!mappings?.data?.length">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                    No item catalog mappings found for this branch.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="mappings?.total > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 bg-slate-50/50 dark:bg-slate-800/40">
                    <div>
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ mappings.from || 0 }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ mappings.to || 0 }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ mappings.total || 0 }}</span> catalog mappings
                    </div>

                    <div v-if="mappings.links && mappings.links.length > 3" class="flex items-center gap-1">
                        <template v-for="(link, idx) in mappings.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition border cursor-pointer"
                                :class="link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                    : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-md text-xs text-slate-400 dark:text-slate-600 border border-slate-200/50 dark:border-slate-800 select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL: RELEASE RESERVATION ──────────────────────────────────── -->
        <div v-if="showReleaseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400">
                    <span class="text-2xl">⚠️</span>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Release Stock Reservation</h3>
                </div>

                <div class="text-xs text-slate-600 dark:text-slate-300 space-y-2">
                    <p>
                        Are you sure you want to release the reservation for order reference
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ selectedReservation?.external_order_reference }}</span>?
                    </p>
                    <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl space-y-1">
                        <div class="font-semibold text-slate-700 dark:text-slate-300">Items to return to front-store:</div>
                        <ul class="list-disc pl-4 space-y-0.5 text-slate-500">
                            <li v-for="item in selectedReservation?.items" :key="item.id">
                                {{ item.quantity }}x {{ item.item?.name || ('Item #' + item.item_id) }}
                            </li>
                        </ul>
                    </div>
                    <p class="text-[11px] text-slate-400 italic">
                        This action frees the reserved stock back into available branch inventory on the POS immediately. It does not cancel or modify the order on the external ecommerce platform.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        @click="showReleaseModal = false"
                        type="button"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmReleaseReservation"
                        :disabled="isSubmitting"
                        type="button"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <span v-if="isSubmitting" class="animate-spin">⏳</span>
                        <span>Confirm & Release Stock</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── MODAL: DEACTIVATE MAPPING ───────────────────────────────────── -->
        <div v-if="showDeactivateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                    <span class="text-2xl">🔗</span>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Deactivate Item Mapping</h3>
                </div>

                <div class="text-xs text-slate-600 dark:text-slate-300 space-y-2">
                    <p>
                        Are you sure you want to mark the mapping for
                        <span class="font-bold text-slate-900 dark:text-white">{{ selectedMapping?.item?.name }}</span> as inactive?
                    </p>
                    <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl font-mono text-[11px] text-slate-500 space-y-0.5">
                        <div>External ID: {{ selectedMapping?.external_product_id }}</div>
                        <div>SKU: {{ selectedMapping?.external_sku || '—' }}</div>
                    </div>
                    <p class="text-[11px] text-slate-400 italic">
                        This stops the POS from considering this item as live online in branch operational reporting. It does <strong>not</strong> reach into the ecommerce store or unpublish the product online.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        @click="showDeactivateModal = false"
                        type="button"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmDeactivateMapping"
                        :disabled="isSubmitting"
                        type="button"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <span v-if="isSubmitting" class="animate-spin">⏳</span>
                        <span>Deactivate Mapping</span>
                    </button>
                </div>
            </div>
        </div>
        </div>
    </div>
</template>
