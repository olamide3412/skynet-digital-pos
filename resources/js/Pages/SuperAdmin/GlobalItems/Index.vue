<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    items: Object,
    branches: Array,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const showCreateModal = ref(false)
const selectedBranchSlug = ref('')
const selectedGlobalIds = ref([])

const doSearch = () => {
    router.get(route('superadmin.global-items.index'), { search: search.value }, { preserveState: true, replace: true })
}

const form = useForm({
    item_name: '',
    barcode_number: '',
    category_hint: '',
    buy_price: 0,
    price: 0,
    wholesale_price: 0,
    unit_label: 'unit',
    pack_label: 'pack',
    carton_label: 'carton',
    units_per_pack: 1,
    packs_per_carton: 1,
    item_description: '',
})

const submitCreate = () => {
    form.post(route('superadmin.global-items.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            form.reset()
        },
    })
}

const pushItemToBranch = (globalItemId, branchSlug) => {
    if (!branchSlug) return
    router.post(route('superadmin.global-items.import', [globalItemId, branchSlug]))
}

const pushBatchToBranch = () => {
    if (!selectedBranchSlug.value || !selectedGlobalIds.value.length) return
    router.post(route('superadmin.branches.items.import-batch', selectedBranchSlug.value), {
        global_item_ids: selectedGlobalIds.value,
    }, {
        onSuccess: () => {
            selectedGlobalIds.value = []
        }
    })
}

const pushAllToBranch = () => {
    if (!selectedBranchSlug.value) return
    const branchName = props.branches.find(b => b.slug === selectedBranchSlug.value)?.name ?? selectedBranchSlug.value
    if (!confirm(`Push ALL ${props.items.total} Master Pool items into branch "${branchName}"?`)) return
    router.post(route('superadmin.branches.items.import-all', selectedBranchSlug.value))
}

const toggleSelectAll = () => {
    if (selectedGlobalIds.value.length === props.items.data.length) {
        selectedGlobalIds.value = []
    } else {
        selectedGlobalIds.value = props.items.data.map(i => i.id)
    }
}

const deleteItem = (item) => {
    if (confirm(`Remove "${item.item_name}" from Global Master Pool?`)) {
        router.delete(route('superadmin.global-items.destroy', item.id))
    }
}
</script>

<template>
    <Head title="Global Item Pool - Super Admin" />
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Global Master Item Pool</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Master product catalog. Select items and push directly into branch catalogs below.</p>
            </div>
            <button
                @click="showCreateModal = true"
                class="w-full sm:w-auto px-4 py-2.5 bg-theme hover:opacity-90 text-white font-bold rounded-xl text-xs transition shadow-md"
            >
                + Add Master Item
            </button>
        </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-rose-500/10 border border-rose-500/30 text-rose-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <!-- Toolbar: Search & Quick Push to Branch -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-4 rounded-xl">
                <!-- Search -->
                <div class="flex items-center space-x-2 w-full md:w-auto flex-1 max-w-md">
                    <div class="relative flex-1">
                        <input
                            v-model="search"
                            @keydown.enter="doSearch"
                            type="text"
                            placeholder="Search item name, barcode, or category…"
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

                <!-- Push to Branch Selector & Actions -->
                <div v-if="branches.length" class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                    <span class="text-xs text-slate-400">Target Branch:</span>
                    <select v-model="selectedBranchSlug" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500">
                        <option value="" disabled>Select branch to push to…</option>
                        <option v-for="b in branches" :key="b.id" :value="b.slug">{{ b.name }}</option>
                    </select>

                    <button
                        @click="pushBatchToBranch"
                        :disabled="!selectedBranchSlug || !selectedGlobalIds.length"
                        class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-indigo-600/20"
                    >
                        Push Selected ({{ selectedGlobalIds.length }})
                    </button>

                    <button
                        @click="pushAllToBranch"
                        :disabled="!selectedBranchSlug"
                        class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-emerald-600/20"
                    >
                        Push All Items
                    </button>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase font-semibold text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3 w-10 text-center">
                                <input
                                    type="checkbox"
                                    :checked="selectedGlobalIds.length === items.data.length && items.data.length > 0"
                                    @change="toggleSelectAll"
                                    class="rounded border-slate-700 bg-slate-950 text-indigo-500"
                                />
                            </th>
                            <th class="px-6 py-3">Item Name</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Barcode</th>
                            <th class="px-6 py-3">Cost Price</th>
                            <th class="px-6 py-3">Selling Price</th>
                            <th class="px-6 py-3">Push to Branch</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    :value="item.id"
                                    v-model="selectedGlobalIds"
                                    class="rounded border-slate-700 bg-slate-950 text-indigo-500"
                                />
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-100">
                                <div>{{ item.item_name }}</div>
                                <div v-if="item.item_description" class="text-xs text-slate-500 font-normal line-clamp-1">{{ item.item_description }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span class="px-2.5 py-1 bg-indigo-500/10 text-indigo-300 border border-indigo-500/30 rounded-md font-medium">
                                    {{ item.category_hint || 'General' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-400">{{ item.barcode_number || 'N/A' }}</td>
                            <td class="px-6 py-4 font-mono text-slate-300">₦{{ Number(item.buy_price).toLocaleString() }}</td>
                            <td class="px-6 py-4 font-mono text-emerald-400 font-semibold">₦{{ Number(item.price).toLocaleString() }}</td>
                            <td class="px-6 py-4">
                                <select
                                    @change="e => { pushItemToBranch(item.id, e.target.value); e.target.value = ''; }"
                                    class="bg-slate-950 border border-slate-800 text-slate-300 rounded-lg px-2.5 py-1 text-xs focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="" disabled selected>Push to branch…</option>
                                    <option v-for="b in branches" :key="b.id" :value="b.slug">Push to {{ b.name }}</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="deleteItem(item)" class="text-xs text-slate-500 hover:text-rose-400 transition-colors px-2 py-1">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!items.data.length">
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">No items found in the global pool.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination Footer (25 per page) -->
                <div v-if="items.total > 0" class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-slate-800 text-xs text-slate-400 gap-3 bg-slate-950/40">
                    <div>
                        Showing <span class="font-semibold text-slate-200">{{ items.from }}</span> to <span class="font-semibold text-slate-200">{{ items.to }}</span> of <span class="font-semibold text-slate-200">{{ items.total }}</span> master items (25 per page)
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

        <!-- Add Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto p-6 space-y-4 shadow-2xl my-auto">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="font-bold text-slate-100">Add Master Item</h2>
                    <button @click="showCreateModal = false" class="text-slate-500 hover:text-slate-300 text-xl">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Item Name *</label>
                        <input v-model="form.item_name" type="text" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Barcode Number</label>
                            <input v-model="form.barcode_number" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Category Hint</label>
                            <input v-model="form.category_hint" type="text" placeholder="e.g. Beverages" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Cost Price (₦) *</label>
                            <input v-model="form.buy_price" type="number" step="0.01" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Selling Price (₦) *</label>
                            <input v-model="form.price" type="number" step="0.01" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" />
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 space-y-2">
                        <h4 class="text-xs font-bold text-indigo-400 uppercase">Unit Conversion Setup</h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <label class="text-slate-400">Packs per Carton</label>
                                <input v-model="form.packs_per_carton" type="number" min="1" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 mt-1 text-slate-100" />
                            </div>
                            <div>
                                <label class="text-slate-400">Units per Pack</label>
                                <input v-model="form.units_per_pack" type="number" min="1" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 mt-1 text-slate-100" />
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="showCreateModal = false" class="text-xs text-slate-400 hover:text-slate-200">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition-colors">
                            Save Master Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
</template>
