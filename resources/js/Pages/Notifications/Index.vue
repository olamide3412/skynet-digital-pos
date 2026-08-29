<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const props = defineProps({
    notifications: Object,
    filters:       Object,
    stats:         Object,
})

const page = usePage()
const branchSlug = page.props.current_branch?.slug || 'branch'

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || 'unresolved')
const type   = ref(props.filters.type || 'all')

const isProcessing = ref(false)

// Debounced / Triggered Search & Filters
function applyFilters() {
    router.get(
        `/${branchSlug}/notifications`,
        {
            search: search.value || undefined,
            status: status.value !== 'unresolved' ? status.value : undefined,
            type:   type.value !== 'all' ? type.value : undefined,
        },
        { preserveState: true, replace: true }
    )
}

function setStatusTab(tab) {
    status.value = tab
    applyFilters()
}

watch(type, () => applyFilters())

let searchTimeout = null
function onSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
}

// ── Actions ───────────────────────────────────────────────────────────────

async function markAsRead(notification) {
    try {
        notification.is_read = true
        await axios.post(`/${branchSlug}/notifications/${notification.id}/read`)
    } catch (e) {
        console.error('Failed to mark read:', e)
    }
}

async function markAllAsRead() {
    if (isProcessing.value) return
    isProcessing.value = true
    try {
        await router.post(`/${branchSlug}/notifications/mark-all-read`)
    } finally {
        isProcessing.value = false
    }
}

async function resolveNotification(notification) {
    try {
        notification.is_resolved = true
        notification.is_read = true
        await router.post(`/${branchSlug}/notifications/${notification.id}/resolve`, {}, {
            preserveScroll: true,
            preserveState: true,
        })
    } catch (e) {
        console.error('Failed to resolve notification:', e)
    }
}

async function deleteNotification(notification) {
    if (!confirm('Are you sure you want to dismiss this notification?')) return
    try {
        await router.delete(`/${branchSlug}/notifications/${notification.id}`, {
            preserveScroll: true,
            preserveState: true,
        })
    } catch (e) {
        console.error('Failed to dismiss notification:', e)
    }
}

function getTypeStyle(itemType) {
    switch (itemType) {
        case 'stock_conflict':
            return {
                border: 'border-l-4 border-amber-500',
                badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
                iconBg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                label: 'Stock Conflict'
            }
        case 'imei_conflict':
            return {
                border: 'border-l-4 border-purple-500',
                badge: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30',
                iconBg: 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
                label: 'IMEI Conflict'
            }
        case 'low_stock':
            return {
                border: 'border-l-4 border-rose-500',
                badge: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30',
                iconBg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
                label: 'Low Stock'
            }
        default:
            return {
                border: 'border-l-4 border-blue-500',
                badge: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
                iconBg: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
                label: 'System Notice'
            }
    }
}
</script>

<template>
    <Head title="Notifications Center" />

    <div class="flex-1 flex flex-col overflow-y-auto bg-slate-50 dark:bg-slate-950 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl w-full mx-auto space-y-6">

            <!-- ── Top Header ────────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                        <Link :href="`/${branchSlug}`" class="hover:text-theme transition">POS</Link>
                        <span>/</span>
                        <span class="text-slate-700 dark:text-slate-300">Notifications</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-3">
                        <span>Notifications Center</span>
                        <span
                            v-if="stats.unresolved > 0"
                            class="text-xs font-bold px-2.5 py-1 rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/20"
                        >
                            {{ stats.unresolved }} unresolved
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Review system alerts, offline sync reconciliation conflicts, and inventory triggers.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        v-if="stats.unread > 0"
                        @click="markAllAsRead"
                        :disabled="isProcessing"
                        class="px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-850 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <svg class="w-4 h-4 text-theme" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Mark All Read</span>
                    </button>

                    <Link
                        :href="`/${branchSlug}/inventory/conflicts`"
                        class="px-4 py-2 text-xs font-bold text-white bg-theme hover:bg-theme-dark rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Manage Stock Conflicts</span>
                    </Link>
                </div>
            </div>

            <!-- ── Summary Statistics Cards ──────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <!-- Unresolved Card -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl flex-shrink-0">
                        ⚡
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Unresolved Issues</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.unresolved }}</div>
                    </div>
                </div>

                <!-- Stock Conflicts Card -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl flex-shrink-0">
                        ⚠️
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Stock & IMEI Conflicts</div>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.stock_conflicts }}</div>
                    </div>
                </div>

                <!-- Unread Card -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl flex-shrink-0">
                        📬
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Unread Alerts</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.unread }}</div>
                    </div>
                </div>

                <!-- Resolved Card -->
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl flex-shrink-0">
                        ✅
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Resolved History</div>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.resolved }}</div>
                    </div>
                </div>
            </div>

            <!-- ── Filters & Tabs Bar ────────────────────────────────────── -->
            <div class="p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                <!-- Status Tabs -->
                <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl overflow-x-auto">
                    <button
                        @click="setStatusTab('unresolved')"
                        :class="[
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap',
                            status === 'unresolved'
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        Unresolved ({{ stats.unresolved }})
                    </button>
                    <button
                        @click="setStatusTab('unread')"
                        :class="[
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap',
                            status === 'unread'
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        Unread ({{ stats.unread }})
                    </button>
                    <button
                        @click="setStatusTab('all')"
                        :class="[
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap',
                            status === 'all'
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        All ({{ stats.total }})
                    </button>
                    <button
                        @click="setStatusTab('resolved')"
                        :class="[
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap',
                            status === 'resolved'
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        Resolved ({{ stats.resolved }})
                    </button>
                </div>

                <!-- Search & Type Filters -->
                <div class="flex items-center gap-2">
                    <!-- Type Selector -->
                    <select
                        v-model="type"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-hidden focus:ring-2 focus:ring-theme"
                    >
                        <option value="all">All Alert Types</option>
                        <option value="stock_conflict">Stock Conflicts</option>
                        <option value="imei_conflict">IMEI Collisions</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="system_alert">System Alerts</option>
                    </select>

                    <!-- Search Input -->
                    <div class="relative flex-1 sm:w-64">
                        <input
                            v-model="search"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Search notifications..."
                            class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-theme"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- ── Notifications List ───────────────────────────────────── -->
            <div class="space-y-3">
                <template v-if="notifications.data && notifications.data.length > 0">
                    <div
                        v-for="item in notifications.data"
                        :key="item.id"
                        :class="[
                            'p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs transition duration-150 flex flex-col md:flex-row items-start md:items-center justify-between gap-4',
                            getTypeStyle(item.type).border,
                            !item.is_read ? 'ring-1 ring-theme/20 bg-indigo-50/20 dark:bg-indigo-950/15' : ''
                        ]"
                    >
                        <!-- Left Details -->
                        <div class="flex items-start gap-3.5 flex-1 min-w-0">
                            <div
                                :class="[
                                    'w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 shadow-2xs',
                                    getTypeStyle(item.type).iconBg
                                ]"
                            >
                                <svg v-if="item.type === 'stock_conflict'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <svg v-else-if="item.type === 'imei_conflict'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ item.title }}
                                    </h3>
                                    <span
                                        :class="[
                                            'text-[10px] font-bold px-2 py-0.5 rounded-full border',
                                            getTypeStyle(item.type).badge
                                        ]"
                                    >
                                        {{ getTypeStyle(item.type).label }}
                                    </span>
                                    <span
                                        v-if="item.is_resolved"
                                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20"
                                    >
                                        Resolved
                                    </span>
                                    <span
                                        v-else
                                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/20"
                                    >
                                        Action Required
                                    </span>
                                </div>

                                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                    {{ item.message }}
                                </p>

                                <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400 pt-1">
                                    <span>🕒 {{ new Date(item.created_at).toLocaleString() }}</span>
                                    <span v-if="item.is_resolved && item.resolver">
                                        • Resolved by <strong class="text-slate-600 dark:text-slate-300">{{ item.resolver.name }}</strong>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center gap-2 self-end md:self-center flex-shrink-0">
                            <!-- Direct Action Link (e.g. Go to Stock Conflict) -->
                            <Link
                                v-if="item.action_url"
                                :href="item.action_url"
                                @click="markAsRead(item)"
                                class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-800 dark:text-white transition flex items-center gap-1 cursor-pointer"
                            >
                                <span>Open Details</span>
                                <span class="text-sm">↗</span>
                            </Link>

                            <!-- Resolve Button -->
                            <button
                                v-if="!item.is_resolved"
                                @click="resolveNotification(item)"
                                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-white shadow-xs transition flex items-center gap-1 cursor-pointer"
                                title="Mark as resolved"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Resolve</span>
                            </button>

                            <!-- Mark as Read Button -->
                            <button
                                v-if="!item.is_read"
                                @click="markAsRead(item)"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                                title="Mark as read"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>

                            <!-- Dismiss / Delete Button -->
                            <button
                                @click="deleteNotification(item)"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer"
                                title="Dismiss notification"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div v-else class="py-16 px-4 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs">
                    <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3 text-3xl shadow-inner">
                        🌿
                    </div>
                    <h3 class="text-base font-black text-slate-800 dark:text-white">No notifications found</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">
                        {{ status === 'unresolved' ? 'Great job! There are no unresolved stock conflicts or alerts in this branch.' : 'No notification history matching your filter criteria.' }}
                    </p>
                </div>
            </div>

            <!-- ── Pagination ───────────────────────────────────────────── -->
            <div v-if="notifications.links && notifications.links.length > 3" class="flex items-center justify-between pt-2">
                <p class="text-xs text-slate-400">
                    Showing {{ notifications.from || 0 }} to {{ notifications.to || 0 }} of {{ notifications.total }} notifications
                </p>
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in notifications.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                link.active
                                    ? 'bg-theme text-white'
                                    : 'bg-white dark:bg-slate-850 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'
                            ]"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-3 py-1.5 rounded-lg text-xs text-slate-400 opacity-50 select-none"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

        </div>
    </div>
</template>
