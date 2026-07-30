<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    purchaseType: { type: String, default: 'Consumer' },
    settings:     { type: Object, required: true },
})
const emit = defineEmits(['select'])

const { format } = useCurrency()
const query      = ref('')
const results    = ref([])
const loading    = ref(false)
let   debounce   = null
let   lastInput  = 0

function onInput(e) {
    const val = e.target.value
    query.value = val
    clearTimeout(debounce)
    if (!val) { results.value = []; return }
    debounce = setTimeout(() => doSearch(val), 300)

    // Barcode scanner: rapid keystrokes ending in Enter
    lastInput = Date.now()
}

function onEnter() {
    const gap = Date.now() - lastInput
    if (gap < 100 && query.value.length > 3) {
        // Likely a hardware scanner
        doSearch(query.value, true)
    }
}

async function doSearch(q, exact = false) {
    loading.value = true
    try {
        const res = await axios.get('/pos/api/items/search', {
            params: { q, purchase_type: props.purchaseType }
        })
        results.value = res.data
        // Barcode exact match → auto-add
        if (exact && res.data.length === 1) {
            selectItem(res.data[0])
        }
    } finally {
        loading.value = false
    }
}

function selectItem(item) {
    emit('select', item)
    query.value   = ''
    results.value = []
    document.getElementById('pos-search')?.focus()
}

function isExpired(item) {
    if (!item.expiry_date) return false
    return new Date(item.expiry_date) < new Date()
}

function isExpiringSoon(item) {
    if (!item.expiry_date) return false
    const diff = (new Date(item.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
    return diff > 0 && diff <= 30
}

function formatDate(date) {
    if (!date) return ''
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric'
    })
}
</script>

<template>
    <div class="relative">
        <div class="flex items-center gap-2 bg-slate-700 rounded-lg px-3 py-2">
            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="pos-search"
                v-model="query"
                @input="onInput"
                @keydown.enter="onEnter"
                @keydown.escape="results = []; query = ''"
                type="text"
                placeholder="Search item name or scan barcode…"
                class="flex-1 bg-transparent text-white placeholder-slate-400 text-sm outline-none"
                autocomplete="off"
            />
            <span v-if="loading" class="text-slate-400 text-xs">Searching…</span>
        </div>

        <!-- Dropdown Results -->
        <div v-if="results.length" class="absolute top-full left-0 right-0 z-50 mt-1 bg-slate-800 border border-slate-600 rounded-lg shadow-2xl max-h-72 overflow-y-auto">
            <button
                v-for="item in results"
                :key="item.id"
                @click="selectItem(item)"
                class="w-full flex items-center justify-between px-3 py-2 hover:bg-slate-700 transition text-left border-b border-slate-700/50 last:border-0"
            >
                <div class="flex-1 min-w-0 flex items-center gap-2.5">
                    <div v-if="item.image_url" class="w-8 h-8 rounded bg-slate-750 overflow-hidden flex items-center justify-center border border-slate-650 flex-shrink-0">
                        <img :src="item.image_url" class="object-cover w-full h-full" alt="Item Thumbnail" />
                    </div>
                    <div class="truncate">
                        <p class="text-sm font-medium text-white truncate">{{ item.item_name }}</p>
                        <p class="text-xs text-slate-400">
                            {{ item.barcode_number }} · {{ item.category?.name }}
                            <span v-if="settings.is_check_expiration && item.expiry_date" class="text-amber-400 font-medium ml-1.5">
                                (Exp: {{ formatDate(item.expiry_date) }})
                            </span>
                        </p>
                    </div>
                </div>
                <div class="ml-3 text-right flex-shrink-0">
                    <p class="text-sm font-bold text-emerald-400">{{ format(item.display_price) }}</p>
                    <p v-if="settings.is_show_buy_price" class="text-[10px] text-slate-400 font-medium mt-0.5">
                        Cost: {{ format(item.buy_price) }}
                    </p>
                    <div class="flex items-center gap-1 justify-end mt-1">
                        <span
                            v-if="item.qty <= (settings.out_of_stock ?? 25)"
                            class="text-[10px] bg-red-500/20 text-red-400 px-1.5 py-0.5 rounded"
                        >Low Stock {{ item.qty }}</span>
                        <span
                            v-else
                            class="text-[10px] text-slate-500"
                        >Qty: {{ item.qty }}</span>
                        <span v-if="isExpired(item)"  class="text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded">EXPIRED</span>
                        <span v-else-if="isExpiringSoon(item)" class="text-[10px] bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded">Exp Soon</span>
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>
