<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
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

const defaultBranchSlug = computed(() => props.default_branch?.slug || 'felix-enterprise')
const posUrl = computed(() => route('pos.index', { branch: defaultBranchSlug.value }))

const whatsappUrl = computed(() => {
    const phone = props.contact_info?.clean_phone || '2348032072831'
    const msg = encodeURIComponent(`Hello ${companyName.value}, I am reviewing your Pricing Plans on the website. Please provide onboarding and deployment details.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const retailPlans = computed(() => (props.pricing?.plans || []).slice(0, 3))
const enterprisePlans = computed(() => (props.pricing?.plans || []).slice(3))

function planWhatsappUrl(planName) {
    const phone = props.contact_info?.clean_phone || '2348032072831'
    const msg = encodeURIComponent(`Hello ${companyName.value}, I am interested in acquiring the ${planName} Plan for my store. Please provide deployment and license activation details.`)
    return `https://wa.me/${phone}?text=${msg}`
}

const pricingFaqs = [
    {
        q: 'What is included in the First-Year price vs the Renewal price?',
        a: 'The first-year investment includes complete cloud instance provisioning, catalog setup, initial staff training, and platform software license. The annual renewal strictly covers continued fast cloud server hosting, automated daily database backups, software upgrades, and ongoing technical support.',
    },
    {
        q: 'Do I need to buy proprietary POS hardware from you?',
        a: 'No. Our point of sale is completely hardware-agnostic. You can run it on your existing Windows PC, Mac, laptop, or Android tablet, and connect standard USB or Bluetooth thermal receipt printers, barcode scanners, and cash drawers without proprietary lock-in.',
    },
    {
        q: 'What happens if our internet connection goes down?',
        a: 'Your store continues ringing up sales without interruption! Our client-side Dexie IndexedDB engine stores transactions securely offline and automatically syncs all data to your central database once connection is restored.',
    },
    {
        q: 'Can we upgrade our tier later as we open more branches?',
        a: 'Yes, absolutely. You can start on Starter or Bronze and upgrade to Silver, Gold, or Enterprise as your branch footprint expands with zero data loss or downtime.',
    },
]
</script>

<template>
    <Head title="Pricing Plans &amp; Investment — Enterprise Cloud POS" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors selection:bg-violet-500 selection:text-white">
        <!-- Reusable Navigation -->
        <Navbar
            :company-name="companyName"
            :app-tagline="appTagline"
            :logo-url="logoUrl"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="true"
        />

        <main class="pt-28 pb-20">
            <!-- ── HERO SECTION ───────────────────────────────────────────── -->
            <section class="relative px-4 sm:px-6 lg:px-8 py-16 text-center overflow-hidden">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[800px] h-[450px] rounded-full bg-gradient-to-tr from-violet-600/15 via-purple-600/10 to-amber-500/10 blur-[130px]"></div>
                </div>

                <div class="relative z-10 max-w-4xl mx-auto space-y-6">
                    <!-- Breadcrumb -->
                    <div class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <Link :href="route('home')" class="hover:text-violet-600">Home</Link>
                        <span>/</span>
                        <span class="text-violet-600 dark:text-violet-400 font-bold">Pricing Plans</span>
                    </div>

                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-violet-100 dark:bg-violet-950/80 border border-violet-300 dark:border-violet-700/60 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                        Transparent Platform Investment
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
                        {{ pricing?.settings?.pricing_intro_heading || 'Smart Retail Management for Growing Businesses' }}
                    </h1>

                    <p class="text-slate-600 dark:text-slate-300 text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
                        {{ pricing?.settings?.pricing_intro_text || 'Manage sales, inventory, staff, customers and multiple branches from anywhere. Transparent pricing designed to help you start easily and scale confidently without surprise fees.' }}
                    </p>
                </div>
            </section>

            <!-- ── VALUE STRIP BANNER ────────────────────────────────────── -->
            <div
                v-if="pricing?.value_props && pricing.value_props.length"
                class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-16"
            >
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div
                        v-for="(vp, vpIdx) in pricing.value_props"
                        :key="vpIdx"
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/80 space-y-1"
                    >
                        <div class="text-xs font-black tracking-wider uppercase text-transparent bg-clip-text bg-gradient-to-r from-violet-600 to-indigo-600 dark:from-violet-400 dark:to-indigo-400">
                            {{ vp.title }}
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-snug font-medium">
                            {{ vp.subtext }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── WHAT EVERY PLAN INCLUDES ──────────────────────────────── -->
            <div
                v-if="pricing?.included_items && pricing.included_items.length"
                class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mb-16"
            >
                <div class="p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-violet-900/10 via-purple-900/5 to-indigo-900/10 border border-violet-200 dark:border-violet-800/60 shadow-xs">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-xl">✨</span>
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
                </div>
            </div>

            <!-- ── PLAN CARDS: USER-FRIENDLY SPACIOUS LAYOUT ────────────────── -->
            <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-20 space-y-16">
                <!-- Group 1: Core Retail & Multi-Branch Plans (Starter, Bronze, Silver [Most Popular & Comprehensive]) -->
                <div class="space-y-6">
                    <div class="text-center space-y-2 max-w-2xl mx-auto">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-violet-100 dark:bg-violet-950/70 border border-violet-200 dark:border-violet-800 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                            Single &amp; Multi-Store Outlets
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                            Standard Retail Plans
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
                        <div
                            v-for="plan in retailPlans"
                            :key="plan.id"
                            :class="[
                                'group relative rounded-3xl p-7 sm:p-8 flex flex-col justify-between transition-all duration-300',
                                plan.is_highlighted
                                    ? 'bg-gradient-to-b from-amber-500/[0.09] via-amber-500/[0.03] to-white dark:to-slate-900 border-2 border-amber-400 dark:border-amber-500 shadow-2xl shadow-amber-500/20 ring-4 ring-amber-400/20 scale-[1.02] z-10'
                                    : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-xl hover:border-violet-400 dark:hover:border-violet-500/80 hover:-translate-y-1.5'
                            ]"
                        >
                            <!-- Most Popular & Comprehensive Badge (Silver) -->
                            <div
                                v-if="plan.is_highlighted"
                                class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1.5 rounded-full bg-gradient-to-r from-amber-500 via-orange-500 to-amber-500 text-slate-950 font-black text-[11px] tracking-wider uppercase shadow-lg shadow-amber-500/30 flex items-center gap-1.5 whitespace-nowrap"
                            >
                                <span>⭐ Most Popular &amp; Comprehensive</span>
                            </div>

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
                                            <span :class="['font-bold text-xs mt-0.5 shrink-0', plan.is_highlighted ? 'text-amber-500' : 'text-emerald-500']">✓</span>
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
                </div>

                <!-- Group 2: High-Volume Chains & Enterprise Conglomerates (Gold, Enterprise) -->
                <div v-if="enterprisePlans.length" class="pt-10 border-t border-slate-200 dark:border-slate-800/80 space-y-8">
                    <div class="text-center space-y-2 max-w-2xl mx-auto">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-violet-100 dark:bg-violet-950/70 border border-violet-200 dark:border-violet-800 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                            Multi-Warehouse &amp; Enterprise Conglomerates
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                            High-Volume Chains &amp; Custom Deployments
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                            Engineered for supermarket chains, regional distributors, and conglomerates requiring warehouse logistics, ERP integration, and dedicated technical account managers.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl mx-auto items-stretch">
                        <div
                            v-for="plan in enterprisePlans"
                            :key="plan.id"
                            class="group relative rounded-3xl p-7 sm:p-8 flex flex-col justify-between transition-all duration-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-xl hover:border-violet-400 dark:hover:border-violet-500/80 hover:-translate-y-1.5"
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
            </section>

            <!-- ── ADD-ONS & COMMERCIAL NOTES ────────────────────────────── -->
            <section
                v-if="pricing?.settings?.addons_content || pricing?.settings?.commercial_notes_content"
                class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-20"
            >
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div
                        v-if="pricing?.settings?.addons_content"
                        class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">🧩</span>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ pricing?.settings?.addons_heading || 'Optional Add-Ons & Expansions' }}
                            </h3>
                        </div>
                        <div
                            class="pricing-markdown-content text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed"
                            v-html="renderMarkdown(pricing.settings.addons_content)"
                        ></div>
                    </div>

                    <div
                        v-if="pricing?.settings?.commercial_notes_content"
                        class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">📋</span>
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
            </section>

            <!-- ── PRICING FAQ ACCORDION ─────────────────────────────────── -->
            <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mb-20 space-y-8">
                <div class="text-center space-y-2">
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        Pricing Questions &amp; Clarifications
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">Everything you need to know about billing, renewals, and hardware compatibility.</p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(faq, fIdx) in pricingFaqs"
                        :key="fIdx"
                        class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2"
                    >
                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">{{ faq.q }}</h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ faq.a }}</p>
                    </div>
                </div>
            </section>

            <!-- ── CLOSING CTA BANNER ────────────────────────────────────── -->
            <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="p-8 sm:p-12 rounded-3xl bg-gradient-to-r from-violet-900 via-purple-900 to-indigo-900 border border-violet-500/40 text-white text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-8 shadow-2xl">
                    <div class="space-y-2">
                        <h3 class="text-2xl sm:text-3xl font-black text-white">
                            Ready to transform your retail operations?
                        </h3>
                        <p class="text-xs sm:text-sm text-violet-200 max-w-xl">
                            Connect with our technical architects for free database migration, onboarding support, and custom multi-branch quotation.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="posUrl"
                            class="px-6 py-3.5 rounded-xl bg-white text-violet-950 font-black text-xs hover:bg-slate-100 transition shadow-lg"
                        >
                            Launch POS Terminal
                        </Link>
                        <a
                            :href="whatsappUrl"
                            target="_blank"
                            rel="noopener"
                            class="px-6 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition shadow-lg flex items-center gap-1.5"
                        >
                            <span>💬 Talk on WhatsApp</span>
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <!-- Reusable Footer -->
        <Footer
            :company-name="companyName"
            :support-phone="contact_info.phone"
            :support-email="contact_info.email"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="true"
        />
    </div>
</template>

<style scoped>
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
