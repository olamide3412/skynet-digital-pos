<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    items:      Object,
    summary:    Object,
    categories: Array,
    filters:    Object,
})

const activeTab        = ref(props.filters?.tab || 'reorder')
const searchFilter     = ref(props.filters?.search || '')
const categoryFilter   = ref(props.filters?.category_id || '')

const editingItem      = ref(null)
const editForm         = ref({ reorder_point: 10, reorder_unit: 'unit' })

function setTab(tabName) {
    activeTab.value = tabName
    applyFilters()
}

function applyFilters() {
    const branchSlug = route().params.branch || 'felix-enterprise'
    router.get(route('pos.inventory.reorder-points', { branch: branchSlug }), {
        tab:         activeTab.value,
        search:      searchFilter.value || undefined,
        category_id: categoryFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true })
}

function resetFilters() {
    activeTab.value      = 'reorder'
    searchFilter.value   = ''
    categoryFilter.value = ''
    applyFilters()
}

function openEditModal(item) {
    editingItem.value = item
    editForm.value = {
        reorder_point: item.reorder_point ?? 10,
        reorder_unit:  item.reorder_unit || 'unit',
    }
}

function saveReorderPoint() {
    if (!editingItem.value) return
    const branchSlug = route().params.branch || 'felix-enterprise'
    router.post(route('pos.inventory.reorder-points.update', { branch: branchSlug, item: editingItem.value.id }), editForm.value, {
        onSuccess: () => {
            editingItem.value = null
        }
    })
}

function getStatusBadge(item) {
    const totalStock = (item.front_store_qty || 0) + (item.back_store_qty || 0)
    if (totalStock <= 0) {
        return { label: 'CRITICAL / OUT OF STOCK', class: 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 animate-pulse' }
    }
    if (item.needs_reorder) {
        return { label: 'REORDER NOW', class: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30' }
    }
    return { label: 'ADEQUATE', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Reorder Points & Stock Replenishment
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Identify items reaching critical thresholds and manage stock replenishment triggers.</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="resetFilters"
                    class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition"
                >
                    Reset Filters
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Reorder Needed -->
                <div
                    @click="setTab('reorder')"
                    :class="[
                        'p-5 rounded-2xl border cursor-pointer transition-all shadow-xs flex items-center justify-between',
                        activeTab === 'reorder'
                            ? 'bg-amber-500/10 border-amber-500 ring-2 ring-amber-500/20'
                            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:border-amber-400'
                    ]"
                >
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Reorder Required</span>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5 font-mono">{{ summary.reorder_count }}</div>
                        <span class="text-[11px] text-slate-500 font-medium">Items below reorder point</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg">
                        ⚠️
                    </div>
                </div>

                <!-- Critical Out of Stock -->
                <div
                    @click="setTab('critical')"
                    :class="[
                        'p-5 rounded-2xl border cursor-pointer transition-all shadow-xs flex items-center justify-between',
                        activeTab === 'critical'
                            ? 'bg-rose-500/10 border-rose-500 ring-2 ring-rose-500/20'
                            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:border-rose-400'
                    ]"
                >
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Critical (0 Stock)</span>
                        <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-0.5 font-mono">{{ summary.critical_count }}</div>
                        <span class="text-[11px] text-slate-500 font-medium">Out of stock completely</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-lg">
                        🚫
                    </div>
                </div>

                <!-- Adequate Stock -->
                <div
                    @click="setTab('adequate')"
                    :class="[
                        'p-5 rounded-2xl border cursor-pointer transition-all shadow-xs flex items-center justify-between',
                        activeTab === 'adequate'
                            ? 'bg-emerald-500/10 border-emerald-500 ring-2 ring-emerald-500/20'
                            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:border-emerald-400'
                    ]"
                >
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Adequate Stock</span>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5 font-mono">{{ summary.adequate_count }}</div>
                        <span class="text-[11px] text-slate-500 font-medium">Sufficient stock levels</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg">
                        ✅
                    </div>
                </div>

                <!-- Total Items -->
                <div
                    @click="setTab('all')"
                    :class="[
                        'p-5 rounded-2xl border cursor-pointer transition-all shadow-xs flex items-center justify-between',
                        activeTab === 'all'
                            ? 'bg-theme/10 border-theme ring-2 ring-theme/20'
                            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:border-theme'
                    ]"
                >
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Items</span>
                        <div class="text-2xl font-black text-slate-900 dark:text-white mt-0.5 font-mono">{{ summary.total_count }}</div>
                        <span class="text-[11px] text-slate-500 font-medium">All active product items</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-theme/20 text-theme flex items-center justify-center font-bold text-lg">
                        📦
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 shadow-xs space-y-4">
                <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                    <!-- Tab Buttons -->
                    <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                        <button
                            @click="setTab('reorder')"
                            :class="['px-3.5 py-2 rounded-xl font-bold text-xs transition whitespace-nowrap', activeTab === 'reorder' ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200']"
                        >
                            Reorder Needed ({{ summary.reorder_count }})
                        </button>
                        <button
                            @click="setTab('critical')"
                            :class="['px-3.5 py-2 rounded-xl font-bold text-xs transition whitespace-nowrap', activeTab === 'critical' ? 'bg-rose-500 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200']"
                        >
                            Critical Out of Stock ({{ summary.critical_count }})
                        </button>
                        <button
                            @click="setTab('adequate')"
                            :class="['px-3.5 py-2 rounded-xl font-bold text-xs transition whitespace-nowrap', activeTab === 'adequate' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200']"
                        >
                            Adequate ({{ summary.adequate_count }})
                        </button>
                        <button
                            @click="setTab('all')"
                            :class="['px-3.5 py-2 rounded-xl font-bold text-xs transition whitespace-nowrap', activeTab === 'all' ? 'bg-theme text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200']"
                        >
                            All Catalog ({{ summary.total_count }})
                        </button>
                    </div>

                    <!-- Search & Category Filters -->
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <select
                            v-model="categoryFilter"
                            @change="applyFilters"
                            class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs"
                        >
                            <option value="">All Categories</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <input
                            type="text"
                            v-model="searchFilter"
                            @keyup.enter="applyFilters"
                            placeholder="Search item or barcode…"
                            class="w-full md:w-48 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs"
                        />
                    </div>
                </div>
            </div>

            <!-- Items Table Card -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                                <th class="py-3.5 px-4">Item & Barcode</th>
                                <th class="py-3.5 px-4">Category</th>
                                <th class="py-3.5 px-4">Front Store</th>
                                <th class="py-3.5 px-4">Back Store</th>
                                <th class="py-3.5 px-4">Total Base Stock</th>
                                <th class="py-3.5 px-4">Reorder Point</th>
                                <th class="py-3.5 px-4">Units Needed</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                            <tr v-if="!items.data || items.data.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    No items found matching your reorder criteria.
                                </td>
                            </tr>
                            <tr
                                v-for="item in items.data"
                                :key="item.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                    {{ item.item_name }}
                                    <span v-if="item.barcode_number" class="block text-[11px] font-mono text-slate-400 font-normal">
                                        {{ item.barcode_number }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                    {{ item.category ? item.category.name : '—' }}
                                </td>
                                <td class="py-3.5 px-4 font-mono font-semibold text-slate-800 dark:text-slate-200">
                                    {{ item.front_store_qty }} {{ item.unit_label }}s
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-400">
                                    {{ item.back_store_qty }} {{ item.unit_label }}s
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ (item.front_store_qty || 0) + (item.back_store_qty || 0) }} base units
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-slate-900 dark:text-white">
                                            {{ item.reorder_point || 10 }} {{ item.reorder_unit || 'unit' }}(s)
                                        </span>
                                        <button
                                            @click="openEditModal(item)"
                                            class="text-slate-400 hover:text-theme transition"
                                            title="Edit Reorder Point"
                                        >
                                            ✏️
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-rose-600 dark:text-rose-400">
                                    <span v-if="item.reorder_deficit_base_units > 0">
                                        +{{ item.reorder_deficit_base_units }} units
                                    </span>
                                    <span v-else class="text-slate-400 font-normal">0</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span :class="['px-2.5 py-1 rounded-full text-[10px] font-bold border', getStatusBadge(item).class]">
                                        {{ getStatusBadge(item).label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                    <button
                                        @click="openEditModal(item)"
                                        class="px-2.5 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-[11px] rounded-lg transition"
                                    >
                                        Threshold
                                    </button>
                                    <Link
                                        :href="route('pos.purchases.create', { branch: $page.props.current_branch?.slug || 'felix-enterprise' })"
                                        class="px-2.5 py-1.5 bg-theme hover:opacity-90 text-white font-bold text-[11px] rounded-lg transition inline-block shadow-2xs"
                                    >
                                        Reorder PO
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="items.links && items.links.length > 3" class="p-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-xs text-slate-500">Showing {{ items.from }} to {{ items.to }} of {{ items.total }} items</span>
                    <div class="flex gap-1">
                        <template v-for="(link, i) in items.links" :key="i">
                            <Component
                                :is="link.url ? Link : 'span'"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                    link.active ? 'bg-theme text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200',
                                    !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                                ]"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Threshold Modal -->
        <div v-if="editingItem" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                        Set Reorder Point: {{ editingItem.item_name }}
                    </h3>
                    <button @click="editingItem = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-white font-bold text-lg">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Reorder Point Threshold Quantity</label>
                        <input
                            type="number"
                            min="0"
                            v-model.number="editForm.reorder_point"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm"
                            placeholder="e.g. 10"
                        />
                        <p class="text-[11px] text-slate-500 mt-1">When total stock drops to or below this level, an automated reorder alert will trigger.</p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Threshold Unit</label>
                        <select
                            v-model="editForm.reorder_unit"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm"
                        >
                            <option value="unit">{{ editingItem.unit_label || 'Unit' }}</option>
                            <option value="pack">{{ editingItem.pack_label || 'Pack' }} ({{ editingItem.units_per_pack }} units)</option>
                            <option value="carton">{{ editingItem.carton_label || 'Carton' }} ({{ (editingItem.units_per_pack || 1) * (editingItem.packs_per_carton || 1) }} units)</option>
                        </select>
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-200 dark:border-slate-700">
                    <button @click="editingItem = null" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs rounded-xl hover:bg-slate-300">
                        Cancel
                    </button>
                    <button @click="saveReorderPoint" class="px-4 py-2 bg-theme text-white font-bold text-xs rounded-xl hover:opacity-90 shadow-md">
                        Save Threshold
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
