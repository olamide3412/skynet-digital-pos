<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    currentTheme:         String,
    customPrimaryHex:     String,
    customSecondaryHex:   String,
    darkModeAccentMode:   String,
    customDarkPrimaryHex: String,
    companyName:          String,
    companyShortName:     String,
    appTagline:           String,
    currencySymbol:       String,
    supportPhone:         String,
    supportEmail:         String,
    companyLogoUrl:       String,
    themePalettes:        Array,
    mailSettings:         Object,
})

const activeTab        = ref('branding')
const selectedTheme    = ref(props.currentTheme || 'skynet')
const customPrimary   = ref(props.customPrimaryHex || '#7B00FF')
const customSecondary = ref(props.customSecondaryHex || '#FBA43D')
const logoPreview      = ref(props.companyLogoUrl || null)
const isTestingEmail   = ref(false)

const LUMINOUS_MAP = {
    skynet: '#C084FC',
    emerald: '#34D399',
    blue: '#60A5FA',
    indigo: '#818CF8',
    violet: '#A78BFA',
    teal: '#2DD4BF',
    cyan: '#22D3EE',
    amber: '#FBBF24',
    rose: '#FB7185',
}

const form = useForm({
    primary_color_theme:    props.currentTheme || 'skynet',
    custom_primary_hex:     props.customPrimaryHex || '#7B00FF',
    custom_secondary_hex:   props.customSecondaryHex || '#FBA43D',
    dark_mode_accent_mode:  props.darkModeAccentMode || 'white',
    custom_dark_primary_hex: props.customDarkPrimaryHex || '#FFFFFF',
    company_name:           props.companyName || 'Skynet POS',
    company_short_name:     props.companyShortName || 'Skynet',
    app_tagline:            props.appTagline || 'Digital POS & Inventory Terminal',
    currency_symbol:        props.currencySymbol || '₦',
    support_phone:          props.supportPhone || '+234 803 207 2831',
    support_email:          props.supportEmail || 'support@skynetdigitalltd.com',
    company_logo:           null,
})

const mailForm = useForm({
    mail_enabled:      props.mailSettings?.mail_enabled ?? false,
    mail_host:         props.mailSettings?.mail_host || '',
    mail_port:         props.mailSettings?.mail_port || 587,
    mail_username:     props.mailSettings?.mail_username || '',
    mail_password:     '',
    mail_encryption:   props.mailSettings?.mail_encryption || 'tls',
    mail_from_address: props.mailSettings?.mail_from_address || '',
    mail_from_name:    props.mailSettings?.mail_from_name || 'Skynet POS System',
})

const testEmailForm = useForm({
    test_email: '',
})

function saveMailSettings() {
    mailForm.post(route('superadmin.settings.mail'), {
        preserveScroll: true,
    })
}

function sendTestEmail() {
    testEmailForm.post(route('superadmin.settings.mail.test'), {
        preserveScroll: true,
        onSuccess: () => {
            testEmailForm.reset()
        }
    })
}

const currentLuminousHex = computed(() => {
    return LUMINOUS_MAP[selectedTheme.value] || customPrimary.value || '#C084FC'
})

function applyDynamicPreview(themeKey, hexPVal, hexSVal, darkAccMode, darkHexVal) {
    if (typeof document === 'undefined') return

    const rootStyle = document.documentElement.style
    const mode = darkAccMode || form.dark_mode_accent_mode || 'white'

    let computedDarkHex = '#FFFFFF'
    if (mode === 'white') {
        computedDarkHex = '#FFFFFF'
    } else if (mode === 'luminous') {
        computedDarkHex = LUMINOUS_MAP[themeKey] || darkHexVal || hexPVal || '#C084FC'
    } else if (mode === 'custom') {
        computedDarkHex = darkHexVal || form.custom_dark_primary_hex || '#FFFFFF'
    }

    let hexD = computedDarkHex.replace('#', '')
    if (hexD.length === 3) hexD = hexD.split('').map(c => c + c).join('')
    const dr = parseInt(hexD.substring(0, 2), 16) || 255
    const dg = parseInt(hexD.substring(2, 4), 16) || 255
    const db = parseInt(hexD.substring(4, 6), 16) || 255

    rootStyle.setProperty('--color-theme-dark', computedDarkHex)
    rootStyle.setProperty('--color-theme-dark-rgb', `${dr}, ${dg}, ${db}`)

    if (themeKey === 'custom' && hexPVal) {
        document.documentElement.setAttribute('data-theme', 'custom')
        
        let hexP = hexPVal.replace('#', '')
        if (hexP.length === 3) hexP = hexP.split('').map(c => c + c).join('')
        const pr = parseInt(hexP.substring(0, 2), 16) || 123
        const pg = parseInt(hexP.substring(2, 4), 16) || 0
        const pb = parseInt(hexP.substring(4, 6), 16) || 255
        const phoverHex = '#' + [Math.max(0, Math.floor(pr * 0.85)), Math.max(0, Math.floor(pg * 0.85)), Math.max(0, Math.floor(pb * 0.85))].map(x => x.toString(16).padStart(2, '0')).join('')

        rootStyle.setProperty('--color-theme', hexPVal)
        rootStyle.setProperty('--color-theme-hover', phoverHex)
        rootStyle.setProperty('--color-theme-light', `rgba(${pr}, ${pg}, ${pb}, 0.15)`)
        rootStyle.setProperty('--color-theme-rgb', `${pr}, ${pg}, ${pb}`)
        rootStyle.setProperty('--color-theme-text', phoverHex)

        const secHex = hexSVal || '#FBA43D'
        let hexS = secHex.replace('#', '')
        if (hexS.length === 3) hexS = hexS.split('').map(c => c + c).join('')
        const sr = parseInt(hexS.substring(0, 2), 16) || 251
        const sg = parseInt(hexS.substring(2, 4), 16) || 164
        const sb = parseInt(hexS.substring(4, 6), 16) || 61
        const shoverHex = '#' + [Math.max(0, Math.floor(sr * 0.85)), Math.max(0, Math.floor(sg * 0.85)), Math.max(0, Math.floor(sb * 0.85))].map(x => x.toString(16).padStart(2, '0')).join('')

        rootStyle.setProperty('--color-theme-secondary', secHex)
        rootStyle.setProperty('--color-theme-secondary-hover', shoverHex)
        rootStyle.setProperty('--color-theme-secondary-light', `rgba(${sr}, ${sg}, ${sb}, 0.15)`)
        rootStyle.setProperty('--color-theme-secondary-rgb', `${sr}, ${sg}, ${sb}`)
    } else {
        document.documentElement.setAttribute('data-theme', themeKey)
        rootStyle.removeProperty('--color-theme')
        rootStyle.removeProperty('--color-theme-hover')
        rootStyle.removeProperty('--color-theme-light')
        rootStyle.removeProperty('--color-theme-rgb')
        rootStyle.removeProperty('--color-theme-text')

        rootStyle.removeProperty('--color-theme-secondary')
        rootStyle.removeProperty('--color-theme-secondary-hover')
        rootStyle.removeProperty('--color-theme-secondary-light')
        rootStyle.removeProperty('--color-theme-secondary-rgb')
    }
}

function selectPalette(themeKey) {
    selectedTheme.value = themeKey
    form.primary_color_theme = themeKey
    applyDynamicPreview(themeKey, customPrimary.value, customSecondary.value, form.dark_mode_accent_mode, form.custom_dark_primary_hex)
}

function updateCustomPrimary(hexVal) {
    selectedTheme.value = 'custom'
    customPrimary.value = hexVal
    form.primary_color_theme = 'custom'
    form.custom_primary_hex  = hexVal
    applyDynamicPreview('custom', hexVal, customSecondary.value, form.dark_mode_accent_mode, form.custom_dark_primary_hex)
}

function updateCustomSecondary(hexVal) {
    selectedTheme.value = 'custom'
    customSecondary.value = hexVal
    form.primary_color_theme  = 'custom'
    form.custom_secondary_hex = hexVal
    applyDynamicPreview('custom', customPrimary.value, hexVal, form.dark_mode_accent_mode, form.custom_dark_primary_hex)
}

function setDarkModeAccentMode(mode) {
    form.dark_mode_accent_mode = mode
    applyDynamicPreview(selectedTheme.value, customPrimary.value, customSecondary.value, mode, form.custom_dark_primary_hex)
}

function updateCustomDarkPrimary(hexVal) {
    form.dark_mode_accent_mode   = 'custom'
    form.custom_dark_primary_hex = hexVal
    applyDynamicPreview(selectedTheme.value, customPrimary.value, customSecondary.value, 'custom', hexVal)
}

function handleLogoFile(e) {
    const file = e.target.files[0]
    if (file) {
        form.company_logo = file
        logoPreview.value = URL.createObjectURL(file)
    }
}

function submit() {
    form.post(route('superadmin.settings.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            applyDynamicPreview(form.primary_color_theme, form.custom_primary_hex, form.custom_secondary_hex, form.dark_mode_accent_mode, form.custom_dark_primary_hex)
        }
    })
}
</script>

<template>
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">SuperAdmin Settings</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Configure global company white-label branding, dual-color theme palettes, and system parameters across all branches.</p>
            </div>
            <button
                @click="submit"
                :disabled="form.processing"
                class="px-5 py-2.5 bg-theme hover:opacity-90 text-white font-bold text-xs rounded-xl shadow-lg shadow-black/10 transition flex items-center gap-2 disabled:opacity-50 cursor-pointer"
            >
                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ form.processing ? 'Saving Changes...' : 'Save All Settings' }}</span>
            </button>
        </div>

        <!-- Settings Navigation Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto scrollbar-hide">
            <button
                @click="activeTab = 'branding'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
                    activeTab === 'branding'
                        ? 'bg-theme text-white shadow-md'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                ]"
            >
                <span>🎨</span>
                <span>Branding & Color Themes</span>
            </button>

            <button
                @click="activeTab = 'general'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
                    activeTab === 'general'
                        ? 'bg-theme text-white shadow-md'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                ]"
            >
                <span>⚙️</span>
                <span>General Company Config</span>
            </button>

            <button
                @click="activeTab = 'security'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
                    activeTab === 'security'
                        ? 'bg-theme text-white shadow-md'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                ]"
            >
                <span>🔐</span>
                <span>Security & Maintenance</span>
            </button>

            <button
                @click="activeTab = 'mail'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
                    activeTab === 'mail'
                        ? 'bg-theme text-white shadow-md'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                ]"
            >
                <span>✉️</span>
                <span>Email & SMTP Server</span>
            </button>
        </div>

        <!-- ── TAB 1: BRANDING & THEME COLORS ────────────────────────────────── -->
        <div v-show="activeTab === 'branding'" class="space-y-6">
            <!-- Palette Selector -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-theme inline-block"></span>
                        Dual-Color Palette Configuration
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Select an official preset theme or configure custom <strong>Primary</strong> and <strong>Secondary</strong> brand colors for POS, Cashier, Admin, Reports, and Charts.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- Preset Palettes -->
                    <div
                        v-for="palette in themePalettes"
                        :key="palette.key"
                        @click="selectPalette(palette.key)"
                        :class="[
                            'relative p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-3',
                            selectedTheme === palette.key
                                ? 'border-slate-900 dark:border-white ring-2 ring-slate-900/20 dark:ring-white/20 bg-slate-50 dark:bg-slate-800/80 shadow-md scale-[1.02]'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 bg-white dark:bg-slate-900'
                        ]"
                    >
                        <div v-if="selectedTheme === palette.key" class="absolute top-3 right-3 w-5 h-5 rounded-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center text-xs font-bold shadow-xs">
                            ✓
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center -space-x-2">
                                <div class="w-7 h-7 rounded-lg shadow-sm flex items-center justify-center text-white font-bold text-xs" :style="{ backgroundColor: palette.hex }">
                                    P
                                </div>
                                <div class="w-7 h-7 rounded-lg shadow-sm flex items-center justify-center text-white font-bold text-xs border-2 border-white dark:border-slate-900" :style="{ backgroundColor: palette.secondaryHex }">
                                    S
                                </div>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ palette.name }}</h3>
                                <p class="text-[10px] font-mono text-slate-400">{{ palette.hex }} • {{ palette.secondaryHex }}</p>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">{{ palette.description }}</p>

                        <div class="h-2 w-full rounded-full overflow-hidden flex gap-1">
                            <div class="h-full flex-1 rounded-full" :style="{ backgroundColor: palette.hex }"></div>
                            <div class="h-full flex-1 rounded-full" :style="{ backgroundColor: palette.secondaryHex }"></div>
                        </div>
                    </div>

                    <!-- Custom Dual-Color Theme Card -->
                    <div
                        @click="selectPalette('custom')"
                        :class="[
                            'relative p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-3',
                            selectedTheme === 'custom'
                                ? 'border-slate-900 dark:border-white ring-2 ring-slate-900/20 dark:ring-white/20 bg-slate-50 dark:bg-slate-800/80 shadow-md scale-[1.02]'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 bg-white dark:bg-slate-900'
                        ]"
                    >
                        <div v-if="selectedTheme === 'custom'" class="absolute top-3 right-3 w-5 h-5 rounded-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center text-xs font-bold shadow-xs">
                            ✓
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center -space-x-2">
                                <div class="relative w-7 h-7 rounded-lg shadow-sm flex items-center justify-center text-white font-bold text-xs overflow-hidden" :style="{ backgroundColor: customPrimary }">
                                    P
                                    <input type="color" :value="customPrimary" @input="updateCustomPrimary($event.target.value)" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                                </div>
                                <div class="relative w-7 h-7 rounded-lg shadow-sm flex items-center justify-center text-white font-bold text-xs border-2 border-white dark:border-slate-900 overflow-hidden" :style="{ backgroundColor: customSecondary }">
                                    S
                                    <input type="color" :value="customSecondary" @input="updateCustomSecondary($event.target.value)" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                                </div>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Custom Theme</h3>
                                <p class="text-[10px] font-mono text-slate-400">{{ customPrimary }} • {{ customSecondary }}</p>
                            </div>
                        </div>

                        <!-- Dual Pickers -->
                        <div class="space-y-2" @click.stop>
                            <div>
                                <label class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Primary Accent:</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="color" :value="customPrimary" @input="updateCustomPrimary($event.target.value)" class="w-6 h-6 rounded border-0 cursor-pointer bg-transparent p-0" />
                                    <input type="text" :value="customPrimary" @input="updateCustomPrimary($event.target.value)" class="px-2 py-0.5 text-[11px] font-mono rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white w-full uppercase" placeholder="#7B00FF" />
                                </div>
                            </div>

                            <div>
                                <label class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Secondary Accent:</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="color" :value="customSecondary" @input="updateCustomSecondary($event.target.value)" class="w-6 h-6 rounded border-0 cursor-pointer bg-transparent p-0" />
                                    <input type="text" :value="customSecondary" @input="updateCustomSecondary($event.target.value)" class="px-2 py-0.5 text-[11px] font-mono rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white w-full uppercase" placeholder="#FBA43D" />
                                </div>
                            </div>
                        </div>

                        <div class="h-2 w-full rounded-full overflow-hidden flex gap-1">
                            <div class="h-full flex-1 rounded-full" :style="{ backgroundColor: customPrimary }"></div>
                            <div class="h-full flex-1 rounded-full" :style="{ backgroundColor: customSecondary }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dark Mode Accent Configuration -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-slate-900 dark:bg-white inline-block"></span>
                        Dark Mode Theme & Text Legibility
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Configure how brand names, total headers, price labels, and accents display when Dark Mode is active for high readability.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Option 1: Crisp Pure White -->
                    <div
                        @click="setDarkModeAccentMode('white')"
                        :class="[
                            'p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-3',
                            form.dark_mode_accent_mode === 'white'
                                ? 'border-slate-900 dark:border-white ring-2 ring-slate-900/20 dark:ring-white/20 bg-slate-50 dark:bg-slate-800/80 shadow-md'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 bg-white dark:bg-slate-900'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-white border border-slate-300 shadow-sm flex items-center justify-center text-slate-900 font-bold text-xs">
                                    ⚪
                                </div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Crisp Pure White</h3>
                            </div>
                            <div v-if="form.dark_mode_accent_mode === 'white'" class="w-4 h-4 rounded-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center text-[10px] font-bold">✓</div>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Ultra-high contrast (<strong>#FFFFFF</strong>) for the highest readability of totals, headings, and prices on dark backgrounds.
                        </p>
                        <div class="px-2.5 py-1.5 rounded-lg bg-slate-950 text-white font-bold text-xs flex justify-between items-center border border-slate-800">
                            <span class="text-slate-400">Sample Total:</span>
                            <span class="text-white">₦24,500.00</span>
                        </div>
                    </div>

                    <!-- Option 2: Luminous Brand Tint -->
                    <div
                        @click="setDarkModeAccentMode('luminous')"
                        :class="[
                            'p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-3',
                            form.dark_mode_accent_mode === 'luminous'
                                ? 'border-slate-900 dark:border-white ring-2 ring-slate-900/20 dark:ring-white/20 bg-slate-50 dark:bg-slate-800/80 shadow-md'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 bg-white dark:bg-slate-900'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg shadow-sm flex items-center justify-center text-slate-900 font-bold text-xs" :style="{ backgroundColor: currentLuminousHex }">
                                    🟣
                                </div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Luminous Brand Tint</h3>
                            </div>
                            <div v-if="form.dark_mode_accent_mode === 'luminous'" class="w-4 h-4 rounded-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center text-[10px] font-bold">✓</div>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Bright neon/pastel tint (<strong>{{ currentLuminousHex }}</strong>) tuned for vibrant brand presence in Dark Mode.
                        </p>
                        <div class="px-2.5 py-1.5 rounded-lg bg-slate-950 text-slate-300 font-bold text-xs flex justify-between items-center border border-slate-800">
                            <span class="text-slate-400">Sample Total:</span>
                            <span :style="{ color: currentLuminousHex }">₦24,500.00</span>
                        </div>
                    </div>

                    <!-- Option 3: Custom Dark Hex -->
                    <div
                        @click="setDarkModeAccentMode('custom')"
                        :class="[
                            'p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-3',
                            form.dark_mode_accent_mode === 'custom'
                                ? 'border-slate-900 dark:border-white ring-2 ring-slate-900/20 dark:ring-white/20 bg-slate-50 dark:bg-slate-800/80 shadow-md'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 bg-white dark:bg-slate-900'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg shadow-sm flex items-center justify-center text-slate-900 font-bold text-xs border border-slate-300" :style="{ backgroundColor: form.custom_dark_primary_hex || '#FFFFFF' }">
                                    🎨
                                </div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Custom Dark Color</h3>
                            </div>
                            <div v-if="form.dark_mode_accent_mode === 'custom'" class="w-4 h-4 rounded-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center text-[10px] font-bold">✓</div>
                        </div>

                        <div class="space-y-1" @click.stop>
                            <label class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Dark Mode Text Color:</label>
                            <div class="flex items-center gap-1.5">
                                <input type="color" :value="form.custom_dark_primary_hex || '#FFFFFF'" @input="updateCustomDarkPrimary($event.target.value)" class="w-6 h-6 rounded border-0 cursor-pointer bg-transparent p-0" />
                                <input type="text" :value="form.custom_dark_primary_hex || '#FFFFFF'" @input="updateCustomDarkPrimary($event.target.value)" class="px-2 py-0.5 text-[11px] font-mono rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white w-full uppercase" placeholder="#FFFFFF" />
                            </div>
                        </div>

                        <div class="px-2.5 py-1.5 rounded-lg bg-slate-950 text-slate-300 font-bold text-xs flex justify-between items-center border border-slate-800">
                            <span class="text-slate-400">Sample Total:</span>
                            <span :style="{ color: form.custom_dark_primary_hex || '#FFFFFF' }">₦24,500.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Preview Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Live UI & Chart Element Preview
                </h2>
                <div class="p-6 bg-slate-100 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <img v-if="logoPreview" :src="logoPreview" class="w-10 h-10 rounded-xl object-cover shadow-md" alt="Logo Preview" />
                            <div v-else class="w-10 h-10 rounded-xl bg-theme flex items-center justify-center text-white font-black text-lg shadow-md">
                                {{ (form.company_name || 'S').charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <span class="text-base font-black text-slate-900 dark:text-white">{{ form.company_name || 'Skynet POS' }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">{{ form.app_tagline || 'Digital POS & Inventory Terminal' }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-theme-light text-theme text-xs font-bold border border-theme">Primary Accent</span>
                            <span class="px-3 py-1 rounded-full bg-theme-secondary-light text-theme-secondary text-xs font-bold border border-theme-secondary">Secondary Accent</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button class="px-4 py-2 bg-theme text-white font-bold text-xs rounded-xl shadow-md">Primary Button</button>
                        <button class="px-4 py-2 bg-theme-secondary text-white font-bold text-xs rounded-xl shadow-md">Secondary Button</button>
                        <span class="px-3 py-1 rounded-lg bg-theme text-white text-xs font-bold">REVENUE</span>
                        <span class="px-3 py-1 rounded-lg bg-theme-secondary text-white text-xs font-bold">GROSS PROFIT</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB 2: GENERAL COMPANY CONFIG ─────────────────────────────────── -->
        <div v-show="activeTab === 'general'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-theme inline-block"></span>
                    Company White-Label & System Parameters
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Configure company name, short brand title, logo mark, tagline, currency symbol, and support contact details across all branches.
                </p>
            </div>

            <!-- Logo Upload Field -->
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950 flex flex-col sm:flex-row items-center gap-4">
                <div class="w-16 h-16 rounded-2xl border-2 border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                    <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" alt="Company Logo" />
                    <span v-else class="text-2xl font-black text-theme">{{ (form.company_name || 'S').charAt(0).toUpperCase() }}</span>
                </div>
                <div class="flex-1 space-y-1">
                    <label class="text-xs font-bold text-slate-900 dark:text-white block">Company Brand Logo Mark</label>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Upload a PNG, JPG, or SVG image (max 2MB) to replace default logos on headers, receipts, and login screens.</p>
                    <input type="file" @change="handleLogoFile" accept="image/*" class="text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-theme file:text-white cursor-pointer" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Company / App Name</label>
                    <input type="text" v-model="form.company_name" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" placeholder="Skynet POS" />
                    <p class="text-[10px] text-slate-400 mt-1">Appears on headers, titles, receipt footers, and emails.</p>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Company Short Abbreviation</label>
                    <input type="text" v-model="form.company_short_name" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" placeholder="Skynet" />
                    <p class="text-[10px] text-slate-400 mt-1">Used in compact badges and short mobile labels.</p>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">System Tagline / Subtitle</label>
                    <input type="text" v-model="form.app_tagline" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" placeholder="Digital POS & Inventory Terminal" />
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Currency Symbol</label>
                    <input type="text" v-model="form.currency_symbol" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono" placeholder="₦" />
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Support Phone Number</label>
                    <input type="text" v-model="form.support_phone" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" placeholder="+234 803 207 2831" />
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Support Email Address</label>
                    <input type="email" v-model="form.support_email" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" placeholder="support@skynetdigitalltd.com" />
                </div>
            </div>
        </div>

        <!-- ── TAB 3: SECURITY & MAINTENANCE ────────────────────────────────── -->
        <div v-show="activeTab === 'security'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                    Security & Maintenance Overview
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Environment status, session security, and system maintenance parameters.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Environment</span>
                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Production Live</p>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Session Driver</span>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">Database / Cookie</p>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Security Index</span>
                    <p class="text-sm font-bold text-theme">Spatie Permissions Enabled</p>
                </div>
            </div>
        </div>

        <!-- ── TAB 4: EMAIL & SMTP SERVER CONFIGURATION ───────────────────── -->
        <div v-show="activeTab === 'mail'" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-theme inline-block"></span>
                        Outgoing Mail (SMTP) Configuration
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Configure the system-wide SMTP mail server used for sending broadcast announcements and email notifications. Credentials are securely encrypted at rest.
                    </p>
                </div>

                <form @submit.prevent="saveMailSettings" class="space-y-5">
                    <!-- Master Toggle -->
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white block">Enable Outgoing Email Delivery</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Master switch. When disabled, broadcast notifications will only deliver in-app.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="mailForm.mail_enabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:bg-slate-700 peer-checked:bg-theme"></div>
                        </label>
                    </div>

                    <!-- SMTP Fields Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">SMTP Host</label>
                            <input
                                type="text"
                                v-model="mailForm.mail_host"
                                placeholder="smtp.gmail.com or smtp.mailgun.org"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">SMTP Port</label>
                            <input
                                type="number"
                                v-model="mailForm.mail_port"
                                placeholder="587"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">SMTP Username / Email</label>
                            <input
                                type="text"
                                v-model="mailForm.mail_username"
                                placeholder="notifications@skynetdigitalltd.com"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">
                                <span>SMTP Password</span>
                                <span v-if="mailSettings?.has_password" class="text-emerald-600 dark:text-emerald-400 font-normal ml-1">
                                    (Password is securely stored. Leave blank to keep)
                                </span>
                            </label>
                            <input
                                type="password"
                                v-model="mailForm.mail_password"
                                :placeholder="mailSettings?.has_password ? '••••••••••••••••' : 'Enter SMTP password or App Password'"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Encryption Protocol</label>
                            <select
                                v-model="mailForm.mail_encryption"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            >
                                <option value="tls">TLS (Recommended - Port 587)</option>
                                <option value="ssl">SSL (Port 465)</option>
                                <option value="none">None (Plaintext - Port 25)</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">From Sender Address</label>
                            <input
                                type="email"
                                v-model="mailForm.mail_from_address"
                                placeholder="notifications@skynetdigitalltd.com"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">From Sender Display Name</label>
                            <input
                                type="text"
                                v-model="mailForm.mail_from_name"
                                placeholder="Skynet POS Notifications"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="mailForm.processing"
                            class="px-6 py-2.5 rounded-xl bg-theme hover:bg-theme-dark text-white text-xs font-bold shadow-md transition cursor-pointer disabled:opacity-50"
                        >
                            {{ mailForm.processing ? 'Saving...' : 'Save Mail Configuration' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Test Email Section -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🧪</span>
                        <span>Send Test Email</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Verify your SMTP credentials by sending a test message before broadcasting to branches.
                    </p>
                </div>

                <form @submit.prevent="sendTestEmail" class="flex flex-col sm:flex-row gap-3 max-w-xl">
                    <input
                        type="email"
                        v-model="testEmailForm.test_email"
                        required
                        placeholder="Enter your email (e.g. admin@example.com)"
                        class="flex-1 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                    />
                    <button
                        type="submit"
                        :disabled="testEmailForm.processing"
                        class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold shadow-sm transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <svg v-if="testEmailForm.processing" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ testEmailForm.processing ? 'Sending...' : 'Send Test' }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Developer Attribution -->
        <div class="pt-6 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-400 dark:text-slate-500">
            Developed by <a href="https://skynetdigitalltd.com" target="_blank" rel="noopener noreferrer" class="font-bold text-slate-600 dark:text-slate-200 hover:text-theme transition">Skynet Digital Limited.</a>
        </div>
    </div>
</template>
