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

db.version(2).stores({
    items:           'id, barcode_number, item_name, category_id, is_imei_tracked, [category_id+is_imei_tracked]',
    available_imeis: 'id, item_id, imei_or_device_id, status, [item_id+status]',
    settings:        'key',
    customers:       'id, name, phone',
    categories:      'id, name',
    queued_sales:    'offline_sale_id, created_at, sync_status',
    held_sales:      'held_sale_id, created_at, status, sync_status',
})

db.version(3).stores({
    items:           'id, barcode_number, item_name, category_id, is_imei_tracked, [category_id+is_imei_tracked]',
    available_imeis: 'id, item_id, imei_or_device_id, status, [item_id+status]',
    settings:        'key',
    customers:       'id, name, phone',
    categories:      'id, name',
    queued_sales:    'offline_sale_id, created_at, sync_status',
    held_sales:      'held_sale_id, created_at, status, sync_status',
    item_discounts:  'id, item_id, is_active',
    discount_groups: 'id, is_active',
})

/**
 * Ingest fresh bootstrap dataset from server into IndexedDB.
 */
export async function saveBootstrapData(payload) {
    if (!payload) return

    await db.transaction('rw', db.items, db.available_imeis, db.settings, db.customers, db.categories, db.item_discounts, db.discount_groups, async () => {
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

        if (payload.item_discounts && Array.isArray(payload.item_discounts)) {
            await db.item_discounts.clear()
            await db.item_discounts.bulkPut(payload.item_discounts)
        }

        if (payload.discount_groups && Array.isArray(payload.discount_groups)) {
            await db.discount_groups.clear()
            await db.discount_groups.bulkPut(payload.discount_groups)
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

        if (payload.quick_access) {
            await db.settings.put({ key: 'quick_access', value: payload.quick_access })
        }

        await db.settings.put({ key: 'last_sync_time', value: new Date().toISOString() })
    })
}


/**
 * Retrieve cached quick access widget data from IndexedDB.
 */
export async function getLocalQuickAccessData() {
    const record = await db.settings.get('quick_access')
    return record?.value || {
        mode:       'none',
        title:      '',
        subtitle:   '',
        items:      [],
        categories: [],
    }
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

        const mappedUnits = (Array.isArray(item.units) ? item.units : []).map(u => {
            let uPrice = parseFloat(u.selling_price || 0)
            if (isWholesale && parseFloat(u.wholesale_price || 0) > 0) {
                uPrice = parseFloat(u.wholesale_price)
            }
            return {
                ...u,
                display_price: uPrice,
            }
        })

        return {
            ...item,
            qty: item.front_store_qty ?? 0,
            total_qty: (item.front_store_qty ?? 0) + (item.back_store_qty ?? 0),
            display_price: displayPrice,
            units: mappedUnits,
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
 * Search customers locally from IndexedDB.
 */
export async function searchLocalCustomers(query = '') {
    const q = String(query).trim().toLowerCase()
    if (!q) return []
    const allCustomers = await db.customers.toArray()
    return allCustomers.filter(c => {
        const nameMatch = (c.name || '').toLowerCase().includes(q)
        const phoneMatch = (c.phone || '').toLowerCase().includes(q)
        return nameMatch || phoneMatch
    }).slice(0, 20)
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
 * Reserve IMEIs locally when a cart is held.
 */
export async function reserveLocalImeisForHold(items = []) {
    if (!Array.isArray(items)) return
    for (const item of items) {
        const imei = item.selected_imei || item.imei_or_device_id
        if (imei && item.item_id) {
            const record = await db.available_imeis
                .where('item_id')
                .equals(Number(item.item_id))
                .filter(u => u.imei_or_device_id === imei)
                .first()
            if (record) {
                await db.available_imeis.update(record.id, { status: 'held_locally' })
            }
        }
    }
}

/**
 * Release IMEIs back to in_stock when a held sale is discarded or completed remotely.
 */
export async function releaseLocalImeisForHold(items = []) {
    if (!Array.isArray(items)) return
    for (const item of items) {
        const imei = item.selected_imei || item.imei_or_device_id
        if (imei && item.item_id) {
            const record = await db.available_imeis
                .where('item_id')
                .equals(Number(item.item_id))
                .filter(u => u.imei_or_device_id === imei)
                .first()
            if (record && record.status === 'held_locally') {
                await db.available_imeis.update(record.id, { status: 'in_stock' })
            }
        }
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
    const cleanPayload = JSON.parse(JSON.stringify(saleRecord))
    await db.queued_sales.put({
        offline_sale_id: cleanPayload.offline_sale_id,
        receipt_id:      cleanPayload.receipt_id,
        created_at:      cleanPayload.created_at || new Date().toISOString(),
        cashier_id:      cleanPayload.cashier_id,
        cashier_name:    cleanPayload.cashier_name,
        payment_method:  cleanPayload.payment_method,
        final_total:     cleanPayload.final_total,
        sync_status:     'pending', // 'pending' | 'syncing' | 'synced' | 'failed'
        payload:         cleanPayload,
        error_message:   null,
        attempts:        0,
    })
}

/**
 * Get all pending/failed sales waiting to be synchronized in FIFO order.
 */
export async function getPendingOfflineSales(limit = 100) {
    const query = db.queued_sales
        .where('sync_status')
        .anyOf(['pending', 'failed'])
        .sortBy('created_at')
    const results = await query
    return limit > 0 ? results.slice(0, limit) : results
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
 * By default fetches all records (pass limit > 0 if capping is desired).
 */
export async function getAllQueuedSales(limit = 0) {
    if (limit && limit > 0) {
        return await db.queued_sales.orderBy('created_at').reverse().limit(limit).toArray()
    }
    return await db.queued_sales.orderBy('created_at').reverse().toArray()
}

// ─────────────────────────────────────────────────────────────────────────────
// ── LOCAL HELD SALES (OFFLINE & ONLINE STORE) ────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Save a held sale to local IndexedDB (with local IMEI reservation).
 */
export async function saveLocalHeldSale(data) {
    const heldSaleId = data.held_sale_id || (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : 'held_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6))
    
    // Sanitize item structures to strip reactive Proxies or non-serializable objects
    const cleanItems = (Array.isArray(data.items) ? data.items : []).map(i => ({
        item_id:           Number(i.item_id),
        item_name:         String(i.item_name || i.item?.item_name || ''),
        qty:               Number(i.qty || 1),
        price:             parseFloat(i.price ?? i.unit_price ?? 0),
        unit_price:        parseFloat(i.unit_price ?? i.price ?? 0),
        buy_price:         parseFloat(i.buy_price || 0),
        unit_used:         String(i.unit_used || 'unit'),
        purchase_type:     String(i.purchase_type || 'Consumer'),
        selected_imei:     i.selected_imei || i.imei_or_device_id || null,
        imei_or_device_id: i.selected_imei || i.imei_or_device_id || null,
        is_imei_tracked:   Boolean(i.is_imei_tracked),
        unit_label:        String(i.unit_label || 'Unit'),
        units:             Array.isArray(i.units) ? JSON.parse(JSON.stringify(i.units)) : [],
    }))

    const record = {
        held_sale_id:     String(heldSaleId),
        server_id:        data.server_id || data.id || null,
        hold_name:        String(data.hold_name || data.hold_reference || 'Held Order'),
        customer:         data.customer ? JSON.parse(JSON.stringify(data.customer)) : null,
        customer_id:      data.customer_id ?? (data.customer?.id ?? null),
        customer_name:    data.customer_name ?? (data.customer?.name ?? null),
        customer_phone:   data.customer_phone ?? (data.customer?.phone ?? null),
        purchase_type:    String(data.purchase_type || 'Consumer'),
        consultation_fee: parseFloat(data.consultation_fee || 0),
        discount:         data.discount ? JSON.parse(JSON.stringify(data.discount)) : null,
        items:            cleanItems,
        total_amount:     parseFloat(data.total_amount || data.total_price || 0),
        created_at:       data.created_at || new Date().toISOString(),
        status:           data.status || 'active', // 'active' | 'resumed' | 'discarded' | 'completed_remotely'
        sync_status:      data.sync_status || 'pending', // 'pending' | 'synced'
    }

    const cleanRecord = JSON.parse(JSON.stringify(record))

    await db.held_sales.put(cleanRecord)

    // Reserve IMEIs locally if active
    if (cleanRecord.status === 'active') {
        await reserveLocalImeisForHold(cleanRecord.items)
    }

    return cleanRecord
}

/**
 * Retrieve all currently active held sales from local IndexedDB (sorted newest first).
 */
export async function getActiveLocalHeldSales() {
    const all = await db.held_sales.toArray()
    return all
        .filter(h => h.status === 'active')
        .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
}

/**
 * Retrieve count of active local held sales.
 */
export async function getActiveLocalHeldSalesCount() {
    const sales = await getActiveLocalHeldSales()
    return sales.length
}

/**
 * Get a specific held sale record by local UUID.
 */
export async function getLocalHeldSale(heldSaleId) {
    if (!heldSaleId) return null
    return await db.held_sales.get(heldSaleId)
}

/**
 * Remove or discard a held sale from the active local queue and restore reserved IMEIs.
 */
export async function removeLocalHeldSale(heldSaleId, reason = 'discarded') {
    const record = await db.held_sales.get(heldSaleId)
    if (record) {
        await releaseLocalImeisForHold(record.items)
        await db.held_sales.update(heldSaleId, {
            status: reason,
            sync_status: 'pending_delete',
        })
    }
}

/**
 * Resume a held sale locally (marks record as resumed; cart will consume the reserved IMEIs).
 */
export async function resumeLocalHeldSale(heldSaleId) {
    const record = await db.held_sales.get(heldSaleId)
    if (!record) return null

    await db.held_sales.update(heldSaleId, {
        status: 'resumed',
    })

    return record
}

/**
 * Get all held sales that need to be pushed to the server (pending creation/sync).
 */
export async function getPendingHeldSales(limit = 100) {
    const all = await db.held_sales.toArray()
    return all
        .filter(h => h.status === 'active' && h.sync_status === 'pending')
        .slice(0, limit)
}

/**
 * Mark a held sale as synced with server.
 */
export async function markHeldSaleSynced(heldSaleId, serverData = {}) {
    await db.held_sales.update(heldSaleId, {
        sync_status: 'synced',
        server_id: serverData.server_id || serverData.id,
    })
}

/**
 * Merge server-side held sales into the local IndexedDB queue per §3.6 rules.
 * - Adds new server holds to local store.
 * - Removes / marks completed any local holds that the server reports as already finished/deleted.
 * - Preserves any unsynced pending local holds.
 */
export async function mergeServerHeldSales(serverSales = []) {
    const serverMap = new Map()
    const serverList = Array.isArray(serverSales) ? serverSales : []

    for (const s of serverList) {
        const key = s.held_sale_id || String(s.id)
        serverMap.set(key, s)
    }

    const localList = await db.held_sales.toArray()

    // 1. Check local items against server
    for (const local of localList) {
        if (local.status === 'active') {
            if (local.sync_status === 'synced') {
                // If it was synced previously but no longer in server list, it was resumed/completed elsewhere
                const serverRecord = serverMap.get(local.held_sale_id) || (local.server_id ? serverMap.get(String(local.server_id)) : null)
                if (!serverRecord) {
                    await releaseLocalImeisForHold(local.items)
                    await db.held_sales.update(local.held_sale_id, {
                        status: 'completed_remotely',
                    })
                }
            }
            // If local.sync_status === 'pending', preserve it completely!
        }
    }

    // 2. Add or update server items into local
    for (const s of serverList) {
        const heldSaleId = s.held_sale_id || ('server_' + s.id)
        const local = await db.held_sales.get(heldSaleId)

        if (!local) {
            // New hold created on another terminal
            const record = {
                held_sale_id:     heldSaleId,
                server_id:        s.id,
                hold_name:        s.hold_name || s.hold_reference || 'Held Order',
                customer:         s.customer || null,
                customer_id:      s.customer_id ?? (s.customer?.id ?? null),
                customer_name:    s.customer_name ?? (s.customer?.name ?? null),
                customer_phone:   s.customer_phone ?? (s.customer?.phone ?? null),
                purchase_type:    s.purchase_type || 'Consumer',
                consultation_fee: parseFloat(s.consultation_fee || 0),
                discount:         s.discount || null,
                items:            (s.items || []).map(i => ({
                    item_id:           i.item_id,
                    item_name:         i.item_name || i.item?.item_name,
                    qty:               i.qty,
                    price:             parseFloat(i.price || 0),
                    unit_price:        parseFloat(i.price || 0),
                    unit_used:         i.unit_used || 'unit',
                    purchase_type:     i.purchase_type,
                    selected_imei:     i.imei_or_device_id || null,
                    imei_or_device_id: i.imei_or_device_id || null,
                    is_imei_tracked:   !!(i.item?.is_imei_tracked || i.imei_or_device_id),
                })),
                total_amount:     parseFloat(s.total_amount || s.total_price || 0),
                created_at:       s.created_at || new Date().toISOString(),
                status:           'active',
                sync_status:      'synced',
            }
            await db.held_sales.put(record)
            await reserveLocalImeisForHold(record.items)
        } else if (local.status === 'active') {
            await db.held_sales.update(local.held_sale_id, {
                server_id: s.id,
                sync_status: 'synced',
            })
        }
    }
}

/**
 * Retrieve cached item discounts from IndexedDB.
 */
export async function getLocalItemDiscounts() {
    try {
        return (await db.item_discounts.toArray()) || []
    } catch (e) {
        return []
    }
}

/**
 * Retrieve cached discount groups from IndexedDB.
 */
export async function getLocalDiscountGroups() {
    try {
        return (await db.discount_groups.toArray()) || []
    } catch (e) {
        return []
    }
}


