<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    status:  { type: Number, default: 403 },
    message: { type: String, default: 'An error occurred.' },
})

const page = usePage()

const homeHref = computed(() => {
    const branchSlug = page.props.current_branch?.slug
    if (branchSlug) {
        return route('pos.index', { branch: branchSlug })
    }
    if (page.props.auth?.user?.is_super_admin) {
        return route('superadmin.dashboard')
    }
    return route('home')
})

const config = computed(() => {
    switch (props.status) {
        case 403:
            return {
                icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                iconColor: 'text-red-400',
                iconBg:    'bg-red-500/10 border-red-500/20',
                label:     'Access Denied',
                hint:      'Your account does not have permission to view this page. Contact your administrator if you believe this is a mistake.',
                back:      true,
            }
        case 404:
            return {
                icon: 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                iconColor: 'text-amber-400',
                iconBg:    'bg-amber-500/10 border-amber-500/20',
                label:     'Page Not Found',
                hint:      'The page you requested does not exist. It may have been moved or deleted.',
                back:      true,
            }
        case 500:
            return {
                icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                iconColor: 'text-orange-400',
                iconBg:    'bg-orange-500/10 border-orange-500/20',
                label:     'Server Error',
                hint:      'Something went wrong on our end. Please try again or contact support.',
                back:      false,
            }
        case 503:
            return {
                icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                iconColor: 'text-blue-400',
                iconBg:    'bg-blue-500/10 border-blue-500/20',
                label:     'Service Unavailable',
                hint:      'We are under maintenance. Please check back shortly.',
                back:      false,
            }
        default:
            return {
                icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                iconColor: 'text-slate-400',
                iconBg:    'bg-slate-500/10 border-slate-500/20',
                label:     'Unexpected Error',
                hint:      props.message,
                back:      true,
            }
    }
})
</script>

<template>
    <!-- Standalone full-page error — no layout wrapper -->
    <div class="min-h-screen bg-slate-900 flex items-center justify-center p-4 font-sans">

        <!-- Animated background dots -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-slate-700/30 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 max-w-md w-full text-center">

            <!-- Brand -->
            <div class="flex items-center justify-center gap-2 mb-10">
                <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M16 5h3m0 0v3m0-3l-5 5"/>
                    </svg>
                </div>
                <span class="font-bold text-emerald-400 text-lg">SkyNet POS</span>
            </div>

            <!-- Error card -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-10 shadow-2xl">

                <!-- Icon -->
                <div class="flex justify-center mb-6">
                    <div :class="['w-20 h-20 rounded-2xl border flex items-center justify-center', config.iconBg]">
                        <svg :class="['w-10 h-10', config.iconColor]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="config.icon"/>
                        </svg>
                    </div>
                </div>

                <!-- Status code -->
                <p class="text-7xl font-black text-slate-700 leading-none mb-2 select-none">{{ status }}</p>

                <!-- Label -->
                <h1 class="text-xl font-bold text-white mb-3">{{ config.label }}</h1>

                <!-- Message -->
                <p class="text-sm text-slate-400 leading-relaxed mb-8">{{ config.hint }}</p>

                <!-- Actions -->
                <div class="flex flex-col gap-3">
                    <Link
                        :href="homeHref"
                        class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-semibold text-sm transition-colors"
                    >
                        Go to Home / Dashboard
                    </Link>

                    <button
                        v-if="config.back"
                        @click="() => window.history.back()"
                        class="w-full py-3 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl font-medium text-sm transition-colors"
                    >
                        ← Go Back
                    </button>
                </div>
            </div>

            <!-- Footer note -->
            <p class="mt-6 text-xs text-slate-600">
                Error {{ status }} · If this persists, contact your system administrator.
            </p>
        </div>
    </div>
</template>
