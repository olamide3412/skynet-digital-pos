<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
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

// Details Modal State
const showDetailsModal = ref(false)
const selectedDetailsTransfer = ref(null)

const openDetailsModal = (transfer) => {
    selectedDetailsTransfer.value = transfer
    showDetailsModal.value = true
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
                                        <div v-if="t.notes" class="mt-1 flex items-start gap-1 text-[11px] text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/60 rounded-md px-2 py-0.5 max-w-xs" :title="t.notes">
                                            <span class="flex-shrink-0 text-amber-600 dark:text-amber-400">📝</span>
                                            <span class="truncate font-medium">{{ t.notes }}</span>
                                        </div>
                                        <div v-if="t.receiver_notes" class="mt-0.5 flex items-start gap-1 text-[10px] text-teal-800 dark:text-teal-200 bg-teal-50 dark:bg-teal-950/40 border border-teal-200/80 dark:border-teal-800/60 rounded-md px-1.5 py-0.5 max-w-xs" :title="t.receiver_notes">
                                            <span class="flex-shrink-0 text-teal-600 dark:text-teal-400">📥</span>
                                            <span class="truncate">{{ t.receiver_notes }}</span>
                                        </div>
                                    </td>

                                    <td class="py-2.5 px-4 font-semibold text-slate-800 dark:text-slate-200">
                                        {{ t.source_branch?.name || 'Unknown' }}
                                    </td>

                                    <td class="py-2.5 px-4">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ t.items?.length || 0 }}</span> items
                                            <span class="text-[11px] text-slate-400 dark:text-slate-500">({{ t.items?.reduce((sum, i) => sum + i.quantity_sent, 0) }} units)</span>
                                        </div>
                                        <div v-if="t.status === 'pending'" class="mt-1 flex items-center gap-1 text-[10px]">
                                            <span v-if="t.items?.every(i => i.destination_item_id)" class="px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-medium">
                                                All items linked
                                            </span>
                                            <span v-else-if="t.items?.some(i => !i.destination_item_id && i.matched_destination_item)" class="px-1.5 py-0.5 rounded bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 font-medium" title="Items match existing products in your branch">
                                                Matches existing stock
                                            </span>
                                            <span v-else class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-medium">
                                                New catalog items
                                            </span>
                                        </div>
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
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="openDetailsModal(t)"
                                                class="px-2 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition cursor-pointer flex items-center gap-1 shadow-2xs"
                                                title="View full transfer details and notes"
                                            >
                                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>Details</span>
                                            </button>

                                            <Link
                                                v-if="t.status === 'pending'"
                                                :href="route('pos.inventory.transfers.inter-branch.receive', { branch: currentBranchSlug, transfer: t.id })"
                                                class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-2xs active:scale-95 transition cursor-pointer flex items-center gap-1"
                                            >
                                                <span>Accept & Receive</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </Link>
                                            <span v-else-if="t.status === 'blocked'" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold" :title="t.blocked_reason">
                                                Units Differ
                                            </span>
                                        </div>
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
                                        <div v-if="t.notes" class="mt-1 flex items-start gap-1 text-[11px] text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/60 rounded-md px-2 py-0.5 max-w-xs" :title="t.notes">
                                            <span class="flex-shrink-0 text-amber-600 dark:text-amber-400">📝</span>
                                            <span class="truncate font-medium">{{ t.notes }}</span>
                                        </div>
                                        <div v-if="t.receiver_notes" class="mt-0.5 flex items-start gap-1 text-[10px] text-teal-800 dark:text-teal-200 bg-teal-50 dark:bg-teal-950/40 border border-teal-200/80 dark:border-teal-800/60 rounded-md px-1.5 py-0.5 max-w-xs" :title="t.receiver_notes">
                                            <span class="flex-shrink-0 text-teal-600 dark:text-teal-400">📥</span>
                                            <span class="truncate">{{ t.receiver_notes }}</span>
                                        </div>
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
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="openDetailsModal(t)"
                                                class="px-2 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition cursor-pointer flex items-center gap-1 shadow-2xs"
                                                title="View full transfer details and notes"
                                            >
                                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>Details</span>
                                            </button>

                                            <button
                                                v-if="['pending', 'blocked'].includes(t.status)"
                                                @click="confirmCancel(t)"
                                                class="px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-800 transition cursor-pointer"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>



        <!-- ── MODAL: View Transfer Details ────────────────────────────────────── -->
        <div v-if="showDetailsModal && selectedDetailsTransfer" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 max-w-2xl w-full p-5 space-y-4 max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Transfer Details</h3>
                            <span class="font-mono font-bold text-xs px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                                {{ selectedDetailsTransfer.reference }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            From <strong>{{ selectedDetailsTransfer.source_branch?.name }}</strong> to <strong>{{ selectedDetailsTransfer.destination_branch?.name }}</strong>
                        </p>
                    </div>
                    <button @click="showDetailsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg p-1 cursor-pointer">✕</button>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-slate-50 dark:bg-slate-750 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase font-bold">Status</span>
                        <span class="font-bold capitalize text-slate-800 dark:text-slate-200">{{ selectedDetailsTransfer.status }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase font-bold">Drawn From</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ selectedDetailsTransfer.from_location === 'back_store' ? 'Back Store' : 'Front Store' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase font-bold">Dispatched</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedDetailsTransfer.sent_at ? new Date(selectedDetailsTransfer.sent_at).toLocaleDateString() : '—' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase font-bold">Destination Store</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedDetailsTransfer.to_location ? (selectedDetailsTransfer.to_location === 'back_store' ? 'Back Store' : 'Front Store') : 'Pending' }}</span>
                    </div>
                </div>

                <!-- Sender Note Box -->
                <div class="p-3 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-xl text-xs space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-200">
                        <span>📝 Sender's Dispatch Note:</span>
                    </div>
                    <p class="text-amber-800 dark:text-amber-300 whitespace-pre-wrap font-medium">
                        {{ selectedDetailsTransfer.notes || 'No dispatch notes provided.' }}
                    </p>
                </div>

                <!-- Receiver Note Box (if present) -->
                <div v-if="selectedDetailsTransfer.receiver_notes" class="p-3 bg-teal-50/80 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-800 rounded-xl text-xs space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-teal-900 dark:text-teal-200">
                        <span>📥 Receiver's Acceptance Note:</span>
                    </div>
                    <p class="text-teal-800 dark:text-teal-300 whitespace-pre-wrap font-medium">
                        {{ selectedDetailsTransfer.receiver_notes }}
                    </p>
                </div>

                <!-- Items Breakdown List -->
                <div class="flex-1 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl divide-y divide-slate-100 dark:divide-slate-700/60 max-h-72">
                    <div
                        v-for="item in selectedDetailsTransfer.items"
                        :key="item.id"
                        class="p-3 bg-white dark:bg-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
                    >
                        <div class="min-w-0">
                            <div class="font-bold text-slate-900 dark:text-white truncate">
                                {{ item.source_item?.item_name || 'Item' }}
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                {{ item.source_item?.barcode_number || 'No Barcode' }}
                            </div>
                            <div v-if="item.notes" class="mt-1 text-[11px] text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/40 px-2 py-0.5 rounded-md border border-amber-200/70 dark:border-amber-800/60 inline-flex items-center gap-1">
                                <span class="font-bold">📝 Note:</span>
                                <span>{{ item.notes }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 text-right flex-shrink-0">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Sent</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.quantity_sent }}</span>
                            </div>
                            <div v-if="selectedDetailsTransfer.status === 'accepted'">
                                <span class="text-[10px] text-slate-400 uppercase font-semibold block">Received</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ item.quantity_received }}</span>
                            </div>
                            <div v-if="selectedDetailsTransfer.status === 'accepted' && item.shortfall_quantity > 0">
                                <span class="text-[10px] text-rose-500 uppercase font-semibold block">Shortfall</span>
                                <span class="font-bold text-rose-600 dark:text-rose-400">-{{ item.shortfall_quantity }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button
                        type="button"
                        @click="showDetailsModal = false"
                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
