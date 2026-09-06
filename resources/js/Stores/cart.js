import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { usePosSettingsStore } from './posSettings'
import { resolveLineItemDiscount, getRawPriceForUnit } from '@/Services/discountResolver'

export const useCartStore = defineStore('cart', () => {
    const settStore    = usePosSettingsStore()
    // ── State ──────────────────────────────────────────────────────────────
    const items        = ref([])
    const customer     = ref(null)
    const purchaseType = ref('Consumer') // 'Consumer' | 'Wholesale'
    const discount     = ref({ type: null, value: 0, applied_id: null, name: '' })
    const activeBranchDiscount = ref(null)
    const itemDiscounts  = ref([])
    const discountGroups = ref([])
    const consultationFee = ref(0)
    const payment      = ref({ method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 })
    const lastAddedKey = ref(null)
    const lastAddedAt  = ref(0)

    // ── Computed ───────────────────────────────────────────────────────────
    const subtotal = computed(() => {
        const sum = items.value.reduce((acc, i) => acc + (Number(i.line_total) || 0), 0)
        return Math.round(sum * 100) / 100
    })

    const discountAmount = computed(() => {
        if (!discount.value.type) return 0
        let val = 0
        if (discount.value.type === 'percentage') {
            val = (subtotal.value * Number(discount.value.value || 0)) / 100
        } else {
            val = Number(discount.value.value || 0)
        }
        return Math.round(val * 100) / 100
    })

    const taxAmount = computed(() => {
        if (!settStore.settings?.is_tax_enabled) return 0
        const rate = parseFloat(settStore.settings.tax_percentage || 0)
        if (rate <= 0) return 0
        const taxableSubtotal = Math.max(0, subtotal.value + Number(consultationFee.value || 0) - discountAmount.value)
        return Math.round(((taxableSubtotal * rate) / 100) * 100) / 100
    })

    const grandTotal = computed(() =>
        Math.round(Math.max(0, subtotal.value + Number(consultationFee.value || 0) + taxAmount.value - discountAmount.value) * 100) / 100
    )

    const itemCount = computed(() =>
        items.value.reduce((sum, i) => sum + i.qty, 0)
    )

    // ── Price & Discount Resolver Helper ────────────────────────────────────
    function getPriceForUnit(item, unit = 'unit', pType = purchaseType.value) {
        return getRawPriceForUnit(item, unit, pType)
    }

    function resolveItemPriceDetails(item, unit = 'unit', pType = purchaseType.value) {
        return resolveLineItemDiscount(
            item,
            unit,
            pType,
            itemDiscounts.value,
            discountGroups.value
        )
    }

    const hasUnassignedImeis = computed(() => {
        return items.value.some(i => i.is_imei_tracked && !i.selected_imei)
    })

    // ── Actions ────────────────────────────────────────────────────────────
    function setItemDiscounts(discs) {
        itemDiscounts.value = Array.isArray(discs) ? discs : []
        refreshAllDiscounts()
    }

    function setDiscountGroups(groups) {
        discountGroups.value = Array.isArray(groups) ? groups : []
        refreshAllDiscounts()
    }

    function refreshAllDiscounts() {
        items.value.forEach(item => {
            if (!item.price_locked) {
                const details = resolveItemPriceDetails(item.raw_item || item, item.unit_used, purchaseType.value)
                item.original_price  = details.original_price
                item.unit_price      = details.unit_price
                item.discount_amount = details.discount_amount
                item.discount_layer  = details.discount_layer
                item.discount_type   = details.discount_type
                item.discount_value  = details.discount_value
                item.discount_name   = details.discount_name
                item.discount_id     = details.discount_id
                item.line_total      = Math.round(details.unit_price * item.qty * 100) / 100
            }
        })
    }

    function addItem(item, unit = 'unit', preselectedImei = null, forceNewRow = false) {
        const unitUsed     = unit || 'unit'
        const isImei       = !!(item.is_imei_tracked || item.raw_item?.is_imei_tracked)
        const priceDetails = resolveItemPriceDetails(item, unitUsed, purchaseType.value)
        const unitPrice    = priceDetails.unit_price

        // For IMEI-tracked items, each unit must be its own line so it can hold 1 unique serial/IMEI
        if (isImei) {
            const key = `${item.id}_unit_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`
            items.value.push({
                cart_key:         key,
                item_id:          item.id,
                item_name:        item.item_name,
                qty:              1,
                unit_used:        'unit',
                original_price:   priceDetails.original_price,
                unit_price:       unitPrice,
                discount_amount:  priceDetails.discount_amount,
                discount_layer:   priceDetails.discount_layer,
                discount_type:    priceDetails.discount_type,
                discount_value:   priceDetails.discount_value,
                discount_name:    priceDetails.discount_name,
                discount_id:      priceDetails.discount_id,
                buy_price:        parseFloat(item.buy_price || 0),
                purchase_type:    purchaseType.value,
                line_total:       Math.round(unitPrice * 100) / 100,
                price_locked:     item.price_locked || false,
                max_qty:          item.front_store_qty ?? item.qty ?? 9999,
                unit_label:       item.unit_label || 'Unit',
                units:            item.units || [],
                is_imei_tracked:  true,
                available_imeis:  item.available_imeis || [],
                selected_imei:    preselectedImei || null,
                raw_item:         item,
            })
            lastAddedKey.value = key
            lastAddedAt.value  = Date.now()
            return
        }

        // When forceNewRow is true (e.g. eatery/restaurant grid view mode)
        if (forceNewRow) {
            const key = `${item.id}_${unitUsed}_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`
            items.value.push({
                cart_key:         key,
                item_id:          item.id,
                item_name:        item.item_name,
                qty:              1,
                unit_used:        unitUsed,
                original_price:   priceDetails.original_price,
                unit_price:       unitPrice,
                discount_amount:  priceDetails.discount_amount,
                discount_layer:   priceDetails.discount_layer,
                discount_type:    priceDetails.discount_type,
                discount_value:   priceDetails.discount_value,
                discount_name:    priceDetails.discount_name,
                discount_id:      priceDetails.discount_id,
                buy_price:        parseFloat(item.buy_price || 0),
                purchase_type:    purchaseType.value,
                line_total:       Math.round(unitPrice * 100) / 100,
                price_locked:     item.price_locked || false,
                max_qty:          item.front_store_qty ?? item.qty ?? 9999,
                unit_label:       item.unit_label || 'Unit',
                units:            item.units || [],
                is_imei_tracked:  false,
                available_imeis:  [],
                selected_imei:    null,
                raw_item:         item,
            })
            lastAddedKey.value = key
            lastAddedAt.value  = Date.now()
            return
        }

        const key = `${item.id}_${unitUsed}`
        const existing = items.value.find(i => i.cart_key === key || (i.item_id === item.id && i.unit_used === unitUsed))
        if (existing) {
            existing.qty++
            existing.line_total = Math.round(existing.unit_price * existing.qty * 100) / 100
            lastAddedKey.value  = existing.cart_key
            lastAddedAt.value   = Date.now()
        } else {
            items.value.push({
                cart_key:         key,
                item_id:          item.id,
                item_name:        item.item_name,
                qty:              1,
                unit_used:        unitUsed,
                original_price:   priceDetails.original_price,
                unit_price:       unitPrice,
                discount_amount:  priceDetails.discount_amount,
                discount_layer:   priceDetails.discount_layer,
                discount_type:    priceDetails.discount_type,
                discount_value:   priceDetails.discount_value,
                discount_name:    priceDetails.discount_name,
                discount_id:      priceDetails.discount_id,
                buy_price:        parseFloat(item.buy_price || 0),
                purchase_type:    purchaseType.value,
                line_total:       Math.round(unitPrice * 100) / 100,
                price_locked:     item.price_locked || false,
                max_qty:          item.front_store_qty ?? item.qty ?? 9999,
                unit_label:       item.unit_label || 'Unit',
                units:            item.units || [],
                is_imei_tracked:  false,
                available_imeis:  [],
                selected_imei:    null,
                raw_item:         item,
            })
            lastAddedKey.value = key
            lastAddedAt.value  = Date.now()
        }
    }


    function setItemImei(cartKey, imei) {
        const item = items.value.find(i => i.cart_key === cartKey)
        if (item) {
            item.selected_imei = imei
        }
    }

    function switchUnit(cartKey, newUnit) {
        const idx = items.value.findIndex(i => i.cart_key === cartKey)
        if (idx === -1) return

        const item         = items.value[idx]
        const newKey       = `${item.item_id}_${newUnit}`
        const priceDetails = resolveItemPriceDetails(item.raw_item || item, newUnit, purchaseType.value)
        const newPrice     = priceDetails.unit_price

        // If a row for the new unit already exists, merge qty into it and remove current row
        const existingIdx = items.value.findIndex((i, j) => j !== idx && i.cart_key === newKey)
        if (existingIdx !== -1) {
            const existing = items.value[existingIdx]
            existing.qty       += item.qty
            existing.line_total = Math.round(existing.unit_price * existing.qty * 100) / 100
            items.value.splice(idx, 1)
            return
        }

        item.unit_used       = newUnit
        item.cart_key        = newKey
        item.original_price  = priceDetails.original_price
        item.unit_price      = newPrice
        item.discount_amount = priceDetails.discount_amount
        item.discount_layer  = priceDetails.discount_layer
        item.discount_type   = priceDetails.discount_type
        item.discount_value  = priceDetails.discount_value
        item.discount_name   = priceDetails.discount_name
        item.discount_id     = priceDetails.discount_id
        item.line_total      = Math.round(newPrice * item.qty * 100) / 100
    }

    function removeItem(cartKey) {
        items.value = items.value.filter(i => i.cart_key !== cartKey)
    }

    function updateQty(cartKey, qty) {
        const item = items.value.find(i => i.cart_key === cartKey)
        if (!item) return
        const clamped   = Math.max(1, Math.min(qty, 99999))
        item.qty        = clamped
        item.line_total = Math.round(item.unit_price * clamped * 100) / 100
    }

    function updatePrice(cartKey, price) {
        const item = items.value.find(i => i.cart_key === cartKey)
        if (!item) return
        item.unit_price   = parseFloat(price)
        item.price_locked = true
        item.line_total   = Math.round(item.unit_price * item.qty * 100) / 100
    }

    function setPurchaseType(type) {
        purchaseType.value = type
        refreshAllDiscounts()
    }


    function setActiveBranchDiscount(disc) {
        activeBranchDiscount.value = disc || null
        if (disc && disc.is_apply) {
            applyBranchDiscount()
        }
    }

    function applyBranchDiscount() {
        if (activeBranchDiscount.value && activeBranchDiscount.value.is_apply) {
            const val = Number(activeBranchDiscount.value.discount_value || 0)
            const type = activeBranchDiscount.value.discount_type || 'percentage'
            const label = activeBranchDiscount.value.description || (type === 'percentage' ? `${val}% Promo Discount` : `₦${val} Promo Discount`)
            discount.value = {
                type: type,
                value: val,
                applied_id: activeBranchDiscount.value.id,
                name: label,
            }
        }
    }

    function applyDiscount(disc) {
        discount.value = {
            type: disc?.type || null,
            value: Number(disc?.value || 0),
            applied_id: disc?.applied_id ?? null,
            name: disc?.name || '',
        }
    }

    function clearDiscount() {
        discount.value = { type: null, value: 0, applied_id: null, name: '' }
    }

    function setCustomer(c) {
        customer.value = c
    }

    function clearCustomer() {
        customer.value = null
    }

    function setConsultationFee(fee) {
        consultationFee.value = parseFloat(fee) || 0
    }

    function clearCart() {
        items.value           = []
        customer.value        = null
        purchaseType.value    = 'Consumer'
        consultationFee.value = 0
        payment.value         = { method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 }
        
        // Auto-reapply branch promotional discount if active
        if (activeBranchDiscount.value && activeBranchDiscount.value.is_apply) {
            applyBranchDiscount()
        } else {
            discount.value = { type: null, value: 0, applied_id: null, name: '' }
        }
    }

    function loadFromHeld(heldSale) {
        clearCart()
        if (heldSale.customer) {
            customer.value = heldSale.customer
        } else if (heldSale.customer_id || heldSale.customer_name) {
            customer.value = {
                id: heldSale.customer_id,
                name: heldSale.customer_name || 'Customer',
                phone: heldSale.customer_phone || '',
            }
        }
        if (heldSale.purchase_type) {
            purchaseType.value = heldSale.purchase_type
        }
        if (heldSale.consultation_fee) {
            consultationFee.value = parseFloat(heldSale.consultation_fee) || 0
        }
        if (heldSale.discount) {
            discount.value = heldSale.discount
        }
        (heldSale.items || []).forEach(heldItem => {
            const unitUsed = heldItem.unit_used || 'unit'
            const isImei   = !!(heldItem.is_imei_tracked || heldItem.selected_imei || heldItem.imei_or_device_id || heldItem.item?.is_imei_tracked)
            const key      = isImei
                ? `${heldItem.item_id}_unit_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`
                : `${heldItem.item_id}_${unitUsed}`
            
            const price = parseFloat(heldItem.unit_price ?? heldItem.price ?? 0)
            const qty   = Number(heldItem.qty || 1)

            items.value.push({
                cart_key:         heldItem.cart_key || key,
                item_id:          heldItem.item_id,
                item_name:        heldItem.item_name || heldItem.item?.item_name,
                qty:              qty,
                unit_used:        unitUsed,
                unit_price:       price,
                buy_price:        parseFloat(heldItem.buy_price || heldItem.item?.buy_price || 0),
                purchase_type:    heldItem.purchase_type || purchaseType.value,
                line_total:       Math.round(price * qty * 100) / 100,
                price_locked:     heldItem.price_locked || false,
                max_qty:          heldItem.max_qty ?? heldItem.item?.front_store_qty ?? 9999,
                unit_label:       heldItem.unit_label || heldItem.item?.unit_label || 'Unit',
                units:            heldItem.units || heldItem.item?.units || [],
                is_imei_tracked:  isImei,
                available_imeis:  heldItem.available_imeis || heldItem.item?.available_imeis || [],
                selected_imei:    heldItem.selected_imei || heldItem.imei_or_device_id || null,
                raw_item:         heldItem.raw_item || heldItem.item || {},
            })
        })
    }

    function toPayload() {
        return {
            items: items.value.map(i => ({
                item_id:            i.item_id,
                qty:                i.qty,
                price:              i.unit_price,
                unit_used:          i.unit_used || 'unit',
                imei_or_device_id:  i.selected_imei || null,
            })),
            customer_id:      customer.value?.id ?? null,
            purchase_type:    purchaseType.value,
            consultation_fee: consultationFee.value,
            discount_amount:  discountAmount.value,
            sale_discount_id: discount.value.applied_id,
            payment_method:   payment.value.method,
            amount_paid:      payment.value.amount_paid,
            cash:             payment.value.cash,
            bank_transfer:     payment.value.bank_transfer,
        }
    }

    return {
        items, customer, purchaseType, discount, activeBranchDiscount, itemDiscounts, discountGroups,
        consultationFee, payment,
        lastAddedKey, lastAddedAt,
        subtotal, discountAmount, taxAmount, grandTotal, itemCount, hasUnassignedImeis,
        addItem, switchUnit, removeItem, updateQty, updatePrice, setItemImei,
        setPurchaseType, applyDiscount, clearDiscount, setActiveBranchDiscount, applyBranchDiscount,
        setItemDiscounts, setDiscountGroups, refreshAllDiscounts, resolveItemPriceDetails,
        setCustomer, clearCustomer, setConsultationFee,
        clearCart, loadFromHeld, toPayload,
    }
})
