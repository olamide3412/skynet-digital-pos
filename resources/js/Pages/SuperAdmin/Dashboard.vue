<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branchStats: Array,
    totalBranches: Number,
    activeBranches: Number,
    month: String,
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(amount || 0)
}

const toggleBranch = (branchSlug) => {
    router.post(route('superadmin.branches.toggle', branchSlug))
}
</script>

<template>
    <Head title="Super Admin Dashboard" />
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Page Title & Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Multi-Branch Overview</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Monthly branch sales metrics and control — {{ month }}</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Link
                    :href="route('superadmin.branches.index')"
                    class="flex-1 sm:flex-none text-center px-4 py-2 text-xs font-bold bg-theme hover:opacity-90 text-white rounded-xl transition shadow-md"
                >
                    Manage Branches
                </Link>
                <Link
                    :href="route('superadmin.global-items.index')"
                    class="flex-1 sm:flex-none text-center px-4 py-2 text-xs font-semibold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl transition border border-slate-200 dark:border-slate-700"
                >
                    Global Item Pool
                </Link>
            </div>
        </div>

        <!-- Top Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Branches</span>
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100 mt-1 font-mono">{{ totalBranches }}</div>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ activeBranches }} Active</span>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Revenue (Month)</span>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
                    {{ formatCurrency(branchStats.reduce((acc, b) => acc + b.monthly_revenue, 0)) }}
                </div>
                <span class="text-xs text-slate-500 font-medium">Across all branches</span>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Sales Count</span>
                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-1 font-mono">
                    {{ branchStats.reduce((acc, b) => acc + b.monthly_sales, 0) }}
                </div>
                <span class="text-xs text-slate-500 font-medium">Completed transactions</span>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Profit (Month)</span>
                <div class="text-2xl font-black text-blue-600 dark:text-sky-400 mt-1 font-mono">
                    {{ formatCurrency(branchStats.reduce((acc, b) => acc + b.monthly_profit, 0)) }}
                </div>
                <span class="text-xs text-slate-500 font-medium">Net estimated margin</span>
            </div>
        </div>

        <!-- Branch Breakdown Table -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
                <h2 class="font-bold text-slate-900 dark:text-slate-100 text-sm">Branch Performance Overview</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Click a branch to log in or manage users</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-800 dark:text-slate-300">
                    <thead class="bg-slate-100 dark:bg-slate-950/60 text-xs uppercase font-semibold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3">Branch</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Users</th>
                            <th class="px-6 py-3">Items</th>
                            <th class="px-6 py-3">Sales (Month)</th>
                            <th class="px-6 py-3">Revenue (Month)</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                        <tr v-for="branch in branchStats" :key="branch.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 dark:text-slate-100">{{ branch.name }}</div>
                                <div class="text-xs text-slate-500 font-mono">/{{ branch.slug }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="branch.is_active ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/30'"
                                    class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                                >
                                    {{ branch.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300 font-semibold">{{ branch.user_count }}</td>
                            <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300 font-semibold">{{ branch.item_count }}</td>
                            <td class="px-6 py-4 font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ branch.monthly_sales }}</td>
                            <td class="px-6 py-4 font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ formatCurrency(branch.monthly_revenue) }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <Link
                                    :href="route('pos.index', { branch: branch.slug })"
                                    class="text-xs bg-indigo-50 dark:bg-indigo-600/20 hover:bg-indigo-100 dark:hover:bg-indigo-600/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 px-3 py-1.5 rounded-lg transition-colors inline-block font-semibold"
                                >
                                    Open POS &rarr;
                                </Link>
                                <Link
                                    :href="route('superadmin.branches.users.index', branch.slug)"
                                    class="text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 px-3 py-1.5 rounded-lg transition-colors inline-block font-semibold"
                                >
                                    Users
                                </Link>
                                <button
                                    @click="toggleBranch(branch.slug)"
                                    :class="branch.is_active ? 'text-rose-600 dark:text-rose-400 hover:text-rose-500' : 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-500'"
                                    class="text-xs font-semibold px-2 py-1 transition-colors"
                                >
                                    {{ branch.is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
