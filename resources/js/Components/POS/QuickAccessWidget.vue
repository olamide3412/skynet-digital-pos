<script setup>
import { ref, computed } from 'vue'
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    quickAccess: {
        type: Object,
        default: () => ({
            mode:       'top_sellers',
            title:      '⚡ Top Sellers',
            subtitle:   'Most purchased items this week',
            items:      [],
            categories: [],
        }),
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    purchaseType: {
        type: String,
        default: 'Consumer',
    },
})

const emit = defineEmits(['select'])
const { format } = useCurrency()

const activeCategoryIndex = ref(0)

const currentItems = computed(() => {
    if (props.quickAccess?.mode === 'category_shortcuts' && Array.isArray(props.quickAccess?.categories) && props.quickAccess.categories.length > 0) {
        return props.quickAccess.categories[activeCategoryIndex.value]?.items || []
    }
    return props.quickAccess?.items || []
})

function getItemQty(item) {
    if (!item) return 0
    if (item.front_store_qty !== undefined && item.front_store_qty !== null) return Number(item.front_store_qty)
    if (item.qty !== undefined && item.qty !== null) return Number(item.qty)
    if (item.total_qty !== undefined && item.total_qty !== null) return Number(item.total_qty)
    return 0
}

function isLowStock(item) {
    const qty = getItemQty(item)
    const threshold = Number(props.settings?.out_of_stock ?? 25)
    return qty > 0 && qty <= threshold
}

function isOutOfStock(item) {
    return getItemQty(item) <= 0
}

function getItemPrice(item) {
    if (!item) return 0
    if (props.purchaseType === 'Wholesale' && Number(item.wholesale_price) > 0) {
        return Number(item.wholesale_price)
    }
    return Number(item.display_price ?? item.price ?? 0)
}
</script>

<template>
    <!-- When widget is disabled or has no items and mode is none -->
    <div v-if="quickAccess?.mode === 'none' || (!currentItems.length && !quickAccess?.categories?.length)" class="text-center text-slate-400 dark:text-slate-500 py-16 px-4">
        <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 dark:text-slate-500 shadow-xs">
            <svg class="w-8 h-8 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Search or scan a barcode to add items to cart</p>
        <p class="text-xs mt-1 text-slate-400 dark:text-slate-500">
            Press <kbd class="bg-slate-200 dark:bg-slate-700 px-1.5 py-0.5 rounded text-[11px] font-mono text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600">F2</kbd> to focus search
        </p>
    </div>

    <!-- Active Quick Access Widget -->
    <div v-else class="space-y-3">
        <!-- Header bar -->
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-emerald-500/10 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs font-bold">
                    <span v-if="quickAccess?.mode === 'top_sellers'">⚡</span>
                    <span v-else-if="quickAccess?.mode === 'recently_sold'">🕒</span>
                    <span v-else-if="quickAccess?.mode === 'pinned_items'">📌</span>
                    <span v-else-if="quickAccess?.mode === 'category_shortcuts'">🏷️</span>
                    <span v-else>✨</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                        {{ quickAccess?.title || 'Quick Access' }}
                    </h3>
                    <p v-if="quickAccess?.subtitle" class="text-[10px] text-slate-500 dark:text-slate-400">
                        {{ quickAccess.subtitle }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                    {{ currentItems.length }} {{ currentItems.length === 1 ? 'item' : 'items' }}
                </span>
            </div>
        </div>

        <!-- Category Shortcut Pills (Only in category_shortcuts mode) -->
        <div v-if="quickAccess?.mode === 'category_shortcuts' && quickAccess.categories?.length" class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
            <button
                v-for="(cat, idx) in quickAccess.categories"
                :key="cat.id"
                type="button"
                @click="activeCategoryIndex = idx"
                :class="[
                    'px-3 py-1 rounded-lg text-xs font-bold whitespace-nowrap transition cursor-pointer flex items-center gap-1.5 shadow-2xs',
                    activeCategoryIndex === idx
                        ? 'bg-emerald-600 text-white shadow-emerald-900/30'
                        : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/60'
                ]"
            >
                <span>{{ cat.name }}</span>
                <span class="text-[10px] opacity-80 bg-black/15 dark:bg-white/15 px-1.5 py-0.2 rounded-full font-mono">
                    {{ cat.items?.length || 0 }}
                </span>
            </button>
        </div>

        <!-- Items Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5">
            <button
                v-for="item in currentItems"
                :key="item.id"
                type="button"
                :disabled="isOutOfStock(item)"
                @click="emit('select', item)"
                class="group relative flex flex-col justify-between p-2.5 rounded-xl text-left transition-all duration-200 cursor-pointer overflow-hidden border shadow-2xs select-none"
                :class="[
                    isOutOfStock(item)
                        ? 'bg-slate-100/70 dark:bg-slate-800/40 border-slate-200 dark:border-slate-800 opacity-50 cursor-not-allowed'
                        : 'bg-white dark:bg-slate-800/90 border-slate-200/90 dark:border-slate-700/80 hover:border-emerald-500/60 dark:hover:border-emerald-400/60 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]'
                ]"
            >
                <!-- Top Section: Image & Stock Badge -->
                <div class="flex items-start gap-2 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700/60 border border-slate-200 dark:border-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden relative">
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.item_name"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            loading="lazy"
                        />
                        <span v-else class="text-base select-none opacity-70">📦</span>
                    </div>

                    <div class="flex-1 min-w-0 flex flex-col justify-between h-10">
                        <div class="flex items-center justify-between gap-1">
                            <!-- Stock Indicator -->
                            <span
                                v-if="isOutOfStock(item)"
                                class="text-[9px] font-extrabold px-1.5 py-0.2 rounded bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 border border-rose-300 dark:border-rose-800 leading-tight"
                            >
                                Out of Stock
                            </span>
                            <span
                                v-else-if="isLowStock(item)"
                                class="text-[9px] font-extrabold px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 border border-amber-300 dark:border-amber-800 leading-tight"
                            >
                                Low: {{ getItemQty(item) }}
                            </span>
                            <span
                                v-else
                                class="text-[9px] font-semibold text-slate-500 dark:text-slate-400"
                            >
                                Stock: <strong class="font-mono text-slate-700 dark:text-slate-200">{{ getItemQty(item) }}</strong>
                            </span>

                            <!-- IMEI Tracker pill -->
                            <span
                                v-if="item.is_imei_tracked"
                                class="text-[8px] font-black uppercase px-1 py-0.2 rounded bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800"
                                title="Device / IMEI tracked item"
                            >
                                IMEI
                            </span>
                        </div>

                        <!-- Barcode snippet -->
                        <span v-if="item.barcode_number" class="text-[9px] font-mono text-slate-400 dark:text-slate-500 truncate">
                            #{{ item.barcode_number }}
                        </span>
                    </div>
                </div>

                <!-- Middle Section: Product Name -->
                <div class="flex-1">
                    <p class="text-xs font-bold text-slate-800 dark:text-slate-100 leading-snug line-clamp-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                        {{ item.item_name }}
                    </p>
                </div>

                <!-- Bottom Section: Price & Unit Tag -->
                <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 tracking-tight">
                        {{ format(getItemPrice(item)) }}
                    </span>

                    <div class="flex items-center gap-1">
                        <!-- Multi-unit pill indicator -->
                        <span
                            v-if="item.units && item.units.length > 0"
                            class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600"
                            title="Multiple units available"
                        >
                            +{{ item.units.length }} units
                        </span>
                        <span
                            v-else-if="item.unit_label && item.unit_label.toLowerCase() !== 'unit'"
                            class="text-[9px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            / {{ item.unit_label }}
                        </span>

                        <!-- Quick Add Plus Icon -->
                        <div class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center text-xs font-bold transition-colors">
                            +
                        </div>
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>
