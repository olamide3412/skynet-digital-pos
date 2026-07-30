<script setup>
import { useCurrency } from '@/Composables/useCurrency'

const props = defineProps({
    itemGrids: { type: Array, default: () => [] },
    settings:  { type: Object, required: true },
})
const emit = defineEmits(['select'])
const { format } = useCurrency()

function isExpired(item) {
    if (!item?.expiry_date) return false
    return new Date(item.expiry_date) < new Date()
}

function isLow(item) {
    return item?.qty <= (props.settings.out_of_stock ?? 25)
}
</script>

<template>
    <div class="grid grid-cols-3 gap-2 p-1">
        <button
            v-for="grid in itemGrids"
            :key="grid.id"
            :disabled="!grid.item || isExpired(grid.item) || grid.item.qty <= 0"
            @click="grid.item && emit('select', grid.item)"
            :style="{
                backgroundColor: grid.back_color || '#1e293b',
                color: grid.fore_color || '#f1f5f9',
            }"
            class="relative group flex flex-col items-center justify-center rounded-xl p-3 h-24 text-center transition hover:opacity-95 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed shadow-md border border-white/5 overflow-hidden"
        >
            <!-- Background Image -->
            <img v-if="grid.item?.image_url" :src="grid.item.image_url" class="absolute inset-0 w-full h-full object-cover opacity-45 group-hover:scale-105 transition duration-300" />
            <!-- Gradient overlay for text readability -->
            <div v-if="grid.item?.image_url" class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/60 to-transparent"></div>

            <div class="relative z-10 w-full flex flex-col items-center justify-center">
                <!-- Item Name -->
                <p class="text-xs font-bold leading-tight line-clamp-2 mb-1 drop-shadow">
                    {{ grid.item?.item_name || grid.menu_name || 'Empty' }}
                </p>
                <!-- Price -->
                <p v-if="grid.item" class="text-xs opacity-90 font-semibold drop-shadow">
                    {{ format(grid.item.price) }}
                </p>
            </div>
            <!-- Badges -->
            <div class="absolute top-1 right-1 flex flex-col gap-0.5 z-10">
                <span v-if="grid.item && isLow(grid.item)" class="text-[9px] bg-red-500 text-white px-1 rounded leading-tight">LOW</span>
                <span v-if="grid.item && isExpired(grid.item)" class="text-[9px] bg-gray-800 text-red-400 px-1 rounded leading-tight">EXP</span>
            </div>
            <!-- Qty badge -->
            <span v-if="grid.item" class="absolute bottom-1 right-1.5 text-[9px] opacity-50 z-10">
                {{ grid.item.qty }}
            </span>
        </button>

        <!-- Empty state -->
        <div v-if="!itemGrids.length" class="col-span-3 text-center text-slate-500 py-16 text-sm">
            No items configured for gallery mode.<br/>
            <span class="text-xs">Configure items in Item Grid Config.</span>
        </div>
    </div>
</template>
