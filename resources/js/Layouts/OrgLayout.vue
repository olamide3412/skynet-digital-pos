<script setup>
import { ref, provide } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import FlashMessages from '@/Components/FlashMessages.vue'

const isMobileOpen = ref(false)
const isCollapsed  = ref(false)

const toggleMobile = () => {
    isMobileOpen.value = !isMobileOpen.value
}

provide('toggleMobileSidebar', toggleMobile)
provide('isMobileSidebarOpen', isMobileOpen)

const page = usePage()

const navLinks = [
    {
        label: 'Dashboard',
        route: 'org.dashboard',
        match: 'Org/Dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'My Branches',
        route: 'org.branches',
        match: 'Org/Branches',
        icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    },
    {
        label: 'Analytics & Reports',
        route: 'org.reports',
        match: 'Org/Reports/Index',
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    },
    {
        label: 'End of Day (Z-Report)',
        route: 'org.reports.end-of-day',
        match: 'Org/Reports/EndOfDay',
        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        label: 'Ask AI Copilot',
        route: 'org.ai.chat',
        match: 'Org/AI',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
    },
    {
        label: 'AI Credits & Add-ons',
        route: 'org.credits.index',
        match: 'Org/Credits',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        label: 'Subscription & Plans',
        route: 'org.subscription.index',
        match: 'Org/Subscription',
        icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    },
    {
        label: 'Ecommerce Activity',
        route: 'org.ecommerce-activity.index',
        match: 'Org/EcommerceActivity',
        icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
    },
    {
        label: 'Activity Logs',
        route: 'org.activity-logs',
        match: 'Org/ActivityLogs',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    },
]

function isActive(link) {
    return page.component.startsWith(link.match)
}

function toggleCollapse() {
    isCollapsed.value = !isCollapsed.value
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-slate-100 dark:bg-slate-950 font-sans transition-colors">
        <!-- Mobile Overlay Backdrop -->
        <div
            v-if="isMobileOpen"
            @click="isMobileOpen = false"
            class="fixed inset-0 z-20 bg-slate-900/60 backdrop-blur-xs lg:hidden"
        ></div>

        <!-- ── Sidebar ──────────────────────────────────────────────── -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col transition-all duration-300 shadow-xl',
                'lg:relative lg:translate-x-0',
                isMobileOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
                isCollapsed ? 'lg:w-16' : 'lg:w-64'
            ]"
        >
            <!-- Brand Logo & Header -->
            <div class="flex items-center h-16 border-b border-slate-200 dark:border-slate-800 px-3 overflow-hidden flex-shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
                <Link :href="route('org.dashboard')" class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center font-black text-white text-base shadow-md flex-shrink-0">
                        🏢
                    </div>
                    <div v-show="!isCollapsed" class="whitespace-nowrap overflow-hidden transition-all duration-300">
                        <span class="text-sm font-black text-slate-900 dark:text-white leading-tight block truncate">
                            {{ $page.props.auth?.user?.organization?.name || 'My Organization' }}
                        </span>
                        <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 tracking-wider uppercase block">Organization Portal</span>
                    </div>
                </Link>
                <!-- Desktop Sidebar Collapse Toggle -->
                <button
                    @click="toggleCollapse"
                    class="hidden lg:flex ml-auto items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex-shrink-0"
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <svg class="w-4 h-4 transition-transform duration-300" :class="isCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1.5">
                <template v-for="link in navLinks" :key="link.label">
                    <div class="relative group">
                        <Link
                            :href="route(link.route)"
                            @click="isMobileOpen = false"
                            :title="isCollapsed ? link.label : ''"
                            :class="[
                                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition duration-150',
                                isActive(link)
                                    ? 'bg-purple-600 text-white shadow-md shadow-purple-600/25'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed" class="truncate">{{ link.label }}</span>
                        </Link>
                    </div>
                </template>
            </nav>

            <!-- Bottom User & Exit -->
            <div class="p-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
                <div v-show="!isCollapsed" class="px-2 py-1 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-bold text-xs flex items-center justify-center flex-shrink-0">
                        {{ ($page.props.auth?.user?.name || 'A').charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $page.props.auth?.user?.name }}</div>
                        <div class="text-[10px] text-slate-400 truncate">{{ $page.props.auth?.user?.email }}</div>
                    </div>
                </div>

                <Link
                    :href="route('org.logout')"
                    method="post"
                    as="button"
                    class="w-full flex items-center gap-3 px-3 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-xl transition cursor-pointer"
                    :title="isCollapsed ? 'Log out' : ''"
                >
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span v-show="!isCollapsed">Sign Out</span>
                </Link>
            </div>
        </aside>

        <!-- ── Main Content Area ─────────────────────────────────────── -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top Bar -->
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 z-10 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleMobile"
                        class="lg:hidden p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            🏢 {{ $page.props.auth?.user?.organization?.name || 'Organization' }}
                        </span>
                        <span class="hidden sm:inline-block text-xs font-semibold text-slate-400">
                            Client Organization Account
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <ThemeToggle />
                </div>
            </header>

            <!-- Scrollable Page Canvas -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <FlashMessages />
                <slot />
            </main>
        </div>
    </div>
</template>
