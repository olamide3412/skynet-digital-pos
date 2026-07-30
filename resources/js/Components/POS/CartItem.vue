<script setup>
import { ref } from 'vue'
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    item:         { type: Object, required: true },
    canEditPrice: { type: Boolean, default: false },
})
const emit = defineEmits(['remove', 'update-qty', 'update-price'])
const { format } = useCurrency()
const editingPrice = ref(false)
const priceInput   = ref(props.item.unit_price)

function startEditPrice() {
    if (!props.canEditPrice || props.item.price_locked) return
    editingPrice.value = true
    priceInput.value   = props.item.unit_price
}

function commitPrice() {
    emit('update-price', priceInput.value)
    editingPrice.value = false
}
</script>

<template>
    <div class="flex items-center gap-2 px-3 py-2 hover:bg-slate-800/50 transition group">
        <!-- Item Info -->
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ item.item_name }}</p>
            <div class="flex items-center gap-2 mt-0.5">
                <!-- Price -->
                <button
                    v-if="!editingPrice"
                    @click="startEditPrice"
                    :title="canEditPrice && !item.price_locked ? 'Click to edit price' : ''"
                    :class="canEditPrice && !item.price_locked ? 'cursor-pointer hover:text-emerald-300' : 'cursor-default'"
                    class="text-xs text-emerald-400 font-mono transition"
                >{{ format(item.unit_price) }}</button>
                <input
                    v-else
                    v-model.number="priceInput"
                    @blur="commitPrice"
                    @keydown.enter="commitPrice"
                    @keydown.escape="editingPrice = false"
                    type="number"
                    min="0"
                    class="w-24 bg-slate-700 text-emerald-400 text-xs font-mono px-1 py-0.5 rounded outline-none border border-emerald-500"
                    autofocus
                />
                <span class="text-slate-500 text-xs">× {{ item.qty }}</span>
                <span class="text-slate-400 text-xs">= {{ format(item.line_total) }}</span>
            </div>
        </div>

        <!-- Qty Controls -->
        <div class="flex items-center gap-1 flex-shrink-0">
            <button
                @click="emit('update-qty', item.qty - 1)"
                :disabled="item.qty <= 1"
                class="w-6 h-6 rounded bg-slate-700 hover:bg-slate-600 text-white text-sm leading-none transition disabled:opacity-30"
            >−</button>
            <input
                :value="item.qty"
                @change="emit('update-qty', parseInt($event.target.value) || 1)"
                type="number"
                min="1"
                class="w-10 text-center bg-slate-700 text-white text-xs rounded py-0.5 outline-none"
            />
            <button
                @click="emit('update-qty', item.qty + 1)"
                class="w-6 h-6 rounded bg-slate-700 hover:bg-slate-600 text-white text-sm leading-none transition"
            >+</button>
        </div>

        <!-- Remove -->
        <button
            @click="emit('remove')"
            class="w-6 h-6 flex items-center justify-center rounded text-slate-500 hover:text-red-400 hover:bg-red-400/10 transition opacity-0 group-hover:opacity-100"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</template>
