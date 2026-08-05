<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const props = defineProps({
    transfers: Object,
    items: Array,
})

const form = useForm({
    item_id: '',
    qty: 1,
    unit: 'unit',
    from_location: 'back_store',
    to_location: 'front_store',
    notes: '',
})

// Search & Dropdown State
const searchQuery = ref('')
const searchResults = ref(props.items || [])
const isSearching = ref(false)
const isDropdownOpen = ref(false)
const selectedItem = ref(null)
const dropdownContainer = ref(null)

let debounceTimer = null

const performSearch = async () => {
    if (!searchQuery.value || searchQuery.value.trim().length === 0) {
        searchResults.value = props.items || []
        isSearching.value = false
        return
    }

    isSearching.value = true
    try {
        const { data } = await axios.get(route('pos.api.items.search'), {
            params: { q: searchQuery.value }
        })
        searchResults.value = data
    } catch (e) {
        console.error('Item search failed', e)
    } finally {
        isSearching.value = false
    }
}

const onSearchInput = () => {
    isDropdownOpen.value = true
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        performSearch()
    }, 250)
}

const selectItem = (item) => {
    selectedItem.value = item
    form.item_id = item.id
    searchQuery.value = item.item_name
    isDropdownOpen.value = false
}

const clearSelection = () => {
    selectedItem.value = null
    form.item_id = ''
    searchQuery.value = ''
    searchResults.value = props.items || []
    isDropdownOpen.value = true
}

const handleClickOutside = (event) => {
    if (dropdownContainer.value && !dropdownContainer.value.contains(event.target)) {
        isDropdownOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const swapLocations = () => {
    const temp = form.from_location
    form.from_location = form.to_location
    form.to_location = temp
}

const onFromChange = () => {
    form.to_location = form.from_location === 'back_store' ? 'front_store' : 'back_store'
}

const onToChange = () => {
    form.from_location = form.to_location === 'back_store' ? 'front_store' : 'back_store'
}

const submit = () => {
    if (!form.item_id) return
    form.post(route('pos.inventory.transfers.store'), {
        onSuccess: () => {
            form.reset('qty', 'notes')
        },
    })
}
</script>

<template>
    <Head title="Stock Transfer - Inventory" />
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-white">Stock Transfer (Back-Store ↔ Front-Store)</h1>
                <p class="text-xs text-slate-400">Transfer inventory between back-store (warehouse) and front-store (POS floor)</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Transfer Form Card -->
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 space-y-4 shadow-lg">
                    <div class="flex items-center justify-between border-b border-slate-700 pb-3">
                        <h2 class="font-bold text-white text-sm uppercase tracking-wider text-emerald-400">
                            New Stock Movement
                        </h2>
                        <button type="button" @click="swapLocations" class="px-2.5 py-1 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded text-xs font-medium border border-slate-600 flex items-center gap-1 transition">
                            <span>⇄</span> Swap Direction
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Item Search Input & Dropdown -->
                        <div ref="dropdownContainer" class="relative">
                            <label class="block text-xs text-slate-400 mb-1 font-medium">Search Item *</label>
                            
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    @input="onSearchInput"
                                    @focus="isDropdownOpen = true"
                                    type="text"
                                    placeholder="Type item name or barcode..."
                                    class="w-full bg-slate-700 border border-slate-600 rounded-lg pl-9 pr-8 py-2.5 text-sm text-white focus:border-emerald-500 outline-none transition placeholder-slate-400"
                                />
                                <!-- Search Icon -->
                                <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <!-- Clear Button -->
                                <button
                                    v-if="searchQuery || selectedItem"
                                    type="button"
                                    @click="clearSelection"
                                    class="absolute right-2.5 top-2.5 text-slate-400 hover:text-white transition"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Dropdown Results Box -->
                            <div
                                v-if="isDropdownOpen"
                                class="absolute left-0 right-0 top-full mt-1 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl z-50 overflow-hidden max-h-64 overflow-y-auto"
                            >
                                <div v-if="isSearching" class="px-4 py-3 text-center text-xs text-slate-400 flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Searching items...
                                </div>
                                <div v-else-if="!searchResults.length" class="px-4 py-4 text-center text-xs text-slate-400">
                                    No items found. Try searching by name or barcode.
                                </div>
                                <ul v-else class="divide-y divide-slate-800">
                                    <li
                                        v-for="item in searchResults"
                                        :key="item.id"
                                        @click="selectItem(item)"
                                        class="px-4 py-2.5 hover:bg-slate-800 cursor-pointer transition flex items-center justify-between group"
                                    >
                                        <div>
                                            <div class="text-sm font-medium text-white group-hover:text-emerald-400 transition">
                                                {{ item.item_name }}
                                            </div>
                                            <div v-if="item.barcode_number" class="text-xs text-slate-400 font-mono">
                                                BC: {{ item.barcode_number }}
                                            </div>
                                        </div>
                                        <div class="text-right text-xs space-y-0.5">
                                            <div class="text-indigo-300">
                                                Back: <span class="font-bold text-white">{{ item.back_store_qty }}</span>
                                            </div>
                                            <div class="text-emerald-400">
                                                Front: <span class="font-bold text-white">{{ item.front_store_qty }}</span>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Selected Item Stock Card -->
                        <div v-if="selectedItem" class="bg-slate-900/90 border border-slate-700 p-3.5 rounded-xl text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-emerald-400 text-sm">{{ selectedItem.item_name }}</span>
                                <button type="button" @click="clearSelection" class="text-xs text-slate-400 hover:text-white underline">Change</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-800">
                                <div class="bg-slate-800/80 p-2 rounded-lg text-center">
                                    <div class="text-slate-400 text-[10px] uppercase font-semibold">Back-Store</div>
                                    <div class="text-base font-bold text-indigo-400 mt-0.5">{{ selectedItem.back_store_qty }}</div>
                                </div>
                                <div class="bg-slate-800/80 p-2 rounded-lg text-center">
                                    <div class="text-slate-400 text-[10px] uppercase font-semibold">Front-Store</div>
                                    <div class="text-base font-bold text-emerald-400 mt-0.5">{{ selectedItem.front_store_qty }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- From / To Location Selection -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">From Location</label>
                                <select v-model="form.from_location" @change="onFromChange" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white outline-none">
                                    <option value="back_store">Back Store (Warehouse)</option>
                                    <option value="front_store">Front Store (POS Floor)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">To Location</label>
                                <select v-model="form.to_location" @change="onToChange" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white outline-none">
                                    <option value="front_store">Front Store (POS Floor)</option>
                                    <option value="back_store">Back Store (Warehouse)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Quantity & Unit Selection -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Quantity *</label>
                                <input v-model="form.qty" type="number" min="1" required class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-emerald-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Unit Type *</label>
                                <select v-model="form.unit" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-emerald-500">
                                    <option value="unit">{{ selectedItem?.unit_label || 'Unit' }}</option>
                                    <option value="pack">{{ selectedItem?.pack_label || 'Pack' }}</option>
                                    <option value="carton">{{ selectedItem?.carton_label || 'Carton' }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Notes / Reason</label>
                            <textarea v-model="form.notes" rows="2" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-emerald-500 resize-none" placeholder="e.g. Restocking front shelf for morning rush"></textarea>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing || !form.item_id"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-semibold rounded-lg text-sm transition shadow-lg shadow-emerald-900/20"
                        >
                            Execute Transfer
                        </button>
                    </form>
                </div>

                <!-- History Table Card -->
                <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-lg">
                    <div class="px-6 py-4 border-b border-slate-700 font-bold text-white text-sm">Recent Transfers</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-700/50 text-xs uppercase font-semibold text-slate-400 border-b border-slate-700">
                                <tr>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Item</th>
                                    <th class="px-6 py-3">Qty Base Units</th>
                                    <th class="px-6 py-3">Movement</th>
                                    <th class="px-6 py-3">User</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <tr v-for="t in transfers.data" :key="t.id" class="hover:bg-slate-700/30">
                                    <td class="px-6 py-4 text-xs text-slate-400 font-mono">{{ new Date(t.created_at).toLocaleString() }}</td>
                                    <td class="px-6 py-4 font-semibold text-white">{{ t.item?.item_name || 'Item Deleted' }}</td>
                                    <td class="px-6 py-4 font-mono font-bold text-emerald-400">{{ t.qty_base_units }}</td>
                                    <td class="px-6 py-4 text-xs">
                                        <span class="text-slate-400 uppercase font-mono">{{ t.from_location }}</span>
                                        <span class="text-emerald-400 mx-1">&rarr;</span>
                                        <span class="text-emerald-400 uppercase font-mono">{{ t.to_location }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-slate-400">{{ t.user?.name }}</td>
                                </tr>
                                <tr v-if="!transfers.data.length">
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">No transfer records found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
