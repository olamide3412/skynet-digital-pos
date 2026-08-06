<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    branchName: String,
    message: String,
})
</script>

<template>
    <Head :title="`${branchName || 'Branch'} Unavailable`" />
    <div class="min-h-screen bg-slate-900 text-white flex items-center justify-center p-6 font-sans">
        <div class="max-w-md w-full text-center space-y-6 bg-slate-800/80 backdrop-blur border border-slate-700/50 p-8 rounded-2xl shadow-2xl">
            <!-- Icon -->
            <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex items-center justify-center mx-auto text-amber-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <!-- Title & Message -->
            <div>
                <h1 class="text-2xl font-bold text-slate-100 tracking-tight">
                    {{ branchName }} is Currently Unavailable
                </h1>
                <p class="text-slate-400 text-sm mt-2 leading-relaxed">
                    {{ message || 'This branch has been temporarily disabled by the system administrator.' }}
                </p>
            </div>

            <!-- Notice -->
            <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-4 text-xs text-slate-400 text-left space-y-1">
                <div class="font-semibold text-slate-300">Need assistance?</div>
                <div>Contact your IT Administrator or support@skynetdigitalltd.com</div>
            </div>

            <!-- Action -->
            <div>
                <Link
                    :href="route('superadmin.login')"
                    class="inline-block text-xs text-slate-500 hover:text-slate-400 transition-colors"
                >
                    Super Admin Portal &rarr;
                </Link>
            </div>
        </div>
    </div>
</template>
