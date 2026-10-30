<script setup>
import { computed } from 'vue'
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
    const msg = encodeURIComponent(`Hello ${companyName.value}, I am reviewing your Industries page and would like to learn more about your POS solutions for my store type.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const pricingEnabled = computed(() => !!(props.pricing?.enabled && props.pricing?.settings?.show_pricing_section))

const verticalSolutions = [
    {
        name: 'Supermarkets & Grocery Stores',
        icon: '🛒',
        tagline: 'Sub-0.4s fast lane checkouts, multi-pack unit conversion & shrinkage control',
        features: [
            'Break down bulk cartons into individual packs and pieces with automated inventory sync',
            'Offline multi-lane registers keep checkout lines moving with zero network delays',
            'Automated low-stock threshold alerts and draft Purchase Orders for quick vendor replenishment',
            'Real-time cash drawer tracking and shift reconciliation per cashier',
        ],
    },
    {
        name: 'Pharmacies & Healthcare Retail',
        icon: '💊',
        tagline: 'Expiry date tracking, batch lot monitoring & dosage strip matrices',
        features: [
            'Sell single sachets, strips, or complete packets from one unified product record',
            'Automatic notifications for items approaching expiration dates to eliminate dead stock',
            'Track customer prescription credits, debts, and partial payment history',
            'Secure role permissions: cashiers cannot alter prices or delete completed sales',
        ],
    },
    {
        name: 'Electronics, Phones & Gadgets',
        icon: '📱',
        tagline: 'IMEI / Serial number tracking, warranty lookups & sales commissions',
        features: [
            'Capture and search unique device IMEIs and serial numbers during sale and return',
            'Customer receipt lookups verify warranty eligibility in seconds with AI summary',
            'Track individual sales reps and calculate monthly commissions automatically',
            'Inter-branch stock transfer lookup: check if another branch has a specific phone model in stock',
        ],
    },
    {
        name: 'Boutiques & Apparel Fashion',
        icon: '👗',
        tagline: 'Matrix variants by size & color, custom barcode tagging & customer loyalty',
        features: [
            'Full variant matrix support (e.g. Red / Size XL, Blue / Size Medium) under one parent SKU',
            'Print custom barcode price tags on standard 58mm/80mm thermal label printers',
            'Held sales (layaway): hold customer items while they continue shopping without blocking registers',
            'Multi-tender checkout: accept split payments across Cash and Card/Transfer',
        ],
    },
    {
        name: 'Wholesale Merchants & Distributors',
        icon: '📦',
        tagline: 'Tiered wholesale pricing, customer credit ledgers & bulk imports',
        features: [
            'Configure automatic retail, wholesale, and major distributor price tiers per variant',
            'Comprehensive customer debt ledger with credit limits, overdue days, and statement exports',
            'Centralized purchase order creation and multi-branch warehouse dispatch tracking',
            'Bulk Excel/CSV catalog imports and seamless legacy POS data migration',
        ],
    },
    {
        name: 'Laundromats & Dry Cleaning Services',
        icon: '🧺',
        tagline: 'Itemized garment intake, visual workflow stages, custom tagging & collection tracking',
        badge: 'Included in All Plans (Even Starter)',
        features: [
            'Itemized garment intake with piece or kg pricing, starch preferences, damage notes, and garment brands',
            'Visual workflow board: track items smoothly from Intake to Washing, Drying, Ironing, Ready, and Picked Up',
            'Thermal garment tag and counter receipt printing with unique order tracking numbers for rapid retrieval',
            'Customer collection tracking with optional unpaid pickup control, automated WhatsApp alerts, and partial balance settlements',
        ],
    },
]
</script>

<template>
    <Head title="Industry Solutions — Tailored Enterprise POS" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors selection:bg-violet-500 selection:text-white">
        <!-- Reusable Navigation -->
        <Navbar
            :company-name="companyName"
            :app-tagline="appTagline"
            :logo-url="logoUrl"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingEnabled"
        />

        <main class="pt-28 pb-20">
            <!-- Hero -->
            <section class="relative px-4 sm:px-6 lg:px-8 py-16 text-center overflow-hidden">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[850px] h-[450px] rounded-full bg-gradient-to-tr from-emerald-600/15 via-teal-600/10 to-violet-600/15 blur-[140px]"></div>
                </div>

                <div class="relative z-10 max-w-4xl mx-auto space-y-6">
                    <div class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <Link :href="route('home')" class="hover:text-violet-600">Home</Link>
                        <span>/</span>
                        <span class="text-violet-600 dark:text-violet-400 font-bold">Industry Solutions</span>
                    </div>

                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-700/60 text-emerald-800 dark:text-emerald-300 text-xs font-bold uppercase tracking-wider">
                        Specialized Store Solutions
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                        Built for Your Exact Retail Workflow.
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
                        Whether you manage a busy supermarket with rush-hour queues, a pharmacy requiring strip-to-packet unit conversion, an electronics hub tracking IMEIs, or a laundromat managing garment intake and pickup workflows, our platform molds perfectly to your day-to-day requirements.
                    </p>
                </div>
            </section>

            <!-- Vertical Solutions -->
            <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12 mb-20">
                <div
                    v-for="(vert, idx) in verticalSolutions"
                    :key="idx"
                    class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6 hover:border-emerald-500/60 transition-colors"
                >
                    <div class="flex items-center justify-between flex-wrap gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 flex items-center justify-center text-3xl shadow-sm">
                                {{ vert.icon }}
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ vert.name }}</h2>
                                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">{{ vert.tagline }}</p>
                            </div>
                        </div>
                        <div v-if="vert.badge" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-violet-100 dark:bg-violet-950/80 border border-violet-300 dark:border-violet-700/60 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider shadow-xs">
                            <span>✨</span>
                            <span>{{ vert.badge }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="(feat, fIdx) in vert.features"
                            :key="fIdx"
                            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/80 flex items-start gap-3"
                        >
                            <span class="text-emerald-500 font-bold text-sm">✓</span>
                            <span class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 font-medium leading-relaxed">{{ feat }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="p-8 sm:p-12 rounded-3xl bg-gradient-to-r from-emerald-900 via-teal-900 to-violet-900 border border-emerald-500/40 text-white text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-8 shadow-2xl">
                    <div class="space-y-2">
                        <h3 class="text-2xl sm:text-3xl font-black text-white">
                            Ready to configure your store's workflow?
                        </h3>
                        <p class="text-xs sm:text-sm text-emerald-200 max-w-xl">
                            Our team sets up your catalog formatting, packaging variants, and barcode printers completely free of charge.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="posUrl"
                            class="px-6 py-3.5 rounded-xl bg-white text-emerald-950 font-black text-xs hover:bg-slate-100 transition shadow-lg"
                        >
                            Launch POS Terminal
                        </Link>
                        <a
                            :href="whatsappUrl"
                            target="_blank"
                            rel="noopener"
                            class="px-6 py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white font-extrabold text-xs transition shadow-lg flex items-center gap-1.5"
                        >
                            <span>💬 Talk on WhatsApp</span>
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
