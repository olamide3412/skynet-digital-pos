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
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Profit & Loss Report</h1>
        </div>

        <div class="px-6 py-3 border-b border-slate-700 flex items-end gap-4 flex-shrink-0 bg-slate-800/50">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Start Date</label>
                <input v-model="startDate" type="date" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">End Date</label>
                <input v-model="endDate" type="date" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
            </div>
            <button @click="doFilter" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition font-medium">Filter</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 flex flex-col items-center pt-10">
            
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-8 max-w-xl w-full shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-bl-full blur-2xl"></div>
                
                <h2 class="text-center font-bold text-slate-300 mb-8 tracking-widest uppercase text-sm">Income Statement</h2>

                <div class="space-y-6">
                    <div class="flex justify-between items-center text-lg">
                        <span class="text-slate-400">Total Sales Revenue</span>
                        <span class="text-white font-medium">{{ format(summary.total_revenue) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-lg border-b border-slate-700/50 pb-4">
                        <span class="text-slate-400">Cost of Goods Sold (COGS)</span>
                        <span class="text-red-400 font-medium">-{{ format(summary.total_cost) }}</span>
                    </div>
                    
                    <div class="flex justify-between items-center text-2xl pt-2 font-bold">
                        <span class="text-white">Gross Profit</span>
                        <span :class="summary.gross_profit >= 0 ? 'text-emerald-400' : 'text-red-500'">
                            {{ format(summary.gross_profit) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center bg-slate-900/50 rounded-lg p-4 mt-6 border border-slate-700/50">
                        <span class="text-slate-400 font-medium">Profit Margin</span>
                        <span class="font-bold text-xl" :class="summary.profit_margin >= 0 ? 'text-blue-400' : 'text-red-400'">
                            {{ summary.profit_margin }}%
                        </span>
                    </div>
                </div>

                <p class="text-center text-xs text-slate-500 mt-8">Note: This report calculates gross profit based strictly on Item Cost vs. Item Sold Price. Fixed operating expenses are not included.</p>
            </div>

        </div>
    </div>
</template>
