<script setup>
import { useForm, usePage, Head } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import Logo from '../../../images/logo.png'
import TextInput from '@/Components/Forms/TextInput.vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
    branch: Object,
})

const page = usePage()
const toast = useToast()
const flashMsg = page.props.flash?.message

const form = useForm({
    login: '',
    password: '',
    remember: false,
    cf_turnstile_response: '',
})

const branchName = computed(() => {
    return props.branch?.name || page.props.current_branch?.name || page.props.store_settings?.company_name || 'Skynet Digital POS'
})

const branchSlug = computed(() => {
    return props.branch?.slug || page.props.current_branch?.slug || route().params?.branch || 'felix-enterprise'
})

const systemCompanyName = computed(() => {
    return page.props.system_config?.company_name || page.props.store_settings?.company_name || 'Skynet Digital'
})

const branchLogo = computed(() => {
    if (props.branch?.logo_url) return props.branch.logo_url
    if (page.props.current_branch?.logo_url) return page.props.current_branch.logo_url
    if (page.props.store_settings?.company_logo) return '/storage/' + page.props.store_settings.company_logo
    return Logo
})

const turnstileWidgetId = ref(null)
const isTurnstileLoaded = ref(false)
const turnstileError = ref('')

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
    const sitekey = page.props.turnstileSiteKey
    if (window.turnstile && document.getElementById('cf-turnstile-widget') && sitekey) {
        turnstileWidgetId.value = window.turnstile.render('#cf-turnstile-widget', {
            sitekey: sitekey,
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
    }
}

const resetTurnstile = () => {
    if (window.turnstile && turnstileWidgetId.value) {
        window.turnstile.reset(turnstileWidgetId.value)
    }
}

const reloadTurnstile = () => {
    resetTurnstile()
    form.cf_turnstile_response = ''
    turnstileError.value = ''
}

onMounted(() => {
    if (page.props.turnstileSiteKey) {
        loadTurnstile()
    }
})

const handleLogin = () => {
    if (page.props.turnstileSiteKey && !form.cf_turnstile_response) {
        turnstileError.value = 'Please complete the CAPTCHA verification'
        return
    }

    form.post(route('pos.login.submit', { branch: branchSlug.value }), {
        onFinish: () => {
            form.reset('password')
            resetTurnstile()
        },
        onError: () => {
            form.reset("password")
            toast.error(form.errors.login ?? 'Something went wrong, please try again')
            if (form.errors.cf_turnstile_response) {
                resetTurnstile()
                form.cf_turnstile_response = ''
            }
        },
        onSuccess: () => {
            if (flashMsg) {
                toast.error(flashMsg)
            }
            form.reset()
        }
    })
}
</script>

<template>
    <Head>
        <title>Login - {{ branchName }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="min-h-screen flex items-center justify-center px-4 py-8 bg-slate-100 dark:bg-slate-950 transition-colors">
        <!-- Login Card -->
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 space-y-6">
            
            <!-- Customized Branch Branding Header -->
            <div class="text-center space-y-2">
                <div class="flex justify-center mb-3">
                    <img
                        :src="branchLogo"
                        :alt="branchName + ' Logo'"
                        class="h-16 max-w-[220px] object-contain drop-shadow-xs"
                    />
                </div>
                <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ branchName }}
                </h2>
                <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">
                    Branch POS Portal
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Welcome back! Please login with your account to continue.
                </p>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleLogin" class="space-y-4">
                <!-- Email or Username -->
                <TextInput
                    name="login"
                    label="Email or Username"
                    v-model="form.login"
                    type="text"
                    placeholder="e.g. cashier or user@example.com"
                    :message="form.errors.login"
                    autofocus
                    autocomplete="username"
                    :required="true"
                />

                <!-- Password -->
                <TextInput
                    name="password"
                    label="Password"
                    type="password"
                    v-model="form.password"
                    placeholder="••••••••"
                    :required="true"
                    autocomplete="current-password"
                />

                <!-- Cloudflare Turnstile Widget (If enabled) -->
                <div v-if="page.props.turnstileSiteKey" class="flex flex-col items-center gap-1">
                    <div id="cf-turnstile-widget" class="cf-turnstile"></div>
                    <div v-if="turnstileError" class="text-red-600 dark:text-red-400 text-xs text-center font-medium">
                        {{ turnstileError }}
                    </div>
                    <div v-if="form.errors.cf_turnstile_response" class="text-red-600 dark:text-red-400 text-xs text-center font-medium">
                        {{ form.errors.cf_turnstile_response }}
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 rounded cursor-pointer"
                            v-model="form.remember"
                        />
                        <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">Remember me</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl shadow-lg shadow-emerald-900/20 transition duration-200 transform hover:scale-[1.01] active:scale-[0.99] disabled:opacity-50"
                >
                    {{ form.processing ? 'Signing in…' : 'Sign In'  }}
                </button>

                <!-- Powered by Branding -->
                <p class="text-center text-[11px] text-slate-400 dark:text-slate-500 font-medium tracking-wide pt-1">
                    Powered by <span class="font-bold text-slate-600 dark:text-slate-400">{{ systemCompanyName }}</span>
                </p>
            </form>
        </div>
    </div>
</template>
