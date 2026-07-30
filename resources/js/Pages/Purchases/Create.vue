<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const props = defineProps({
    vendors: Array,
})

const { format } = useCurrency()
const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)

const form = useForm({
    vendor_id: '',
    expected_date: '',
    shipping_cost: 0,
    discount: 0,
    notes: '',
    items: [],
})

async function searchItems() {
    if (searchQuery.value.length < 2) {
        searchResults.value = []
        return
    }
    isSearching.value = true
    try {
        const { data } = await axios.get(route('pos.api.items.search', { q: searchQuery.value }))
        searchResults.value = data
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
    searchResults.value = []
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
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.purchases.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Create Purchase Order</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-5xl mx-auto flex gap-6 items-start">
                
                <!-- Left: Items -->
                <div class="flex-1 bg-slate-800 rounded-xl border border-slate-700 flex flex-col min-h-[500px]">
                    <div class="px-5 py-4 border-b border-slate-700 relative">
                        <input v-model="searchQuery" @input="searchItems" type="text" placeholder="Search item by name or barcode to add..." class="w-full bg-slate-700 text-white placeholder-slate-400 px-4 py-2.5 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
                        
                        <!-- Search Dropdown -->
                        <div v-if="searchQuery.length >= 2" class="absolute top-full left-5 right-5 mt-1 bg-slate-700 border border-slate-600 rounded-lg shadow-xl z-10 overflow-hidden text-sm">
                            <div v-if="isSearching" class="p-3 text-slate-400 text-center">Searching...</div>
                            <div v-else-if="!searchResults.length" class="p-3 text-slate-400 text-center">No items found.</div>
                            <ul v-else class="max-h-60 overflow-y-auto w-full">
                                <li v-for="item in searchResults" :key="item.id" @click="addItem(item)" class="px-4 py-2 hover:bg-slate-600 cursor-pointer text-white flex justify-between items-center transition border-b border-slate-600/50 last:border-0 w-full">
                                    <span>{{ item.item_name }}</span>
                                    <span class="text-slate-400 text-xs shadow-sm bg-slate-800 px-2 py-0.5 rounded">{{ item.qty }} in stock</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex-1 p-5 overflow-y-auto h-full">
                        <table v-if="form.items.length" class="w-full text-sm">
                            <thead class="text-xs text-slate-400 border-b border-slate-700">
                                <tr>
                                    <th class="text-left pb-2 font-medium">Item</th>
                                    <th class="text-right pb-2 font-medium w-24">Qty</th>
                                    <th class="text-right pb-2 font-medium w-32">Unit Cost</th>
                                    <th class="text-right pb-2 font-medium w-32">Total</th>
                                    <th class="pb-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <tr v-for="(item, index) in form.items" :key="index">
                                    <td class="py-3 text-white">{{ item.item_name }}</td>
                                    <td class="py-3 text-right">
                                        <input v-model.number="item.qty" type="number" min="1" class="w-20 bg-slate-700 text-white px-2 py-1.5 rounded border border-slate-600 text-right outline-none focus:border-emerald-500" />
                                    </td>
                                    <td class="py-3 text-right">
                                        <input v-model.number="item.cost" type="number" min="0" step="0.01" class="w-24 bg-slate-700 text-white px-2 py-1.5 rounded border border-slate-600 text-right outline-none focus:border-emerald-500" />
                                    </td>
                                    <td class="py-3 text-right text-emerald-400">{{ format(item.qty * item.cost) }}</td>
                                    <td class="py-3 text-right">
                                        <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-300 transition w-6 h-6 inline-flex items-center justify-center">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="h-full flex items-center justify-center text-slate-500 text-sm">
                            Search and select items to add to the PO.
                        </div>
                    </div>
                </div>

                <!-- Right: Meta -->
                <div class="w-80 space-y-4 flex-shrink-0">
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Vendor *</label>
                            <select v-model="form.vendor_id" required class="w-full bg-slate-700 text-white px-3 py-2.5 rounded-lg border border-slate-600 outline-none text-sm transition">
                                <option value="" disabled>Select Vendor</option>
                                <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }} - {{ v.company_name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Expected Date</label>
                            <input v-model="form.expected_date" type="date" class="w-full bg-slate-700 text-white px-3 py-2.5 rounded-lg border border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2" class="w-full bg-slate-700 text-white px-3 py-2.5 rounded-lg border border-slate-600 outline-none text-sm transition resize-none"></textarea>
                        </div>
                    </div>

                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Subtotal</span>
                            <span class="text-white font-medium">{{ format(subtotal) }}</span>
                        </div>
                        <div>
                            <label class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Shipping Cost</span>
                                <input v-model.number="form.shipping_cost" type="number" min="0" step="0.01" class="w-24 bg-slate-700 text-white px-2 py-1 text-right border border-slate-600 rounded text-sm outline-none" />
                            </label>
                        </div>
                        <div>
                            <label class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Discount</span>
                                <input v-model.number="form.discount" type="number" min="0" step="0.01" class="w-24 bg-slate-700 text-white px-2 py-1 text-right border border-slate-600 rounded text-sm outline-none" />
                            </label>
                        </div>
                        <div class="pt-3 flex items-center justify-between border-t border-slate-700 font-bold text-lg">
                            <span class="text-white">Total</span>
                            <span class="text-emerald-400">{{ format(grandTotal) }}</span>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing || !form.items.length" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-900/20 transition disabled:opacity-40">
                        {{ form.processing ? 'Saving...' : 'Create Order' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
