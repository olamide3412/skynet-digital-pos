<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    branch: Object,
    items: Object,          // Paginated branch items
    globalItems: Array,     // [{ id, item_name, barcode_number, buy_price, price, category_hint, is_imported }]
    categories: Array,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const masterSearch = ref('')
const showImportModal = ref(false)
const selectedGlobalIds = ref([])

const doSearch = () => {
    router.get(route('superadmin.branches.items.index', props.branch.slug), { search: search.value }, { preserveState: true, replace: true })
}

// Master items available for import
const filteredMasterItems = computed(() => {
    if (!masterSearch.value) return props.globalItems
    const q = masterSearch.value.toLowerCase()
    return props.globalItems.filter(item =>
        item.item_name.toLowerCase().includes(q) ||
        (item.barcode_number && item.barcode_number.toLowerCase().includes(q)) ||
        (item.category_hint && item.category_hint.toLowerCase().includes(q))
    )
})

const availableMasterItems = computed(() => {
    return filteredMasterItems.value.filter(i => !i.is_imported)
})

const toggleGlobalItem = (id) => {
    const idx = selectedGlobalIds.value.indexOf(id)
    if (idx === -1) {
        selectedGlobalIds.value.push(id)
    } else {
        selectedGlobalIds.value.splice(idx, 1)
    }
}

const selectAllAvailable = () => {
    selectedGlobalIds.value = availableMasterItems.value.map(i => i.id)
}

const deselectAll = () => {
    selectedGlobalIds.value = []
}

const importForm = useForm({
    global_item_ids: [],
})

const submitImportBatch = () => {
    if (!selectedGlobalIds.value.length) return
    importForm.global_item_ids = selectedGlobalIds.value
    importForm.post(route('superadmin.branches.items.import-batch', props.branch.slug), {
        onSuccess: () => {
            showImportModal.value = false
            selectedGlobalIds.value = []
        },
    })
}

const importAllForm = useForm({})
const submitImportAll = () => {
    if (!confirm(`Import ALL ${props.globalItems.length} Master Pool items into ${props.branch.name}?`)) return
    importAllForm.post(route('superadmin.branches.items.import-all', props.branch.slug), {
        onSuccess: () => {
            showImportModal.value = false
            selectedGlobalIds.value = []
        },
    })
}

const removeItem = (item) => {
    if (confirm(`Remove "${item.item_name}" from ${props.branch.name} catalog?`)) {
        router.delete(route('superadmin.branches.items.destroy', [props.branch.slug, item.id]))
    }
}
</script>

<template>
    <Head :title="`Catalog & Master Import — ${branch.name}`" />
    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans p-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <Link :href="route('superadmin.branches.index')"
                        class="text-xs text-indigo-400 hover:text-indigo-300 mb-1 inline-block">
                        &larr; Back to Branches
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-100">Branch Catalog — {{ branch.name }}</h1>
                    <p class="text-xs text-slate-400 font-mono">{{ branch.slug }} · {{ items.total }} item(s) in branch catalog</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        @click="showImportModal = true"
                        class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition-colors shadow-lg shadow-indigo-600/20 flex items-center space-x-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Import from Global Master Pool</span>
                    </button>
                    <button
                        @click="submitImportAll"
                        :disabled="importAllForm.processing"
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl text-xs transition-colors shadow-lg shadow-emerald-600/20 disabled:opacity-50 flex items-center space-x-2"
                    >
                        <span>Import All ({{ globalItems.length }}) Items</span>
                    </button>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success"
                class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error"
                class="bg-rose-500/10 border border-rose-500/30 text-rose-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <!-- Search Bar -->
            <div class="flex items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-4 rounded-xl">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="search"
                        @keydown.enter="doSearch"
                        type="text"
                        placeholder="Search items in this branch catalog…"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <svg class="w-4 h-4 text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button @click="doSearch" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700">
                    Search
                </button>
            </div>

            <!-- Empty State -->
            <div v-if="!items.data.length" class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center text-slate-500 space-y-4">
                <svg class="w-12 h-12 mx-auto text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                </svg>
                <div>
                    <h3 class="text-lg font-bold text-slate-200">No Items in {{ branch.name }} Catalog</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        This branch currently has no inventory items. Click <strong>"Import from Global Master Pool"</strong> above to select and push master items into this branch!
                    </p>
                </div>
                <button @click="showImportModal = true" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-indigo-600/20">
                    Import Master Items Now
                </button>
            </div>

            <!-- Branch Items Table -->
            <div v-else class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase font-semibold text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3">Item Name</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Barcode</th>
                            <th class="px-6 py-3">Cost Price</th>
                            <th class="px-6 py-3">Selling Price</th>
                            <th class="px-6 py-3">Front Store</th>
                            <th class="px-6 py-3">Back Store</th>
                            <th class="px-6 py-3">Total Qty</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-100">{{ item.item_name }}</td>
                            <td class="px-6 py-4 text-xs">
                                <span v-if="item.category" class="px-2 py-0.5 bg-indigo-500/10 text-indigo-300 rounded border border-indigo-500/20">
                                    {{ item.category.name }}
                                </span>
                                <span v-else class="text-slate-500 italic">General</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-400">{{ item.barcode_number || 'N/A' }}</td>
                            <td class="px-6 py-4 font-mono text-slate-300">₦{{ Number(item.buy_price).toLocaleString() }}</td>
                            <td class="px-6 py-4 font-mono text-emerald-400 font-semibold">₦{{ Number(item.price).toLocaleString() }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-200">{{ item.front_store_qty }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-200">{{ item.back_store_qty }}</td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-100 text-xs">{{ item.total_qty }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="removeItem(item)" class="text-xs text-slate-500 hover:text-rose-400 transition-colors px-2 py-1">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination Footer -->
                <div v-if="items.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-slate-800 text-xs text-slate-400 gap-3 bg-slate-950/40">
                    <div>
                        Showing <span class="font-semibold text-slate-200">{{ items.from }}</span> to <span class="font-semibold text-slate-200">{{ items.to }}</span> of <span class="font-semibold text-slate-200">{{ items.total }}</span> branch items
                    </div>
                    <div class="flex items-center space-x-1">
                        <template v-for="(link, key) in items.links" :key="key">
                            <div v-if="link.url === null"
                                class="px-3 py-1.5 rounded-lg border border-slate-800 text-slate-600 cursor-not-allowed select-none"
                                v-html="link.label"
                            />
                            <Link v-else
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors"
                                :class="link.active
                                    ? 'bg-indigo-600 border-indigo-500 text-white font-bold'
                                    : 'border-slate-800 bg-slate-900 text-slate-300 hover:bg-slate-800 hover:text-white'"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Import Global Master Items Modal ────────────────────────────────────── -->
        <div v-if="showImportModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-3xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">

                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-lg text-slate-100">Import Master Items to {{ branch.name }}</h2>
                        <p class="text-xs text-slate-400">Select items from the Global Master Pool to push into this branch</p>
                    </div>
                    <button @click="showImportModal = false" class="text-slate-500 hover:text-slate-300 text-xl">&times;</button>
                </div>

                <!-- Master Search & Shortcuts -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0">
                    <input
                        v-model="masterSearch"
                        type="text"
                        placeholder="Filter master items by name, barcode, or category…"
                        class="w-full sm:w-72 bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        <button @click="selectAllAvailable" type="button" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-lg">
                            Select Available ({{ availableMasterItems.length }})
                        </button>
                        <button @click="deselectAll" type="button" class="text-xs text-slate-400 hover:text-slate-200 px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-lg">
                            Deselect All
                        </button>
                    </div>
                </div>

                <!-- Items Grid List -->
                <div class="flex-1 overflow-y-auto max-h-80 border border-slate-800 rounded-xl bg-slate-950/60 p-2 divide-y divide-slate-800/40">
                    <div v-for="gItem in filteredMasterItems" :key="gItem.id"
                        class="flex items-center justify-between p-2.5 rounded-lg text-xs transition-colors"
                        :class="gItem.is_imported ? 'opacity-60 bg-slate-900/40' : selectedGlobalIds.includes(gItem.id) ? 'bg-indigo-600/10 border border-indigo-500/30' : 'hover:bg-slate-900'"
                    >
                        <div class="flex items-center space-x-3">
                            <input
                                v-if="!gItem.is_imported"
                                type="checkbox"
                                :value="gItem.id"
                                :checked="selectedGlobalIds.includes(gItem.id)"
                                @change="toggleGlobalItem(gItem.id)"
                                class="rounded border-slate-700 bg-slate-900 text-indigo-500 focus:ring-0"
                            />
                            <div v-else class="text-emerald-400 font-bold text-xs">✓</div>

                            <div>
                                <div class="font-semibold text-slate-100">{{ gItem.item_name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">
                                    Barcode: {{ gItem.barcode_number || 'N/A' }} · Category: {{ gItem.category_hint || 'General' }}
                                </div>
                            </div>
                        </div>

                        <div class="text-right flex items-center space-x-4">
                            <div>
                                <div class="text-emerald-400 font-mono font-semibold">₦{{ Number(gItem.price).toLocaleString() }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">Cost: ₦{{ Number(gItem.buy_price).toLocaleString() }}</div>
                            </div>
                            <span v-if="gItem.is_imported" class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-semibold rounded">
                                Already in Branch
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="pt-3 border-t border-slate-800 flex items-center justify-between flex-shrink-0">
                    <div class="text-xs text-slate-400">
                        <span class="font-bold text-indigo-400">{{ selectedGlobalIds.length }}</span> item(s) selected
                    </div>
                    <div class="flex items-center space-x-3">
                        <button type="button" @click="showImportModal = false" class="text-xs text-slate-400 hover:text-slate-200">
                            Cancel
                        </button>
                        <button
                            @click="submitImportBatch"
                            :disabled="!selectedGlobalIds.length || importForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-semibold rounded-xl text-xs transition-colors shadow-lg shadow-indigo-600/20"
                        >
                            {{ importForm.processing ? 'Importing…' : `Import Selected (${selectedGlobalIds.length})` }}
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
