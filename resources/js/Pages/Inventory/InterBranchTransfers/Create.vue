<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const page = usePage()
const currentBranchSlug = computed(() => {
    return page.props.current_branch?.slug
        || page.props.branch?.slug
        || page.props.auth?.user?.branch?.slug
        || ''
})

const props = defineProps({
    siblingBranches: Array,
    items: Array,
})

const form = useForm({
    destination_branch_id: props.siblingBranches?.[0]?.id || '',
    from_location: 'back_store',
    items: [],
    notes: '',
})

// Search & Dropdown State (matching StockTransfer.vue UX)
const searchQuery = ref('')
const searchResults = ref(props.items || [])
const isSearching = ref(false)
const isDropdownOpen = ref(false)
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
        const { data } = await axios.get(route('pos.api.items.search', { branch: currentBranchSlug.value }), {
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

const clearSearch = () => {
    searchQuery.value = ''
    searchResults.value = props.items || []
    isDropdownOpen.value = false
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

const getItemAvailableStock = (item) => {
    if (!item) return 0
    return form.from_location === 'back_store' ? (item.back_store_qty || 0) : (item.front_store_qty || 0)
}

const selectItemToAdd = (item) => {
    const existingIndex = form.items.findIndex(i => i.item_id === item.id)
    const available = getItemAvailableStock(item)

    if (existingIndex !== -1) {
        // Increment quantity if not exceeding available stock
        if (form.items[existingIndex].quantity < available) {
            form.items[existingIndex].quantity++
        }
    } else {
        // Add new item line
        form.items.push({
            item_id: item.id,
            item_name: item.item_name,
            barcode_number: item.barcode_number,
            unit_label: item.unit_label || 'pcs',
            back_store_qty: item.back_store_qty || 0,
            front_store_qty: item.front_store_qty || 0,
            quantity: available > 0 ? 1 : 1,
            notes: '',
        })
    }

    clearSearch()
}

const removeItemLine = (idx) => {
    form.items.splice(idx, 1)
}

const incrementQty = (line) => {
    const available = form.from_location === 'back_store' ? line.back_store_qty : line.front_store_qty
    if (line.quantity < available) {
        line.quantity++
    }
}

const decrementQty = (line) => {
    if (line.quantity > 1) {
        line.quantity--
    }
}

const totalItemsCount = computed(() => {
    return form.items.filter(i => i.item_id && i.quantity > 0).length
})

const totalUnitsCount = computed(() => {
    return form.items.reduce((sum, i) => sum + (Number(i.quantity) || 0), 0)
})

const submitTransfer = () => {
    if (form.items.length === 0) return

    form.transform((data) => ({
        ...data,
        items: data.items.map(i => ({
            item_id: i.item_id,
            quantity: i.quantity,
            notes: i.notes || '',
        })),
    })).post(route('pos.inventory.transfers.inter-branch.store', { branch: currentBranchSlug.value }))
}
</script>

<template>
    <Head title="Send Stock to Branch - Inventory" />

    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Top Fixed Header Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500 mb-0.5">
                    <Link :href="route('pos.inventory.index', { branch: currentBranchSlug })" class="hover:text-theme transition">Inventory</Link>
                    <span>/</span>
                    <Link :href="route('pos.inventory.transfers.inter-branch.index', { branch: currentBranchSlug })" class="hover:text-theme transition">Inter-Branch Transfers</Link>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-slate-300 font-medium">New Transfer</span>
                </div>
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
                    Dispatch Stock to Another Branch
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Select target branch, storage location, and search items to transfer
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    :href="route('pos.inventory.transfers.inter-branch.index', { branch: currentBranchSlug })"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 transition shadow-2xs"
                >
                    Cancel
                </Link>
            </div>
        </div>

        <!-- Scrollable Body Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6">
            <div class="max-w-4xl mx-auto space-y-5 pb-12">
                <form @submit.prevent="submitTransfer" class="space-y-5">
                    <!-- Card 1: Destination Branch & Storage Location -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                1. Destination & Source Location
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Destination Branch *
                                </label>
                                <select
                                    v-model="form.destination_branch_id"
                                    required
                                    class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white py-2 px-3 focus:ring-1 focus:ring-theme focus:border-theme font-medium outline-none"
                                >
                                    <option value="" disabled>Select target branch...</option>
                                    <option v-for="b in siblingBranches" :key="b.id" :value="b.id">
                                        {{ b.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Draw Stock From Location *
                                </label>
                                <div class="grid grid-cols-2 gap-2.5">
                                    <label
                                        class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition text-xs"
                                        :class="form.from_location === 'back_store'
                                            ? 'border-purple-500 bg-purple-50/60 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200 font-bold'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
                                    >
                                        <input
                                            type="radio"
                                            value="back_store"
                                            v-model="form.from_location"
                                            class="text-theme focus:ring-theme"
                                        />
                                        <div>
                                            <div class="font-bold">Back Store</div>
                                            <div class="text-[10px] text-slate-400 font-normal">Warehouse / Back</div>
                                        </div>
                                    </label>

                                    <label
                                        class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition text-xs"
                                        :class="form.from_location === 'front_store'
                                            ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 font-bold'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
                                    >
                                        <input
                                            type="radio"
                                            value="front_store"
                                            v-model="form.from_location"
                                            class="text-theme focus:ring-theme"
                                        />
                                        <div>
                                            <div class="font-bold">Front Store</div>
                                            <div class="text-[10px] text-slate-400 font-normal">POS / Sales Floor</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Items to Transfer (Live Search & Item List) -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700">
                            <div>
                                <h2 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                    2. Search & Select Items to Transfer
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Type item name or barcode to quickly search across inventory and add to this transfer.
                                </p>
                            </div>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                Selected: <strong class="text-slate-800 dark:text-slate-200">{{ totalItemsCount }}</strong> items ({{ totalUnitsCount }} total units)
                            </span>
                        </div>

                        <!-- Item Search Input & Dropdown (Identical UX to StockTransfer.vue) -->
                        <div ref="dropdownContainer" class="relative">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Search Item by Name or Barcode
                            </label>

                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    @input="onSearchInput"
                                    @focus="isDropdownOpen = true"
                                    type="text"
                                    placeholder="Type item name or scan barcode to add..."
                                    class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-xl pl-9 pr-8 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-theme outline-none transition placeholder-slate-400 shadow-2xs"
                                />
                                <!-- Search Icon -->
                                <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <!-- Clear Button -->
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    @click="clearSearch"
                                    class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-700 dark:hover:text-white transition cursor-pointer"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Dropdown Results Popover -->
                            <div
                                v-if="isDropdownOpen"
                                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl z-50 overflow-hidden max-h-72 overflow-y-auto"
                            >
                                <div v-if="isSearching" class="px-4 py-3 text-center text-xs text-slate-400 flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-theme" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Searching branch inventory...
                                </div>
                                <div v-else-if="!searchResults.length" class="px-4 py-3 text-center text-xs text-slate-400">
                                    No matching items found.
                                </div>
                                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <li
                                        v-for="item in searchResults"
                                        :key="item.id"
                                        @click="selectItemToAdd(item)"
                                        class="px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between transition text-xs"
                                    >
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                {{ item.item_name }}
                                                <span v-if="item.unit_label" class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 font-normal">
                                                    {{ item.unit_label }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 font-mono">{{ item.barcode_number || 'No Barcode' }}</div>
                                        </div>
                                        <div class="text-right text-[11px] space-y-0.5">
                                            <div class="text-purple-600 dark:text-purple-400 font-medium">
                                                Back: <span class="font-bold text-slate-900 dark:text-white">{{ item.back_store_qty ?? 0 }}</span>
                                            </div>
                                            <div class="text-blue-600 dark:text-blue-400 font-medium">
                                                Front: <span class="font-bold text-slate-900 dark:text-white">{{ item.front_store_qty ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Selected Items Table / Transfer Lines -->
                        <div class="pt-2">
                            <!-- Empty State -->
                            <div
                                v-if="form.items.length === 0"
                                class="py-10 text-center border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 dark:text-slate-500"
                            >
                                <svg class="w-9 h-9 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="font-bold text-slate-700 dark:text-slate-300 text-xs">No items added to this transfer yet</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Use the search box above to search by item name or barcode to add items.</p>
                            </div>

                            <!-- List of Added Items -->
                            <div v-else class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden divide-y divide-slate-100 dark:divide-slate-700">
                                <div
                                    v-for="(line, idx) in form.items"
                                    :key="line.item_id"
                                    class="p-3.5 bg-white dark:bg-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs"
                                >
                                    <!-- Item Details -->
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white text-xs truncate">
                                            {{ line.item_name }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                            {{ line.barcode_number || 'No Barcode' }}
                                        </div>
                                    </div>

                                    <!-- Available in Selected Storage -->
                                    <div class="flex-shrink-0 text-center">
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 block uppercase font-semibold">Available</span>
                                        <span
                                            class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border mt-0.5"
                                            :class="form.from_location === 'back_store'
                                                ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800'
                                                : 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'"
                                        >
                                            {{ form.from_location === 'back_store' ? line.back_store_qty : line.front_store_qty }} {{ line.unit_label }}
                                        </span>
                                    </div>

                                    <!-- Quantity Input with Stepper -->
                                    <div class="flex-shrink-0 flex items-center gap-1.5">
                                        <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 mr-1">Send Qty:</span>
                                        <button
                                            type="button"
                                            @click="decrementQty(line)"
                                            class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-200 dark:hover:bg-slate-600 flex items-center justify-center cursor-pointer select-none"
                                        >
                                            -
                                        </button>
                                        <input
                                            type="number"
                                            min="1"
                                            :max="form.from_location === 'back_store' ? line.back_store_qty : line.front_store_qty"
                                            v-model.number="line.quantity"
                                            required
                                            class="w-16 px-2 py-1 text-center font-bold text-xs rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white outline-none focus:border-theme"
                                        />
                                        <button
                                            type="button"
                                            @click="incrementQty(line)"
                                            class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-200 dark:hover:bg-slate-600 flex items-center justify-center cursor-pointer select-none"
                                        >
                                            +
                                        </button>
                                        <span class="text-[11px] text-slate-400 ml-1">{{ line.unit_label }}</span>
                                    </div>

                                    <!-- Line Notes (Optional) -->
                                    <div class="w-full md:w-36 flex-shrink-0">
                                        <input
                                            type="text"
                                            v-model="line.notes"
                                            placeholder="Note (optional)..."
                                            class="w-full text-[11px] px-2 py-1 rounded-md border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white outline-none focus:border-theme placeholder-slate-400"
                                        />
                                    </div>

                                    <!-- Remove Button -->
                                    <div class="flex-shrink-0">
                                        <button
                                            type="button"
                                            @click="removeItemLine(idx)"
                                            class="p-1 text-slate-400 hover:text-red-500 transition cursor-pointer"
                                            title="Remove item"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Notes & Submit -->
                    <div class="bg-white dark:bg-slate-800 p-5 rounded-xl shadow-xs border border-slate-200 dark:border-slate-700 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Transfer Notes / Memo (Optional)
                            </label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                placeholder="Add notes for the receiving branch manager..."
                                class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-1 focus:ring-theme focus:border-theme outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700">
                            <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                Stock will be immediately deducted from your chosen storage location upon dispatch.
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing || form.items.length === 0"
                                class="px-5 py-2 text-xs font-bold rounded-xl bg-theme hover:bg-theme-dark disabled:opacity-40 text-white shadow-xs active:scale-95 transition cursor-pointer"
                            >
                                Dispatch Transfer Out ({{ totalUnitsCount }} Units)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
