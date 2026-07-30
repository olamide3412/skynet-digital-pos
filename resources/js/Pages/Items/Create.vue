<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    categories:     Array,
    groupAddresses: Array,
})

const form = useForm({
    category_id:      '',
    group_address_id: '',
    item_name:        '',
    barcode_number:   '',
    qty:              0,
    buy_price:        0,
    price:            0,
    wholesale_price:  0,
    expiry_date:      '',
    item_description: '',
    price_locked:     false,
    image:            null,
})

function submit() {
    form.post(route('pos.items.store'))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.items.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </Link>
            <h1 class="text-lg font-bold text-white">New Item</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-2xl mx-auto space-y-5">
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Item Name *</label>
                        <input v-model="form.item_name" type="text" required
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        <p v-if="form.errors.item_name" class="text-red-400 text-xs mt-1">{{ form.errors.item_name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Barcode *</label>
                        <input v-model="form.barcode_number" type="text" required
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        <p v-if="form.errors.barcode_number" class="text-red-400 text-xs mt-1">{{ form.errors.barcode_number }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Category *</label>
                        <select v-model="form.category_id" required
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition">
                            <option value="">Select category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <p v-if="form.errors.category_id" class="text-red-400 text-xs mt-1">{{ form.errors.category_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Initial Qty</label>
                        <input v-model.number="form.qty" type="number" min="0"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Buy Price (₦) *</label>
                        <input v-model.number="form.buy_price" type="number" min="0" step="0.01" required
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Selling Price (₦) *</label>
                        <input v-model.number="form.price" type="number" min="0" step="0.01" required
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Wholesale Price (₦)</label>
                        <input v-model.number="form.wholesale_price" type="number" min="0" step="0.01"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Expiry Date</label>
                        <input v-model="form.expiry_date" type="date"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Description</label>
                        <input v-model="form.item_description" type="text" maxlength="255"
                            class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Item Image (Optional)</label>
                        <input type="file" @input="form.image = $event.target.files[0]" accept="image/*"
                            class="w-full text-slate-350 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 outline-none text-sm transition cursor-pointer" />
                        <p v-if="form.errors.image" class="text-red-400 text-xs mt-1">{{ form.errors.image }}</p>
                    </div>

                    <div class="col-span-2 flex items-center gap-2">
                        <input v-model="form.price_locked" type="checkbox" id="price_locked"
                            class="w-4 h-4 accent-emerald-500" />
                        <label for="price_locked" class="text-sm text-slate-300">Lock price (prevent cashier edits)</label>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link :href="route('pos.items.index')"
                        class="px-5 py-2.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600 transition">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition disabled:opacity-40">
                        {{ form.processing ? 'Saving…' : 'Save Item' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
