<script setup>
import { ref, computed } from 'vue'
import { useCurrency } from '@/Composables/useCurrency'
import { useCartStore } from '@/Stores/cart'

const props = defineProps({
    item:         { type: Object, required: true },
    canEditPrice: { type: Boolean, default: false },
})

const emit = defineEmits(['remove', 'update-qty', 'update-price'])
const { format } = useCurrency()
const cartStore  = useCartStore()

const editingPrice = ref(false)
const priceInput   = ref(props.item.unit_price)

const currentUnitLabel = computed(() => {
    switch (props.item.unit_used) {
        case 'carton': return props.item.carton_label || 'Carton'
        case 'pack':   return props.item.pack_label || 'Pack'
        default:       return props.item.unit_label || 'Unit'
    }
})

const baseUnitsDeducted = computed(() => {
    const qty = Number(props.item.qty) || 1
    const upp = Number(props.item.units_per_pack) || 1
    const ppc = Number(props.item.packs_per_carton) || 1

    switch (props.item.unit_used) {
        case 'carton': return qty * ppc * upp
        case 'pack':   return qty * upp
        default:       return qty
    }
})

function startEditPrice() {
    if (!props.canEditPrice) return
    editingPrice.value = true
    priceInput.value   = props.item.unit_price
}

function commitPrice() {
    emit('update-price', priceInput.value)
    editingPrice.value = false
}

function onUnitChange(newUnit) {
    cartStore.switchUnit(props.item.cart_key || props.item.item_id, newUnit)
}
</script>

<template>
    <div class="p-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition group space-y-1.5 border-b border-slate-200 dark:border-slate-700/50">
        <!-- Top Row: Name & Remove -->
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ item.item_name }}</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-mono bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                    {{ currentUnitLabel }}
                </span>
            </div>

            <!-- Remove Button -->
            <button
                @click="emit('remove')"
                class="w-5 h-5 flex items-center justify-center rounded text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-400/10 transition"
                title="Remove item"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Unit Level Selector Pills -->
        <div class="flex items-center gap-1 text-[11px]">
            <span class="text-slate-500 dark:text-slate-400 mr-1">Unit Level:</span>
            <button
                type="button"
                @click="onUnitChange('unit')"
                :class="item.unit_used === 'unit' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-650'"
                class="px-2 py-0.5 rounded transition text-[11px]"
            >
                {{ item.unit_label || 'Unit' }}
            </button>
            <button
                type="button"
                @click="onUnitChange('pack')"
                :class="item.unit_used === 'pack' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-650'"
                class="px-2 py-0.5 rounded transition text-[11px]"
            >
                {{ item.pack_label || 'Pack' }}
            </button>
            <button
                type="button"
                @click="onUnitChange('carton')"
                :class="item.unit_used === 'carton' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-650'"
                class="px-2 py-0.5 rounded transition text-[11px]"
            >
                {{ item.carton_label || 'Carton' }}
            </button>
        </div>

        <!-- Bottom Row: Price, Qty Controls & Total -->
        <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
            <!-- Price Display / Input -->
            <div class="flex items-center gap-1">
                <button
                    v-if="!editingPrice"
                    @click="startEditPrice"
                    :title="canEditPrice ? 'Click to edit price' : ''"
                    :class="canEditPrice ? 'cursor-pointer hover:text-emerald-700 dark:hover:text-emerald-300' : 'cursor-default'"
                    class="text-xs text-emerald-600 dark:text-emerald-400 font-mono font-bold transition"
                >
                    {{ format(item.unit_price) }}
                </button>
                <input
                    v-else
                    v-model.number="priceInput"
                    @blur="commitPrice"
                    @keydown.enter="commitPrice"
                    @keydown.escape="editingPrice = false"
                    type="number"
                    min="0"
                    class="w-20 bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 text-xs font-mono px-1 py-0.5 rounded outline-none border border-emerald-500"
                    autofocus
                />
                <span class="text-slate-500 dark:text-slate-400 text-xs">/ {{ currentUnitLabel }}</span>
            </div>

            <!-- Qty Stepper Controls -->
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click="emit('update-qty', item.qty - 1)"
                    :disabled="item.qty <= 1"
                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm font-bold transition disabled:opacity-30 flex-shrink-0"
                >−</button>
                <input
                    :value="item.qty"
                    @change="emit('update-qty', parseInt($event.target.value) || 1)"
                    type="number"
                    min="1"
                    class="w-14 text-center bg-white dark:bg-slate-750 text-slate-900 dark:text-white text-xs font-bold font-mono rounded-lg py-1 px-1 outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                />
                <button
                    type="button"
                    @click="emit('update-qty', item.qty + 1)"
                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm font-bold transition flex-shrink-0"
                >+</button>
            </div>

            <!-- Line Total -->
            <div class="text-right">
                <div class="text-sm font-bold text-slate-900 dark:text-white font-mono">{{ format(item.line_total) }}</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400" title="Base units deducted from inventory stock">
                    -{{ baseUnitsDeducted }} {{ item.unit_label || 'units' }}
                </div>
            </div>
        </div>
    </div>
</template>
