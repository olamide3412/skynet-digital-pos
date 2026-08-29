<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    files: Object,
    item_types: Array,
    branches: Array,
    stats: Object,
    filters: Object,
})

// ── Search & Filter State ──────────────────────────────────────────────────
const search = ref(props.filters?.search ?? '')
const selectedType = ref(props.filters?.type_id ?? null)
const activeStatus = ref(props.filters?.status ?? 'active')

const applyFilters = () => {
    router.get(
        route('superadmin.import-library.index'),
        {
            search: search.value || undefined,
            type_id: selectedType.value || undefined,
            status: activeStatus.value,
        },
        { preserveState: true, replace: true }
    )
}

const filterByType = (typeId) => {
    selectedType.value = selectedType.value === typeId ? null : typeId
    applyFilters()
}

const filterByStatus = (status) => {
    activeStatus.value = status
    applyFilters()
}

// ── Template Downloads Dropdown ────────────────────────────────────────────
const showTemplateDropdown = ref(false)

// ── Upload Modal ───────────────────────────────────────────────────────────
const showUploadModal = ref(false)
const isAddingCustomType = ref(false)
const uploadForm = useForm({
    name: '',
    item_type_id: '',
    custom_type_name: '',
    file: null,
    description: '',
})

const fileInputRef = ref(null)
const selectedFileName = ref('')

const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    uploadForm.file = file
    selectedFileName.value = file.name
    if (!uploadForm.name) {
        // Auto-fill name from file basename
        uploadForm.name = file.name.replace(/\.[^/.]+$/, '').replace(/[_\-+]+/g, ' ')
    }
}

const submitUpload = () => {
    uploadForm.post(route('superadmin.import-library.store'), {
        onSuccess: () => {
            showUploadModal.value = false
            uploadForm.reset()
            selectedFileName.value = ''
            isAddingCustomType.value = false
        },
    })
}

// ── Replace Version Modal ──────────────────────────────────────────────────
const showReplaceModal = ref(false)
const selectedFileToReplace = ref(null)
const replaceForm = useForm({
    name: '',
    item_type_id: '',
    file: null,
    description: '',
})
const replaceFileRef = ref(null)
const replaceFileName = ref('')

const openReplaceModal = (file) => {
    selectedFileToReplace.value = file
    replaceForm.name = file.name
    replaceForm.item_type_id = file.item_type_id || ''
    replaceForm.description = file.description || ''
    replaceForm.file = null
    replaceFileName.value = ''
    showReplaceModal.value = true
}

const handleReplaceFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    replaceForm.file = file
    replaceFileName.value = file.name
}

const submitReplace = () => {
    if (!selectedFileToReplace.value) return
    replaceForm.post(route('superadmin.import-library.replace', { libraryFile: selectedFileToReplace.value.id }), {
        onSuccess: () => {
            showReplaceModal.value = false
            replaceForm.reset()
            selectedFileToReplace.value = null
        },
    })
}

// ── Archive / Status Toggle ────────────────────────────────────────────────
const toggleStatus = (file) => {
    const action = file.status === 'active' ? 'archive' : 'restore'
    if (!confirm(`Are you sure you want to ${action} "${file.name}"?`)) return
    router.post(route('superadmin.import-library.toggle-status', { libraryFile: file.id }), {}, { preserveScroll: true })
}

// ── Permanent Delete ───────────────────────────────────────────────────────
const destroyFile = (file) => {
    if (!confirm(`Permanently delete "${file.name}" from the Import Library?\n\nNote: Items previously imported into branches from this file will NOT be deleted.`)) return
    router.delete(route('superadmin.import-library.destroy', { libraryFile: file.id }), { preserveScroll: true })
}

// ── Manage Category Types Modal ────────────────────────────────────────────
const showTypesModal = ref(false)
const newTypeForm = useForm({
    name: '',
    description: '',
})
const editingType = ref(null)
const editTypeForm = useForm({
    name: '',
    description: '',
})

const submitNewType = () => {
    newTypeForm.post(route('superadmin.import-item-types.store'), {
        onSuccess: () => newTypeForm.reset(),
    })
}

const startEditType = (type) => {
    editingType.value = type
    editTypeForm.name = type.name
    editTypeForm.description = type.description || ''
}

const submitEditType = () => {
    if (!editingType.value) return
    editTypeForm.put(route('superadmin.import-item-types.update', { itemType: editingType.value.id }), {
        onSuccess: () => {
            editingType.value = null
            editTypeForm.reset()
        },
    })
}

const deleteType = (type) => {
    if (!confirm(`Delete category tag "${type.name}"?`)) return
    router.delete(route('superadmin.import-item-types.destroy', { itemType: type.id }), { preserveScroll: true })
}

// ── Branch Import Modal Flow ───────────────────────────────────────────────
const showImportModal = ref(false)
const selectedFileForImport = ref(null)
const importBranchForm = useForm({
    branch_id: '',
    source_type: 'library',
    library_file_id: '',
    on_duplicate_barcode: 'skip', // 'skip' or 'generate_new'
    default_stock_location: 'front_store',
})

const isPreviewLoading = ref(false)
const previewData = ref(null)
const importResult = ref(null)

const openImportModal = (file) => {
    selectedFileForImport.value = file
    importBranchForm.branch_id = props.branches[0]?.id || ''
    importBranchForm.source_type = 'library'
    importBranchForm.library_file_id = file.id
    importBranchForm.on_duplicate_barcode = 'skip'
    importBranchForm.default_stock_location = 'front_store'
    previewData.value = null
    importResult.value = null
    showImportModal.value = true

    if (importBranchForm.branch_id) {
        fetchPreview()
    }
}

const fetchPreview = async () => {
    if (!importBranchForm.branch_id || !importBranchForm.library_file_id) return
    isPreviewLoading.value = true
    try {
        const res = await window.axios.post(route('superadmin.import-library.preview'), {
            branch_id: importBranchForm.branch_id,
            library_file_id: importBranchForm.library_file_id,
        })
        previewData.value = res.data
    } catch (e) {
        console.error('Preview error:', e)
    } finally {
        isPreviewLoading.value = false
    }
}

const submitImportToBranch = () => {
    importBranchForm.post(route('superadmin.import-library.import-to-branch'), {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false
            selectedFileForImport.value = null
            previewData.value = null
        },
    })
}
</script>

<template>
    <Head title="Item Import Library - Super Admin" />

    <div class="max-w-7xl mx-auto space-y-6 pb-12">
        <!-- ── Hero Header ──────────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-[11px] font-black uppercase tracking-wider bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 rounded-lg border border-indigo-200 dark:border-indigo-500/20">
                        Catalog Manager
                    </span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    Item Import Library
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-2xl">
                    Store, categorize, and reuse ready-made CSV/Excel catalogs (Pharmacy, Supermarket, Gadgets, etc.) to quickly stock new and existing branches.
                </p>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex items-center flex-wrap gap-2.5">
                <!-- Templates Dropdown -->
                <div class="relative">
                    <button
                        @click="showTemplateDropdown = !showTemplateDropdown"
                        class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download Templates</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div
                        v-if="showTemplateDropdown"
                        @click="showTemplateDropdown = false"
                        class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl z-30 p-2 space-y-1 text-xs"
                    >
                        <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Blank Templates</div>
                        <a :href="route('superadmin.import-library.template', { format: 'xlsx' })" class="flex items-center justify-between px-2.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-xl text-slate-700 dark:text-slate-200 font-semibold transition">
                            <span class="flex items-center gap-2">📊 Blank Excel Template</span>
                            <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-bold">.xlsx</span>
                        </a>
                        <a :href="route('superadmin.import-library.template', { format: 'csv' })" class="flex items-center justify-between px-2.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-xl text-slate-700 dark:text-slate-200 font-semibold transition">
                            <span class="flex items-center gap-2">📄 Blank CSV Template</span>
                            <span class="text-[10px] font-mono text-amber-600 dark:text-amber-400 font-bold">.csv</span>
                        </a>
                        <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
                        <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Sample Demo Templates</div>
                        <a :href="route('superadmin.import-library.template', { format: 'xlsx', sample: 1 })" class="flex items-center justify-between px-2.5 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl text-indigo-700 dark:text-indigo-300 font-semibold transition">
                            <span class="flex items-center gap-2">✨ Sample Excel (Demo Data)</span>
                            <span class="text-[10px] font-mono font-bold">.xlsx</span>
                        </a>
                        <a :href="route('superadmin.import-library.template', { format: 'csv', sample: 1 })" class="flex items-center justify-between px-2.5 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl text-indigo-700 dark:text-indigo-300 font-semibold transition">
                            <span class="flex items-center gap-2">✨ Sample CSV (Demo Data)</span>
                            <span class="text-[10px] font-mono font-bold">.csv</span>
                        </a>
                    </div>
                </div>

                <!-- Manage Tags Button -->
                <button
                    @click="showTypesModal = true"
                    class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Manage Categories</span>
                </button>

                <!-- Upload New File Button -->
                <button
                    @click="showUploadModal = true"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Upload File to Library</span>
                </button>
            </div>
        </div>

        <!-- ── Top KPI Stat Cards ────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl flex-shrink-0">
                    📁
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Catalogs</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-0.5">{{ stats.active_files }} files</div>
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl flex-shrink-0">
                    📦
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Library Items</div>
                    <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ stats.total_items_in_library.toLocaleString() }}</div>
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl flex-shrink-0">
                    🏷️
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Store Categories</div>
                    <div class="text-xl font-black text-violet-600 dark:text-violet-400 mt-0.5">{{ stats.total_store_types }} types</div>
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl flex-shrink-0">
                    🚀
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Imports Executed</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-0.5">{{ stats.total_imports_done }} times</div>
                </div>
            </div>
        </div>

        <!-- ── Search & Filter Tabs Bar ──────────────────────────────────────── -->
        <div class="space-y-3">
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                <!-- Search Box -->
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="search"
                        @keydown.enter="applyFilters"
                        type="text"
                        placeholder="Search files by name, type, or notes…"
                        class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl pl-9 pr-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-indigo-500 shadow-xs"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <!-- Status Selector Tabs (Active vs Archived) -->
                <div class="flex items-center p-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs text-xs font-bold">
                    <button
                        @click="filterByStatus('active')"
                        :class="activeStatus === 'active'
                            ? 'bg-indigo-600 text-white shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-4 py-1.5 rounded-xl transition cursor-pointer"
                    >
                        Active Catalogs ({{ stats.active_files }})
                    </button>
                    <button
                        @click="filterByStatus('archived')"
                        :class="activeStatus === 'archived'
                            ? 'bg-amber-600 text-white shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-4 py-1.5 rounded-xl transition cursor-pointer"
                    >
                        Archived ({{ stats.archived_files }})
                    </button>
                    <button
                        @click="filterByStatus('all')"
                        :class="activeStatus === 'all'
                            ? 'bg-slate-800 text-white dark:bg-slate-700'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-4 py-1.5 rounded-xl transition cursor-pointer"
                    >
                        All Files
                    </button>
                </div>
            </div>

            <!-- Horizontal Store Category Pill Tags -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
                <button
                    @click="filterByType(null)"
                    :class="selectedType === null
                        ? 'bg-indigo-600 text-white border-indigo-600 font-bold'
                        : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium'"
                    class="px-3.5 py-1.5 rounded-xl border transition cursor-pointer whitespace-nowrap shadow-2xs"
                >
                    All Categories
                </button>
                <button
                    v-for="type in item_types"
                    :key="type.id"
                    @click="filterByType(type.id)"
                    :class="selectedType === type.id
                        ? 'bg-indigo-600 text-white border-indigo-600 font-bold'
                        : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium'"
                    class="px-3.5 py-1.5 rounded-xl border transition cursor-pointer whitespace-nowrap shadow-2xs flex items-center gap-1.5"
                >
                    <span>{{ type.name }}</span>
                    <span
                        v-if="type.files_count > 0"
                        :class="selectedType === type.id ? 'bg-indigo-700 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                        class="px-1.5 py-0.2 rounded-md text-[10px] font-mono font-bold"
                    >
                        {{ type.files_count }}
                    </span>
                </button>
            </div>
        </div>

        <!-- ── Files Grid ───────────────────────────────────────────────────── -->
        <div v-if="!files.data.length" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-16 text-center space-y-4 shadow-xs">
            <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center text-3xl mx-auto">
                📂
            </div>
            <div class="space-y-1">
                <h3 class="font-bold text-slate-900 dark:text-white text-base">No Import Files Found</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    {{ search || selectedType ? 'No files match your filter criteria.' : 'Your import file library is currently empty. Upload a CSV or Excel template to get started.' }}
                </p>
            </div>
            <div class="flex items-center justify-center gap-3 pt-2">
                <button
                    @click="showUploadModal = true"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer"
                >
                    Upload First Catalog
                </button>
            </div>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="file in files.data"
                :key="file.id"
                class="bg-white dark:bg-slate-900 border rounded-3xl p-5 space-y-4 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex flex-col justify-between"
                :class="file.status === 'archived' ? 'border-amber-200 dark:border-amber-900/40 opacity-80' : 'border-slate-200 dark:border-slate-800'"
            >
                <div class="space-y-3">
                    <!-- Card Top: File Format Icon, Title, Type Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                :class="file.file_format === 'xlsx' || file.file_format === 'xls'
                                    ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20'
                                    : 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-500/20'"
                                class="w-11 h-11 rounded-2xl border flex flex-col items-center justify-center font-black flex-shrink-0 uppercase text-[10px]"
                            >
                                <span class="text-sm">📄</span>
                                <span class="leading-none">{{ file.file_format }}</span>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-900 dark:text-white text-base leading-snug truncate" :title="file.name">
                                    {{ file.name }}
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 rounded-md text-[10px] font-bold border border-indigo-200 dark:border-indigo-500/20">
                                        🏷️ {{ file.item_type_tag || file.item_type?.name || 'General' }}
                                    </span>
                                    <span
                                        v-if="file.status === 'archived'"
                                        class="px-2 py-0.5 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 rounded-md text-[10px] font-bold border border-amber-200 dark:border-amber-500/20"
                                    >
                                        Archived
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="file.description" class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                        {{ file.description }}
                    </p>

                    <!-- Meta details: Row count, File size, Imported count -->
                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px]">
                        <div class="p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-200/60 dark:border-slate-800/60 text-center">
                            <div class="text-slate-400 font-bold uppercase text-[9px]">Items</div>
                            <div class="font-black text-slate-900 dark:text-white mt-0.5">{{ file.row_count.toLocaleString() }}</div>
                        </div>
                        <div class="p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-200/60 dark:border-slate-800/60 text-center">
                            <div class="text-slate-400 font-bold uppercase text-[9px]">Size</div>
                            <div class="font-bold text-slate-700 dark:text-slate-300 mt-0.5">{{ file.file_size_formatted }}</div>
                        </div>
                        <div class="p-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-200/60 dark:border-slate-800/60 text-center">
                            <div class="text-slate-400 font-bold uppercase text-[9px]">Imports</div>
                            <div class="font-black text-indigo-600 dark:text-indigo-400 mt-0.5">{{ file.import_count }}x</div>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-400 flex items-center justify-between pt-1">
                        <span>Uploaded: <strong class="text-slate-600 dark:text-slate-300 font-normal">{{ new Date(file.created_at).toLocaleDateString() }}</strong></span>
                        <span v-if="file.user">By: <strong class="text-slate-600 dark:text-slate-300 font-normal">{{ file.user.name }}</strong></span>
                    </div>
                </div>

                <!-- Card Bottom Actions -->
                <div class="space-y-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <!-- Primary CTA: Import into Branch -->
                    <button
                        @click="openImportModal(file)"
                        class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Import into Branch &rarr;</span>
                    </button>

                    <!-- Secondary Row of Actions -->
                    <div class="flex items-center justify-between gap-1 text-xs">
                        <a
                            :href="route('superadmin.import-library.download', { libraryFile: file.id })"
                            class="px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg font-semibold transition flex items-center gap-1"
                            title="Download original file"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Download</span>
                        </a>

                        <button
                            @click="openReplaceModal(file)"
                            class="px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg font-semibold transition flex items-center gap-1 cursor-pointer"
                            title="Upload new file version"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Update</span>
                        </button>

                        <button
                            @click="toggleStatus(file)"
                            :class="file.status === 'active' ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700'"
                            class="px-2 py-1.5 font-semibold transition cursor-pointer"
                        >
                            {{ file.status === 'active' ? 'Archive' : 'Restore' }}
                        </button>

                        <button
                            @click="destroyFile(file)"
                            class="px-2 py-1.5 text-slate-400 hover:text-rose-600 transition cursor-pointer"
                            title="Permanently remove file"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Pagination Footer ─────────────────────────────────────────────── -->
        <div v-if="files.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl text-xs text-slate-500 dark:text-slate-400 gap-3 shadow-xs">
            <div>
                Showing <span class="font-semibold text-slate-900 dark:text-slate-200">{{ files.from }}</span> to <span class="font-semibold text-slate-900 dark:text-slate-200">{{ files.to }}</span> of <span class="font-semibold text-slate-900 dark:text-slate-200">{{ files.total }}</span> library files
            </div>
            <div class="flex items-center space-x-1">
                <template v-for="(link, key) in files.links" :key="key">
                    <div v-if="link.url === null"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 cursor-not-allowed select-none bg-slate-100 dark:bg-slate-900"
                        v-html="link.label"
                    />
                    <Link v-else
                        :href="link.url"
                        class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition"
                        :class="link.active
                            ? 'bg-indigo-600 border-indigo-600 text-white font-bold'
                            : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: UPLOAD NEW FILE                                                -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-if="showUploadModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl my-8">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Upload Catalog to Library</h2>
                        <p class="text-xs text-slate-500">Save a reusable CSV or Excel catalog template for branches.</p>
                    </div>
                    <button @click="showUploadModal = false; uploadForm.reset()" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Catalog Display Name *</label>
                        <input
                            v-model="uploadForm.name"
                            type="text"
                            required
                            placeholder="e.g. Master Pharmacy Formulary 2026"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Store / Category Tag *</label>
                            <button
                                type="button"
                                @click="isAddingCustomType = !isAddingCustomType"
                                class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                            >
                                {{ isAddingCustomType ? '← Pick from list' : '+ Add new tag' }}
                            </button>
                        </div>

                        <select
                            v-if="!isAddingCustomType"
                            v-model="uploadForm.item_type_id"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="" disabled>Select Store / Item Category</option>
                            <option v-for="t in item_types" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>

                        <input
                            v-else
                            v-model="uploadForm.custom_type_name"
                            type="text"
                            required
                            placeholder="e.g. Cosmetics & Skincare"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Spreadsheet File (.xlsx, .xls, .csv) *</label>
                        <div
                            @click="$refs.fileInputRef.click()"
                            class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 rounded-2xl p-5 text-center cursor-pointer transition bg-slate-50/60 dark:bg-slate-950/40 space-y-2"
                        >
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                @change="handleFileChange"
                                class="hidden"
                            />
                            <div class="text-2xl">📊</div>
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                {{ selectedFileName || 'Click to select CSV or Excel file' }}
                            </div>
                            <p class="text-[11px] text-slate-400">Supported formats: .xlsx, .xls, .csv up to 20MB</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Description / Internal Notes (Optional)</label>
                        <textarea
                            v-model="uploadForm.description"
                            rows="2"
                            placeholder="e.g. Standard 400+ OTC and prescription items for pharmacy onboarding."
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showUploadModal = false; uploadForm.reset()"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="uploadForm.processing || !uploadForm.file"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 disabled:opacity-50 cursor-pointer"
                        >
                            {{ uploadForm.processing ? 'Uploading & Parsing…' : 'Save to Library' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: REPLACE FILE VERSION                                           -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-if="showReplaceModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Update / Replace File Version</h2>
                        <p class="text-xs text-slate-500">Upload a refreshed spreadsheet under "{{ selectedFileToReplace?.name }}".</p>
                    </div>
                    <button @click="showReplaceModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-2xl text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                    ℹ️ <strong>Safety Guarantee:</strong> Replacing this file will update the template for future imports only. Items already imported into branches in the past will remain completely untouched.
                </div>

                <form @submit.prevent="submitReplace" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Catalog Name</label>
                        <input
                            v-model="replaceForm.name"
                            type="text"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Select New Spreadsheet File (.xlsx, .csv) *</label>
                        <div
                            @click="$refs.replaceFileRef.click()"
                            class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 rounded-2xl p-4 text-center cursor-pointer transition bg-slate-50/60 dark:bg-slate-950/40 space-y-1"
                        >
                            <input
                                ref="replaceFileRef"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                @change="handleReplaceFileChange"
                                class="hidden"
                            />
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                {{ replaceFileName || 'Click to select new file version' }}
                            </div>
                            <p class="text-[11px] text-slate-400">Will replace current file and recalculate item count.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showReplaceModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="replaceForm.processing || !replaceForm.file"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 disabled:opacity-50 cursor-pointer"
                        >
                            {{ replaceForm.processing ? 'Updating…' : 'Commit New Version' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: MANAGE CATEGORY TYPES                                          -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-if="showTypesModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl my-8">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Manage Category / Store Tags</h2>
                        <p class="text-xs text-slate-500">Configure business category tags used to organize catalog files.</p>
                    </div>
                    <button @click="showTypesModal = false; editingType = null" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <!-- Add / Edit Form -->
                <div class="p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        {{ editingType ? `Edit Tag: ${editingType.name}` : '+ Create New Category Tag' }}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input
                            v-if="!editingType"
                            v-model="newTypeForm.name"
                            type="text"
                            placeholder="Tag Name (e.g. Pharmacy)"
                            class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100"
                        />
                        <input
                            v-else
                            v-model="editTypeForm.name"
                            type="text"
                            placeholder="Tag Name"
                            class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100"
                        />

                        <input
                            v-if="!editingType"
                            v-model="newTypeForm.description"
                            type="text"
                            placeholder="Short description (optional)"
                            class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100"
                        />
                        <input
                            v-else
                            v-model="editTypeForm.description"
                            type="text"
                            placeholder="Short description"
                            class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100"
                        />
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button
                            v-if="editingType"
                            type="button"
                            @click="editingType = null"
                            class="px-3 py-1.5 text-xs text-slate-500 font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            v-if="!editingType"
                            type="button"
                            @click="submitNewType"
                            :disabled="newTypeForm.processing || !newTypeForm.name"
                            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl disabled:opacity-50 cursor-pointer"
                        >
                            Save Tag
                        </button>
                        <button
                            v-else
                            type="button"
                            @click="submitEditType"
                            :disabled="editTypeForm.processing || !editTypeForm.name"
                            class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl disabled:opacity-50 cursor-pointer"
                        >
                            Update Tag
                        </button>
                    </div>
                </div>

                <!-- Existing Tags List -->
                <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-64 overflow-y-auto pr-1">
                    <div
                        v-for="type in item_types"
                        :key="type.id"
                        class="py-2.5 flex items-center justify-between gap-3 text-xs"
                    >
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span>{{ type.name }}</span>
                                <span class="text-[10px] font-mono text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                    {{ type.files_count }} files
                                </span>
                            </div>
                            <div v-if="type.description" class="text-[11px] text-slate-400 truncate max-w-xs">
                                {{ type.description }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button
                                @click="startEditType(type)"
                                class="px-2 py-1 text-slate-600 dark:text-slate-400 hover:text-indigo-600 font-semibold cursor-pointer"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteType(type)"
                                class="px-2 py-1 text-slate-400 hover:text-rose-600 font-semibold cursor-pointer"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: IMPORT INTO BRANCH                                             -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-if="showImportModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Import Catalog into Branch</h2>
                        <p class="text-xs text-slate-500">Stock a branch with items from "{{ selectedFileForImport?.name }}".</p>
                    </div>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="space-y-4 overflow-y-auto pr-1 flex-1 text-xs">
                    <!-- Target Branch Selector -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Branch *</label>
                        <select
                            v-model="importBranchForm.branch_id"
                            @change="fetchPreview"
                            required
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500 font-semibold"
                        >
                            <option v-for="b in branches" :key="b.id" :value="b.id">
                                🏢 {{ b.name }} ({{ b.slug }})
                            </option>
                        </select>
                    </div>

                    <!-- Import Strategy Options -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">On Duplicate Barcode:</label>
                            <select
                                v-model="importBranchForm.on_duplicate_barcode"
                                class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                            >
                                <option value="skip">Skip row (Do not overwrite)</option>
                                <option value="leave_empty">Import item with empty barcode (Leave null)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Default Initial Stock Location:</label>
                            <select
                                v-model="importBranchForm.default_stock_location"
                                class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-slate-100"
                            >
                                <option value="front_store">Front Store (Ready to sell)</option>
                                <option value="back_store">Back Store / Warehouse</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Validation Preview Box -->
                    <div v-if="isPreviewLoading" class="p-8 text-center text-slate-400 space-y-2">
                        <div class="inline-block animate-spin text-2xl">⏳</div>
                        <div>Validating catalog against branch records…</div>
                    </div>

                    <div v-else-if="previewData" class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl border border-emerald-200 dark:border-emerald-500/20 text-center">
                                <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 uppercase">Valid Rows Ready</div>
                                <div class="text-base font-black text-emerald-800 dark:text-emerald-300">{{ previewData.valid_rows }}</div>
                            </div>
                            <div class="p-3 bg-amber-50 dark:bg-amber-500/10 rounded-xl border border-amber-200 dark:border-amber-500/20 text-center">
                                <div class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase">Barcode Conflicts</div>
                                <div class="text-base font-black text-amber-800 dark:text-amber-300">{{ previewData.barcode_conflicts_count }}</div>
                            </div>
                            <div class="p-3 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 text-center">
                                <div class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase">Invalid / Skipped</div>
                                <div class="text-base font-black text-slate-800 dark:text-slate-200">{{ previewData.invalid_rows }}</div>
                            </div>
                        </div>

                        <!-- Sample Rows Table -->
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sample Items Preview (First 5 Rows):</div>
                            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                                <table class="w-full text-left text-[11px]">
                                    <thead class="bg-slate-50 dark:bg-slate-800/80 font-semibold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                        <tr>
                                            <th class="px-3 py-2">Item Name</th>
                                            <th class="px-3 py-2">Category</th>
                                            <th class="px-3 py-2">Unit Price</th>
                                            <th class="px-3 py-2">Packaging Units</th>
                                            <th class="px-3 py-2">IMEI</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        <tr v-for="(row, idx) in previewData.sample_rows.slice(0, 5)" :key="idx" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                            <td class="px-3 py-2 font-bold text-slate-900 dark:text-white">{{ row.data.item_name }}</td>
                                            <td class="px-3 py-2 text-slate-500">{{ row.data.category || '—' }}</td>
                                            <td class="px-3 py-2 font-mono font-bold text-emerald-600 dark:text-emerald-400">₦{{ Number(row.data.base_unit_price).toLocaleString() }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-500 text-[10px]">
                                                <span v-if="row.data.pack_price">
                                                    {{ row.data.pack_unit_name || 'Pack' }}: ₦{{ Number(row.data.pack_price).toLocaleString() }} ({{ row.data.pack_quantity }})
                                                </span>
                                                <span v-else-if="row.data.carton_price">
                                                    {{ row.data.carton_unit_name || 'Carton' }}: ₦{{ Number(row.data.carton_price).toLocaleString() }}
                                                </span>
                                                <span v-else class="text-slate-400">—</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span v-if="row.data.track_by_imei" class="px-1.5 py-0.5 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 rounded font-bold text-[9px]">IMEI</span>
                                                <span v-else class="text-slate-400">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800 flex-shrink-0">
                    <div class="text-[11px] text-slate-500">
                        Total {{ previewData?.valid_rows || selectedFileForImport?.row_count }} items will be added to this branch.
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="showImportModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitImportToBranch"
                            :disabled="importBranchForm.processing || isPreviewLoading"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ importBranchForm.processing ? 'Importing Items…' : 'Execute Branch Import' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
