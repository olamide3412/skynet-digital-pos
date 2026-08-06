<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({ customers: Object, filters: Object })
const { format } = useCurrency()
const search = ref(props.filters?.search ?? '')

function doSearch() {
    router.get(route('pos.customers.index'), { search: search.value }, { preserveState: true, replace: true })
}

function destroy(id, name) {
    if (confirm(`Delete customer "${name}"?`)) router.delete(route('pos.customers.destroy', id))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Customers</h1>
            <Link :href="route('pos.customers.create')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Customer
            </Link>
        </div>

        <!-- Search Bar -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex gap-2 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <input v-model="search" @keydown.enter="doSearch" type="text" placeholder="Search name or phone…"
                class="flex-1 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            <button @click="doSearch" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm font-semibold rounded-lg transition">Search</button>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Name</th>
                            <th class="text-left px-4 py-3 font-semibold">Phone</th>
                            <th class="text-left px-4 py-3 font-semibold">Gender</th>
                            <th class="text-right px-4 py-3 font-semibold">Debt Balance</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3">
                                <Link :href="route('pos.customers.show', customer.id)"
                                    class="text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 font-semibold transition">{{ customer.name }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs">{{ customer.phone }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ customer.gender ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">
                                <span :class="customer.debt_bal > 0 ? 'text-red-600 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400'">
                                    {{ customer.debt_bal > 0 ? format(customer.debt_bal) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <Link :href="route('pos.customers.debt-ledger', customer.id)"
                                        :class="['text-xs font-semibold transition', customer.debt_bal > 0 ? 'text-orange-600 dark:text-orange-400 hover:text-orange-500' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200']">
                                        Ledger{{ customer.debt_bal > 0 ? ' ●' : '' }}
                                    </Link>
                                    <Link :href="route('pos.customers.edit', customer.id)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition">Edit</Link>
                                    <button @click="destroy(customer.id, customer.name)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!customers.data.length">
                            <td colspan="5" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No customers found.</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="customers.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ customers.from }}–{{ customers.to }} of {{ customers.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="customers.prev_page_url" :href="customers.prev_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Prev</Link>
                        <Link v-if="customers.next_page_url" :href="customers.next_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
