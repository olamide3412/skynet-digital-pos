<script setup>
import { ref, watch, nextTick, computed } from 'vue'
import { useCartStore } from '@/Stores/cart'
import { usePosSettingsStore } from '@/Stores/posSettings'
import { useCurrency } from '@/Composables/useCurrency'
import CartItem from './CartItem.vue'

const props = defineProps({
    canEditPrice: { type: Boolean, default: false },
    settings:     { type: Object, default: () => ({}) },
})

const cart = useCartStore()
const settStore = usePosSettingsStore()
const { format } = useCurrency()

const scrollContainer = ref(null)
const highlightedKey = ref(null)
let highlightTimer = null

const isAutoScrollHighlightEnabled = computed(() => {
    if (props.settings && typeof props.settings.is_cart_auto_scroll_highlight !== 'undefined') {
        return !!props.settings.is_cart_auto_scroll_highlight
    }
    if (settStore.settings && typeof settStore.settings.is_cart_auto_scroll_highlight !== 'undefined') {
        return !!settStore.settings.is_cart_auto_scroll_highlight
    }
    return true // Default: ON
})

// Watch for items being added or updated in the cart from search, barcode, or grid
watch(() => cart.lastAddedAt, async (timestamp) => {
    if (!timestamp || !cart.lastAddedKey || !isAutoScrollHighlightEnabled.value) return
    const key = cart.lastAddedKey
    highlightedKey.value = key

    await nextTick()
    setTimeout(() => {
        if (!scrollContainer.value) return
        
        // 1. Locate specific row element
        const el = document.getElementById(`cart-row-${key}`) || scrollContainer.value.querySelector(`[data-cart-key="${key}"]`)
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        } else {
            scrollContainer.value.scrollTo({
                top: scrollContainer.value.scrollHeight + 1000,
                behavior: 'smooth'
            })
        }
    }, 50)

    if (highlightTimer) clearTimeout(highlightTimer)
    highlightTimer = setTimeout(() => {
        if (highlightedKey.value === key) {
            highlightedKey.value = null
        }
    }, 1200)
})
</script>

<template>
    <div ref="scrollContainer" class="flex-1 overflow-y-auto min-h-0 scroll-smooth">
        <!-- Empty State -->
        <div v-if="!cart.items.length" class="flex flex-col items-center justify-center h-full text-slate-400 dark:text-slate-500 py-12">
            <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="text-sm font-medium">Cart is empty</p>
            <p class="text-xs mt-1 text-slate-400 dark:text-slate-500">Search or scan items to add</p>
        </div>

        <!-- Cart Items -->
        <div v-else class="divide-y divide-slate-200 dark:divide-slate-700/50 p-1">
            <CartItem
                v-for="item in cart.items"
                :key="item.cart_key"
                :data-cart-key="item.cart_key"
                :item="item"
                :is-highlighted="highlightedKey === item.cart_key"
                :can-edit-price="canEditPrice"
                @remove="cart.removeItem(item.cart_key)"
                @update-qty="(qty) => cart.updateQty(item.cart_key, qty)"
                @update-price="(price) => cart.updatePrice(item.cart_key, price)"
            />
        </div>
    </div>
</template>
