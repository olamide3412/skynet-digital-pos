<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    baseUrl: {
        type: String,
        required: true,
    },
    params: {
        type: Object,
        default: () => ({}),
    },
    label: {
        type: String,
        default: 'Export',
    },
    title: {
        type: String,
        default: 'Export',
    },
})

const isOpen = ref(false)
const dropdownRef = ref(null)

function buildUrl(format) {
    const searchParams = new URLSearchParams()
    searchParams.set('format', format)

    for (const [key, value] of Object.entries(props.params || {})) {
        if (value !== null && value !== undefined && value !== '') {
            searchParams.set(key, value)
        }
    }

    const separator = props.baseUrl.includes('?') ? '&' : '?'
    return `${props.baseUrl}${separator}${searchParams.toString()}`
}

function download(format) {
    isOpen.value = false
    const url = buildUrl(format)
    window.location.href = url
}

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="relative inline-block text-left" ref="dropdownRef">
        <button
            type="button"
            @click="isOpen = !isOpen"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition inline-flex items-center gap-1.5 shadow-xs cursor-pointer"
            :title="title"
        >
            <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>{{ label }}</span>
            <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            v-if="isOpen"
            class="absolute right-0 mt-1.5 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100"
        >
            <div class="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                {{ title }}
            </div>
            <button
                type="button"
                @click="download('xlsx')"
                class="w-full text-left px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-2 transition cursor-pointer font-medium"
            >
                <span class="w-5 h-5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px]">
                    XLS
                </span>
                Excel (.xlsx)
            </button>
            <button
                type="button"
                @click="download('csv')"
                class="w-full text-left px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center gap-2 transition cursor-pointer font-medium"
            >
                <span class="w-5 h-5 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-[10px]">
                    CSV
                </span>
                CSV (.csv)
            </button>
        </div>
    </div>
</template>
