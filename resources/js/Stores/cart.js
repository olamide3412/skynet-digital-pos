import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { usePosSettingsStore } from './posSettings'

export const useCartStore = defineStore('cart', () => {
    const settStore    = usePosSettingsStore()
    // ── State ──────────────────────────────────────────────────────────────
    const items        = ref([])
    const customer     = ref(null)
    const purchaseType = ref('Consumer') // 'Consumer' | 'Wholesale'
    const discount     = ref({ type: null, value: 0, applied_id: null })
    const consultationFee = ref(0)
    const payment      = ref({ method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 })

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

    // ── Price Resolver Helper ──────────────────────────────────────────────
    function getPriceForUnit(item, unit = 'unit', pType = purchaseType.value) {
        const isWholesale = pType === 'Wholesale'
        const u = (unit || 'unit').toLowerCase()

        if (u === 'carton') {
            if (isWholesale && Number(item.carton_wholesale_price) > 0) return parseFloat(item.carton_wholesale_price)
            if (Number(item.carton_price) > 0) return parseFloat(item.carton_price)
            if (Number(item.carton_display_price) > 0) return parseFloat(item.carton_display_price)
            const base = (isWholesale && item.wholesale_price > 0) ? item.wholesale_price : item.price
            return parseFloat(base * (item.packs_per_carton || 1) * (item.units_per_pack || 1))
        }

        if (u === 'pack') {
            if (isWholesale && Number(item.pack_wholesale_price) > 0) return parseFloat(item.pack_wholesale_price)
            if (Number(item.pack_price) > 0) return parseFloat(item.pack_price)
            if (Number(item.pack_display_price) > 0) return parseFloat(item.pack_display_price)
            const base = (isWholesale && item.wholesale_price > 0) ? item.wholesale_price : item.price
            return parseFloat(base * (item.units_per_pack || 1))
        }

        return parseFloat(isWholesale && Number(item.wholesale_price) > 0 ? item.wholesale_price : item.price)
    }

    // ── Actions ────────────────────────────────────────────────────────────
    function addItem(item, unit = 'unit') {
        const unitUsed  = unit || 'unit'
        const key       = `${item.id}_${unitUsed}`
        const unitPrice = getPriceForUnit(item, unitUsed, purchaseType.value)

        const existing = items.value.find(i => i.cart_key === key || (i.item_id === item.id && i.unit_used === unitUsed))
        if (existing) {
            existing.qty++
            existing.line_total = Math.round(existing.unit_price * existing.qty * 100) / 100
        } else {
            items.value.push({
                cart_key:         key,
                item_id:          item.id,
                item_name:        item.item_name,
                qty:              1,
                unit_used:        unitUsed,
                unit_price:       unitPrice,
                buy_price:        parseFloat(item.buy_price || 0),
                purchase_type:    purchaseType.value,
                line_total:       Math.round(unitPrice * 100) / 100,
                price_locked:     item.price_locked || false,
                max_qty:          item.front_store_qty ?? item.qty ?? 9999,
                unit_label:       item.unit_label || 'Unit',
                pack_label:       item.pack_label || 'Pack',
                carton_label:     item.carton_label || 'Carton',
                units_per_pack:   item.units_per_pack || 1,
                packs_per_carton: item.packs_per_carton || 1,
                raw_item:         item,
            })
        }
    }

    function switchUnit(cartKey, newUnit) {
        const item = items.value.find(i => i.cart_key === cartKey)
        if (!item) return

        item.unit_used  = newUnit
        item.cart_key   = `${item.item_id}_${newUnit}`
        item.unit_price = getPriceForUnit(item.raw_item || item, newUnit, purchaseType.value)
        item.line_total = Math.round(item.unit_price * item.qty * 100) / 100
    }

    function removeItem(cartKey) {
        items.value = items.value.filter(i => i.cart_key !== cartKey && i.item_id !== cartKey)
    }

    function updateQty(cartKey, qty) {
        const item = items.value.find(i => i.cart_key === cartKey || i.item_id === cartKey)
        if (!item) return
        const clamped   = Math.max(1, Math.min(qty, 99999))
        item.qty        = clamped
        item.line_total = Math.round(item.unit_price * clamped * 100) / 100
    }

    function updatePrice(cartKey, price) {
        const item = items.value.find(i => i.cart_key === cartKey || i.item_id === cartKey)
        if (!item) return
        item.unit_price = parseFloat(price)
        item.line_total = Math.round(item.unit_price * item.qty * 100) / 100
    }

    function setPurchaseType(type) {
        purchaseType.value = type
        items.value.forEach(item => {
            item.purchase_type = type
            item.unit_price    = getPriceForUnit(item.raw_item || item, item.unit_used, type)
            item.line_total    = Math.round(item.unit_price * item.qty * 100) / 100
        })
    }

    function applyDiscount(disc) {
        discount.value = disc
    }

    function clearDiscount() {
        discount.value = { type: null, value: 0, applied_id: null }
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
        discount.value        = { type: null, value: 0, applied_id: null }
        consultationFee.value = 0
        payment.value         = { method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 }
    }

    function loadFromHeld(heldSale) {
        clearCart()
        if (heldSale.customer) customer.value = heldSale.customer
        heldSale.items.forEach(heldItem => {
            const unitUsed = heldItem.unit_used || 'unit'
            items.value.push({
                cart_key:      `${heldItem.item_id}_${unitUsed}`,
                item_id:       heldItem.item_id,
                item_name:     heldItem.item_name || heldItem.item?.item_name,
                qty:           heldItem.qty,
                unit_used:     unitUsed,
                unit_price:    parseFloat(heldItem.price),
                buy_price:     parseFloat(heldItem.item?.buy_price || 0),
                purchase_type: heldItem.purchase_type,
                line_total:    heldItem.price * heldItem.qty,
                price_locked:  false,
                max_qty:       heldItem.item?.front_store_qty ?? 9999,
                unit_label:    heldItem.item?.unit_label || 'Unit',
                pack_label:    heldItem.item?.pack_label || 'Pack',
                carton_label:  heldItem.item?.carton_label || 'Carton',
                raw_item:      heldItem.item || {},
            })
        })
    }

    function toPayload() {
        return {
            items: items.value.map(i => ({
                item_id:   i.item_id,
                qty:       i.qty,
                price:     i.unit_price,
                unit_used: i.unit_used || 'unit',
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
        items, customer, purchaseType, discount, consultationFee, payment,
        subtotal, discountAmount, taxAmount, grandTotal, itemCount,
        addItem, switchUnit, removeItem, updateQty, updatePrice,
        setPurchaseType, applyDiscount, clearDiscount,
        setCustomer, clearCustomer, setConsultationFee,
        clearCart, loadFromHeld, toPayload,
    }
})
