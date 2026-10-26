<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/LaundryLayout.vue'
import axios from 'axios'

const props = defineProps({
    services: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Object,
        required: true,
    },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Customer State ──────────────────────────────────────────────────────────
const selectedCustomer = ref(null)
const customerSearch = ref('')
const customerSearchResults = ref([])
const isSearchingCustomer = ref(false)
const showQuickAddCustomer = ref(false)
const quickCustomerForm = ref({
    name: '',
    phone: '',
    address: '',
    email: '',
})
const quickCustomerError = ref('')
const duplicateWarning = ref(null)

let searchTimeout = null
const onCustomerSearchInput = () => {
    clearTimeout(searchTimeout)
    if (customerSearch.value.trim().length < 2) {
        customerSearchResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        isSearchingCustomer.value = true
        try {
            const res = await axios.get(safeRoute('laundry.customers.search'), {
                params: { query: customerSearch.value.trim() },
            })
            customerSearchResults.value = res.data
        } catch (e) {
            console.error('Customer search error:', e)
        } finally {
            isSearchingCustomer.value = false
        }
    }, 300)
}

const selectCustomer = (cust) => {
    selectedCustomer.value = cust
    customerSearch.value = ''
    customerSearchResults.value = []
    duplicateWarning.value = null
}

const clearCustomer = () => {
    selectedCustomer.value = null
}

const submitQuickCustomer = async () => {
    quickCustomerError.value = ''
    duplicateWarning.value = null

    if (!quickCustomerForm.value.name.trim() || !quickCustomerForm.value.phone.trim()) {
        quickCustomerError.value = 'Name and Phone number are required.'
        return
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
        quickCustomerForm.value = { name: '', phone: '', address: '', email: '' }
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
        const matchesCategory = selectedCategory.value === 'All' || s.category === selectedCategory.value
        const matchesSearch = !catalogSearch.value.trim() || s.name.toLowerCase().includes(catalogSearch.value.trim().toLowerCase())
        return matchesCategory && matchesSearch
    })
})

const addServiceToOrder = (service) => {
    const existingIndex = orderItems.value.findIndex(i => i.service_id === service.id && !i.note)
    if (existingIndex > -1 && service.pricing_type === 'per_piece') {
        orderItems.value[existingIndex].quantity += 1
        orderItems.value[existingIndex].line_total = round(orderItems.value[existingIndex].quantity * orderItems.value[existingIndex].unit_price)
    } else {
        const defaultQty = service.pricing_type === 'per_piece' ? 1 : 1.0
        orderItems.value.push({
            service_id:   service.id,
            service_name: service.name,
            pricing_type: service.pricing_type,
            unit_price:   Number(service.price),
            quantity:     defaultQty,
            line_total:   Number(service.price) * defaultQty,
            note:         '',
        })
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
const discountType = ref('fixed') // 'fixed' or 'percentage'
const discountValue = ref(0)
const depositAmount = ref(0)
const paymentMethod = ref('Cash')
const paymentReference = ref('')

const subtotal = computed(() => {
    return round(orderItems.value.reduce((sum, item) => sum + (Number(item.line_total) || 0), 0))
})

const discountAmount = computed(() => {
    const val = Number(discountValue.value) || 0
    if (val <= 0) return 0
    if (discountType.value === 'percentage') {
        return round((subtotal.value * Math.min(100, val)) / 100)
    }
    return round(Math.min(subtotal.value, val))
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
        return
    }

    if (orderItems.value.length === 0) {
        submissionError.value = 'Please select at least one garment or service item.'
        return
    }

    isSubmitting.value = true

    const payload = {
        customer_id:       selectedCustomer.value.id,
        items:             orderItems.value,
        due_at:            dueDate.value || null,
        discount_amount:   discountAmount.value,
        discount_type:     discountType.value,
        deposit_amount:    Number(depositAmount.value) || 0,
        payment_method:    Number(depositAmount.value) > 0 ? paymentMethod.value : null,
        payment_reference: paymentReference.value || null,
        note:              orderNote.value || null,
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
    <LaundryLayout title="New Intake Order">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header / Back Link -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">
                        Garment Intake & Order Registration
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Select customer, add clothes/services, set turnaround time and collect initial deposit.
                    </p>
                </div>

                <Link
                    :href="safeRoute('laundry.orders.index')"
                    class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 text-xs font-semibold transition"
                >
                    ← Back to Orders
                </Link>
            </div>

            <!-- Error Banner -->
            <div v-if="submissionError" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-medium flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ submissionError }}</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- LEFT COLUMN: Customer & Service Catalog (7 Cols) -->
                <div class="lg:col-span-7 space-y-6">

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
                                <p class="text-xs text-slate-600 dark:text-slate-300 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    <span>{{ selectedCustomer.phone }}</span>
                                </p>
                                <p v-if="selectedCustomer.address" class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ selectedCustomer.address }}
                                </p>
                                <p v-if="selectedCustomer.debt_bal > 0" class="text-xs font-bold text-amber-600 dark:text-amber-400 pt-1">
                                    Outstanding Customer Debt: {{ formatMoney(selectedCustomer.debt_bal) }}
                                </p>
                            </div>

                            <button
                                @click="clearCustomer"
                                class="text-xs text-slate-400 hover:text-rose-600 font-semibold transition"
                            >
                                Change
                            </button>
                        </div>

                        <!-- Customer Search Input -->
                        <div v-else-if="!showQuickAddCustomer" class="relative">
                            <input
                                v-model="customerSearch"
                                @input="onCustomerSearchInput"
                                type="text"
                                placeholder="Search customer by name or phone number..."
                                class="w-full px-4 py-2.5 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />

                            <!-- Search Results Dropdown -->
                            <div
                                v-if="customerSearchResults.length > 0"
                                class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-xl z-30 overflow-hidden divide-y divide-slate-100 dark:divide-slate-700"
                            >
                                <div
                                    v-for="cust in customerSearchResults"
                                    :key="cust.id"
                                    @click="selectCustomer(cust)"
                                    class="p-3 hover:bg-indigo-50/70 dark:hover:bg-slate-700/60 cursor-pointer flex items-center justify-between text-xs transition"
                                >
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ cust.name }}</p>
                                        <p class="text-slate-400">{{ cust.phone }}</p>
                                    </div>
                                    <div v-if="cust.debt_bal > 0" class="text-right">
                                        <span class="text-[10px] text-amber-600 font-bold block">Debt Bal</span>
                                        <span class="font-bold text-amber-700">{{ formatMoney(cust.debt_bal) }}</span>
                                    </div>
                                </div>
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
                                    <input v-model="quickCustomerForm.phone" type="text" placeholder="080..." class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Address (Optional)</label>
                                    <input v-model="quickCustomerForm.address" type="text" class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800" />
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

                        <!-- Category Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                @click="selectedCategory = 'All'"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                    selectedCategory === 'All'
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                                ]"
                            >
                                All ({{ services.length }})
                            </button>
                            <button
                                v-for="cat in categories"
                                :key="cat"
                                @click="selectedCategory = cat"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                    selectedCategory === cat
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                                ]"
                            >
                                {{ cat }}
                            </button>
                        </div>

                        <!-- Items Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-80 overflow-y-auto pr-1 scrollbar-thin">
                            <div
                                v-for="item in filteredServices"
                                :key="item.id"
                                @click="addServiceToOrder(item)"
                                class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 hover:border-indigo-500 hover:bg-indigo-50/30 dark:hover:bg-slate-700/50 cursor-pointer transition select-none flex flex-col justify-between"
                            >
                                <div>
                                    <p class="font-bold text-xs text-slate-900 dark:text-white line-clamp-2">
                                        {{ item.name }}
                                    </p>
                                    <span class="text-[10px] font-semibold text-slate-400 capitalize">
                                        {{ item.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}
                                    </span>
                                </div>
                                <div class="mt-2 text-right">
                                    <span class="font-black text-sm text-indigo-600 dark:text-indigo-400">
                                        {{ formatMoney(item.price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Order Items & Billing Summary (5 Cols) -->
                <div class="lg:col-span-5 space-y-6">

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
                                class="text-xs text-rose-500 font-semibold hover:underline"
                            >
                                Clear All
                            </button>
                        </div>

                        <div v-if="orderItems.length === 0" class="py-12 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                            No items added yet. Click services on the left to add.
                        </div>

                        <div v-else class="space-y-3 max-h-72 overflow-y-auto pr-1 scrollbar-thin divide-y divide-slate-100 dark:divide-slate-700">
                            <div
                                v-for="(line, idx) in orderItems"
                                :key="idx"
                                class="pt-3 first:pt-0 space-y-1.5"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                            {{ line.service_name }}
                                        </p>
                                        <span class="text-[10px] text-slate-400">
                                            {{ formatMoney(line.unit_price) }} / {{ line.pricing_type === 'per_kg' ? 'kg' : 'pc' }}
                                        </span>
                                    </div>
                                    <span class="font-extrabold text-xs text-slate-900 dark:text-white whitespace-nowrap">
                                        {{ formatMoney(line.line_total) }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <label class="text-[10px] font-semibold text-slate-400">
                                            {{ line.pricing_type === 'per_kg' ? 'Weight (kg):' : 'Qty (pcs):' }}
                                        </label>
                                        <input
                                            v-model.number="line.quantity"
                                            @input="updateLineTotal(line)"
                                            :step="line.pricing_type === 'per_kg' ? '0.1' : '1'"
                                            type="number"
                                            min="0.01"
                                            class="w-16 px-2 py-0.5 text-xs text-center font-bold rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50"
                                        />
                                    </div>

                                    <button
                                        @click="removeLine(idx)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition"
                                        title="Remove item"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>

                                <input
                                    v-model="line.note"
                                    type="text"
                                    placeholder="Garment notes (e.g. stain on collar, special care)..."
                                    class="w-full px-2 py-1 text-[11px] rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 text-slate-600 dark:text-slate-300"
                                />
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

                        <!-- Financial Calculation Totals -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Subtotal</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(subtotal) }}</span>
                            </div>

                            <div v-if="settings.is_tax_enabled" class="flex items-center justify-between text-slate-500">
                                <span>VAT / Tax ({{ settings.tax_percentage }}%)</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(taxAmount) }}</span>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700 text-sm font-black text-slate-900 dark:text-white">
                                <span>Total Amount</span>
                                <span class="text-indigo-600 dark:text-indigo-400 text-base">{{ formatMoney(total) }}</span>
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
                            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-extrabold text-sm shadow-md shadow-indigo-600/20 transition transform active:scale-98 flex items-center justify-center gap-2"
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
    </LaundryLayout>
</template>
