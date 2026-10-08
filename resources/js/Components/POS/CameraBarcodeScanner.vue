<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useCameraScanner } from '@/Composables/useCameraScanner'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    // Handler that takes barcode string and returns { success: boolean, item?: Object, reason?: string }
    processBarcodeFn: {
        type: Function,
        required: true,
    },
})

const emit = defineEmits(['close', 'scanned'])

// Modes: 'continuous' (default) | 'single'
const scanMode = ref('continuous')

// Last Scanned Result Strip
const lastResult = ref(null) // { success: boolean, name: string, code: string, timestamp: number }
const flashActive = ref(false)

const scanner = useCameraScanner({
    onBarcodeDetected: handleBarcodeDetected,
})

async function handleBarcodeDetected(code) {
    if (!props.isOpen) return

    // Trigger visual frame flash
    flashActive.value = true
    setTimeout(() => { flashActive.value = false }, 250)

    try {
        const result = await props.processBarcodeFn(code)

        if (result && result.success) {
            scanner.playBeep(true)
            scanner.triggerVibration()

            const itemName = result.item?.item_name || 'Item added'
            lastResult.value = {
                success: true,
                name: itemName,
                code: code,
                timestamp: Date.now(),
            }

            emit('scanned', { code, item: result.item })

            // If single scan mode, close overlay immediately
            if (scanMode.value === 'single') {
                closeModal()
            }
        } else {
            // Not found in local or server inventory
            scanner.playBeep(false)
            lastResult.value = {
                success: false,
                name: 'Item Not Found',
                code: code,
                timestamp: Date.now(),
            }
        }
    } catch (err) {
        scanner.playBeep(false)
        lastResult.value = {
            success: false,
            name: 'Scan Error',
            code: code,
            timestamp: Date.now(),
        }
    }
}

// Watch modal open/close state
watch(() => props.isOpen, async (open) => {
    if (open) {
        lastResult.value = null
        await nextTick()
        scanner.startStream()
    } else {
        scanner.stopStream()
    }
})

function closeModal() {
    scanner.stopStream()
    emit('close')
}

// Keyboard shortcuts inside overlay (Escape to close)
function handleKeydown(e) {
    if (!props.isOpen) return
    if (e.key === 'Escape') {
        e.preventDefault()
        closeModal()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    scanner.stopStream()
})
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[99999] bg-black flex flex-col justify-between overflow-hidden select-none"
            >
                <!-- ── TOP BAR CONTROLS ──────────────────────────────────────────────── -->
                <div class="relative z-20 flex items-center justify-between px-4 py-3 bg-gradient-to-b from-black/80 via-black/40 to-transparent">
                    <!-- Left: Close Button -->
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-2.5 rounded-full bg-white/20 hover:bg-white/30 text-white backdrop-blur-md transition flex items-center justify-center cursor-pointer shadow-lg active:scale-95"
                        title="Close camera scanner (Esc)"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Center: Scan Mode Toggle (Continuous vs Single) -->
                    <div class="flex items-center p-0.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-medium">
                        <button
                            type="button"
                            @click="scanMode = 'continuous'"
                            :class="[
                                'px-3 py-1 rounded-full transition cursor-pointer',
                                scanMode === 'continuous'
                                    ? 'bg-emerald-500 text-white font-bold shadow-sm'
                                    : 'text-white/80 hover:text-white'
                            ]"
                        >
                            Continuous
                        </button>
                        <button
                            type="button"
                            @click="scanMode = 'single'"
                            :class="[
                                'px-3 py-1 rounded-full transition cursor-pointer',
                                scanMode === 'single'
                                    ? 'bg-emerald-500 text-white font-bold shadow-sm'
                                    : 'text-white/80 hover:text-white'
                            ]"
                        >
                            Single Scan
                        </button>
                    </div>

                    <!-- Right Actions: Torch & Camera Switch -->
                    <div class="flex items-center gap-2">
                        <!-- Torch Button (Shown only when torch is supported by camera) -->
                        <button
                            v-if="scanner.hasTorch.value"
                            type="button"
                            @click="scanner.toggleTorch"
                            :class="[
                                'p-2.5 rounded-full backdrop-blur-md transition flex items-center justify-center cursor-pointer shadow-lg active:scale-95',
                                scanner.isTorchOn.value
                                    ? 'bg-amber-400 text-black shadow-amber-400/30'
                                    : 'bg-white/20 hover:bg-white/30 text-white'
                            ]"
                            title="Toggle Torch / Flashlight"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </button>

                        <!-- Switch Camera (Shown only if > 1 video inputs available) -->
                        <button
                            v-if="scanner.availableCameras.value.length > 1"
                            type="button"
                            @click="scanner.switchCamera"
                            class="p-2.5 rounded-full bg-white/20 hover:bg-white/30 text-white backdrop-blur-md transition flex items-center justify-center cursor-pointer shadow-lg active:scale-95"
                            title="Switch camera device"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ── VIEWPORT PREVIEW & SCANNING FRAME ─────────────────────────────── -->
                <div class="relative flex-1 flex items-center justify-center overflow-hidden">
                    <!-- Live HTML5 Video Element -->
                    <video
                        ref="scanner.videoRef"
                        class="absolute inset-0 w-full h-full object-cover"
                        playsinline
                        muted
                        autoplay
                    ></video>

                    <!-- Flash visual indicator on successful decode -->
                    <div
                        v-if="flashActive"
                        class="absolute inset-0 bg-white/30 backdrop-blur-xs transition-opacity duration-150 pointer-events-none z-10"
                    ></div>

                    <!-- Scan Guide Overlay -->
                    <div class="relative z-10 flex flex-col items-center">
                        <div
                            :class="[
                                'w-72 sm:w-80 h-44 sm:h-52 rounded-2xl border-2 transition-all relative shadow-2xl',
                                lastResult?.success === false
                                    ? 'border-red-500 ring-4 ring-red-500/20'
                                    : (lastResult?.success === true ? 'border-emerald-400 ring-4 ring-emerald-400/20' : 'border-white/80')
                            ]"
                        >
                            <!-- Corner Accents -->
                            <div class="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg"></div>
                            <div class="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg"></div>
                            <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg"></div>
                            <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-emerald-400 rounded-br-lg"></div>

                            <!-- Animated Red Laser Scan Line -->
                            <div class="absolute inset-x-2 top-0 h-0.5 bg-gradient-to-r from-transparent via-red-500 to-transparent shadow-[0_0_8px_#ef4444] animate-bounce"></div>
                        </div>

                        <p class="text-xs text-white/80 font-medium mt-4 px-3 py-1 rounded-full bg-black/40 backdrop-blur-md">
                            Align barcode inside the guide frame
                        </p>
                    </div>

                    <!-- Loading / Initialization Indicator -->
                    <div
                        v-if="scanner.isLoadingCamera.value"
                        class="absolute inset-0 bg-black/80 flex flex-col items-center justify-center text-white z-20 space-y-3"
                    >
                        <div class="w-10 h-10 border-4 border-white/20 border-t-emerald-400 rounded-full animate-spin"></div>
                        <p class="text-sm font-semibold tracking-wide">Starting Camera…</p>
                    </div>

                    <!-- Error & Permission Prompts (§6) -->
                    <div
                        v-if="scanner.errorState.value"
                        class="absolute inset-0 bg-black/90 flex flex-col items-center justify-center p-6 text-center text-white z-20 space-y-4 max-w-sm mx-auto"
                    >
                        <div class="w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold">
                                {{ scanner.errorState.value === 'denied' ? 'Camera Permission Required' : 'Camera Unavailable' }}
                            </h4>
                            <p class="text-xs text-slate-300 mt-1">
                                {{ scanner.errorMessage.value }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                            <button
                                type="button"
                                @click="scanner.startStream()"
                                class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition cursor-pointer"
                            >
                                Try Again
                            </button>
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-medium transition cursor-pointer"
                            >
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── BOTTOM STATUS BAR & LAST SCANNED STRIP ────────────────────────── -->
                <div class="relative z-20 px-4 py-3 bg-gradient-to-t from-black/90 via-black/60 to-transparent flex flex-col items-center gap-2">
                    <!-- Last Scanned Feedback Strip -->
                    <transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="transform translate-y-2 opacity-0"
                        enter-to-class="transform translate-y-0 opacity-100"
                    >
                        <div
                            v-if="lastResult"
                            :class="[
                                'w-full max-w-md px-4 py-2.5 rounded-xl flex items-center justify-between border backdrop-blur-md shadow-xl transition-all',
                                lastResult.success
                                    ? 'bg-emerald-950/80 border-emerald-500/60 text-emerald-200'
                                    : 'bg-rose-950/80 border-rose-500/60 text-rose-200'
                            ]"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-base flex-shrink-0">
                                    {{ lastResult.success ? '✅' : '⚠️' }}
                                </span>
                                <div class="truncate">
                                    <p class="text-xs font-bold truncate">
                                        {{ lastResult.name }}
                                    </p>
                                    <p class="text-[10px] opacity-75 font-mono">
                                        {{ lastResult.code }}
                                    </p>
                                </div>
                            </div>

                            <span
                                :class="[
                                    'text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md flex-shrink-0',
                                    lastResult.success ? 'bg-emerald-500/30 text-emerald-300' : 'bg-rose-500/30 text-rose-300'
                                ]"
                            >
                                {{ lastResult.success ? 'Added to Cart' : 'Not Found' }}
                            </span>
                        </div>
                    </transition>

                    <!-- Done Button -->
                    <button
                        type="button"
                        @click="closeModal"
                        class="w-full max-w-md py-3 rounded-xl bg-white hover:bg-slate-100 text-black font-bold text-sm tracking-wide transition shadow-lg active:scale-98 cursor-pointer flex items-center justify-center gap-2"
                    >
                        <span>Done Scanning</span>
                        <span class="text-xs text-slate-500 font-normal">(Return to POS)</span>
                    </button>
                </div>
            </div>
        </transition>
    </teleport>
</template>
