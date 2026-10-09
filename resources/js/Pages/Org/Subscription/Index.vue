<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import OrgLayout from '@/Layouts/OrgLayout.vue'

defineOptions({ layout: OrgLayout })

const page = usePage()

const props = defineProps({
    organization: Object,
    subscription: Object,
    usageStats: Object,
    pricingPlans: Array,
    payments: Array,
    gateways: Array,
})

// Active Tab
const activeTab = ref('plans') // 'plans' or 'history'

// Checkout Modal State
const showCheckoutModal = ref(false)
const selectedPlan = ref(null)
const checkoutType = ref('renewal') // 'renewal' or 'upgrade'

// Gateway selection (default to first active gateway)
const defaultGateway = computed(() => {
    return props.gateways && props.gateways.length > 0 ? props.gateways[0].id : 'paystack'
})

const checkoutForm = useForm({
    pricing_plan_id: null,
    type: 'renewal',
    gateway: 'paystack',
    _token: '',
})

// Computed price for modal
const modalAmount = computed(() => {
    if (!selectedPlan.value) return 0
    return checkoutType.value === 'renewal'
        ? selectedPlan.value.amount_renewal
        : selectedPlan.value.amount_first_year
})

const openRenewalModal = () => {
    if (!props.subscription) return
    const currentPlan = props.pricingPlans.find(p => p.id === props.subscription.pricing_plan_id)
    if (!currentPlan) return

    selectedPlan.value = currentPlan
    checkoutType.value = 'renewal'
    checkoutForm.pricing_plan_id = currentPlan.id
    checkoutForm.type = 'renewal'
    checkoutForm.gateway = defaultGateway.value
    showCheckoutModal.value = true
}

const openUpgradeModal = (plan) => {
    selectedPlan.value = plan
    checkoutType.value = 'upgrade'
    checkoutForm.pricing_plan_id = plan.id
    checkoutForm.type = 'upgrade'
    checkoutForm.gateway = defaultGateway.value
    showCheckoutModal.value = true
}

const submitCheckout = () => {
    const token = page.props.csrf_token || (typeof document !== 'undefined' ? document.head.querySelector('meta[name="csrf-token"]')?.content : '') || ''
    checkoutForm
        .transform((data) => ({
            ...data,
            _token: token,
        }))
        .post(route('org.subscription.checkout'), {
            headers: token ? { 'X-CSRF-TOKEN': token } : {},
            onFinish: () => {
                // Redirect will be handled by Inertia::location
            },
        })
}

// Helpers
const formatNaira = (amount) => {
    return '₦' + Number(amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

const getStatusBadge = (status) => {
    switch (status) {
        case 'active':
            return { label: 'Active', class: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' }
        case 'grace_period':
            return { label: 'Grace Period (14 Days)', class: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' }
        case 'expired':
            return { label: 'Expired', class: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800' }
        default:
            return { label: 'Active', class: 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700' }
    }
}
</script>

<template>
    <Head title="Subscription & Plans — Organization Portal" />

    <div class="space-y-6 max-w-7xl mx-auto pb-12">
        <!-- ── Page Header ─────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-purple-500/20 text-lg">
                        💳
                    </span>
                    Subscription & Plans
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Manage your organization's subscription tier, review feature limits, and securely renew or upgrade your plan.
                </p>
            </div>

            <!-- Header Quick Action -->
            <div v-if="subscription" class="flex items-center gap-2">
                <button
                    @click="openRenewalModal"
                    :disabled="!gateways || gateways.length === 0"
                    class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md shadow-purple-600/20 transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Renew Plan (1 Year)</span>
                </button>
            </div>
        </div>

        <!-- ── Current Plan Hero Status Card ────────────────────────────── -->
        <div v-if="subscription" class="bg-gradient-to-br from-white via-purple-50/20 to-indigo-50/30 dark:from-slate-900 dark:via-purple-950/10 dark:to-slate-900 border border-purple-200/60 dark:border-purple-900/40 rounded-3xl p-6 sm:p-8 shadow-sm relative overflow-hidden">
            <!-- Background Decorative Blur -->
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-purple-500/10 dark:bg-purple-500/5 blur-3xl pointer-events-none"></div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center relative z-10">
                <!-- Left Details -->
                <div class="lg:col-span-8 space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-purple-600 text-white shadow-sm shadow-purple-500/30">
                            {{ subscription.plan_name }} Plan
                        </span>
                        <span :class="['px-3 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5', getStatusBadge(subscription.computed_status).class]">
                            <span class="w-2 h-2 rounded-full bg-current"></span>
                            {{ getStatusBadge(subscription.computed_status).label }}
                        </span>
                        <span v-if="subscription.is_expiring_soon" class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500 text-white shadow-sm animate-pulse">
                            ⚠️ Renewal Due in {{ subscription.days_until_renewal }} Days
                        </span>
                    </div>

                    <div>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
                            {{ subscription.plan_name }}
                            <span v-if="subscription.plan_tagline" class="text-sm font-medium text-slate-500 dark:text-slate-400 block sm:inline sm:ml-2">
                                — {{ subscription.plan_tagline }}
                            </span>
                        </h2>
                        <div class="flex flex-wrap items-center gap-6 mt-3 text-xs text-slate-600 dark:text-slate-300">
                            <div>
                                <span class="text-slate-400 dark:text-slate-500">Started on:</span>
                                <span class="font-bold ml-1 text-slate-800 dark:text-slate-200">{{ subscription.started_at }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 dark:text-slate-500">Next Renewal:</span>
                                <span class="font-bold ml-1 text-slate-800 dark:text-slate-200">{{ subscription.renewal_date }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 dark:text-slate-500">Annual Renewal Price:</span>
                                <span class="font-bold ml-1 text-purple-600 dark:text-purple-400">{{ formatNaira(subscription.renewal_amount) }} / year</span>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Meters -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                        <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xs border border-slate-200/80 dark:border-slate-700 rounded-2xl p-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Branches</span>
                            <span class="text-base font-black text-slate-900 dark:text-slate-100">
                                {{ usageStats.branches_used }} <span class="text-xs font-normal text-slate-400">/ {{ usageStats.max_branches ? usageStats.max_branches : 'Unlimited' }}</span>
                            </span>
                        </div>
                        <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xs border border-slate-200/80 dark:border-slate-700 rounded-2xl p-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Staff Users</span>
                            <span class="text-base font-black text-slate-900 dark:text-slate-100">
                                {{ usageStats.users_total }} <span class="text-xs font-normal text-slate-400">total</span>
                            </span>
                        </div>
                        <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xs border border-slate-200/80 dark:border-slate-700 rounded-2xl p-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Monthly AI Credits</span>
                            <span class="text-base font-black text-indigo-600 dark:text-indigo-400">
                                {{ usageStats.ai_credits_monthly?.toLocaleString() }} <span class="text-xs font-normal text-slate-400">/mo</span>
                            </span>
                        </div>
                        <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xs border border-slate-200/80 dark:border-slate-700 rounded-2xl p-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">E-commerce Sync</span>
                            <span :class="['text-xs font-black inline-flex items-center gap-1 mt-1', usageStats.feature_ecommerce ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400']">
                                <span v-if="usageStats.feature_ecommerce">✓ Enabled</span>
                                <span v-else>✕ Locked</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Countdown & CTA -->
                <div class="lg:col-span-4 flex flex-col items-center justify-center p-6 bg-white dark:bg-slate-800/90 rounded-2xl border border-purple-200 dark:border-purple-800/60 shadow-sm text-center">
                    <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Days Remaining</span>
                    <div class="my-2">
                        <span :class="['text-4xl sm:text-5xl font-black tracking-tight', subscription.days_until_renewal < 14 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100']">
                            {{ Math.max(0, subscription.days_until_renewal) }}
                        </span>
                        <span class="text-xs font-bold text-slate-400 ml-1">days</span>
                    </div>

                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4">
                        Renew anytime to extend your validity by 12 months without downtime.
                    </p>

                    <button
                        @click="openRenewalModal"
                        :disabled="!gateways || gateways.length === 0"
                        class="w-full py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md shadow-purple-600/20 transition cursor-pointer disabled:opacity-50"
                    >
                        Renew for {{ formatNaira(subscription.renewal_amount) }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Navigation Tabs ─────────────────────────────────────────── -->
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
            <button
                @click="activeTab = 'plans'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition cursor-pointer flex items-center gap-2',
                    activeTab === 'plans'
                        ? 'border-purple-600 text-purple-600 dark:text-purple-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                ]"
            >
                <span>📦 Upgrade & Tier Comparison</span>
            </button>
            <button
                @click="activeTab = 'history'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition cursor-pointer flex items-center gap-2',
                    activeTab === 'history'
                        ? 'border-purple-600 text-purple-600 dark:text-purple-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                ]"
            >
                <span>📜 Payment & Renewal History ({{ payments.length }})</span>
            </button>
        </div>

        <!-- ── TAB 1: Available Plans & Upgrade Matrix ──────────────────── -->
        <div v-if="activeTab === 'plans'" class="space-y-6">
            <div class="text-center max-w-xl mx-auto space-y-1">
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Looking to expand your business?</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Upgrading immediately unlocks additional branch quotas, higher staff capacities, and advanced platform capabilities.
                </p>
            </div>

            <!-- Pricing Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div
                    v-for="plan in pricingPlans"
                    :key="plan.id"
                    :class="[
                        'relative rounded-3xl p-6 transition-all duration-200 flex flex-col justify-between border',
                        plan.is_current
                            ? 'bg-purple-50/30 dark:bg-purple-950/20 border-purple-300 dark:border-purple-700 shadow-md ring-2 ring-purple-500/20'
                            : plan.is_highlighted
                                ? 'bg-white dark:bg-slate-900 border-indigo-300 dark:border-indigo-700 shadow-lg'
                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300'
                    ]"
                >
                    <!-- Highlighted Badge -->
                    <div v-if="plan.is_highlighted && !plan.is_current" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-[10px] font-black tracking-wider uppercase shadow-sm">
                        Popular Choice
                    </div>

                    <!-- Current Plan Badge -->
                    <div v-if="plan.is_current" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-purple-600 text-white text-[10px] font-black tracking-wider uppercase shadow-sm">
                        Current Plan
                    </div>

                    <div>
                        <!-- Plan Header -->
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-4">
                            <h4 class="text-base font-black text-slate-900 dark:text-slate-100">{{ plan.name }}</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 min-h-[32px]">{{ plan.tagline || 'Essential tools for growing retailers.' }}</p>

                            <div class="mt-4">
                                <div class="text-2xl font-black text-slate-900 dark:text-slate-100">
                                    {{ plan.first_year_price }}
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    Renews at {{ plan.renewal_price }}
                                </div>
                            </div>
                        </div>

                        <!-- Limits Snapshot -->
                        <div class="space-y-2 text-xs mb-6">
                            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                <span class="text-slate-400">Branches</span>
                                <span class="font-bold">{{ plan.branches_limit }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                <span class="text-slate-400">Users</span>
                                <span class="font-bold">{{ plan.users_limit }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                <span class="text-slate-400">AI Monthly</span>
                                <span class="font-bold text-purple-600 dark:text-purple-400">{{ plan.ai_credits || 'Standard' }}</span>
                            </div>
                        </div>

                        <!-- Feature List -->
                        <div v-if="plan.features && plan.features.length > 0" class="space-y-2 text-xs border-t border-slate-100 dark:border-slate-800 pt-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Included Features:</span>
                            <div v-for="feat in plan.features" :key="feat.id" class="flex items-start gap-2 text-slate-600 dark:text-slate-300">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span class="text-[11px] leading-tight">{{ feat.text }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Action -->
                    <div class="pt-6 border-t border-slate-100 dark:border-slate-800 mt-6">
                        <button
                            v-if="plan.is_current"
                            @click="openRenewalModal"
                            :disabled="!gateways || gateways.length === 0"
                            class="w-full py-2.5 px-4 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 hover:bg-purple-200 text-xs font-bold transition cursor-pointer disabled:opacity-50"
                        >
                            Renew Subscription
                        </button>
                        <button
                            v-else-if="plan.is_upgrade"
                            @click="openUpgradeModal(plan)"
                            :disabled="!gateways || gateways.length === 0"
                            class="w-full py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition shadow-md shadow-purple-600/20 cursor-pointer disabled:opacity-50"
                        >
                            Upgrade to {{ plan.name }}
                        </button>
                        <button
                            v-else
                            disabled
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 text-xs font-medium cursor-not-allowed"
                        >
                            Lower Tier
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB 2: Payment & Renewal History ────────────────────────── -->
        <div v-if="activeTab === 'history'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
            <div class="p-6 border-b border-slate-200 dark:border-slate-800">
                <h3 class="font-bold text-slate-900 dark:text-slate-100 text-sm">Subscription Payment Ledger</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Chronological audit history of all renewals and tier upgrades processed for this organization.
                </p>
            </div>

            <div v-if="payments && payments.length > 0" class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-400 font-semibold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Plan</th>
                            <th class="py-3 px-4">Amount</th>
                            <th class="py-3 px-4">Gateway</th>
                            <th class="py-3 px-4">Reference</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Initiated By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="item in payments" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">{{ item.created_at }}</td>
                            <td class="py-3 px-4">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', item.type === 'upgrade' ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400']">
                                    {{ item.type }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-slate-100">{{ item.plan_name }}</td>
                            <td class="py-3 px-4 font-black text-slate-900 dark:text-slate-100">{{ formatNaira(item.amount_paid) }}</td>
                            <td class="py-3 px-4 capitalize text-slate-600 dark:text-slate-400">{{ item.gateway }}</td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ item.gateway_reference }}</td>
                            <td class="py-3 px-4">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                        item.status === 'successful' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' :
                                        item.status === 'pending' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800' :
                                        'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800'
                                    ]"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500">{{ item.user_name }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-12 text-slate-400 text-xs">
                No online subscription payments recorded yet.
            </div>
        </div>

        <!-- ── Interactive Checkout Modal ──────────────────────────────── -->
        <div v-if="showCheckoutModal && selectedPlan" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-slate-100 text-base">
                            {{ checkoutType === 'renewal' ? 'Renew Plan Subscription' : 'Upgrade Plan Subscription' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Target Plan: <span class="font-bold text-purple-600 dark:text-purple-400">{{ selectedPlan.name }}</span>
                        </p>
                    </div>
                    <button @click="showCheckoutModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <!-- Order Breakdown -->
                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-4 space-y-2 border border-slate-100 dark:border-slate-800 text-xs">
                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                        <span>Action</span>
                        <span class="font-bold capitalize">{{ checkoutType }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                        <span>Validity Period</span>
                        <span class="font-bold">12 Months (1 Year)</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                        <span>Branch Quota</span>
                        <span class="font-bold">{{ selectedPlan.branches_limit }}</span>
                    </div>
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-2 flex items-center justify-between">
                        <span class="font-bold text-slate-800 dark:text-slate-200">Total Due Today</span>
                        <span class="text-lg font-black text-purple-600 dark:text-purple-400">{{ formatNaira(modalAmount) }}</span>
                    </div>
                </div>

                <!-- Gateway Selection -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Select Payment Method</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="gw in gateways"
                            :key="gw.id"
                            @click="checkoutForm.gateway = gw.id"
                            :class="[
                                'p-3 rounded-2xl border text-center cursor-pointer transition flex flex-col items-center justify-center gap-1',
                                checkoutForm.gateway === gw.id
                                    ? 'bg-purple-50/50 dark:bg-purple-950/40 border-purple-500 text-purple-700 dark:text-purple-300 ring-2 ring-purple-500/20'
                                    : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-300'
                            ]"
                        >
                            <span class="text-xs font-bold capitalize">{{ gw.name }}</span>
                            <span class="text-[10px] text-slate-400">Cards & Bank Transfer</span>
                        </div>
                    </div>
                </div>

                <!-- Security Assurance -->
                <div class="flex items-center gap-2 text-[11px] text-slate-400 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>256-bit encrypted checkout hosted securely by the payment gateway.</span>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="showCheckoutModal = false"
                        class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-800 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitCheckout"
                        :disabled="checkoutForm.processing || !gateways || gateways.length === 0"
                        class="px-6 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md shadow-purple-600/20 transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <span v-if="checkoutForm.processing">Connecting to Gateway...</span>
                        <span v-else>Pay {{ formatNaira(modalAmount) }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
