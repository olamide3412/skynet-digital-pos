<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import FlashMessages from '@/Components/FlashMessages.vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Laundry Terminal',
    },
})

const page = usePage()
const isMobileOpen = ref(false)
const isCollapsed  = ref(false)
const isOnline     = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)

const perms = computed(() => page.props.pos_permissions ?? {})
const posSettings = computed(() => page.props.pos_settings ?? {})
const currentBranch = computed(() => page.props.current_branch ?? {})
const authUser = computed(() => page.props.auth?.user ?? {})

const isPosEnabled = computed(() => Boolean(posSettings.value?.pos_enabled ?? true))
const canAccessPos = computed(() => {
    if (!isPosEnabled.value) return false
    const isFullAdmin = Boolean(
        authUser.value?.is_super_admin ||
        perms.value?.isSuperAdmin ||
        perms.value?.isBranchAdmin ||
        authUser.value?.roles?.some(r => r.name === 'branch-admin' || r.name === 'organization_admin')
    )
    return isFullAdmin || Boolean(perms.value?.canAccessPos)
})

const currentBranchSlug = computed(() => {
    if (page.props.current_branch?.slug) return page.props.current_branch.slug
    if (page.props.branch?.slug) return page.props.branch.slug
    if (page.props.auth?.user?.branch?.slug) return page.props.auth.user.branch.slug
    if (typeof window !== 'undefined') {
        const seg = window.location.pathname.split('/').filter(Boolean)[0]
        const reserved = ['superadmin', 'org', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'login', 'logout', 'register', 'password']
        if (seg && !reserved.includes(seg)) return seg
    }
    return route().params?.branch || ''
})

const safeRoute = (routeName, params = {}) => {
    if (!routeName) return '#'
    return route(routeName, { branch: currentBranchSlug.value, ...params })
}

const toggleMobile = () => {
    isMobileOpen.value = !isMobileOpen.value
}

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value
    localStorage.setItem('laundry_sidebar_collapsed', isCollapsed.value)
}

const updateOnlineStatus = () => {
    isOnline.value = navigator.onLine
}

const handleLogout = () => {
    router.post(safeRoute('pos.logout'))
}

onMounted(() => {
    window.addEventListener('online', updateOnlineStatus)
    window.addEventListener('offline', updateOnlineStatus)
    const saved = localStorage.getItem('laundry_sidebar_collapsed')
    if (saved !== null) isCollapsed.value = saved === 'true'
})

onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus)
    window.removeEventListener('offline', updateOnlineStatus)
})

const isActive = (match) => {
    const comp = page.component
    if (!match) return false
    return comp === match || comp.startsWith(match)
}
</script>

<template>
    <div class="flex h-screen bg-slate-100 dark:bg-slate-900 overflow-hidden font-sans text-slate-800 dark:text-slate-100 transition-colors duration-300">

        <!-- Offline Banner -->
        <div
            v-if="!isOnline"
            class="fixed top-0 inset-x-0 z-50 bg-amber-600 text-white text-xs px-4 py-2 font-medium flex items-center justify-between shadow-lg"
        >
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 4.243a9 9 0 01-12.728 0m0 0l2.829-2.829m-2.829 2.829L3 21m2.828-12.728a5 5 0 017.072 0m-7.072 0l2.829 2.829" />
                </svg>
                <span>You are currently offline. Laundry operations require an active network connection.</span>
            </div>
            <div v-if="canAccessPos">
                <Link
                    :href="safeRoute('pos.index')"
                    class="bg-white text-amber-700 hover:bg-amber-50 px-2.5 py-1 rounded text-xs font-semibold inline-flex items-center gap-1 shadow-xs transition"
                >
                    <span>Switch to POS (Offline Enabled)</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>

        <!-- Mobile Overlay Backdrop -->
        <div
            v-if="isMobileOpen"
            @click="isMobileOpen = false"
            class="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-xs lg:hidden transition-opacity"
        ></div>

        <!-- Laundry Dedicated Sidebar -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 transition-all duration-300 select-none shadow-2xl lg:shadow-none',
            'lg:relative lg:translate-x-0',
            isMobileOpen ? 'translate-x-0' : '-translate-x-full',
            isCollapsed ? 'lg:w-20' : 'lg:w-64',
            'w-64'
        ]">
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between h-16 px-4 border-b border-slate-200 dark:border-slate-700 bg-white/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-cyan-500 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-500/20 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 4h-3V2h-2v2h-4V2H8v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V8h14v12z M12 9c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z" />
                        </svg>
                    </div>
                    <div v-show="!isCollapsed" class="flex flex-col min-w-0 transition-opacity duration-200">
                        <span class="font-bold text-sm text-slate-900 dark:text-white truncate">
                            {{ currentBranch.name || 'Laundry POS' }}
                        </span>
                        <span class="text-[11px] font-semibold tracking-wider text-indigo-600 dark:text-indigo-400 uppercase">
                            Laundry Terminal
                        </span>
                    </div>
                </div>

                <!-- Collapse Button (Desktop) -->
                <button
                    @click="toggleCollapse"
                    class="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition"
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5 scrollbar-thin">

                <!-- 1. POS Switch Link (Top of sidebar per §5.3, only if POS on and user has POS access) -->
                <div v-if="canAccessPos" class="mb-3 pb-3 border-b border-slate-200 dark:border-slate-700">
                    <Link
                        :href="safeRoute('pos.index')"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-semibold text-xs tracking-wide text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition group"
                        :title="isCollapsed ? 'Switch to Retail POS' : undefined"
                    >
                        <div class="p-1 rounded-lg bg-emerald-500 text-white shrink-0 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5" />
                            </svg>
                        </div>
                        <span v-show="!isCollapsed" class="truncate font-bold">Switch to Retail POS</span>
                    </Link>
                </div>

                <!-- 2. Laundry Home / Dashboard -->
                <Link
                    :href="safeRoute('laundry.home')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Home')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Dashboard' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Dashboard</span>
                </Link>

                <!-- 3. New Order Intake -->
                <Link
                    :href="safeRoute('laundry.orders.create')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Orders/Create')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'New Order' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate font-semibold">New Intake Order</span>
                </Link>

                <!-- 4. Workflow Board (Kanban) -->
                <Link
                    :href="safeRoute('laundry.board.index')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Orders/Board')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Workflow Board' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Workflow Board</span>
                </Link>

                <!-- 5. All Orders (Data Table) -->
                <Link
                    :href="safeRoute('laundry.orders.index')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Orders/Index') || isActive('Laundry/Orders/Show')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'All Orders' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">All Orders</span>
                </Link>

                <!-- 5. Customers (Shared) -->
                <Link
                    :href="safeRoute('pos.customers.index')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Customers/Index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Customers' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Customers & Debt</span>
                </Link>

                <!-- 6. Services (Catalog) -->
                <Link
                    :href="safeRoute('laundry.services.index')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Services/Index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Services' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Services & Prices</span>
                </Link>

                <!-- 7. Discounts & Promos -->
                <Link
                    v-if="perms.isBranchAdmin || perms.isSuperAdmin || perms['laundry.manage_discounts'] || perms['laundry.manage_services']"
                    :href="safeRoute('laundry.discounts.index')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Discounts/Index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Discounts' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Discounts & Promos</span>
                </Link>

                <!-- 8. Reports -->
                <Link
                    :href="safeRoute('laundry.reports.index')"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Laundry/Reports/Index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Reports' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Laundry Reports</span>
                </Link>

                <!-- 8. Settings -->
                <Link
                    :href="safeRoute('pos.settings.index', { tab: 'laundry' })"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group',
                        isActive('Settings/Index')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60'
                    ]"
                    :title="isCollapsed ? 'Settings' : undefined"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span v-show="!isCollapsed" class="truncate">Laundry Settings</span>
                </Link>

                <!-- Section Divider: Shared Operations -->
                <div class="pt-3 mt-3 border-t border-slate-200 dark:border-slate-700">
                    <p v-show="!isCollapsed" class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                        Shared Management
                    </p>

                    <!-- Till Sessions -->
                    <Link
                        :href="safeRoute('pos.till.sessions.index')"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition"
                        :title="isCollapsed ? 'Till Sessions' : undefined"
                    >
                        <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span v-show="!isCollapsed" class="truncate">Till & Cash Sessions</span>
                    </Link>

                    <!-- Expenses -->
                    <Link
                        :href="safeRoute('pos.reports.expenses')"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition"
                        :title="isCollapsed ? 'Expenses' : undefined"
                    >
                        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span v-show="!isCollapsed" class="truncate">Expenses & Supplies</span>
                    </Link>

                    <!-- Users & Staff -->
                    <Link
                        v-if="perms.canManageUsers"
                        :href="safeRoute('pos.users.index')"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition"
                        :title="isCollapsed ? 'Staff Users' : undefined"
                    >
                        <svg class="w-4 h-4 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span v-show="!isCollapsed" class="truncate">Staff & Roles</span>
                    </Link>
                </div>
            </div>

            <!-- Sidebar User & Footer -->
            <div class="p-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/80">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center text-xs shrink-0">
                            {{ authUser.name ? authUser.name.charAt(0).toUpperCase() : 'U' }}
                        </div>
                        <div v-show="!isCollapsed" class="min-w-0">
                            <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                {{ authUser.name }}
                            </p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate capitalize">
                                {{ authUser.roles?.[0]?.name || 'Attendant' }}
                            </p>
                        </div>
                    </div>

                    <button
                        @click="handleLogout"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition shrink-0"
                        title="Sign out"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top App Bar -->
            <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 sm:px-6 z-30 shrink-0">
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger -->
                    <button
                        @click="toggleMobile"
                        class="lg:hidden p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div>
                        <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white leading-tight">
                            {{ title }}
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
                            Branch: <span class="font-medium text-slate-700 dark:text-slate-300">{{ currentBranch.name }}</span>
                        </p>
                    </div>
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Quick New Order Action -->
                    <Link
                        :href="safeRoute('laundry.orders.create')"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>New Intake</span>
                    </Link>

                    <!-- Theme Toggle -->
                    <ThemeToggle />

                    <!-- Connectivity Indicator -->
                    <div
                        :class="[
                            'flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border',
                            isOnline
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800'
                        ]"
                    >
                        <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        <span class="hidden sm:inline">{{ isOnline ? 'Online' : 'Offline' }}</span>
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 relative">
                <!-- Global Flash Notifications -->
                <FlashMessages />

                <slot />
            </main>
        </div>

    </div>
</template>
