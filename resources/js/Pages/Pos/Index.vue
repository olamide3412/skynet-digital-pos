<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useCartStore } from '@/Stores/cart'
import { usePosSettingsStore } from '@/Stores/posSettings'
import { useCurrency } from '@/Composables/useCurrency'
import PosLayout from '@/Layouts/PosLayout.vue'
import ProductSearch from '@/Components/POS/ProductSearch.vue'
import ProductGrid from '@/Components/POS/ProductGrid.vue'
import Cart from '@/Components/POS/Cart.vue'
import PaymentModal from '@/Components/POS/PaymentModal.vue'
import ReceiptModal from '@/Components/POS/ReceiptModal.vue'
import HoldCartModal from '@/Components/POS/HoldCartModal.vue'
import HeldCartsList from '@/Components/POS/HeldCartsList.vue'
import ReturnModal from '@/Components/POS/ReturnModal.vue'
import CustomerSelector from '@/Components/POS/CustomerSelector.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    settings:         Object,
    itemGrids:        Array,
    heldSales:        Array,
    activeDiscount:   Object,
    canEditPrice:     Boolean,
    canApplyDiscount: Boolean,
    now:              String,
})

const { format } = useCurrency()
const cart       = useCartStore()
const settStore  = usePosSettingsStore()
settStore.set(props.settings)

// Modal visibility
const showPayment    = ref(false)
const showReceipt    = ref(false)
const showHold       = ref(false)
const showHeld       = ref(false)
const showReturn     = ref(false)
const completedSale  = ref(null)

// Current time display
const currentTime = ref(new Date())
let clockInterval = null
onMounted(() => { clockInterval = setInterval(() => { currentTime.value = new Date() }, 1000) })
onUnmounted(() => clearInterval(clockInterval))

const formattedTime = computed(() => {
    return currentTime.value.toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
})

// Keyboard shortcuts
function handleKeydown(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        if (e.key === 'Escape') e.target.blur()
        return
    }
    switch (e.key) {
        case 'F2':  e.preventDefault(); document.getElementById('pos-search')?.focus(); break
        case 'F10': e.preventDefault(); if (cart.items.length) showPayment.value = true; break
        case 'F9':  e.preventDefault(); if (cart.items.length) showHold.value = true; break
        case 'Escape':
            showPayment.value = false
            showReceipt.value = false
            showHold.value    = false
            showHeld.value    = false
            showReturn.value  = false
            break
    }
}
onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))

// After sale completes
function onSaleCompleted(sale) {
    completedSale.value = sale
    showPayment.value   = false
    showReceipt.value   = true
    cart.clearCart()
}

function onReceiptClose() {
    showReceipt.value  = false
    completedSale.value = null
}

// Load held cart
function onLoadHeld(heldSale) {
    cart.loadFromHeld(heldSale)
    showHeld.value = false
    router.reload({ only: ['heldSales'] })
}

function onHeld() {
    showHold.value = false
    router.reload({ only: ['heldSales'] })
}

function onHeldDeleted() {
    router.reload({ only: ['heldSales'] })
}
const viewMode = ref(props.settings?.sell_interface ?? 'classic')
</script>

<template>
    <div class="flex flex-col h-screen bg-slate-900 text-slate-100 overflow-hidden select-none">

        <!-- ── TOPBAR ─────────────────────────────────────────────────────── -->
        <header class="h-12 flex-shrink-0 flex items-center justify-between bg-slate-800 border-b border-slate-700 px-4 text-sm">
            <div class="flex items-center gap-3">
                <span class="font-bold text-emerald-400 text-base">{{ settings.business_name }}</span>
                <span class="text-slate-400">|</span>
                <span class="text-slate-300">Cashier: <span class="font-medium text-white">{{ $page.props.auth.user?.full_name || $page.props.auth.user?.name }}</span></span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-slate-400 text-xs">{{ formattedTime }}</span>
                <div class="flex items-center gap-2">
                    <button
                        title="F9 – Hold Cart"
                        class="px-3 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white text-xs font-medium transition"
                        :class="{ 'opacity-40 cursor-not-allowed': !cart.items.length }"
                        @click="cart.items.length && (showHold = true)"
                    >Hold</button>
                    <button
                        title="Held Carts"
                        class="px-3 py-1 rounded bg-slate-600 hover:bg-slate-500 text-white text-xs font-medium transition"
                        @click="showHeld = true"
                    >Held ({{ heldSales.length }})</button>
                    <button
                        title="Process Return"
                        class="px-3 py-1 rounded bg-slate-600 hover:bg-slate-500 text-white text-xs font-medium transition"
                        @click="showReturn = true"
                    >Return</button>
                    <button
                        @click="viewMode = (viewMode === 'gallery' ? 'classic' : 'gallery')"
                        class="px-3 py-1 rounded bg-slate-600 hover:bg-slate-500 text-white text-xs font-medium transition flex items-center gap-1.5"
                        :title="viewMode === 'gallery' ? 'Switch to Classic search-only view' : 'Switch to Grid (Gallery) view'"
                    >
                        <span>{{ viewMode === 'gallery' ? 'Classic View' : 'Grid View' }}</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- ── TWO-PANEL BODY ─────────────────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden">

            <!-- LEFT PANEL: Search + Items (60%) -->
            <div class="flex flex-col w-[60%] border-r border-slate-700 overflow-hidden">
                <div class="p-3 border-b border-slate-700 bg-slate-800">
                    <ProductSearch
                        :purchase-type="cart.purchaseType"
                        :settings="settings"
                        @select="cart.addItem"
                    />
                </div>
                <div class="flex-1 overflow-y-auto p-3">
                    <ProductGrid
                        v-if="viewMode === 'gallery'"
                        :item-grids="itemGrids"
                        :settings="settings"
                        @select="cart.addItem"
                    />
                    <!-- Classic mode shows recent/top items -->
                    <div v-else class="text-center text-slate-500 mt-16">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <p class="text-sm">Search or scan a barcode to add items to cart</p>
                        <p class="text-xs mt-1 text-slate-600">Press <kbd class="bg-slate-700 px-1 rounded">F2</kbd> to focus search</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Cart (40%) -->
            <div class="flex flex-col w-[40%] overflow-hidden bg-slate-850">
                <!-- Customer Selector -->
                <div class="px-3 pt-3 pb-2 border-b border-slate-700">
                    <CustomerSelector
                        :customer="cart.customer"
                        @select="cart.setCustomer"
                        @clear="cart.clearCustomer"
                    />
                </div>

                <!-- Purchase Type Toggle -->
                <div class="flex px-3 py-2 gap-2 border-b border-slate-700">
                    <button
                        @click="cart.setPurchaseType('Consumer')"
                        :class="cart.purchaseType === 'Consumer'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                        class="flex-1 py-1 rounded text-xs font-medium transition"
                    >Consumer</button>
                    <button
                        @click="cart.setPurchaseType('Wholesale')"
                        :class="cart.purchaseType === 'Wholesale'
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                        class="flex-1 py-1 rounded text-xs font-medium transition"
                    >Wholesale</button>
                </div>

                <!-- Cart Items -->
                <Cart :can-edit-price="canEditPrice" />

                <!-- Totals -->
                <div class="border-t border-slate-700 p-3 space-y-1 text-sm bg-slate-800">
                    <div class="flex justify-between text-slate-400">
                        <span>Subtotal</span>
                        <span>{{ format(cart.subtotal) }}</span>
                    </div>
                    <div v-if="cart.discountAmount > 0" class="flex justify-between text-red-400">
                        <span>Discount</span>
                        <span>-{{ format(cart.discountAmount) }}</span>
                    </div>
                    <div v-if="cart.consultationFee > 0" class="flex justify-between text-slate-400">
                        <span>Consult Fee</span>
                        <span>{{ format(cart.consultationFee) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-lg text-emerald-400 pt-1 border-t border-slate-700">
                        <span>TOTAL</span>
                        <span>{{ format(cart.grandTotal) }}</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="p-3 grid grid-cols-2 gap-2 bg-slate-800 border-t border-slate-700">
                    <button
                        @click="cart.clearCart()"
                        :disabled="!cart.items.length"
                        class="py-2 rounded bg-red-600/80 hover:bg-red-600 text-white text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed"
                    >Clear</button>
                    <button
                        id="pay-now-btn"
                        @click="showPayment = true"
                        :disabled="!cart.items.length"
                        class="py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed"
                    >Pay Now <span class="text-xs opacity-70">(F10)</span></button>
                </div>
            </div>
        </div>

        <!-- ── MODALS ──────────────────────────────────────────────────────── -->
        <PaymentModal
            v-if="showPayment"
            :settings="settings"
            :can-apply-discount="canApplyDiscount"
            @close="showPayment = false"
            @completed="onSaleCompleted"
        />

        <ReceiptModal
            v-if="showReceipt && completedSale"
            :sale="completedSale"
            :settings="settings"
            @close="onReceiptClose"
        />

        <HoldCartModal
            v-if="showHold"
            @close="showHold = false"
            @held="onHeld"
        />

        <HeldCartsList
            v-if="showHeld"
            :held-sales="heldSales"
            @close="showHeld = false"
            @load="onLoadHeld"
            @deleted="onHeldDeleted"
        />

        <ReturnModal
            v-if="showReturn"
            @close="showReturn = false"
        />
    </div>
</template>

<style>
/* Thermal receipt print area */
@media print {
    body > *:not(.receipt-print-area) { display: none !important; }
    .receipt-print-area {
        display: block !important;
        width: 80mm;
        max-width: 80mm;
        font-size: 11px;
        font-family: monospace;
    }
}
</style>
