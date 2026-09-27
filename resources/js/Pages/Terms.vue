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
    const msg = encodeURIComponent(`Hello ${companyName.value}, I have an inquiry regarding your Terms and Conditions.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const lastUpdated = 'September 2026'

const activeSection = ref('section-1')

const sections = [
    { id: 'section-1', title: '1. Master Subscription Agreement' },
    { id: 'section-2', title: '2. Software License & Multi-Branch Access' },
    { id: 'section-3', title: '3. Offline Architecture & Local Sync' },
    { id: 'section-4', title: '4. User Accounts, Roles & Terminal Security' },
    { id: 'section-5', title: '5. Merchant Data Sovereignty & Customer Data' },
    { id: 'section-6', title: '6. Hardware & Peripheral Compatibility' },
    { id: 'section-7', title: '7. AI Assistant & Conversational Analytics' },
    { id: 'section-8', title: '8. Subscriptions, Payments & Taxes' },
    { id: 'section-9', title: '9. SLA, System Availability & Backups' },
    { id: 'section-10', title: '10. Limitation of Liability' },
    { id: 'section-11', title: '11. Termination & Account Suspension' },
    { id: 'section-12', title: '12. Governing Law & Contact Details' },
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
    <Head :title="`Terms & Conditions — ${companyName}`">
        <meta name="description" :content="`Read the Terms and Conditions for ${companyName}. Understand our SaaS subscription agreement, offline-sync protocols, data sovereignty, and terminal usage.`" />
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
                <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-gradient-to-r from-violet-600/30 to-indigo-500/20 blur-3xl rounded-full"></div>
            </div>

            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-violet-50 dark:bg-violet-950/70 border border-violet-200 dark:border-violet-800/70 text-violet-700 dark:text-violet-300 text-xs font-bold tracking-wide uppercase mb-4">
                    <span>📜</span>
                    <span>Legal Agreement</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                    Terms &amp; Conditions
                </h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    Please read these terms carefully before accessing or deploying {{ companyName }}. By using our cloud and offline point of sale services, you agree to these legal obligations.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>📅</span>
                        <span>Effective Date: {{ lastUpdated }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>🏢</span>
                        <span>Entity: {{ companyName }}</span>
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
                            Table of Contents
                        </div>
                        <nav class="space-y-1 max-h-[calc(100vh-220px)] overflow-y-auto pr-1">
                            <button
                                v-for="sec in sections"
                                :key="sec.id"
                                @click="scrollToSection(sec.id)"
                                :class="[
                                    'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-150 flex items-center justify-between',
                                    activeSection === sec.id
                                        ? 'bg-violet-600 text-white shadow-sm'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"
                            >
                                <span class="truncate">{{ sec.title }}</span>
                                <span v-if="activeSection === sec.id">→</span>
                            </button>
                        </nav>
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 space-y-1">
                            <div>Questions? Contact:</div>
                            <a :href="'mailto:' + supportEmail" class="text-violet-600 dark:text-violet-400 font-medium hover:underline">{{ supportEmail }}</a>
                        </div>
                    </div>
                </aside>

                <!-- Document Body -->
                <div class="flex-1 min-w-0 bg-white dark:bg-slate-900 p-6 sm:p-10 lg:p-12 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm text-sm sm:text-base leading-relaxed space-y-10">
                    
                    <!-- 1 -->
                    <section id="section-1" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>1.</span>
                            <span>Master Subscription Agreement</span>
                        </h2>
                        <p>
                            This Master Subscription Agreement ("Agreement") constitutes a binding legal contract between <strong>{{ companyName }}</strong> ("we", "us", or "our") and the business entity, individual, or merchant ("Client", "Subscriber", or "Merchant") accessing or deploying the {{ companyName }} suite of software, cloud backend, web applications, and offline checkout engines (collectively, the "Platform").
                        </p>
                        <p>
                            By provisioning a merchant tenant, activating a retail branch, creating staff credentials, or operating the point of sale register, you certify that you have the full legal capacity and corporate authority to bind your organization to these Terms. If you do not accept these Terms without reservation, you must immediately cease all access and software usage.
                        </p>
                    </section>

                    <!-- 2 -->
                    <section id="section-2" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>2.</span>
                            <span>Software License &amp; Multi-Branch Access</span>
                        </h2>
                        <p>
                            Subject to timely subscription payment and compliance with this Agreement, {{ companyName }} grants you a non-exclusive, non-transferable, revocable license to access and run the platform across your authorized physical and virtual retail branches.
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Branch Quotas:</strong> Operating terminals and branches must stay within the branch allowance granted by your designated pricing plan tier.</li>
                            <li><strong>Tenant Isolation:</strong> Merchant data, sales records, customer lists, and branch inventories are strictly partitioned. You may not attempt to circumvent multi-tenant isolation or probe other clients' databases.</li>
                            <li><strong>License Restrictions:</strong> You shall not reverse engineer, decompile, redistribute, sublicense, or create derivative works of any source code, offline synchronization algorithms, or AI translation pipelines provided by {{ companyName }}.</li>
                        </ul>
                    </section>

                    <!-- 3 -->
                    <section id="section-3" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>3.</span>
                            <span>Offline Architecture &amp; Local Synchronization</span>
                        </h2>
                        <p>
                            {{ companyName }} features proprietary offline-first architecture powered by client-side browser storage (IndexedDB via Dexie.js). Cashiers may ring up sales, issue receipts, and hold transactions during complete internet disruptions.
                        </p>
                        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 text-amber-900 dark:text-amber-200 text-xs leading-relaxed space-y-2">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>⚠️</span>
                                <span>Local Storage Custody Notice</span>
                            </div>
                            <p>
                                When ringing sales in offline mode, receipt records are cached locally on that specific device and browser profile until an active connection is restored. Merchants must instruct staff <strong>not to clear browser site data, cookies, or IndexedDB caches</strong> until offline queues have successfully synced with the cloud server. {{ companyName }} is not responsible for data loss caused by unauthorized clearing of local browser caches prior to server synchronization.
                            </p>
                        </div>
                    </section>

                    <!-- 4 -->
                    <section id="section-4" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>4.</span>
                            <span>User Accounts, Roles &amp; Terminal Security</span>
                        </h2>
                        <p>
                            Access to {{ companyName }} is governed by Role-Based Access Control (RBAC). Super Admins, Branch Managers, Cashiers, and Inventory Clerks receive isolated permission boundaries.
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Credential Secrecy:</strong> Each staff member must maintain individual credentials or PIN codes. Sharing administrative master passwords across multiple staff members violates acceptable use.</li>
                            <li><strong>Audit Logs:</strong> The system logs all sensitive actions (price modifications, discounts, held sales returns, inventory stock transfers, and refunds). Audit logs cannot be selectively purged by branch-level staff.</li>
                            <li><strong>Terminal Session Timeout:</strong> Registers should be locked when left unattended to prevent unauthorized transactions.</li>
                        </ul>
                    </section>

                    <!-- 5 -->
                    <section id="section-5" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>5.</span>
                            <span>Merchant Data Sovereignty &amp; Customer Data</span>
                        </h2>
                        <p>
                            You retain 100% full legal ownership of your business data, product inventories, sales metrics, and customer contact records stored within {{ companyName }}.
                        </p>
                        <p>
                            {{ companyName }} acts solely as a data processor on your behalf. We will never sell, lease, or monetize your transaction histories or customer lists to any third-party advertisers or competing retailers. You may request a full export of your business data at any time in standard formats (CSV/Excel/JSON).
                        </p>
                    </section>

                    <!-- 6 -->
                    <section id="section-6" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>6.</span>
                            <span>Hardware &amp; Peripheral Compatibility</span>
                        </h2>
                        <p>
                            {{ companyName }} is engineered as an open, hardware-agnostic platform compatible with standard retail peripherals:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>58mm &amp; 80mm ESC/POS thermal receipt printers (USB, Network LAN, Bluetooth).</li>
                            <li>1D/2D USB and wireless handheld barcode scanners (HID keyboard emulation).</li>
                            <li>Standard RJ11/RJ12 drawer trigger interfaces connected via receipt printers.</li>
                            <li>Touchscreen POS terminals, Windows PCs, Macs, iPads, and Android tablets running modern web browsers.</li>
                        </ul>
                        <p class="text-xs text-slate-500">
                            * Note: Peripherals are manufactured by independent hardware vendors. While our software supports industry-standard protocols, {{ companyName }} does not warrant third-party device firmware failures, physical wear, or damaged USB cabling.
                        </p>
                    </section>

                    <!-- 7 -->
                    <section id="section-7" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>7.</span>
                            <span>AI Assistant &amp; Conversational Analytics</span>
                        </h2>
                        <p>
                            Our platform integrates intelligent business query translation and conversational assistants (e.g. branch revenue breakdowns, dead stock alerts, and expense analysis).
                        </p>
                        <div class="p-4 rounded-2xl bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-800/50 text-slate-700 dark:text-slate-300 text-xs leading-relaxed space-y-2">
                            <div class="font-bold text-violet-900 dark:text-violet-200 flex items-center gap-1.5">
                                <span>🤖</span>
                                <span>Zero-Model-Training Guarantee</span>
                            </div>
                            <p>
                                Your natural language queries are converted into strictly scoped database aggregations. <strong>Neither your inventory records, pricing margins, nor sales receipts are ever transmitted to train public AI foundational models</strong>. All AI telemetry is strictly confined to your organization's authenticated session.
                            </p>
                        </div>
                    </section>

                    <!-- 8 -->
                    <section id="section-8" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>8.</span>
                            <span>Subscriptions, Payments &amp; Taxes</span>
                        </h2>
                        <p>
                            Access to {{ companyName }} is billed according to your selected plan (Monthly or Annually). Pricing is denominated in your branch's primary operating currency unless stated otherwise.
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Timely Invoicing:</strong> Subscriptions renew automatically at the end of each billing cycle unless cancelled prior to renewal.</li>
                            <li><strong>Applicable Taxes:</strong> Unless explicitly noted, plan fees exclude regional sales taxes, value-added taxes (VAT), or withholding charges mandated by your local jurisdiction.</li>
                            <li><strong>Price Modifications:</strong> {{ companyName }} reserves the right to modify subscription fees with at least 30 calendar days of advance written notice to account administrators.</li>
                        </ul>
                    </section>

                    <!-- 9 -->
                    <section id="section-9" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>9.</span>
                            <span>Service Level Agreement (SLA) &amp; Backups</span>
                        </h2>
                        <p>
                            We maintain high-availability cloud infrastructure with a targeted 99.9% uptime for cloud synchronization and administrative reporting endpoints. Because the POS registers operate offline, front-counter checkout can continue uninterrupted even in the event of upstream telecommunication disruptions.
                        </p>
                        <p>
                            Automated daily encrypted database backups are taken across our redundant cloud servers to prevent catastrophic data loss.
                        </p>
                    </section>

                    <!-- 10 -->
                    <section id="section-10" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>10.</span>
                            <span>Limitation of Liability</span>
                        </h2>
                        <p>
                            To the maximum extent permitted by applicable law, in no event shall <strong>{{ companyName }}</strong>, its directors, developers, or affiliates be liable for indirect, punitive, incidental, special, or consequential damages, including loss of profits, retail sales downtime, or data corruption arising from power failures, telecommunication carrier blackouts, or unauthorized physical tampering with local checkout hardware.
                        </p>
                        <p>
                            Our total cumulative liability under this Agreement shall not exceed the total subscription fees paid by you to {{ companyName }} during the three (3) months preceding the incident giving rise to liability.
                        </p>
                    </section>

                    <!-- 11 -->
                    <section id="section-11" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>11.</span>
                            <span>Termination &amp; Account Suspension</span>
                        </h2>
                        <p>
                            Either party may terminate a subscription by providing written notification or initiating cancellation from the merchant account portal prior to the start of the subsequent billing cycle.
                        </p>
                        <p>
                            {{ companyName }} reserves the right to suspend or terminate accounts that engage in fraudulent payment transactions, malicious penetration testing of server endpoints, or non-payment of subscription dues beyond a 14-day grace period. Upon termination, merchants will have 30 days to export all sales history and inventory databases.
                        </p>
                    </section>

                    <!-- 12 -->
                    <section id="section-12" class="scroll-mt-28 space-y-3">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>12.</span>
                            <span>Governing Law &amp; Contact Details</span>
                        </h2>
                        <p>
                            This Agreement shall be interpreted and governed in accordance with the laws of the Federal Republic of Nigeria, without giving effect to any principles of conflicts of law.
                        </p>
                        <div class="mt-4 p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 space-y-2">
                            <div class="font-bold text-slate-900 dark:text-white">{{ companyName }} Legal &amp; Commercial Office</div>
                            <div class="text-slate-600 dark:text-slate-300">📍 Address: {{ businessAddress }}</div>
                            <div class="text-slate-600 dark:text-slate-300">📞 Phone: <a :href="'tel:' + supportPhone" class="text-violet-600 dark:text-violet-400 font-mono">{{ supportPhone }}</a></div>
                            <div class="text-slate-600 dark:text-slate-300">✉️ Legal Email: <a :href="'mailto:' + supportEmail" class="text-violet-600 dark:text-violet-400 font-mono">{{ supportEmail }}</a></div>
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
