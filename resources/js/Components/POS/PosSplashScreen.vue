<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    branchName: {
        type: String,
        default: 'Skynet POS Terminal',
    },
    branchLogo: {
        type: String,
        default: null,
    },
    tagline: {
        type: String,
        default: 'Point of Sale & Checkout',
    },
    isReady: {
        type: Boolean,
        default: false,
    },
    syncStatus: {
        type: String,
        default: 'syncing', // 'connecting' | 'syncing' | 'ready'
    },
})

const emit = defineEmits(['finish'])

const progress = ref(15)
const currentStepText = ref('Connecting to store register...')
const showSlowNotice = ref(false)
const isVisible = ref(true)

let progressInterval = null
let slowTimer = null

onMounted(() => {
    // Step 1: Connecting
    progressInterval = setInterval(() => {
        if (progress.value < 85 && !props.isReady) {
            progress.value += Math.floor(Math.random() * 8) + 4
            if (progress.value > 30 && progress.value < 65) {
                currentStepText.value = 'Synchronizing catalogue, stock & pricing...'
            } else if (progress.value >= 65) {
                currentStepText.value = 'Preparing quick access & checkout register...'
            }
        }
    }, 180)

    // Slow connection reminder threshold (after 3.5 seconds)
    slowTimer = setTimeout(() => {
        if (!props.isReady) {
            showSlowNotice.value = true
        }
    }, 3500)
})

onUnmounted(() => {
    if (progressInterval) clearInterval(progressInterval)
    if (slowTimer) clearTimeout(slowTimer)
})

// Watch for readiness from the parent POS component
import { watch } from 'vue'
watch(
    () => props.isReady,
    (ready) => {
        if (ready) {
            progress.value = 100
            currentStepText.value = 'Register ready! Launching checkout...'
            if (progressInterval) clearInterval(progressInterval)
            
            // Brief pause to display 100% complete before clean fade-out
            setTimeout(() => {
                isVisible.value = false
                setTimeout(() => {
                    emit('finish')
                }, 400)
            }, 300)
        }
    },
    { immediate: true }
)
</script>

<template>
    <Transition name="pos-splash">
        <div
            v-if="isVisible"
            class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-slate-100/95 dark:bg-slate-900/95 text-slate-900 dark:text-white select-none overflow-hidden transition-colors duration-300 backdrop-blur-xs"
        >
            <!-- Background ambient glow -->
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-purple-500/10 dark:bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-indigo-500/10 dark:bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col items-center max-w-sm px-6 text-center space-y-6">
                <!-- Branch Logo & Glowing Ring -->
                <div class="relative flex items-center justify-center">
                    <div class="absolute -inset-2 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-3xl opacity-30 dark:opacity-40 blur-lg animate-pulse"></div>
                    <div class="relative w-24 h-24 rounded-2xl bg-white dark:bg-slate-800/90 border border-slate-200/90 dark:border-slate-700/80 backdrop-blur-md flex items-center justify-center p-3 shadow-xl dark:shadow-2xl">
                        <img
                            v-if="branchLogo"
                            :src="branchLogo"
                            :alt="branchName"
                            class="w-full h-full object-contain filter drop-shadow"
                            @error="(e) => e.target.style.display = 'none'"
                        />
                        <span v-else class="text-3xl font-black bg-gradient-to-br from-purple-600 to-indigo-600 dark:from-purple-400 dark:to-indigo-400 bg-clip-text text-transparent">
                            POS
                        </span>
                    </div>
                </div>

                <!-- Titles -->
                <div class="space-y-1.5">
                    <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center justify-center gap-2">
                        <span>{{ branchName }}</span>
                    </h2>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ tagline }}
                    </p>
                </div>

                <!-- Status Progress Area -->
                <div class="w-full space-y-3 pt-2">
                    <!-- Progress Bar Track -->
                    <div class="w-full h-2.5 bg-slate-200/90 dark:bg-slate-800 rounded-full overflow-hidden border border-slate-300/70 dark:border-slate-700/60 p-0.5 shadow-inner">
                        <div
                            class="h-full bg-gradient-to-r from-purple-600 via-indigo-600 to-emerald-500 rounded-full transition-all duration-300 ease-out shadow-xs"
                            :style="{ width: `${progress}%` }"
                        ></div>
                    </div>

                    <!-- Progress Details -->
                    <div class="flex items-center justify-between text-[11px] font-medium px-0.5">
                        <span class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300">
                            <span class="w-2 h-2 rounded-full bg-purple-500 animate-ping inline-block"></span>
                            <span>{{ currentStepText }}</span>
                        </span>
                        <span class="font-mono text-purple-600 dark:text-purple-300 font-bold">{{ Math.min(100, Math.round(progress)) }}%</span>
                    </div>
                </div>

                <!-- Slow Connection Notification Fallback -->
                <Transition name="fade">
                    <div
                        v-if="showSlowNotice && !isReady"
                        class="p-2.5 rounded-xl bg-white/90 dark:bg-slate-800/90 border border-amber-300/80 dark:border-amber-700/80 backdrop-blur-sm text-[11px] text-amber-800 dark:text-amber-300 flex items-center gap-2 shadow-md"
                    >
                        <span>⏳</span>
                        <span>Finalizing offline catalogue cache, hang tight...</span>
                    </div>
                </Transition>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.pos-splash-leave-active {
    transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1), transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.pos-splash-leave-to {
    opacity: 0;
    transform: scale(0.985);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
