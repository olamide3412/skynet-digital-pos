<script setup>
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import ThemeToggle from '@/Components/ThemeToggle.vue'

const page = usePage()
const isOpen = ref(false)
const toggle = () => (isOpen.value = !isOpen.value)

const posUrl = computed(() => {
    const branchSlug = page.props.current_branch?.slug || 'skynet-digital-enterprise'
    return route('pos.index', { branch: branchSlug })
})
</script>

<template>
    <header class="fixed top-0 left-0 right-0 z-40 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <!-- Logo & Title -->
            <Link :href="route('home')" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-blue-600 flex items-center justify-center font-black text-white text-lg shadow-sm">
                    S
                </div>
                <div>
                    <span class="font-bold text-base tracking-tight text-slate-900 dark:text-white block leading-none">SKYNET DIGITAL</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold uppercase tracking-wider block mt-0.5">Point of Sale System</span>
                </div>
            </Link>

            <!-- Right Actions (Desktop) -->
            <div class="hidden md:flex items-center gap-3">
                <ThemeToggle />

                <Link
                    :href="posUrl"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-xl transition shadow-xs flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    POS Terminal
                </Link>

                <Link
                    :href="route('superadmin.login')"
                    class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-xs rounded-xl transition border border-slate-200 dark:border-slate-600 flex items-center gap-1.5"
                >
                    Super Admin
                </Link>
            </div>

            <!-- Mobile Toggle -->
            <div class="flex items-center gap-2 md:hidden">
                <ThemeToggle />
                <button @click="toggle" class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="!isOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div v-if="isOpen" class="md:hidden border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-4 space-y-2">
            <Link
                :href="posUrl"
                @click="isOpen = false"
                class="block w-full text-center py-2.5 bg-emerald-600 text-white font-semibold text-xs rounded-xl"
            >
                Launch POS Terminal
            </Link>
            <Link
                :href="route('superadmin.login')"
                @click="isOpen = false"
                class="block w-full text-center py-2.5 bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold text-xs rounded-xl"
            >
                Super Admin Portal
            </Link>
        </div>
    </header>
</template>
