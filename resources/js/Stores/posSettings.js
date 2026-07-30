import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const usePosSettingsStore = defineStore('posSettings', () => {
    const settings = ref(null)
    const loaded   = ref(false)

    async function load() {
        if (loaded.value) return
        const res  = await axios.get('/pos/api/settings')
        settings.value = res.data
        loaded.value   = true
    }

    function set(data) {
        settings.value = data
        loaded.value   = true
    }

    return { settings, loaded, load, set }
})
