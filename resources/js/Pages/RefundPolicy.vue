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
            company_short_name: 'Skynet',
            app_tagline: 'AI-Powered Cloud & Offline Point of Sale',
            address: 'Delta State, Nigeria',
        }),
    },
    pricing: {
        type: Object,
        default: null,
    },
})

const page = usePage()
const systemConfig     = computed(() => page.props.system_config || {})
const companyName      = computed(() => props.contact_info?.company_name || systemConfig.value.company_name || 'Skynet Digital POS')
const companyShortName = computed(() => props.contact_info?.company_short_name || systemConfig.value.company_short_name || 'Skynet')
const appTagline       = computed(() => props.contact_info?.app_tagline || systemConfig.value.app_tagline || 'AI-Powered Cloud & Offline Point of Sale')
const supportPhone     = computed(() => props.contact_info?.phone || systemConfig.value.support_phone || '+234 803 207 2831')
const supportEmail     = computed(() => props.contact_info?.email || systemConfig.value.support_email || 'support@skynetdigitalltd.com')
const businessAddress  = computed(() => props.contact_info?.address || 'Delta State, Nigeria')
const logoUrl          = computed(() => systemConfig.value.company_logo_url || null)

const defaultBranchSlug = computed(() => props.default_branch?.slug || 'felix-enterprise')
const posUrl = computed(() => route('pos.index', { branch: defaultBranchSlug.value }))
const pricingEnabled = computed(() => !!props.pricing?.enabled)

const whatsappUrl = computed(() => {
    const phone = props.contact_info?.clean_phone || '2348032072831'
    const msg = encodeURIComponent(`Hello ${companyName.value}, I have an inquiry regarding your Subscription Cancellation and Refund Policy.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const lastUpdated = 'September 2026'

const activeSection = ref('section-1')

const sections = [
    { id: 'section-1', title: '1. SaaS Digital Subscription Model' },
    { id: 'section-2', title: '2. 14-Day Onboarding & Trial Evaluation' },
    { id: 'section-3', title: '3. Annual Subscription Refund Terms' },
    { id: 'section-4', title: '4. Monthly Subscriptions & Cancellation' },
    { id: 'section-5', title: '5. Multi-Branch & Extra Terminal Add-Ons' },
    { id: 'section-6', title: '6. Hardware & 3rd-Party Peripherals' },
    { id: 'section-7', title: '7. Exceptional Outage Circumstances' },
    { id: 'section-8', title: '8. How to Submit a Refund Claim' },
]

function scrollToSection(id) {
    activeSection.value = id
    const el = document.getElementById(id)
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}
</script>

<template>
    <Head :title="`Refund & Cancellation Policy — ${companyName}`">
        <meta name="description" :content="`Understand the transparent refund, cancellation, and subscription terms for ${companyName} cloud and offline POS.`" />
    </Head>

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 font-sans antialiased selection:bg-violet-500 selection:text-white transition-colors duration-300">
        
        <!-- Navigation -->
        <Navbar
            :company-name="companyName"
            :app-tagline="appTagline"
            :logo-url="logoUrl"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingEnabled"
        />

        <!-- Page Header -->
        <header class="relative pt-32 pb-16 overflow-hidden border-b border-slate-200/80 dark:border-slate-800/80 bg-white/70 dark:bg-slate-900/60 backdrop-blur-md">
            <div class="absolute inset-0 -z-10 pointer-events-none opacity-40 dark:opacity-20">
                <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-gradient-to-r from-blue-600/30 to-indigo-500/20 blur-3xl rounded-full"></div>
            </div>

            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-800/70 text-blue-700 dark:text-blue-300 text-xs font-bold tracking-wide uppercase mb-4">
                    <span>💳</span>
                    <span>Commercial Billing Transparency</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                    Refund &amp; Cancellation Policy
                </h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    We stand behind the engineering of {{ companyName }}. Our refund and cancellation guidelines are structured to be transparent, fair, and merchant-centric.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>📅</span>
                        <span>Effective Date: {{ lastUpdated }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>⏱️</span>
                        <span>Resolution Time: 3–5 Business Days</span>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Content Area with Sticky Sidebar -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <div class="flex flex-col lg:flex-row items-start gap-8 lg:gap-12">
                
                <!-- Table of Contents Sidebar -->
                <aside class="hidden lg:block w-72 shrink-0">
                    <div class="sticky top-28 p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                        <div class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                            Policy Outline
                        </div>
                        <nav class="space-y-1 max-h-[calc(100vh-220px)] overflow-y-auto pr-1">
                            <button
                                v-for="sec in sections"
                                :key="sec.id"
                                @click="scrollToSection(sec.id)"
                                :class="[
                                    'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-150 flex items-center justify-between',
                                    activeSection === sec.id
                                        ? 'bg-blue-600 text-white shadow-sm'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                            >
                                <span class="truncate">{{ sec.title }}</span>
                                <span v-if="activeSection === sec.id">→</span>
                            </button>
                        </nav>
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 space-y-1">
                            <div>Billing &amp; Refund Inquiries:</div>
                            <a :href="'mailto:' + supportEmail" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">{{ supportEmail }}</a>
                        </div>
                    </div>
                </aside>

                <!-- Document Body -->
                <div class="flex-1 min-w-0 bg-white dark:bg-slate-900 p-6 sm:p-10 lg:p-12 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm text-sm sm:text-base leading-relaxed space-y-10">
                    
                    <!-- 1 -->
                    <section id="section-1" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>1.</span>
                            <span>SaaS Digital Subscription Model</span>
                        </h2>
                        <p>
                            <strong>{{ companyName }}</strong> is delivered as a Software-as-a-Service (SaaS) platform, combining multi-branch cloud infrastructure with local offline browser execution. Upon subscription confirmation, immediate automated provisioning of tenant resources, cloud databases, sync channels, and AI copilot services occurs.
                        </p>
                    </section>

                    <!-- 2 -->
                    <section id="section-2" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>2.</span>
                            <span>14-Day Onboarding &amp; Trial Evaluation</span>
                        </h2>
                        <p>
                            We encourage all merchants to thoroughly test {{ companyName }} with our live demo register and sandbox data prior to or during the initial onboarding window.
                        </p>
                        <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/50 text-blue-900 dark:text-blue-200 text-xs leading-relaxed space-y-2">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>🛡️</span>
                                <span>First-Time Subscriber Guarantee</span>
                            </div>
                            <p>
                                If within the first <strong>14 calendar days</strong> of your initial new account subscription you determine that {{ companyName }} does not fit your operational workflows, you may submit a cancellation request. We will issue a <strong>100% full refund</strong> of the subscription fee paid, no questions asked.
                            </p>
                        </div>
                    </section>

                    <!-- 3 -->
                    <section id="section-3" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>3.</span>
                            <span>Annual Subscription Refund Terms</span>
                        </h2>
                        <p>
                            Merchants who commit to annual subscription plans receive significant upfront discounts (up to two months free).
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Within 14 Days:</strong> 100% full refund.</li>
                            <li><strong>Between 15 and 60 Days:</strong> Pro-rated refund calculated on the remaining unutilized full months, minus the standard non-discounted monthly fee for months elapsed.</li>
                            <li><strong>Beyond 60 Days:</strong> Annual subscriptions become non-refundable due to reserved server allocations and committed cloud tenant capacity. However, you retain full operational access through the completion of your paid annual cycle.</li>
                        </ul>
                    </section>

                    <!-- 4 -->
                    <section id="section-4" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>4.</span>
                            <span>Monthly Subscriptions &amp; Cancellation</span>
                        </h2>
                        <p>
                            Monthly subscriptions provide ongoing operational flexibility. You may cancel your monthly plan at any time through the administration billing portal or by emailing our billing desk.
                        </p>
                        <p>
                            Cancellations take effect at the conclusion of the current monthly billing period. Because cloud services remain active and available throughout the current cycle, partial mid-month refunds are not issued for monthly plans once the 14-day initial window has elapsed.
                        </p>
                    </section>

                    <!-- 5 -->
                    <section id="section-5" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>5.</span>
                            <span>Multi-Branch &amp; Extra Terminal Add-Ons</span>
                        </h2>
                        <p>
                            Additional branch licenses or high-volume terminal add-on packs added midway through a billing cycle are co-termed with your base subscription. Add-on fees are non-refundable once activated, but may be downgraded or removed prior to the subsequent renewal invoice.
                        </p>
                    </section>

                    <!-- 6 -->
                    <section id="section-6" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>6.</span>
                            <span>Hardware &amp; 3rd-Party Peripherals</span>
                        </h2>
                        <p>
                            {{ companyName }} is a software platform designed to integrate with standard commercial POS hardware (thermal receipt printers, handheld USB/Bluetooth barcode scanners, cash drawers, and barcode label printers).
                        </p>
                        <p class="text-xs text-slate-500">
                            * Notice: Any physical hardware peripherals purchased through independent third-party distributors or electronics suppliers are subject to that manufacturer's return policy and warranty. {{ companyName }} does not accept hardware returns or issue hardware cash refunds for equipment purchased from outside vendors.
                        </p>
                    </section>

                    <!-- 7 -->
                    <section id="section-7" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>7.</span>
                            <span>Exceptional Outage Circumstances</span>
                        </h2>
                        <p>
                            In the unlikely event of an extended upstream cloud synchronization outage attributable directly to our infrastructure exceeding our 99.9% SLA commitment (more than 4.3 hours of unscheduled cloud downtime in a calendar month), affected merchants are eligible to receive a pro-rated service credit or invoice deduction upon application.
                        </p>
                    </section>

                    <!-- 8 -->
                    <section id="section-8" class="scroll-mt-28 space-y-3">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>8.</span>
                            <span>How to Submit a Refund Claim</span>
                        </h2>
                        <p>
                            To submit a refund or cancellation claim, follow these steps:
                        </p>
                        <ol class="list-decimal pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>Send an email from your registered account owner address to <strong>{{ supportEmail }}</strong>.</li>
                            <li>Include your organization or branch name and invoice / payment reference number.</li>
                            <li>Briefly outline the reason for cancellation or refund request to help our team improve the software.</li>
                        </ol>
                        <p class="mt-3">
                            Our billing department will process your claim within <strong>3 to 5 business days</strong>. Approved refunds are credited directly back to the original funding payment method.
                        </p>

                        <div class="mt-4 p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 space-y-2">
                            <div class="font-bold text-slate-900 dark:text-white">{{ companyName }} Billing &amp; Merchant Accounts</div>
                            <div class="text-slate-600 dark:text-slate-300">📍 Office: {{ businessAddress }}</div>
                            <div class="text-slate-600 dark:text-slate-300">📞 Phone Desk: <a :href="'tel:' + supportPhone" class="text-blue-600 dark:text-blue-400 font-mono">{{ supportPhone }}</a></div>
                            <div class="text-slate-600 dark:text-slate-300">✉️ Billing Email: <a :href="'mailto:' + supportEmail" class="text-blue-600 dark:text-blue-400 font-mono">{{ supportEmail }}</a></div>
                        </div>
                    </section>

                </div>

            </div>
        </main>

        <!-- Dynamic Footer -->
        <Footer
            :company-name="companyName"
            :support-phone="supportPhone"
            :support-email="supportEmail"
            :pos-url="posUrl"
            :whatsapp-url="whatsappUrl"
            :pricing-enabled="pricingEnabled"
        />

    </div>
</template>
