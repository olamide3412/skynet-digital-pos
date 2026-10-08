<script setup>
import { ref, watch, computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    plans: {
        type: Array,
        default: () => [],
    },
})

// Active Plan Selection
const selectedPlanId = ref(props.plans[0]?.id || null)

const selectedPlan = computed(() => {
    return props.plans.find(p => p.id === selectedPlanId.value) || props.plans[0] || null
})

// Form instance for the selected plan
function createFormData(plan) {
    const caps = plan?.capabilities || {}
    const isAiUnmetered = caps.ai_credits_monthly === null
    const isBranchesUnlimited = caps.max_branches === null
    const isUsersUnlimited = caps.max_users_per_branch === null

    return {
        ai_credits_monthly: isAiUnmetered ? 100 : (caps.ai_credits_monthly ?? 25),
        is_ai_unmetered: isAiUnmetered,
        ai_credit_pool_type: caps.ai_credit_pool_type || 'per_branch',
        max_branches: isBranchesUnlimited ? 5 : (caps.max_branches ?? 1),
        is_branches_unlimited: isBranchesUnlimited,
        max_users_per_branch: isUsersUnlimited ? 20 : (caps.max_users_per_branch ?? 5),
        is_users_unlimited: isUsersUnlimited,

        // Feature Flags
        feature_ecommerce_integration: Boolean(caps.feature_ecommerce_integration),
        feature_multi_branch: Boolean(caps.feature_multi_branch),
        feature_debt_customer_accounts: Boolean(caps.feature_debt_customer_accounts),
        feature_discounts: Boolean(caps.feature_discounts),
        feature_batch_expiry_tracking: Boolean(caps.feature_batch_expiry_tracking),
        feature_imei_tracking: Boolean(caps.feature_imei_tracking),
        feature_barcode_label_printing: Boolean(caps.feature_barcode_label_printing),
        feature_inventory_import: Boolean(caps.feature_inventory_import),
        feature_advanced_staff_permissions: Boolean(caps.feature_advanced_staff_permissions),
        feature_ai_chat_assistant: Boolean(caps.feature_ai_chat_assistant),
        feature_ai_reorder_prediction: Boolean(caps.feature_ai_reorder_prediction),
        feature_broadcast_notifications: Boolean(caps.feature_broadcast_notifications),
    }
}

const form = useForm(createFormData(selectedPlan.value))

// When switching plans, re-populate the form
watch(selectedPlanId, () => {
    if (selectedPlan.value) {
        const data = createFormData(selectedPlan.value)
        Object.keys(data).forEach(key => {
            form[key] = data[key]
        })
        form.clearErrors()
    }
})

function selectPlan(planId) {
    selectedPlanId.value = planId
}

function resetForm() {
    if (selectedPlan.value) {
        const data = createFormData(selectedPlan.value)
        Object.keys(data).forEach(key => {
            form[key] = data[key]
        })
        form.clearErrors()
    }
}

function submit() {
    if (!selectedPlan.value) return
    form.put(route('superadmin.plan-capabilities.update', selectedPlan.value.id), {
        preserveScroll: true,
    })
}

// Feature definition list with icons and descriptions
const featureList = [
    {
        key: 'feature_ecommerce_integration',
        title: 'Ecommerce Integration',
        desc: 'Shopify & WooCommerce online catalog sync, webhooks, and automatic stock deduction.',
        icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        color: 'text-indigo-500 dark:text-indigo-400',
    },
    {
        key: 'feature_multi_branch',
        title: 'Multi-Branch Operations',
        desc: 'Inter-branch stock transfers, remote branch inventory lookup, and central administration.',
        icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        color: 'text-blue-500 dark:text-blue-400',
    },
    {
        key: 'feature_debt_customer_accounts',
        title: 'Customer Debt & Credit Accounts',
        desc: 'Customer credit ledger, outstanding debt balances, and payment repayment tracking.',
        icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        color: 'text-amber-500 dark:text-amber-400',
    },
    {
        key: 'feature_discounts',
        title: 'Discounts & Promotions Matrix',
        desc: 'POS cashier discount overrides, promotion rules, and invoice markdown permissions.',
        icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        color: 'text-emerald-500 dark:text-emerald-400',
    },
    {
        key: 'feature_batch_expiry_tracking',
        title: 'Batch Numbers & Expiry Tracking',
        desc: 'Lot/batch number management, perishable expiry warnings, and FIFO stock prioritization.',
        icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        color: 'text-rose-500 dark:text-rose-400',
    },
    {
        key: 'feature_imei_tracking',
        title: 'IMEI & Serial Number Tracking',
        desc: 'Unique serial / IMEI verification for phone and electronics sales, returns, and warranties.',
        icon: 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
        color: 'text-cyan-500 dark:text-cyan-400',
    },
    {
        key: 'feature_barcode_label_printing',
        title: 'Barcode Label Printing',
        desc: 'Direct thermal barcode generator and price sticker printing from the POS terminal.',
        icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
        color: 'text-teal-500 dark:text-teal-400',
    },
    {
        key: 'feature_inventory_import',
        title: 'Batch Inventory Import Library',
        desc: 'Super Admin and Branch CSV/Excel product catalog import templates and mapper.',
        icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        color: 'text-sky-500 dark:text-sky-400',
    },
    {
        key: 'feature_advanced_staff_permissions',
        title: 'Advanced Staff Permissions',
        desc: 'Granular cashier rights, item void restrictions, price change locks, and supervisor pin codes.',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        color: 'text-purple-500 dark:text-purple-400',
    },
    {
        key: 'feature_ai_chat_assistant',
        title: 'Ask-Your-Business AI Copilot',
        desc: 'Natural language querying for business analytics, staff queries, and AI assistants.',
        icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        color: 'text-violet-500 dark:text-violet-400',
    },
    {
        key: 'feature_ai_reorder_prediction',
        title: 'AI Reorder Predictions',
        desc: 'Automated sales velocity forecasting and predictive inventory reorder recommendations.',
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        color: 'text-pink-500 dark:text-pink-400',
    },
    {
        key: 'feature_broadcast_notifications',
        title: 'Broadcast System Announcements',
        desc: 'Receive urgent platform notifications and broadcast updates from Super Admin.',
        icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
        color: 'text-amber-500 dark:text-amber-400',
    },
]
</script>

<template>
    <Head title="Plan Capabilities & System Limits" />

    <div class="space-y-8 pb-16">
        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300">
                        Operational Engine
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Machine Rules & Limits
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                    Plan Capabilities & Entitlements
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">
                    Configure machine limits and feature access rules enforced by the backend engine across all subscribed organizations. Changes take effect immediately.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    :href="route('superadmin.pricing-plans.index')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 shadow-sm transition"
                >
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Marketing Homepage Display
                </Link>
            </div>
        </div>

        <!-- Plan Selection Tabs -->
        <div class="space-y-3">
            <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Select Pricing Plan Tier
            </label>
            <div class="flex flex-wrap gap-2.5">
                <button
                    v-for="plan in plans"
                    :key="plan.id"
                    type="button"
                    @click="selectPlan(plan.id)"
                    :class="[
                        'relative px-4 py-3 rounded-xl border text-left transition-all flex flex-col min-w-[150px] shadow-sm',
                        selectedPlanId === plan.id
                            ? 'bg-indigo-600 border-indigo-600 text-white shadow-indigo-500/20 ring-2 ring-indigo-500/30'
                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600'
                    ]"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-bold text-sm tracking-tight truncate">
                            {{ plan.name }}
                        </span>
                        <span
                            v-if="plan.is_highlighted"
                            :class="[
                                'text-[10px] uppercase font-bold px-1.5 py-0.5 rounded-full',
                                selectedPlanId === plan.id ? 'bg-indigo-700 text-indigo-100' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                            ]"
                        >
                            Featured
                        </span>
                    </div>

                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span :class="selectedPlanId === plan.id ? 'text-indigo-100' : 'text-gray-500 dark:text-gray-400'">
                            {{ plan.subscriptions_count || 0 }} {{ plan.subscriptions_count === 1 ? 'client' : 'clients' }}
                        </span>
                        <span
                            :class="[
                                'inline-block w-2 h-2 rounded-full',
                                plan.is_active ? (selectedPlanId === plan.id ? 'bg-emerald-300' : 'bg-emerald-500') : 'bg-gray-400'
                            ]"
                            :title="plan.is_active ? 'Active on platform' : 'Inactive'"
                        />
                    </div>
                </button>
            </div>
        </div>

        <div v-if="!selectedPlan" class="p-12 text-center text-gray-500 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700">
            No pricing plans found. Please seed or add pricing plans first.
        </div>

        <!-- Main Form Panel for Selected Plan -->
        <form v-else @submit.prevent="submit" class="space-y-8">
            <!-- Plan Context Banner -->
            <div class="bg-gradient-to-r from-gray-900 to-gray-800 dark:from-gray-800 dark:to-gray-900 rounded-2xl p-6 text-white shadow-sm border border-gray-700">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-bold tracking-tight">
                                {{ selectedPlan.name }} Tier Engine Rules
                            </h2>
                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-white/10 text-gray-200 border border-white/20">
                                Order: #{{ selectedPlan.sort_order }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-300 mt-1">
                            {{ selectedPlan.tagline || 'Standard subscription tier configuration.' }}
                        </p>
                    </div>

                    <!-- Commercial Reference (Read-only reference from marketing display) -->
                    <div class="flex items-center gap-4 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-xs">
                        <div>
                            <div class="text-gray-400 font-medium">Public First Year</div>
                            <div class="text-sm font-bold text-white">{{ selectedPlan.first_year_price }}</div>
                        </div>
                        <div class="w-px h-8 bg-white/10" />
                        <div>
                            <div class="text-gray-400 font-medium">Public Renewal</div>
                            <div class="text-sm font-bold text-white">{{ selectedPlan.renewal_price }}</div>
                        </div>
                        <div class="w-px h-8 bg-white/10" />
                        <div>
                            <div class="text-gray-400 font-medium">Subscribed Orgs</div>
                            <div class="text-sm font-bold text-emerald-400">{{ selectedPlan.subscriptions_count || 0 }} Active</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 1: AI Credits & Metering Engine -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm space-y-6">
                <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700/80 pb-4">
                    <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            AI Credits & Monthly Quota
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Governs baseline quotas available for natural language queries and predictive recommendations.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Monthly Quota Input -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Monthly AI Credits Quota
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    v-model="form.is_ai_unmetered"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                Unmetered (Unlimited Credits)
                            </label>
                        </div>

                        <div class="relative">
                            <input
                                type="number"
                                v-model.number="form.ai_credits_monthly"
                                :disabled="form.is_ai_unmetered"
                                min="0"
                                step="1"
                                placeholder="e.g. 60"
                                :class="[
                                    'w-full px-4 py-2.5 rounded-xl border text-sm transition',
                                    form.is_ai_unmetered
                                        ? 'bg-gray-100 dark:bg-gray-700/50 text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                                        : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500'
                                ]"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400 font-medium">
                                {{ form.is_ai_unmetered ? 'UNMETERED' : 'credits / mo' }}
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ form.is_ai_unmetered
                                ? 'Subscribed organizations have unlimited AI credits with no monthly deduction ceiling.'
                                : 'Assigned to organizations on the 1st day of every month or upon subscription start.'
                            }}
                        </p>
                        <div v-if="form.errors.ai_credits_monthly" class="text-xs text-red-500">
                            {{ form.errors.ai_credits_monthly }}
                        </div>
                    </div>

                    <!-- AI Credit Pool Distribution Type -->
                    <div class="space-y-3">
                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Credit Pool Distribution
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                :class="[
                                    'cursor-pointer border rounded-xl p-3.5 flex flex-col justify-between transition',
                                    form.ai_credit_pool_type === 'per_branch'
                                        ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900'
                                ]"
                            >
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">
                                        Per Branch Pool
                                    </span>
                                    <input
                                        type="radio"
                                        value="per_branch"
                                        v-model="form.ai_credit_pool_type"
                                        class="text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600"
                                    />
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Every branch receives its own independent monthly quota of credits.
                                </p>
                            </label>

                            <label
                                :class="[
                                    'cursor-pointer border rounded-xl p-3.5 flex flex-col justify-between transition',
                                    form.ai_credit_pool_type === 'account_wide'
                                        ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900'
                                ]"
                            >
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">
                                        Shared Account Pool
                                    </span>
                                    <input
                                        type="radio"
                                        value="account_wide"
                                        v-model="form.ai_credit_pool_type"
                                        class="text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600"
                                    />
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    All branches draw from one shared central organization credit ledger.
                                </p>
                            </label>
                        </div>

                        <div v-if="form.errors.ai_credit_pool_type" class="text-xs text-red-500">
                            {{ form.errors.ai_credit_pool_type }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Scale & Capacity Hard Limits -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm space-y-6">
                <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700/80 pb-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Operational Capacity Limits
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Enforces physical entity limits preventing organizations from creating more branches or staff than entitled.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Max Branches -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Maximum Branches Allowed
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    v-model="form.is_branches_unlimited"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                Unlimited Branches
                            </label>
                        </div>

                        <div class="relative">
                            <input
                                type="number"
                                v-model.number="form.max_branches"
                                :disabled="form.is_branches_unlimited"
                                min="1"
                                step="1"
                                placeholder="e.g. 1"
                                :class="[
                                    'w-full px-4 py-2.5 rounded-xl border text-sm transition',
                                    form.is_branches_unlimited
                                        ? 'bg-gray-100 dark:bg-gray-700/50 text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                                        : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500'
                                ]"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400 font-medium">
                                {{ form.is_branches_unlimited ? 'UNLIMITED' : 'branches' }}
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Attempts to add branches beyond this number will be blocked with a plan upgrade prompt.
                        </p>
                        <div v-if="form.errors.max_branches" class="text-xs text-red-500">
                            {{ form.errors.max_branches }}
                        </div>
                    </div>

                    <!-- Max Users Per Branch -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Maximum Staff Users Per Branch
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    v-model="form.is_users_unlimited"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                Unlimited Users
                            </label>
                        </div>

                        <div class="relative">
                            <input
                                type="number"
                                v-model.number="form.max_users_per_branch"
                                :disabled="form.is_users_unlimited"
                                min="1"
                                step="1"
                                placeholder="e.g. 5"
                                :class="[
                                    'w-full px-4 py-2.5 rounded-xl border text-sm transition',
                                    form.is_users_unlimited
                                        ? 'bg-gray-100 dark:bg-gray-700/50 text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                                        : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500'
                                ]"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400 font-medium">
                                {{ form.is_users_unlimited ? 'UNLIMITED' : 'users / branch' }}
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Enforces maximum active cashiers and managers permitted in each physical store.
                        </p>
                        <div v-if="form.errors.max_users_per_branch" class="text-xs text-red-500">
                            {{ form.errors.max_users_per_branch }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Feature Entitlements & Access Switches -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700/80 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                Feature Access Switches
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Toggle access to specific POS modules and backend capabilities for this plan tier.
                            </p>
                        </div>
                    </div>

                    <!-- Quick Bulk Toggles -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="featureList.forEach(f => form[f.key] = true)"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
                        >
                            Enable All
                        </button>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        <button
                            type="button"
                            @click="featureList.forEach(f => form[f.key] = false)"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:underline font-medium"
                        >
                            Disable All
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="feat in featureList"
                        :key="feat.key"
                        @click="form[feat.key] = !form[feat.key]"
                        :class="[
                            'cursor-pointer border rounded-2xl p-4 transition-all flex flex-col justify-between select-none',
                            form[feat.key]
                                ? 'bg-white dark:bg-gray-800/90 border-indigo-500/80 shadow-sm ring-1 ring-indigo-500/30'
                                : 'bg-gray-50/70 dark:bg-gray-900/40 border-gray-200 dark:border-gray-800 opacity-75 hover:opacity-100'
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 dark:bg-gray-700" :class="feat.color">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="feat.icon" />
                                    </svg>
                                </div>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white leading-tight">
                                    {{ feat.title }}
                                </h4>
                            </div>

                            <!-- iOS-style Switch -->
                            <button
                                type="button"
                                @click.stop="form[feat.key] = !form[feat.key]"
                                :class="[
                                    'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                                    form[feat.key] ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-700'
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        form[feat.key] ? 'translate-x-4' : 'translate-x-0'
                                    ]"
                                />
                            </button>
                        </div>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2">
                            {{ feat.desc }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bottom Sticky Save Bar -->
            <div class="sticky bottom-4 z-20 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-700 p-4 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2.5 text-xs text-gray-600 dark:text-gray-300">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" />
                    <span>
                        Editing live engine rules for <strong>{{ selectedPlan.name }}</strong> tier. Changes apply automatically to active subscribers.
                    </span>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                    <button
                        type="button"
                        @click="resetForm"
                        :disabled="form.processing"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition"
                    >
                        Reset Defaults
                    </button>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-500/30 shadow-md shadow-indigo-500/20 disabled:opacity-50 transition"
                    >
                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                        </svg>
                        Save {{ selectedPlan.name }} Capabilities
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
