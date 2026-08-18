<script setup>
import { ref, nextTick } from 'vue'
import axios from 'axios'
import { useCurrency } from '@/Composables/useCurrency'
import { searchLocalItems } from '@/Services/offlineDb'

const props = defineProps({
    purchaseType: { type: String, default: 'Consumer' },
    settings:     { type: Object, required: true },
})
const emit = defineEmits(['select'])

const { format } = useCurrency()
const searchInput = ref(null)   // template ref for reliable focus
const query       = ref('')
const results     = ref([])
const loading     = ref(false)
let   debounce    = null
let   lastInput   = 0

function refocusSearch() {
    nextTick(() => {
        searchInput.value?.focus()
    })
}

function onBlur() {
    setTimeout(() => {
        const active = document.activeElement
        if (!active || (active.tagName !== 'INPUT' && active.tagName !== 'TEXTAREA' && active.tagName !== 'SELECT' && active.tagName !== 'BUTTON')) {
            refocusSearch()
        }
    }, 120)
}

function onInput(e) {
    const val = e.target.value
    query.value = val
    clearTimeout(debounce)
    if (!val) { results.value = []; return }
    debounce = setTimeout(() => doSearch(val), 300)
}

async function onEnter() {
    clearTimeout(debounce)
    if (results.value.length > 0) {
        selectItem(results.value[0])
        return
    }
    if (query.value.trim()) {
        await doSearch(query.value.trim(), true)
    }
}

async function doSearch(q, autoSelectFirst = false) {
    if (!q) { results.value = []; return }
    loading.value = true
    try {
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            const localResults = await searchLocalItems(q, props.purchaseType)
            results.value = localResults
            if (autoSelectFirst && localResults.length > 0) {
                selectItem(localResults[0])
            }
            return
        }

        const res = await axios.get(route('pos.api.items.search'), {
            params: { q, purchase_type: props.purchaseType }
        })
        results.value = res.data
        if (autoSelectFirst && res.data.length > 0) {
            selectItem(res.data[0])
        }
    } catch (err) {
        console.warn('Network search unavailable, falling back to local storage search:', err)
        try {
            const localResults = await searchLocalItems(q, props.purchaseType)
            results.value = localResults
            if (autoSelectFirst && localResults.length > 0) {
                selectItem(localResults[0])
            }
        } catch (dbErr) {
            console.error('Local IndexedDB fallback error:', dbErr)
        }
    } finally {
        loading.value = false
    }
}

function selectItem(item) {
    emit('select', item)
    query.value   = ''
    results.value = []
    refocusSearch()
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

function getItemQty(item) {
    if (!item) return 0
    if (item.qty !== undefined && item.qty !== null) return Number(item.qty)
    if (item.front_store_qty !== undefined && item.front_store_qty !== null) return Number(item.front_store_qty)
    if (item.total_qty !== undefined && item.total_qty !== null) return Number(item.total_qty)
    return 0
}

// Allow parent to programmatically focus the search bar
defineExpose({ focus: refocusSearch })
</script>

<template>
    <div class="relative">
        <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-700 rounded-lg px-3 py-2 border border-slate-200 dark:border-slate-600 transition-colors">
            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="pos-search"
                ref="searchInput"
                v-model="query"
                @input="onInput"
                @blur="onBlur"
                @keydown.enter="onEnter"
                @keydown.escape="results = []; query = ''"
                type="text"
                placeholder="Search item name or scan barcode…"
                class="flex-1 bg-transparent text-slate-900 dark:text-white placeholder-slate-400 text-sm outline-none"
                autocomplete="off"
            />
            <span v-if="loading" class="text-slate-500 dark:text-slate-400 text-xs">Searching…</span>
        </div>

        <!-- Dropdown Results -->
        <div v-if="results.length" class="absolute top-full left-0 right-0 z-50 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-2xl max-h-72 overflow-y-auto">
            <button
                v-for="item in results"
                :key="item.id"
                @click="selectItem(item)"
                class="w-full flex items-center justify-between px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 transition text-left border-b border-slate-100 dark:border-slate-700/50 last:border-0"
            >
                <div class="flex-1 min-w-0 flex items-center gap-2.5">
                    <div v-if="item.image_url" class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-750 overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-650 flex-shrink-0">
                        <img :src="item.image_url" class="object-cover w-full h-full" alt="Item Thumbnail" />
                    </div>
                    <div class="truncate">
                        <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ item.item_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ item.barcode_number }} · {{ item.category?.name }}
                            <span v-if="settings.is_check_expiration && item.expiry_date" class="text-amber-600 dark:text-amber-400 font-medium ml-1.5">
                                (Exp: {{ formatDate(item.expiry_date) }})
                            </span>
                        </p>
                    </div>
                </div>
                <div class="ml-3 text-right flex-shrink-0">
                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ format(item.display_price) }}</p>
                    <p v-if="settings.is_show_buy_price" class="text-[10px] text-slate-500 dark:text-slate-400 font-medium mt-0.5">
                        Cost: {{ format(item.buy_price) }}
                    </p>
                    <div class="flex items-center gap-1 justify-end mt-1">
                        <span
                            v-if="getItemQty(item) <= (settings.out_of_stock ?? 25)"
                            class="text-[10px] bg-red-500/20 text-red-600 dark:text-red-400 px-1.5 py-0.5 rounded font-semibold"
                        >Low Stock: {{ getItemQty(item) }}</span>
                        <span
                            v-else
                            class="text-[10px] text-slate-500 dark:text-slate-400 font-medium"
                        >Qty: {{ getItemQty(item) }}</span>
                        <span v-if="isExpired(item)"  class="text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded">EXPIRED</span>
                        <span v-else-if="isExpiringSoon(item)" class="text-[10px] bg-amber-500/20 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 rounded">Exp Soon</span>
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>
