<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import CreateBatchModal from '@/Components/CreateBatchModal.vue'
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    item: { type: Object, required: true },
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')
const alertDays = computed(() => Number(page.props.pos_settings?.expiry_alert_days || 30))
const { format } = useCurrency()

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingBatch = ref(null)

const batches = computed(() => props.item.batches || [])

// Total active batch count
const totalBatchQty = computed(() => {
    return batches.value
        .filter(b => b.status === 'active' && Number(b.quantity) > 0)
        .reduce((sum, b) => sum + Number(b.quantity || 0), 0)
})

const actualStock = computed(() => {
    return Number(props.item.front_store_qty || 0) + Number(props.item.back_store_qty || 0)
})

const variance = computed(() => {
    return actualStock.value - totalBatchQty.value
})

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
    <div class="space-y-6">
        <!-- 1. Variance Indicator Card (§6.1) -->
        <div
            class="rounded-2xl border p-5 shadow-xs transition-all flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
            :class="{
                'bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/60': variance === 0,
                'bg-amber-50/60 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/60': variance > 0,
                'bg-rose-50/60 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800/60': variance < 0
            }"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span
                        class="w-2.5 h-2.5 rounded-full"
                        :class="{
                            'bg-emerald-500 animate-pulse': variance === 0,
                            'bg-amber-500': variance > 0,
                            'bg-rose-500': variance < 0
                        }"
                    ></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        Batch Tracking & Stock Variance
                    </h3>
                </div>

                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                    <span v-if="variance === 0" class="text-emerald-700 dark:text-emerald-400 font-bold">
                        Synced: Batch total ({{ totalBatchQty }}) matches total physical stock ({{ actualStock }} units).
                    </span>
                    <span v-else-if="variance > 0" class="text-amber-700 dark:text-amber-400 font-bold">
                        Batch total: {{ totalBatchQty }} / Actual stock: {{ actualStock }} — {{ variance }} untracked unit{{ variance > 1 ? 's' : '' }}.
                    </span>
                    <span v-else class="text-rose-700 dark:text-rose-400 font-bold">
                        Batch total: {{ totalBatchQty }} / Actual stock: {{ actualStock }} — {{ Math.abs(variance) }} excess batch unit{{ Math.abs(variance) > 1 ? 's' : '' }}.
                    </span>
                </p>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ variance === 0
                        ? 'All stock on hand is accounted for across active expiring batches.'
                        : 'Stock changes without a batch creation or adjustment will display as untracked stock variance.'
                    }}
                </p>
            </div>

            <!-- Action Button -->
            <button
                type="button"
                @click="showCreateModal = true"
                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-900/20 transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create New Batch
            </button>
        </div>

        <!-- 2. Batches Table -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Active & Past Batches</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sorted by soonest expiry date (FEFO priority)</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    {{ batches.length }} batch{{ batches.length === 1 ? '' : 'es' }} recorded
                </span>
            </div>

            <div v-if="!batches.length" class="p-8 text-center text-slate-400 space-y-3">
                <div class="text-3xl">📦</div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No batches created for this item yet.</p>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                    Click "Create New Batch" above to record expiry dates, lot numbers, and quantities.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                            <th class="px-4 py-3">Batch / Lot #</th>
                            <th class="px-4 py-3">Expiry Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Remaining Qty</th>
                            <th class="px-4 py-3 text-right">Cost Price</th>
                            <th class="px-4 py-3">Received Date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr
                            v-for="batch in batches"
                            :key="batch.id"
                            class="hover:bg-slate-50/80 dark:hover:bg-slate-750 transition"
                        >
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                {{ batch.batch_label || '—' }}
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
                            <td class="px-4 py-3 text-right space-x-2">
                                <button
                                    type="button"
                                    @click="openEditBatch(batch)"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="batch.status !== 'cleared'"
                                    type="button"
                                    @click="clearBatch(batch)"
                                    class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-semibold"
                                    title="Mark batch as cleared/disposed"
                                >
                                    Clear
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Reusable Create Batch Modal -->
        <CreateBatchModal
            v-model="showCreateModal"
            :item="{ id: item.id, item_name: item.item_name, added_qty: item.front_store_qty, cost_price: item.buy_price }"
            @created="onBatchCreated"
        />

        <!-- 4. Edit Batch Modal -->
        <div
            v-if="showEditModal && editingBatch"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fade-in"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Edit Batch</h3>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
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
                        <button type="button" @click="showEditModal = false" class="flex-1 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
