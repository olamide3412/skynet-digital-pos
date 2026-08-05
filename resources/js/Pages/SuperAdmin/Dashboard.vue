<script setup>
import { Head, Link, router } from '@inertiajs/vue3'

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
    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans">
        <!-- Header -->
        <header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur sticky top-0 z-10 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-black text-white text-sm">
                    S
                </div>
                <div>
                    <h1 class="font-bold text-slate-100 leading-none">Skynet POS Super Admin</h1>
                    <span class="text-xs text-slate-400">Multi-Branch Overview — {{ month }}</span>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <Link
                    :href="route('superadmin.branches.index')"
                    class="px-3 py-1.5 text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors"
                >
                    Manage Branches
                </Link>
                <Link
                    :href="route('superadmin.global-items.index')"
                    class="px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition-colors border border-slate-700"
                >
                    Global Item Pool
                </Link>
                <Link
                    :href="route('superadmin.logout')"
                    method="post"
                    as="button"
                    class="px-3 py-1.5 text-xs font-medium text-rose-400 hover:text-rose-300 transition-colors"
                >
                    Logout
                </Link>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-6 space-y-6">
            <!-- Top Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Branches</span>
                    <div class="text-3xl font-black text-slate-100 mt-1">{{ totalBranches }}</div>
                    <span class="text-xs text-emerald-400 font-medium">{{ activeBranches }} Active</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Revenue (Month)</span>
                    <div class="text-2xl font-black text-emerald-400 mt-1">
                        {{ formatCurrency(branchStats.reduce((acc, b) => acc + b.monthly_revenue, 0)) }}
                    </div>
                    <span class="text-xs text-slate-500">Across all branches</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Sales Count</span>
                    <div class="text-3xl font-black text-indigo-400 mt-1">
                        {{ branchStats.reduce((acc, b) => acc + b.monthly_sales, 0) }}
                    </div>
                    <span class="text-xs text-slate-500">Completed transactions</span>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-xl">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Profit (Month)</span>
                    <div class="text-2xl font-black text-sky-400 mt-1">
                        {{ formatCurrency(branchStats.reduce((acc, b) => acc + b.monthly_profit, 0)) }}
                    </div>
                    <span class="text-xs text-slate-500">Net estimated margin</span>
                </div>
            </div>

            <!-- Branch Breakdown Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h2 class="font-bold text-slate-100">Branch Performance Overview</h2>
                    <span class="text-xs text-slate-400">Click a branch to log in or manage users</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/60 text-xs uppercase font-semibold text-slate-400 border-b border-slate-800">
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
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="branch in branchStats" :key="branch.id" class="hover:bg-slate-850/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-100">{{ branch.name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">/{{ branch.slug }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="branch.is_active ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border-rose-500/30'"
                                        class="px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                    >
                                        {{ branch.is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-300">{{ branch.user_count }}</td>
                                <td class="px-6 py-4 font-mono text-slate-300">{{ branch.item_count }}</td>
                                <td class="px-6 py-4 font-mono text-indigo-400 font-semibold">{{ branch.monthly_sales }}</td>
                                <td class="px-6 py-4 font-mono text-emerald-400 font-semibold">{{ formatCurrency(branch.monthly_revenue) }}</td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <Link
                                        :href="route('pos.index', { branch: branch.slug })"
                                        class="text-xs bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 border border-indigo-500/30 px-3 py-1.5 rounded-lg transition-colors inline-block"
                                    >
                                        Open POS &rarr;
                                    </Link>
                                    <Link
                                        :href="route('superadmin.branches.users.index', branch.slug)"
                                        class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 px-3 py-1.5 rounded-lg transition-colors inline-block"
                                    >
                                        Users
                                    </Link>
                                    <button
                                        @click="toggleBranch(branch.slug)"
                                        :class="branch.is_active ? 'text-rose-400 hover:text-rose-300' : 'text-emerald-400 hover:text-emerald-300'"
                                        class="text-xs font-medium px-2 py-1 transition-colors"
                                    >
                                        {{ branch.is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</template>
