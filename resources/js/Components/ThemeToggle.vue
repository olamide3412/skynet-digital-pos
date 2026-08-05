<script setup>
import { ref, onMounted, computed } from 'vue'
import { useThemeStore } from '@/Stores/themeStore'

const themeStore = useThemeStore()
const theme = ref('light')

onMounted(() => {
  theme.value = themeStore.theme || 'light'
})

const themeLabel = computed(() => {
  if (theme.value === 'light') return 'Light Mode'
  if (theme.value === 'dark') return 'Dark Mode'
  return 'System Default'
})

const toggleTheme = () => {
  let newTheme = 'light'
  if (theme.value === 'light') {
    newTheme = 'dark'
  } else if (theme.value === 'dark') {
    newTheme = 'system'
  } else {
    newTheme = 'light'
  }

  theme.value = newTheme
  themeStore.setTheme(newTheme)
}
</script>

<template>
    <div class="inline-flex items-center">
      <button
        @click="toggleTheme"
        type="button"
        class="p-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition flex items-center gap-1.5 text-xs font-medium shadow-xs cursor-pointer select-none"
        :title="`Current: ${themeLabel}. Click to switch theme.`"
      >
        <!-- Sun icon for Light -->
        <svg v-if="theme === 'light'" class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="5" stroke-width="2"/>
          <path stroke-linecap="round" stroke-width="2" d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
        </svg>

        <!-- Moon icon for Dark -->
        <svg v-else-if="theme === 'dark'" class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
        </svg>

        <!-- System/Monitor icon -->
        <svg v-else class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"/>
          <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"/>
          <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"/>
        </svg>

        <span class="hidden sm:inline text-xs font-medium">{{ themeLabel }}</span>
      </button>
    </div>
</template>
