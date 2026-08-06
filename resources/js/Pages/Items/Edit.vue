<script setup>
import { computed, watch } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    item:           Object,
    categories:     Array,
    groupAddresses: Array,
    settings:       Object,
})

const form = useForm({
    _method:                'PUT',
    category_id:            props.item.category_id,
    group_address_id:       props.item.group_address_id ?? '',
    item_name:              props.item.item_name,
    barcode_number:         props.item.barcode_number,
    front_store_qty:        props.item.front_store_qty ?? 0,
    back_store_qty:         props.item.back_store_qty ?? 0,
    buy_price:              props.item.buy_price,
    price:                  props.item.price,
    wholesale_price:        props.item.wholesale_price,
    pack_price:             props.item.pack_price ?? '',
    carton_price:           props.item.carton_price ?? '',
    pack_wholesale_price:   props.item.pack_wholesale_price ?? '',
    carton_wholesale_price: props.item.carton_wholesale_price ?? '',
    unit_label:             props.item.unit_label ?? 'Unit',
    pack_label:             props.item.pack_label ?? 'Pack',
    carton_label:           props.item.carton_label ?? 'Carton',
    units_per_pack:         props.item.units_per_pack ?? 1,
    packs_per_carton:       props.item.packs_per_carton ?? 1,
    expiry_date:            props.item.expiry_date ?? '',
    item_description:       props.item.item_description ?? '',
    price_locked:           props.item.price_locked,
    image:                  null,
})

// Auto-calculate retail and wholesale prices from Buy Price when profit percentage setting is ON
watch(() => form.buy_price, (newBuyPrice) => {
    if (props.settings?.is_use_profit_percentage && newBuyPrice > 0) {
        const retailPct    = Number(props.settings.consumer_profit_percent) || 15
        const wholesalePct = Number(props.settings.wholesale_profit_percent) || 10

        form.price           = Math.round((newBuyPrice * (1 + retailPct / 100)) * 100) / 100
        form.wholesale_price = Math.round((newBuyPrice * (1 + wholesalePct / 100)) * 100) / 100
    }
})

const totalUnitsPerCarton = computed(() => {
    const upp = Number(form.units_per_pack) || 1
    const ppc = Number(form.packs_per_carton) || 1
    return upp * ppc
})

// Calculate linear regular prices for comparison hints
const regularPackPrice = computed(() => {
    const unitPrice = Number(form.price) || 0
    const upp = Number(form.units_per_pack) || 1
    return unitPrice * upp
})

const regularCartonPrice = computed(() => {
    const unitPrice = Number(form.price) || 0
    return unitPrice * totalUnitsPerCarton.value
})

const packDiscount = computed(() => {
    if (!form.pack_price) return 0
    return regularPackPrice.value - Number(form.pack_price)
})

const cartonDiscount = computed(() => {
    if (!form.carton_price) return 0
    return regularCartonPrice.value - Number(form.carton_price)
})

function submit() {
    form.post(route('pos.items.update', props.item.id))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.items.index')" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </Link>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Edit Item — {{ item.item_name }}</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-3xl mx-auto space-y-6">
                
                <!-- 1. General Information -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                        General Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Item Name *</label>
                            <input v-model="form.item_name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p v-if="form.errors.item_name" class="text-red-500 text-xs mt-1">{{ form.errors.item_name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Barcode Number (Optional)</label>
                            <input v-model="form.barcode_number" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            <p v-if="form.errors.barcode_number" class="text-red-500 text-xs mt-1">{{ form.errors.barcode_number }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Category *</label>
                            <select v-model="form.category_id" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition">
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ cat.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Group / Address Storage (Optional)</label>
                            <select v-model="form.group_address_id"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition">
                                <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">None / Default</option>
                                <option v-for="g in groupAddresses" :key="g.id" :value="g.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ g.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expiry Date (Optional)</label>
                            <input v-model="form.expiry_date" type="date"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>
                </div>

                <!-- 2. Packaging & Unit Conversions -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            Packaging & Unit Conversions (Stock Count Math)
                        </h2>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Conversion used strictly for inventory counting</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Unit Label *</label>
                            <input v-model="form.unit_label" type="text" required placeholder="e.g. Sachet, Can, Pcs"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Single loose item</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pack / Roll Label *</label>
                            <input v-model="form.pack_label" type="text" required placeholder="e.g. Roll, Pack, Dozen"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Bundle of base units</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Carton / Box Label *</label>
                            <input v-model="form.carton_label" type="text" required placeholder="e.g. Carton, Box, Case"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Bundle of packs</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Units per {{ form.pack_label || 'Pack' }} *</label>
                            <input v-model.number="form.units_per_pack" type="number" min="1" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">How many {{ form.unit_label || 'Units' }} are in 1 {{ form.pack_label || 'Pack' }}?</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Packs per {{ form.carton_label || 'Carton' }} *</label>
                            <input v-model.number="form.packs_per_carton" type="number" min="1" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">How many {{ form.pack_label || 'Packs' }} are in 1 {{ form.carton_label || 'Carton' }}?</p>
                        </div>
                    </div>

                    <!-- Live Conversion Summary Badge -->
                    <div class="bg-slate-100 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700 p-3 rounded-lg flex items-center justify-between text-xs">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Stock Conversion Breakdown:</span>
                        <div class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                            1 {{ form.carton_label || 'Carton' }} = {{ form.packs_per_carton || 1 }} {{ form.pack_label || 'Pack' }} = <span class="text-slate-900 dark:text-white font-bold">{{ totalUnitsPerCarton }} {{ form.unit_label || 'Unit' }}s</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Independent Unit Level Selling Prices -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            Unit Level Selling Prices
                        </h2>
                        <span v-if="settings?.is_use_profit_percentage" class="text-xs text-emerald-600 dark:text-emerald-400 font-mono flex items-center gap-1 font-semibold">
                            ⚡ Auto Profit % Active (Retail {{ settings.consumer_profit_percent }}% | Wholesale {{ settings.wholesale_profit_percent }}%)
                        </span>
                        <span v-else class="text-xs text-slate-500 dark:text-slate-400 font-mono">Independent Prices</span>
                    </div>

                    <div class="space-y-4">
                        <!-- Cost / Buy Price -->
                        <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/60">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Cost / Buy Price per Base {{ form.unit_label || 'Unit' }} (₦) *</label>
                            <input v-model.number="form.buy_price" type="number" min="0" step="0.01" required
                                class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            <p v-if="settings?.is_use_profit_percentage" class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                                Typing Cost Price automatically updates Retail (+{{ settings.consumer_profit_percent }}%) and Wholesale (+{{ settings.wholesale_profit_percent }}%) prices.
                            </p>
                        </div>

                        <!-- Base Unit Price -->
                        <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/60 space-y-2">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ form.unit_label || 'Single Unit' }} Selling Price</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Price for 1 single {{ form.unit_label || 'unit' }}</div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Retail Price (₦) *</label>
                                    <input v-model.number="form.price" type="number" min="0" step="0.01" required
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Wholesale Price (₦)</label>
                                    <input v-model.number="form.wholesale_price" type="number" min="0" step="0.01"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                            </div>
                            <div v-if="form.buy_price > 0 && form.price > 0" class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-3 pt-1 border-t border-slate-200 dark:border-slate-800">
                                <span>Retail Profit: <strong class="text-emerald-600 dark:text-emerald-400">₦{{ (form.price - form.buy_price).toFixed(2) }}</strong> ({{ Math.round(((form.price - form.buy_price) / form.buy_price) * 100) }}%)</span>
                                <span v-if="form.wholesale_price > 0">Wholesale Profit: <strong class="text-emerald-600 dark:text-emerald-400">₦{{ (form.wholesale_price - form.buy_price).toFixed(2) }}</strong> ({{ Math.round(((form.wholesale_price - form.buy_price) / form.buy_price) * 100) }}%)</span>
                            </div>
                        </div>

                        <!-- Pack / Roll Price -->
                        <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/60 space-y-2">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div>
                                    <div class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">{{ form.pack_label || 'Pack / Roll' }} Selling Price</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Price for 1 {{ form.pack_label || 'pack' }} (Contains {{ form.units_per_pack || 1 }} {{ form.unit_label || 'units' }})</div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pack Retail Price (₦)</label>
                                    <input v-model.number="form.pack_price" type="number" min="0" step="0.01" placeholder="e.g. 2250"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pack Wholesale Price (₦)</label>
                                    <input v-model.number="form.pack_wholesale_price" type="number" min="0" step="0.01" placeholder="e.g. 2100"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                            </div>
                            <div v-if="form.pack_price && regularPackPrice > 0" class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 pt-1 border-t border-slate-200 dark:border-slate-800">
                                <span>Linear math ({{ form.units_per_pack }} × ₦{{ form.price }}): <strong class="text-slate-700 dark:text-slate-300">₦{{ regularPackPrice.toLocaleString() }}</strong></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold" v-if="packDiscount > 0">→ Bulk Discount Savings: ₦{{ packDiscount.toLocaleString() }} per {{ form.pack_label }}</span>
                            </div>
                        </div>

                        <!-- Carton Price -->
                        <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/60 space-y-2">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div>
                                    <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm">{{ form.carton_label || 'Carton / Box' }} Selling Price</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Price for 1 {{ form.carton_label || 'carton' }} (Contains {{ totalUnitsPerCarton }} {{ form.unit_label || 'units' }})</div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Carton Retail Price (₦)</label>
                                    <input v-model.number="form.carton_price" type="number" min="0" step="0.01" placeholder="e.g. 8500"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Carton Wholesale Price (₦)</label>
                                    <input v-model.number="form.carton_wholesale_price" type="number" min="0" step="0.01" placeholder="e.g. 8000"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                            </div>
                            <div v-if="form.carton_price && regularCartonPrice > 0" class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 pt-1 border-t border-slate-200 dark:border-slate-800">
                                <span>Linear math ({{ totalUnitsPerCarton }} × ₦{{ form.price }}): <strong class="text-slate-700 dark:text-slate-300">₦{{ regularCartonPrice.toLocaleString() }}</strong></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold" v-if="cartonDiscount > 0">→ Bulk Discount Savings: ₦{{ cartonDiscount.toLocaleString() }} per {{ form.carton_label }}</span>
                            </div>
                        </div>

                        <!-- Price Lock Checkbox -->
                        <div class="pt-2">
                            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900/60 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700/60">
                                <input v-model="form.price_locked" type="checkbox" id="price_locked"
                                    class="w-4 h-4 accent-emerald-500 rounded cursor-pointer flex-shrink-0" />
                                <label for="price_locked" class="text-xs text-slate-600 dark:text-slate-300 cursor-pointer select-none">
                                    <strong class="text-slate-900 dark:text-white font-semibold">Lock prices against profit margin updates</strong> — protects this item's selling prices from being automatically overwritten when global profit percentage targets are updated in POS Settings.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Inventory Stock Levels (Base Units) -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                        Inventory Stock Levels (In Base {{ form.unit_label || 'Units' }})
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Front Store Qty (POS Active Floor)</label>
                            <input v-model.number="form.front_store_qty" type="number" min="0"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Stock available immediately for sales at POS</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Back Store Qty (Warehouse)</label>
                            <input v-model.number="form.back_store_qty" type="number" min="0"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Stock held in warehouse storage room</p>
                        </div>
                    </div>
                </div>

                <!-- 5. Description & Image -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                        Additional Information
                    </h2>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Item Description</label>
                        <textarea v-model="form.item_description" rows="2" maxlength="500" placeholder="Optional notes or specifications..."
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition resize-none"></textarea>
                    </div>

                    <div class="flex items-center gap-4">
                        <div v-if="item.image_url" class="w-16 h-16 rounded-lg bg-slate-100 dark:bg-slate-700 overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-600 flex-shrink-0">
                            <img :src="item.image_url" class="object-cover w-full h-full" alt="Current Image" />
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Item Image (Optional)</label>
                            <input type="file" @input="form.image = $event.target.files[0]" accept="image/*"
                                class="w-full text-slate-600 dark:text-slate-300 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-200 dark:file:bg-slate-700 file:text-slate-800 dark:file:text-white hover:file:bg-slate-300 dark:hover:file:bg-slate-600 outline-none text-sm transition cursor-pointer" />
                            <p v-if="form.errors.image" class="text-red-500 text-xs mt-1">{{ form.errors.image }}</p>
                        </div>
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="flex gap-3">
                    <Link :href="route('pos.items.index')"
                        class="px-6 py-3 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-md shadow-emerald-900/20 transition disabled:opacity-40">
                        {{ form.processing ? 'Updating Item...' : 'Update Item' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
