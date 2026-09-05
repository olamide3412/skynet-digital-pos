<script setup>
import { ref, computed } from 'vue'
import { useCartStore } from '@/Stores/cart'
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    activeDiscount: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close'])

const cart = useCartStore()
const { format } = useCurrency()

// Local edit state
const discountType = ref(cart.discount?.type || 'percentage') // 'percentage' | 'fixed'
const discountValue = ref(cart.discount?.value || 0)
const discountNote = ref(cart.discount?.name || '')

const subtotal = computed(() => cart.subtotal)

const previewDiscountAmount = computed(() => {
    const val = Number(discountValue.value || 0)
    if (val <= 0) return 0
    if (discountType.value === 'percentage') {
        return Math.min(subtotal.value, Math.round((subtotal.value * val / 100) * 100) / 100)
    }
    return Math.min(subtotal.value, Math.round(val * 100) / 100)
})

const previewGrandTotal = computed(() => {
    return Math.max(0, Math.round((subtotal.value - previewDiscountAmount.value) * 100) / 100)
})

function applyActivePromo() {
    if (cart.activeBranchDiscount && cart.activeBranchDiscount.is_apply) {
        cart.applyBranchDiscount()
        emit('close')
    }
}

function setPercentagePreset(pct) {
    discountType.value = 'percentage'
    discountValue.value = pct
    discountNote.value = `${pct}% Special Discount`
}

function setFixedPreset(amt) {
    discountType.value = 'fixed'
    discountValue.value = amt
    discountNote.value = `${format(amt)} Flat Discount`
}

function saveDiscount() {
    const val = Number(discountValue.value || 0)
    if (val <= 0) {
        cart.clearDiscount()
    } else {
        cart.applyDiscount({
            type: discountType.value,
            value: val,
            applied_id: (discountType.value === cart.activeBranchDiscount?.discount_type && val === Number(cart.activeBranchDiscount?.discount_value)) ? cart.activeBranchDiscount.id : null,
            name: discountNote.value.trim() || (discountType.value === 'percentage' ? `${val}% Discount` : `${format(val)} Discount`),
        })
    }
    emit('close')
}

function removeDiscount() {
    cart.clearDiscount()
    emit('close')
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4 animate-in fade-in duration-200">
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700 transition-all flex flex-col max-h-[90vh]">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-850/80">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🏷️</span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Sale Discount Management</h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Apply promotional rules or custom discounts to current cart</p>
                    </div>
                </div>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4 overflow-y-auto flex-1">
                <!-- Current Active Branch Promotional Rule -->
                <div v-if="cart.activeBranchDiscount && cart.activeBranchDiscount.is_apply" class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300">Active Store Promotion</span>
                        </div>
                        <p class="text-xs font-extrabold text-slate-800 dark:text-slate-100 mt-0.5">
                            {{ cart.activeBranchDiscount.discount_type === 'percentage' ? cart.activeBranchDiscount.discount_value + '% OFF Total' : format(cart.activeBranchDiscount.discount_value) + ' Flat OFF' }}
                        </p>
                        <p v-if="cart.activeBranchDiscount.description" class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
                            {{ cart.activeBranchDiscount.description }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="applyActivePromo"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-xs flex-shrink-0 cursor-pointer"
                    >
                        Apply Promo
                    </button>
                </div>

                <!-- Discount Type Switcher -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Discount Mode</label>
                    <div class="grid grid-cols-2 gap-2 bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                        <button
                            type="button"
                            @click="discountType = 'percentage'"
                            :class="discountType === 'percentage' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400'"
                            class="py-2 text-xs rounded-lg transition cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <span>%</span>
                            <span>Percentage Discount</span>
                        </button>
                        <button
                            type="button"
                            @click="discountType = 'fixed'"
                            :class="discountType === 'fixed' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400'"
                            class="py-2 text-xs rounded-lg transition cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <span>₦</span>
                            <span>Fixed Amount (₦)</span>
                        </button>
                    </div>
                </div>

                <!-- Quick Presets -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Quick Presets</label>
                    <div v-if="discountType === 'percentage'" class="grid grid-cols-5 gap-1.5">
                        <button
                            v-for="pct in [5, 10, 15, 20, 50]"
                            :key="pct"
                            type="button"
                            @click="setPercentagePreset(pct)"
                            :class="Number(discountValue) === pct && discountType === 'percentage' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600'"
                            class="py-1.5 rounded-lg text-xs font-bold transition cursor-pointer border border-transparent"
                        >
                            {{ pct }}%
                        </button>
                    </div>
                    <div v-else class="grid grid-cols-5 gap-1.5">
                        <button
                            v-for="amt in [200, 500, 1000, 2000, 5000]"
                            :key="amt"
                            type="button"
                            @click="setFixedPreset(amt)"
                            :class="Number(discountValue) === amt && discountType === 'fixed' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600'"
                            class="py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer border border-transparent"
                        >
                            ₦{{ amt >= 1000 ? (amt/1000) + 'k' : amt }}
                        </button>
                    </div>
                </div>

                <!-- Value Input -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ discountType === 'percentage' ? 'Discount Percentage (%)' : 'Discount Amount (₦)' }}
                    </label>
                    <div class="relative">
                        <input
                            v-model.number="discountValue"
                            type="number"
                            min="0"
                            :max="discountType === 'percentage' ? 100 : subtotal"
                            step="any"
                            placeholder="0"
                            class="w-full bg-white dark:bg-slate-750 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 font-mono font-bold text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition"
                        />
                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
                            {{ discountType === 'percentage' ? '%' : '₦' }}
                        </span>
                    </div>
                </div>

                <!-- Reason / Note -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Discount Description / Note</label>
                    <input
                        v-model="discountNote"
                        type="text"
                        placeholder="e.g. VIP Customer, Promo, Damaged packaging"
                        class="w-full bg-white dark:bg-slate-750 text-slate-900 dark:text-white px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs focus:border-emerald-500 outline-none transition"
                    />
                </div>

                <!-- Live Summary Preview -->
                <div class="p-3.5 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700/80 space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-500 dark:text-slate-400">
                        <span>Cart Subtotal:</span>
                        <span class="font-mono font-medium text-slate-800 dark:text-slate-200">{{ format(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-red-600 dark:text-red-400 font-semibold">
                        <span>Discount Deduction:</span>
                        <span class="font-mono font-bold">-{{ format(previewDiscountAmount) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-900 dark:text-white pt-1.5 border-t border-slate-200 dark:border-slate-700 text-sm">
                        <span>New Subtotal:</span>
                        <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ format(previewGrandTotal) }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="p-4 bg-slate-50 dark:bg-slate-850 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2">
                <button
                    type="button"
                    @click="removeDiscount"
                    class="px-3.5 py-2 rounded-xl text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 text-xs font-bold transition cursor-pointer"
                >
                    Remove Discount
                </button>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveDiscount"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-md shadow-emerald-900/20 cursor-pointer"
                    >
                        Apply to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
