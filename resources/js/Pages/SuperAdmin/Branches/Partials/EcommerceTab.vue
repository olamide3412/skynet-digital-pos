<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
    branch: Object,
    commerceIntegration: Object,
    ecommerceCapabilityEnabled: Boolean,
    commerceApiBaseUrl: String,
    newCommerceToken: String,
})

const page = usePage()

const copiedField = ref('')
const revealedToken = ref(props.newCommerceToken || page.props.new_commerce_token || page.props.flash?.new_commerce_token || '')
const showNewTokenModal = ref(false)

const activeSecret = computed(() => {
    return revealedToken.value || props.newCommerceToken || page.props.new_commerce_token || page.props.flash?.new_commerce_token || ''
})

watch(
    [() => props.newCommerceToken, () => page.props.new_commerce_token, () => page.props.flash?.new_commerce_token],
    ([propToken, rootToken, flashToken]) => {
        const token = propToken || rootToken || flashToken
        if (token) {
            revealedToken.value = token
            showNewTokenModal.value = true
        }
    },
    { immediate: true }
)

const copyToClipboard = (text, field) => {
    if (!text) return
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(text)
    }
    copiedField.value = field
    setTimeout(() => {
        if (copiedField.value === field) {
            copiedField.value = ''
        }
    }, 2500)
}

const apiBaseUrl = computed(() => {
    return props.commerceIntegration?.api_base_url 
        || props.commerceApiBaseUrl 
        || (typeof window !== 'undefined' && window.location?.origin ? `${window.location.origin}/api/commerce/v1` : '/api/commerce/v1')
})

const copyAllCredentials = () => {
    const token = activeSecret.value || ''
    const lines = [
        `POS API Base URL: ${apiBaseUrl.value}`,
        `Client ID: ${props.commerceIntegration?.client_id || ''}`,
    ]
    if (token) {
        lines.push(`API Secret: ${token}`)
    }
    copyToClipboard(lines.join('\n'), 'all')
}

// ── Setup Form ─────────────────────────────────────────────────────────────
const setupForm = useForm({
    name: `${props.branch.name} E-Commerce`,
    platform: 'custom',
    reservation_ttl_minutes: 30,
    webhook_url: '',
})

const submitSetup = () => {
    setupForm.post(route('superadmin.branches.ecommerce.store', { branch: props.branch.slug }), {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            const token = responsePage.props.new_commerce_token || responsePage.props.flash?.new_commerce_token
            if (token) {
                revealedToken.value = token
                showNewTokenModal.value = true
            }
        },
    })
}

// ── Settings Form & Modal ──────────────────────────────────────────────────
const isEditingSettings = ref(false)
const settingsForm = useForm({
    name: props.commerceIntegration?.name || '',
    platform: props.commerceIntegration?.platform || 'custom',
    reservation_ttl_minutes: props.commerceIntegration?.settings?.reservation_ttl_minutes || 30,
    webhook_url: props.commerceIntegration?.webhook_url || '',
})

const openEditSettings = () => {
    settingsForm.name = props.commerceIntegration?.name || ''
    settingsForm.platform = props.commerceIntegration?.platform || 'custom'
    settingsForm.reservation_ttl_minutes = props.commerceIntegration?.settings?.reservation_ttl_minutes || 30
    settingsForm.webhook_url = props.commerceIntegration?.webhook_url || ''
    isEditingSettings.value = true
}

const submitUpdateSettings = () => {
    settingsForm.put(route('superadmin.branches.ecommerce.settings.update', { branch: props.branch.slug }), {
        preserveScroll: true,
        onSuccess: () => { isEditingSettings.value = false },
    })
}

// ── Webhook Testing ────────────────────────────────────────────────────────
const isTestingWebhook = ref(false)
const executeTestWebhook = () => {
    isTestingWebhook.value = true
    router.post(
        route('superadmin.branches.ecommerce.test-webhook', { branch: props.branch.slug }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isTestingWebhook.value = false
            },
        }
    )
}

// ── Action State & Modals ──────────────────────────────────────────────────
const isActionProcessing = ref(false)
const showDeactivateModal = ref(false)
const showDisconnectModal = ref(false)
const showRegenerateModal = ref(false)

const executeToggleStatus = () => {
    isActionProcessing.value = true
    router.post(
        route('superadmin.branches.ecommerce.toggle-status', { branch: props.branch.slug }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isActionProcessing.value = false
                showDeactivateModal.value = false
            },
        }
    )
}

const executeRegenerateToken = () => {
    isActionProcessing.value = true
    router.post(
        route('superadmin.branches.ecommerce.regenerate-token', { branch: props.branch.slug }),
        {},
        {
            preserveScroll: true,
            onSuccess: (responsePage) => {
                const token = responsePage.props.new_commerce_token || responsePage.props.flash?.new_commerce_token
                if (token) {
                    revealedToken.value = token
                    showNewTokenModal.value = true
                }
            },
            onFinish: () => {
                isActionProcessing.value = false
                showRegenerateModal.value = false
            },
        }
    )
}

const executeDisconnect = () => {
    isActionProcessing.value = true
    router.delete(
        route('superadmin.branches.ecommerce.destroy', { branch: props.branch.slug }),
        {
            preserveScroll: true,
            onFinish: () => {
                isActionProcessing.value = false
                showDisconnectModal.value = false
                revealedToken.value = ''
            },
        }
    )
}
</script>

<template>
    <div class="space-y-6">
        <!-- ── PLAN CAPABILITY LOCKED BANNER ──────────────────────────────── -->
        <div v-if="!ecommerceCapabilityEnabled" class="rounded-2xl border border-amber-300 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-950/30 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 text-2xl font-bold">
                    🔒
                </div>
                <div>
                    <h3 class="text-lg font-bold text-amber-900 dark:text-amber-200">
                        E-Commerce Integration Plan Required
                    </h3>
                    <p class="text-sm text-amber-800 dark:text-amber-300/80 mt-1 max-w-2xl">
                        This branch belongs to an organization whose subscription plan does not currently include the
                        <strong class="font-semibold">Independent E-Commerce Module</strong>. Upgrading to the
                        <strong>Bronze Plan or higher</strong> unlocks atomic catalog synchronization, live multi-item checkout stock reservation, and real-time inventory deductions for Shopify, WooCommerce, and custom web stores.
                    </p>
                </div>
            </div>
        </div>

        <div v-else>
            <!-- ── NO INTEGRATION: CONNECT FORM ──────────────────────────────── -->
            <div v-if="!commerceIntegration" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl font-bold">
                            🛒
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                Connect E-Commerce Store
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Connect exactly one external web store (Shopify, WooCommerce, custom) to sync catalog items and reserve inventory from branch <strong>{{ branch.name }}</strong>.
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submitSetup" class="mt-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Integration Name
                            </label>
                            <input
                                v-model="setupForm.name"
                                type="text"
                                required
                                class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:outline-none"
                                placeholder="e.g. Main Online Store"
                            />
                            <div v-if="setupForm.errors.name" class="text-red-500 text-xs mt-1">
                                {{ setupForm.errors.name }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Platform
                                </label>
                                <select
                                    v-model="setupForm.platform"
                                    class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:outline-none cursor-pointer"
                                >
                                    <option value="custom">Custom Web Application / Headless API</option>
                                    <option value="shopify">Shopify</option>
                                    <option value="woocommerce">WooCommerce</option>
                                    <option value="magento">Magento</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Reservation Hold TTL (Minutes)
                                </label>
                                <input
                                    v-model.number="setupForm.reservation_ttl_minutes"
                                    type="number"
                                    min="5"
                                    max="120"
                                    class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:outline-none"
                                />
                                <span class="text-[11px] text-slate-400">Time-to-live for checkout stock holds (5–120 min)</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Webhook Notification URL (Optional)
                            </label>
                            <input
                                v-model="setupForm.webhook_url"
                                type="url"
                                class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:outline-none"
                                placeholder="https://ecommerce.example.com/api/webhooks/pos-inventory"
                            />
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="setupForm.processing"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-sm disabled:opacity-50 cursor-pointer"
                            >
                                <span>🚀 Connect Store & Generate Token</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── INTEGRATION ACTIVE / DETAILS VIEW ────────────────────────── -->
            <div v-else class="space-y-6">
                <!-- Status & Overview Header -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white">
                                    {{ commerceIntegration.name }}
                                </h3>
                                <span
                                    :class="commerceIntegration.status === 'active'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold border flex items-center gap-1.5"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :class="commerceIntegration.status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                                    {{ commerceIntegration.status.toUpperCase() }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Platform: <span class="font-semibold text-slate-700 dark:text-slate-200 capitalize">{{ commerceIntegration.platform }}</span> · Connected on {{ commerceIntegration.created_at }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 flex-wrap">
                            <button
                                type="button"
                                @click="openEditSettings"
                                class="px-3.5 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1.5"
                            >
                                <span>⚙️ Settings</span>
                            </button>

                            <button
                                type="button"
                                @click="showDeactivateModal = true"
                                :disabled="isActionProcessing"
                                class="px-3.5 py-2 border rounded-xl text-xs font-semibold transition cursor-pointer"
                                :class="commerceIntegration.status === 'active'
                                    ? 'border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/30'
                                    : 'border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
                            >
                                {{ commerceIntegration.status === 'active' ? '⏸ Deactivate' : '▶ Activate' }}
                            </button>

                            <button
                                type="button"
                                @click="showRegenerateModal = true"
                                :disabled="isActionProcessing"
                                class="px-3.5 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl text-xs font-semibold transition cursor-pointer"
                            >
                                🔄 Regenerate Token
                            </button>

                            <button
                                type="button"
                                @click="showDisconnectModal = true"
                                :disabled="isActionProcessing"
                                class="px-3.5 py-2 border border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-xl text-xs font-semibold transition cursor-pointer"
                            >
                                🗑 Disconnect
                            </button>
                        </div>
                    </div>

                    <!-- Live Stats Counters -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-6 pt-6 border-t border-slate-100 dark:border-slate-800">
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Active Holds</span>
                            <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-0.5">
                                {{ commerceIntegration.stats?.active_reservations ?? 0 }}
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Confirmed</span>
                            <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                                {{ commerceIntegration.stats?.confirmed_reservations ?? 0 }}
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Orders</span>
                            <div class="text-xl font-bold text-slate-900 dark:text-white mt-0.5">
                                {{ commerceIntegration.stats?.total_reservations ?? 0 }}
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Mapped Items</span>
                            <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">
                                {{ commerceIntegration.stats?.mappings_count ?? 0 }}
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Movements</span>
                            <div class="text-xl font-bold text-slate-700 dark:text-slate-300 mt-0.5">
                                {{ commerceIntegration.stats?.movements_count ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════ -->
                <!-- ── CONNECTION DETAILS PANEL (PAIRING CREDENTIALS) ───────────── -->
                <!-- ══════════════════════════════════════════════════════════════ -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border-2 border-indigo-200 dark:border-indigo-900/60 p-6 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl font-bold">
                                🔌
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-base font-bold text-slate-900 dark:text-white">
                                        Connection Details
                                    </h4>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 font-bold uppercase tracking-wider">
                                        Store Config Pairing
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Enter these three values in the ecommerce installation's Store Config screen to complete pairing.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="copyAllCredentials"
                            class="px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition shrink-0 flex items-center gap-1.5 cursor-pointer"
                        >
                            <span>{{ copiedField === 'all' ? '✓ All Copied!' : '📋 Copy All Details' }}</span>
                        </button>
                    </div>

                    <!-- Informational Alert Note -->
                    <div class="bg-indigo-50/80 dark:bg-indigo-950/30 border border-indigo-200/70 dark:border-indigo-800/50 rounded-xl p-3.5 flex items-start gap-3">
                        <span class="text-indigo-600 dark:text-indigo-400 text-base leading-none mt-0.5">💡</span>
                        <div class="text-xs text-indigo-950 dark:text-indigo-200 leading-relaxed">
                            <strong>How to connect:</strong> Copy the <strong>POS API Base URL</strong>, <strong>Client ID</strong>, and <strong>API Secret</strong> below into your companion ecommerce store's <em>Settings &rarr; POS Connection</em> or <em>Store Config</em> page, then click <strong>"Save & Test Connection"</strong>.
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Field 1: POS API Base URL -->
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/50 dark:bg-slate-800/30">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <span>🌐 POS API Base URL</span>
                                </label>
                                <span class="text-[11px] text-slate-400">System-wide Endpoint</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    :value="apiBaseUrl"
                                    class="w-full text-xs font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 select-all focus:outline-none"
                                />
                                <button
                                    type="button"
                                    @click="copyToClipboard(apiBaseUrl, 'baseUrl')"
                                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shrink-0 transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                                >
                                    <span>{{ copiedField === 'baseUrl' ? '✓ Copied' : '📋 Copy URL' }}</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                                Same endpoint for all branch integrations on this POS installation. Requests are automatically scoped by authentication credential.
                            </p>
                        </div>

                        <!-- Field 2: Client ID -->
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/50 dark:bg-slate-800/30">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <span>🆔 Client ID</span>
                                </label>
                                <span class="text-[11px] text-slate-400">Public Branch Key</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    :value="commerceIntegration.client_id || 'Generating...'"
                                    class="w-full text-xs font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 select-all focus:outline-none"
                                />
                                <button
                                    type="button"
                                    @click="copyToClipboard(commerceIntegration.client_id, 'clientId')"
                                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shrink-0 transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                                >
                                    <span>{{ copiedField === 'clientId' ? '✓ Copied' : '📋 Copy Client ID' }}</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                                Unique persistent identifier for <strong>{{ branch.name }}</strong> (ID: {{ branch.id }}).
                            </p>
                        </div>

                        <!-- Field 3: API Secret / Token -->
                        <div
                            class="rounded-xl border p-4 transition"
                            :class="activeSecret
                                ? 'border-emerald-400 dark:border-emerald-600 bg-emerald-50/60 dark:bg-emerald-950/30 shadow-xs'
                                : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30'"
                        >
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <label class="text-xs font-bold flex items-center gap-1.5" :class="activeSecret ? 'text-emerald-900 dark:text-emerald-200' : 'text-slate-700 dark:text-slate-300'">
                                    <span>🔑 API Secret (Bearer Access Token)</span>
                                </label>
                                <span
                                    v-if="activeSecret"
                                    class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-200 dark:bg-emerald-800 text-emerald-900 dark:text-emerald-100 font-bold uppercase tracking-wider animate-pulse"
                                >
                                    Active New Secret · Copy Now
                                </span>
                                <span v-else class="text-[11px] text-slate-400">
                                    Last token used: {{ commerceIntegration.last_token_used_at || 'Never' }}
                                </span>
                            </div>

                            <!-- Case A: Token is freshly generated and active -->
                            <div v-if="activeSecret" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="activeSecret"
                                        class="w-full text-xs font-mono rounded-xl border border-emerald-300 dark:border-emerald-700 bg-white dark:bg-slate-900 text-emerald-950 dark:text-emerald-100 px-3.5 py-2.5 select-all focus:outline-none ring-2 ring-emerald-500/20"
                                    />
                                    <button
                                        type="button"
                                        @click="copyToClipboard(activeSecret, 'secret')"
                                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shrink-0 transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                                    >
                                        <span>{{ copiedField === 'secret' ? '✓ Copied' : '📋 Copy Secret' }}</span>
                                    </button>
                                </div>
                                <div class="text-[11px] text-emerald-800 dark:text-emerald-300 bg-emerald-100/60 dark:bg-emerald-900/40 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800/50 flex items-center justify-between gap-2">
                                    <span>⚠️ <strong>Copy this secret now:</strong> This token will not be retrievable again once you refresh this page. Paste it into your ecommerce store configuration.</span>
                                    <button
                                        type="button"
                                        @click="showNewTokenModal = true"
                                        class="underline hover:text-emerald-950 dark:hover:text-white font-semibold shrink-0 cursor-pointer text-xs"
                                    >
                                        View Modal
                                    </button>
                                </div>
                            </div>

                            <!-- Case B: Existing token is masked -->
                            <div v-else class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="password"
                                        readonly
                                        value="••••••••••••••••••••••••••••••••••••••••••••••••"
                                        class="w-full text-xs font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/80 text-slate-500 px-3.5 py-2.5 select-none focus:outline-none cursor-not-allowed"
                                    />
                                    <button
                                        type="button"
                                        @click="showRegenerateModal = true"
                                        :disabled="isActionProcessing"
                                        class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold shrink-0 transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50"
                                    >
                                        <span>🔄 Regenerate Secret</span>
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    For security, API Secret tokens cannot be retrieved once created. If you did not copy it or need to connect a new store, click <strong>Regenerate Secret</strong> above to generate and reveal a new token immediately.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TECHNICAL ENDPOINTS REFERENCE & DETAILS ──────────────────── -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Scope & Details Card -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>🛡️ Branch Isolation & Hold TTL</span>
                        </h4>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Assigned Branch Scope
                            </label>
                            <div class="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60 space-y-1">
                                <div><strong>Branch:</strong> {{ branch.name }} (ID: {{ branch.id }})</div>
                                <div class="text-slate-500 dark:text-slate-400">
                                    All incoming catalog and inventory requests are authenticated server-to-server and strictly bounded to this branch. <code>back_store_qty</code> is shielded from the API; only <code>front_store_qty - reserved_qty</code> is exposed.
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Cart Hold Expiry Window (TTL)
                            </label>
                            <div class="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                                <strong>{{ commerceIntegration.settings?.reservation_ttl_minutes || 30 }} minutes</strong>
                                <p class="text-slate-500 dark:text-slate-400 mt-0.5">
                                    Unconfirmed checkout reservations automatically expire and release stock back to the physical POS floor if the customer does not complete payment.
                                </p>
                            </div>
                        </div>

                        <div v-if="commerceIntegration.webhook_url">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">
                                    Configured Webhook URL
                                </label>
                                <button
                                    type="button"
                                    @click="executeTestWebhook"
                                    :disabled="isTestingWebhook"
                                    class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                >
                                    <span v-if="isTestingWebhook">Sending Test…</span>
                                    <span v-else>⚡ Send Test Webhook</span>
                                </button>
                            </div>
                            <div class="text-xs font-mono text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60 truncate">
                                {{ commerceIntegration.webhook_url }}
                            </div>
                        </div>
                    </div>

                    <!-- Developer Quick Cheatsheet -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span>📡 REST API Endpoints Quick Reference</span>
                            </h4>
                            <span class="text-[11px] font-mono text-slate-400">Bearer Auth Required</span>
                        </div>

                        <div class="space-y-2 text-xs font-mono">
                            <!-- Ping / Connection Verification -->
                            <div class="p-2.5 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold">GET</span>
                                    <span class="ml-2 text-indigo-900 dark:text-indigo-200 font-semibold">/ping</span>
                                </div>
                                <span class="text-indigo-600 dark:text-indigo-400 text-[11px] font-sans font-medium">Save & Test Connection</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-bold">GET</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/catalog</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Full item sync</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-bold">GET</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/catalog/{id}/stock</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Live available qty</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold">POST</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/reservations</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Atomic hold</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold">POST</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/reservations/confirm</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Deduct inventory</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 font-bold">POST</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/reservations/release</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Release cart</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold">POST</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/mappings/register</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Register deletion protection links</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 font-bold">POST</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/mappings/unregister</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Remove deletion protection</span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between">
                                <div>
                                    <span class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-bold">POST</span>
                                    <span class="ml-2 text-slate-800 dark:text-slate-200">/mappings/reconcile</span>
                                </div>
                                <span class="text-slate-400 text-[11px] font-sans">Sync active mappings</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── MODAL: EDIT SETTINGS ────────────────────────────────────── -->
                <div v-if="isEditingSettings" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                Edit Integration Settings
                            </h3>
                            <button
                                type="button"
                                @click="isEditingSettings = false"
                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold cursor-pointer"
                            >
                                &times;
                            </button>
                        </div>

                        <form @submit.prevent="submitUpdateSettings" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Integration Name
                                </label>
                                <input
                                    v-model="settingsForm.name"
                                    type="text"
                                    required
                                    class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Platform
                                    </label>
                                    <select
                                        v-model="settingsForm.platform"
                                        class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer"
                                    >
                                        <option value="custom">Custom Web Application</option>
                                        <option value="shopify">Shopify</option>
                                        <option value="woocommerce">WooCommerce</option>
                                        <option value="magento">Magento</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Hold TTL (Minutes)
                                    </label>
                                    <input
                                        v-model.number="settingsForm.reservation_ttl_minutes"
                                        type="number"
                                        min="5"
                                        max="120"
                                        class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Webhook URL (Optional)
                                </label>
                                <input
                                    v-model="settingsForm.webhook_url"
                                    type="url"
                                    class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    placeholder="https://ecommerce.example.com/api/webhooks/pos-inventory"
                                />
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    @click="isEditingSettings = false"
                                    class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="settingsForm.processing"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition cursor-pointer disabled:opacity-50"
                                >
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── MODAL: CONFIRM DEACTIVATE / ACTIVATE ────────────────────── -->
                <div v-if="showDeactivateModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto"
                             :class="commerceIntegration.status === 'active' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'">
                            {{ commerceIntegration.status === 'active' ? '⏸' : '▶' }}
                        </div>
                        <div class="text-center space-y-2">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                {{ commerceIntegration.status === 'active' ? 'Deactivate E-Commerce Integration?' : 'Activate E-Commerce Integration?' }}
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ commerceIntegration.status === 'active'
                                    ? 'This temporarily pauses all incoming sync requests and stock holds from the external store. Credentials and settings remain saved.'
                                    : 'This reactivates server-to-server API access for external web store syncing and reservations.' }}
                            </p>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button
                                type="button"
                                @click="showDeactivateModal = false"
                                :disabled="isActionProcessing"
                                class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="executeToggleStatus"
                                :disabled="isActionProcessing"
                                class="px-5 py-2.5 font-bold rounded-xl text-xs transition shadow-md cursor-pointer disabled:opacity-50 text-white"
                                :class="commerceIntegration.status === 'active' ? 'bg-amber-600 hover:bg-amber-500 shadow-amber-600/20' : 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/20'"
                            >
                                <span v-if="isActionProcessing">Processing...</span>
                                <span v-else>{{ commerceIntegration.status === 'active' ? 'Yes, Deactivate' : 'Yes, Activate' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── MODAL: CONFIRM REGENERATE TOKEN ────────────────────────── -->
                <div v-if="showRegenerateModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mx-auto">
                            🔄
                        </div>
                        <div class="text-center space-y-2">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                Regenerate API Secret Token?
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                Regenerating will <strong class="text-amber-600 dark:text-amber-400">immediately invalidate</strong> the current API token. Any active integration connecting to this branch will need the new token to authenticate.
                            </p>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button
                                type="button"
                                @click="showRegenerateModal = false"
                                :disabled="isActionProcessing"
                                class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="executeRegenerateToken"
                                :disabled="isActionProcessing"
                                class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-amber-600/20 cursor-pointer disabled:opacity-50"
                            >
                                <span v-if="isActionProcessing">Generating...</span>
                                <span v-else>Yes, Regenerate Token</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── MODAL: CONFIRM DISCONNECT ──────────────────────────────── -->
                <div v-if="showDisconnectModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div class="bg-white dark:bg-slate-900 border border-red-200 dark:border-red-900/60 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                        <div class="w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 flex items-center justify-center text-2xl mx-auto">
                            🗑️
                        </div>
                        <div class="text-center space-y-2">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                Disconnect E-Commerce Store?
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                This will remove the external store connection from <strong>{{ branch.name }}</strong> and permanently revoke its API access credentials.
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700">
                                Your physical branch inventory, items, and sales records remain 100% safe. You can connect a new store anytime.
                            </p>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button
                                type="button"
                                @click="showDisconnectModal = false"
                                :disabled="isActionProcessing"
                                class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="executeDisconnect"
                                :disabled="isActionProcessing"
                                class="px-5 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-red-600/20 cursor-pointer disabled:opacity-50"
                            >
                                <span v-if="isActionProcessing">Disconnecting...</span>
                                <span v-else>Yes, Disconnect Store</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── MODAL: NEW API SECRET GENERATED (PROMINENT REVEAL) ──────── -->
                <div v-if="showNewTokenModal && activeSecret" class="fixed inset-0 bg-slate-900/70 dark:bg-slate-950/85 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div class="bg-white dark:bg-slate-900 border-2 border-emerald-400 dark:border-emerald-600 rounded-3xl max-w-xl w-full p-6 space-y-5 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 text-2xl font-bold">
                                🔑
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                        New API Secret Token
                                    </h3>
                                    <span class="text-[10px] px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 font-bold uppercase tracking-wider">
                                        Copy Now
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                                    Your server-to-server API Secret token has been generated. This token is <strong class="underline font-bold text-emerald-700 dark:text-emerald-300">only displayed once</strong> and cannot be retrieved again after you reload this page.
                                </p>
                            </div>
                        </div>

                        <!-- Credentials Box -->
                        <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 space-y-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    POS API Base URL
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="apiBaseUrl"
                                        class="w-full text-xs font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-3 py-2 select-all focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="copyToClipboard(apiBaseUrl, 'modalUrl')"
                                        class="px-3 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-800 dark:text-white rounded-xl text-xs font-semibold shrink-0 transition cursor-pointer"
                                    >
                                        {{ copiedField === 'modalUrl' ? '✓' : 'Copy' }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Client ID
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="commerceIntegration.client_id"
                                        class="w-full text-xs font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-3 py-2 select-all focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="copyToClipboard(commerceIntegration.client_id, 'modalClientId')"
                                        class="px-3 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-800 dark:text-white rounded-xl text-xs font-semibold shrink-0 transition cursor-pointer"
                                    >
                                        {{ copiedField === 'modalClientId' ? '✓' : 'Copy' }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider mb-1 flex items-center justify-between">
                                    <span>API Secret (Access Token)</span>
                                    <span class="text-[10px] text-emerald-600 font-normal">Bearer Token</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="activeSecret"
                                        class="w-full text-xs font-mono font-bold rounded-xl border border-emerald-400 dark:border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-950 dark:text-emerald-100 px-3 py-2 select-all focus:outline-none ring-2 ring-emerald-500/20"
                                    />
                                    <button
                                        type="button"
                                        @click="copyToClipboard(activeSecret, 'modalSecret')"
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shrink-0 transition cursor-pointer shadow-sm flex items-center gap-1.5"
                                    >
                                        <span>{{ copiedField === 'modalSecret' ? '✓ Copied' : '📋 Copy Secret' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            <button
                                type="button"
                                @click="copyAllCredentials"
                                class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 transition cursor-pointer flex items-center gap-1.5"
                            >
                                <span>{{ copiedField === 'all' ? '✓ All Copied!' : '📋 Copy All 3 Details' }}</span>
                            </button>

                            <button
                                type="button"
                                @click="showNewTokenModal = false"
                                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 cursor-pointer"
                            >
                                ✓ I Have Saved My Secret
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
