<script setup>
import { ref } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const isEditing = ref(false)
const currentUser = ref(null)

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role_ids: [],
    status: 'Active',
})

function doSearch() {
    router.get(route('pos.users.index'), { search: search.value }, { preserveState: true, replace: true })
}

function openCreateModal() {
    isEditing.value = false
    currentUser.value = null
    form.reset()
    document.getElementById('user-modal').showModal()
}

function openEditModal(user) {
    isEditing.value = true
    currentUser.value = user
    form.name = user.name
    form.username = user.username
    form.email = user.email || ''
    form.password = ''
    form.role_ids = user.roles.map(r => r.id)
    form.status = user.status ? 'Active' : 'Inactive'
    document.getElementById('user-modal').showModal()
}

function submit() {
    if (isEditing.value) {
        form.put(route('pos.users.update', currentUser.value.id), {
            onSuccess: () => document.getElementById('user-modal').close(),
        })
    } else {
        form.post(route('pos.users.store'), {
            onSuccess: () => document.getElementById('user-modal').close(),
        })
    }
}

function destroy(user) {
    if (confirm(`Are you sure you want to delete user "${user.username}"?`)) {
        router.delete(route('pos.users.destroy', user.id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">System Users</h1>
            <div class="flex gap-2">
                <Link :href="route('pos.roles.index')" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded-lg transition font-medium">
                    Manage Roles
                </Link>
                <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New User
                </button>
            </div>
        </div>

        <div class="px-6 py-3 border-b border-slate-700 flex gap-2 flex-shrink-0 bg-slate-800/50">
            <input v-model="search" @keydown.enter="doSearch" type="text" placeholder="Search name or username…" class="flex-1 max-w-sm bg-slate-700 text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
            <button @click="doSearch" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded-lg transition">Search</button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Name</th>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Username</th>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Roles</th>
                            <th class="text-left px-5 py-3 text-slate-400 font-medium">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-white font-medium">
                                {{ user.name }}
                                <div v-if="user.email" class="text-xs text-slate-500 font-normal">{{ user.email }}</div>
                            </td>
                            <td class="px-5 py-3 text-slate-300">{{ user.username }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in user.roles" :key="role.id" class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider border border-slate-600 text-slate-300">
                                        {{ role.name }}
                                    </span>
                                    <span v-if="!user.roles.length" class="text-xs text-slate-500 italic">None</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 flex items-center">
                                <span class="px-2 py-0.5 text-xs rounded-full" :class="user.status ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                    {{ user.status ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2 text-xs">
                                    <button @click="openEditModal(user)" class="text-blue-400 hover:text-blue-300 transition">Edit</button>
                                    <button v-if="$page.props.auth.user.id !== user.id" @click="destroy(user)" class="text-red-400 hover:text-red-300 transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">No users found.</td>
                        </tr>
                    </tbody>
                </table>
               <div v-if="users.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-700 text-xs text-slate-400">
                    <span>{{ users.from }}–{{ users.to }} of {{ users.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Prev</Link>
                        <Link v-if="users.next_page_url" :href="users.next_page_url" class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="user-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95 w-full max-w-lg">
            <div class="bg-slate-800 border border-slate-700 overflow-hidden rounded-xl flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                    <h3 class="font-bold text-white">{{ isEditing ? 'Edit User' : 'New User' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Full Name *</label>
                            <input v-model="form.name" type="text" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Username *</label>
                                <input v-model="form.username" type="text" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                                <p v-if="form.errors.username" class="text-red-400 text-xs mt-1">{{ form.errors.username }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Password <span v-if="isEditing" class="text-slate-500">(Leave blank to keep)</span><span v-else>*</span></label>
                                <input v-model="form.password" type="password" :required="!isEditing" minlength="8" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                                <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Email <span class="text-slate-500">(Optional)</span></label>
                            <input v-model="form.email" type="email" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>
                        
                        <div class="border-t border-slate-700 pt-4">
                            <label class="block text-xs text-slate-400 mb-2">Assign Roles *</label>
                            <div class="grid grid-cols-2 gap-2 max-h-32 overflow-y-auto">
                                <label v-for="role in roles" :key="role.id" class="flex items-center gap-2 cursor-pointer bg-slate-700/50 hover:bg-slate-700 p-2 rounded border border-slate-600 transition group">
                                    <input v-model="form.role_ids" type="checkbox" :value="role.id" class="accent-emerald-500" />
                                    <span class="text-sm text-slate-300 group-hover:text-white capitalize">{{ role.name }}</span>
                                </label>
                            </div>
                            <p v-if="form.errors.role_ids" class="text-red-400 text-xs mt-1">{{ form.errors.role_ids }}</p>
                        </div>

                        <div class="border-t border-slate-700 pt-4">
                            <label class="block text-xs text-slate-400 mb-1">Account Status</label>
                            <select v-model="form.status" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition disabled:opacity-40">
                                {{ form.processing ? 'Saving...' : 'Save User' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</template>
