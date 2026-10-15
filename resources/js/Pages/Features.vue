<script setup>
import { ref, computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import Navbar from '@/Components/Storefront/Navbar.vue'
import Footer from '@/Components/Storefront/Footer.vue'

defineOptions({ layout: null })

const props = defineProps({
    default_branch: Object,
    contact_info: {
        type: Object,
        default: () => ({
            phone: '+234 803 207 2831',
            clean_phone: '2348032072831',
            email: 'support@skynetdigitalltd.com',
            whatsapp: '2348032072831',
            company_name: 'Skynet Digital POS',
            app_tagline: 'AI-Powered Cloud & Offline Point of Sale',
        }),
    },
    pricing: Object,
})

const page = usePage()
const systemConfig = computed(() => page.props.system_config || {})
const companyName  = computed(() => props.contact_info?.company_name || systemConfig.value.company_name || 'Skynet Digital POS')
const appTagline   = computed(() => props.contact_info?.app_tagline || systemConfig.value.app_tagline || 'AI-Powered Cloud & Offline Point of Sale')
const logoUrl      = computed(() => systemConfig.value.company_logo_url || null)

const defaultBranchSlug = computed(() => props.default_branch?.slug || 'felix-enterprise')
const posUrl = computed(() => route('pos.index', { branch: defaultBranchSlug.value }))

const whatsappUrl = computed(() => {
    const phone = props.contact_info?.clean_phone || '2348032072831'
    const msg = encodeURIComponent(`Hello ${companyName.value}, I am reviewing the Features page and would like to learn more about the POS platform.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const pricingEnabled = computed(() => !!(props.pricing?.enabled && props.pricing?.settings?.show_pricing_section))

// Active filter for feature pillars
const activeCategory = ref('all')

const categories = [
    { id: 'all', label: 'All Capabilities' },
    { id: 'pos', label: 'POS & Offline' },
    { id: 'branch', label: 'Multi-Branch & Warehouses' },
    { id: 'inventory', label: 'Inventory, Variants & IMEI' },
    { id: 'accounting', label: 'Double-Entry Accounting' },
    { id: 'ecommerce', label: 'Omnichannel E-Commerce' },
    { id: 'ai', label: 'AI Copilot Analytics' },
    { id: 'security', label: 'Staff & Security' },
    { id: 'enterprise', label: 'Enterprise & SLA' },
]

const featurePillars = [
    {
        id: 'core',
        category: 'pos',
        title: 'Core POS & Offline Checkout Engine',
        subtitle: 'Sub-0.4s fast lane checkout with 100% offline resilience and split payments',
        icon: '⚡',
        badge: 'Offline-First Engine',
        pricingTier: 'Included in all plans (Starter+)',
        highlights: [
            {
                name: '100% Offline Checkout with Dexie Sync',
                desc: 'Continue ringing up customers, taking payments, and issuing receipts even during total Internet loss. Transactions sync automatically to the cloud once reconnected.',
                tag: 'Offline Resilience',
            },
            {
                name: 'High-Speed Barcode & SKU Scanning',
                desc: 'Sub-0.4s instantaneous barcode recognition with automatic price resolution, unit conversion, and stock verification.',
                tag: 'Fast-Lane POS',
            },
            {
                name: 'Split & Multi-Method Payments',
                desc: 'Accept flexible payment combinations on a single receipt: Cash, Bank Transfer, POS Card terminal, and Customer Debt Ledger balance.',
                tag: 'Payment Flexibility',
            },
            {
                name: 'Receipt Printing & Digital Sales Reports',
                desc: 'Print to standard 58mm & 80mm thermal printers or generate digital electronic receipt summaries with real-time daily sales totals.',
                tag: 'Thermal & Digital',
            },
            {
                name: 'Debt & Customer Account Management',
                desc: 'Track trusted customers who buy on credit, manage repayment installments, issue statement records, and monitor aging balances.',
                tag: 'Credit Ledger',
            },
            {
                name: 'Custom Receipt Templates & Brand Styling',
                desc: 'Customize receipt headers with store logo, branch details, tax IDs, custom thank-you notes, and return policy disclosures.',
                tag: 'Branding',
            },
        ],
    },
    {
        id: 'branch',
        category: 'branch',
        title: 'Multi-Branch & Multi-Warehouse Supply Chain',
        subtitle: 'Centralized command center for managing stock, transfers, and expenses across locations',
        icon: '🏢',
        badge: 'Multi-Location Control',
        pricingTier: 'Bronze, Silver, Gold, Enterprise',
        highlights: [
            {
                name: 'Multi-Branch Inventory Lookup & Transfers',
                desc: 'Check live product stock at any other branch with a single click. Dispatch and receive tracked stock transfer batches between locations.',
                tag: 'Branch Sync',
            },
            {
                name: 'Centralized Purchase Orders & Supplier Dispatch',
                desc: 'Create, approve, and track purchase orders with suppliers. Receive partial or full shipments with automated stock replenishment.',
                tag: 'Purchase Orders',
            },
            {
                name: 'Enterprise Multi-Warehouse Distribution',
                desc: 'Manage centralized storage depots and primary warehouses with bulk dispatch to regional retail branch outlets.',
                tag: 'Warehouse Logistics',
            },
            {
                name: 'Cross-Branch Expense Tracking & P&L Reports',
                desc: 'Log branch operating expenses (petty cash, diesel, utility bills, staff allowances) and analyze net profit per individual store.',
                tag: 'Financial Visibility',
            },
            {
                name: 'Branch-Specific Settings & Cashier Registers',
                desc: 'Configure individual tax rules, currency symbols, offline quantity deduction policies, and receipt headers per store.',
                tag: 'Autonomous Terminals',
            },
            {
                name: 'Centralized Super Admin Portal',
                desc: 'Control all branches from a single unified dashboard with global user management, system-wide settings, and activity oversight.',
                tag: 'Enterprise Admin',
            },
        ],
    },
    {
        id: 'inventory',
        category: 'inventory',
        title: 'Inventory, Variant Matrices & Serial Tracking',
        subtitle: 'Eliminate stock shrinkage with unit conversions, IMEI tracking, and reorder intelligence',
        icon: '📦',
        badge: 'Zero Shrinkage',
        pricingTier: 'Starter, Bronze, Silver, Gold, Enterprise',
        highlights: [
            {
                name: 'Real-Time Stock Inventory & Low Stock Alerts',
                desc: 'Live stock counts updated automatically on every checkout. Proactive notifications alert managers before items run out.',
                tag: 'Real-Time Tracking',
            },
            {
                name: 'Product Variants & Packaging Matrices',
                desc: 'Sell products in multiple packaging tiers (cartons, packs, strips, pieces) or variant attributes (sizes, colors) drawing from one unified inventory.',
                tag: 'Multi-Pack Matrices',
            },
            {
                name: 'IMEI & Serial Number Tracking',
                desc: 'Record and track unique IMEIs/serial numbers for phones, gadgets, and electronics. Trace exact warranty records and customer sales receipts.',
                tag: 'Electronics & Gadgets',
            },
            {
                name: 'Automated Reorder Level Suggestions',
                desc: 'System calculates intelligent replenishment thresholds based on sales velocity, preventing stockouts and over-purchasing.',
                tag: 'Smart Reordering',
            },
            {
                name: 'Advanced Discount & Promotion Matrix',
                desc: 'Run promotional campaigns, apply percentage or flat discounts, enforce supervisor approval limits, and issue bulk wholesale pricing.',
                tag: 'Pricing Rules',
            },
            {
                name: 'Stock Adjustments & Loss Audits',
                desc: 'Record stock reconciliations, damaged goods, expirations, and write-offs with mandatory audit reason logging.',
                tag: 'Audit Trails',
            },
        ],
    },
    {
        id: 'ai',
        category: 'ai',
        title: 'Ask-Your-Business AI Copilot Engine',
        subtitle: 'Conversational business analytics in English, Nigerian Pidgin, and native languages',
        icon: '🤖',
        badge: 'Multilingual Intelligence',
        pricingTier: 'Available across all plans with monthly AI credits',
        highlights: [
            {
                name: 'Zero Fabrication Query Engine',
                desc: 'The AI copilot translates natural questions into verified database queries. It never invents numbers — every figure comes directly from real sales data.',
                tag: 'Verified Analytics',
            },
            {
                name: 'Nigerian Pidgin & Local Dialect Support',
                desc: 'Speak naturally: "Summarize today sales, cash in hand, and fast-moving items sharp-sharp" or query in Yoruba or Igbo with full semantic understanding.',
                tag: 'Local Language',
            },
            {
                name: 'Instant Inventory Valuation',
                desc: 'Ask: "How much is my total inventory worth?" The copilot computes live cost and retail values across all product categories and branches.',
                tag: 'Valuation Intelligence',
            },
            {
                name: 'Customer Debt & Receivables Analysis',
                desc: 'Instantly uncover total outstanding debt, who owes the most, payment aging, and contact details in seconds without manually scrolling spreadsheets.',
                tag: 'Debt Recovery',
            },
            {
                name: 'Operating Expense Breakdowns',
                desc: 'Query: "Show me total diesel and maintenance expenses for Lagos branch this month." Receive categorized breakdown charts instantly.',
                tag: 'Cost Control',
            },
            {
                name: 'Held Sales & Abandoned Checkout Recovery',
                desc: 'Ask: "How many sales are currently on hold?" See customer carts parked at registers and recover pending transactions.',
                tag: 'Lost Sales Recovery',
            },
        ],
    },
    {
        id: 'security',
        category: 'security',
        title: 'Staff Management, Security & Access Governance',
        subtitle: 'Role-based access control, shift balancing, and tamper-proof audit trails',
        icon: '🛡️',
        badge: 'Enterprise Security',
        pricingTier: 'Bronze, Silver, Gold, Enterprise',
        highlights: [
            {
                name: 'Staff Performance Tracking & Shift Reports',
                desc: 'Track sales volume, transaction counts, and register totals per staff member. Cashiers can only view their own shift drawer till.',
                tag: 'Staff Accountability',
            },
            {
                name: 'Granular Role-Based Access Control',
                desc: 'Enforce distinct permissions for Super Admins, Branch Managers, Cashiers, and Inventory Clerks. Restrict sensitive financial figures from registers.',
                tag: 'Role Permissions',
            },
            {
                name: 'Comprehensive Audit Logs & Tamper Prevention',
                desc: 'Every price edit, stock write-off, receipt void, and configuration change is logged with timestamp, user identity, and IP location.',
                tag: 'Audit Logging',
            },
            {
                name: 'Cash Drawer Kick & End-of-Day Balancing',
                desc: 'Cash drawer automatically triggers open upon approved payment. Conduct blind end-of-day register counts to prevent cashier skimming.',
                tag: 'Cash Protection',
            },
            {
                name: 'Quick Cashier Pin-Switching',
                desc: 'Fast PIN-based cashier login allows multiple clerks to share a single high-throughput terminal during busy rush hours.',
                tag: 'Fast Handoff',
            },
            {
                name: 'Cloud Data Encryption & Automated Backups',
                desc: 'All branch sales data is securely backed up to the cloud in real time with enterprise-grade encryption at rest and in transit.',
                tag: 'Data Safety',
            },
        ],
    },
    {
        id: 'enterprise',
        category: 'enterprise',
        title: 'Enterprise Architecture, Integrations & SLA Support',
        subtitle: 'Tailored infrastructure, ERP synchronization, and dedicated technical engineers',
        icon: '🚀',
        badge: 'Enterprise Conglomerates',
        pricingTier: 'Gold & Enterprise',
        highlights: [
            {
                name: 'Custom Database Deployment & Private Cloud',
                desc: 'Deploy on dedicated private cloud instances with isolated databases, high-availability replication, and custom domain hosting.',
                tag: 'Private Cloud',
            },
            {
                name: 'ERP & Accounting Software Integrations',
                desc: 'Seamlessly synchronize sales, payments, and inventory batches with SAP, QuickBooks, Microsoft Dynamics, or custom accounting systems.',
                tag: 'ERP Integration',
            },
            {
                name: 'Dedicated Technical Account Engineer',
                desc: 'Enterprise customers receive an assigned senior software engineer for direct architectural support, custom feature development, and uptime monitoring.',
                tag: 'Dedicated Engineer',
            },
            {
                name: 'Priority Onboarding & Data Migration',
                desc: 'Our engineering team migrates your existing product catalogs, customer ledgers, and historical sales from legacy POS platforms free of charge.',
                tag: 'White-Glove Migration',
            },
            {
                name: 'On-Site Staff Training & Branch Rollout',
                desc: 'Hands-on training sessions for your store cashiers, supervisors, and warehouse managers to ensure immediate team proficiency.',
                tag: 'Hands-On Training',
            },
            {
                name: 'Quarterly Business Reviews & SLA Uptime Guarantee',
                desc: 'Strategic capacity planning, infrastructure scaling consultations, and strict service-level agreements guaranteeing 99.9% platform availability.',
                tag: 'SLA Guarantee',
            },
        ],
    },
    {
        id: 'accounting',
        category: 'accounting',
        title: 'Automated Double-Entry Accounting & General Ledger',
        subtitle: 'Audit-ready financial books, real-time debits/credits, Chart of Accounts, and VAT tax compliance',
        icon: '📊',
        badge: 'Double-Entry Engine',
        pricingTier: 'Silver, Gold, Enterprise',
        highlights: [
            {
                name: 'Automated Real-Time Journal Posting',
                desc: 'Every retail transaction (sales, customer debts, till drops, returns, vendor purchases, and expenses) automatically creates balanced double-entry ledger lines with zero manual posting.',
                tag: 'Automated GL',
            },
            {
                name: 'Standard 17 System Accounts & Custom Chart of Accounts',
                desc: 'Pre-configured system accounts for Cash on Hand (1000), Bank (1010), POS Card (1020), Debtors (1100), Inventory (1200), VAT Payable (2100), Revenue (4000), and COGS (5000), plus custom sub-accounts.',
                tag: 'Chart of Accounts',
            },
            {
                name: '5 Real-Time Financial Statements',
                desc: 'Generate balanced Trial Balance, Balance Sheet, Profit & Loss (P&L), Cash Flow (Direct Method), and Accounts Receivable (AR) Aging reports with 1-click Excel/PDF exports.',
                tag: 'Financial Statements',
            },
            {
                name: 'Dedicated Tax & VAT Liability Tracking (Account 2100)',
                desc: 'Separate tax collected from sales revenue into Account 2100. Unlock instant periodic VAT reporting to simplify audit compliance and tax filings.',
                tag: 'Tax & VAT Compliance',
            },
            {
                name: 'Financial Period Controls & Hard Closing Locks',
                desc: 'Close monthly accounting periods to lock historical data against backdated tampering. Reopen periods with supervisor authorization and complete audit logging.',
                tag: 'Period Governance',
            },
            {
                name: 'Opening Balance Wizard & 1-Click Posting Retry',
                desc: 'Initialize newly activated branch books with balanced opening equity. Monitor unposted transactions with automatic self-healing and one-click posting retries.',
                tag: 'Audit & Resilience',
            },
        ],
    },
    {
        id: 'ecommerce',
        category: 'ecommerce',
        title: 'Omnichannel E-Commerce & Online Store Sync',
        subtitle: 'Unify physical branch inventory with your digital storefront via secure REST API & real-time webhooks',
        icon: '🛍️',
        badge: 'Omnichannel Engine',
        pricingTier: 'Bronze, Silver, Gold, Enterprise',
        highlights: [
            {
                name: 'Two-Way Sub-Second Stock Synchronization',
                desc: 'Instantly sync stock levels between physical register terminals and your digital storefront (WooCommerce or Custom Web Application) via secure REST API in <0.3s.',
                tag: 'Real-Time Sync',
            },
            {
                name: 'Zero-Oversell Online Cart Reservations',
                desc: 'When online customers start checkout, items enter an active reservation buffer. Physical cashier registers cannot sell reserved units, eliminating stock conflict errors.',
                tag: 'Cart Reservations',
            },
            {
                name: 'Centralized Catalog & Variant Matrix Publishing',
                desc: 'Maintain product names, descriptions, images, multi-unit prices (Piece/Pack/Carton), and variant matrices in one central POS hub that auto-populates your web store.',
                tag: 'Single-Source Catalog',
            },
            {
                name: 'Real-Time Webhook Activity & Audit Feed',
                desc: 'Full transparency into digital store communication with live activity logs, reservation status tracking, and automated failure retries.',
                tag: 'Webhook Audits',
            },
            {
                name: 'Storefront Connection Security & Token Rotation',
                desc: 'Manage store integrations from the SuperAdmin portal with scoped API tokens, instant token regeneration, and one-click store deactivation safety controls.',
                tag: 'Integration Security',
            },
            {
                name: 'Multi-Branch Inventory Allocation',
                desc: 'Configure which physical branch warehouse fulfills digital web orders or route orders dynamically based on proximity and live stock availability.',
                tag: 'Fulfillment Routing',
            },
        ],
    },
]

const filteredPillars = computed(() => {
    if (activeCategory.value === 'all') return featurePillars
    return featurePillars.filter(p => p.category === activeCategory.value)
})

// Plans from pricing data
const pricingPlans = computed(() => props.pricing?.plans || [])
const retailPlans = computed(() => (props.pricing?.plans || []).slice(0, 3))
const enterprisePlans = computed(() => (props.pricing?.plans || []).slice(3))
</script>

<template>
    <Head title="Platform Capabilities &amp; Features — Enterprise POS" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors selection:bg-violet-500 selection:text-white font-sans antialiased">
        <!-- Reusable Navigation -->
        <Navbar
            :company-name="companyName"
            :app-tagline="appTagline"
            :logo-url="logoUrl"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingEnabled"
        />

        <main class="pt-28 pb-24">
            <!-- ── 1. Hero ─────────────────────────────────────────────── -->
            <section class="relative px-4 sm:px-6 lg:px-8 py-16 text-center overflow-hidden">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[850px] h-[450px] rounded-full bg-gradient-to-tr from-violet-600/15 via-purple-600/10 to-indigo-600/15 blur-[140px]"></div>
                </div>

                <div class="relative z-10 max-w-4xl mx-auto space-y-6">
                    <div class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <Link :href="route('home')" class="hover:text-violet-600">Home</Link>
                        <span>/</span>
                        <span class="text-violet-600 dark:text-violet-400 font-bold">Platform Capabilities</span>
                    </div>

                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-violet-100 dark:bg-violet-950/80 border border-violet-300 dark:border-violet-700/60 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                        Comprehensive Retail Platform
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                        Every Feature Engineered for Speed, Accuracy &amp; Scale.
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 text-base sm:text-lg max-w-3xl mx-auto leading-relaxed">
                        Explore every capability included across our pricing tiers — from the sub-0.4s offline Dexie checkout engine and multi-warehouse logistics to conversational AI analytics and enterprise ERP integrations.
                    </p>

                    <!-- Quick Navigation CTA Strip -->
                    <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                        <a
                            href="#plan-matrix"
                            class="px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold text-xs transition shadow-md shadow-violet-600/20 flex items-center gap-2"
                        >
                            <span>📊 Compare Features by Plan</span>
                            <span>↓</span>
                        </a>
                        <Link
                            v-if="pricingEnabled"
                            :href="route('pricing')"
                            class="px-5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 hover:border-violet-500 font-bold text-xs transition shadow-sm"
                        >
                            <span>View Pricing &amp; Tier Limits →</span>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ── 2. Category Filter Tabs ─────────────────────────────── -->
            <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
                <div class="flex items-center justify-start sm:justify-center gap-2 overflow-x-auto pb-4 no-scrollbar">
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="activeCategory = cat.id"
                        :class="[
                            'px-4 py-2 rounded-2xl text-xs font-bold transition-all shrink-0 cursor-pointer',
                            activeCategory === cat.id
                                ? 'bg-violet-600 text-white shadow-md shadow-violet-600/30'
                                : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        {{ cat.label }}
                    </button>
                </div>
            </section>

            <!-- ── 3. Detailed Feature Pillars ─────────────────────────── -->
            <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16 mb-24">
                <div
                    v-for="pillar in filteredPillars"
                    :id="pillar.id"
                    :key="pillar.id"
                    class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-8 hover:border-violet-500/40 transition-colors"
                >
                    <!-- Pillar Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl p-3 rounded-2xl bg-violet-50 dark:bg-violet-950/60 border border-violet-100 dark:border-violet-800/60">{{ pillar.icon }}</span>
                            <div>
                                <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ pillar.title }}</h2>
                                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ pillar.subtitle }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col sm:items-end gap-1">
                            <span class="inline-flex items-center self-start sm:self-end px-3 py-1 rounded-full bg-violet-100 dark:bg-violet-950/70 border border-violet-300 dark:border-violet-700/60 text-violet-800 dark:text-violet-300 text-xs font-bold">
                                {{ pillar.badge }}
                            </span>
                            <span class="text-[11px] font-semibold text-slate-400">{{ pillar.pricingTier }}</span>
                        </div>
                    </div>

                    <!-- Highlight Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div
                            v-for="(item, iIdx) in pillar.highlights"
                            :key="iIdx"
                            class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200/80 dark:border-slate-800/80 flex flex-col justify-between space-y-3 hover:scale-[1.01] hover:border-violet-500/40 transition-all group"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-md bg-violet-100 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400">
                                        {{ item.tag }}
                                    </span>
                                    <span class="text-emerald-500 font-bold text-sm">✓</span>
                                </div>
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                    {{ item.name }}
                                </h3>
                                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                    {{ item.desc }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── 4. Plan-by-Plan Feature Matrix Breakdown ─────────────── -->
            <section id="plan-matrix" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-24">
                <div class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-8">
                    <div class="text-center space-y-3 max-w-3xl mx-auto">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-100 dark:bg-violet-950/80 border border-violet-300 dark:border-violet-700/60 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                            Pricing Tier Alignment
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">
                            Features Included by Pricing Plan
                        </h2>
                        <p class="text-slate-600 dark:text-slate-400 text-xs sm:text-sm">
                            Every single feature on this page maps directly to our pricing plans so you know exactly which tier powers your business.
                        </p>
                    </div>

                    <!-- Plan Matrix: Spacious 3-Col Standard + 2-Col Enterprise Layout -->
                    <div v-if="pricingPlans && pricingPlans.length > 0" class="space-y-10">
                        <!-- Standard Retail Tiers -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="plan in retailPlans"
                                :key="plan.id"
                                :class="[
                                    'p-6 rounded-2xl border flex flex-col justify-between space-y-4 transition-all',
                                    plan.is_highlighted
                                        ? 'bg-gradient-to-b from-amber-500/[0.08] to-white dark:to-slate-900 border-2 border-amber-400 dark:border-amber-500 shadow-xl shadow-amber-500/15 ring-2 ring-amber-400/20'
                                        : 'bg-slate-50/70 dark:bg-slate-950/60 border-slate-200 dark:border-slate-800'
                                ]"
                            >
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-extrabold text-base text-slate-900 dark:text-white">{{ plan.name }}</h3>
                                        <span v-if="plan.is_highlighted" class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-xs">
                                            ⭐ Most Popular &amp; Comprehensive
                                        </span>
                                    </div>
                                    <div class="text-sm font-mono font-bold text-violet-600 dark:text-violet-400">
                                        {{ plan.first_year_price }}
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-snug">
                                        {{ plan.tagline }}
                                    </p>
                                    
                                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                            Key Capabilities Included:
                                        </div>
                                        <ul class="space-y-1.5">
                                            <li
                                                v-for="(f, fIdx) in plan.features"
                                                :key="fIdx"
                                                class="text-xs text-slate-700 dark:text-slate-300 flex items-start gap-2 leading-snug"
                                            >
                                                <span :class="['font-bold shrink-0', plan.is_highlighted ? 'text-amber-500' : 'text-emerald-500']">✓</span>
                                                <span>{{ f.feature_text }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-slate-200/80 dark:border-slate-800/80">
                                    <Link
                                        v-if="pricingEnabled"
                                        :href="route('pricing')"
                                        :class="[
                                            'w-full block text-center py-2.5 px-3 rounded-xl text-xs font-bold transition shadow-xs',
                                            plan.is_highlighted
                                                ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-black hover:from-amber-400 hover:to-orange-400'
                                                : 'bg-white dark:bg-slate-900 hover:bg-violet-600 hover:text-white border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200'
                                        ]"
                                    >
                                        Choose {{ plan.name }} →
                                    </Link>
                                    <a
                                        v-else
                                        :href="whatsappUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="w-full block text-center py-2.5 px-3 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500 transition"
                                    >
                                        Inquire on WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Enterprise & High Volume Tiers -->
                        <div v-if="enterprisePlans.length" class="pt-6 border-t border-slate-200 dark:border-slate-800 space-y-4">
                            <div class="text-center">
                                <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    High-Volume Chains &amp; Enterprise Deployments
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto">
                                <div
                                    v-for="plan in enterprisePlans"
                                    :key="plan.id"
                                    class="p-6 rounded-2xl border bg-slate-50/70 dark:bg-slate-950/60 border-slate-200 dark:border-slate-800 flex flex-col justify-between space-y-4 transition-all"
                                >
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <h3 class="font-extrabold text-base text-slate-900 dark:text-white">{{ plan.name }}</h3>
                                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                Enterprise
                                            </span>
                                        </div>
                                        <div class="text-sm font-mono font-bold text-violet-600 dark:text-violet-400">
                                            {{ plan.first_year_price }}
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-snug">
                                            {{ plan.tagline }}
                                        </p>
                                        
                                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                                Key Capabilities Included:
                                            </div>
                                            <ul class="space-y-1.5">
                                                <li
                                                    v-for="(f, fIdx) in plan.features"
                                                    :key="fIdx"
                                                    class="text-xs text-slate-700 dark:text-slate-300 flex items-start gap-2 leading-snug"
                                                >
                                                    <span class="text-emerald-500 font-bold shrink-0">✓</span>
                                                    <span>{{ f.feature_text }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="pt-3 border-t border-slate-200/80 dark:border-slate-800/80">
                                        <Link
                                            v-if="pricingEnabled"
                                            :href="route('pricing')"
                                            class="w-full block text-center py-2.5 px-3 rounded-xl bg-white dark:bg-slate-900 hover:bg-violet-600 hover:text-white border border-slate-200 dark:border-slate-800 text-xs font-bold transition text-slate-800 dark:text-slate-200 shadow-xs"
                                        >
                                            Choose {{ plan.name }} →
                                        </Link>
                                        <a
                                            v-else
                                            :href="whatsappUrl"
                                            target="_blank"
                                            rel="noopener"
                                            class="w-full block text-center py-2.5 px-3 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500 transition"
                                        >
                                            Inquire on WhatsApp
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── 5. Bottom Conversion CTA ─────────────────────────────── -->
            <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="p-8 sm:p-14 rounded-3xl bg-gradient-to-r from-violet-900 via-purple-900 to-indigo-900 border border-violet-500/40 text-white text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-8 shadow-2xl">
                    <div class="space-y-3">
                        <h3 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                            Ready to empower your retail store?
                        </h3>
                        <p class="text-xs sm:text-sm text-violet-200 max-w-xl leading-relaxed">
                            Experience the checkout register live or connect with our engineering team on WhatsApp for free product catalog migration and customized multi-store rollouts.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="posUrl"
                            class="px-7 py-4 rounded-2xl bg-white text-violet-950 font-black text-sm hover:bg-slate-100 transition shadow-xl hover:scale-105 active:scale-95"
                        >
                            Launch POS Terminal →
                        </Link>
                        <a
                            :href="whatsappUrl"
                            target="_blank"
                            rel="noopener"
                            class="px-7 py-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-sm transition shadow-xl hover:scale-105 active:scale-95 flex items-center gap-2 border border-emerald-400/40"
                        >
                            <span>💬 Talk to Sales on WhatsApp</span>
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <Footer
            :company-name="companyName"
            :support-phone="contact_info.phone"
            :support-email="contact_info.email"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingEnabled"
        />
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
