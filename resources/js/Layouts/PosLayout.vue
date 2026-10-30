<script setup>
import { ref, computed, onMounted, onUnmounted, provide } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import FlashMessages from '@/Components/FlashMessages.vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'

const isMobileOpen = ref(false)
const isCollapsed  = ref(false)

const toggleMobile = () => {
    isMobileOpen.value = !isMobileOpen.value
}

defineExpose({
    toggleMobile,
    isMobileOpen,
})

const handleSidebarToggleEvent = () => {
    toggleMobile()
}

provide('toggleMobileSidebar', toggleMobile)
provide('isMobileSidebarOpen', isMobileOpen)

const page = usePage()
const perms = computed(() => page.props.pos_permissions ?? {})
const planFeatures = computed(() => page.props.plan_features ?? null)

function isPlanFeatureEnabled(key) {
    if (!key) return true
    if (!planFeatures.value) return true // Unrestricted / grandfathered
    return Boolean(planFeatures.value[key] ?? true)
}

function handleLogout() {
    if (typeof document !== 'undefined' && (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement)) {
        try {
            if (document.exitFullscreen) document.exitFullscreen()
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen()
            else if (document.mozCancelFullScreen) document.mozCancelFullScreen()
            else if (document.msExitFullscreen) document.msExitFullscreen()
        } catch (e) {
            console.warn('Exit fullscreen on logout error:', e)
        }
    }
    router.post(safeRoute('pos.logout'))
}

const allNavLinks = [
    {
        label: 'POS',
        route: 'pos.index',
        match: 'Pos/Index',
        icon:  'M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5',
        color: 'text-emerald-500',
        perm:  'canAccessPos',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
    },
    {
        label: 'Laundry',
        icon:  'M19 4h-3V2h-2v2h-4V2H8v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V8h14v12z M12 9c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z',
        color: 'text-indigo-500',
        perm:  'laundry.access',
        condition: () => Boolean(page.props.pos_settings?.laundry_enabled),
        children: [
            { label: 'Overview',           route: 'laundry.home',           match: 'Laundry/Home' },
            { label: 'New Intake Order',   route: 'laundry.orders.create',  match: 'Laundry/Orders/Create', perm: 'laundry.create_order' },
            { label: 'Workflow Board',     route: 'laundry.board.index',    match: 'Laundry/Orders/Board' },
            { label: 'All Orders',         route: 'laundry.orders.index',   match: ['Laundry/Orders/Index', 'Laundry/Orders/Show'] },
            { label: 'Services & Pricing', route: 'laundry.services.index', match: 'Laundry/Services/Index', perm: 'laundry.manage_services' },
            { label: 'Discounts & Promos', route: 'laundry.discounts.index', match: 'Laundry/Discounts/Index', perm: 'laundry.manage_discounts' },
            { label: 'End of Day',         route: 'laundry.reports.end-of-day', match: 'Laundry/Reports/EndOfDay', perm: 'canViewEndOfDay' },
            { label: 'Laundry Reports',    route: 'laundry.reports.index',  match: 'Laundry/Reports/Index', perm: 'laundry.view_reports' },
            { label: 'Laundry Settings',   route: 'pos.settings.index',     params: { tab: 'laundry' }, match: 'Settings/Index', perm: 'laundry.manage_settings' },
        ],
    },
    {
        label: 'Items',
        icon:  'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10',
        perm: 'canManageItems',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
        children: [
            { label: 'All Items',              route: 'pos.items.index',           match: 'Items/Index' },
            { label: 'IMEI / Device Tracking', route: 'pos.items.imeis',           match: 'Items/Imeis', feature: 'imei_tracking' },
            { label: 'Print Barcodes',         route: 'pos.items.barcodes',        match: 'Items/Barcodes', perm: 'canManageBarcodes', feature: 'barcode_label_printing' },
            { label: 'Categories',             route: 'pos.categories.index',      match: 'Categories/Index' },
            { label: 'Group / Address',        route: 'pos.group-addresses.index', match: 'GroupAddresses/Index' },
            { label: 'Item Grid Config',       route: 'pos.item-grids.index',      match: 'Items/Grid' },
            { label: 'Active Discounts',       route: 'pos.discounts.index',       match: 'Discounts/Index', perm: 'canApplyDiscount', feature: 'discounts' },
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
        perm:  'canViewSales',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
    },
    {
        label: 'Purchasing',
        icon:  'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17',
        perm: 'canManagePurchases',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
        children: [
            { label: 'Purchase Orders', route: 'pos.purchases.index', match: 'Purchases/Index' },
            { label: 'Vendors',         route: 'pos.vendors.index',   match: 'Vendors/Index' },
        ],
    },
    {
        label: 'Inventory',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
        children: [
            { label: 'Stock Conflicts', route: 'pos.inventory.conflicts.index', match: 'Inventory/Conflicts',     perm: 'canAdjustStock' },
            { label: 'Reorder Points',  route: 'pos.inventory.reorder-points',  match: 'Inventory/ReorderPoints', perm: 'canViewReorderPoints' },
            { label: 'Inventory Logs',  route: 'pos.inventory.index',           match: 'Inventory/Index',         perm: 'canAdjustStock' },
            { label: 'Adjust Stock',    route: 'pos.inventory.adjust',          match: 'Inventory/Adjust',        perm: 'canAdjustStock' },
            { label: 'Stock Transfer',  route: 'pos.inventory.transfers.index', match: 'Inventory/StockTransfer', perm: 'canTransferStock' },
            { label: 'Inter-Branch Transfers', route: 'pos.inventory.transfers.inter-branch.index', match: 'Inventory/InterBranchTransfers', perm: 'canTransferInterBranchStock' },
        ],
    },
    {
        label: () => page.props.pos_settings?.laundry_enabled ? 'POS End of Day' : 'End of Day',
        route: 'pos.reports.end-of-day',
        match: 'Reports/EndOfDay',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        perm: 'canViewEndOfDay',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
    },
    {
        label: 'Reports',
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        perm: 'canViewReports',
        children: [
            { label: 'Dashboard',      route: 'pos.reports.index',        match: 'Reports/Index', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Till Sessions',  route: 'pos.till.sessions.index',  match: 'Reports/TillSessions', perm: 'canViewEndOfDay' },
            { label: 'Sales Report',   route: 'pos.reports.daily-sales',  match: 'Reports/DailySales', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Expenses',       route: 'pos.reports.expenses',     match: 'Reports/Expenses', perm: 'canViewExpenseReports' },
            { label: 'Returned Items', route: 'pos.reports.returns',      match: 'Reports/Returns', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Profit & Loss',       route: 'pos.reports.profit-loss',  match: 'Reports/ProfitLoss', perm: 'canViewProfitLoss', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Item Expiry Report',  route: 'pos.reports.item-expiry',  match: 'Reports/ItemExpiry', perm: 'canViewExpiryReport', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Batch Expiry Report', route: 'pos.reports.batch-expiry', match: 'Reports/ExpiryOverview', perm: 'canViewBatchExpiryReport', feature: 'batch_expiry_tracking', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false && page.props.pos_settings?.batch_tracking_enabled) },
            { label: 'Reorder Points', route: 'pos.inventory.reorder-points', match: 'Inventory/ReorderPoints', perm: 'canViewReorderPoints', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Low Stock',      route: 'pos.reports.low-stock',    match: 'Reports/LowStock', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
            { label: 'Customer Debt',  route: 'pos.reports.customer-debt',match: 'Reports/CustomerDebt', perm: 'canManageDebt', feature: 'debt_customer_accounts' },
        ],
    },
    {
        label: 'Accounting',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        perm: 'canViewAccounting',
        feature: 'feature_accounting',
        children: [
            { label: 'Chart of Accounts', route: 'pos.accounting.accounts.index', match: 'Accounting/Accounts', perm: 'canManageChartOfAccounts' },
            { label: 'Opening Balances',  route: 'pos.accounting.opening-balances.index', match: 'Accounting/OpeningBalances', perm: 'canManageChartOfAccounts' },
            { label: 'General Ledger',    route: 'pos.accounting.ledger.index',   match: 'Accounting/Ledger',   perm: 'canViewAccounting' },
            { label: 'Journal Entries',   route: 'pos.accounting.journals.index', match: 'Accounting/Journals', perm: 'canManageJournalEntries' },
            { label: 'Financial Periods', route: 'pos.accounting.periods.index',  match: 'Accounting/Periods',  perm: 'canCloseFinancialPeriods' },
            { label: 'Unposted Queue',    route: 'pos.accounting.unposted.index', match: 'Accounting/Unposted', perm: 'canViewAccounting' },
            { label: 'Trial Balance',     route: 'pos.accounting.reports.trial-balance', match: 'Accounting/Reports/TrialBalance', perm: 'canViewAccounting' },
            { label: 'Income Statement',  route: 'pos.accounting.reports.profit-loss', match: 'Accounting/Reports/ProfitLoss', perm: 'canViewAccounting' },
            { label: 'Balance Sheet',     route: 'pos.accounting.reports.balance-sheet', match: 'Accounting/Reports/BalanceSheet', perm: 'canViewAccounting' },
            { label: 'Cash Flow',         route: 'pos.accounting.reports.cash-flow', match: 'Accounting/Reports/CashFlow', perm: 'canViewAccounting' },
            { label: 'AR Aging',          route: 'pos.accounting.reports.ar-aging', match: 'Accounting/Reports/ArAging', perm: 'canViewAccounting' },
            { label: 'VAT Summary',       route: 'pos.accounting.reports.vat', match: 'Accounting/Reports/Vat', perm: 'canViewAccounting' },
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
        label: 'AI Intelligence',
        icon:  'M13 10V3L4 14h7v7l9-11h-7z',
        children: [
            { label: 'Ask-Your-Business AI', route: 'pos.ai.chat',    match: 'AI/ChatAssistant', feature: 'ai_chat_assistant' },
            { label: 'Reorder Predictions',  route: 'pos.ai.reorder', match: 'AI/ReorderPredictions', feature: 'ai_reorder_prediction', condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false) },
        ],
    },
    {
        label: 'Notifications',
        route: 'pos.notifications.index',
        match: 'Notifications/Index',
        icon:  'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        badgeKey: 'unread_notifications_count',
        feature: 'broadcast_notifications',
        always: false,
    },
    {
        label: 'Printer Setup',
        route: 'pos.printer-setup',
        match: 'Printer/Index',
        icon:  'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
        always: true,
    },
    {
        label: 'Ecommerce Activity',
        route: 'pos.ecommerce-activity.index',
        match: 'EcommerceActivity/Index',
        icon:  'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        perm:  'view_ecommerce_activity',
        feature: 'feature_ecommerce_integration',
        condition: () => Boolean(page.props.pos_settings?.pos_enabled !== false),
    },
    {
        label: 'Settings',
        route: 'pos.settings.index',
        match: 'Settings/Index',
        icon:  'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        perm: 'canEditSettings',
    },
]

// Filter nav links based on permissions, condition, and plan feature capabilities
const navLinks = computed(() => {
    const isFullAdmin = Boolean(
        page.props.auth?.user?.is_super_admin || 
        perms.value?.isSuperAdmin || 
        perms.value?.isBranchAdmin || 
        page.props.auth?.user?.roles?.some(r => r.name === 'branch-admin' || r.name === 'organization_admin')
    )

    return allNavLinks
        .filter(link => {
            if (link.feature && !isPlanFeatureEnabled(link.feature)) return false
            if (link.condition && !link.condition()) return false
            if (link.always) return true
            if (isFullAdmin) return true
            if (!link.perm || perms.value[link.perm]) return true
            if (link.children && link.children.some(c => (!c.feature || isPlanFeatureEnabled(c.feature)) && (isFullAdmin || !c.perm || perms.value[c.perm]) && (!c.condition || c.condition()))) return true
            return false
        })
        .map(link => {
            const resolvedLabel = typeof link.label === 'function' ? link.label() : link.label
            if (!link.children) return { ...link, label: resolvedLabel }
            return {
                ...link,
                label: resolvedLabel,
                children: link.children.filter(c => {
                    if (c.feature && !isPlanFeatureEnabled(c.feature)) return false
                    if (c.condition && !c.condition()) return false
                    if (isFullAdmin) return true
                    return !c.perm || perms.value[c.perm]
                }).map(c => ({
                    ...c,
                    label: typeof c.label === 'function' ? c.label() : c.label
                }))
            }
        })
        .filter(link => !link.children || link.children.length > 0)
})

const expandedMenus = ref([])

const matchesComponent = (comp, matchTarget) => {
    if (!matchTarget) return false
    if (Array.isArray(matchTarget)) {
        return matchTarget.some(m => matchesComponent(comp, m))
    }
    return comp === matchTarget || comp.startsWith(matchTarget + '/')
}

const isActive = (link) => {
    const comp = usePage().component
    if (link.match && matchesComponent(comp, link.match)) return true
    if (link.children) return link.children.some(c => isChildActive(c))
    return false
}

const isChildActive = (child) => {
    const comp = usePage().component
    return matchesComponent(comp, child.match)
}

const toggleMenu = (label) => {
    if (expandedMenus.value.includes(label)) {
        expandedMenus.value = expandedMenus.value.filter(l => l !== label)
    } else {
        expandedMenus.value.push(label)
    }
}

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
    const target = typeof routeName === 'function' ? routeName() : routeName
    if (!target) return '#'
    return route(target, { branch: currentBranchSlug.value, ...params })
}

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value
    localStorage.setItem('pos_sidebar_collapsed', isCollapsed.value)
}

onMounted(() => {
    window.addEventListener('toggle-mobile-sidebar', handleSidebarToggleEvent)
    window.addEventListener('toggle-pos-mobile-sidebar', handleSidebarToggleEvent)
    const saved = localStorage.getItem('pos_sidebar_collapsed')
    if (saved !== null) isCollapsed.value = saved === 'true'
    navLinks.value.forEach(link => {
        if (link.children && isActive(link)) expandedMenus.value.push(link.label)
    })
})

onUnmounted(() => {
    window.removeEventListener('toggle-mobile-sidebar', handleSidebarToggleEvent)
    window.removeEventListener('toggle-pos-mobile-sidebar', handleSidebarToggleEvent)
})
</script>

<template>
    <div class="flex h-screen bg-slate-100 dark:bg-slate-900 overflow-hidden font-sans text-slate-800 dark:text-slate-100 transition-colors duration-300 print:h-auto print:overflow-visible print:bg-white print:block">

        <!-- Mobile Overlay Backdrop -->
        <div
            v-if="isMobileOpen"
            @click="isMobileOpen = false"
            class="print:hidden fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-xs lg:hidden transition-opacity"
        ></div>

        <!-- Sidebar -->
        <aside :class="[
            'print:hidden fixed inset-y-0 left-0 z-50 flex flex-col bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 transition-all duration-300 select-none shadow-2xl lg:shadow-none',
            'lg:relative lg:translate-x-0',
            isMobileOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
            isCollapsed ? 'lg:w-16 lg:overflow-visible' : 'lg:w-56 lg:overflow-hidden',
        ]">
            <!-- Brand & Toggle Header -->
            <div class="h-14 flex items-center border-b border-slate-200 dark:border-slate-700 transition-all duration-300"
                :class="isCollapsed ? 'justify-center px-1' : 'justify-between px-3.5'">
                
                <!-- Brand logo & title when expanded -->
                <div v-show="!isCollapsed || isMobileOpen" class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-theme flex items-center justify-center flex-shrink-0 shadow-xs">
                        <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5"/>
                        </svg>
                    </div>
                    <span class="font-bold text-theme text-sm whitespace-nowrap overflow-hidden truncate">
                        {{ $page.props.system_config?.company_name || 'SkyNet POS' }}
                    </span>
                </div>

                <!-- Desktop Collapse / Expand Toggle Button -->
                <button
                    @click="toggleCollapse"
                    class="hidden lg:flex w-8 h-8 items-center justify-center rounded-lg text-slate-500 hover:text-theme dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer flex-shrink-0"
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <svg class="w-4.5 h-4.5 transition-transform duration-300" :class="isCollapsed ? 'rotate-180 text-theme' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>

                <!-- Mobile Close Button -->
                <button
                    @click="isMobileOpen = false"
                    class="lg:hidden w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Nav Links -->
            <nav :class="[
                'flex-1 py-2.5 px-2 space-y-1',
                (isCollapsed && !isMobileOpen) ? 'overflow-visible' : 'overflow-y-auto'
            ]">
                <template v-for="link in navLinks" :key="link.label">
                    <div class="relative group">
                        <!-- Standalone Link -->
                        <Link v-if="link.route"
                            :href="safeRoute(link.route)"
                            @click="isMobileOpen = false"
                            :class="[
                                'flex items-center rounded-xl text-xs font-medium transition duration-150 relative',
                                (isCollapsed && !isMobileOpen) ? 'justify-center p-2.5' : 'px-3 py-2 gap-2.5',
                                isActive(link)
                                    ? 'bg-theme-light dark:bg-theme/20 text-theme-dark dark:text-white font-bold shadow-2xs'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white',
                            ]">
                            <svg class="w-4.5 h-4.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed || isMobileOpen" class="flex-1 truncate">{{ link.label }}</span>
                            <span
                                v-if="link.badgeKey && $page.props[link.badgeKey] > 0 && (!isCollapsed || isMobileOpen)"
                                class="px-1.5 py-0.5 text-[10px] font-black rounded-full bg-rose-500 text-white leading-none shadow-xs ml-auto"
                            >
                                {{ $page.props[link.badgeKey] }}
                            </span>
                            <span
                                v-if="link.badgeKey && $page.props[link.badgeKey] > 0 && isCollapsed && !isMobileOpen"
                                class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900 animate-pulse"
                            ></span>
                        </Link>

                        <!-- Standalone Tooltip Toast on Hover when Collapsed -->
                        <div v-if="isCollapsed && link.route"
                            class="absolute left-full ml-3.5 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-slate-900/95 dark:bg-slate-950 text-white text-xs font-bold rounded-lg shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-150 z-50 flex items-center gap-2 border border-slate-700/80 backdrop-blur-sm drop-shadow-lg">
                            <span class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-slate-900 dark:border-r-slate-950"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-theme inline-block"></span>
                            <span>{{ link.label }}</span>
                        </div>

                        <!-- Group Button (when has children) -->
                        <button v-if="link.children"
                            @click="toggleMenu(link.label)"
                            :class="[
                                'w-full flex items-center rounded-xl text-xs font-medium transition duration-150 cursor-pointer',
                                (isCollapsed && !isMobileOpen) ? 'justify-center p-2.5' : 'px-3 py-2 gap-2.5',
                                isActive(link)
                                    ? 'bg-theme-light dark:bg-theme/20 text-theme-dark dark:text-white font-bold shadow-2xs'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white',
                            ]">
                            <svg class="w-4.5 h-4.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed || isMobileOpen" class="flex-1 text-left">{{ link.label }}</span>
                            <svg v-show="!isCollapsed || isMobileOpen" class="w-3.5 h-3.5 transition-transform duration-200" :class="expandedMenus.includes(link.label) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Group Flyout Popover on Hover when Collapsed -->
                        <div v-if="isCollapsed && link.children"
                            class="absolute left-full ml-3.5 top-0 py-2 px-1.5 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md text-slate-800 dark:text-slate-100 rounded-xl shadow-2xl min-w-[210px] border border-slate-200 dark:border-slate-700/90 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50 space-y-1">
                            <span class="absolute right-full top-3.5 border-4 border-transparent border-r-white dark:border-r-slate-900"></span>
                            <div class="px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-theme border-b border-slate-100 dark:border-slate-800 pb-1.5 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-theme inline-block"></span>
                                {{ link.label }}
                            </div>
                            <div class="space-y-0.5 pt-0.5">
                                <Link
                                    v-for="child in link.children"
                                    :key="child.label"
                                    :href="safeRoute(child.route, child.params)"
                                    :class="[
                                        'block px-2.5 py-1.5 rounded-lg text-xs font-medium transition',
                                        isChildActive(child)
                                            ? 'bg-theme-light dark:bg-theme/25 text-theme dark:text-white font-bold'
                                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800'
                                    ]"
                                >
                                    {{ child.label }}
                                </Link>
                            </div>
                        </div>

                        <!-- Children (When Expanded) -->
                        <div v-show="link.children && (!isCollapsed || isMobileOpen) && expandedMenus.includes(link.label)" class="pl-7 pr-2 mt-1 space-y-0.5 border-l-2 border-slate-200 dark:border-slate-700 ml-4">
                            <Link v-for="child in link.children" :key="child.label"
                                :href="safeRoute(child.route, child.params)"
                                @click="isMobileOpen = false"
                                :class="[
                                    'block px-3 py-1.5 rounded-lg text-xs transition',
                                    isChildActive(child)
                                        ? 'bg-theme-light dark:bg-theme/25 text-theme dark:text-white font-bold shadow-2xs'
                                        : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-700/60',
                                ]">{{ child.label }}</Link>
                        </div>
                    </div>
                </template>
            </nav>

            <!-- User Profile -->
            <div class="p-2 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <div v-show="!isCollapsed || isMobileOpen" class="flex items-center gap-2.5 px-1.5 py-1 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-theme text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-xs">
                        {{ $page.props.auth?.user?.name ? $page.props.auth.user.name.charAt(0).toUpperCase() : 'U' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                            {{ $page.props.auth?.user?.name }}
                        </p>
                        <p class="text-[10px] font-black text-theme dark:text-purple-300 uppercase tracking-wider truncate">
                            {{ $page.props.auth?.user?.role_display || 'Staff' }}
                        </p>
                    </div>
                </div>

                <div v-if="isCollapsed && !isMobileOpen" class="relative group flex justify-center py-1">
                    <div class="w-8 h-8 rounded-full bg-theme text-white font-bold flex items-center justify-center text-xs shadow-xs cursor-default">
                        {{ $page.props.auth?.user?.name ? $page.props.auth.user.name.charAt(0).toUpperCase() : 'U' }}
                    </div>
                    <div class="absolute left-full ml-3.5 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-slate-900/95 dark:bg-slate-950 text-white text-xs rounded-lg shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-150 z-50 border border-slate-700/80 backdrop-blur-sm">
                        <div class="font-bold">{{ $page.props.auth?.user?.name }}</div>
                        <div class="text-[10px] text-theme-light font-bold uppercase tracking-wider">{{ $page.props.auth?.user?.role_display || 'Staff' }}</div>
                    </div>
                </div>
            </div>

            <!-- Theme Toggle & Logout Footer -->
            <div class="p-2 border-t border-slate-200 dark:border-slate-700 space-y-1">
                <div class="relative group" :class="['flex items-center', isCollapsed ? 'justify-center p-1' : 'px-2 py-1 justify-between']">
                    <span v-show="!isCollapsed" class="text-xs text-slate-500 dark:text-slate-400 font-medium">Theme</span>
                    <ThemeToggle />
                    
                    <div v-if="isCollapsed"
                        class="absolute left-full ml-3.5 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-slate-900/95 dark:bg-slate-950 text-white text-xs font-semibold rounded-lg shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-150 z-50 border border-slate-700/80 backdrop-blur-sm">
                        <span class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-slate-900 dark:border-r-slate-950"></span>
                        Toggle Theme
                    </div>
                </div>

                <div class="relative group">
                    <button
                        @click="handleLogout"
                        type="button"
                        :class="[
                            'flex items-center rounded-xl text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-400/10 transition w-full cursor-pointer',
                            isCollapsed ? 'justify-center p-2.5' : 'px-3 py-2 gap-2.5'
                        ]"
                    >
                        <svg class="w-4.5 h-4.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span v-show="!isCollapsed">Logout</span>
                    </button>

                    <div v-if="isCollapsed"
                        class="absolute left-full ml-3.5 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-red-900/95 text-white text-xs font-bold rounded-lg shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-150 z-50 border border-red-700 backdrop-blur-sm">
                        <span class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-red-900"></span>
                        Logout
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 flex flex-col overflow-hidden min-w-0 print:h-auto print:overflow-visible print:block">
            <!-- Mobile Top Header for Non-POS Pages -->
            <header
                v-if="page.component !== 'Pos/Index' && page.component !== 'AI/ChatAssistant'"
                class="print:hidden lg:hidden h-14 flex-shrink-0 flex items-center justify-between bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-3.5 transition-colors z-20 select-none"
            >
                <div class="flex items-center gap-2.5 min-w-0">
                    <button
                        @click="toggleMobile"
                        type="button"
                        class="p-2 -ml-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer flex-shrink-0"
                        title="Toggle Navigation Menu"
                    >
                        <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-theme flex items-center justify-center flex-shrink-0 shadow-xs">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5"/>
                            </svg>
                        </div>
                        <span class="font-bold text-theme text-xs sm:text-sm truncate">
                            {{ $page.props.system_config?.company_name || $page.props.settings?.business_name || 'SkyNet POS' }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <ThemeToggle />
                </div>
            </header>

            <slot :toggleMobile="toggleMobile" :isMobileOpen="isMobileOpen" />
        </main>

        <FlashMessages class="print:hidden" />
    </div>
</template>
