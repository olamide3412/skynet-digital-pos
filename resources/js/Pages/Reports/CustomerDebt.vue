<script setup>
import { Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    customers:  Object,   // paginated
    total_debt: Number,
})

const { format } = useCurrency()
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.reports.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Customer Debts</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6 flex items-start justify-center">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden w-full max-w-4xl">
                <div class="px-5 py-5 border-b border-slate-700 flex justify-between items-center bg-slate-800">
                    <div>
                        <h2 class="font-bold text-white text-lg">Outstanding Balances</h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ customers.total }} customers with outstanding debt</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Total System Debt</p>
                        <p class="text-xl font-bold text-red-500">{{ format(total_debt) }}</p>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700 text-left">
                        <tr>
                            <th class="px-6 py-4 text-slate-400 font-medium">Customer Name</th>
                            <th class="px-6 py-4 text-slate-400 font-medium">Contact Details</th>
                            <th class="px-6 py-4 text-slate-400 font-medium text-right">Debt Amount</th>
                            <th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="c in customers.data" :key="c.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-6 py-4 text-white font-medium">{{ c.name }}</td>
                            <td class="px-6 py-4 text-slate-400">
                                <p>{{ c.phone }}</p>
                                <p v-if="c.email" class="text-xs">{{ c.email }}</p>
                            </td>
                            <td class="px-6 py-4 font-bold text-red-400 text-right text-lg">{{ format(c.debt_bal) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('pos.customers.debt-ledger', c.id)"
                                    class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-600 text-white text-xs rounded transition inline-flex">
                                    Ledger
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!customers.data?.length">
                            <td colspan="4" class="px-6 py-16 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>No outstanding customer debts!</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <PaginationLinks :links="customers.links" :meta="customers.meta ?? customers" />
            </div>
        </div>
    </div>
</template>
