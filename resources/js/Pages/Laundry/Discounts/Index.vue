<script setup>
import { ref, computed } from 'vue'
import { useForm, router, usePage, Link } from '@inertiajs/vue3'
import dayjs from 'dayjs'

const props = defineProps({
    generalDiscounts: { type: Array, default: () => [] },
    serviceDiscounts: { type: Array, default: () => [] },
    services:         { type: Array, default: () => [] },
})

const page = usePage()
const currencySymbol = computed(() => page.props.system_config?.currency_symbol || '₦')
const branchSlug = computed(() => page.props.current_branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const formatMoney = (val) => {
    const num = Number(val || 0)
    return currencySymbol.value + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const activeTab = ref('general') // 'general' | 'service'

// ── General Promo Modal & Form ──────────────────────────────────────────────
const showGeneralModal = ref(false)
const isEditingGeneral = ref(false)
const editingGeneralId = ref(null)

const generalForm = useForm({
    scope:          'general',
    name:           '',
    discount_type:  'percentage',
    discount_value: 10,
    starts_at:      '',
    ends_at:        '',
    is_active:      true,
    notes:          '',
})

function openCreateGeneralModal() {
    isEditingGeneral.value = false
    editingGeneralId.value = null
    generalForm.reset()
    generalForm.scope          = 'general'
    generalForm.name           = ''
    generalForm.discount_type  = 'percentage'
    generalForm.discount_value = 10
    generalForm.starts_at      = dayjs().format('YYYY-MM-DDTHH:mm')
    generalForm.ends_at        = dayjs().add(14, 'day').format('YYYY-MM-DDTHH:mm')
    generalForm.is_active      = true
    generalForm.notes          = ''
    showGeneralModal.value     = true
}

function openEditGeneralModal(discount) {
    isEditingGeneral.value = true
    editingGeneralId.value = discount.id
    generalForm.scope          = 'general'
    generalForm.name           = discount.name
    generalForm.discount_type  = discount.discount_type
    generalForm.discount_value = Number(discount.discount_value)
    generalForm.starts_at      = dayjs(discount.starts_at).format('YYYY-MM-DDTHH:mm')
    generalForm.ends_at        = dayjs(discount.ends_at).format('YYYY-MM-DDTHH:mm')
    generalForm.is_active      = Boolean(discount.is_active)
    generalForm.notes          = discount.notes || ''
    showGeneralModal.value     = true
}

function submitGeneral() {
    if (isEditingGeneral.value) {
        generalForm.put(safeRoute('laundry.discounts.update', { discount: editingGeneralId.value }), {
            onSuccess: () => { showGeneralModal.value = false },
        })
    } else {
        generalForm.post(safeRoute('laundry.discounts.store'), {
            onSuccess: () => { showGeneralModal.value = false },
        })
    }
}

// ── Service Promo Modal & Form ──────────────────────────────────────────────
const showServiceModal = ref(false)
const isEditingService = ref(false)
const editingServiceDiscountId = ref(null)
const serviceSearchQuery = ref('')

const serviceForm = useForm({
    scope:          'service',
    service_id:     '',
    name:           '',
    discount_type:  'percentage',
    discount_value: 15,
    starts_at:      '',
    ends_at:        '',
    is_active:      true,
    notes:          '',
})

const filteredServicesForSelect = computed(() => {
    const q = serviceSearchQuery.value.toLowerCase().trim()
    if (!q) return props.services
    return props.services.filter(s => (s.name || '').toLowerCase().includes(q))
})

const selectedServiceDetails = computed(() => {
    if (!serviceForm.service_id) return null
    return props.services.find(s => Number(s.id) === Number(serviceForm.service_id))
})

const previewServiceDiscountPrice = computed(() => {
    if (!selectedServiceDetails.value) return 0
    const base = Number(selectedServiceDetails.value.price || 0)
    const val = Number(serviceForm.discount_value || 0)
    if (serviceForm.discount_type === 'percentage') {
        const off = (base * Math.min(100, val)) / 100
        return Math.max(0, Math.round((base - off) * 100) / 100)
    }
    return Math.max(0, Math.round((base - val) * 100) / 100)
})

function openCreateServiceModal() {
    isEditingService.value = false
    editingServiceDiscountId.value = null
    serviceSearchQuery.value = ''
    serviceForm.reset()
    serviceForm.scope          = 'service'
    serviceForm.service_id     = props.services[0]?.id || ''
    serviceForm.name           = ''
    serviceForm.discount_type  = 'percentage'
    serviceForm.discount_value = 15
    serviceForm.starts_at      = dayjs().format('YYYY-MM-DDTHH:mm')
    serviceForm.ends_at        = dayjs().add(14, 'day').format('YYYY-MM-DDTHH:mm')
    serviceForm.is_active      = true
    serviceForm.notes          = ''
    showServiceModal.value     = true
}

function openEditServiceModal(discount) {
    isEditingService.value = true
    editingServiceDiscountId.value = discount.id
    serviceSearchQuery.value = ''
    serviceForm.scope          = 'service'
    serviceForm.service_id     = discount.service_id
    serviceForm.name           = discount.name
    serviceForm.discount_type  = discount.discount_type
    serviceForm.discount_value = Number(discount.discount_value)
    serviceForm.starts_at      = dayjs(discount.starts_at).format('YYYY-MM-DDTHH:mm')
    serviceForm.ends_at        = dayjs(discount.ends_at).format('YYYY-MM-DDTHH:mm')
    serviceForm.is_active      = Boolean(discount.is_active)
    serviceForm.notes          = discount.notes || ''
    showServiceModal.value     = true
}

function submitService() {
    if (isEditingService.value) {
        serviceForm.put(safeRoute('laundry.discounts.update', { discount: editingServiceDiscountId.value }), {
            onSuccess: () => { showServiceModal.value = false },
        })
    } else {
        serviceForm.post(safeRoute('laundry.discounts.store'), {
            onSuccess: () => { showServiceModal.value = false },
        })
    }
}

// ── Generic Actions ─────────────────────────────────────────────────────────
function toggleActive(discount) {
    router.patch(safeRoute('laundry.discounts.toggle', { discount: discount.id }), {}, {
        preserveScroll: true,
    })
}

function destroyDiscount(discount) {
    if (confirm(`Delete discount rule "${discount.name}"?`)) {
        router.delete(safeRoute('laundry.discounts.destroy', { discount: discount.id }), {
            preserveScroll: true,
        })
    }
}

function getDurationStatus(discount) {
    if (!discount.is_active) return { label: 'Disabled', class: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-200 dark:border-slate-700' }
    const now = dayjs()
    const start = dayjs(discount.starts_at)
    const end = dayjs(discount.ends_at)

    if (now.isBefore(start)) {
        return { label: 'Upcoming', class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800' }
    }
    if (now.isAfter(end)) {
        return { label: 'Expired', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800' }
    }
    return { label: 'Active Now', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' }
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- ── Top Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏷️</span>
                    <span>Discounts & Promotional Pricing</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Configure service-level promos and general store-wide order discounts using start and end duration dates.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="activeTab === 'general'"
                    @click="openCreateGeneralModal"
                    type="button"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-md shadow-indigo-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ New General Promo</span>
                </button>

                <button
                    v-else
                    @click="openCreateServiceModal"
                    type="button"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-md shadow-indigo-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ New Service Promo</span>
                </button>
            </div>
        </div>

        <!-- ── Navigation Tabs ────────────────────────────────────────────────── -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/80 dark:bg-slate-800/60 flex items-center gap-2 flex-shrink-0">
            <button
                type="button"
                @click="activeTab = 'general'"
                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 cursor-pointer"
                :class="activeTab === 'general'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
            >
                <span>🏷️ General Order Promos</span>
                <span
                    class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                    :class="activeTab === 'general' ? 'bg-indigo-700 text-white' : 'bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-300'"
                >
                    {{ generalDiscounts.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'service'"
                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 cursor-pointer"
                :class="activeTab === 'service'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
            >
                <span>🧺 Service-Level Promos</span>
                <span
                    class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                    :class="activeTab === 'service' ? 'bg-indigo-700 text-white' : 'bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-300'"
                >
                    {{ serviceDiscounts.length }}
                </span>
            </button>
        </div>

        <!-- ── Main Content Area ──────────────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- ════ TAB 1: GENERAL ORDER PROMOS ════════════════════════════ -->
                <div v-if="activeTab === 'general'" class="space-y-4">
                    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                                    General Order Discounts
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Store-wide promotional discounts applied to the overall laundry order subtotal at checkout.
                                </p>
                            </div>
                            <button
                                @click="openCreateGeneralModal"
                                type="button"
                                class="px-3 py-1.5 text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 rounded-lg border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition cursor-pointer"
                            >
                                + Add General Promo
                            </button>
                        </div>

                        <div v-if="generalDiscounts.length === 0" class="py-16 text-center text-slate-400">
                            <span class="text-3xl block mb-2">🏷️</span>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No general discounts configured yet.</p>
                            <p class="text-xs text-slate-400 mt-1">Create a general discount to offer percentage or fixed amount discounts on entire laundry orders.</p>
                            <button
                                @click="openCreateGeneralModal"
                                type="button"
                                class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition"
                            >
                                Create First General Promo
                            </button>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3">Discount Name</th>
                                        <th class="px-5 py-3">Type & Value</th>
                                        <th class="px-5 py-3">Duration (Validity Period)</th>
                                        <th class="px-5 py-3 text-center">Status</th>
                                        <th class="px-5 py-3 text-center">Active</th>
                                        <th class="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="d in generalDiscounts" :key="d.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span>{{ d.name }}</span>
                                            </div>
                                            <div v-if="d.notes" class="text-[11px] text-slate-400 mt-0.5">{{ d.notes }}</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">By {{ d.creator?.name || 'Admin' }}</div>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black" :class="d.discount_type === 'percentage' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'">
                                                {{ d.discount_type === 'percentage' ? `${Number(d.discount_value)}% OFF` : `${formatMoney(d.discount_value)} OFF` }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <div class="font-medium text-slate-800 dark:text-slate-200">
                                                {{ dayjs(d.starts_at).format('D MMM YYYY, h:mm A') }}
                                            </div>
                                            <div class="text-[11px] text-slate-400">
                                                to {{ dayjs(d.ends_at).format('D MMM YYYY, h:mm A') }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase" :class="getDurationStatus(d).class">
                                                {{ getDurationStatus(d).label }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <button
                                                type="button"
                                                @click="toggleActive(d)"
                                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                                :class="d.is_active ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-700'"
                                                :title="d.is_active ? 'Click to deactivate' : 'Click to activate'"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="d.is_active ? 'translate-x-4' : 'translate-x-0'"
                                                />
                                            </button>
                                        </td>
                                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    @click="openEditGeneralModal(d)"
                                                    class="px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="destroyDiscount(d)"
                                                    class="px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ════ TAB 2: SERVICE-LEVEL PROMOS ════════════════════════════ -->
                <div v-if="activeTab === 'service'" class="space-y-4">
                    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                        <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">
                                    Service Promotional Discounts
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Promotional discounts tied directly to specific laundry services (e.g. Suits, Agbada, Bedspreads).
                                </p>
                            </div>
                            <button
                                @click="openCreateServiceModal"
                                type="button"
                                class="px-3 py-1.5 text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 rounded-lg border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition cursor-pointer"
                            >
                                + Add Service Promo
                            </button>
                        </div>

                        <div v-if="serviceDiscounts.length === 0" class="py-16 text-center text-slate-400">
                            <span class="text-3xl block mb-2">🧺</span>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No service discounts configured yet.</p>
                            <p class="text-xs text-slate-400 mt-1">Create a service discount to discount specific garments or washing services for a limited duration.</p>
                            <button
                                @click="openCreateServiceModal"
                                type="button"
                                class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition"
                            >
                                Create First Service Promo
                            </button>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3">Laundry Service</th>
                                        <th class="px-5 py-3">Promo Name</th>
                                        <th class="px-5 py-3">Discount</th>
                                        <th class="px-5 py-3 text-right">Promo Price</th>
                                        <th class="px-5 py-3">Duration (Validity Period)</th>
                                        <th class="px-5 py-3 text-center">Status</th>
                                        <th class="px-5 py-3 text-center">Active</th>
                                        <th class="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="sd in serviceDiscounts" :key="sd.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span>{{ sd.service?.name || 'Service #' + sd.service_id }}</span>
                                                <span class="px-1.5 py-0.2 rounded text-[10px] uppercase font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ sd.service?.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">
                                                Base Price: {{ formatMoney(sd.service?.price || 0) }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 font-semibold text-slate-800 dark:text-slate-200">
                                            {{ sd.name }}
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black" :class="sd.discount_type === 'percentage' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'">
                                                {{ sd.discount_type === 'percentage' ? `${Number(sd.discount_value)}% OFF` : `${formatMoney(sd.discount_value)} OFF` }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-black">
                                            <div v-if="sd.service" class="text-emerald-600 dark:text-emerald-400 font-mono text-sm">
                                                {{ formatMoney(
                                                    sd.discount_type === 'percentage'
                                                        ? Math.max(0, sd.service.price - (sd.service.price * Number(sd.discount_value) / 100))
                                                        : Math.max(0, sd.service.price - Number(sd.discount_value))
                                                ) }}
                                            </div>
                                            <div v-if="sd.service" class="line-through text-[11px] text-slate-400 font-mono">
                                                {{ formatMoney(sd.service.price) }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <div class="font-medium text-slate-800 dark:text-slate-200">
                                                {{ dayjs(sd.starts_at).format('D MMM YYYY, h:mm A') }}
                                            </div>
                                            <div class="text-[11px] text-slate-400">
                                                to {{ dayjs(sd.ends_at).format('D MMM YYYY, h:mm A') }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase" :class="getDurationStatus(sd).class">
                                                {{ getDurationStatus(sd).label }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <button
                                                type="button"
                                                @click="toggleActive(sd)"
                                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                                :class="sd.is_active ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-700'"
                                                :title="sd.is_active ? 'Click to deactivate' : 'Click to activate'"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="sd.is_active ? 'translate-x-4' : 'translate-x-0'"
                                                />
                                            </button>
                                        </td>
                                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    @click="openEditServiceModal(sd)"
                                                    class="px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="destroyDiscount(sd)"
                                                    class="px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ════ MODAL: CREATE / EDIT GENERAL PROMO ═════════════════════════════ -->
        <div
            v-if="showGeneralModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
            @click.self="showGeneralModal = false"
        >
            <div class="w-full max-w-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🏷️</span>
                        <span>{{ isEditingGeneral ? 'Edit General Order Promo' : 'New General Order Promo' }}</span>
                    </h3>
                    <button @click="showGeneralModal = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitGeneral" class="space-y-4">
                    <!-- Promo Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Promo Campaign Name</label>
                        <input
                            v-model="generalForm.name"
                            type="text"
                            required
                            placeholder="e.g. Weekend Special, Ramadan Promo, 10% Off"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Discount Type & Value -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Discount Type</label>
                            <select
                                v-model="generalForm.discount_type"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
                            >
                                <option value="percentage">Percentage (% Off)</option>
                                <option value="fixed">Fixed Cash Amount (₦ Off)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                {{ generalForm.discount_type === 'percentage' ? 'Percentage (%)' : 'Amount (' + currencySymbol + ')' }}
                            </label>
                            <input
                                v-model.number="generalForm.discount_value"
                                type="number"
                                step="any"
                                min="0.01"
                                :max="generalForm.discount_type === 'percentage' ? 100 : undefined"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold"
                            />
                        </div>
                    </div>

                    <!-- Duration: Start and End Datetime -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Start Date & Time</label>
                            <input
                                v-model="generalForm.starts_at"
                                type="datetime-local"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">End Date & Time</label>
                            <input
                                v-model="generalForm.ends_at"
                                type="datetime-local"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Notes / Description (Optional)</label>
                        <textarea
                            v-model="generalForm.notes"
                            rows="2"
                            placeholder="Optional internal remarks or rules..."
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-2 pt-1">
                        <input
                            v-model="generalForm.is_active"
                            id="gen-is-active"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                        />
                        <label for="gen-is-active" class="text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                            Enable promo discount immediately
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <button
                            type="button"
                            @click="showGeneralModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="generalForm.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md transition disabled:opacity-50"
                        >
                            {{ isEditingGeneral ? 'Update Promo' : 'Create Promo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ════ MODAL: CREATE / EDIT SERVICE PROMO ═════════════════════════════ -->
        <div
            v-if="showServiceModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
            @click.self="showServiceModal = false"
        >
            <div class="w-full max-w-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🧺</span>
                        <span>{{ isEditingService ? 'Edit Service Promo' : 'New Service Promo' }}</span>
                    </h3>
                    <button @click="showServiceModal = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitService" class="space-y-4">
                    <!-- Service Selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Laundry Service</label>
                        <select
                            v-model="serviceForm.service_id"
                            required
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold"
                        >
                            <option value="" disabled>Select a laundry service...</option>
                            <option v-for="s in filteredServicesForSelect" :key="s.id" :value="s.id">
                                {{ s.name }} ({{ s.pricing_type === 'per_kg' ? 'Per KG' : 'Per Piece' }}) - {{ formatMoney(s.price) }}
                            </option>
                        </select>
                    </div>

                    <!-- Promo Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Promo Label / Name</label>
                        <input
                            v-model="serviceForm.name"
                            type="text"
                            required
                            placeholder="e.g. Suit Special, Agbada 15% Off, Blanket Promo"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Discount Type & Value -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Discount Type</label>
                            <select
                                v-model="serviceForm.discount_type"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
                            >
                                <option value="percentage">Percentage (% Off)</option>
                                <option value="fixed">Fixed Amount (₦ Off)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                {{ serviceForm.discount_type === 'percentage' ? 'Percentage (%)' : 'Amount (' + currencySymbol + ')' }}
                            </label>
                            <input
                                v-model.number="serviceForm.discount_value"
                                type="number"
                                step="any"
                                min="0.01"
                                :max="serviceForm.discount_type === 'percentage' ? 100 : undefined"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold"
                            />
                        </div>
                    </div>

                    <!-- Price Preview Banner -->
                    <div v-if="selectedServiceDetails" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-semibold text-slate-500">Preview Discounted Price:</span>
                            <div class="text-xs text-slate-400">
                                Original: <span class="line-through">{{ formatMoney(selectedServiceDetails.price) }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-base font-black text-emerald-600 dark:text-emerald-400">
                                {{ formatMoney(previewServiceDiscountPrice) }}
                            </span>
                            <span class="text-[10px] text-slate-400 block">
                                / {{ selectedServiceDetails.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}
                            </span>
                        </div>
                    </div>

                    <!-- Duration: Start and End Datetime -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Start Date & Time</label>
                            <input
                                v-model="serviceForm.starts_at"
                                type="datetime-local"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">End Date & Time</label>
                            <input
                                v-model="serviceForm.ends_at"
                                type="datetime-local"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-2 pt-1">
                        <input
                            v-model="serviceForm.is_active"
                            id="srv-is-active"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                        />
                        <label for="srv-is-active" class="text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                            Enable service promo immediately
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <button
                            type="button"
                            @click="showServiceModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="serviceForm.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md transition disabled:opacity-50"
                        >
                            {{ isEditingService ? 'Update Service Promo' : 'Create Service Promo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
