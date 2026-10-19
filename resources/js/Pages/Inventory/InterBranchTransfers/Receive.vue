<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const page = usePage()
const currentBranchSlug = computed(() => {
    return page.props.current_branch?.slug
        || page.props.branch?.slug
        || page.props.auth?.user?.branch?.slug
        || ''
})

const props = defineProps({
    transfer: Object,
})

const form = useForm({
    to_location: 'back_store',
    receiver_notes: '',
    items: (props.transfer?.items || []).map(item => {
        const hasDest = !!item.destination_item_id
        const matched = item.matched_destination_item || null

        let linkAction = 'none'
        let existingId = null

        if (!hasDest) {
            if (matched) {
                linkAction = 'existing'
                existingId = matched.id
            } else {
                linkAction = 'copy'
                existingId = null
            }
        }

        return {
            line_id: item.id,
            item_name: item.source_item?.item_name || 'Item',
            barcode_number: item.source_item?.barcode_number || '',
            unit_label: item.source_item?.unit_label || 'pcs',
            quantity_sent: item.quantity_sent,
            quantity_received: item.quantity_sent,
            notes: item.notes || '',
            has_destination_item: hasDest,
            destination_item_name: item.destination_item?.item_name,
            matched_destination_item: matched,
            link_action: linkAction,
            existing_item_id: existingId,
            selected_custom_item: null,
            show_search_picker: false,
            search_query: '',
            search_results: [],
            is_searching: false,
        }
    }),
})

const updatingItemsCount = computed(() => {
    return form.items.filter(i => i.has_destination_item || i.link_action === 'existing').length
})

const creatingItemsCount = computed(() => {
    return form.items.filter(i => !i.has_destination_item && i.link_action === 'copy').length
})

const totalUnitsReceived = computed(() => {
    return form.items.reduce((sum, i) => sum + (Number(i.quantity_received) || 0), 0)
})

const totalShortfallCount = computed(() => {
    return form.items.reduce((sum, i) => sum + Math.max(0, (Number(i.quantity_sent) || 0) - (Number(i.quantity_received) || 0)), 0)
})

const lineSearchTimers = {}
const searchBranchItems = (item) => {
    if (!item.search_query || item.search_query.trim().length === 0) {
        item.search_results = []
        item.is_searching = false
        return
    }

    item.is_searching = true
    clearTimeout(lineSearchTimers[item.line_id])
    lineSearchTimers[item.line_id] = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('pos.api.items.search', { branch: currentBranchSlug.value }), {
                params: { q: item.search_query }
            })
            item.search_results = data || []
        } catch (e) {
            console.error('Error searching items', e)
        } finally {
            item.is_searching = false
        }
    }, 250)
}

const selectExistingItemForLine = (item, candidate) => {
    item.existing_item_id = candidate.id
    item.selected_custom_item = candidate
    item.link_action = 'existing'
    item.show_search_picker = false
    item.search_query = ''
    item.search_results = []
}

const resetToAutoMatch = (item) => {
    if (item.matched_destination_item) {
        item.link_action = 'existing'
        item.existing_item_id = item.matched_destination_item.id
    } else {
        item.link_action = 'copy'
        item.existing_item_id = null
    }
    item.selected_custom_item = null
    item.show_search_picker = false
    item.search_query = ''
    item.search_results = []
}

const submitAccept = () => {
    form.transform((data) => ({
        to_location: data.to_location,
        receiver_notes: data.receiver_notes || '',
        items: data.items.map(item => ({
            line_id: item.line_id,
            quantity_received: item.quantity_received,
            link_action: item.link_action,
            existing_item_id: item.link_action === 'existing' ? item.existing_item_id : null,
        })),
    })).post(
        route('pos.inventory.transfers.inter-branch.accept', { branch: currentBranchSlug.value, transfer: props.transfer.id })
    )
}
</script>

<template>
    <Head :title="`Receive Transfer ${transfer.reference} - Inventory`" />

    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Top Fixed Header Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500 mb-0.5">
                    <Link :href="route('pos.inventory.index', { branch: currentBranchSlug })" class="hover:text-theme transition">Inventory</Link>
                    <span>/</span>
                    <Link :href="route('pos.inventory.transfers.inter-branch.index', { branch: currentBranchSlug, tab: 'incoming' })" class="hover:text-theme transition">Inter-Branch Transfers</Link>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-slate-300 font-medium">Receive {{ transfer.reference }}</span>
                </div>
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Accept & Inspect Incoming Stock Transfer</span>
                    <span class="font-mono text-xs px-2 py-0.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        {{ transfer.reference }}
                    </span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Dispatched from <strong class="text-slate-700 dark:text-slate-300">{{ transfer.source_branch?.name }}</strong> on {{ transfer.sent_at ? new Date(transfer.sent_at).toLocaleString() : '—' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    :href="route('pos.inventory.transfers.inter-branch.index', { branch: currentBranchSlug, tab: 'incoming' })"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 transition shadow-2xs cursor-pointer"
                >
                    Back to Receiving Queue
                </Link>
            </div>
        </div>

        <!-- Scrollable Full-Page Body Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto space-y-6 pb-16">
                <form @submit.prevent="submitAccept" class="space-y-6">

                    <!-- Section 1: Sender's Dispatch Note & Transfer Info -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>1. Transfer Overview & Sender's Instructions</span>
                            </h2>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                Total Items: <strong class="text-slate-800 dark:text-slate-200">{{ form.items.length }}</strong>
                            </span>
                        </div>

                        <!-- Sender's Transfer Note Alert Box (Prominent & Clear) -->
                        <div
                            v-if="transfer.notes"
                            class="p-4 bg-amber-50/90 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80 rounded-xl text-xs space-y-1.5 shadow-2xs"
                        >
                            <div class="font-bold flex items-center gap-2 text-amber-900 dark:text-amber-200">
                                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                                <span>Note from Sender ({{ transfer.source_branch?.name }}):</span>
                            </div>
                            <p class="text-xs text-amber-900 dark:text-amber-200 font-medium pl-6 whitespace-pre-wrap leading-relaxed">
                                {{ transfer.notes }}
                            </p>
                        </div>
                        <div v-else class="text-xs text-slate-400 dark:text-slate-500 italic p-3 bg-slate-50 dark:bg-slate-750 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                            No dispatch notes provided by sender.
                        </div>
                    </div>

                    <!-- Section 2: Destination Store Location Picker -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-theme"></span>
                                <span>2. Storage Location for Received Stock *</span>
                            </h2>
                        </div>

                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                                Select where the accepted quantities will be credited in your branch inventory:
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <label
                                    class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                    :class="form.to_location === 'back_store'
                                        ? 'border-purple-500 bg-purple-50/60 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200 font-bold shadow-2xs'
                                        : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                >
                                    <input
                                        type="radio"
                                        value="back_store"
                                        v-model="form.to_location"
                                        class="mt-1 text-purple-600 focus:ring-purple-500"
                                    />
                                    <div>
                                        <div class="text-xs font-bold">Back Store (Storage / Warehouse)</div>
                                        <div class="text-[11px] text-slate-400 font-normal mt-0.5">
                                            Stock will be deposited into your back warehouse for internal distribution.
                                        </div>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                    :class="form.to_location === 'front_store'
                                        ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 font-bold shadow-2xs'
                                        : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750'"
                                >
                                    <input
                                        type="radio"
                                        value="front_store"
                                        v-model="form.to_location"
                                        class="mt-1 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div>
                                        <div class="text-xs font-bold">Front Store (Direct to Sales Shelves)</div>
                                        <div class="text-[11px] text-slate-400 font-normal mt-0.5">
                                            Stock will be deposited directly to your front POS floor ready for immediate sales.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Items Inspection & Catalog Linking (Full Page View) -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-700">
                            <div>
                                <h2 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span>3. Items to Inspect & Receive</span>
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Verify physical count against sent quantities and confirm catalog linking.
                                </p>
                            </div>
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                                {{ form.items.length }} items ({{ totalUnitsReceived }} units to receive)
                            </span>
                        </div>

                        <!-- Item Cards List (No cramped modal scroll!) -->
                        <div class="space-y-4 pt-1">
                            <div
                                v-for="(item, idx) in form.items"
                                :key="idx"
                                class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-850/60 space-y-3.5"
                            >
                                <!-- Top row: Item Name, Barcode, Dispatched Qty, Actual Received Input -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-2">
                                            <span>{{ item.item_name }}</span>
                                            <span v-if="item.barcode_number" class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 shadow-2xs">
                                                {{ item.barcode_number }}
                                            </span>
                                        </div>
                                        <div class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">
                                            Dispatched by sender: <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ item.quantity_sent }} {{ item.unit_label }}</strong>
                                        </div>
                                        <!-- Item Line Note from Sender -->
                                        <div v-if="item.notes" class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 px-2.5 py-1 rounded-lg border border-amber-200/80 dark:border-amber-800/60 font-medium">
                                            <span class="font-bold">📝 Sender Item Note:</span>
                                            <span>"{{ item.notes }}"</span>
                                        </div>
                                    </div>

                                    <!-- Received Qty Stepper Input -->
                                    <div class="flex items-center gap-2 flex-shrink-0 bg-white dark:bg-slate-800 p-2 rounded-xl border border-slate-200 dark:border-slate-700">
                                        <label class="text-slate-600 dark:text-slate-400 font-semibold text-xs">Actual Received:</label>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                @click="item.quantity_received > 0 ? item.quantity_received-- : null"
                                                class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-200 dark:hover:bg-slate-600 flex items-center justify-center cursor-pointer select-none"
                                            >
                                                -
                                            </button>
                                            <input
                                                type="number"
                                                min="0"
                                                :max="item.quantity_sent"
                                                v-model.number="item.quantity_received"
                                                class="w-16 px-2 py-1 text-center font-bold text-xs rounded-lg border outline-none focus:ring-1 focus:ring-theme transition"
                                                :class="item.quantity_received < item.quantity_sent
                                                    ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200'
                                                    : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white'"
                                            />
                                            <button
                                                type="button"
                                                @click="item.quantity_received < item.quantity_sent ? item.quantity_received++ : null"
                                                class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-200 dark:hover:bg-slate-600 flex items-center justify-center cursor-pointer select-none"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <span class="text-xs text-slate-500 font-semibold">{{ item.unit_label }}</span>
                                    </div>
                                </div>

                                <!-- Shortfall alert if physical count is less than sent -->
                                <div
                                    v-if="item.quantity_received < item.quantity_sent"
                                    class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2"
                                >
                                    <span class="text-sm">⚠️</span>
                                    <span>
                                        Shortfall of <strong>{{ item.quantity_sent - item.quantity_received }} {{ item.unit_label }}</strong> will be recorded and notified to the sender as a discrepancy.
                                    </span>
                                </div>

                                <!-- ── CATALOG LINKING DECISION ────────────────────────── -->
                                <!-- Case 1: Already linked to a destination item in this branch -->
                                <div v-if="item.has_destination_item" class="p-3 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300">
                                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Linked to branch item: <strong class="font-bold">{{ item.destination_item_name }}</strong></span>
                                    </div>
                                    <span class="text-emerald-700 dark:text-emerald-400 font-medium">
                                        Stock quantity will update (+{{ item.quantity_received }} {{ item.unit_label }})
                                    </span>
                                </div>

                                <!-- Case 2: Not yet linked - SIMILAR ITEM FOUND IN THIS BRANCH -->
                                <div v-else-if="item.matched_destination_item && !item.selected_custom_item" class="p-4 rounded-xl bg-teal-50/80 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800 space-y-3">
                                    <!-- Header notice -->
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 text-teal-900 dark:text-teal-200 font-bold text-xs">
                                            <span class="text-teal-600 dark:text-teal-400 text-base">🔍</span>
                                            <span>Existing Item Found in Your Branch Catalog</span>
                                        </div>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 dark:bg-teal-900/60 text-teal-800 dark:text-teal-300 border border-teal-200 dark:border-teal-700">
                                            {{ item.matched_destination_item.match_type === 'barcode' ? 'Matched by Barcode: ' + item.matched_destination_item.barcode_number : 'Matched by Item Name' }}
                                        </span>
                                    </div>

                                    <!-- Matched item details badge -->
                                    <div class="p-3 rounded-lg bg-white/90 dark:bg-slate-800/90 border border-teal-100 dark:border-teal-900/40 flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <div>
                                            <span class="text-slate-400 dark:text-slate-500">Your Branch Item:</span>
                                            <strong class="ml-1 text-slate-900 dark:text-white font-bold">{{ item.matched_destination_item.item_name }}</strong>
                                            <span v-if="item.matched_destination_item.barcode_number" class="ml-1.5 font-mono text-[10px] text-slate-400">[{{ item.matched_destination_item.barcode_number }}]</span>
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            Current Stock:
                                            <span class="font-semibold text-purple-600 dark:text-purple-400">Back: {{ item.matched_destination_item.back_store_qty }}</span> /
                                            <span class="font-semibold text-blue-600 dark:text-blue-400">Front: {{ item.matched_destination_item.front_store_qty }}</span>
                                        </div>
                                    </div>

                                    <!-- Decision Choices -->
                                    <div class="space-y-2 pt-1 text-xs">
                                        <!-- Option A: Update existing stock (Default & Recommended) -->
                                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                            :class="item.link_action === 'existing'
                                                ? 'bg-teal-100/80 dark:bg-teal-900/50 border-teal-300 dark:border-teal-600 text-teal-950 dark:text-teal-100 font-medium'
                                                : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50'">
                                            <input
                                                type="radio"
                                                value="existing"
                                                v-model="item.link_action"
                                                @change="item.existing_item_id = item.matched_destination_item.id; item.show_search_picker = false"
                                                class="mt-1 text-teal-600 focus:ring-teal-500"
                                            />
                                            <div>
                                                <div class="font-bold flex items-center gap-2">
                                                    <span>Update Existing Item Stock (Recommended)</span>
                                                    <span class="text-[9px] uppercase tracking-wider px-2 py-0.5 rounded bg-teal-200 dark:bg-teal-800 text-teal-900 dark:text-teal-200 font-bold">No Duplicate</span>
                                                </div>
                                                <div class="text-[11px] text-teal-800 dark:text-teal-300 mt-0.5">
                                                    Proceeding will link this transfer to existing item "<strong>{{ item.matched_destination_item.item_name }}</strong>" and add <strong>+{{ item.quantity_received }} {{ item.unit_label }}</strong> into your {{ form.to_location === 'back_store' ? 'Back Store' : 'Front Store' }}.
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Option B: Create as new item -->
                                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                            :class="item.link_action === 'copy'
                                                ? 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-300 dark:border-indigo-600 text-indigo-950 dark:text-indigo-100 font-medium'
                                                : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50'">
                                            <input
                                                type="radio"
                                                value="copy"
                                                v-model="item.link_action"
                                                @change="item.existing_item_id = null; item.show_search_picker = false"
                                                class="mt-1 text-theme focus:ring-theme"
                                            />
                                            <div>
                                                <div class="font-bold">Create as New Separate Item</div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                    Do not link to the matched item. Creates a new separate product record in your branch catalog.
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Option C: Choose different existing item -->
                                        <button
                                            type="button"
                                            @click="item.show_search_picker = !item.show_search_picker"
                                            class="text-xs font-semibold text-teal-700 dark:text-teal-300 hover:underline flex items-center gap-1 mt-1 cursor-pointer"
                                        >
                                            <span>🔍 Link to a different item from your catalog instead</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Case 3: Not yet linked - NO SIMILAR ITEM FOUND (or Custom Selected) -->
                                <div v-else class="p-4 rounded-xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 space-y-3">
                                    <!-- Notice if custom item selected -->
                                    <div v-if="item.selected_custom_item" class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 font-bold text-xs">
                                            <span>🔗</span>
                                            <span>Manually Linked to Branch Item: <strong>{{ item.selected_custom_item.item_name }}</strong></span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="resetToAutoMatch(item)"
                                            class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline cursor-pointer"
                                        >
                                            Clear / Reset
                                        </button>
                                    </div>

                                    <!-- Notice if brand new item -->
                                    <div v-else class="space-y-1">
                                        <div class="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 font-bold text-xs">
                                            <span class="text-sm">✨</span>
                                            <span>New Item — Not Found in Your Branch Catalog</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                            No item matching "<strong>{{ item.item_name }}</strong>" {{ item.barcode_number ? '(Barcode: ' + item.barcode_number + ')' : '' }} was found in your branch inventory.
                                        </p>
                                    </div>

                                    <!-- Decision Choices when not manually selected -->
                                    <div v-if="!item.selected_custom_item" class="space-y-2 pt-1 text-xs">
                                        <!-- Option A: Create new item (Default) -->
                                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                            :class="item.link_action === 'copy'
                                                ? 'bg-indigo-100/80 dark:bg-indigo-900/50 border-indigo-300 dark:border-indigo-600 text-indigo-950 dark:text-indigo-100 font-medium'
                                                : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50'">
                                            <input
                                                type="radio"
                                                value="copy"
                                                v-model="item.link_action"
                                                @change="item.existing_item_id = null; item.show_search_picker = false"
                                                class="mt-1 text-theme focus:ring-theme"
                                            />
                                            <div>
                                                <div class="font-bold flex items-center gap-2">
                                                    <span>Create New Item in My Branch (Recommended)</span>
                                                    <span class="text-[9px] uppercase tracking-wider px-2 py-0.5 rounded bg-indigo-200 dark:bg-indigo-800 text-indigo-900 dark:text-indigo-200 font-bold">Create & Link</span>
                                                </div>
                                                <div class="text-[11px] text-indigo-800 dark:text-indigo-300 mt-0.5">
                                                    Proceeding will create "<strong>{{ item.item_name }}</strong>" in your catalog, link it, and add <strong>+{{ item.quantity_received }} {{ item.unit_label }}</strong> into your {{ form.to_location === 'back_store' ? 'Back Store' : 'Front Store' }}.
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Option B: Choose existing item button -->
                                        <button
                                            type="button"
                                            @click="item.show_search_picker = !item.show_search_picker"
                                            class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:underline flex items-center gap-1 mt-1 cursor-pointer"
                                        >
                                            <span>🔍 Link to an existing item instead (search catalog)</span>
                                        </button>
                                    </div>

                                    <!-- Custom Item Selected text -->
                                    <div v-else class="text-xs text-indigo-800 dark:text-indigo-300 p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-indigo-100 dark:border-indigo-900/40">
                                        Stock will update for existing item "<strong>{{ item.selected_custom_item.item_name }}</strong>" (+{{ item.quantity_received }} {{ item.unit_label }}) in {{ form.to_location === 'back_store' ? 'Back Store' : 'Front Store' }}.
                                    </div>
                                </div>

                                <!-- Inline Search Picker for choosing a different item in branch -->
                                <div v-if="item.show_search_picker" class="p-3.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 space-y-2 mt-2 shadow-sm">
                                    <div class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200">
                                        <span>Search Your Branch Catalog to Link:</span>
                                        <button type="button" @click="item.show_search_picker = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer">✕ Close</button>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            v-model="item.search_query"
                                            @input="searchBranchItems(item)"
                                            placeholder="Type item name or barcode in your store..."
                                            class="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white outline-none focus:ring-1 focus:ring-theme"
                                        />
                                        <span v-if="item.is_searching" class="absolute right-3 top-2.5 text-[10px] text-slate-400 animate-pulse">Searching...</span>
                                    </div>
                                    <!-- Results list -->
                                    <div v-if="item.search_results.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700 text-xs">
                                        <div
                                            v-for="cand in item.search_results"
                                            :key="cand.id"
                                            @click="selectExistingItemForLine(item, cand)"
                                            class="p-2.5 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer flex items-center justify-between transition"
                                        >
                                            <div>
                                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ cand.item_name }}</span>
                                                <span v-if="cand.barcode_number" class="ml-2 text-[10px] text-slate-400 font-mono">[{{ cand.barcode_number }}]</span>
                                            </div>
                                            <div class="text-[11px] text-slate-400">
                                                Stock: Back {{ cand.back_store_qty ?? 0 }} / Front {{ cand.front_store_qty ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="item.search_query && !item.is_searching" class="text-xs text-slate-400 text-center py-2">
                                        No items found matching "{{ item.search_query }}".
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Receiver's Remarks / Acceptance Note -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                <span>4. Receiver's Remarks / Acceptance Note (Optional)</span>
                            </h2>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Add note or remarks on delivery condition, shortfall reasons, or receipt acknowledgment:
                            </label>
                            <textarea
                                v-model="form.receiver_notes"
                                rows="3"
                                placeholder="E.g. Received in good condition via courier; 1 item had carton seal broken..."
                                class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white p-3 focus:ring-1 focus:ring-theme focus:border-theme outline-none resize-none placeholder-slate-400"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Section 5: Summary & Submit Action Card -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div class="space-y-1">
                                <span class="font-bold text-slate-800 dark:text-slate-200 text-xs">Action Summary on Proceed:</span>
                                <div class="text-xs flex flex-wrap items-center gap-4">
                                    <span v-if="updatingItemsCount > 0" class="text-teal-700 dark:text-teal-300 font-semibold flex items-center gap-1.5">
                                        <span>✔</span>
                                        <span><strong>{{ updatingItemsCount }}</strong> item(s) will update existing stock</span>
                                    </span>
                                    <span v-if="creatingItemsCount > 0" class="text-indigo-700 dark:text-indigo-300 font-semibold flex items-center gap-1.5">
                                        <span>➕</span>
                                        <span><strong>{{ creatingItemsCount }}</strong> new item(s) will be created</span>
                                    </span>
                                    <span v-if="totalShortfallCount > 0" class="text-amber-700 dark:text-amber-300 font-semibold flex items-center gap-1.5">
                                        <span>⚠️</span>
                                        <span><strong>{{ totalShortfallCount }}</strong> total shortfall unit(s) flagged</span>
                                    </span>
                                </div>
                            </div>
                            <div class="text-xs text-slate-600 dark:text-slate-300">
                                Target Store: <strong class="font-bold text-slate-900 dark:text-white">{{ form.to_location === 'back_store' ? 'Back Store' : 'Front Store' }}</strong>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700">
                            <div class="text-xs text-slate-400 dark:text-slate-500">
                                Stock will be immediately credited to your chosen store location.
                            </div>

                            <div class="flex items-center gap-3">
                                <Link
                                    :href="route('pos.inventory.transfers.inter-branch.index', { branch: currentBranchSlug, tab: 'incoming' })"
                                    class="px-4 py-2 text-xs font-semibold rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-6 py-2.5 text-xs font-bold rounded-xl bg-theme hover:bg-theme-dark disabled:opacity-50 text-white shadow-xs transition active:scale-95 cursor-pointer flex items-center gap-2"
                                >
                                    <span v-if="form.processing">Processing...</span>
                                    <span v-else>Confirm & Update Stock ({{ totalUnitsReceived }} Units)</span>
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</template>
