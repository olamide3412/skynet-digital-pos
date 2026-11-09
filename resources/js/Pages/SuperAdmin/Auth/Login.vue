<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, nextTick } from 'vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'

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

    form.post(route('superadmin.login.submit'), {
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
    <Head :title="($page.props.system_config?.company_name || 'SuperAdmin') + ' - System Portal Login'" />
    <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col items-center justify-between p-4 sm:p-6 font-sans transition-colors duration-200">
        <!-- Top Row: Theme Toggle -->
        <div class="w-full max-w-md flex justify-end pt-2 pb-1">
            <ThemeToggle />
        </div>

        <div class="my-auto max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl dark:shadow-2xl space-y-6 transition-colors duration-200">
            <div class="text-center space-y-2">
                <img v-if="$page.props.system_config?.company_logo_url" :src="$page.props.system_config.company_logo_url" class="h-14 max-w-[200px] object-contain mx-auto shadow-sm rounded-xl" alt="Company Logo" />
                <div v-else class="w-14 h-14 bg-theme rounded-2xl mx-auto flex items-center justify-center font-black text-2xl text-white shadow-lg">
                    {{ ($page.props.system_config?.company_name || 'S').charAt(0).toUpperCase() }}
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">{{ $page.props.system_config?.company_name || 'Skynet' }} SuperAdmin Portal</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $page.props.system_config?.app_tagline || 'Platform Management Terminal' }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">Email or Username</label>
                    <input
                        v-model="form.login"
                        type="text"
                        required
                        autofocus
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 focus:border-theme rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-theme transition-all"
                        placeholder="superadmin@skynetpos.com"
                    />
                    <div v-if="form.errors.login" class="text-rose-500 dark:text-rose-400 text-xs mt-1">{{ form.errors.login }}</div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">Password</label>
                    <div class="relative flex items-center">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 focus:border-theme rounded-xl px-4 py-3 pr-11 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-theme transition-all"
                            placeholder="••••••••"
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
                            class="h-4 w-4 text-theme focus:ring-theme border-slate-300 dark:border-slate-700 rounded cursor-pointer"
                            v-model="form.remember"
                        />
                        <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">Remember me</span>
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 bg-theme hover:opacity-90 disabled:opacity-50 text-white font-semibold rounded-xl text-sm transition-all shadow-lg cursor-pointer"
                >
                    {{ form.processing ? 'Authenticating...' : 'Sign In as Super Admin' }}
                </button>

                <!-- Powered by Branding -->
                <p class="text-center text-[11px] text-slate-400 dark:text-slate-500 font-medium tracking-wide pt-1">
                    Powered by <span class="font-bold text-slate-600 dark:text-slate-400">{{ $page.props.system_config?.company_name || 'Skynet Digital' }}</span>
                </p>
            </form>
        </div>

        <footer class="hidden py-2 text-center text-xs text-slate-500 dark:text-slate-400">
            Developed by <a href="https://skynetdigitalltd.com" target="_blank" rel="noopener noreferrer" class="hover:text-slate-700 dark:hover:text-slate-300 font-semibold transition">Skynet Digital Limited.</a>
        </footer>
    </div>
</template>
