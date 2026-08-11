<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    currentTheme:       String,
    customPrimaryHex:   String,
    customSecondaryHex: String,
    companyName:        String,
    companyShortName:   String,
    appTagline:         String,
    currencySymbol:     String,
    supportPhone:       String,
    supportEmail:       String,
    companyLogoUrl:     String,
    themePalettes:      Array,
})

const activeTab        = ref('branding')
const selectedTheme    = ref(props.currentTheme || 'skynet')
const customPrimary   = ref(props.customPrimaryHex || '#7B00FF')
const customSecondary = ref(props.customSecondaryHex || '#FBA43D')
const logoPreview      = ref(props.companyLogoUrl || null)

const form = useForm({
    primary_color_theme:  props.currentTheme || 'skynet',
    custom_primary_hex:   props.customPrimaryHex || '#7B00FF',
    custom_secondary_hex: props.customSecondaryHex || '#FBA43D',
    company_name:         props.companyName || 'Skynet POS',
    company_short_name:   props.companyShortName || 'Skynet',
    app_tagline:          props.appTagline || 'Digital POS & Inventory Terminal',
    currency_symbol:      props.currencySymbol || '₦',
    support_phone:        props.supportPhone || '+234 803 207 2831',
    support_email:        props.supportEmail || 'support@skynetdigitalltd.com',
    company_logo:         null,
})

function applyDynamicPreview(themeKey, hexPVal, hexSVal) {
    if (typeof document === 'undefined') return

    const rootStyle = document.documentElement.style

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
    applyDynamicPreview(themeKey, customPrimary.value, customSecondary.value)
}

function updateCustomPrimary(hexVal) {
    selectedTheme.value = 'custom'
    customPrimary.value = hexVal
    form.primary_color_theme = 'custom'
    form.custom_primary_hex  = hexVal
    applyDynamicPreview('custom', hexVal, customSecondary.value)
}

function updateCustomSecondary(hexVal) {
    selectedTheme.value = 'custom'
    customSecondary.value = hexVal
    form.primary_color_theme  = 'custom'
    form.custom_secondary_hex = hexVal
    applyDynamicPreview('custom', customPrimary.value, hexVal)
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
            applyDynamicPreview(form.primary_color_theme, form.custom_primary_hex, form.custom_secondary_hex)
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
    </div>
</template>
