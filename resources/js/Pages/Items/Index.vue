<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    items:      Object,
    filters:    Object,
    totalWorth: Number,
})

const { format } = useCurrency()
const search    = ref(props.filters?.search ?? '')
const showWorth = ref(false)

const showImportModal = ref(false)
const importForm      = useForm({ csv_file: null })

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
    if (diff < 0)  return 'text-red-400'
    if (diff < 30) return 'text-amber-400'
    return 'text-emerald-400'
}

function submitImport() {
    if (!importForm.csv_file) return
    importForm.post(route('pos.items.import'), {
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
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Items</h1>
            <div class="flex items-center gap-2">
                <button
                    @click="showImportModal = true"
                    class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import CSV
                </button>
                <Link :href="route('pos.items.create')"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5 shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Item
                </Link>
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
                <!-- Icon -->
                <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <!-- Label + value -->
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Stock Worth (Cost Price)</p>
                    <p class="text-lg font-bold font-mono tracking-wide" :class="showWorth ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500'">
                        <span v-if="showWorth">{{ format(totalWorth) }}</span>
                        <span v-else class="tracking-widest select-none">&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;</span>
                    </p>
                </div>
                <!-- Eye toggle button -->
                <button @click="showWorth = !showWorth"
                    :title="showWorth ? 'Hide worth' : 'Show worth'"
                    class="w-8 h-8 rounded-lg flex items-center justify-center transition"
                    :class="showWorth ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 hover:bg-amber-500/30' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-600 hover:text-slate-900 dark:hover:text-white'">
                    <!-- Eye open -->
                    <svg v-if="showWorth" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <!-- Eye closed -->
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Table -->
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
                            <td class="px-4 py-3 text-right">
                                <span :class="item.qty <= 25 ? 'text-red-500 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white'">{{ item.qty }}</span>
                            </td>
                            <!-- Stock Worth cell — respects showWorth toggle -->
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
            <div class="bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700 transition-colors">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Import Items CSV</h3>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition">✕</button>
                </div>
                <form @submit.prevent="submitImport" class="px-5 py-4 space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Upload your CSV file containing columns: <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">item_name</code>, <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">barcode_number</code>, <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">category_name</code>, <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">qty</code>, <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">Buy_price</code>, <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">price</code>, <code class="bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded text-emerald-600 dark:text-emerald-400">wholesale</code>.
                    </p>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Select CSV File</label>
                        <input
                            type="file"
                            accept=".csv, .txt"
                            @change="importForm.csv_file = $event.target.files[0]"
                            class="w-full bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white text-xs px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 outline-none file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white"
                            required
                        />
                        <p v-if="importForm.errors.csv_file" class="text-red-500 text-xs mt-1">{{ importForm.errors.csv_file }}</p>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showImportModal = false" class="flex-1 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-600 transition">Cancel</button>
                        <button type="submit" :disabled="importForm.processing || !importForm.csv_file" class="flex-1 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition disabled:opacity-40 shadow-xs">
                            {{ importForm.processing ? 'Importing…' : 'Upload & Import' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
