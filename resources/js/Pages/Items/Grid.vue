<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    grids: Array,
    items: Array,
})

const isEditing = ref(false)
const currentGrid = ref(null)

const form = useForm({
    item_id: '',
    menu_name: '',
    menu_index: props.grids.length + 1,
    fore_color: '#ffffff',
    back_color: '#3b82f6',
})

function openCreateModal() {
    isEditing.value = false
    currentGrid.value = null
    form.reset()
    form.menu_index = props.grids.length + 1
    document.getElementById('grid-modal').showModal()
}

function openEditModal(grid) {
    isEditing.value = true
    currentGrid.value = grid
    form.item_id = grid.item_id
    form.menu_name = grid.menu_name
    form.menu_index = grid.menu_index
    form.fore_color = grid.fore_color || '#ffffff'
    form.back_color = grid.back_color || '#3b82f6'
    document.getElementById('grid-modal').showModal()
}

function submit() {
    if (isEditing.value) {
        form.put(route('pos.item-grids.update', currentGrid.value.id), {
            onSuccess: () => document.getElementById('grid-modal').close(),
        })
    } else {
        form.post(route('pos.item-grids.store'), {
            onSuccess: () => document.getElementById('grid-modal').close(),
        })
    }
}

function destroy(grid) {
    if (confirm('Are you sure you want to remove this item from the grid?')) {
        router.delete(route('pos.item-grids.destroy', grid.id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">Grid Configuration</h1>
            <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Grid Item
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Index</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Item Name</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Display Name</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Colors</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="grid in grids" :key="grid.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-300">{{ grid.menu_index }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ grid.item?.item_name }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ grid.menu_name || grid.item?.item_name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded border border-slate-600" :style="{ backgroundColor: grid.back_color || '#3b82f6' }"></div>
                                    <span class="text-xs text-slate-400" :style="{ color: grid.fore_color || '#fff' }">Text</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEditModal(grid)" class="text-xs text-blue-400 hover:text-blue-300 transition">Edit</button>
                                    <button @click="destroy(grid)" class="text-xs text-red-400 hover:text-red-300 transition">Remove</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!grids.length">
                            <td colspan="5" class="px-4 py-12 text-center text-slate-500">No grid elements configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <dialog id="grid-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95">
            <div class="bg-slate-800 border border-slate-700 w-full max-w-md overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                    <h3 class="font-bold text-white">{{ isEditing ? 'Edit Grid Item' : 'Add Grid Item' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Select Item *</label>
                            <select v-model="form.item_id" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm transition">
                                <option value="" disabled>Choose an item...</option>
                                <option v-for="item in items" :key="item.id" :value="item.id">
                                    {{ item.item_name }} ({{ item.barcode_number }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Display Name (Optional)</label>
                            <input v-model="form.menu_name" type="text" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" placeholder="Leave empty to use item name" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Index (Order) *</label>
                            <input v-model.number="form.menu_index" type="number" required min="0" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Background Color</label>
                                <input v-model="form.back_color" type="color" class="w-full h-10 bg-slate-700 rounded-lg cursor-pointer" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Text Color</label>
                                <input v-model="form.fore_color" type="color" class="w-full h-10 bg-slate-700 rounded-lg cursor-pointer" />
                            </div>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition disabled:opacity-40">
                                {{ form.processing ? 'Saving...' : 'Save Item' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</template>
