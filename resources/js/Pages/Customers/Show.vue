<script setup>
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    customer: Object,
})

const { format } = useCurrency()

function destroyCustomer() {
    if (props.customer.debt_bal > 0) {
        alert(`Cannot delete customer "${props.customer.name}" because they have an outstanding debt balance of ${format(props.customer.debt_bal)}. Debt must be paid before deleting.`)
        return
    }
    if (confirm(`Are you sure you want to delete customer "${props.customer.name}"?`)) {
        router.delete(route('pos.customers.destroy', props.customer.id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.customers.index')" class="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ customer.name }}</h1>
                        <span v-if="customer.watch_list" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                            ⚠️ Watchlist Flag
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                        {{ customer.phone }}
                        <span v-if="customer.email" class="text-slate-400 font-sans ml-2">• {{ customer.email }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a :href="`https://wa.me/${(customer.whatsapp_number || customer.phone).replace(/[^0-9]/g, '')}`"
                    target="_blank"
                    class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold rounded-lg transition flex items-center gap-1.5">
                    <span>💬 WhatsApp</span>
                </a>
                <Link :href="route('pos.customers.debt-ledger', customer.id)"
                    class="px-3 py-1.5 bg-orange-50 dark:bg-orange-950/40 hover:bg-orange-100 dark:hover:bg-orange-900/50 text-orange-700 dark:text-orange-300 border border-orange-200 dark:border-orange-800 text-xs font-bold rounded-lg transition flex items-center gap-1.5">
                    <span>💳 Debt Ledger</span>
                    <span v-if="customer.debt_bal > 0" class="font-mono text-red-600 dark:text-red-400">({{ format(customer.debt_bal) }})</span>
                </Link>
                <Link :href="route('pos.customers.edit', customer.id)"
                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-xs">
                    ✏️ Edit Profile
                </Link>
                <button
                    @click="destroyCustomer"
                    :disabled="customer.debt_bal > 0"
                    :title="customer.debt_bal > 0 ? 'Cannot delete customer with outstanding debt' : 'Delete Customer'"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition border cursor-pointer"
                    :class="customer.debt_bal > 0 ? 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700 cursor-not-allowed opacity-50' : 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 border-red-200 dark:border-red-800'"
                >
                    Delete
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 max-w-5xl mx-auto w-full">
            
            <!-- Summary Stats Banner -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Outstanding Debt</div>
                    <div class="text-xl font-bold font-mono mt-1" :class="customer.debt_bal > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ customer.debt_bal > 0 ? format(customer.debt_bal) : '₦0.00 (Cleared)' }}
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Lifetime Sales Orders</div>
                    <div class="text-xl font-bold text-slate-900 dark:text-white font-mono mt-1">
                        {{ customer.sales?.length ?? 0 }} Orders
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Customer Since</div>
                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-2">
                        {{ new Date(customer.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                    </div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Info -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-3 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Contact & Demographics</h3>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Phone</span>
                            <span class="font-mono font-medium text-slate-900 dark:text-white">{{ customer.phone }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">WhatsApp</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-medium text-slate-900 dark:text-white">{{ customer.whatsapp_number || customer.phone }}</span>
                                <span v-if="!customer.whatsapp_number || customer.whatsapp_number === customer.phone" class="text-[10px] text-slate-400 font-sans">(Same as phone)</span>
                                <a :href="`https://wa.me/${(customer.whatsapp_number || customer.phone).replace(/[^0-9]/g, '')}`" target="_blank" class="text-emerald-600 hover:text-emerald-500 text-xs font-bold" title="Chat on WhatsApp">↗</a>
                            </div>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Email</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ customer.email || 'Not specified' }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Gender</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ customer.gender || 'Not specified' }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Date of Birth</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ customer.dob ? new Date(customer.dob).toLocaleDateString('en-GB') : 'Not specified' }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Address</span>
                            <span class="font-medium text-slate-900 dark:text-white text-right max-w-[240px]">{{ customer.address === 'NA' ? 'Not specified' : customer.address }}</span>
                        </div>
                        <div class="pt-1">
                            <span class="text-slate-500 dark:text-slate-400 text-xs block mb-1">Notes</span>
                            <p class="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700/60">
                                {{ customer.note === 'No Note' ? 'No notes recorded.' : customer.note }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact / Guarantor -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-3 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Guarantor / Emergency Contact</h3>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Contact Name</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ customer.contact_name === 'NA' ? 'None' : customer.contact_name }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Contact Phone</span>
                            <span class="font-mono font-medium text-slate-900 dark:text-white">{{ customer.contact_phone === 'NA' ? 'None' : customer.contact_phone }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">Contact Address</span>
                            <span class="font-medium text-slate-900 dark:text-white text-right max-w-[240px]">{{ customer.contact_address === 'NA' ? 'None' : customer.contact_address }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Sales History -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent Sales History</h3>
                    <span class="text-xs text-slate-500">{{ customer.sales?.length ?? 0 }} total orders</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="text-left px-4 py-2.5 font-semibold">Receipt #</th>
                                <th class="text-left px-4 py-2.5 font-semibold">Date</th>
                                <th class="text-left px-4 py-2.5 font-semibold">Payment</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Total</th>
                                <th class="text-right px-4 py-2.5 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="sale in (customer.sales ?? []).slice(0, 10)" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-4 py-2.5 font-mono font-semibold text-slate-900 dark:text-white">{{ sale.invoice_no }}</td>
                                <td class="px-4 py-2.5 text-slate-500">{{ new Date(sale.created_at).toLocaleString('en-GB') }}</td>
                                <td class="px-4 py-2.5">
                                    <span v-if="sale.is_debt" class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-400 border border-red-200 dark:border-red-800">
                                        Debt (Credit)
                                    </span>
                                    <span v-else-if="sale.is_split" class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                                        Split Payment
                                    </span>
                                    <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        Settled
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900 dark:text-white">{{ format(sale.final_total) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <Link :href="route('pos.sales.show', sale.id)" class="text-emerald-600 dark:text-emerald-400 hover:underline font-semibold">View Invoice</Link>
                                </td>
                            </tr>
                            <tr v-if="!customer.sales?.length">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No sales transactions recorded for this customer.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
