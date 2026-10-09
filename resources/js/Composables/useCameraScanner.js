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
    const isZoomSupported  = ref(false)
    const zoomLevel        = ref(1.0)
    const minZoomLevel     = ref(1.0)
    const maxZoomLevel     = ref(1.0)
    const isDigitalZoom    = ref(false)
    const availableCameras = ref([])
    const currentCameraId  = ref(null)

    let currentStream      = null
    let nativeDetector     = null
    let zxingReader        = null
    let zxingClasses       = null
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
            'code_93',
            'itf',
            'codabar',
            'qr_code',
            'data_matrix',
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
            const {
                BrowserMultiFormatReader,
                BarcodeFormat,
                DecodeHintType,
                HTMLCanvasElementLuminanceSource,
                HybridBinarizer,
                BinaryBitmap,
            } = await import('@zxing/library')

            const hints = new Map()
            const formats = [
                BarcodeFormat.CODE_128,
                BarcodeFormat.EAN_13,
                BarcodeFormat.EAN_8,
                BarcodeFormat.UPC_A,
                BarcodeFormat.UPC_E,
                BarcodeFormat.CODE_39,
                BarcodeFormat.CODE_93,
                BarcodeFormat.ITF,
                BarcodeFormat.CODABAR,
                BarcodeFormat.QR_CODE,
                BarcodeFormat.DATA_MATRIX,
            ]
            hints.set(DecodeHintType.POSSIBLE_FORMATS, formats)
            hints.set(DecodeHintType.TRY_HARDER, true)

            zxingReader = new BrowserMultiFormatReader(hints, 200)
            zxingClasses = {
                HTMLCanvasElementLuminanceSource,
                HybridBinarizer,
                BinaryBitmap,
            }

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

                // Zoom check
                if (capabilities.zoom) {
                    isZoomSupported.value = true
                    minZoomLevel.value = capabilities.zoom.min || 1.0
                    maxZoomLevel.value = capabilities.zoom.max || 1.0
                    zoomLevel.value = 1.0
                } else {
                    isZoomSupported.value = false
                    zoomLevel.value = 1.0
                }
                isDigitalZoom.value = false

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

    let offscreenCanvas = null
    let offscreenCtx = null
    let rotatedCanvas   = null
    let rotatedCtx      = null
    let fullCanvas      = null
    let fullCtx         = null

    function getCropCanvas(w, h) {
        if (!offscreenCanvas) {
            offscreenCanvas = document.createElement('canvas')
            offscreenCtx = offscreenCanvas.getContext('2d', { willReadFrequently: true })
        }
        if (offscreenCanvas.width !== w || offscreenCanvas.height !== h) {
            offscreenCanvas.width = w
            offscreenCanvas.height = h
        }
        return { canvas: offscreenCanvas, ctx: offscreenCtx }
    }

    function getRotatedCanvas(w, h) {
        if (!rotatedCanvas) {
            rotatedCanvas = document.createElement('canvas')
            rotatedCtx = rotatedCanvas.getContext('2d', { willReadFrequently: true })
        }
        if (rotatedCanvas.width !== w || rotatedCanvas.height !== h) {
            rotatedCanvas.width = w
            rotatedCanvas.height = h
        }
        return { canvas: rotatedCanvas, ctx: rotatedCtx }
    }

    function getFullCanvas(w, h) {
        if (!fullCanvas) {
            fullCanvas = document.createElement('canvas')
            fullCtx = fullCanvas.getContext('2d', { willReadFrequently: true })
        }
        if (fullCanvas.width !== w || fullCanvas.height !== h) {
            fullCanvas.width = w
            fullCanvas.height = h
        }
        return { canvas: fullCanvas, ctx: fullCtx }
    }

    function decodeCanvasWithZxing(targetCanvas, doInvert = false) {
        if (!zxingReader || !zxingClasses) return null
        try {
            const luminanceSource = new zxingClasses.HTMLCanvasElementLuminanceSource(targetCanvas, doInvert)
            const hybridBinarizer = new zxingClasses.HybridBinarizer(luminanceSource)
            const binaryBitmap = new zxingClasses.BinaryBitmap(hybridBinarizer)
            const result = zxingReader.decodeBitmap(binaryBitmap)
            return result ? result.getText() : null
        } catch (e) {
            return null
        }
    }

    // Decode loop throttled at ~12-15 fps to conserve battery & keep mobile responsive
    function startDecodingLoop() {
        let isDecoding = false
        let scanTick = 0

        decodeIntervalId = setInterval(async () => {
            if (!isScanning.value || isDecoding || !videoRef.value || videoRef.value.readyState < 2) {
                return
            }

            const video = videoRef.value
            const vw = video.videoWidth
            const vh = video.videoHeight
            if (!vw || !vh) return

            isDecoding = true
            scanTick++

            try {
                let rawCode = null

                // Compute Region of Interest (ROI) matching guide overlay
                const isZoomed = isDigitalZoom.value || zoomLevel.value > 1.2
                let cropW, cropH
                if (vw >= vh) {
                    // Landscape camera feed (common on mobile back cameras)
                    cropW = Math.round(vh * (isZoomed ? 0.65 : 0.85))
                    cropH = Math.round(vh * (isZoomed ? 0.35 : 0.48))
                } else {
                    // Portrait camera feed
                    cropW = Math.round(vw * (isZoomed ? 0.70 : 0.85))
                    cropH = Math.round(vh * (isZoomed ? 0.25 : 0.38))
                }
                cropW = Math.min(vw, Math.max(280, cropW))
                cropH = Math.min(vh, Math.max(160, cropH))
                const cropX = Math.max(0, Math.round((vw - cropW) / 2))
                const cropY = Math.max(0, Math.round((vh - cropH) / 2))

                const { canvas, ctx } = getCropCanvas(cropW, cropH)
                ctx.drawImage(video, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH)

                if (nativeDetector) {
                    // Hardware accelerated BarcodeDetector (Android Chrome)
                    try {
                        const barcodes = await nativeDetector.detect(canvas)
                        if (barcodes && barcodes.length > 0) {
                            rawCode = barcodes[0].rawValue
                        }
                    } catch (cropErr) {}

                    // Fallback to full frame every 4th tick
                    if (!rawCode && scanTick % 4 === 0) {
                        try {
                            const fullBarcodes = await nativeDetector.detect(video)
                            if (fullBarcodes && fullBarcodes.length > 0) {
                                rawCode = fullBarcodes[0].rawValue
                            }
                        } catch (fullErr) {}
                    }
                } else if (zxingReader) {
                    // Optimized ZXing Engine for WebKit / iOS Safari & Chrome
                    // 1. Primary pass: Horizontal scan (standard black-on-white retail barcodes)
                    rawCode = decodeCanvasWithZxing(canvas, false)

                    // 2. Multi-orientation pass: Rotate 90° on alternating ticks (decodes vertical barcodes)
                    if (!rawCode && (scanTick % 2 === 0)) {
                        const { canvas: rCanvas, ctx: rCtx } = getRotatedCanvas(cropH, cropW)
                        rCtx.save()
                        rCtx.translate(cropH / 2, cropW / 2)
                        rCtx.rotate(Math.PI / 2)
                        rCtx.drawImage(canvas, -cropW / 2, -cropH / 2)
                        rCtx.restore()
                        rawCode = decodeCanvasWithZxing(rCanvas, false)
                    }

                    // 3. Wide fallback: Scaled full frame every 4th tick (if held near frame edges)
                    if (!rawCode && (scanTick % 4 === 0)) {
                        const scale = Math.min(1, 640 / Math.max(vw, vh))
                        const fw = Math.round(vw * scale)
                        const fh = Math.round(vh * scale)
                        const { canvas: fCanvas, ctx: fCtx } = getFullCanvas(fw, fh)
                        fCtx.drawImage(video, 0, 0, fw, fh)
                        rawCode = decodeCanvasWithZxing(fCanvas, false)
                    }

                    // 4. Inverted barcode fallback: Check white-on-black every 8th tick
                    if (!rawCode && (scanTick % 8 === 0)) {
                        rawCode = decodeCanvasWithZxing(canvas, true)
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
        }, 90)
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

    // Toggle 1x / 2x Zoom (hardware lens zoom or smooth digital zoom fallback)
    async function toggleZoom() {
        if (isZoomSupported.value && currentStream) {
            const videoTrack = currentStream.getVideoTracks()[0]
            if (videoTrack) {
                try {
                    const nextZoom = zoomLevel.value >= 1.8 ? minZoomLevel.value : Math.min(2.0, maxZoomLevel.value)
                    await videoTrack.applyConstraints({
                        advanced: [{ zoom: nextZoom }],
                    })
                    zoomLevel.value = nextZoom
                    isDigitalZoom.value = false
                    return
                } catch (e) {
                    console.warn('Hardware zoom failed, falling back to digital zoom:', e)
                }
            }
        }

        // Digital zoom fallback (magnify preview and crop tighter)
        isDigitalZoom.value = !isDigitalZoom.value
        zoomLevel.value = isDigitalZoom.value ? 1.6 : 1.0
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
        isDigitalZoom.value = false
        zoomLevel.value = 1.0
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
        isZoomSupported,
        zoomLevel,
        isDigitalZoom,
        toggleZoom,
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
