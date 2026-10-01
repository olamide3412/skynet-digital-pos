<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    meta: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <div v-if="links.length > 3" class="flex items-center justify-between px-5 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
        <!-- Info -->
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Showing <span class="text-slate-800 dark:text-slate-200 font-medium">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}</span>
            of <span class="text-slate-800 dark:text-slate-200 font-medium">{{ meta.total ?? 0 }}</span> records
        </p>

        <!-- Links -->
        <div class="flex items-center gap-1">
            <template v-for="link in links" :key="link.label">
                <Link v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                        link.active
                            ? 'bg-emerald-600 text-white shadow-xs'
                            : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 hover:text-slate-900 dark:hover:text-white',
                    ]"
                    v-html="link.label"
                />
                <span v-else
                    class="px-3 py-1.5 rounded-lg text-xs text-slate-400 dark:text-slate-600 cursor-not-allowed"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
