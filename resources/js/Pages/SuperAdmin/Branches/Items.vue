<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branch: Object,
    items: Object,          // Paginated branch items
    globalItems: Array,     // [{ id, item_name, barcode_number, buy_price, price, category_hint, brand_hint, is_imported }]
    categories: Array,
    brands: Array,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const masterSearch = ref('')
const showImportModal = ref(false)
const selectedGlobalIds = ref([])

const doSearch = () => {
    router.get(route('superadmin.branches.items.index', { branch: props.branch.slug }), { search: search.value }, { preserveState: true, replace: true })
}

// Master items available for import
const filteredMasterItems = computed(() => {
    if (!masterSearch.value) return props.globalItems
    const q = masterSearch.value.toLowerCase()
    return props.globalItems.filter(item =>
        item.item_name.toLowerCase().includes(q) ||
        (item.barcode_number && item.barcode_number.toLowerCase().includes(q)) ||
        (item.category_hint && item.category_hint.toLowerCase().includes(q)) ||
        (item.brand_hint && item.brand_hint.toLowerCase().includes(q))
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
    importForm.post(route('superadmin.branches.items.import-batch', { branch: props.branch.slug }), {
        onSuccess: () => {
            showImportModal.value = false
            selectedGlobalIds.value = []
        },
    })
}

const importAllForm = useForm({})
const submitImportAll = () => {
    if (!confirm(`Import ALL ${props.globalItems.length} Master Pool items into ${props.branch.name}?`)) return
    importAllForm.post(route('superadmin.branches.items.import-all', { branch: props.branch.slug }), {
        onSuccess: () => {
            showImportModal.value = false
            selectedGlobalIds.value = []
        },
    })
}

const removeItem = (item) => {
    if (item.is_linked_to_ecommerce) {
        alert(`Cannot remove "${item.item_name}". It is currently linked to an active product in your online Ecommerce store. Please unlink it from Ecommerce first.`)
        return
    }
    if (confirm(`Remove "${item.item_name}" from ${props.branch.name} catalog?`)) {
        router.delete(route('superadmin.branches.items.destroy', { branch: props.branch.slug, item: item.id }))
    }
}
</script>

<template>
    <Head :title="`Catalog & Master Import — ${branch.name}`" />
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <Link :href="route('superadmin.branches.index')"
                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline mb-1 inline-flex items-center gap-1 font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to Branches
                </Link>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Branch Catalog — {{ branch.name }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ branch.slug }} · {{ items.total }} item(s) in branch catalog</p>
            </div>
            <div class="flex items-center space-x-3">
                <button
                    @click="showImportModal = true"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 flex items-center space-x-2 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Import from Global Master Pool</span>
                </button>
                <button
                    @click="submitImportAll"
                    :disabled="importAllForm.processing"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-600/20 disabled:opacity-50 flex items-center space-x-2 cursor-pointer"
                >
                    <span>Import All ({{ globalItems.length }}) Items</span>
                </button>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
            class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 rounded-xl px-4 py-3 text-sm font-medium">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error"
            class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-300 rounded-xl px-4 py-3 text-sm font-medium">
            {{ $page.props.flash.error }}
        </div>

        <!-- Search Bar -->
        <div class="flex items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="search"
                    @keydown.enter="doSearch"
                    type="text"
                    placeholder="Search items in this branch catalog…"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                />
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <button @click="doSearch" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 transition cursor-pointer">
                Search
            </button>
        </div>

        <!-- Empty State -->
        <div v-if="!items.data.length" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center text-slate-500 dark:text-slate-400 space-y-4 shadow-xs">
            <svg class="w-12 h-12 mx-auto text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
            </svg>
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-200">No Items in {{ branch.name }} Catalog</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">
                    This branch currently has no inventory items. Click <strong>"Import from Global Master Pool"</strong> above to select and push master items into this branch!
                </p>
            </div>
            <button @click="showImportModal = true" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 cursor-pointer">
                Import Master Items Now
            </button>
        </div>

        <!-- Branch Items Table -->
        <div v-else class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Item Name</th>
                            <th class="px-6 py-3.5">Category</th>
                            <th class="px-6 py-3.5">Brand</th>
                            <th class="px-6 py-3.5">Barcode</th>
                            <th class="px-6 py-3.5">Cost Price</th>
                            <th class="px-6 py-3.5">Selling Price</th>
                            <th class="px-6 py-3.5">Front Store</th>
                            <th class="px-6 py-3.5">Back Store</th>
                            <th class="px-6 py-3.5">Total Qty</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span>{{ item.item_name }}</span>
                                    <span
                                        v-if="item.is_linked_to_ecommerce"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                        title="Linked to active Online Ecommerce Store product. Deletion is locked."
                                    >
                                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        Online Store
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span v-if="item.category" class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 rounded border border-indigo-200 dark:border-indigo-500/20 font-medium">
                                    {{ item.category.name }}
                                </span>
                                <span v-else class="text-slate-400 dark:text-slate-500 italic">General</span>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span v-if="item.brand" class="px-2 py-0.5 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 rounded border border-amber-200 dark:border-amber-500/20 font-medium">
                                    {{ item.brand.name }}
                                </span>
                                <span v-else class="text-slate-400 dark:text-slate-500">-</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ item.barcode_number || 'N/A' }}</td>
                            <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-300">₦{{ Number(item.buy_price).toLocaleString() }}</td>
                            <td class="px-6 py-4 font-mono text-emerald-600 dark:text-emerald-400 font-semibold">₦{{ Number(item.price).toLocaleString() }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-700 dark:text-slate-200">{{ item.front_store_qty }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-700 dark:text-slate-200">{{ item.back_store_qty }}</td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-slate-100 text-xs">{{ item.total_qty }}</td>
                            <td class="px-6 py-4 text-right">
                                <span
                                    v-if="item.is_linked_to_ecommerce"
                                    class="text-xs font-semibold text-amber-500/80 dark:text-amber-400/80 inline-flex items-center gap-1 cursor-not-allowed px-2 py-1"
                                    title="Cannot remove: Linked to active Online Ecommerce product"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Locked
                                </span>
                                <button v-else @click="removeItem(item)" class="text-xs font-semibold text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition px-2 py-1 cursor-pointer">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="items.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 gap-3 bg-slate-50/50 dark:bg-slate-950/40">
                <div>
                    Showing <span class="font-semibold text-slate-900 dark:text-slate-200">{{ items.from }}</span> to <span class="font-semibold text-slate-900 dark:text-slate-200">{{ items.to }}</span> of <span class="font-semibold text-slate-900 dark:text-slate-200">{{ items.total }}</span> branch items
                </div>
                <div class="flex items-center space-x-1">
                    <template v-for="(link, key) in items.links" :key="key">
                        <div v-if="link.url === null"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-600 cursor-not-allowed select-none bg-slate-100 dark:bg-slate-900"
                            v-html="link.label"
                        />
                        <Link v-else
                            :href="link.url"
                            class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition"
                            :class="link.active
                                ? 'bg-indigo-600 border-indigo-600 text-white font-bold'
                                : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ── Import Global Master Items Modal ────────────────────────────────────── -->
        <div v-if="showImportModal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-3xl w-full p-6 space-y-4 shadow-2xl my-8 max-h-[90vh] flex flex-col">

                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 flex-shrink-0">
                    <div>
                        <h2 class="font-bold text-base text-slate-900 dark:text-slate-100">Import Master Items to {{ branch.name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Select items from the Global Master Pool to push into this branch</p>
                    </div>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl font-bold">&times;</button>
                </div>

                <!-- Master Search & Shortcuts -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0">
                    <input
                        v-model="masterSearch"
                        type="text"
                        placeholder="Filter master items by name, barcode, category, or brand…"
                        class="w-full sm:w-72 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
                    />
                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        <button @click="selectAllAvailable" type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer">
                            Select Available ({{ availableMasterItems.length }})
                        </button>
                        <button @click="deselectAll" type="button" class="text-xs text-slate-600 dark:text-slate-400 hover:underline px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer">
                            Deselect All
                        </button>
                    </div>
                </div>

                <!-- Items Grid List -->
                <div class="flex-1 overflow-y-auto max-h-80 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-950/60 p-2 divide-y divide-slate-100 dark:divide-slate-800/40">
                    <div v-for="gItem in filteredMasterItems" :key="gItem.id"
                        class="flex items-center justify-between p-2.5 rounded-lg text-xs transition-colors"
                        :class="gItem.is_imported ? 'opacity-60 bg-slate-100/50 dark:bg-slate-900/40' : selectedGlobalIds.includes(gItem.id) ? 'bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-200 dark:border-indigo-500/30' : 'hover:bg-white dark:hover:bg-slate-900'"
                    >
                        <div class="flex items-center space-x-3">
                            <input
                                v-if="!gItem.is_imported"
                                type="checkbox"
                                :value="gItem.id"
                                :checked="selectedGlobalIds.includes(gItem.id)"
                                @change="toggleGlobalItem(gItem.id)"
                                class="rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-indigo-600 focus:ring-0"
                            />
                            <div v-else class="text-emerald-600 dark:text-emerald-400 font-bold text-xs">✓</div>

                            <div>
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ gItem.item_name }}</div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
                                    Barcode: {{ gItem.barcode_number || 'N/A' }} · Category: {{ gItem.category_hint || 'General' }} <span v-if="gItem.brand_hint">· Brand: {{ gItem.brand_hint }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-right flex items-center space-x-4">
                            <div>
                                <div class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">₦{{ Number(gItem.price).toLocaleString() }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">Cost: ₦{{ Number(gItem.buy_price).toLocaleString() }}</div>
                            </div>
                            <span v-if="gItem.is_imported" class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 text-[10px] font-semibold rounded">
                                Already in Branch
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
                    <div class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ selectedGlobalIds.length }}</span> item(s) selected
                    </div>
                    <div class="flex items-center space-x-3">
                        <button type="button" @click="showImportModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition">
                            Cancel
                        </button>
                        <button
                            @click="submitImportBatch"
                            :disabled="!selectedGlobalIds.length || importForm.processing"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-600/20 cursor-pointer"
                        >
                            {{ importForm.processing ? 'Importing…' : `Import Selected (${selectedGlobalIds.length})` }}
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
