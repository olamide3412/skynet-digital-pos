<script setup>
import { useCartStore } from '@/Stores/cart'
import { useCurrency } from '@/Composables/useCurrency'
import CartItem from './CartItem.vue'

defineProps({ canEditPrice: Boolean })

const cart = useCartStore()
const { format } = useCurrency()
</script>

<template>
    <div class="flex-1 overflow-y-auto min-h-0">
        <!-- Empty State -->
        <div v-if="!cart.items.length" class="flex flex-col items-center justify-center h-full text-slate-500 py-12">
            <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="text-sm">Cart is empty</p>
            <p class="text-xs mt-1">Search or scan items to add</p>
        </div>

        <!-- Cart Items -->
        <div v-else class="divide-y divide-slate-700/50">
            <CartItem
                v-for="item in cart.items"
                :key="item.item_id"
                :item="item"
                :can-edit-price="canEditPrice"
                @remove="cart.removeItem(item.item_id)"
                @update-qty="(qty) => cart.updateQty(item.item_id, qty)"
                @update-price="(price) => cart.updatePrice(item.item_id, price)"
            />
        </div>
    </div>
</template>
