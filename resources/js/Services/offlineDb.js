import Dexie from 'dexie'

export const db = new Dexie('SkynetPosOfflineDB')

db.version(1).stores({
    items:           'id, barcode_number, item_name, category_id, is_imei_tracked, [category_id+is_imei_tracked]',
    available_imeis: 'id, item_id, imei_or_device_id, status, [item_id+status]',
    settings:        'key',
    customers:       'id, name, phone',
    categories:      'id, name',
    queued_sales:    'offline_sale_id, created_at, sync_status',
})

/**
 * Ingest fresh bootstrap dataset from server into IndexedDB.
 */
export async function saveBootstrapData(payload) {
    if (!payload) return

    await db.transaction('rw', db.items, db.available_imeis, db.settings, db.customers, db.categories, async () => {
        if (payload.items && Array.isArray(payload.items)) {
            await db.items.clear()
            await db.items.bulkPut(payload.items)
        }

        if (payload.available_imeis && Array.isArray(payload.available_imeis)) {
            await db.available_imeis.clear()
            await db.available_imeis.bulkPut(payload.available_imeis)
        }

        if (payload.customers && Array.isArray(payload.customers)) {
            await db.customers.clear()
            await db.customers.bulkPut(payload.customers)
        }

        if (payload.categories && Array.isArray(payload.categories)) {
            await db.categories.clear()
            await db.categories.bulkPut(payload.categories)
        }

        if (payload.settings) {
            await db.settings.put({ key: 'branch_settings', value: payload.settings })
        }

        if (payload.branch) {
            await db.settings.put({ key: 'branch_info', value: payload.branch })
        }

        if (payload.activeDiscount) {
            await db.settings.put({ key: 'active_discount', value: payload.activeDiscount })
        }

        await db.settings.put({ key: 'last_sync_time', value: new Date().toISOString() })
    })
}

/**
 * Search items locally from IndexedDB.
 */
export async function searchLocalItems(query = '', purchaseType = 'Consumer') {
    const q = String(query).trim().toLowerCase()
    const allItems = await db.items.toArray()

    let filtered = allItems
    if (q) {
        filtered = allItems.filter(item => {
            const nameMatch = (item.item_name || '').toLowerCase().includes(q)
            const barcodeMatch = (item.barcode_number || '').toLowerCase().startsWith(q) || (item.barcode_number || '').includes(q)
            return nameMatch || barcodeMatch
        })
    }

    const isWholesale = (purchaseType === 'Wholesale')
    return filtered.slice(0, 25).map(item => {
        let displayPrice = parseFloat(item.price || 0)
        if (isWholesale && parseFloat(item.wholesale_price || 0) > 0) {
            displayPrice = parseFloat(item.wholesale_price)
        }

        let packPrice = parseFloat(item.pack_price || 0)
        if (isWholesale && parseFloat(item.pack_wholesale_price || 0) > 0) {
            packPrice = parseFloat(item.pack_wholesale_price)
        }

        let cartonPrice = parseFloat(item.carton_price || 0)
        if (isWholesale && parseFloat(item.carton_wholesale_price || 0) > 0) {
            cartonPrice = parseFloat(item.carton_wholesale_price)
        }

        return {
            ...item,
            qty: item.front_store_qty ?? 0,
            total_qty: (item.front_store_qty ?? 0) + (item.back_store_qty ?? 0),
            display_price: displayPrice,
            pack_display_price: packPrice,
            carton_display_price: cartonPrice,
        }
    })
}

/**
 * Find item locally by exact barcode.
 */
export async function getLocalItemByBarcode(barcode, purchaseType = 'Consumer') {
    const cleanCode = String(barcode).trim()
    if (!cleanCode) return null

    const item = await db.items.where('barcode_number').equalsIgnoreCase(cleanCode).first()
    if (!item) return null

    const results = await searchLocalItems(cleanCode, purchaseType)
    return results.find(i => String(i.barcode_number).trim() === cleanCode) || results[0] || item
}

/**
 * Get available in-stock IMEIs for an item from local IndexedDB.
 */
export async function getLocalAvailableImeis(itemId) {
    if (!itemId) return []
    const imeis = await db.available_imeis
        .where('item_id')
        .equals(Number(itemId))
        .filter(u => u.status === 'in_stock')
        .toArray()

    return imeis.map(u => u.imei_or_device_id)
}

/**
 * Consume an IMEI locally during an offline sale to avoid assigning it twice.
 */
export async function consumeLocalImei(itemId, imei) {
    if (!itemId || !imei) return
    const record = await db.available_imeis
        .where('item_id')
        .equals(Number(itemId))
        .filter(u => u.imei_or_device_id === imei)
        .first()

    if (record) {
        await db.available_imeis.update(record.id, { status: 'sold_locally' })
    }
}

/**
 * Restore an IMEI locally if removed from cart.
 */
export async function restoreLocalImei(itemId, imei) {
    if (!itemId || !imei) return
    const record = await db.available_imeis
        .where('item_id')
        .equals(Number(itemId))
        .filter(u => u.imei_or_device_id === imei)
        .first()

    if (record) {
        await db.available_imeis.update(record.id, { status: 'in_stock' })
    }
}

/**
 * Decrement local front-store stock quantity for instant local updates.
 */
export async function deductLocalStock(itemId, baseQty) {
    const item = await db.items.get(Number(itemId))
    if (item) {
        const newQty = Math.max(0, (item.front_store_qty || 0) - Number(baseQty))
        await db.items.update(item.id, { front_store_qty: newQty })
    }
}

/**
 * Insert a sale into the offline synchronization queue.
 */
export async function queueOfflineSale(saleRecord) {
    await db.queued_sales.put({
        offline_sale_id: saleRecord.offline_sale_id,
        receipt_id:      saleRecord.receipt_id,
        created_at:      saleRecord.created_at || new Date().toISOString(),
        cashier_id:      saleRecord.cashier_id,
        cashier_name:    saleRecord.cashier_name,
        payment_method:  saleRecord.payment_method,
        final_total:     saleRecord.final_total,
        sync_status:     'pending', // 'pending' | 'syncing' | 'synced' | 'failed'
        payload:         saleRecord,
        error_message:   null,
        attempts:        0,
    })
}

/**
 * Get all pending/failed sales waiting to be synchronized in FIFO order.
 */
export async function getPendingOfflineSales() {
    return await db.queued_sales
        .where('sync_status')
        .anyOf(['pending', 'failed'])
        .sortBy('created_at')
}

/**
 * Get count of pending offline sales.
 */
export async function getPendingOfflineSalesCount() {
    return await db.queued_sales
        .where('sync_status')
        .anyOf(['pending', 'failed'])
        .count()
}

/**
 * Mark a queued sale as successfully synced with the server.
 */
export async function markSaleSynced(offlineSaleId, serverData = {}) {
    await db.queued_sales.update(offlineSaleId, {
        sync_status: 'synced',
        server_sale_id: serverData.sale_id,
        server_receipt_id: serverData.receipt_id,
        synced_at: new Date().toISOString(),
    })
}

/**
 * Mark a queued sale as failed during sync attempt.
 */
export async function markSaleFailed(offlineSaleId, errorMsg) {
    const record = await db.queued_sales.get(offlineSaleId)
    if (record) {
        await db.queued_sales.update(offlineSaleId, {
            sync_status: 'failed',
            error_message: errorMsg,
            attempts: (record.attempts || 0) + 1,
        })
    }
}

/**
 * Get all queued sales for inspection drawer.
 */
export async function getAllQueuedSales() {
    return await db.queued_sales.orderBy('created_at').reverse().toArray()
}
