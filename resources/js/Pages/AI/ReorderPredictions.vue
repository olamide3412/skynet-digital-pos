<script setup>
import { ref, computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const page = usePage()
const isSuperAdmin = computed(() => !!page.props.auth?.user?.is_super_admin)

const props = defineProps({
    branch:         Object,
    branches:       Array,
    summary:        Object,
    predictions:    Array,
    aiInsight:      String,
    isAiConfigured: Boolean,
    filters:        Object,
})

const layoutComponent = computed(() => {
    if (isSuperAdmin.value && !props.branch) {
        return SuperAdminLayout
    }
    return PosLayout
})

const searchQuery = ref(props.filters?.search || '')
const selectedUrgency = ref(props.filters?.urgency || 'all')
const selectedCategory = ref(props.filters?.category || 'all')
const selectedBranchId = ref(props.filters?.branch_id || '')
const velocityDays = ref(props.filters?.velocity_days || 14)
const thresholdDays = ref(props.filters?.threshold_days || 7)

const categories = computed(() => {
    const set = new Set()
    props.predictions.forEach(p => {
        if (p.category_name) set.add(p.category_name)
    })
    return Array.from(set).sort()
})

const filteredPredictions = computed(() => {
    return props.predictions.filter(item => {
        // Urgency filter
        if (selectedUrgency.value === 'critical' && item.urgency !== 'critical') return false
        if (selectedUrgency.value === 'warning' && item.urgency !== 'warning') return false
        if (selectedUrgency.value === 'needs_reorder' && item.urgency === 'safe') return false
        if (selectedUrgency.value === 'safe' && item.urgency !== 'safe') return false

        // Category filter
        if (selectedCategory.value !== 'all' && item.category_name !== selectedCategory.value) return false

        // Search query
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim()
            const nameMatch = (item.item_name || '').toLowerCase().includes(q)
            const codeMatch = (item.barcode_number || '').toLowerCase().includes(q)
            return nameMatch || codeMatch
        }

        return true
    })
})

function applyFilters() {
    const isSuper = isSuperAdmin.value && !props.branch
    const routeName = isSuper ? 'superadmin.ai.reorder' : 'pos.ai.reorder'
    const branchSlug = props.branch?.slug || route().params?.branch

    const params = {
        velocity_days:  velocityDays.value,
        threshold_days: thresholdDays.value,
        urgency:        selectedUrgency.value,
        category:       selectedCategory.value,
        search:         searchQuery.value,
        branch_id:      selectedBranchId.value || undefined,
    }

    if (!isSuper && branchSlug) {
        params.branch = branchSlug
    }

    router.get(route(routeName, params), {}, {
        preserveState: true,
        preserveScroll: true,
    })
}

function dispatchNotifications() {
    const branchSlug = props.branch?.slug || route().params?.branch
    if (!branchSlug) return
    router.post(route('pos.ai.reorder.notify', { branch: branchSlug }), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <component :is="layoutComponent">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚡</span>
                    <h1 class="text-xl font-black text-slate-900 dark:text-white">
                        AI Reorder Predictions & Sales Velocity
                    </h1>
                    <span v-if="branch" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                        {{ branch.name }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Forecasts days-until-stockout based on rolling {{ summary?.velocity_days || 14 }}-day sales velocity and converts replenishment orders into optimal unit packaging variants.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Branch Selector for Super Admin -->
                <div v-if="isSuperAdmin && branches?.length > 0">
                    <select
                        v-model="selectedBranchId"
                        @change="applyFilters"
                        class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 outline-none"
                    >
                        <option value="">All Branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Velocity Days Selector -->
                <select
                    v-model="velocityDays"
                    @change="applyFilters"
                    class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 outline-none"
                >
                    <option :value="7">Last 7 Days Velocity</option>
                    <option :value="14">Last 14 Days Velocity (Default)</option>
                    <option :value="30">Last 30 Days Velocity</option>
                </select>

                <!-- Dispatch Notifications Button (for Branch Admins) -->
                <button
                    v-if="branch"
                    type="button"
                    @click="dispatchNotifications"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-900/20 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <span>🔔</span>
                    <span>Send Staff Alerts</span>
                </button>
            </div>
        </div>

        <!-- KPI Metric Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Critical Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-rose-200 dark:border-rose-900/40 p-4 shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl font-bold flex-shrink-0">
                    🚨
                </div>
                <div>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
                        {{ summary?.critical_items_count || 0 }}
                    </div>
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        Critical Stockouts (≤ 2 Days)
                    </div>
                </div>
            </div>

            <!-- Warning Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-amber-200 dark:border-amber-900/40 p-4 shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl font-bold flex-shrink-0">
                    ⚠️
                </div>
                <div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">
                        {{ summary?.warning_items_count || 0 }}
                    </div>
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        Warning (≤ {{ summary?.threshold_days || 7 }} Days)
                    </div>
                </div>
            </div>

            <!-- Suggested Reorder Units -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-indigo-200 dark:border-indigo-900/40 p-4 shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl font-bold flex-shrink-0">
                    📦
                </div>
                <div>
                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono">
                        {{ Number(summary?.total_suggested_units || 0).toLocaleString() }}
                    </div>
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        Suggested Reorder (Units)
                    </div>
                </div>
            </div>

            <!-- Total Analyzed -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xl font-bold flex-shrink-0">
                    📊
                </div>
                <div>
                    <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
                        {{ summary?.total_items_analyzed || 0 }}
                    </div>
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        Total Catalog Analyzed
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Executive Recommendation Banner -->
        <div v-if="aiInsight" class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-5 border border-indigo-500/30 shadow-lg space-y-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-sm">✨</span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300">
                        AI Reorder Strategy & Recommendations
                    </h3>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-200 border border-indigo-500/40 font-mono">
                    AI Verified
                </span>
            </div>
            <div class="text-xs text-indigo-100/90 leading-relaxed whitespace-pre-line font-sans">
                {{ aiInsight }}
            </div>
        </div>

        <!-- Filter Toolbar & Urgency Tabs -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 space-y-3 shadow-xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <!-- Urgency Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                    <button
                        v-for="tab in [
                            { id: 'all', label: 'All Items', count: predictions.length },
                            { id: 'needs_reorder', label: '⚡ Needs Reorder', count: summary?.needs_reorder_count },
                            { id: 'critical', label: '🚨 Critical (≤ 2d)', count: summary?.critical_items_count },
                            { id: 'warning', label: '⚠️ Warning', count: summary?.warning_items_count },
                            { id: 'safe', label: '✓ Safe', count: summary?.safe_items_count },
                        ]"
                        :key="tab.id"
                        type="button"
                        @click="selectedUrgency = tab.id"
                        :class="[
                            'px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer flex items-center gap-1.5',
                            selectedUrgency === tab.id
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                        ]"
                    >
                        <span>{{ tab.label }}</span>
                        <span class="text-[10px] opacity-80 px-1.5 py-0.2 rounded-full bg-black/15 font-mono">
                            {{ tab.count || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Search Input & Category Filter -->
                <div class="flex items-center gap-2">
                    <select
                        v-model="selectedCategory"
                        class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 outline-none"
                    >
                        <option value="all">All Categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">
                            {{ cat }}
                        </option>
                    </select>

                    <div class="relative w-full sm:w-64">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Filter item name or barcode..."
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 pl-8 rounded-xl border border-slate-300 dark:border-slate-600 outline-none focus:border-emerald-500 transition"
                        />
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Predictions Table -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3.5">Product & Barcode</th>
                            <th class="px-4 py-3.5">Category</th>
                            <th class="px-4 py-3.5 text-center">In Stock (Front / Back)</th>
                            <th class="px-4 py-3.5 text-center">Daily Velocity</th>
                            <th class="px-4 py-3.5 text-center">Days Remaining</th>
                            <th class="px-4 py-3.5">Suggested Reorder</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr
                            v-for="item in filteredPredictions"
                            :key="item.item_id"
                            class="hover:bg-slate-50/80 dark:hover:bg-slate-750 transition"
                        >
                            <!-- Product -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-white leading-tight">
                                    {{ item.item_name }}
                                </div>
                                <div class="text-[10px] font-mono text-slate-400 mt-0.5 flex items-center gap-1.5">
                                    <span>#{{ item.barcode_number || 'No Barcode' }}</span>
                                    <span v-if="item.is_imei_tracked" class="px-1 py-0.2 rounded bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-bold text-[8px] uppercase">
                                        IMEI
                                    </span>
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ item.category_name }}
                            </td>

                            <!-- In Stock -->
                            <td class="px-4 py-3 text-center font-mono">
                                <span class="font-bold text-slate-900 dark:text-white">{{ item.front_store_qty }}</span>
                                <span class="text-slate-400"> / {{ item.back_store_qty }}</span>
                            </td>

                            <!-- Daily Velocity -->
                            <td class="px-4 py-3 text-center font-mono">
                                <span class="font-bold text-slate-900 dark:text-white">{{ item.avg_daily_sales }}</span>
                                <span class="text-[10px] text-slate-400">/day</span>
                                <div class="text-[9px] text-slate-400">
                                    ({{ item.total_sold_in_period }} in {{ summary?.velocity_days }}d)
                                </div>
                            </td>

                            <!-- Days Remaining -->
                            <td class="px-4 py-3 text-center font-mono">
                                <span
                                    v-if="item.days_left === 999"
                                    class="text-slate-400 font-bold text-[11px]"
                                >
                                    &gt; 30 days
                                </span>
                                <span
                                    v-else-if="item.days_left <= 2"
                                    class="px-2 py-1 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 font-black text-xs border border-rose-500/20 animate-pulse"
                                >
                                    {{ item.days_left }} {{ item.days_left === 1 ? 'Day' : 'Days' }}
                                </span>
                                <span
                                    v-else-if="item.days_left <= (summary?.threshold_days || 7)"
                                    class="px-2 py-1 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 font-black text-xs border border-amber-500/20"
                                >
                                    {{ item.days_left }} Days
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-black text-xs"
                                >
                                    {{ item.days_left }} Days
                                </span>
                            </td>

                            <!-- Suggested Reorder Variant -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ item.suggested_reorder_display }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    Reorder Point: {{ item.reorder_point }}
                                </div>
                            </td>

                            <!-- Urgency Status Badge -->
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="item.urgency === 'critical'"
                                    class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-rose-600 text-white shadow-2xs"
                                >
                                    Critical
                                </span>
                                <span
                                    v-else-if="item.urgency === 'warning'"
                                    class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-amber-500 text-white shadow-2xs"
                                >
                                    Warning
                                </span>
                                <span
                                    v-else
                                    class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-600/20 text-emerald-700 dark:text-emerald-300"
                                >
                                    Safe
                                </span>
                            </td>
                        </tr>

                        <tr v-if="filteredPredictions.length === 0">
                            <td colspan="7" class="text-center py-12 text-slate-400 dark:text-slate-500 text-xs">
                                No inventory items match the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</component>
</template>
