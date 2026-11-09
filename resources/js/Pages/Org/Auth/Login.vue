<script setup>
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'
import { ref, onMounted, computed, nextTick } from 'vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'

defineOptions({ layout: null })

const props = defineProps({
    turnstileSiteKey: String,
})

const page = usePage()

const form = useForm({
    login: '',
    password: '',
    remember: false,
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
    // Ensure fullscreen is exited upon landing on login page
    if (typeof document !== 'undefined' && (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement)) {
        try {
            if (document.exitFullscreen) document.exitFullscreen()
            else if (document.webkitExitFullscreen) document.webkitExitFullscreen()
            else if (document.mozCancelFullScreen) document.mozCancelFullScreen()
            else if (document.msExitFullscreen) document.msExitFullscreen()
        } catch (e) {}
    }

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

    form.post(route('org.login.submit'), {
        onFinish: () => {
            form.reset('password')
            resetTurnstile()
        },
        onError: (errs) => {
            if (form.errors.cf_turnstile_response) {
                resetTurnstile()
                form.cf_turnstile_response = ''
            }
            if (!Object.keys(errs || {}).length) {
                form.setError('login', 'Network or connection error. Please check connection and try again.')
            }
        }
    })
}
</script>

<template>
    <Head :title="($page.props.system_config?.company_name || 'Organization') + ' - Client Portal Login'" />

    <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col items-center justify-between p-4 sm:p-6 font-sans transition-colors duration-200">
        <!-- Top Row: Theme Toggle -->
        <div class="w-full max-w-md flex justify-end pt-2 pb-1">
            <ThemeToggle />
        </div>

        <!-- Login Card -->
        <div class="my-auto max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl dark:shadow-2xl space-y-6 transition-colors duration-200">
            <!-- Branding Header -->
            <div class="text-center space-y-2">
                <img
                    v-if="$page.props.system_config?.company_logo_url"
                    :src="$page.props.system_config.company_logo_url"
                    class="h-14 max-w-[200px] object-contain mx-auto shadow-sm rounded-xl"
                    alt="Company Logo"
                />
                <div
                    v-else
                    class="w-14 h-14 bg-gradient-to-tr from-purple-600 to-indigo-600 rounded-2xl mx-auto flex items-center justify-center font-black text-2xl text-white shadow-lg shadow-purple-600/25"
                >
                    🏢
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                    Organization Portal
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $page.props.system_config?.company_name || 'Skynet' }} Multi-Branch &amp; Executive Management
                </p>
            </div>

            <!-- Error Banner -->
            <div
                v-if="form.errors.login || turnstileError"
                class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 rounded-xl text-xs text-rose-700 dark:text-rose-300 space-y-1"
            >
                <div class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-rose-500 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>Authentication Error</span>
                </div>
                <p>{{ form.errors.login || turnstileError }}</p>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">
                        Email or Username
                    </label>
                    <input
                        v-model="form.login"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 focus:border-purple-600 dark:focus:border-purple-500 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-purple-600 dark:focus:ring-purple-500 transition-all"
                        placeholder="admin@yourbusiness.com"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">
                        Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            autocomplete="current-password"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 focus:border-purple-600 dark:focus:border-purple-500 rounded-xl px-4 py-3 pr-11 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-purple-600 dark:focus:ring-purple-500 transition-all"
                            placeholder="••••••••••••"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none cursor-pointer"
                            :title="showPassword ? 'Hide password' : 'Show password'"
                        >
                            <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 01-6 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                    <div v-if="form.errors.password" class="text-rose-500 dark:text-rose-400 text-xs mt-1">{{ form.errors.password }}</div>
                </div>

                <!-- Cloudflare Turnstile Widget (If siteKey is present) -->
                <div v-if="siteKey" class="flex flex-col items-center gap-1 pt-1">
                    <div id="cf-turnstile-widget" class="cf-turnstile"></div>
                    <div v-if="turnstileError" class="text-rose-600 dark:text-rose-400 text-xs text-center font-medium">
                        {{ turnstileError }}
                    </div>
                    <div v-if="form.errors.cf_turnstile_response" class="text-rose-600 dark:text-rose-400 text-xs text-center font-medium">
                        {{ form.errors.cf_turnstile_response }}
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-slate-300 dark:border-slate-700 rounded cursor-pointer"
                            v-model="form.remember"
                        />
                        <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">Remember me</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-50 text-white font-bold rounded-xl text-sm transition-all shadow-lg shadow-purple-600/20 cursor-pointer flex items-center justify-center gap-2"
                >
                    <span v-if="form.processing">Authenticating...</span>
                    <span v-else>Access Organization Portal</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>

                <!-- Powered by Branding -->
                <p class="text-center text-[11px] text-slate-400 dark:text-slate-500 font-medium tracking-wide pt-1">
                    Powered by <span class="font-bold text-slate-600 dark:text-slate-400">{{ $page.props.system_config?.company_name || 'Skynet Digital' }}</span>
                </p>
            </form>
        </div>

        <!-- Footer -->
        <footer class="py-2 text-center text-xs text-slate-500 dark:text-slate-400">
            <Link href="/" class="hover:text-slate-700 dark:hover:text-slate-200 transition font-medium">
                &larr; Back to Main Website
            </Link>
        </footer>
    </div>
</template>
