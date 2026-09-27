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
    const msg = encodeURIComponent(`Hello ${companyName.value}, I have an inquiry regarding your Security & Compliance standards.`)
    return `https://wa.me/${phone}?text=${msg}`
})

const lastUpdated = 'September 2026'

const activeSection = ref('section-1')

const sections = [
    { id: 'section-1', title: '1. Defense-in-Depth Architecture' },
    { id: 'section-2', title: '2. Offline Sandbox & IndexedDB Security' },
    { id: 'section-3', title: '3. Encryption in Transit & at Rest' },
    { id: 'section-4', title: '4. Granular RBAC & Permission Boundaries' },
    { id: 'section-5', title: '5. Tamper-Evident Audit Logging' },
    { id: 'section-6', title: '6. Zero-Loss Offline Sync Pipeline' },
    { id: 'section-7', title: '7. Automated Backups & Disaster Recovery' },
    { id: 'section-8', title: '8. Vulnerability Reporting & Disclosure' },
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
    <Head :title="`Security & Compliance — ${companyName}`">
        <meta name="description" :content="`Discover the enterprise-grade security architecture, offline encryption, and RBAC safeguards protecting ${companyName}.`" />
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
                <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-gradient-to-r from-violet-600/30 to-purple-500/20 blur-3xl rounded-full"></div>
            </div>

            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-violet-50 dark:bg-violet-950/70 border border-violet-200 dark:border-violet-800/70 text-violet-700 dark:text-violet-300 text-xs font-bold tracking-wide uppercase mb-4">
                    <span>🛡️</span>
                    <span>Enterprise Security Whitepaper</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                    Security &amp; Compliance
                </h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    How {{ companyName }} defends retail transactions, multi-branch inventory, and cashier registers with cryptographic safeguards, offline isolation, and immutable audit trails.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>🔒</span>
                        <span>TLS 1.3 / AES-256 Transport &amp; Storage</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full font-medium">
                        <span>⚡</span>
                        <span>Client Sandbox (IndexedDB / Dexie.js)</span>
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
                            Security Architecture
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
                            <div>Security Response Team:</div>
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
                            <span>Defense-in-Depth Architecture</span>
                        </h2>
                        <p>
                            At <strong>{{ companyName }}</strong>, security is built into every architectural layer rather than patched on as an afterthought. Our point-of-sale platform combines zero-trust perimeter controls, multi-tenant database partitioning, and client-side cryptographic isolation to safeguard enterprise merchants from checkout fraud, unauthorized inventory shrinkage, and unauthorized data leakage.
                        </p>
                    </section>

                    <!-- 2 -->
                    <section id="section-2" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>2.</span>
                            <span>Offline Sandbox &amp; IndexedDB Security</span>
                        </h2>
                        <p>
                            Unlike traditional cloud-only systems that crash when network connections drop, {{ companyName }} provides continuous checkout via an in-browser sandbox built with <strong>Dexie.js / IndexedDB</strong>:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Same-Origin Isolation:</strong> The local database is isolated by the browser's Same-Origin Policy. No other website or browser tab can read your offline sales queue.</li>
                            <li><strong>Sanitized Memory Cache:</strong> Sensitive payment gateway secrets or full credit card PANs are never written into browser storage.</li>
                            <li><strong>Integrity Signatures:</strong> Every offline sale is tagged with a client-generated UUID to ensure mutations cannot be modified or replayed maliciously.</li>
                        </ul>
                    </section>

                    <!-- 3 -->
                    <section id="section-3" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>3.</span>
                            <span>Encryption in Transit &amp; at Rest</span>
                        </h2>
                        <p>
                            All interactions between your physical POS registers, management portals, and cloud servers are protected by modern cryptographic standards:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 space-y-1.5">
                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>🌐</span>
                                    <span>In Transit: TLS 1.3</span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Enforced HTTPS with HSTS headers, elliptic curve Diffie-Hellman forward secrecy, and strict SSL ciphers.
                                </p>
                            </div>
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 space-y-1.5">
                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>🗄️</span>
                                    <span>At Rest: AES-256</span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Database volume encryption, encrypted automated backup archives, and bcrypt-salted user credentials.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- 4 -->
                    <section id="section-4" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>4.</span>
                            <span>Granular RBAC &amp; Permission Boundaries</span>
                        </h2>
                        <p>
                            {{ companyName }} employs strict <strong>Role-Based Access Control (RBAC)</strong> powered by Spatie permissions, scoping every API request to the authenticated branch and staff tier:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li><strong>Super Admin:</strong> Manages global system settings, company branding, plan tiers, and creates new branch entities.</li>
                            <li><strong>Branch Manager:</strong> Full visibility into branch financial profit margins, inventory valuation, stock adjustments, and staff management.</li>
                            <li><strong>Cashier:</strong> Restricted strictly to the sales register, receipt reprints, and holding sales. Cashiers cannot view cost margins, delete sales records, or modify master product catalogs.</li>
                        </ul>
                    </section>

                    <!-- 5 -->
                    <section id="section-5" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>5.</span>
                            <span>Tamper-Evident Audit Logging</span>
                        </h2>
                        <p>
                            Internal theft and inventory discrepancies are deterred through automated activity logging:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>Every sale discount, price override, line-item removal, and sale return is timestamped with the acting cashier's user ID and IP address.</li>
                            <li>Inter-branch stock transfers generate dual-confirmation receipts: dispatched items must be acknowledged by the receiving branch before inventory reconciles.</li>
                            <li>Audit log tables are append-only and cannot be cleared by branch-level administrators.</li>
                        </ul>
                    </section>

                    <!-- 6 -->
                    <section id="section-6" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>6.</span>
                            <span>Zero-Loss Offline Sync Pipeline</span>
                        </h2>
                        <p>
                            When connectivity is restored after an internet interruption, {{ companyName }} executes an idempotent bulk synchronization:
                        </p>
                        <ol class="list-decimal pl-5 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>The browser verifies server heartbeat and exchanges an authenticated CSRF handshake.</li>
                            <li>Sales batched in IndexedDB are dispatched in transactional payloads.</li>
                            <li>The cloud backend validates receipt sequencing and stock deduction constraints, returning confirmation IDs for local queue clearance.</li>
                            <li>Any barcode or inventory collision is flagged in the Super Admin conflict drawer for human review without interrupting the checkout line.</li>
                        </ol>
                    </section>

                    <!-- 7 -->
                    <section id="section-7" class="scroll-mt-28 space-y-3 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>7.</span>
                            <span>Automated Backups &amp; Disaster Recovery</span>
                        </h2>
                        <p>
                            We maintain automated daily database backups replicated across geodiversified cloud availability zones. In the event of a catastrophic server hardware failure, our automated container orchestration spins up replica instances with a Recovery Point Objective (RPO) of under 4 hours and Recovery Time Objective (RTO) of under 30 minutes.
                        </p>
                    </section>

                    <!-- 8 -->
                    <section id="section-8" class="scroll-mt-28 space-y-3">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>8.</span>
                            <span>Vulnerability Reporting &amp; Disclosure</span>
                        </h2>
                        <p>
                            We welcome responsible security research from developers and cybersecurity specialists. If you believe you have discovered a potential security vulnerability within {{ companyName }}, please report it responsibly:
                        </p>
                        <div class="mt-4 p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 space-y-2">
                            <div class="font-bold text-slate-900 dark:text-white">{{ companyName }} Security &amp; Vulnerability Response</div>
                            <div class="text-slate-600 dark:text-slate-300">📍 Physical Lab: {{ businessAddress }}</div>
                            <div class="text-slate-600 dark:text-slate-300">📞 Phone: <a :href="'tel:' + supportPhone" class="text-violet-600 dark:text-violet-400 font-mono">{{ supportPhone }}</a></div>
                            <div class="text-slate-600 dark:text-slate-300">🛡️ Direct Security Contact: <a :href="'mailto:' + supportEmail" class="text-violet-600 dark:text-violet-400 font-mono">{{ supportEmail }}</a></div>
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
