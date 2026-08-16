import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const LOCAL_STORAGE_KEY = 'pos_till_printer_config'

export function usePrinterSetting() {
    const page = usePage()

    // Local device till override (stored per computer / browser session)
    const localOverride = ref(loadLocalOverride())

    function loadLocalOverride() {
        if (typeof window === 'undefined') return null
        try {
            const raw = localStorage.getItem(LOCAL_STORAGE_KEY)
            return raw ? JSON.parse(raw) : null
        } catch (e) {
            console.warn('Failed to parse local till printer override', e)
            return null
        }
    }

    function saveLocalOverride(config) {
        localOverride.value = config
        if (typeof window !== 'undefined') {
            try {
                if (config) {
                    localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(config))
                } else {
                    localStorage.removeItem(LOCAL_STORAGE_KEY)
                }
            } catch (e) {
                console.warn('Failed to save local till printer override', e)
            }
        }
    }

    function clearLocalOverride() {
        saveLocalOverride(null)
    }

    /**
     * Resolves the effective printer configuration for the current till/modal.
     * Hierarchy:
     * 1. Local Device Till Override (if enabled)
     * 2. Passed Settings Prop / Current Branch POS Settings
     * 3. System Defaults (80mm Thermal, Kiosk Direct)
     */
    function resolvePrinter(passedSettings = {}) {
        const branchSettings = passedSettings || {}
        const branchDefaults = {
            printer_name:       branchSettings.receipt_printer_name || page.props.current_branch?.receipt_printer_name || 'Default POS Printer',
            printer_type:       branchSettings.printer_type || 'thermal_80mm',
            paper_size:         (branchSettings.receipt_paper_size || '80mm').toLowerCase() === 'a4' ? 'A4' : '80mm',
            printer_connection: branchSettings.printer_connection || 'kiosk_direct',
            printer_ip_address: branchSettings.printer_ip_address || '',
            receipt_copies:     parseInt(branchSettings.receipt_copies) || 1,
        }

        if (localOverride.value && localOverride.value.is_active) {
            return {
                ...branchDefaults,
                ...localOverride.value,
                receipt_copies: parseInt(localOverride.value.receipt_copies || localOverride.value.print_copies) || branchDefaults.receipt_copies || 1,
                is_override: true,
            }
        }

        return {
            ...branchDefaults,
            is_override: false,
        }
    }

    return {
        localOverride,
        saveLocalOverride,
        clearLocalOverride,
        resolvePrinter,
    }
}
