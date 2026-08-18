<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
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
import ThemeToggle from '@/Components/ThemeToggle.vue'
import { useBarcodeScanner } from '@/Composables/useBarcodeScanner'
import { useOfflineSync } from '@/Composables/useOfflineSync'

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

const page = usePage()
const canProcessReturn = computed(() => {
    const user = page.props.auth?.user
    if (!user) return false
    if (user.role === 'branch-admin' || user.role === 'superadmin' || user.is_branch_admin) return true
    return Boolean(page.props.pos_permissions?.canProcessReturn)
})

const { format } = useCurrency()
const cart          = useCartStore()
const settStore     = usePosSettingsStore()
const productSearch = ref(null)   // ref to ProductSearch component
settStore.set(props.settings)

// Offline synchronization composable
const {
    isOnline,
    isSyncing,
    syncState,
    pendingCount,
    queuedSalesList,
    syncQueuedSales,
    refreshCatalog,
    startSyncMonitor,
    stopSyncMonitor,
} = useOfflineSync()

const showOfflineModal = ref(false)

onMounted(() => {
    if (props.settings?.is_offline_enabled) {
        startSyncMonitor()
    }
})

onUnmounted(() => {
    stopSyncMonitor()
})

// Modal visibility
const showPayment    = ref(false)
const showReceipt    = ref(false)
const showHold       = ref(false)
const showHeld       = ref(false)
const showReturn     = ref(false)
const completedSale  = ref(null)

const isAnyModalOpen = computed(() => showPayment.value || showReceipt.value || showHold.value || showHeld.value || showReturn.value || showOfflineModal.value)

useBarcodeScanner({
    onScan: (item) => {
        cart.addItem(item)
        nextTick(() => productSearch.value?.focus())
    },
    getPurchaseType: () => cart.purchaseType,
    isEnabled: () => !isAnyModalOpen.value,
})

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
        case 'F2':  e.preventDefault(); productSearch.value?.focus(); break
        case 'F10': e.preventDefault(); if (cart.items.length) showPayment.value = true; break
        case 'F9':  e.preventDefault(); if (cart.items.length) showHold.value = true; break
        case 'Escape':
            showPayment.value    = false
            showReceipt.value    = false
            showHold.value       = false
            showHeld.value       = false
            showReturn.value     = false
            showOfflineModal.value = false
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
    showReceipt.value   = false
    completedSale.value = null
    // Refocus search bar after receipt is closed for next customer
    nextTick(() => productSearch.value?.focus())
}

// Load held cart
function onLoadHeld(heldSale) {
    cart.loadFromHeld(heldSale)
    showHeld.value = false
    router.reload({ only: ['heldSales'] })
    nextTick(() => productSearch.value?.focus())
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
        <header class="h-12 flex-shrink-0 flex items-center justify-between bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-4 text-sm transition-colors duration-300">
            <div class="flex items-center gap-3">
                <span class="font-bold text-emerald-600 dark:text-emerald-400 text-base">{{ settings.business_name }}</span>
                <span class="text-slate-400">|</span>
                <span class="text-slate-600 dark:text-slate-300">Cashier: <span class="font-medium text-slate-900 dark:text-white">{{ $page.props.auth.user?.full_name || $page.props.auth.user?.name }}</span></span>
            </div>
            <div class="flex items-center gap-3">
                <!-- Offline Connection Status Badge -->
                <div v-if="settings?.is_offline_enabled" class="flex items-center">
                    <button
                        @click="showOfflineModal = true"
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-full text-xs font-bold flex items-center gap-1.5 transition cursor-pointer shadow-xs',
                            syncState === 'online'
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/25'
                                : syncState === 'syncing'
                                ? 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/30 animate-pulse hover:bg-blue-500/25'
                                : 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30 hover:bg-rose-500/25'
                        ]"
                        :title="syncState === 'online' ? 'Online – All systems operational' : syncState === 'syncing' ? 'Syncing queued sales with server...' : 'Offline – Sales are stored locally on device'"
                    >
                        <span v-if="syncState === 'online'" class="w-2 h-2 rounded-full bg-emerald-500 shadow-xs"></span>
                        <span v-else-if="syncState === 'syncing'" class="animate-spin text-[10px]">🔄</span>
                        <span v-else class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                        </span>
                        
                        <span class="capitalize tracking-wide">{{ syncState }}</span>
                        <span v-if="pendingCount > 0" class="px-1.5 py-0.2 bg-rose-600 text-white rounded-full text-[10px] font-bold">
                            {{ pendingCount }}
                        </span>
                    </button>
                </div>

                <span class="text-slate-500 dark:text-slate-400 text-xs hidden sm:inline">{{ formattedTime }}</span>
                <ThemeToggle />
                <div class="flex items-center gap-2">
                    <button
                        title="F9 – Hold Cart"
                        class="px-3 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white text-xs font-medium transition"
                        :class="{ 'opacity-40 cursor-not-allowed': !cart.items.length }"
                        @click="cart.items.length && (showHold = true)"
                    >Hold</button>
                    <button
                        title="Held Carts"
                        class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 text-slate-800 dark:text-white text-xs font-medium transition"
                        @click="showHeld = true"
                    >Held ({{ heldSales.length }})</button>
                    <button
                        v-if="canProcessReturn"
                        title="Process Return"
                        class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 text-slate-800 dark:text-white text-xs font-medium transition"
                        @click="showReturn = true"
                    >Return</button>
                    <button
                        @click="viewMode = (viewMode === 'gallery' ? 'classic' : 'gallery')"
                        class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 text-slate-800 dark:text-white text-xs font-medium transition flex items-center gap-1.5"
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
            <div class="flex flex-col w-[60%] border-r border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-50 dark:bg-slate-900">
                <div class="p-3 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                    <ProductSearch
                        ref="productSearch"
                        :purchase-type="cart.purchaseType"
                        :settings="settings"
                        @select="(item) => { cart.addItem(item); productSearch.value?.focus() }"
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
                    <div v-else class="text-center text-slate-400 dark:text-slate-500 mt-16">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Search or scan a barcode to add items to cart</p>
                        <p class="text-xs mt-1 text-slate-400 dark:text-slate-500">Press <kbd class="bg-slate-200 dark:bg-slate-700 px-1 rounded text-slate-700 dark:text-slate-300">F2</kbd> to focus search</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Cart (40%) -->
            <div class="flex flex-col w-[40%] overflow-hidden bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700">
                <!-- Customer Selector -->
                <div class="px-3 pt-3 pb-2 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-transparent">
                    <CustomerSelector
                        :customer="cart.customer"
                        @select="cart.setCustomer"
                        @clear="cart.clearCustomer"
                    />
                </div>

                <!-- Purchase Type Toggle -->
                <div class="flex px-3 py-2 gap-2 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-transparent">
                    <button
                        @click="cart.setPurchaseType('Consumer')"
                        :class="cart.purchaseType === 'Consumer'
                            ? 'bg-emerald-600 text-white font-bold'
                            : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600'"
                        class="flex-1 py-1 rounded text-xs font-medium transition"
                    >Consumer</button>
                    <button
                        @click="cart.setPurchaseType('Wholesale')"
                        :class="cart.purchaseType === 'Wholesale'
                            ? 'bg-blue-600 text-white font-bold'
                            : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600'"
                        class="flex-1 py-1 rounded text-xs font-medium transition"
                    >Wholesale</button>
                </div>

                <!-- Cart Items -->
                <Cart :can-edit-price="canEditPrice" />

                <!-- Totals -->
                <div class="border-t border-slate-200 dark:border-slate-700 p-3 space-y-1 text-sm bg-slate-50 dark:bg-slate-800">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Subtotal</span>
                        <span>{{ format(cart.subtotal) }}</span>
                    </div>
                    <div v-if="cart.discountAmount > 0" class="flex justify-between text-red-600 dark:text-red-400">
                        <span>Discount</span>
                        <span>-{{ format(cart.discountAmount) }}</span>
                    </div>
                    <div v-if="cart.consultationFee > 0" class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Consult Fee</span>
                        <span>{{ format(cart.consultationFee) }}</span>
                    </div>
                    <div v-if="cart.taxAmount > 0" class="flex justify-between text-amber-600 dark:text-amber-400 font-medium">
                        <span>Tax ({{ settings?.tax_percentage }}%)</span>
                        <span>+{{ format(cart.taxAmount) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-lg text-emerald-600 dark:text-emerald-400 pt-1 border-t border-slate-200 dark:border-slate-700">
                        <span>TOTAL</span>
                        <span>{{ format(cart.grandTotal) }}</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="p-3 grid grid-cols-2 gap-2 bg-slate-100 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700">
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

        <!-- Offline Sales Queue Inspection Modal -->
        <div v-if="showOfflineModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-lg w-full overflow-hidden shadow-2xl space-y-4 p-5">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📡</span>
                        <div>
                            <h3 class="font-bold text-slate-800 dark:text-white text-base">Offline Sales Queue</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Terminal Status: 
                                <strong :class="isOnline ? 'text-emerald-500' : 'text-rose-500'">
                                    {{ isOnline ? 'Connected' : 'Offline' }}
                                </strong>
                            </p>
                        </div>
                    </div>
                    <button @click="showOfflineModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg">✕</button>
                </div>

                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    <div v-if="queuedSalesList.length === 0" class="text-center py-8 text-slate-400 text-sm">
                        <p class="text-3xl mb-2">🎉</p>
                        All sales are synced with the server. No pending offline transactions.
                    </div>
                    <div
                        v-for="sale in queuedSalesList"
                        :key="sale.offline_sale_id"
                        class="p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-between text-xs"
                    >
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <span>{{ sale.receipt_id }}</span>
                                <span
                                    :class="[
                                        'px-1.5 py-0.2 rounded text-[10px] font-bold uppercase',
                                        sale.sync_status === 'synced' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' :
                                        sale.sync_status === 'failed' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' :
                                        'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300'
                                    ]"
                                >
                                    {{ sale.sync_status }}
                                </span>
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 text-[11px] mt-0.5">
                                {{ new Date(sale.created_at).toLocaleTimeString() }} • {{ sale.cashier_name || 'Cashier' }} • {{ sale.payment_method }}
                            </div>
                            <div v-if="sale.error_message" class="text-rose-500 text-[11px] mt-1 font-mono">
                                {{ sale.error_message }}
                            </div>
                        </div>
                        <div class="font-bold text-slate-800 dark:text-white text-sm">
                            {{ format(sale.final_total) }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                    <button
                        @click="refreshCatalog"
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 transition"
                    >
                        📥 Refresh Catalog
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            @click="syncQueuedSales"
                            :disabled="isSyncing || pendingCount === 0 || !isOnline"
                            type="button"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                        >
                            <span v-if="isSyncing" class="animate-spin">🔄</span>
                            <span>{{ isSyncing ? 'Syncing...' : 'Sync Now' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
