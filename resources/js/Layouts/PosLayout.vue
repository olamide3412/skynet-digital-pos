<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import FlashMessages from '@/Components/FlashMessages.vue'

const isMobileOpen = ref(false)
const isCollapsed  = ref(false)

const page = usePage()
const perms = computed(() => page.props.pos_permissions ?? {})

const allNavLinks = [
    {
        label: 'POS',
        route: 'pos.index',
        match: 'Pos/Index',
        icon:  'M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5',
        color: 'text-emerald-400',
        always: true,
    },
    {
        label: 'Items',
        icon:  'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10',
        perm: 'canManageItems',
        children: [
            { label: 'All Items',        route: 'pos.items.index',      match: 'Items/Index' },
            { label: 'Categories',       route: 'pos.categories.index', match: 'Categories/Index' },
            { label: 'Item Grid Config', route: 'pos.item-grids.index', match: 'Items/Grid' },
            { label: 'Active Discounts', route: 'pos.discounts.index',  match: 'Discounts/Index', perm: 'canApplyDiscount' },
        ],
    },
    {
        label: 'Customers',
        route: 'pos.customers.index',
        match: 'Customers/Index',
        icon:  'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        perm: 'canManageCustomers',
    },
    {
        label: 'Sales History',
        route: 'pos.sales.index',
        match: 'Sales/Index',
        icon:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        always: true,
    },
    {
        label: 'Purchasing',
        icon:  'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17',
        perm: 'canManagePurchases',
        children: [
            { label: 'Purchase Orders', route: 'pos.purchases.index', match: 'Purchases/Index' },
            { label: 'Vendors',         route: 'pos.vendors.index',   match: 'Vendors/Index' },
        ],
    },
    {
        label: 'Inventory',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        perm: 'canAdjustStock',
        children: [
            { label: 'Inventory Logs', route: 'pos.inventory.index',  match: 'Inventory/Index' },
            { label: 'Adjust Stock',   route: 'pos.inventory.adjust', match: 'Inventory/Adjust' },
        ],
    },
    {
        label: 'End of Day',
        route: 'pos.reports.end-of-day',
        match: 'Reports/EndOfDay',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        perm: 'canViewEndOfDay',
    },
    {
        label: 'Reports',
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        perm: 'canViewReports',
        children: [
            { label: 'Dashboard',     route: 'pos.reports.index',        match: 'Reports/Index' },
            { label: 'Daily Sales',   route: 'pos.reports.daily-sales',  match: 'Reports/DailySales' },
            { label: 'Profit & Loss', route: 'pos.reports.profit-loss',  match: 'Reports/ProfitLoss' },
            { label: 'Low Stock',     route: 'pos.reports.low-stock',    match: 'Reports/LowStock' },
            { label: 'Customer Debt', route: 'pos.reports.customer-debt',match: 'Reports/CustomerDebt' },
        ],
    },
    {
        label: 'Users & Roles',
        icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        perm: 'canManageUsers',
        children: [
            { label: 'System Users', route: 'pos.users.index', match: 'Users/Index' },
            { label: 'Roles',        route: 'pos.roles.index', match: 'Roles/Index' },
        ],
    },
    {
        label: 'Settings',
        route: 'pos.settings.index',
        match: 'Settings/Index',
        icon:  'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        perm: 'canEditSettings',
    },
]

// Filter nav links based on permissions
const navLinks = computed(() => {
    return allNavLinks
        .filter(link => link.always || !link.perm || perms.value[link.perm])
        .map(link => {
            if (!link.children) return link
            return {
                ...link,
                children: link.children.filter(c => !c.perm || perms.value[c.perm])
            }
        })
        .filter(link => !link.children || link.children.length > 0)
})

const expandedMenus = ref([])

const isActive = (link) => {
    const comp = usePage().component
    if (link.match && (comp === link.match || comp.startsWith(link.match))) return true
    if (link.children) return link.children.some(c => comp === c.match || comp.startsWith(c.match))
    return false
}

const isChildActive = (child) => {
    const comp = usePage().component
    return comp === child.match || comp.startsWith(child.match)
}

const toggleMenu = (label) => {
    if (expandedMenus.value.includes(label)) {
        expandedMenus.value = expandedMenus.value.filter(l => l !== label)
    } else {
        expandedMenus.value.push(label)
    }
}

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value
    localStorage.setItem('pos_sidebar_collapsed', isCollapsed.value)
}

onMounted(() => {
    const saved = localStorage.getItem('pos_sidebar_collapsed')
    if (saved !== null) isCollapsed.value = saved === 'true'
    navLinks.value.forEach(link => {
        if (link.children && isActive(link)) expandedMenus.value.push(link.label)
    })
})
</script>

<template>
    <div class="flex h-screen bg-slate-900 overflow-hidden font-sans text-slate-100">

        <!-- Sidebar -->
        <aside :class="[
            'flex-shrink-0 flex flex-col bg-slate-800 border-r border-slate-700 transition-all duration-300',
            isCollapsed ? 'w-14' : 'w-56',
        ]">
            <!-- Brand -->
            <div class="h-12 flex items-center border-b border-slate-700 px-3 gap-2 overflow-hidden">
                <div class="w-7 h-7 rounded-lg bg-emerald-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5"/>
                    </svg>
                </div>
                <span v-show="!isCollapsed" class="font-bold text-emerald-400 text-sm whitespace-nowrap overflow-hidden">SkyNet POS</span>
                <button @click="toggleCollapse" class="ml-auto text-slate-500 hover:text-white transition flex-shrink-0">
                    <svg class="w-4 h-4" :class="isCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-2 px-2 space-y-0.5">
                <template v-for="link in navLinks" :key="link.label">
                    <div class="relative group">
                        <!-- Standalone -->
                        <Link v-if="link.route"
                            :href="route(link.route)"
                            :title="isCollapsed ? link.label : ''"
                            :class="[
                                'flex items-center rounded-lg py-2 text-xs font-medium transition',
                                isCollapsed ? 'justify-center px-2' : 'px-3 gap-2.5',
                                isActive(link) ? 'bg-emerald-600/20 text-emerald-400' : 'text-slate-400 hover:bg-slate-700 hover:text-white',
                            ]">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed">{{ link.label }}</span>
                        </Link>

                        <!-- Group -->
                        <button v-else @click="toggleMenu(link.label)"
                            :class="[
                                'w-full flex items-center rounded-lg py-2 text-xs font-medium transition',
                                isCollapsed ? 'justify-center px-2' : 'px-3 gap-2.5',
                                isActive(link) ? 'bg-emerald-600/20 text-emerald-400' : 'text-slate-400 hover:bg-slate-700 hover:text-white',
                            ]">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed" class="flex-1 text-left">{{ link.label }}</span>
                            <svg v-show="!isCollapsed" class="w-3 h-3 transition-transform" :class="expandedMenus.includes(link.label) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Children -->
                        <div v-show="link.children && !isCollapsed && expandedMenus.includes(link.label)" class="pl-8 pr-2 mt-0.5 space-y-0.5">
                            <Link v-for="child in link.children" :key="child.label"
                                :href="route(child.route)"
                                :class="[
                                    'block px-3 py-1.5 rounded-lg text-xs transition',
                                    isChildActive(child) ? 'text-emerald-400 bg-emerald-600/10 font-medium' : 'text-slate-500 hover:text-white hover:bg-slate-700',
                                ]">{{ child.label }}</Link>
                        </div>
                    </div>
                </template>
            </nav>

            <!-- Logout -->
            <div class="p-2 border-t border-slate-700">
                <Link :href="route('pos.logout')" method="post" as="button"
                    :class="['flex items-center rounded-lg py-2 text-xs text-red-400 hover:bg-red-400/10 transition w-full', isCollapsed ? 'justify-center px-2' : 'px-3 gap-2.5']">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span v-show="!isCollapsed">Logout</span>
                </Link>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 flex flex-col overflow-hidden min-w-0">
            <slot />
        </main>

        <FlashMessages />
    </div>
</template>
