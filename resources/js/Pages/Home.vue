<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const posUrl = computed(() => {
    const branchSlug = page.props.current_branch?.slug || 'skynet-digital-enterprise'
    return route('pos.index', { branch: branchSlug })
})
</script>

<template>
    <Head title="Skynet Digital POS Solution" />

    <div class="min-h-screen bg-slate-900 text-white flex flex-col justify-between selection:bg-emerald-500 selection:text-white">
        <!-- Header / Top Bar -->
        <header class="max-w-7xl w-full mx-auto px-6 py-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-blue-600 flex items-center justify-center font-black text-xl shadow-lg shadow-emerald-500/20">
                    S
                </div>
                <div>
                    <span class="font-bold text-lg tracking-tight block text-white">SKYNET DIGITAL</span>
                    <span class="text-[10px] text-emerald-400 font-semibold tracking-wider uppercase block">Point of Sale System</span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Link
                    :href="posUrl"
                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm rounded-xl transition shadow-lg shadow-emerald-600/25 flex items-center gap-2"
                >
                    Launch POS App
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </Link>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="max-w-5xl mx-auto px-6 py-16 text-center space-y-8 flex-1 flex flex-col items-center justify-center">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                System Ready & Active
            </div>

            <h1 class="text-4xl sm:text-6xl font-black text-slate-100 tracking-tight leading-tight max-w-3xl">
                Smart Digital Management for Your Business
            </h1>

            <p class="text-slate-400 text-base sm:text-lg max-w-2xl leading-relaxed">
                Streamlined sales processing, multi-branch inventory tracking, real-time barcode scanning, and intelligent business reporting.
            </p>

            <div class="pt-4 flex flex-wrap items-center justify-center gap-4">
                <Link
                    :href="posUrl"
                    class="px-8 py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-base rounded-2xl transition shadow-xl shadow-emerald-600/30 flex items-center gap-3"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Open POS Terminal
                </Link>

                <Link
                    :href="route('superadmin.login')"
                    class="px-8 py-4 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-base rounded-2xl transition shadow-lg flex items-center gap-3"
                >
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Super Admin Portal
                </Link>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
            &copy; {{ new Date().getFullYear() }} Skynet Digital POS. All rights reserved.
        </footer>
    </div>
</template>
