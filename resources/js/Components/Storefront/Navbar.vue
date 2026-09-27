<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ThemeToggle from '@/Components/ThemeToggle.vue'

const props = defineProps({
    companyName: {
        type: String,
        default: 'Skynet Digital POS',
    },
    appTagline: {
        type: String,
        default: 'AI-Powered Cloud & Offline Point of Sale',
    },
    logoUrl: {
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
const scrolled = ref(false)
const mobileMenuOpen = ref(false)
const featuresDropdownOpen = ref(false)
const mobileFeaturesExpanded = ref(false)

const currentUrl = computed(() => page.url)
const isHomePage = computed(() => currentUrl.value === '/' || currentUrl.value.startsWith('/#'))
const isFeaturesPage = computed(() => currentUrl.value.startsWith('/features'))
const isIndustriesPage = computed(() => currentUrl.value.startsWith('/industries'))
const isPricingPage = computed(() => currentUrl.value.startsWith('/pricing'))

function onScroll() {
    scrolled.value = window.scrollY > 25
}

function onClickOutside(e) {
    if (!e.target.closest('.features-dropdown-container')) {
        featuresDropdownOpen.value = false
    }
}

onMounted(() => {
    window.addEventListener('scroll', onScroll)
    window.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('click', onClickOutside)
})

const featureMenuItems = computed(() => [
    {
        title: 'Core POS Features',
        desc: 'Sub-0.4s checkouts, Dexie offline engine & multi-branch sync',
        icon: '⚡',
        badge: 'Offline-First',
        href: isHomePage.value ? '#features' : '/features#core',
    },
    {
        title: 'AI Copilot Engine',
        desc: 'Natural language queries in English, Nigerian Pidgin & Yoruba',
        icon: '🤖',
        badge: 'Multilingual',
        href: isHomePage.value ? '#ai-intelligence' : '/features#ai',
    },
    {
        title: 'Product Variants & Matrices',
        desc: 'Pieces, packs, cartons, and tiered wholesale pricing',
        icon: '📦',
        badge: 'Multi-Pack',
        href: isHomePage.value ? '#variants' : '/features#variants',
    },
    {
        title: 'Supported POS Hardware',
        desc: 'Plug & play thermal printers, barcode scanners & cash drawers',
        icon: '🔌',
        badge: 'Zero-Driver',
        href: isHomePage.value ? '#hardware' : '/features#hardware',
    },
])
</script>

<template>
    <header
        :class="[
            'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
            scrolled
                ? 'bg-white/90 dark:bg-slate-950/90 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800/80 shadow-md py-3'
                : 'bg-transparent py-4 sm:py-5'
        ]"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4">
                
                <!-- ── 1. Brand Logo & Title ──────────────────────────────── -->
                <Link :href="route('home')" class="flex items-center gap-3 group">
                    <div class="relative">
                        <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 opacity-60 blur-xs group-hover:opacity-100 transition duration-300"></div>
                        <img
                            v-if="logoUrl"
                            :src="logoUrl"
                            class="relative w-9 h-9 rounded-xl object-contain bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1 shadow-sm"
                            alt="Logo"
                        />
                        <div
                            v-else
                            class="relative w-9 h-9 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-700 flex items-center justify-center font-black text-base text-white shadow-lg"
                        >
                            {{ companyName.charAt(0).toUpperCase() }}
                        </div>
                    </div>
                    <div>
                        <span class="font-extrabold text-base tracking-tight text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                            {{ companyName }}
                        </span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold tracking-wide block leading-none mt-0.5">
                            {{ appTagline }}
                        </span>
                    </div>
                </Link>

                <!-- ── 2. Streamlined Desktop Nav Links ───────────────────── -->
                <nav class="hidden lg:flex items-center gap-1 xl:gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <!-- 1. Home (First item as requested) -->
                    <Link
                        :href="route('home')"
                        :class="[
                            'px-3 py-1.5 rounded-xl transition-all cursor-pointer font-bold',
                            isHomePage
                                ? 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/60'
                                : 'hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900'
                        ]"
                    >
                        Home
                    </Link>

                    <!-- 2. Features Dropdown (Groups AI Copilot, Variants, Hardware, Core) -->
                    <div
                        class="relative features-dropdown-container"
                        @mouseenter="featuresDropdownOpen = true"
                        @mouseleave="featuresDropdownOpen = false"
                    >
                        <button
                            type="button"
                            @click.stop="featuresDropdownOpen = !featuresDropdownOpen"
                            :class="[
                                'px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5 font-bold',
                                isFeaturesPage || featuresDropdownOpen
                                    ? 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/60'
                                    : 'hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900'
                            ]"
                        >
                            <span>Features</span>
                            <svg
                                class="w-3.5 h-3.5 transition-transform duration-200"
                                :class="featuresDropdownOpen ? 'rotate-180 text-violet-600' : 'text-slate-400'"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Features Mega Menu Dropdown -->
                        <div
                            v-show="featuresDropdownOpen"
                            class="absolute top-full left-1/2 -translate-x-1/2 pt-2 w-[480px] z-50 transition-all duration-200"
                        >
                            <div class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-3 space-y-1.5">
                                <div class="px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Platform Engine &amp; Capabilities
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <template v-for="(item, idx) in featureMenuItems" :key="idx">
                                        <a
                                            v-if="item.href.startsWith('#')"
                                            :href="item.href"
                                            @click="featuresDropdownOpen = false"
                                            class="p-2.5 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-all block group"
                                        >
                                            <div class="flex items-center justify-between mb-1">
                                                <div class="flex items-center gap-1.5 font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                                    <span>{{ item.icon }}</span>
                                                    <span>{{ item.title }}</span>
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug line-clamp-2">
                                                {{ item.desc }}
                                            </p>
                                        </a>
                                        <Link
                                            v-else
                                            :href="item.href"
                                            @click="featuresDropdownOpen = false"
                                            class="p-2.5 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-all block group"
                                        >
                                            <div class="flex items-center justify-between mb-1">
                                                <div class="flex items-center gap-1.5 font-extrabold text-xs text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                                    <span>{{ item.icon }}</span>
                                                    <span>{{ item.title }}</span>
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug line-clamp-2">
                                                {{ item.desc }}
                                            </p>
                                        </Link>
                                    </template>
                                </div>

                                <!-- Dropdown Bottom Action Bar -->
                                <div class="pt-2 mt-1 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between px-3 py-1">
                                    <span class="text-[11px] text-slate-400">Want full architecture specs?</span>
                                    <Link
                                        :href="route('features')"
                                        @click="featuresDropdownOpen = false"
                                        class="text-xs font-bold text-violet-600 dark:text-violet-400 hover:underline flex items-center gap-1"
                                    >
                                        <span>View Features Page</span>
                                        <span>→</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Industries -->
                    <Link
                        :href="route('industries')"
                        :class="[
                            'px-3 py-1.5 rounded-xl transition-all cursor-pointer font-bold',
                            isIndustriesPage
                                ? 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/60'
                                : 'hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900'
                        ]"
                    >
                        Industries
                    </Link>

                    <!-- 4. Pricing Plans (Dedicated subpage or homepage section) -->
                    <Link
                        v-if="pricingEnabled"
                        :href="route('pricing')"
                        :class="[
                            'px-3 py-1.5 rounded-xl transition-all cursor-pointer font-bold',
                            isPricingPage
                                ? 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/60'
                                : 'hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900'
                        ]"
                    >
                        Pricing
                    </Link>

                    <!-- 5. How It Works -->
                    <a
                        :href="isHomePage ? '#how-it-works' : '/#how-it-works'"
                        class="px-3 py-1.5 rounded-xl transition-all cursor-pointer hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900"
                    >
                        How It Works
                    </a>

                    <!-- 6. FAQ -->
                    <a
                        :href="isHomePage ? '#faq' : '/#faq'"
                        class="px-3 py-1.5 rounded-xl transition-all cursor-pointer hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-900"
                    >
                        FAQ
                    </a>
                </nav>

                <!-- ── 3. Actions & CTA Buttons ───────────────────────────── -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <ThemeToggle />

                    <!-- Primary Launch POS -->
                    <Link
                        :href="posUrl"
                        class="relative group inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-violet-600/30 dark:shadow-violet-900/40 hover:shadow-violet-600/50 active:scale-95"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                        <span>Launch POS</span>
                    </Link>

                    <!-- Mobile Menu Toggle Button -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300"
                        aria-label="Toggle Menu"
                    >
                        <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── Mobile Navigation Drawer ──────────────────────────────── -->
            <div
                v-if="mobileMenuOpen"
                class="lg:hidden mt-3 px-5 pt-4 pb-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-3xl space-y-4 shadow-2xl"
            >
                <div class="flex flex-col space-y-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <!-- Home -->
                    <Link
                        :href="route('home')"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'py-2 px-3 rounded-xl flex items-center gap-2',
                            isHomePage ? 'bg-violet-50 dark:bg-violet-950/60 text-violet-600 font-bold' : 'hover:bg-slate-100 dark:hover:bg-slate-900'
                        ]"
                    >
                        <span>🏠 Home</span>
                    </Link>

                    <!-- Features Accordion -->
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800/80 overflow-hidden">
                        <button
                            @click="mobileFeaturesExpanded = !mobileFeaturesExpanded"
                            type="button"
                            class="w-full py-2.5 px-3 flex items-center justify-between text-left font-bold text-slate-900 dark:text-white"
                        >
                            <span>⚡ Features &amp; Capabilities</span>
                            <span class="text-xs text-slate-400">{{ mobileFeaturesExpanded ? '▲' : '▼' }}</span>
                        </button>
                        <div v-show="mobileFeaturesExpanded" class="p-2 space-y-1 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800">
                            <a
                                v-for="(f, fIdx) in featureMenuItems"
                                :key="fIdx"
                                :href="f.href"
                                @click="mobileMenuOpen = false"
                                class="flex items-center gap-2 py-1.5 px-2.5 text-xs text-slate-600 dark:text-slate-300 hover:text-violet-600"
                            >
                                <span>{{ f.icon }}</span>
                                <span>{{ f.title }}</span>
                            </a>
                            <Link
                                :href="route('features')"
                                @click="mobileMenuOpen = false"
                                class="flex items-center justify-between py-2 px-2.5 text-xs font-bold text-violet-600 dark:text-violet-400 border-t border-slate-200 dark:border-slate-800 mt-1"
                            >
                                <span>Explore Dedicated Features Page</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Industries -->
                    <Link
                        :href="route('industries')"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'py-2 px-3 rounded-xl flex items-center gap-2',
                            isIndustriesPage ? 'bg-violet-50 dark:bg-violet-950/60 text-violet-600 font-bold' : 'hover:bg-slate-100 dark:hover:bg-slate-900'
                        ]"
                    >
                        <span>🏬 Industries &amp; Solutions</span>
                    </Link>

                    <!-- Pricing -->
                    <Link
                        v-if="pricingEnabled"
                        :href="route('pricing')"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'py-2 px-3 rounded-xl flex items-center gap-2',
                            isPricingPage ? 'bg-violet-50 dark:bg-violet-950/60 text-violet-600 font-bold' : 'hover:bg-slate-100 dark:hover:bg-slate-900'
                        ]"
                    >
                        <span>💳 Pricing Plans</span>
                    </Link>

                    <!-- How It Works -->
                    <a
                        :href="isHomePage ? '#how-it-works' : '/#how-it-works'"
                        @click="mobileMenuOpen = false"
                        class="py-2 px-3 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900"
                    >
                        <span>🛠️ How It Works</span>
                    </a>

                    <!-- FAQ -->
                    <a
                        :href="isHomePage ? '#faq' : '/#faq'"
                        @click="mobileMenuOpen = false"
                        class="py-2 px-3 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900"
                    >
                        <span>❓ FAQ</span>
                    </a>

                    <!-- Launch POS Register -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <Link
                            :href="posUrl"
                            class="py-2.5 px-3 rounded-xl bg-violet-600 text-white font-bold flex items-center justify-center gap-2 shadow-md"
                        >
                            <span>Launch POS Register →</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
