<script setup>
import { ref, watch } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    categories: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const showModal = ref(false)
const editingCategory = ref(null)

const form = useForm({
    name: '',
    slug: '',
})

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('pos.categories.index'), { search: val }, {
            preserveState: true,
            replace: true,
        })
    }, 300)
})

const openCreate = () => {
    editingCategory.value = null
    form.reset()
    showModal.value = true
}

const openEdit = (cat) => {
    editingCategory.value = cat
    form.name = cat.name
    form.slug = cat.slug || ''
    showModal.value = true
}

const submit = () => {
    if (editingCategory.value) {
        form.put(route('pos.categories.update', editingCategory.value.id), {
            onSuccess: () => { showModal.value = false; form.reset() }
        })
    } else {
        form.post(route('pos.categories.store'), {
            onSuccess: () => { showModal.value = false; form.reset() }
        })
    }
}

const deleteCategory = (id) => {
    if (confirm('Delete Category? Items linked to this category will not be deleted, but will become uncategorized.')) {
        useForm({}).delete(route('pos.categories.destroy', id))
    }
}
</script>

<template>
    <div class="h-full flex flex-col bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Categories</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Organize and manage catalog item groupings and stock counts</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="openCreate"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl transition font-bold text-xs shadow-md shadow-emerald-900/20 cursor-pointer flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Category</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <!-- Search & Quick Filter Bar -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative w-full max-w-sm">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search categories..."
                        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-10 pr-4 py-2 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition shadow-xs"
                    />
                </div>
            </div>

            <!-- Categories Table -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700/80 text-xs font-semibold">
                        <tr>
                            <th class="text-left px-5 py-3.5">Category Name</th>
                            <th class="text-left px-5 py-3.5">Items Count</th>
                            <th class="text-left px-5 py-3.5">Total In-Stock Units</th>
                            <th class="text-right px-5 py-3.5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="cat in categories.data" :key="cat.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                            <!-- Name -->
                            <td class="px-5 py-3.5 font-medium text-slate-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500/70"></span>
                                    <span>{{ cat.name }}</span>
                                </div>
                            </td>

                            <!-- Items Count -->
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="route('pos.items.index', { category_id: cat.id })"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition"
                                    title="Click to view all items in this category"
                                >
                                    <span>{{ (cat.items_count ?? 0).toLocaleString() }}</span>
                                    <span class="text-[10px] text-indigo-500 dark:text-indigo-400 font-normal">items</span>
                                    <svg class="w-3 h-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </Link>
                            </td>

                            <!-- Total Stock Quantity -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 font-mono text-xs">
                                    <span :class="(cat.total_stock_qty ?? 0) > 0 ? 'text-slate-800 dark:text-slate-200 font-semibold' : 'text-rose-500 font-bold'">
                                        {{ Number(cat.total_stock_qty || 0).toLocaleString() }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">units in stock</span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openEdit(cat)"
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteCategory(cat.id)"
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition cursor-pointer"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!categories.data.length">
                            <td colspan="4" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="text-3xl mb-2">🏷️</div>
                                <p class="text-sm font-medium">No categories found</p>
                                <p class="text-xs text-slate-400 mt-0.5">Try a different search term or add a new category.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination Footer -->
                <div v-if="categories.last_page > 1" class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 dark:border-slate-700/80 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/50">
                    <span>Showing {{ categories.from }}–{{ categories.to }} of {{ categories.total }} categories</span>
                    <div class="flex gap-1.5">
                        <Link
                            v-if="categories.prev_page_url"
                            :href="categories.prev_page_url"
                            class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition shadow-2xs font-semibold"
                        >
                            Prev
                        </Link>
                        <Link
                            v-if="categories.next_page_url"
                            :href="categories.next_page_url"
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
                        {{ editingCategory ? 'Edit Category' : 'Add New Category' }}
                    </h2>
                    <button @click="showModal = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Category Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Beverages, Pharmaceuticals"
                            class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 focus:border-emerald-500 outline-none text-xs transition"
                        />
                    </div>

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
                            {{ form.processing ? 'Saving…' : 'Save Category' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
