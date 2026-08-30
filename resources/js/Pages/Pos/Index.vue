<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted, inject } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useCartStore } from '@/Stores/cart'
import { usePosSettingsStore } from '@/Stores/posSettings'
import { useCurrency } from '@/Composables/useCurrency'
import PosLayout from '@/Layouts/PosLayout.vue'
import ProductSearch from '@/Components/POS/ProductSearch.vue'
import ProductGrid from '@/Components/POS/ProductGrid.vue'
import QuickAccessWidget from '@/Components/POS/QuickAccessWidget.vue'
import Cart from '@/Components/POS/Cart.vue'
import PaymentModal from '@/Components/POS/PaymentModal.vue'
import ReceiptModal from '@/Components/POS/ReceiptModal.vue'
import HoldCartModal from '@/Components/POS/HoldCartModal.vue'
import HeldCartsList from '@/Components/POS/HeldCartsList.vue'
import ReturnModal from '@/Components/POS/ReturnModal.vue'
import CustomerSelector from '@/Components/POS/CustomerSelector.vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import NotificationBell from '@/Components/Notifications/NotificationBell.vue'
import PosSplashScreen from '@/Components/POS/PosSplashScreen.vue'
import { useBarcodeScanner } from '@/Composables/useBarcodeScanner'
import { useOfflineSync } from '@/Composables/useOfflineSync'
import { getLocalQuickAccessData } from '@/Services/offlineDb'

defineOptions({ layout: PosLayout })

const props = defineProps({
    settings:         Object,
    quickAccess:      Object,
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

// ── Splash Screen & Terminal Launch State ─────────────────────────────────────
const isPosReady = ref(false)
const showPosSplash = ref(true)

const branchName = computed(() => {
    return page.props.current_branch?.name || page.props.store_settings?.company_name || props.settings?.business_name || 'Skynet POS Terminal'
})

const branchLogo = computed(() => {
    if (page.props.current_branch?.logo_url) return page.props.current_branch.logo_url
    if (page.props.store_settings?.company_logo) return '/storage/' + page.props.store_settings.company_logo
    return null
})

// Reactive Quick Access dataset (with offline Dexie fallback)
const localQuickAccess = ref(props.quickAccess || null)
const currentQuickAccess = computed(() => {
    if (!isOnline.value && localQuickAccess.value) {
        return localQuickAccess.value
    }
    return props.quickAccess || localQuickAccess.value || {
        mode:       'top_sellers',
        title:      '⚡ Top Sellers',
        subtitle:   'Most purchased items this week',
        items:      [],
        categories: [],
    }
})

// Offline synchronization composable
const {
    isOnline,
    isSyncing,
    syncState,
    pendingCount,
    queuedSalesList,
    syncQueuedSales,
    refreshCatalog,
    updatePendingCount,
    startSyncMonitor,
    stopSyncMonitor,
} = useOfflineSync()

onMounted(async () => {
    try {
        if (!props.quickAccess || props.quickAccess.mode === 'none') {
            const cached = await getLocalQuickAccessData()
            if (cached && cached.mode && cached.mode !== 'none') {
                localQuickAccess.value = cached
            }
        }

        // Prime local offline cache on terminal load
        if (props.settings?.is_offline_enabled && isOnline.value) {
            await refreshCatalog()
        }
    } catch (err) {
        console.warn('POS initial cache sync notice:', err)
    } finally {
        await nextTick()
        // Smoothly unlock terminal and evaluate auto-fullscreen
        setTimeout(() => {
            isPosReady.value = true
            tryAutoFullscreen()
        }, 200)
    }
})

const showOfflineModal = ref(false)
const offlineTabFilter = ref('all') // 'all' | 'pending' | 'synced' | 'failed'
const offlineSearchQuery = ref('')

const pendingSalesCount = computed(() => queuedSalesList.value.filter(s => s.sync_status === 'pending').length)
const syncedSalesCount = computed(() => queuedSalesList.value.filter(s => s.sync_status === 'synced').length)
const failedSalesCount = computed(() => queuedSalesList.value.filter(s => s.sync_status === 'failed').length)

const pendingTotalAmount = computed(() => {
    return queuedSalesList.value
        .filter(s => s.sync_status === 'pending')
        .reduce((sum, s) => sum + (parseFloat(s.final_total) || 0), 0)
})

const filteredQueuedSales = computed(() => {
    let list = queuedSalesList.value
    if (offlineTabFilter.value !== 'all') {
        list = list.filter(s => s.sync_status === offlineTabFilter.value)
    }
    const q = offlineSearchQuery.value.trim().toLowerCase()
    if (q) {
        list = list.filter(s => {
            return (s.receipt_id || '').toLowerCase().includes(q) ||
                   (s.cashier_name || '').toLowerCase().includes(q) ||
                   (s.payment_method || '').toLowerCase().includes(q)
        })
    }
    return list
})

async function openOfflineModal() {
    await updatePendingCount()
    showOfflineModal.value = true
}

watch(showOfflineModal, async (open) => {
    if (open) {
        await updatePendingCount()
    }
})

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

// ── Fullscreen Desktop Mode ──────────────────────────────────────────────────
const isFullscreen = ref(false)

function checkFullscreen() {
    if (typeof document === 'undefined') return
    isFullscreen.value = Boolean(
        document.fullscreenElement ||
        document.webkitFullscreenElement ||
        document.mozFullScreenElement ||
        document.msFullscreenElement
    )
}

function toggleFullscreen() {
    if (typeof document === 'undefined') return

    if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement && !document.msFullscreenElement) {
        const el = document.documentElement
        if (el.requestFullscreen) {
            el.requestFullscreen().catch(err => console.warn('Fullscreen error:', err))
        } else if (el.webkitRequestFullscreen) {
            el.webkitRequestFullscreen()
        } else if (el.mozRequestFullScreen) {
            el.mozRequestFullScreen()
        } else if (el.msRequestFullscreen) {
            el.msRequestFullscreen()
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen().catch(err => console.warn('Exit fullscreen error:', err))
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen()
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen()
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen()
        }
    }
}

async function tryAutoFullscreen() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return
    if (!props.settings?.is_auto_fullscreen_enabled) return
    if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement) return

    try {
        const el = document.documentElement
        if (el.requestFullscreen) {
            await el.requestFullscreen()
        } else if (el.webkitRequestFullscreen) {
            el.webkitRequestFullscreen()
        }
    } catch (err) {
        // Browser requires explicit user gesture; user can toggle fullscreen anytime via the top bar button or F11 shortcut.
        // We purposefully avoid hijacking document click events so clicking items/search remains 100% responsive.
    }
}

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
        case 'F11': e.preventDefault(); toggleFullscreen(); break
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
onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
    document.addEventListener('fullscreenchange', checkFullscreen)
    document.addEventListener('webkitfullscreenchange', checkFullscreen)
    document.addEventListener('mozfullscreenchange', checkFullscreen)
    document.addEventListener('MSFullscreenChange', checkFullscreen)
})
onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    document.removeEventListener('fullscreenchange', checkFullscreen)
    document.removeEventListener('webkitfullscreenchange', checkFullscreen)
    document.removeEventListener('mozfullscreenchange', checkFullscreen)
    document.removeEventListener('MSFullscreenChange', checkFullscreen)
})

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

// Mobile layout states
const toggleMobileSidebar = inject('toggleMobileSidebar', () => {})
const mobileActiveTab = ref('catalog') // 'catalog' | 'cart'
const showMobileMenu = ref(false)
const productSearchMobile = ref(null)
</script>

<template>
    <div class="flex flex-col h-screen bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 overflow-hidden select-none transition-colors duration-300">

        <!-- ── Splash Screen #2: Post-Login POS Launch Screen ── -->
        <PosSplashScreen
            v-if="showPosSplash"
            :branch-name="branchName"
            :branch-logo="branchLogo"
            :is-ready="isPosReady"
            @finish="showPosSplash = false"
        />

        <!-- ========================================================================= -->
        <!-- ── DESKTOP VIEW (>= lg) ────────────────────────────────────────────────── -->
        <!-- ========================================================================= -->
        <div class="hidden lg:flex flex-col flex-1 h-full overflow-hidden">
            <!-- ── DESKTOP TOPBAR ──────────────────────────────────────────────── -->
            <header class="h-12 flex-shrink-0 flex items-center justify-between bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-4 text-sm transition-colors duration-300">
                <div class="flex items-center gap-3">
                    <span class="font-bold text-theme text-base">{{ settings.business_name }}</span>
                    <span class="text-slate-400">|</span>
                    <span class="text-slate-600 dark:text-slate-300">Cashier: <span class="font-medium text-slate-900 dark:text-white">{{ $page.props.auth.user?.full_name || $page.props.auth.user?.name }}</span></span>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Offline Connection Status Badge -->
                    <div v-if="settings?.is_offline_enabled" class="flex items-center">
                        <button
                            @click="openOfflineModal"
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
                    <NotificationBell />
                    <ThemeToggle />

                    <!-- Desktop Fullscreen Mode Toggle (Icon-Only) -->
                    <button
                        @click="toggleFullscreen"
                        type="button"
                        class="p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition cursor-pointer"
                        :class="{ 'text-theme dark:text-theme bg-theme/10 dark:bg-theme/20': isFullscreen }"
                    >
                        <svg v-if="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m6 0h4m-4 0l5 5m-5-5v4m-6 6l-5 5m0 0v-4m0 4h4m11 0l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            title="F9 – Hold Cart"
                            class="px-3 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white text-xs font-medium transition cursor-pointer"
                            :class="{ 'opacity-40 cursor-not-allowed': !cart.items.length }"
                            @click="cart.items.length && (showHold = true)"
                        >Hold</button>
                        <button
                            title="Held Carts"
                            class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 text-slate-800 dark:text-white text-xs font-medium transition cursor-pointer"
                            @click="showHeld = true"
                        >Held ({{ heldSales.length }})</button>
                        <button
                            v-if="canProcessReturn"
                            title="Process Return"
                            class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 text-slate-800 dark:text-white text-xs font-medium transition cursor-pointer"
                            @click="showReturn = true"
                        >Return</button>
                        <button
                            @click="viewMode = (viewMode === 'gallery' ? 'classic' : 'gallery')"
                            class="px-3 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500 text-slate-800 dark:text-white text-xs font-medium transition flex items-center gap-1.5 cursor-pointer"
                            :title="viewMode === 'gallery' ? 'Switch to Classic search-only view' : 'Switch to Grid (Gallery) view'"
                        >
                            <span>{{ viewMode === 'gallery' ? 'Classic View' : 'Grid View' }}</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- ── DESKTOP TWO-PANEL BODY ──────────────────────────────────────── -->
            <div class="flex flex-1 overflow-hidden">

                <!-- LEFT PANEL: Search + Items (60%) -->
                <div class="flex flex-col w-[60%] border-r border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-50 dark:bg-slate-900">
                    <div class="p-3 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <ProductSearch
                            ref="productSearch"
                            :purchase-type="cart.purchaseType"
                            :settings="settings"
                            @select="(item) => { cart.addItem(item); productSearch?.focus?.() }"
                        />
                    </div>
                    <div class="flex-1 overflow-y-auto p-3">
                        <ProductGrid
                            v-if="viewMode === 'gallery'"
                            :item-grids="itemGrids"
                            :settings="settings"
                            @select="(item) => cart.addItem(item, 'unit', null, !!(settStore.settings?.is_grid_separate_rows ?? settings?.is_grid_separate_rows))"
                        />
                        <!-- Classic mode shows Quick Access Widget -->
                        <div v-else class="p-1">
                            <QuickAccessWidget
                                :quick-access="currentQuickAccess"
                                :settings="settings"
                                :purchase-type="cart.purchaseType"
                                @select="(item) => { cart.addItem(item); productSearch?.focus?.() }"
                            />
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
                            class="flex-1 py-1 rounded text-xs font-medium transition cursor-pointer"
                        >Consumer</button>
                        <button
                            @click="cart.setPurchaseType('Wholesale')"
                            :class="cart.purchaseType === 'Wholesale'
                                ? 'bg-blue-600 text-white font-bold'
                                : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600'"
                            class="flex-1 py-1 rounded text-xs font-medium transition cursor-pointer"
                        >Wholesale</button>
                    </div>

                    <!-- Cart Items -->
                    <Cart :can-edit-price="canEditPrice" :settings="settings" />

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
                        <div class="flex justify-between font-bold text-lg text-theme pt-1 border-t border-slate-200 dark:border-slate-700">
                            <span>TOTAL</span>
                            <span>{{ format(cart.grandTotal) }}</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="p-3 grid grid-cols-2 gap-2 bg-slate-100 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700">
                        <button
                            @click="cart.clearCart()"
                            :disabled="!cart.items.length"
                            class="py-2 rounded bg-red-600/80 hover:bg-red-600 text-white text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                        >Clear</button>
                        <button
                            id="pay-now-btn"
                            @click="showPayment = true"
                            :disabled="!cart.items.length"
                            class="py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                        >Pay Now <span class="text-xs opacity-70">(F10)</span></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- ── DEDICATED MOBILE POS VIEW (< lg) ────────────────────────────────────── -->
        <!-- ========================================================================= -->
        <div class="flex lg:hidden flex-col flex-1 h-full overflow-hidden bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 relative transition-colors duration-300">
            <!-- ── Mobile Top Header ── -->
            <header class="h-13 flex-shrink-0 flex items-center justify-between bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-3 transition-colors duration-300">
                <div class="flex items-center gap-2 min-w-0">
                    <!-- Navigation Drawer Hamburger Toggle -->
                    <button
                        @click="toggleMobileSidebar"
                        type="button"
                        class="p-2 -ml-1 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                        title="Main Navigation Menu"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <div class="font-bold text-theme text-xs sm:text-sm truncate leading-tight">
                            {{ settings.business_name }}
                        </div>
                        <div class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">
                            {{ $page.props.auth.user?.full_name || $page.props.auth.user?.name }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <!-- Offline sync indicator -->
                    <button
                        v-if="settings?.is_offline_enabled"
                        @click="openOfflineModal"
                        type="button"
                        :class="[
                            'px-2 py-1 rounded-full text-[10px] sm:text-[11px] font-bold flex items-center gap-1 transition cursor-pointer',
                            syncState === 'online'
                                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                : syncState === 'syncing'
                                ? 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/20 animate-pulse'
                                : 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                        ]"
                    >
                        <span v-if="syncState === 'online'" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span v-else-if="syncState === 'syncing'" class="animate-spin text-[10px]">🔄</span>
                        <span v-else class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <span v-if="pendingCount > 0" class="px-1 bg-rose-600 text-white rounded-full text-[9px] font-black">
                            {{ pendingCount }}
                        </span>
                    </button>

                    <!-- Notifications Bell -->
                    <NotificationBell />

                    <!-- Held Carts Quick Button with Badge -->
                    <button
                        @click="showHeld = true"
                        type="button"
                        class="relative px-2 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold flex items-center gap-1 transition cursor-pointer"
                        title="Held Carts"
                    >
                        <span>📂</span>
                        <span v-if="heldSales.length > 0" class="px-1.5 py-0.2 bg-amber-500 text-white rounded-full text-[9px] font-black">
                            {{ heldSales.length }}
                        </span>
                    </button>

                    <!-- Mobile Fullscreen Toggle Button -->
                    <button
                        @click="toggleFullscreen"
                        type="button"
                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                        :title="isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'"
                    >
                        <svg v-if="!isFullscreen" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                        <svg v-else class="w-4.5 h-4.5 text-theme" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l5 5m0 0H5m4 0V5m11-1l-5 5m0 0h4m-4 0V5M4 20l5-5m0 0H5m4 0v4m11-4l-5 5m0 0h4m-4 0v-4"/>
                        </svg>
                    </button>

                    <!-- Mobile Actions Overflow Menu -->
                    <button
                        @click="showMobileMenu = true"
                        type="button"
                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                        title="More POS Actions"
                    >
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                        </svg>
                    </button>
                </div>
            </header>

            <!-- ── Mobile Tab Switcher Bar ── -->
            <div class="grid grid-cols-2 p-1.5 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700/80 gap-1.5 flex-shrink-0 transition-colors duration-300">
                <button
                    @click="mobileActiveTab = 'catalog'"
                    type="button"
                    :class="[
                        'py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                        mobileActiveTab === 'catalog'
                            ? 'bg-theme text-white shadow-md shadow-theme/20'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 bg-slate-50 dark:bg-slate-800/60'
                    ]"
                >
                    <span>🛍️ Catalog</span>
                </button>
                <button
                    @click="mobileActiveTab = 'cart'"
                    type="button"
                    :class="[
                        'py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer relative',
                        mobileActiveTab === 'cart'
                            ? 'bg-emerald-600 text-white shadow-md shadow-emerald-900/30'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 bg-slate-50 dark:bg-slate-800/60'
                    ]"
                >
                    <span>🛒 Cart</span>
                    <span v-if="cart.items.length > 0" class="px-1.5 py-0.2 rounded-full text-[10px] font-black" :class="mobileActiveTab === 'cart' ? 'bg-white text-emerald-700' : 'bg-emerald-600 text-white'">
                        {{ cart.items.length }}
                    </span>
                    <span v-if="cart.grandTotal > 0" class="text-[11px] font-bold opacity-90">
                        • {{ format(cart.grandTotal) }}
                    </span>
                </button>
            </div>

            <!-- ── Tab 1: Mobile Catalog / Search View ── -->
            <div v-show="mobileActiveTab === 'catalog'" class="flex-1 flex flex-col overflow-hidden relative bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
                <!-- Search Component -->
                <div class="p-2.5 border-b border-slate-200 dark:border-slate-700/70 bg-white dark:bg-slate-800/80 flex-shrink-0 transition-colors duration-300">
                    <ProductSearch
                        ref="productSearchMobile"
                        :purchase-type="cart.purchaseType"
                        :settings="settings"
                        @select="(item) => cart.addItem(item)"
                    />
                </div>

                <!-- Catalog Content -->
                <div class="flex-1 overflow-y-auto p-3 pb-28">
                    <!-- Mode switch quick bar -->
                    <div class="flex items-center justify-between mb-3 px-1">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            {{ viewMode === 'gallery' ? 'Quick Product Grid' : 'Standard Search Mode' }}
                        </span>
                        <button
                            @click="viewMode = (viewMode === 'gallery' ? 'classic' : 'gallery')"
                            type="button"
                            class="text-xs text-theme hover:underline font-medium flex items-center gap-1 cursor-pointer"
                        >
                            <span>{{ viewMode === 'gallery' ? 'Switch to Classic' : 'Switch to Grid View' }}</span>
                            <span>⇄</span>
                        </button>
                    </div>

                    <ProductGrid
                        v-if="viewMode === 'gallery'"
                        :item-grids="itemGrids"
                        :settings="settings"
                        @select="(item) => cart.addItem(item, 'unit', null, !!(settStore.settings?.is_grid_separate_rows ?? settings?.is_grid_separate_rows))"
                    />
                    
                    <!-- Mobile Classic mode shows Quick Access Widget -->
                    <div v-else class="p-1">
                        <QuickAccessWidget
                            :quick-access="currentQuickAccess"
                            :settings="settings"
                            :purchase-type="cart.purchaseType"
                            @select="(item) => cart.addItem(item)"
                        />
                    </div>
                </div>

                <!-- Floating Bottom Quick Cart Bar (Appears when cart has items) -->
                <div
                    v-if="cart.items.length > 0"
                    class="absolute bottom-3 inset-x-3 z-20 transition-all duration-300 transform translate-y-0"
                >
                    <button
                        @click="mobileActiveTab = 'cart'"
                        type="button"
                        class="w-full p-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-2xl shadow-xl shadow-emerald-900/20 dark:shadow-emerald-950/50 border border-emerald-400/30 flex items-center justify-between transition cursor-pointer"
                    >
                        <div class="flex items-center gap-2.5 text-left">
                            <span class="p-2 rounded-xl bg-white/15 text-lg">🛒</span>
                            <div>
                                <div class="font-bold text-sm leading-tight">
                                    {{ cart.items.length }} {{ cart.items.length === 1 ? 'item' : 'items' }} in Cart
                                </div>
                                <div class="text-xs text-emerald-100 font-medium">
                                    Tap to review & checkout
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="font-black text-base tracking-wide">{{ format(cart.grandTotal) }}</span>
                            <span class="text-lg font-bold">→</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── Tab 2: Mobile Cart & Checkout View ── -->
            <div v-show="mobileActiveTab === 'cart'" class="flex-1 flex flex-col overflow-hidden bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
                <!-- Customer Selector & Purchase Type -->
                <div class="p-2.5 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/80 space-y-2 flex-shrink-0 transition-colors duration-300">
                    <CustomerSelector
                        :customer="cart.customer"
                        @select="cart.setCustomer"
                        @clear="cart.clearCustomer"
                    />

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            @click="cart.setPurchaseType('Consumer')"
                            :class="cart.purchaseType === 'Consumer'
                                ? 'bg-emerald-600 text-white font-bold shadow-xs'
                                : 'bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'"
                            class="py-1.5 rounded-xl text-xs font-medium transition cursor-pointer"
                        >Retail (Consumer)</button>
                        <button
                            @click="cart.setPurchaseType('Wholesale')"
                            :class="cart.purchaseType === 'Wholesale'
                                ? 'bg-blue-600 text-white font-bold shadow-xs'
                                : 'bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'"
                            class="py-1.5 rounded-xl text-xs font-medium transition cursor-pointer"
                        >Wholesale</button>
                    </div>
                </div>

                <!-- Cart Items Scroll List -->
                <div class="flex-1 overflow-y-auto p-2 min-h-0 bg-slate-50 dark:bg-slate-900">
                    <Cart :can-edit-price="canEditPrice" :settings="settings" />
                </div>

                <!-- Sticky Bottom Order Summary & Checkout Action -->
                <div class="p-3 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 space-y-2 flex-shrink-0 shadow-2xl transition-colors duration-300">
                    <div class="space-y-1 text-xs text-slate-700 dark:text-slate-300">
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Subtotal</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ format(cart.subtotal) }}</span>
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
                        <div class="flex justify-between font-black text-lg text-theme pt-1 border-t border-slate-200 dark:border-slate-700">
                            <span>TOTAL</span>
                            <span>{{ format(cart.grandTotal) }}</span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="grid grid-cols-3 gap-2 pt-1">
                        <button
                            @click="cart.clearCart()"
                            :disabled="!cart.items.length"
                            type="button"
                            class="py-2.5 rounded-xl bg-red-50 dark:bg-red-950/30 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30 text-xs font-bold transition disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                        >
                            Clear
                        </button>
                        <button
                            @click="showPayment = true"
                            :disabled="!cart.items.length"
                            type="button"
                            class="col-span-2 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white text-sm font-black shadow-lg shadow-emerald-950/20 dark:shadow-emerald-950/50 transition disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
                        >
                            <span>Charge {{ format(cart.grandTotal) }}</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Mobile Quick Actions Drawer Modal ── -->
            <div v-if="showMobileMenu" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-end justify-center p-0 lg:hidden">
                <div class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700 rounded-t-3xl w-full max-w-lg p-5 space-y-4 shadow-2xl animate-in slide-in-from-bottom duration-200 text-slate-900 dark:text-white transition-colors duration-300">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                        <h3 class="font-bold text-slate-900 dark:text-white text-base flex items-center gap-2">
                            <span>⚡ Quick Actions</span>
                        </h3>
                        <button @click="showMobileMenu = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 text-lg cursor-pointer">✕</button>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <button
                            @click="cart.items.length ? (showHold = true, showMobileMenu = false) : null"
                            :disabled="!cart.items.length"
                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700/80 text-left space-y-1 transition disabled:opacity-40 cursor-pointer"
                        >
                            <div class="text-lg">⏸️</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Hold Cart</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Save current order</div>
                        </button>

                        <button
                            @click="showHeld = true; showMobileMenu = false"
                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700/80 text-left space-y-1 transition cursor-pointer"
                        >
                            <div class="text-lg">📂</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                <span>Held Carts</span>
                                <span class="px-1.5 py-0.2 bg-amber-500 text-white rounded-full text-[10px]">{{ heldSales.length }}</span>
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Resume paused sales</div>
                        </button>

                        <button
                            v-if="canProcessReturn"
                            @click="showReturn = true; showMobileMenu = false"
                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700/80 text-left space-y-1 transition cursor-pointer"
                        >
                            <div class="text-lg">🔄</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Returns</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Process item return</div>
                        </button>

                        <button
                            @click="viewMode = (viewMode === 'gallery' ? 'classic' : 'gallery'); showMobileMenu = false"
                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700/80 text-left space-y-1 transition cursor-pointer"
                        >
                            <div class="text-lg">{{ viewMode === 'gallery' ? '📋' : '🖼️' }}</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ viewMode === 'gallery' ? 'Classic Mode' : 'Grid Mode' }}</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Switch catalog layout</div>
                        </button>

                        <button
                            v-if="settings?.is_offline_enabled"
                            @click="openOfflineModal(); showMobileMenu = false"
                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700/80 text-left space-y-1 transition cursor-pointer"
                        >
                            <div class="text-lg">📡</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                <span>Offline Queue</span>
                                <span v-if="pendingCount > 0" class="px-1.5 py-0.2 bg-rose-600 text-white rounded-full text-[10px]">{{ pendingCount }}</span>
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Sync status & queue</div>
                        </button>

                        <!-- Fullscreen Toggle Option -->
                        <button
                            @click="toggleFullscreen(); showMobileMenu = false"
                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700/80 text-left space-y-1 transition cursor-pointer"
                        >
                            <div class="text-lg">{{ isFullscreen ? '🗗' : '⛶' }}</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen Mode' }}</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ isFullscreen ? 'Return to windowed' : 'Fill full device display' }}</div>
                        </button>

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-200">Theme</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">Light / Dark</div>
                            </div>
                            <ThemeToggle />
                        </div>
                    </div>

                    <button
                        @click="toggleMobileSidebar(); showMobileMenu = false"
                        type="button"
                        class="w-full py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>🧭 Open Navigation Menu</span>
                    </button>
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
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-xl w-full overflow-hidden shadow-2xl space-y-4 p-5">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">📡</span>
                        <div>
                            <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                                <span>Offline Sales Queue</span>
                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400">
                                    ({{ queuedSalesList.length }} Total Transactions)
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Terminal Status: 
                                <strong :class="isOnline ? 'text-emerald-500' : 'text-rose-500'">
                                    {{ isOnline ? 'Connected (Online)' : 'Disconnected (Offline Mode)' }}
                                </strong>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="updatePendingCount"
                            type="button"
                            title="Re-query local IndexedDB queue"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                        >
                            🔄 Refresh
                        </button>
                        <button @click="showOfflineModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg p-1">✕</button>
                    </div>
                </div>

                <!-- Pending Summary Banner if pending transactions exist -->
                <div v-if="pendingSalesCount > 0" class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300">
                        <span class="text-sm">⏳</span>
                        <span><strong>{{ pendingSalesCount }}</strong> offline {{ pendingSalesCount === 1 ? 'sale' : 'sales' }} waiting to sync</span>
                    </div>
                    <div class="font-bold text-amber-900 dark:text-amber-200">
                        Total: {{ format(pendingTotalAmount) }}
                    </div>
                </div>

                <!-- Status Filter Tabs -->
                <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-900/60 rounded-xl text-xs font-semibold">
                    <button
                        type="button"
                        @click="offlineTabFilter = 'all'"
                        :class="[
                            'flex-1 py-1.5 px-2 rounded-lg transition text-center cursor-pointer',
                            offlineTabFilter === 'all'
                                ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs font-bold'
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                        ]"
                    >
                        All ({{ queuedSalesList.length }})
                    </button>
                    <button
                        type="button"
                        @click="offlineTabFilter = 'pending'"
                        :class="[
                            'flex-1 py-1.5 px-2 rounded-lg transition text-center cursor-pointer',
                            offlineTabFilter === 'pending'
                                ? 'bg-amber-500 text-white shadow-xs font-bold'
                                : 'text-amber-600 dark:text-amber-400 hover:bg-amber-500/10'
                        ]"
                    >
                        Pending ({{ pendingSalesCount }})
                    </button>
                    <button
                        type="button"
                        @click="offlineTabFilter = 'synced'"
                        :class="[
                            'flex-1 py-1.5 px-2 rounded-lg transition text-center cursor-pointer',
                            offlineTabFilter === 'synced'
                                ? 'bg-emerald-600 text-white shadow-xs font-bold'
                                : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600/10'
                        ]"
                    >
                        Synced ({{ syncedSalesCount }})
                    </button>
                    <button
                        type="button"
                        @click="offlineTabFilter = 'failed'"
                        :class="[
                            'flex-1 py-1.5 px-2 rounded-lg transition text-center cursor-pointer',
                            offlineTabFilter === 'failed'
                                ? 'bg-rose-600 text-white shadow-xs font-bold'
                                : 'text-rose-600 dark:text-rose-400 hover:bg-rose-600/10'
                        ]"
                    >
                        Failed ({{ failedSalesCount }})
                    </button>
                </div>

                <!-- Search Input for Queued Sales -->
                <div v-if="queuedSalesList.length > 5">
                    <input
                        v-model="offlineSearchQuery"
                        type="text"
                        placeholder="Search offline sales by receipt ID, cashier or payment method..."
                        class="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-hidden focus:ring-1 focus:ring-emerald-500"
                    />
                </div>

                <!-- Sales Queue Listing -->
                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    <div v-if="filteredQueuedSales.length === 0" class="text-center py-8 text-slate-400 text-sm">
                        <p class="text-3xl mb-2">🎉</p>
                        <span v-if="offlineTabFilter === 'pending'">No pending offline sales to sync.</span>
                        <span v-else-if="offlineTabFilter === 'failed'">No failed sync records.</span>
                        <span v-else-if="offlineTabFilter === 'synced'">No synced records on this terminal.</span>
                        <span v-else>No offline transactions currently recorded on this terminal.</span>
                    </div>
                    <div
                        v-for="sale in filteredQueuedSales"
                        :key="sale.offline_sale_id"
                        class="p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-between text-xs transition"
                    >
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <span>{{ sale.receipt_id }}</span>
                                <span
                                    :class="[
                                        'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase',
                                        sale.sync_status === 'synced' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' :
                                        sale.sync_status === 'failed' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' :
                                        'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 animate-pulse'
                                    ]"
                                >
                                    {{ sale.sync_status }}
                                </span>
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 text-[11px] mt-0.5">
                                {{ new Date(sale.created_at).toLocaleTimeString() }} • {{ sale.cashier_name || 'Cashier' }} • {{ sale.payment_method }} • {{ sale.payload?.sale_orders?.length || 1 }} items
                            </div>
                            <div v-if="sale.error_message" class="text-rose-500 text-[11px] mt-1 font-mono">
                                {{ sale.error_message }}
                            </div>
                        </div>
                        <div class="font-bold text-slate-800 dark:text-white text-sm text-right">
                            {{ format(sale.final_total) }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                    <button
                        @click="refreshCatalog"
                        type="button"
                        :disabled="!isOnline"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 transition disabled:opacity-40"
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
