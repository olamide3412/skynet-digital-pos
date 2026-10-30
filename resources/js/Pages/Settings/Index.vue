<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import { BrowserQRCodeSvgWriter, EncodeHintType } from '@zxing/library'

defineOptions({ layout: PosLayout })

const props = defineProps({
    settings:              { type: Object, required: true },
    laundry_settings:      { type: Object, default: () => ({}) },
    branchItems:           { type: Array, default: () => [] },
    categories:            { type: Array, default: () => [] },
    is_accounting_enabled: { type: Boolean, default: false },
    initial_tab:           { type: String, default: 'general' },
    can_manage_laundry:    { type: Boolean, default: true },
    can_manage_pos:        { type: Boolean, default: true },
})

const { format } = useCurrency()

const isLaundryEnabled = computed(() => Boolean(props.settings?.laundry_enabled))
const isPosEnabled = computed(() => props.settings?.pos_enabled !== false && props.settings?.pos_enabled !== 0)
const isMixedStore = computed(() => isLaundryEnabled.value && isPosEnabled.value)

// ── Tab Management ─────────────────────────────────────────────────────────────
const availableTabs = computed(() => {
    const tabs = [
        {
            id: 'general',
            name: 'General Settings',
            shortName: 'General',
            icon: 'building',
            description: 'Store identity, branding, logo, cash till accountability, and core interface',
        },
    ]

    if (isPosEnabled.value) {
        tabs.push({
            id: 'pos',
            name: 'Retail & POS Settings',
            shortName: 'Retail & POS',
            icon: 'shopping-cart',
            description: 'Terminal layout, barcode scanning, item shortcuts, margin rules, and hardware',
        })
    }

    if (isLaundryEnabled.value) {
        tabs.push({
            id: 'laundry',
            name: 'Laundry Module Settings',
            shortName: 'Laundry',
            icon: 'sparkles',
            description: 'Turnaround periods, ticket footer, WhatsApp message alerts, and staff policies',
        })
    }

    if (props.is_accounting_enabled) {
        tabs.push({
            id: 'accounting',
            name: 'Accounting & VAT',
            shortName: 'Accounting',
            icon: 'calculator',
            description: 'Automated VAT rates, ledger synchronization, and tax calculations',
        })
    }

    return tabs
})

// Initialize active tab from URL query param, prop, or fallback
const getInitialTab = () => {
    const urlParams = new URLSearchParams(window.location.search)
    const paramTab = urlParams.get('tab')
    if (paramTab && availableTabs.value.some(t => t.id === paramTab)) {
        return paramTab
    }
    if (props.initial_tab && availableTabs.value.some(t => t.id === props.initial_tab)) {
        return props.initial_tab
    }
    return 'general'
}

const activeTab = ref(getInitialTab())

const selectTab = (tabId) => {
    activeTab.value = tabId
    const url = new URL(window.location.href)
    url.searchParams.set('tab', tabId)
    window.history.replaceState({}, '', url.toString())
}

// ── Form State ────────────────────────────────────────────────────────────────
const form = useForm({
    _method:                       'PUT',
    active_tab:                    activeTab.value,

    // ── General / Shared ──
    business_name:                 props.settings.business_name ?? '',
    business_address:              props.settings.business_address ?? '',
    business_contact_number:       props.settings.business_contact_number ?? '',
    business_email:                props.settings.business_email ?? '',
    logo:                          null,
    business_sector:               props.settings.business_sector ?? 'commerce',
    default_interface:             props.settings.default_interface ?? 'pos',
    require_till_opening:          props.settings.require_till_opening !== false && props.settings.require_till_opening !== 0,
    print_till_slip:               Boolean(props.settings.print_till_slip),

    // ── POS Terminal & Selling ──
    sell_interface:                props.settings.sell_interface ?? 'classic',
    quick_access_mode:             props.settings.quick_access_mode ?? 'top_sellers',
    pinned_quick_access_items:     Array.isArray(props.settings.pinned_quick_access_items) ? [...props.settings.pinned_quick_access_items] : [],
    quick_access_category_ids:     Array.isArray(props.settings.quick_access_category_ids) ? [...props.settings.quick_access_category_ids] : [],
    is_grid_separate_rows:         Boolean(props.settings.is_grid_separate_rows),
    is_cart_auto_scroll_highlight: props.settings.is_cart_auto_scroll_highlight !== false && props.settings.is_cart_auto_scroll_highlight !== 0,
    is_camera_scanner_enabled:     props.settings.is_camera_scanner_enabled !== false && props.settings.is_camera_scanner_enabled !== 0,

    // ── Pricing & Margins ──
    is_price_editable:             Boolean(props.settings.is_price_editable),
    is_show_buy_price:             Boolean(props.settings.is_show_buy_price),
    is_qty_deduction:              Boolean(props.settings.is_qty_deduction),
    is_offline_qty_deduction:      Boolean(props.settings.is_offline_qty_deduction),
    out_of_stock:                  props.settings.out_of_stock ?? 25,
    is_use_profit_percentage:      Boolean(props.settings.is_use_profit_percentage),
    wholesale_profit_percent:      props.settings.wholesale_profit_percent ?? 10,
    consumer_profit_percent:       props.settings.consumer_profit_percent ?? 15,

    // ── Expiry & Tracking ──
    is_check_expiration:           Boolean(props.settings.is_check_expiration),
    batch_tracking_enabled:        Boolean(props.settings.batch_tracking_enabled),
    expiry_alert_days:             props.settings.expiry_alert_days ?? 30,
    is_imei_enabled:               Boolean(props.settings.is_imei_enabled),

    // ── Hardware & Receipts ──
    receipt_paper_size:            props.settings.receipt_paper_size ?? '80mm',
    receipt_printer_name:          props.settings.receipt_printer_name ?? 'Default POS Printer',
    printer_type:                  props.settings.printer_type ?? 'thermal_80mm',
    printer_connection:            props.settings.printer_connection ?? 'kiosk_direct',
    printer_ip_address:            props.settings.printer_ip_address ?? '',
    auto_print_receipt:            Boolean(props.settings.auto_print_receipt),
    show_receipt_preview:          props.settings.show_receipt_preview !== false && props.settings.show_receipt_preview !== 0,

    // ── Public Storefront Catalog ──
    public_catalog_enabled:        Boolean(props.settings.public_catalog_enabled),

    // ── Laundry Settings ──
    default_turnaround_hours:      props.laundry_settings?.default_turnaround_hours ?? 48,
    unclaimed_after_days:          props.laundry_settings?.unclaimed_after_days ?? 14,
    order_prefix:                  props.laundry_settings?.order_prefix ?? 'LDY-',
    whatsapp_template:             props.laundry_settings?.whatsapp_template ?? 'Hello {customer_name}, your laundry order #{order_number} is ready for pickup at {branch_name}! Outstanding balance: {balance}. Thank you!',
    default_country_code:          props.laundry_settings?.default_country_code ?? '234',
    receipt_footer:                props.laundry_settings?.receipt_footer ?? 'Thank you for choosing us! Please present this ticket at pickup.',
    allow_price_override:          Boolean(props.laundry_settings?.allow_price_override),
    allow_unpaid_pickup:           Boolean(props.laundry_settings?.allow_unpaid_pickup),

    // ── Accounting & Tax ──
    is_tax_enabled:                Boolean(props.settings.is_tax_enabled),
    tax_percentage:                props.settings.tax_percentage ?? 7.5,
    is_accounting_vat_enabled:     Boolean(props.settings.is_accounting_vat_enabled),
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

function toggleCategory(categoryId) {
    const idx = form.quick_access_category_ids.indexOf(categoryId)
    if (idx >= 0) {
        form.quick_access_category_ids.splice(idx, 1)
    } else {
        if (form.quick_access_category_ids.length >= 6) return
        form.quick_access_category_ids.push(categoryId)
    }
}

// ── Public Storefront Catalog QR Code ──────────────────────────────────────────
const publicCatalogUrl = computed(() => {
    return props.settings.public_catalog_url || (window.location.origin + '/catalog/' + (props.settings.branch_slug || ''))
})

const qrContainerRef = ref(null)
const copySuccess = ref(false)

function copyCatalogLink() {
    if (!publicCatalogUrl.value) return
    navigator.clipboard.writeText(publicCatalogUrl.value).then(() => {
        copySuccess.value = true
        setTimeout(() => copySuccess.value = false, 2500)
    })
}

function renderQr() {
    if (!qrContainerRef.value || !form.public_catalog_enabled || !publicCatalogUrl.value) return
    qrContainerRef.value.innerHTML = ''
    try {
        const writer = new BrowserQRCodeSvgWriter()
        const hints = new Map()
        hints.set(EncodeHintType.MARGIN, 1)
        const size = 200
        const svg = writer.write(publicCatalogUrl.value, size, size, hints)
        svg.setAttribute('viewBox', `0 0 ${size} ${size}`)
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet')
        svg.setAttribute('class', 'w-full h-full block')
        qrContainerRef.value.appendChild(svg)
    } catch (e) {
        console.error('Failed to generate QR Code:', e)
    }
}

watch(() => [form.public_catalog_enabled, publicCatalogUrl.value, activeTab.value], () => {
    if (form.public_catalog_enabled && activeTab.value === 'pos') {
        nextTick(() => renderQr())
    }
})

onMounted(() => {
    if (form.public_catalog_enabled && activeTab.value === 'pos') {
        nextTick(() => renderQr())
    }
})

function downloadQrPng() {
    const svgElement = qrContainerRef.value?.querySelector('svg')
    if (!svgElement) return
    const svgData = new XMLSerializer().serializeToString(svgElement)
    const canvas = document.createElement('canvas')
    canvas.width = 600
    canvas.height = 600
    const ctx = canvas.getContext('2d')
    const img = new Image()
    img.onload = () => {
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, 600, 600)
        ctx.drawImage(img, 30, 30, 540, 540)
        const pngUrl = canvas.toDataURL('image/png')
        const downloadLink = document.createElement('a')
        downloadLink.href = pngUrl
        downloadLink.download = `${props.settings.branch_slug || 'branch'}-catalog-qr.png`
        document.body.appendChild(downloadLink)
        downloadLink.click()
        document.body.removeChild(downloadLink)
    }
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)))
}

function printQrPoster() {
    const svgElement = qrContainerRef.value?.querySelector('svg')
    if (!svgElement) return
    const svgData = new XMLSerializer().serializeToString(svgElement)
    const printWindow = window.open('', '_blank', 'width=750,height=900')
    if (!printWindow) return

    const logoHtml = props.settings.logo_url 
        ? `<img src="${props.settings.logo_url}" style="max-height: 60px; max-width: 140px; margin-bottom: 12px; object-fit: contain;">`
        : ''

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Scan for Price List — ${props.settings.business_name || 'Branch'}</title>
            <style>
                @page { size: A4 portrait; margin: 15mm; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    text-align: center;
                    padding: 30px 15px;
                    color: #0f172a;
                    margin: 0;
                }
                .card {
                    border: 2px dashed #cbd5e1;
                    border-radius: 28px;
                    padding: 40px 30px;
                    max-width: 520px;
                    margin: 0 auto;
                }
                h1 { font-size: 24px; margin: 0 0 6px; font-weight: 800; color: #0f172a; }
                p.sub { font-size: 13px; color: #475569; margin: 0 0 24px; line-height: 1.4; }
                .qr-box {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 20px;
                    padding: 20px;
                    display: inline-block;
                    margin-bottom: 20px;
                }
                .qr-box svg { width: 220px; height: 220px; display: block; }
                .prompt { font-size: 15px; font-weight: 800; color: #059669; text-transform: uppercase; letter-spacing: 0.05em; }
                .url { margin-top: 6px; font-size: 11px; font-family: monospace; color: #64748b; word-break: break-all; }
                .address { margin-top: 20px; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 14px; }
            </style>
        </head>
        <body>
            <div class="card">
                ${logoHtml}
                <h1>${props.settings.business_name || 'Our Shop'}</h1>
                <p class="sub">View our live prices and available products right on your phone!</p>
                <div class="qr-box">
                    ${svgData}
                </div>
                <div class="prompt">Scan with Camera to Browse</div>
                <div class="url">${publicCatalogUrl.value}</div>
                ${props.settings.business_address ? `<div class="address">${props.settings.business_address}</div>` : ''}
            </div>
            <script>
                window.onload = function() { window.print(); }
            <\/script>
        </body>
        </html>
    `)
    printWindow.document.close()
}

// ── Save Function ─────────────────────────────────────────────────────────────
const submitTab = (tabId) => {
    form.active_tab = tabId || activeTab.value
    form.post(route('pos.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
    })
}

// Sample live preview for Laundry WhatsApp Template
const liveWhatsAppPreview = computed(() => {
    const raw = form.whatsapp_template || ''
    return raw
        .replace('{customer_name}', 'John Doe')
        .replace('{order_number}', `${form.order_prefix || 'LDY-'}0042`)
        .replace('{branch_name}', form.business_name || 'Our Branch')
        .replace('{balance}', '₦0.00')
})
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        
        <!-- Header -->
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 max-w-5xl mx-auto">
                <div>
                    <h1 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⚙️</span>
                        <span>Branch & System Settings</span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Configure business profile, cash drawer accountability, POS terminal, and operational modules.
                    </p>
                </div>

                <!-- Active tab save indicator button -->
                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <button
                        @click="submitTab(activeTab)"
                        :disabled="form.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-emerald-600/20 cursor-pointer disabled:opacity-50 active:scale-95"
                    >
                        <svg v-if="form.processing" class="w-3.5 h-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ form.processing ? 'Saving...' : 'Save ' + (availableTabs.find(t => t.id === activeTab)?.shortName || 'Settings') }}</span>
                    </button>
                </div>
            </div>

            <!-- Tab Navigation Bar (Scrollable on Mobile) -->
            <div class="max-w-5xl mx-auto mt-4 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
                <button
                    v-for="tab in availableTabs"
                    :key="tab.id"
                    @click="selectTab(tab.id)"
                    type="button"
                    :class="[
                        'px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition flex items-center gap-2 cursor-pointer shrink-0 border select-none',
                        activeTab === tab.id
                            ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25'
                            : 'bg-slate-100 dark:bg-slate-700/50 hover:bg-slate-200/80 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200/80 dark:border-slate-700'
                    ]"
                >
                    <!-- Tab Icons -->
                    <span v-if="tab.id === 'general'">🏢</span>
                    <span v-else-if="tab.id === 'pos'">🛒</span>
                    <span v-else-if="tab.id === 'laundry'">🧺</span>
                    <span v-else-if="tab.id === 'accounting'">🧾</span>

                    <span>{{ tab.name }}</span>

                    <!-- Mixed store / badge indicators -->
                    <span v-if="tab.id === 'laundry' && isLaundryEnabled" class="text-[9px] px-1.5 py-0.2 rounded-full font-black uppercase tracking-wider bg-white/20 text-white">
                        Active
                    </span>
                </button>
            </div>
        </div>

        <!-- Scrollable Tab Content Container -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto">

                <!-- ════════════════════════════════════════════════════════════════
                     TAB 1: GENERAL SETTINGS (Shared Business, Branding & Till)
                     ════════════════════════════════════════════════════════════════ -->
                <div v-show="activeTab === 'general'" class="space-y-6">

                    <!-- Business Profile & Logo Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-5 shadow-xs">
                        <div class="border-b border-slate-200 dark:border-slate-700 pb-3 flex items-center justify-between">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <span>🏢</span>
                                    <span>Business Profile & Branch Branding</span>
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Shared location details used across customer receipts, login screens, and reports.
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Business / Branch Name *</label>
                            <input v-model="form.business_name" type="text" required maxlength="100"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-bold" />
                            <p v-if="form.errors.business_name" class="text-red-500 text-xs mt-1">{{ form.errors.business_name }}</p>
                        </div>

                        <!-- Branch Logo Upload & Preview -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Branch Logo (Displays on Login, POS & Laundry Receipts)</label>
                            <div class="flex items-center gap-4 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                                <div v-if="settings.logo_url" class="w-16 h-16 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 overflow-hidden flex items-center justify-center flex-shrink-0 p-1 shadow-2xs">
                                    <img :src="settings.logo_url" class="w-full h-full object-contain" alt="Branch Logo" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @input="form.logo = $event.target.files[0]"
                                        class="w-full text-slate-600 dark:text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-200 dark:file:bg-slate-700 file:text-slate-800 dark:file:text-white hover:file:bg-slate-300 dark:hover:file:bg-slate-600 outline-none text-xs transition cursor-pointer"
                                    />
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Recommended format: Square or landscape transparent PNG / JPG under 2MB.</p>
                                    <p v-if="form.errors.logo" class="text-red-500 text-xs mt-1">{{ form.errors.logo }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Official Contact Phone</label>
                                <input v-model="form.business_contact_number" type="text"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Official Contact Email</label>
                                <input v-model="form.business_email" type="email"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Physical Business Address</label>
                            <input v-model="form.business_address" type="text" maxlength="255"
                                class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-sm transition" />
                        </div>
                    </div>

                    <!-- Cash Till Shift & Cash Drawer Accountability (Affects all sales & laundry) -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <div class="flex items-start gap-3 border-b border-slate-200 dark:border-slate-700 pb-3">
                            <span class="text-2xl">💰</span>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Till Shift & Cash Drawer Accountability</h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Control mandatory opening float declaration, cash drawer audit, and shift reconciliation across POS sales and Laundry intake deposits.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 pt-1">
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition cursor-pointer">
                                <input type="checkbox" v-model="form.require_till_opening" class="w-4 h-4 mt-0.5 accent-emerald-500 rounded cursor-pointer shrink-0" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Require Cash Float Before Ringing Sales or Laundry Orders</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                        When enabled, cashiers must declare their opening drawer balance before processing transactions. Prevents unaccounted register shortages.
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition cursor-pointer">
                                <input type="checkbox" v-model="form.print_till_slip" class="w-4 h-4 mt-0.5 accent-emerald-500 rounded cursor-pointer shrink-0" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Print Shift Balancing Slip on Till Close</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                        Generates a printable 80mm cash drawer reconciliation slip when a shift ends showing expected cash, actual declared cash, and variances.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Store Mode & Landing Interface Preference -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <div class="border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <span>⚙️</span>
                                <span>Branch Operating Sector & Interface Preference</span>
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Set business sector classification and choose default landing interface after staff sign in.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Business Sector</label>
                                <select v-model="form.business_sector" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-semibold transition">
                                    <option value="commerce">General Commerce / Retail Store</option>
                                    <option value="health">Pharmacy / Healthcare & Chemist</option>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1">Health mode optimizes expiry warnings and batch tracking.</p>
                            </div>

                            <div v-if="isMixedStore">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Default Staff Landing Interface</label>
                                <select v-model="form.default_interface" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-semibold transition">
                                    <option value="pos">Retail POS Terminal</option>
                                    <option value="laundry">Laundry Operations Hub</option>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1">Attendants land on this screen after logging in (they can switch anytime from the sidebar).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Bar for General Settings -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            @click="submitTab('general')"
                            :disabled="form.processing"
                            type="button"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition flex items-center gap-2 shadow-md shadow-emerald-600/20 cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ form.processing ? 'Saving...' : 'Save General Settings' }}</span>
                        </button>
                    </div>
                </div>


                <!-- ════════════════════════════════════════════════════════════════
                     TAB 2: RETAIL & POS SETTINGS (Terminal, Scanning, Hardware, Margins)
                     ════════════════════════════════════════════════════════════════ -->
                <div v-show="activeTab === 'pos'" class="space-y-6">

                    <!-- Receipt Printer Banner -->
                    <div class="bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-300 dark:border-emerald-700/60 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                        <div class="flex items-center gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg flex-shrink-0 shadow-sm">
                                🖨️
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Printer Setup & Zero-Dialogue Printing</h3>
                                <p class="text-xs text-slate-600 dark:text-slate-400">
                                    Configure 80mm/58mm thermal receipt printers, network IP addresses, and test thermal slips.
                                </p>
                            </div>
                        </div>
                        <Link :href="route('pos.printer-setup')"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5 flex-shrink-0 cursor-pointer">
                            <span>Open Printer Setup</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </div>

                    <!-- POS Terminal Behaviour & Screen Mode -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                            POS Terminal Screen & Barcode Scanning
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Selling Interface</label>
                                <select v-model="form.sell_interface" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-semibold">
                                    <option value="classic">Classic Table View</option>
                                    <option value="gallery">Touch Gallery / Grid View</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Low Stock Warning Threshold</label>
                                <input v-model.number="form.out_of_stock" type="number" min="0"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-bold" />
                            </div>
                        </div>

                        <div class="space-y-3 pt-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_camera_scanner_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Enable Camera Barcode Scanner on Checkout</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Shows a camera scan button on the POS selling page for cashiers using phones or tablets.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_grid_separate_rows" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Separate Cart Rows for Scanned Items</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Add identical scanned items as individual cart rows instead of stacking quantity.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_cart_auto_scroll_highlight" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Auto-Scroll Cart to Scanned Item</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Smoothly scrolls to and pulses the active cart row whenever a barcode is scanned.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Quick Access Shortcuts Panel -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <span>⚡</span>
                                <span>Quick Access Shortcut Panel (Checkout Screen)</span>
                            </h2>
                            <span class="text-[10px] font-bold text-slate-400">Max 10 Items</span>
                        </div>

                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            Populates the checkout screen with a fast, capped list of shortcut items before any search/barcode scan happens:
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <label :class="['p-3 rounded-xl border text-xs cursor-pointer transition flex items-start gap-2.5', form.quick_access_mode === 'top_sellers' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-500' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700']">
                                <input type="radio" value="top_sellers" v-model="form.quick_access_mode" class="mt-0.5 text-emerald-600" />
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">🔥 Top Sellers (Auto)</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Top 10 most frequently purchased items across last 30 days.</div>
                                </div>
                            </label>

                            <label :class="['p-3 rounded-xl border text-xs cursor-pointer transition flex items-start gap-2.5', form.quick_access_mode === 'recently_sold' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-500' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700']">
                                <input type="radio" value="recently_sold" v-model="form.quick_access_mode" class="mt-0.5 text-emerald-600" />
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">⏱️ Recently Sold (Dynamic)</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Last 10 unique products sold in this branch.</div>
                                </div>
                            </label>

                            <label :class="['p-3 rounded-xl border text-xs cursor-pointer transition flex items-start gap-2.5', form.quick_access_mode === 'pinned_items' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-500' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700']">
                                <input type="radio" value="pinned_items" v-model="form.quick_access_mode" class="mt-0.5 text-emerald-600" />
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">📌 Custom Pinned Items</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Hand-pick up to 10 specific products for cashiers.</div>
                                </div>
                            </label>

                            <label :class="['p-3 rounded-xl border text-xs cursor-pointer transition flex items-start gap-2.5', form.quick_access_mode === 'category_shortcuts' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-500' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700']">
                                <input type="radio" value="category_shortcuts" v-model="form.quick_access_mode" class="mt-0.5 text-emerald-600" />
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">🏷️ Category Shortcut Tabs</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Display quick-filter category pills at the top of checkout.</div>
                                </div>
                            </label>
                        </div>

                        <!-- Pinned Items Sub-Picker -->
                        <div v-if="form.quick_access_mode === 'pinned_items'" class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-3">
                            <div class="relative">
                                <input
                                    v-model="itemSearchQuery"
                                    type="text"
                                    placeholder="Search products to pin by name or barcode..."
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs outline-none"
                                    @focus="isSearchDropdownOpen = true"
                                />
                                <div v-if="isSearchDropdownOpen && filteredBranchItems.length > 0" class="absolute z-20 left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg p-1 space-y-1">
                                    <button
                                        v-for="item in filteredBranchItems"
                                        :key="item.id"
                                        type="button"
                                        @click="pinItem(item)"
                                        class="w-full text-left px-3 py-1.5 rounded-lg text-xs hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-between"
                                    >
                                        <span class="font-medium text-slate-800 dark:text-white truncate">{{ item.item_name }}</span>
                                        <span class="text-slate-400 font-mono">{{ format(item.price) }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Pinned Badges -->
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="itemId in form.pinned_quick_access_items"
                                    :key="itemId"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold text-emerald-800 dark:text-emerald-300"
                                >
                                    <span>{{ getItemById(itemId).item_name }}</span>
                                    <button type="button" @click="unpinItem(itemId)" class="text-emerald-600 hover:text-red-500 font-bold ml-1">×</button>
                                </div>
                            </div>
                        </div>

                        <!-- Category Shortcuts Sub-Picker -->
                        <div v-if="form.quick_access_mode === 'category_shortcuts'" class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-2">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Select Up to 6 Shortcut Categories:</span>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    type="button"
                                    @click="toggleCategory(cat.id)"
                                    :class="[
                                        'px-2.5 py-1 rounded-lg text-xs font-semibold border transition',
                                        form.quick_access_category_ids.includes(cat.id)
                                            ? 'bg-emerald-600 text-white border-emerald-600'
                                            : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600'
                                    ]"
                                >
                                    {{ cat.name }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing, Inventory Deduction & Profit Margins -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                            Pricing, Inventory Deduction & Margins
                        </h2>

                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_price_editable" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Allow POS Line Price Overrides</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Permit authorized cashiers with permission to adjust selling prices on the POS cart.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_show_buy_price" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Show Buy / Cost Price to Authorized Staff</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Displays unit cost in product lookup dialogs for managers and admins.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_qty_deduction" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Automatic Front-Store Stock Deduction</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Instantly decrements inventory counts when POS sales are concluded.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_offline_qty_deduction" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Deduct Stock on Offline Sales</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Syncs local offline queue sales and adjusts inventory once internet reconnects.</div>
                                </div>
                            </label>
                        </div>

                        <!-- Auto Profit Margins -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_use_profit_percentage" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Automatic Markup Pricing from Cost Price</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Auto-calculates consumer and wholesale prices based on set margin percentages.</div>
                                </div>
                            </label>

                            <div v-if="form.is_use_profit_percentage" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Consumer Markup %</label>
                                    <input v-model.number="form.consumer_profit_percent" type="number" min="0" step="0.5"
                                        class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-bold" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Wholesale Markup %</label>
                                    <input v-model.number="form.wholesale_profit_percent" type="number" min="0" step="0.5"
                                        class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-bold" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expiration, Batch & Serial Tracking -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700 pb-2">
                            Product Expiration & Device Tracking
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_check_expiration" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Track Item Expiration Dates</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Enables expiry date logging on intake and checkout.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.batch_tracking_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Batch / Lot Tracking</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Track shipments and batch numbers for medicines and perishables.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_imei_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">IMEI & Serial Number Tracking</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Prompt for device IMEI on sale (electronics & phones).</div>
                                </div>
                            </label>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expiry Alert Warning (Days)</label>
                                <input v-model.number="form.expiry_alert_days" type="number" min="1"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-bold" />
                            </div>
                        </div>
                    </div>

                    <!-- Public Counter Catalog & QR Code Poster -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-4 shadow-xs">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <span>📲</span>
                                    <span>Public Counter Catalog & QR Code Price List</span>
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Allow walk-in customers to scan a counter QR code to browse live inventory and prices on their phones.
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.public_catalog_enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <div v-if="form.public_catalog_enabled" class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                            <div class="md:col-span-2 space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Public Shareable Link</label>
                                    <div class="flex items-center gap-2">
                                        <input :value="publicCatalogUrl" readonly
                                            class="w-full bg-slate-50 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-mono select-all" />
                                        <button type="button" @click="copyCatalogLink" class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-xs font-bold transition shrink-0">
                                            {{ copySuccess ? 'Copied!' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 pt-2">
                                    <button type="button" @click="downloadQrPng" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1.5 transition">
                                        <span>📥</span> Download QR (PNG)
                                    </button>
                                    <button type="button" @click="printQrPoster" class="px-3 py-1.5 rounded-lg bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 text-white text-xs font-bold flex items-center gap-1.5 transition">
                                        <span>🖨️</span> Print Counter Poster
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col items-center justify-center p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                                <div ref="qrContainerRef" class="w-36 h-36 flex items-center justify-center bg-white p-2 rounded-lg shadow-2xs"></div>
                                <span class="text-[10px] text-slate-400 mt-2 font-mono">Scan with Phone Camera</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Bar for POS Settings -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            @click="submitTab('pos')"
                            :disabled="form.processing"
                            type="button"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition flex items-center gap-2 shadow-md shadow-emerald-600/20 cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ form.processing ? 'Saving...' : 'Save POS Settings' }}</span>
                        </button>
                    </div>
                </div>


                <!-- ════════════════════════════════════════════════════════════════
                     TAB 3: LAUNDRY MODULE SETTINGS (Turnaround, Prefix, Alerts, Policies)
                     ════════════════════════════════════════════════════════════════ -->
                <div v-show="activeTab === 'laundry'" class="space-y-6">

                    <!-- Direct Shortcuts Banner -->
                    <div class="bg-indigo-50 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/60 p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🧺</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Laundry Services & Promotional Discounts</h3>
                                <p class="text-xs text-slate-600 dark:text-slate-400">
                                    Manage dry cleaning categories, item service pricing (per piece / per kg), and active promo campaigns.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <Link :href="route('laundry.services.index', { branch: settings.branch_slug })"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition flex items-center gap-1 shadow-xs">
                                <span>🏷️ Services Catalog</span>
                            </Link>
                            <Link :href="route('laundry.discounts.index', { branch: settings.branch_slug })"
                                class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 text-xs font-bold transition flex items-center gap-1 shadow-xs">
                                <span>🎟️ Promos & Rules</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Turnaround & Order Numbering -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-5 shadow-xs">
                        <div class="border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                <span>⏱️</span>
                                <span>Operational Turnaround & Order Numbering</span>
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Set default turnaround deadlines and order identifier prefix format.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Default Turnaround (Hours)
                                </label>
                                <input
                                    v-model.number="form.default_turnaround_hours"
                                    type="number"
                                    min="1"
                                    max="720"
                                    class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white font-bold"
                                />
                                <p class="text-[11px] text-slate-400 mt-1">48 hours = 2 days turnaround</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Unclaimed After (Days)
                                </label>
                                <input
                                    v-model.number="form.unclaimed_after_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white font-bold"
                                />
                                <p class="text-[11px] text-slate-400 mt-1">Days left ready before marked unclaimed</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Order Number Prefix
                                </label>
                                <input
                                    v-model="form.order_prefix"
                                    type="text"
                                    maxlength="10"
                                    class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white font-black uppercase tracking-wider"
                                />
                                <p class="text-[11px] text-slate-400 mt-1">e.g. LDY- yields LDY-000123</p>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Ready Notifications -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-5 shadow-xs">
                        <div class="border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                <span>💬</span>
                                <span>WhatsApp Customer Notification Alert Template</span>
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Custom message triggered when attendants click the WhatsApp notification icon on completed orders.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Country Dial Code
                                </label>
                                <input
                                    v-model="form.default_country_code"
                                    type="text"
                                    placeholder="234"
                                    class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white font-bold"
                                />
                                <p class="text-[10px] text-slate-400 mt-1">Prefix for local 080... mobile numbers</p>
                            </div>

                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Message Template
                                </label>
                                <textarea
                                    v-model="form.whatsapp_template"
                                    rows="3"
                                    class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white leading-relaxed"
                                ></textarea>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5 text-[10px] text-slate-500">
                                    <span>Placeholders:</span>
                                    <code class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono font-bold">{customer_name}</code>
                                    <code class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono font-bold">{order_number}</code>
                                    <code class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono font-bold">{branch_name}</code>
                                    <code class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono font-bold">{balance}</code>
                                </div>
                            </div>
                        </div>

                        <!-- Live WhatsApp Message Preview Bubble -->
                        <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-emerald-700 dark:text-emerald-400 tracking-wider">Preview WhatsApp Chat Bubble:</span>
                            <p class="text-xs text-slate-800 dark:text-slate-200 italic font-sans leading-relaxed">
                                "{{ liveWhatsAppPreview }}"
                            </p>
                        </div>
                    </div>

                    <!-- Operational Policies & Ticket Footer -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-5 shadow-xs">
                        <div class="border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                <span>📋</span>
                                <span>Ticket Disclaimer & Staff Operational Policies</span>
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Set thermal ticket disclaimers and branch-level permission bypass rules.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Thermal Laundry Ticket Footer Disclaimer
                            </label>
                            <input
                                v-model="form.receipt_footer"
                                type="text"
                                class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Thank you for choosing us! Please present this ticket at pickup."
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition cursor-pointer">
                                <input v-model="form.allow_price_override" type="checkbox" class="w-4 h-4 mt-0.5 accent-indigo-600 rounded cursor-pointer shrink-0" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Allow Line Price Override</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                        Permit authorized attendants to modify line item unit prices on order intake. (Also requires the user to have the <span class="font-bold text-indigo-600 dark:text-indigo-400">Override Laundry Service Prices</span> permission).
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition cursor-pointer">
                                <input v-model="form.allow_unpaid_pickup" type="checkbox" class="w-4 h-4 mt-0.5 accent-indigo-600 rounded cursor-pointer shrink-0" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Allow Unpaid Order Pickup</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                        Allow releasing finished garments before full payment is settled, automatically posting the balance to customer debt ledger.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Bottom Action Bar for Laundry Settings -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            @click="submitTab('laundry')"
                            :disabled="form.processing"
                            type="button"
                            class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs transition flex items-center gap-2 shadow-md shadow-indigo-600/20 cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ form.processing ? 'Saving...' : 'Save Laundry Settings' }}</span>
                        </button>
                    </div>
                </div>


                <!-- ════════════════════════════════════════════════════════════════
                     TAB 4: ACCOUNTING & VAT SETTINGS
                     ════════════════════════════════════════════════════════════════ -->
                <div v-show="activeTab === 'accounting'" class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 space-y-5 shadow-xs">
                        <div class="border-b border-slate-200 dark:border-slate-700 pb-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <span>🧾</span>
                                <span>Double-Entry Accounting & VAT Automation</span>
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Configure automated sales tax, journal entry creation, and ledger accounting.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.is_tax_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Enable Sales Tax / VAT on Transactions</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Calculates and prints VAT breakdown on sales receipts and laundry tickets.</div>
                                </div>
                            </label>

                            <div v-if="form.is_tax_enabled" class="w-full sm:w-64 pt-1">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tax Percentage (%)</label>
                                <input v-model.number="form.tax_percentage" type="number" min="0" max="100" step="0.1"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-bold" />
                            </div>

                            <label class="flex items-center gap-3 cursor-pointer pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                <input type="checkbox" v-model="form.is_accounting_vat_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Post Tax Directly to Output VAT Liability Ledger</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Synchronizes tax amounts into chart of accounts journal vouchers for balance sheet reporting.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Bottom Action Bar for Accounting Settings -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            @click="submitTab('accounting')"
                            :disabled="form.processing"
                            type="button"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition flex items-center gap-2 shadow-md shadow-emerald-600/20 cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ form.processing ? 'Saving...' : 'Save Accounting Settings' }}</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
