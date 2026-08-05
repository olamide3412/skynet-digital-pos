<script setup>
import { ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const props = defineProps({
    grids: Array,
    items: Array,
})

const isEditing       = ref(false)
const currentGrid     = ref(null)
const itemSearchQuery = ref('')
const searchResults   = ref([])
const selectedItemObj = ref(null)
const isSearching     = ref(false)
let   searchDebounce  = null

const form = useForm({
    item_id:    '',
    menu_name:  '',
    menu_index: props.grids.length + 1,
    fore_color: '#ffffff',
    back_color: '#3b82f6',
})

// Initialize dropdown with first 25 items
const displayItems = ref([...props.items])

function onSearchInput(e) {
    const q = e.target.value
    itemSearchQuery.value = q
    clearTimeout(searchDebounce)

    if (!q.trim()) {
        searchResults.value = []
        return
    }

    searchDebounce = setTimeout(async () => {
        isSearching.value = true
        try {
            const res = await axios.get(route('pos.api.items.search'), { params: { q } })
            searchResults.value = res.data
        } catch (err) {
            console.error(err)
        } finally {
            isSearching.value = false
        }
    }, 250)
}

function selectGridItem(item) {
    form.item_id          = item.id
    selectedItemObj.value = item
    itemSearchQuery.value = item.item_name
    searchResults.value   = []
    if (!form.menu_name) {
        form.menu_name = item.item_name
    }
}

function openCreateModal() {
    isEditing.value       = false
    currentGrid.value     = null
    selectedItemObj.value = null
    itemSearchQuery.value = ''
    searchResults.value   = []
    form.reset()
    form.menu_index = props.grids.length + 1
    document.getElementById('grid-modal').showModal()
}

function openEditModal(grid) {
    isEditing.value       = true
    currentGrid.value     = grid
    selectedItemObj.value = grid.item
    itemSearchQuery.value = grid.item?.item_name ?? ''
    searchResults.value   = []
    form.item_id          = grid.item_id
    form.menu_name        = grid.menu_name
    form.menu_index       = grid.menu_index
    form.fore_color       = grid.fore_color || '#ffffff'
    form.back_color       = grid.back_color || '#3b82f6'
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
            <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5 shadow-lg shadow-emerald-900/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Grid Item
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
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
                            <td class="px-4 py-3 text-slate-300 font-mono font-bold">{{ grid.menu_index }}</td>
                            <td class="px-4 py-3 text-slate-300 font-medium">{{ grid.item?.item_name }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ grid.menu_name || grid.item?.item_name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded border border-slate-600 shadow-inner" :style="{ backgroundColor: grid.back_color || '#3b82f6' }"></div>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded" :style="{ color: grid.fore_color || '#fff', backgroundColor: grid.back_color || '#3b82f6' }">Preview</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button @click="openEditModal(grid)" class="text-xs font-semibold text-blue-400 hover:text-blue-300 transition">Edit</button>
                                    <button @click="destroy(grid)" class="text-xs font-semibold text-red-400 hover:text-red-300 transition">Remove</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!grids.length">
                            <td colspan="5" class="px-4 py-12 text-center text-slate-500">No grid elements configured yet. Click "Add Grid Item" to start.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <dialog id="grid-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-2xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95">
            <div class="bg-slate-800 border border-slate-700 w-full max-w-md overflow-hidden rounded-2xl">
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/80">
                    <h3 class="font-bold text-white text-base">{{ isEditing ? 'Edit Grid Item' : 'Add Grid Item' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-white transition">✕</button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        
                        <!-- Searchable Item Selector Input -->
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Search & Select Item *</label>
                            <div class="relative">
                                <input
                                    :value="itemSearchQuery"
                                    @input="onSearchInput"
                                    type="text"
                                    placeholder="Type item name or barcode..."
                                    required
                                    class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition"
                                />
                                <span v-if="isSearching" class="absolute right-3 top-2.5 text-xs text-slate-400">Searching…</span>

                                <!-- Live Dynamic Search Results Dropdown -->
                                <div v-if="searchResults.length" class="absolute z-30 top-full left-0 right-0 mt-1 bg-slate-900 border border-slate-650 rounded-xl shadow-2xl max-h-52 overflow-y-auto divide-y divide-slate-800">
                                    <button
                                        v-for="item in searchResults"
                                        :key="item.id"
                                        type="button"
                                        @click="selectGridItem(item)"
                                        class="w-full px-3.5 py-2.5 text-left hover:bg-slate-800 transition flex justify-between items-center text-xs group"
                                    >
                                        <span class="font-semibold text-white group-hover:text-emerald-300 transition truncate">{{ item.item_name }}</span>
                                        <span class="text-slate-400 font-mono text-[11px] ml-2 flex-shrink-0">{{ item.barcode_number || 'No Barcode' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Initial Quick Dropdown Pick list (First 25 Items) -->
                            <div v-if="!itemSearchQuery && displayItems.length" class="mt-2">
                                <label class="block text-[11px] text-slate-500 mb-1">Or choose from initial catalog (first 25):</label>
                                <select
                                    @change="selectGridItem(displayItems.find(i => i.id == $event.target.value))"
                                    class="w-full bg-slate-750 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-700 outline-none text-xs"
                                >
                                    <option value="" disabled selected>-- Select from catalog --</option>
                                    <option v-for="item in displayItems" :key="item.id" :value="item.id">
                                        {{ item.item_name }} ({{ item.barcode_number || 'No Barcode' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Selected Item Badge -->
                            <div v-if="selectedItemObj" class="mt-3 flex items-center justify-between bg-emerald-500/10 p-3 rounded-xl border border-emerald-500/30 text-xs">
                                <div>
                                    <p class="text-[10px] text-emerald-400 font-semibold uppercase tracking-wider">Selected Product</p>
                                    <p class="font-bold text-white text-sm mt-0.5">{{ selectedItemObj.item_name }}</p>
                                </div>
                                <span class="text-slate-400 font-mono text-xs bg-slate-800 px-2 py-1 rounded border border-slate-700">{{ selectedItemObj.barcode_number || 'No Barcode' }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Display Label on POS Grid (Optional)</label>
                            <input v-model="form.menu_name" type="text" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" placeholder="Leave empty to use item name" />
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Grid Display Order (Index) *</label>
                            <input v-model.number="form.menu_index" type="number" required min="0" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Background Color</label>
                                <input v-model="form.back_color" type="color" class="w-full h-10 bg-slate-700 rounded-lg cursor-pointer border border-slate-600 p-1" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Text Color</label>
                                <input v-model="form.fore_color" type="color" class="w-full h-10 bg-slate-700 rounded-lg cursor-pointer border border-slate-600 p-1" />
                            </div>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-700 text-slate-300 rounded-xl text-sm hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing || !form.item_id" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-900/20 transition disabled:opacity-40">
                                {{ form.processing ? 'Saving...' : 'Save Grid Item' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</template>
