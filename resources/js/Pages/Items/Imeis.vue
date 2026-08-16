<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    units:        Object,
    filters:      Object,
    stats:        Object,
    trackedItems: Array,
    settings:     Object,
})

const { format } = useCurrency()

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const location = ref(props.filters.location || '')
const itemId = ref(props.filters.item_id || '')

const selectedUnit = ref(null)
const showDetailModal = ref(false)

function openDetail(unit) {
    selectedUnit.value = unit
    showDetailModal.value = true
}

let timeout = null
function applyFilters() {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get(
            route('pos.items.imeis'),
            {
                search: search.value || undefined,
                status: status.value || undefined,
                location: location.value || undefined,
                item_id: itemId.value || undefined,
            },
            { preserveState: true, replace: true }
        )
    }, 300)
}

watch([search, status, location, itemId], () => {
    applyFilters()
})

function copyImei(imei) {
    if (!imei) return
    navigator.clipboard.writeText(imei)
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl">
                    📱
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white">IMEI & Device ID Tracking</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Unit-level serial traceability, warranty lookup, and lifecycle auditing</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    v-if="!settings?.is_imei_enabled"
                    :href="route('pos.settings.index')"
                    class="px-3.5 py-1.5 bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs font-semibold rounded-lg flex items-center gap-2 hover:bg-amber-500/20 transition"
                >
                    <span>⚠️ IMEI Tracking is Disabled in Settings</span>
                    <span class="underline">Enable</span>
                </Link>
                <Link
                    :href="route('pos.items.create')"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5"
                >
                    <span>+</span> New Device Item
                </Link>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- ── Top Stats Cards ─────────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Tracked Units</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1 font-mono">{{ stats.total }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">In Stock (Front Store)</p>
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 font-mono">{{ stats.in_stock_front }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">In Stock (Warehouse)</p>
                    <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1 font-mono">{{ stats.in_stock_back }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs">
                    <p class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Total Units Sold</p>
                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1 font-mono">{{ stats.sold }}</p>
                </div>
            </div>

            <!-- ── Search & Filters Bar ────────────────────────────────────── -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs flex flex-wrap items-center gap-3">
                <!-- Search input -->
                <div class="flex-1 min-w-[240px]">
                    <div class="relative">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by IMEI, Serial, Model, Receipt # or Customer..."
                            class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white rounded-lg border border-slate-200 dark:border-slate-600 focus:border-indigo-500 outline-none text-xs transition"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Status filter -->
                <select
                    v-model="status"
                    class="bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-xs outline-none focus:border-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="in_stock">In Stock</option>
                    <option value="sold">Sold</option>
                    <option value="returned">Returned</option>
                    <option value="damaged">Damaged</option>
                </select>

                <!-- Location filter -->
                <select
                    v-model="location"
                    class="bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-xs outline-none focus:border-indigo-500"
                >
                    <option value="">All Locations</option>
                    <option value="front_store">Front Store (Active Floor)</option>
                    <option value="back_store">Back Store (Warehouse)</option>
                </select>

                <!-- Item Filter -->
                <select
                    v-model="itemId"
                    class="bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-xs outline-none focus:border-indigo-500 max-w-[200px]"
                >
                    <option value="">All Device Models</option>
                    <option v-for="item in trackedItems" :key="item.id" :value="item.id">
                        {{ item.item_name }}
                    </option>
                </select>
            </div>

            <!-- ── Device Units Table ──────────────────────────────────────── -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400 font-semibold">
                        <tr>
                            <th class="px-5 py-3">Device & IMEI / Serial</th>
                            <th class="px-5 py-3">Status & Location</th>
                            <th class="px-5 py-3">Received Info</th>
                            <th class="px-5 py-3">Sale & Customer</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-if="units.data.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                                <div class="text-3xl mb-2">🔍</div>
                                <p class="text-sm font-semibold">No device serial/IMEI records found.</p>
                                <p class="text-xs text-slate-500 mt-0.5">Try searching with a different term or enable IMEI tracking on item creation.</p>
                            </td>
                        </tr>
                        <tr
                            v-for="unit in units.data"
                            :key="unit.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition"
                        >
                            <!-- IMEI & Device Name -->
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white text-sm">
                                    {{ unit.item?.item_name || '—' }}
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded border border-indigo-100 dark:border-indigo-900/50">
                                        {{ unit.imei_or_device_id }}
                                    </span>
                                    <button
                                        @click="copyImei(unit.imei_or_device_id)"
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition"
                                        title="Copy IMEI"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>

                            <!-- Status & Location -->
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col gap-1 items-start">
                                    <span
                                        v-if="unit.status === 'in_stock'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                    >
                                        ● In Stock
                                    </span>
                                    <span
                                        v-else-if="unit.status === 'sold'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
                                    >
                                        ✓ Sold
                                    </span>
                                    <span
                                        v-else-if="unit.status === 'returned'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                    >
                                        ↺ Returned
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        ⚠ {{ unit.status }}
                                    </span>

                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ unit.location === 'front_store' ? 'Front Store (Active Floor)' : 'Back Store (Warehouse)' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Received Info -->
                            <td class="px-5 py-3.5 text-slate-600 dark:text-slate-300">
                                <div v-if="unit.purchase_order">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">
                                        PO #{{ unit.purchase_order.po_number }}
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Vendor: {{ unit.purchase_order.vendor?.name || '—' }}
                                    </div>
                                </div>
                                <div v-else class="text-slate-400 text-[11px]">
                                    Direct Stock Entry
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                    {{ dayjs(unit.created_at).format('MMM D, YYYY') }}
                                </div>
                            </td>

                            <!-- Sale & Customer Info -->
                            <td class="px-5 py-3.5">
                                <div v-if="unit.sale">
                                    <Link
                                        :href="route('pos.sales.show', unit.sale.id)"
                                        class="font-mono font-bold text-emerald-600 dark:text-emerald-400 hover:underline"
                                    >
                                        #{{ unit.sale.receipt_id }}
                                    </Link>
                                    <div class="text-[11px] text-slate-700 dark:text-slate-300 font-medium">
                                        Customer: {{ unit.sale.customer?.name || 'Walk-in' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono">
                                        Sold: {{ dayjs(unit.sold_at || unit.sale.created_at).format('MMM D, YYYY h:mm A') }}
                                    </div>
                                </div>
                                <div v-else class="text-slate-400 text-[11px] italic">
                                    Not yet sold
                                </div>
                            </td>

                            <!-- Action -->
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    @click="openDetail(unit)"
                                    class="px-2.5 py-1 text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-300 text-slate-700 dark:text-slate-200 rounded-lg transition"
                                >
                                    Inspect Lifecycle
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination ──────────────────────────────────────────────── -->
            <div v-if="units.links && units.links.length > 3" class="flex items-center justify-between pt-2">
                <span class="text-xs text-slate-500">
                    Showing {{ units.from || 0 }} to {{ units.to || 0 }} of {{ units.total }} units
                </span>
                <div class="flex items-center gap-1">
                    <Link
                        v-for="link in units.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                            link.active
                                ? 'bg-indigo-600 text-white font-bold'
                                : link.url
                                ? 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                                : 'text-slate-400 opacity-50 cursor-not-allowed'
                        ]"
                    />
                </div>
            </div>
        </div>

        <!-- ── Detailed Lifecycle Modal ────────────────────────────────────── -->
        <div
            v-if="showDetailModal && selectedUnit"
            class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-lg w-full overflow-hidden shadow-2xl space-y-4">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-750">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">📱</span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Device Lifecycle & Warranty Audit</h3>
                    </div>
                    <button
                        @click="showDetailModal = false"
                        class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg font-bold"
                    >
                        ✕
                    </button>
                </div>

                <div class="p-6 space-y-5 text-xs">
                    <!-- Unit Card -->
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-1">
                        <p class="font-bold text-sm text-slate-900 dark:text-white">{{ selectedUnit.item?.item_name }}</p>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                IMEI / Serial: {{ selectedUnit.imei_or_device_id }}
                            </span>
                        </div>
                    </div>

                    <!-- Visual Timeline -->
                    <div class="space-y-4 border-l-2 border-indigo-200 dark:border-indigo-800 ml-3 pl-4">
                        <!-- Step 1: Receiving -->
                        <div class="relative">
                            <div class="absolute -left-[23px] top-0.5 w-3.5 h-3.5 rounded-full bg-indigo-600 border-2 border-white dark:border-slate-800"></div>
                            <div class="font-bold text-slate-900 dark:text-white">1. Stock Entry & Reception</div>
                            <div class="text-slate-600 dark:text-slate-300 mt-0.5">
                                <span v-if="selectedUnit.purchase_order">
                                    Received via PO #{{ selectedUnit.purchase_order.po_number }} from {{ selectedUnit.purchase_order.vendor?.name || 'Vendor' }}
                                </span>
                                <span v-else>Direct Stock Creation</span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                Date: {{ dayjs(selectedUnit.created_at).format('MMM D, YYYY h:mm A') }}
                            </div>
                        </div>

                        <!-- Step 2: Storage -->
                        <div class="relative">
                            <div class="absolute -left-[23px] top-0.5 w-3.5 h-3.5 rounded-full bg-emerald-600 border-2 border-white dark:border-slate-800"></div>
                            <div class="font-bold text-slate-900 dark:text-white">2. Current Location & Status</div>
                            <div class="text-slate-600 dark:text-slate-300 mt-0.5">
                                Location: <strong>{{ selectedUnit.location === 'front_store' ? 'Front Store' : 'Back Store' }}</strong>
                                (Status: <span class="uppercase font-bold">{{ selectedUnit.status }}</span>)
                            </div>
                        </div>

                        <!-- Step 3: Sale (If sold) -->
                        <div class="relative">
                            <div
                                class="absolute -left-[23px] top-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800"
                                :class="selectedUnit.sale ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600'"
                            ></div>
                            <div class="font-bold text-slate-900 dark:text-white">3. Point of Sale & Customer Delivery</div>
                            <div v-if="selectedUnit.sale" class="text-slate-600 dark:text-slate-300 mt-0.5 space-y-0.5">
                                <div>Receipt #: <strong class="font-mono">{{ selectedUnit.sale.receipt_id }}</strong></div>
                                <div>Customer: <strong>{{ selectedUnit.sale.customer?.name || 'Walk-in Customer' }}</strong></div>
                                <div v-if="selectedUnit.sale.customer?.phone">Phone: {{ selectedUnit.sale.customer.phone }}</div>
                                <div>Cashier: {{ selectedUnit.sale.user?.name || '—' }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    Sold on: {{ dayjs(selectedUnit.sold_at || selectedUnit.sale.created_at).format('MMM D, YYYY h:mm A') }}
                                </div>
                            </div>
                            <div v-else class="text-slate-400 italic mt-0.5">
                                Unit is currently unsold in store inventory.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3.5 bg-slate-50 dark:bg-slate-750 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <button
                        @click="showDetailModal = false"
                        class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-xl transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
