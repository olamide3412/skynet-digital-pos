<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    discounts:      { type: Array, default: () => [] },
    itemDiscounts:  { type: Array, default: () => [] },
    discountGroups: { type: Array, default: () => [] },
    items:          { type: Array, default: () => [] },
})

const activeTab = ref('overall') // 'overall' | 'item' | 'group'

// ── Overall Discounts (Store-Wide) State & Logic ─────────────────────────────
const isEditingOverall = ref(false)
const currentOverallDiscount = ref(null)

const overallForm = useForm({
    discount_type:   'percentage',
    discount_value:  0,
    start_date_time: '',
    end_date_time:   '',
    is_apply:        true,
})

function openCreateOverallModal() {
    isEditingOverall.value = false
    currentOverallDiscount.value = null
    overallForm.reset()
    overallForm.discount_type   = 'percentage'
    overallForm.discount_value  = 5
    overallForm.start_date_time = dayjs().format('YYYY-MM-DDTHH:mm')
    overallForm.end_date_time   = dayjs().add(1, 'month').format('YYYY-MM-DDTHH:mm')
    overallForm.is_apply        = true
    document.getElementById('overall-discount-modal').showModal()
}

function openEditOverallModal(discount) {
    isEditingOverall.value = true
    currentOverallDiscount.value = discount
    overallForm.discount_type   = discount.discount_type
    overallForm.discount_value  = Number(discount.discount_value)
    overallForm.start_date_time = dayjs(discount.start_date_time).format('YYYY-MM-DDTHH:mm')
    overallForm.end_date_time   = dayjs(discount.end_date_time).format('YYYY-MM-DDTHH:mm')
    overallForm.is_apply        = !!discount.is_apply
    document.getElementById('overall-discount-modal').showModal()
}

function submitOverall() {
    if (isEditingOverall.value) {
        overallForm.put(route('pos.discounts.update', currentOverallDiscount.value.id), {
            onSuccess: () => document.getElementById('overall-discount-modal').close(),
        })
    } else {
        overallForm.post(route('pos.discounts.store'), {
            onSuccess: () => document.getElementById('overall-discount-modal').close(),
        })
    }
}

function destroyOverall(discount) {
    if (confirm('Are you sure you want to delete this store-wide discount rule?')) {
        router.delete(route('pos.discounts.destroy', discount.id))
    }
}

// ── Item Discounts State & Logic ─────────────────────────────────────────────
const isEditingItem = ref(false)
const currentItemDiscount = ref(null)
const itemSearchQuery = ref('')

const itemDiscountForm = useForm({
    item_id:        '',
    discount_type:  'percentage',
    discount_value: 0,
    starts_at:      '',
    ends_at:        '',
    is_active:      true,
})

const filteredItemsForSelect = computed(() => {
    const q = itemSearchQuery.value.toLowerCase().trim()
    if (!q) return props.items.slice(0, 50)
    return props.items.filter(i =>
        (i.item_name || '').toLowerCase().includes(q) ||
        (i.barcode_number || '').toLowerCase().includes(q)
    ).slice(0, 50)
})

const selectedItemDetails = computed(() => {
    if (!itemDiscountForm.item_id) return null
    return props.items.find(i => String(i.id) === String(itemDiscountForm.item_id))
})

const selectedItemInGroupWarning = computed(() => {
    if (!itemDiscountForm.item_id) return null
    const matchedGroup = props.discountGroups.find(g =>
        g.is_active && Array.isArray(g.items) && g.items.some(it => String(it.id) === String(itemDiscountForm.item_id))
    )
    return matchedGroup ? matchedGroup.name : null
})

function openCreateItemDiscountModal() {
    isEditingItem.value = false
    currentItemDiscount.value = null
    itemDiscountForm.reset()
    itemDiscountForm.item_id        = props.items[0]?.id || ''
    itemDiscountForm.discount_type  = 'percentage'
    itemDiscountForm.discount_value = 10
    itemDiscountForm.starts_at      = dayjs().format('YYYY-MM-DDTHH:mm')
    itemDiscountForm.ends_at        = dayjs().add(1, 'month').format('YYYY-MM-DDTHH:mm')
    itemDiscountForm.is_active      = true
    itemSearchQuery.value           = ''
    document.getElementById('item-discount-modal').showModal()
}

function openEditItemDiscountModal(disc) {
    isEditingItem.value = true
    currentItemDiscount.value = disc
    itemDiscountForm.item_id        = disc.item_id
    itemDiscountForm.discount_type  = disc.discount_type
    itemDiscountForm.discount_value = Number(disc.discount_value)
    itemDiscountForm.starts_at      = dayjs(disc.starts_at).format('YYYY-MM-DDTHH:mm')
    itemDiscountForm.ends_at        = dayjs(disc.ends_at).format('YYYY-MM-DDTHH:mm')
    itemDiscountForm.is_active      = !!disc.is_active
    itemSearchQuery.value           = disc.item?.item_name || ''
    document.getElementById('item-discount-modal').showModal()
}

function submitItemDiscount() {
    if (isEditingItem.value) {
        itemDiscountForm.put(route('pos.item-discounts.update', currentItemDiscount.value.id), {
            onSuccess: () => document.getElementById('item-discount-modal').close(),
        })
    } else {
        itemDiscountForm.post(route('pos.item-discounts.store'), {
            onSuccess: () => document.getElementById('item-discount-modal').close(),
        })
    }
}

function toggleItemDiscountStatus(disc) {
    router.patch(route('pos.item-discounts.toggle', disc.id), {}, { preserveScroll: true })
}

function destroyItemDiscount(disc) {
    if (confirm(`Are you sure you want to delete the item discount for "${disc.item?.item_name}"?`)) {
        router.delete(route('pos.item-discounts.destroy', disc.id))
    }
}

// ── Group Discounts State & Logic ────────────────────────────────────────────
const isEditingGroup = ref(false)
const currentGroupDiscount = ref(null)
const groupItemSearch = ref('')

const groupForm = useForm({
    name:           '',
    discount_type:  'percentage',
    discount_value: 0,
    starts_at:      '',
    ends_at:        '',
    is_active:      true,
    item_ids:       [],
})

const filteredItemsForGroup = computed(() => {
    const q = groupItemSearch.value.toLowerCase().trim()
    if (!q) return props.items
    return props.items.filter(i =>
        (i.item_name || '').toLowerCase().includes(q) ||
        (i.barcode_number || '').toLowerCase().includes(q)
    )
})

function hasActiveItemDiscount(itemId) {
    return props.itemDiscounts.some(d => d.is_active && String(d.item_id) === String(itemId))
}

function isInAnotherActiveGroup(itemId, currentGroupId = null) {
    return props.discountGroups.some(g =>
        g.is_active &&
        String(g.id) !== String(currentGroupId) &&
        Array.isArray(g.items) &&
        g.items.some(it => String(it.id) === String(itemId))
    )
}

function toggleGroupItemSelection(itemId) {
    const idx = groupForm.item_ids.indexOf(itemId)
    if (idx > -1) {
        groupForm.item_ids.splice(idx, 1)
    } else {
        groupForm.item_ids.push(itemId)
    }
}

function selectAllFilteredGroupItems() {
    filteredItemsForGroup.value.forEach(i => {
        if (!groupForm.item_ids.includes(i.id)) {
            groupForm.item_ids.push(i.id)
        }
    })
}

function deselectAllGroupItems() {
    groupForm.item_ids = []
}

function openCreateGroupModal() {
    isEditingGroup.value = false
    currentGroupDiscount.value = null
    groupForm.reset()
    groupForm.name           = ''
    groupForm.discount_type  = 'percentage'
    groupForm.discount_value = 10
    groupForm.starts_at      = dayjs().format('YYYY-MM-DDTHH:mm')
    groupForm.ends_at        = dayjs().add(1, 'month').format('YYYY-MM-DDTHH:mm')
    groupForm.is_active      = true
    groupForm.item_ids       = []
    groupItemSearch.value    = ''
    document.getElementById('group-discount-modal').showModal()
}

function openEditGroupModal(group) {
    isEditingGroup.value = true
    currentGroupDiscount.value = group
    groupForm.name           = group.name
    groupForm.discount_type  = group.discount_type
    groupForm.discount_value = Number(group.discount_value)
    groupForm.starts_at      = dayjs(group.starts_at).format('YYYY-MM-DDTHH:mm')
    groupForm.ends_at        = dayjs(group.ends_at).format('YYYY-MM-DDTHH:mm')
    groupForm.is_active      = !!group.is_active
    groupForm.item_ids       = (group.items || []).map(i => i.id)
    groupItemSearch.value    = ''
    document.getElementById('group-discount-modal').showModal()
}

function submitGroupDiscount() {
    if (isEditingGroup.value) {
        groupForm.put(route('pos.discount-groups.update', currentGroupDiscount.value.id), {
            onSuccess: () => document.getElementById('group-discount-modal').close(),
        })
    } else {
        groupForm.post(route('pos.discount-groups.store'), {
            onSuccess: () => document.getElementById('group-discount-modal').close(),
        })
    }
}

function toggleGroupStatus(group) {
    router.patch(route('pos.discount-groups.toggle', group.id), {}, { preserveScroll: true })
}

function destroyGroup(group) {
    if (confirm(`Are you sure you want to delete the discount group "${group.name}"?`)) {
        router.delete(route('pos.discount-groups.destroy', group.id))
    }
}

// ── Timing & Status Helpers ──────────────────────────────────────────────────
function getDiscountStatus(is_active, start, end) {
    if (!is_active) {
        return { label: 'Inactive', class: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }
    }
    const now = dayjs()
    const startDate = dayjs(start)
    const endDate = dayjs(end)

    if (now.isBefore(startDate)) {
        return { label: 'Scheduled', class: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' }
    }
    if (now.isAfter(endDate)) {
        return { label: 'Expired', class: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400' }
    }
    return { label: 'Active Now', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' }
}

function computeDiscountedPreview(originalPrice, type, value) {
    const orig = Number(originalPrice || 0)
    const val = Number(value || 0)
    if (type === 'percentage') {
        const off = (orig * val) / 100
        return Math.max(0, Math.round((orig - off) * 100) / 100)
    }
    return Math.max(0, Math.round((orig - val) * 100) / 100)
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0 gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏷️</span> Discounts & Promotions
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Manage item-level, group promotional campaigns, and store-wide cart discounts</p>
            </div>

            <!-- Action Button Dynamic to Active Tab -->
            <div class="flex items-center gap-2">
                <button
                    v-if="activeTab === 'overall'"
                    @click="openCreateOverallModal"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-emerald-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Store Discount
                </button>

                <button
                    v-if="activeTab === 'item'"
                    @click="openCreateItemDiscountModal"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-indigo-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Item Discount
                </button>

                <button
                    v-if="activeTab === 'group'"
                    @click="openCreateGroupModal"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow-md shadow-purple-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Discount Group
                </button>
            </div>
        </div>

        <!-- Tab Navigation Bar -->
        <div class="px-6 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0 flex items-center gap-2 overflow-x-auto">
            <button
                type="button"
                @click="activeTab = 'overall'"
                :class="activeTab === 'overall' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium'"
                class="py-3 px-4 border-b-2 text-xs flex items-center gap-2 transition cursor-pointer whitespace-nowrap"
            >
                <span>🛒</span>
                <span>Overall (Cart) Discounts</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono">{{ discounts.length }}</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'item'"
                :class="activeTab === 'item' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium'"
                class="py-3 px-4 border-b-2 text-xs flex items-center gap-2 transition cursor-pointer whitespace-nowrap"
            >
                <span>📦</span>
                <span>Item-Level Discounts</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono">{{ itemDiscounts.length }}</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'group'"
                :class="activeTab === 'group' ? 'border-purple-600 text-purple-600 dark:text-purple-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 font-medium'"
                class="py-3 px-4 border-b-2 text-xs flex items-center gap-2 transition cursor-pointer whitespace-nowrap"
            >
                <span>📁</span>
                <span>Group Discounts</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono">{{ discountGroups.length }}</span>
            </button>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 1: OVERALL STORE DISCOUNTS
                 ═══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'overall'" class="space-y-4">
                <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-900 dark:text-emerald-200 flex items-start gap-3">
                    <span class="text-base">💡</span>
                    <div>
                        <span class="font-bold">Order of Application:</span> Overall cart discounts apply <strong>last</strong> on top of the subtotal that already includes any item or group level promotional pricing.
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold">Type</th>
                                <th class="text-left px-4 py-3 font-semibold">Value</th>
                                <th class="text-left px-4 py-3 font-semibold">Valid From</th>
                                <th class="text-left px-4 py-3 font-semibold">Valid Until</th>
                                <th class="text-left px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="discount in discounts" :key="discount.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-4 py-3 text-slate-800 dark:text-slate-300 font-medium capitalize">{{ discount.discount_type }}</td>
                                <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400 font-bold font-mono">
                                    {{ discount.discount_type === 'percentage' ? discount.discount_value + '%' : '₦' + discount.discount_value }}
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-mono text-xs">{{ dayjs(discount.start_date_time).format('DD MMM YYYY HH:mm') }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-mono text-xs">{{ dayjs(discount.end_date_time).format('DD MMM YYYY HH:mm') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full" :class="getDiscountStatus(discount.is_apply, discount.start_date_time, discount.end_date_time).class">
                                        {{ getDiscountStatus(discount.is_apply, discount.start_date_time, discount.end_date_time).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEditOverallModal(discount)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition cursor-pointer">Edit</button>
                                        <button @click="destroyOverall(discount)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition cursor-pointer">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!discounts.length">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No store-wide discount rules configured.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 2: ITEM-LEVEL DISCOUNTS
                 ═══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'item'" class="space-y-4">
                <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 text-xs text-indigo-900 dark:text-indigo-200 flex items-start gap-3">
                    <span class="text-base">⭐</span>
                    <div>
                        <span class="font-bold">Priority 1 Rule:</span> Item-level discounts always apply first. If an item has an active item discount, it overrides any group discount assigned to that item.
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold">Item</th>
                                <th class="text-left px-4 py-3 font-semibold">Standard Price</th>
                                <th class="text-left px-4 py-3 font-semibold">Discount</th>
                                <th class="text-left px-4 py-3 font-semibold">Effective Price</th>
                                <th class="text-left px-4 py-3 font-semibold">Schedule Period</th>
                                <th class="text-left px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="disc in itemDiscounts" :key="disc.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-4 py-3 text-slate-900 dark:text-white font-medium">
                                    <div class="flex items-center gap-2">
                                        <span>{{ disc.item?.item_name || 'Item #' + disc.item_id }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-mono bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                            {{ disc.item?.unit_label || 'Unit' }}
                                        </span>
                                    </div>
                                    <div v-if="disc.item?.barcode_number" class="text-[11px] text-slate-400 font-mono">
                                        {{ disc.item.barcode_number }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono">
                                    ₦{{ Number(disc.item?.price || 0).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 text-indigo-600 dark:text-indigo-400 font-bold font-mono">
                                    {{ disc.discount_type === 'percentage' ? disc.discount_value + '%' : '₦' + disc.discount_value }}
                                </td>
                                <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400 font-bold font-mono">
                                    ₦{{ computeDiscountedPreview(disc.item?.price, disc.discount_type, disc.discount_value).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300 text-xs font-mono">
                                    <div>{{ dayjs(disc.starts_at).format('DD MMM YYYY HH:mm') }}</div>
                                    <div class="text-slate-400">→ {{ dayjs(disc.ends_at).format('DD MMM YYYY HH:mm') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full" :class="getDiscountStatus(disc.is_active, disc.starts_at, disc.ends_at).class">
                                        {{ getDiscountStatus(disc.is_active, disc.starts_at, disc.ends_at).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="toggleItemDiscountStatus(disc)"
                                            :class="disc.is_active ? 'text-amber-600 hover:text-amber-500' : 'text-emerald-600 hover:text-emerald-500'"
                                            class="text-xs font-semibold transition cursor-pointer"
                                        >
                                            {{ disc.is_active ? 'Pause' : 'Activate' }}
                                        </button>
                                        <button @click="openEditItemDiscountModal(disc)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition cursor-pointer">Edit</button>
                                        <button @click="destroyItemDiscount(disc)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition cursor-pointer">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!itemDiscounts.length">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No item-level discounts configured.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 3: GROUP DISCOUNTS
                 ═══════════════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'group'" class="space-y-4">
                <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800/60 text-xs text-purple-900 dark:text-purple-200 flex items-start gap-3">
                    <span class="text-base">📁</span>
                    <div>
                        <span class="font-bold">Priority 2 Rule:</span> Group discounts apply to all assigned items that do not have their own active item discount. An item belongs to at most one active discount group.
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold">Group Name</th>
                                <th class="text-left px-4 py-3 font-semibold">Discount</th>
                                <th class="text-left px-4 py-3 font-semibold">Assigned Items</th>
                                <th class="text-left px-4 py-3 font-semibold">Schedule Period</th>
                                <th class="text-left px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                            <tr v-for="group in discountGroups" :key="group.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-4 py-3 text-slate-900 dark:text-white font-bold">
                                    {{ group.name }}
                                </td>
                                <td class="px-4 py-3 text-purple-600 dark:text-purple-400 font-bold font-mono">
                                    {{ group.discount_type === 'percentage' ? group.discount_value + '%' : '₦' + group.discount_value }}
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300 text-xs">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 font-mono">
                                            {{ (group.items || []).length }} items
                                        </span>
                                        <span v-for="it in (group.items || []).slice(0, 2)" :key="it.id" class="px-1.5 py-0.5 rounded text-[11px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 truncate max-w-[120px]">
                                            {{ it.item_name }}
                                        </span>
                                        <span v-if="(group.items || []).length > 2" class="text-[10px] text-slate-400">
                                            +{{ (group.items || []).length - 2 }} more
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300 text-xs font-mono">
                                    <div>{{ dayjs(group.starts_at).format('DD MMM YYYY HH:mm') }}</div>
                                    <div class="text-slate-400">→ {{ dayjs(group.ends_at).format('DD MMM YYYY HH:mm') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full" :class="getDiscountStatus(group.is_active, group.starts_at, group.ends_at).class">
                                        {{ getDiscountStatus(group.is_active, group.starts_at, group.ends_at).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="toggleGroupStatus(group)"
                                            :class="group.is_active ? 'text-amber-600 hover:text-amber-500' : 'text-emerald-600 hover:text-emerald-500'"
                                            class="text-xs font-semibold transition cursor-pointer"
                                        >
                                            {{ group.is_active ? 'Pause' : 'Activate' }}
                                        </button>
                                        <button @click="openEditGroupModal(group)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 transition cursor-pointer">Edit / Items</button>
                                        <button @click="destroyGroup(group)" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-500 transition cursor-pointer">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!discountGroups.length">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">No group discounts configured.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL 1: OVERALL STORE-WIDE DISCOUNT
             ═══════════════════════════════════════════════════════════════════ -->
        <dialog id="overall-discount-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ isEditingOverall ? 'Edit Store Discount' : 'New Store-Wide Discount' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition cursor-pointer"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submitOverall" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Type *</label>
                                <select v-model="overallForm.discount_type" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₦)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Value *</label>
                                <input v-model.number="overallForm.discount_value" type="number" required min="0.01" step="0.01" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Start Date & Time *</label>
                            <input v-model="overallForm.start_date_time" type="datetime-local" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">End Date & Time *</label>
                            <input v-model="overallForm.end_date_time" type="datetime-local" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div class="pt-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input v-model="overallForm.is_apply" type="checkbox" class="w-4 h-4 accent-emerald-500" />
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition">Rule applies to POS cart subtotal</span>
                            </label>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button type="button" onclick="document.getElementById('overall-discount-modal').close()" class="w-full py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition cursor-pointer">Cancel</button>
                            </form>
                            <button type="submit" :disabled="overallForm.processing" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40 cursor-pointer">
                                {{ overallForm.processing ? 'Saving...' : 'Save Rule' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL 2: ITEM-LEVEL DISCOUNT
             ═══════════════════════════════════════════════════════════════════ -->
        <dialog id="item-discount-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden rounded-xl">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ isEditingItem ? 'Edit Item Discount' : 'New Item Discount' }}</h3>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition cursor-pointer"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submitItemDiscount" class="space-y-4">
                        <!-- Select Target Item -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Search & Select Item *</label>
                            <input
                                v-model="itemSearchQuery"
                                type="text"
                                placeholder="Filter items by name or barcode..."
                                class="w-full mb-1.5 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none focus:border-indigo-500"
                            />
                            <select
                                v-model="itemDiscountForm.item_id"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition font-medium"
                            >
                                <option value="" disabled>-- Select Item --</option>
                                <option v-for="it in filteredItemsForSelect" :key="it.id" :value="it.id">
                                    {{ it.item_name }} (₦{{ Number(it.price).toLocaleString() }} / {{ it.unit_label || 'Unit' }})
                                </option>
                            </select>
                        </div>

                        <!-- Conflict Warning if item is in an active group -->
                        <div v-if="selectedItemInGroupWarning" class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300 flex items-start gap-1.5">
                            <span>⚠️</span>
                            <span>This item is currently in the <strong>"{{ selectedItemInGroupWarning }}"</strong> group. Setting an active item discount will override the group discount for this item.</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount Type *</label>
                                <select v-model="itemDiscountForm.discount_type" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₦)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount Value *</label>
                                <input v-model.number="itemDiscountForm.discount_value" type="number" required min="0.01" step="0.01" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-indigo-500 outline-none text-sm transition" />
                            </div>
                        </div>

                        <!-- Price Preview -->
                        <div v-if="selectedItemDetails" class="p-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Effective Unit Price:</span>
                            <div class="flex items-center gap-2">
                                <span class="line-through text-slate-400 font-mono">₦{{ Number(selectedItemDetails.price).toLocaleString() }}</span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold font-mono text-sm">
                                    ₦{{ computeDiscountedPreview(selectedItemDetails.price, itemDiscountForm.discount_type, itemDiscountForm.discount_value).toLocaleString() }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Start Date & Time *</label>
                            <input v-model="itemDiscountForm.starts_at" type="datetime-local" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">End Date & Time *</label>
                            <input v-model="itemDiscountForm.ends_at" type="datetime-local" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input v-model="itemDiscountForm.is_active" type="checkbox" class="w-4 h-4 accent-indigo-500" />
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition">Discount is Active</span>
                            </label>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <form method="dialog" class="flex-1">
                                <button type="button" onclick="document.getElementById('item-discount-modal').close()" class="w-full py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition cursor-pointer">Cancel</button>
                            </form>
                            <button type="submit" :disabled="itemDiscountForm.processing" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40 cursor-pointer">
                                {{ itemDiscountForm.processing ? 'Saving...' : 'Save Item Discount' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </dialog>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL 3: DISCOUNT GROUP (WITH ITEM ASSIGNMENT)
             ═══════════════════════════════════════════════════════════════════ -->
        <dialog id="group-discount-modal" class="bg-transparent backdrop:bg-slate-900/80 p-0 m-auto rounded-xl shadow-2xl backdrop-blur-sm open:animate-in open:zoom-in-95">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 w-full max-w-xl overflow-hidden rounded-xl flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 flex-shrink-0">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ isEditingGroup ? 'Edit Discount Group' : 'New Discount Group' }}</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Configure promotional group and assign member products</p>
                    </div>
                    <form method="dialog"><button class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition cursor-pointer"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-4">
                    <form id="group-discount-form" @submit.prevent="submitGroupDiscount" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Group Name *</label>
                            <input
                                v-model="groupForm.name"
                                type="text"
                                required
                                placeholder="e.g. Weekend Snacks Promo, Clearance Sale..."
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-purple-500 outline-none text-sm transition"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount Type *</label>
                                <select v-model="groupForm.discount_type" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₦)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount Value *</label>
                                <input v-model.number="groupForm.discount_value" type="number" required min="0.01" step="0.01" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-purple-500 outline-none text-sm transition" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Start Date & Time *</label>
                                <input v-model="groupForm.starts_at" type="datetime-local" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">End Date & Time *</label>
                                <input v-model="groupForm.ends_at" type="datetime-local" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 outline-none text-sm transition" />
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input v-model="groupForm.is_active" type="checkbox" class="w-4 h-4 accent-purple-500" />
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition">Discount Group is Active</span>
                            </label>
                        </div>

                        <!-- Item Assignment Section -->
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <label class="text-xs font-bold text-slate-900 dark:text-white">Assign Items ({{ groupForm.item_ids.length }} selected)</label>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Items assigned to this group will inherit the group discount</p>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <button type="button" @click="selectAllFilteredGroupItems" class="text-purple-600 hover:text-purple-500 font-semibold cursor-pointer">Select All Filtered</button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button" @click="deselectAllGroupItems" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 cursor-pointer">Clear</button>
                                </div>
                            </div>

                            <input
                                v-model="groupItemSearch"
                                type="text"
                                placeholder="Search products to assign..."
                                class="w-full mb-2 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs outline-none focus:border-purple-500"
                            />

                            <!-- Items Checklist Box -->
                            <div class="max-h-56 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-lg divide-y divide-slate-100 dark:divide-slate-700/50 bg-slate-50/50 dark:bg-slate-900/30">
                                <div
                                    v-for="it in filteredItemsForGroup"
                                    :key="it.id"
                                    @click="toggleGroupItemSelection(it.id)"
                                    class="p-2.5 flex items-center justify-between hover:bg-purple-500/5 transition cursor-pointer"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <input
                                            type="checkbox"
                                            :checked="groupForm.item_ids.includes(it.id)"
                                            @click.stop="toggleGroupItemSelection(it.id)"
                                            class="w-4 h-4 accent-purple-600 rounded cursor-pointer"
                                        />
                                        <div class="truncate text-xs">
                                            <div class="font-medium text-slate-900 dark:text-white truncate">{{ it.item_name }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">₦{{ Number(it.price).toLocaleString() }} / {{ it.unit_label || 'Unit' }}</div>
                                        </div>
                                    </div>

                                    <!-- Warning badges -->
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span v-if="hasActiveItemDiscount(it.id)" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800" title="Item discount takes priority over group discount">
                                            ⭐ Has Item Promo
                                        </span>
                                        <span v-else-if="isInAnotherActiveGroup(it.id, currentGroupDiscount?.id)" class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300" title="Will be moved to this group">
                                            ⚠️ In Other Group
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!filteredItemsForGroup.length" class="p-4 text-center text-xs text-slate-400">
                                    No products matching "{{ groupItemSearch }}".
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="px-6 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex gap-3 flex-shrink-0">
                    <form method="dialog" class="flex-1">
                        <button type="button" onclick="document.getElementById('group-discount-modal').close()" class="w-full py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-300 dark:hover:bg-slate-600 transition cursor-pointer">Cancel</button>
                    </form>
                    <button form="group-discount-form" type="submit" :disabled="groupForm.processing" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-40 cursor-pointer">
                        {{ groupForm.processing ? 'Saving...' : 'Save Group Discount' }}
                    </button>
                </div>
            </div>
        </dialog>
    </div>
</template>
