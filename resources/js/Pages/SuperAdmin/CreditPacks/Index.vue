<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    packs: Array,
})

const isModalOpen = ref(false)
const editingPack = ref(null)

const form = useForm({
    name:       '',
    credits:    500,
    price:      2500,
    currency:   'NGN',
    sort_order: 0,
    is_active:  true,
})

function openCreateModal() {
    editingPack.value = null
    form.reset()
    form.name       = ''
    form.credits    = 500
    form.price      = 2500
    form.currency   = 'NGN'
    form.sort_order = (props.packs.length + 1)
    form.is_active  = true
    isModalOpen.value = true
}

function openEditModal(pack) {
    editingPack.value = pack
    form.name       = pack.name
    form.credits    = pack.credits
    form.price      = pack.price
    form.currency   = pack.currency
    form.sort_order = pack.sort_order
    form.is_active  = Boolean(pack.is_active)
    isModalOpen.value = true
}

function closeModal() {
    isModalOpen.value = false
    editingPack.value = null
    form.reset()
}

function submit() {
    if (editingPack.value) {
        form.put(route('superadmin.credit-packs.update', editingPack.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('superadmin.credit-packs.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        })
    }
}

function togglePack(pack) {
    router.post(route('superadmin.credit-packs.toggle', pack.id), {}, {
        preserveScroll: true,
    })
}

function deletePack(pack) {
    if (confirm(`Are you sure you want to delete the "${pack.name}" credit pack?`)) {
        router.delete(route('superadmin.credit-packs.destroy', pack.id), {
            preserveScroll: true,
        })
    }
}

const formatCurrency = (val, currency = 'NGN') => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}
</script>

<template>
    <Head title="AI Credit Packs - SuperAdmin" />

    <div class="max-w-6xl mx-auto space-y-6">
        <!-- ── Top Header ────────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                    <span>✨</span>
                    <span>AI Credit Packs Catalog</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Manage available add-on credit packages and pricing that organizations can purchase via Flutterwave and Paystack.
                </p>
            </div>

            <button
                type="button"
                @click="openCreateModal"
                class="px-5 py-2.5 bg-theme hover:opacity-90 text-white font-bold text-xs rounded-xl shadow-lg shadow-black/10 transition flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Create Credit Pack</span>
            </button>
        </div>

        <!-- ── Packs Table ───────────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/40 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Order</th>
                            <th class="py-3 px-4">Pack Name</th>
                            <th class="py-3 px-4">Credits</th>
                            <th class="py-3 px-4">Price</th>
                            <th class="py-3 px-4">Unit Rate</th>
                            <th class="py-3 px-4">Purchases</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs text-slate-700 dark:text-slate-300">
                        <tr v-for="pack in packs" :key="pack.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-400">
                                #{{ pack.sort_order }}
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                {{ pack.name }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-black bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                    {{ pack.credits.toLocaleString() }} Credits
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-black text-slate-900 dark:text-white">
                                {{ formatCurrency(pack.price, pack.currency) }}
                            </td>
                            <td class="py-3.5 px-4 text-[11px] text-slate-500 font-mono">
                                ₦{{ (pack.price / (pack.credits || 1)).toFixed(2) }} / credit
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                {{ pack.topups_count || 0 }} orders
                            </td>
                            <td class="py-3.5 px-4">
                                <button
                                    type="button"
                                    @click="togglePack(pack)"
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition cursor-pointer',
                                        pack.is_active
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
                                    ]"
                                >
                                    {{ pack.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(pack)"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                                        title="Edit pack"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="deletePack(pack)"
                                        class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                                        title="Delete pack"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!packs || packs.length === 0">
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                No credit packs created yet. Click "Create Credit Pack" to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── CREATE / EDIT MODAL ────────────────────────────────────────── -->
        <teleport to="body">
            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ editingPack ? 'Edit AI Credit Pack' : 'Create AI Credit Pack' }}
                        </h3>
                        <button
                            type="button"
                            @click="closeModal"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Pack Name</label>
                            <input
                                type="text"
                                v-model="form.name"
                                required
                                placeholder="e.g. Starter Pack (500 Credits)"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Credits Amount</label>
                                <input
                                    type="number"
                                    v-model="form.credits"
                                    required
                                    min="1"
                                    step="1"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                                />
                            </div>

                            <div>
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Price (₦ NGN)</label>
                                <input
                                    type="number"
                                    v-model="form.price"
                                    required
                                    min="0"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Currency</label>
                                <input
                                    type="text"
                                    v-model="form.currency"
                                    required
                                    placeholder="NGN"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                                />
                            </div>

                            <div>
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Sort Order</label>
                                <input
                                    type="number"
                                    v-model="form.sort_order"
                                    min="0"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                            </label>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Active and available for purchase</span>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2.5 rounded-xl bg-theme hover:opacity-90 text-white text-xs font-bold shadow-md transition cursor-pointer disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : (editingPack ? 'Update Pack' : 'Create Pack') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </div>
</template>
