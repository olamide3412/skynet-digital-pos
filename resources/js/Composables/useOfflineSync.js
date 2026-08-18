import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import {
    saveBootstrapData,
    getPendingOfflineSales,
    getPendingOfflineSalesCount,
    markSaleSynced,
    markSaleFailed,
    getAllQueuedSales,
} from '@/Services/offlineDb'

// Shared singleton state across all components
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const isSyncing = ref(false)
const pendingCount = ref(0)
const lastSyncTime = ref(null)
const queuedSalesList = ref([])

export function useOfflineSync() {
    const syncState = computed(() => {
        if (isSyncing.value) return 'syncing'
        if (!isOnline.value) return 'offline'
        return 'online'
    })

    async function updatePendingCount() {
        try {
            pendingCount.value = await getPendingOfflineSalesCount()
            queuedSalesList.value = await getAllQueuedSales()
        } catch (e) {
            console.warn('Could not read IndexedDB pending sales count:', e)
        }
    }

    /**
     * Download fresh branch dataset from server and prime local IndexedDB store.
     */
    async function refreshCatalog() {
        if (!isOnline.value) return false
        try {
            const res = await axios.get(route('pos.api.offline.bootstrap'), { timeout: 10000 })
            if (res.data) {
                await saveBootstrapData(res.data)
                lastSyncTime.value = new Date().toISOString()
                return true
            }
        } catch (e) {
            console.warn('Offline catalog refresh failed (will retry when online):', e)
        }
        return false
    }

    /**
     * Check actual server reachability (beyond browser navigator.onLine flag).
     */
    async function checkServerHealth() {
        if (!navigator.onLine) {
            isOnline.value = false
            return false
        }
        try {
            await axios.get(route('pos.api.settings.show'), { timeout: 4000 })
            isOnline.value = true
            return true
        } catch (e) {
            if (e.code === 'ECONNABORTED' || !e.response) {
                isOnline.value = false
                return false
            }
            // If server returned an HTTP response (401, 403, 500), we still have connectivity
            isOnline.value = true
            return true
        }
    }

    /**
     * Flush all queued offline sales to server in FIFO order.
     */
    async function syncQueuedSales() {
        if (isSyncing.value) return
        await updatePendingCount()

        if (pendingCount.value === 0) return

        const online = await checkServerHealth()
        if (!online) {
            isOnline.value = false
            return
        }

        isSyncing.value = true

        try {
            const pendingSales = await getPendingOfflineSales()
            if (pendingSales.length === 0) {
                isSyncing.value = false
                return
            }

            // Extract the payloads
            const salesPayloads = pendingSales.map(s => s.payload)

            const res = await axios.post(route('pos.api.offline.sync'), {
                sales: salesPayloads,
            }, { timeout: 30000 })

            if (res.data && Array.isArray(res.data.results)) {
                for (const result of res.data.results) {
                    if (result.status === 'synced') {
                        await markSaleSynced(result.offline_sale_id, result)
                    } else if (result.status === 'failed') {
                        await markSaleFailed(result.offline_sale_id, result.error)
                    }
                }
            }

            // Re-fetch catalog to update live stock counts and IMEI pools on device
            await refreshCatalog()
            await updatePendingCount()
        } catch (err) {
            console.error('Error during offline sales sync:', err)
        } finally {
            isSyncing.value = false
        }
    }

    // Event Handlers
    async function handleOnline() {
        isOnline.value = true
        await checkServerHealth()
        if (isOnline.value) {
            await syncQueuedSales()
            await refreshCatalog()
        }
    }

    function handleOffline() {
        isOnline.value = false
    }

    let intervalId = null

    function startSyncMonitor() {
        if (typeof window === 'undefined') return

        window.addEventListener('online', handleOnline)
        window.addEventListener('offline', handleOffline)

        updatePendingCount()
        refreshCatalog()

        // Periodic heartbeat & auto-sync every 25 seconds
        if (!intervalId) {
            intervalId = setInterval(async () => {
                const online = await checkServerHealth()
                if (online) {
                    await syncQueuedSales()
                }
            }, 25000)
        }
    }

    function stopSyncMonitor() {
        if (typeof window === 'undefined') return
        window.removeEventListener('online', handleOnline)
        window.removeEventListener('offline', handleOffline)
        if (intervalId) {
            clearInterval(intervalId)
            intervalId = null
        }
    }

    return {
        isOnline,
        isSyncing,
        syncState,
        pendingCount,
        lastSyncTime,
        queuedSalesList,
        refreshCatalog,
        syncQueuedSales,
        updatePendingCount,
        startSyncMonitor,
        stopSyncMonitor,
        checkServerHealth,
    }
}
