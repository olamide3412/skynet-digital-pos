<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

defineOptions({ layout: null })

const props = defineProps({
    branch:           Object,
    items:            Array,
    categories:       Array,
    selectedCategory: Number,
    generatedAt:      String,
    currencySymbol:   { type: String, default: '₦' },
})

// ── Interactive Filters (Screen Only) ─────────────────────────────────────────
const searchQuery     = ref('')
const selectedCat     = ref(props.selectedCategory ?? '')
const showWholesale   = ref(true)
const showBarcodes    = ref(true)
const showStockQty    = ref(true)
const showSecondaryUnits = ref(true)
const groupByCat      = ref(true)

function formatPrice(val) {
    if (val === null || val === undefined || isNaN(val)) return '—'
    const num = Number(val)
    return props.currencySymbol + num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Filtered Item List ────────────────────────────────────────────────────────
const filteredItems = computed(() => {
    let list = props.items || []

    if (selectedCat.value) {
        list = list.filter(i => i.category_id === Number(selectedCat.value))
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase()
        list = list.filter(i => {
            const name = (i.item_name || '').toLowerCase()
            const barcode = (i.barcode_number || '').toLowerCase()
            const cat = (i.category?.name || '').toLowerCase()
            return name.includes(q) || barcode.includes(q) || cat.includes(q)
        })
    }

    return list
})

// ── Grouped by Category ───────────────────────────────────────────────────────
const groupedItems = computed(() => {
    if (!groupByCat.value) {
        return [{ categoryName: 'All Items', items: filteredItems.value }]
    }

    const groups = {}
    filteredItems.value.forEach(item => {
        const catName = item.category?.name || 'Uncategorized'
        if (!groups[catName]) {
            groups[catName] = []
        }
        groups[catName].push(item)
    })

    return Object.keys(groups).sort().map(catName => ({
        categoryName: catName,
        items: groups[catName],
    }))
})

function triggerPrint() {
    window.print()
}

onMounted(() => {
    // If URL contains auto_print=1, trigger print automatically on load
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.get('auto_print') === '1') {
        setTimeout(() => {
            window.print()
        }, 400)
    }
})
</script>

<template>
    <Head :title="`Price List - ${branch?.name || 'Store Catalog'}`" />

    <div class="min-h-screen bg-slate-100 print:bg-white text-slate-800 font-sans text-xs antialiased">
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- SCREEN-ONLY CONTROL TOOLBAR (HIDDEN IN PRINT)                       -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <header class="no-print sticky top-0 z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-sm px-4 sm:px-6 py-3 transition-colors">
            <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3">
                <!-- Left: Title & Quick Stats -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-slate-900 dark:text-white leading-tight">Printable Price List & Catalog</h1>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ filteredItems.length }} items visible • Offline customer reference
                        </p>
                    </div>
                </div>

                <!-- Center: Interactive Filter Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search -->
                    <div class="relative w-44 sm:w-56">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search name or barcode…"
                            class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg text-xs border border-slate-200 dark:border-slate-700 outline-none focus:border-indigo-500"
                        />
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Category Selector -->
                    <select
                        v-model="selectedCat"
                        class="bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 outline-none focus:border-indigo-500"
                    >
                        <option value="">All Categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>

                    <!-- Toggles -->
                    <label class="flex items-center gap-1.5 text-[11px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer bg-slate-100 dark:bg-slate-800 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">
                        <input type="checkbox" v-model="showWholesale" class="rounded accent-indigo-600 text-xs" />
                        <span>Wholesale</span>
                    </label>

                    <label class="flex items-center gap-1.5 text-[11px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer bg-slate-100 dark:bg-slate-800 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">
                        <input type="checkbox" v-model="showBarcodes" class="rounded accent-indigo-600 text-xs" />
                        <span>Barcodes</span>
                    </label>

                    <label class="flex items-center gap-1.5 text-[11px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer bg-slate-100 dark:bg-slate-800 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">
                        <input type="checkbox" v-model="showStockQty" class="rounded accent-indigo-600 text-xs" />
                        <span>Stock Qty</span>
                    </label>

                    <label class="hidden md:flex items-center gap-1.5 text-[11px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer bg-slate-100 dark:bg-slate-800 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">
                        <input type="checkbox" v-model="groupByCat" class="rounded accent-indigo-600 text-xs" />
                        <span>Group Categories</span>
                    </label>
                </div>

                <!-- Right: Action Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        @click="triggerPrint"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print Document</span>
                    </button>

                    <button
                        onclick="window.close()"
                        class="px-3 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl transition font-medium cursor-pointer"
                        title="Close this window"
                    >
                        ✕ Close
                    </button>
                </div>
            </div>
        </header>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- PRINTABLE CATALOG SHEET                                             -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <main class="max-w-5xl mx-auto my-6 print:my-0 bg-white shadow-xl print:shadow-none print:border-none border border-slate-200 rounded-2xl print:rounded-none p-6 sm:p-10 print:p-0 transition-all">
            <!-- ── Document Header ───────────────────────────────────────────── -->
            <div class="border-b-2 border-slate-900 pb-4 mb-4">
                <div class="flex items-start justify-between gap-4">
                    <!-- Branch Info -->
                    <div class="flex items-start gap-3.5">
                        <img
                            v-if="branch?.logo_url"
                            :src="branch.logo_url"
                            alt="Logo"
                            class="w-14 h-14 rounded-lg object-contain border border-slate-200 print:border-slate-300 p-1"
                        />
                        <div>
                            <h2 class="text-xl font-black text-slate-900 tracking-tight uppercase leading-tight">
                                {{ branch?.name || 'Skynet Digital POS' }}
                            </h2>
                            <p v-if="branch?.address" class="text-xs text-slate-600 font-medium mt-0.5 max-w-md">
                                📍 {{ branch.address }}
                            </p>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-[11px] text-slate-500 mt-1">
                                <span v-if="branch?.phone">📞 {{ branch.phone }}</span>
                                <span v-if="branch?.email">✉️ {{ branch.email }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Document Title & Timestamp -->
                    <div class="text-right flex-shrink-0">
                        <span class="inline-block px-2.5 py-1 bg-slate-900 text-white text-[10px] font-black uppercase tracking-wider rounded">
                            Official Price Sheet
                        </span>
                        <h3 class="text-base font-extrabold text-slate-900 mt-1 uppercase tracking-wide">
                            Product Price Catalog
                        </h3>
                        <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                            Generated: {{ generatedAt }}
                        </p>
                        <p class="text-[10px] text-slate-400 font-medium">
                            Currency: {{ currencySymbol }} (NGN)
                        </p>
                    </div>
                </div>

                <!-- Emergency Notice Banner -->
                <div class="mt-3.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded text-[11px] text-slate-600 flex items-center justify-between">
                    <span>
                        <strong>Notice:</strong> Authorized pricing sheet for offline customer inquiries and manual sales continuity during network downtime.
                    </span>
                    <span class="font-bold text-slate-800">
                        Total Items: {{ filteredItems.length }}
                    </span>
                </div>
            </div>

            <!-- ── Product Listings (Grouped or Flat) ─────────────────────────── -->
            <div class="space-y-6 print:space-y-4">
                <div
                    v-for="(group, gIdx) in groupedItems"
                    :key="group.categoryName"
                    class="category-block"
                >
                    <!-- Category Header Banner -->
                    <div
                        v-if="groupByCat"
                        class="flex items-center justify-between bg-slate-900 text-white px-3 py-1.5 rounded-t font-bold text-xs uppercase tracking-wide print:bg-slate-800"
                    >
                        <span>📁 {{ group.categoryName }}</span>
                        <span class="text-[11px] font-normal text-slate-300">{{ group.items.length }} {{ group.items.length === 1 ? 'item' : 'items' }}</span>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-x-auto border border-slate-300 border-t-0 rounded-b">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 print:bg-slate-200 text-slate-800 font-bold uppercase text-[10px] tracking-wider border-b border-slate-300">
                                    <th class="py-1.5 px-2.5 w-10 text-center">#</th>
                                    <th class="py-1.5 px-3">Item Description</th>
                                    <th class="py-1.5 px-3 w-24">Base Unit</th>
                                    <th class="py-1.5 px-3 w-28 text-right">Retail Price</th>
                                    <th v-if="showWholesale" class="py-1.5 px-3 w-28 text-right">Wholesale</th>
                                    <th v-if="showStockQty" class="py-1.5 px-3 w-24 text-center">In Stock</th>
                                    <th class="py-1.5 px-3 w-24 text-center">Shelf / Row</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-800">
                                <template v-for="(item, idx) in group.items" :key="item.id">
                                    <!-- Primary Item Row -->
                                    <tr class="hover:bg-slate-50 print:hover:bg-transparent transition-colors even:bg-slate-50/50 print:even:bg-slate-50/40">
                                        <!-- Row Number -->
                                        <td class="py-2 px-2.5 text-center font-mono text-slate-500 text-[11px]">
                                            {{ idx + 1 }}
                                        </td>

                                        <!-- Item Name & Barcode -->
                                        <td class="py-2 px-3">
                                            <div class="font-bold text-slate-900 text-xs leading-snug">
                                                {{ item.item_name }}
                                            </div>
                                            <div v-if="showBarcodes && item.barcode_number" class="text-[10px] font-mono text-slate-500 tracking-wider">
                                                SKU: {{ item.barcode_number }}
                                            </div>
                                        </td>

                                        <!-- Base Unit of Measure -->
                                        <td class="py-2 px-3">
                                            <span class="inline-block px-2 py-0.5 bg-slate-100 print:bg-transparent print:border print:border-slate-300 rounded text-[11px] font-semibold text-slate-700">
                                                {{ item.unit_label || 'Unit' }}
                                            </span>
                                        </td>

                                        <!-- Retail Price -->
                                        <td class="py-2 px-3 text-right font-bold font-mono text-xs text-slate-900">
                                            {{ formatPrice(item.price) }}
                                        </td>

                                        <!-- Wholesale Price (Optional) -->
                                        <td v-if="showWholesale" class="py-2 px-3 text-right font-mono text-xs text-slate-700">
                                            {{ Number(item.wholesale_price) > 0 ? formatPrice(item.wholesale_price) : '—' }}
                                        </td>

                                        <!-- Current Available Stock Qty -->
                                        <td v-if="showStockQty" class="py-2 px-3 text-center font-mono text-xs">
                                            <span
                                                :class="[
                                                    (item.front_store_qty ?? 0) <= 0
                                                        ? 'text-red-600 font-bold'
                                                        : (item.front_store_qty ?? 0) <= 5
                                                            ? 'text-amber-600 font-semibold'
                                                            : 'text-slate-800 font-medium'
                                                ]"
                                            >
                                                {{ item.front_store_qty ?? 0 }}
                                            </span>
                                        </td>

                                        <!-- Shelf / Storage Location -->
                                        <td class="py-2 px-3 text-center text-[11px] text-slate-600">
                                            {{ item.group_address?.name || '—' }}
                                        </td>
                                    </tr>

                                    <!-- Secondary Packaging Units Rows (If configured) -->
                                    <template v-if="showSecondaryUnits && item.units && item.units.length">
                                        <tr
                                            v-for="u in item.units"
                                            :key="'unit-' + u.id"
                                            class="bg-indigo-50/40 print:bg-slate-100/60 text-[11px] text-slate-700 border-t border-dashed border-slate-200"
                                        >
                                            <td></td>
                                            <td class="py-1.5 px-3 pl-6">
                                                <span class="text-indigo-600 print:text-slate-700 font-bold">↳ Package:</span>
                                                <span class="font-medium ml-1">{{ u.unit_name }}</span>
                                                <span class="text-slate-400 text-[10px] ml-1.5">({{ u.quantity_in_base_units }} × {{ item.unit_label || 'base' }})</span>
                                            </td>
                                            <td class="py-1.5 px-3 font-semibold text-slate-600">
                                                {{ u.unit_name }}
                                            </td>
                                            <td class="py-1.5 px-3 text-right font-bold font-mono text-slate-900">
                                                {{ formatPrice(u.selling_price) }}
                                            </td>
                                            <td v-if="showWholesale" class="py-1.5 px-3 text-right font-mono text-slate-700">
                                                {{ Number(u.wholesale_price) > 0 ? formatPrice(u.wholesale_price) : formatPrice(u.selling_price) }}
                                            </td>
                                            <td v-if="showStockQty" class="py-1.5 px-3 text-center text-slate-400 font-mono text-[10px]">
                                                calc
                                            </td>
                                            <td class="py-1.5 px-3 text-center text-slate-400 text-[10px]">
                                                —
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!filteredItems.length" class="text-center py-12 text-slate-400">
                    <p class="text-sm font-semibold">No products found matching the selected filter criteria.</p>
                </div>
            </div>

            <!-- ── Document Footer & Sign-off ─────────────────────────────────── -->
            <div class="mt-8 pt-4 border-t-2 border-slate-900 text-xs text-slate-600 space-y-3 print:pt-3">
                <div class="flex items-center justify-between text-[11px]">
                    <span>
                        Prepared for: <strong>{{ branch?.name }}</strong>
                    </span>
                    <span>
                        Verified as of: <strong>{{ generatedAt }}</strong>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-8 pt-6 pb-2 text-[11px]">
                    <div class="border-t border-slate-400 pt-1">
                        <span class="font-bold text-slate-800">Store Cashier / Sales Representative</span>
                        <p class="text-[10px] text-slate-400">Name & Signature</p>
                    </div>
                    <div class="border-t border-slate-400 pt-1 text-right">
                        <span class="font-bold text-slate-800">Branch Supervisor / Manager</span>
                        <p class="text-[10px] text-slate-400">Authorized Stamp / Date</p>
                    </div>
                </div>

                <p class="text-[10px] text-slate-400 text-center italic">
                    SkyNet POS Terminal • Official Product & Price Catalog • Prices subject to change per store policies.
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 10mm 12mm 12mm 12mm;
        size: A4 portrait;
    }

    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .no-print {
        display: none !important;
    }

    .category-block {
        page-break-inside: auto;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    thead {
        display: table-header-group;
    }

    tfoot {
        display: table-footer-group;
    }
}
</style>
