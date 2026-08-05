<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const props = defineProps({ items: Array })

const form = useForm({
    item_id: '',
    location: 'front_store',
    type: 'Addition',
    qty: 1,
    reason: '',
})

// Search & Dropdown state
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
        console.error('Item search error', e)
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

function submit() {
    if (!form.item_id) return
    form.post(route('pos.inventory.process-adjust'))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.inventory.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Manual Stock Adjustment</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-xl mx-auto space-y-5">
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-amber-400 text-sm">
                    <strong>Warning:</strong> Manual adjustments directly impact inventory levels without associated sales or purchase records. Always provide a clear reason for the adjustment (e.g., Damage, Expiry, Stock-take correction).
                </div>

                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4 shadow-lg">
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
                        <p v-if="form.errors.item_id" class="text-red-400 text-xs mt-1">{{ form.errors.item_id }}</p>

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
                                        <div class="text-emerald-400">
                                            Front: <span class="font-bold text-white">{{ item.front_store_qty }}</span>
                                        </div>
                                        <div class="text-indigo-300">
                                            Back: <span class="font-bold text-white">{{ item.back_store_qty }}</span>
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
                                <div class="text-slate-400 text-[10px] uppercase font-semibold">Front Store Stock</div>
                                <div class="text-base font-bold text-emerald-400 mt-0.5">{{ selectedItem.front_store_qty }}</div>
                            </div>
                            <div class="bg-slate-800/80 p-2 rounded-lg text-center">
                                <div class="text-slate-400 text-[10px] uppercase font-semibold">Back Store Stock</div>
                                <div class="text-base font-bold text-indigo-400 mt-0.5">{{ selectedItem.back_store_qty }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Location *</label>
                            <select v-model="form.location" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                <option value="front_store">Front Store</option>
                                <option value="back_store">Back Store</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Type *</label>
                            <select v-model="form.type" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                <option value="Addition">Addition (+)</option>
                                <option value="Subtraction">Subtraction (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Quantity *</label>
                            <input v-model.number="form.qty" type="number" min="1" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            <p v-if="form.errors.qty" class="text-red-400 text-xs mt-1">{{ form.errors.qty }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Reason *</label>
                        <textarea v-model="form.reason" rows="3" required placeholder="e.g. Broken item identified during cycle count" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition resize-none"></textarea>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link :href="route('pos.inventory.index')" class="flex-1 text-center py-3 bg-slate-700 text-slate-300 rounded-xl text-sm hover:bg-slate-600 transition">Cancel</Link>
                    <button type="submit" :disabled="form.processing || !form.item_id" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold transition disabled:opacity-40">
                        {{ form.processing ? 'Processing...' : 'Confirm Adjustment' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
