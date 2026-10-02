<script setup>
import { Head, Link } from '@inertiajs/vue3'
import OrgLayout from '@/Layouts/OrgLayout.vue'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization: Object,
    kpis: Object,
    branches: Array,
    recent_sales: Array,
    all_organizations: Array,
    is_super_admin: Boolean,
})

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}
</script>

<template>
    <Head :title="`${organization.name} - Organization Dashboard`" />

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- ── Top Header Banner ─────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ organization.name }}
                    </h1>
                    <span
                        :class="organization.status === 'active'
                            ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30'
                            : 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-500/30'"
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase tracking-wider"
                    >
                        {{ organization.status }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span v-if="organization.contact_name">👤 {{ organization.contact_name }}</span>
                    <span v-if="organization.contact_email">✉️ {{ organization.contact_email }}</span>
                    <span v-if="organization.contact_phone">📞 {{ organization.contact_phone }}</span>
                    <span v-if="organization.address">📍 {{ organization.address }}</span>
                </div>
            </div>

            <!-- Quick Action Links -->
            <div class="flex items-center gap-3 w-full md:w-auto">
                <Link
                    :href="route('org.reports')"
                    class="flex-1 md:flex-none px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>View Reports</span>
                </Link>
                <Link
                    :href="route('org.ai.chat')"
                    class="flex-1 md:flex-none px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-purple-600/20 flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Ask AI Copilot</span>
                </Link>
            </div>
        </div>

        <!-- ── Organization Subscription Status & Soft Alerts ────────────── -->
        <div v-if="$page.props.current_subscription" class="space-y-4">
            <!-- Non-blocking Soft Warnings -->
            <div v-if="$page.props.current_subscription.is_past_due" class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-xs">
                <span class="text-xl">⚠️</span>
                <div>
                    <h4 class="font-bold text-amber-900 dark:text-amber-100">Subscription Renewal Due</h4>
                    <p class="mt-0.5 text-amber-700 dark:text-amber-300">
                        Your organization's annual plan renewal was due on <strong>{{ $page.props.current_subscription.renewal_date }}</strong> ({{ Math.abs($page.props.current_subscription.days_until_renewal) }} days ago). Under our soft renewal policy, all branches and terminals remain fully operational without service disruption. Please contact Skynet support to renew your license.
                    </p>
                </div>
            </div>

            <div v-else-if="$page.props.current_subscription.is_expiring_soon" class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-800 dark:text-indigo-200 flex items-start gap-3 shadow-xs">
                <span class="text-xl">⏰</span>
                <div>
                    <h4 class="font-bold text-indigo-900 dark:text-indigo-100">Annual Renewal Approaching</h4>
                    <p class="mt-0.5 text-indigo-700 dark:text-indigo-300">
                        Your plan renews in <strong>{{ $page.props.current_subscription.days_until_renewal }} day(s)</strong> on <strong>{{ $page.props.current_subscription.renewal_date }}</strong>.
                    </p>
                </div>
            </div>

            <!-- Active Plan Snapshot Pill -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900 flex items-center justify-center text-lg">
                        💳
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ $page.props.current_subscription.plan_name }} Plan
                            </h3>
                            <span
                                :class="{
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400': $page.props.current_subscription.computed_status === 'active',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400': $page.props.current_subscription.computed_status === 'grace_period',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400': $page.props.current_subscription.computed_status === 'expired'
                                }"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                            >
                                {{ $page.props.current_subscription.computed_status.replace('_', ' ') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ $page.props.current_subscription.plan_tagline || 'Commercial subscription tier' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400 border-t sm:border-t-0 pt-2 sm:pt-0">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Renewal Date</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $page.props.current_subscription.renewal_date }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Time Left</span>
                        <span class="font-bold text-indigo-600 dark:text-indigo-400">
                            {{ $page.props.current_subscription.days_until_renewal >= 0 ? `${$page.props.current_subscription.days_until_renewal} Days` : 'Renewal Due' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Key Performance Indicators (KPIs) ────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-4">
            <!-- Today Revenue -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 space-y-1 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Today's Revenue</div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                    {{ formatCurrency(kpis.today_revenue) }}
                </div>
                <div class="text-xs text-slate-500">{{ kpis.today_orders }} completed sales today</div>
            </div>

            <!-- This Week Revenue -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 space-y-1 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">This Week</div>
                <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400">
                    {{ formatCurrency(kpis.week_revenue) }}
                </div>
                <div class="text-xs text-slate-500">{{ kpis.week_orders }} orders this week</div>
            </div>

            <!-- This Month Revenue -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 space-y-1 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">This Month</div>
                <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400">
                    {{ formatCurrency(kpis.month_revenue) }}
                </div>
                <div class="text-xs text-slate-500">Total Month-to-Date</div>
            </div>

            <!-- Branches Count -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 space-y-1 shadow-xs">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Locations</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ kpis.active_branches }} / {{ kpis.total_branches }}
                </div>
                <div class="text-xs text-slate-500">Operating branch locations</div>
            </div>
        </div>

        <!-- Secondary Alert Strip -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-amber-800 dark:text-amber-300">Low Stock Alerts</div>
                    <div class="text-[11px] text-amber-700/80 dark:text-amber-400/80">Items at or below reorder point</div>
                </div>
                <span class="text-xl font-black text-amber-600 dark:text-amber-400">{{ kpis.low_stock_count }}</span>
            </div>

            <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-rose-800 dark:text-rose-300">Expiring Items (30d)</div>
                    <div class="text-[11px] text-rose-700/80 dark:text-rose-400/80">Batches expiring within 30 days</div>
                </div>
                <span class="text-xl font-black text-rose-600 dark:text-rose-400">{{ kpis.expiring_count }}</span>
            </div>

            <div class="bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/40 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-purple-800 dark:text-purple-300">Stock Conflicts</div>
                    <div class="text-[11px] text-purple-700/80 dark:text-purple-400/80">Pending offline sync conflicts</div>
                </div>
                <span class="text-xl font-black text-purple-600 dark:text-purple-400">{{ kpis.conflicts_count }}</span>
            </div>
        </div>

        <!-- ── Organization Branches ─────────────────────────────────────── -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">Organization Branches</h2>
                    <p class="text-xs text-slate-500">Live operational overview for each of your business branches.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="branch in branches"
                    :key="branch.id"
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs hover:border-purple-300 dark:hover:border-purple-800 transition flex flex-col justify-between space-y-5"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-sm flex-shrink-0 overflow-hidden">
                                    <img v-if="branch.logo_url" :src="branch.logo_url" :alt="branch.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ branch.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white leading-tight">{{ branch.name }}</h3>
                                    <span class="text-[11px] font-mono text-purple-600 dark:text-purple-400">/{{ branch.slug }}</span>
                                </div>
                            </div>
                            <span
                                :class="branch.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/30'"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase"
                            >
                                {{ branch.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1 pt-1 border-t border-slate-100 dark:border-slate-800">
                            <div v-if="branch.address" class="truncate">📍 {{ branch.address }}</div>
                            <div v-if="branch.phone">📞 {{ branch.phone }}</div>
                        </div>

                        <!-- Mini metrics -->
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                            <div class="p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl">
                                <div class="text-[10px] text-slate-400 uppercase font-semibold">Today's Sales</div>
                                <div class="font-bold text-slate-900 dark:text-white mt-0.5">{{ formatCurrency(branch.today_revenue) }}</div>
                            </div>
                            <div class="p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl">
                                <div class="text-[10px] text-slate-400 uppercase font-semibold">Catalog Items</div>
                                <div class="font-bold text-slate-900 dark:text-white mt-0.5">{{ branch.items_count }} products</div>
                            </div>
                        </div>
                    </div>

                    <!-- Direct POS action -->
                    <div class="pt-2">
                        <a
                            :href="branch.pos_url"
                            target="_blank"
                            class="w-full py-2.5 px-3 bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>Open Branch POS</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Recent Sales Transactions ─────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Recent Transactions Across Organization</h2>
                    <p class="text-xs text-slate-500">Live sales feed from your registered branches.</p>
                </div>
                <Link
                    :href="route('org.reports')"
                    class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline"
                >
                    Full Reports &rarr;
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Receipt</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Cashier</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="sale in recent_sales" :key="sale.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50 transition">
                            <td class="px-4 py-3 font-mono font-bold text-purple-600 dark:text-purple-400">{{ sale.receipt_number }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ sale.branch_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ sale.cashier_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ sale.customer_name }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ formatCurrency(sale.final_total) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ sale.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ sale.created_at }}</td>
                        </tr>
                        <tr v-if="!recent_sales.length">
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                No recent sales recorded yet for this organization.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
