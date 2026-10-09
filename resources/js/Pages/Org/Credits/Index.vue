<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import OrgLayout from '@/Layouts/OrgLayout.vue'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization:   Object,
    branches:       Array,
    selectedBranch: Object,
    summary:        Object,
    packs:          Array,
    gateways:       Array,
    history:        Object,
    paymentNotice:  String,
})

const showNotice = ref(Boolean(props.paymentNotice))
const isCheckoutModalOpen = ref(false)
const selectedPack = ref(null)

const checkoutForm = useForm({
    credit_pack_id: null,
    gateway:        props.gateways?.[0]?.identifier || 'flutterwave',
    branch_id:      props.selectedBranch?.id || null,
})

function onBranchChange(branchId) {
    router.get(route('org.credits.index'), { branch_id: branchId || undefined }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function openCheckout(pack) {
    selectedPack.value = pack
    checkoutForm.credit_pack_id = pack.id
    checkoutForm.branch_id = props.selectedBranch?.id || (props.branches?.[0]?.id ?? null)
    if (!checkoutForm.gateway && props.gateways?.length > 0) {
        checkoutForm.gateway = props.gateways[0].identifier
    }
    isCheckoutModalOpen.value = true
}

function closeCheckout() {
    isCheckoutModalOpen.value = false
    selectedPack.value = null
    checkoutForm.reset('credit_pack_id')
}

function submitCheckout() {
    checkoutForm.post(route('org.credits.purchase'), {
        preserveScroll: true,
    })
}

const formatCurrency = (val, currency = 'NGN') => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formatDate = (dateStr) => {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const usagePercentage = computed(() => {
    if (props.summary?.is_unmetered) return 0
    const quota = props.summary?.monthly_quota || 0
    if (quota <= 0) return 100
    const used = props.summary?.used || 0
    return Math.min(100, Math.round((used / quota) * 100))
})
</script>

<template>
    <Head :title="`${organization.name} - AI Credits & Add-ons`" />

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- ── Flash Processing Notice Banner ────────────────────────────── -->
        <div
            v-if="showNotice"
            class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-3xl p-5 text-white shadow-xl flex items-center justify-between gap-4"
        >
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-xl flex-shrink-0">
                    💳
                </div>
                <div>
                    <h4 class="font-black text-sm">Payment Processing</h4>
                    <p class="text-xs text-blue-100 mt-0.5">
                        {{ paymentNotice }}
                    </p>
                </div>
            </div>
            <button
                type="button"
                @click="showNotice = false"
                class="text-white/80 hover:text-white p-2 rounded-xl hover:bg-white/10 transition cursor-pointer"
            >
                ✕
            </button>
        </div>

        <!-- ── Top Header Banner ─────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        AI Credits & Add-on Packs
                    </h1>
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                        {{ summary?.plan_name || 'Plan' }}
                    </span>
                    <span
                        v-if="summary?.pool_type === 'per_branch'"
                        class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                    >
                        Per-Branch Quota
                    </span>
                    <span
                        v-else
                        class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                    >
                        Account-Wide Shared Pool
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Monitor monthly AI usage and buy persistent add-on packs via Flutterwave or Paystack that carry over month-to-month.
                </p>
            </div>

            <!-- Branch Context Selector (shown if per-branch pooling) -->
            <div v-if="summary?.pool_type === 'per_branch' && branches?.length > 1" class="flex flex-col gap-1 w-full md:w-auto">
                <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Viewing Branch Pool:</label>
                <select
                    :value="selectedBranch?.id"
                    @change="onBranchChange($event.target.value)"
                    class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold shadow-xs cursor-pointer"
                >
                    <option v-for="b in branches" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- ── Credit Balances Breakdown Cards ────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Card 1: Monthly Plan Allocation -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Monthly Plan Quota</span>
                    <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 text-sm">📅</span>
                </div>

                <div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-slate-900 dark:text-white">
                            {{ summary?.is_unmetered ? '∞' : (summary?.plan_remaining ?? 0).toLocaleString() }}
                        </span>
                        <span v-if="!summary?.is_unmetered" class="text-xs text-slate-400 font-bold">
                            / {{ (summary?.monthly_quota ?? 0).toLocaleString() }}
                        </span>
                    </div>
                    <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">
                        {{ summary?.is_unmetered ? 'Unlimited monthly credits' : 'Remaining from this calendar month quota' }}
                    </span>
                </div>

                <!-- Progress Bar -->
                <div v-if="!summary?.is_unmetered" class="space-y-1.5 pt-1">
                    <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="usagePercentage >= 90 ? 'bg-rose-500' : (usagePercentage >= 70 ? 'bg-amber-500' : 'bg-purple-600')"
                            :style="{ width: `${usagePercentage}%` }"
                        ></div>
                    </div>
                    <div class="flex justify-between text-[11px] text-slate-400 font-medium">
                        <span>{{ (summary?.used ?? 0).toLocaleString() }} used</span>
                        <span>Resets {{ summary?.resets_at }}</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Active Purchased Balance -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Purchased Top-Up Balance</span>
                    <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 text-sm">💎</span>
                </div>

                <div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">
                            {{ (summary?.topup_remaining ?? 0).toLocaleString() }}
                        </span>
                        <span class="text-xs text-slate-400 font-bold">credits</span>
                    </div>
                    <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">
                        Non-expiring add-on pool
                    </span>
                </div>

                <div class="pt-2 text-[11px] text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800">
                    <span class="font-bold text-slate-700 dark:text-slate-300">Carries over:</span>
                    Does not expire at month-end. Used automatically once monthly allocation is exhausted.
                </div>
            </div>

            <!-- Card 3: Total Available Balance -->
            <div class="bg-gradient-to-br from-purple-900 to-indigo-900 text-white rounded-3xl p-6 shadow-xl space-y-4 relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-purple-500/10 rounded-full blur-2xl"></div>

                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-purple-200 uppercase tracking-wider">Total Available Now</span>
                    <span class="p-2 rounded-xl bg-white/10 backdrop-blur-md text-amber-300 text-sm">⚡</span>
                </div>

                <div>
                    <div class="text-3xl font-black tracking-tight">
                        {{ summary?.is_unmetered ? 'Unmetered (∞)' : (summary?.remaining ?? 0).toLocaleString() }}
                    </div>
                    <span class="text-xs text-purple-200/90 mt-1 block">
                        {{ summary?.is_exhausted ? 'Credits exhausted — purchase a pack below to continue' : 'Ready for AI queries & predictions' }}
                    </span>
                </div>

                <div class="pt-2 flex items-center gap-2 text-xs font-bold text-purple-200">
                    <span class="w-2 h-2 rounded-full" :class="summary?.is_exhausted ? 'bg-rose-400 animate-ping' : 'bg-emerald-400'"></span>
                    <span>{{ summary?.is_exhausted ? 'Action Needed' : 'Operational' }}</span>
                </div>
            </div>
        </div>

        <!-- ── CREDIT PACKS CATALOG ──────────────────────────────────────── -->
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                    <span>📦</span>
                    <span>Purchase Credit Packs</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Select a pack to instantly top up your account via Flutterwave or Paystack.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div
                    v-for="pack in packs"
                    :key="pack.id"
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-purple-500 dark:hover:border-purple-500 rounded-3xl p-6 shadow-xs hover:shadow-xl transition-all duration-200 flex flex-col justify-between group"
                >
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                {{ pack.credits.toLocaleString() }} Credits
                            </span>
                            <span class="text-[11px] font-mono text-slate-400 font-bold">
                                ₦{{ (pack.price / pack.credits).toFixed(2) }}/credit
                            </span>
                        </div>

                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition">
                                {{ pack.name }}
                            </h3>
                            <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
                                {{ formatCurrency(pack.price, pack.currency) }}
                            </div>
                        </div>

                        <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Never expires at month-end</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Covers AI Assistant & Predictions</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500">✓</span>
                                <span>Instant activation upon payment</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-6">
                        <button
                            type="button"
                            @click="openCheckout(pack)"
                            class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md shadow-purple-600/20 group-hover:shadow-purple-600/40 transition cursor-pointer flex items-center justify-center gap-2"
                        >
                            <span>Purchase Pack</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TOP-UP & PAYMENT HISTORY ──────────────────────────────────── -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>📜</span>
                        <span>Top-Up & Payment History</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Unified ledger of all manual grants and paid add-on purchases.
                    </p>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/40 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Item / Details</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">Credits</th>
                                <th class="py-3 px-4">Remaining</th>
                                <th class="py-3 px-4">Target Pool</th>
                                <th class="py-3 px-4">Gateway</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs text-slate-700 dark:text-slate-300">
                            <tr v-for="item in history.data" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-500">
                                    {{ formatDate(item.created_at) }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                    <div>{{ item.credit_pack?.name || item.notes || 'AI Credit Grant' }}</div>
                                    <div v-if="item.gateway_reference" class="text-[10px] text-slate-400 font-mono">
                                        Ref: {{ item.gateway_reference }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span
                                        :class="item.type === 'addon_purchase'
                                            ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'
                                            : 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider"
                                    >
                                        {{ item.type === 'addon_purchase' ? 'Purchase' : 'Grant' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-black text-purple-600 dark:text-purple-400">
                                    +{{ item.credits?.toLocaleString() }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-600 dark:text-slate-300">
                                    {{ item.credits_remaining?.toLocaleString() }}
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                    {{ item.branch ? item.branch.name : 'Organization-Wide' }}
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 capitalize">
                                    {{ item.gateway || '—' }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                    {{ item.amount_paid ? formatCurrency(item.amount_paid, item.currency) : 'Free' }}
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <span
                                        :class="{
                                            'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800': item.status === 'successful' || !item.status,
                                            'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800 animate-pulse': item.status === 'pending',
                                            'bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800': item.status === 'failed',
                                        }"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider inline-block"
                                    >
                                        {{ item.status || 'Successful' }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="!history.data || history.data.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    No credit top-ups or purchases found for this organization.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── CHECKOUT MODAL ────────────────────────────────────────────── -->
        <teleport to="body">
            <div v-if="isCheckoutModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-6 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center text-xl">
                                🛒
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    Purchase AI Credit Pack
                                </h3>
                                <p class="text-xs text-slate-400">Secure payment integration</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="closeCheckout"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitCheckout" class="space-y-5">
                        <!-- Pack Summary Card -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-slate-400 font-bold block">Selected Package:</span>
                                <span class="text-sm font-black text-slate-900 dark:text-white block">{{ selectedPack?.name }}</span>
                                <span class="text-xs text-purple-600 dark:text-purple-400 font-bold">+{{ selectedPack?.credits?.toLocaleString() }} Credits</span>
                            </div>
                            <div class="text-xl font-black text-slate-900 dark:text-white">
                                {{ formatCurrency(selectedPack?.price, selectedPack?.currency) }}
                            </div>
                        </div>

                        <!-- Target Branch (for per-branch pool) -->
                        <div v-if="summary?.pool_type === 'per_branch' && branches?.length > 1">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">
                                Target Branch Pool
                            </label>
                            <select
                                v-model="checkoutForm.branch_id"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            >
                                <option v-for="b in branches" :key="b.id" :value="b.id">
                                    {{ b.name }}
                                </option>
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Credits will be assigned to this branch's top-up pool.
                            </p>
                        </div>

                        <!-- Payment Gateway Selector -->
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">
                                Choose Payment Provider
                            </label>

                            <div v-if="gateways && gateways.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    v-for="gw in gateways"
                                    :key="gw.identifier"
                                    :class="[
                                        'p-4 rounded-2xl border-2 transition cursor-pointer flex flex-col justify-between gap-2',
                                        checkoutForm.gateway === gw.identifier
                                            ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-900/20'
                                            : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-800'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <input
                                                type="radio"
                                                v-model="checkoutForm.gateway"
                                                :value="gw.identifier"
                                                class="text-purple-600 focus:ring-purple-500"
                                            />
                                            <span class="font-bold text-sm text-slate-900 dark:text-white">{{ gw.name }}</span>
                                        </div>
                                    </div>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 pl-6">
                                        {{ gw.identifier === 'flutterwave' ? 'Cards, Mobile Money, Transfers' : 'Cards, Bank Transfer, Apple Pay' }}
                                    </span>
                                </label>
                            </div>

                            <div v-else class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                                No payment gateways are currently enabled by the platform administrator. Please contact support.
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="closeCheckout"
                                class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="checkoutForm.processing || !gateways || gateways.length === 0"
                                class="px-7 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-lg shadow-purple-600/30 transition cursor-pointer disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg v-if="checkoutForm.processing" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                <span>{{ checkoutForm.processing ? 'Redirecting to Gateway...' : `Pay ${formatCurrency(selectedPack?.price, selectedPack?.currency)}` }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </div>
</template>
