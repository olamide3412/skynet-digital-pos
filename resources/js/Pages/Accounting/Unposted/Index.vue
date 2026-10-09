<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    unpostedSales: Array,
    unpostedExpenses: Array,
    unpostedPurchases: Array,
    accountingActive: Boolean,
    startDate: String,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const { format } = useCurrency()

const activeTab = ref('sales')
const retryingId = ref(null)

function retry(type, id) {
    retryingId.value = `${type}_${id}`
    router.post(route('pos.accounting.unposted.retry', { branch: branchSlug.value }), {
        type,
        id
    }, {
        onFinish: () => retryingId.value = null
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Unposted Transactions Screen</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Transactions recorded on or after books activation ({{ startDate }}) pending ledger posting
                </p>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 px-6 pt-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex-shrink-0">
            <button
                @click="activeTab = 'sales'"
                class="px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2"
                :class="activeTab === 'sales' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                Sales ({{ unpostedSales?.length || 0 }})
            </button>
            <button
                @click="activeTab = 'expenses'"
                class="px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2"
                :class="activeTab === 'expenses' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                Expenses ({{ unpostedExpenses?.length || 0 }})
            </button>
            <button
                @click="activeTab = 'purchases'"
                class="px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2"
                :class="activeTab === 'purchases' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                Purchase Orders ({{ unpostedPurchases?.length || 0 }})
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6">
            <!-- Sales Tab -->
            <div v-if="activeTab === 'sales'" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/80 text-xs font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Receipt ID</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Payment Method</th>
                            <th class="px-4 py-3 text-right">Total Amount</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="s in unpostedSales" :key="s.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-slate-900 dark:text-white">{{ s.receipt_id }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ s.created_at ? new Date(s.created_at).toLocaleString() : '-' }}</td>
                            <td class="px-4 py-3 text-xs capitalize text-slate-700 dark:text-slate-300">{{ s.payment_method }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">{{ format(s.final_total) }}</td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    @click="retry('sale', s.id)"
                                    :disabled="retryingId === `sale_${s.id}`"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-50"
                                >
                                    {{ retryingId === `sale_${s.id}` ? 'Posting...' : 'Retry Posting' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!unpostedSales?.length">
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400">All sales are up to date and posted to double-entry ledger!</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Expenses Tab -->
            <div v-if="activeTab === 'expenses'" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/80 text-xs font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="e in unpostedExpenses" :key="e.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ e.category?.name || 'Expense' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ e.expense_date }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ e.description || '-' }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">{{ format(e.amount) }}</td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    @click="retry('expense', e.id)"
                                    :disabled="retryingId === `expense_${e.id}`"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-50"
                                >
                                    {{ retryingId === `expense_${e.id}` ? 'Posting...' : 'Retry Posting' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!unpostedExpenses?.length">
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400">All expenses are up to date and posted to double-entry ledger!</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Purchases Tab -->
            <div v-if="activeTab === 'purchases'" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/80 text-xs font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left">PO Number</th>
                            <th class="px-4 py-3 text-left">Vendor</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="p in unpostedPurchases" :key="p.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-slate-900 dark:text-white">{{ p.po_number }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-300">{{ p.vendor?.name || 'Vendor' }}</td>
                            <td class="px-4 py-3 text-xs font-semibold text-amber-600 dark:text-amber-400">{{ p.status }}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">{{ format(p.total_amount) }}</td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    @click="retry('purchase', p.id)"
                                    :disabled="retryingId === `purchase_${p.id}`"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-50"
                                >
                                    {{ retryingId === `purchase_${p.id}` ? 'Posting...' : 'Retry Posting' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!unpostedPurchases?.length">
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400">All received purchase orders are up to date!</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
