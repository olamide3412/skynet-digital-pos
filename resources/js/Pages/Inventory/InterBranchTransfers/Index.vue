<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const page = usePage()
const currentBranchSlug = computed(() => {
    return page.props.current_branch?.slug
        || page.props.branch?.slug
        || page.props.auth?.user?.branch?.slug
        || ''
})

const props = defineProps({
    incomingTransfers: Object,
    outgoingTransfers: Object,
    siblingBranches: Array,
    activeTab: String,
    hasCapability: Boolean,
})

const tab = ref(props.activeTab || 'incoming')

const incomingPendingCount = computed(() => {
    return props.incomingTransfers?.data?.filter(t => t.status === 'pending').length || 0
})

const outgoingPendingCount = computed(() => {
    return props.outgoingTransfers?.data?.filter(t => t.status === 'pending' || t.status === 'blocked').length || 0
})

// Accept Modal State
const showAcceptModal = ref(false)
const selectedTransfer = ref(null)

const acceptForm = useForm({
    to_location: 'back_store',
    items: [],
})

const openAcceptModal = (transfer) => {
    selectedTransfer.value = transfer
    acceptForm.to_location = 'back_store'
    acceptForm.items = transfer.items.map(item => ({
        line_id: item.id,
        item_name: item.source_item?.item_name || 'Item',
        unit_label: item.source_item?.unit_label || 'pcs',
        quantity_sent: item.quantity_sent,
        quantity_received: item.quantity_sent,
        has_destination_item: !!item.destination_item_id,
        destination_item_name: item.destination_item?.item_name,
        link_action: item.destination_item_id ? 'none' : 'copy',
        existing_item_id: null,
    }))
    showAcceptModal.value = true
}

const submitAccept = () => {
    if (!selectedTransfer.value) return
    acceptForm.post(
        route('pos.inventory.transfers.inter-branch.accept', { branch: currentBranchSlug.value, transfer: selectedTransfer.value.id }),
        {
            onSuccess: () => {
                showAcceptModal.value = false
                selectedTransfer.value = null
            },
        }
    )
}

const confirmCancel = (transfer) => {
    if (!confirm(`Are you sure you want to cancel transfer ${transfer.reference}? Stock will be immediately restored to your ${transfer.from_location === 'back_store' ? 'Back Store' : 'Front Store'}.`)) {
        return
    }
    router.post(
        route('pos.inventory.transfers.inter-branch.cancel', { branch: currentBranchSlug.value, transfer: transfer.id }),
        {},
        { preserveScroll: true }
    )
}
</script>

<template>
    <Head title="Inter-Branch Stock Movement - Inventory" />

    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Top Fixed Header Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500 mb-0.5">
                    <Link :href="route('pos.inventory.index', { branch: currentBranchSlug })" class="hover:text-theme transition">Inventory</Link>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-slate-300 font-medium">Inter-Branch Transfers</span>
                </div>
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    Inter-Branch Stock Movement
                    <span v-if="!hasCapability" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                        Plan Upgrade Required
                    </span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Transfer stock across branches in your organization with automated transit tracking
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <!-- Intra-Branch (Front ↔ Back) shortcut -->
                <Link
                    :href="route('pos.inventory.transfers.index', { branch: currentBranchSlug })"
                    class="px-3 py-1.5 sm:py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition border border-slate-300 dark:border-slate-600 flex items-center gap-1.5 shadow-2xs"
                    title="Move stock internally between Front and Back store"
                >
                    <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span>Intra-Branch (Front ↔ Back)</span>
                </Link>

                <!-- Send Stock to Branch button -->
                <Link
                    v-if="siblingBranches.length > 0"
                    :href="route('pos.inventory.transfers.inter-branch.create', { branch: currentBranchSlug })"
                    class="px-3.5 py-1.5 sm:py-2 bg-theme hover:bg-theme-dark text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Send Stock to Branch</span>
                </Link>
            </div>
        </div>

        <!-- Scrollable Body Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
            <!-- Plan Capability Warning Banner -->
            <div v-if="!hasCapability" class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 flex items-start gap-3 text-xs">
                <span class="text-base flex-shrink-0">⚠️</span>
                <div>
                    <p class="font-bold">Inter-Branch Transfers feature is not enabled on your plan.</p>
                    <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-0.5">Please upgrade your organization plan to Silver, Gold, or Enterprise to unlock multi-branch stock movement.</p>
                </div>
            </div>

            <!-- Segmented Navigation Tabs -->
            <div class="flex items-center justify-between">
                <div class="inline-flex p-1 bg-slate-200/80 dark:bg-slate-800 rounded-xl gap-1">
                    <button
                        type="button"
                        @click="tab = 'incoming'"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 cursor-pointer',
                            tab === 'incoming'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-2xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                        <span>Receiving Queue (Incoming)</span>
                        <span
                            v-if="incomingPendingCount > 0"
                            class="px-1.5 py-0.2 text-[10px] font-black rounded-full bg-emerald-500 text-white leading-tight"
                        >
                            {{ incomingPendingCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="tab = 'outgoing'"
                        :class="[
                            'px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 cursor-pointer',
                            tab === 'outgoing'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-2xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                        </svg>
                        <span>Sent Transfers (Outgoing)</span>
                        <span
                            v-if="outgoingTransfers?.total > 0"
                            class="px-1.5 py-0.2 text-[10px] font-bold rounded-full bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-200 leading-tight"
                        >
                            {{ outgoingTransfers.total }}
                        </span>
                    </button>
                </div>

                <div class="text-xs text-slate-400 dark:text-slate-500 hidden sm:block">
                    Showing {{ tab === 'incoming' ? (incomingTransfers?.data?.length || 0) : (outgoingTransfers?.data?.length || 0) }} transfers
                </div>
            </div>

            <!-- TAB 1: Incoming Transfers (Receiving Queue) -->
            <div v-if="tab === 'incoming'" class="space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xs border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-50/90 dark:bg-slate-700/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-4">Transfer Ref</th>
                                    <th class="py-2.5 px-4">Origin Branch</th>
                                    <th class="py-2.5 px-4">Items / Qty</th>
                                    <th class="py-2.5 px-4">Source Store</th>
                                    <th class="py-2.5 px-4">Status</th>
                                    <th class="py-2.5 px-4">Dispatched At</th>
                                    <th class="py-2.5 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-200">
                                <tr v-if="!incomingTransfers?.data || incomingTransfers.data.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        <p class="font-bold text-slate-700 dark:text-slate-200 text-xs">No incoming transfers in receiving queue.</p>
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Transfers dispatched to this branch from sibling branches will appear here.</p>
                                    </td>
                                </tr>

                                <tr
                                    v-for="t in incomingTransfers?.data"
                                    :key="t.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40 transition"
                                >
                                    <td class="py-2.5 px-4">
                                        <div class="font-mono font-bold text-slate-900 dark:text-white text-xs">{{ t.reference }}</div>
                                        <div v-if="t.notes" class="text-[10px] text-slate-500 dark:text-slate-400 truncate max-w-xs">{{ t.notes }}</div>
                                    </td>

                                    <td class="py-2.5 px-4 font-semibold text-slate-800 dark:text-slate-200">
                                        {{ t.source_branch?.name || 'Unknown' }}
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ t.items?.length || 0 }}</span> items
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">({{ t.items?.reduce((sum, i) => sum + i.quantity_sent, 0) }} units)</span>
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <span
                                            class="text-[11px] px-2 py-0.5 rounded-full font-semibold border"
                                            :class="t.from_location === 'back_store'
                                                ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'
                                                : 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'"
                                        >
                                            {{ t.from_location === 'back_store' ? 'Back Store' : 'Front Store' }}
                                        </span>
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <span
                                            v-if="t.status === 'pending'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                                        >
                                            Pending Arrival
                                        </span>
                                        <span
                                            v-else-if="t.status === 'blocked'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
                                            :title="t.blocked_reason"
                                        >
                                            Blocked (Unit Mismatch)
                                        </span>
                                        <span
                                            v-else-if="t.status === 'accepted'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                        >
                                            Accepted
                                        </span>
                                        <span
                                            v-else-if="t.status === 'cancelled'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400"
                                        >
                                            Cancelled
                                        </span>
                                    </td>

                                    <td class="py-2.5 px-4 text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                        {{ t.sent_at ? new Date(t.sent_at).toLocaleString() : '—' }}
                                    </td>

                                    <td class="py-2.5 px-4 text-right">
                                        <button
                                            v-if="t.status === 'pending'"
                                            @click="openAcceptModal(t)"
                                            class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-2xs active:scale-95 transition cursor-pointer"
                                        >
                                            Accept & Receive
                                        </button>
                                        <span v-else-if="t.status === 'blocked'" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold" :title="t.blocked_reason">
                                            Units Differ
                                        </span>
                                        <span v-else class="text-[11px] text-slate-400 dark:text-slate-500">Completed</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Outgoing Transfers (Sent) -->
            <div v-if="tab === 'outgoing'" class="space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xs border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-50/90 dark:bg-slate-700/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-4">Transfer Ref</th>
                                    <th class="py-2.5 px-4">Destination Branch</th>
                                    <th class="py-2.5 px-4">Items / Qty</th>
                                    <th class="py-2.5 px-4">Drawn From</th>
                                    <th class="py-2.5 px-4">Status</th>
                                    <th class="py-2.5 px-4">Sent At</th>
                                    <th class="py-2.5 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-200">
                                <tr v-if="!outgoingTransfers?.data || outgoingTransfers.data.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                        <p class="font-bold text-slate-700 dark:text-slate-200 text-xs">No outgoing transfers dispatched yet.</p>
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Click "Send Stock to Branch" above to dispatch stock to another branch.</p>
                                    </td>
                                </tr>

                                <tr
                                    v-for="t in outgoingTransfers?.data"
                                    :key="t.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40 transition"
                                >
                                    <td class="py-2.5 px-4">
                                        <div class="font-mono font-bold text-slate-900 dark:text-white text-xs">{{ t.reference }}</div>
                                        <div v-if="t.notes" class="text-[10px] text-slate-500 dark:text-slate-400 truncate max-w-xs">{{ t.notes }}</div>
                                    </td>

                                    <td class="py-2.5 px-4 font-semibold text-slate-800 dark:text-slate-200">
                                        {{ t.destination_branch?.name || 'Unknown' }}
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ t.items?.length || 0 }}</span> items
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">({{ t.items?.reduce((sum, i) => sum + i.quantity_sent, 0) }} units)</span>
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <span
                                            class="text-[11px] px-2 py-0.5 rounded-full font-semibold border"
                                            :class="t.from_location === 'back_store'
                                                ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'
                                                : 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'"
                                        >
                                            {{ t.from_location === 'back_store' ? 'Back Store' : 'Front Store' }}
                                        </span>
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <span
                                            v-if="t.status === 'pending'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                                        >
                                            In Transit
                                        </span>
                                        <span
                                            v-else-if="t.status === 'blocked'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
                                            :title="t.blocked_reason"
                                        >
                                            Blocked (Unit Mismatch)
                                        </span>
                                        <span
                                            v-else-if="t.status === 'accepted'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                        >
                                            Accepted by Receiver
                                        </span>
                                        <span
                                            v-else-if="t.status === 'cancelled'"
                                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400"
                                        >
                                            Cancelled (Stock Returned)
                                        </span>
                                    </td>

                                    <td class="py-2.5 px-4 text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                        {{ t.sent_at ? new Date(t.sent_at).toLocaleString() : '—' }}
                                    </td>

                                    <td class="py-2.5 px-4 text-right">
                                        <button
                                            v-if="['pending', 'blocked'].includes(t.status)"
                                            @click="confirmCancel(t)"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-800 transition cursor-pointer"
                                        >
                                            Cancel Transfer
                                        </button>
                                        <span v-else class="text-[11px] text-slate-400 dark:text-slate-500">Completed</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL: Accept Transfer (Receiving Queue) ────────────────────────── -->
        <div v-if="showAcceptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 max-w-2xl w-full p-5 space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Accept Incoming Stock Transfer</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Ref: <strong class="font-mono text-slate-800 dark:text-slate-200">{{ selectedTransfer?.reference }}</strong> from {{ selectedTransfer?.source_branch?.name }}</p>
                    </div>
                    <button @click="showAcceptModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg p-1 cursor-pointer">✕</button>
                </div>

                <!-- Destination Store Location Selector -->
                <div class="p-3 bg-slate-50 dark:bg-slate-750 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        Deposit Received Stock Into:
                    </label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <input
                                type="radio"
                                value="back_store"
                                v-model="acceptForm.to_location"
                                class="text-theme focus:ring-theme"
                            />
                            <span>Back Store (Storage / Warehouse)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <input
                                type="radio"
                                value="front_store"
                                v-model="acceptForm.to_location"
                                class="text-theme focus:ring-theme"
                            />
                            <span>Front Store (Direct to Sales Shelves)</span>
                        </label>
                    </div>
                </div>

                <!-- Items Counted / Shortfall Input Table -->
                <div class="flex-1 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl divide-y divide-slate-100 dark:divide-slate-700/60 max-h-72">
                    <div
                        v-for="(item, idx) in acceptForm.items"
                        :key="idx"
                        class="p-3 space-y-2 text-xs bg-white dark:bg-slate-800"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-bold text-slate-900 dark:text-white text-xs">{{ item.item_name }}</span>
                                <span class="text-slate-400 dark:text-slate-500 ml-2 text-[11px]">Sent: <strong>{{ item.quantity_sent }} {{ item.unit_label }}</strong></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <label class="text-slate-600 dark:text-slate-400 font-medium text-xs">Actual Received:</label>
                                <input
                                    type="number"
                                    min="0"
                                    :max="item.quantity_sent"
                                    v-model.number="item.quantity_received"
                                    class="w-20 px-2 py-1 text-center font-bold text-xs border rounded-lg outline-none focus:ring-1 focus:ring-theme"
                                    :class="item.quantity_received < item.quantity_sent
                                        ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200'
                                        : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white'"
                                />
                            </div>
                        </div>

                        <!-- Shortfall alert -->
                        <div v-if="item.quantity_received < item.quantity_sent" class="text-[11px] text-amber-700 dark:text-amber-400 font-medium flex items-center gap-1">
                            ⚠️ Shortfall of {{ item.quantity_sent - item.quantity_received }} unit(s) will be logged as a discrepancy.
                        </div>

                        <!-- On-the-fly linking notice if item not yet linked -->
                        <div v-if="!item.has_destination_item" class="p-2.5 rounded-lg bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-800 space-y-1 text-[11px]">
                            <div class="font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-1">
                                🔗 Item not linked yet in your branch catalog
                            </div>
                            <div class="flex items-center gap-4 text-slate-700 dark:text-slate-300">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="copy" v-model="item.link_action" class="text-theme" />
                                    <span>Create item in my branch (copy catalog & link)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                    <div class="text-[11px] text-slate-400 dark:text-slate-500">
                        Receiving branch retains its own item cost price.
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showAcceptModal = false"
                            class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitAccept"
                            :disabled="acceptForm.processing"
                            class="px-4 py-1.5 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white shadow-xs transition cursor-pointer"
                        >
                            Confirm Acceptance & Update Stock
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
