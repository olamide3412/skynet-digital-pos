<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useCurrency } from '@/Composables/useCurrency'
import { searchLocalItems } from '@/Services/offlineDb'
import { useOfflineSync } from '@/Composables/useOfflineSync'

const props = defineProps({
    purchaseType: { type: String, default: 'Consumer' },
    settings:     { type: Object, required: true },
})
const emit = defineEmits(['select'])

const { format } = useCurrency()
const { isOnline } = useOfflineSync()

const rootContainer  = ref(null)
const searchInput    = ref(null)   // template ref for reliable focus
const query          = ref('')
const results        = ref([])
const loading        = ref(false)
const selectedIndex  = ref(-1)

let debounce               = null
let searchSeq              = 0
let activeAbortController  = null

function cancelPendingSearches() {
    clearTimeout(debounce)
    debounce = null
    if (activeAbortController) {
        try {
            activeAbortController.abort()
        } catch (e) {}
        activeAbortController = null
    }
    searchSeq++
    loading.value = false
}

function refocusSearch() {
    nextTick(() => {
        searchInput.value?.focus()
    })
}

function onInput(e) {
    const val = e.target.value
    query.value = val
    selectedIndex.value = -1

    cancelPendingSearches()

    if (!val || !val.trim()) {
        results.value = []
        return
    }

    const currentSeq = ++searchSeq

    // Instant local cache peek (0ms latency)
    searchLocalItems(val.trim(), props.purchaseType).then((local) => {
        if (currentSeq === searchSeq && query.value.trim() && local && local.length > 0) {
            results.value = local
        }
    }).catch(() => {})

    debounce = setTimeout(() => {
        doSearch(val.trim())
    }, 150)
}

function onKeyDown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        if (results.value.length > 0) {
            selectedIndex.value = (selectedIndex.value + 1) % results.value.length
            scrollToSelected()
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        if (results.value.length > 0) {
            selectedIndex.value = (selectedIndex.value - 1 + results.value.length) % results.value.length
            scrollToSelected()
        }
    } else if (e.key === 'Enter') {
        e.preventDefault()
        onEnter()
    } else if (e.key === 'Escape') {
        e.preventDefault()
        cancelPendingSearches()
        results.value = []
        query.value = ''
    }
}

function scrollToSelected() {
    nextTick(() => {
        const activeEl = rootContainer.value?.querySelector('[data-selected="true"]')
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' })
        }
    })
}

async function onEnter() {
    cancelPendingSearches()

    // If an item in the list is highlighted with Arrow keys
    if (selectedIndex.value >= 0 && results.value[selectedIndex.value]) {
        selectItem(results.value[selectedIndex.value])
        return
    }

    // If results are already loaded, select the first one
    if (results.value.length > 0) {
        selectItem(results.value[0])
        return
    }

    // If query is present but results not yet ready (e.g. fast barcode scan), do search and auto-select
    if (query.value.trim()) {
        await doSearch(query.value.trim(), true)
    }
}

async function doSearch(q, autoSelectFirst = false) {
    if (!q || !q.trim()) {
        results.value = []
        loading.value = false
        return
    }

    const thisSeq = ++searchSeq
    loading.value = true

    // 1. Instant local search from IndexedDB
    let localResults = []
    try {
        localResults = await searchLocalItems(q, props.purchaseType)
    } catch (e) {
        console.warn('Local search error:', e)
    }

    // If outdated request or user cleared input while waiting, discard
    if (thisSeq !== searchSeq || !query.value.trim()) {
        loading.value = false
        return
    }

    const isDeviceOffline = !isOnline.value || (typeof navigator !== 'undefined' && !navigator.onLine)
    if (isDeviceOffline || (props.settings?.is_offline_enabled && localResults.length > 0)) {
        if (thisSeq === searchSeq && query.value.trim()) {
            results.value = localResults
            if (autoSelectFirst && localResults.length > 0) {
                selectItem(localResults[0])
                loading.value = false
                return
            }
        }
        if (isDeviceOffline) {
            loading.value = false
            return
        }
    }

    // 2. Online Server Query with AbortController signal
    try {
        activeAbortController = new AbortController()
        const res = await axios.get(route('pos.api.items.search'), {
            params: { q, purchase_type: props.purchaseType },
            timeout: 2500,
            signal: activeAbortController.signal,
        })

        // Verify that this is still the active search query
        if (thisSeq === searchSeq && query.value.trim()) {
            if (res.data && Array.isArray(res.data)) {
                results.value = res.data
                selectedIndex.value = -1

                if (autoSelectFirst && res.data.length > 0) {
                    selectItem(res.data[0])
                    return
                }
            }
        }
    } catch (err) {
        if (axios.isCancel(err) || err.name === 'CanceledError' || err.name === 'AbortError') {
            // Request was cancelled cleanly
            return
        }
        // Fallback to localResults if server failed
        if (thisSeq === searchSeq && query.value.trim() && localResults.length > 0) {
            results.value = localResults
        }
    } finally {
        if (thisSeq === searchSeq) {
            loading.value = false
        }
    }
}

function selectItem(item) {
    if (!item) return

    // Immediately cancel and invalidate any pending searches or responses
    cancelPendingSearches()

    emit('select', item)
    query.value         = ''
    results.value       = []
    selectedIndex.value = -1

    refocusSearch()
}

// Click-outside listener to close dropdown
function handleDocumentClick(e) {
    if (rootContainer.value && !rootContainer.value.contains(e.target)) {
        results.value = []
        selectedIndex.value = -1
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick, true)
})

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick, true)
    cancelPendingSearches()
})

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
    <div ref="rootContainer" class="relative">
        <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-700 rounded-lg px-3 py-2 border border-slate-200 dark:border-slate-600 transition-colors">
            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="pos-search"
                ref="searchInput"
                v-model="query"
                @input="onInput"
                @keydown="onKeyDown"
                type="text"
                placeholder="Search item name or scan barcode…"
                class="flex-1 bg-transparent text-slate-900 dark:text-white placeholder-slate-400 text-sm outline-none"
                autocomplete="off"
            />
            <span v-if="loading" class="text-slate-500 dark:text-slate-400 text-xs">Searching…</span>
        </div>

        <!-- Dropdown Results -->
        <div
            v-if="results.length && query.trim()"
            class="absolute top-full left-0 right-0 z-50 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-2xl max-h-72 overflow-y-auto"
        >
            <button
                v-for="(item, idx) in results"
                :key="item.id"
                :data-selected="selectedIndex === idx"
                @mousedown.prevent="selectItem(item)"
                type="button"
                :class="[
                    'w-full flex items-center justify-between px-3 py-2 transition text-left border-b border-slate-100 dark:border-slate-700/50 last:border-0 cursor-pointer',
                    selectedIndex === idx
                        ? 'bg-emerald-50 dark:bg-slate-700 text-emerald-900 dark:text-emerald-300'
                        : 'hover:bg-slate-100 dark:hover:bg-slate-700/80 text-slate-900 dark:text-white'
                ]"
            >
                <div class="flex-1 min-w-0 flex items-center gap-2.5">
                    <div v-if="item.image_url" class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700 overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-600 flex-shrink-0">
                        <img :src="item.image_url" class="object-cover w-full h-full" alt="Item Thumbnail" />
                    </div>
                    <div class="truncate">
                        <p class="text-sm font-medium truncate">{{ item.item_name }}</p>
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
