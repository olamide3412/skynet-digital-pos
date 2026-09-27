<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    companyName: {
        type: String,
        default: null,
    },
    supportPhone: {
        type: String,
        default: null,
    },
    supportEmail: {
        type: String,
        default: null,
    },
    posUrl: {
        type: String,
        default: '/felix-enterprise',
    },
    whatsappUrl: {
        type: String,
        default: 'https://wa.me/2348032072831',
    },
    pricingEnabled: {
        type: Boolean,
        default: true,
    },
})

const page = usePage()
const systemConfig = computed(() => page?.props?.system_config || {})
const dynamicCompanyName = computed(() => props.companyName || systemConfig.value?.company_name || 'Skynet Digital POS')
const dynamicSupportPhone = computed(() => props.supportPhone || systemConfig.value?.support_phone || '+234 803 207 2831')
const dynamicSupportEmail = computed(() => props.supportEmail || systemConfig.value?.support_email || 'support@skynetdigitalltd.com')

const currentYear = new Date().getFullYear()
</script>

<template>
    <footer class="border-t border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-950 pt-16 pb-12 px-4 sm:px-6 lg:px-8 text-slate-600 dark:text-slate-400 text-xs">
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 pb-12 border-b border-slate-200 dark:border-slate-800/60">
            
            <!-- Col 1: Brand & Bio (2 cols wide on desktop) -->
            <div class="space-y-4 lg:col-span-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-700 flex items-center justify-center font-black text-sm text-white shadow-md">
                        {{ dynamicCompanyName.charAt(0).toUpperCase() }}
                    </div>
                    <span class="font-extrabold text-base text-slate-900 dark:text-white tracking-tight">{{ dynamicCompanyName }}</span>
                </div>
                <p class="text-slate-500 dark:text-slate-400 text-xs leading-relaxed max-w-md">
                    {{ dynamicCompanyName }} is the enterprise point of sale and multi-branch inventory engine trusted by supermarkets, pharmacies, electronic retailers, and wholesale merchants for speed, offline resilience, and conversational AI analytics.
                </p>
                <div class="pt-2 text-slate-700 dark:text-slate-300 font-semibold space-y-1.5">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400">📞 Support:</span>
                        <a :href="'tel:' + dynamicSupportPhone" class="hover:text-violet-600 dark:hover:text-violet-400 font-mono">{{ dynamicSupportPhone }}</a>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400">✉️ Email:</span>
                        <a :href="'mailto:' + dynamicSupportEmail" class="hover:text-violet-600 dark:hover:text-violet-400 font-mono">{{ dynamicSupportEmail }}</a>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>System Status: 99.9% Uptime &amp; Offline Active</span>
                </div>
            </div>

            <!-- Col 2: Navigation -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-slate-900 dark:text-white text-xs uppercase tracking-wider">Navigation</h4>
                <ul class="space-y-2 font-medium">
                    <li><Link :href="route('home')" class="hover:text-violet-600 dark:hover:text-white transition">Home</Link></li>
                    <li><Link :href="route('features')" class="hover:text-violet-600 dark:hover:text-white transition">Features Overview</Link></li>
                    <li><Link :href="route('industries')" class="hover:text-violet-600 dark:hover:text-white transition">Industry Solutions</Link></li>
                    <li v-if="pricingEnabled"><Link :href="route('pricing')" class="hover:text-violet-600 dark:hover:text-white transition font-bold text-violet-600 dark:text-violet-400">Pricing &amp; Plans</Link></li>
                    <li><a href="/#how-it-works" class="hover:text-violet-600 dark:hover:text-white transition">How It Works</a></li>
                    <li><a href="/#faq" class="hover:text-violet-600 dark:hover:text-white transition">FAQ</a></li>
                </ul>
            </div>

            <!-- Col 3: Legal & Trust -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-slate-900 dark:text-white text-xs uppercase tracking-wider">Trust &amp; Legal</h4>
                <ul class="space-y-2 font-medium">
                    <li>
                        <Link :href="route('terms')" class="hover:text-violet-600 dark:hover:text-white transition flex items-center gap-1.5">
                            <span>Terms &amp; Conditions</span>
                        </Link>
                    </li>
                    <li>
                        <Link :href="route('privacy')" class="hover:text-violet-600 dark:hover:text-white transition flex items-center gap-1.5">
                            <span>Privacy Policy</span>
                        </Link>
                    </li>
                    <li>
                        <Link :href="route('refund-policy')" class="hover:text-violet-600 dark:hover:text-white transition flex items-center gap-1.5">
                            <span>Refund &amp; Cancellation</span>
                        </Link>
                    </li>
                    <li>
                        <Link :href="route('security')" class="hover:text-violet-600 dark:hover:text-white transition flex items-center gap-1.5">
                            <span>Security &amp; Compliance</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Col 4: Quick Launch & Actions -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-slate-900 dark:text-white text-xs uppercase tracking-wider">Quick Actions</h4>
                <ul class="space-y-2 font-medium">
                    <li>
                        <Link :href="posUrl" class="text-violet-600 dark:text-violet-400 font-bold hover:underline flex items-center gap-1">
                            <span>Launch POS Register</span>
                            <span>→</span>
                        </Link>
                    </li>
                    <li>
                        <a :href="whatsappUrl" target="_blank" rel="noopener" class="text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1">
                            <span>💬 WhatsApp Live Sales</span>
                        </a>
                    </li>
                    <li>
                        <a :href="'tel:' + dynamicSupportPhone" class="hover:text-violet-600 dark:hover:text-white transition flex items-center gap-1">
                            <span>Direct Helpline</span>
                        </a>
                    </li>
                    <li>
                        <a :href="'mailto:' + dynamicSupportEmail" class="hover:text-violet-600 dark:hover:text-white transition flex items-center gap-1">
                            <span>Email Desk</span>
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <div class="max-w-7xl mx-auto pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-slate-500">
            <div>
                &copy; {{ currentYear }} {{ dynamicCompanyName }}. All rights reserved. &nbsp;·&nbsp; Enterprise Cloud &amp; Offline POS
            </div>
            <div class="flex flex-wrap items-center gap-4 text-[11px]">
                <Link :href="route('terms')" class="hover:text-violet-600 dark:hover:text-slate-300 transition">Terms</Link>
                <span>•</span>
                <Link :href="route('privacy')" class="hover:text-violet-600 dark:hover:text-slate-300 transition">Privacy</Link>
                <span>•</span>
                <Link :href="route('refund-policy')" class="hover:text-violet-600 dark:hover:text-slate-300 transition">Refunds</Link>
                <span>•</span>
                <Link :href="route('security')" class="hover:text-violet-600 dark:hover:text-slate-300 transition">Security</Link>
            </div>
        </div>
    </footer>
</template>
