<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    groupAddresses: Object
})

const showModal          = ref(false)
const editingGroupAddress = ref(null)

const form = useForm({
    name: ''
})

const openCreate = () => {
    editingGroupAddress.value = null
    form.reset()
    form.clearErrors()
    showModal.value = true
}

const openEdit = (group) => {
    editingGroupAddress.value = group
    form.name = group.name
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    if (editingGroupAddress.value) {
        form.put(route('pos.group-addresses.update', editingGroupAddress.value.id), {
            onSuccess: () => { showModal.value = false; form.reset() }
        })
    } else {
        form.post(route('pos.group-addresses.store'), {
            onSuccess: () => { showModal.value = false; form.reset() }
        })
    }
}

const deleteGroupAddress = (group) => {
    if (confirm(`Delete Group/Address "${group.name}"?`)) {
        useForm({}).delete(route('pos.group-addresses.destroy', group.id))
    }
}
</script>

<template>
    <div class="h-full flex flex-col overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Group / Address Storage Locations</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage shelf, aisle, or warehouse storage locations for items</p>
            </div>
            <button @click="openCreate" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl transition text-xs font-bold shadow-md shadow-emerald-900/20 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Group / Address
            </button>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Storage / Group Name</th>
                            <th class="px-6 py-3 font-semibold text-center">Items Assigned</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50 text-slate-800 dark:text-slate-300">
                        <tr v-for="group in groupAddresses.data" :key="group.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-mono text-xs border border-emerald-500/20">
                                        📍
                                    </div>
                                    <span>{{ group.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-mono">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    {{ group.items_count ?? 0 }} items
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button @click="openEdit(group)" class="px-3 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-semibold transition">
                                    Edit
                                </button>
                                <button @click="deleteGroupAddress(group)" class="px-3 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold transition">
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="!groupAddresses.data.length">
                            <td colspan="3" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                No group / address locations found. Click "Add Group / Address" to create one.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex gap-2 justify-end" v-if="groupAddresses.links?.length > 3">
                <template v-for="link in groupAddresses.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition text-xs font-medium border border-slate-200 dark:border-slate-700"
                        :class="{'bg-emerald-600 text-white font-bold border-emerald-500': link.active}"
                    />
                </template>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-xl w-full max-w-md shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/80">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ editingGroupAddress ? 'Edit Storage Location' : 'New Storage Location' }}
                    </h2>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition">✕</button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Group / Address Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. Shelf A1, Warehouse Row 3, Rack 5"
                            class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-slate-900 dark:text-white focus:border-emerald-500 outline-none text-sm transition"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 bg-slate-50 dark:bg-slate-800">
                    <button @click="showModal = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 rounded-lg text-xs font-semibold transition">
                        Cancel
                    </button>
                    <button @click="submit" :disabled="form.processing" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40">
                        {{ form.processing ? 'Saving...' : 'Save Storage Location' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
