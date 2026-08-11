<script setup>
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import FlashMessages from '@/Components/FlashMessages.vue'

const isMobileOpen = ref(false)
const isCollapsed  = ref(false)

const page = usePage()

const navLinks = [
    {
        label: 'Dashboard',
        route: 'superadmin.dashboard',
        match: 'SuperAdmin/Dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'Manage Branches',
        route: 'superadmin.branches.index',
        match: 'SuperAdmin/Branches',
        icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    },
    {
        label: 'Global Item Pool',
        route: 'superadmin.global-items.index',
        match: 'SuperAdmin/GlobalItems',
        icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    },
    {
        label: 'Settings',
        route: 'superadmin.settings.index',
        match: 'SuperAdmin/Settings',
        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    },
    {
        label: 'System Activity & Logs',
        route: 'superadmin.logs.index',
        match: 'SuperAdmin/Logs',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        label: 'My Profile',
        route: 'superadmin.profile.index',
        match: 'SuperAdmin/Profile',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
]

function isActive(link) {
    return page.component.startsWith(link.match)
}

function toggleMobile() {
    isMobileOpen.value = !isMobileOpen.value
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
                <Link :href="route('superadmin.dashboard')" class="flex items-center gap-3 min-w-0">
                    <img v-if="$page.props.system_config?.company_logo_url" :src="$page.props.system_config.company_logo_url" class="w-9 h-9 rounded-xl object-cover shadow-md flex-shrink-0" alt="Logo" />
                    <div v-else class="w-9 h-9 rounded-xl bg-theme flex items-center justify-center font-black text-white text-base shadow-md flex-shrink-0">
                        {{ ($page.props.system_config?.company_name || 'S').charAt(0).toUpperCase() }}
                    </div>
                    <div v-show="!isCollapsed" class="whitespace-nowrap overflow-hidden transition-all duration-300">
                        <span class="text-sm font-black text-slate-900 dark:text-white leading-tight block truncate">
                            {{ $page.props.system_config?.company_name || 'Skynet POS' }}
                        </span>
                        <span class="text-[11px] font-bold text-theme tracking-wider uppercase block">Super Admin</span>
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
                                'flex w-full items-center rounded-xl font-semibold transition-all cursor-pointer text-sm',
                                isCollapsed ? 'justify-center px-0 py-3' : 'px-3.5 py-2.5 gap-3',
                                isActive(link)
                                    ? 'bg-theme text-white shadow-md'
                                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-900 dark:hover:text-white'
                            ]"
                        >
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed" class="whitespace-nowrap truncate">
                                {{ link.label }}
                            </span>
                            <!-- Tooltip when collapsed -->
                            <span v-show="isCollapsed" class="absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50">
                                {{ link.label }}
                            </span>
                        </Link>
                    </div>
                </template>
            </nav>

            <!-- SuperAdmin User Card & Logout Button -->
            <div class="p-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex-shrink-0">
                <Link
                    :href="route('superadmin.profile.index')"
                    @click="isMobileOpen = false"
                    v-show="!isCollapsed"
                    class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-200/60 dark:hover:bg-slate-800/60 transition mb-2 group"
                >
                    <div class="w-8 h-8 rounded-lg bg-theme-light text-theme flex items-center justify-center font-bold text-xs border border-theme flex-shrink-0">
                        {{ $page.props.auth.user?.name?.charAt(0)?.toUpperCase() || 'S' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-theme transition">{{ $page.props.auth.user?.name || 'Super Admin' }}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ $page.props.auth.user?.email }}</p>
                    </div>
                </Link>

                <Link
                    :href="route('superadmin.logout')"
                    method="post"
                    as="button"
                    :title="isCollapsed ? 'Logout' : ''"
                    :class="[
                        'flex w-full items-center rounded-xl text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors group relative',
                        isCollapsed ? 'justify-center py-3' : 'px-3 py-2 gap-2'
                    ]"
                >
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span v-show="!isCollapsed">Logout Account</span>
                    <span v-show="isCollapsed"
                        class="absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50">
                        Logout
                    </span>
                </Link>
            </div>
        </aside>

        <!-- ── Main Content Area ─────────────────────────────────────────── -->
        <main class="flex-1 flex flex-col overflow-hidden min-w-0 min-h-0">
            <!-- Top Header -->
            <header class="h-16 flex-shrink-0 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 sm:px-6">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Mobile Hamburger Toggle Button -->
                    <button @click="toggleMobile" class="lg:hidden text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white focus:outline-none p-1 flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 truncate">
                        {{ $page.props.system_config?.company_name || 'Skynet' }} Super Admin
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    
                    <div class="h-5 w-px bg-slate-200 dark:bg-slate-800 mx-1 hidden sm:block"></div>

                    <!-- User Quick Menu -->
                    <Link :href="route('superadmin.profile.index')" class="flex items-center gap-2 hover:opacity-80 transition cursor-pointer">
                        <div class="h-8 w-8 rounded-xl bg-theme flex items-center justify-center text-white font-bold text-xs shadow-md">
                            {{ $page.props.auth.user?.name?.charAt(0)?.toUpperCase() || 'S' }}
                        </div>
                        <span class="text-xs font-bold text-slate-800 dark:text-white hidden sm:block">{{ $page.props.auth.user?.name || 'Super Admin' }}</span>
                    </Link>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto bg-slate-50 dark:bg-slate-950 p-4 sm:p-6 lg:p-8">
                <slot/>
            </div>

        </main>

        <FlashMessages/>
    </div>
</template>
