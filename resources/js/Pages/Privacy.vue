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
    const msg = encodeURIComponent(`Hello ${companyName.value}, I have an inquiry regarding your Privacy Policy and Data Protection.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const lastUpdated = 'September 2026'

const activeSection = ref('section-1')

const sections = [
    { id: 'section-1', title: '1. Privacy Commitment & Data Sovereignty' },
    { id: 'section-2', title: '2. Types of Information We Collect' },
    { id: 'section-3', title: '3. End-Customer & Transaction Data' },
    { id: 'section-4', title: '4. AI Privacy & Zero-Training Guarantee' },
    { id: 'section-5', title: '5. Offline Storage & Local Browser Sandbox' },
    { id: 'section-6', title: '6. How We Use Business Data' },
    { id: 'section-7', title: '7. Information Sharing & Third Parties' },
    { id: 'section-8', title: '8. Data Retention, Portability & Deletion' },
    { id: 'section-9', title: '9. Security & Encryption Standards' },
    { id: 'section-10', title: '10. Compliance (NDPR / GDPR Principles)' },
    { id: 'section-11', title: '11. Cookies & Web Storage' },
    { id: 'section-12', title: '12. Contact Our Data Protection Officer' },
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
    <Head :title="`Privacy Policy — ${companyName}`">
        <meta name="description" :content="`Learn how ${companyName} protects your business, retail inventory, checkout records, and customer privacy with zero AI training on merchant data.`" />
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
                <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-gradient-to-r from-emerald-600/30 to-teal-500/20 blur-3xl rounded-full"></div>
            </div>

            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800/70 text-emerald-700 dark:text-emerald-300 text-xs font-bold tracking-wide uppercase mb-4">
                    <span>🛡️</span>
                    <span>Data Protection &amp; Confidentiality</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                    Privacy Policy
                </h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    At {{ companyName }}, merchant trust and transaction confidentiality are our foundational principles. We do not sell your sales records, monetize customer databases, or train external AI models with your business data.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>📅</span>
                        <span>Last Updated: {{ lastUpdated }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>🔒</span>
                        <span>Zero AI Training Guarantee</span>
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
                            Policy Navigation
                        </div>
                        <nav class="space-y-1 max-h-[calc(100vh-220px)] overflow-y-auto pr-1">
                            <button
                                v-for="sec in sections"
                                :key="sec.id"
                                @click="scrollToSection(sec.id)"
                                :class="[
                                    'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-150 flex items-center justify-between',
                                    activeSection === sec.id
                                        ? 'bg-emerald-600 text-white shadow-sm'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                            >
                                <span class="truncate">{{ sec.title }}</span>
                                <span v-if="activeSection === sec.id">→</span>
                            </button>
                        </nav>
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 space-y-1">
                            <div>Data Protection Desk:</div>
                            <a :href="'mailto:' + supportEmail" class="text-emerald-600 dark:text-emerald-400 font-medium hover:underline">{{ supportEmail }}</a>
                        </div>
                    </div>
                </aside>

                <!-- Document Body -->
                <div class="flex-1 min-w-0 bg-white dark:bg-slate-900 p-6 sm:p-10 lg:p-12 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm text-sm sm:text-base leading-relaxed space-y-10">
                    
                    <!-- 1 -->
                    <section id="section-1" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>1.</span>
                            <span>Privacy Commitment &amp; Data Sovereignty</span>
                        </h2>
                        <p>
                            <strong>{{ companyName }}</strong> recognizes that point-of-sale data represents the lifeblood of your commercial enterprise. Our privacy framework operates under the core tenet of <strong>complete data sovereignty</strong>: you retain exclusive ownership of all items, stock levels, sales transactions, supplier cost prices, cashier logs, and customer registries managed on the platform.
                        </p>
                        <p>
                            We act solely as an encrypted custodian and technical data processor. We will never sell, lease, commercialize, or share your proprietary retail metrics with advertisers, data brokers, or competitor businesses.
                        </p>
                    </section>

                    <!-- 2 -->
                    <section id="section-2" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>2.</span>
                            <span>Types of Information We Collect</span>
                        </h2>
                        <p>To operate our enterprise cloud and offline POS architecture, we collect the following categories of information:</p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Account &amp; Onboarding Data:</strong> Business name, tax ID (if supplied), branch physical addresses, manager email addresses, phone contacts, and login credentials.</li>
                            <li><strong>Product &amp; Inventory Telemetry:</strong> Barcodes, SKUs, item categories, cost and selling prices, serial/IMEI numbers, batch numbers, and stock movement transfers.</li>
                            <li><strong>Checkout Records:</strong> Transaction timestamps, line items, payment tender modes (cash, card POS terminal, bank transfer, split payments), receipt numbers, and discount approvals.</li>
                            <li><strong>Device &amp; Session Diagnostics:</strong> IP addresses, browser version, screen dimensions, network connectivity status, and offline synchronization queue metrics.</li>
                        </ul>
                    </section>

                    <!-- 3 -->
                    <section id="section-3" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>3.</span>
                            <span>End-Customer &amp; Transaction Data</span>
                        </h2>
                        <p>
                            When cashiers register retail customers during checkout (e.g. for e-receipt delivery, customer store credit, loyalty points, or wholesale invoicing), {{ companyName }} securely processes their name, phone number, and transaction history on behalf of the merchant.
                        </p>
                        <p>
                            We do not contact your shoppers directly, market promotional products to them, or build global tracking consumer profiles across multiple independent merchants. Your customer list belongs entirely to your business.
                        </p>
                    </section>

                    <!-- 4 -->
                    <section id="section-4" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>4.</span>
                            <span>AI Privacy &amp; Zero-Training Guarantee</span>
                        </h2>
                        <div class="p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 text-emerald-900 dark:text-emerald-200 text-xs leading-relaxed space-y-2">
                            <div class="font-bold flex items-center gap-1.5 text-sm">
                                <span>🛡️</span>
                                <span>Zero-Data-Training Guarantee for AI</span>
                            </div>
                            <p>
                                {{ companyName }} includes conversational AI assistant capabilities allowing managers to ask questions such as <em>"What were my top five best-selling items this weekend?"</em> or <em>"Summarize branch expenses for this month."</em>
                            </p>
                            <p>
                                <strong>We strictly guarantee that your sales figures, inventory prices, customer receipts, and financial margins are NEVER transmitted into public AI models for model training, reinforcement learning, or external fine-tuning.</strong>
                            </p>
                        </div>
                        <p class="mt-3">
                            The assistant translates user queries into isolated, read-only SQL aggregations against your private tenant database. Calculations occur entirely within your authenticated perimeter.
                        </p>
                    </section>

                    <!-- 5 -->
                    <section id="section-5" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>5.</span>
                            <span>Offline Storage &amp; Local Browser Sandbox</span>
                        </h2>
                        <p>
                            To ensure high-speed retail checkout without internet dependency, {{ companyName }} stores product catalogs and pending offline sales inside the browser's local sandbox via <strong>IndexedDB (Dexie.js)</strong>.
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>Data stored in IndexedDB is domain-scoped and accessible only to {{ companyName }} on that particular physical machine.</li>
                            <li>Pending offline sales are synchronized using idempotent cryptographic mutation IDs to prevent double-charging or duplicate inventory decrements.</li>
                            <li>Merchants retain control over local cached databases and can inspect offline queues directly via the POS diagnostic drawer.</li>
                        </ul>
                    </section>

                    <!-- 6 -->
                    <section id="section-6" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>6.</span>
                            <span>How We Use Business Data</span>
                        </h2>
                        <p>Information collected is utilized strictly for legitimate business purposes:</p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>Rendering your multi-branch dashboard, inventory reports, and financial sales analytics.</li>
                            <li>Processing real-time stock transfers and batch expiration notifications.</li>
                            <li>Verifying cashier authentication and auditing sensitive actions.</li>
                            <li>Delivering essential billing invoices and security incident advisories.</li>
                            <li>Diagnosing synchronization exceptions and server performance bottlenecks.</li>
                        </ul>
                    </section>

                    <!-- 7 -->
                    <section id="section-7" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>7.</span>
                            <span>Information Sharing &amp; Third Parties</span>
                        </h2>
                        <p>
                            We do not sell personal or business information. We share minimal data exclusively with trusted technical infrastructure sub-processors under strict confidentiality agreements:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Cloud Hosting:</strong> Enterprise datacenter providers offering encrypted storage and 99.9% uptime SLAs.</li>
                            <li><strong>Email &amp; SMS Dispatch:</strong> Transactional email gateways (SMTP) used solely to send e-receipts and password reset links requested by your staff or shoppers.</li>
                            <li><strong>Legal Obligations:</strong> We may disclose information only if strictly compelled by a valid court order or statutory requirement under Nigerian law, with prompt advance notice to the merchant where legally permissible.</li>
                        </ul>
                    </section>

                    <!-- 8 -->
                    <section id="section-8" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>8.</span>
                            <span>Data Retention, Portability &amp; Deletion</span>
                        </h2>
                        <p>
                            We retain your retail data for as long as your subscription remains active. If you choose to close your account:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Export Rights:</strong> You may export comprehensive sales histories, customer ledgers, and inventory databases in standard CSV/Excel format prior to account termination.</li>
                            <li><strong>Grace Period:</strong> Data is retained in an inactive state for 30 calendar days to accommodate accidental cancellations or reactivations.</li>
                            <li><strong>Permanent Purge:</strong> Following the grace period, all database records, cached indexes, and branch logs are permanently deleted from active production servers.</li>
                        </ul>
                    </section>

                    <!-- 9 -->
                    <section id="section-9" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>9.</span>
                            <span>Security &amp; Encryption Standards</span>
                        </h2>
                        <p>
                            We apply stringent defense-in-depth safeguards:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Transport Layer Security:</strong> All data transmitted between local checkout registers and cloud endpoints is encrypted using TLS 1.3 / HTTPS.</li>
                            <li><strong>Password Hashing:</strong> User passwords and cash-drawer PINs are hashed using bcrypt with salt rounds meeting high cryptographic thresholds.</li>
                            <li><strong>Spatie RBAC:</strong> Cashiers cannot view supplier purchase costs or modify product retail prices unless explicitly granted granular permissions by management.</li>
                        </ul>
                    </section>

                    <!-- 10 -->
                    <section id="section-10" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>10.</span>
                            <span>Compliance (NDPR / GDPR Principles)</span>
                        </h2>
                        <p>
                            {{ companyName }} complies with the Nigeria Data Protection Act (NDPA) and follows internationally recognized principles under the General Data Protection Regulation (GDPR), including lawfulness, transparency, purpose limitation, data minimization, accuracy, and storage limitation.
                        </p>
                    </section>

                    <!-- 11 -->
                    <section id="section-11" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>11.</span>
                            <span>Cookies &amp; Web Storage</span>
                        </h2>
                        <p>
                            We utilize essential session cookies and local storage tokens strictly necessary to maintain cashier logins, preserve active theme preferences (Light/Dark mode), and manage offline synchronization queues. We do not deploy third-party advertising cookies or cross-site tracking pixels on POS terminals.
                        </p>
                    </section>

                    <!-- 12 -->
                    <section id="section-12" class="scroll-mt-28 space-y-3">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>12.</span>
                            <span>Contact Our Data Protection Officer</span>
                        </h2>
                        <p>
                            If you have questions regarding this Privacy Policy, wish to exercise data subject rights, or request an audit report, please reach out directly:
                        </p>
                        <div class="mt-4 p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 space-y-2">
                            <div class="font-bold text-slate-900 dark:text-white">{{ companyName }} Privacy &amp; Data Governance Office</div>
                            <div class="text-slate-600 dark:text-slate-300">📍 Headquarters: {{ businessAddress }}</div>
                            <div class="text-slate-600 dark:text-slate-300">📞 Phone: <a :href="'tel:' + supportPhone" class="text-emerald-600 dark:text-emerald-400 font-mono">{{ supportPhone }}</a></div>
                            <div class="text-slate-600 dark:text-slate-300">✉️ Privacy Email: <a :href="'mailto:' + supportEmail" class="text-emerald-600 dark:text-emerald-400 font-mono">{{ supportEmail }}</a></div>
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
