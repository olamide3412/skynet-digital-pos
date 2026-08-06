<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, nextTick } from 'vue'

const props = defineProps({
    turnstileSiteKey: String,
})

const page = usePage()

const form = useForm({
    login: '',
    password: '',
    cf_turnstile_response: '',
})

const turnstileWidgetId = ref(null)
const isTurnstileLoaded = ref(false)
const turnstileError    = ref('')
const showPassword      = ref(false)

const siteKey = computed(() => props.turnstileSiteKey || page.props.turnstileSiteKey || '')

const loadTurnstile = () => {
    if (window.turnstile) {
        renderTurnstile()
        return
    }

    const script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.defer = true

    script.onload = () => {
        isTurnstileLoaded.value = true
        renderTurnstile()
    }

    script.onerror = () => {
        turnstileError.value = 'Failed to load CAPTCHA. Please refresh the page.'
    }

    document.head.appendChild(script)
}

const renderTurnstile = () => {
    const key = siteKey.value
    if (window.turnstile && document.getElementById('cf-turnstile-widget') && key) {
        try {
            turnstileWidgetId.value = window.turnstile.render('#cf-turnstile-widget', {
                sitekey: key,
                callback: (token) => {
                    form.cf_turnstile_response = token
                    turnstileError.value = ''
                },
                'expired-callback': () => {
                    form.cf_turnstile_response = ''
                    turnstileError.value = 'CAPTCHA expired. Please verify again.'
                    resetTurnstile()
                },
                'error-callback': () => {
                    form.cf_turnstile_response = ''
                    turnstileError.value = 'CAPTCHA error. Please try again.'
                    resetTurnstile()
                }
            })
        } catch (e) {
            console.warn('Turnstile render exception:', e)
        }
    }
}

const resetTurnstile = () => {
    if (window.turnstile && turnstileWidgetId.value) {
        window.turnstile.reset(turnstileWidgetId.value)
    }
}

onMounted(() => {
    nextTick(() => {
        if (siteKey.value) {
            loadTurnstile()
        }
    })
})

const submit = () => {
    if (siteKey.value && !form.cf_turnstile_response) {
        turnstileError.value = 'Please complete the CAPTCHA verification'
        return
    }

    form.post(route('superadmin.login.submit'), {
        onFinish: () => {
            form.reset('password')
            resetTurnstile()
        },
        onError: () => {
            if (form.errors.cf_turnstile_response) {
                resetTurnstile()
                form.cf_turnstile_response = ''
            }
        }
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
                    <div class="relative flex items-center">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl px-4 py-3 pr-11 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition-all"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3.5 text-slate-400 hover:text-slate-200 transition focus:outline-none"
                            :title="showPassword ? 'Hide password' : 'Show password'"
                        >
                            <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                    <div v-if="form.errors.password" class="text-rose-400 text-xs mt-1">{{ form.errors.password }}</div>
                </div>

                <!-- Cloudflare Turnstile Widget (If siteKey is present) -->
                <div v-if="siteKey" class="flex flex-col items-center gap-1 pt-1">
                    <div id="cf-turnstile-widget" class="cf-turnstile"></div>
                    <div v-if="turnstileError" class="text-rose-400 text-xs text-center font-medium">
                        {{ turnstileError }}
                    </div>
                    <div v-if="form.errors.cf_turnstile_response" class="text-rose-400 text-xs text-center font-medium">
                        {{ form.errors.cf_turnstile_response }}
                    </div>
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
