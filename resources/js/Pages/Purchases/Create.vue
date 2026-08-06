<script setup>
import { ref, computed } from 'vue'
import { useForm, Link, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const page = usePage()
const props = defineProps({
    vendors: Array,
    availableItems: Array,
})

const { format } = useCurrency()
const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)
const showDropdown = ref(false)

const form = useForm({
    vendor_id: '',
    expected_date: '',
    shipping_cost: 0,
    discount: 0,
    notes: '',
    items: [],
})

const branchSlug = computed(() => page.props.current_branch?.slug || 'skynet-digital-enterprise')

// Filter available items locally or via API search
function onSearchInput() {
    showDropdown.value = true
    const q = searchQuery.value.trim().toLowerCase()

    if (!q) {
        searchResults.value = (props.availableItems || []).slice(0, 15)
        return
    }

    if (props.availableItems && props.availableItems.length) {
        searchResults.value = props.availableItems.filter(i =>
            i.item_name.toLowerCase().includes(q) ||
            (i.barcode_number && i.barcode_number.toLowerCase().includes(q))
        )
    } else {
        searchResults.value = []
    }

    if (searchResults.value.length < 5 && q.length >= 2) {
        fetchFromApi(q)
    }
}

function onSearchFocus() {
    showDropdown.value = true
    onSearchInput()
}

async function fetchFromApi(q) {
    isSearching.value = true
    try {
        const { data } = await axios.get(route('pos.api.items.search', { q }))
        if (Array.isArray(data)) {
            const existingIds = new Set(searchResults.value.map(i => i.id))
            data.forEach(item => {
                if (!existingIds.has(item.id)) {
                    searchResults.value.push(item)
                }
            })
        }
    } catch (e) {
        // Fallback silently if offline
    } finally {
        isSearching.value = false
    }
}

function addItem(item) {
    const existing = form.items.find(i => i.item_id === item.id)
    if (existing) {
        existing.qty++
    } else {
        form.items.push({
            item_id: item.id,
            item_name: item.item_name,
            qty: 1,
            cost: item.buy_price || 0,
        })
    }
    searchQuery.value = ''
    showDropdown.value = false
}

function removeItem(index) {
    form.items.splice(index, 1)
}

const subtotal = computed(() => form.items.reduce((sum, item) => sum + (item.qty * item.cost), 0))
const grandTotal = computed(() => subtotal.value + Number(form.shipping_cost) - Number(form.discount))

function submit() {
    if (!form.items.length) {
        alert('Please add at least one item.')
        return
    }
    form.post(route('pos.purchases.store'))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.purchases.index')" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Create Purchase Order</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-5xl mx-auto flex flex-col md:flex-row gap-6 items-start">

                <!-- Left: Items Table & Item Search -->
                <div class="flex-1 w-full bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col min-h-[480px] shadow-xs">
                    
                    <!-- Search Input Bar -->
                    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                @focus="onSearchFocus"
                                type="text"
                                placeholder="Search item by name or barcode to add to PO..."
                                class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-4 py-2.5 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
                            />

                            <!-- Search Results Dropdown Panel -->
                            <div v-if="showDropdown && searchResults.length" class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl z-30 overflow-hidden text-sm">
                                <div class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-600 flex justify-between items-center text-xs text-slate-500 dark:text-slate-400">
                                    <span>Click item to add to Purchase Order</span>
                                    <button type="button" @click="showDropdown = false" class="hover:text-slate-900 dark:hover:text-white">Close ✕</button>
                                </div>
                                <ul class="max-h-60 overflow-y-auto w-full divide-y divide-slate-100 dark:divide-slate-600">
                                    <li
                                        v-for="item in searchResults"
                                        :key="item.id"
                                        @click="addItem(item)"
                                        class="px-4 py-2.5 hover:bg-slate-100 dark:hover:bg-slate-600 cursor-pointer text-slate-800 dark:text-white flex justify-between items-center transition w-full"
                                    >
                                        <div>
                                            <span class="font-medium block">{{ item.item_name }}</span>
                                            <span class="text-xs text-slate-400 font-mono">{{ item.barcode_number || 'No Barcode' }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs bg-slate-200 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-700 dark:text-slate-300 block">
                                                Stock: {{ item.total_qty ?? item.front_store_qty ?? 0 }}
                                            </span>
                                            <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5 block">
                                                Cost: {{ format(item.buy_price || 0) }}
                                            </span>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Items Table -->
                    <div class="flex-1 p-5 overflow-y-auto">
                        <table v-if="form.items.length" class="w-full text-sm">
                            <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="text-left pb-2 font-medium">Item Name</th>
                                    <th class="text-right pb-2 font-medium w-24">Qty</th>
                                    <th class="text-right pb-2 font-medium w-32">Unit Cost</th>
                                    <th class="text-right pb-2 font-medium w-32">Total</th>
                                    <th class="pb-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="(item, index) in form.items" :key="index">
                                    <td class="py-3 text-slate-900 dark:text-white font-medium">{{ item.item_name }}</td>
                                    <td class="py-3 text-right">
                                        <input v-model.number="item.qty" type="number" min="1" class="w-20 bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded border border-slate-200 dark:border-slate-600 text-right outline-none focus:border-emerald-500 font-medium" />
                                    </td>
                                    <td class="py-3 text-right">
                                        <input v-model.number="item.cost" type="number" min="0" step="0.01" class="w-24 bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1.5 rounded border border-slate-200 dark:border-slate-600 text-right outline-none focus:border-emerald-500 font-medium" />
                                    </td>
                                    <td class="py-3 text-right text-emerald-600 dark:text-emerald-400 font-bold">{{ format(item.qty * item.cost) }}</td>
                                    <td class="py-3 text-right">
                                        <button type="button" @click="removeItem(index)" class="text-red-500 hover:text-red-700 transition w-6 h-6 inline-flex items-center justify-center font-bold">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="h-64 flex flex-col items-center justify-center text-slate-400 text-sm space-y-2">
                            <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <p>Search above to add items to this purchase order.</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Vendor & Order Summary -->
                <div class="w-full md:w-80 space-y-4 flex-shrink-0">
                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Vendor *</label>
                            <select v-model="form.vendor_id" required class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 outline-none text-sm transition font-medium">
                                <option value="" disabled>-- Select Vendor --</option>
                                <option v-for="v in vendors" :key="v.id" :value="v.id">
                                    {{ v.name }} {{ v.company_name ? `(${v.company_name})` : '' }}
                                </option>
                            </select>
                            <p v-if="!vendors?.length" class="text-xs text-amber-500 mt-1">No active vendors found. Please add a vendor first.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Expected Delivery Date</label>
                            <input v-model="form.expected_date" type="date" class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Notes / Instructions</label>
                            <textarea v-model="form.notes" rows="2" placeholder="Optional notes for vendor..." class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 outline-none text-sm transition resize-none"></textarea>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-3 shadow-xs">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500 dark:text-slate-400">Subtotal</span>
                            <span class="text-slate-900 dark:text-white font-medium">{{ format(subtotal) }}</span>
                        </div>
                        <div>
                            <label class="flex justify-between items-center text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Shipping Cost</span>
                                <input v-model.number="form.shipping_cost" type="number" min="0" step="0.01" class="w-24 bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1 text-right border border-slate-200 dark:border-slate-600 rounded text-sm outline-none font-medium" />
                            </label>
                        </div>
                        <div>
                            <label class="flex justify-between items-center text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Discount</span>
                                <input v-model.number="form.discount" type="number" min="0" step="0.01" class="w-24 bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white px-2 py-1 text-right border border-slate-200 dark:border-slate-600 rounded text-sm outline-none font-medium" />
                            </label>
                        </div>
                        <div class="pt-3 flex items-center justify-between border-t border-slate-200 dark:border-slate-700 font-bold text-lg">
                            <span class="text-slate-900 dark:text-white">Total Amount</span>
                            <span class="text-emerald-600 dark:text-emerald-400">{{ format(grandTotal) }}</span>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing || !form.items.length || !form.vendor_id" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-md transition disabled:opacity-40">
                        {{ form.processing ? 'Saving Order...' : 'Create Purchase Order' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
