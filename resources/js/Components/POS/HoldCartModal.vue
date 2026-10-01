<script setup>
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useCartStore } from '@/Stores/cart'
import { usePosSettingsStore } from '@/Stores/posSettings'
import { saveLocalHeldSale } from '@/Services/offlineDb'
import { useOfflineSync } from '@/Composables/useOfflineSync'

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'held'])
const page = usePage()
const cart = useCartStore()
const settStore = usePosSettingsStore()
const { isOnline, updateHeldSales, syncHeldSales } = useOfflineSync()

const holdName = ref('')
const saving = ref(false)
const error = ref('')

function getCurrentCashier() {
    const u = page?.props?.auth?.user
    if (u) {
        try {
            localStorage.setItem('pos_active_cashier', JSON.stringify({
                id: u.id,
                name: u.name,
                full_name: u.full_name || u.name,
            }))
        } catch (e) {}
        return {
            id: u.id,
            name: u.full_name || u.name || 'Cashier',
        }
    }
    try {
        const cached = localStorage.getItem('pos_active_cashier')
        if (cached) {
            const parsed = JSON.parse(cached)
            return {
                id: parsed.id ?? null,
                name: parsed.full_name || parsed.name || 'Cashier',
            }
        }
    } catch (e) {}
    return { id: null, name: 'Cashier' }
}

async function holdCart() {
    if (!cart.items.length) return
    saving.value = true
    error.value  = ''

    try {
        const heldSaleId = (typeof crypto !== 'undefined' && crypto.randomUUID) 
            ? crypto.randomUUID() 
            : 'held_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6)

        const visibilityMode = props.settings?.held_sales_visibility || settStore.settings?.held_sales_visibility || 'device_only'
        const cashier = getCurrentCashier()

        const heldPayload = {
            held_sale_id:     heldSaleId,
            hold_name:        holdName.value.trim() || 'Held Cart',
            user_id:          cashier.id,
            cashier_id:       cashier.id,
            cashier_name:     cashier.name,
            customer:         cart.customer ? JSON.parse(JSON.stringify(cart.customer)) : null,
            customer_id:      cart.customer?.id ?? null,
            customer_name:    cart.customer?.name ?? null,
            customer_phone:   cart.customer?.phone ?? null,
            purchase_type:    cart.purchaseType,
            consultation_fee: parseFloat(cart.consultationFee || 0),
            discount:         cart.discount ? JSON.parse(JSON.stringify(cart.discount)) : null,
            total_amount:     parseFloat(cart.grandTotal || 0),
            created_at:       new Date().toISOString(),
            status:           'active',
            sync_status:      'pending',
            items: cart.items.map(i => ({
                item_id:           i.item_id,
                item_name:         i.item_name,
                qty:               Number(i.qty || 1),
                price:             parseFloat(i.unit_price || i.price || 0),
                unit_price:        parseFloat(i.unit_price || i.price || 0),
                buy_price:         parseFloat(i.buy_price || 0),
                unit_used:         i.unit_used || 'unit',
                purchase_type:     i.purchase_type || cart.purchaseType,
                selected_imei:     i.selected_imei || null,
                imei_or_device_id: i.selected_imei || null,
                is_imei_tracked:   Boolean(i.is_imei_tracked),
                unit_label:        i.unit_label || 'Unit',
                units:             Array.isArray(i.units) ? JSON.parse(JSON.stringify(i.units)) : [],
            })),
        }

        // 1. ALWAYS write to local IndexedDB first (works 100% offline & instant)
        await saveLocalHeldSale(JSON.parse(JSON.stringify(heldPayload)))

        // 2. Clear current cart
        cart.clearCart()

        // 3. Update reactive count in composable
        await updateHeldSales()

        // 4. If online & shared branch mode, trigger background sync
        if (visibilityMode === 'shared_across_branch' && isOnline.value) {
            syncHeldSales('shared_across_branch').catch(err => {
                console.warn('Background hold sync notice:', err)
            })
        }

        emit('held')
        emit('close')
    } catch (e) {
        console.error('Failed to hold cart locally:', e)
        error.value = 'Failed to hold cart locally. Please try again.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-700 transition-colors overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base flex items-center gap-2">
                    <span>📌</span>
                    <span>Hold Current Cart</span>
                </h3>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer text-xs"
                >
                    ✕
                </button>
            </div>
            <div class="px-5 py-4 space-y-3">
                <p class="text-slate-500 dark:text-slate-400 text-xs leading-relaxed">
                    <strong class="text-slate-700 dark:text-slate-200">{{ cart.items.length }} item(s)</strong> will be held in memory. Add an optional label to identify this customer's cart quickly.
                </p>
                <input
                    v-model="holdName"
                    type="text"
                    placeholder="e.g. Table 4 / Blue Shirt Customer"
                    class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl outline-none border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 text-sm transition placeholder-slate-400"
                />
                <p v-if="error" class="text-rose-600 dark:text-rose-400 text-xs font-semibold">{{ error }}</p>
            </div>
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex gap-2">
                <button
                    @click="emit('close')"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition hover:bg-slate-200 dark:hover:bg-slate-600 cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    @click="holdCart"
                    :disabled="saving"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition disabled:opacity-40 shadow-xs cursor-pointer"
                >
                    {{ saving ? 'Holding…' : 'Hold Cart' }}
                </button>
            </div>
        </div>
    </div>
</template>
