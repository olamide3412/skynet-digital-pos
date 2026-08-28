<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branches: Array,
})

const showCreateModal = ref(false)
const showAdminPass = ref(false)

const form = useForm({
    name: '',
    slug: '',
    address: '',
    phone: '',
    email: '',
    is_offline_enabled: false,
    is_turnstile_enabled: true,
    admin_name: '',
    admin_username: '',
    admin_email: '',
    admin_password: '',
})

const generateSlug = () => {
    form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '')
}

const submitCreate = () => {
    form.post(route('superadmin.branches.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            form.reset()
        },
    })
}

const toggleBranch = (branchSlug) => {
    router.post(route('superadmin.branches.toggle', { branch: branchSlug }))
}

const toggleOffline = (branchSlug) => {
    router.post(route('superadmin.branches.toggle-offline', { branch: branchSlug }))
}

const toggleTurnstile = (branchSlug) => {
    router.post(route('superadmin.branches.toggle-turnstile', { branch: branchSlug }), {}, {
        preserveScroll: true,
    })
}

const copiedBranchSlug = ref(null)
const copyBranchUrl = (slug) => {
    const fullUrl = typeof window !== 'undefined' && window.location?.origin
        ? `${window.location.origin}/${slug}`
        : `https://skynetpos.com/${slug}`
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(fullUrl)
    }
    copiedBranchSlug.value = slug
    setTimeout(() => {
        if (copiedBranchSlug.value === slug) copiedBranchSlug.value = null
    }, 2000)
}

// Export Items State
const showExportModal = ref(false)
const exportingBranch = ref(null)
const exportForm = ref({
    format: 'xlsx',
    quantity_option: 'total',
})

const openExportModal = (branch) => {
    exportingBranch.value = branch
    exportForm.value = {
        format: 'xlsx',
        quantity_option: 'total',
    }
    showExportModal.value = true
}

const triggerExport = () => {
    if (!exportingBranch.value) return
    const url = route('superadmin.branches.export-items', {
        branch: exportingBranch.value.slug,
        format: exportForm.value.format,
        quantity_option: exportForm.value.quantity_option,
    })
    window.location.href = url
    showExportModal.value = false
}
</script>

<template>
    <Head title="Manage Branches - Super Admin" />
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Branches Directory</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage registered business locations, offline sales capabilities, and initial branch administrators</p>
            </div>
            <button
                @click="showCreateModal = true"
                class="w-full sm:w-auto px-4 py-2.5 bg-theme hover:opacity-90 text-white font-bold rounded-xl text-xs transition shadow-md flex items-center justify-center space-x-2"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Create New Branch</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="branch in branches"
                :key="branch.id"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-4 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex flex-col justify-between"
            >
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white text-sm font-black shadow-xs overflow-hidden flex-shrink-0">
                                <img v-if="branch.logo_url" :src="branch.logo_url" :alt="branch.name" class="w-full h-full object-cover" />
                                <span v-else>{{ branch.name.substring(0, 2).toUpperCase() }}</span>
                            </div>
                            <div>
                                <Link
                                    :href="route('superadmin.branches.show', { branch: branch.slug })"
                                    class="font-bold text-slate-900 dark:text-slate-100 text-base leading-tight hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                >
                                    {{ branch.name }}
                                </Link>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-xs text-indigo-600 dark:text-indigo-400 font-mono">/{{ branch.slug }}</span>
                                    <button
                                        @click="copyBranchUrl(branch.slug)"
                                        type="button"
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 p-0.5 rounded transition cursor-pointer flex items-center gap-1"
                                        title="Copy full branch share link"
                                    >
                                        <svg v-if="copiedBranchSlug !== branch.slug" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <span v-if="copiedBranchSlug === branch.slug" class="inline-flex items-center gap-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-200 dark:border-emerald-500/20">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            Copied!
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <span
                            :class="branch.is_active ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/30'"
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                        >
                            {{ branch.is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </div>

                    <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                        <div v-if="branch.address" class="line-clamp-1"><span class="text-slate-400 dark:text-slate-500 font-medium">📍</span> {{ branch.address }}</div>
                        <div v-if="branch.phone"><span class="text-slate-400 dark:text-slate-500 font-medium">📞</span> {{ branch.phone }}</div>
                        <div v-if="branch.email"><span class="text-slate-400 dark:text-slate-500 font-medium">✉️</span> {{ branch.email }}</div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500">
                            <span>Staff: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.users_count }}</strong></span>
                            <span>Items: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.items_count }}</strong></span>
                            <span>Sales: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.sales_count }}</strong></span>
                        </div>
                    </div>

                    <!-- Offline Capability Toggle Control (Super Admin only) -->
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">📡</span>
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Offline Sales</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                    {{ branch.settings?.is_offline_enabled ? 'Active on terminals' : 'Disabled' }}
                                </div>
                            </div>
                        </div>
                        <button
                            @click="toggleOffline(branch.slug)"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer',
                                branch.settings?.is_offline_enabled
                                    ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs'
                                    : 'bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200'
                            ]"
                            :title="branch.settings?.is_offline_enabled ? 'Click to disable offline checkout for this branch' : 'Click to enable offline checkout for this branch'"
                        >
                            <span v-if="branch.settings?.is_offline_enabled">● Enabled</span>
                            <span v-else>Disabled</span>
                        </button>
                    </div>

                    <!-- Cloudflare CAPTCHA Toggle Control (Super Admin only) -->
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🛡️</span>
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Cloudflare CAPTCHA</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                    {{ (branch.settings?.is_turnstile_enabled ?? true) ? 'Active on login' : 'Bypassed (Fast Login)' }}
                                </div>
                            </div>
                        </div>
                        <button
                            @click="toggleTurnstile(branch.slug)"
                            type="button"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer',
                                (branch.settings?.is_turnstile_enabled ?? true)
                                    ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs'
                                    : 'bg-amber-100 dark:bg-amber-950/60 hover:bg-amber-200 dark:hover:bg-amber-900/80 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-700'
                            ]"
                            :title="(branch.settings?.is_turnstile_enabled ?? true) ? 'Click to bypass Cloudflare CAPTCHA for slow networks' : 'Click to enable Cloudflare CAPTCHA verification'"
                        >
                            <span v-if="(branch.settings?.is_turnstile_enabled ?? true)">● Enabled</span>
                            <span v-else>⚡ Bypassed</span>
                        </button>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-2">
                    <!-- Primary Manage Branch CTA -->
                    <Link
                        :href="route('superadmin.branches.show', { branch: branch.slug })"
                        class="w-full text-center py-2.5 px-4 text-xs bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-1.5"
                    >
                        <span>Manage Branch Workspace</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </Link>

                    <div class="flex items-center gap-1.5 flex-wrap">
                        <Link
                            :href="route('pos.index', { branch: branch.slug })"
                            target="_blank"
                            class="flex-1 min-w-[80px] text-center py-1.5 text-xs bg-indigo-50 dark:bg-indigo-600/20 hover:bg-indigo-100 dark:hover:bg-indigo-600/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 rounded-lg font-semibold transition-colors"
                        >
                            Open POS &rarr;
                        </Link>
                        <button
                            @click="openExportModal(branch)"
                            type="button"
                            class="px-2.5 py-1.5 text-xs bg-emerald-50 dark:bg-emerald-950/30 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 rounded-lg font-semibold transition-colors flex items-center gap-1 cursor-pointer"
                            title="Download branch items in CSV or Excel format"
                        >
                            <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Export</span>
                        </button>
                        <Link
                            :href="route('superadmin.branches.users.index', { branch: branch.slug })"
                            class="px-2.5 py-1.5 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg font-semibold transition-colors"
                        >
                            Staff
                        </Link>
                        <button
                            @click="toggleBranch(branch.slug)"
                            :class="branch.is_active ? 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
                            class="px-2 py-1.5 text-xs rounded-lg transition-colors font-bold cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-slate-800"
                        >
                            {{ branch.is_active ? 'Disable' : 'Enable' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Branch Items Modal -->
        <div v-if="showExportModal && exportingBranch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-5 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Export Branch Catalog</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Download current item inventory for <strong>{{ exportingBranch.name }}</strong></p>
                        </div>
                    </div>
                    <button @click="showExportModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-4">
                    <!-- Format selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">1. Choose File Format:</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-center gap-3',
                                    exportForm.format === 'xlsx'
                                        ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/50'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.format" value="xlsx" class="sr-only" />
                                <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">
                                    XLS
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Excel (.xlsx)</div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Best for Excel / Spreadsheets</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-center gap-3',
                                    exportForm.format === 'csv'
                                        ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 ring-2 ring-emerald-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/50'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.format" value="csv" class="sr-only" />
                                <div class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-xs">
                                    CSV
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">CSV (.csv)</div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Universal comma-separated</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Quantity option selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">2. Choose Stock Quantity Level:</label>
                        <div class="space-y-2">
                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'total'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="total" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Total Stock (Front + Back Store)</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Exports combined quantity across floor & warehouse locations</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'front_store'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="front_store" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Front Store Quantity Only</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Exports only floor stock currently available for retail sale</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'back_store'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="back_store" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Back Store / Warehouse Quantity Only</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Exports reserve stock in warehouse storage</div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'p-3 rounded-2xl border cursor-pointer transition flex items-start gap-3',
                                    exportForm.quantity_option === 'none'
                                        ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20'
                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 bg-slate-50/50 dark:bg-slate-800/30'
                                ]"
                            >
                                <input type="radio" v-model="exportForm.quantity_option" value="none" class="mt-0.5 text-indigo-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100">Catalog Only (No Quantities / 0 Qty)</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Perfect for cloning product catalog into other branches or saving to Import Library</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <button
                        type="button"
                        @click="showExportModal = false"
                        class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="triggerExport"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-emerald-600/20 flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download {{ exportForm.format.toUpperCase() }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Branch Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 space-y-4 shadow-2xl my-auto">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Create New Branch & Initial Admin</h2>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div class="space-y-3">
                        <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">1. Branch Details</h3>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Name *</label>
                            <input
                                v-model="form.name"
                                @input="generateSlug"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                                placeholder="e.g. Asaba Main Branch"
                            />
                            <div v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Slug (URL identifier) *</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-indigo-600 dark:text-indigo-300 outline-none focus:border-indigo-500 transition"
                                placeholder="asaba-main"
                            />
                            <span class="text-[11px] text-slate-500">URL: skynetpos.com/{{ form.slug || 'slug' }}</span>
                            <div v-if="form.errors.slug" class="text-rose-500 text-xs mt-1">{{ form.errors.slug }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                            <input v-model="form.address" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                        </div>

                        <div class="pt-1 space-y-2">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input v-model="form.is_offline_enabled" type="checkbox" class="w-4 h-4 accent-indigo-600 rounded cursor-pointer" />
                                <div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Enable Offline Sales Mode (PWA)</span>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Allows cashiers at this branch to sell and print receipts when offline</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input v-model="form.is_turnstile_enabled" type="checkbox" class="w-4 h-4 accent-indigo-600 rounded cursor-pointer" />
                                <div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Require Cloudflare Turnstile on Login</span>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Uncheck to bypass CAPTCHA on slow or unstable network connections</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">2. Initial Branch Admin User</h3>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Admin Full Name *</label>
                            <input v-model="form.admin_name" type="text" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                                <input v-model="form.admin_username" type="text" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition font-mono" />
                                <div v-if="form.errors.admin_username" class="text-rose-500 text-xs mt-1">{{ form.errors.admin_username }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email *</label>
                                <input v-model="form.admin_email" type="email" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                                <div v-if="form.errors.admin_email" class="text-rose-500 text-xs mt-1">{{ form.errors.admin_email }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Initial Password *</label>
                            <div class="relative flex items-center">
                                <input v-model="form.admin_password" :type="showAdminPass ? 'text' : 'password'" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 pr-9 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                                <button type="button" @click="showAdminPass = !showAdminPass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" :title="showAdminPass ? 'Hide password' : 'Show password'">
                                    <svg v-if="!showAdminPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20">
                            Create Branch & Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
