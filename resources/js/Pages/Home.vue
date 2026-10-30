<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, reactive } from 'vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import Navbar from '@/Components/Storefront/Navbar.vue'
import Footer from '@/Components/Storefront/Footer.vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

marked.use({
    gfm: true,
    breaks: true,
})

function renderMarkdown(content) {
    if (!content || typeof content !== 'string') return ''
    try {
        const rawHtml = marked.parse(content)
        return DOMPurify.sanitize(rawHtml, {
            ADD_ATTR: ['target', 'rel', 'class'],
            ADD_TAGS: ['table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'span', 'code', 'pre', 'blockquote', 'hr', 'a', 'p', 'strong', 'em', 'b', 'i', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'br'],
        })
    } catch (e) {
        return content
    }
}

defineOptions({ layout: null })

const props = defineProps({
    default_branch: Object,
    stats: {
        type: Object,
        default: () => ({
            items_count: 0,
            sales_count: 0,
        }),
    },
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
    pricing: {
        type: Object,
        default: null,
    },
})

const page = usePage()

const systemConfig = computed(() => page.props.system_config || {})
const companyName  = computed(() => props.contact_info?.company_name || systemConfig.value.company_name || 'Skynet Digital POS')
const appTagline   = computed(() => props.contact_info?.app_tagline || systemConfig.value.app_tagline || 'AI-Powered Cloud & Offline Point of Sale')
const logoUrl      = computed(() => systemConfig.value.company_logo_url || null)

const defaultBranchSlug = computed(() => {
    return props.default_branch?.slug || 'felix-enterprise'
})

const posUrl = computed(() => {
    return route('pos.index', { branch: defaultBranchSlug.value })
})

const currentYear = new Date().getFullYear()

const pricingSectionEnabled = computed(() => {
    return Boolean(props.pricing?.enabled && props.pricing?.settings?.show_pricing_section)
})

onMounted(() => {
    // Smooth Scroll Reveal Intersection Observer
    if (typeof window !== 'undefined' && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed')
                    obs.unobserve(entry.target)
                }
            })
        }, {
            root: null,
            rootMargin: '0px 0px -40px 0px',
            threshold: 0.08,
        })

        document.querySelectorAll('.reveal-up, .reveal-left, .reveal-right, .reveal-zoom, .reveal-fade').forEach(el => {
            observer.observe(el)
        })
    }
})

// ── WhatsApp Inquiry Link Generator ──────────────────────────────────────────
const whatsappUrl = computed(() => {
    const phone = props.contact_info?.clean_phone || '2348032072831'
    const msg = encodeURIComponent(`Hello ${companyName.value}, I am interested in acquiring your AI-Powered POS & Multi-Branch Inventory Management System for my business. Please share pricing and setup details.`)
    return `https://wa.me/${phone}?text=${msg}`
})

function planWhatsappUrl(planName) {
    const phone = props.contact_info?.clean_phone || '2348032072831'
    const msg = encodeURIComponent(`Hello ${companyName.value}, I am interested in getting started with the ${planName} Plan for my store. Please share setup and rollout details.`)
    return `https://wa.me/${phone}?text=${msg}`
}

const retailPlans = computed(() => (props.pricing?.plans || []).slice(0, 3))
const enterprisePlans = computed(() => (props.pricing?.plans || []).slice(3))


// ── Interactive AI Assistant Simulator State ──────────────────────────────────
const activeAiPromptIndex = ref(0)
const aiTyping = ref(false)
const customAiQuery = ref('')

const aiPrompts = [
    {
        query: 'What were our top 3 best-selling products today and their profit margins?',
        lang: 'Executive Intelligence',
        icon: '📈',
        response: 'Today’s top 3 performers across all terminal registers:\n1. **Dano Full Cream Milk 400g** — 42 units sold (₦134,400 revenue | ₦38,200 profit | 28.4% margin)\n2. **Samsung Galaxy S24 Ultra** — 2 devices sold (₦2,900,000 revenue | ₦420,000 profit | 14.5% margin) [IMEIs verified]\n3. **Paracetamol Extra 500mg** — 24 packs sold (₦39,600 revenue | ₦14,400 profit | 36.3% margin)\n\n💡 *Intelligence Recommendation: Dano Milk inventory is depleting 22% faster than last Saturday. Automated reorder purchase order #PO-882 has been drafted for your 1-click approval.*',
        metrics: { totalRevenue: '₦3,104,400', bestMarginCategory: 'Pharmacy (36.3%)', confidence: '99.4%' },
    },
    {
        query: 'Summarize today\'s sales, cash in hand, and low stock in Nigerian Pidgin',
        lang: 'Nigerian Pidgin',
        icon: '🇳🇬',
        response: 'Oga boss, market make sense well-well today! 🔥\n• We don close **64 customer transactions** with total money of **₦4,850,200**.\n• Cash wey cashier hold na **₦1,200,000**, Bank Transfer na **₦3,650,200**, Zero debt record today!\n• **Stock Alert**: Paracetamol pack don remain only **12 packs** for shelf. Make una approve Purchase Order #PO-882 make supplier bring new carton sharp-sharp!',
        metrics: { totalRevenue: '₦4,850,200', completedOrders: '64 Transactions', confidence: '98.8%' },
    },
    {
        query: 'Which items are approaching their reorder threshold across all branches?',
        lang: 'Predictive Stockout',
        icon: '📦',
        response: 'Found **3 critical items** below safe buffer threshold across your network:\n• **Paracetamol Extra 500mg**: 12 packs remaining (Reorder point: 25 packs)\n• **Heineken Beer 330ml Can**: 8 cans remaining (Reorder point: 30 cans)\n• **Augmentin 625mg Tabs**: 4 packs remaining (Reorder point: 15 packs)\n\n📦 *Automated Action Ready: Inter-branch transfer from Central Warehouse or vendor PO can be dispatched in 1 click.*',
        metrics: { alertCount: '3 Items Critical', autoPoStatus: 'Drafted (#PO-882)', confidence: '100%' },
    },
    {
        query: 'Calculate estimated gross profit and departmental margins for this week',
        lang: 'Financial Analytics',
        icon: '📊',
        response: 'Weekly Financial Performance Breakdown:\n• **Gross Revenue**: ₦18,450,000\n• **Cost of Goods Sold (COGS)**: ₦13,100,000\n• **Gross Profit**: **₦5,350,000**\n• **Overall Profit Margin**: **28.99%** (↑ 3.2% vs prior week)\n\n🚀 *Phones & Gadgets generated 64% of gross margin volume, while Pharmacy delivered the highest unit ROI at 38.5%.*',
        metrics: { grossProfit: '₦5,350,000', marginPct: '28.99%', confidence: '99.1%' },
    },
    {
        query: 'Se akopọ awọn tita oni ati owo ti a wọle fun mi ni ede Yoruba',
        lang: 'Yoruba Dialect',
        icon: '🗣️',
        response: 'Ẹ ku iṣẹ loni oluwa mi! Iṣowo lọ daadaa loni:\n• Lapapọ owo tita ti a wọle loni jẹ **₦4,850,200** lati awọn alabara **64**.\n• Owo sisanwọle nipasẹ banki jẹ **₦3,650,200**, owo gidi (Cash) jẹ **₦1,200,000**.\n• Ọja Dano Milk ti fẹrẹ tan ni ile-itaja, jọwọ paṣẹ titun ki o ma ba tán patapata.',
        metrics: { totalRevenue: '₦4,850,200', transactions: '64 Onibara', confidence: '98.2%' },
    },
]

function selectAiPrompt(index) {
    if (activeAiPromptIndex.value === index) return
    aiTyping.value = true
    activeAiPromptIndex.value = index
    setTimeout(() => {
        aiTyping.value = false
    }, 250)
}

function submitCustomAiQuery() {
    if (!customAiQuery.value.trim()) return
    aiTyping.value = true
    setTimeout(() => {
        aiTyping.value = false
        // Switch to the closest prompt or display custom response
        activeAiPromptIndex.value = 0
        customAiQuery.value = ''
    }, 450)
}


// ── Feature Matrix Highlights ────────────────────────────────────────────────
const coreFeatures = [
    {
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        gradient: 'from-amber-500 to-orange-600',
        badge: 'Lightning Speed',
        title: 'Ultra-Fast Checkout (<0.4s)',
        desc: 'Scan barcodes, search SKU, select variations (Pieces/Packs/Cartons), apply discounts, and process cash, transfer, card, or split payments instantly.',
    },
    {
        icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        gradient: 'from-violet-500 to-purple-600',
        badge: 'Autonomous AI',
        title: 'Conversational Store AI',
        desc: 'Ask your POS anything in plain English, Pidgin, Yoruba, Hausa, or Igbo. Get immediate sales predictions, profit breakdowns, and restock alerts.',
    },
    {
        icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        gradient: 'from-emerald-500 to-teal-600',
        badge: 'Zero Downtime',
        title: 'True Offline-First',
        desc: 'Internet cuts off? Cashiers keep ringing up sales and printing receipts uninterrupted. Everything auto-syncs with smart conflict resolution once reconnected.',
    },
    {
        icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7l8 4',
        gradient: 'from-blue-500 to-indigo-600',
        badge: 'Multi-Unit',
        title: 'Multi-Level Unit Pricing',
        desc: 'Sell products in Single Units, Packs, or Cartons with automatic stock deduction, distinct wholesale pricing, and instant barcode recognition per unit level.',
    },
    {
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        gradient: 'from-rose-500 to-pink-600',
        badge: 'Credit Control',
        title: 'Customer Debt Ledger CRM',
        desc: 'Maintain customer history, enforce credit limits, track unpaid customer balances, accept installment debt payments, and print detailed debt statements.',
    },
    {
        icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
        gradient: 'from-cyan-500 to-blue-600',
        badge: 'Hardware Ready',
        title: 'Thermal 80mm/58mm Printing',
        desc: 'Direct printing to 80mm & 58mm thermal printers (USB/Network/Bluetooth), automatic receipt copies, paper cutter trigger, and dynamic barcode label generator.',
    },
    {
        icon: 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
        gradient: 'from-yellow-500 to-amber-600',
        badge: 'Warranty Tracking',
        title: 'IMEI & Device Serial Engine',
        desc: 'Track individual device IMEIs and serial numbers from purchase orders through inventory to checkout receipt for airtight warranty and fraud protection.',
    },
    {
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        gradient: 'from-fuchsia-500 to-purple-600',
        badge: 'Analytics',
        title: 'Deep Sales & Profit Analytics',
        desc: 'Track daily profit margins, cashier end-of-day register balancing, returned inventory logs, and comprehensive financial reports with instant exports.',
    },
    {
        icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        gradient: 'from-emerald-600 to-teal-700',
        badge: 'Double-Entry',
        title: 'Automated Accounting & GL',
        desc: 'Every sale, till drop, customer debt, and expense auto-posts balanced debits and credits. Real-time Trial Balance, Balance Sheet, P&L, and VAT liability reporting.',
    },
    {
        icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        gradient: 'from-indigo-600 to-blue-700',
        badge: 'Omnichannel Sync',
        title: 'E-Commerce & Online Store Sync',
        desc: 'Connect WooCommerce and online web stores via REST API. 2-way real-time stock sync and instant checkout reservations eliminate overselling across channels.',
    },
]

// ── Target Industries ────────────────────────────────────────────────────────
const targetIndustries = [
    { name: 'Supermarkets & Groceries', icon: '🛒', desc: 'High-speed barcode scanning, multi-cashier registers, held sales queues, and scale weight integration.' },
    { name: 'Pharmacies & Chemists', icon: '💊', desc: 'Expiry date tracking, dosage packs/strips conversions, and prescribed customer history.' },
    { name: 'Phones & Electronics', icon: '📱', desc: 'IMEI serial number registration, warranty receipt generation, and accessory inventories.' },
    { name: 'Laundromats & Dry Cleaners', icon: '🧺', desc: 'Itemized garment intake, piece/kg billing, visual workflow board (intake to ready), garment tag printing, and collection tracking. Included in all plans, even Starter!' },
    { name: 'Wholesale & Distributors', icon: '📦', desc: 'Carton/Pack tiered pricing, customer credit ledgers, and multi-warehouse transfers.' },
    { name: 'Boutiques & Fashion', icon: '👗', desc: 'Color/size product matrices, custom barcode generation, and customer VIP discounts.' },
    { name: 'Bars, Lounges & Cafes', icon: '☕', desc: 'Table held sales, split bill settlements, and end-of-day register reconciliation.' },
]

// ── Hardware Ecosystem ───────────────────────────────────────────────────────
const hardwareSupported = [
    { name: '80mm & 58mm Thermal Printers', icon: '🖨️', detail: 'USB, Network IP, Bluetooth, ESC/POS native commands' },
    { name: '1D & 2D Barcode Scanners', icon: '⚡', detail: 'Omnidirectional desktop & handheld wireless scanners' },
    { name: 'Electronic Cash Drawers', icon: '💵', detail: 'RJ11 auto-kick upon payment receipt print' },
    { name: 'Touchscreen POS & Tablets', icon: '🖥️', detail: 'Desktop auto-fullscreen kiosk mode & iPad/tablet responsive' },
]

// ── Testimonials ─────────────────────────────────────────────────────────────
const testimonials = [
    {
        name: 'Alhaji Musa Ibrahim',
        role: 'Managing Director, MegaMart Supermarkets (3 Branches)',
        quote: 'The offline capability saved us during a major fiber outage. Our cashiers did not drop a single customer transaction. Plus, asking the AI for sales in Pidgin makes my morning reviews effortless!',
        avatarBg: 'bg-emerald-600',
    },
    {
        name: 'Dr. Chioma Okonkwo',
        role: 'Lead Pharmacist, MedCare Pharmacy Chain',
        quote: 'Being able to sell by single strip or full pack with automated stock deduction solved our inventory discrepancy once and for all. Customer debt tracking has improved our cash flow tremendously.',
        avatarBg: 'bg-violet-600',
    },
    {
        name: 'Tunde Adebayo',
        role: 'CEO, GadgetHub Electronics Ltd',
        quote: 'The IMEI serial tracking is top tier. Every phone sold has its IMEI printed right on the thermal receipt for warranty verification. No more warranty fraud!',
        avatarBg: 'bg-amber-600',
    },
]

// ── FAQ Items ────────────────────────────────────────────────────────────────
const faqs = [
    {
        q: 'Can the POS work without an active internet connection?',
        a: 'Yes, 100%! Our system utilizes an advanced offline-first architecture (Dexie IndexedDB). Your cashiers can continue scanning, ringing up sales, applying discounts, and printing receipts even when the network is completely severed. Everything synchronizes automatically with smart timestamped conflict resolution once the connection is restored.',
    },
    {
        q: 'How does the AI Assistant help my business make more profit?',
        a: 'The built-in Skynet AI acts as an executive store manager. You can ask natural-language business questions like "What were my top items today?", "Which items should I reorder?", or even request summaries in Nigerian Pidgin or native languages. It analyzes real-time database transactions instantly with zero manual spreadsheet work.',
    },
    {
        q: 'Can I manage multiple branch stores from one login?',
        a: 'Absolutely. The Super Admin command center allows you to monitor all branch locations in real time, deploy product catalogs in bulk, perform inter-branch warehouse stock transfers, configure branch-specific settings (like auto-fullscreen on cashier login), and compare individual branch performance without extra per-store software fees.',
    },
    {
        q: 'What receipt printers and barcode scanners are supported?',
        a: 'The system supports all standard 80mm and 58mm thermal receipt printers (USB, Network IP, Bluetooth, ESC/POS) as well as all 1D/2D handheld and omnidirectional USB barcode scanners right out of the box with zero drivers needed.',
    },
    {
        q: 'How does the Product Variants & Multi-Tier pricing system work?',
        a: 'When creating products, you can configure both attribute variations (such as Size, Color, or Flavor) and multi-level packaging tiers (Single Unit, Pack, Master Carton). Each variant or packaging tier has its own distinct barcode, retail price, wholesale price, and reorder point. When a cashier scans or rings up any variant, the system automatically deducts the exact base units from unified inventory in real time.',
    },
    {
        q: 'Can you assist in migrating my data from an existing POS system?',
        a: 'Yes, absolutely! We provide complimentary, end-to-end data migration assistance. Whether your inventory and customer data are currently in Excel/CSV spreadsheets, QuickBooks, Loyverse, Odoo, Vend, or any custom POS, our technical team will format, verify barcodes, and import your complete database so your store transitions smoothly with zero operational downtime.',
    },
    {
        q: 'Is the Laundry Management Module included in all subscription plans?',
        a: 'Yes, absolutely! The complete Laundry & Dry Cleaning Management suite (including garment intake, piece/kg pricing, status workflow boards, thermal garment tags, and customer collection tracking) is fully included across all subscription tiers—even the Starter plan—with zero add-on license fees. Branches can enable laundry operations at any time alongside standard retail sales.',
    },
    {
        q: 'Does the system include full double-entry accounting and financial reports?',
        a: 'Yes! Starting on our Silver plan, every retail operation auto-posts balanced debits and credits into an integrated General Ledger. Sales, cash drops, customer debt settlements, returns, and expense receipts instantly update your 17 standard system accounts. You gain real-time access to Trial Balance, Profit & Loss (P&L), Balance Sheet, Cash Flow, and dedicated Account 2100 VAT Payable reports with zero bookkeeping delays.',
    },
    {
        q: 'Can I connect my e-commerce website or WooCommerce store to the POS?',
        a: 'Yes! With our Omnichannel E-Commerce Integration (available on Bronze and above), your physical retail branches and online store operate from one unified inventory. When customers checkout online, stock is instantly reserved in real time to prevent overselling. Product prices, catalog descriptions, and stock quantities sync two-way via secure REST API and webhooks.',
    },
    {
        q: 'How do I get started with Skynet Digital POS?',
        a: 'Getting started is immediate. You can launch your branch terminal directly, or connect with our support team on WhatsApp to configure your custom domain, import existing catalog data via Excel/CSV, and set up your staff accounts.',
    },
]

const activeFaqIndex = ref(0)
function toggleFaq(index) {
    activeFaqIndex.value = activeFaqIndex.value === index ? null : index
}
</script>

<template>
    <Head :title="companyName + ' — AI-Powered Cloud & Offline Point of Sale Platform'" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 selection:bg-violet-600 selection:text-white font-sans antialiased overflow-x-hidden transition-colors duration-300">

        <!-- ══ 1. TOP NAVIGATION ═════════════════════════════════════════ -->
        <Navbar
            :company-name="companyName"
            :app-tagline="appTagline"
            :logo-url="logoUrl"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingSectionEnabled"
        />

        <!-- ══ 2. HERO SECTION ═════════════════════════════════════════════ -->
        <section class="relative min-h-[90vh] flex flex-col items-center justify-center text-center px-4 sm:px-6 pt-32 pb-20 overflow-hidden">
            <!-- Background Glow & Animated Mesh Orbs -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[900px] h-[600px] rounded-full bg-gradient-to-tr from-violet-600/20 via-purple-600/15 to-indigo-600/20 dark:from-violet-600/25 dark:via-purple-600/20 dark:to-indigo-600/25 blur-[140px] animate-float-orb"></div>
                <div class="absolute -bottom-10 left-1/4 w-[450px] h-[450px] rounded-full bg-emerald-500/10 dark:bg-emerald-500/15 blur-[120px] animate-float-orb-delayed"></div>
                <div class="absolute top-20 right-10 w-[400px] h-[400px] rounded-full bg-fuchsia-500/10 dark:bg-fuchsia-500/15 blur-[120px] animate-float-orb"></div>
                <!-- Subtle Grid Texture -->
                <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.04]" style="background-image: linear-gradient(rgba(139,92,246,1) 1px, transparent 1px), linear-gradient(90deg, rgba(139,92,246,1) 1px, transparent 1px); background-size: 56px 56px;"></div>
            </div>

            <div class="relative z-10 max-w-5xl mx-auto space-y-8">
                <!-- Status Badge -->
                <div class="animate-hero-badge inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-violet-100 dark:bg-gradient-to-r dark:from-violet-950/80 dark:to-slate-900/80 border border-violet-300 dark:border-violet-500/30 text-violet-800 dark:text-violet-300 text-xs font-bold tracking-wide shadow-md dark:shadow-xl backdrop-blur-md hover:scale-105 hover:border-violet-400 transition-all duration-300 cursor-default">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>AI-Powered Point of Sale · 100% Offline-First (Dexie) · Real-Time Cloud Sync</span>
                </div>

                <!-- Main Punchy Headline -->
                <h1 class="animate-hero-title text-4xl sm:text-6xl lg:text-7xl font-black tracking-tight text-slate-900 dark:text-white leading-[1.08]">
                    The Smarter, Faster &amp; <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-600 via-fuchsia-600 to-amber-500 dark:from-violet-400 dark:via-fuchsia-400 dark:to-amber-300 animate-shimmer">
                        AI-Powered
                    </span>
                    Point of Sale Platform.
                </h1>

                <!-- Persuasive Subtitle -->
                <p class="animate-hero-subtitle text-slate-600 dark:text-slate-300 text-base sm:text-lg lg:text-xl max-w-3xl mx-auto leading-relaxed font-normal">
                    Engineered for high-volume retail stores, supermarkets, pharmacies, electronics shops, laundromats &amp; dry cleaners, and wholesale merchants. Ring up checkouts in under 0.4s offline or online, track garment workflows &amp; IMEIs, eliminate stock shrinkage, and query your business performance in natural English, Pidgin, or native dialects.
                </p>

                <!-- Primary Action Buttons -->
                <div class="animate-hero-cta flex flex-wrap items-center justify-center gap-4 pt-3">
                    <Link
                        :href="posUrl"
                        class="flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-extrabold text-base rounded-2xl transition-all duration-300 shadow-xl shadow-violet-600/30 dark:shadow-2xl dark:shadow-violet-900/60 hover:scale-105 hover:-translate-y-0.5 active:scale-95 cursor-pointer"
                    >
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>Open POS Terminal</span>
                        <span class="text-violet-200 text-xs font-semibold bg-white/20 px-2 py-0.5 rounded-lg">Instant</span>
                    </Link>

                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-2.5 px-6 py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-base rounded-2xl transition-all duration-300 shadow-xl shadow-emerald-700/20 dark:shadow-emerald-950/50 hover:scale-105 hover:-translate-y-0.5 active:scale-95 border border-emerald-400/40 cursor-pointer"
                    >
                        <span class="text-xl">💬</span>
                        <span>Chat on WhatsApp</span>
                    </a>
                </div>

                <!-- Free POS Migration Assistance Banner -->
                <div class="animate-hero-migration inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl px-5 py-2.5 shadow-xs backdrop-blur-md hover:scale-[1.02] hover:-translate-y-0.5 hover:border-emerald-500/50 transition-all duration-300 cursor-default">
                    <span class="text-emerald-500 font-bold text-sm">🔄</span>
                    <span>Switching from an existing POS? <strong>We easily assist to migrate all your products &amp; customer records with zero downtime.</strong></span>
                </div>

                <!-- Trust Badges Strip -->
                <div class="animate-hero-badges pt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-4xl mx-auto text-xs font-semibold text-slate-600 dark:text-slate-400">
                    <div class="group flex items-center justify-center gap-2 p-2.5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-xs hover:border-emerald-400 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <span class="text-emerald-500 text-base group-hover:scale-125 transition-transform duration-300">⚡</span>
                        <span>100% Offline-First</span>
                    </div>
                    <div class="group flex items-center justify-center gap-2 p-2.5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-xs hover:border-violet-400 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <span class="text-violet-500 text-base group-hover:scale-125 transition-transform duration-300">🤖</span>
                        <span>AI Assistant Built-in</span>
                    </div>
                    <div class="group flex items-center justify-center gap-2 p-2.5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-xs hover:border-cyan-400 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <span class="text-cyan-500 text-base group-hover:scale-125 transition-transform duration-300">🖨️</span>
                        <span>Thermal ESC/POS Ready</span>
                    </div>
                    <div class="group flex items-center justify-center gap-2 p-2.5 rounded-xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 shadow-xs hover:border-amber-400 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <span class="text-amber-500 text-base group-hover:scale-125 transition-transform duration-300">📦</span>
                        <span>Multi-Unit &amp; IMEI Engine</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 3. HOW IT WORKS: CHECKOUT EXPLAINED ══════════════════════════ -->
        <section id="how-it-works" class="py-20 px-4 sm:px-6 lg:px-8 border-y border-slate-200 dark:border-slate-800 bg-slate-100/70 dark:bg-slate-900/40 relative overflow-hidden">
            <div class="max-w-7xl mx-auto space-y-14">
                <!-- Section Title -->
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-violet-100 dark:bg-violet-500/10 border border-violet-300 dark:border-violet-500/30 text-violet-700 dark:text-violet-400 text-xs font-extrabold uppercase tracking-wider">
                        ⚡ Built for Real Stores
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Fast, Reliable Checkout — Every Time
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base max-w-2xl mx-auto">
                        Engineered for high-volume Nigerian retail — checkout in under 0.4 seconds, fully offline-capable, with instant 80mm thermal receipt printing at every step.
                    </p>
                </div>

                <!-- 4-Step Checkout Process Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">

                    <!-- Step 1: Scan or Search -->
                    <div class="reveal-up delay-100 group relative p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-lg hover:border-violet-500/80 hover:shadow-2xl hover:shadow-violet-600/10 dark:hover:shadow-violet-950/40 hover:-translate-y-2.5 hover:scale-[1.025] transition-all duration-300 space-y-4 cursor-pointer">
                        <div class="absolute -top-3.5 left-6 bg-gradient-to-r from-violet-600 to-indigo-600 text-white text-[10px] font-black px-3 py-0.5 rounded-full uppercase tracking-widest shadow">Step 1</div>
                        <div class="w-12 h-12 rounded-2xl bg-violet-100 dark:bg-violet-950 flex items-center justify-center text-2xl group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">
                            🔍
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white mb-1 group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">Scan or Search</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Instantly locate any product by barcode scan or SKU keyword search. Our engine resolves the item in under <strong class="text-slate-700 dark:text-slate-300">0.4 seconds</strong> — no lag, no lookup delay, even offline.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-semibold text-violet-600 dark:text-violet-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                            Works fully offline via Dexie
                        </div>
                    </div>

                    <!-- Step 2: Select the Unit -->
                    <div class="reveal-up delay-200 group relative p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-lg hover:border-blue-500/80 hover:shadow-2xl hover:shadow-blue-600/10 dark:hover:shadow-blue-950/40 hover:-translate-y-2.5 hover:scale-[1.025] transition-all duration-300 space-y-4 cursor-pointer">
                        <div class="absolute -top-3.5 left-6 bg-gradient-to-r from-blue-600 to-cyan-600 text-white text-[10px] font-black px-3 py-0.5 rounded-full uppercase tracking-widest shadow">Step 2</div>
                        <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-950 flex items-center justify-center text-2xl group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">
                            📦
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white mb-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">Select the Unit</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Choose <strong class="text-slate-700 dark:text-slate-300">Piece, Pack, or Carton</strong> — each with its own price tier. Stock is automatically deducted from a single warehouse source regardless of unit level chosen.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-semibold text-blue-600 dark:text-blue-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                            Auto stock deduction per tier
                        </div>
                    </div>

                    <!-- Step 3: Choose Payment -->
                    <div class="reveal-up delay-300 group relative p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-lg hover:border-emerald-500/80 hover:shadow-2xl hover:shadow-emerald-600/10 dark:hover:shadow-emerald-950/40 hover:-translate-y-2.5 hover:scale-[1.025] transition-all duration-300 space-y-4 cursor-pointer">
                        <div class="absolute -top-3.5 left-6 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[10px] font-black px-3 py-0.5 rounded-full uppercase tracking-widest shadow">Step 3</div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center text-2xl group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">
                            💳
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white mb-1 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">Choose Payment</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Accept <strong class="text-slate-700 dark:text-slate-300">Cash, Bank Transfer, Split Payment, or Debt Ledger</strong>. Customer debt is automatically posted to their CRM balance with full audit trail.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Cash · Transfer · Split · Debt
                        </div>
                    </div>

                    <!-- Step 4: Instant Receipt -->
                    <div class="reveal-up delay-400 group relative p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-lg hover:border-amber-500/80 hover:shadow-2xl hover:shadow-amber-600/10 dark:hover:shadow-amber-950/40 hover:-translate-y-2.5 hover:scale-[1.025] transition-all duration-300 space-y-4 cursor-pointer">
                        <div class="absolute -top-3.5 left-6 bg-gradient-to-r from-amber-500 to-orange-600 text-white text-[10px] font-black px-3 py-0.5 rounded-full uppercase tracking-widest shadow">Step 4</div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950 flex items-center justify-center text-2xl group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">
                            🖨️
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white mb-1 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">Instant Receipt</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                An <strong class="text-slate-700 dark:text-slate-300">80mm thermal receipt</strong> prints immediately — including IMEI serials for electronics, VAT breakdown, and payment summary. Works online or completely offline.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            80mm/58mm ESC/POS ready
                        </div>
                    </div>

                </div>

                <!-- Performance Stats Strip -->
                <div class="reveal-zoom delay-200 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs text-center space-y-1 hover:border-violet-400/80 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <div class="text-2xl font-black text-violet-600 dark:text-violet-400">&lt;0.4s</div>
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Checkout Speed</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs text-center space-y-1 hover:border-emerald-400/80 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">100%</div>
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Offline Capable</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs text-center space-y-1 hover:border-amber-400/80 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400">80mm</div>
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Thermal Receipt</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs text-center space-y-1 hover:border-blue-400/80 hover:shadow-lg hover:-translate-y-1 hover:scale-105 transition-all duration-300 cursor-default">
                        <div class="text-2xl font-black text-blue-600 dark:text-blue-400">3-Tier</div>
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit Pricing</div>
                    </div>
                </div>

            </div>
        </section>


        <!-- ══ 4. AI BUSINESS INTELLIGENCE COPILOT SPOTLIGHT ════════════════ -->
        <section id="ai-intelligence" class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <!-- Ambient Glow -->
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[750px] h-[550px] bg-gradient-to-br from-violet-500/15 via-fuchsia-500/10 to-indigo-500/15 rounded-full blur-[140px] pointer-events-none animate-float-orb"></div>

            <div class="max-w-7xl mx-auto space-y-16 relative z-10">
                <!-- Section Header -->
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-fuchsia-100 dark:bg-fuchsia-500/10 border border-fuchsia-300 dark:border-fuchsia-500/30 text-fuchsia-800 dark:text-fuchsia-400 text-xs font-extrabold uppercase tracking-wider">
                        🤖 Autonomous AI Assistant
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Conversational Business Intelligence
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base">
                        Your POS now thinks and speaks like an executive store manager. Query sales in real time, translate stock reports into native languages (English, Pidgin, Yoruba, Hausa, Igbo), and predict stockouts with zero spreadsheets.
                    </p>
                </div>

                <!-- AI Interactive Showcase Card -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch max-w-5xl mx-auto">
                    
                    <!-- Left: Prompt Pills & Custom Input (5 Cols) -->
                    <div class="reveal-left delay-100 lg:col-span-5 space-y-3 flex flex-col justify-between">
                        <div class="space-y-2.5">
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-2">
                                Select a live sample query:
                            </span>
                            <div
                                v-for="(item, idx) in aiPrompts"
                                :key="idx"
                                @click="selectAiPrompt(idx)"
                                :class="[
                                    'p-3.5 rounded-2xl border transition-all duration-300 cursor-pointer text-left hover:scale-[1.015] hover:-translate-y-0.5 hover:shadow-md',
                                    activeAiPromptIndex === idx
                                        ? 'bg-violet-50 dark:bg-gradient-to-r dark:from-violet-950/90 dark:to-purple-950/80 border-violet-400 dark:border-violet-500/70 shadow-md dark:shadow-lg dark:shadow-violet-950/60'
                                        : 'bg-white dark:bg-slate-900/80 hover:bg-slate-100 dark:hover:bg-slate-800/80 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300'
                                ]"
                            >
                                <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                                    <span class="text-violet-600 dark:text-violet-400 font-extrabold flex items-center gap-1">
                                        <span>{{ item.icon }}</span>
                                        <span>{{ item.lang }}</span>
                                    </span>
                                    <span class="text-slate-400">Prompt #{{ idx + 1 }}</span>
                                </div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white line-clamp-2">
                                    "{{ item.query }}"
                                </div>
                            </div>
                        </div>

                        <!-- Custom Query Simulator Input -->
                        <form @submit.prevent="submitCustomAiQuery" class="pt-2">
                            <div class="relative">
                                <input
                                    v-model="customAiQuery"
                                    type="text"
                                    placeholder="Type any custom store question..."
                                    class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-3.5 pr-10 py-2.5 text-xs text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:border-violet-500 shadow-xs transition-colors"
                                />
                                <button
                                    type="submit"
                                    class="absolute right-1.5 top-1.5 p-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-xs transition cursor-pointer"
                                >
                                    ➔
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right: AI Live Response Window (7 Cols) -->
                    <div class="reveal-right delay-200 lg:col-span-7 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xl dark:shadow-2xl hover:border-fuchsia-500/60 hover:shadow-fuchsia-900/20 transition-all duration-300 flex flex-col justify-between space-y-6">
                        <!-- AI Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center text-base font-black text-white shadow-md">
                                    🤖
                                </div>
                                <div>
                                    <div class="text-xs font-extrabold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <span>Skynet AI Copilot</span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">Neural Store Intelligence Engine</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-mono text-fuchsia-700 dark:text-fuchsia-400 bg-fuchsia-100 dark:bg-fuchsia-950/60 border border-fuchsia-300 dark:border-fuchsia-800/40 px-2.5 py-0.5 rounded-full font-bold">
                                Live Copilot
                            </span>
                        </div>

                        <!-- AI Response Body -->
                        <div class="min-h-[180px] flex flex-col justify-between space-y-4">
                            <div
                                v-if="!aiTyping"
                                class="ai-rendered-response text-xs sm:text-sm text-slate-800 dark:text-slate-200 leading-relaxed"
                                v-html="renderMarkdown(aiPrompts[activeAiPromptIndex].response)"
                            ></div>
                            <div v-else class="flex items-center gap-2 py-12 text-xs text-violet-600 dark:text-violet-400 justify-center">
                                <span class="w-2 h-2 rounded-full bg-violet-500 animate-ping"></span>
                                <span>Analyzing sales &amp; calculating inventory metrics across registers...</span>
                            </div>

                            <!-- Response Metrics Badges -->
                            <div v-if="!aiTyping" class="pt-4 border-t border-slate-200 dark:border-slate-800/80 flex flex-wrap gap-2 text-[11px] font-mono">
                                <span
                                    v-for="(val, key) in aiPrompts[activeAiPromptIndex].metrics"
                                    :key="key"
                                    class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300"
                                >
                                    <span class="text-slate-400 capitalize">{{ key }}: </span>
                                    <strong class="text-emerald-600 dark:text-emerald-400">{{ val }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ══ 5. PRODUCT VARIANTS & INVENTORY HIERARCHY ═════════════════ -->
        <section id="variants" class="py-20 px-4 sm:px-6 lg:px-8 bg-slate-100/80 dark:bg-slate-900/30 border-y border-slate-200 dark:border-slate-800 relative overflow-hidden">
            <div class="max-w-7xl mx-auto space-y-12">
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-blue-100 dark:bg-blue-500/10 border border-blue-300 dark:border-blue-500/30 text-blue-700 dark:text-blue-400 text-xs font-extrabold uppercase tracking-wider">
                        📦 Variants &amp; Packaging Tiers
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Flexible Product Variants &amp; Unit Levels
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base">
                        Stop manual math and stock shrinkage. Manage item variants by packaging level (Piece, Pack, Carton) or product attribute (Size, Color, Flavor, Storage) with dedicated barcodes, tiered pricing, and unified single-source warehouse deduction.
                    </p>
                </div>

                <!-- 3-Tier Multi-Unit / Variant Visual Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                    <!-- Tier 1: Single Unit -->
                    <div class="reveal-left delay-100 group p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-lg space-y-4 hover:border-blue-500/80 hover:shadow-2xl hover:shadow-blue-600/10 dark:hover:shadow-blue-950/40 hover:-translate-y-2 hover:scale-[1.03] transition-all duration-300 cursor-pointer">
                        <div class="w-10 h-10 rounded-2xl bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">
                            1️⃣
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Retail Level / Base Variant</span>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">Single Piece / Unit</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">e.g. 1 Single Tin Dano Milk @ ₦3,200</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 text-xs font-mono space-y-1">
                            <div class="text-slate-500">Stock Deduction: <strong class="text-slate-800 dark:text-white">1.00 Base Unit</strong></div>
                            <div class="text-slate-500">Barcode: <span class="text-blue-600 dark:text-blue-400">60012345678</span></div>
                        </div>
                    </div>

                    <!-- Tier 2: Pack -->
                    <div class="reveal-up delay-200 group p-6 rounded-3xl bg-white dark:bg-slate-950 border-2 border-violet-500 dark:border-violet-500/80 shadow-xl space-y-4 relative hover:shadow-2xl hover:shadow-violet-500/25 hover:border-violet-400 hover:-translate-y-2.5 hover:scale-[1.04] transition-all duration-300 cursor-pointer">
                        <div class="absolute -top-3 right-6 bg-violet-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow">
                            Wholesale Tier
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-violet-100 dark:bg-violet-950 text-violet-600 dark:text-violet-400 flex items-center justify-center font-bold text-lg group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">
                            6️⃣
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-violet-600 uppercase tracking-wider">Wholesale Pack Variant</span>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">Pack of 6 Units</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">e.g. 1 Pack @ ₦18,500 (Bulk Savings)</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 text-xs font-mono space-y-1">
                            <div class="text-slate-500">Stock Deduction: <strong class="text-slate-800 dark:text-white">6.00 Base Units</strong></div>
                            <div class="text-slate-500">Barcode: <span class="text-violet-600 dark:text-violet-400">60012345678-PK</span></div>
                        </div>
                    </div>

                    <!-- Tier 3: Carton -->
                    <div class="reveal-right delay-300 group p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-lg space-y-4 hover:border-emerald-500/80 hover:shadow-2xl hover:shadow-emerald-600/10 dark:hover:shadow-emerald-950/40 hover:-translate-y-2 hover:scale-[1.03] transition-all duration-300 cursor-pointer">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">
                            📦
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Distributor Master Carton</span>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">Master Carton (24x)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">e.g. 1 Master Carton @ ₦72,000</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 text-xs font-mono space-y-1">
                            <div class="text-slate-500">Stock Deduction: <strong class="text-slate-800 dark:text-white">24.00 Base Units</strong></div>
                            <div class="text-slate-500">Barcode: <span class="text-emerald-600 dark:text-emerald-400">60012345678-CTN</span></div>
                        </div>
                    </div>
                </div>

                <!-- Product Variant Highlights Matrix -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
                    <div class="reveal-up delay-100 group p-5 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2 hover:border-violet-500/70 hover:shadow-xl hover:-translate-y-1.5 hover:scale-[1.02] transition-all duration-300 cursor-pointer">
                        <div class="text-xl group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">🏷️</div>
                        <h4 class="text-xs font-extrabold text-slate-900 dark:text-white">Unique Variant Barcodes</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Each variant or packaging tier has its own scannable barcode for instantaneous 0.4s checkout lookup.
                        </p>
                    </div>

                    <div class="reveal-up delay-200 group p-5 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2 hover:border-violet-500/70 hover:shadow-xl hover:-translate-y-1.5 hover:scale-[1.02] transition-all duration-300 cursor-pointer">
                        <div class="text-xl group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">🔄</div>
                        <h4 class="text-xs font-extrabold text-slate-900 dark:text-white">Single-Source Stock Sync</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Selling a single piece, pack, or carton draws directly from unified warehouse inventory with zero math errors.
                        </p>
                    </div>

                    <div class="reveal-up delay-300 group p-5 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2 hover:border-violet-500/70 hover:shadow-xl hover:-translate-y-1.5 hover:scale-[1.02] transition-all duration-300 cursor-pointer">
                        <div class="text-xl group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">💰</div>
                        <h4 class="text-xs font-extrabold text-slate-900 dark:text-white">Tiered Profit Margins</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Configure retail, wholesale, and distributor prices with automated margin calculation per variant level.
                        </p>
                    </div>

                    <div class="reveal-up delay-400 group p-5 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2 hover:border-violet-500/70 hover:shadow-xl hover:-translate-y-1.5 hover:scale-[1.02] transition-all duration-300 cursor-pointer">
                        <div class="text-xl group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">🎯</div>
                        <h4 class="text-xs font-extrabold text-slate-900 dark:text-white">Multi-Industry Matrices</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Full matrix support for Supermarkets (packs/cartons), Pharmacies (strips/boxes), Boutiques (sizes/colors), and Laundromats (garments/piece/kg).
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 6. CORE ENTERPRISE FEATURES GRID ════════════════════════════ -->
        <section id="features" class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="max-w-7xl mx-auto space-y-16">
                <div class="reveal-up text-center space-y-4 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-violet-100 dark:bg-violet-500/10 border border-violet-300 dark:border-violet-500/30 text-violet-700 dark:text-violet-400 text-xs font-extrabold uppercase tracking-wider">
                        Enterprise Architecture
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Built for Speed, Scale &amp; Profit
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base">
                        Every tool your retail, pharmacy, supermarket, or distribution enterprise requires to stop inventory leaks and accelerate revenue.
                    </p>
                </div>

                <!-- 10-Card Feature Matrix Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                    <div
                        v-for="(feat, idx) in coreFeatures"
                        :key="idx"
                        :class="[
                            'reveal-up group relative p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 hover:border-violet-500/80 transition-all duration-300 shadow-md hover:shadow-2xl hover:shadow-violet-600/15 dark:hover:shadow-violet-950/50 hover:-translate-y-2.5 hover:scale-[1.025] flex flex-col justify-between space-y-4 cursor-pointer',
                            'delay-' + ((idx % 5 + 1) * 100)
                        ]"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div :class="['w-10 h-10 rounded-xl bg-gradient-to-br flex items-center justify-center text-white shadow-md transition-transform duration-300 group-hover:scale-120 group-hover:rotate-6', feat.gradient]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="feat.icon"/>
                                    </svg>
                                </div>
                                <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-2 py-0.5 rounded-md">
                                    {{ feat.badge }}
                                </span>
                            </div>
                            <h3 class="font-extrabold text-slate-900 dark:text-white text-base group-hover:text-violet-600 dark:group-hover:text-violet-300 transition-colors">
                                {{ feat.title }}
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400 text-xs leading-relaxed mt-2 font-normal">
                                {{ feat.desc }}
                            </p>
                        </div>
                        <div class="text-[11px] font-bold text-violet-600 dark:text-violet-400 flex items-center gap-1 group-hover:translate-x-1.5 transition-transform">
                            <span>Ready in Platform</span>
                            <span>→</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 6B. DOUBLE-ENTRY ACCOUNTING & FINANCIAL LEDGER SPOTLIGHT ══════ -->
        <section id="accounting-spotlight" class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-gradient-to-b from-slate-100/70 via-white to-slate-50 dark:from-slate-950 dark:via-slate-900/40 dark:to-slate-950 border-t border-slate-200 dark:border-slate-800/80">
            <div class="max-w-7xl mx-auto space-y-16 relative z-10">
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/70 border border-emerald-300 dark:border-emerald-700/60 text-emerald-800 dark:text-emerald-300 text-xs font-black uppercase tracking-wider">
                        📊 Automated Double-Entry Bookkeeping
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Real-Time Accounting Behind Every Sale &amp; Expense
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base leading-relaxed">
                        Say goodbye to end-of-month manual reconciliation headaches. Every checkout, till closure, customer credit sale, and expense receipt automatically posts balanced debits and credits straight into your General Ledger.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center max-w-6xl mx-auto">
                    <!-- Left: Interactive Live Journal Entry Mockup (7 Cols) -->
                    <div class="reveal-left delay-100 lg:col-span-7 rounded-3xl p-6 sm:p-7 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3 h-3 rounded-full bg-emerald-500 animate-ping"></span>
                                <span class="font-mono text-xs font-bold text-slate-900 dark:text-white">JOURNAL ENTRY #JE-202610-0418</span>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                Real-Time Balanced ✓
                            </span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="grid grid-cols-12 text-[10px] font-extrabold uppercase text-slate-400 px-2 pb-1">
                                <span class="col-span-6">Account &amp; Description</span>
                                <span class="col-span-3 text-right">Debit (DR)</span>
                                <span class="col-span-3 text-right">Credit (CR)</span>
                            </div>

                            <div class="grid grid-cols-12 items-center p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 font-mono text-[11px]">
                                <div class="col-span-6 font-sans">
                                    <div class="font-bold text-slate-900 dark:text-white">1000 — Cash on Hand</div>
                                    <div class="text-[10px] text-slate-400">Cash Register Till A Settlement</div>
                                </div>
                                <div class="col-span-3 text-right font-bold text-emerald-600 dark:text-emerald-400">₦25,000.00</div>
                                <div class="col-span-3 text-right text-slate-400">—</div>
                            </div>

                            <div class="grid grid-cols-12 items-center p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 font-mono text-[11px]">
                                <div class="col-span-6 font-sans">
                                    <div class="font-bold text-slate-900 dark:text-white">4000 — Sales Revenue</div>
                                    <div class="text-[10px] text-slate-400">Gross Retail Sales</div>
                                </div>
                                <div class="col-span-3 text-right text-slate-400">—</div>
                                <div class="col-span-3 text-right font-bold text-slate-900 dark:text-white">₦23,255.81</div>
                            </div>

                            <div class="grid grid-cols-12 items-center p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 font-mono text-[11px]">
                                <div class="col-span-6 font-sans">
                                    <div class="font-bold text-slate-900 dark:text-white">2100 — VAT / Tax Payable</div>
                                    <div class="text-[10px] text-slate-400">7.5% Output VAT Liability</div>
                                </div>
                                <div class="col-span-3 text-right text-slate-400">—</div>
                                <div class="col-span-3 text-right font-bold text-amber-600 dark:text-amber-400">₦1,744.19</div>
                            </div>

                            <div class="grid grid-cols-12 items-center p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 font-mono text-[11px]">
                                <div class="col-span-6 font-sans">
                                    <div class="font-bold text-slate-900 dark:text-white">5000 — Cost of Goods Sold</div>
                                    <div class="text-[10px] text-slate-400">Inventory Cost Depletion</div>
                                </div>
                                <div class="col-span-3 text-right font-bold text-rose-600 dark:text-rose-400">₦16,000.00</div>
                                <div class="col-span-3 text-right text-slate-400">—</div>
                            </div>

                            <div class="grid grid-cols-12 items-center p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 font-mono text-[11px]">
                                <div class="col-span-6 font-sans">
                                    <div class="font-bold text-slate-900 dark:text-white">1200 — Inventory Asset</div>
                                    <div class="text-[10px] text-slate-400">Warehouse Asset Deduction</div>
                                </div>
                                <div class="col-span-3 text-right text-slate-400">—</div>
                                <div class="col-span-3 text-right font-bold text-slate-900 dark:text-white">₦16,000.00</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs font-mono font-bold">
                            <span class="text-slate-500 uppercase text-[10px]">Total Balance Check:</span>
                            <div class="flex items-center gap-6">
                                <span class="text-emerald-600 dark:text-emerald-400">DR ₦41,000.00</span>
                                <span class="text-slate-900 dark:text-white">CR ₦41,000.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Pillars & Report Highlights (5 Cols) -->
                    <div class="reveal-right delay-200 lg:col-span-5 space-y-4">
                        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5 shadow-xs">
                            <div class="text-xs font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 uppercase tracking-wider">
                                <span>📚</span> 17 Core System Accounts + Custom GL
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                Standard chart of accounts covering Cash, Banks, POS Clearing, Debtors (AR), Creditors (AP), Inventory, Revenue, and Operating Expenses with instant custom additions.
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5 shadow-xs">
                            <div class="text-xs font-black text-violet-600 dark:text-violet-400 flex items-center gap-1.5 uppercase tracking-wider">
                                <span>📑</span> 5 Instant Financial Reports
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                Generate balanced <strong>Trial Balance</strong>, <strong>Balance Sheet</strong>, <strong>Profit &amp; Loss</strong>, <strong>Cash Flow Statement</strong>, and <strong>AR Aging Buckets</strong> with 1-click Excel and PDF export.
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5 shadow-xs">
                            <div class="text-xs font-black text-amber-600 dark:text-amber-400 flex items-center gap-1.5 uppercase tracking-wider">
                                <span>🏛️</span> Dedicated Tax &amp; VAT 2100 Ledger
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                Automatically separates VAT collected from sales revenue into Account 2100. Streamline tax audits and filing without calculating percentages manually.
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5 shadow-xs">
                            <div class="text-xs font-black text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5 uppercase tracking-wider">
                                <span>🔒</span> Financial Period Controls &amp; Reopening Locks
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                Lock closed months to prevent backdated tampering. Reopen with supervisor authorization and maintain complete audit logs for every adjustment.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 6C. OMNICHANNEL E-COMMERCE & WEB STORE SYNC SPOTLIGHT ════════ -->
        <section id="ecommerce-spotlight" class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800/80">
            <div class="max-w-7xl mx-auto space-y-16 relative z-10">
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-950/70 border border-indigo-300 dark:border-indigo-700/60 text-indigo-800 dark:text-indigo-300 text-xs font-black uppercase tracking-wider">
                        🛍️ Unified Omnichannel E-Commerce
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Sell in Store and Online Without Overselling
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base leading-relaxed">
                        Connect your online WooCommerce store or custom web application directly to your physical store registers. Two-way live stock synchronization and cart reservations eliminate stock discrepancies permanently.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                    <!-- Step 1: Two-Way Real-Time Sync -->
                    <div class="reveal-up delay-100 p-7 rounded-3xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm hover:shadow-xl hover:border-indigo-400 dark:hover:border-indigo-500/80 transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl">
                            ⚡
                        </div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">Sub-0.3s Two-Way Stock Sync</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            When an item is sold across an in-store cashier counter, the online catalog stock quantity decrements instantly. When an online customer places an order, your POS terminals update immediately.
                        </p>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800/80 flex items-center gap-1.5 text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                            <span>✓ REST API &amp; Webhook Engine</span>
                        </div>
                    </div>

                    <!-- Step 2: Smart Cart Reservations -->
                    <div class="reveal-up delay-200 p-7 rounded-3xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm hover:shadow-xl hover:border-emerald-400 dark:hover:border-emerald-500/80 transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xl">
                            🛡️
                        </div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">Zero-Oversell Cart Reservations</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            While an online customer proceeds through web checkout, their items are placed in a temporary reservation buffer. Cashiers cannot accidentally sell the reserved stock in person, preventing double-selling.
                        </p>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800/80 flex items-center gap-1.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                            <span>✓ Auto-Expire Unpaid Carts</span>
                        </div>
                    </div>

                    <!-- Step 3: Central Catalog Publishing -->
                    <div class="reveal-up delay-300 p-7 rounded-3xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm hover:shadow-xl hover:border-amber-400 dark:hover:border-amber-500/80 transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xl">
                            📦
                        </div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">One Central Catalog Hub</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Upload product images, descriptions, barcode variations, packaging sizes, and category tags once. Changes publish immediately across both your digital storefront and in-store registers.
                        </p>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800/80 flex items-center gap-1.5 text-[11px] font-bold text-amber-600 dark:text-amber-400">
                            <span>✓ WooCommerce &amp; Custom Stores</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 7. HARDWARE & PERIPHERALS COMPATIBILITY ═════════════════════ -->
        <section id="hardware" class="py-20 px-4 sm:px-6 lg:px-8 border-y border-slate-200 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900/30 relative overflow-hidden">
            <div class="max-w-7xl mx-auto space-y-12">
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-cyan-100 dark:bg-cyan-500/10 border border-cyan-300 dark:border-cyan-500/30 text-cyan-800 dark:text-cyan-400 text-xs font-extrabold uppercase tracking-wider">
                        🔌 Zero-Driver Hardware
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Plug &amp; Play with Any POS Hardware
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base">
                        Works seamlessly with all standard retail equipment. Plug in your thermal printer, cash drawer, and barcode scanner and start ringing up customers immediately.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="(hw, hIdx) in hardwareSupported"
                        :key="hIdx"
                        :class="[
                            'reveal-up group p-6 rounded-3xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-md space-y-3 hover:border-cyan-500/80 hover:shadow-2xl hover:shadow-cyan-500/15 hover:-translate-y-2 hover:scale-[1.03] transition-all duration-300 cursor-pointer',
                            'delay-' + ((hIdx + 1) * 100)
                        ]"
                    >
                        <div class="text-3xl group-hover:scale-125 group-hover:-rotate-6 transition-transform duration-300">{{ hw.icon }}</div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-base group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">{{ hw.name }}</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-xs leading-relaxed">{{ hw.detail }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 8. INDUSTRY SOLUTIONS MATRIX ════════════════════════════════ -->
        <section id="industries" class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="max-w-7xl mx-auto space-y-16">
                <div class="reveal-up text-center space-y-4 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-xs font-extrabold uppercase tracking-wider">
                        Industry Solutions
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Built for Every Type of Retail Business
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base">
                        Whether you operate a high-volume supermarket, a pharmacy, an electronics shop with IMEI tracking, a boutique, or a wholesale distributor, our POS adapts seamlessly to your store's daily workflow.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="(ind, i) in targetIndustries"
                        :key="i"
                        :class="[
                            'reveal-up group p-6 rounded-3xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-emerald-500/80 dark:hover:border-emerald-500/80 transition-all duration-300 space-y-3 shadow-sm hover:shadow-2xl hover:shadow-emerald-500/15 hover:-translate-y-2 hover:scale-[1.03] cursor-pointer',
                            'delay-' + ((i + 1) * 100)
                        ]"
                    >
                        <div class="text-3xl group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">{{ ind.icon }}</div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">{{ ind.name }}</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-xs leading-relaxed">{{ ind.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 9. NUMERICAL PROOF & ROI STATS ══════════════════════════════ -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 border-y border-slate-200 dark:border-slate-800 bg-white dark:bg-gradient-to-b dark:from-slate-950 dark:via-slate-900/80 dark:to-slate-950 relative overflow-hidden">
            <div class="reveal-zoom delay-100 max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="group space-y-1 p-4 rounded-2xl hover:bg-slate-100/70 dark:hover:bg-slate-900/70 hover:scale-110 hover:-translate-y-1 transition-all duration-300 cursor-default">
                    <div class="text-4xl sm:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-violet-600 to-indigo-600 dark:from-violet-400 dark:to-indigo-400 group-hover:scale-105 transition-transform">
                        &lt; 0.4s
                    </div>
                    <div class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">Checkout Speed</div>
                    <div class="text-[11px] text-slate-500">Zero lag during peak rush hours</div>
                </div>
                <div class="group space-y-1 p-4 rounded-2xl hover:bg-slate-100/70 dark:hover:bg-slate-900/70 hover:scale-110 hover:-translate-y-1 transition-all duration-300 cursor-default">
                    <div class="text-4xl sm:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-400 group-hover:scale-105 transition-transform">
                        100%
                    </div>
                    <div class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">Offline Capable</div>
                    <div class="text-[11px] text-slate-500">Dexie IndexedDB local engine</div>
                </div>
                <div class="group space-y-1 p-4 rounded-2xl hover:bg-slate-100/70 dark:hover:bg-slate-900/70 hover:scale-110 hover:-translate-y-1 transition-all duration-300 cursor-default">
                    <div class="text-4xl sm:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-600 to-orange-600 dark:from-amber-400 dark:to-orange-400 group-hover:scale-105 transition-transform">
                        ∞
                    </div>
                    <div class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">Branches &amp; Staff</div>
                    <div class="text-[11px] text-slate-500">Scale without per-device tax</div>
                </div>
                <div class="group space-y-1 p-4 rounded-2xl hover:bg-slate-100/70 dark:hover:bg-slate-900/70 hover:scale-110 hover:-translate-y-1 transition-all duration-300 cursor-default">
                    <div class="text-4xl sm:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-fuchsia-600 to-pink-600 dark:from-fuchsia-400 dark:to-pink-400 group-hover:scale-105 transition-transform">
                        24/7
                    </div>
                    <div class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">AI Business Insights</div>
                    <div class="text-[11px] text-slate-500">Instant multi-lingual answers</div>
                </div>
            </div>
        </section>

        <!-- ══ 10. CLIENT TESTIMONIALS & TRUST ═════════════════════════════ -->
        <section class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="max-w-7xl mx-auto space-y-16">
                <div class="reveal-up text-center space-y-3 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-100 dark:bg-amber-500/10 border border-amber-300 dark:border-amber-500/30 text-amber-800 dark:text-amber-400 text-xs font-extrabold uppercase tracking-wider">
                        ⭐️ Client Stories
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        Trusted by High-Volume Merchants
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        v-for="(t, tIdx) in testimonials"
                        :key="tIdx"
                        :class="[
                            'reveal-up group p-6 rounded-3xl bg-white dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 shadow-md space-y-4 flex flex-col justify-between hover:border-amber-500/80 hover:shadow-2xl hover:shadow-amber-500/15 hover:-translate-y-2.5 hover:scale-[1.025] transition-all duration-300 cursor-default',
                            'delay-' + ((tIdx + 1) * 150)
                        ]"
                    >
                        <p class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed italic">
                            "{{ t.quote }}"
                        </p>
                        <div class="flex items-center gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <div :class="['w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs transition-transform duration-300 group-hover:scale-115', t.avatarBg]">
                                {{ t.name.charAt(0) }}
                            </div>
                            <div>
                                <h4 class="font-extrabold text-xs text-slate-900 dark:text-white">{{ t.name }}</h4>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ t.role }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 11. FREE POS DATA MIGRATION ASSISTANCE ═══════════════════ -->
        <section class="py-16 px-4 sm:px-6 lg:px-8">
            <div class="reveal-zoom delay-100 max-w-6xl mx-auto rounded-3xl bg-gradient-to-br from-violet-500/10 via-purple-500/5 to-emerald-500/10 border border-violet-200 dark:border-violet-800/60 p-8 sm:p-12 shadow-xl hover:shadow-2xl hover:border-violet-400/80 hover:scale-[1.01] transition-all duration-300 relative overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-7 space-y-4 text-left">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-700/60 text-emerald-800 dark:text-emerald-300 text-xs font-black uppercase tracking-wider">
                            🔄 Hassle-Free Onboarding
                        </div>
                        <h2 class="text-2xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
                            Switching from Another POS? <br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-600 to-emerald-600 dark:from-violet-400 dark:to-emerald-400">
                                We Migrate Your Entire Store for Free.
                            </span>
                        </h2>
                        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed">
                            Don't let data migration hold your business back. Our dedicated technical team easily extracts, formats, and imports all your existing product catalogs, barcodes, customer credit balances, and supplier lists from your old POS with 100% accuracy and zero downtime.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                            <div class="flex items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <span class="text-emerald-500 text-base">✓</span>
                                <span>Bulk Excel, CSV &amp; Database Imports</span>
                            </div>
                            <div class="flex items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <span class="text-emerald-500 text-base">✓</span>
                                <span>Customer Debt &amp; Ledger Transfer</span>
                            </div>
                            <div class="flex items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <span class="text-emerald-500 text-base">✓</span>
                                <span>Barcode &amp; Packaging Variant Formatting</span>
                            </div>
                            <div class="flex items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <span class="text-emerald-500 text-base">✓</span>
                                <span>1-on-1 Dedicated Switch Support</span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-5 flex flex-col items-center lg:items-end justify-center">
                        <div class="group p-6 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4 text-center w-full max-w-sm hover:-translate-y-1 hover:shadow-2xl hover:scale-[1.02] transition-all duration-300">
                            <div class="text-4xl group-hover:scale-125 group-hover:rotate-6 transition-transform duration-300">🚀</div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white">Seamless Data Migration</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Send us your existing inventory export or connect with our migration engineers on WhatsApp.</p>
                            <a
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener"
                                class="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition shadow-lg shadow-emerald-600/30 hover:scale-105 active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <span>💬 Request Free POS Migration</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 12. PRICING & PLANS SECTION (SUPER ADMIN MANAGED) ════════════ -->
        <section
            v-if="pricingSectionEnabled"
            id="pricing"
            class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-slate-50/70 dark:bg-slate-950/80 border-t border-slate-200 dark:border-slate-800"
        >
            <!-- Ambient Glow Mesh -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-gradient-to-tr from-violet-600/10 via-amber-500/10 to-indigo-600/10 blur-[130px]"></div>
            </div>

            <div class="max-w-7xl mx-auto space-y-16 relative z-10">
                <!-- 4-Column Marketing Value Props Strip -->
                <div
                    v-if="pricing?.value_props && pricing.value_props.length"
                    class="reveal-zoom delay-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-md"
                >
                    <div
                        v-for="(vp, vpIdx) in pricing.value_props"
                        :key="vpIdx"
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-100 dark:border-slate-800/80 space-y-1 hover:border-violet-500/40 transition-colors"
                    >
                        <div class="text-xs font-black tracking-wider uppercase text-transparent bg-clip-text bg-gradient-to-r from-violet-600 to-indigo-600 dark:from-violet-400 dark:to-indigo-400">
                            {{ vp.title }}
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-snug font-medium">
                            {{ vp.subtext }}
                        </p>
                    </div>
                </div>

                <!-- Intro Heading & Description -->
                <div class="reveal-up text-center space-y-4 max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-violet-100 dark:bg-violet-500/10 border border-violet-300 dark:border-violet-500/30 text-violet-700 dark:text-violet-400 text-xs font-extrabold uppercase tracking-wider">
                        Pricing &amp; Investment
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ pricing?.settings?.pricing_intro_heading || 'Smart Retail Management for Growing Businesses' }}
                    </h2>
                    <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base leading-relaxed">
                        {{ pricing?.settings?.pricing_intro_text || 'Manage sales, inventory, staff, customers and multiple branches from anywhere. Transparent pricing designed to help you start easily and scale confidently.' }}
                    </p>
                </div>

                <!-- Baseline "What Every Plan Includes" Strip -->
                <div
                    v-if="pricing?.included_items && pricing.included_items.length"
                    class="reveal-up delay-150 max-w-5xl mx-auto p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-violet-900/10 via-purple-900/5 to-indigo-900/10 border border-violet-200 dark:border-violet-800/60 shadow-xs"
                >
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-lg">✨</span>
                        <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            {{ pricing?.settings?.whats_included_heading || 'What Every Plan Includes' }}
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div
                            v-for="(item, iIdx) in pricing.included_items"
                            :key="iIdx"
                            class="flex items-start gap-2.5 text-xs text-slate-700 dark:text-slate-300 font-medium"
                        >
                            <span class="text-emerald-500 font-bold flex-shrink-0">✓</span>
                            <span>{{ item.item_text }}</span>
                        </div>
                    </div>
                    <div class="mt-5 pt-4 border-t border-violet-200/60 dark:border-violet-800/60 flex items-center gap-2 text-xs text-violet-800 dark:text-violet-300 font-bold">
                        <span class="text-base">🧺</span>
                        <span>Full Laundry Management Module is included across all plans (even Starter) with zero add-on license fees.</span>
                    </div>
                </div>

                <!-- Plan Cards: Spacious 3-col Standard + 2-col Enterprise Layout -->
                <div class="space-y-12">
                    <!-- Group 1: Standard Retail Plans (Starter, Bronze, Silver [Most Popular & Comprehensive]) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
                        <div
                            v-for="(plan, pIdx) in retailPlans"
                            :key="plan.id"
                            :class="[
                                'reveal-up group relative rounded-3xl p-7 sm:p-8 flex flex-col justify-between transition-all duration-300',
                                plan.is_highlighted
                                    ? 'bg-gradient-to-b from-amber-500/[0.09] via-amber-500/[0.03] to-white dark:to-slate-900 border-2 border-amber-400 dark:border-amber-500 shadow-2xl shadow-amber-500/20 ring-4 ring-amber-400/20 scale-[1.02] z-10'
                                    : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-xl hover:border-violet-400 dark:hover:border-violet-500/80 hover:-translate-y-1.5',
                                'delay-' + ((pIdx + 1) * 100)
                            ]"
                        >
                            <!-- Top Tag / Pill for Highlighted Plan (Silver) -->
                            <div
                                v-if="plan.is_highlighted"
                                class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1.5 rounded-full bg-gradient-to-r from-amber-500 via-orange-500 to-amber-500 text-slate-950 font-black text-[11px] tracking-wider uppercase shadow-lg shadow-amber-500/30 flex items-center gap-1.5 whitespace-nowrap"
                            >
                                <span>⭐ Most Popular &amp; Comprehensive</span>
                            </div>

                            <!-- Card Header & Pricing -->
                            <div class="space-y-5">
                                <div class="pt-2">
                                    <h3 class="text-2xl font-black text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                        {{ plan.name }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 min-h-[32px] leading-relaxed">
                                        {{ plan.tagline }}
                                    </p>
                                </div>

                                <!-- Price Display -->
                                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-100 dark:border-slate-800/80">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">First Year Setup &amp; License</div>
                                    <div class="text-3xl font-black text-slate-900 dark:text-white font-mono mt-0.5 tracking-tight">
                                        {{ plan.first_year_price }}
                                    </div>
                                    <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono mt-1.5 border-t border-slate-200/60 dark:border-slate-800/60 pt-2 flex items-center justify-between">
                                        <span>Annual Renewal:</span>
                                        <span>{{ plan.renewal_price }}</span>
                                    </div>
                                </div>

                                <!-- Plan Capacity / Operational Limits -->
                                <div class="space-y-2 text-xs border-y border-slate-100 dark:border-slate-800/80 py-3.5">
                                    <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                        <span class="text-slate-400">Branches:</span>
                                        <span class="font-extrabold">{{ plan.branches_limit }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                        <span class="text-slate-400">Users:</span>
                                        <span class="font-extrabold">{{ plan.users_limit }}</span>
                                    </div>
                                    <div v-if="plan.ai_credits" class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                        <span class="text-slate-400">AI Queries:</span>
                                        <span class="font-bold text-xs text-violet-600 dark:text-violet-400 text-right">{{ plan.ai_credits }}</span>
                                    </div>
                                    <div v-if="plan.support_level" class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                        <span class="text-slate-400">Support:</span>
                                        <span class="font-medium text-xs text-right">{{ plan.support_level }}</span>
                                    </div>
                                </div>

                                <!-- Bullet Features List -->
                                <div class="space-y-2.5 pt-1">
                                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                        Included Capabilities
                                    </div>
                                    <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                                        <li
                                            v-for="(f, featIdx) in plan.features"
                                            :key="featIdx"
                                            class="flex items-start gap-2.5 leading-snug"
                                        >
                                            <span :class="['font-bold text-xs mt-0.5 shrink-0', plan.is_highlighted ? 'text-amber-500' : 'text-emerald-500']">✓</span>
                                            <span>{{ f.feature_text }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Card CTA -->
                            <div class="pt-6">
                                <a
                                    :href="planWhatsappUrl(plan.name)"
                                    target="_blank"
                                    rel="noopener"
                                    :class="[
                                        'w-full py-3.5 px-4 rounded-xl font-extrabold text-xs transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer hover:scale-105 active:scale-95',
                                        plan.is_highlighted
                                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 shadow-amber-500/25'
                                            : 'bg-violet-600 hover:bg-violet-500 text-white shadow-violet-600/20'
                                    ]"
                                >
                                    <span>Get Started on WhatsApp</span>
                                    <span>→</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Group 2: High-Volume Chains & Enterprise Conglomerates (Gold, Enterprise) -->
                    <div v-if="enterprisePlans.length" class="pt-8 border-t border-slate-200 dark:border-slate-800/80 space-y-6">
                        <div class="text-center space-y-2 max-w-2xl mx-auto">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-violet-100 dark:bg-violet-950/70 border border-violet-200 dark:border-violet-800 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                                Multi-Warehouse &amp; Enterprise Conglomerates
                            </span>
                            <h3 class="text-2xl font-black text-slate-900 dark:text-white">
                                High-Volume Chains &amp; Custom Deployments
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Supermarket chains and enterprise retail groups requiring multi-warehouse distribution, custom ERP integration, and dedicated technical engineers.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl mx-auto items-stretch">
                            <div
                                v-for="(plan, epIdx) in enterprisePlans"
                                :key="plan.id"
                                :class="[
                                    'reveal-up group relative rounded-3xl p-7 sm:p-8 flex flex-col justify-between transition-all duration-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-xl hover:border-violet-400 dark:hover:border-violet-500/80 hover:-translate-y-1.5',
                                    'delay-' + ((epIdx + 1) * 150)
                                ]"
                            >
                                <div class="space-y-5">
                                    <div class="pt-1">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-2xl font-black text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                                {{ plan.name }}
                                            </h3>
                                            <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                Enterprise Tier
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 min-h-[32px] leading-relaxed">
                                            {{ plan.tagline }}
                                        </p>
                                    </div>

                                    <!-- Price Display -->
                                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-100 dark:border-slate-800/80">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">First Year Setup &amp; License</div>
                                        <div class="text-3xl font-black text-slate-900 dark:text-white font-mono mt-0.5 tracking-tight">
                                            {{ plan.first_year_price }}
                                        </div>
                                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono mt-1.5 border-t border-slate-200/60 dark:border-slate-800/60 pt-2 flex items-center justify-between">
                                            <span>Annual Renewal:</span>
                                            <span>{{ plan.renewal_price }}</span>
                                        </div>
                                    </div>

                                    <!-- Plan Capacity -->
                                    <div class="space-y-2 text-xs border-y border-slate-100 dark:border-slate-800/80 py-3.5">
                                        <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                            <span class="text-slate-400">Branches:</span>
                                            <span class="font-extrabold">{{ plan.branches_limit }}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                            <span class="text-slate-400">Users:</span>
                                            <span class="font-extrabold">{{ plan.users_limit }}</span>
                                        </div>
                                        <div v-if="plan.ai_credits" class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                            <span class="text-slate-400">AI Queries:</span>
                                            <span class="font-bold text-xs text-violet-600 dark:text-violet-400 text-right">{{ plan.ai_credits }}</span>
                                        </div>
                                        <div v-if="plan.support_level" class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                            <span class="text-slate-400">Support:</span>
                                            <span class="font-medium text-xs text-right">{{ plan.support_level }}</span>
                                        </div>
                                    </div>

                                    <!-- Bullet Features -->
                                    <div class="space-y-2.5 pt-1">
                                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                            Included Capabilities
                                        </div>
                                        <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                                            <li
                                                v-for="(f, featIdx) in plan.features"
                                                :key="featIdx"
                                                class="flex items-start gap-2.5 leading-snug"
                                            >
                                                <span class="font-bold text-xs mt-0.5 text-emerald-500 shrink-0">✓</span>
                                                <span>{{ f.feature_text }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="pt-6">
                                    <a
                                        :href="planWhatsappUrl(plan.name)"
                                        target="_blank"
                                        rel="noopener"
                                        class="w-full py-3.5 px-4 rounded-xl font-extrabold text-xs transition-all shadow-md flex items-center justify-center gap-1.5 cursor-pointer hover:scale-105 active:scale-95 bg-violet-600 hover:bg-violet-500 text-white shadow-violet-600/20"
                                    >
                                        <span>Get Started on WhatsApp</span>
                                        <span>→</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add-Ons & Commercial Notes (Rendered Markdown) -->
                <div
                    v-if="pricing?.settings?.addons_content || pricing?.settings?.commercial_notes_content"
                    class="reveal-up delay-200 grid grid-cols-1 lg:grid-cols-2 gap-8 pt-4"
                >
                    <!-- Left: Optional Add-Ons -->
                    <div
                        v-if="pricing?.settings?.addons_content"
                        class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🧩</span>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ pricing?.settings?.addons_heading || 'Optional Add-Ons & Expansions' }}
                            </h3>
                        </div>
                        <div
                            class="pricing-markdown-content text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed"
                            v-html="renderMarkdown(pricing.settings.addons_content)"
                        ></div>
                    </div>

                    <!-- Right: Commercial Notes -->
                    <div
                        v-if="pricing?.settings?.commercial_notes_content"
                        class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📋</span>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ pricing?.settings?.commercial_notes_heading || 'Important Commercial Notes' }}
                            </h3>
                        </div>
                        <div
                            class="pricing-markdown-content text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed"
                            v-html="renderMarkdown(pricing.settings.commercial_notes_content)"
                        ></div>
                    </div>
                </div>

                <!-- Section Footer Banner -->
                <div class="reveal-zoom delay-250 p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 text-white flex flex-col sm:flex-row items-center justify-between gap-6 shadow-xl shadow-violet-600/20">
                    <div class="space-y-1 text-center sm:text-left">
                        <h4 class="text-lg sm:text-xl font-black">
                            Have unique multi-branch retail requirements?
                        </h4>
                        <p class="text-xs sm:text-sm text-violet-100">
                            Our enterprise engineering specialists will tailor a custom architecture quote and rollout strategy.
                        </p>
                    </div>
                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener"
                        class="flex-shrink-0 px-6 py-3.5 rounded-2xl bg-white text-violet-950 font-black text-xs hover:bg-slate-100 transition shadow-lg hover:scale-105 active:scale-95 flex items-center gap-2 cursor-pointer"
                    >
                        <span>💬 Talk to Sales on WhatsApp</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- ══ 13. FAQ ACCORDION ════════════════════════════════════════════ -->
        <section id="faq" class="py-24 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-12">
            <div class="reveal-up text-center space-y-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-violet-100 dark:bg-violet-500/10 border border-violet-300 dark:border-violet-500/30 text-violet-700 dark:text-violet-400 text-xs font-extrabold uppercase tracking-wider">
                    Got Questions?
                </div>
                <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                    Frequently Asked Questions
                </h2>
            </div>

            <div class="space-y-3">
                <div
                    v-for="(faq, fIndex) in faqs"
                    :key="fIndex"
                    :class="[
                        'reveal-up rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 overflow-hidden transition-all duration-300 shadow-xs hover:border-violet-400 dark:hover:border-violet-500/80 hover:shadow-md hover:scale-[1.01]',
                        'delay-' + ((fIndex % 4 + 1) * 100)
                    ]"
                >
                    <button
                        @click="toggleFaq(fIndex)"
                        type="button"
                        class="w-full p-5 text-left flex items-center justify-between gap-4 font-extrabold text-sm text-slate-900 dark:text-white hover:text-violet-600 dark:hover:text-violet-300 transition cursor-pointer"
                    >
                        <span>{{ faq.q }}</span>
                        <span class="text-violet-600 dark:text-violet-400 font-mono text-base flex-shrink-0 transition-transform duration-200">
                            {{ activeFaqIndex === fIndex ? '−' : '+' }}
                        </span>
                    </button>
                    <div
                        v-if="activeFaqIndex === fIndex"
                        class="px-5 pb-5 text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 dark:border-slate-800/60 pt-3"
                    >
                        {{ faq.a }}
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ 13. HIGH-CONVERTING CLOSING CTA ═════════════════════════════ -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="reveal-zoom delay-100 max-w-5xl mx-auto relative z-10 bg-gradient-to-r from-violet-900/90 via-purple-900/90 to-indigo-900/90 border border-violet-500/40 rounded-3xl p-8 sm:p-14 text-center space-y-8 shadow-2xl hover:shadow-violet-600/30 hover:scale-[1.01] transition-all duration-300 backdrop-blur-xl text-white">
                <div class="space-y-3">
                    <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                        Ready to Upgrade Your Store Operations?
                    </h2>
                    <p class="text-violet-200 text-sm sm:text-base max-w-2xl mx-auto">
                        Launch your POS terminal instantly or connect directly with our engineering team on WhatsApp for free POS data migration, catalog onboarding, and custom multi-store rollouts.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                    <Link
                        :href="posUrl"
                        class="flex items-center gap-3 px-8 py-4 bg-white text-violet-950 font-black text-base rounded-2xl transition-all shadow-xl hover:bg-slate-100 hover:scale-105 active:scale-95 cursor-pointer"
                    >
                        <span>Launch POS Terminal</span>
                        <span>→</span>
                    </Link>

                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-2.5 px-8 py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-base rounded-2xl transition-all shadow-xl hover:scale-105 active:scale-95 border border-emerald-400/40 cursor-pointer"
                    >
                        <span>💬 Talk to Sales on WhatsApp</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- ══ 13. COMPREHENSIVE FOOTER ════════════════════════════════════ -->
        <Footer
            :company-name="companyName"
            :support-phone="contact_info.phone"
            :support-email="contact_info.email"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingSectionEnabled"
        />

    </div>
</template>

<style scoped>
/* ── Hero Load Animations ─────────────────────────────────────────────────── */
@keyframes heroFadeUp {
    from {
        opacity: 0;
        transform: translateY(28px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-hero-badge { animation: heroFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.05s both; }
.animate-hero-title { animation: heroFadeUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.15s both; }
.animate-hero-subtitle { animation: heroFadeUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.28s both; }
.animate-hero-cta { animation: heroFadeUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.38s both; }
.animate-hero-migration { animation: heroFadeUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.48s both; }
.animate-hero-badges { animation: heroFadeUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.58s both; }

/* ── Ambient Mesh Floating Keyframes ───────────────────────────────────────── */
@keyframes floatOrb {
    0%, 100% {
        transform: translateY(0px) scale(1);
    }
    50% {
        transform: translateY(-20px) scale(1.05);
    }
}
.animate-float-orb {
    animation: floatOrb 10s ease-in-out infinite;
}
.animate-float-orb-delayed {
    animation: floatOrb 12s ease-in-out infinite 3s;
}

/* ── Scroll Reveal System ──────────────────────────────────────────────────── */
.reveal-up {
    opacity: 0;
    transform: translateY(32px);
    transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}
.reveal-left {
    opacity: 0;
    transform: translateX(-40px);
    transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}
.reveal-right {
    opacity: 0;
    transform: translateX(40px);
    transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}
.reveal-zoom {
    opacity: 0;
    transform: scale(0.93);
    transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal-up.is-revealed,
.reveal-left.is-revealed,
.reveal-right.is-revealed,
.reveal-zoom.is-revealed {
    opacity: 1;
    transform: translate(0, 0) scale(1);
}

/* Stagger Delays */
.delay-100 { transition-delay: 100ms; }
.delay-150 { transition-delay: 150ms; }
.delay-200 { transition-delay: 200ms; }
.delay-250 { transition-delay: 250ms; }
.delay-300 { transition-delay: 300ms; }
.delay-400 { transition-delay: 400ms; }
.delay-450 { transition-delay: 450ms; }

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: scale(0.97);
}

:deep(.ai-rendered-response) p {
    margin-bottom: 0.65rem;
}
:deep(.ai-rendered-response) p:last-child {
    margin-bottom: 0;
}
:deep(.ai-rendered-response) strong,
:deep(.ai-rendered-response) b {
    font-weight: 700;
}
:deep(.ai-rendered-response) em,
:deep(.ai-rendered-response) i {
    font-style: italic;
}
:deep(.ai-rendered-response) ol {
    list-style-type: decimal;
    padding-left: 1.25rem;
    margin-bottom: 0.65rem;
}
:deep(.ai-rendered-response) ul {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.65rem;
}
:deep(.ai-rendered-response) li {
    margin-bottom: 0.35rem;
    line-height: 1.5;
}
:deep(.ai-rendered-response) code {
    background-color: rgba(148, 163, 184, 0.15);
    border: 1px solid rgba(148, 163, 184, 0.25);
    padding: 0.1rem 0.35rem;
    border-radius: 0.375rem;
    font-family: monospace;
    color: #8b5cf6;
    font-size: 0.8em;
}
:deep(.pricing-markdown-content) p {
    margin-bottom: 0.65rem;
}
:deep(.pricing-markdown-content) p:last-child {
    margin-bottom: 0;
}
:deep(.pricing-markdown-content) strong,
:deep(.pricing-markdown-content) b {
    font-weight: 700;
}
:deep(.pricing-markdown-content) ul {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.65rem;
}
:deep(.pricing-markdown-content) ol {
    list-style-type: decimal;
    padding-left: 1.25rem;
    margin-bottom: 0.65rem;
}
:deep(.pricing-markdown-content) li {
    margin-bottom: 0.4rem;
    line-height: 1.5;
}
</style>
