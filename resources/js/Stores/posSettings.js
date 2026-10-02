import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const usePosSettingsStore = defineStore('posSettings', () => {
    const settings = ref(null)
    const loaded   = ref(false)

    function getBranchSlug() {
        if (typeof window === 'undefined') return ''
        const seg = window.location.pathname.split('/').filter(Boolean)[0]
        const reserved = ['superadmin', 'org', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'login', 'logout', 'register', 'password']
        if (seg && !reserved.includes(seg)) return seg
        return window.Ziggy?.defaults?.branch || ''
    }

    async function load() {
        if (loaded.value) return
        const branch = getBranchSlug()
        const res  = await axios.get(route('pos.api.settings.show', branch ? { branch } : undefined))
        settings.value = res.data
        loaded.value   = true
    }

    function set(data) {
        settings.value = data
        loaded.value   = true
    }

    return { settings, loaded, load, set }
})
