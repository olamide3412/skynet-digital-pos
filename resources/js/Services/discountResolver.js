/**
 * Discount Resolver Service
 * Evaluates line-item level discounts adhering strictly to POS hierarchy:
 * 1. Item Discount (Priority 1)
 * 2. Group Discount (Priority 2, only if no Item Discount applies)
 *
 * Offline-compatible: uses client device clock.
 */

/**
 * Get base unit multiplier for a unit level.
 */
export function getMultiplierForItemUnit(item, unit = 'unit') {
    const u = (unit || 'unit').toLowerCase().trim()
    const baseUnit = (item?.unit_label || item?.raw_item?.unit_label || 'unit').toLowerCase().trim()

    if (u === 'unit' || u === baseUnit) {
        return 1
    }

    const unitsList = item?.units || item?.raw_item?.units || item?.active_units || item?.raw_item?.active_units || []
    if (Array.isArray(unitsList) && unitsList.length > 0) {
        const matched = unitsList.find(un => (un.unit_name || '').toLowerCase().trim() === u || String(un.id) === String(unit))
        if (matched) {
            return Number(matched.quantity_in_base_units) || 1
        }
    }

    return 1
}

/**
 * Get regular price for item at unit level and purchase type before line-item discounts.
 */
export function getRawPriceForUnit(item, unit = 'unit', purchaseType = 'Consumer') {
    const isWholesale = purchaseType === 'Wholesale'
    const u = (unit || 'unit').toLowerCase().trim()
    const baseUnit = (item?.unit_label || item?.raw_item?.unit_label || 'unit').toLowerCase().trim()

    if (u === 'unit' || u === baseUnit) {
        return parseFloat(isWholesale && Number(item?.wholesale_price) > 0 ? item.wholesale_price : (item?.price || 0))
    }

    const unitsList = item?.units || item?.raw_item?.units || item?.active_units || item?.raw_item?.active_units || []
    if (Array.isArray(unitsList) && unitsList.length > 0) {
        const matched = unitsList.find(un => (un.unit_name || '').toLowerCase().trim() === u || String(un.id) === String(unit))
        if (matched) {
            if (isWholesale && Number(matched.wholesale_price) > 0) {
                return parseFloat(matched.wholesale_price)
            }
            return parseFloat(matched.selling_price || 0)
        }
    }

    return parseFloat(isWholesale && Number(item?.wholesale_price) > 0 ? item.wholesale_price : (item?.price || 0))
}

/**
 * Check if a discount rule is active and within scheduled date/time window.
 */
export function isDiscountInWindow(rule, checkDate = new Date()) {
    if (!rule || !rule.is_active) return false

    const now = (checkDate instanceof Date) ? checkDate.getTime() : new Date(checkDate).getTime()
    if (isNaN(now)) return false

    const start = new Date(rule.starts_at || rule.start_date_time).getTime()
    const end = new Date(rule.ends_at || rule.end_date_time).getTime()

    if (!isNaN(start) && now < start) return false
    if (!isNaN(end) && now > end) return false

    return true
}

/**
 * Resolve price and discount for a line item.
 */
export function resolveLineItemDiscount(
    item,
    unit = 'unit',
    purchaseType = 'Consumer',
    itemDiscounts = [],
    discountGroups = [],
    checkTime = new Date()
) {
    const itemId = item?.id ?? item?.item_id
    const rawPrice = getRawPriceForUnit(item, unit, purchaseType)
    const multiplier = getMultiplierForItemUnit(item, unit)

    // ── 1. Priority 1: Item Discount ─────────────────────────────────────────
    if (Array.isArray(itemDiscounts) && itemDiscounts.length > 0 && itemId) {
        const itemDisc = itemDiscounts.find(d => {
            return String(d.item_id) === String(itemId) && isDiscountInWindow(d, checkTime)
        })

        if (itemDisc) {
            const discVal = Number(itemDisc.discount_value || 0)
            let discAmt = 0

            if (itemDisc.discount_type === 'percentage') {
                discAmt = Math.round((rawPrice * discVal / 100) * 100) / 100
            } else {
                // Fixed amount per base unit scales with unit multiplier
                discAmt = Math.round((discVal * multiplier) * 100) / 100
            }

            discAmt = Math.min(rawPrice, Math.max(0, discAmt))
            const finalPrice = Math.max(0, Math.round((rawPrice - discAmt) * 100) / 100)

            const label = itemDisc.discount_type === 'percentage'
                ? `${discVal}% Off (Item Promo)`
                : `₦${discVal} Off (Item Promo)`

            return {
                original_price:  rawPrice,
                unit_price:      finalPrice,
                discount_amount: discAmt,
                discount_layer:  'item',
                discount_type:   itemDisc.discount_type,
                discount_value:  discVal,
                discount_name:   label,
                discount_id:     itemDisc.id,
                multiplier:      multiplier,
            }
        }
    }

    // ── 2. Priority 2: Group Discount (Only if no Item Discount applies) ─────
    if (Array.isArray(discountGroups) && discountGroups.length > 0 && itemId) {
        const groupDisc = discountGroups.find(g => {
            if (!isDiscountInWindow(g, checkTime)) return false
            const ids = Array.isArray(g.item_ids) ? g.item_ids.map(String) : []
            return ids.includes(String(itemId))
        })

        if (groupDisc) {
            const discVal = Number(groupDisc.discount_value || 0)
            let discAmt = 0

            if (groupDisc.discount_type === 'percentage') {
                discAmt = Math.round((rawPrice * discVal / 100) * 100) / 100
            } else {
                discAmt = Math.round((discVal * multiplier) * 100) / 100
            }

            discAmt = Math.min(rawPrice, Math.max(0, discAmt))
            const finalPrice = Math.max(0, Math.round((rawPrice - discAmt) * 100) / 100)

            const label = `${groupDisc.name} (${groupDisc.discount_type === 'percentage' ? `${discVal}%` : `₦${discVal}`})`

            return {
                original_price:  rawPrice,
                unit_price:      finalPrice,
                discount_amount: discAmt,
                discount_layer:  'group',
                discount_type:   groupDisc.discount_type,
                discount_value:  discVal,
                discount_name:   label,
                discount_id:     groupDisc.id,
                multiplier:      multiplier,
            }
        }
    }

    // ── 3. No Line-Level Discount ────────────────────────────────────────────
    return {
        original_price:  rawPrice,
        unit_price:      rawPrice,
        discount_amount: 0,
        discount_layer:  null,
        discount_type:   null,
        discount_value:  0,
        discount_name:   null,
        discount_id:     null,
        multiplier:      multiplier,
    }
}
