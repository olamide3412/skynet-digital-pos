<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/LaundryLayout.vue'

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    metrics: {
        type: Object,
        required: true,
    },
    status_breakdown: {
        type: Object,
        default: () => ({}),
    },
    methods_summary: {
        type: Array,
        default: () => [],
    },
    top_services: {
        type: Array,
        default: () => [],
    },
    overdue_orders: {
        type: Array,
        default: () => [],
    },
    unclaimed_orders: {
        type: Array,
        default: () => [],
    },
    outstanding_by_customer: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const selectedRange = ref(props.filters.range || 'this_month')
const customStart = ref(props.filters.start_date || '')
const customEnd = ref(props.filters.end_date || '')

const applyRange = (range) => {
    selectedRange.value = range
    router.get(
        safeRoute('laundry.reports.index'),
        {
            range: range,
            start_date: range === 'custom' ? customStart.value : undefined,
            end_date: range === 'custom' ? customEnd.value : undefined,
        },
        { preserveState: true }
    )
}
</script>

<template>
    <LaundryLayout title="Laundry Reports & Metrics">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Filter Period Controls -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">
                        Performance & Revenue Analytics
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Period: <span class="font-bold text-slate-700 dark:text-slate-300">{{ filters.start_date }}</span> to <span class="font-bold text-slate-700 dark:text-slate-300">{{ filters.end_date }}</span>
                    </p>
                </div>

                <!-- Range Buttons -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="r in [
                            { key: 'today', label: 'Today' },
                            { key: 'yesterday', label: 'Yesterday' },
                            { key: 'this_week', label: 'This Week' },
                            { key: 'this_month', label: 'This Month' },
                            { key: 'last_month', label: 'Last Month' }
                        ]"
                        :key="r.key"
                        @click="applyRange(r.key)"
                        :class="[
                            'px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                            selectedRange === r.key
                                ? 'bg-indigo-600 text-white shadow-xs'
                                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                        ]"
                    >
                        {{ r.label }}
                    </button>
                </div>
            </div>

            <!-- Key Metric Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <span class="text-xs font-semibold text-slate-500">Orders Created</span>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ metrics.orders_count }}</p>
                    <span class="text-[11px] text-slate-400">Gross Value: {{ formatMoney(metrics.orders_value) }}</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Net Collections</span>
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ formatMoney(metrics.net_collections) }}</p>
                    <span class="text-[11px] text-slate-400">Gross: {{ formatMoney(metrics.gross_collections) }}</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">Total Unpaid Balances</span>
                    <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ formatMoney(metrics.outstanding_debt) }}</p>
                    <span class="text-[11px] text-slate-400">Active Customer Receivables</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                    <span class="text-xs font-semibold text-rose-600">Overdue / Unclaimed</span>
                    <p class="text-2xl font-black text-rose-600 mt-1">{{ metrics.overdue_count }} / {{ metrics.unclaimed_count }}</p>
                    <span class="text-[11px] text-slate-400">Require Follow-up</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- 1. Collections by Payment Method (6 Cols) -->
                <div class="lg:col-span-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                        Collections by Payment Method
                    </h3>

                    <div v-if="methods_summary.length === 0" class="py-8 text-center text-xs text-slate-400">
                        No payments recorded in this period.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-4 py-2.5">Method</th>
                                    <th class="px-4 py-2.5 text-right">Payments</th>
                                    <th class="px-4 py-2.5 text-right">Refunds</th>
                                    <th class="px-4 py-2.5 text-right font-black">Net Cash</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="m in methods_summary" :key="m.method">
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ m.method }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 font-semibold">{{ formatMoney(m.payments) }}</td>
                                    <td class="px-4 py-3 text-right text-rose-600 font-semibold">{{ formatMoney(m.refunds) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">{{ formatMoney(m.net) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. Top Services Processed (6 Cols) -->
                <div class="lg:col-span-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                        Top Performing Services
                    </h3>

                    <div v-if="top_services.length === 0" class="py-8 text-center text-xs text-slate-400">
                        No services data available for this range.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-4 py-2.5">Service</th>
                                    <th class="px-4 py-2.5 text-center">Volume</th>
                                    <th class="px-4 py-2.5 text-right font-black">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="srv in top_services" :key="srv.service_name">
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ srv.service_name }}</td>
                                    <td class="px-4 py-3 text-center text-slate-500">
                                        {{ srv.total_qty }} {{ srv.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-indigo-600 dark:text-indigo-400">
                                        {{ formatMoney(srv.total_revenue) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Outstanding Balances by Customer (12 Cols) -->
                <div class="lg:col-span-12 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                        Outstanding Debt by Customer
                    </h3>

                    <div v-if="outstanding_by_customer.length === 0" class="py-8 text-center text-xs text-slate-400">
                        No customers with unpaid laundry balances.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-5 py-3">Customer Name</th>
                                    <th class="px-5 py-3">Phone</th>
                                    <th class="px-5 py-3 text-center">Unpaid Orders</th>
                                    <th class="px-5 py-3 text-right font-black">Total Amount Owed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="cust in outstanding_by_customer" :key="cust.customer_id">
                                    <td class="px-5 py-3 font-bold text-slate-900 dark:text-white">{{ cust.customer_name }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ cust.customer_phone }}</td>
                                    <td class="px-5 py-3 text-center font-bold text-amber-600">{{ cust.orders_count }}</td>
                                    <td class="px-5 py-3 text-right font-black text-rose-600">{{ formatMoney(cust.total_balance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </LaundryLayout>
</template>
