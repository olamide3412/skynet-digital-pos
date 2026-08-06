<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    summary: Object,
    filters: Object,
})

const { format } = useCurrency()
const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

function doFilter() {
    router.get(route('pos.reports.profit-loss'), {
        start_date: startDate.value,
        end_date: endDate.value,
    }, { preserveState: true, replace: true })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Profit & Loss Report</h1>
        </div>

        <!-- Filter -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex items-end gap-4 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">Start Date</label>
                <input v-model="startDate" type="date" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-400 mb-1">End Date</label>
                <input v-model="endDate" type="date" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <button @click="doFilter" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md shadow-blue-900/20">Filter</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 flex flex-col items-center pt-10">
            
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-8 max-w-xl w-full shadow-lg relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-bl-full blur-2xl"></div>
                
                <h2 class="text-center font-bold text-slate-600 dark:text-slate-300 mb-8 tracking-widest uppercase text-xs">Income Statement Summary</h2>

                <div class="space-y-6">
                    <div class="flex justify-between items-center text-lg">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Total Sales Revenue</span>
                        <span class="text-slate-900 dark:text-white font-mono font-bold">{{ format(summary.total_revenue) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-lg border-b border-slate-200 dark:border-slate-700/50 pb-4">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Cost of Goods Sold (COGS)</span>
                        <span class="text-red-600 dark:text-red-400 font-mono font-bold">-{{ format(summary.total_cost) }}</span>
                    </div>
                    
                    <div class="flex justify-between items-center text-2xl pt-2 font-black">
                        <span class="text-slate-900 dark:text-white">Gross Profit</span>
                        <span :class="summary.gross_profit >= 0 ? 'text-emerald-600 dark:text-emerald-400 font-mono' : 'text-red-600 dark:text-red-500 font-mono'">
                            {{ format(summary.gross_profit) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 mt-6 border border-slate-200 dark:border-slate-700/50">
                        <span class="text-slate-700 dark:text-slate-400 font-semibold text-sm">Profit Margin</span>
                        <span class="font-bold text-xl font-mono" :class="summary.profit_margin >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400'">
                            {{ summary.profit_margin }}%
                        </span>
                    </div>
                </div>

                <p class="text-center text-xs text-slate-500 dark:text-slate-400 mt-8">Note: This report calculates gross profit based strictly on Item Cost vs. Item Sold Price. Fixed operating expenses are not included.</p>
            </div>

        </div>
    </div>
</template>
