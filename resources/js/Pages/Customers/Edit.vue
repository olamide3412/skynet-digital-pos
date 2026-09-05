<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    customer: Object,
})

const { format } = useCurrency()

const form = useForm({
    name:            props.customer?.name ?? '',
    phone:           props.customer?.phone ?? '',
    address:         props.customer?.address === 'NA' ? '' : (props.customer?.address ?? ''),
    gender:          props.customer?.gender ?? '',
    dob:             props.customer?.dob ? props.customer.dob.split('T')[0] : '',
    note:            props.customer?.note === 'No Note' ? '' : (props.customer?.note ?? ''),
    contact_name:    props.customer?.contact_name === 'NA' ? '' : (props.customer?.contact_name ?? ''),
    contact_phone:   props.customer?.contact_phone === 'NA' ? '' : (props.customer?.contact_phone ?? ''),
    contact_address: props.customer?.contact_address === 'NA' ? '' : (props.customer?.contact_address ?? ''),
    watch_list:      Boolean(props.customer?.watch_list),
})

function submit() {
    form.put(route('pos.customers.update', props.customer.id))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('pos.customers.index')" class="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white">Edit Customer Profile</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Update account info, contact details, and status for {{ customer.name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('pos.customers.debt-ledger', customer.id)"
                    class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                    <span>💳 Debt Ledger</span>
                    <span v-if="customer.debt_bal > 0" class="font-mono text-red-600 dark:text-red-400 font-bold">({{ format(customer.debt_bal) }})</span>
                </Link>
            </div>
        </div>

        <!-- Form Body -->
        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-2xl mx-auto space-y-5">
                
                <!-- Debt status alert if customer is owing -->
                <div v-if="customer.debt_bal > 0" class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/60 text-red-600 dark:text-red-400 flex items-center justify-center font-bold text-lg">
                            ⚠️
                        </div>
                        <div>
                            <div class="text-sm font-bold text-red-800 dark:text-red-300">Customer Owes Outstanding Debt</div>
                            <div class="text-xs text-red-600 dark:text-red-400">Current balance: <span class="font-mono font-bold">{{ format(customer.debt_bal) }}</span>. Customer record cannot be deleted until balance is fully paid.</div>
                        </div>
                    </div>
                    <Link :href="route('pos.customers.debt-ledger', customer.id)" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-bold rounded-lg transition shadow-xs">
                        View / Pay Debt
                    </Link>
                </div>

                <!-- Primary Details Card -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Personal Information</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input v-model="form.name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone Number *</label>
                            <input v-model="form.phone" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Gender</label>
                            <select v-model="form.gender"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <p v-if="form.errors.gender" class="text-red-500 text-xs mt-1">{{ form.errors.gender }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Date of Birth</label>
                            <input v-model="form.dob" type="date"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                            <p v-if="form.errors.dob" class="text-red-500 text-xs mt-1">{{ form.errors.dob }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Residential / Business Address</label>
                            <input v-model="form.address" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p v-if="form.errors.address" class="text-red-500 text-xs mt-1">{{ form.errors.address }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Internal Notes</label>
                            <textarea v-model="form.note" rows="2"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition"></textarea>
                            <p v-if="form.errors.note" class="text-red-500 text-xs mt-1">{{ form.errors.note }}</p>
                        </div>
                    </div>
                </div>

                <!-- Next of Kin / Guarantor Details Card -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Emergency / Guarantor Contact</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact / Guarantor Name</label>
                            <input v-model="form.contact_name" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Phone</label>
                            <input v-model="form.contact_phone" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Address</label>
                            <input v-model="form.contact_address" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>
                </div>

                <!-- Account Flags -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input v-model="form.watch_list" type="checkbox"
                            class="w-4 h-4 accent-amber-500 rounded cursor-pointer" />
                        <div>
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">Flag on Watch List / Blacklist Alert</span>
                            <p class="text-xs text-slate-500 dark:text-slate-400">When checked, cashiers will see a warning indicator when this customer is selected at checkout.</p>
                        </div>
                    </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('pos.customers.index')"
                        class="px-5 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold transition disabled:opacity-40 shadow-md shadow-emerald-900/20 cursor-pointer">
                        {{ form.processing ? 'Saving Changes…' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
