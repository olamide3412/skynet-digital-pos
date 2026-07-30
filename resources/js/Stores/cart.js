import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
    // ── State ──────────────────────────────────────────────────────────────
    const items        = ref([]) // { item_id, item_name, qty, unit_price, purchase_type, buy_price, line_total }
    const customer     = ref(null)
    const purchaseType = ref('Consumer') // 'Consumer' | 'Wholesale'
    const discount     = ref({ type: null, value: 0, applied_id: null })
    const consultationFee = ref(0)
    const payment      = ref({ method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 })

    // ── Computed ───────────────────────────────────────────────────────────
    const subtotal = computed(() =>
        items.value.reduce((sum, i) => sum + i.line_total, 0)
    )

    const discountAmount = computed(() => {
        if (!discount.value.type) return 0
        if (discount.value.type === 'percentage') {
            return (subtotal.value * discount.value.value) / 100
        }
        return discount.value.value
    })

    const grandTotal = computed(() =>
        Math.max(0, subtotal.value + consultationFee.value - discountAmount.value)
    )

    const itemCount = computed(() =>
        items.value.reduce((sum, i) => sum + i.qty, 0)
    )

    // ── Actions ────────────────────────────────────────────────────────────
    function getPrice(item) {
        return purchaseType.value === 'Wholesale'
            ? parseFloat(item.wholesale_price || item.price)
            : parseFloat(item.price)
    }

    function addItem(item) {
        const existing = items.value.find(i => i.item_id === item.id)
        if (existing) {
            existing.qty++
            existing.line_total = existing.unit_price * existing.qty
        } else {
            const unitPrice = getPrice(item)
            items.value.push({
                item_id:       item.id,
                item_name:     item.item_name,
                qty:           1,
                unit_price:    unitPrice,
                buy_price:     parseFloat(item.buy_price || 0),
                purchase_type: purchaseType.value,
                line_total:    unitPrice,
                price_locked:  item.price_locked || false,
                max_qty:       item.qty,
            })
        }
    }

    function removeItem(itemId) {
        items.value = items.value.filter(i => i.item_id !== itemId)
    }

    function updateQty(itemId, qty) {
        const item = items.value.find(i => i.item_id === itemId)
        if (!item) return
        const clamped  = Math.max(1, Math.min(qty, item.max_qty ?? 9999))
        item.qty       = clamped
        item.line_total = item.unit_price * clamped
    }

    function updatePrice(itemId, price) {
        const item = items.value.find(i => i.item_id === itemId)
        if (!item) return
        item.unit_price = parseFloat(price)
        item.line_total = item.unit_price * item.qty
    }

    function setPurchaseType(type) {
        purchaseType.value = type
        // Recalculate prices for non-locked items
        items.value.forEach(item => {
            item.purchase_type = type
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
        items.value        = []
        customer.value     = null
        purchaseType.value = 'Consumer'
        discount.value     = { type: null, value: 0, applied_id: null }
        consultationFee.value = 0
        payment.value      = { method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 }
    }

    function loadFromHeld(heldSale) {
        clearCart()
        if (heldSale.customer) customer.value = heldSale.customer
        heldSale.items.forEach(heldItem => {
            items.value.push({
                item_id:       heldItem.item_id,
                item_name:     heldItem.item_name || heldItem.item?.item_name,
                qty:           heldItem.qty,
                unit_price:    parseFloat(heldItem.price),
                buy_price:     parseFloat(heldItem.item?.buy_price || 0),
                purchase_type: heldItem.purchase_type,
                line_total:    heldItem.price * heldItem.qty,
                price_locked:  false,
                max_qty:       heldItem.item?.qty ?? 9999,
            })
        })
    }

    function toPayload() {
        return {
            items:           items.value.map(i => ({
                item_id: i.item_id,
                qty:     i.qty,
                price:   i.unit_price,
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
        subtotal, discountAmount, grandTotal, itemCount,
        addItem, removeItem, updateQty, updatePrice,
        setPurchaseType, applyDiscount, clearDiscount,
        setCustomer, clearCustomer, setConsultationFee,
        clearCart, loadFromHeld, toPayload,
    }
})
