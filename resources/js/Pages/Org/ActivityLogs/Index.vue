<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import OrgLayout from '@/Layouts/OrgLayout.vue'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization: Object,
    logs: Object,
    branches: Array,
    users: Array,
    actionTypes: Array,
    filters: Object,
})

// Filter states
const searchFilter   = ref(props.filters?.search || '')
const selectedBranch = ref(props.filters?.branch_id || '')
const selectedType   = ref(props.filters?.action_type || '')
const selectedUser   = ref(props.filters?.user_id || '')
const startDate      = ref(props.filters?.start_date || '')
const endDate        = ref(props.filters?.end_date || '')
const activePreset   = ref('all')

const selectedLogModal = ref(null)
const exportMenuOpen   = ref(false)
const exportDropdownRef = ref(null)

// Date Range Presets
const setPreset = (preset) => {
    activePreset.value = preset
    const today = new Date()
    const fmt = (d) => {
        const year = d.getFullYear()
        const month = String(d.getMonth() + 1).padStart(2, '0')
        const day = String(d.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    if (preset === 'today') {
        startDate.value = fmt(today)
        endDate.value = fmt(today)
    } else if (preset === 'yesterday') {
        const y = new Date()
        y.setDate(y.getDate() - 1)
        startDate.value = fmt(y)
        endDate.value = fmt(y)
    } else if (preset === 'week') {
        const curr = new Date()
        const day = curr.getDay()
        const diff = curr.getDate() - day + (day === 0 ? -6 : 1)
        const mon = new Date(curr.setDate(diff))
        startDate.value = fmt(mon)
        endDate.value = fmt(new Date())
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)
        startDate.value = fmt(firstDay)
        endDate.value = fmt(new Date())
    } else if (preset === 'all') {
        startDate.value = ''
        endDate.value = ''
    }
    applyFilters()
}

let searchTimer = null
const onSearchInput = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        applyFilters()
    }, 350)
}

function applyFilters() {
    router.get(route('org.activity-logs'), {
        search:      searchFilter.value || undefined,
        branch_id:   selectedBranch.value || undefined,
        action_type: selectedType.value || undefined,
        user_id:     selectedUser.value || undefined,
        start_date:  startDate.value || undefined,
        end_date:    endDate.value || undefined,
    }, { preserveState: true, preserveScroll: true })
}

function resetFilters() {
    searchFilter.value   = ''
    selectedBranch.value = ''
    selectedType.value   = ''
    selectedUser.value   = ''
    startDate.value      = ''
    endDate.value        = ''
    activePreset.value   = 'all'
    applyFilters()
}

const downloadExport = (format = 'xlsx') => {
    exportMenuOpen.value = false
    const params = new URLSearchParams()
    params.set('format', format)
    if (searchFilter.value)   params.set('search', searchFilter.value.trim())
    if (selectedBranch.value) params.set('branch_id', selectedBranch.value)
    if (selectedType.value)   params.set('action_type', selectedType.value)
    if (selectedUser.value)   params.set('user_id', selectedUser.value)
    if (startDate.value)      params.set('start_date', startDate.value)
    if (endDate.value)        params.set('end_date', endDate.value)
    window.location.href = `${route('org.activity-logs.export')}?${params.toString()}`
}

const handleClickOutside = (e) => {
    if (exportDropdownRef.value && !exportDropdownRef.value.contains(e.target)) {
        exportMenuOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

function getBadgeClass(type) {
    switch (type) {
        case 'sale':    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        case 'stock':   return 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border-blue-200 dark:border-blue-800'
        case 'return':  return 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800'
        case 'auth':    return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
        case 'item':    return 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 border-purple-200 dark:border-purple-800'
        case 'user':    return 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800'
        case 'setting': return 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800'
        case 'branch':  return 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300 border-violet-200 dark:border-violet-800'
        default:        return 'bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
    }
}

const currentBranchName = computed(() => {
    if (!selectedBranch.value) return 'All Branches'
    const b = props.branches.find(x => x.id === Number(selectedBranch.value))
    return b ? b.name : 'Selected Branch'
})
</script>

<template>
    <Head :title="`Activity Logs — ${organization.name}`" />

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <Link :href="route('org.dashboard')" class="hover:text-purple-600 transition">Dashboard</Link>
                    <span>/</span>
                    <span class="text-slate-800 dark:text-slate-200 font-semibold">Activity Logs</span>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Branch Activity & Audit Logs</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200 dark:border-purple-800">
                        {{ currentBranchName }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        {{ logs.total.toLocaleString() }} total entries
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Audited operational events, transactions, stock adjustments, and staff activities across {{ organization.name }} branches.
                </p>
            </div>

            <!-- Export Buttons -->
            <div class="flex items-center gap-2.5">
                <div class="relative" ref="exportDropdownRef">
                    <button
                        @click="exportMenuOpen = !exportMenuOpen"
                        type="button"
                        class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-purple-600/20 flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Export Logs</span>
                        <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-180': exportMenuOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div
                        v-if="exportMenuOpen"
                        class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl z-30 p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-100"
                    >
                        <button
                            @click="downloadExport('xlsx')"
                            class="w-full text-left px-3.5 py-2 text-xs rounded-xl hover:bg-purple-50 dark:hover:bg-purple-950/50 text-slate-700 dark:text-slate-200 font-semibold flex items-center gap-2 transition cursor-pointer"
                        >
                            <span class="text-base">📊</span>
                            <span>Excel Spreadsheet (.xlsx)</span>
                        </button>
                        <button
                            @click="downloadExport('csv')"
                            class="w-full text-left px-3.5 py-2 text-xs rounded-xl hover:bg-purple-50 dark:hover:bg-purple-950/50 text-slate-700 dark:text-slate-200 font-semibold flex items-center gap-2 transition cursor-pointer"
                        >
                            <span class="text-base">📄</span>
                            <span>CSV Document (.csv)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Controls Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
            <!-- Row 1: Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Branch Selector -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Filter by Branch</label>
                    <select
                        v-model="selectedBranch"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition cursor-pointer"
                    >
                        <option value="">🏪 All Organization Branches</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }} (/{{ branch.slug }})
                        </option>
                    </select>
                </div>

                <!-- Action Type Selector -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Action Category</label>
                    <select
                        v-model="selectedType"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition cursor-pointer capitalize"
                    >
                        <option value="">⚡ All Categories</option>
                        <option v-for="type in actionTypes" :key="type" :value="type">
                            {{ type.toUpperCase() }}
                        </option>
                    </select>
                </div>

                <!-- Staff Member Selector -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Staff Member</label>
                    <select
                        v-model="selectedUser"
                        @change="applyFilters"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition cursor-pointer"
                    >
                        <option value="">👤 All Staff Members</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} (@{{ user.username }})
                        </option>
                    </select>
                </div>

                <!-- Search Input -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Keywords</label>
                    <div class="relative">
                        <input
                            v-model="searchFilter"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Receipt ID, staff, action..."
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 pr-8 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-none focus:border-purple-500 transition"
                        />
                        <button
                            v-if="searchFilter"
                            @click="searchFilter = ''; applyFilters()"
                            type="button"
                            class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 2: Date Range & Quick Presets -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs">
                <!-- Date Presets -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-slate-400 font-semibold mr-1">Period:</span>
                    <button
                        v-for="p in [
                            { key: 'all', label: 'All Time' },
                            { key: 'today', label: 'Today' },
                            { key: 'yesterday', label: 'Yesterday' },
                            { key: 'week', label: 'This Week' },
                            { key: 'month', label: 'This Month' },
                        ]"
                        :key="p.key"
                        @click="setPreset(p.key)"
                        type="button"
                        :class="activePreset === p.key
                            ? 'bg-purple-600 text-white font-bold'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition cursor-pointer"
                    >
                        {{ p.label }}
                    </button>
                </div>

                <!-- Custom Range Inputs & Reset -->
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="flex items-center gap-1">
                        <span class="text-slate-400 text-[11px]">From:</span>
                        <input
                            v-model="startDate"
                            @change="activePreset = 'custom'; applyFilters()"
                            type="date"
                            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-[11px] text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500"
                        />
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-slate-400 text-[11px]">To:</span>
                        <input
                            v-model="endDate"
                            @change="activePreset = 'custom'; applyFilters()"
                            type="date"
                            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-[11px] text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500"
                        />
                    </div>

                    <button
                        @click="resetFilters"
                        type="button"
                        class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-[11px] font-semibold transition cursor-pointer ml-auto sm:ml-0"
                    >
                        Reset All
                    </button>
                </div>
            </div>
        </div>

        <!-- Logs Table Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
            <div v-if="logs.data.length > 0" class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-5 py-3.5">Timestamp</th>
                            <th class="px-5 py-3.5">Branch</th>
                            <th class="px-5 py-3.5">Category</th>
                            <th class="px-5 py-3.5">Staff</th>
                            <th class="px-5 py-3.5">Log Description</th>
                            <th class="px-5 py-3.5">IP Address</th>
                            <th class="px-5 py-3.5 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition"
                        >
                            <!-- Timestamp -->
                            <td class="px-5 py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                <div>{{ new Date(log.created_at).toLocaleDateString() }}</div>
                                <div class="text-[10px] text-slate-400">{{ new Date(log.created_at).toLocaleTimeString() }}</div>
                            </td>

                            <!-- Branch Location -->
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span v-if="log.branch" class="font-bold text-slate-900 dark:text-white">
                                    {{ log.branch.name }}
                                </span>
                                <span v-else class="text-slate-400 italic">Organization</span>
                            </td>

                            <!-- Category / Action Type -->
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span
                                    :class="getBadgeClass(log.action_type)"
                                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider"
                                >
                                    {{ log.action_type || 'INFO' }}
                                </span>
                            </td>

                            <!-- Staff Member -->
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div v-if="log.user">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ log.user.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">@{{ log.user.username }}</div>
                                </div>
                                <span v-else class="text-slate-400 italic">System Automation</span>
                            </td>

                            <!-- Message -->
                            <td class="px-5 py-3 text-slate-700 dark:text-slate-300 max-w-md">
                                <div class="line-clamp-2 leading-relaxed font-sans text-xs">
                                    {{ log.log }}
                                </div>
                            </td>

                            <!-- IP Address -->
                            <td class="px-5 py-3 font-mono text-[11px] text-slate-400 whitespace-nowrap">
                                {{ log.ip_address || '—' }}
                            </td>

                            <!-- Action / Details Modal -->
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button
                                    v-if="log.details && Object.keys(log.details).length > 0"
                                    @click="selectedLogModal = log"
                                    type="button"
                                    class="px-2.5 py-1 text-[11px] font-semibold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/40 hover:bg-purple-100 rounded-lg transition cursor-pointer border border-purple-200 dark:border-purple-800"
                                >
                                    View Payload
                                </button>
                                <span v-else class="text-slate-300 dark:text-slate-700 text-[11px]">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-else class="py-16 text-center space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center mx-auto text-xl">
                    📜
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No Activity Logs Found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    No log events match your current filter parameters. Try expanding your search or selecting a different date range.
                </p>
                <button
                    @click="resetFilters"
                    type="button"
                    class="px-4 py-2 bg-purple-600 text-white rounded-xl text-xs font-bold transition hover:bg-purple-500 cursor-pointer"
                >
                    Clear Filters
                </button>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="logs.data.length > 0 && logs.links && logs.links.length > 3"
                class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500"
            >
                <div>
                    Showing <strong>{{ logs.from }}</strong> to <strong>{{ logs.to }}</strong> of <strong>{{ logs.total }}</strong> logs
                </div>

                <div class="flex items-center gap-1 flex-wrap">
                    <Component
                        v-for="(link, i) in logs.links"
                        :key="i"
                        :is="link.url ? Link : 'span'"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 rounded-lg font-semibold transition',
                            link.active
                                ? 'bg-purple-600 text-white font-bold shadow-xs'
                                : link.url
                                    ? 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
                                    : 'text-slate-300 dark:text-slate-700 cursor-not-allowed'
                        ]"
                    />
                </div>
            </div>
        </div>

        <!-- ── LOG DETAILS MODAL ──────────────────────────────────────────────── -->
        <div v-if="selectedLogModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150 max-h-[85vh] overflow-y-auto">
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span :class="getBadgeClass(selectedLogModal.action_type)" class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase">
                                {{ selectedLogModal.action_type || 'INFO' }}
                            </span>
                            <span class="text-xs font-mono text-slate-400">#{{ selectedLogModal.id }}</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mt-1">Activity Log Details</h3>
                    </div>
                    <button @click="selectedLogModal = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                        <div>
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase">Branch Location</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ selectedLogModal.branch?.name || 'Organization' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase">Staff Member</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ selectedLogModal.user?.name || 'System' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase">Timestamp</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300">{{ new Date(selectedLogModal.created_at).toLocaleString() }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold text-[10px] uppercase">IP Address</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300">{{ selectedLogModal.ip_address || '—' }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-400 font-semibold text-[10px] uppercase mb-1">Log Message</label>
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl text-slate-800 dark:text-slate-200 font-sans">
                            {{ selectedLogModal.log }}
                        </div>
                    </div>

                    <div v-if="selectedLogModal.details">
                        <label class="block text-slate-400 font-semibold text-[10px] uppercase mb-1">Structured Metadata (JSON)</label>
                        <pre class="p-3 bg-slate-950 text-emerald-400 rounded-xl font-mono text-[11px] overflow-x-auto max-h-60">{{ JSON.stringify(selectedLogModal.details, null, 2) }}</pre>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                    <button
                        @click="selectedLogModal = null"
                        type="button"
                        class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
