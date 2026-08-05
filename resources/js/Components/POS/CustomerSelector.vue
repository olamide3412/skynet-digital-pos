<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    customer: { type: Object, default: null },
})
const emit    = defineEmits(['select', 'clear'])
const query   = ref('')
const results = ref([])
const loading = ref(false)
let   debounce = null

async function search(val) {
    if (!val) { results.value = []; return }
    loading.value = true
    try {
        const res = await axios.get(route('pos.api.customers.search'), { params: { q: val } })
        results.value = res.data
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
        <!-- Selected Customer -->
        <div v-if="customer" class="flex items-center justify-between bg-blue-50 dark:bg-blue-600/20 border border-blue-200 dark:border-blue-500/30 rounded-lg px-3 py-1.5">
            <div>
                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ customer.name }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ customer.phone }}
                    <span v-if="customer.debt_bal > 0" class="text-red-600 dark:text-red-400 ml-1">· Debt: ₦{{ parseFloat(customer.debt_bal).toLocaleString() }}</span>
                </p>
            </div>
            <button @click="emit('clear')" class="text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition text-xs ml-2 font-medium">✕ Remove</button>
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

        <!-- Dropdown -->
        <div v-if="results.length"
            class="absolute top-full left-0 right-0 z-40 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl max-h-48 overflow-y-auto">
            <button v-for="c in results" :key="c.id"
                @click="pick(c)"
                class="w-full flex items-center justify-between px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 border-b border-slate-100 dark:border-slate-700/50 last:border-0 transition text-left">
                <div>
                    <p class="text-sm text-slate-900 dark:text-white font-medium">{{ c.name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ c.phone }}</p>
                </div>
                <span v-if="c.debt_bal > 0" class="text-xs text-red-600 dark:text-red-400 font-medium">Debt: ₦{{ parseFloat(c.debt_bal).toLocaleString() }}</span>
            </button>
        </div>
    </div>
</template>
