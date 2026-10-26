<script setup>
import { ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/LaundryLayout.vue'

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    branch: {
        type: Object,
        required: true,
    },
    can_manage: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

import { computed } from 'vue'

const form = ref({
    default_turnaround_hours: props.settings.default_turnaround_hours || 48,
    unclaimed_after_days:     props.settings.unclaimed_after_days || 14,
    order_prefix:             props.settings.order_prefix || 'LDY-',
    whatsapp_template:        props.settings.whatsapp_template || 'Hello {customer_name}, your laundry order #{order_number} is ready for pickup at {branch_name}! Outstanding balance: {balance}. Thank you!',
    default_country_code:     props.settings.default_country_code || '234',
    receipt_footer:           props.settings.receipt_footer || 'Thank you for choosing us! Please present this ticket at pickup.',
    allow_price_override:     Boolean(props.settings.allow_price_override),
    allow_unpaid_pickup:      Boolean(props.settings.allow_unpaid_pickup),
    default_interface:        props.branch.default_interface || 'pos',
})

const isSaving = ref(false)

const saveSettings = () => {
    isSaving.value = true
    router.post(safeRoute('laundry.settings.update'), form.value, {
        onFinish: () => {
            isSaving.value = false
        },
    })
}
</script>

<template>
    <LaundryLayout title="Laundry Settings">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <h2 class="text-xl font-black text-slate-900 dark:text-white">
                    Laundry Module Configuration
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Configure operational policies, WhatsApp alerts, order numbering, and ticket footers.
                </p>
            </div>

            <!-- Settings Form Card -->
            <form @submit.prevent="saveSettings" class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-6">

                <!-- 1. General Turnaround & Prefixes -->
                <div class="space-y-4">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        Operational Turnaround & Numbering
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Default Turnaround (Hours)
                            </label>
                            <input
                                v-model.number="form.default_turnaround_hours"
                                type="number"
                                min="1"
                                max="720"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">48 hours = 2 days turnaround</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Unclaimed After (Days)
                            </label>
                            <input
                                v-model.number="form.unclaimed_after_days"
                                type="number"
                                min="1"
                                max="365"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Days left ready before flagged unclaimed</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Order Number Prefix
                            </label>
                            <input
                                v-model="form.order_prefix"
                                type="text"
                                maxlength="10"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-black uppercase"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">e.g. LDY- yielding LDY-000123</p>
                        </div>
                    </div>
                </div>

                <!-- 2. Interface Preferences (Mixed store) -->
                <div v-if="branch.laundry_enabled && branch.pos_enabled" class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        Default Interface Preference
                    </h3>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Default Interface After Staff Login
                        </label>
                        <select
                            v-model="form.default_interface"
                            class="w-full sm:w-64 px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold"
                        >
                            <option value="pos">Retail POS Terminal</option>
                            <option value="laundry">Laundry Terminal</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">
                            When staff log in, they will automatically land on this interface. They can switch anytime from the sidebar.
                        </p>
                    </div>
                </div>

                <!-- 3. WhatsApp Notification Template -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        WhatsApp Click-to-Chat Alert Template
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Country Code
                            </label>
                            <input
                                v-model="form.default_country_code"
                                type="text"
                                placeholder="234"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-bold"
                            />
                            <p class="text-[10px] text-slate-400 mt-1">Replaces leading 0 for WhatsApp links</p>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Ready Message Template
                            </label>
                            <textarea
                                v-model="form.whatsapp_template"
                                rows="3"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50"
                            ></textarea>
                            <p class="text-[10px] text-slate-400 mt-0.5">
                                Placeholders: <code class="text-indigo-600">{customer_name}</code>, <code class="text-indigo-600">{order_number}</code>, <code class="text-indigo-600">{branch_name}</code>, <code class="text-indigo-600">{balance}</code>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 4. Ticket Footer & Operational Policies -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-4">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        Ticket & Staff Policies
                    </h3>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Receipt / Ticket Footer Disclaimer
                        </label>
                        <input
                            v-model="form.receipt_footer"
                            type="text"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 cursor-pointer">
                            <input v-model="form.allow_price_override" type="checkbox" class="mt-0.5 rounded text-indigo-600" />
                            <div class="text-xs">
                                <span class="font-bold text-slate-900 dark:text-white block">Allow Line Price Override</span>
                                <span class="text-slate-400 text-[11px]">Permit authorized attendants to edit catalog item prices on intake.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 cursor-pointer">
                            <input v-model="form.allow_unpaid_pickup" type="checkbox" class="mt-0.5 rounded text-indigo-600" />
                            <div class="text-xs">
                                <span class="font-bold text-slate-900 dark:text-white block">Allow Unpaid Order Pickup</span>
                                <span class="text-slate-400 text-[11px]">Allow handing over clothes before full payment, posting balance to customer debt ledger.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                    <button
                        :disabled="isSaving"
                        type="submit"
                        class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs sm:text-sm shadow-md shadow-indigo-600/20 transition flex items-center gap-2"
                    >
                        <svg v-if="isSaving" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ isSaving ? 'Saving Settings...' : 'Save Configuration' }}</span>
                    </button>
                </div>

            </form>

        </div>
    </LaundryLayout>
</template>
