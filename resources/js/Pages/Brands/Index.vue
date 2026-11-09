<script setup>
import { ref, watch } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    brands: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const showModal = ref(false)
const editingBrand = ref(null)
const logoPreview = ref(null)

const form = useForm({
    name: '',
    slug: '',
    description: '',
    logo: null,
    is_active: true,
})

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('pos.brands.index'), { search: val }, {
            preserveState: true,
            replace: true,
        })
    }, 300)
})

const openCreate = () => {
    editingBrand.value = null
    form.reset()
    logoPreview.value = null
    showModal.value = true
}

const openEdit = (brand) => {
    editingBrand.value = brand
    form.name = brand.name
    form.slug = brand.slug || ''
    form.description = brand.description || ''
    form.logo = null
    form.is_active = Boolean(brand.is_active)
    logoPreview.value = brand.logo_url || null
    showModal.value = true
}

const onFileChange = (e) => {
    const file = e.target.files[0]
    if (file) {
        form.logo = file
        logoPreview.value = URL.createObjectURL(file)
    }
}

const submit = () => {
    if (editingBrand.value) {
        // Inertia multipart file upload requires POST with _method = PUT or transform
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('pos.brands.update', editingBrand.value.id), {
            onSuccess: () => {
                showModal.value = false
                form.reset()
                logoPreview.value = null
            }
        })
    } else {
        form.post(route('pos.brands.store'), {
            onSuccess: () => {
                showModal.value = false
                form.reset()
                logoPreview.value = null
            }
        })
    }
}

const deleteBrand = (id) => {
    if (confirm('Delete Brand? Items linked to this brand will not be deleted, but will have their brand unassigned.')) {
        useForm({}).delete(route('pos.brands.destroy', id))
    }
}
</script>

<template>
    <div class="h-full flex flex-col bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Brands</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Organize and manage catalog item brands, logos, and stock counts</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="openCreate"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl transition font-bold text-xs shadow-md shadow-emerald-900/20 cursor-pointer flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Brand</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <!-- Search Bar -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative w-full max-w-sm">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search brands..."
                        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-10 pr-4 py-2 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition shadow-xs"
                    />
                </div>
            </div>

            <!-- Brands Table -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700/80 text-xs font-semibold">
                        <tr>
                            <th class="text-left px-5 py-3.5">Brand</th>
                            <th class="text-left px-5 py-3.5">Items Count</th>
                            <th class="text-left px-5 py-3.5">Total In-Stock Units</th>
                            <th class="text-left px-5 py-3.5">Status</th>
                            <th class="text-right px-5 py-3.5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="brand in brands.data" :key="brand.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                            <!-- Name & Logo -->
                            <td class="px-5 py-3.5 font-medium text-slate-900 dark:text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        <img
                                            v-if="brand.logo_url"
                                            :src="brand.logo_url"
                                            :alt="brand.name"
                                            class="w-full h-full object-contain"
                                        />
                                        <span v-else class="text-xs font-bold text-slate-400 uppercase">
                                            {{ brand.name.slice(0, 2) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block font-semibold">{{ brand.name }}</span>
                                        <span v-if="brand.description" class="block text-[11px] text-slate-400 max-w-xs truncate">
                                            {{ brand.description }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Items Count -->
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="route('pos.items.index', { brand_id: brand.id })"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition"
                                    title="Click to view all items in this brand"
                                >
                                    <span>{{ (brand.items_count ?? 0).toLocaleString() }}</span>
                                    <span class="text-[10px] text-indigo-500 dark:text-indigo-400 font-normal">items</span>
                                    <svg class="w-3 h-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </Link>
                            </td>

                            <!-- Total Stock Quantity -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 font-mono text-xs">
                                    <span :class="(brand.total_stock_qty ?? 0) > 0 ? 'text-slate-800 dark:text-slate-200 font-semibold' : 'text-rose-500 font-bold'">
                                        {{ Number(brand.total_stock_qty || 0).toLocaleString() }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">units in stock</span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5">
                                <span
                                    :class="brand.is_active ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/80' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 border-slate-300 dark:border-slate-600'"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border"
                                >
                                    {{ brand.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openEdit(brand)"
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteBrand(brand.id)"
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition cursor-pointer"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!brands.data.length">
                            <td colspan="5" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="text-3xl mb-2">✨</div>
                                <p class="text-sm font-medium">No brands found</p>
                                <p class="text-xs text-slate-400 mt-0.5">Try a different search term or add a new brand.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination Footer -->
                <div v-if="brands.last_page > 1" class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 dark:border-slate-700/80 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/50">
                    <span>Showing {{ brands.from }}–{{ brands.to }} of {{ brands.total }} brands</span>
                    <div class="flex gap-1.5">
                        <Link
                            v-if="brands.prev_page_url"
                            :href="brands.prev_page_url"
                            class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition shadow-2xs font-semibold"
                        >
                            Prev
                        </Link>
                        <Link
                            v-if="brands.next_page_url"
                            :href="brands.next_page_url"
                            class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition shadow-2xs font-semibold"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-3">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ editingBrand ? 'Edit Brand' : 'Add New Brand' }}
                    </h2>
                    <button @click="showModal = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Brand Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Apple, Samsung, Coca-Cola"
                            class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 focus:border-emerald-500 outline-none text-xs transition"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Description (Optional)</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Brief details about the manufacturer or brand line"
                            class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 focus:border-emerald-500 outline-none text-xs transition"
                        ></textarea>
                    </div>

                    <!-- Logo Upload -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Brand Logo (Optional)</label>
                        <div class="flex items-center gap-3">
                            <div v-if="logoPreview" class="w-12 h-12 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-100 dark:bg-slate-900 flex items-center justify-center flex-shrink-0">
                                <img :src="logoPreview" alt="Preview" class="w-full h-full object-contain" />
                            </div>
                            <input
                                type="file"
                                accept="image/*"
                                @change="onFileChange"
                                class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-700 file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-600 cursor-pointer"
                            />
                        </div>
                    </div>

                    <!-- Status -->
                    <label class="flex items-center gap-2 cursor-pointer pt-1">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 dark:border-slate-600 cursor-pointer"
                        />
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Brand is active</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-600 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50 cursor-pointer shadow-xs"
                        >
                            {{ form.processing ? 'Saving…' : 'Save Brand' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

