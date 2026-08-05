<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    branches: Array,
})

const showCreateModal = ref(false)

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
    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('superadmin.dashboard')" class="text-xs text-indigo-400 hover:text-indigo-300 mb-1 inline-block">&larr; Back to Dashboard</Link>
                    <h1 class="text-2xl font-bold text-slate-100">Branches Directory</h1>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition-colors shadow-lg shadow-indigo-600/20 flex items-center space-x-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Create New Branch</span>
                </button>
            </div>

            <!-- Branch List -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    v-for="branch in branches"
                    :key="branch.id"
                    class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xl"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-lg text-slate-100 leading-snug">{{ branch.name }}</h3>
                                <span class="text-xs text-slate-500 font-mono">/{{ branch.slug }}</span>
                            </div>
                            <span
                                :class="branch.is_active ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border-rose-500/30'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                            >
                                {{ branch.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="text-xs text-slate-400 space-y-1">
                            <div v-if="branch.address" class="flex items-center space-x-2">
                                <span>📍</span> <span>{{ branch.address }}</span>
                            </div>
                            <div v-if="branch.phone" class="flex items-center space-x-2">
                                <span>📞</span> <span>{{ branch.phone }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-800 text-center">
                            <div class="bg-slate-950 p-2 rounded-lg">
                                <div class="text-xs text-slate-500">Users</div>
                                <div class="font-bold text-slate-200">{{ branch.users_count }}</div>
                            </div>
                            <div class="bg-slate-950 p-2 rounded-lg">
                                <div class="text-xs text-slate-500">Items</div>
                                <div class="font-bold text-slate-200">{{ branch.items_count }}</div>
                            </div>
                            <div class="bg-slate-950 p-2 rounded-lg">
                                <div class="text-xs text-slate-500">Sales</div>
                                <div class="font-bold text-slate-200">{{ branch.sales_count }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center space-x-3 text-xs font-semibold">
                            <Link
                                :href="route('superadmin.branches.users.index', branch.slug)"
                                class="text-indigo-400 hover:text-indigo-300"
                            >
                                Users &rarr;
                            </Link>
                            <Link
                                :href="route('superadmin.branches.items.index', branch.slug)"
                                class="text-emerald-400 hover:text-emerald-300 flex items-center space-x-1"
                            >
                                <span>Import / Items &rarr;</span>
                            </Link>
                        </div>
                        <button
                            @click="toggleBranch(branch.slug)"
                            :class="branch.is_active ? 'text-rose-400 hover:text-rose-300' : 'text-emerald-400 hover:text-emerald-300'"
                            class="text-xs font-medium"
                        >
                            {{ branch.is_active ? 'Disable' : 'Enable' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-6 shadow-2xl my-8">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <h2 class="font-bold text-lg text-slate-100">Create New Branch</h2>
                    <button @click="showCreateModal = false" class="text-slate-500 hover:text-slate-300">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div class="space-y-3">
                        <h3 class="text-xs uppercase font-bold text-indigo-400 tracking-wider">1. Branch Details</h3>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Branch Name *</label>
                            <input
                                v-model="form.name"
                                @input="generateSlug"
                                type="text"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100"
                                placeholder="e.g. Asaba Main Branch"
                            />
                            <div v-if="form.errors.name" class="text-rose-400 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Branch Slug (URL identifier) *</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm font-mono text-indigo-300"
                                placeholder="asaba-main"
                            />
                            <span class="text-xs text-slate-500">URL: skynetpos.com/{{ form.slug || 'slug' }}</span>
                            <div v-if="form.errors.slug" class="text-rose-400 text-xs mt-1">{{ form.errors.slug }}</div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Address</label>
                            <input v-model="form.address" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100" />
                        </div>
                    </div>

                    <div class="space-y-3 pt-3 border-t border-slate-800">
                        <h3 class="text-xs uppercase font-bold text-indigo-400 tracking-wider">2. Initial Branch Admin User</h3>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Admin Full Name *</label>
                            <input v-model="form.admin_name" type="text" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Username *</label>
                                <input v-model="form.admin_username" type="text" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100" />
                                <div v-if="form.errors.admin_username" class="text-rose-400 text-xs mt-1">{{ form.errors.admin_username }}</div>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Email *</label>
                                <input v-model="form.admin_email" type="email" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100" />
                                <div v-if="form.errors.admin_email" class="text-rose-400 text-xs mt-1">{{ form.errors.admin_email }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Initial Password *</label>
                            <input v-model="form.admin_password" type="password" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-xs text-slate-400 hover:text-slate-200">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold">
                            Create Branch & Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
