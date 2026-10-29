<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'
import { searchLocalCustomers } from '@/Services/offlineDb'

const props = defineProps({
    services: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    activeGeneralDiscount: {
        type: Object,
        default: null,
    },
    canApplyDiscount: {
        type: Boolean,
        default: true,
    },
    settings: {
        type: Object,
        required: true,
    },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => {
    if (page.props.current_branch?.slug) return page.props.current_branch.slug
    if (page.props.branch_slug) return page.props.branch_slug
    if (page.props.current_branch_slug) return page.props.current_branch_slug
    if (typeof window !== 'undefined') {
        const seg = window.location.pathname.split('/').filter(Boolean)[0]
        const reserved = ['superadmin', 'org', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'login', 'logout', 'register', 'password']
        if (seg && !reserved.includes(seg)) return seg
    }
    return ''
})
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Customer State ──────────────────────────────────────────────────────────
const selectedCustomer = ref(null)
const customerSearch = ref('')
const customerSearchResults = ref([])
const activeCustomerIndex = ref(-1)
const isSearchingCustomer = ref(false)
const hasSearched = ref(false)
const showQuickAddCustomer = ref(false)
const customerSearchInputRef = ref(null)
const quickCustomerForm = ref({
    name: '',
    phone: '',
    same_as_phone: true,
    whatsapp_number: '',
    email: '',
    address: '',
})
const quickCustomerError = ref('')
const duplicateWarning = ref(null)

let searchTimeout = null
const onCustomerSearchInput = async () => {
    clearTimeout(searchTimeout)
    activeCustomerIndex.value = -1
    const term = customerSearch.value ? customerSearch.value.trim() : ''
    if (term.length < 1) {
        customerSearchResults.value = []
        hasSearched.value = false
        return
    }

    // 1. Instant 0ms local offline lookup from Dexie if available
    try {
        const localCusts = await searchLocalCustomers(term)
        if (localCusts && localCusts.length > 0) {
            customerSearchResults.value = localCusts
            hasSearched.value = true
        }
    } catch (e) {
        // Dexie offline fallback silent
    }

    // 2. Debounced server search
    searchTimeout = setTimeout(async () => {
        isSearchingCustomer.value = true
        try {
            const slug = branchSlug.value || route().params?.branch || (typeof window !== 'undefined' ? window.location.pathname.split('/').filter(Boolean)[0] : '') || ''
            const url = slug ? `/${slug}/laundry/customers/search` : safeRoute('laundry.customers.search')
            const res = await axios.get(url, {
                params: { 
                    query: term,
                    q: term,
                    search: term,
                },
            })
            if (res.data && Array.isArray(res.data)) {
                customerSearchResults.value = res.data
            }
            hasSearched.value = true
        } catch (e) {
            console.error('Customer search error:', e)
            hasSearched.value = true
        } finally {
            isSearchingCustomer.value = false
        }
    }, 150)
}

const onSearchKeyDown = () => {
    if (customerSearchResults.value.length === 0) return
    activeCustomerIndex.value = (activeCustomerIndex.value + 1) % customerSearchResults.value.length
}

const onSearchKeyUp = () => {
    if (customerSearchResults.value.length === 0) return
    if (activeCustomerIndex.value <= 0) {
        activeCustomerIndex.value = customerSearchResults.value.length - 1
    } else {
        activeCustomerIndex.value--
    }
}

const handleCustomerSearchEnter = () => {
    if (activeCustomerIndex.value >= 0 && activeCustomerIndex.value < customerSearchResults.value.length) {
        selectCustomer(customerSearchResults.value[activeCustomerIndex.value])
    } else if (customerSearchResults.value.length > 0) {
        selectCustomer(customerSearchResults.value[0])
    } else if (hasSearched.value && customerSearch.value.trim().length >= 1) {
        openQuickAddFromSearch()
    }
}

const selectCustomer = (cust) => {
    if (!cust) return
    selectedCustomer.value = cust
    customerSearch.value = ''
    customerSearchResults.value = []
    activeCustomerIndex.value = -1
    hasSearched.value = false
    duplicateWarning.value = null
}

const clearCustomer = () => {
    selectedCustomer.value = null
    customerSearch.value = ''
    customerSearchResults.value = []
    activeCustomerIndex.value = -1
    hasSearched.value = false
    nextTick(() => {
        customerSearchInputRef.value?.focus()
    })
}

const openQuickAddFromSearch = () => {
    const term = customerSearch.value.trim()
    const isDigits = /^\+?[\d\s-]+$/.test(term)
    const isEmail = term.includes('@')
    quickCustomerForm.value = {
        name: (isDigits || isEmail) ? '' : term,
        phone: isDigits ? term : '',
        same_as_phone: true,
        whatsapp_number: isDigits ? term : '',
        email: isEmail ? term : '',
        address: '',
    }
    showQuickAddCustomer.value = true
    customerSearchResults.value = []
    hasSearched.value = false
}

const submitQuickCustomer = async () => {
    quickCustomerError.value = ''
    duplicateWarning.value = null

    if (!quickCustomerForm.value.name.trim() || !quickCustomerForm.value.phone.trim()) {
        quickCustomerError.value = 'Name and Phone number are required.'
        return
    }

    if (quickCustomerForm.value.same_as_phone) {
        quickCustomerForm.value.whatsapp_number = quickCustomerForm.value.phone
    }

    try {
        const res = await axios.post(safeRoute('laundry.customers.quick'), quickCustomerForm.value)
        if (res.data.duplicate) {
            duplicateWarning.value = res.data.message
            selectedCustomer.value = res.data.customer
        } else {
            selectedCustomer.value = res.data.customer
        }
        showQuickAddCustomer.value = false
        customerSearch.value = ''
        quickCustomerForm.value = { name: '', phone: '', same_as_phone: true, whatsapp_number: '', email: '', address: '' }
    } catch (e) {
        quickCustomerError.value = e.response?.data?.message || 'Failed to create customer.'
    }
}

// ── Service Catalogue & Lines ───────────────────────────────────────────────
const selectedCategory = ref('All')
const catalogSearch = ref('')
const orderItems = ref([])

const filteredServices = computed(() => {
    return props.services.filter(s => {
        const catName = s.category_name || s.category?.name || s.category || ''
        const matchesCategory = selectedCategory.value === 'All' || catName === selectedCategory.value
        const matchesSearch = !catalogSearch.value.trim() || s.name.toLowerCase().includes(catalogSearch.value.trim().toLowerCase())
        return matchesCategory && matchesSearch
    })
})

// Mobile Responsive State & Cart Helpers
const activeMobileTab = ref('services') // 'services' or 'cart'

const totalPieces = computed(() => {
    const sum = orderItems.value.reduce((s, item) => s + (Number(item.quantity) || 0), 0)
    return sum % 1 === 0 ? sum : Math.round(sum * 100) / 100
})

const getServiceCartQty = (serviceId) => {
    const lines = orderItems.value.filter(i => i.service_id === serviceId)
    if (lines.length === 0) return 0
    const qty = lines.reduce((s, l) => s + (Number(l.quantity) || 0), 0)
    return qty % 1 === 0 ? qty : Math.round(qty * 10) / 10
}

const scrollContainer = ref(null)
const highlightedLineKey = ref(null)
let highlightTimer = null

const scrollToLine = async (key) => {
    highlightedLineKey.value = key
    await nextTick()
    setTimeout(() => {
        if (!scrollContainer.value) return
        const el = scrollContainer.value.querySelector(`[data-order-line-key="${key}"]`)
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        } else {
            scrollContainer.value.scrollTo({
                top: scrollContainer.value.scrollHeight + 1000,
                behavior: 'smooth'
            })
        }
    }, 40)

    if (highlightTimer) clearTimeout(highlightTimer)
    highlightTimer = setTimeout(() => {
        if (highlightedLineKey.value === key) {
            highlightedLineKey.value = null
        }
    }, 1200)
}

const addServiceToOrder = (service) => {
    const existingIndex = orderItems.value.findIndex(i => i.service_id === service.id && !i.note)
    let keyToHighlight = null

    const unitPrice = service.has_discount ? Number(service.discounted_price) : Number(service.price)
    const origPrice = Number(service.price)
    const discAmt = service.has_discount ? Number(service.discount_amount) : 0
    const discId = service.has_discount ? service.discount_id : null
    const discName = service.has_discount ? service.discount_name : null

    if (existingIndex > -1 && service.pricing_type === 'per_piece') {
        orderItems.value[existingIndex].quantity += 1
        orderItems.value[existingIndex].line_total = round(orderItems.value[existingIndex].quantity * orderItems.value[existingIndex].unit_price)
        keyToHighlight = orderItems.value[existingIndex].line_key
    } else {
        const defaultQty = 1
        const lineKey = 'line_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7)
        orderItems.value.push({
            line_key:            lineKey,
            service_id:          service.id,
            service_name:        service.name,
            pricing_type:        service.pricing_type,
            original_price:      origPrice,
            unit_price:          unitPrice,
            discount_amount:     discAmt,
            laundry_discount_id: discId,
            discount_name:       discName,
            has_discount:        Boolean(service.has_discount),
            quantity:            defaultQty,
            line_total:          round(unitPrice * defaultQty),
            note:                '',
        })
        keyToHighlight = lineKey
    }

    if (keyToHighlight) {
        scrollToLine(keyToHighlight)
    }
}

const incrementQty = (line) => {
    const step = line.pricing_type === 'per_kg' ? 0.5 : 1
    line.quantity = round(Number(line.quantity || 0) + step)
    updateLineTotal(line)
}

const decrementQty = (line) => {
    const step = line.pricing_type === 'per_kg' ? 0.5 : 1
    const min = line.pricing_type === 'per_kg' ? 0.1 : 1
    if (line.quantity > min) {
        line.quantity = round(Math.max(min, Number(line.quantity || 0) - step))
        updateLineTotal(line)
    }
}

const updateLineTotal = (item) => {
    item.quantity = Math.max(0.01, Number(item.quantity || 0))
    item.unit_price = Math.max(0, Number(item.unit_price || 0))
    item.line_total = round(item.quantity * item.unit_price)
}

const removeLine = (index) => {
    orderItems.value.splice(index, 1)
}

const round = (num) => Math.round((num + Number.EPSILON) * 100) / 100

// ── Turnaround & Dates ──────────────────────────────────────────────────────
const defaultHours = Number(props.settings.default_turnaround_days || 2) * 24
const now = new Date()
now.setHours(now.getHours() + defaultHours)
const defaultDueFormatted = now.toISOString().slice(0, 16)
const dueDate = ref(defaultDueFormatted)
const orderNote = ref('')

// ── Discount & Tax Calculations ─────────────────────────────────────────────
const applyGeneralPromo = ref(Boolean(props.activeGeneralDiscount))
const manualDiscountType = ref('fixed') // 'fixed' or 'percentage'
const manualDiscountValue = ref(0)
const showManualDiscount = ref(false)

const depositAmount = ref(0)
const paymentMethod = ref('Cash')
const paymentReference = ref('')

const subtotal = computed(() => {
    return round(orderItems.value.reduce((sum, item) => sum + (Number(item.line_total) || 0), 0))
})

const generalPromoDiscountAmount = computed(() => {
    if (!props.activeGeneralDiscount || !applyGeneralPromo.value) return 0
    const val = Number(props.activeGeneralDiscount.discount_value) || 0
    if (props.activeGeneralDiscount.discount_type === 'percentage') {
        return round((subtotal.value * Math.min(100, val)) / 100)
    }
    return round(Math.min(subtotal.value, val))
})

const manualDiscountAmount = computed(() => {
    if (!showManualDiscount.value) return 0
    const val = Number(manualDiscountValue.value) || 0
    if (val <= 0) return 0
    if (manualDiscountType.value === 'percentage') {
        return round((subtotal.value * Math.min(100, val)) / 100)
    }
    return round(Math.min(subtotal.value, val))
})

const discountAmount = computed(() => {
    if (applyGeneralPromo.value && props.activeGeneralDiscount) {
        return generalPromoDiscountAmount.value
    }
    if (showManualDiscount.value) {
        return manualDiscountAmount.value
    }
    return 0
})

const finalDiscountType = computed(() => {
    if (applyGeneralPromo.value && props.activeGeneralDiscount) {
        return props.activeGeneralDiscount.discount_type
    }
    if (showManualDiscount.value && Number(manualDiscountValue.value) > 0) {
        return manualDiscountType.value
    }
    return null
})

const selectedDiscountId = computed(() => {
    if (applyGeneralPromo.value && props.activeGeneralDiscount) {
        return props.activeGeneralDiscount.id
    }
    return null
})

const serviceDiscountsTotal = computed(() => {
    return round(orderItems.value.reduce((sum, item) => {
        return sum + ((Number(item.discount_amount) || 0) * (Number(item.quantity) || 0))
    }, 0))
})

const totalOrderSavings = computed(() => {
    return round(serviceDiscountsTotal.value + discountAmount.value)
})

const taxableAmount = computed(() => {
    return Math.max(0, subtotal.value - discountAmount.value)
})

const taxAmount = computed(() => {
    if (!props.settings.is_tax_enabled) return 0
    const pct = Number(props.settings.tax_percentage || 0)
    return round((taxableAmount.value * pct) / 100)
})

const total = computed(() => {
    return round(taxableAmount.value + taxAmount.value)
})

const balanceDue = computed(() => {
    const deposit = Math.min(total.value, Math.max(0, Number(depositAmount.value || 0)))
    return round(Math.max(0, total.value - deposit))
})

// Auto-adjust deposit if it exceeds total
watch(total, (newTotal) => {
    if (depositAmount.value > newTotal) {
        depositAmount.value = newTotal
    }
})

// ── Submission ──────────────────────────────────────────────────────────────
const isSubmitting = ref(false)
const submissionError = ref('')

const submitOrder = () => {
    submissionError.value = ''

    if (!selectedCustomer.value) {
        submissionError.value = 'A customer is required to take a laundry order.'
        activeMobileTab.value = 'services'
        nextTick(() => {
            if (customerSearchInputRef.value) {
                customerSearchInputRef.value.focus()
            }
        })
        return
    }

    if (orderItems.value.length === 0) {
        submissionError.value = 'Please select at least one garment or service item.'
        activeMobileTab.value = 'services'
        return
    }

    isSubmitting.value = true

    const payload = {
        customer_id:         selectedCustomer.value.id,
        items:               orderItems.value,
        due_at:              dueDate.value || null,
        discount_amount:     discountAmount.value,
        discount_type:       finalDiscountType.value,
        laundry_discount_id: selectedDiscountId.value,
        deposit_amount:      Number(depositAmount.value) || 0,
        payment_method:      Number(depositAmount.value) > 0 ? paymentMethod.value : null,
        payment_reference:   paymentReference.value || null,
        note:                orderNote.value || null,
    }

    router.post(safeRoute('laundry.orders.store'), payload, {
        onError: (errs) => {
            submissionError.value = Object.values(errs).flat().join(' ')
            isSubmitting.value = false
        },
        onFinish: () => {
            isSubmitting.value = false
        },
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between gap-3 px-3.5 sm:px-6 py-2.5 sm:py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="min-w-0 flex-1">
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">Garment Intake & Order Registration</h1>
                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 truncate">Select customer, add clothes/services, set turnaround time</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <Link
                    :href="safeRoute('laundry.orders.index')"
                    class="px-2.5 sm:px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 text-xs font-semibold transition flex items-center gap-1"
                >
                    <span>←</span>
                    <span class="hidden sm:inline">Back to Orders</span>
                    <span class="sm:hidden">Orders</span>
                </Link>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-3 sm:p-6 lg:p-8 space-y-3 sm:space-y-6 pb-28 lg:pb-8">
            <div class="max-w-7xl mx-auto space-y-3 sm:space-y-6">

            <!-- Mobile Segmented Navigation Tab Switcher (Visible ONLY on screens < lg) -->
            <div class="lg:hidden sticky top-0 z-30 bg-slate-50/95 dark:bg-slate-900/95 backdrop-blur-md pt-0.5 pb-2">
                <div class="flex items-center bg-slate-200/90 dark:bg-slate-800 p-1 rounded-xl shadow-inner text-xs font-bold">
                    <button
                        @click="activeMobileTab = 'services'"
                        type="button"
                        :class="[
                            'flex-1 py-2 px-2 rounded-lg transition flex items-center justify-center gap-1.5 cursor-pointer',
                            activeMobileTab === 'services'
                                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <span>👕 Services & Clothes</span>
                        <span v-if="filteredServices.length > 0" class="text-[10px] text-slate-400 font-normal">({{ filteredServices.length }})</span>
                    </button>
                    <button
                        @click="activeMobileTab = 'cart'"
                        type="button"
                        :class="[
                            'flex-1 py-2 px-2 rounded-lg transition flex items-center justify-center gap-1.5 cursor-pointer relative',
                            activeMobileTab === 'cart'
                                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <span>🛒 Order Lines</span>
                        <span
                            v-if="orderItems.length > 0"
                            class="px-1.5 py-0.2 rounded-full bg-indigo-600 text-white text-[10px] font-black"
                        >
                            {{ orderItems.length }}
                        </span>
                        <span v-if="total > 0" class="font-mono text-emerald-600 dark:text-emerald-400 font-extrabold text-[11px] ml-0.5">
                            {{ formatMoney(total) }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="submissionError" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-medium flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ submissionError }}</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">

                <!-- LEFT COLUMN: Customer & Service Catalog (7 Cols) -->
                <div :class="['lg:col-span-7 space-y-4 sm:space-y-6', activeMobileTab !== 'services' ? 'hidden lg:block' : 'block']">

                    <!-- 1. Customer Selection Card -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 font-black text-xs flex items-center justify-center">1</span>
                                <span>Customer Information (Required)</span>
                            </h3>

                            <button
                                v-if="!selectedCustomer && !showQuickAddCustomer"
                                @click="showQuickAddCustomer = true"
                                class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Quick Add Customer</span>
                            </button>
                        </div>

                        <!-- Customer Selected State -->
                        <div v-if="selectedCustomer" class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-black text-base text-indigo-950 dark:text-indigo-200">
                                        {{ selectedCustomer.name }}
                                    </h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-200 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-200">
                                        Verified
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 flex items-center gap-1.5 flex-wrap">
                                    <span class="flex items-center gap-1 font-mono">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        {{ selectedCustomer.phone }}
                                    </span>
                                    <span v-if="selectedCustomer.whatsapp_number && selectedCustomer.whatsapp_number !== selectedCustomer.phone" class="text-emerald-600 dark:text-emerald-400 font-mono text-xs flex items-center gap-1">
                                        • 💬 WA: {{ selectedCustomer.whatsapp_number }}
                                    </span>
                                    <span v-if="selectedCustomer.email" class="text-slate-500 dark:text-slate-400 font-sans">
                                        • {{ selectedCustomer.email }}
                                    </span>
                                </p>
                                <p v-if="selectedCustomer.address" class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ selectedCustomer.address }}
                                </p>
                                <p v-if="selectedCustomer.debt_bal > 0" class="text-xs font-bold text-amber-600 dark:text-amber-400 pt-1">
                                    Outstanding Customer Debt: {{ formatMoney(selectedCustomer.debt_bal) }}
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="clearCustomer"
                                class="text-xs text-slate-400 hover:text-rose-600 font-semibold transition cursor-pointer"
                            >
                                Change
                            </button>
                        </div>

                        <!-- Customer Search Input -->
                        <div v-else-if="!showQuickAddCustomer" class="relative">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    ref="customerSearchInputRef"
                                    v-model="customerSearch"
                                    @input="onCustomerSearchInput"
                                    @focus="onCustomerSearchInput"
                                    @keydown.enter.prevent="handleCustomerSearchEnter"
                                    @keydown.down.prevent="onSearchKeyDown"
                                    @keydown.up.prevent="onSearchKeyUp"
                                    @keydown.escape="customerSearchResults = []; activeCustomerIndex = -1"
                                    type="text"
                                    placeholder="Search customer by name, phone, or email..."
                                    class="w-full pl-10 pr-10 py-2.5 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <div v-if="isSearchingCustomer" class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none">
                                    <svg class="animate-spin w-4 h-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Search Results Dropdown -->
                            <div
                                v-if="customerSearchResults.length > 0"
                                class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xl z-50 overflow-hidden divide-y divide-slate-100 dark:divide-slate-700 max-h-64 overflow-y-auto"
                            >
                                <button
                                    v-for="(cust, idx) in customerSearchResults"
                                    :key="cust.id"
                                    type="button"
                                    @mousedown.prevent="selectCustomer(cust)"
                                    @click="selectCustomer(cust)"
                                    class="w-full text-left p-3 hover:bg-indigo-50/70 dark:hover:bg-slate-700/60 cursor-pointer flex items-center justify-between text-xs transition"
                                    :class="{ 'bg-indigo-50/90 dark:bg-slate-700': activeCustomerIndex === idx }"
                                >
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                            <span>{{ cust.name }}</span>
                                            <span v-if="cust.branch_id && page.props.current_branch?.id && cust.branch_id !== page.props.current_branch.id" class="px-1.5 py-0.2 text-[9px] font-semibold rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">
                                                Directory
                                            </span>
                                        </p>
                                        <p class="text-slate-400">
                                            <span class="font-mono">{{ cust.phone }}</span>
                                            <span v-if="cust.whatsapp_number && cust.whatsapp_number !== cust.phone" class="text-emerald-500 font-mono text-[11px] ml-1.5">💬 WA: {{ cust.whatsapp_number }}</span>
                                            <span v-if="cust.email" class="text-slate-500 font-sans ml-1.5">• {{ cust.email }}</span>
                                            <span v-if="cust.address" class="text-slate-300 dark:text-slate-500 ml-1.5">• {{ cust.address }}</span>
                                        </p>
                                    </div>
                                    <div v-if="cust.debt_bal > 0" class="text-right">
                                        <span class="text-[10px] text-amber-600 font-bold block">Debt Bal</span>
                                        <span class="font-bold text-amber-700">{{ formatMoney(cust.debt_bal) }}</span>
                                    </div>
                                </button>
                            </div>

                            <!-- Empty Results / Not Found Dropdown -->
                            <div
                                v-else-if="customerSearch.trim().length >= 1 && !isSearchingCustomer && hasSearched"
                                class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xl z-50 p-4 text-center space-y-2"
                            >
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    No customer found matching "<span class="font-bold text-slate-700 dark:text-slate-200">{{ customerSearch }}</span>".
                                </p>
                                <button
                                    @click="openQuickAddFromSearch"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-xs font-bold transition cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>+ Quick Add "{{ customerSearch }}" as Customer</span>
                                </button>
                            </div>
                        </div>

                        <!-- Quick Add Customer Form -->
                        <div v-if="showQuickAddCustomer" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300">New Customer Quick-Add</h4>
                                <button @click="showQuickAddCustomer = false" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                            </div>

                            <p v-if="quickCustomerError" class="text-xs text-rose-600 font-semibold">{{ quickCustomerError }}</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Full Name *</label>
                                    <input v-model="quickCustomerForm.name" type="text" class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Phone Number *</label>
                                    <input v-model="quickCustomerForm.phone" type="text" placeholder="080..." class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono" />
                                    
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <input
                                            id="quick_same_whatsapp"
                                            v-model="quickCustomerForm.same_as_phone"
                                            type="checkbox"
                                            class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                        />
                                        <label for="quick_same_whatsapp" class="text-[11px] text-slate-500 dark:text-slate-400 select-none cursor-pointer">
                                            Same Phone & WhatsApp
                                        </label>
                                    </div>

                                    <div v-if="!quickCustomerForm.same_as_phone" class="mt-2">
                                        <label class="block text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 mb-0.5">
                                            WhatsApp Number
                                        </label>
                                        <input
                                            v-model="quickCustomerForm.whatsapp_number"
                                            type="text"
                                            placeholder="080... or +234..."
                                            class="w-full px-3 py-1.5 text-xs rounded-lg border border-emerald-400 dark:border-emerald-600 bg-white dark:bg-slate-800 font-mono"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">
                                        Email Address <span class="text-slate-400 font-normal">(Optional)</span>
                                    </label>
                                    <input v-model="quickCustomerForm.email" type="email" placeholder="customer@example.com" class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Address (Optional)</label>
                                    <input v-model="quickCustomerForm.address" type="text" placeholder="Street, City..." class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800" />
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button
                                    @click="submitQuickCustomer"
                                    type="button"
                                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition"
                                >
                                    Save & Select Customer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Services Catalogue Browser -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 font-black text-xs flex items-center justify-center">2</span>
                                <span>Pick Garments & Laundry Services</span>
                            </h3>

                            <!-- Search catalog -->
                            <input
                                v-model="catalogSearch"
                                type="text"
                                placeholder="Filter items..."
                                class="px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50"
                            />
                        </div>

                        <!-- Category Tabs (Horizontal Scrollable on Mobile without scrollbar) -->
                        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 sm:flex-wrap -mx-0.5 px-0.5">
                            <button
                                @click="selectedCategory = 'All'"
                                type="button"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition flex-shrink-0 cursor-pointer whitespace-nowrap',
                                    selectedCategory === 'All'
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                                ]"
                            >
                                All ({{ services.length }})
                            </button>
                            <button
                                v-for="cat in categories"
                                :key="cat"
                                @click="selectedCategory = cat"
                                type="button"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition flex-shrink-0 cursor-pointer whitespace-nowrap',
                                    selectedCategory === cat
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                                ]"
                            >
                                {{ cat }}
                            </button>
                        </div>

                        <!-- Items Grid (with padding to prevent any scroll-clipping) -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-2.5 max-h-[460px] sm:max-h-80 lg:max-h-[520px] overflow-y-auto p-1.5 pt-2.5 pr-2 scrollbar-thin">
                            <div
                                v-for="item in filteredServices"
                                :key="item.id"
                                @click="addServiceToOrder(item)"
                                class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 hover:border-indigo-500 hover:bg-indigo-50/30 dark:hover:bg-slate-700/50 cursor-pointer transition select-none flex flex-col justify-between active:scale-95 duration-150 relative group"
                            >
                                <!-- Promo Badge (Top Left) -->
                                <div
                                    v-if="item.has_discount"
                                    class="absolute -top-1.5 -left-1.5 px-2 py-0.5 rounded-full bg-rose-500 text-white font-black text-[9px] shadow-sm flex items-center gap-0.5 z-10 ring-2 ring-white dark:ring-slate-800"
                                >
                                    <span>🏷️</span>
                                    <span>{{ item.discount_type === 'percentage' ? `${item.discount_value}% OFF` : `-${formatMoney(item.discount_value)}` }}</span>
                                </div>

                                <!-- Cart quantity badge (safely placed inside card top-right) -->
                                <div
                                    v-if="getServiceCartQty(item.id) > 0"
                                    class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md bg-indigo-600 text-white font-black text-[10px] shadow-xs flex items-center gap-0.5 z-10 ring-2 ring-white dark:ring-slate-800"
                                >
                                    <span>✓</span>
                                    <span>{{ getServiceCartQty(item.id) }}</span>
                                </div>

                                <div :class="getServiceCartQty(item.id) > 0 ? 'pr-7' : ''">
                                    <p class="font-bold text-xs text-slate-900 dark:text-white line-clamp-2 leading-snug">
                                        {{ item.name }}
                                    </p>
                                    <span class="text-[10px] font-semibold text-slate-400 capitalize block mt-0.5">
                                        {{ item.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}
                                    </span>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between">
                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                        <template v-if="item.has_discount">
                                            <span class="font-black text-xs sm:text-sm text-emerald-600 dark:text-emerald-400 font-mono">
                                                {{ formatMoney(item.discounted_price) }}
                                            </span>
                                            <span class="line-through text-[10px] font-semibold text-slate-400 font-mono">
                                                {{ formatMoney(item.price) }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span class="font-black text-xs sm:text-sm text-indigo-600 dark:text-indigo-400 font-mono">
                                                {{ formatMoney(item.price) }}
                                            </span>
                                        </template>
                                    </div>
                                    <span class="w-5 h-5 rounded-md bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 text-xs font-bold flex items-center justify-center opacity-80 group-hover:opacity-100 group-hover:bg-indigo-600 group-hover:text-white transition">
                                        +
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Order Items & Billing Summary (5 Cols) -->
                <div :class="['lg:col-span-5 space-y-4 sm:space-y-6', activeMobileTab !== 'cart' ? 'hidden lg:block' : 'block']">

                    <!-- Mobile Customer summary in cart tab -->
                    <div class="lg:hidden p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                        <div v-if="selectedCustomer" class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Customer</span>
                                <p class="font-extrabold text-xs text-slate-900 dark:text-white truncate">{{ selectedCustomer.name }}</p>
                                <p class="text-[11px] text-indigo-600 dark:text-indigo-400 font-mono">{{ selectedCustomer.phone }}</p>
                            </div>
                            <button
                                @click="activeMobileTab = 'services'"
                                type="button"
                                class="text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline cursor-pointer shrink-0 ml-2"
                            >
                                Change
                            </button>
                        </div>
                        <div v-else class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-xs text-rose-600 dark:text-rose-400">⚠ Customer Required</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Select or add a customer to finish order.</p>
                            </div>
                            <button
                                @click="activeMobileTab = 'services'"
                                type="button"
                                class="px-2.5 py-1.5 rounded-lg bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition cursor-pointer shrink-0"
                            >
                                Select Customer
                            </button>
                        </div>
                    </div>

                    <!-- Mobile Add More Garments Button -->
                    <button
                        @click="activeMobileTab = 'services'"
                        type="button"
                        class="lg:hidden w-full py-2.5 px-3 rounded-xl border border-indigo-200 dark:border-indigo-800/80 bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold text-xs flex items-center justify-center gap-1.5 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition cursor-pointer"
                    >
                        <span>← Back to Services / Add More Clothes</span>
                    </button>

                    <!-- Selected Items Ticket -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 font-black text-xs flex items-center justify-center">3</span>
                                <span>Order Lines ({{ orderItems.length }})</span>
                            </h3>

                            <button
                                v-if="orderItems.length > 0"
                                @click="orderItems = []"
                                class="text-xs text-rose-500 font-semibold hover:underline cursor-pointer"
                            >
                                Clear All
                            </button>
                        </div>

                        <div v-if="orderItems.length === 0" class="py-12 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl space-y-2">
                            <p>No items added yet. Click services on the left to add.</p>
                            <button
                                @click="activeMobileTab = 'services'"
                                type="button"
                                class="lg:hidden inline-block px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold text-xs mt-2 cursor-pointer"
                            >
                                Browse Services →
                            </button>
                        </div>

                        <div
                            v-else
                            ref="scrollContainer"
                            class="space-y-2.5 max-h-[380px] overflow-y-auto p-2 sm:p-2.5 scroll-smooth scrollbar-thin"
                        >
                            <div
                                v-for="(line, idx) in orderItems"
                                :key="line.line_key || idx"
                                :data-order-line-key="line.line_key || idx"
                                :class="[
                                    highlightedLineKey === (line.line_key || idx)
                                        ? 'order-line-flash shadow-md border-indigo-500 dark:border-indigo-500 ring-2 ring-indigo-500/40'
                                        : 'border-slate-200 dark:border-slate-700/80 hover:border-slate-300 dark:hover:border-slate-600'
                                ]"
                                class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border shadow-2xs space-y-2 group transition-all duration-300 mx-0.5"
                            >
                                <!-- Top Row: #, Service Name, Unit Price, Line Total & Remove -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                        <span class="w-5 h-5 rounded-md bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 font-mono text-[10px] font-extrabold flex items-center justify-center flex-shrink-0 border border-indigo-200/80 dark:border-indigo-800/80 shadow-2xs">
                                            #{{ idx + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <p class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                                    {{ line.service_name }}
                                                </p>
                                                <span v-if="line.discount_amount > 0" class="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                                                    🏷️ {{ line.discount_name || 'Promo' }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1 text-[10px] font-mono flex-wrap">
                                                <span v-if="line.discount_amount > 0" class="line-through text-slate-400 font-medium">
                                                    {{ formatMoney(line.original_price) }}
                                                </span>
                                                <span :class="line.discount_amount > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-400 font-medium'">
                                                    {{ formatMoney(line.unit_price) }} / {{ line.pricing_type === 'per_kg' ? 'kg' : 'pc' }}
                                                </span>
                                                <span v-if="line.discount_amount > 0" class="text-emerald-700 dark:text-emerald-300 font-semibold text-[9px]">
                                                    (Saved {{ formatMoney(line.discount_amount * line.quantity) }})
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="font-extrabold text-xs text-indigo-600 dark:text-indigo-400 whitespace-nowrap font-mono">
                                            {{ formatMoney(line.line_total) }}
                                        </span>
                                        <button
                                            @click="removeLine(idx)"
                                            type="button"
                                            class="w-6 h-6 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                                            title="Remove item"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Middle Row: Stepper Controls & Label -->
                                <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-200/60 dark:border-slate-700/50">
                                    <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                                        {{ line.pricing_type === 'per_kg' ? 'Weight (kg)' : 'Quantity (pcs)' }}
                                    </span>

                                    <!-- Stepper buttons (Touch Optimized) -->
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="decrementQty(line)"
                                            :disabled="line.quantity <= (line.pricing_type === 'per_kg' ? 0.1 : 1)"
                                            class="w-7 h-7 sm:w-6 sm:h-6 flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm sm:text-xs font-bold border border-slate-200 dark:border-slate-700 transition disabled:opacity-30 cursor-pointer shadow-2xs active:scale-90"
                                        >−</button>
                                        <input
                                            v-model.number="line.quantity"
                                            @input="updateLineTotal(line)"
                                            :step="line.pricing_type === 'per_kg' ? '0.1' : '1'"
                                            type="number"
                                            min="0.01"
                                            class="w-16 sm:w-14 text-center bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold font-mono rounded-lg py-1 sm:py-0.5 px-1 outline-none border border-slate-200 dark:border-slate-700 focus:border-indigo-500 transition [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none shadow-2xs"
                                        />
                                        <button
                                            type="button"
                                            @click="incrementQty(line)"
                                            class="w-7 h-7 sm:w-6 sm:h-6 flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm sm:text-xs font-bold border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-2xs active:scale-90"
                                        >+</button>
                                    </div>
                                </div>

                                <!-- Bottom Row: Garment Special Notes -->
                                <div class="pt-0.5">
                                    <input
                                        v-model="line.note"
                                        type="text"
                                        placeholder="Garment notes (e.g. stain on collar, special care)..."
                                        class="w-full px-2.5 py-1.5 text-xs sm:text-[11px] rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Due Date and Instructions -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Promised Due Date & Time
                                </label>
                                <input
                                    v-model="dueDate"
                                    type="datetime-local"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 font-medium"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Order Instructions (Optional)
                                </label>
                                <textarea
                                    v-model="orderNote"
                                    rows="2"
                                    placeholder="Add overall notes or attendant remarks..."
                                    class="w-full px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Order-Level Discounts & Promotions -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <span>🏷️ Order Discount & Promo</span>
                                </span>
                                <span v-if="discountAmount > 0" class="text-[11px] font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">
                                    -{{ formatMoney(discountAmount) }}
                                </span>
                            </div>

                            <!-- Active General Promo Banner if Available -->
                            <div
                                v-if="activeGeneralDiscount"
                                class="p-3 rounded-xl border transition-all"
                                :class="applyGeneralPromo
                                    ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 ring-1 ring-emerald-500/30'
                                    : 'bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 opacity-80'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-emerald-600 text-white">
                                                Store Promo
                                            </span>
                                            <strong class="text-xs text-slate-900 dark:text-white font-extrabold truncate">
                                                {{ activeGeneralDiscount.name }}
                                            </strong>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            {{ activeGeneralDiscount.discount_type === 'percentage'
                                                ? `${activeGeneralDiscount.discount_value}% off order subtotal`
                                                : `${formatMoney(activeGeneralDiscount.discount_value)} flat discount`
                                            }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="applyGeneralPromo = !applyGeneralPromo; if (applyGeneralPromo) showManualDiscount = false"
                                        :class="applyGeneralPromo
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                            : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100'"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer flex-shrink-0"
                                    >
                                        <span v-if="applyGeneralPromo">✓ Applied</span>
                                        <span v-else>+ Apply Promo</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Manual / Custom Discount Toggle (Staff role permitted) -->
                            <div v-if="canApplyDiscount" class="space-y-2">
                                <button
                                    v-if="!showManualDiscount && (!activeGeneralDiscount || !applyGeneralPromo)"
                                    type="button"
                                    @click="showManualDiscount = true; applyGeneralPromo = false"
                                    class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold hover:underline flex items-center gap-1 cursor-pointer"
                                >
                                    <span>+ Apply manual discount code / amount</span>
                                </button>

                                <div v-if="showManualDiscount" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300">Manual Discount</span>
                                        <button
                                            type="button"
                                            @click="showManualDiscount = false; manualDiscountValue = 0"
                                            class="text-[11px] text-slate-400 hover:text-rose-500 cursor-pointer"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[10px] text-slate-400 mb-0.5">Type</label>
                                            <select
                                                v-model="manualDiscountType"
                                                class="w-full px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                                            >
                                                <option value="fixed">Fixed Amount ({{ currencySymbol }})</option>
                                                <option value="percentage">Percentage (%)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-slate-400 mb-0.5">Value</label>
                                            <input
                                                v-model.number="manualDiscountValue"
                                                type="number"
                                                min="0"
                                                :max="manualDiscountType === 'percentage' ? 100 : subtotal"
                                                class="w-full px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Calculation Totals -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Subtotal</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ formatMoney(subtotal) }}</span>
                            </div>

                            <div v-if="discountAmount > 0" class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-medium">
                                <span>
                                    Order Discount
                                    <span class="text-[10px] text-slate-400 ml-1">
                                        ({{ activeGeneralDiscount && applyGeneralPromo ? activeGeneralDiscount.name : (finalDiscountType === 'percentage' ? manualDiscountValue + '%' : 'Fixed') }})
                                    </span>
                                </span>
                                <span class="font-bold font-mono">-{{ formatMoney(discountAmount) }}</span>
                            </div>

                            <div v-if="settings.is_tax_enabled" class="flex items-center justify-between text-slate-500">
                                <span>VAT / Tax ({{ settings.tax_percentage }}%)</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ formatMoney(taxAmount) }}</span>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700 text-sm font-black text-slate-900 dark:text-white">
                                <span>Total Amount</span>
                                <span class="text-indigo-600 dark:text-indigo-400 text-base font-mono">{{ formatMoney(total) }}</span>
                            </div>

                            <!-- Total Savings Pill -->
                            <div v-if="totalOrderSavings > 0" class="mt-1 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/80 text-[11px] font-bold text-emerald-700 dark:text-emerald-300 flex items-center justify-between">
                                <span>🎉 Total Order Savings:</span>
                                <span class="font-black font-mono">{{ formatMoney(totalOrderSavings) }}</span>
                            </div>
                        </div>

                        <!-- Deposit at Intake (Optional) -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Payment / Deposit Now</span>
                                <span class="text-[11px] font-semibold text-slate-400">Optional</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] text-slate-400 mb-0.5">Deposit Amount</label>
                                    <input
                                        v-model.number="depositAmount"
                                        type="number"
                                        min="0"
                                        :max="total"
                                        class="w-full px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-400 mb-0.5">Method</label>
                                    <select
                                        v-model="paymentMethod"
                                        class="w-full px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                                    >
                                        <option value="Cash">Cash</option>
                                        <option value="Card / POS">Card / POS</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Quick Deposit Presets (Touch Friendly) -->
                            <div class="flex items-center gap-1.5 pt-0.5">
                                <button
                                    @click="depositAmount = 0"
                                    type="button"
                                    class="flex-1 py-1 px-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-[10px] font-bold hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition cursor-pointer"
                                >
                                    No Deposit
                                </button>
                                <button
                                    @click="depositAmount = round(total * 0.5)"
                                    type="button"
                                    class="flex-1 py-1 px-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-[10px] font-bold hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition cursor-pointer"
                                >
                                    50%
                                </button>
                                <button
                                    @click="depositAmount = total"
                                    type="button"
                                    class="flex-1 py-1 px-1.5 rounded-md border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/40 text-[10px] font-bold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 transition cursor-pointer"
                                >
                                    Full Pay
                                </button>
                            </div>

                            <div class="flex items-center justify-between pt-1 text-xs">
                                <span class="text-slate-500 font-medium">Balance Due:</span>
                                <span :class="balanceDue > 0 ? 'text-amber-600 dark:text-amber-400 font-black' : 'text-emerald-600 font-bold'">
                                    {{ balanceDue > 0 ? formatMoney(balanceDue) : 'Fully Paid' }}
                                </span>
                            </div>
                        </div>

                        <!-- Submit Order Button -->
                        <button
                            @click="submitOrder"
                            :disabled="isSubmitting || orderItems.length === 0 || !selectedCustomer"
                            type="button"
                            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-extrabold text-sm shadow-md shadow-indigo-600/20 transition transform active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ isSubmitting ? 'Registering Order...' : 'Confirm Order & Print Ticket' }}</span>
                        </button>
                    </div>

                </div>

            </div>

            </div>
        </div>

        <!-- Floating Sticky Mobile Bottom Bar (When on Services tab & has items) -->
        <div
            v-if="orderItems.length > 0 && activeMobileTab === 'services'"
            class="lg:hidden fixed bottom-3 left-3 right-3 z-40 bg-slate-900/95 dark:bg-slate-800/95 backdrop-blur-md text-white p-3 rounded-2xl shadow-2xl border border-slate-700/80 flex items-center justify-between gap-3 transition-all duration-200"
        >
            <div class="min-w-0 pl-1">
                <p class="text-[11px] text-slate-300 font-medium truncate">
                    {{ orderItems.length }} line{{ orderItems.length > 1 ? 's' : '' }} ({{ totalPieces }} {{ totalPieces === 1 ? 'pc' : 'pcs' }})
                </p>
                <p class="text-base font-black text-emerald-400 font-mono leading-tight">
                    {{ formatMoney(total) }}
                </p>
            </div>
            <button
                @click="activeMobileTab = 'cart'"
                type="button"
                class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-extrabold text-xs shadow-md shadow-indigo-600/30 transition flex items-center gap-1.5 flex-shrink-0 cursor-pointer"
            >
                <span>Review Order & Pay</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes orderLineFlashAnim {
    0% {
        background-color: rgba(99, 102, 241, 0.22);
        box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
    }
    45% {
        background-color: rgba(99, 102, 241, 0.12);
        box-shadow: 0 0 5px rgba(99, 102, 241, 0.2);
    }
    100% {
        background-color: transparent;
        box-shadow: none;
    }
}

.order-line-flash {
    animation: orderLineFlashAnim 1.15s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
