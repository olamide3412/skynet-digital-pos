<script setup>
import { useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { ref } from 'vue'

defineOptions({ layout: PosLayout })
const props = defineProps({ categories: Object })

const showModal = ref(false)
const editingCategory = ref(null)

const form = useForm({ name: '', slug: '' })

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
    if(confirm('Delete Category?')) {
        useForm({}).delete(route('pos.categories.destroy', id))
    }
}
</script>

<template>
    <div class="h-full flex flex-col bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Categories</h1>
            <button @click="openCreate" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition font-medium text-xs shadow-md shadow-emerald-900/20">
                Add Category
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Name</th>
                            <th class="text-left px-4 py-3 font-semibold">Items Count</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="cat in categories.data" :key="cat.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ cat.name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono">{{ cat.items_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button @click="openEdit(cat)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition">Edit</button>
                                    <button @click="deleteCategory(cat.id)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!categories.data.length">
                            <td colspan="3" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No categories found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6 w-full max-w-md shadow-2xl space-y-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ editingCategory ? 'Edit Category' : 'Add Category' }}</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Category Name *</label>
                        <input v-model="form.name" type="text" required
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-50">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
