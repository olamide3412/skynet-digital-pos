<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import PosLayout from '@/Layouts/PosLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: null })

// Configure marked options
marked.setOptions({
    breaks: true,
    gfm: true,
})

// Configure DOMPurify hook for secure external links
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank')
        node.setAttribute('rel', 'noopener noreferrer')
    }
})

function renderMarkdown(content) {
    if (!content) return ''
    try {
        const rawHtml = marked.parse(content)
        const sanitized = DOMPurify.sanitize(rawHtml)
        return sanitized
            .replace(/<table/gi, '<div class="ai-table-wrapper overflow-x-auto my-3 rounded-xl border border-indigo-500/20 shadow-2xs"><table class="ai-table w-full border-collapse text-left text-xs"')
            .replace(/<\/table>/gi, '</table></div>')
    } catch (e) {
        console.warn('Markdown parsing error:', e)
        return DOMPurify.sanitize(content)
    }
}

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
const selectedBranchFilter = ref(props.filters?.branch_id || 'all')
const selectedBranchId = ref(props.filters?.branch_id || '')
const velocityDays = ref(props.filters?.velocity_days || 14)
const thresholdDays = ref(props.filters?.threshold_days || 7)

// ── Sorting State ────────────────────────────────────────────────────────────
const sortBy = ref(isSuperAdmin.value && !props.branch ? 'branch_name' : 'urgency')
const sortDirection = ref('asc')

function setSort(field) {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = field
        // Set intuitive default direction per field
        sortDirection.value = (field === 'avg_daily_sales' || field === 'suggested_reorder_units') ? 'desc' : 'asc'
    }
}

function getSortIcon(field) {
    if (sortBy.value !== field) return '↕'
    return sortDirection.value === 'asc' ? '▲' : '▼'
}

// ── Asynchronous AI Insight Loading ──────────────────────────────────────────
const aiInsight = ref(props.aiInsight || '')
const isGeneratingInsight = ref(false)
const insightError = ref(null)

async function fetchInsight() {
    isGeneratingInsight.value = true
    insightError.value = null

    try {
        const isSuper = isSuperAdmin.value && !props.branch
        const branchSlug = props.branch?.slug || route().params?.branch
        const url = isSuper
            ? route('superadmin.ai.reorder.insight')
            : route('pos.ai.reorder.insight', { branch: branchSlug })

        const res = await axios.post(url, {
            velocity_days:  velocityDays.value,
            threshold_days: thresholdDays.value,
            branch_id:      selectedBranchId.value || (selectedBranchFilter.value !== 'all' ? selectedBranchFilter.value : undefined),
        })

        if (res.data?.insight) {
            aiInsight.value = res.data.insight
        }
    } catch (err) {
        console.error('Failed to generate AI insight:', err)
        insightError.value = err?.response?.data?.message || 'Could not generate strategic AI recommendations. Click retry to try again.'
    } finally {
        isGeneratingInsight.value = false
    }
}

onMounted(() => {
    if (!aiInsight.value) {
        fetchInsight()
    }
})

// ── Categories & Branches ────────────────────────────────────────────────────
const categories = computed(() => {
    const set = new Set()
    props.predictions.forEach(p => {
        if (p.category_name) set.add(p.category_name)
    })
    return Array.from(set).sort()
})

const uniqueBranches = computed(() => {
    if (props.branches?.length > 0) return props.branches
    const map = new Map()
    props.predictions.forEach(p => {
        if (p.branch_id && p.branch_name) {
            map.set(p.branch_id, { id: p.branch_id, name: p.branch_name })
        }
    })
    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name))
})

// ── Filtering ────────────────────────────────────────────────────────────────
const filteredPredictions = computed(() => {
    return props.predictions.filter(item => {
        // Branch filter
        if (selectedBranchFilter.value !== 'all' && Number(item.branch_id) !== Number(selectedBranchFilter.value)) {
            return false
        }

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
            const branchMatch = (item.branch_name || '').toLowerCase().includes(q)
            return nameMatch || codeMatch || branchMatch
        }

        return true
    })
})

// ── Sorting ──────────────────────────────────────────────────────────────────
const sortedPredictions = computed(() => {
    const list = [...filteredPredictions.value]
    const field = sortBy.value
    const dir = sortDirection.value === 'asc' ? 1 : -1

    return list.sort((a, b) => {
        if (field === 'urgency') {
            const order = { critical: 1, warning: 2, safe: 3 }
            const valA = order[a.urgency] ?? 9
            const valB = order[b.urgency] ?? 9
            if (valA !== valB) return (valA - valB) * dir
            return (a.days_left - b.days_left) * dir
        }

        if (field === 'branch_name') {
            const nameA = (a.branch_name || '').toLowerCase()
            const nameB = (b.branch_name || '').toLowerCase()
            const cmp = nameA.localeCompare(nameB)
            if (cmp !== 0) return cmp * dir
            // Secondary sort by urgency
            const order = { critical: 1, warning: 2, safe: 3 }
            return (order[a.urgency] ?? 9) - (order[b.urgency] ?? 9)
        }

        if (field === 'item_name' || field === 'category_name') {
            const strA = (a[field] || '').toLowerCase()
            const strB = (b[field] || '').toLowerCase()
            return strA.localeCompare(strB) * dir
        }

        const numA = Number(a[field] ?? 0)
        const numB = Number(b[field] ?? 0)
        return (numA - numB) * dir
    })
})

// ── Pagination State & Computeds ─────────────────────────────────────────────
const currentPage = ref(1)
const perPage = ref(20)
const perPageOptions = [10, 20, 50, 100]

// Reset to page 1 whenever any filter, search, or sorting changes
watch([searchQuery, selectedUrgency, selectedCategory, selectedBranchFilter, perPage, sortBy, sortDirection], () => {
    currentPage.value = 1
})

const totalPages = computed(() => {
    return Math.ceil(sortedPredictions.value.length / perPage.value) || 1
})

const paginatedPredictions = computed(() => {
    const start = (currentPage.value - 1) * perPage.value
    return sortedPredictions.value.slice(start, start + perPage.value)
})

const displayedRange = computed(() => {
    const total = sortedPredictions.value.length
    if (total === 0) return { start: 0, end: 0, total: 0 }
    const start = (currentPage.value - 1) * perPage.value + 1
    const end = Math.min(currentPage.value * perPage.value, total)
    return { start, end, total }
})

const pageNumbers = computed(() => {
    const total = totalPages.value
    const cur = currentPage.value
    const pages = []

    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i)
    } else {
        pages.push(1)
        if (cur > 3) pages.push('...')
        const start = Math.max(2, cur - 1)
        const end = Math.min(total - 1, cur + 1)
        for (let i = start; i <= end; i++) {
            if (!pages.includes(i)) pages.push(i)
        }
        if (cur < total - 2) pages.push('...')
        if (!pages.includes(total)) pages.push(total)
    }
    return pages
})

function goToPage(p) {
    if (p === '...' || p < 1 || p > totalPages.value) return
    currentPage.value = p
}

// ── Server Filter Application & Notification Dispatch ────────────────────────
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
        onSuccess: () => {
            fetchInsight()
        }
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
        <div
            :class="[
                'space-y-6',
                layoutComponent === PosLayout ? 'h-full flex-1 min-h-0 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-50 dark:bg-slate-900/50' : ''
            ]"
        >
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
                        <span v-else-if="isSuperAdmin" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700">
                            All Branches (Cross-Branch)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Forecasts days-until-stockout based on rolling {{ summary?.velocity_days || 14 }}-day sales velocity and converts replenishment orders into optimal unit packaging variants.
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Branch Selector for Super Admin (Server Reload) -->
                    <div v-if="isSuperAdmin && branches?.length > 0">
                        <select
                            v-model="selectedBranchId"
                            @change="applyFilters"
                            class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 outline-none font-medium"
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

            <!-- AI Executive Strategy & Recommendation Banner (Async & Standard Markdown) -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-5 border border-indigo-500/30 shadow-lg space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">✨</span>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300">
                            AI Reorder Strategy & Recommendations
                        </h3>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Loading Status Pill -->
                        <span
                            v-if="isGeneratingInsight"
                            class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 animate-pulse font-mono"
                        >
                            <svg class="animate-spin h-3 w-3 text-indigo-400" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generating Strategy...
                        </span>

                        <span
                            v-else-if="aiInsight"
                            class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-200 border border-indigo-500/40 font-mono"
                        >
                            AI Verified
                        </span>

                        <!-- Refresh Strategy Button -->
                        <button
                            type="button"
                            @click="fetchInsight"
                            :disabled="isGeneratingInsight"
                            class="text-[11px] font-medium text-indigo-300 hover:text-white px-2 py-0.5 rounded-lg bg-indigo-900/40 hover:bg-indigo-800/60 border border-indigo-700/50 transition cursor-pointer flex items-center gap-1 disabled:opacity-50"
                            title="Regenerate strategic analysis"
                        >
                            <span>🔄</span>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                <!-- 1. Generating Shimmer Loading State -->
                <div v-if="isGeneratingInsight && !aiInsight" class="space-y-2.5 py-2 animate-pulse">
                    <div class="flex items-center gap-2 text-xs font-semibold text-indigo-200">
                        <span>Analyzing sales velocity, lead times & stockout risk factors...</span>
                    </div>
                    <div class="h-3.5 bg-indigo-900/50 rounded-full w-full"></div>
                    <div class="h-3.5 bg-indigo-900/50 rounded-full w-5/6"></div>
                    <div class="h-3.5 bg-indigo-900/50 rounded-full w-4/6"></div>
                    <div class="pt-2 space-y-2">
                        <div class="h-3 bg-indigo-950/70 rounded-full w-3/4"></div>
                        <div class="h-3 bg-indigo-950/70 rounded-full w-2/3"></div>
                    </div>
                </div>

                <!-- 2. Error Fallback State -->
                <div v-else-if="insightError" class="p-3 rounded-xl bg-rose-950/50 border border-rose-800/50 text-xs text-rose-200 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span>⚠️</span>
                        <span>{{ insightError }}</span>
                    </div>
                    <button
                        type="button"
                        @click="fetchInsight"
                        class="px-2.5 py-1 rounded bg-rose-800 hover:bg-rose-700 text-white font-bold text-[10px] transition cursor-pointer"
                    >
                        Retry
                    </button>
                </div>

                <!-- 3. Standard Markdown Rendered Insight -->
                <div
                    v-else-if="aiInsight"
                    class="ai-strategy-content text-xs text-indigo-100/90 leading-relaxed font-sans"
                    v-html="renderMarkdown(aiInsight)"
                ></div>
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

                    <!-- Search, Category & Super Admin Branch Filters -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Branch Filter Dropdown for Super Admin -->
                        <div v-if="isSuperAdmin && !branch && uniqueBranches.length > 0">
                            <select
                                v-model="selectedBranchFilter"
                                class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 outline-none font-medium"
                            >
                                <option value="all">🏢 All Branches</option>
                                <option v-for="b in uniqueBranches" :key="b.id" :value="b.id">
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <select
                            v-model="selectedCategory"
                            class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 outline-none"
                        >
                            <option value="all">All Categories</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>

                        <!-- Search Input -->
                        <div class="relative w-full sm:w-56">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Filter item, barcode, or branch..."
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-2 pl-8 rounded-xl border border-slate-300 dark:border-slate-600 outline-none focus:border-emerald-500 transition"
                            />
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
                        </div>
                    </div>
                </div>

                <!-- Sort Control Toolbar -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">Sort By:</span>

                        <!-- Quick Sort Dropdown -->
                        <select
                            v-model="sortBy"
                            class="bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-600 outline-none font-medium"
                        >
                            <option v-if="isSuperAdmin && !branch" value="branch_name">Branch Name (A-Z / Z-A)</option>
                            <option value="urgency">Urgency (Critical First)</option>
                            <option value="days_left">Days Remaining (Lowest First)</option>
                            <option value="avg_daily_sales">Sales Velocity (Highest First)</option>
                            <option value="item_name">Product Name (A-Z)</option>
                            <option value="suggested_reorder_units">Suggested Reorder (Highest First)</option>
                            <option value="total_stock">Total Stock (Lowest First)</option>
                            <option value="category_name">Category (A-Z)</option>
                        </select>

                        <!-- Sort Direction Toggle -->
                        <button
                            type="button"
                            @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'"
                            class="px-2 py-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 text-xs font-bold transition cursor-pointer flex items-center gap-1"
                        >
                            <span>{{ sortDirection === 'asc' ? '▲ Ascending' : '▼ Descending' }}</span>
                        </button>
                    </div>

                    <div class="text-[11px] text-slate-400">
                        Click column headers to sort instantly
                    </div>
                </div>
            </div>

            <!-- Predictions Table (Paginated & Sortable) -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 select-none">
                            <tr>
                                <!-- Branch Column for Super Admin -->
                                <th
                                    v-if="isSuperAdmin && !branch"
                                    @click="setSort('branch_name')"
                                    class="px-4 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center gap-1">
                                        <span>Branch / Store</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('branch_name') }}</span>
                                    </div>
                                </th>

                                <!-- Product & Barcode -->
                                <th
                                    @click="setSort('item_name')"
                                    class="px-4 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center gap-1">
                                        <span>Product & Barcode</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('item_name') }}</span>
                                    </div>
                                </th>

                                <!-- Category -->
                                <th
                                    @click="setSort('category_name')"
                                    class="px-4 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center gap-1">
                                        <span>Category</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('category_name') }}</span>
                                    </div>
                                </th>

                                <!-- In Stock -->
                                <th
                                    @click="setSort('total_stock')"
                                    class="px-4 py-3.5 text-center cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center justify-center gap-1">
                                        <span>In Stock (Front/Back)</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('total_stock') }}</span>
                                    </div>
                                </th>

                                <!-- Daily Velocity -->
                                <th
                                    @click="setSort('avg_daily_sales')"
                                    class="px-4 py-3.5 text-center cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Daily Velocity</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('avg_daily_sales') }}</span>
                                    </div>
                                </th>

                                <!-- Days Remaining -->
                                <th
                                    @click="setSort('days_left')"
                                    class="px-4 py-3.5 text-center cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Days Remaining</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('days_left') }}</span>
                                    </div>
                                </th>

                                <!-- Suggested Reorder -->
                                <th
                                    @click="setSort('suggested_reorder_units')"
                                    class="px-4 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center gap-1">
                                        <span>Suggested Reorder</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('suggested_reorder_units') }}</span>
                                    </div>
                                </th>

                                <!-- Status / Urgency -->
                                <th
                                    @click="setSort('urgency')"
                                    class="px-4 py-3.5 text-center cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Status</span>
                                        <span class="text-[10px] text-indigo-500 font-mono">{{ getSortIcon('urgency') }}</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr
                                v-for="item in paginatedPredictions"
                                :key="`${item.branch_id}_${item.item_id}`"
                                class="hover:bg-slate-100/70 dark:hover:bg-slate-700/60 transition"
                            >
                                <!-- Branch Name for Super Admin -->
                                <td v-if="isSuperAdmin && !branch" class="px-4 py-3">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-600 inline-flex items-center gap-1">
                                        <span>🏢</span>
                                        <span>{{ item.branch_name }}</span>
                                    </span>
                                </td>

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

                            <tr v-if="sortedPredictions.length === 0">
                                <td :colspan="isSuperAdmin && !branch ? 8 : 7" class="text-center py-12 text-slate-400 dark:text-slate-500 text-xs">
                                    No inventory items match the selected filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Toolbar -->
                <div
                    v-if="sortedPredictions.length > 0"
                    class="px-4 py-3 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600 dark:text-slate-400"
                >
                    <!-- Left: Range Summary & Per Page Selector -->
                    <div class="flex items-center gap-3">
                        <span>
                            Showing <strong class="text-slate-900 dark:text-white font-mono">{{ displayedRange.start }}</strong> to <strong class="text-slate-900 dark:text-white font-mono">{{ displayedRange.end }}</strong> of <strong class="text-slate-900 dark:text-white font-mono">{{ displayedRange.total }}</strong> items
                        </span>

                        <div class="flex items-center gap-1.5">
                            <label class="text-[11px] text-slate-400">Per page:</label>
                            <select
                                v-model="perPage"
                                class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs px-2 py-1 rounded-lg border border-slate-300 dark:border-slate-600 outline-none"
                            >
                                <option v-for="opt in perPageOptions" :key="opt" :value="opt">
                                    {{ opt }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Right: Pagination Buttons -->
                    <div class="flex items-center gap-1">
                        <!-- First Page -->
                        <button
                            type="button"
                            @click="goToPage(1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer"
                            title="First Page"
                        >
                            «
                        </button>

                        <!-- Previous -->
                        <button
                            type="button"
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer"
                        >
                            ‹ Prev
                        </button>

                        <!-- Page Numbers -->
                        <button
                            v-for="(p, idx) in pageNumbers"
                            :key="idx"
                            type="button"
                            @click="goToPage(p)"
                            :disabled="p === '...'"
                            :class="[
                                'min-w-7 px-2 py-1 rounded-lg text-xs font-bold transition font-mono',
                                p === currentPage
                                    ? 'bg-emerald-600 text-white shadow-2xs'
                                    : p === '...'
                                        ? 'cursor-default text-slate-400 dark:text-slate-500'
                                        : 'border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 cursor-pointer'
                            ]"
                        >
                            {{ p }}
                        </button>

                        <!-- Next -->
                        <button
                            type="button"
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer"
                        >
                            Next ›
                        </button>

                        <!-- Last Page -->
                        <button
                            type="button"
                            @click="goToPage(totalPages)"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer"
                            title="Last Page"
                        >
                            »
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
/* Scoped Typography & Markdown Styling for AI Reorder Strategy */
.ai-strategy-content :deep(h1),
.ai-strategy-content :deep(h2),
.ai-strategy-content :deep(h3),
.ai-strategy-content :deep(h4) {
    font-weight: 800;
    color: #e0e7ff; /* indigo-100 */
    margin-top: 0.85rem;
    margin-bottom: 0.35rem;
    letter-spacing: -0.01em;
}

.ai-strategy-content :deep(h1) { font-size: 1rem; }
.ai-strategy-content :deep(h2) { font-size: 0.95rem; }
.ai-strategy-content :deep(h3) { font-size: 0.875rem; color: #a5b4fc; } /* indigo-300 */
.ai-strategy-content :deep(h4) { font-size: 0.825rem; color: #818cf8; } /* indigo-400 */

.ai-strategy-content :deep(p) {
    margin-bottom: 0.65rem;
    line-height: 1.55;
    color: rgba(224, 231, 255, 0.9);
}

.ai-strategy-content :deep(strong) {
    font-weight: 700;
    color: #ffffff;
}

.ai-strategy-content :deep(ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.65rem;
    space-y: 0.25rem;
}

.ai-strategy-content :deep(ol) {
    list-style-type: decimal;
    padding-left: 1.25rem;
    margin-bottom: 0.65rem;
}

.ai-strategy-content :deep(li) {
    margin-bottom: 0.25rem;
    line-height: 1.5;
}

.ai-strategy-content :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.85em;
    padding: 0.15em 0.35em;
    border-radius: 0.375rem;
    background: rgba(99, 102, 241, 0.2);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #c7d2fe;
}

.ai-strategy-content :deep(blockquote) {
    border-left: 3px solid #6366f1;
    padding-left: 0.75rem;
    margin: 0.5rem 0;
    font-style: italic;
    color: #c7d2fe;
}
</style>
