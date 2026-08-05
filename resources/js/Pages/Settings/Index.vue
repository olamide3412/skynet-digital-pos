<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({ settings: Object })

const form = useForm({
    business_name:            props.settings.business_name ?? '',
    business_address:         props.settings.business_address ?? '',
    business_contact_number:  props.settings.business_contact_number ?? '',
    business_email:           props.settings.business_email ?? '',
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

function submit() { form.put(route('pos.settings.update')) }
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">POS Settings</h1>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-2xl mx-auto space-y-5">

                <!-- Business Info -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-emerald-400 border-b border-slate-700 pb-2">Business Info</h2>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Business Name *</label>
                        <input v-model="form.business_name" type="text" required maxlength="50"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Contact Number</label>
                            <input v-model="form.business_contact_number" type="text"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Email</label>
                            <input v-model="form.business_email" type="email"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Address</label>
                        <input v-model="form.business_address" type="text" maxlength="100"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                </div>

                <!-- POS Behaviour -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-emerald-400 border-b border-slate-700 pb-2">POS Behaviour & Pricing Automation</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Sell Interface</label>
                            <select v-model="form.sell_interface"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm transition">
                                <option value="classic">Classic (Search Only)</option>
                                <option value="gallery">Gallery (Button Grid)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Business Sector</label>
                            <select v-model="form.business_sector"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm transition">
                                <option value="commerce">Commerce</option>
                                <option value="health">Health / Pharmacy</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Out of Stock Threshold</label>
                            <input v-model.number="form.out_of_stock" type="number" min="0"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Wholesale Profit Target (%)</label>
                            <input v-model.number="form.wholesale_profit_percent" type="number" min="0" step="0.01"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Retail / Consumer Profit Target (%)</label>
                            <input v-model.number="form.consumer_profit_percent" type="number" min="0" step="0.01"
                                class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>

                    <div class="space-y-3 pt-1">
                        <!-- Auto Profit Percentage Pricing Toggle -->
                        <label class="flex items-start justify-between cursor-pointer group bg-slate-900/60 p-3 rounded-lg border border-slate-700">
                            <div>
                                <span class="text-sm font-semibold text-emerald-400 group-hover:text-emerald-300 transition">Enable Profit Percentage Pricing & Auto-Update</span>
                                <p class="text-xs text-slate-400 mt-0.5">Automatically calculate selling prices from Cost Price when creating items. Updating profit % recalculates all non-locked items.</p>
                            </div>
                            <div class="relative flex-shrink-0 mt-0.5">
                                <input v-model="form.is_use_profit_percentage" type="checkbox" class="sr-only peer" />
                                <div class="w-10 h-5 bg-slate-600 peer-checked:bg-emerald-600 rounded-full transition-colors"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-5"></div>
                            </div>
                        </label>

                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-slate-300 group-hover:text-white transition">Allow cashiers to edit item prices</span>
                            <div class="relative">
                                <input v-model="form.is_price_editable" type="checkbox" class="sr-only peer" />
                                <div class="w-10 h-5 bg-slate-600 peer-checked:bg-emerald-600 rounded-full transition-colors"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-5"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-slate-300 group-hover:text-white transition">Deduct inventory on sale</span>
                            <div class="relative">
                                <input v-model="form.is_qty_deduction" type="checkbox" class="sr-only peer" />
                                <div class="w-10 h-5 bg-slate-600 peer-checked:bg-emerald-600 rounded-full transition-colors"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-5"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-slate-300 group-hover:text-white transition">Check expiry date on sale</span>
                            <div class="relative">
                                <input v-model="form.is_check_expiration" type="checkbox" class="sr-only peer" />
                                <div class="w-10 h-5 bg-slate-600 peer-checked:bg-emerald-600 rounded-full transition-colors"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-5"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm text-slate-300 group-hover:text-white transition">Show buy price to cashiers</span>
                            <div class="relative">
                                <input v-model="form.is_show_buy_price" type="checkbox" class="sr-only peer" />
                                <div class="w-10 h-5 bg-slate-600 peer-checked:bg-emerald-600 rounded-full transition-colors"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-5"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Tax Settings -->
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-emerald-400 border-b border-slate-700 pb-2">Tax Settings</h2>
                    
                    <label class="flex items-center justify-between cursor-pointer group">
                        <div>
                            <span class="text-sm text-slate-300 group-hover:text-white transition font-medium">Enable Tax Calculation</span>
                            <p class="text-xs text-slate-400">Automatically calculate and itemize tax on checkout</p>
                        </div>
                        <div class="relative">
                            <input v-model="form.is_tax_enabled" type="checkbox" class="sr-only peer" />
                            <div class="w-10 h-5 bg-slate-600 peer-checked:bg-emerald-600 rounded-full transition-colors"></div>
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <div v-if="form.is_tax_enabled" class="pt-2 border-t border-slate-700/50">
                        <label class="block text-xs text-slate-400 mb-1">Tax Percentage (%) *</label>
                        <div class="relative max-w-xs">
                            <input v-model.number="form.tax_percentage" type="number" min="0" max="100" step="0.01" required
                                class="w-full bg-slate-700 text-white px-3 py-2 pr-8 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <span class="absolute right-3 top-2 text-slate-400 text-sm font-bold">%</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Tax will be calculated on the post-discount subtotal at checkout.</p>
                    </div>
                </div>

                <!-- Flash message -->
                <div v-if="$page.props.flash?.success" class="text-emerald-400 text-sm bg-emerald-400/10 px-4 py-3 rounded-lg">
                    ✓ {{ $page.props.flash.success }}
                </div>
                <div v-if="form.hasErrors" class="text-red-400 text-sm bg-red-400/10 px-4 py-3 rounded-lg">
                    Please correct the errors above.
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-sm transition disabled:opacity-40">
                    {{ form.processing ? 'Saving…' : 'Save Settings' }}
                </button>
            </form>
        </div>
    </div>
</template>
