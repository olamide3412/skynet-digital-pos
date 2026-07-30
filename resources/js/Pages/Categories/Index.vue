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
    <div class="h-full flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800">
            <h1 class="text-xl font-bold text-white">Categories</h1>
            <button @click="openCreate" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition font-medium">
                Add Category
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-slate-900">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">Name</th>
                            <th class="text-left px-4 py-3 font-medium">Slug</th>
                            <th class="text-right px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 text-slate-300">
                        <tr v-for="cat in categories.data" :key="cat.id" class="hover:bg-slate-700/20">
                            <td class="px-4 py-3 font-medium text-white">{{ cat.name }}</td>
                            <td class="px-4 py-3">{{ cat.slug || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openEdit(cat)" class="text-blue-400 hover:text-blue-300 mr-3">Edit</button>
                                <button @click="deleteCategory(cat.id)" class="text-red-400 hover:text-red-300">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-4 flex gap-2 justify-end" v-if="categories.links?.length > 3">
                <template v-for="link in categories.links" :key="link.label">
                    <a v-if="link.url" :href="link.url" v-html="link.label" class="px-3 py-1 rounded bg-slate-800 text-slate-300 hover:bg-slate-700 transition" :class="{'bg-emerald-600/20 text-emerald-400': link.active}"></a>
                </template>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-slate-800 rounded-xl w-full max-w-md shadow-xl border border-slate-700 overflow-hidden">
                <div class="p-4 border-b border-slate-700 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-white">{{ editingCategory ? 'Edit Category' : 'New Category' }}</h2>
                    <button @click="showModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Name</label>
                        <input v-model="form.name" type="text" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" required>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-700 flex justify-end gap-2">
                    <button @click="showModal = false" class="px-4 py-2 text-slate-400 hover:text-white">Cancel</button>
                    <button @click="submit" :disabled="form.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
