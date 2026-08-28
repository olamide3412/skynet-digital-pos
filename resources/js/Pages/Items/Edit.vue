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

const initialUnits = props.item?.units?.length
    ? props.item.units.map((u, i) => ({
        id:                     u.id,
        unit_name:              u.unit_name ?? '',
        quantity_in_base_units: u.quantity_in_base_units ?? 1,
        selling_price:          u.selling_price ?? '',
        wholesale_price:        u.wholesale_price ?? '',
        sort_order:             u.sort_order ?? i,
        status:                 u.status ?? 'active',
    }))
    : []

const form = useForm({
    _method:                'PUT',
    category_id:            props.item.category_id,
    group_address_id:       props.item.group_address_id ?? '',
    item_name:              props.item.item_name,
    barcode_number:         props.item.barcode_number ?? '',
    front_store_qty:        props.item.front_store_qty ?? 0,
    back_store_qty:         props.item.back_store_qty ?? 0,
    buy_price:              props.item.buy_price ?? '',
    price:                  props.item.price ?? '',
    wholesale_price:        props.item.wholesale_price ?? '',
    unit_label:             props.item.unit_label ?? 'Unit',
    expiry_date:            props.item.expiry_date ?? '',
    item_description:       props.item.item_description ?? '',
    price_locked:           props.item.price_locked,
    is_imei_tracked:        !!props.item.is_imei_tracked,
    image:                  null,
    units:                  initialUnits,
})

// Auto-calculate retail and wholesale prices from Buy Price when profit percentage setting is ON
watch(() => form.buy_price, (newBuyPrice) => {
    if (props.settings?.is_use_profit_percentage && newBuyPrice > 0 && !form.price_locked) {
        const retailPct    = Number(props.settings.consumer_profit_percent) || 15
        const wholesalePct = Number(props.settings.wholesale_profit_percent) || 10

        form.price           = Math.round((newBuyPrice * (1 + retailPct / 100)) * 100) / 100
        form.wholesale_price = Math.round((newBuyPrice * (1 + wholesalePct / 100)) * 100) / 100
    }
})

function addUnit() {
    form.units.push({
        id:                     null,
        unit_name:              '',
        quantity_in_base_units: 10,
        selling_price:          '',
        wholesale_price:        '',
        sort_order:             form.units.length + 1,
        status:                 'active',
    })
}

function removeUnit(index) {
    form.units.splice(index, 1)
}

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
                        Product Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Item Name *</label>
                            <input v-model="form.item_name" type="text" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            <p v-if="form.errors.item_name" class="text-red-500 text-xs mt-1">{{ form.errors.item_name }}</p>
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
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Storage Location / Shelf (Optional)</label>
                            <select v-model="form.group_address_id"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition">
                                <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">None / Default</option>
                                <option v-for="g in groupAddresses" :key="g.id" :value="g.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">{{ g.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Barcode Number (Item Level)
                            </label>
                            <input v-model="form.barcode_number" type="text" placeholder="Scan or leave blank (Generable via Barcodes menu)"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm font-mono transition" />
                            <p class="text-[11px] text-slate-400 mt-1">Barcode attaches to the item. If blank, you can generate one later from Barcode Studio.</p>
                            <p v-if="form.errors.barcode_number" class="text-red-500 text-xs mt-1">{{ form.errors.barcode_number }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expiry Date (Optional)</label>
                            <input v-model="form.expiry_date" type="date"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>
                </div>

                <!-- 2. Base Unit & Base Pricing -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            Base Unit & Base Pricing
                        </h2>
                        <span v-if="settings?.is_use_profit_percentage" class="text-xs text-emerald-600 dark:text-emerald-400 font-mono flex items-center gap-1 font-semibold">
                            ⚡ Auto Profit Active (Retail {{ settings.consumer_profit_percent }}% | Wholesale {{ settings.wholesale_profit_percent }}%)
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Unit Name *</label>
                                <input v-model="form.unit_label" type="text" required placeholder="e.g. Sachet, Bottle, Piece, Unit"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">The fundamental single unit of stock (e.g. 1 Sachet)</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Cost / Buy Price (₦) *</label>
                                <input v-model.number="form.buy_price" type="number" min="0" step="0.01" required
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Cost price per single base unit</p>
                            </div>
                        </div>

                        <!-- Base Unit Selling Prices -->
                        <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/60 space-y-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Retail Price (₦) *</label>
                                    <input v-model.number="form.price" type="number" min="0" step="0.01" required
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Base Wholesale Price (₦)</label>
                                    <input v-model.number="form.wholesale_price" type="number" min="0" step="0.01"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                                </div>
                            </div>
                            <div v-if="form.buy_price > 0 && form.price > 0" class="text-[11px] text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-3 pt-1 border-t border-slate-200 dark:border-slate-800">
                                <span>Retail Profit: <strong class="text-emerald-600 dark:text-emerald-400">₦{{ (form.price - form.buy_price).toFixed(2) }}</strong> ({{ Math.round(((form.price - form.buy_price) / form.buy_price) * 100) }}%)</span>
                                <span v-if="form.wholesale_price > 0">Wholesale Profit: <strong class="text-emerald-600 dark:text-emerald-400">₦{{ (form.wholesale_price - form.buy_price).toFixed(2) }}</strong> ({{ Math.round(((form.wholesale_price - form.buy_price) / form.buy_price) * 100) }}%)</span>
                            </div>
                        </div>

                        <!-- Price Lock Checkbox -->
                        <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900/60 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700/60">
                            <input v-model="form.price_locked" type="checkbox" id="price_locked"
                                class="w-4 h-4 accent-emerald-500 rounded cursor-pointer flex-shrink-0" />
                            <label for="price_locked" class="text-xs text-slate-600 dark:text-slate-300 cursor-pointer select-none">
                                <strong class="text-slate-900 dark:text-white font-semibold">Lock base prices against global margin updates</strong> — protects this item from auto-overwrites when global profit percentages change.
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 3. Additional Unit Variants (Flexible / Unlimited) -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                Additional Units / Variants (Optional)
                            </h2>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                Configure unlimited custom packaging tiers (e.g. "Half Roll", "Roll", "Carton", "Half Carton", "Dozen"). Cashiers can select any variant at checkout.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="addUnit"
                            class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 rounded-lg text-xs font-bold flex items-center gap-1 transition cursor-pointer"
                        >
                            <span>+</span> Add Unit Level
                        </button>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!form.units.length" class="text-center py-6 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/30">
                        <p class="text-xs text-slate-500 dark:text-slate-400">No additional variants configured. This item is sold solely by single <strong>{{ form.unit_label || 'Unit' }}</strong>.</p>
                        <button
                            type="button"
                            @click="addUnit"
                            class="mt-2 text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline cursor-pointer"
                        >
                            + Add an additional packaging level (e.g. Roll or Carton)
                        </button>
                    </div>

                    <!-- Repeatable Variant Cards -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="(unit, idx) in form.units"
                            :key="idx"
                            class="p-4 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/50 space-y-3 relative group"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 inline-flex items-center justify-center text-[10px]">
                                        {{ idx + 1 }}
                                    </span>
                                    Variant Level #{{ idx + 1 }}
                                </span>
                                <button
                                    type="button"
                                    @click="removeUnit(idx)"
                                    class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 text-xs transition p-1 cursor-pointer"
                                    title="Remove this unit variant"
                                >
                                    🗑️ Remove
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <!-- Variant Name -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Variant Name *</label>
                                    <input
                                        v-model="unit.unit_name"
                                        type="text"
                                        required
                                        placeholder="e.g. Roll, Carton, Half Roll"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none focus:border-indigo-500"
                                    />
                                </div>

                                <!-- Conversion: Whole number integer -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Qty in {{ form.unit_label || 'Base Units' }} *
                                    </label>
                                    <input
                                        v-model.number="unit.quantity_in_base_units"
                                        type="number"
                                        min="1"
                                        step="1"
                                        required
                                        placeholder="e.g. 10"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none focus:border-indigo-500 font-mono"
                                    />
                                </div>

                                <!-- Selling Price -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Retail Price (₦) *</label>
                                    <input
                                        v-model.number="unit.selling_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        placeholder="e.g. 2250"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none focus:border-indigo-500 font-mono"
                                    />
                                </div>

                                <!-- Wholesale Price -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Wholesale Price (₦)</label>
                                    <input
                                        v-model.number="unit.wholesale_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="e.g. 2000"
                                        class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none focus:border-indigo-500 font-mono"
                                    />
                                </div>
                            </div>

                            <!-- Plain language explanation & comparison hint -->
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-200 dark:border-slate-700/60">
                                <span>
                                    1 {{ unit.unit_name || 'Variant' }} = <strong>{{ unit.quantity_in_base_units || 1 }} × {{ form.unit_label || 'Unit' }}s</strong>
                                </span>
                                <span v-if="form.price > 0 && unit.quantity_in_base_units > 1" class="font-mono text-slate-600 dark:text-slate-300">
                                    Linear value: ₦{{ ((unit.quantity_in_base_units || 1) * form.price).toLocaleString() }}
                                    <span v-if="unit.selling_price && unit.selling_price < ((unit.quantity_in_base_units || 1) * form.price)" class="text-emerald-600 dark:text-emerald-400 font-semibold ml-1">
                                        (Bulk savings: ₦{{ (((unit.quantity_in_base_units || 1) * form.price) - unit.selling_price).toLocaleString() }})
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. IMEI / Device ID Tracking (When Enabled for Branch) -->
                <div v-if="settings?.is_imei_enabled" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            📱 IMEI / Serial / Device ID Tracking
                        </h2>
                        <span class="text-xs text-slate-500">For gadgets, smartphones, laptops & electronics</span>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                        <input type="checkbox" v-model="form.is_imei_tracked" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                        <div>
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">Track this item by unique IMEI / Serial Number</span>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Each physical unit sold will require selecting or scanning its specific serial/IMEI at checkout.</p>
                        </div>
                    </label>
                </div>

                <!-- 5. Inventory Stock Levels (Base Units) -->
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

                <!-- 6. Description & Image -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                        Additional Information
                    </h2>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Item Description</label>
                        <textarea v-model="form.item_description" rows="2" maxlength="500" placeholder="Optional notes or specifications..."
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Item Image (Optional)</label>
                        <div v-if="item.image_url" class="mb-2">
                            <img :src="item.image_url" class="w-16 h-16 object-cover rounded-lg border border-slate-200 dark:border-slate-700" alt="Current image" />
                        </div>
                        <input type="file" @input="form.image = $event.target.files[0]" accept="image/*"
                            class="w-full text-slate-600 dark:text-slate-300 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-200 dark:file:bg-slate-700 file:text-slate-800 dark:file:text-white hover:file:bg-slate-300 dark:hover:file:bg-slate-600 outline-none text-sm transition cursor-pointer" />
                        <p v-if="form.errors.image" class="text-red-500 text-xs mt-1">{{ form.errors.image }}</p>
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="flex gap-3">
                    <Link :href="route('pos.items.index')"
                        class="px-6 py-3 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-md shadow-emerald-900/20 transition disabled:opacity-40 cursor-pointer">
                        {{ form.processing ? 'Saving Changes...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
