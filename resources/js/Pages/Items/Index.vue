<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const page = usePage()
const props = defineProps({
    items:      Object,
    filters:    Object,
    totalWorth: Number,
})

const { format } = useCurrency()
const search    = ref(props.filters?.search ?? '')
const showWorth = ref(false)

const showImportModal = ref(false)
const importType      = ref('native') // 'native' or 'medfusion'
const importForm      = useForm({ csv_file: null })

const importReport = computed(() => page.props.flash?.importReport || null)

function doSearch() {
    router.get(route('pos.items.index'), { search: search.value }, { preserveState: true, replace: true })
}

function destroy(id) {
    if (confirm('Delete this item?')) {
        router.delete(route('pos.items.destroy', id))
    }
}

function expiryClass(date) {
    if (!date) return ''
    const diff = (new Date(date) - new Date()) / (1000 * 60 * 60 * 24)
    if (diff < 0)  return 'text-red-500 font-bold'
    if (diff < 30) return 'text-amber-500 font-semibold'
    return 'text-emerald-500'
}

function submitImport() {
    if (!importForm.csv_file) return
    const routeName = importType.value === 'medfusion' ? 'pos.items.import-medfusion' : 'pos.items.import'

    importForm.post(route(routeName), {
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
        },
    })
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0 gap-3">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Items</h1>
            
            <div class="flex flex-wrap items-center gap-2">
                <!-- Download Sample Template -->
                <a
                    :href="route('pos.items.export-template')"
                    download
                    class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                    title="Download CSV Template matching current item schema"
                >
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Template
                </a>

                <!-- Export CSV -->
                <a
                    :href="route('pos.items.export')"
                    class="px-3 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-600"
                    title="Export current branch items to CSV"
                >
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export CSV
                </a>

                <!-- Import CSV Modal Trigger -->
                <button
                    @click="showImportModal = true"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import CSV
                </button>

                <!-- New Item Button -->
                <Link :href="route('pos.items.create')"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs rounded-lg transition font-bold flex items-center gap-1.5 shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Item
                </Link>
            </div>
        </div>

        <!-- Import Results Progress Report Card (If Just Imported) -->
        <div v-if="importReport" class="px-6 pt-4 flex-shrink-0">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm">Import Results & Progress Report</h3>
                    </div>
                    <span class="text-xs text-slate-400">Total Rows Processed: {{ importReport.total }}</span>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center text-xs">
                    <div class="bg-slate-100 dark:bg-slate-700/50 p-2.5 rounded-lg border border-slate-200 dark:border-slate-600">
                        <span class="text-slate-500 dark:text-slate-400 block font-medium">Total Rows</span>
                        <span class="text-base font-bold text-slate-900 dark:text-white">{{ importReport.total }}</span>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-500/10 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-500/20">
                        <span class="text-emerald-700 dark:text-emerald-400 block font-medium">Successfully Uploaded</span>
                        <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ importReport.success }}</span>
                    </div>
                    <div class="bg-red-50 dark:bg-red-500/10 p-2.5 rounded-lg border border-red-200 dark:border-red-500/20">
                        <span class="text-red-700 dark:text-red-400 block font-medium">Failed / Skipped</span>
                        <span class="text-base font-bold text-red-600 dark:text-red-400">{{ importReport.failed.length }}</span>
                    </div>
                </div>

                <!-- Failed Items List Table -->
                <div v-if="importReport.failed.length" class="mt-2">
                    <p class="text-xs font-bold text-red-600 dark:text-red-400 mb-1">Failed Items Breakdown:</p>
                    <div class="max-h-36 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                <tr>
                                    <th class="px-3 py-1.5">Line #</th>
                                    <th class="px-3 py-1.5">Item Name</th>
                                    <th class="px-3 py-1.5">Reason for Failure</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="fail in importReport.failed" :key="fail.line" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-3 py-1.5 font-mono text-slate-500">Row {{ fail.line }}</td>
                                    <td class="px-3 py-1.5 font-medium text-slate-900 dark:text-slate-100">{{ fail.item_name }}</td>
                                    <td class="px-3 py-1.5 text-red-500 font-mono">{{ fail.reason }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + Worth Banner -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 flex-shrink-0 space-y-3 bg-white dark:bg-transparent">
            <div class="flex gap-2">
                <input v-model="search" @keydown.enter="doSearch" type="text"
                    placeholder="Search by name or barcode…"
                    class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition" />
                <button @click="doSearch" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm rounded-lg transition font-medium">Search</button>
            </div>

            <!-- Total Stock Worth card -->
            <div class="flex items-center gap-3 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 shadow-xs">
                <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Stock Worth (Cost Price)</p>
                    <p class="text-lg font-bold font-mono tracking-wide" :class="showWorth ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500'">
                        <span v-if="showWorth">{{ format(totalWorth) }}</span>
                        <span v-else class="tracking-widest select-none">&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;</span>
                    </p>
                </div>
                <button @click="showWorth = !showWorth"
                    :title="showWorth ? 'Hide worth' : 'Show worth'"
                    class="w-8 h-8 rounded-lg flex items-center justify-center transition"
                    :class="showWorth ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 hover:bg-amber-500/30' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-600 hover:text-slate-900 dark:hover:text-white'">
                    <svg v-if="showWorth" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Items Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Item</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Barcode</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Category</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Price</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Qty</th>
                            <th class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">
                                <span class="flex items-center justify-end gap-1">
                                    Stock Worth
                                    <span class="text-xs text-slate-400 dark:text-slate-500">(cost)</span>
                                </span>
                            </th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Expiry</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-slate-900 dark:text-white font-medium flex items-center gap-2.5">
                                <div v-if="item.image_url" class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700 overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-650 flex-shrink-0">
                                    <img :src="item.image_url" class="object-cover w-full h-full" alt="Item Thumbnail" />
                                </div>
                                <div v-else class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center border border-slate-200 dark:border-slate-700 flex-shrink-0 text-slate-400 dark:text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <span>{{ item.item_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs">{{ item.barcode_number }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ item.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 font-medium">{{ format(item.price) }}</td>
                            <td class="px-4 py-3 text-right font-medium">
                                <span :class="(item.total_qty ?? item.front_store_qty ?? 0) <= 5 ? 'text-red-500 dark:text-red-400 font-bold' : 'text-slate-900 dark:text-white'">
                                    {{ item.total_qty ?? item.front_store_qty ?? 0 }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs">
                                <span v-if="showWorth" class="text-amber-600 dark:text-amber-400 font-semibold">{{ format(item.stock_worth) }}</span>
                                <span v-else class="text-slate-400 dark:text-slate-600 tracking-widest select-none">&#9679;&#9679;&#9679;&#9679;</span>
                            </td>
                            <td class="px-4 py-3 text-xs" :class="expiryClass(item.expiry_date)">
                                {{ item.expiry_date ? dayjs(item.expiry_date).format('DD-MMM-YYYY') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('pos.items.edit', item.id)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 font-medium transition">Edit</Link>
                                    <button @click="destroy(item.id)"
                                        class="text-xs text-red-600 dark:text-red-400 hover:text-red-500 dark:hover:text-red-300 font-medium transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.data.length">
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">No items found.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="items.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-sm text-slate-500 dark:text-slate-400">
                    <span>Showing {{ items.from }}–{{ items.to }} of {{ items.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="items.prev_page_url" :href="items.prev_page_url"
                            class="px-3 py-1 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 text-xs transition">Prev</Link>
                        <Link v-if="items.next_page_url" :href="items.next_page_url"
                            class="px-3 py-1 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 text-xs transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 dark:border-slate-700 transition-colors">
                
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Import Items CSV</h3>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition font-bold">✕</button>
                </div>

                <!-- Import Mode Tabs -->
                <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-750">
                    <button
                        type="button"
                        @click="importType = 'native'"
                        class="flex-1 py-2.5 text-xs font-bold transition border-b-2"
                        :class="importType === 'native' ? 'border-blue-600 text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                    >
                        Standard Items CSV (Current Schema)
                    </button>
                    <button
                        type="button"
                        @click="importType = 'medfusion'"
                        class="flex-1 py-2.5 text-xs font-bold transition border-b-2"
                        :class="importType === 'medfusion' ? 'border-amber-600 text-amber-600 dark:text-amber-400 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                    >
                        Import Medfusion CSV (Legacy POS)
                    </button>
                </div>

                <form @submit.prevent="submitImport" class="px-5 py-4 space-y-4">
                    
                    <!-- Mode Description -->
                    <div v-if="importType === 'native'" class="space-y-2">
                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            Upload a CSV matching the current items table schema. Download the sample template to see required columns.
                        </p>
                        <a
                            :href="route('pos.items.export-template')"
                            download
                            class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                        >
                            📥 Download Items CSV Template
                        </a>
                    </div>

                    <div v-else class="space-y-2">
                        <p class="text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 p-2.5 rounded-lg border border-amber-200 dark:border-amber-500/20">
                            <strong>Import Medfusion CSV:</strong> Supports legacy old POS format (<code class="font-mono">barcode_number</code>, <code class="font-mono">item_name</code>, <code class="font-mono">category_name</code>, <code class="font-mono">qty</code>, <code class="font-mono">Buy_price</code>, <code class="font-mono">price</code>, <code class="font-mono">wholesale</code>, <code class="font-mono">expiry_date</code>). Missing categories will be created automatically.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Select CSV File</label>
                        <input
                            type="file"
                            accept=".csv, .txt"
                            @change="importForm.csv_file = $event.target.files[0]"
                            class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white text-xs px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 outline-none file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white"
                            required
                        />
                        <p v-if="importForm.errors.csv_file" class="text-red-500 text-xs mt-1">{{ importForm.errors.csv_file }}</p>
                    </div>

                    <div class="flex gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showImportModal = false" class="flex-1 py-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-600 transition">Cancel</button>
                        <button type="submit" :disabled="importForm.processing || !importForm.csv_file" class="flex-1 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition disabled:opacity-40 shadow-xs">
                            {{ importForm.processing ? 'Uploading & Processing…' : (importType === 'medfusion' ? 'Import Medfusion CSV' : 'Import Items CSV') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
