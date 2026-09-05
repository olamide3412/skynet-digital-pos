import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import {
    saveBootstrapData,
    getPendingOfflineSales,
    getPendingOfflineSalesCount,
    markSaleSynced,
    markSaleFailed,
    getAllQueuedSales,
    getActiveLocalHeldSales,
    getActiveLocalHeldSalesCount,
    getPendingHeldSales,
    markHeldSaleSynced,
    mergeServerHeldSales,
} from '@/Services/offlineDb'

// Shared singleton state across all components
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const isSyncing = ref(false)
const pendingCount = ref(0)
const lastSyncTime = ref(null)
const queuedSalesList = ref([])

// Held Sales Shared State
const activeHeldSales = ref([])
const heldSalesCount = ref(0)
const lastHeldRefreshTime = ref(null)
const isRefreshingHeld = ref(false)

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

    async function updateHeldSales() {
        try {
            const list = await getActiveLocalHeldSales()
            activeHeldSales.value = list
            heldSalesCount.value = list.length
        } catch (e) {
            console.warn('Could not read local held sales from IndexedDB:', e)
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
     * Flush all queued offline sales to server in FIFO order (loops in batches until 100% complete).
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
            let hasMore = true
            while (hasMore) {
                const pendingSales = await getPendingOfflineSales(100)
                if (!pendingSales || pendingSales.length === 0) {
                    hasMore = false
                    break
                }

                // Extract the payloads
                const salesPayloads = pendingSales.map(s => s.payload)

                const res = await axios.post(route('pos.api.offline.sync'), {
                    sales: salesPayloads,
                }, { timeout: 45000 })

                let syncedInThisBatch = 0
                if (res.data && Array.isArray(res.data.results)) {
                    for (const result of res.data.results) {
                        if (result.status === 'synced') {
                            await markSaleSynced(result.offline_sale_id, result)
                            syncedInThisBatch++
                        } else if (result.status === 'failed') {
                            await markSaleFailed(result.offline_sale_id, result.error)
                        }
                    }
                }

                // If nothing synced (e.g. server error or permanent conflict), break to avoid infinite loop
                if (syncedInThisBatch === 0 && res.data?.results?.length > 0) {
                    hasMore = false
                }

                // If fewer items than the batch limit were fetched, we have reached the end
                if (pendingSales.length < 100) {
                    hasMore = false
                }

                await updatePendingCount()
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

    /**
     * Synchronize locally held sales to server in background if branch is in 'shared_across_branch' mode.
     */
    async function syncHeldSales(visibilityMode = 'device_only') {
        if (visibilityMode !== 'shared_across_branch') return
        if (!isOnline.value) return

        try {
            const pendingHolds = await getPendingHeldSales(50)
            if (!pendingHolds || !pendingHolds.length) return

            const res = await axios.post(route('pos.api.held-sales.sync'), {
                held_sales: pendingHolds,
            }, { timeout: 15000 })

            if (res.data && Array.isArray(res.data.results)) {
                for (const r of res.data.results) {
                    if (r.status === 'synced') {
                        await markHeldSaleSynced(r.held_sale_id, r)
                    }
                }
            }
            await updateHeldSales()
        } catch (err) {
            console.warn('Background held sales sync notice:', err)
        }
    }

    /**
     * Manual Pull: Fetch held sales from server and merge into local IndexedDB per §3.6.
     */
    async function pullServerHeldSales() {
        if (isRefreshingHeld.value) return false
        isRefreshingHeld.value = true

        try {
            const online = await checkServerHealth()
            if (!online) {
                isOnline.value = false
                return false
            }

            const res = await axios.get(route('pos.api.held-sales.index'), { timeout: 10000 })
            if (res.data && Array.isArray(res.data)) {
                await mergeServerHeldSales(res.data)
                lastHeldRefreshTime.value = new Date().toISOString()
                await updateHeldSales()
                return true
            }
        } catch (err) {
            console.warn('Pull held sales error:', err)
        } finally {
            isRefreshingHeld.value = false
        }
        return false
    }

    // Event Handlers
    async function handleOnline() {
        isOnline.value = true
        await checkServerHealth()
        if (isOnline.value) {
            await syncQueuedSales()
            await refreshCatalog()
            await updateHeldSales()
        }
    }

    function handleOffline() {
        isOnline.value = false
    }

    let intervalId = null

    function startSyncMonitor(visibilityMode = 'device_only') {
        if (typeof window === 'undefined') return

        window.addEventListener('online', handleOnline)
        window.addEventListener('offline', handleOffline)

        updatePendingCount()
        updateHeldSales()
        refreshCatalog()

        // Periodic heartbeat & auto-sync every 25 seconds
        if (!intervalId) {
            intervalId = setInterval(async () => {
                const online = await checkServerHealth()
                if (online) {
                    await syncQueuedSales()
                    await syncHeldSales(visibilityMode)
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
        activeHeldSales,
        heldSalesCount,
        lastHeldRefreshTime,
        isRefreshingHeld,
        refreshCatalog,
        syncQueuedSales,
        syncHeldSales,
        pullServerHeldSales,
        updatePendingCount,
        updateHeldSales,
        startSyncMonitor,
        stopSyncMonitor,
        checkServerHealth,
    }
}

