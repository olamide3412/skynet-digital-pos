<script setup>
import { computed, onMounted } from 'vue'
import { useCurrency } from '@/Composables/useCurrency'
import { usePosSettingsStore } from '@/Stores/posSettings'
import { useOfflineSync } from '@/Composables/useOfflineSync'
import { removeLocalHeldSale, resumeLocalHeldSale } from '@/Services/offlineDb'
import axios from 'axios'

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'load', 'deleted'])
const { format } = useCurrency()
const settStore = usePosSettingsStore()

const {
    isOnline,
    activeHeldSales,
    heldSalesCount,
    lastHeldRefreshTime,
    isRefreshingHeld,
    pullServerHeldSales,
    updateHeldSales,
} = useOfflineSync()

const visibilityMode = computed(() => {
    return props.settings?.held_sales_visibility || settStore.settings?.held_sales_visibility || 'device_only'
})

const isSharedMode = computed(() => visibilityMode.value === 'shared_across_branch')

onMounted(async () => {
    await updateHeldSales()
})

const formattedLastRefresh = computed(() => {
    if (!lastHeldRefreshTime.value) return null
    try {
        const d = new Date(lastHeldRefreshTime.value)
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    } catch {
        return null
    }
})

function formatHoldDate(dateStr) {
    if (!dateStr) return ''
    try {
        const d = new Date(dateStr)
        const now = new Date()
        const isToday = d.toDateString() === now.toDateString()
        const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        return isToday ? `Today at ${timeStr}` : `${d.toLocaleDateString([], { month: 'short', day: 'numeric' })} at ${timeStr}`
    } catch {
        return ''
    }
}

async function onRefreshClick() {
    if (!isOnline.value) {
        alert('You are currently offline. Held sales are loaded from your local device storage.')
        return
    }
    await pullServerHeldSales()
}

async function resumeCart(held) {
    const heldSaleId = held.held_sale_id || held.id
    const localRecord = await resumeLocalHeldSale(heldSaleId)
    const dataToLoad = localRecord || held

    // If online and shared mode, delete from server so other devices remove it on next refresh
    if (isSharedMode.value && isOnline.value) {
        const identifier = held.server_id || held.id || heldSaleId
        axios.delete(route('pos.api.held-sales.destroy', identifier)).catch(e => {
            console.warn('Notice deleting resumed held sale from server:', e)
        })
    }

    await updateHeldSales()
    emit('load', dataToLoad)
    emit('close')
}

async function discardCart(held) {
    if (confirm(`Are you sure you want to discard held cart "${held.hold_name || 'Unnamed'}"?`)) {
        const heldSaleId = held.held_sale_id || held.id
        await removeLocalHeldSale(heldSaleId, 'discarded')

        if (isSharedMode.value && isOnline.value) {
            const identifier = held.server_id || held.id || heldSaleId
            axios.delete(route('pos.api.held-sales.destroy', identifier)).catch(e => {
                console.warn('Notice deleting discarded held sale from server:', e)
            })
        }

        await updateHeldSales()
        emit('deleted')
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-700 transition-colors overflow-hidden flex flex-col max-h-[85vh]">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm sm:text-base">
                        <span>📂</span>
                        <span>Held Carts ({{ heldSalesCount }})</span>
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Mode: <strong class="text-slate-700 dark:text-slate-200">{{ isSharedMode ? 'Shared Across Branch' : 'Device Only' }}</strong>
                        <span v-if="isSharedMode && formattedLastRefresh" class="ml-1 text-[10px] text-slate-400">
                            · Last refreshed {{ formattedLastRefresh }}
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Manual Refresh Button for Shared Across Branch Mode (§3.3 & §3.6) -->
                    <button
                        v-if="isSharedMode"
                        @click="onRefreshClick"
                        :disabled="isRefreshingHeld"
                        type="button"
                        class="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold flex items-center gap-1.5 transition disabled:opacity-50 cursor-pointer shadow-xs"
                        title="Pull latest held sales from other branch terminals"
                    >
                        <span :class="{ 'animate-spin': isRefreshingHeld }">🔄</span>
                        <span class="hidden sm:inline">{{ isRefreshingHeld ? 'Refreshing…' : 'Refresh' }}</span>
                    </button>

                    <button
                        @click="emit('close')"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer text-xs"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Content List -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60 p-1">
                <div v-if="!activeHeldSales.length" class="text-center text-slate-400 dark:text-slate-500 py-12 text-sm">
                    <span class="text-3xl block mb-2 opacity-50">📂</span>
                    No held carts currently saved on this terminal.
                </div>
                <div
                    v-for="held in activeHeldSales"
                    :key="held.held_sale_id || held.id"
                    class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 rounded-xl transition gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                {{ held.hold_name || 'Unnamed Hold' }}
                            </p>
                            <span v-if="held.purchase_type === 'Wholesale'" class="px-1.5 py-0.2 bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded">
                                Wholesale
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                {{ held.items?.length || 0 }} {{ (held.items?.length === 1) ? 'item' : 'items' }}
                            </span>
                            <span v-if="held.total_amount > 0" class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                · {{ format(held.total_amount) }}
                            </span>
                            <span v-if="held.customer_name || held.customer?.name" class="text-slate-600 dark:text-slate-300">
                                · 👤 {{ held.customer_name || held.customer?.name }}
                            </span>
                            <!-- Created by Cashier Badge -->
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-950/60 px-2 py-0.5 rounded-md border border-violet-200/70 dark:border-violet-800/60">
                                <span class="text-violet-500 dark:text-violet-400">👤 Held by:</span>
                                <span class="font-bold">{{ held.cashier_name || 'Staff' }}</span>
                            </span>
                            <span v-if="held.created_at" class="text-slate-400 dark:text-slate-500 text-[10px]">
                                · 🕒 {{ formatHoldDate(held.created_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button
                            @click="discardCart(held)"
                            type="button"
                            class="px-2.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60 text-xs font-semibold rounded-xl transition cursor-pointer"
                        >
                            Discard
                        </button>
                        <button
                            @click="resumeCart(held)"
                            type="button"
                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition cursor-pointer shadow-xs flex items-center gap-1"
                        >
                            <span>Resume</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    <span v-if="isOnline" class="text-emerald-600 dark:text-emerald-400">● Online</span>
                    <span v-else class="text-amber-600 dark:text-amber-400">○ Offline Local Store</span>
                </span>
                <button
                    @click="emit('close')"
                    type="button"
                    class="px-5 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-600 transition cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>

