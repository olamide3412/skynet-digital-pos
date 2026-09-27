<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'

defineOptions({ layout: PosLayout })

const props = defineProps({
    settings:    Object,
    branchItems: { type: Array, default: () => [] },
    categories:  { type: Array, default: () => [] },
})

const { format } = useCurrency()

const form = useForm({
    _method:                       'PUT',
    business_name:                 props.settings.business_name ?? '',
    business_address:              props.settings.business_address ?? '',
    business_contact_number:       props.settings.business_contact_number ?? '',
    business_email:                props.settings.business_email ?? '',
    logo:                          null,
    sell_interface:                props.settings.sell_interface ?? 'classic',
    quick_access_mode:             props.settings.quick_access_mode ?? 'top_sellers',
    pinned_quick_access_items:     Array.isArray(props.settings.pinned_quick_access_items) ? [...props.settings.pinned_quick_access_items] : [],
    quick_access_category_ids:     Array.isArray(props.settings.quick_access_category_ids) ? [...props.settings.quick_access_category_ids] : [],
    is_price_editable:             !!props.settings.is_price_editable,
    is_qty_deduction:              !!props.settings.is_qty_deduction,
    is_offline_qty_deduction:      !!props.settings.is_offline_qty_deduction,
    out_of_stock:                  props.settings.out_of_stock ?? 25,
    is_check_expiration:           !!props.settings.is_check_expiration,
    batch_tracking_enabled:        !!props.settings.batch_tracking_enabled,
    expiry_alert_days:             props.settings.expiry_alert_days ?? 30,
    is_imei_enabled:               !!props.settings.is_imei_enabled,
    is_grid_separate_rows:         !!props.settings.is_grid_separate_rows,
    is_cart_auto_scroll_highlight: props.settings.is_cart_auto_scroll_highlight !== false && props.settings.is_cart_auto_scroll_highlight !== 0,
    is_show_buy_price:             !!props.settings.is_show_buy_price,
    is_use_profit_percentage:      !!props.settings.is_use_profit_percentage,
    is_tax_enabled:                !!props.settings.is_tax_enabled,
    tax_percentage:                props.settings.tax_percentage ?? 7.5,
    wholesale_profit_percent:      props.settings.wholesale_profit_percent ?? 10,
    consumer_profit_percent:       props.settings.consumer_profit_percent ?? 15,
    business_sector:               props.settings.business_sector ?? 'commerce',
    receipt_paper_size:            props.settings.receipt_paper_size ?? '80mm',
    receipt_printer_name:          props.settings.receipt_printer_name ?? 'Default POS Printer',
    printer_type:                  props.settings.printer_type ?? 'thermal_80mm',
    printer_connection:            props.settings.printer_connection ?? 'kiosk_direct',
    printer_ip_address:            props.settings.printer_ip_address ?? '',
    auto_print_receipt:            !!props.settings.auto_print_receipt,
    show_receipt_preview:          props.settings.show_receipt_preview !== false && props.settings.show_receipt_preview !== 0,
})

// Pinned Items Search State
const itemSearchQuery = ref('')
const isSearchDropdownOpen = ref(false)

const filteredBranchItems = computed(() => {
    const q = itemSearchQuery.value.trim().toLowerCase()
    const pinned = new Set(form.pinned_quick_access_items)
    return (props.branchItems || []).filter(item => {
        if (pinned.has(item.id)) return false
        if (!q) return true
        return (item.item_name || '').toLowerCase().includes(q) ||
               (item.barcode_number || '').includes(q)
    }).slice(0, 10)
})

function getItemById(id) {
    return (props.branchItems || []).find(i => i.id === id) || {
        id,
        item_name: `Item #${id}`,
        price: 0,
        front_store_qty: 0,
    }
}

function pinItem(item) {
    if (form.pinned_quick_access_items.length >= 10) return
    if (!form.pinned_quick_access_items.includes(item.id)) {
        form.pinned_quick_access_items.push(item.id)
    }
    itemSearchQuery.value = ''
    isSearchDropdownOpen.value = false
}

function unpinItem(id) {
    form.pinned_quick_access_items = form.pinned_quick_access_items.filter(itemId => itemId !== id)
}

function movePinnedItem(index, direction) {
    const newIndex = index + direction
    if (newIndex < 0 || newIndex >= form.pinned_quick_access_items.length) return
    const arr = [...form.pinned_quick_access_items]
    const temp = arr[index]
    arr[index] = arr[newIndex]
    arr[newIndex] = temp
    form.pinned_quick_access_items = arr
}

function toggleCategory(catId) {
    const exists = form.quick_access_category_ids.includes(catId)
    if (exists) {
        form.quick_access_category_ids = form.quick_access_category_ids.filter(id => id !== catId)
    } else if (form.quick_access_category_ids.length < 6) {
        form.quick_access_category_ids.push(catId)
    }
}

function submit() {
    form.post(route('pos.settings.update'), {
        forceFormData: true,
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">POS Settings</h1>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <form @submit.prevent="submit" class="max-w-2xl mx-auto space-y-5">

                <!-- Business Info -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">Business & Branch Branding</h2>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Business Name *</label>
                        <input v-model="form.business_name" type="text" required maxlength="100"
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        <p v-if="form.errors.business_name" class="text-red-500 text-xs mt-1">{{ form.errors.business_name }}</p>
                    </div>

                    <!-- Branch Logo Upload -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Logo (Displays on Login Page & Receipts)</label>
                        <div class="flex items-center gap-4">
                            <div v-if="settings.logo_url" class="w-14 h-14 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 overflow-hidden flex items-center justify-center flex-shrink-0">
                                <img :src="settings.logo_url" class="w-full h-full object-contain" alt="Branch Logo" />
                            </div>
                            <div class="flex-1">
                                <input
                                    type="file"
                                    accept="image/*"
                                    @input="form.logo = $event.target.files[0]"
                                    class="w-full text-slate-600 dark:text-slate-300 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-200 dark:file:bg-slate-700 file:text-slate-800 dark:file:text-white hover:file:bg-slate-300 dark:hover:file:bg-slate-600 outline-none text-xs transition cursor-pointer"
                                />
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Uploaded logo will automatically customize your branch login screen and receipts.</p>
                                <p v-if="form.errors.logo" class="text-red-500 text-xs mt-1">{{ form.errors.logo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Contact Number</label>
                            <input v-model="form.business_contact_number" type="text"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email</label>
                            <input v-model="form.business_email" type="email"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Address</label>
                        <input v-model="form.business_address" type="text" maxlength="255"
                            class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                    </div>
                </div>

                <!-- ══ RECEIPT & PRINTER SETTINGS BANNER ═════════════════════ -->
                <div class="bg-emerald-50 dark:bg-emerald-950/40 rounded-xl border border-emerald-300 dark:border-emerald-700/60 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg flex-shrink-0 shadow-sm">
                            🖨️
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Terminal & Printer Configuration</h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                Configure local thermal 80mm / A4 receipt printers, network IP addresses, and test zero-dialogue printing.
                            </p>
                        </div>
                    </div>
                    <Link :href="route('pos.printer-setup')"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-1.5 flex-shrink-0 cursor-pointer">
                        <span>Open Printer Setup</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </Link>
                </div>

                <!-- ══ QUICK ACCESS WIDGET (CHECKOUT EMPTY SCREEN) ═══════════════ -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>⚡</span>
                            <span>Quick Access Shortcut Panel (Checkout Screen)</span>
                        </h2>
                        <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500">Max 10 Items Cap</span>
                    </div>

                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Populates the checkout screen with a fast, capped list of shortcut items before any search/barcode scan happens. Choose only one active mode for this branch:
                    </p>

                    <!-- Mode Select Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Mode 1: Top Sellers (Default) -->
                        <label
                            @click="form.quick_access_mode = 'top_sellers'"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-start gap-2.5',
                                form.quick_access_mode === 'top_sellers'
                                    ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-500 dark:border-emerald-400'
                                    : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            ]"
                        >
                            <input type="radio" v-model="form.quick_access_mode" value="top_sellers" class="mt-0.5 accent-emerald-600" />
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <span>⚡</span> Top Sellers (Rolling 7 Days)
                                    <span class="text-[9px] bg-emerald-500 text-white font-bold px-1.5 py-0.2 rounded-full">Default</span>
                                </span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Automatically ranks and shows this branch's most purchased items over the past 7 days.
                                </p>
                            </div>
                        </label>

                        <!-- Mode 2: Recently Sold -->
                        <label
                            @click="form.quick_access_mode = 'recently_sold'"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-start gap-2.5',
                                form.quick_access_mode === 'recently_sold'
                                    ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-500 dark:border-emerald-400'
                                    : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            ]"
                        >
                            <input type="radio" v-model="form.quick_access_mode" value="recently_sold" class="mt-0.5 accent-emerald-600" />
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <span>🕒</span> Recently Sold Items
                                </span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Live chronological stream of the latest items checked out at this branch.
                                </p>
                            </div>
                        </label>

                        <!-- Mode 3: Pinned Items -->
                        <label
                            @click="form.quick_access_mode = 'pinned_items'"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-start gap-2.5',
                                form.quick_access_mode === 'pinned_items'
                                    ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-500 dark:border-emerald-400'
                                    : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            ]"
                        >
                            <input type="radio" v-model="form.quick_access_mode" value="pinned_items" class="mt-0.5 accent-emerald-600" />
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <span>📌</span> Branch Pinned Items
                                </span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Curate and arrange a custom hand-picked list of high-speed items (up to 10).
                                </p>
                            </div>
                        </label>

                        <!-- Mode 4: Category Shortcuts -->
                        <label
                            @click="form.quick_access_mode = 'category_shortcuts'"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-start gap-2.5',
                                form.quick_access_mode === 'category_shortcuts'
                                    ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-500 dark:border-emerald-400'
                                    : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            ]"
                        >
                            <input type="radio" v-model="form.quick_access_mode" value="category_shortcuts" class="mt-0.5 accent-emerald-600" />
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <span>🏷️</span> Category Shortcuts
                                </span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Shows category shortcut pills with mini quick-item grids for each category.
                                </p>
                            </div>
                        </label>

                        <!-- Mode 5: None / Disabled -->
                        <label
                            @click="form.quick_access_mode = 'none'"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-start gap-2.5 sm:col-span-2',
                                form.quick_access_mode === 'none'
                                    ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-500 dark:border-emerald-400'
                                    : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            ]"
                        >
                            <input type="radio" v-model="form.quick_access_mode" value="none" class="mt-0.5 accent-emerald-600" />
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <span>🚫</span> Disabled (Classic Search-Only Placeholder)
                                </span>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Shows the clean standard search prompt without shortcut items.
                                </p>
                            </div>
                        </label>
                    </div>

                    <!-- ── Pinned Items Manager Sub-panel ── -->
                    <div v-if="form.quick_access_mode === 'pinned_items'" class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <span>📌</span> Manage Pinned Shortcut Items
                            </span>
                            <span
                                :class="form.pinned_quick_access_items.length >= 10 ? 'text-amber-600 dark:text-amber-400 font-black' : 'text-slate-500 dark:text-slate-400'"
                                class="text-xs font-mono"
                            >
                                {{ form.pinned_quick_access_items.length }} / 10 Pinned (Max 10)
                            </span>
                        </div>

                        <!-- Item Search Input to Add -->
                        <div v-if="form.pinned_quick_access_items.length < 10" class="relative">
                            <input
                                v-model="itemSearchQuery"
                                @focus="isSearchDropdownOpen = true"
                                type="text"
                                placeholder="Type item name or barcode to add to shortcuts..."
                                class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none transition"
                            />
                            
                            <!-- Search Dropdown Results -->
                            <div
                                v-if="isSearchDropdownOpen && filteredBranchItems.length > 0"
                                class="absolute top-full left-0 right-0 mt-1 z-30 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl max-h-48 overflow-y-auto p-1 divide-y divide-slate-100 dark:divide-slate-700/60"
                            >
                                <button
                                    v-for="item in filteredBranchItems"
                                    :key="item.id"
                                    type="button"
                                    @click="pinItem(item)"
                                    class="w-full p-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/70 rounded-lg transition flex items-center justify-between cursor-pointer"
                                >
                                    <div class="min-w-0 pr-2">
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.item_name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">#{{ item.barcode_number || 'No Barcode' }} • Stock: {{ item.front_store_qty }}</div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="text-xs font-black text-emerald-600 dark:text-emerald-400">{{ format(item.price) }}</span>
                                        <span class="px-2 py-0.5 bg-emerald-600 text-white rounded text-[10px] font-bold">+ Pin</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Pinned Items List -->
                        <div v-if="form.pinned_quick_access_items.length > 0" class="space-y-1.5">
                            <div
                                v-for="(itemId, idx) in form.pinned_quick_access_items"
                                :key="itemId"
                                class="flex items-center justify-between p-2.5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-bold flex items-center justify-center font-mono">
                                        {{ idx + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate leading-tight">
                                            {{ getItemById(itemId).item_name }}
                                        </div>
                                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">
                                            {{ format(getItemById(itemId).price) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <!-- Move Up -->
                                    <button
                                        type="button"
                                        :disabled="idx === 0"
                                        @click="movePinnedItem(idx, -1)"
                                        class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 disabled:opacity-30 cursor-pointer"
                                        title="Move Up"
                                    >
                                        ▲
                                    </button>
                                    <!-- Move Down -->
                                    <button
                                        type="button"
                                        :disabled="idx === form.pinned_quick_access_items.length - 1"
                                        @click="movePinnedItem(idx, 1)"
                                        class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 disabled:opacity-30 cursor-pointer"
                                        title="Move Down"
                                    >
                                        ▼
                                    </button>
                                    <!-- Remove -->
                                    <button
                                        type="button"
                                        @click="unpinItem(itemId)"
                                        class="p-1 rounded text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition cursor-pointer ml-1"
                                        title="Remove shortcut"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-slate-400 dark:text-slate-500 text-center py-2">
                            No shortcut items pinned yet. Use the search bar above to pin items.
                        </p>
                    </div>

                    <!-- ── Category Shortcuts Picker Sub-panel ── -->
                    <div v-if="form.quick_access_mode === 'category_shortcuts'" class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <span>🏷️</span> Select Categories for Shortcut Tabs
                            </span>
                            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">
                                {{ form.quick_access_category_ids.length }} / 6 Selected (Max 6)
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Leave empty to automatically display top categories with available stock.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                type="button"
                                @click="toggleCategory(cat.id)"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                    form.quick_access_category_ids.includes(cat.id)
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'
                                ]"
                            >
                                <span>{{ cat.name }}</span>
                                <span v-if="form.quick_access_category_ids.includes(cat.id)" class="text-[10px]">✓</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- POS Behaviour -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-xs">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">POS Behaviour & Pricing Automation</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">POS Interface Style</label>
                            <select v-model="form.sell_interface"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition">
                                <option value="classic" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Classic Table View</option>
                                <option value="gallery" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">Visual Grid View</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Out of Stock Alert Threshold</label>
                            <input v-model.number="form.out_of_stock" type="number" min="0" required
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                        </div>
                    </div>

                    <!-- Automation Toggles -->
                    <div class="space-y-3 pt-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_use_profit_percentage" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Auto-Calculate Selling Prices from Cost</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Automatically computes retail & wholesale prices whenever buy price changes</p>
                            </div>
                        </label>

                        <div v-if="form.is_use_profit_percentage" class="grid grid-cols-2 gap-4 p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Consumer Retail Margin (%)</label>
                                <input v-model.number="form.consumer_profit_percent" type="number" min="0" step="0.1" required
                                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Wholesale Margin (%)</label>
                                <input v-model.number="form.wholesale_profit_percent" type="number" min="0" step="0.1" required
                                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            </div>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_price_editable" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Allow Cashier Price Override</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Cashiers can edit unit selling price directly on the checkout cart</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_qty_deduction" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Enable Auto Stock Deduction</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Deducts inventory levels automatically when sales are completed</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_offline_qty_deduction" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Local Quantity Deduction when Offline</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Validate available stock on Payment Modal and deduct local inventory when offline (uncheck to skip offline stock blocking)</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_check_expiration" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Enable Expiry Date Alerts</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Warns cashiers if an item is expiring soon during checkout</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_imei_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Enable IMEI / Device ID Tracking</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Allows unit-level serial & IMEI tracking for gadgets, phones, laptops, and electronics</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.batch_tracking_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Enable Item Expiry & Batch Tracking</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Enables batch/lot recording, FEFO checkout deduction, and expiry alert overview for this branch</p>
                            </div>
                        </label>

                        <div v-if="form.batch_tracking_enabled" class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Expiry Alert Threshold (Days before expiry)
                            </label>
                            <input
                                v-model.number="form.expiry_alert_days"
                                type="number"
                                min="1"
                                max="365"
                                required
                                class="w-full sm:w-48 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono"
                            />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Batches expiring within this number of days (default 30) will trigger notifications and appear in the Expiring Soon filter.
                            </p>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_grid_separate_rows" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Separate Rows for Grid View Selections (Eateries & Restaurants)</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">When clicking an item in Grid View, add it as a new separate line in the cart instead of incrementing existing quantity (e.g. for individual portions/diners)</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.is_cart_auto_scroll_highlight" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <div>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Auto-Scroll & 1-Second Highlight on Added Cart Items</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Automatically scroll down to focus on added/updated cart items and flash a 1-second pulse animation (from search bar, barcode, or grid)</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl shadow-md shadow-emerald-900/20 transition disabled:opacity-40 cursor-pointer">
                        {{ form.processing ? 'Saving Settings…' : 'Save POS Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
