<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    items:   Object,
    filters: Object,
})

const { format } = useCurrency()
const search = ref(props.filters?.search ?? '')

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
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800 flex-shrink-0">
            <h1 class="text-lg font-bold text-white">Items</h1>
            <Link :href="route('pos.items.create')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Item
            </Link>
        </div>

        <!-- Search -->
        <div class="px-6 py-3 border-b border-slate-700 flex-shrink-0">
            <div class="flex gap-2">
                <input v-model="search" @keydown.enter="doSearch" type="text"
                    placeholder="Search by name or barcode…"
                    class="flex-1 bg-slate-700 text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-600 focus:border-emerald-500 transition" />
                <button @click="doSearch" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded-lg transition">Search</button>
            </div>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 border-b border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Item</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Barcode</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Category</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-medium">Price</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-medium">Qty</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-medium">Expiry</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 text-white font-medium flex items-center gap-2.5">
                                <div v-if="item.image_url" class="w-8 h-8 rounded bg-slate-700 overflow-hidden flex items-center justify-center border border-slate-650 flex-shrink-0">
                                    <img :src="item.image_url" class="object-cover w-full h-full" alt="Item Thumbnail" />
                                </div>
                                <div v-else class="w-8 h-8 rounded bg-slate-700/50 flex items-center justify-center border border-slate-700 flex-shrink-0 text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <span>{{ item.item_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ item.barcode_number }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ item.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-emerald-400">{{ format(item.price) }}</td>
                            <td class="px-4 py-3 text-right">
                                <span :class="item.qty <= 25 ? 'text-red-400' : 'text-white'">{{ item.qty }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs" :class="expiryClass(item.expiry_date)">
                                {{ item.expiry_date ? dayjs(item.expiry_date).format('DD-MMM-YYYY') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('pos.items.edit', item.id)"
                                        class="text-xs text-blue-400 hover:text-blue-300 transition">Edit</Link>
                                    <button @click="destroy(item.id)"
                                        class="text-xs text-red-400 hover:text-red-300 transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.data.length">
                            <td colspan="7" class="px-4 py-12 text-center text-slate-500">No items found.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="items.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-700 text-sm text-slate-400">
                    <span>Showing {{ items.from }}–{{ items.to }} of {{ items.total }}</span>
                    <div class="flex gap-1">
                        <Link v-if="items.prev_page_url" :href="items.prev_page_url"
                            class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Prev</Link>
                        <Link v-if="items.next_page_url" :href="items.next_page_url"
                            class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600 transition">Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
