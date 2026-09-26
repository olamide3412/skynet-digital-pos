<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import dayjs from 'dayjs'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    item: { type: Object, default: () => ({}) }, // { id, item_name, added_qty, cost_price, batch_label }
    queue: { type: Array, default: () => [] },   // Optional queue of multiple items (e.g. from PO receive)
})

const emit = defineEmits(['update:modelValue', 'created', 'declined', 'finished'])

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')

// Queue management
const queueIndex = ref(0)
const currentItem = computed(() => {
    if (props.queue && props.queue.length > 0) {
        return props.queue[queueIndex.value] || {}
    }
    return props.item || {}
})

const isAccepted = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')

const form = ref({
    item_id: '',
    batch_label: '',
    quantity: 1,
    expiry_date: '',
    received_date: dayjs().format('YYYY-MM-DD'),
    cost_price: '',
})

function initForm(item) {
    if (!item || !item.item_id && !item.id) return
    form.value = {
        item_id: item.item_id || item.id,
        batch_label: item.batch_label || '',
        quantity: item.added_qty || item.quantity || 1,
        expiry_date: '',
        received_date: dayjs().format('YYYY-MM-DD'),
        cost_price: item.cost_price !== undefined ? item.cost_price : '',
    }
    isAccepted.value = false
    errorMessage.value = ''
}

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        queueIndex.value = 0
        initForm(currentItem.value)
    }
})

watch(currentItem, (newItem) => {
    if (newItem && (newItem.id || newItem.item_id)) {
        initForm(newItem)
    }
})

function decline() {
    emit('declined', currentItem.value)
    nextOrClose()
}

function nextOrClose() {
    if (props.queue && props.queue.length > 0 && queueIndex.value < props.queue.length - 1) {
        queueIndex.value++
    } else {
        emit('update:modelValue', false)
        emit('finished')
    }
}

async function submitBatch() {
    if (!form.value.expiry_date) {
        errorMessage.value = 'Please select a valid expiry date.'
        return
    }

    if (!form.value.quantity || form.value.quantity < 1) {
        errorMessage.value = 'Batch quantity must be at least 1.'
        return
    }

    isSubmitting.value = true
    errorMessage.value = ''

    try {
        const response = await axios.post(route('pos.batches.store', { branch: branchSlug.value }), {
            item_id: form.value.item_id,
            batch_label: form.value.batch_label,
            quantity: form.value.quantity,
            expiry_date: form.value.expiry_date,
            received_date: form.value.received_date,
            cost_price: form.value.cost_price,
        })

        emit('created', response.data?.batch || form.value)
        nextOrClose()
    } catch (err) {
        console.error('Failed to create batch:', err)
        errorMessage.value = err.response?.data?.message || 'Failed to save batch. Please check your inputs.'
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity animate-fade-in"
    >
        <div
            class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transform transition-all"
        >
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        💊
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            {{ isAccepted ? 'Create Batch Details' : 'Batch Tracking Available' }}
                        </h3>
                        <p v-if="queue && queue.length > 1" class="text-[11px] text-slate-500 dark:text-slate-400">
                            Item {{ queueIndex + 1 }} of {{ queue.length }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="decline"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition p-1"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-4">
                <!-- Item Banner -->
                <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-700/50 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Item Added</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ currentItem.item_name }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-500 dark:text-slate-400 block">Stock In Qty</span>
                        <span class="text-sm font-mono font-bold text-emerald-600 dark:text-emerald-400">+{{ currentItem.added_qty || currentItem.quantity || 0 }}</span>
                    </div>
                </div>

                <!-- Error Notice -->
                <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-600 dark:text-rose-400">
                    {{ errorMessage }}
                </div>

                <!-- STEP 1: Prompt State -->
                <div v-if="!isAccepted" class="space-y-3 py-2 text-center">
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        This item has <strong>batch & expiry tracking enabled</strong>. Would you like to record a specific batch number and expiry date for this stock?
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        If declined, the stock increase remains saved safely, and untracked stock will be visible on the Batches overview.
                    </p>

                    <div class="pt-4 flex gap-3">
                        <button
                            type="button"
                            @click="decline"
                            class="flex-1 py-2.5 px-4 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                        >
                            Skip / Untracked
                        </button>
                        <button
                            type="button"
                            @click="isAccepted = true"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-900/20 transition flex items-center justify-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Yes, Create Batch
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Expanded Form State -->
                <form v-else @submit.prevent="submitBatch" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Batch / Lot Number -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Batch / Lot # <span class="text-slate-400 text-[10px] font-normal">(Optional)</span>
                            </label>
                            <input
                                v-model="form.batch_label"
                                type="text"
                                placeholder="e.g. LOT-2026-A"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs transition"
                            />
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Quantity for Batch *
                            </label>
                            <input
                                v-model.number="form.quantity"
                                type="number"
                                min="1"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-mono transition"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Expiry Date -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Expiry Date *
                            </label>
                            <input
                                v-model="form.expiry_date"
                                type="date"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs transition font-mono"
                            />
                        </div>

                        <!-- Received Date -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Received Date
                            </label>
                            <input
                                v-model="form.received_date"
                                type="date"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs transition font-mono"
                            />
                        </div>
                    </div>

                    <!-- Cost Price -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Cost Price for this Batch <span class="text-slate-400 text-[10px] font-normal">(Optional)</span>
                        </label>
                        <input
                            v-model.number="form.cost_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-mono transition"
                        />
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 flex gap-3">
                        <button
                            type="button"
                            @click="isAccepted = false"
                            class="py-2.5 px-4 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            @click="decline"
                            class="py-2.5 px-4 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-500 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                        >
                            Skip
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md shadow-emerald-900/20 transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                        >
                            <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ isSubmitting ? 'Saving Batch...' : 'Confirm & Save Batch' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
