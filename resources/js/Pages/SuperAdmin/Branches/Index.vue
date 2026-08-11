<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branches: Array,
})

const showCreateModal = ref(false)
const showAdminPass = ref(false)

const form = useForm({
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
    form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '')
}

const submitCreate = () => {
    form.post(route('superadmin.branches.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            form.reset()
        },
    })
}

const toggleBranch = (branchSlug) => {
    router.post(route('superadmin.branches.toggle', branchSlug))
}
</script>

<template>
    <Head title="Manage Branches - Super Admin" />
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Branches Directory</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage registered business locations and initial branch administrators</p>
            </div>
            <button
                @click="showCreateModal = true"
                class="w-full sm:w-auto px-4 py-2.5 bg-theme hover:opacity-90 text-white font-bold rounded-xl text-xs transition shadow-md flex items-center justify-center space-x-2"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Create New Branch</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="branch in branches"
                :key="branch.id"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 space-y-4 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all flex flex-col"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base leading-tight">{{ branch.name }}</h2>
                        <span class="text-xs text-indigo-600 dark:text-indigo-400 font-mono">/{{ branch.slug }}</span>
                    </div>
                    <span
                        :class="branch.is_active ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/30'"
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                    >
                        {{ branch.is_active ? 'Active' : 'Disabled' }}
                    </span>
                </div>

                <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1 flex-1">
                    <div v-if="branch.address"><span class="text-slate-400 dark:text-slate-500 font-medium">Address:</span> {{ branch.address }}</div>
                    <div v-if="branch.phone"><span class="text-slate-400 dark:text-slate-500 font-medium">Phone:</span> {{ branch.phone }}</div>
                    <div v-if="branch.email"><span class="text-slate-400 dark:text-slate-500 font-medium">Email:</span> {{ branch.email }}</div>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500">
                        <span>Staff: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.users_count }}</strong></span>
                        <span>Items: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ branch.items_count }}</strong></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap gap-2">
                    <Link
                        :href="route('pos.index', { branch: branch.slug })"
                        class="flex-1 text-center py-2 text-xs bg-indigo-50 dark:bg-indigo-600/20 hover:bg-indigo-100 dark:hover:bg-indigo-600/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 rounded-xl font-bold transition-colors"
                    >
                        Open POS &rarr;
                    </Link>
                    <Link
                        :href="route('superadmin.branches.users.index', branch.slug)"
                        class="px-3 py-2 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold transition-colors"
                    >
                        Users
                    </Link>
                    <Link
                        :href="route('superadmin.branches.items.index', branch.slug)"
                        class="px-3 py-2 text-xs bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold transition-colors"
                    >
                        Items
                    </Link>
                    <button
                        @click="toggleBranch(branch.slug)"
                        :class="branch.is_active ? 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
                        class="px-2.5 py-2 text-xs rounded-xl transition-colors font-bold"
                    >
                        {{ branch.is_active ? 'Disable' : 'Enable' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Branch Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 space-y-4 shadow-2xl my-auto">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-900 dark:text-slate-100 text-base">Create New Branch & Initial Admin</h2>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div class="space-y-3">
                        <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">1. Branch Details</h3>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Name *</label>
                            <input
                                v-model="form.name"
                                @input="generateSlug"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition"
                                placeholder="e.g. Asaba Main Branch"
                            />
                            <div v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Slug (URL identifier) *</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-indigo-600 dark:text-indigo-300 outline-none focus:border-indigo-500 transition"
                                placeholder="asaba-main"
                            />
                            <span class="text-[11px] text-slate-500">URL: skynetpos.com/{{ form.slug || 'slug' }}</span>
                            <div v-if="form.errors.slug" class="text-rose-500 text-xs mt-1">{{ form.errors.slug }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                            <input v-model="form.address" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                        </div>
                    </div>

                    <div class="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <h3 class="text-xs uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">2. Initial Branch Admin User</h3>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Admin Full Name *</label>
                            <input v-model="form.admin_name" type="text" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                                <input v-model="form.admin_username" type="text" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition font-mono" />
                                <div v-if="form.errors.admin_username" class="text-rose-500 text-xs mt-1">{{ form.errors.admin_username }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email *</label>
                                <input v-model="form.admin_email" type="email" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                                <div v-if="form.errors.admin_email" class="text-rose-500 text-xs mt-1">{{ form.errors.admin_email }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Initial Password *</label>
                            <div class="relative flex items-center">
                                <input v-model="form.admin_password" :type="showAdminPass ? 'text' : 'password'" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 pr-9 text-sm text-slate-900 dark:text-slate-100 outline-none focus:border-indigo-500 transition" />
                                <button type="button" @click="showAdminPass = !showAdminPass" class="absolute right-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none" :title="showAdminPass ? 'Hide password' : 'Show password'">
                                    <svg v-if="!showAdminPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20">
                            Create Branch & Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
