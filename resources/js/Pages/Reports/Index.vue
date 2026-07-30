<script setup>
import { Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const reports = [
    {
        title: 'End of Day',
        description: "Summary of today's sales, payment modes, and register closeout.",
        route: 'pos.reports.end-of-day',
        color: 'emerald',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    {
        title: 'Sales',
        description: 'View sales transactions for a specific day or date range.',
        route: 'pos.reports.daily-sales',
        color: 'teal',
        icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
    },
    {
        title: 'Profit & Loss',
        description: 'Calculate gross profit based on items sold and their cost.',
        route: 'pos.reports.profit-loss',
        color: 'blue',
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
    },
    {
        title: 'Low Stock Alerts',
        description: 'Items that have fallen below their configured alert threshold.',
        route: 'pos.reports.low-stock',
        color: 'amber',
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
    },
    {
        title: 'Customer Debts',
        description: 'List of customers with outstanding balances.',
        route: 'pos.reports.customer-debt',
        color: 'red',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    }
]
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">Reports & Analytics</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <Link v-for="report in reports" :key="report.title" :href="route(report.route, report.params || {})" class="group bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl p-6 transition flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 transition-transform group-hover:scale-110" :class="`bg-${report.color}-500/20 text-${report.color}-400`">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="report.icon"/></svg>
                    </div>
                    <div>
                        <h2 class="text-white font-bold text-lg mb-1 group-hover:text-emerald-400 transition">{{ report.title }}</h2>
                        <p class="text-slate-400 text-sm leading-relaxed">{{ report.description }}</p>
                    </div>
                </Link>

            </div>
        </div>
    </div>
</template>
