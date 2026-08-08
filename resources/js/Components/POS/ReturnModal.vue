<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useCurrency } from '@/Composables/useCurrency'

const emit = defineEmits(['close'])
const { format } = useCurrency()

const activeTab = ref('receipt') // 'receipt' | 'direct'

// Receipt Search Mode
const receiptQuery = ref('')
const sale         = ref(null)
const saleLoading  = ref(false)
const saleError    = ref('')
const returnItems  = ref([])

// Direct Item Return Mode (No Receipt)
const itemQuery       = ref('')
const itemSearchResults = ref([])
const itemSearchLoading = ref(false)
const directReturnItems = ref([])

const submitting   = ref(false)
const success      = ref('')

// Helpers for Unit Conversions & Pricing
function getUnitsPerPack(item) {
    return Math.max(1, Number(item?.units_per_pack || 1))
}

function getPacksPerCarton(item) {
    return Math.max(1, Number(item?.packs_per_carton || 1))
}

function getUnitsPerUnitLevel(item, unitUsed = 'unit') {
    if (!item) return 1
    const u = String(unitUsed).toLowerCase()
    if (u === 'carton') return getPacksPerCarton(item) * getUnitsPerPack(item)
    if (u === 'pack') return getUnitsPerPack(item)
    return 1
}

function getUnitPrice(item, unitUsed = 'unit') {
    if (!item) return 0
    const u = String(unitUsed).toLowerCase()
    if (u === 'carton' && item.carton_price > 0) return Number(item.carton_price)
    if (u === 'pack' && item.pack_price > 0) return Number(item.pack_price)
    if (u === 'carton') return Number(item.price || 0) * getPacksPerCarton(item) * getUnitsPerPack(item)
    if (u === 'pack') return Number(item.price || 0) * getUnitsPerPack(item)
    return Number(item.price || 0)
}

function getBaseUnits(item, qty = 1, unitUsed = 'unit') {
    if (!item || !qty) return 0
    return Number(qty) * getUnitsPerUnitLevel(item, unitUsed)
}

function getUnitName(item, unitUsed = 'unit') {
    if (!item) return 'Unit'
    const u = String(unitUsed).toLowerCase()
    if (u === 'carton') return item.carton_label || 'Carton'
    if (u === 'pack') return item.pack_label || 'Pack'
    return item.unit_label || 'Unit'
}

function getMaxReturnQty(itemRow) {
    if (!itemRow || !itemRow.purchased_base_units) return itemRow?.max_qty || 1
    const multiplier = getUnitsPerUnitLevel(itemRow.item, itemRow.unit_used)
    return Math.floor(itemRow.purchased_base_units / multiplier) || 1
}

function getReceiptRefundAmount(itemRow) {
    if (!itemRow || !itemRow.qty) return 0
    const baseUnitsToReturn = getBaseUnits(itemRow.item, itemRow.qty, itemRow.unit_used)
    const baseUnitPrice = itemRow.purchased_base_units > 0
        ? (itemRow.total_selling_price / itemRow.purchased_base_units)
        : (itemRow.selling_price || 0)
    return baseUnitsToReturn * baseUnitPrice
}

function onUnitChange(itemRow) {
    if (!itemRow.qty || itemRow.qty <= 0) {
        itemRow.qty = 1
    }
}

function decrementQty(itemRow, min = 0) {
    if (itemRow.qty > min) {
        itemRow.qty--
    }
}

function incrementQty(itemRow, max = null) {
    if (max !== null && itemRow.qty >= max) return
    itemRow.qty++
}

// ── Search Receipt Function ──────────────────────────────────────────────────
async function findSale() {
    if (!receiptQuery.value.trim()) return
    saleLoading.value = true
    saleError.value   = ''
    sale.value        = null
    returnItems.value = []
    try {
        const res = await axios.get(route('pos.sales.search-receipt'), {
            params: { query: receiptQuery.value.trim() }
        })
        sale.value = res.data ?? null
        if (sale.value) {
            returnItems.value = (sale.value.sale_orders || []).map(o => {
                const itemModel = o.item || {}
                const purchasedBase = getBaseUnits(itemModel, o.qty, o.unit_used || 'unit')
                return {
                    item_id:               o.item_id,
                    item_name:             o.item_name,
                    max_qty:               o.qty,
                    purchased_base_units:  purchasedBase,
                    selling_price:         Number(o.selling_price || 0),
                    total_selling_price:   Number(o.total_selling_price || 0),
                    original_unit:         o.unit_used || 'unit',
                    unit_used:             o.unit_used || 'unit',
                    qty:                   0,
                    reason:                '',
                    item:                  itemModel,
                }
            })
        }
    } catch (e) {
        saleError.value = e.response?.data?.message || 'Sale receipt not found. Check the Receipt ID.'
    } finally {
        saleLoading.value = false
    }
}

// ── Search Direct Items Function ─────────────────────────────────────────────
let debounce = null
function onSearchItems() {
    clearTimeout(debounce)
    if (!itemQuery.value.trim()) {
        itemSearchResults.value = []
        return
    }
    debounce = setTimeout(async () => {
        itemSearchLoading.value = true
        try {
            const res = await axios.get(route('pos.api.items.search'), {
                params: { q: itemQuery.value.trim() }
            })
            itemSearchResults.value = res.data || []
        } catch (e) {
            console.error(e)
        } finally {
            itemSearchLoading.value = false
        }
    }, 250)
}

function addDirectItem(item) {
    const existing = directReturnItems.value.find(i => i.item_id === item.id && i.unit_used === 'unit')
    if (existing) {
        existing.qty += 1
    } else {
        directReturnItems.value.push({
            item_id:   item.id,
            item_name: item.item_name,
            unit_used: 'unit',
            qty:       1,
            reason:    '',
            item:      item,
        })
    }
    itemQuery.value = ''
    itemSearchResults.value = []
}

function removeDirectItem(index) {
    directReturnItems.value.splice(index, 1)
}

// ── Submit Return ─────────────────────────────────────────────────────────────
async function submitReturn() {
    let payload = {}

    if (activeTab.value === 'receipt') {
        const selected = returnItems.value.filter(i => i.qty > 0)
        if (!selected.length || !sale.value) return
        payload = {
            sale_id: sale.value.id,
            items:   selected.map(i => ({
                item_id:   i.item_id,
                qty:       i.qty,
                unit_used: i.unit_used,
                reason:    i.reason,
            })),
        }
    } else {
        const selected = directReturnItems.value.filter(i => i.qty > 0)
        if (!selected.length) return
        payload = {
            sale_id: null,
            items:   selected.map(i => ({
                item_id:   i.item_id,
                qty:       i.qty,
                unit_used: i.unit_used,
                reason:    i.reason,
            })),
        }
    }

    submitting.value = true
    saleError.value  = ''
    try {
        await axios.post(route('pos.sale-returns.store'), payload)
        success.value = 'Return processed successfully! Item stock has been returned to inventory.'
    } catch (e) {
        saleError.value = e.response?.data?.errors?.return || e.response?.data?.message || 'Return processing failed.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 dark:border-slate-700 transition-colors">
            
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-lg text-slate-900 dark:text-white">Process Return / Refund</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Return items in units, packs, or cartons with live stock conversion.</p>
                </div>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition text-lg">&times;</button>
            </div>

            <!-- Tabs -->
            <div v-if="!success" class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                <button type="button"
                    @click="activeTab = 'receipt'"
                    :class="activeTab === 'receipt' ? 'border-b-2 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    class="flex-1 py-2.5 text-xs font-semibold transition"
                >Return with Receipt</button>
                <button type="button"
                    @click="activeTab = 'direct'"
                    :class="activeTab === 'direct' ? 'border-b-2 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    class="flex-1 py-2.5 text-xs font-semibold transition"
                >Direct Return (No Receipt)</button>
            </div>

            <div class="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                <!-- Success Message -->
                <div v-if="success" class="bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 p-4 rounded-xl text-sm font-medium border border-emerald-200 dark:border-emerald-500/20 text-center space-y-3">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto text-emerald-600 dark:text-emerald-400 text-xl font-bold">✓</div>
                    <p>{{ success }}</p>
                    <button type="button" @click="emit('close')" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-500 transition shadow-xs">Done</button>
                </div>

                <div v-if="!success">
                    <p v-if="saleError" class="text-red-600 dark:text-red-400 text-xs bg-red-50 dark:bg-red-400/10 p-3 rounded-lg border border-red-200 dark:border-red-500/20 font-medium mb-3">{{ saleError }}</p>

                    <!-- TAB 1: Return with Receipt -->
                    <template v-if="activeTab === 'receipt'">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Receipt ID / Number</label>
                            <div class="flex gap-2">
                                <input v-model="receiptQuery" @keydown.enter="findSale" type="text"
                                    placeholder="e.g. RC202608070001 or 1"
                                    class="flex-1 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 text-sm transition" />
                                <button type="button" @click="findSale" :disabled="saleLoading"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition disabled:opacity-40 flex items-center gap-1.5">
                                    <span v-if="saleLoading">Searching…</span>
                                    <span v-else>Search Sale</span>
                                </button>
                            </div>
                        </div>

                        <!-- Sale Details & Return Items -->
                        <div v-if="sale" class="space-y-3 mt-4">
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/40 rounded-lg text-xs space-y-1 border border-slate-200 dark:border-slate-700">
                                <div class="flex justify-between font-medium">
                                    <span class="text-slate-500 dark:text-slate-400">Receipt:</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ sale.receipt_id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Customer:</span>
                                    <span class="text-slate-900 dark:text-white font-medium">{{ sale.customer?.name || 'Walk-in Customer' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Total Amount:</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ format(sale.final_total) }}</span>
                                </div>
                            </div>

                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Select items & quantities to return:</p>
                            <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                                <div v-for="item in returnItems" :key="item.item_id"
                                    class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ item.item_name }}</p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                                Purchased: {{ item.max_qty }} {{ getUnitName(item.item, item.original_unit) }}
                                                <span v-if="item.purchased_base_units > 1 && item.original_unit !== 'unit'" class="font-semibold text-slate-600 dark:text-slate-300"> ({{ item.purchased_base_units }} Base Units)</span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <!-- Unit Level Selection -->
                                            <select v-model="item.unit_used" @change="onUnitChange(item)" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs px-2 py-1 rounded border border-slate-300 dark:border-slate-600 outline-none font-medium">
                                                <option value="unit">{{ item.item?.unit_label || 'Unit' }} (1 unit)</option>
                                                <option value="pack">{{ item.item?.pack_label || 'Pack' }} ({{ getUnitsPerPack(item.item) }} units/pack)</option>
                                                <option value="carton">{{ item.item?.carton_label || 'Carton' }} ({{ getPacksPerCarton(item.item) * getUnitsPerPack(item.item) }} units/carton)</option>
                                            </select>
                                            
                                            <!-- Clean Stepper Controls without ugly browser arrows -->
                                            <div class="flex items-center rounded-lg overflow-hidden border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-2xs">
                                                <button type="button" @click="decrementQty(item, 0)" 
                                                    class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition font-bold text-xs select-none">
                                                    −
                                                </button>
                                                <input v-model.number="item.qty" type="number" :min="0" :max="getMaxReturnQty(item)"
                                                    class="w-10 bg-transparent text-slate-900 dark:text-white text-center text-xs font-bold outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                                <button type="button" @click="incrementQty(item, getMaxReturnQty(item))" 
                                                    class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition font-bold text-xs select-none">
                                                    +
                                                </button>
                                            </div>

                                            <input v-if="item.qty > 0" v-model="item.reason" type="text" placeholder="Reason"
                                                class="w-24 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs px-2 py-1 rounded border border-slate-300 dark:border-slate-600 outline-none" />
                                        </div>
                                    </div>

                                    <!-- Helper Conversion Banner -->
                                    <div v-if="item.qty > 0" class="flex flex-col gap-0.5 text-xs bg-emerald-50 dark:bg-emerald-500/10 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-500/20 font-medium">
                                        <div class="flex justify-between items-center text-emerald-700 dark:text-emerald-300 font-bold">
                                            <span>Returning: {{ item.qty }} {{ getUnitName(item.item, item.unit_used) }}(s)</span>
                                            <span>Refund: {{ format(getReceiptRefundAmount(item)) }}</span>
                                        </div>
                                        <div class="flex justify-between items-center text-[11px] text-emerald-600 dark:text-emerald-400">
                                            <span>Conversion: {{ getUnitsPerUnitLevel(item.item, item.unit_used) }} units per {{ getUnitName(item.item, item.unit_used) }}</span>
                                            <span>Inventory Restock: <strong class="text-emerald-700 dark:text-emerald-300 font-bold font-mono">+{{ getBaseUnits(item.item, item.qty, item.unit_used) }} Base Units</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- TAB 2: Direct Return (No Receipt Needed) -->
                    <template v-else>
                        <div class="relative">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Search Item to Return (Name or Barcode)</label>
                            <input v-model="itemQuery" @input="onSearchItems" type="text"
                                placeholder="Type item name or scan barcode…"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 text-sm transition" />
                            
                            <!-- Search Results Dropdown -->
                            <div v-if="itemSearchResults.length" class="absolute top-full left-0 right-0 z-20 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl max-h-48 overflow-y-auto">
                                <button type="button" v-for="resItem in itemSearchResults" :key="resItem.id"
                                    @click="addDirectItem(resItem)"
                                    class="w-full text-left px-3 py-2 text-xs hover:bg-slate-100 dark:hover:bg-slate-700 flex justify-between items-center border-b border-slate-100 dark:border-slate-700/50 last:border-0">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ resItem.item_name }}</p>
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ resItem.barcode_number }} (Pack: {{ resItem.units_per_pack || 1 }} units)</p>
                                    </div>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ format(resItem.display_price || resItem.price) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Direct Return Items List -->
                        <div class="mt-4 space-y-3">
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Items for Direct Return:</p>
                            <div v-if="!directReturnItems.length" class="text-center py-6 text-slate-400 text-xs bg-slate-50 dark:bg-slate-700/20 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
                                Search and select items above to add to direct return list.
                            </div>
                            <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                                <div v-for="(item, idx) in directReturnItems" :key="item.item_id"
                                    class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ item.item_name }}</p>
                                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold font-mono">
                                                {{ format(getUnitPrice(item.item, item.unit_used)) }} / {{ getUnitName(item.item, item.unit_used) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <!-- Unit Selection -->
                                            <select v-model="item.unit_used" @change="onUnitChange(item)" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs px-2 py-1 rounded border border-slate-300 dark:border-slate-600 outline-none font-medium">
                                                <option value="unit">{{ item.item?.unit_label || 'Unit' }} (1 unit)</option>
                                                <option value="pack">{{ item.item?.pack_label || 'Pack' }} ({{ getUnitsPerPack(item.item) }} units/pack)</option>
                                                <option value="carton">{{ item.item?.carton_label || 'Carton' }} ({{ getPacksPerCarton(item.item) * getUnitsPerPack(item.item) }} units/carton)</option>
                                            </select>

                                            <!-- Clean Stepper Controls without ugly browser arrows -->
                                            <div class="flex items-center rounded-lg overflow-hidden border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-2xs">
                                                <button type="button" @click="decrementQty(item, 1)" 
                                                    class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition font-bold text-xs select-none">
                                                    −
                                                </button>
                                                <input v-model.number="item.qty" type="number" :min="1"
                                                    class="w-10 bg-transparent text-slate-900 dark:text-white text-center text-xs font-bold outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                                <button type="button" @click="incrementQty(item)" 
                                                    class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition font-bold text-xs select-none">
                                                    +
                                                </button>
                                            </div>

                                            <input v-model="item.reason" type="text" placeholder="Reason"
                                                class="w-24 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs px-2 py-1 rounded border border-slate-300 dark:border-slate-600 outline-none" />
                                            <button type="button" @click="removeDirectItem(idx)" class="text-red-500 hover:text-red-700 text-xs px-1 font-bold">&times;</button>
                                        </div>
                                    </div>

                                    <!-- Helper Conversion Banner -->
                                    <div v-if="item.qty > 0" class="flex flex-col gap-0.5 text-xs bg-emerald-50 dark:bg-emerald-500/10 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-500/20 font-medium">
                                        <div class="flex justify-between items-center text-emerald-700 dark:text-emerald-300 font-bold">
                                            <span>Returning: {{ item.qty }} {{ getUnitName(item.item, item.unit_used) }}(s)</span>
                                            <span>Refund: {{ format(item.qty * getUnitPrice(item.item, item.unit_used)) }}</span>
                                        </div>
                                        <div class="flex justify-between items-center text-[11px] text-emerald-600 dark:text-emerald-400">
                                            <span>Conversion: {{ getUnitsPerUnitLevel(item.item, item.unit_used) }} units per {{ getUnitName(item.item, item.unit_used) }}</span>
                                            <span>Inventory Restock: <strong class="text-emerald-700 dark:text-emerald-300 font-bold font-mono">+{{ getBaseUnits(item.item, item.qty, item.unit_used) }} Base Units</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Actions Footer -->
            <div v-if="!success" class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex gap-3 bg-slate-50 dark:bg-slate-800/80">
                <button type="button" @click="emit('close')" class="flex-1 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</button>
                
                <!-- Submit Receipt Return -->
                <button v-if="activeTab === 'receipt'" type="button" @click="submitReturn"
                    :disabled="submitting || !sale || !returnItems.some(i => i.qty > 0)"
                    class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-40 shadow-xs">
                    {{ submitting ? 'Processing…' : 'Process Receipt Return' }}
                </button>

                <!-- Submit Direct Return -->
                <button v-else type="button" @click="submitReturn"
                    :disabled="submitting || !directReturnItems.some(i => i.qty > 0)"
                    class="flex-1 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-40 shadow-xs">
                    {{ submitting ? 'Processing…' : 'Process Direct Return' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
}
input[type="number"] {
    -moz-appearance: textfield !important;
    appearance: textfield !important;
}
</style>
