<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import OrgLayout from '@/Layouts/OrgLayout.vue'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization: Object,
    branches: Array,
    plan_info: Object,
})

const search = ref('')
const showCreateModal = ref(false)
const showPassword = ref(false)

const branchForm = useForm({
    name: '',
    slug: '',
    address: '',
    phone: '',
    email: '',
    admin_name: '',
    admin_username: '',
    admin_email: '',
    admin_password: '',
})

const generateSlug = () => {
    branchForm.slug = branchForm.name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '')
}

const openCreateModal = () => {
    branchForm.reset()
    showPassword.value = false
    showCreateModal.value = true
}

const submitCreateBranch = () => {
    branchForm.post(route('org.branches.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            branchForm.reset()
        },
    })
}

const filteredBranches = computed(() => {
    if (!search.value) return props.branches
    const q = search.value.toLowerCase()
    return props.branches.filter(b =>
        b.name.toLowerCase().includes(q) ||
        b.slug.toLowerCase().includes(q) ||
        (b.address && b.address.toLowerCase().includes(q))
    )
})

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}
</script>

<template>
    <Head :title="`Branches - ${organization.name}`" />

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Organization Branches</h1>
                    <span
                        v-if="plan_info?.branch_limit !== null"
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                        :class="plan_info?.is_limit_reached
                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200 dark:border-amber-800'
                            : 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200 dark:border-purple-800'"
                    >
                        {{ plan_info?.current_branches_count }} of {{ plan_info?.branch_limit }} Branches Used ({{ plan_info?.plan_name }})
                    </span>
                    <span
                        v-else
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold border bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200 dark:border-purple-800"
                    >
                        {{ plan_info?.current_branches_count }} Branches (Unrestricted)
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Manage and provision operating business locations under {{ organization.name }}.</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="w-full sm:w-64">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search branches..."
                        class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 outline-none focus:border-purple-500 transition"
                    />
                </div>

                <!-- Create Branch Button -->
                <button
                    v-if="plan_info?.can_create_branch"
                    @click="openCreateModal"
                    type="button"
                    class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-purple-600/20 flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Branch</span>
                </button>

                <!-- Disabled States -->
                <div v-else-if="!plan_info?.branch_creation_allowed" class="group relative">
                    <button
                        type="button"
                        disabled
                        class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 flex items-center gap-1.5 whitespace-nowrap cursor-not-allowed opacity-80"
                    >
                        <span>🔒 Branch Creation Disabled</span>
                    </button>
                </div>

                <div v-else-if="plan_info?.is_limit_reached" class="group relative">
                    <button
                        type="button"
                        disabled
                        class="px-4 py-2 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 rounded-xl text-xs font-bold border border-amber-200 dark:border-amber-800 flex items-center gap-1.5 whitespace-nowrap cursor-not-allowed"
                    >
                        <span>⚠️ Plan Limit Reached</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Warning Banners if Gated -->
        <div
            v-if="!plan_info?.branch_creation_allowed"
            class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 flex items-start gap-3 text-xs text-rose-800 dark:text-rose-300"
        >
            <span class="text-lg">🔒</span>
            <div>
                <strong class="font-bold block">Branch Creation Restricted</strong>
                <span>The ability to create new branches has been disabled for this organization by Platform Administration. Please contact your platform administrator if you need to add an operating branch.</span>
            </div>
        </div>

        <div
            v-else-if="plan_info?.is_limit_reached"
            class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-start gap-3 text-xs text-amber-800 dark:text-amber-300"
        >
            <span class="text-lg">⚠️</span>
            <div>
                <strong class="font-bold block">Branch Quota Capacity Reached</strong>
                <span>
                    Your current <strong>{{ plan_info.plan_name }}</strong> plan allows a maximum of <strong>{{ plan_info.branch_limit }} branch(es)</strong>. You are currently utilizing all {{ plan_info.current_branches_count }} slots. Please contact platform support or upgrade your subscription plan to add more branches.
                </span>
            </div>
        </div>

        <!-- Branches Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="branch in filteredBranches"
                :key="branch.id"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs hover:border-purple-300 dark:hover:border-purple-800 transition flex flex-col justify-between space-y-4"
            >
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white font-black text-base shadow-sm flex-shrink-0 overflow-hidden">
                                <img v-if="branch.logo_url" :src="branch.logo_url" :alt="branch.name" class="w-full h-full object-cover" />
                                <span v-else>{{ branch.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ branch.name }}</h3>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-xs font-mono text-purple-600 dark:text-purple-400">/{{ branch.slug }}</span>
                                    <span
                                        v-if="branch.operating_mode === 'laundry_only'"
                                        class="px-1.5 py-0.2 rounded-md text-[9px] font-bold bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800"
                                    >
                                        🧺 Laundry
                                    </span>
                                    <span
                                        v-else-if="branch.operating_mode === 'pos_only'"
                                        class="px-1.5 py-0.2 rounded-md text-[9px] font-bold bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800"
                                    >
                                        💻 POS
                                    </span>
                                    <span
                                        v-else
                                        class="px-1.5 py-0.2 rounded-md text-[9px] font-bold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800"
                                    >
                                        🏪 Mixed
                                    </span>
                                </div>
                            </div>
                        </div>
                        <span
                            :class="branch.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/30'"
                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase"
                        >
                            {{ branch.is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div v-if="branch.address" class="truncate">📍 {{ branch.address }}</div>
                        <div v-if="branch.phone">📞 {{ branch.phone }}</div>
                        <div v-if="branch.email">✉️ {{ branch.email }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <div class="p-2.5 bg-slate-50 dark:bg-slate-950/60 rounded-xl">
                            <div class="text-[10px] text-slate-400 uppercase font-semibold">
                                {{ branch.operating_mode === 'laundry_only' ? "Today's Intake" : "Today's Sales" }}
                            </div>
                            <div class="font-bold text-slate-900 dark:text-white mt-0.5">{{ formatCurrency(branch.today_revenue) }}</div>
                        </div>
                        <div class="p-2.5 bg-slate-50 dark:bg-slate-950/60 rounded-xl">
                            <div class="text-[10px] text-slate-400 uppercase font-semibold">This Month</div>
                            <div class="font-bold text-purple-600 dark:text-purple-400 mt-0.5">{{ formatCurrency(branch.month_revenue) }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span v-if="branch.operating_mode === 'laundry_only'">🧺 Active: <strong>{{ branch.active_laundry_count ?? 0 }}</strong> orders</span>
                        <span v-else>📦 Catalog: <strong>{{ branch.items_count }}</strong> items</span>
                        <span>👥 Staff: <strong>{{ branch.users_count }}</strong> users</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a
                        :href="branch.pos_url"
                        target="_blank"
                        class="w-full py-2.5 px-4 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-purple-600/20"
                    >
                        <span>{{ branch.operating_mode === 'laundry_only' ? 'Open Laundry Hub' : 'Open Branch POS Terminal' }}</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>
            </div>

            <div v-if="!filteredBranches.length" class="col-span-full py-12 text-center text-slate-400">
                No branches found matching your search.
            </div>
        </div>

        <!-- ── CREATE BRANCH MODAL ────────────────────────────────────────────── -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full max-h-[92vh] overflow-y-auto p-6 sm:p-7 space-y-5 shadow-2xl my-auto animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-start justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-black text-slate-900 dark:text-white text-lg">Create New Branch</h2>
                            <span v-if="plan_info?.branch_limit" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300">
                                Slot {{ plan_info.current_branches_count + 1 }} of {{ plan_info.branch_limit }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Provision a new store location and assign its initial branch manager.</p>
                    </div>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreateBranch" class="space-y-5">
                    <!-- Validation Error Summary Banner -->
                    <div v-if="Object.keys(branchForm.errors).length > 0" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs space-y-1">
                        <p class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Please resolve the following issues:</span>
                        </p>
                        <ul class="list-disc list-inside space-y-0.5 pl-1 text-[11px]">
                            <li v-for="(err, field) in branchForm.errors" :key="field">{{ err }}</li>
                        </ul>
                    </div>

                    <!-- Step 1: Branch Location Info -->
                    <div class="space-y-3.5">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 flex items-center justify-center text-[11px] font-bold">1</span>
                            <h3 class="text-xs uppercase font-extrabold text-purple-600 dark:text-purple-400 tracking-wider">Branch Store Information</h3>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Branch Name <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="branchForm.name"
                                @input="generateSlug"
                                type="text"
                                required
                                placeholder="e.g. Lekki Phase 1 Store"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition"
                            />
                            <div v-if="branchForm.errors.name" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Branch URL Slug <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="branchForm.slug"
                                type="text"
                                required
                                placeholder="lekki-phase-1-store"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-mono text-purple-600 dark:text-purple-300 outline-none focus:border-purple-500 transition"
                            />
                            <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1">
                                <span>POS Terminal URL: /{{ branchForm.slug || 'branch-slug' }}</span>
                            </div>
                            <div v-if="branchForm.errors.slug" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.slug }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Address</label>
                            <input
                                v-model="branchForm.address"
                                type="text"
                                placeholder="e.g. 14 Admiralty Way, Lekki Phase 1, Lagos"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition"
                            />
                            <div v-if="branchForm.errors.address" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.address }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Contact Phone</label>
                                <input
                                    v-model="branchForm.phone"
                                    type="text"
                                    placeholder="+234..."
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Contact Email</label>
                                <input
                                    v-model="branchForm.email"
                                    type="email"
                                    placeholder="lekki@store.com"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-purple-500 transition"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Branch Admin User Account -->
                    <div class="space-y-3.5 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-[11px] font-bold">2</span>
                            <h3 class="text-xs uppercase font-extrabold text-indigo-600 dark:text-indigo-400 tracking-wider">Initial Branch Administrator</h3>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Manager Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="branchForm.admin_name"
                                type="text"
                                required
                                placeholder="e.g. John Doe"
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                            />
                            <div v-if="branchForm.errors.admin_name" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_name }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Username <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="branchForm.admin_username"
                                    type="text"
                                    required
                                    placeholder="lekki_manager"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-mono text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                                />
                                <div v-if="branchForm.errors.admin_username" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_username }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Login Email <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="branchForm.admin_email"
                                    type="email"
                                    required
                                    placeholder="manager@store.com"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                                />
                                <div v-if="branchForm.errors.admin_email" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_email }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Initial Password (min 8 characters) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <input
                                    v-model="branchForm.admin_password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    minlength="8"
                                    placeholder="••••••••"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 pr-10 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none cursor-pointer"
                                >
                                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                                </button>
                            </div>
                            <div v-if="branchForm.errors.admin_password" class="text-rose-500 text-xs mt-1">{{ branchForm.errors.admin_password }}</div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="branchForm.processing"
                            class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-md shadow-purple-600/20 cursor-pointer flex items-center gap-2"
                        >
                            <span v-if="branchForm.processing">Provisioning Branch...</span>
                            <span v-else>Confirm & Provision Branch</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
