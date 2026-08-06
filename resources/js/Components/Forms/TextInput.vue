<script setup>
import { ref, computed } from 'vue'

const model = defineModel({
    type: null,
    required: true,
})

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    type:{
        type: String,
        default: 'text'
    },
    message: String,
    placeholder: String,
    required:{
        type: Boolean,
        default: false
    },
})

const showPassword = ref(false)
const inputType = computed(() => {
    if (props.type === 'password') {
        return showPassword.value ? 'text' : 'password'
    }
    return props.type
})
</script>

<template>
    <div class="mb-2">
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            {{ label }} <span v-if="required" class="text-red-500">*</span>
        </label>
        
        <div class="relative flex items-center">
            <input
                :type="inputType"
                v-model="model"
                :class="[
                    'w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white px-3.5 py-2.5 rounded-xl outline-none text-sm focus:border-indigo-500 transition pr-10',
                    {'!ring-red-500 border-red-500': message}
                ]"
                :placeholder="placeholder ?? label"
                :required="required"
                :name="name"
            />

            <!-- Eye toggle for password -->
            <button
                v-if="type === 'password'"
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition focus:outline-none"
                :title="showPassword ? 'Hide password' : 'Show password'"
            >
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.05 10.05 0 014.122-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/>
                </svg>
            </button>
        </div>

        <small class="error text-red-500 text-xs mt-1 block" v-if="message">{{ message }}</small>
    </div>
</template>
