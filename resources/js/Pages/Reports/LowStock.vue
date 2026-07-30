<script setup>
import { Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    items: Object,  // paginated
})
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Low Stock Alerts</h1>
            <span class="px-2 py-0.5 rounded-full bg-red-500/20 text-red-500 text-xs font-bold">
                {{ items.total ?? items.data?.length }} items
            </span>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden max-w-4xl mx-auto">
                <div class="px-5 py-4 border-b border-slate-700 bg-red-900/10 flex items-center gap-3 text-red-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span class="text-sm">Items with inventory at or below the alert threshold that need restocking.</span>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700 text-left">
                        <tr>
                            <th class="px-5 py-3 text-slate-400 font-medium">Barcode</th>
                            <th class="px-5 py-3 text-slate-400 font-medium">Item Name</th>
                            <th class="px-5 py-3 text-slate-400 font-medium text-center">Alert Level</th>
                            <th class="px-5 py-3 font-medium text-right text-red-400">Current Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-400 font-mono text-xs">{{ item.barcode_number || 'N/A' }}</td>
                            <td class="px-5 py-3 text-white">{{ item.item_name }}</td>
                            <td class="px-5 py-3 text-slate-400 text-center">{{ item.alert_quantity }}</td>
                            <td class="px-5 py-3 font-bold text-right" :class="item.qty <= 0 ? 'text-red-500' : 'text-amber-500'">
                                {{ item.qty }}
                            </td>
                        </tr>
                        <tr v-if="!items.data?.length">
                            <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-emerald-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>All items are sufficiently stocked.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <PaginationLinks :links="items.links" :meta="items.meta ?? items" />
            </div>
        </div>
    </div>
</template>
