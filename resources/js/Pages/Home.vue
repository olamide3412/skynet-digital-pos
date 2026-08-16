<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted } from 'vue'

defineOptions({ layout: null })


const page = usePage()

const systemConfig = computed(() => page.props.system_config || {})
const companyName  = computed(() => systemConfig.value.company_name || 'Skynet POS')
const appTagline   = computed(() => systemConfig.value.app_tagline || 'Digital POS & Inventory Terminal')
const logoUrl      = computed(() => systemConfig.value.company_logo_url || null)

const posUrl = computed(() => {
    const branchSlug = page.props.current_branch?.slug || 'skynet-digital-enterprise'
    return route('pos.index', { branch: branchSlug })
})

const currentYear = new Date().getFullYear()
const scrolled = ref(false)
onMounted(() => {
    window.addEventListener('scroll', () => { scrolled.value = window.scrollY > 40 })
})

const features = [
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>`,
        title: 'Lightning-Fast Sales',
        desc: 'Process sales with cash, card, bank transfer, or split payments in seconds. Barcode scanning, custom discounts, and instant receipts built in.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>`,
        title: 'Real-Time Reporting',
        desc: 'Daily sales summaries, profit & loss breakdowns, inventory valuations, and end-of-day reports available at a glance — always up to date.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7l8 4"/>`,
        title: 'Smart Inventory',
        desc: 'Track stock levels, set reorder points, manage unit conversions (unit/pack/carton), and get low-stock alerts before you run out.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>`,
        title: 'Customer & Debt Management',
        desc: 'Maintain a customer database, manage debt ledgers, track payment history, and stay on top of outstanding balances effortlessly.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>`,
        title: 'Multi-Payment Methods',
        desc: 'Accept cash, bank transfers, and split payments. Record change, process returns, and attach receipts — all from a single, clean interface.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>`,
        title: 'Multi-Branch Control',
        desc: 'Manage unlimited branches from a single super-admin portal. Each branch has its own settings, staff, and stock while you see the full picture.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>`,
        title: 'Purchase Orders',
        desc: 'Create purchase orders, receive stock from vendors, and track goods in transit. Full procurement workflow built directly into the system.',
    },
    {
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>`,
        title: 'Role-Based Access',
        desc: 'Fine-grained user permissions — cashiers, managers, and admins each see only what they need. Secure, auditable, and fully customizable.',
    },
]

const stats = [
    { value: '100%', label: 'Web-Based', sub: 'No installation needed' },
    { value: '∞',    label: 'Branches',  sub: 'Scale without limits' },
    { value: '< 1s', label: 'Sale Speed', sub: 'Average checkout time' },
    { value: '24/7', label: 'Accessible', sub: 'From any device' },
]
</script>

<template>
    <Head :title="companyName + ' — Professional POS & Inventory System'" />

    <div class="min-h-screen bg-slate-950 text-white selection:bg-violet-600 selection:text-white font-sans">

        <!-- ══ NAVBAR ══════════════════════════════════════════════════ -->
        <nav :class="['fixed inset-x-0 top-0 z-50 transition-all duration-300', scrolled ? 'bg-slate-950/95 backdrop-blur-md border-b border-slate-800 shadow-xl shadow-black/40' : 'bg-transparent']">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                <!-- Brand -->
                <Link :href="route('home')" class="flex items-center gap-3 group">
                    <img v-if="logoUrl" :src="logoUrl" class="w-9 h-9 rounded-xl object-cover shadow-lg" alt="Logo" />
                    <div v-else class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-600 to-purple-700 flex items-center justify-center font-black text-lg shadow-lg text-white">
                        {{ companyName.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <span class="font-extrabold text-base tracking-tight text-white group-hover:text-violet-400 transition-colors">{{ companyName }}</span>
                        <span class="text-[9px] text-violet-400 font-bold tracking-widest uppercase block leading-none mt-0.5">{{ appTagline }}</span>
                    </div>
                </Link>

                <!-- Nav links -->
                <div class="hidden sm:flex items-center gap-6 text-sm text-slate-400 font-medium">
                    <a href="#features" class="hover:text-white transition-colors">Features</a>
                    <a href="#how-it-works" class="hover:text-white transition-colors">How it works</a>
                    <a href="#stats" class="hover:text-white transition-colors">Why us</a>
                </div>

                <!-- CTA -->
                <div class="flex items-center gap-3">
                    <Link :href="posUrl" class="flex items-center gap-2 px-4 py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold text-sm rounded-xl transition shadow-lg shadow-violet-900/40">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                        Launch POS
                    </Link>
                </div>
            </div>
        </nav>

        <!-- ══ HERO ══════════════════════════════════════════════════════ -->
        <section class="relative min-h-screen flex flex-col items-center justify-center text-center px-6 pt-24 pb-16 overflow-hidden">
            <!-- Background glow effects -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[700px] h-[700px] rounded-full bg-violet-600/10 blur-[120px]"></div>
                <div class="absolute bottom-0 right-0 w-[400px] h-[400px] rounded-full bg-amber-500/5 blur-[100px]"></div>
                <!-- Grid pattern -->
                <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(rgba(139,92,246,1) 1px, transparent 1px), linear-gradient(90deg, rgba(139,92,246,1) 1px, transparent 1px); background-size: 64px 64px;"></div>
            </div>

            <div class="relative z-10 max-w-4xl mx-auto space-y-8">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/10 border border-violet-500/30 text-violet-300 text-xs font-bold tracking-wide uppercase">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    System Online & Ready
                </div>

                <!-- Headline -->
                <h1 class="text-5xl sm:text-7xl font-black tracking-tight text-white leading-[1.05]">
                    The <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 via-purple-400 to-amber-400">Smarter</span> Way<br>
                    to Run Your Business
                </h1>

                <!-- Subtitle -->
                <p class="text-slate-400 text-lg sm:text-xl max-w-2xl mx-auto leading-relaxed">
                    <strong class="text-slate-200">{{ companyName }}</strong> is a complete cloud-based POS and inventory management system — built for retailers, pharmacies, and multi-branch businesses that demand speed, accuracy, and control.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
                    <Link :href="posUrl" class="flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-extrabold text-base rounded-2xl transition-all shadow-2xl shadow-violet-900/50 hover:scale-[1.02] active:scale-[0.98]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Open POS Terminal
                    </Link>
                </div>

                <!-- Scroll hint -->
                <div class="pt-8 flex flex-col items-center gap-2 opacity-40">
                    <span class="text-xs text-slate-400 font-medium">Explore features</span>
                    <svg class="w-5 h-5 text-slate-400 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </section>

        <!-- ══ STATS ══════════════════════════════════════════════════════ -->
        <section id="stats" class="border-y border-slate-800 bg-slate-900/60 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-2 md:grid-cols-4 gap-8">
                <div v-for="stat in stats" :key="stat.label" class="text-center space-y-1">
                    <div class="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-violet-400 to-amber-400">{{ stat.value }}</div>
                    <div class="text-sm font-bold text-white">{{ stat.label }}</div>
                    <div class="text-xs text-slate-500">{{ stat.sub }}</div>
                </div>
            </div>
        </section>

        <!-- ══ FEATURES ════════════════════════════════════════════════════ -->
        <section id="features" class="py-24 px-6">
            <div class="max-w-7xl mx-auto space-y-16">
                <!-- Heading -->
                <div class="text-center space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/10 border border-violet-500/20 text-violet-400 text-xs font-bold tracking-wide uppercase">
                        Everything You Need
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                        Packed with powerful features
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg max-w-2xl mx-auto">
                        From the first sale to end-of-month reporting, every tool your retail or pharmacy business needs — in one beautiful system.
                    </p>
                </div>

                <!-- Feature grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="(feature, i) in features"
                        :key="i"
                        class="group relative bg-slate-900 border border-slate-800 hover:border-violet-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-xl hover:shadow-violet-900/20 hover:-translate-y-1"
                    >
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-600/20 to-violet-600/5 border border-violet-500/20 flex items-center justify-center mb-4 group-hover:from-violet-600/30 transition-all">
                            <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="feature.icon"></svg>
                        </div>
                        <h3 class="font-bold text-white text-sm mb-2">{{ feature.title }}</h3>
                        <p class="text-slate-500 text-xs leading-relaxed">{{ feature.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ HOW IT WORKS ════════════════════════════════════════════════ -->
        <section id="how-it-works" class="py-24 px-6 bg-slate-900/50 border-y border-slate-800">
            <div class="max-w-5xl mx-auto space-y-16">
                <div class="text-center space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-bold tracking-wide uppercase">
                        Simple Setup
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                        Up and running in minutes
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div v-for="(step, i) in [
                        { num: '01', title: 'Set Up Your Branch', desc: 'Configure your branch name, logo, tax settings, and staff roles from the administration panel. Add your product catalog once and sync everywhere.' },
                        { num: '02', title: 'Open the POS Terminal', desc: 'Staff log in to their branch portal from any browser. Scan items, set prices, apply discounts, and process multi-method payments instantly.' },
                        { num: '03', title: 'Track & Grow', desc: 'Monitor real-time sales, receive low-stock alerts, generate profit reports, and manage customer debts — all from one clean dashboard.' },
                    ]" :key="i" class="relative bg-slate-950 border border-slate-800 rounded-2xl p-8 space-y-4">
                        <span class="text-6xl font-black text-violet-500/20 leading-none select-none">{{ step.num }}</span>
                        <h3 class="text-lg font-extrabold text-white -mt-2">{{ step.title }}</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ FINAL CTA ════════════════════════════════════════════════════ -->
        <section class="py-24 px-6">
            <div class="max-w-3xl mx-auto text-center space-y-8">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-96 h-96 rounded-full bg-violet-600/10 blur-[80px]"></div>
                    </div>
                    <div class="relative space-y-6">
                        <h2 class="text-4xl sm:text-6xl font-black text-white tracking-tight">
                            Ready to sell<br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 to-amber-400">smarter?</span>
                        </h2>
                        <p class="text-slate-400 text-base sm:text-lg max-w-xl mx-auto">
                            Your {{ companyName }} POS terminal is live and waiting. Click below to start processing sales instantly.
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-4">
                            <Link :href="posUrl" class="flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-extrabold text-lg rounded-2xl transition-all shadow-2xl shadow-violet-900/50 hover:scale-[1.02]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Launch POS Now
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ FOOTER ════════════════════════════════════════════════════════ -->
        <footer class="border-t border-slate-800 bg-slate-950">
            <div class="max-w-7xl mx-auto px-6 py-10 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img v-if="logoUrl" :src="logoUrl" class="w-7 h-7 rounded-lg object-cover" alt="Logo" />
                    <div v-else class="w-7 h-7 rounded-lg bg-violet-600 flex items-center justify-center font-black text-sm text-white">
                        {{ companyName.charAt(0).toUpperCase() }}
                    </div>
                    <span class="text-sm font-bold text-slate-300">{{ companyName }}</span>
                </div>
                <div class="text-xs text-slate-600 text-center">
                    &copy; {{ currentYear }} {{ companyName }}. All rights reserved. &nbsp;·&nbsp; Powered by SkyNet Digital POS
                </div>
                <div class="flex items-center gap-4 text-xs text-slate-600">
                    <Link :href="posUrl" class="hover:text-violet-400 transition-colors font-bold text-violet-500">POS Terminal →</Link>
                </div>
            </div>
        </footer>

    </div>
</template>
