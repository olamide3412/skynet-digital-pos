<script setup>
import { ref, computed, onMounted } from 'vue'
import { useCurrency } from '@/Composables/useCurrency'
import { useCartStore } from '@/Stores/cart'
import { useOfflineSync } from '@/Composables/useOfflineSync'
import { getLocalAvailableImeis } from '@/Services/offlineDb'
import axios from 'axios'

const props = defineProps({
    item:          { type: Object, required: true },
    canEditPrice:  { type: Boolean, default: false },
    isHighlighted: { type: Boolean, default: false },
})

const emit = defineEmits(['remove', 'update-qty', 'update-price'])
const { format } = useCurrency()
const { isOnline } = useOfflineSync()
const cartStore  = useCartStore()

const editingPrice = ref(false)
const priceInput   = ref(props.item.unit_price)

const isImeiItem = computed(() => !!props.item.is_imei_tracked)
const localAvailableImeis = ref(props.item.available_imeis || [])
const loadingImeis = ref(false)

async function fetchFreshImeis() {
    if (!isImeiItem.value || !props.item.item_id) return
    loadingImeis.value = true

    // 1. Instant local lookup (0ms)
    try {
        const localImeis = await getLocalAvailableImeis(props.item.item_id)
        if (localImeis && localImeis.length > 0) {
            localAvailableImeis.value = localImeis
        }
    } catch (e) {}

    const isDeviceOffline = !isOnline.value || (typeof navigator !== 'undefined' && !navigator.onLine)
    if (isDeviceOffline) {
        loadingImeis.value = false
        return
    }

    try {
        const res = await axios.get(route('pos.api.items.available-imeis'), {
            params: { item_id: props.item.item_id },
            timeout: 2500,
        })
        if (res.data && Array.isArray(res.data.imeis)) {
            localAvailableImeis.value = res.data.imeis
                .filter(u => u.status === 'in_stock')
                .map(u => u.imei_or_device_id)
            
            // Only unselect if server explicitly returned available in-stock list and current item is not in it
            if (props.item.selected_imei && localAvailableImeis.value.length > 0 && !localAvailableImeis.value.includes(props.item.selected_imei)) {
                cartStore.setItemImei(props.item.cart_key || props.item.item_id, null)
            }
        }
    } catch (e) {
        // Already loaded local IMEIs
    } finally {
        loadingImeis.value = false
    }
}

onMounted(() => {
    fetchFreshImeis()
})

const selectableImeis = computed(() => {
    // Filter out IMEIs already selected by other lines in the cart
    const otherSelected = cartStore.items
        .filter(i => i.cart_key !== props.item.cart_key && i.item_id === props.item.item_id && i.selected_imei)
        .map(i => i.selected_imei)

    return localAvailableImeis.value.filter(imei => !otherSelected.includes(imei))
})

function onImeiSelected(val) {
    cartStore.setItemImei(props.item.cart_key || props.item.item_id, val || null)
}

const availableUnits = computed(() => {
    const list = [
        { key: 'unit', label: props.item.unit_label || 'Unit', multiplier: 1 }
    ]

    const itemUnits = props.item.units || props.item.raw_item?.units
    if (Array.isArray(itemUnits) && itemUnits.length > 0) {
        itemUnits.forEach(u => {
            if (u.unit_name) {
                list.push({
                    key:        u.unit_name,
                    label:      u.unit_name,
                    multiplier: Number(u.quantity_in_base_units) || 1,
                })
            }
        })
    } else {
        // Fallback to legacy pack & carton
        if (Number(props.item.units_per_pack) > 1) {
            list.push({
                key:        'pack',
                label:      props.item.pack_label || 'Pack',
                multiplier: Number(props.item.units_per_pack),
            })
        }
        if (Number(props.item.packs_per_carton) > 1) {
            const upp = Number(props.item.units_per_pack) || 1
            const ppc = Number(props.item.packs_per_carton) || 1
            list.push({
                key:        'carton',
                label:      props.item.carton_label || 'Carton',
                multiplier: upp * ppc,
            })
        }
    }
    return list
})

const currentUnitLabel = computed(() => {
    const uUsed = (props.item.unit_used || 'unit').toLowerCase().trim()
    const found = availableUnits.value.find(u => u.key.toLowerCase().trim() === uUsed || u.label.toLowerCase().trim() === uUsed)
    return found ? found.label : (props.item.unit_used || props.item.unit_label || 'Unit')
})

const baseUnitsDeducted = computed(() => {
    const qty   = Number(props.item.qty) || 1
    const uUsed = (props.item.unit_used || 'unit').toLowerCase().trim()
    const found = availableUnits.value.find(u => u.key.toLowerCase().trim() === uUsed || u.label.toLowerCase().trim() === uUsed)
    return qty * (found?.multiplier || 1)
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
    <div
        :id="'cart-row-' + item.cart_key"
        :class="[
            isHighlighted 
                ? 'cart-item-flash shadow-lg' 
                : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'
        ]"
        class="p-3 group space-y-2 border-b border-slate-200 dark:border-slate-700/50 rounded-lg mx-1 my-0.5 transition-colors duration-500"
    >
        <!-- Top Row: Name, Badge & Remove -->
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ item.item_name }}</span>
                <span v-if="!isImeiItem" class="px-1.5 py-0.5 rounded text-[10px] uppercase font-mono bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                    {{ currentUnitLabel }}
                </span>
                <span v-else class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
                    📱 Serial Unit
                </span>
            </div>

            <!-- Remove Button -->
            <button
                @click="emit('remove')"
                class="w-5 h-5 flex items-center justify-center rounded text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-400/10 transition cursor-pointer"
                title="Remove item"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- IMEI / Device ID Selection Row (For IMEI Tracked Items) -->
        <div v-if="isImeiItem" class="p-2 rounded-lg bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 space-y-1.5">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-indigo-900 dark:text-indigo-200 flex items-center gap-1">
                    <span>📱</span> Device IMEI / Serial:
                </span>
                <span 
                    v-if="!item.selected_imei"
                    class="text-[10px] font-bold text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded"
                >
                    ⚠️ Unassigned
                </span>
                <span 
                    v-else
                    class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded font-mono"
                >
                    ✓ Assigned
                </span>
            </div>

            <div class="flex items-center gap-2">
                <select
                    :value="item.selected_imei || ''"
                    @change="onImeiSelected($event.target.value)"
                    class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-2.5 py-1.5 rounded border border-indigo-200 dark:border-indigo-700 text-xs font-mono outline-none focus:border-indigo-500 shadow-xs cursor-pointer"
                >
                    <option value="">-- Select In-Stock IMEI ({{ selectableImeis.length }} available) --</option>
                    <option v-if="item.selected_imei && !selectableImeis.includes(item.selected_imei)" :value="item.selected_imei">
                        {{ item.selected_imei }} (Current)
                    </option>
                    <option v-for="imei in selectableImeis" :key="imei" :value="imei">
                        {{ imei }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Unit Level Selector Pills (Only for non-IMEI items when variants available) -->
        <div v-if="!isImeiItem && availableUnits.length > 1" class="flex flex-wrap items-center gap-1 text-[11px]">
            <span class="text-slate-500 dark:text-slate-400 mr-1 font-medium">Unit Level:</span>
            <button
                v-for="u in availableUnits"
                :key="u.key"
                type="button"
                @click="onUnitChange(u.key)"
                :class="((item.unit_used || 'unit').toLowerCase() === u.key.toLowerCase() || (item.unit_used || 'unit').toLowerCase() === u.label.toLowerCase()) ? 'bg-emerald-600 text-white font-bold shadow-2xs' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600'"
                class="px-2 py-0.5 rounded transition text-[11px] cursor-pointer"
            >
                {{ u.label }}
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
                <span class="text-slate-500 dark:text-slate-400 text-xs">/ {{ isImeiItem ? 'Unit' : currentUnitLabel }}</span>
            </div>

            <!-- Qty Stepper Controls (Disabled for IMEI items since 1 line = 1 unit) -->
            <div v-if="!isImeiItem" class="flex items-center gap-1">
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
                    class="w-14 text-center bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-xs font-bold font-mono rounded-lg py-1 px-1 outline-none border border-slate-300 dark:border-slate-600 focus:border-emerald-500 transition [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                />
                <button
                    type="button"
                    @click="emit('update-qty', item.qty + 1)"
                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm font-bold transition flex-shrink-0"
                >+</button>
            </div>
            <div v-else class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400 px-2 py-1 bg-slate-100 dark:bg-slate-800 rounded">
                Qty: 1
            </div>

            <!-- Line Total -->
            <div class="text-right">
                <div class="text-sm font-bold text-slate-900 dark:text-white font-mono">{{ format(item.line_total) }}</div>
                <div v-if="!isImeiItem" class="text-[10px] text-slate-500 dark:text-slate-400" title="Base units deducted from inventory stock">
                    -{{ baseUnitsDeducted }} {{ item.unit_label || 'units' }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes cartFlashAnim {
    0% {
        background-color: var(--color-theme-light, rgba(123, 0, 255, 0.35));
        transform: scale(1.02);
        box-shadow: 0 0 18px var(--color-theme-light, rgba(123, 0, 255, 0.5));
    }
    40% {
        background-color: var(--color-theme-light, rgba(123, 0, 255, 0.2));
        transform: scale(1.01);
        box-shadow: 0 0 10px var(--color-theme-light, rgba(123, 0, 255, 0.3));
    }
    100% {
        background-color: transparent;
        transform: scale(1);
    }
}

.cart-item-flash {
    animation: cartFlashAnim 1.15s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    outline: 2px solid var(--color-theme, #7B00FF);
    outline-offset: -1px;
}
</style>
