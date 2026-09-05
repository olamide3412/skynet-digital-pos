<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { searchLocalCustomers } from '@/Services/offlineDb'
import { useOfflineSync } from '@/Composables/useOfflineSync'
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    customer: { type: Object, default: null },
})
const emit    = defineEmits(['select', 'clear'])
const { isOnline } = useOfflineSync()
const { format }   = useCurrency()
const query   = ref('')
const results = ref([])
const loading = ref(false)
let   debounce = null

async function search(val) {
    if (!val) { results.value = []; return }
    loading.value = true

    // 1. Instant local customer search (0ms)
    let localCusts = []
    try {
        localCusts = await searchLocalCustomers(val)
    } catch (e) {}

    const isDeviceOffline = !isOnline.value || (typeof navigator !== 'undefined' && !navigator.onLine)
    if (isDeviceOffline || localCusts.length > 0) {
        results.value = localCusts
        if (isDeviceOffline) {
            loading.value = false
            return
        }
    }

    try {
        const res = await axios.get(route('pos.api.customers.search'), {
            params: { q: val },
            timeout: 2500,
        })
        if (res.data && Array.isArray(res.data)) {
            results.value = res.data
        }
    } catch (e) {
        if (localCusts.length > 0) results.value = localCusts
    } finally { loading.value = false }
}

function onInput() {
    clearTimeout(debounce)
    debounce = setTimeout(() => search(query.value), 300)
}

function pick(c) {
    emit('select', c)
    query.value   = ''
    results.value = []
}
</script>

<template>
    <div class="relative">
        <!-- Selected Customer Card -->
        <div v-if="customer" class="flex items-center justify-between bg-blue-50 dark:bg-blue-600/20 border border-blue-200 dark:border-blue-500/30 rounded-lg px-3 py-1.5">
            <div class="flex-1 min-w-0 pr-2">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ customer.name }}</p>
                    <span v-if="customer.watch_list" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 flex-shrink-0">
                        ⚠️ Watchlist
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                    {{ customer.phone }}
                    <span v-if="Number(customer.debt_bal) > 0" class="text-red-600 dark:text-red-400 font-semibold ml-1.5">
                        · Owing: {{ format(customer.debt_bal) }}
                    </span>
                </p>
            </div>
            <button
                type="button"
                @click="emit('clear')"
                class="text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition text-xs font-medium px-1.5 py-0.5 rounded hover:bg-red-50 dark:hover:bg-red-950/40 cursor-pointer"
            >
                ✕ Remove
            </button>
        </div>

        <!-- Search Input -->
        <div v-else class="flex items-center gap-2 bg-slate-100 dark:bg-slate-700 rounded-lg px-3 py-1.5 border border-slate-200 dark:border-slate-600 transition-colors">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <input
                v-model="query"
                @input="onInput"
                @keydown.escape="results = []; query = ''"
                type="text"
                placeholder="Search customer (optional)…"
                class="flex-1 bg-transparent text-slate-900 dark:text-white placeholder-slate-400 text-xs outline-none"
            />
            <span v-if="loading" class="text-slate-500 dark:text-slate-400 text-xs">…</span>
        </div>

        <!-- Dropdown Search Results -->
        <div v-if="results.length"
            class="absolute top-full left-0 right-0 z-40 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl max-h-56 overflow-y-auto">
            <button v-for="c in results" :key="c.id"
                type="button"
                @click="pick(c)"
                class="w-full flex items-center justify-between px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/60 border-b border-slate-100 dark:border-slate-700/50 last:border-0 transition text-left cursor-pointer">
                <!-- Customer Name & Phone -->
                <div class="flex-1 min-w-0 pr-2">
                    <div class="flex items-center gap-1.5">
                        <p class="text-sm text-slate-900 dark:text-white font-medium truncate">{{ c.name }}</p>
                        <span v-if="c.watch_list" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 flex-shrink-0">
                            ⚠️ Flagged
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ c.phone }}</p>
                </div>

                <!-- Outstanding Debt Display (In Red, Bit Smaller Than Name) -->
                <div v-if="Number(c.debt_bal) > 0" class="text-right flex-shrink-0 pl-2">
                    <span class="text-xs text-red-600 dark:text-red-400 font-semibold font-mono bg-red-50 dark:bg-red-950/50 px-2 py-0.5 rounded border border-red-200 dark:border-red-900/60 inline-block">
                        Owing: {{ format(c.debt_bal) }}
                    </span>
                </div>
            </button>
        </div>
    </div>
</template>
