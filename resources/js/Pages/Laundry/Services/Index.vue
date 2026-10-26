<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/LaundryLayout.vue'
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

// ── Modals & Form State ─────────────────────────────────────────────────────
const showCreateModal = ref(false)
const showEditModal   = ref(false)
const editingService  = ref(null)

const serviceForm = ref({
    name: '',
    category: '',
    pricing_type: 'per_piece',
    price: 0,
    is_active: true,
    sort_order: 0,
})

const openCreate = () => {
    serviceForm.value = {
        name: '',
        category: '',
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
        category: service.category || '',
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
</script>

<template>
    <LaundryLayout title="Services & Price List">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header & Action -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">
                        Laundry Service Catalogue
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Manage your branch's service items, wash/dry pricing, and piece/kg rates.
                    </p>
                </div>

                <div v-if="can_manage">
                    <button
                        @click="openCreate"
                        class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm flex items-center gap-1.5 shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add New Service</span>
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <input
                        v-model="search"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search service name..."
                        class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 w-full sm:w-64"
                    />

                    <select
                        v-model="selectedCategory"
                        @change="applyFilter"
                        class="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
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
                                <th class="px-5 py-3 text-center">Pricing Type</th>
                                <th class="px-5 py-3 text-right">Price</th>
                                <th class="px-5 py-3 text-center">Status</th>
                                <th v-if="can_manage" class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="srv in services.data" :key="srv.id" class="hover:bg-slate-50/50">
                                <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white">
                                    {{ srv.name }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-500">
                                    {{ srv.category || 'General' }}
                                </td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ srv.pricing_type === 'per_kg' ? 'Per Kilogram (KG)' : 'Per Piece' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-black text-indigo-600 dark:text-indigo-400 text-sm whitespace-nowrap">
                                    {{ formatMoney(srv.price) }}
                                </td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <span :class="[
                                        'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border',
                                        srv.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-slate-100 text-slate-500 border-slate-200'
                                    ]">
                                        {{ srv.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td v-if="can_manage" class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="toggleActive(srv)"
                                            class="p-1 rounded text-slate-400 hover:text-slate-600"
                                            :title="srv.is_active ? 'Deactivate' : 'Activate'"
                                        >
                                            <span class="text-[11px] font-semibold">{{ srv.is_active ? 'Pause' : 'Enable' }}</span>
                                        </button>
                                        <button
                                            @click="openEdit(srv)"
                                            class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition"
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

        <!-- CREATE SERVICE MODAL -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Create Laundry Service</h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Service Name *</label>
                        <input v-model="serviceForm.name" type="text" placeholder="e.g. Shirt — Wash & Iron" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Category (Optional)</label>
                        <input v-model="serviceForm.category" type="text" placeholder="e.g. Tops, Bedding, Dry Clean" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
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

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showCreateModal = false" class="px-4 py-2 rounded-xl text-slate-600 font-semibold text-xs">Cancel</button>
                    <button @click="submitCreate" :disabled="!serviceForm.name.trim()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 disabled:opacity-50">Create Service</button>
                </div>
            </div>
        </div>

        <!-- EDIT SERVICE MODAL -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-900 dark:text-white">Edit Laundry Service</h3>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Service Name *</label>
                        <input v-model="serviceForm.name" type="text" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold" />
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">Category</label>
                        <input v-model="serviceForm.category" type="text" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50" />
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

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showEditModal = false" class="px-4 py-2 rounded-xl text-slate-600 font-semibold text-xs">Cancel</button>
                    <button @click="submitUpdate" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700">Save Changes</button>
                </div>
            </div>
        </div>

    </LaundryLayout>
</template>
