<script setup>
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
    login: '',
    password: '',
})

const submit = () => {
    form.post(route('superadmin.login.submit'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Super Admin Login" />
    <div class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6 font-sans">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl space-y-6">
            <div class="text-center space-y-2">
                <div class="w-12 h-12 bg-indigo-600 rounded-xl mx-auto flex items-center justify-center font-black text-xl text-white">
                    S
                </div>
                <h1 class="text-2xl font-bold text-slate-100 tracking-tight">Super Admin Portal</h1>
                <p class="text-xs text-slate-400">Skynet POS Platform Management</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Email or Username</label>
                    <input
                        v-model="form.login"
                        type="text"
                        required
                        autofocus
                        class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl px-4 py-3 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition-all"
                        placeholder="superadmin@skynetpos.com"
                    />
                    <div v-if="form.errors.login" class="text-rose-400 text-xs mt-1">{{ form.errors.login }}</div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl px-4 py-3 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition-all"
                        placeholder="••••••••"
                    />
                    <div v-if="form.errors.password" class="text-rose-400 text-xs mt-1">{{ form.errors.password }}</div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold rounded-xl text-sm transition-colors shadow-lg shadow-indigo-600/20"
                >
                    {{ form.processing ? 'Authenticating...' : 'Sign In as Super Admin' }}
                </button>
            </form>
        </div>
    </div>
</template>
