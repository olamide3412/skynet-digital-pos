<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import { useCurrency } from '@/Composables/useCurrency'
import JsBarcode from 'jsbarcode'
import axios from 'axios'

defineOptions({ layout: PosLayout })

const props = defineProps({
    items:        { type: Object, required: true },
    missingCount: { type: Number, default: 0 },
    totalItems:   { type: Number, default: 0 },
    printLogs:    { type: Object, default: () => ({ data: [] }) },
    categories:   { type: Array,  default: () => [] },
    settings:     { type: Object, default: () => ({}) },
    filters:      { type: Object, default: () => ({}) },
})

const page = usePage()
const { format } = useCurrency()

// ── Active Tab ─────────────────────────────────────────────────────────────
const activeTab = ref(props.missingCount > 0 ? 'generator' : 'studio')

// ── Search & Filter State ──────────────────────────────────────────────────
const search       = ref(props.filters.search || '')
const categoryId   = ref(props.filters.category_id || '')
const filterStatus = ref(props.filters.filter || '')

function applyFilters() {
    router.get(route('pos.items.barcodes'), {
        search:      search.value || undefined,
        category_id: categoryId.value || undefined,
        filter:      filterStatus.value || undefined,
    }, {
        preserveState: true,
        replace:       true,
    })
}

function clearFilters() {
    search.value       = ''
    categoryId.value   = ''
    filterStatus.value = ''
    applyFilters()
}

// ── Async Live Item Search (Fast 5,000+ records lookup) ────────────────────
const asyncQuery    = ref('')
const asyncResults  = ref([])
const isSearching   = ref(false)
let searchDebounce  = null

function onAsyncSearchInput(e) {
    const val = e.target.value.trim()
    asyncQuery.value = val
    clearTimeout(searchDebounce)
    if (!val) {
        asyncResults.value = []
        return
    }
    searchDebounce = setTimeout(async () => {
        isSearching.value = true
        try {
            const res = await axios.get(route('pos.items.barcodes.search-items'), {
                params: { q: val }
            })
            asyncResults.value = res.data || []
        } catch (e) {
            console.warn('Async item search failed', e)
        } finally {
            isSearching.value = false
        }
    }, 250)
}

function selectAsyncItem(item) {
    if (!item.barcode_number || item.barcode_number.startsWith('NO_BARCODE')) {
        alert(`Item "${item.item_name}" has no barcode assigned yet. Please auto-generate a barcode in the Auto-Generator tab first.`)
        return
    }
    if (!selectedItems.value[item.id]) {
        selectedItems.value[item.id] = { item, quantity: 1 }
    } else {
        selectedItems.value[item.id].quantity += 1
    }
    asyncQuery.value   = ''
    asyncResults.value = []
}

// ── Label Presets & Custom Sizing ──────────────────────────────────────────
const PRESETS = [
    { id: '25x50', label: '25mm × 50mm (Standard Small)', width: 50, height: 25 },
    { id: '40x30', label: '40mm × 30mm (Compact Tag)',    width: 40, height: 30 },
    { id: '50x80', label: '50mm × 80mm (Large / Shelf)',   width: 80, height: 50 },
    { id: 'a4',    label: '📄 A4 Sheet (Grid Tiling)',     width: 40, height: 25, isA4: true },
    { id: 'custom',label: '📐 Custom Dimensions (mm)',    width: 50, height: 25, isCustom: true },
]

const selectedPreset = ref('25x50')
const customWidth    = ref(50)
const customHeight   = ref(25)

const activeWidth = computed(() => {
    if (selectedPreset.value === 'custom') return customWidth.value || 50
    const p = PRESETS.find(x => x.id === selectedPreset.value)
    return p ? p.width : 50
})

const activeHeight = computed(() => {
    if (selectedPreset.value === 'custom') return customHeight.value || 25
    const p = PRESETS.find(x => x.id === selectedPreset.value)
    return p ? p.height : 25
})

const isA4Mode = computed(() => selectedPreset.value === 'a4')

// ── Label Content & Styling Toggles ────────────────────────────────────────
const labelConfig = ref({
    showBusinessName: true,
    showItemName:     true,
    showPrice:        true,
    showBarcodeText:  true,
    fontSize:         'medium', // small, medium, large
    barcodeHeight:    32,       // in px (22, 32, 44)
})

const businessTitle = computed(() => {
    return page.props.current_branch?.name
        || props.settings?.business_name
        || page.props.system_config?.company_name
        || 'SkyNet POS'
})

// ── Saved Custom Templates (LocalStorage) ──────────────────────────────────
const SAVED_TEMPLATES_KEY = 'pos_barcode_label_templates'
const savedTemplates = ref([])
const newTemplateName = ref('')

function loadTemplates() {
    try {
        const raw = localStorage.getItem(SAVED_TEMPLATES_KEY)
        savedTemplates.value = raw ? JSON.parse(raw) : []
    } catch {
        savedTemplates.value = []
    }
}

function saveCurrentTemplate() {
    const name = newTemplateName.value.trim() || `Template ${savedTemplates.value.length + 1}`
    const template = {
        name,
        preset:       selectedPreset.value,
        customWidth:  customWidth.value,
        customHeight: customHeight.value,
        config:       { ...labelConfig.value },
    }
    savedTemplates.value.push(template)
    localStorage.setItem(SAVED_TEMPLATES_KEY, JSON.stringify(savedTemplates.value))
    newTemplateName.value = ''
}

function applyTemplate(template) {
    selectedPreset.value = template.preset
    customWidth.value    = template.customWidth
    customHeight.value   = template.customHeight
    labelConfig.value    = { ...template.config }
}

function deleteTemplate(index) {
    savedTemplates.value.splice(index, 1)
    localStorage.setItem(SAVED_TEMPLATES_KEY, JSON.stringify(savedTemplates.value))
}

// ── Selected Items for Printing Studio ─────────────────────────────────────
// Map of item_id -> { item, quantity }
const selectedItems = ref({})

function toggleItemSelection(item) {
    if (!item.barcode_number || item.barcode_number.startsWith('NO_BARCODE')) return
    if (selectedItems.value[item.id]) {
        delete selectedItems.value[item.id]
    } else {
        selectedItems.value[item.id] = {
            item,
            quantity: 1,
        }
    }
}

function removeItemFromSelection(itemId) {
    delete selectedItems.value[itemId]
}

function isSelected(itemId) {
    return Boolean(selectedItems.value[itemId])
}

function setItemQty(itemId, qty) {
    if (selectedItems.value[itemId]) {
        selectedItems.value[itemId].quantity = Math.max(1, parseInt(qty) || 1)
    }
}

function selectAllFiltered() {
    props.items.data.forEach(item => {
        if (item.barcode_number && !item.barcode_number.startsWith('NO_BARCODE')) {
            if (!selectedItems.value[item.id]) {
                selectedItems.value[item.id] = { item, quantity: 1 }
            }
        }
    })
}

function clearSelection() {
    selectedItems.value = {}
}

const selectedCount = computed(() => Object.keys(selectedItems.value).length)
const totalLabelsToPrint = computed(() => {
    return Object.values(selectedItems.value).reduce((sum, entry) => sum + (entry.quantity || 1), 0)
})

// Flattened list of individual labels to render
const flatLabelsToRender = computed(() => {
    const list = []
    Object.values(selectedItems.value).forEach(entry => {
        const qty = entry.quantity || 1
        for (let i = 0; i < qty; i++) {
            list.push(entry.item)
        }
    })
    return list
})

// First sample item for single live preview card
const sampleItem = computed(() => {
    const firstSelected = Object.values(selectedItems.value)[0]?.item
    if (firstSelected) return firstSelected
    return props.items.data.find(i => i.barcode_number && !i.barcode_number.startsWith('NO_BARCODE')) || {
        item_name: 'Sample Retail Product',
        barcode_number: 'ITM000101',
        price: 2500,
    }
})

// ── Barcode SVG Rendering Engine ───────────────────────────────────────────
function renderBarcodes() {
    nextTick(() => {
        const svgs = document.querySelectorAll('.barcode-svg-element')
        svgs.forEach(svg => {
            const code = svg.getAttribute('data-barcode')
            if (!code) return
            try {
                JsBarcode(svg, code, {
                    format:       'CODE128',
                    width:        activeWidth.value <= 35 ? 1.0 : 1.3,
                    height:       labelConfig.value.barcodeHeight || 32,
                    displayValue: labelConfig.value.showBarcodeText,
                    fontSize:     labelConfig.value.fontSize === 'small' ? 9 : 11,
                    fontOptions:  'bold',
                    margin:       2,
                    background:   '#ffffff',
                    lineColor:    '#000000',
                    textAlign:    'center',
                })
            } catch (err) {
                console.warn('Barcode SVG render failed for code:', code, err)
            }
        })
    })
}

watch([selectedPreset, customWidth, customHeight, labelConfig, selectedItems, activeTab], () => {
    renderBarcodes()
}, { deep: true })

onMounted(() => {
    loadTemplates()
    renderBarcodes()
})

// ── Auto-Generation Handlers ───────────────────────────────────────────────
const isGeneratingBulk = ref(false)
const generatingItemId = ref(null)

function generateSingle(item) {
    generatingItemId.value = item.id
    router.post(route('pos.items.barcodes.generate', { item: item.id }), {}, {
        preserveScroll: true,
        onFinish: () => {
            generatingItemId.value = null
        },
    })
}

function generateBulkAll() {
    if (!confirm(`Auto-generate barcodes for all ${props.missingCount} items missing a barcode?`)) return
    isGeneratingBulk.value = true
    router.post(route('pos.items.barcodes.generate-bulk'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isGeneratingBulk.value = false
        },
    })
}

// ── Quick Reprint from History ─────────────────────────────────────────────
function loadItemForReprint(item) {
    if (!item.barcode_number) return
    selectedItems.value[item.id] = {
        item,
        quantity: 1,
    }
    activeTab.value = 'studio'
}

// ── Print Execution ────────────────────────────────────────────────────────
const isPrinting = ref(false)

async function executePrint() {
    if (flatLabelsToRender.value.length === 0) {
        alert('Please select at least one item to print.')
        return
    }

    isPrinting.value = true

    // Log print run in backend
    const printPayload = Object.values(selectedItems.value).map(entry => ({
        item_id:          entry.item.id,
        item_name:        entry.item.item_name,
        barcode_value:    entry.item.barcode_number,
        quantity_printed: entry.quantity || 1,
    }))

    try {
        await axios.post(route('pos.items.barcodes.log-print'), {
            label_size: isA4Mode.value ? 'A4 Sheet' : `${activeWidth.value}x${activeHeight.value}mm`,
            prints:     printPayload,
        })
    } catch (e) {
        console.warn('Failed to log print action', e)
    }

    const printContainer = document.querySelector('.barcode-print-stage')
    if (!printContainer) {
        isPrinting.value = false
        return
    }

    const clone = printContainer.cloneNode(true)
    const srcSvgs = printContainer.querySelectorAll('svg')
    const cloneSvgs = clone.querySelectorAll('svg')
    srcSvgs.forEach((svg, i) => {
        if (cloneSvgs[i]) {
            cloneSvgs[i].outerHTML = svg.outerHTML
        }
    })

    const content = clone.innerHTML

    const pageRule = isA4Mode.value
        ? `@page { size: A4 portrait; margin: 6mm; }`
        : `@page { size: ${activeWidth.value}mm ${activeHeight.value}mm; margin: 0mm; }`

    const containerStyle = isA4Mode.value
        ? `
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(${activeWidth.value}mm, 1fr));
            gap: 3mm;
            width: 100%;
        `
        : `
            display: flex;
            flex-direction: column;
            align-items: center;
            width: ${activeWidth.value}mm;
        `

    const labelStyle = `
        width: ${activeWidth.value}mm;
        height: ${activeHeight.value}mm;
        max-height: ${activeHeight.value}mm;
        box-sizing: border-box;
        padding: 1.5mm;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        text-align: center;
        background: #ffffff;
        color: #000000;
        page-break-inside: avoid;
        break-inside: avoid;
        overflow: hidden;
        font-family: system-ui, -apple-system, sans-serif;
    `

    let iframe = document.getElementById('barcode-label-print-frame')
    if (!iframe) {
        iframe = document.createElement('iframe')
        iframe.id = 'barcode-label-print-frame'
        iframe.style.position = 'fixed'
        iframe.style.right = '0'
        iframe.style.bottom = '0'
        iframe.style.width = '0'
        iframe.style.height = '0'
        iframe.style.border = '0'
        document.body.appendChild(iframe)
    }

    const doc = iframe.contentWindow.document
    doc.open()
    doc.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Barcode Labels</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; }
        ${pageRule}
        body {
            background: #fff;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .print-grid {
            ${containerStyle}
        }
        .barcode-single-label {
            ${labelStyle}
        }
        .label-biz {
            font-size: ${labelConfig.value.fontSize === 'small' ? '8px' : '10px'};
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.1;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .label-name {
            font-size: ${labelConfig.value.fontSize === 'small' ? '9px' : '11px'};
            font-weight: 600;
            line-height: 1.15;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .label-price {
            font-size: ${labelConfig.value.fontSize === 'small' ? '10px' : '12px'};
            font-weight: 800;
            font-family: monospace;
            line-height: 1.1;
        }
        svg {
            display: block;
            margin: 0 auto;
            max-width: 100%;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="print-grid">
        ${content}
    </div>
</body>
</html>`)
    doc.close()

    setTimeout(() => {
        try {
            iframe.contentWindow.focus()
            iframe.contentWindow.print()
        } catch (e) {
            console.error('Direct print failed', e)
        } finally {
            isPrinting.value = false
        }
    }, 300)
}
</script>

<template>
    <Head title="Barcode Management & Labels" />

    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">

        <!-- ── Top Header ─────────────────────────────────────────────── -->
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xl">
                        🏷️
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white">Barcode Management & Label Studio</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Auto-generate Code 128 barcodes, customize multi-size labels, and reprint stock tags.
                        </p>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-700/50 rounded-xl border border-slate-200/80 dark:border-slate-700">
                    <button
                        @click="activeTab = 'studio'"
                        :class="['px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                            activeTab === 'studio'
                                ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white']">
                        <span>🎨 Label Studio</span>
                        <span v-if="selectedCount > 0" class="px-1.5 py-0.2 bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 rounded-full text-[10px]">
                            {{ selectedCount }}
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'generator'"
                        :class="['px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                            activeTab === 'generator'
                                ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white']">
                        <span>⚡ Auto-Generator</span>
                        <span v-if="missingCount > 0" class="px-1.5 py-0.2 bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 rounded-full text-[10px] font-bold">
                            {{ missingCount }} Missing
                        </span>
                    </button>

                    <button
                        @click="activeTab = 'history'"
                        :class="['px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                            activeTab === 'history'
                                ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white']">
                        <span>📜 History & Reprints</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Main Workspace Body ────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-6">

            <!-- ════════════════════════════════════════════════════════════
                 TAB 1: LABEL PRINT STUDIO
            ════════════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'studio'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 max-w-7xl mx-auto">

                <!-- Left Column: Item Selection & Style Controls (7 cols) -->
                <div class="lg:col-span-7 space-y-5">

                    <!-- Live Instant Search across 5,000+ Items -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs space-y-3 relative">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                1. Search & Pick Items (Across 5,000+ Catalog)
                            </h2>
                            <span v-if="isSearching" class="text-[11px] text-emerald-500 animate-pulse font-semibold">Searching catalog...</span>
                        </div>

                        <!-- Async Search Bar with Auto-suggest -->
                        <div class="relative">
                            <input
                                :value="asyncQuery"
                                @input="onAsyncSearchInput"
                                placeholder="Type item name, SKU, or barcode to add to print queue..."
                                class="w-full bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white pl-9 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm focus:border-emerald-500 outline-none transition"
                            />
                            <div class="absolute left-3 top-2.5 text-slate-400">
                                🔍
                            </div>

                            <!-- Live Search Results Dropdown -->
                            <div
                                v-if="asyncResults.length > 0"
                                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xl z-30 max-h-64 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
                                <div
                                    v-for="res in asyncResults"
                                    :key="res.id"
                                    @click="selectAsyncItem(res)"
                                    class="p-3 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 flex items-center justify-between cursor-pointer transition">
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs text-slate-900 dark:text-white truncate">
                                            {{ res.item_name }}
                                        </div>
                                        <div class="flex items-center gap-2 text-[11px] text-slate-500">
                                            <span class="font-mono text-emerald-600 font-bold">{{ res.barcode_number || 'No Barcode' }}</span>
                                            <span>·</span>
                                            <span>Stock: {{ res.front_store_qty }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <span class="font-mono font-bold text-xs text-slate-800 dark:text-slate-200 block">
                                            {{ format(res.price) }}
                                        </span>
                                        <span class="text-[10px] text-emerald-600 font-semibold">+ Add to Queue</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Items Queue Box -->
                        <div v-if="selectedCount > 0" class="pt-2 space-y-2">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-slate-400">
                                <span>Selected for Printing ({{ selectedCount }} items, {{ totalLabelsToPrint }} labels):</span>
                                <button @click="clearSelection" type="button" class="text-red-500 hover:underline cursor-pointer">
                                    Clear All
                                </button>
                            </div>

                            <div class="max-h-48 overflow-y-auto border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/30 dark:bg-emerald-950/10 rounded-xl divide-y divide-emerald-100 dark:divide-emerald-900/30 p-1">
                                <div
                                    v-for="entry in selectedItems"
                                    :key="entry.item.id"
                                    class="p-2 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white truncate">
                                            {{ entry.item.item_name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 font-mono">
                                            {{ entry.item.barcode_number }} · {{ format(entry.item.price) }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="text-[11px] text-slate-500 font-semibold">Copies:</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max="500"
                                            :value="entry.quantity || 1"
                                            @input="setItemQty(entry.item.id, $event.target.value)"
                                            class="w-14 bg-white dark:bg-slate-800 text-center px-1.5 py-1 rounded-md border border-emerald-500 text-xs font-bold font-mono"
                                        />
                                        <button
                                            @click="removeItemFromSelection(entry.item.id)"
                                            class="text-slate-400 hover:text-red-500 text-base font-bold px-1 cursor-pointer">
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Label Sizing Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs space-y-4">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-100 dark:border-slate-700/60 pb-2 flex items-center justify-between">
                            <span>2. Label Sizing & Stock Selection</span>
                            <span class="text-[11px] font-mono text-slate-500">{{ activeWidth }}mm × {{ activeHeight }}mm</span>
                        </h2>

                        <!-- Preset Buttons -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            <button
                                v-for="preset in PRESETS"
                                :key="preset.id"
                                @click="selectedPreset = preset.id"
                                type="button"
                                :class="['p-2.5 text-left rounded-xl border text-xs font-semibold transition-all cursor-pointer flex flex-col justify-between',
                                    selectedPreset === preset.id
                                        ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-500 text-emerald-800 dark:text-emerald-200 ring-2 ring-emerald-500/20'
                                        : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 hover:border-slate-300 text-slate-700 dark:text-slate-300']">
                                <span class="font-bold">{{ preset.label.split('(')[0] }}</span>
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-normal">
                                    {{ preset.label.includes('(') ? preset.label.substring(preset.label.indexOf('(')) : '' }}
                                </span>
                            </button>
                        </div>

                        <!-- Custom Dimensions Input (if Custom selected) -->
                        <div v-if="selectedPreset === 'custom'" class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Width (mm)</label>
                                <input v-model.number="customWidth" type="number" min="20" max="210"
                                    class="w-full bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-sm font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Height (mm)</label>
                                <input v-model.number="customHeight" type="number" min="15" max="297"
                                    class="w-full bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-sm font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- Label Content & Styling Customizer -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs space-y-4">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                            3. Label Content & Styling Options
                        </h2>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <label class="flex items-center gap-2 p-2.5 bg-slate-50 dark:bg-slate-700/40 rounded-xl border border-slate-200 dark:border-slate-600/60 cursor-pointer">
                                <input type="checkbox" v-model="labelConfig.showBusinessName" class="w-4 h-4 accent-emerald-500 rounded" />
                                <span class="text-xs font-semibold">Business Name</span>
                            </label>

                            <label class="flex items-center gap-2 p-2.5 bg-slate-50 dark:bg-slate-700/40 rounded-xl border border-slate-200 dark:border-slate-600/60 cursor-pointer">
                                <input type="checkbox" v-model="labelConfig.showItemName" class="w-4 h-4 accent-emerald-500 rounded" />
                                <span class="text-xs font-semibold">Item Name</span>
                            </label>

                            <label class="flex items-center gap-2 p-2.5 bg-slate-50 dark:bg-slate-700/40 rounded-xl border border-slate-200 dark:border-slate-600/60 cursor-pointer">
                                <input type="checkbox" v-model="labelConfig.showPrice" class="w-4 h-4 accent-emerald-500 rounded" />
                                <span class="text-xs font-semibold">Retail Price</span>
                            </label>

                            <label class="flex items-center gap-2 p-2.5 bg-slate-50 dark:bg-slate-700/40 rounded-xl border border-slate-200 dark:border-slate-600/60 cursor-pointer">
                                <input type="checkbox" v-model="labelConfig.showBarcodeText" class="w-4 h-4 accent-emerald-500 rounded" />
                                <span class="text-xs font-semibold">Barcode Code</span>
                            </label>
                        </div>

                        <!-- Barcode Height & Font Controls -->
                        <div class="grid grid-cols-2 gap-4 pt-1">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Barcode Height</label>
                                <select v-model.number="labelConfig.barcodeHeight"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs">
                                    <option :value="22">Compact (22px)</option>
                                    <option :value="32">Standard (32px)</option>
                                    <option :value="44">Tall (44px)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Text Typography Size</label>
                                <select v-model="labelConfig.fontSize"
                                    class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs">
                                    <option value="small">Small (Compact labels)</option>
                                    <option value="medium">Medium (Standard)</option>
                                    <option value="large">Large (Shelf tags)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Save As Template -->
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
                            <input v-model="newTemplateName" placeholder="Save style as template (e.g., Shelf Tag)"
                                class="flex-1 bg-slate-50 dark:bg-slate-700 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs" />
                            <button @click="saveCurrentTemplate" type="button"
                                class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-white font-semibold text-xs rounded-lg transition cursor-pointer">
                                💾 Save Template
                            </button>
                        </div>

                        <!-- Saved Templates Chips -->
                        <div v-if="savedTemplates.length > 0" class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span class="text-[11px] font-semibold text-slate-400">Saved:</span>
                            <span
                                v-for="(tpl, idx) in savedTemplates"
                                :key="idx"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 dark:bg-slate-700 rounded-full text-xs text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                <button @click="applyTemplate(tpl)" class="hover:text-emerald-600 font-medium cursor-pointer">
                                    {{ tpl.name }}
                                </button>
                                <button @click="deleteTemplate(idx)" class="text-slate-400 hover:text-red-500 cursor-pointer ml-1">
                                    &times;
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Interactive Preview & Action Panel (5 cols) -->
                <div class="lg:col-span-5 space-y-5">

                    <!-- Live Preview Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs sticky top-4 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-2">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                Live Label Preview
                            </h2>
                            <span class="text-[10px] px-2 py-0.5 bg-slate-100 dark:bg-slate-700 rounded-full font-mono font-bold text-slate-600 dark:text-slate-300">
                                {{ activeWidth }}mm × {{ activeHeight }}mm
                            </span>
                        </div>

                        <!-- Simulated Label Canvas -->
                        <div class="p-6 bg-slate-100 dark:bg-slate-900/80 rounded-xl flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-800 min-h-[220px]">
                            <!-- Physical Label Wrapper -->
                            <div
                                :style="{
                                    width: `${activeWidth * 3.77}px`,
                                    minHeight: `${activeHeight * 3.77}px`,
                                }"
                                class="bg-white text-black p-2.5 rounded shadow-lg flex flex-col justify-between items-center text-center transition-all">
                                
                                <!-- Top Business Title -->
                                <div v-if="labelConfig.showBusinessName"
                                    :class="['font-bold text-slate-800 uppercase tracking-wide truncate max-w-full leading-tight',
                                        labelConfig.fontSize === 'small' ? 'text-[9px]' : 'text-[11px]']">
                                    {{ businessTitle }}
                                </div>

                                <!-- Item Name -->
                                <div v-if="labelConfig.showItemName"
                                    :class="['font-semibold text-black truncate max-w-full leading-tight my-0.5',
                                        labelConfig.fontSize === 'small' ? 'text-[10px]' : 'text-[12px]']">
                                    {{ sampleItem.item_name }}
                                </div>

                                <!-- Barcode SVG Image -->
                                <div class="my-0.5 w-full flex justify-center">
                                    <svg class="barcode-svg-element" :data-barcode="sampleItem.barcode_number || 'ITM000101'"></svg>
                                </div>

                                <!-- Price -->
                                <div v-if="labelConfig.showPrice"
                                    :class="['font-extrabold text-black font-mono leading-tight',
                                        labelConfig.fontSize === 'small' ? 'text-[11px]' : 'text-[13px]']">
                                    {{ format(sampleItem.price) }}
                                </div>
                            </div>
                        </div>

                        <!-- Print Summary Banner -->
                        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 rounded-xl border border-emerald-200 dark:border-emerald-800/60 text-xs flex items-center justify-between">
                            <div>
                                <span class="font-bold text-emerald-800 dark:text-emerald-300">
                                    {{ selectedCount }} Items in Print Queue
                                </span>
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-400">
                                    Total {{ totalLabelsToPrint }} label stickers to print
                                </p>
                            </div>
                            <span class="text-xl">🖨️</span>
                        </div>

                        <!-- Primary Print Action Button -->
                        <button
                            @click="executePrint"
                            :disabled="selectedCount === 0 || isPrinting"
                            type="button"
                            :class="['w-full py-3 px-4 rounded-xl font-bold text-sm flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer',
                                selectedCount > 0 && !isPrinting
                                    ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-600/30 hover:scale-[1.01] active:scale-95'
                                    : 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-not-allowed']">
                            <svg v-if="!isPrinting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ isPrinting ? 'Sending to Printer...' : `Print ${totalLabelsToPrint} Barcode Labels` }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════
                 TAB 2: AUTO-GENERATOR & MISSING BARCODES
            ════════════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'generator'" class="max-w-5xl mx-auto space-y-6">

                <!-- Alert / Banner Card -->
                <div :class="['p-5 rounded-2xl border shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4',
                    missingCount > 0
                        ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800'
                        : 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800']">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">{{ missingCount > 0 ? '⚠️' : '✅' }}</span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ missingCount > 0 ? `${missingCount} Items Missing Barcodes` : 'All Items Have Barcodes!' }}
                            </h2>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                {{ missingCount > 0
                                    ? 'Auto-generate standard Code 128 barcodes (e.g. ITM000042) ensuring uniqueness without collisions.'
                                    : `All ${totalItems} items in this branch are fully cataloged with permanent scannable barcodes.` }}
                            </p>
                        </div>
                    </div>

                    <!-- Bulk Auto-Generate Button -->
                    <button
                        v-if="missingCount > 0"
                        @click="generateBulkAll"
                        :disabled="isGeneratingBulk"
                        type="button"
                        class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-md transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <svg v-if="!isGeneratingBulk" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span>{{ isGeneratingBulk ? 'Generating...' : `⚡ Auto-Generate All (${missingCount})` }}</span>
                    </button>
                </div>

                <!-- Missing Barcodes Table -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            Items Awaiting Barcode Assignment
                        </h3>
                        <button
                            @click="filterStatus = 'missing'; applyFilters()"
                            class="text-xs text-emerald-600 font-semibold hover:underline cursor-pointer">
                            Filter Missing Only
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-3 px-4 font-bold">Item Name</th>
                                    <th class="py-3 px-4 font-bold">Category</th>
                                    <th class="py-3 px-4 font-bold">Price</th>
                                    <th class="py-3 px-4 font-bold">Stock</th>
                                    <th class="py-3 px-4 font-bold">Status</th>
                                    <th class="py-3 px-4 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr
                                    v-for="item in items.data"
                                    :key="item.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                                    <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                        {{ item.item_name }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-500">
                                        {{ item.category?.name || '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-bold font-mono text-emerald-600">
                                        {{ format(item.price) }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                                        {{ item.front_store_qty }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span v-if="!item.barcode_number || item.barcode_number.startsWith('NO_BARCODE')"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">
                                            No Barcode
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300 font-mono">
                                            {{ item.barcode_number }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <button
                                            v-if="!item.barcode_number || item.barcode_number.startsWith('NO_BARCODE')"
                                            @click="generateSingle(item)"
                                            :disabled="generatingItemId === item.id"
                                            type="button"
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold text-xs transition active:scale-95 cursor-pointer">
                                            {{ generatingItemId === item.id ? 'Generating...' : '⚡ Generate' }}
                                        </button>
                                        <button
                                            v-else
                                            @click="loadItemForReprint(item)"
                                            type="button"
                                            class="px-2.5 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 dark:bg-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold cursor-pointer">
                                            🏷️ Studio
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links for Missing Items -->
                    <PaginationLinks :links="items.links" :meta="items" />
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════
                 TAB 3: BARCODE HISTORY & EASY REPRINTING
            ════════════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'history'" class="max-w-6xl mx-auto space-y-6">

                <!-- Searchable Barcode Catalog -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Active Item Barcode Registry</h2>
                            <p class="text-xs text-slate-500">Every permanent barcode assigned to items in this branch. Click Quick Reprint anytime stock arrives.</p>
                        </div>

                        <!-- Search Bar -->
                        <div class="flex items-center gap-2">
                            <input v-model="search" @keydown.enter="applyFilters" placeholder="Search barcode or item..."
                                class="bg-slate-50 dark:bg-slate-700 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs w-64" />
                            <button @click="applyFilters" type="button" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold cursor-pointer">
                                Search
                            </button>
                            <button v-if="search || categoryId || filterStatus" @click="clearFilters" type="button" class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs cursor-pointer">
                                Reset
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-3 px-4 font-bold">Item Name</th>
                                    <th class="py-3 px-4 font-bold">Barcode Value</th>
                                    <th class="py-3 px-4 font-bold">Category</th>
                                    <th class="py-3 px-4 font-bold">Selling Price</th>
                                    <th class="py-3 px-4 font-bold text-right">Quick Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr
                                    v-for="item in items.data"
                                    :key="item.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                                    <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                        {{ item.item_name }}
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ item.barcode_number || '—' }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-500">
                                        {{ item.category?.name || '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                                        {{ format(item.price) }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <button
                                            v-if="item.barcode_number && !item.barcode_number.startsWith('NO_BARCODE')"
                                            @click="loadItemForReprint(item)"
                                            type="button"
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition active:scale-95 cursor-pointer">
                                            🖨️ Quick Reprint
                                        </button>
                                        <button
                                            v-else
                                            @click="generateSingle(item)"
                                            type="button"
                                            class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold cursor-pointer">
                                            ⚡ Auto-Generate
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links for History Table -->
                    <PaginationLinks :links="items.links" :meta="items" />
                </div>

                <!-- Print Audit History Log -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-xs space-y-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                        Recent Barcode Print Run Audit Logs
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                                <tr>
                                    <th class="py-2.5 px-4 font-bold">Printed At</th>
                                    <th class="py-2.5 px-4 font-bold">Item</th>
                                    <th class="py-2.5 px-4 font-bold">Barcode</th>
                                    <th class="py-2.5 px-4 font-bold">Label Size</th>
                                    <th class="py-2.5 px-4 font-bold">Quantity</th>
                                    <th class="py-2.5 px-4 font-bold">Printed By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-slate-600 dark:text-slate-300">
                                <tr v-if="!printLogs.data || printLogs.data.length === 0">
                                    <td colspan="6" class="py-6 text-center text-slate-400">
                                        No print history recorded yet.
                                    </td>
                                </tr>
                                <tr v-for="log in printLogs.data" :key="log.id" class="hover:bg-slate-50 dark:hover:bg-slate-750">
                                    <td class="py-2.5 px-4 font-mono text-[11px] text-slate-500">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </td>
                                    <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">
                                        {{ log.item_name }}
                                    </td>
                                    <td class="py-2.5 px-4 font-mono text-emerald-600">
                                        {{ log.barcode_value }}
                                    </td>
                                    <td class="py-2.5 px-4 font-mono text-[11px]">
                                        {{ log.label_size }}
                                    </td>
                                    <td class="py-2.5 px-4 font-bold">
                                        {{ log.quantity_printed }} copies
                                    </td>
                                    <td class="py-2.5 px-4 text-slate-500">
                                        {{ log.user?.name || 'Admin' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Print Logs Pagination -->
                    <PaginationLinks v-if="printLogs.links" :links="printLogs.links" :meta="printLogs" />
                </div>
            </div>
        </div>

        <!-- ── Hidden Render Stage for Direct Printing ────────────────── -->
        <div class="hidden">
            <div class="barcode-print-stage">
                <div
                    v-for="(lblItem, idx) in flatLabelsToRender"
                    :key="idx"
                    class="barcode-single-label">
                    <div v-if="labelConfig.showBusinessName" class="label-biz">
                        {{ businessTitle }}
                    </div>
                    <div v-if="labelConfig.showItemName" class="label-name">
                        {{ lblItem.item_name }}
                    </div>
                    <div class="barcode-wrapper">
                        <svg class="barcode-svg-element" :data-barcode="lblItem.barcode_number"></svg>
                    </div>
                    <div v-if="labelConfig.showPrice" class="label-price">
                        {{ format(lblItem.price) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
