import { onMounted, onUnmounted } from 'vue'
import axios from 'axios'

/**
 * Global Barcode Scanner Composable
 * Captures rapid hardware barcode scanner keypresses globally, even when 
 * the barcode search input is not focused, and automatically adds the item to cart.
 * 
 * @param {Object} options
 * @param {Function} options.onScan - Callback function(item) when item is found
 * @param {Function} options.getPurchaseType - Returns current purchaseType ('Consumer' | 'Wholesale')
 * @param {Function} options.isEnabled - Returns boolean indicating if scanner should accept input (e.g. !modalOpen)
 */
export function useBarcodeScanner({ onScan, getPurchaseType, isEnabled }) {
    let buffer = ''
    let lastKeyTime = 0
    let resetTimer = null

    // Play subtle Web Audio API confirmation beep on successful scan
    function playBeepSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)()
            const osc = ctx.createOscillator()
            const gain = ctx.createGain()
            osc.type = 'sine'
            osc.frequency.setValueAtTime(1400, ctx.currentTime)
            gain.gain.setValueAtTime(0.08, ctx.currentTime)
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08)
            osc.connect(gain)
            gain.connect(ctx.destination)
            osc.start()
            osc.stop(ctx.currentTime + 0.08)
        } catch (e) {
            // Audio Context not allowed or unsupported
        }
    }

    async function processBarcode(code) {
        const cleanCode = code.trim()
        if (!cleanCode || cleanCode.length < 2) return

        const purchaseType = getPurchaseType ? getPurchaseType() : 'Consumer'

        // If explicitly offline, immediately query local IndexedDB
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            try {
                const { getLocalItemByBarcode } = await import('@/Services/offlineDb')
                const matchedItem = await getLocalItemByBarcode(cleanCode, purchaseType)
                if (matchedItem) {
                    playBeepSound()
                    if (onScan) onScan(matchedItem)
                }
            } catch (e) {
                console.error('Offline barcode scan error:', e)
            }
            return
        }

        try {
            const res = await axios.get(route('pos.api.items.search'), {
                params: { q: cleanCode, purchase_type: purchaseType }
            })

            if (res.data && res.data.length > 0) {
                // Find exact barcode match first, or fallback to first search result
                const matchedItem = res.data.find(i => String(i.barcode_number).trim() === cleanCode) || res.data[0]
                if (matchedItem) {
                    playBeepSound()
                    if (onScan) onScan(matchedItem)
                }
            }
        } catch (err) {
            console.warn('Online barcode lookup failed, trying local store:', err)
            try {
                const { getLocalItemByBarcode } = await import('@/Services/offlineDb')
                const matchedItem = await getLocalItemByBarcode(cleanCode, purchaseType)
                if (matchedItem) {
                    playBeepSound()
                    if (onScan) onScan(matchedItem)
                }
            } catch (dbErr) {
                console.error('Local barcode scan fallback error:', dbErr)
            }
        }
    }

    function handleKeydown(e) {
        // If disabled (e.g. modal is open), do nothing
        if (isEnabled && !isEnabled()) return

        const target = e.target
        const isInputField = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)

        // Don't hijack if focused on search bar directly (search bar handles its own enter key)
        if (target && target.id === 'pos-search') return

        const now = Date.now()
        const timeDiff = now - lastKeyTime
        lastKeyTime = now

        // Clear buffer if pause between keypresses is > 100ms
        if (timeDiff > 100) {
            buffer = ''
        }

        clearTimeout(resetTimer)
        resetTimer = setTimeout(() => { buffer = '' }, 150)

        // Handle Enter keypress (end of barcode scan sequence)
        if (e.key === 'Enter') {
            if (buffer.length >= 2) {
                e.preventDefault()
                const scannedBarcode = buffer
                buffer = ''
                processBarcode(scannedBarcode)
            }
            return
        }

        // Collect single printable characters
        if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            // If focused in another input field (e.g. price edit, notes), only collect if typing speed is super fast (< 45ms)
            if (isInputField && timeDiff > 45 && buffer.length <= 1) {
                buffer = ''
                return
            }

            buffer += e.key
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown, true)
    })

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown, true)
        clearTimeout(resetTimer)
    })

    return { processBarcode }
}
