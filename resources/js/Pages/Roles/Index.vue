<script setup>
import { ref } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    roles: Array,
})

const isEditing = ref(false)
const currentRole = ref(null)

const form = useForm({
    name: '',
    description: '',
})

function openCreateModal() {
    isEditing.value = false
    currentRole.value = null
    form.reset()
    document.getElementById('role-modal').showModal()
}

function openEditModal(role) {
    isEditing.value = true
    currentRole.value = role
    form.name = role.name
    form.description = role.description || ''
    document.getElementById('role-modal').showModal()
}

function submit() {
    if (isEditing.value) {
        form.put(route('pos.roles.update', currentRole.value.id), {
            onSuccess: () => document.getElementById('role-modal').close(),
        })
    } else {
        form.post(route('pos.roles.store'), {
            onSuccess: () => document.getElementById('role-modal').close(),
        })
    }
}

const isCoreRole = (name) => {
    return ['branch-admin', 'admin', 'cashier', 'manager', 'inventory-manager', 'laundry-staff'].includes((name || '').toLowerCase())
}

function destroy(role) {
    if (isCoreRole(role.name)) {
        alert(`Role "${role.name}" is a core system role and cannot be deleted.`)
        return
    }
    if (role.users_count > 0) {
        alert(`Cannot delete role "${role.name}" because it is currently assigned to ${role.users_count} user(s). Please reassign them to another role first.`)
        return
    }
    if (confirm(`Are you sure you want to delete role "${role.name}"?`)) {
        router.delete(route('pos.roles.destroy', role.id), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.role) {
                    alert(errors.role)
                }
            }
        })
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.users.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white flex-1">System Roles</h1>
            <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Role
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 flex flex-col items-center">
            <!-- Inline Error Banner if role error returned -->
            <div v-if="$page.props.errors?.role" class="w-full max-w-3xl mb-4 p-3.5 bg-red-500/10 border border-red-500/30 rounded-xl text-red-600 dark:text-red-400 text-xs font-semibold flex items-center gap-2.5">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ $page.props.errors.role }}</span>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden w-full max-w-3xl h-fit shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold w-1/3">Role Name</th>
                            <th class="text-left px-5 py-3 font-semibold">Description</th>
                            <th class="text-center px-5 py-3 font-semibold w-24">Users</th>
                            <th class="px-5 py-3 w-32 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="role in roles" :key="role.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-5 py-3 text-slate-900 dark:text-white font-bold tracking-wide uppercase text-xs flex items-center gap-2">
                                <span>{{ role.name }}</span>
                                <span v-if="isCoreRole(role.name)" class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">System</span>
                            </td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ role.description || '—' }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="bg-slate-100 dark:bg-slate-700 px-2.5 py-0.5 rounded text-emerald-600 dark:text-emerald-400 font-bold font-mono text-xs border border-slate-200 dark:border-slate-600">{{ role.users_count }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-3 text-xs">
                                    <button @click="openEditModal(role)" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 font-semibold transition">Edit</button>
                                    <button v-if="!isCoreRole(role.name)" @click="destroy(role)" class="text-red-600 dark:text-red-400 hover:text-red-500 font-semibold transition">Delete</button>
                                    <span v-else class="text-slate-400 dark:text-slate-500 text-[11px] italic select-none">Protected</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!roles.length">
                            <td colspan="4" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">No roles configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <dialog id="role-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95 w-full max-w-sm">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ isEditing ? 'Edit Role' : 'New Role' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Role Name *</label>
                            <input v-model="form.name" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
                            <textarea v-model="form.description" rows="3" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition resize-none"></textarea>
                        </div>
                        
                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40 shadow-md shadow-emerald-900/20">
                                {{ form.processing ? 'Saving...' : 'Save Role' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</template>
