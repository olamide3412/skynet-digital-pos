<script setup>
import { ref } from 'vue'
import { Link, router, usePage, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    customers: Object,
    filters: Object,
    counts: Object,
})

const page = usePage()
const { format } = useCurrency()
const search = ref(props.filters?.search ?? '')
const activeTab = ref(props.filters?.tab ?? 'all')

function setTab(tab) {
    activeTab.value = tab
    router.get(route('pos.customers.index'), {
        search: search.value,
        tab: tab,
    }, { preserveState: true, replace: true })
}

function doSearch() {
    router.get(route('pos.customers.index'), {
        search: search.value,
        tab: activeTab.value,
    }, { preserveState: true, replace: true })
}

function toggleWatchlist(customer) {
    router.post(route('pos.customers.toggle-watchlist', customer.id), {}, {
        preserveScroll: true,
    })
}

// ── Quick Note Modal ─────────────────────────────────────────────────────────
const selectedCustomerForNote = ref(null)
const showNoteModal = ref(false)
const noteForm = useForm({
    note: '',
})

function openNoteModal(customer) {
    selectedCustomerForNote.value = customer
    noteForm.note = (customer.note && customer.note !== 'No Note') ? customer.note : ''
    showNoteModal.value = true
}

function closeNoteModal() {
    showNoteModal.value = false
    selectedCustomerForNote.value = null
    noteForm.reset()
}

function saveNote() {
    if (!selectedCustomerForNote.value) return
    noteForm.patch(route('pos.customers.update-note', selectedCustomerForNote.value.id), {
        preserveScroll: true,
        onSuccess: () => closeNoteModal(),
    })
}

// ── Delete Customer Guard ───────────────────────────────────────────────────
function destroyCustomer(customer) {
    if (customer.debt_bal > 0) {
        alert(`Cannot delete customer "${customer.name}" because they have an outstanding debt balance of ${format(customer.debt_bal)}. All debts must be settled before deleting.`)
        return
    }
    if (confirm(`Are you sure you want to delete customer "${customer.name}"?`)) {
        router.delete(route('pos.customers.destroy', customer.id))
    }
}

// ── Mobile View Helpers ──────────────────────────────────────────────────────
function getInitials(name) {
    if (!name) return '?'
    const parts = name.trim().split(/\s+/)
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function getWhatsAppUrl(phone, wa) {
    const raw = wa || phone || ''
    const cleaned = String(raw).replace(/[^0-9]/g, '')
    return cleaned ? `https://wa.me/${cleaned}` : null
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Customers Directory</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage client profiles, watchlist flags, notes, and debt balances</p>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <Link :href="route('pos.customers.create')"
                    class="px-3.5 py-2 sm:px-4 sm:py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Customer</span>
                </Link>
            </div>
        </div>

        <!-- Flash error banner -->
        <div v-if="page.props.errors?.customer" class="px-4 sm:px-6 pt-3">
            <div class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-xl text-xs text-red-700 dark:text-red-300 flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ page.props.errors.customer }}</span>
            </div>
        </div>

        <!-- Filter Tabs & Search Bar -->
        <div class="px-4 sm:px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-100/60 dark:bg-slate-800/40 flex-shrink-0">
            <!-- Tabs (horizontal scrollable on mobile) -->
            <div class="flex items-center gap-1.5 p-1 bg-white dark:bg-slate-900/80 rounded-xl border border-slate-200 dark:border-slate-700 overflow-x-auto scrollbar-none w-full md:w-auto">
                <button
                    type="button"
                    @click="setTab('all')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap shrink-0"
                    :class="activeTab === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                    <span>👥 All Customers</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'all' ? 'bg-emerald-700/80 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'">
                        {{ counts?.all ?? 0 }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="setTab('watchlist')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap shrink-0"
                    :class="activeTab === 'watchlist' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                    <span>⚠️ Watchlist / Flagged</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'watchlist' ? 'bg-amber-600 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-bold'">
                        {{ counts?.watchlist ?? 0 }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="setTab('debt')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap shrink-0"
                    :class="activeTab === 'debt' ? 'bg-red-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                    <span>💳 Owing Debt</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'debt' ? 'bg-red-700 text-white' : 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 font-bold'">
                        {{ counts?.debt ?? 0 }}
                    </span>
                </button>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-2 w-full md:max-w-xs">
                <div class="relative flex-1">
                    <input
                        v-model="search"
                        @keydown.enter="doSearch"
                        type="text"
                        placeholder="Search name, phone, or email…"
                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 pl-8 pr-7 py-1.5 rounded-lg text-xs outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition"
                    />
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <button
                        v-if="search"
                        @click="search = ''; doSearch()"
                        class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer"
                        title="Clear search"
                    >
                        ✕
                    </button>
                </div>
                <button
                    @click="doSearch"
                    class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-xs font-semibold rounded-lg transition cursor-pointer shrink-0"
                >
                    Search
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4">
            <!-- ── CUSTOM MOBILE VIEW (Visible on screens < md) ──────────── -->
            <div class="md:hidden space-y-3 pb-4">
                <div
                    v-for="customer in customers.data"
                    :key="'mob-' + customer.id"
                    class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs relative overflow-hidden transition"
                >
                    <!-- Top colored accent stripe based on status -->
                    <div
                        v-if="customer.debt_bal > 0"
                        class="absolute top-0 left-0 right-0 h-1 bg-red-500"
                    />
                    <div
                        v-else-if="customer.watch_list"
                        class="absolute top-0 left-0 right-0 h-1 bg-amber-500"
                    />
                    <div
                        v-else
                        class="absolute top-0 left-0 right-0 h-1 bg-emerald-500/30"
                    />

                    <!-- Card Top: Avatar, Name, Phone & Status -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Initials Avatar -->
                            <div
                                class="w-11 h-11 rounded-2xl flex items-center justify-center font-bold text-xs shrink-0 select-none shadow-xs"
                                :class="[
                                    customer.debt_bal > 0
                                        ? 'bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800'
                                        : customer.watch_list
                                        ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                        : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                ]"
                            >
                                {{ getInitials(customer.name) }}
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Link
                                        :href="route('pos.customers.show', customer.id)"
                                        class="text-sm font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition truncate"
                                    >
                                        {{ customer.name }}
                                    </Link>
                                    <span v-if="customer.gender" class="text-[10px] text-slate-500 dark:text-slate-400 font-medium px-1.5 py-0.2 rounded-full bg-slate-100 dark:bg-slate-700/60">
                                        {{ customer.gender }}
                                    </span>
                                </div>

                                <div class="text-xs font-mono text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ customer.phone }}
                                </div>
                            </div>
                        </div>

                        <!-- Debt / Watchlist Badges -->
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <span
                                v-if="customer.debt_bal > 0"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold font-mono bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 inline-flex items-center gap-1 shadow-xs"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                <span>{{ format(customer.debt_bal) }}</span>
                            </span>
                            <span
                                v-else
                                class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400"
                            >
                                No Debt
                            </span>

                            <button
                                type="button"
                                @click="toggleWatchlist(customer)"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold transition flex items-center gap-1 cursor-pointer border"
                                :class="customer.watch_list
                                    ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700'
                                    : 'bg-slate-50 dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700 hover:text-slate-600'"
                                :title="customer.watch_list ? 'Click to remove from watchlist' : 'Click to add to watchlist'"
                            >
                                <span>{{ customer.watch_list ? '⚠️ On Watchlist' : '+ Watchlist' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Contact & Communication Quick Actions -->
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center gap-2">
                        <!-- Direct Call -->
                        <a
                            :href="`tel:${customer.phone}`"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium inline-flex items-center gap-1.5 transition active:scale-95"
                            title="Call customer"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span>Call</span>
                        </a>

                        <!-- WhatsApp Action -->
                        <a
                            v-if="getWhatsAppUrl(customer.phone, customer.whatsapp_number)"
                            :href="getWhatsAppUrl(customer.phone, customer.whatsapp_number)"
                            target="_blank"
                            class="px-2.5 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold inline-flex items-center gap-1.5 transition active:scale-95"
                            title="Open WhatsApp chat"
                        >
                            <span>💬</span>
                            <span>WhatsApp</span>
                            <span v-if="customer.whatsapp_number && customer.whatsapp_number !== customer.phone" class="text-[10px] font-mono text-emerald-600/80">
                                ({{ customer.whatsapp_number }})
                            </span>
                        </a>

                        <!-- Email Link -->
                        <a
                            v-if="customer.email"
                            :href="`mailto:${customer.email}`"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-medium inline-flex items-center gap-1.5 max-w-[200px] truncate transition active:scale-95"
                            :title="customer.email"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="truncate">{{ customer.email }}</span>
                        </a>
                    </div>

                    <!-- Customer Address (if recorded) -->
                    <div v-if="customer.address && customer.address !== 'NA'" class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                        <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="truncate">{{ customer.address }}</span>
                    </div>

                    <!-- Internal Note Preview & Edit -->
                    <div class="mt-2.5">
                        <button
                            type="button"
                            @click="openNoteModal(customer)"
                            class="w-full text-left p-2.5 rounded-xl border text-xs transition flex items-start gap-2 cursor-pointer"
                            :class="(customer.note && customer.note !== 'No Note')
                                ? 'bg-indigo-50/70 dark:bg-indigo-950/30 text-indigo-900 dark:text-indigo-200 border-indigo-200 dark:border-indigo-800/70 hover:bg-indigo-100/70'
                                : 'bg-slate-50 dark:bg-slate-800/60 text-slate-400 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/60'"
                        >
                            <span class="shrink-0 text-xs">📝</span>
                            <div class="min-w-0 flex-1">
                                <span v-if="customer.note && customer.note !== 'No Note'" class="line-clamp-2 leading-relaxed">
                                    {{ customer.note }}
                                </span>
                                <span v-else class="italic text-[11px]">Tap to add internal note…</span>
                            </div>
                            <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold shrink-0">
                                {{ (customer.note && customer.note !== 'No Note') ? 'Edit' : 'Add' }}
                            </span>
                        </button>
                    </div>

                    <!-- Bottom Action Controls -->
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <!-- Show Profile Link -->
                            <Link
                                :href="route('pos.customers.show', customer.id)"
                                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-lg transition"
                            >
                                Profile
                            </Link>

                            <!-- Debt Ledger Link -->
                            <Link
                                :href="route('pos.customers.debt-ledger', customer.id)"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 border"
                                :class="customer.debt_bal > 0
                                    ? 'bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800 hover:bg-orange-100'
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:text-slate-900 dark:hover:text-white'"
                            >
                                <span>Ledger</span>
                                <span v-if="customer.debt_bal > 0" class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                            </Link>

                            <!-- Edit Customer Link -->
                            <Link
                                :href="route('pos.customers.edit', customer.id)"
                                class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-lg transition border border-blue-200 dark:border-blue-800"
                            >
                                Edit
                            </Link>
                        </div>

                        <!-- Delete button -->
                        <button
                            type="button"
                            @click="destroyCustomer(customer)"
                            :disabled="customer.debt_bal > 0"
                            :title="customer.debt_bal > 0 ? 'Cannot delete customer who owes debt' : 'Delete Customer'"
                            class="p-2 rounded-lg text-xs font-semibold transition border cursor-pointer"
                            :class="customer.debt_bal > 0
                                ? 'bg-slate-100 dark:bg-slate-800 text-slate-300 dark:text-slate-600 border-slate-200 dark:border-slate-700 cursor-not-allowed opacity-40'
                                : 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-100'"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty State for Mobile -->
                <div v-if="!customers.data.length" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-8 text-center text-slate-500 dark:text-slate-400">
                    <div class="text-3xl mb-2">👥</div>
                    <div class="font-bold text-slate-700 dark:text-slate-200 text-sm">No customers found</div>
                    <div class="text-xs text-slate-400 mt-1">Try changing your search term or tab filter</div>
                </div>

                <!-- Mobile Pagination Card -->
                <div v-if="customers.total > 0" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs text-xs space-y-3">
                    <div class="text-center text-slate-500 dark:text-slate-400">
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ customers.from }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ customers.to }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ customers.total }}</span> customers
                    </div>
                    <div class="flex items-center justify-center gap-1 overflow-x-auto pb-1 max-w-full scrollbar-none">
                        <template v-for="(link, idx) in customers.links" :key="'mob-page-' + idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer whitespace-nowrap shrink-0"
                                :class="link.active
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                    : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-600'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-lg text-xs text-slate-400 dark:text-slate-600 border border-slate-200/50 dark:border-slate-700/50 select-none whitespace-nowrap shrink-0"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── DESKTOP TABLE VIEW (Visible on screens >= md) ─────────── -->
            <div class="hidden md:block bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-xs">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Customer</th>
                            <th class="text-left px-4 py-3 font-semibold">Phone & Email</th>
                            <th class="text-left px-4 py-3 font-semibold">Watchlist Status</th>
                            <th class="text-left px-4 py-3 font-semibold">Internal Note</th>
                            <th class="text-right px-4 py-3 font-semibold">Debt Balance</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <!-- Name -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="route('pos.customers.show', customer.id)"
                                        class="text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 font-bold transition">
                                        {{ customer.name }}
                                    </Link>
                                    <span v-if="customer.gender" class="text-[10px] text-slate-400 font-normal">
                                        ({{ customer.gender }})
                                    </span>
                                </div>
                            </td>

                            <!-- Phone & Email & WhatsApp -->
                            <td class="px-4 py-3">
                                <div class="text-slate-600 dark:text-slate-300 font-mono">{{ customer.phone }}</div>
                                <div v-if="customer.whatsapp_number && customer.whatsapp_number !== customer.phone" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono flex items-center gap-1 mt-0.5">
                                    <span>💬</span>
                                    <span>WA: {{ customer.whatsapp_number }}</span>
                                </div>
                                <div v-if="customer.email" class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[180px]">{{ customer.email }}</div>
                            </td>

                            <!-- Watchlist Toggle Button / Badge -->
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    @click="toggleWatchlist(customer)"
                                    class="px-2.5 py-1 rounded-full text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer border shadow-xs"
                                    :class="customer.watch_list
                                        ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 hover:bg-amber-500/25'
                                        : 'bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-800 dark:hover:text-slate-200'"
                                    :title="customer.watch_list ? 'Click to remove from watchlist' : 'Click to add to watchlist'"
                                >
                                    <span>{{ customer.watch_list ? '⚠️ On Watchlist' : '+ Add to Watchlist' }}</span>
                                </button>
                            </td>

                            <!-- Quick Note View & Edit Trigger -->
                            <td class="px-4 py-3 max-w-[200px]">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="openNoteModal(customer)"
                                        class="text-[11px] font-medium transition flex items-center gap-1 px-2 py-0.5 rounded-md border cursor-pointer truncate max-w-[170px]"
                                        :class="(customer.note && customer.note !== 'No Note')
                                            ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100'
                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700 hover:text-slate-600'"
                                        :title="customer.note || 'No note recorded'"
                                    >
                                        <span>📝</span>
                                        <span class="truncate">{{ (customer.note && customer.note !== 'No Note') ? customer.note : 'Add Note' }}</span>
                                    </button>
                                </div>
                            </td>

                            <!-- Debt Balance -->
                            <td class="px-4 py-3 text-right font-mono">
                                <span v-if="customer.debt_bal > 0" class="text-red-600 dark:text-red-400 font-bold bg-red-50 dark:bg-red-950/40 px-2 py-0.5 rounded border border-red-200 dark:border-red-800 text-xs inline-block">
                                    {{ format(customer.debt_bal) }}
                                </span>
                                <span v-else class="text-slate-400 dark:text-slate-500 text-xs">
                                    ₦0.00
                                </span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2.5">
                                    <Link :href="route('pos.customers.debt-ledger', customer.id)"
                                        :class="['px-2 py-1 rounded text-xs font-bold transition flex items-center gap-1 border', customer.debt_bal > 0 ? 'bg-orange-50 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800 hover:bg-orange-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:text-slate-900 dark:hover:text-white']">
                                        <span>Ledger</span>
                                        <span v-if="customer.debt_bal > 0" class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                    </Link>
                                    <Link :href="route('pos.customers.edit', customer.id)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition px-1">
                                        Edit
                                    </Link>
                                    <button
                                        @click="destroyCustomer(customer)"
                                        :disabled="customer.debt_bal > 0"
                                        :title="customer.debt_bal > 0 ? 'Cannot delete customer who owes outstanding debt' : 'Delete Customer'"
                                        class="text-xs font-semibold transition px-1 cursor-pointer"
                                        :class="customer.debt_bal > 0 ? 'text-slate-400 dark:text-slate-600 cursor-not-allowed opacity-40' : 'text-red-600 dark:text-red-400 hover:text-red-500'"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!customers.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="space-y-1">
                                    <div class="text-2xl">👥</div>
                                    <div class="font-semibold text-slate-700 dark:text-slate-300">No customers found</div>
                                    <div class="text-xs text-slate-400">Try changing your search term or tab filter</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination footer -->
                <div v-if="customers.total > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/40">
                    <div>
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200">{{ customers.from }}</span> to <span class="font-bold text-slate-800 dark:text-slate-200">{{ customers.to }}</span> of <span class="font-bold text-slate-800 dark:text-slate-200">{{ customers.total }}</span> customers
                    </div>
                    
                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in customers.links" :key="'desk-page-' + idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition border cursor-pointer"
                                :class="link.active
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                    : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-600'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-lg text-xs text-slate-400 dark:text-slate-600 border border-slate-200/50 dark:border-slate-700/50 select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Quick Note Modal ──────────────────────────────────────────────── -->
        <div v-if="showNoteModal && selectedCustomerForNote" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-md w-full overflow-hidden shadow-2xl space-y-4 p-5">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">Quick Note</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Customer: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ selectedCustomerForNote.name }}</span> ({{ selectedCustomerForNote.phone }})</p>
                    </div>
                    <button @click="closeNoteModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold transition cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="saveNote" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Internal Note</label>
                        <textarea
                            v-model="noteForm.note"
                            rows="4"
                            placeholder="Add important customer preferences, credit warnings, delivery instructions, or notes here…"
                            class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 p-3 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs leading-relaxed transition"
                        ></textarea>
                        <p v-if="noteForm.errors.note" class="text-red-500 text-xs mt-1">{{ noteForm.errors.note }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <button
                            type="button"
                            @click="closeNoteModal"
                            class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="noteForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-40 shadow-xs cursor-pointer"
                        >
                            {{ noteForm.processing ? 'Saving…' : 'Save Note' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
