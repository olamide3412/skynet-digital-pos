<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    user: Object,
})

// Eye toggles for password fields
const showCurrentPass = ref(false)
const showNewPass     = ref(false)
const showConfirmPass = ref(false)

// Profile Details Form
const profileForm = useForm({
    name:      props.user.name || '',
    full_name: props.user.full_name || '',
    username:  props.user.username || '',
    email:     props.user.email || '',
})

const updateProfile = () => {
    profileForm.put(route('superadmin.profile.update'))
}

// Change Password Form
const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
})

const updatePassword = () => {
    passwordForm.put(route('superadmin.profile.password'), {
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <Head title="SuperAdmin Profile - Skynet POS" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header Title -->
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">SuperAdmin Profile & Account Settings</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage your account information, credentials, and security password.</p>
            </div>
            <span class="px-3 py-1 bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 rounded-full text-xs font-bold uppercase tracking-wider">
                Super Administrator
            </span>
        </div>

        <!-- 1. Current Account Info Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col md:flex-row items-center gap-6">
            <div class="w-20 h-20 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-3xl font-black shadow-lg shadow-indigo-600/30 flex-shrink-0">
                {{ user.name?.charAt(0)?.toUpperCase() || 'S' }}
            </div>
            <div class="space-y-1 flex-1 text-center md:text-left">
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ user.full_name || user.name }}</h2>
                    <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                        Active Account
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">@{{ user.username }} · {{ user.email }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-medium">Created On: {{ user.created_at }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 2. Update Details Form -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Current Profile Details</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Update your primary identity and email address</p>
                </div>

                <form @submit.prevent="updateProfile" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Display Name *</label>
                        <input
                            v-model="profileForm.name"
                            type="text"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition"
                        />
                        <p v-if="profileForm.errors.name" class="text-red-500 text-xs mt-1">{{ profileForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Legal Name</label>
                        <input
                            v-model="profileForm.full_name"
                            type="text"
                            placeholder="e.g. Skynet System Super Administrator"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition"
                        />
                        <p v-if="profileForm.errors.full_name" class="text-red-500 text-xs mt-1">{{ profileForm.errors.full_name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                        <input
                            v-model="profileForm.username"
                            type="text"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition font-mono"
                        />
                        <p v-if="profileForm.errors.username" class="text-red-500 text-xs mt-1">{{ profileForm.errors.username }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition"
                        />
                        <p v-if="profileForm.errors.email" class="text-red-500 text-xs mt-1">{{ profileForm.errors.email }}</p>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition shadow-md shadow-indigo-600/20 disabled:opacity-40"
                        >
                            {{ profileForm.processing ? 'Saving Changes...' : 'Update Details' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- 3. Change Password Form -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Change Password</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ensure your account is using a long, random password to stay secure.</p>
                </div>

                <form @submit.prevent="updatePassword" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Current Password *</label>
                        <div class="relative flex items-center">
                            <input
                                v-model="passwordForm.current_password"
                                :type="showCurrentPass ? 'text' : 'password'"
                                required
                                placeholder="••••••••"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 pr-10 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition"
                            />
                            <button
                                type="button"
                                @click="showCurrentPass = !showCurrentPass"
                                class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none"
                                :title="showCurrentPass ? 'Hide password' : 'Show password'"
                            >
                                <svg v-if="!showCurrentPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <p v-if="passwordForm.errors.current_password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
                        <div class="relative flex items-center">
                            <input
                                v-model="passwordForm.password"
                                :type="showNewPass ? 'text' : 'password'"
                                required
                                minlength="8"
                                placeholder="At least 8 characters"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 pr-10 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition"
                            />
                            <button
                                type="button"
                                @click="showNewPass = !showNewPass"
                                class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none"
                                :title="showNewPass ? 'Hide password' : 'Show password'"
                            >
                                <svg v-if="!showNewPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <p v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
                        <div class="relative flex items-center">
                            <input
                                v-model="passwordForm.password_confirmation"
                                :type="showConfirmPass ? 'text' : 'password'"
                                required
                                minlength="8"
                                placeholder="Repeat new password"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 pr-10 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition"
                            />
                            <button
                                type="button"
                                @click="showConfirmPass = !showConfirmPass"
                                class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none"
                                :title="showConfirmPass ? 'Hide password' : 'Show password'"
                            >
                                <svg v-if="!showConfirmPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition shadow-md shadow-purple-600/20 disabled:opacity-40"
                        >
                            {{ passwordForm.processing ? 'Updating Password...' : 'Save New Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
