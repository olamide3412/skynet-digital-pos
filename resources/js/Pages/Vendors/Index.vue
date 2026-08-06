<script setup>
import { ref } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    vendors: Object,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const isEditing = ref(false)
const currentVendor = ref(null)

const form = useForm({
    name: '',
    company_name: '',
    phone: '',
    email: '',
    address: '',
    status: 'Active',
})

function doSearch() {
    router.get(route('pos.vendors.index'), { search: search.value }, { preserveState: true, replace: true })
}

function openCreateModal() {
    isEditing.value = false
    currentVendor.value = null
    form.reset()
    document.getElementById('vendor-modal').showModal()
}

function openEditModal(vendor) {
    isEditing.value = true
    currentVendor.value = vendor
    form.name = vendor.name
    form.company_name = vendor.company_name || ''
    form.phone = vendor.phone
    form.email = vendor.email || ''
    form.address = vendor.address || ''
    form.status = vendor.status
    document.getElementById('vendor-modal').showModal()
}

function submit() {
    if (isEditing.value) {
        form.put(route('pos.vendors.update', currentVendor.value.id), {
            onSuccess: () => document.getElementById('vendor-modal').close(),
        })
    } else {
        form.post(route('pos.vendors.store'), {
            onSuccess: () => document.getElementById('vendor-modal').close(),
        })
    }
}

function destroy(vendor) {
    if (confirm(`Are you sure you want to delete vendor "${vendor.name}"?`)) {
        router.delete(route('pos.vendors.destroy', vendor.id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Vendors & Suppliers</h1>
            <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Vendor
            </button>
        </div>

        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex gap-2 flex-shrink-0 bg-slate-100/50 dark:bg-slate-800/40">
            <input v-model="search" @keydown.enter="doSearch" type="text" placeholder="Search by name or company…" class="flex-1 max-w-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition" />
            <button @click="doSearch" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm font-semibold rounded-lg transition">Search</button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Name</th>
                            <th class="text-left px-4 py-3 font-semibold">Company</th>
                            <th class="text-left px-4 py-3 font-semibold">Phone</th>
                            <th class="text-center px-4 py-3 font-semibold">Orders</th>
                            <th class="text-left px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                        <tr v-for="vendor in vendors.data" :key="vendor.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-900 dark:text-white font-medium">{{ vendor.name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ vendor.company_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs">{{ vendor.phone }}</td>
                            <td class="px-4 py-3 text-center text-emerald-600 dark:text-emerald-400 font-bold font-mono">{{ vendor.purchase_orders_count }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full" :class="vendor.status === 'Active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'">
                                    {{ vendor.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button @click="openEditModal(vendor)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition">Edit</button>
                                    <button @click="destroy(vendor)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!vendors.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No vendors found.</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="vendors.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ vendors.from }}–{{ vendors.to }} of {{ vendors.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="vendors.prev_page_url" :href="vendors.prev_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Prev</Link>
                        <Link v-if="vendors.next_page_url" :href="vendors.next_page_url" class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="vendor-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95 w-full max-w-lg">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ isEditing ? 'Edit Vendor' : 'New Vendor' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Name *</label>
                                <input v-model="form.name" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Company Name</label>
                                <input v-model="form.company_name" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone *</label>
                                <input v-model="form.phone" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                                <input v-model="form.email" type="email" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                                <input v-model="form.address" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                                <select v-model="form.status" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                    <option value="Active" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Active</option>
                                    <option value="Inactive" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40">
                                {{ form.processing ? 'Saving...' : 'Save Vendor' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</template>
