<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const form = useForm({
    name: '', phone: '', address: '', gender: '', dob: '', note: '',
    contact_name: '', contact_phone: '', contact_address: '', watch_list: false,
})

function submit() { form.post(route('pos.customers.store')) }
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.customers.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">New Customer</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-xl mx-auto space-y-5">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 grid grid-cols-2 gap-4 shadow-xs">
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                        <input v-model="form.name" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone *</label>
                        <input v-model="form.phone" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Gender</label>
                        <select v-model="form.gender" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition">
                            <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Select</option>
                            <option value="Male" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Male</option>
                            <option value="Female" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Female</option>
                            <option value="Other" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Date of Birth</label>
                        <input v-model="form.dob" type="date" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                        <input v-model="form.address" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Note</label>
                        <input v-model="form.note" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                    <div class="col-span-2 flex items-center gap-2 pt-1">
                        <input v-model="form.watch_list" type="checkbox" id="watch_list" class="w-4 h-4 accent-amber-500 cursor-pointer" />
                        <label for="watch_list" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">Add to watch list (flagged customer)</label>
                    </div>
                </div>
                <div class="flex gap-3">
                    <Link :href="route('pos.customers.index')" class="px-5 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-bold transition disabled:opacity-40">
                        {{ form.processing ? 'Saving…' : 'Create Customer' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
