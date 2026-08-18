<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import debounce from 'lodash/debounce'

defineOptions({ layout: PosLayout })

const props = defineProps({
    conflicts: Object,
    filters:   Object,
    stats:     Object,
})

const { format } = useCurrency()

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const conflictType = ref(props.filters.conflict_type || '')

const selectedConflict = ref(null)
const showModal = ref(false)
const resolutionStatus = ref('resolved')
const resolutionNotes = ref('')
const isSubmitting = ref(false)

function applyFilters() {
    router.get(
        route('pos.inventory.conflicts.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            conflict_type: conflictType.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}

const onSearchInput = debounce(() => {
    applyFilters()
}, 350)

function openResolveModal(conflict) {
    selectedConflict.value = conflict
    resolutionStatus.value = conflict.status === 'pending_review' ? 'resolved' : conflict.status
    resolutionNotes.value = conflict.resolution_notes || ''
    showModal.value = true
}

function submitResolution() {
    if (!selectedConflict.value) return
    isSubmitting.value = true

    router.post(
        route('pos.inventory.conflicts.resolve', { conflict: selectedConflict.value.id }),
        {
            status: resolutionStatus.value,
            resolution_notes: resolutionNotes.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                selectedConflict.value = null
                isSubmitting.value = false
            },
            onError: () => {
                isSubmitting.value = false
            }
        }
    )
}
</script>

<template>
    <Head title="Stock Conflicts Review - Inventory" />

    <div class="p-6 space-y-6 max-w-7xl mx-auto">

        <!-- ── HEADER ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400">⚠️</span>
                    Stock Conflicts & Offline Reconciliation
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Review and resolve inventory shortfalls and duplicate IMEI sales that occurred while cashier terminals were offline.
                </p>
            </div>
        </div>

        <!-- ── STATS ROW ───────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pending Review</div>
                <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">
                    {{ stats?.pending || 0 }}
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Resolved</div>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ stats?.resolved || 0 }}
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dismissed</div>
                <div class="text-2xl font-black text-slate-600 dark:text-slate-400 mt-1">
                    {{ stats?.dismissed || 0 }}
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Recorded</div>
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">
                    {{ stats?.total || 0 }}
                </div>
            </div>
        </div>

        <!-- ── FILTER BAR ──────────────────────────────────────────────────── -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[240px]">
                <input
                    v-model="search"
                    @input="onSearchInput"
                    type="text"
                    placeholder="Search by item name, IMEI, receipt #, or offline ID..."
                    class="w-full px-3.5 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                />
            </div>

            <div class="w-48">
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="resolved">Resolved</option>
                    <option value="dismissed">Dismissed</option>
                </select>
            </div>

            <div class="w-48">
                <select
                    v-model="conflictType"
                    @change="applyFilters"
                    class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none"
                >
                    <option value="">All Conflict Types</option>
                    <option value="stock_shortfall">Stock Shortfall 📉</option>
                    <option value="imei_already_sold">Duplicate IMEI Sold 📱</option>
                </select>
            </div>
        </div>

        <!-- ── CONFLICTS TABLE ─────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">Conflict Detail</th>
                            <th class="px-5 py-3.5">Type</th>
                            <th class="px-5 py-3.5">Sale & Cashier</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Date Synced</th>
                            <th class="px-5 py-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr v-if="conflicts.data.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                <span class="text-3xl block mb-2">🎉</span>
                                No inventory conflicts found matching your filter criteria.
                            </td>
                        </tr>
                        <tr
                            v-for="c in conflicts.data"
                            :key="c.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-750/50 transition-colors"
                        >
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ c.item_name }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    <span v-if="c.imei_or_device_id" class="font-mono text-purple-600 dark:text-purple-400 font-semibold">
                                        IMEI: {{ c.imei_or_device_id }}
                                    </span>
                                    <span v-else>
                                        Required: <strong class="text-rose-600 dark:text-rose-400">{{ c.requested_qty }}</strong> base units
                                        (Available: {{ c.available_qty_at_sync }})
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1.5',
                                        c.conflict_type === 'stock_shortfall'
                                            ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'
                                            : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                                    ]"
                                >
                                    <span>{{ c.conflict_type === 'stock_shortfall' ? '📉 Stock Shortfall' : '📱 Duplicate IMEI' }}</span>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ c.sale ? '#' + c.sale.receipt_id : 'Sale #' + c.sale_id }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ c.sale?.user?.name || 'Cashier' }} • {{ c.sale?.payment_method }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-xs font-bold capitalize',
                                        c.status === 'pending_review' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' :
                                        c.status === 'resolved' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' :
                                        'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20'
                                    ]"
                                >
                                    {{ c.status.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-500 dark:text-slate-400">
                                {{ new Date(c.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button
                                    @click="openResolveModal(c)"
                                    type="button"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 transition cursor-pointer"
                                >
                                    Review
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="conflicts.links && conflicts.links.length > 3" class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    Showing {{ conflicts.from || 0 }} to {{ conflicts.to || 0 }} of {{ conflicts.total }} conflicts
                </div>
                <div class="flex gap-1">
                    <button
                        v-for="(link, i) in conflicts.links"
                        :key="i"
                        @click="link.url && router.get(link.url)"
                        :disabled="!link.url || link.active"
                        v-html="link.label"
                        :class="[
                            'px-2.5 py-1 text-xs rounded-lg transition',
                            link.active ? 'bg-rose-600 text-white font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30'
                        ]"
                    />
                </div>
            </div>
        </div>

        <!-- ── RESOLUTION MODAL ────────────────────────────────────────────── -->
        <div v-if="showModal && selectedConflict" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-lg w-full overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base flex items-center gap-2">
                        <span>Resolve Stock Conflict</span>
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg">✕</button>
                </div>

                <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Item:</span>
                        <strong class="text-slate-800 dark:text-slate-200">{{ selectedConflict.item_name }}</strong>
                    </div>
                    <div class="flex justify-between" v-if="selectedConflict.imei_or_device_id">
                        <span class="text-slate-500">IMEI / Serial:</span>
                        <strong class="font-mono text-purple-600 dark:text-purple-400">{{ selectedConflict.imei_or_device_id }}</strong>
                    </div>
                    <div class="flex justify-between" v-else>
                        <span class="text-slate-500">Units Shortfall:</span>
                        <strong class="text-rose-600">{{ selectedConflict.requested_qty }} required ({{ selectedConflict.available_qty_at_sync }} was in stock)</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Receipt Ref:</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300">#{{ selectedConflict.sale?.receipt_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Offline Sale Ref:</span>
                        <span class="font-mono text-slate-400 text-[11px] truncate max-w-[200px]">{{ selectedConflict.offline_sale_id }}</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Status Outcome
                        </label>
                        <select
                            v-model="resolutionStatus"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none"
                        >
                            <option value="resolved">Resolved (Action taken, stock reconciled)</option>
                            <option value="dismissed">Dismissed (Ignored / Logged as acceptable)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Resolution Notes
                        </label>
                        <textarea
                            v-model="resolutionNotes"
                            rows="3"
                            placeholder="Enter notes on how this discrepancy was handled (e.g. physical stock count adjusted, supplier restock received)..."
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <button
                        @click="showModal = false"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitResolution"
                        :disabled="isSubmitting"
                        type="button"
                        class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition disabled:opacity-40 shadow-sm cursor-pointer"
                    >
                        {{ isSubmitting ? 'Saving...' : 'Save Resolution' }}
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
