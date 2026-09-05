<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'

const page = usePage()

const isOpen = ref(false)
const popoverRef = ref(null)
const isMarkingAll = ref(false)

// Local reactive state initialized from Inertia shared props
const liveUnreadCount = ref(page.props.unread_notifications_count ?? 0)
const liveNotifications = ref(page.props.recent_notifications ?? [])

const unreadCount = computed(() => {
    return page.props.unread_notifications_count ?? liveUnreadCount.value
})

const notifications = computed(() => {
    return liveNotifications.value.length > 0
        ? liveNotifications.value
        : (page.props.recent_notifications ?? [])
})

// Current branch slug
const branchSlug = computed(() => {
    return page.props.current_branch?.slug || 'branch'
})

function toggleDropdown() {
    isOpen.value = !isOpen.value
    if (isOpen.value) {
        fetchLatestPreview()
    }
}

function closeDropdown() {
    isOpen.value = false
}

// Click outside handler
function handleClickOutside(event) {
    if (popoverRef.value && !popoverRef.value.contains(event.target)) {
        closeDropdown()
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    // Optional periodic refresh every 45s
    const timer = setInterval(fetchLatestPreview, 45000)
    onUnmounted(() => {
        document.removeEventListener('click', handleClickOutside)
        clearInterval(timer)
    })
})

async function fetchLatestPreview() {
    try {
        const url = `/${branchSlug.value}/notifications/preview`
        const res = await axios.get(url)
        if (res.data) {
            liveUnreadCount.value = res.data.unread_count ?? 0
            liveNotifications.value = res.data.notifications ?? []
        }
    } catch (e) {
        // Fallback silently if offline or network error
    }
}

async function markAsRead(notification, event) {
    if (event) event.stopPropagation()
    try {
        notification.is_read = true
        if (liveUnreadCount.value > 0) liveUnreadCount.value--
        const url = `/${branchSlug.value}/notifications/${notification.id}/read`
        await axios.post(url)
    } catch (e) {
        console.error('Failed to mark notification as read:', e)
    }
}

async function markAllAsRead() {
    if (isMarkingAll.value) return
    isMarkingAll.value = true
    try {
        const url = `/${branchSlug.value}/notifications/mark-all-read`
        await axios.post(url)
        liveUnreadCount.value = 0
        liveNotifications.value.forEach(n => { n.is_read = true })
    } catch (e) {
        console.error('Failed to mark all as read:', e)
    } finally {
        isMarkingAll.value = false
    }
}

function handleNotificationClick(notification) {
    markAsRead(notification)
    closeDropdown()
    if (notification.action_url) {
        router.visit(notification.action_url)
    } else {
        router.visit(`/${branchSlug.value}/notifications`)
    }
}

function getTypeIcon(type) {
    switch (type) {
        case 'stock_conflict':
            return {
                bg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
                label: 'Stock Conflict'
            }
        case 'held_sale_conflict':
            return {
                bg: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                badge: 'bg-purple-500/15 text-purple-700 dark:text-purple-300',
                label: 'Held Sale Conflict'
            }
        case 'imei_conflict':
            return {
                bg: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                badge: 'bg-purple-500/15 text-purple-700 dark:text-purple-300',
                label: 'IMEI Collision'
            }
        case 'low_stock':
            return {
                bg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                badge: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
                label: 'Low Stock'
            }
        default:
            return {
                bg: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
                badge: 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
                label: 'System Notice'
            }
    }
}
</script>

<template>
    <div ref="popoverRef" class="relative inline-block text-left">
        <!-- ── Bell Button ──────────────────────────────────────────────── -->
        <button
            @click.stop="toggleDropdown"
            type="button"
            class="relative p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:text-theme dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition duration-150 focus:outline-hidden cursor-pointer"
            :title="unreadCount > 0 ? `${unreadCount} unread notifications` : 'Notifications'"
            aria-label="Notifications"
        >
            <!-- Bell Icon -->
            <svg class="w-5 h-5 transition-transform duration-150" :class="{ 'scale-105': isOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>

            <!-- Unread Badge Counter with Pulse Animation -->
            <span
                v-if="unreadCount > 0"
                class="absolute top-1 right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-black text-white bg-rose-600 rounded-full ring-2 ring-white dark:ring-slate-900 shadow-md transform translate-x-1/4 -translate-y-1/4 animate-pulse"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- ── Dropdown Flyout Menu ──────────────────────────────────────── -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95 -translate-y-2"
            enter-to-class="transform opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="transform opacity-100 scale-100 translate-y-0"
            leave-to-class="transform opacity-0 scale-95 -translate-y-2"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl z-50 overflow-hidden backdrop-blur-md"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/70 dark:bg-slate-850/50">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-slate-900 dark:text-white">Notifications</span>
                        <span
                            v-if="unreadCount > 0"
                            class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-400"
                        >
                            {{ unreadCount }} new
                        </span>
                    </div>

                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        :disabled="isMarkingAll"
                        class="text-xs font-semibold text-theme hover:text-theme-dark dark:hover:text-theme-light transition cursor-pointer disabled:opacity-50"
                    >
                        {{ isMarkingAll ? 'Marking...' : 'Mark all read' }}
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-[380px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
                    <template v-if="notifications.length > 0">
                        <div
                            v-for="item in notifications"
                            :key="item.id"
                            @click="handleNotificationClick(item)"
                            :class="[
                                'p-3.5 transition flex items-start gap-3 cursor-pointer group',
                                !item.is_read
                                    ? 'bg-indigo-50/30 dark:bg-indigo-950/20 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/40'
                                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/50 opacity-85 hover:opacity-100'
                            ]"
                        >
                            <!-- Icon / Type Indicator -->
                            <div
                                :class="[
                                    'w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 border shadow-xs',
                                    getTypeIcon(item.type).bg
                                ]"
                            >
                                <svg v-if="item.type === 'stock_conflict'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <svg v-else-if="item.type === 'imei_conflict'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <svg v-else class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <!-- Content Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1 mb-0.5">
                                    <h4
                                        :class="[
                                            'text-xs truncate font-bold',
                                            !item.is_read ? 'text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-300'
                                        ]"
                                    >
                                        {{ item.title }}
                                    </h4>
                                    <span class="text-[10px] text-slate-400 whitespace-nowrap flex-shrink-0">
                                        {{ item.created_at }}
                                    </span>
                                </div>

                                <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed mb-1.5">
                                    {{ item.message }}
                                </p>

                                <div class="flex items-center justify-between gap-2">
                                    <span
                                        :class="[
                                            'text-[10px] font-bold px-1.5 py-0.5 rounded-md',
                                            getTypeIcon(item.type).badge
                                        ]"
                                    >
                                        {{ getTypeIcon(item.type).label }}
                                    </span>

                                    <!-- Quick Mark Read action -->
                                    <button
                                        v-if="!item.is_read"
                                        @click="markAsRead(item, $event)"
                                        class="text-[10px] font-semibold text-slate-400 hover:text-theme dark:hover:text-white transition cursor-pointer flex items-center gap-1"
                                        title="Mark as read"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Mark read
                                    </button>
                                </div>
                            </div>

                            <!-- Unread Indicator Dot -->
                            <span v-if="!item.is_read" class="w-2 h-2 rounded-full bg-theme mt-1 flex-shrink-0"></span>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="py-10 px-4 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2 text-xl shadow-inner">
                            🎉
                        </div>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">All caught up!</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">No pending notifications at this time.</p>
                    </div>
                </div>

                <!-- Footer: Link to Full Notifications Center -->
                <div class="p-2.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-850/50 text-center">
                    <Link
                        :href="`/${branchSlug}/notifications`"
                        @click="closeDropdown"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-theme hover:text-theme-dark dark:hover:text-theme-light transition py-1 px-3 rounded-lg hover:bg-white dark:hover:bg-slate-800"
                    >
                        <span>View all notifications</span>
                        <span class="text-sm">→</span>
                    </Link>
                </div>
            </div>
        </transition>
    </div>
</template>
