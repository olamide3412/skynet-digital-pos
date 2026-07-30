<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({ items: Array })

const form = useForm({
    item_id: '',
    type: 'Addition',
    qty: 1,
    reason: '',
})

function submit() {
    form.post(route('pos.inventory.process-adjust'))
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <Link :href="route('pos.inventory.index')" class="text-slate-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Manual Stock Adjustment</h1>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-xl mx-auto space-y-5">
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-amber-400 text-sm">
                    <strong>Warning:</strong> Manual adjustments directly impact inventory levels without associated sales or purchase records. Always provide a clear reason for the adjustment (e.g., Damage, Expiry, Stock-take correction).
                </div>

                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Item *</label>
                        <select v-model="form.item_id" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                            <option value="">Select an Item...</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">
                                {{ item.item_name }} (Current: {{ item.qty }})
                            </option>
                        </select>
                        <p v-if="form.errors.item_id" class="text-red-400 text-xs mt-1">{{ form.errors.item_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Adjustment Type *</label>
                            <select v-model="form.type" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition">
                                <option value="Addition">Addition (+)</option>
                                <option value="Subtraction">Subtraction (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Quantity *</label>
                            <input v-model.number="form.qty" type="number" min="1" required class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition" />
                            <p v-if="form.errors.qty" class="text-red-400 text-xs mt-1">{{ form.errors.qty }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Reason *</label>
                        <textarea v-model="form.reason" rows="3" required placeholder="e.g. Broken item identified during cycle count" class="w-full bg-slate-700 text-white px-3 py-2 rounded-lg border border-slate-600 outline-none text-sm focus:border-emerald-500 transition resize-none"></textarea>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link :href="route('pos.inventory.index')" class="flex-1 text-center py-3 bg-slate-700 text-slate-300 rounded-xl text-sm hover:bg-slate-600 transition">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold transition disabled:opacity-40">
                        {{ form.processing ? 'Processing...' : 'Confirm Adjustment' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
