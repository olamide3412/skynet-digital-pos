<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({ settings: Object })

const form = useForm({
    _method:                  'PUT',
    business_name:            props.settings.business_name ?? '',
    business_address:         props.settings.business_address ?? '',
    business_contact_number:  props.settings.business_contact_number ?? '',
    business_email:           props.settings.business_email ?? '',
    logo:                     null,
    sell_interface:           props.settings.sell_interface ?? 'classic',
    is_price_editable:        !!props.settings.is_price_editable,
    is_qty_deduction:         !!props.settings.is_qty_deduction,
    out_of_stock:             props.settings.out_of_stock ?? 25,
    is_check_expiration:      !!props.settings.is_check_expiration,
    is_show_buy_price:        !!props.settings.is_show_buy_price,
    is_use_profit_percentage: !!props.settings.is_use_profit_percentage,
    is_tax_enabled:           !!props.settings.is_tax_enabled,
    tax_percentage:           props.settings.tax_percentage ?? 7.5,
    wholesale_profit_percent: props.settings.wholesale_profit_percent ?? 10,
    consumer_profit_percent:  props.settings.consumer_profit_percent ?? 15,
    business_sector:          props.settings.business_sector ?? 'commerce',
})

function submit() {
    form.post(route('pos.settings.update'), {
        forceFormData: true,
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">POS Settings</h1>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-2xl mx-auto space-y-5">

                <!-- Business Info -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">Business & Branch Branding</h2>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Business Name *</label>
                        <input v-model="form.business_name" type="text" required maxlength="100"
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        <p v-if="form.errors.business_name" class="text-red-500 text-xs mt-1">{{ form.errors.business_name }}</p>
                    </div>

                    <!-- Branch Logo Upload -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Logo (Displays on Login Page & Receipts)</label>
                        <div class="flex items-center gap-4">
                            <div v-if="settings.logo_url" class="w-14 h-14 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 overflow-hidden flex items-center justify-center flex-shrink-0">
                                <img :src="settings.logo_url" class="w-full h-full object-contain" alt="Branch Logo" />
                            </div>
                            <div class="flex-1">
                                <input
                                    type="file"
                                    accept="image/*"
                                    @input="form.logo = $event.target.files[0]"
                                    class="w-full text-slate-600 dark:text-slate-300 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-200 dark:file:bg-slate-700 file:text-slate-800 dark:file:text-white hover:file:bg-slate-300 dark:hover:file:bg-slate-600 outline-none text-xs transition cursor-pointer"
                                />
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Uploaded logo will automatically customize your branch login screen and receipts.</p>
                                <p v-if="form.errors.logo" class="text-red-500 text-xs mt-1">{{ form.errors.logo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Number</label>
                            <input v-model="form.business_contact_number" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                            <input v-model="form.business_email" type="email"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                        <input v-model="form.business_address" type="text" maxlength="255"
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                </div>

                <!-- POS Behaviour -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">POS Behaviour & Pricing Automation</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">POS Interface Style</label>
                            <select v-model="form.sell_interface"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition">
                                <option value="classic" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Classic Table View</option>
                                <option value="gallery" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Visual Grid View</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Out of Stock Alert Threshold</label>
                            <input v-model.number="form.out_of_stock" type="number" min="0" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        </div>
                    </div>

                    <!-- Automation Toggles -->
                    <div class="space-y-3 pt-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_use_profit_percentage" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Auto-Calculate Selling Prices from Cost</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Automatically computes retail & wholesale prices whenever buy price changes</p>
                            </div>
                        </label>

                        <div v-if="form.is_use_profit_percentage" class="grid grid-cols-2 gap-4 p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Consumer Retail Margin (%)</label>
                                <input v-model.number="form.consumer_profit_percent" type="number" min="0" step="0.1" required
                                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Wholesale Margin (%)</label>
                                <input v-model.number="form.wholesale_profit_percent" type="number" min="0" step="0.1" required
                                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            </div>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_price_editable" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Allow Cashier Price Override</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Cashiers can edit unit selling price directly on the checkout cart</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_qty_deduction" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Enable Auto Stock Deduction</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Deducts inventory levels automatically when sales are completed</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_check_expiration" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Enable Expiry Date Alerts</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Warns cashiers if an item is expiring soon during checkout</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl shadow-md shadow-emerald-900/20 transition disabled:opacity-40">
                        {{ form.processing ? 'Saving Settings…' : 'Save POS Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
