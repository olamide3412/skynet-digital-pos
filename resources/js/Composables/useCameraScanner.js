import { ref, shallowRef, onUnmounted } from 'vue'

// Cooldown to prevent duplicate reads of the exact same barcode across continuous frames
export const DUPLICATE_COOLDOWN_MS = 1500

/**
 * Camera Barcode Scanner Composable
 * Handles camera stream acquisition, hardware capabilities (torch, switch cameras),
 * native BarcodeDetector with @zxing/library fallback, and duplicate cooldown.
 */
export function useCameraScanner({ onBarcodeDetected, videoRef: externalVideoRef } = {}) {
    const isSupported = typeof window !== 'undefined' &&
        Boolean(window.isSecureContext) &&
        Boolean(navigator?.mediaDevices?.getUserMedia)

    const videoRef         = externalVideoRef || ref(null)
    const isScanning       = ref(false)
    const isLoadingCamera  = ref(false)
    const errorState       = ref(null) // 'denied' | 'not_found' | 'busy' | 'unsupported' | 'stream_error'
    const errorMessage     = ref('')
    const isTorchOn        = ref(false)
    const hasTorch         = ref(false)
    const availableCameras = ref([])
    const currentCameraId  = ref(null)

    let currentStream      = null
    let nativeDetector     = null
    let zxingReader        = null
    let decodeAnimationFrame = null
    let decodeIntervalId   = null
    let wakeLockSentinel   = null

    let lastCode           = ''
    let lastCodeTime       = 0

    // Audio Feedback using Web Audio API
    function playBeep(success = true) {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext
            if (!AudioCtx) return
            const ctx = new AudioCtx()
            const osc = ctx.createOscillator()
            const gain = ctx.createGain()

            if (success) {
                // High confirmation beep
                osc.type = 'sine'
                osc.frequency.setValueAtTime(1750, ctx.currentTime)
                gain.gain.setValueAtTime(0.12, ctx.currentTime)
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.09)
                osc.connect(gain)
                gain.connect(ctx.destination)
                osc.start()
                osc.stop(ctx.currentTime + 0.09)
            } else {
                // Lower error / not found tone
                osc.type = 'triangle'
                osc.frequency.setValueAtTime(320, ctx.currentTime)
                gain.gain.setValueAtTime(0.15, ctx.currentTime)
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22)
                osc.connect(gain)
                gain.connect(ctx.destination)
                osc.start()
                osc.stop(ctx.currentTime + 0.22)
            }
        } catch (e) {
            // Audio context not allowed or failed silently
        }
    }

    function triggerVibration() {
        try {
            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate(80)
            }
        } catch (e) {
            // Ignore vibration errors on iOS Safari
        }
    }

    // Acquire and initialize available cameras list
    async function refreshCameraDevices() {
        if (!navigator?.mediaDevices?.enumerateDevices) return
        try {
            const devices = await navigator.mediaDevices.enumerateDevices()
            const videoDevices = devices.filter(d => d.kind === 'videoinput')
            availableCameras.value = videoDevices.map((d, index) => ({
                deviceId: d.deviceId,
                label: d.label || `Camera ${index + 1}`,
            }))
        } catch (e) {
            // Non-critical: device enumeration may require active stream first
        }
    }

    // Initialize native or fallback decoder
    async function initDecoder() {
        const supportedFormats = [
            'code_128',
            'ean_13',
            'ean_8',
            'upc_a',
            'upc_e',
            'code_39',
        ]

        if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
            try {
                const supportedByBrowser = await window.BarcodeDetector.getSupportedFormats?.()
                const validFormats = supportedFormats.filter(f => supportedByBrowser?.includes(f))
                if (validFormats.length > 0) {
                    nativeDetector = new window.BarcodeDetector({ formats: validFormats })
                    return 'native'
                }
            } catch (e) {
                console.warn('Native BarcodeDetector initialization fallback to ZXing:', e)
            }
        }

        // Fallback: Lazy load ZXing library
        try {
            const { BrowserMultiFormatReader, BarcodeFormat, DecodeHintType } = await import('@zxing/library')
            const hints = new Map()
            const formats = [
                BarcodeFormat.CODE_128,
                BarcodeFormat.EAN_13,
                BarcodeFormat.EAN_8,
                BarcodeFormat.UPC_A,
                BarcodeFormat.UPC_E,
                BarcodeFormat.CODE_39,
            ]
            hints.set(DecodeHintType.POSSIBLE_FORMATS, formats)
            hints.set(DecodeHintType.TRY_HARDER, true)

            zxingReader = new BrowserMultiFormatReader(hints, 250)
            return 'zxing'
        } catch (err) {
            console.error('Failed to load ZXing decoder:', err)
            throw new Error('No barcode decoder available on this browser.')
        }
    }

    // Start video stream
    async function startStream(preferredDeviceId = null) {
        if (!isSupported) {
            errorState.value = 'unsupported'
            errorMessage.value = 'Camera access requires a secure context (HTTPS) and a supported browser.'
            return false
        }

        stopStream()
        isLoadingCamera.value = true
        errorState.value = null
        errorMessage.value = ''

        try {
            // Restore last camera preference if device exists
            const savedDeviceId = preferredDeviceId || currentCameraId.value || localStorage.getItem('pos_last_camera_id')

            const constraints = {
                video: {
                    facingMode: savedDeviceId ? undefined : { ideal: 'environment' },
                    deviceId: savedDeviceId ? { exact: savedDeviceId } : undefined,
                    width:  { ideal: 1280, max: 1920 },
                    height: { ideal: 720,  max: 1080 },
                    frameRate: { ideal: 30, max: 30 },
                },
                audio: false,
            }

            try {
                currentStream = await navigator.mediaDevices.getUserMedia(constraints)
            } catch (firstErr) {
                // If exact device failed, fallback to generic environment
                if (savedDeviceId) {
                    currentStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false,
                    })
                } else {
                    throw firstErr
                }
            }

            if (!videoRef.value) {
                // Brief pause if modal transition/teleport is still rendering into DOM
                await new Promise(resolve => setTimeout(resolve, 100))
            }

            if (!videoRef.value) {
                throw new Error('Video preview element not mounted.')
            }

            videoRef.value.srcObject = currentStream
            await videoRef.value.play()

            // Track active camera ID and torch capabilities
            const videoTrack = currentStream.getVideoTracks()[0]
            if (videoTrack) {
                const settings = videoTrack.getSettings?.() || {}
                currentCameraId.value = settings.deviceId || null
                if (currentCameraId.value) {
                    localStorage.setItem('pos_last_camera_id', currentCameraId.value)
                }

                // Torch check
                const capabilities = videoTrack.getCapabilities?.() || {}
                hasTorch.value = Boolean(capabilities.torch)
                isTorchOn.value = false

                // Try applying continuous autofocus if supported
                if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                    try {
                        await videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] })
                    } catch (e) {}
                }
            }

            await refreshCameraDevices()
            await initDecoder()

            // Request screen wake lock so display does not turn off while scanning
            requestWakeLock()

            // Begin decoding loop
            isScanning.value = true
            isLoadingCamera.value = false
            startDecodingLoop()
            return true
        } catch (err) {
            isLoadingCamera.value = false
            isScanning.value = false
            stopStream()

            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                errorState.value = 'denied'
                errorMessage.value = 'Camera permission was denied. Please allow camera access in your browser site settings.'
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                errorState.value = 'not_found'
                errorMessage.value = 'No camera device found on this system.'
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                errorState.value = 'busy'
                errorMessage.value = 'Camera is currently in use by another application or tab.'
            } else {
                errorState.value = 'stream_error'
                errorMessage.value = err.message || 'Unable to access camera.'
            }
            return false
        }
    }

    // Decode loop throttled at ~15-20 fps to conserve battery & keep mobile responsive
    function startDecodingLoop() {
        let isDecoding = false

        decodeIntervalId = setInterval(async () => {
            if (!isScanning.value || isDecoding || !videoRef.value || videoRef.value.readyState < 2) {
                return
            }

            isDecoding = true
            try {
                let rawCode = null

                if (nativeDetector) {
                    const barcodes = await nativeDetector.detect(videoRef.value)
                    if (barcodes && barcodes.length > 0) {
                        rawCode = barcodes[0].rawValue
                    }
                } else if (zxingReader) {
                    try {
                        const result = await zxingReader.decodeOnce(videoRef.value)
                        if (result) {
                            rawCode = result.getText()
                        }
                    } catch (zxErr) {
                        // ZXing throws NotFoundException when frame has no barcode, which is expected
                    }
                }

                if (rawCode) {
                    handleDecodedValue(rawCode)
                }
            } catch (e) {
                // Non-critical frame decode failure
            } finally {
                isDecoding = false
            }
        }, 80)
    }

    function handleDecodedValue(code) {
        const clean = String(code || '').trim()
        if (!clean) return

        const now = Date.now()
        // If same code is read within cooldown period, ignore
        if (clean === lastCode && (now - lastCodeTime) < DUPLICATE_COOLDOWN_MS) {
            return
        }

        lastCode = clean
        lastCodeTime = now

        if (onBarcodeDetected) {
            onBarcodeDetected(clean)
        }
    }

    // Toggle Torch / Flashlight
    async function toggleTorch() {
        if (!hasTorch.value || !currentStream) return
        const videoTrack = currentStream.getVideoTracks()[0]
        if (!videoTrack) return

        try {
            const nextState = !isTorchOn.value
            await videoTrack.applyConstraints({
                advanced: [{ torch: nextState }],
            })
            isTorchOn.value = nextState
        } catch (e) {
            console.warn('Torch toggle failed:', e)
        }
    }

    // Switch between front/back or multiple cameras
    async function switchCamera() {
        if (availableCameras.value.length < 2) return
        const currentIndex = availableCameras.value.findIndex(c => c.deviceId === currentCameraId.value)
        const nextIndex = (currentIndex + 1) % availableCameras.value.length
        const nextDevice = availableCameras.value[nextIndex]
        if (nextDevice) {
            await startStream(nextDevice.deviceId)
        }
    }

    // Screen Wake Lock API
    async function requestWakeLock() {
        if (typeof navigator !== 'undefined' && navigator.wakeLock) {
            try {
                wakeLockSentinel = await navigator.wakeLock.request('screen')
            } catch (e) {}
        }
    }

    function releaseWakeLock() {
        if (wakeLockSentinel) {
            try {
                wakeLockSentinel.release()
            } catch (e) {}
            wakeLockSentinel = null
        }
    }

    // Full camera stop & track release
    function stopStream() {
        isScanning.value = false
        if (decodeIntervalId) {
            clearInterval(decodeIntervalId)
            decodeIntervalId = null
        }
        if (decodeAnimationFrame) {
            cancelAnimationFrame(decodeAnimationFrame)
            decodeAnimationFrame = null
        }

        if (zxingReader) {
            try {
                zxingReader.reset()
            } catch (e) {}
        }

        if (currentStream) {
            currentStream.getTracks().forEach(track => {
                try {
                    track.stop()
                } catch (e) {}
            })
            currentStream = null
        }

        if (videoRef.value) {
            videoRef.value.srcObject = null
        }

        isTorchOn.value = false
        hasTorch.value = false
        releaseWakeLock()
    }

    // Stop camera when user switches app/tab (lifecycle requirement §7.5)
    function handleVisibilityChange() {
        if (document.hidden && isScanning.value) {
            stopStream()
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibilityChange)
    }

    onUnmounted(() => {
        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', handleVisibilityChange)
        }
        stopStream()
    })

    return {
        isSupported,
        videoRef,
        isScanning,
        isLoadingCamera,
        errorState,
        errorMessage,
        isTorchOn,
        hasTorch,
        availableCameras,
        currentCameraId,
        startStream,
        stopStream,
        toggleTorch,
        switchCamera,
        playBeep,
        triggerVibration,
    }
}
