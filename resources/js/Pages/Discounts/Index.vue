<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    discounts: Array,
})

const isEditing = ref(false)
const currentDiscount = ref(null)

const form = useForm({
    discount_type: 'percentage',
    discount_value: 0,
    start_date_time: '',
    end_date_time: '',
    is_apply: false,
})

function openCreateModal() {
    isEditing.value = false
    currentDiscount.value = null
    form.reset()
    form.start_date_time = dayjs().format('YYYY-MM-DDTHH:mm')
    form.end_date_time = dayjs().add(1, 'month').format('YYYY-MM-DDTHH:mm')
    document.getElementById('discount-modal').showModal()
}

function openEditModal(discount) {
    isEditing.value = true
    currentDiscount.value = discount
    form.discount_type = discount.discount_type
    form.discount_value = discount.discount_value
    form.start_date_time = dayjs(discount.start_date_time).format('YYYY-MM-DDTHH:mm')
    form.end_date_time = dayjs(discount.end_date_time).format('YYYY-MM-DDTHH:mm')
    form.is_apply = !!discount.is_apply
    document.getElementById('discount-modal').showModal()
}

function submit() {
    if (isEditing.value) {
        form.put(route('pos.discounts.update', currentDiscount.value.id), {
            onSuccess: () => document.getElementById('discount-modal').close(),
        })
    } else {
        form.post(route('pos.discounts.store'), {
            onSuccess: () => document.getElementById('discount-modal').close(),
        })
    }
}

function destroy(discount) {
    if (confirm('Are you sure you want to delete this discount rule?')) {
        router.delete(route('pos.discounts.destroy', discount.id))
    }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">Active Discounts</h1>
            <button @click="openCreateModal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Discount Rule
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Type</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Value</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Valid From</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Valid Until</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="discount in discounts" :key="discount.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-300 capitalize">{{ discount.discount_type }}</td>
                            <td class="px-4 py-3 text-emerald-400 font-medium">
                                {{ discount.discount_type === 'percentage' ? discount.discount_value + '%' : '₦' + discount.discount_value }}
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ dayjs(discount.start_date_time).format('DD MMM YYYY HH:mm') }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ dayjs(discount.end_date_time).format('DD MMM YYYY HH:mm') }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full" :class="discount.is_apply ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/50 text-slate-400'">
                                    {{ discount.is_apply ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEditModal(discount)" class="text-xs text-blue-400 hover:text-blue-300 transition">Edit</button>
                                    <button @click="destroy(discount)" class="text-xs text-red-400 hover:text-red-300 transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!discounts.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">No discount rules configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <dialog id="discount-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95">
            <div class="bg-slate-800 border border-slate-700 w-full max-w-md overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                    <h3 class="font-bold text-white">{{ isEditing ? 'Edit Discount Rule' : 'New Discount Rule' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-white transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Type *</label>
                                <select v-model="form.discount_type" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm transition">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₦)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Value *</label>
                                <input v-model.number="form.discount_value" type="number" required min="0" step="0.01" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Start Date & Time *</label>
                            <input v-model="form.start_date_time" type="datetime-local" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">End Date & Time *</label>
                            <input v-model="form.end_date_time" type="datetime-local" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div class="pt-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input v-model="form.is_apply" type="checkbox" class="w-4 h-4 accent-emerald-500" />
                                <span class="text-sm text-slate-300 group-hover:text-white transition">Rule applies to POS sales</span>
                            </label>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button class="w-full py-2.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600 transition">Cancel</button>
                            </form>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition disabled:opacity-40">
                                {{ form.processing ? 'Saving...' : 'Save Rule' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</template>
