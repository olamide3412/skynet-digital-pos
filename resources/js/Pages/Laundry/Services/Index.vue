<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import PaginationLinks from '@/Components/PaginationLinks.vue'

const props = defineProps({
    services: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    can_manage: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const getDiscountedPrice = (srv) => {
    if (!srv.active_discount) return Number(srv.price)
    const val = Number(srv.active_discount.discount_value) || 0
    if (srv.active_discount.discount_type === 'percentage') {
        return Math.max(0, Math.round((Number(srv.price) * (1 - val / 100)) * 100) / 100)
    }
    return Math.max(0, Math.round((Number(srv.price) - val) * 100) / 100)
}

// ── Search & Filter ─────────────────────────────────────────────────────────
const search = ref(props.filters.search || '')
const selectedCategory = ref(props.filters.category || '')
const selectedStatus = ref(props.filters.status || '')

const applyFilter = () => {
    router.get(
        safeRoute('laundry.services.index'),
        {
            search: search.value || undefined,
            category: selectedCategory.value || undefined,
            status: selectedStatus.value || undefined,
        },
        { preserveState: true, replace: true }
    )
}

let timer = null
const onSearchInput = () => {
    clearTimeout(timer)
    timer = setTimeout(applyFilter, 350)
}

// ── Service Modals & Form State ─────────────────────────────────────────────
const showCreateModal = ref(false)
const showEditModal   = ref(false)
const editingService  = ref(null)

const serviceForm = ref({
    name: '',
    category_id: null,
    pricing_type: 'per_piece',
    price: 0,
    is_active: true,
    sort_order: 0,
})

const openCreate = () => {
    serviceForm.value = {
        name: '',
        category_id: props.categories.length > 0 ? props.categories[0].id : null,
        pricing_type: 'per_piece',
        price: 0,
        is_active: true,
        sort_order: 0,
    }
    showCreateModal.value = true
}

const openEdit = (service) => {
    editingService.value = service
    serviceForm.value = {
        name: service.name,
        category_id: service.category_id || null,
        pricing_type: service.pricing_type,
        price: service.price,
        is_active: Boolean(service.is_active),
        sort_order: service.sort_order || 0,
    }
    showEditModal.value = true
}

const submitCreate = () => {
    router.post(safeRoute('laundry.services.store'), serviceForm.value, {
        onSuccess: () => {
            showCreateModal.value = false
        },
    })
}

const submitUpdate = () => {
    router.put(safeRoute('laundry.services.update', { service: editingService.value.id }), serviceForm.value, {
        onSuccess: () => {
            showEditModal.value = false
        },
    })
}

const toggleActive = (service) => {
    router.patch(safeRoute('laundry.services.toggle', { service: service.id }), {}, {
        preserveScroll: true,
    })
}

const deleteService = (service) => {
    if (confirm(`Are you sure you want to delete or deactivate "${service.name}"?`)) {
        router.delete(safeRoute('laundry.services.destroy', { service: service.id }), {
            preserveScroll: true,
        })
    }
}

// ── Category Management State & Actions ─────────────────────────────────────
const showCategoryModal = ref(false)
const editingCategory = ref(null)
const categoryError = ref('')
const categorySubmitting = ref(false)

const categoryForm = ref({
    name: '',
    description: '',
    sort_order: 0,
})

const openManageCategories = () => {
    editingCategory.value = null
    categoryError.value = ''
    categoryForm.value = { name: '', description: '', sort_order: 0 }
    showCategoryModal.value = true
}

const startEditCategory = (cat) => {
    editingCategory.value = cat
    categoryError.value = ''
    categoryForm.value = {
        name: cat.name,
        description: cat.description || '',
        sort_order: cat.sort_order || 0,
    }
}

const cancelEditCategory = () => {
    editingCategory.value = null
    categoryError.value = ''
    categoryForm.value = { name: '', description: '', sort_order: 0 }
}

const submitCategoryForm = () => {
    if (!categoryForm.value.name.trim()) {
        categoryError.value = 'Category name is required.'
        return
    }

    categorySubmitting.value = true
    categoryError.value = ''

    if (editingCategory.value) {
        // Update existing category
        router.put(
            safeRoute('laundry.services.categories.update', { id: editingCategory.value.id }),
            categoryForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEditCategory()
                    categorySubmitting.value = false
                },
                onError: (errors) => {
                    categoryError.value = errors.name || errors.error || 'Failed to update category.'
                    categorySubmitting.value = false
                },
            }
        )
    } else {
        // Create new category
        router.post(
            safeRoute('laundry.services.categories.store'),
            categoryForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    categoryForm.value = { name: '', description: '', sort_order: 0 }
                    categorySubmitting.value = false
                },
                onError: (errors) => {
                    categoryError.value = errors.name || errors.error || 'Failed to create category.'
                    categorySubmitting.value = false
                },
            }
        )
    }
}

const deleteCategory = (cat) => {
    if ((cat.services_count || 0) > 0) {
        alert(`Cannot delete "${cat.name}" because it has ${cat.services_count} active service(s) assigned to it. Please reassign or delete those services first.`)
        return
    }

    if (confirm(`Are you sure you want to delete the category "${cat.name}"?`)) {
        router.delete(
            safeRoute('laundry.services.categories.destroy', { id: cat.id }),
            {
                preserveScroll: true,
                onError: (errors) => {
                    alert(errors.error || 'Failed to delete category.')
                },
            }
        )
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Laundry Service Catalogue</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage service offerings, organized categories, pricing models (piece / kg), and rates</p>
            </div>
            <div v-if="can_manage" class="flex items-center gap-2.5">
                <!-- Manage Discounts & Promos Button -->
                <Link
                    :href="safeRoute('laundry.discounts.index')"
                    class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/80 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                >
                    <span>🏷️ Promos & Discounts</span>
                </Link>

                <!-- Manage Categories Button -->
                <button
                    type="button"
                    @click="openManageCategories"
                    class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-2xs cursor-pointer"
                >
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Categories</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900">
                        {{ categories.length }}
                    </span>
                </button>

                <!-- Add New Service Button -->
                <button
                    type="button"
                    @click="openCreate"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-md shadow-indigo-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add New Service</span>
                </button>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-6">

            <!-- Filter Controls -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <input
                        v-model="search"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search service name or category..."
                        class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 w-full sm:w-64"
                    />

                    <select
                        v-model="selectedCategory"
                        @change="applyFilter"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium"
                    >
                        <option value="">All Categories ({{ categories.length }})</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }} ({{ cat.services_count || 0 }})
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        @change="applyFilter"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>

                <div class="text-slate-400 font-semibold text-xs">
                    Total: {{ services.total || services.data.length }} services
                </div>
            </div>

            <!-- Services Table -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                <div v-if="services.data.length === 0" class="py-16 text-center text-slate-400 text-xs">
                    No services found matching filters.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3">Service Name</th>
                                <th class="px-5 py-3">Category</th>
                                <th class="px-5 py-3 text-center">Pricing Model</th>
                                <th class="px-5 py-3 text-right">Standard Price</th>
                                <th class="px-5 py-3 text-center">Status</th>
                                <th v-if="can_manage" class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="srv in services.data" :key="srv.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                                    {{ srv.name }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="srv.category?.name || srv.category_name" class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900">
                                        {{ srv.category?.name || srv.category_name }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-[11px]">
                                        Uncategorized
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ srv.pricing_type === 'per_kg' ? 'Per Kilogram (KG)' : 'Per Piece' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <template v-if="srv.active_discount">
                                        <div class="flex flex-col items-end">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm font-mono">
                                                    {{ formatMoney(getDiscountedPrice(srv)) }}
                                                </span>
                                                <span class="line-through text-slate-400 text-xs font-mono">
                                                    {{ formatMoney(srv.price) }}
                                                </span>
                                            </div>
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800 mt-0.5">
                                                🏷️ {{ srv.active_discount.discount_type === 'percentage' ? `${srv.active_discount.discount_value}% OFF` : `-${formatMoney(srv.active_discount.discount_value)}` }}
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="font-black text-indigo-600 dark:text-indigo-400 text-sm font-mono">
                                            {{ formatMoney(srv.price) }}
                                        </span>
                                    </template>
                                </td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <span :class="[
                                        'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border',
                                        srv.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-700 dark:text-slate-400'
                                    ]">
                                        {{ srv.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td v-if="can_manage" class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="toggleActive(srv)"
                                            class="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                            :title="srv.is_active ? 'Deactivate' : 'Activate'"
                                        >
                                            <span class="text-[11px] font-semibold">{{ srv.is_active ? 'Pause' : 'Enable' }}</span>
                                        </button>
                                        <button
                                            @click="openEdit(srv)"
                                            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-xs transition"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteService(srv)"
                                            class="p-1 text-slate-400 hover:text-rose-600 rounded transition"
                                            title="Delete"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="services.links && services.links.length > 3" class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <PaginationLinks :links="services.links" />
                </div>
            </div>

            </div>
        </div>

        <!-- CREATE SERVICE MODAL -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm">Create Laundry Service</h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Service Name *</label>
                        <input v-model="serviceForm.name" type="text" placeholder="e.g. Suit 2-Piece Dry Clean" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-semibold">Category</label>
                            <button
                                type="button"
                                @click="openManageCategories"
                                class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-0.5"
                            >
                                <span>+ New Category</span>
                            </button>
                        </div>
                        <select v-model="serviceForm.category_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium">
                            <option :value="null">-- Select Category (or Uncategorized) --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Pricing Model *</label>
                            <select v-model="serviceForm.pricing_type" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold">
                                <option value="per_piece">Per Piece</option>
                                <option value="per_kg">Per Kilogram (KG)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Price *</label>
                            <input v-model.number="serviceForm.price" type="number" min="0" step="0.5" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <button @click="showCreateModal = false" class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 font-semibold text-xs">Cancel</button>
                    <button @click="submitCreate" :disabled="!serviceForm.name.trim()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 disabled:opacity-50">Create Service</button>
                </div>
            </div>
        </div>

        <!-- EDIT SERVICE MODAL -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm">Edit Laundry Service</h3>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Service Name *</label>
                        <input v-model="serviceForm.name" type="text" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-semibold">Category</label>
                            <button
                                type="button"
                                @click="openManageCategories"
                                class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-0.5"
                            >
                                <span>+ New Category</span>
                            </button>
                        </div>
                        <select v-model="serviceForm.category_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium">
                            <option :value="null">-- Select Category (or Uncategorized) --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Pricing Model</label>
                            <select v-model="serviceForm.pricing_type" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold">
                                <option value="per_piece">Per Piece</option>
                                <option value="per_kg">Per Kilogram (KG)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Price</label>
                            <input v-model.number="serviceForm.price" type="number" min="0" step="0.5" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <button @click="showEditModal = false" class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 font-semibold text-xs">Cancel</button>
                    <button @click="submitUpdate" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700">Save Changes</button>
                </div>
            </div>
        </div>

        <!-- DEDICATED CATEGORY MANAGEMENT MODAL -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700 flex-shrink-0">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm">Laundry Service Categories</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Add, rename, or manage service categories without duplicating names in the database</p>
                    </div>
                    <button @click="showCategoryModal = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
                </div>

                <!-- Create / Edit Category Form -->
                <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs text-slate-700 dark:text-slate-300">
                            {{ editingCategory ? `Edit Category: "${editingCategory.name}"` : 'Add New Category' }}
                        </span>
                        <button
                            v-if="editingCategory"
                            type="button"
                            @click="cancelEditCategory"
                            class="text-[11px] text-slate-400 hover:text-slate-600 underline"
                        >
                            Cancel Edit
                        </button>
                    </div>

                    <div v-if="categoryError" class="p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                        {{ categoryError }}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold mb-1 text-[11px] text-slate-600 dark:text-slate-400">Category Name *</label>
                            <input
                                v-model="categoryForm.name"
                                type="text"
                                placeholder="e.g. Dry Cleaning, Wash & Fold, Bedding"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-semibold"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-[11px] text-slate-600 dark:text-slate-400">Sort Order</label>
                            <input
                                v-model.number="categoryForm.sort_order"
                                type="number"
                                min="0"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button
                            type="button"
                            @click="submitCategoryForm"
                            :disabled="categorySubmitting || !categoryForm.name.trim()"
                            class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition disabled:opacity-50"
                        >
                            {{ editingCategory ? 'Update Category' : '+ Add Category' }}
                        </button>
                    </div>
                </div>

                <!-- Existing Categories List -->
                <div class="flex-1 overflow-y-auto space-y-2 pr-1 min-h-[160px]">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-1 pt-1">
                        Branch Categories ({{ categories.length }})
                    </div>

                    <div v-if="categories.length === 0" class="py-8 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                        No categories created yet. Add one above!
                    </div>

                    <div
                        v-for="cat in categories"
                        :key="cat.id"
                        class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xs flex items-center justify-between gap-3 text-xs"
                    >
                        <div class="flex items-center gap-2.5 truncate">
                            <span class="w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span>
                            <span class="font-bold text-slate-900 dark:text-white truncate">{{ cat.name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 shrink-0">
                                {{ cat.services_count || 0 }} services
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <button
                                type="button"
                                @click="startEditCategory(cat)"
                                class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-semibold text-[11px] transition"
                            >
                                Rename
                            </button>
                            <button
                                type="button"
                                @click="deleteCategory(cat)"
                                class="p-1 text-slate-400 hover:text-rose-600 transition"
                                title="Delete Category"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-700 flex-shrink-0">
                    <button
                        type="button"
                        @click="showCategoryModal = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition"
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
