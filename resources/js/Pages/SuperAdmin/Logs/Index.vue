<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    logs:     Object,
    branches: Array,
    users:    Array,
    filters:  Object,
})

const searchFilter     = ref(props.filters?.search || '')
const selectedBranch   = ref(props.filters?.branch_id || '')
const selectedType     = ref(props.filters?.action_type || '')
const selectedUser     = ref(props.filters?.user_id || '')
const startDate        = ref(props.filters?.start_date || '')
const endDate          = ref(props.filters?.end_date || '')
const activePreset     = ref('custom')

const selectedLogModal = ref(null)

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

function applyFilters() {
    router.get(route('superadmin.logs.index'), {
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

// ── Export Dropdown ──────────────────────────────────────────────────────────
const exportMenuOpen = ref(false)
const exportDropdownRef = ref(null)

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
    window.location.href = `${route('superadmin.logs.export')}?${params.toString()}`
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
        case 'sale':    return 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'
        case 'stock':   return 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'
        case 'return':  return 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'
        case 'auth':    return 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        case 'item':    return 'bg-cyan-100 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800'
        case 'user':    return 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 border-teal-200 dark:border-teal-800'
        case 'setting': return 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
        default:        return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'
    }
}
</script>

<template>
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">System Activity & Error Logs</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Master audit trail of all transactions, inventory movements, logins, setting changes, and system errors.</p>
            </div>
            <div class="flex items-center gap-2.5">
                <!-- Export Dropdown -->
                <div class="relative" ref="exportDropdownRef">
                    <button
                        type="button"
                        @click="exportMenuOpen = !exportMenuOpen"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center space-x-1.5 cursor-pointer"
                        title="Export filtered activity logs"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export Logs</span>
                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="exportMenuOpen"
                        class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
                    >
                        <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            Export Logs List
                        </div>
                        <button
                            type="button"
                            @click="downloadExport('xlsx')"
                            class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                                XLS
                            </span>
                            Excel (.xlsx)
                        </button>
                        <button
                            type="button"
                            @click="downloadExport('csv')"
                            class="w-full text-left px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-950/40 text-slate-700 dark:text-slate-200 hover:text-blue-700 dark:hover:text-blue-300 flex items-center gap-2 transition cursor-pointer font-medium"
                        >
                            <span class="w-5 h-5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-[10px]">
                                CSV
                            </span>
                            CSV Document (.csv)
                        </button>
                    </div>
                </div>

                <button
                    @click="resetFilters"
                    class="px-4 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition cursor-pointer"
                >
                    Reset All Filters
                </button>
            </div>
        </div>

        <!-- Filter Bar Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <!-- Date Presets -->
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mr-1">Date Presets:</span>
                <button
                    type="button"
                    @click="setPreset('all')"
                    :class="activePreset === 'all' && !startDate && !endDate
                        ? 'bg-indigo-600 text-white shadow-xs'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                >
                    All Time
                </button>
                <button
                    type="button"
                    @click="setPreset('today')"
                    :class="activePreset === 'today'
                        ? 'bg-indigo-600 text-white shadow-xs'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                >
                    Today
                </button>
                <button
                    type="button"
                    @click="setPreset('yesterday')"
                    :class="activePreset === 'yesterday'
                        ? 'bg-indigo-600 text-white shadow-xs'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                >
                    Yesterday
                </button>
                <button
                    type="button"
                    @click="setPreset('week')"
                    :class="activePreset === 'week'
                        ? 'bg-indigo-600 text-white shadow-xs'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                >
                    This Week
                </button>
                <button
                    type="button"
                    @click="setPreset('month')"
                    :class="activePreset === 'month'
                        ? 'bg-indigo-600 text-white shadow-xs'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                >
                    This Month
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3">
                <!-- Search Input -->
                <div class="lg:col-span-2">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Search Keywords</label>
                    <input
                        type="text"
                        v-model="searchFilter"
                        @keyup.enter="applyFilters"
                        placeholder="Search logs, IP, users..."
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                    />
                </div>

                <!-- Branch Selector -->
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Filter Branch</label>
                    <select
                        v-model="selectedBranch"
                        @change="applyFilters"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                    >
                        <option value="">All Branches & System</option>
                        <option value="system">Global System Only</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <!-- Action Type Filter -->
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Action Category</label>
                    <select
                        v-model="selectedType"
                        @change="applyFilters"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                    >
                        <option value="">All Categories</option>
                        <option value="sale">Sales Transactions</option>
                        <option value="stock">Inventory & Stock</option>
                        <option value="return">Sales Returns</option>
                        <option value="item">Items & Catalog</option>
                        <option value="user">User & Staff Accounts</option>
                        <option value="auth">User Auth & Logins</option>
                        <option value="setting">Settings Changes</option>
                    </select>
                </div>

                <!-- User Filter -->
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Filter User</label>
                    <select
                        v-model="selectedUser"
                        @change="applyFilters"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                    >
                        <option value="">All System Users</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>

                <!-- Start Date -->
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Start Date</label>
                    <input
                        type="date"
                        v-model="startDate"
                        @change="activePreset = 'custom'; applyFilters()"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                    />
                </div>

                <!-- End Date -->
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">End Date</label>
                    <input
                        type="date"
                        v-model="endDate"
                        @change="activePreset = 'custom'; applyFilters()"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs"
                    />
                </div>
            </div>
        </div>

        <!-- Logs Table Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                            <th class="py-3.5 px-4">Timestamp</th>
                            <th class="py-3.5 px-4">Category</th>
                            <th class="py-3.5 px-4">Branch</th>
                            <th class="py-3.5 px-4">User</th>
                            <th class="py-3.5 px-4">Log Description</th>
                            <th class="py-3.5 px-4">IP Address</th>
                            <th class="py-3.5 px-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs">
                        <tr v-if="!logs.data || logs.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No activity or error logs found matching your filters.
                            </td>
                        </tr>
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                        >
                            <td class="py-3 px-4 text-slate-500 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                {{ new Date(log.created_at).toLocaleString() }}
                            </td>
                            <td class="py-3 px-4">
                                <span :class="['px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border', getBadgeClass(log.action_type)]">
                                    {{ log.action_type || 'info' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                <span v-if="log.branch" class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-theme"></span>
                                    {{ log.branch.name }}
                                </span>
                                <span v-else class="text-slate-400 italic">Global System</span>
                            </td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300 font-medium whitespace-nowrap">
                                {{ log.user ? log.user.name : 'System / Guest' }}
                            </td>
                            <td class="py-3 px-4 text-slate-800 dark:text-slate-200 font-medium max-w-md truncate">
                                {{ log.log }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                {{ log.ip_address || '—' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button
                                    @click="selectedLogModal = log"
                                    class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-theme hover:text-white dark:hover:bg-theme text-slate-700 dark:text-slate-300 font-bold text-[11px] rounded-lg transition"
                                >
                                    View Payload
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="logs.links && logs.links.length > 3" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <span class="text-xs text-slate-500">Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} log records</span>
                <div class="flex gap-1">
                    <template v-for="(link, i) in logs.links" :key="i">
                        <Component
                            :is="link.url ? Link : 'span'"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                link.active ? 'bg-theme text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200',
                                !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Log Details Modal -->
        <div v-if="selectedLogModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span :class="['px-2 py-0.5 rounded-md text-[10px] font-bold uppercase', getBadgeClass(selectedLogModal.action_type)]">
                            {{ selectedLogModal.action_type }}
                        </span>
                        Log Record Details #{{ selectedLogModal.id }}
                    </h3>
                    <button @click="selectedLogModal = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-white font-bold text-lg">×</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 font-bold uppercase text-[10px] block">Log Description</span>
                        <p class="text-slate-800 dark:text-slate-200 font-semibold mt-0.5">{{ selectedLogModal.log }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-slate-400 font-bold uppercase text-[10px] block">Branch</span>
                            <p class="text-slate-800 dark:text-slate-200 font-medium">{{ selectedLogModal.branch ? selectedLogModal.branch.name : 'Global System' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold uppercase text-[10px] block">User</span>
                            <p class="text-slate-800 dark:text-slate-200 font-medium">{{ selectedLogModal.user ? selectedLogModal.user.name + ' (' + selectedLogModal.user.email + ')' : 'System / Guest' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold uppercase text-[10px] block">IP Address</span>
                            <p class="font-mono text-slate-800 dark:text-slate-200">{{ selectedLogModal.ip_address || 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold uppercase text-[10px] block">Timestamp</span>
                            <p class="font-mono text-slate-800 dark:text-slate-200">{{ new Date(selectedLogModal.created_at).toLocaleString() }}</p>
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400 font-bold uppercase text-[10px] block mb-1">Payload / Error Details</span>
                        <pre class="p-3 bg-slate-900 text-emerald-400 font-mono text-[11px] rounded-xl overflow-x-auto max-h-60 border border-slate-800">{{ JSON.stringify(selectedLogModal.details || {}, null, 2) }}</pre>
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button @click="selectedLogModal = null" class="px-5 py-2 bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-bold text-xs rounded-xl hover:bg-slate-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
